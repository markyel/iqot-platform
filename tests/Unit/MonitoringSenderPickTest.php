<?php

namespace Tests\Unit;

use App\Services\Monitoring\Hybrid\VerificationMailer;
use PHPUnit\Framework\TestCase;

class MonitoringSenderPickTest extends TestCase
{
    public function test_new_mailbox_with_small_limit_is_not_starved(): void
    {
        // Крупный ящик потратил 10 из 250 (доля 0.96), новый — 0 из 19 (доля 1.0): берём новый.
        $this->assertSame(276, VerificationMailer::pickByShare([22 => 240, 276 => 19], [22 => 250, 276 => 19], [], 'a@x.ru'));
        // Новый потратил половину (доля 0.5) — снова крупный.
        $this->assertSame(22, VerificationMailer::pickByShare([22 => 240, 276 => 9], [22 => 250, 276 => 19], [], 'a@x.ru'));
    }

    public function test_skips_exhausted_and_recent_pairs(): void
    {
        $left = [1 => 0, 2 => 10, 3 => 30];
        $limits = [1 => 30, 2 => 30, 3 => 30];
        $this->assertSame(2, VerificationMailer::pickByShare($left, $limits, ['3|a@x.ru' => true], 'a@x.ru'));
        $this->assertNull(VerificationMailer::pickByShare([1 => 0], [1 => 30], [], 'a@x.ru'));
    }

    public function test_spreads_proportionally_to_limits(): void
    {
        // Раздача 100 писем разным получателям: ящики набирают пропорционально лимитам.
        $limits = [1 => 200, 2 => 50, 3 => 19];
        $left = $limits;
        $got = [1 => 0, 2 => 0, 3 => 0];
        for ($i = 0; $i < 100; $i++) {
            $id = VerificationMailer::pickByShare($left, $limits, [], "r{$i}@x.ru");
            $left[$id]--;
            $got[$id]++;
        }
        $this->assertGreaterThanOrEqual(6, $got[3]);  // ≈ 100·19/269 = 7
        $this->assertGreaterThanOrEqual(17, $got[2]); // ≈ 19
        $this->assertSame(100, array_sum($got));
    }
}
