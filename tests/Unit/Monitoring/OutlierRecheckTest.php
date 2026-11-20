<?php

namespace Tests\Unit\Monitoring;

use App\Services\Monitoring\ListingChecker;
use App\Services\Monitoring\OutlierRecheck;
use PHPUnit\Framework\TestCase;

class OutlierRecheckTest extends TestCase
{
    public function test_low_and_high_outliers_found_by_all_prices(): void
    {
        // M16759: 16 ₽ среди 2 296–7 400 ₽.
        $contactor = [1 => 16, 2 => 2296, 3 => 2412, 4 => 2511, 5 => 2523, 6 => 4747, 7 => 5510, 8 => 5860, 9 => 7400];
        $this->assertSame([1], OutlierRecheck::outliers($contactor, 4.0, 4));

        // M00183: 6 804 ₽ (колодки armtek) среди 119–201 ₽.
        $fixing = [1 => 138.75, 2 => 198, 3 => 194, 4 => 6804, 5 => 177, 6 => 119, 7 => 156, 8 => 201.3];
        $this->assertSame([4], OutlierRecheck::outliers($fixing, 4.0, 4));
    }

    public function test_too_few_prices_no_decision(): void
    {
        $this->assertSame([], OutlierRecheck::outliers([1 => 16, 2 => 2300, 3 => 2500], 4.0, 4));
    }

    public function test_confirmed_price_not_rechecked_again(): void
    {
        $this->assertTrue(OutlierRecheck::alreadyConfirmed(16.0, 16.0));
        $this->assertFalse(OutlierRecheck::alreadyConfirmed(16.0, null));
        $this->assertFalse(OutlierRecheck::alreadyConfirmed(20.0, 16.0));
    }

    public function test_recheck_requires_llm_confirmation(): void
    {
        $ok = ['accepted' => true, 'method' => 'structured_sku', 'confidence' => 0.95, 'reason' => '', 'evidence' => 'sku in markup'];

        $r = ListingChecker::confirmOnRecheck($ok, ['is_same_product' => false, 'evidence_identity' => 'Колодки тормозные'], ['price' => 6804]);
        $this->assertFalse($r['accepted']);
        $this->assertSame('recheck_unconfirmed', $r['reason']);

        $r = ListingChecker::confirmOnRecheck($ok, null, ['price' => 6804]);
        $this->assertFalse($r['accepted']);

        $r = ListingChecker::confirmOnRecheck($ok, ['is_same_product' => true, 'price' => 2296], ['price' => 16]);
        $this->assertSame('recheck_price_unconfirmed', $r['reason']);

        $this->assertTrue(ListingChecker::confirmOnRecheck($ok, ['is_same_product' => true, 'price' => 2296], ['price' => 1882])['accepted']);
        $this->assertTrue(ListingChecker::confirmOnRecheck($ok, ['is_same_product' => true, 'price' => null], ['price' => 16])['accepted']);
    }
}
