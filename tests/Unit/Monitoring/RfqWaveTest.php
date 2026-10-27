<?php

namespace Tests\Unit\Monitoring;

use App\Services\Monitoring\Hybrid\RfqPlanner;
use Tests\TestCase;

class RfqWaveTest extends TestCase
{
    public function test_smoothed_yield(): void
    {
        $this->assertEqualsWithDelta(0.01, RfqPlanner::smoothedYield(0, 0, 0.01, 20), 1e-9);       // новичок — средняя
        $this->assertEqualsWithDelta(0.2 / 120, RfqPlanner::smoothedYield(100, 0, 0.01, 20), 1e-9); // много писем, ни одной цены
        $this->assertEqualsWithDelta(5.2 / 30, RfqPlanner::smoothedYield(10, 5, 0.01, 20), 1e-9);  // отвечает охотно
    }

    public function test_wave_stops_at_expected_deficit(): void
    {
        // Ожидаемые КП 0,5 + 0,4 ≥ дефицита 0,8 — третий не нужен.
        $this->assertSame([1, 2], RfqPlanner::wave([1 => 0.5, 2 => 0.4, 3 => 0.3], 0.8, 30));
    }

    public function test_wave_capped_when_yield_is_low(): void
    {
        // При отдаче ~1% ожидаемые 6 КП не набрать — волна упирается в потолок.
        $ranked = array_fill_keys(range(1, 100), 0.01);
        $this->assertCount(30, RfqPlanner::wave($ranked, 6.0, 30));
        $this->assertSame(range(1, 30), RfqPlanner::wave($ranked, 6.0, 30));
    }

    public function test_wave_edges(): void
    {
        $this->assertSame([], RfqPlanner::wave([], 3.0, 30));
        $this->assertSame([7], RfqPlanner::wave([7 => 0.2, 8 => 0.1], 0.0, 30)); // хотя бы один
        $this->assertSame([7], RfqPlanner::wave([7 => 0.2, 8 => 0.1], 5.0, 1));
    }
}
