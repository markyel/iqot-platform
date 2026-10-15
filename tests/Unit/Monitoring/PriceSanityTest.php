<?php

namespace Tests\Unit\Monitoring;

use App\Support\Monitoring\PriceSanity;
use Tests\TestCase;

class PriceSanityTest extends TestCase
{
    private function row(float $price, string $status = 'active', bool $outlier = false): array
    {
        return ['price' => $price, 'currency' => 'RUB', 'price_kind' => 'retail', 'status' => $status, '_outlier' => $outlier];
    }

    public function test_m02502_three_prices_flags_only_the_extreme_one(): void
    {
        // optozip 550, oiz-spb 725 («подозрительна» на фоне 5 000 ₽ других длин), snab-lift 32 810.
        $out = PriceSanity::apply([$this->row(550), $this->row(725, 'suspicious', true), $this->row(32810)]);
        $this->assertSame(['active', 'active', 'suspicious'], array_column($out, 'status'));
    }

    public function test_four_and_more_prices_use_ratio_four(): void
    {
        $out = PriceSanity::apply([$this->row(1000), $this->row(1100), $this->row(1200), $this->row(5200)]);
        $this->assertSame(['active', 'active', 'active', 'suspicious'], array_column($out, 'status'));
    }

    public function test_two_prices_are_left_as_is_and_jump_flags_are_kept(): void
    {
        $two = PriceSanity::apply([$this->row(500), $this->row(9000, 'suspicious', true)]);
        $this->assertSame(['active', 'suspicious'], array_column($two, 'status'));

        // «Скачок цены» (не выброс от медианы) не пересчитываем и в медиану не берём.
        $jump = PriceSanity::apply([$this->row(100), $this->row(110), $this->row(120), $this->row(115, 'suspicious', false)]);
        $this->assertSame(['active', 'active', 'active', 'suspicious'], array_column($jump, 'status'));
    }
}
