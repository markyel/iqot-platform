<?php

namespace Tests\Unit\Monitoring;

use App\Http\Controllers\Api\V1\MonitoringProgramController;
use Tests\TestCase;

class VatSummaryTest extends TestCase
{
    public function test_prices_brought_to_with_vat_basis(): void
    {
        config(['monitoring.vat_percent' => 22]);

        $this->assertSame(1220.0, MonitoringProgramController::withVat(1000, false));
        $this->assertSame(1100.0, MonitoringProgramController::withVat(1100, true));
        $this->assertSame(1100.0, MonitoringProgramController::withVat(1100, null));
        // «1 000 без НДС» дороже «1 100 с НДС» на единой базе.
        $this->assertGreaterThan(MonitoringProgramController::withVat(1100, true), MonitoringProgramController::withVat(1000, false));
    }
}
