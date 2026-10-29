<?php

namespace Tests\Unit;

use App\Console\Commands\DispatchPendingEmails;
use PHPUnit\Framework\TestCase;

class DispatchSenderOrderTest extends TestCase
{
    private function s(int $id, int $limit, int $sent, string $date = '2026-10-29'): object
    {
        return (object) ['id' => $id, 'daily_limit' => $limit, 'emails_sent_today' => $sent, 'last_send_date' => $date];
    }

    private function ids(array $senders): array
    {
        return array_map(fn ($s) => $s->id, $senders);
    }

    public function test_least_used_share_goes_first_alias_before_all(): void
    {
        $senders = [
            $this->s(3, 100, 50),   // 0.5
            $this->s(100, 250, 25), // 0.1
            $this->s(276, 19, 0),   // 0 — новый ящик с большим id
            $this->s(270, 10, 9),   // alias, 0.9 — всё равно первым
            $this->s(5, 20, 19),    // 0.95
        ];
        $order = DispatchPendingEmails::orderSenders($senders, [270 => true], [], '2026-10-29');
        $this->assertSame([270, 276, 100, 3, 5], $this->ids($order));
    }

    public function test_in_flight_and_stale_counter(): void
    {
        $senders = [
            $this->s(1, 100, 10),               // 0.1 + 30 в полёте → 0.4
            $this->s(2, 100, 80, '2026-10-28'), // вчерашний счётчик не в счёт → 0
            $this->s(3, 100, 20),               // 0.2
        ];
        $order = DispatchPendingEmails::orderSenders($senders, [], [1 => 30], '2026-10-29');
        $this->assertSame([2, 3, 1], $this->ids($order));
    }

    public function test_equal_shares_are_not_ordered_by_id(): void
    {
        $senders = array_map(fn ($id) => $this->s($id, 19, 0), range(1, 8));
        $firsts = [];
        for ($i = 0; $i < 200; $i++) {
            $order = DispatchPendingEmails::orderSenders($senders, [], [], '2026-10-29');
            $this->assertCount(8, $order);
            $firsts[$order[0]->id] = true;
        }
        // При равных долях первым бывает не только наименьший id.
        $this->assertGreaterThan(1, count($firsts));
    }
}
