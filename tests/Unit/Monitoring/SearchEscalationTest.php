<?php

namespace Tests\Unit\Monitoring;

use App\Services\Monitoring\Discovery\ListingDiscoverer;
use App\Services\Monitoring\Discovery\SearchQueryBuilder;
use PHPUnit\Framework\TestCase;

class SearchEscalationTest extends TestCase
{
    private const LADDER = [[4, 25], [8, 25]];

    public function test_depth_by_level(): void
    {
        // «Эконом»: 2 запроса × 12 кандидатов, затем дожим 4×25 и 8×25.
        $this->assertSame([2, 12], ListingDiscoverer::depthFor(0, 2, 12, self::LADDER));
        $this->assertSame([4, 25], ListingDiscoverer::depthFor(1, 2, 12, self::LADDER));
        $this->assertSame([8, 25], ListingDiscoverer::depthFor(2, 2, 12, self::LADDER));
        $this->assertSame([8, 25], ListingDiscoverer::depthFor(5, 2, 12, self::LADDER));
    }

    public function test_depth_never_below_preset(): void
    {
        // «Максимум»: 8×25 — дожим не делает мельче.
        $this->assertSame([8, 25], ListingDiscoverer::depthFor(1, 8, 25, self::LADDER));
        $this->assertSame([10, 30], ListingDiscoverer::depthFor(2, 10, 30, self::LADDER));
        $this->assertSame([2, 12], ListingDiscoverer::depthFor(1, 2, 12, []));
    }

    public function test_used_queries_not_repeated(): void
    {
        $used = ['"FAA456X1" Otis цена', 'ролик каретки otis купить'];
        $new = ['FAA456X1 otis   цена', 'Ролик каретки OTIS купить', '"FAA456X1" в наличии', 'ролик двери 55 мм'];

        $this->assertSame(['"FAA456X1" в наличии', 'ролик двери 55 мм'], SearchQueryBuilder::without($new, $used));
        $this->assertSame($new, SearchQueryBuilder::without($new, []));
    }
}
