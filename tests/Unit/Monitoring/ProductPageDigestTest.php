<?php

namespace Tests\Unit\Monitoring;

use App\Support\Monitoring\ProductPageDigest;
use Tests\TestCase;

class ProductPageDigestTest extends TestCase
{
    public function test_title_and_codes_from_card(): void
    {
        $html = '<html><head><title>Купить ролик | Магазин</title>'
            . '<script type="application/ld+json">{"@type":"Product","name":"Ролик","sku":"RR3096","mpn":"KM713150G01"}</script></head>'
            . '<body><h1>Ролик натяжной KONE  KM 713150 G01</h1><div>Артикул: 3215-05-0037 &nbsp; Цена 4 520 ₽</div>'
            . '<span itemprop="model">DCSS5-E</span></body></html>';
        $d = ProductPageDigest::fromHtml($html);

        $this->assertSame('Ролик натяжной KONE KM 713150 G01', $d['title']);
        $this->assertContains('KM713150G01', $d['codes']);
        $this->assertContains('RR3096', $d['codes']);
        $this->assertContains('3215050037', $d['codes']);
        $this->assertContains('DCSS5E', $d['codes']);
    }

    public function test_no_garbage_codes_from_plain_page(): void
    {
        $d = ProductPageDigest::fromHtml('<html><title>О компании</title><body><p>Работаем с 2005 года, тел. 8 800 555-35-35</p></body></html>');
        $this->assertSame('О компании', $d['title']);
        $this->assertSame([], $d['codes']);
    }
}
