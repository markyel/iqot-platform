<?php

namespace Tests\Unit\Monitoring;

use App\Support\Monitoring\ArticleNormalizer;
use App\Support\Monitoring\BrandNormalizer;
use App\Support\Monitoring\DomainPolicy;
use App\Support\Monitoring\NonItemPriceEvidence;
use App\Support\Monitoring\PriceNumberParser;
use App\Support\Monitoring\UnitNormalizer;
use App\Support\Monitoring\UrlCanonicalizer;
use PHPUnit\Framework\TestCase;

class NormalizersTest extends TestCase
{
    public function test_article_normalize_strips_separators_and_case(): void
    {
        $this->assertSame('KM763640G01', ArticleNormalizer::normalize('km-763640.g01'));
        $this->assertSame('KM763640G01', ArticleNormalizer::normalize(' KM 763640 G01 '));
        $this->assertSame('', ArticleNormalizer::normalize('a1'));
    }

    public function test_cyrillic_homoglyphs_become_latin_in_codes(): void
    {
        // «МТ42» набран кириллицей М и Т.
        $this->assertSame('MT42RUS', ArticleNormalizer::normalize('МТ42RUS'));
        $this->assertSame(ArticleNormalizer::normalize('AEG09C220*B'), ArticleNormalizer::normalize('АЕG09С220*В'));
        // Чисто русское слово не трогаем.
        $this->assertSame('РЕДУКТОР', ArticleNormalizer::normalize('редуктор'));
    }

    public function test_token_match_is_not_substring(): void
    {
        $this->assertTrue(ArticleNormalizer::containsToken('Ролик ABC123 в наличии', 'ABC123'));
        $this->assertFalse(ArticleNormalizer::containsToken('Ролик ABC1234 в наличии', 'ABC123'));
        $this->assertFalse(ArticleNormalizer::containsToken('Ролик ABC123 4 шт', 'ABC1234'));
        // Код, набранный с пробелами, склеивается.
        $this->assertTrue(ArticleNormalizer::containsToken('Артикул: KM 763640 G01, цена', 'KM763640G01'));
    }

    public function test_brand_normalize_and_aliases(): void
    {
        $aliases = ['THYSSENKRUPP' => 'TKE', 'THYSSEN' => 'TKE'];
        $this->assertSame('TKE', BrandNormalizer::normalize('ThyssenKrupp', $aliases));
        $this->assertSame('OTIS', BrandNormalizer::normalize('ООО «Otis»', $aliases));
        $this->assertTrue(BrandNormalizer::differ('KONE', 'OTIS'));
        $this->assertFalse(BrandNormalizer::differ('OTIS', ''));
        $this->assertFalse(BrandNormalizer::differ('OTIS', 'OTISRU'));
    }

    public function test_price_number_parser(): void
    {
        $this->assertSame(13903.91, PriceNumberParser::parse('13 903,91'));
        $this->assertSame(13903.91, PriceNumberParser::parse('13,903.91'));
        $this->assertSame(13903.91, PriceNumberParser::parse('13.903,91'));
        $this->assertSame(269500.0, PriceNumberParser::parse('269,500'));
        $this->assertSame(12400.0, PriceNumberParser::parse('12 400 ₽'));
        $this->assertSame(22053.0, PriceNumberParser::parse('22053.00'));
        $this->assertNull(PriceNumberParser::parse('по запросу'));
        $this->assertNull(PriceNumberParser::parse('0'));
    }

    public function test_unit_normalizer(): void
    {
        $this->assertSame('piece', UnitNormalizer::normalize('шт.'));
        $this->assertSame('meter', UnitNormalizer::normalize('п.м.'));
        $this->assertSame('meter', UnitNormalizer::normalize('м'));
        $this->assertSame('set', UnitNormalizer::normalize('компл'));
        $this->assertSame('unknown', UnitNormalizer::normalize('рулон'));
    }

    public function test_non_item_price_evidence_from_ce_cases(): void
    {
        // vent7.ru: цена доставки по МКАД вместо цены товара.
        $this->assertTrue(NonItemPriceEvidence::check('Стоимость 1800 руб в пределах МКАД', 1800));
        $this->assertTrue(NonItemPriceEvidence::check('Бесплатная доставка при заказе от 40 000 руб', 40000));
        // Законная цена рядом со словом «доставка» в другой части фразы.
        $this->assertFalse(NonItemPriceEvidence::check('Цена 5 000 ₽, доставка 1–3 дня', 5000));
        // Рекламный оборот «с доставкой по РФ».
        $this->assertFalse(NonItemPriceEvidence::check('Купить за 12 400 ₽ с доставкой по РФ', 12400));
    }

    public function test_domain_policy_exact_and_prefix(): void
    {
        $blocked = ['ozon.ru', 'x.com', 'aliexpress.'];
        $this->assertTrue(DomainPolicy::isBlocked('https://www.ozon.ru/product/1', $blocked));
        $this->assertTrue(DomainPolicy::isBlocked('seller.ozon.ru', $blocked));
        $this->assertTrue(DomainPolicy::isBlocked('https://aliexpress.ru/item/1', $blocked));
        // Подстрока не блокирует: «x.com» ≠ «liftmax.com».
        $this->assertFalse(DomainPolicy::isBlocked('https://liftmax.com/p/1', $blocked));
        $this->assertFalse(DomainPolicy::isBlocked('https://revator.ru/p/1', $blocked));
    }

    public function test_url_canonicalizer(): void
    {
        $this->assertSame('https://lift.ru/p/1?id=5', UrlCanonicalizer::canonicalize('http://www.lift.ru/p/1/?utm_source=y&id=5#top'));
        $this->assertSame('lift.msk.ru', UrlCanonicalizer::registrableDomain('https://shop.lift.msk.ru/x'));
        $this->assertSame('revator.ru', UrlCanonicalizer::registrableDomain('https://www.revator.ru/x'));
        $this->assertTrue(UrlCanonicalizer::looksLikeNonProduct('https://lift.ru/catalog/'));
        $this->assertFalse(UrlCanonicalizer::looksLikeNonProduct('https://lift.ru/catalog/rolik-km763640g01'));
    }
}
