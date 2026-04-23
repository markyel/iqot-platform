<?php

namespace App\Jobs\Api;

use App\Models\Api\SupplierDiscoveryRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Оркестратор Discovery (§7.1–7.2).
 *
 * - Параллелизм = 1. Если есть running — выходим, ничего не запускаем.
 * - Если есть queued — берём FIFO и диспатчим DiscoverSuppliersForPairJob.
 *
 * Cron: каждые 10 минут (см. routes/console.php).
 */
class DiscoveryOrchestratorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 1;

    public function __construct()
    {
        $this->onConnection('database');
    }

    public function handle(): void
    {
        $running = SupplierDiscoveryRun::query()->where('status', 'running')->exists();
        if ($running) {
            return;
        }

        /** @var SupplierDiscoveryRun|null $next */
        $next = SupplierDiscoveryRun::query()
            ->where('status', 'queued')
            ->orderBy('created_at')
            ->first();
        if (!$next) {
            return;
        }

        Log::info('DiscoveryOrchestrator: dispatching run', ['run_id' => $next->id]);
        DiscoverSuppliersForPairJob::dispatch($next->id);
    }
}
