<?php

namespace Tests\Unit\Monitoring;

use App\Services\Monitoring\Discovery\CatalogCrawler;
use PHPUnit\Framework\TestCase;

class CatalogCrawlerTest extends TestCase
{
    public function test_pattern_from_known_cards(): void
    {
        $p = CatalogCrawler::productPattern([
            'https://liftformat.ru/magazin/product/modul-knopochnyj-thyssenkrupp',
            'https://liftformat.ru/magazin/product/plata-otis-faa628h1',
            'https://liftformat.ru/magazin/product/rolik-karetki-d55',
            'https://liftformat.ru/magazin/folder/plati-stantsii-upravleniya',
        ]);

        $this->assertSame(['magazin', 'product'], $p['prefix']);
        $this->assertSame(3, $p['depth']);
        $this->assertTrue(CatalogCrawler::isProduct('/magazin/product/new-item', $p));
        $this->assertFalse(CatalogCrawler::isProduct('/magazin/folder/rolik', $p));
    }

    public function test_deep_catalog_pattern_and_sections(): void
    {
        $p = CatalogCrawler::productPattern([
            'https://snab-lift.ru/catalog/zapchasti-k-liftam/rolik/r1_rolik-karetki',
            'https://snab-lift.ru/catalog/zapchasti-k-liftam/knopki/k2_knopka',
            'https://snab-lift.ru/catalog/eskalatory/stupeni/s3_stupen',
        ]);

        $this->assertSame(['catalog'], $p['prefix']);
        $this->assertSame(4, $p['depth']);
        $this->assertTrue(CatalogCrawler::isSection('/catalog/zapchasti-k-liftam/', $p, 'catalog'));
        $this->assertTrue(CatalogCrawler::isSection('/catalog/zapchasti-k-liftam/rolik/', $p, 'catalog'));
        $this->assertFalse(CatalogCrawler::isSection('/catalog/a/b/c_item', $p), 'карточка — не раздел');
        $this->assertTrue(CatalogCrawler::isSection('/magazin/folder/rolik', CatalogCrawler::productPattern(['https://x.ru/magazin/product/a', 'https://x.ru/magazin/product/b', 'https://x.ru/magazin/product/c'])));
    }

    public function test_links_are_absolute(): void
    {
        $html = '<a href="/catalog/a/">A</a><a href="b/">B</a><a href="https://x.ru/c">C</a><a href="tel:+7">T</a><a href="#top">H</a>';
        $links = CatalogCrawler::links($html, 'https://site.ru/catalog/');

        $this->assertContains('https://site.ru/catalog/a/', $links);
        $this->assertContains('https://site.ru/catalog/b/', $links);
        $this->assertContains('https://x.ru/c', $links);
        $this->assertCount(3, $links);
    }

    public function test_too_few_cards_no_pattern(): void
    {
        $this->assertNull(CatalogCrawler::productPattern(['https://a.ru/p/1', 'https://a.ru/x/y/z']));
    }

    public function test_url_encoding(): void
    {
        $this->assertSame('https://snab-lift.ru/catalog/plata-%C2%AB%D0%B7%D0%B5%D0%BD%D0%B8%D1%82%C2%BB1.html',
            CatalogCrawler::encodeUrl('https://snab-lift.ru/catalog/plata-«зенит»1.html'));
        $this->assertSame('https://a.ru/p%20q?x=1', CatalogCrawler::encodeUrl('https://a.ru/p q?x=1'));
        $this->assertSame('https://a.ru/%D0%B0', CatalogCrawler::encodeUrl('https://a.ru/%D0%B0'), 'закодированное не трогаем');
    }
}
