<?php

namespace Tests\Unit\Monitoring;

use App\Models\Monitoring\MpIdentifier;
use App\Models\Monitoring\MpProduct;
use App\Services\Monitoring\Extract\ListingIdentityVerifier;
use App\Support\Monitoring\ArticleNormalizer;
use App\Support\Monitoring\KeyAttributes;
use Tests\TestCase;

/** Код на странице против вердикта ИИ (решение владельца 23.11.2026). */
class CodeVsLlmRulesTest extends TestCase
{
    private function product(string $name, string $code, array $attrs = []): MpProduct
    {
        $p = new MpProduct(['name' => $name, 'brand' => '', 'key_attrs' => KeyAttributes::normalize($attrs)]);
        $p->setRelation('identifiers', collect([new MpIdentifier(['kind' => 'oem', 'value' => $code, 'value_norm' => ArticleNormalizer::normalize($code)])]));

        return $p;
    }

    public function test_brand_conflict_with_code_on_page_is_rejected(): void
    {
        $p = $this->product('Плата кабины LEX-C лифта ELEX', 'LEX-C',
            [['key' => 'бренд', 'value' => 'ELEX', 'kind' => 'brand', 'match' => [], 'conflict' => []]]);
        $llm = ['is_same_product' => false, 'is_analog' => false, 'evidence_identity' => 'Интерактивный робот-игрушка emotoBOT LEX-C',
            'attrs' => [['key' => 'бренд', 'verdict' => 'conflict', 'page' => 'Just Play']]];
        $r = (new ListingIdentityVerifier())->verify($p, 'Интерактивный робот-игрушка emotoBOT LEX-C 4 870 ₽', ['sku_matched' => true], $llm,
            'emotobot lex c interactive robot');

        $this->assertFalse($r['accepted']);
        $this->assertSame('attr_conflict', $r['reason']);
    }

    public function test_code_in_title_beats_llm_without_conflicts(): void
    {
        $p = $this->product('Амортизатор купе кабины Otis', 'TAA316BJN3');
        $llm = ['is_same_product' => false, 'is_analog' => false, 'evidence_identity' => 'Амортизатор купе кабины TAA316BJN3 Otis', 'attrs' => []];
        $r = (new ListingIdentityVerifier())->verify($p, 'Амортизатор купе кабины TAA316BJN3 Otis 1 250 ₽', null, $llm,
            'amortizator kupe kabiny Амортизатор купе кабины TAA316BJN3 Otis');

        $this->assertTrue($r['accepted']);
        $this->assertSame('llm≠code', $r['extra_note']);
    }

    public function test_code_only_in_body_or_short_numeric_follows_llm(): void
    {
        $v = new ListingIdentityVerifier();
        $llm = ['is_same_product' => false, 'is_analog' => false, 'evidence_identity' => 'Тормозные колодки FOMAR', 'attrs' => []];

        // Короткий цифровой код в заголовке не доказывает товар.
        $p = $this->product('Крепеж паллеты траволатора Schindler 9500', '468881');
        $r = $v->verify($p, 'Тормозные колодки дисковые FOMAR FO 468881 PRO 6 804 ₽', ['sku_matched' => true], $llm,
            'tormoznye kolodki Тормозные колодки дисковые FOMAR FO 468881 PRO');
        $this->assertSame('llm_other_product', $r['reason']);

        // Код только в описании (заголовок — другой товар).
        $p2 = $this->product('Программатор УСНА-2.0.7-421-У', 'ЕМРЦ.421243.201-07');
        $r2 = $v->verify($p2, 'Блок БУАД 4-25.8 ... совместим с программатором ЕМРЦ.421243.201-07 59 020 ₽', null, $llm,
            'ustroystvo upravleniya dveryami Устройство управления автоматическими дверьми БУАД');
        $this->assertSame('llm_other_product', $r2['reason']);
    }

