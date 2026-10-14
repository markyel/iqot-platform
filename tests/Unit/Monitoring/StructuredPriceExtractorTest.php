<?php

namespace Tests\Unit\Monitoring;

use App\Services\Monitoring\Extract\StructuredPriceExtractor;
use PHPUnit\Framework\TestCase;

class StructuredPriceExtractorTest extends TestCase
{
    private function jsonLd(array $data): string
    {
        return '<html><head><script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE) . '</script></head><body></body></html>';
    }

    public function test_single_product_with_matching_sku(): void
    {
        $html = $this->jsonLd(['@type' => 'Product', 'name' => 'Ролик KM763640G01', 'sku' => 'KM763640G01',
            'offers' => ['@type' => 'Offer', 'price' => '4520.00', 'priceCurrency' => 'RUR', 'availability' => 'https://schema.org/InStock']]);
        $r = (new StructuredPriceExtractor())->extract($html, ['KM763640G01']);
        $this->assertNotNull($r);
        $this->assertSame(4520.0, $r['price']);
        $this->assertSame('RUB', $r['currency']);
        $this->assertSame('in_stock', $r['availability']);
        $this->assertTrue($r['sku_matched']);
    }

    public function test_single_product_without_article_is_flagged_not_matched(): void
    {
        $html = $this->jsonLd(['@type' => 'Product', 'name' => 'Ролик другой', 'sku' => 'ZZZ999',
            'offers' => ['@type' => 'Offer', 'price' => 100, 'priceCurrency' => 'RUB']]);
        $r = (new StructuredPriceExtractor())->extract($html, ['KM763640G01']);
        $this->assertNotNull($r);
        $this->assertFalse($r['sku_matched']); // решает верификатор идентичности
    }

    public function test_several_products_pick_ours_by_token_not_substring(): void
    {
        $html = $this->jsonLd(['@graph' => [
            ['@type' => 'Product', 'name' => 'ABC1234', 'offers' => ['price' => 200, 'priceCurrency' => 'RUB']],
            ['@type' => 'Product', 'name' => 'ABC123', 'offers' => ['price' => 100, 'priceCurrency' => 'RUB']],
        ]]);
        $r = (new StructuredPriceExtractor())->extract($html, ['ABC123']);
        $this->assertSame(100.0, $r['price']);
    }

    public function test_several_products_none_ours_returns_null(): void
    {
        $html = $this->jsonLd(['@graph' => [
            ['@type' => 'Product', 'name' => 'X1', 'offers' => ['price' => 200]],
            ['@type' => 'Product', 'name' => 'X2', 'offers' => ['price' => 100]],
        ]]);
        $this->assertNull((new StructuredPriceExtractor())->extract($html, ['ABC123']));
    }

    public function test_aggregate_offer_range_is_from_price(): void
    {
        $html = $this->jsonLd(['@type' => 'Product', 'name' => 'ABC123',
            'offers' => ['@type' => 'AggregateOffer', 'lowPrice' => '1500', 'highPrice' => '2500', 'priceCurrency' => 'RUB']]);
        $r = (new StructuredPriceExtractor())->extract($html, ['ABC123']);
        $this->assertSame(1500.0, $r['price']);
        $this->assertSame('from', $r['price_kind']);
    }

    public function test_vat_and_unit_from_price_specification(): void
    {
        $html = $this->jsonLd(['@type' => 'Product', 'name' => 'Канат ABC123', 'offers' => ['@type' => 'Offer',
            'priceSpecification' => ['price' => 680, 'priceCurrency' => 'RUB', 'valueAddedTaxIncluded' => true, 'unitCode' => 'MTR']]]);
        $r = (new StructuredPriceExtractor())->extract($html, ['ABC123']);
        $this->assertSame(680.0, $r['price']);
        $this->assertTrue($r['vat_included']);
        $this->assertSame('meter', $r['price_unit']);
    }

    public function test_microdata_with_two_prices_is_ambiguous(): void
    {
        $html = '<div itemprop="price" content="100"></div><div itemprop="price" content="250"></div>';
        $this->assertNull((new StructuredPriceExtractor())->extract($html, []));
    }
}
