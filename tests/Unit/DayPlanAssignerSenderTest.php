<?php

namespace Tests\Unit;

use App\Services\Generate\DayPlanAssigner;
use PHPUnit\Framework\TestCase;

/** Этап 2 плана дня: ящики группам одинаковых наборов позиций — минимум батчей при соблюдении ротации. */
class DayPlanAssignerSenderTest extends TestCase
{
    /** @return array<int,array{supplier_id:int,sender_id:int,item_ids:array<int,int>}> */
    private function planFor(array $senderCaps, array $senderRecent, int $suppliers = 10, ?int &$dropped = null): array
    {
        $pool = [100 => range(1, $suppliers)];
        $emails = [];
        $recipientCaps = [];
        foreach (range(1, $suppliers) as $s) {
            $emails[$s] = "s{$s}@x.ru";
            $recipientCaps["s{$s}@x.ru"] = 5;
        }

        return (new DayPlanAssigner())->plan([100 => ['request_id' => 1, 'urgency' => 1.0]], $pool, [], [], $emails,
            $senderCaps, $recipientCaps, $senderRecent, 1, $dropped);
    }

    private function batches(array $plan): array
    {
        $b = [];
        foreach ($plan as $p) {
            $b[$p['sender_id'] . '|' . implode(',', $p['item_ids'])] = ($b[$p['sender_id'] . '|' . implode(',', $p['item_ids'])] ?? 0) + 1;
        }

        return $b;
    }

    public function test_whole_group_goes_to_one_sender_when_possible(): void
    {
        $plan = $this->planFor([1 => 50, 2 => 50, 3 => 50], []);
        $this->assertCount(10, $plan);
        $this->assertCount(1, $this->batches($plan));
    }

    public function test_rotation_conflicts_land_on_one_second_sender(): void
    {
        // Ящик 1 писал s1..s4 за неделю, ящик 2 — s5..s10: лучший — 1 (берёт 6), отказники s1..s4 — одним батчем на 2 или 3.
        $recent = [];
        foreach (range(1, 4) as $s) {
            $recent["1|s{$s}@x.ru"] = true;
        }
        foreach (range(5, 10) as $s) {
            $recent["2|s{$s}@x.ru"] = true;
        }
        foreach (range(1, 8) as $s) {
            $recent["3|s{$s}@x.ru"] = true; // ни один ящик не берёт всех
        }
        $plan = $this->planFor([1 => 50, 2 => 50, 3 => 50], $recent);
        $this->assertCount(10, $plan);
        $this->assertCount(2, $this->batches($plan), 'было бы 5 батчей: липкий + каждый отказник отдельно');
        foreach ($plan as $p) {
            $this->assertArrayNotHasKey($p['sender_id'] . '|s' . $p['supplier_id'] . '@x.ru', $recent, 'ротация нарушена');
        }
    }

    public function test_sender_cap_respected_and_nothing_dropped(): void
    {
        $plan = $this->planFor([1 => 4, 2 => 4, 3 => 4], [], 10, $dropped);
        $this->assertSame(0, $dropped);
        $per = [];
        foreach ($plan as $p) {
            $per[$p['sender_id']] = ($per[$p['sender_id']] ?? 0) + 1;
        }
        $this->assertLessThanOrEqual(4, max($per));
        $this->assertCount(10, $plan);
    }
}
