<?php

namespace Tests\Unit\Monitoring;

use App\Models\Monitoring\ProgramItem;
use App\Services\Monitoring\ProductIdentityResolver;
use App\Support\Monitoring\ItemScope;
use Tests\TestCase;

/** Инцидент 15.10.2026, M02502: позиция «Ремень D4*600, KM247024» и товар с ремнями других длин. */
class ItemScopeTest extends TestCase
{
    private const PRODUCT = ['FI264123', 'KM247024', 'FI264124', 'ERB04KGC250'];

    public function test_narrow_item_sees_only_pages_with_its_codes(): void
    {
        $item = ['KM247024'];
        $this->assertTrue(ItemScope::isNarrow($item, self::PRODUCT));
        $this->assertTrue(ItemScope::allows($item, self::PRODUCT, ['KM247024']));
        $this->assertTrue(ItemScope::allows($item, self::PRODUCT, ['FI264123', 'KM247024'])); // кросс-номер на той же странице
        $this->assertFalse(ItemScope::allows($item, self::PRODUCT, ['FI264123']));           // D4×550 — чужой вариант
        $this->assertFalse(ItemScope::allows($item, self::PRODUCT, []));                     // по названию моделью — проверить нечем
    }

    public function test_item_with_all_product_codes_or_without_codes_sees_everything(): void
    {
        $this->assertTrue(ItemScope::allows(self::PRODUCT, self::PRODUCT, ['FI264123']));
        $this->assertTrue(ItemScope::allows([], self::PRODUCT, []));
        $this->assertFalse(ItemScope::isNarrow(['KM247024'], ['KM247024']));
    }

    public function test_listing_codes_fall_back_to_oem_exact_evidence(): void
    {
        $this->assertSame(['KM247024'], ItemScope::listingCodes(null, 'oem_exact', 'KM247024'));
        $this->assertSame([], ItemScope::listingCodes(null, 'llm_verified', 'Ремень тахометра D4X550'));
        $this->assertSame(['FI264123'], ItemScope::listingCodes('["FI264123"]', 'oem_exact', 'KM247024')); // вычисленные — главнее
    }

    public function test_item_codes_include_oem_articles_and_skip_name_codes_then(): void
    {
        $r = new ProductIdentityResolver();
        $item = new ProgramItem(['raw_name' => 'Ремень тахометра D4*600', 'raw_article' => 'KM247024',
            'oem_articles' => ['KM85869H01', 'PC-04L=600', 'KM247024']]);
        $this->assertSame(['KM247024', 'KM85869H01', 'PC04L=600'], $r->itemCodes($item, 'KONE'));

        // Комментарии в скобках отрезаются, короткие числа и размеры — не коды.
        $dirty = new ProgramItem(['raw_name' => 'Гребенка', 'raw_article' => 'SMR313609',
            'oem_articles' => ['50630296 ( компл. 4 шт.+ 2 боковинки)', '147', 'D4', 'SCB201', '3X02429*A']]);
        $this->assertSame(['SMR313609', '50630296', 'SCB201', '3X02429*A'], $r->itemCodes($dirty, 'SCHINDLER'));

        $noArticle = new ProgramItem(['raw_name' => 'Выключатель МТ42RUS', 'raw_article' => null, 'oem_articles' => ['AB12345']]);
        $this->assertSame(['AB12345'], $r->itemCodes($noArticle));
    }
}
