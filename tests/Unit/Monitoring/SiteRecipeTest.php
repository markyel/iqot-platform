<?php

namespace Tests\Unit\Monitoring;

use App\Services\Monitoring\Recipes\SiteRecipe;
use App\Services\Monitoring\Recipes\SiteRecipeLearner;
use PHPUnit\Framework\TestCase;

class SiteRecipeTest extends TestCase
{
    private const HTML = '<html><body><h1 class="product-title">Ролик каретки D=55 FAA456X1</h1>'
        . '<div class="sku">Артикул: FAA456X1</div><div class="price-block"><span class="old">1 500 ₽</span>'
        . '<span class="price-current">1 250 ₽</span></div><div class="related"><span class="price-current">900 ₽</span></div>'
        . '<script>var x=1;</script></body></html>';

    public function test_apply_takes_first_match_of_each_selector(): void
    {
        $r = (new SiteRecipe(['title' => 'h1.product-title', 'sku' => '.sku', 'price' => '.price-block .price-current']))->apply(self::HTML);

        $this->assertSame('Ролик каретки D=55 FAA456X1', $r['title']);
        $this->assertSame(1250.0, $r['price']);
        $this->assertStringContainsString('FAA456X1', (string) $r['sku']);
    }

    public function test_bad_selector_is_harmless(): void
    {
        $r = (new SiteRecipe(['price' => '!!bad[[']))->apply(self::HTML);
        $this->assertNull($r['price']);
    }

    public function test_compact_keeps_title_and_price_block_without_scripts(): void
    {
        $c = SiteRecipeLearner::compact(self::HTML, 1250.0);

        $this->assertStringContainsString('product-title', $c);
        $this->assertStringContainsString('price-current', $c);
        $this->assertStringNotContainsString('var x', $c);
    }

    public function test_validate_counts_price_hits(): void
    {
        $v = SiteRecipeLearner::validate(new SiteRecipe(['price' => '.price-block .price-current']),
            [['url' => 'a', 'price' => 1250.0, 'codes' => [], 'html' => self::HTML], ['url' => 'b', 'price' => 999.0, 'codes' => [], 'html' => self::HTML]]);
        $this->assertSame(1, $v['hits']);
        $this->assertSame(2, $v['total']);
    }
}
