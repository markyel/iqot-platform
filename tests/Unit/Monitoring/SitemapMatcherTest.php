<?php

namespace Tests\Unit\Monitoring;

use App\Models\Monitoring\MpIdentifier;
use App\Models\Monitoring\MpProduct;
use App\Services\Monitoring\Discovery\SitemapMatcher;
use Tests\TestCase;

class SitemapMatcherTest extends TestCase
{
    public function test_fold_unifies_transliteration_variants(): void
    {
        $this->assertSame(SitemapMatcher::fold('площадки'), SitemapMatcher::fold('ploshhadki'));
        $this->assertSame(SitemapMatcher::fold('кронштейнами'), SitemapMatcher::fold('kronshtejnami'));
        $this->assertSame(SitemapMatcher::fold('зубца'), SitemapMatcher::fold('zubtsa'));
        $this->assertSame(SitemapMatcher::fold('кнопочный'), SitemapMatcher::fold('knopochnyy'));
        $this->assertSame(SitemapMatcher::fold('ролика'), SitemapMatcher::fold('rolik'));
        $this->assertSame(SitemapMatcher::fold('для'), SitemapMatcher::fold('dlja'));
    }

    public function test_url_tokens_find_codes_and_words(): void
    {
        $t = SitemapMatcher::urlTokens('https://apexx.ru/modul-knopochnyy-kone-km804342g10');
        $this->assertArrayHasKey('KM804342G10', $t['codes']);
        $this->assertArrayHasKey(SitemapMatcher::fold('модуль'), $t['words']);

        $t = SitemapMatcher::urlTokens('https://zaplift.ru/id/komplekt-kolodok-tormoza-ferodo-160vat-t0c5048b1-otis-3127');
        $this->assertArrayHasKey('T0C5048B1', $t['codes']);
        $this->assertArrayHasKey('3127', $t['nums']);
    }

    public function test_product_tokens_use_oem_and_name_codes(): void
    {
        $p = new MpProduct(['name' => 'Кнопка "СТОП" P1EC400E40-K/E с фиксацией', 'brand' => 'Schaefer']);
        $p->setRelation('identifiers', collect([
            new MpIdentifier(['kind' => 'oem', 'value' => 'KM804342G10', 'value_norm' => 'KM804342G10']),
            new MpIdentifier(['kind' => 'alias', 'value' => 'LW-0001547', 'value_norm' => 'LW0001547']),
        ]));
        $t = SitemapMatcher::productTokens($p);
        $this->assertArrayHasKey('KM804342G10', $t['codes']);
        $this->assertArrayHasKey('P1EC400E40KE', $t['codes']);
        $this->assertArrayNotHasKey('LW0001547', $t['codes']);
    }
}
