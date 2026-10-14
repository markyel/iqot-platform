<?php

namespace Tests\Unit\Monitoring;

use App\Models\Monitoring\MpIdentifier;
use App\Models\Monitoring\MpProduct;
use App\Services\Monitoring\Extract\ListingIdentityVerifier;
use App\Services\Monitoring\LlmCostCalculator;
use App\Services\Monitoring\ProductIdentityResolver;
use App\Support\Monitoring\HtmlText;
use Tests\TestCase;

class IdentityAndCostTest extends TestCase
{
    private function product(string $name, array $codes, ?string $brand = null): MpProduct
    {
        $p = new MpProduct(['name' => $name, 'brand' => $brand]);
        $p->setRelation('identifiers', collect(array_map(
            fn ($c) => new MpIdentifier(['kind' => 'oem', 'value' => $c, 'value_norm' => \App\Support\Monitoring\ArticleNormalizer::normalize($c)]),
            $codes
        )));

        return $p;
    }

    public function test_exact_article_token_accepts_without_llm(): void
    {
        $r = (new ListingIdentityVerifier())->verify($this->product('Ролик', ['KM763640G01']), 'Ролик KM763640G01 цена 4520', null, null);
        $this->assertTrue($r['accepted']);
        $this->assertSame('oem_exact', $r['method']);
    }

    public function test_analog_is_rejected_even_with_article(): void
    {
        $r = (new ListingIdentityVerifier())->verify($this->product('Ролик', ['KM763640G01']), 'Аналог KM763640G01',
            null, ['is_analog' => true, 'is_same_product' => null, 'evidence_identity' => 'аналог']);
        $this->assertFalse($r['accepted']);
        $this->assertSame('analog', $r['reason']);
    }

    public function test_gray_zone_requires_llm_confirmation(): void
    {
        $v = new ListingIdentityVerifier();
        $p = $this->product('Ролик натяжной', ['KM763640G01']);
        $this->assertFalse($v->verify($p, 'Ролик натяжной для лифта', null, ['is_same_product' => null])['accepted']);
        $ok = $v->verify($p, 'Ролик натяжной для лифта', null, ['is_same_product' => true, 'evidence_identity' => 'x']);
        $this->assertTrue($ok['accepted']);
        $this->assertSame('llm_verified', $ok['method']);
    }

    public function test_numeric_signals_conflict_in_gray_zone(): void
    {
        $v = new ListingIdentityVerifier();
        $this->assertTrue($v->numericSignalsConflict('Шкив D=240 10 ручьев', 'Шкив КВШ D=320, 10 ручьев'));
        $this->assertFalse($v->numericSignalsConflict('Шкив D=240', 'Шкив D=240 мм'));
    }

    public function test_llm_cost_uses_longest_prefix(): void
    {
        $calc = new LlmCostCalculator(['gpt-4o' => [2.50, 10.00], 'gpt-4o-mini' => [0.15, 0.60]]);
        // Урок CE: «gpt-4o-mini-2024-07-18» должен считаться по mini, а не по gpt-4o.
        $this->assertSame(0.00021, $calc->usd('gpt-4o-mini-2024-07-18', 1000, 100));
        $this->assertSame(0.0035, $calc->usd('gpt-4o-2024-08-06', 1000, 100));
    }

    public function test_resolver_extracts_codes_and_skips_internal_and_series(): void
    {
        $r = new ProductIdentityResolver();
        $this->assertSame(['KM763640G01'], $r->extractCodes('KM763640G01', 'Ролик'));
        // Внутренний код учётной системы — не артикул; берём код модели из названия.
        $this->assertSame(['MT42RUS'], $r->extractCodes('КСУ НСИ 000123', 'Выключатель МТ42RUS'));
        // Код серии не артикул; размеры тоже.
        $this->assertSame([], $r->extractCodes(null, 'Ступень ECO3000 шириной 1000ММ'));
        $this->assertSame([], $r->extractCodes('1234567890123', 'Позиция')); // длинный числовой без бренда
        $this->assertSame(['1234567890123'], $r->extractCodes('1234567890123', 'Позиция', 'OTIS'));
    }

    public function test_variant_names_differ_but_formatting_does_not(): void
    {
        $r = new ProductIdentityResolver();
        $this->assertTrue($r->namesDiffer(
            'Кнопка в сборе Отис 2000 и GEN2 D посадка 27,5мм КРАСНЫЙ ареол полированная нержа, AMP',
            'Кнопка в сборе Отис 2000 и GEN2 D посадка 27,5мм СИНИЙ ореол полированная нержа AMP для MCS220'
        ));
        $this->assertFalse($r->namesDiffer('Ролик KM763640G01', 'ролик  KM763640G01.'));
    }

    public function test_discovery_queries_use_oem_not_client_catalog_code(): void
    {
        $p = new MpProduct(['name' => 'Ролик натяжной', 'brand' => 'KONE']);
        $p->setRelation('identifiers', collect([
            new MpIdentifier(['kind' => 'alias', 'value' => 'LW-0002478', 'value_norm' => 'LW0002478']),
            new MpIdentifier(['kind' => 'oem', 'value' => 'KM763640G01', 'value_norm' => 'KM763640G01']),
        ]));
        // Без LLM — простые шаблоны (с LLM промпт тоже получает только OEM/MPN).
        $q = (new \App\Services\Monitoring\Discovery\SearchQueryBuilder(
            null,
            $this->createMock(\App\Services\Monitoring\MonitoringGovernor::class),
            new \App\Services\Monitoring\LlmCostCalculator()
        ))->build($p, 2);
        $this->assertSame(['"KM763640G01" KONE цена', '"KM763640G01" купить'], $q);
    }

    public function test_html_window_around_article(): void
    {
        $text = str_repeat('шапка сайта ', 2000) . ' Ролик KM763640G01 цена 4 520 ₽ в наличии ' . str_repeat('футер ', 500);
        $w = HtmlText::window($text, ['KM763640G01'], 100, 8000);
        $this->assertStringContainsString('4 520 ₽', $w);
        $this->assertLessThan(400, mb_strlen($w));
    }
}
