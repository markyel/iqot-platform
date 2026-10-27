<?php

namespace Tests\Unit\Monitoring;

use App\Models\Monitoring\MpSite;
use App\Services\Monitoring\Hybrid\PriceComparator;
use App\Services\Monitoring\Hybrid\TrustPolicy;
use Tests\TestCase;

class HybridTrustTest extends TestCase
{
    private function site(int $level = 0): MpSite
    {
        return new MpSite(['trust_level' => $level, 'trust_scope' => 'none', 'sample_every' => 1,
            'verified_ok' => 0, 'verified_mismatch' => 0, 'consecutive_ok' => 0]);
    }

    public function test_compare_outcomes(): void
    {
        $c = fn (...$a) => PriceComparator::compare(...$a)['outcome'];
        $this->assertSame('match', $c(10000.0, true, 'RUB', 10400.0, true, 'RUB'));
        $this->assertSame('match', $c(12000.0, true, 'RUB', 10000.0, false, 'RUR'));        // КП без НДС → ×1.2
        $this->assertSame('basis_diff', $c(12000.0, null, 'RUB', 10000.0, false, 'RUB'));  // ≈ НДС при неизвестной базе
        $this->assertSame('basis_diff', $c(500.0, true, 'RUB', 5000.0, true, 'RUB'));      // ×10 — упаковка
        $this->assertSame('match_error', $c(10000.0, true, 'RUB', 7000.0, true, 'RUB', ['notes' => 'предлагаем аналог производства Китай']));
        $this->assertSame('inconclusive', $c(10000.0, true, 'RUB', 120.0, true, 'EUR'));
        $this->assertSame('price_mismatch', $c(10000.0, true, 'RUB', 13500.0, true, 'RUB'));
    }

    public function test_trust_promotion_and_demotion(): void
    {
        $s = $this->site(0);
        foreach (range(1, 3) as $_) {
            TrustPolicy::apply($s, 'match');
        }
        $this->assertSame(1, $s->trust_level);
        $this->assertSame(10, $s->sample_every);
        $this->assertSame('all', $s->trust_scope);

        foreach (range(1, 6) as $_) {
            TrustPolicy::apply($s, 'match');
        }
        $this->assertSame(3, $s->trust_level);

        // Ошибка сопоставления и разная база — уровень не трогают.
        TrustPolicy::apply($s, 'match_error');
        TrustPolicy::apply($s, 'basis_diff');
        $this->assertSame(3, $s->trust_level);

        // Расхождение цены — минус 2; второе подряд — сразу 0.
        TrustPolicy::apply($s, 'price_mismatch');
        $this->assertSame(1, $s->trust_level);
        TrustPolicy::apply($s, 'price_mismatch');
        $this->assertSame(0, $s->trust_level);
        $this->assertSame(1, $s->sample_every);
    }

    public function test_pause_when_pool_mostly_mismatch(): void
    {
        // Правило 3: ≥5 ценовых сверок и ≥80% расхождений → пауза сайта на 30 дней.
        $s = $this->site(0);
        $r = [];
        foreach (range(1, 5) as $_) {
            $r = TrustPolicy::apply($s, 'price_mismatch');
        }
        $this->assertTrue($r['paused']);
        $this->assertNotNull($s->blocked_until);
        $this->assertTrue($s->blocked_until->isFuture());
        $this->assertSame('verify_mismatch', $s->blocked_reason);

        // Пул преимущественно совпадений (4 match + 1 mismatch = 20%) — без паузы.
        $s2 = $this->site(0);
        foreach (range(1, 4) as $_) {
            TrustPolicy::apply($s2, 'match');
        }
        $res = TrustPolicy::apply($s2, 'price_mismatch');
        $this->assertFalse($res['paused']);
        $this->assertNull($s2->blocked_until);
    }

    public function test_sample_size(): void
    {
        $this->assertSame(5, TrustPolicy::sampleSize($this->site(0), 40));
        $s = $this->site(2);
        $s->next_verification_due_at = now()->addDays(5);
        $this->assertSame(0, TrustPolicy::sampleSize($s, 30));    // 30/50 = 0, срок не подошёл
        $s->next_verification_due_at = now()->subDay();
        $this->assertSame(1, TrustPolicy::sampleSize($s, 30));    // обязательная раз в 20 дней
        $this->assertSame(3, TrustPolicy::sampleSize($s, 160));
    }
}
