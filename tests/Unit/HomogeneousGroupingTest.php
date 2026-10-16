<?php

namespace Tests\Unit;

use App\Services\Generate\DayPlanAssigner;
use App\Services\Generate\HomogeneousKey;
use Tests\TestCase;

/** Однотипные позиции — в одно письмо, разношёрстные — по одной (решение владельца 16.10.2026). */
class HomogeneousGroupingTest extends TestCase
{
    public function test_floor_buttons_of_one_series_share_a_key(): void
    {
        $a = HomogeneousKey::of(12, 'KONE', 'Кнопка приказа «1» (низкий держатель) KM804340G001');
        $b = HomogeneousKey::of(12, 'Kone', 'Кнопка приказа «2» (низкий держатель) KM804340G002');
        $this->assertNotNull($a);
        $this->assertSame($a, $b);
    }

    public function test_different_brand_type_or_name_do_not_group(): void
    {
        $base = HomogeneousKey::of(12, 'KONE', 'Кнопка приказа «1» (низкий держатель)');
        $this->assertNotSame($base, HomogeneousKey::of(12, 'OTIS', 'Кнопка приказа «1» (низкий держатель)'));
        $this->assertNotSame($base, HomogeneousKey::of(13, 'KONE', 'Кнопка приказа «1» (низкий держатель)'));
        $this->assertNotSame($base, HomogeneousKey::of(12, 'KONE', 'Кнопка вызова «1» с подсветкой'));
        $this->assertNull(HomogeneousKey::of(12, null, 'Кнопка приказа «1»'));   // без бренда — по одной
        $this->assertNull(HomogeneousKey::of(null, 'KONE', 'Кнопка приказа «1»')); // без типа — по одной
        $this->assertNull(HomogeneousKey::of(12, 'KONE', 'Ролик 33'));            // слишком общее название
    }

    private function plan(array $homogeneous, int $maxHomogeneous): array
    {
        $positions = [1 => ['urgency' => 1.0], 2 => ['urgency' => 1.0], 3 => ['urgency' => 1.0]];
        $pool = [1 => [10], 2 => [10], 3 => [10]];

        return (new DayPlanAssigner())->plan($positions, $pool, [], [], [10 => 'sup@example.com'], [100 => 50, 101 => 50, 102 => 50, 103 => 50],
            ['sup@example.com' => 10], [], 1, $dropped, $stats, false, false, $homogeneous, $maxHomogeneous);
    }

    public function test_one_item_per_letter_but_homogeneous_items_are_bundled(): void
    {
        $key = HomogeneousKey::of(12, 'KONE', 'Кнопка приказа «1» (низкий держатель)');
        // Позиции 1 и 2 однотипны, 3 — другая.
        $plan = $this->plan([1 => $key, 2 => $key], 6);
        $sizes = array_map(fn ($e) => count($e['item_ids']), $plan);
        sort($sizes);
        $this->assertSame([1, 2], $sizes);

        // Без правила — каждая позиция отдельным письмом.
        $plan = $this->plan([1 => $key, 2 => $key], 0);
        $this->assertSame([1, 1, 1], array_map(fn ($e) => count($e['item_ids']), $plan));
    }
}
