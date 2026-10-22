<?php

namespace Tests\Unit\Monitoring;

use App\Models\Monitoring\MpIdentifier;
use App\Models\Monitoring\MpProduct;
use App\Services\Monitoring\Extract\ListingIdentityVerifier;
use App\Services\Monitoring\Extract\LlmPriceExtractor;
use App\Support\Monitoring\ArticleNormalizer;
use App\Support\Monitoring\KeyAttributes;
use Tests\TestCase;

class KeyAttributesTest extends TestCase
{
    private function sideAttrs(): array
    {
        return KeyAttributes::normalize([
            ['key' => 'сторона', 'value' => 'правая', 'kind' => 'side',
                'match' => ['правый', 'прав.', 'right', 'RH', 'R', 'pravaya', 'pravaja'],
                'conflict' => ['левая', 'левый', 'лев.', 'left', 'LH', 'levaya', 'levaja']],
            ['key' => 'бренд', 'value' => 'OTIS', 'kind' => 'brand', 'match' => [], 'conflict' => []],
        ]);
    }

    private function product(array $attrs): MpProduct
    {
        $p = new MpProduct(['name' => 'Отводка Правая для дверей Techna / DO2000 лифта OTIS L=510мм', 'brand' => 'OTIS', 'key_attrs' => $attrs]);
        $p->setRelation('identifiers', collect([new MpIdentifier(['kind' => 'oem', 'value' => 'FEA24390C1', 'value_norm' => ArticleNormalizer::normalize('FEA24390C1')])]));

        return $p;
    }

    public function test_normalize_drops_single_letter_terms_and_empty_attrs(): void
    {
        $a = $this->sideAttrs();
        $this->assertCount(2, $a);
        $this->assertNotContains('r', $a[0]['match']);
        $this->assertContains('правая', $a[0]['match']);
        $this->assertSame([], KeyAttributes::normalize([['key' => '', 'value' => 'x'], 'junk']));
    }

    public function test_conflict_found_by_inflected_form_and_translit(): void
    {
        $a = $this->sideAttrs();
        $this->assertSame('сторона: левая', KeyAttributes::conflictIn($a, 'Отводка привода ДК, OTIS, L - 510 мм, механическая, левой стороны'));
        $this->assertNotNull(KeyAttributes::conflictIn($a, 'product otvodka levaja dlja dverej techna'));
        $this->assertNull(KeyAttributes::conflictIn($a, 'Отводка правая Techna 510 мм'));
        // Оба варианта на странице («левая/правая») — не противоречие.
        $this->assertNull(KeyAttributes::conflictIn($a, 'Отводка левая/правая Techna'));
        // «Левашов» не путаем с «лев.»: сравниваем словами.
        $this->assertNull(KeyAttributes::conflictIn($a, 'Склад на ул. Левашовской'));
    }

    public function test_verifier_rejects_side_conflict_even_with_article_on_page(): void
    {
        $v = new ListingIdentityVerifier();
        $p = $this->product($this->sideAttrs());
        $llm = ['is_same_product' => true, 'is_analog' => false, 'attrs' => [],
            'evidence_identity' => 'Отводка привода ДК, OTIS, L - 510 мм, механическая, левая Доп. артикул: FEA24390C1', 'evidence_price' => '9 847 ₽'];
        $r = $v->verify($p, 'Отводка привода ДК, OTIS, L - 510 мм, механическая, левая Доп. артикул: FEA24390C1 9 847 ₽', null, $llm, 'otvodka sistemy dverej otis');
        $this->assertFalse($r['accepted']);
        $this->assertSame('attr_conflict', $r['reason']);
    }

    public function test_verifier_rejects_llm_reported_brand_conflict_and_ignores_absent(): void
    {
        $v = new ListingIdentityVerifier();
        $p = $this->product($this->sideAttrs());
        $page = 'Отводка дверей кабины лифта 90.16.00 (правая) Подходит для: BLT, Fermator 22 337 р.';
        $conflict = ['is_same_product' => true, 'is_analog' => false, 'evidence_identity' => 'Отводка дверей кабины лифта 90.16.00 (правая)',
            'attrs' => LlmPriceExtractor::attrVerdicts([['key' => 'бренд', 'page' => 'Подходит для: BLT, Fermator', 'verdict' => 'conflict']])];
        $r = $v->verify($p, $page, null, $conflict, 'otvodka dverei kabiny lifta 90 16 00 pravaia');
        $this->assertFalse($r['accepted']);
        $this->assertSame('attr_conflict', $r['reason']);

        // Бренд не указан на странице — не повод отклонять.
        $absent = ['is_same_product' => true, 'is_analog' => false, 'evidence_identity' => 'Отводка правая Techna DO2000 L=510мм',
            'attrs' => LlmPriceExtractor::attrVerdicts([['key' => 'бренд', 'page' => '', 'verdict' => 'absent'], ['key' => 'сторона', 'page' => 'правая', 'verdict' => 'match']])];
        $ok = $v->verify($p, 'Отводка правая Techna DO2000 L=510мм 34 707 ₽', null, $absent, 'otvodka pravaja techna');
        $this->assertTrue($ok['accepted']);
        $this->assertSame('llm_verified', $ok['method']);
    }

    public function test_llm_conflict_with_our_own_value_is_ignored(): void
    {
        $v = new ListingIdentityVerifier();
        $p = $this->product($this->sideAttrs());
        $llm = ['is_same_product' => true, 'is_analog' => false, 'evidence_identity' => 'Отводка привода ДК, OTIS, TECHNA/DO2000, L - 510 мм, правая',
            'attrs' => LlmPriceExtractor::attrVerdicts([['key' => 'сторона', 'page' => 'правый', 'verdict' => 'conflict']])];
        $r = $v->verify($p, 'Отводка привода ДК, OTIS, TECHNA/DO2000, L - 510 мм, правая 9 738₽', null, $llm, 'otvodka sistemy dverej otis');
        $this->assertTrue($r['accepted']);
        $this->assertTrue(KeyAttributes::sameValue($this->sideAttrs(), 'Сторона', 'правый'));
        $this->assertFalse(KeyAttributes::sameValue($this->sideAttrs(), 'сторона', 'левая'));
    }

    public function test_attr_verdicts_skip_garbage(): void
    {
        $this->assertSame([], LlmPriceExtractor::attrVerdicts('x'));
        $this->assertCount(1, LlmPriceExtractor::attrVerdicts([['key' => 'a', 'verdict' => 'maybe'], ['key' => 'b', 'verdict' => 'match']]));
    }
}