    public function test_invented_brand_does_not_judge_page(): void
    {
        // Qwen приписал «Блоку контроля фаз (БКФ)» бренд «Электро-Импульс» — в наименовании его нет.
        $attrs = KeyAttributes::normalize([['key' => 'бренд', 'value' => 'Электро-Импульс', 'kind' => 'brand',
            'match' => ['электро-импульс', 'эи'], 'conflict' => []]]);
        $this->assertSame([], KeyAttributes::grounded($attrs, 'Блок контроля фаз (БКФ)'));

        $p = $this->product('Блок контроля фаз (БКФ)', 'БКФ-0.5', [['key' => 'бренд', 'value' => 'Электро-Импульс', 'kind' => 'brand',
            'match' => ['электро-импульс'], 'conflict' => []]]);
        $llm = ['is_same_product' => true, 'is_analog' => false, 'evidence_identity' => 'Блок контроля фаз БКФ-0.5 ЩЛЗ',
            'attrs' => [['key' => 'бренд', 'verdict' => 'conflict', 'page' => 'Производитель: ЩЛЗ']]];
        $r = (new ListingIdentityVerifier())->verify($p, 'Блок контроля фаз БКФ-0.5 ЩЛЗ 2 350 ₽', null, $llm, 'blok kontrolya faz');
        $this->assertTrue($r['accepted']);
        $this->assertNull($r['attr_note']);
    }

    public function test_rounding_is_not_a_conflict(): void
    {
        $attrs = KeyAttributes::normalize([
            ['key' => 'диаметр', 'value' => '39,5 мм', 'kind' => 'size', 'match' => ['d39,5'], 'conflict' => []],
            ['key' => 'напряжение', 'value' => '220ВAC', 'kind' => 'electrical', 'match' => ['220в'], 'conflict' => []],
            ['key' => 'ширина паза', 'value' => '17 мм', 'kind' => 'size', 'match' => ['паз 17'], 'conflict' => []],
        ]);
        $this->assertTrue(KeyAttributes::numericClose($attrs, 'диаметр', 'D=40мм'));
        $this->assertTrue(KeyAttributes::numericClose($attrs, 'напряжение', '230В'));
        $this->assertFalse(KeyAttributes::numericClose($attrs, 'напряжение', '110V'));
        $this->assertFalse(KeyAttributes::numericClose($attrs, 'ширина паза', 'P-16'));
    }

    public function test_numeric_code_in_url_does_not_beat_llm(): void
    {
        // M27057: артикул ThyssenKrupp «10082665» = номер товара «Трусы мужские» в адресе sima-land.ru.
        $p = $this->product('Кнопка СТРЕЛКА, круглая, шлиф, белая подсветка лифта Thyssen', '10082665');
        $llm = ['is_same_product' => false, 'is_analog' => false, 'evidence_identity' => 'Трусы мужские, размер 50, цвет зелёный', 'attrs' => []];
        $r = (new ListingIdentityVerifier())->verify($p, 'Трусы мужские размер 50 цвет зелёный 779 ₽ Артикул 10082665', null, $llm,
            '10082665 trusy muzhskie razmer 50 cvet zelenyy Трусы мужские');

        $this->assertFalse($r['accepted']);
        $this->assertSame('llm_other_product', $r['reason']);
        $this->assertFalse(ListingIdentityVerifier::strongCodeIn(['10082665'], '10082665 trusy muzhskie'));
        $this->assertTrue(ListingIdentityVerifier::strongCodeIn(['TAA316BJN3'], 'Амортизатор TAA316BJN3 Otis'));
    }

    public function test_soft_attr_keys(): void
    {
        $this->assertTrue(ListingIdentityVerifier::softAttr('сторона: левая'));
        $this->assertTrue(ListingIdentityVerifier::softAttr('Исполнение: правое'));
        $this->assertFalse(ListingIdentityVerifier::softAttr('бренд: Just Play'));
        $this->assertFalse(ListingIdentityVerifier::softAttr('длина: 298 мм'));
    }
}
