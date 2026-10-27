<?php

namespace Tests\Unit\Monitoring;

use App\Services\Monitoring\Hybrid\TrustPolicy;
use App\Services\Monitoring\Hybrid\VerificationMailer;
use App\Support\Monitoring\SiteTrust;
use Tests\TestCase;

class SiteTrustTest extends TestCase
{
    public function test_paused_only_by_verify_mismatch_in_future(): void
    {
        $this->assertTrue(SiteTrust::paused(TrustPolicy::PAUSE_REASON, now()->addDays(10)));
        $this->assertTrue(SiteTrust::paused(TrustPolicy::PAUSE_REASON, now()->addDay()->toDateTimeString()));
        $this->assertFalse(SiteTrust::paused(TrustPolicy::PAUSE_REASON, now()->subDay()));   // пауза кончилась
        $this->assertFalse(SiteTrust::paused('captcha', now()->addDays(10)));               // блок обхода — не недоверие
        $this->assertFalse(SiteTrust::paused(null, null));
    }

    public function test_reliable_by_trust_tier(): void
    {
        // Доверяем сайту — цена надёжна.
        $this->assertTrue(SiteTrust::reliable(null, false, false));
        // Иногда ошибается — сомнительно, кроме индивидуально подтверждённой цены.
        $this->assertFalse(SiteTrust::reliable(null, SiteTrust::doubtful(1, false), false));
        $this->assertTrue(SiteTrust::reliable('match', SiteTrust::doubtful(1, false), false));
        // Опровергнута КП — не надёжна даже у доверенного сайта.
        $this->assertFalse(SiteTrust::reliable('price_mismatch', false, false));
        // В основном неправда (пауза) — не верим ничему, даже match.
        $this->assertTrue(SiteTrust::doubtful(0, true));
        $this->assertFalse(SiteTrust::reliable('match', true, true));
    }

    public function test_pool_alias_share_stable(): void
    {
        config(['services.alias_exp.share' => 0.0]);
        $this->assertFalse(VerificationMailer::poolInAliasShare(123));
        config(['services.alias_exp.share' => 1.0]);
        $this->assertTrue(VerificationMailer::poolInAliasShare(123));
        config(['services.alias_exp.share' => 0.4]);
        $in = array_filter(range(1, 2000), fn ($id) => VerificationMailer::poolInAliasShare($id));
        $this->assertEqualsWithDelta(0.4, count($in) / 2000, 0.05);                         // доля ≈ ALIAS_EXP_SHARE
        $this->assertSame(VerificationMailer::poolInAliasShare(777), VerificationMailer::poolInAliasShare(777)); // стабильно
    }
}
