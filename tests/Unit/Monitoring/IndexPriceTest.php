<?php

namespace Tests\Unit\Monitoring;

use App\Jobs\Monitoring\IndexSitePagesJob;
use App\Models\Monitoring\MpSite;
use Tests\TestCase;

class IndexPriceTest extends TestCase
{
    public function test_markup_price_and_vat_label(): void
    {
        $html = '<html><head><script type="application/ld+json">{"@context":"https://schema.org","@type":"Product","name":"Энкодер",'
            . '"sku":"TAA27076ADY1","offers":{"@type":"Offer","price":"28696","priceCurrency":"RUB","availability":"https://schema.org/InStock"}}</script>'
            . '</head><body><h1>Энкодер TAA27076ADY1</h1><div>28 696 ₽ (+НДС)</div></body></html>';
        $r = IndexSitePagesJob::priceOf(new MpSite(), $html, ['TAA27076ADY1']);

        $this->assertSame(28696.0, $r['price']);
        $this->assertSame('markup', $r['price_source']);
        $this->assertSame('in_stock', $r['availability']);
        $this->assertFalse($r['vat_included']);
    }

    public function test_recipe_when_no_markup(): void
    {
        $site = new MpSite(['recipe' => ['title' => 'h1', 'price' => '.price-current'], 'recipe_score' => 1.0]);
        $html = '<html><body><h1>Ролик FAA456X1</h1><span class="price-current">1 250 ₽</span></body></html>';
        $r = IndexSitePagesJob::priceOf($site, $html, ['FAA456X1']);

        $this->assertSame(1250.0, $r['price']);
        $this->assertSame('recipe', $r['price_source']);
    }

    public function test_no_price(): void
    {
        $r = IndexSitePagesJob::priceOf(new MpSite(), '<html><body><h1>Ролик</h1>Цена по запросу</body></html>', []);
        $this->assertNull($r['price']);
    }
}
