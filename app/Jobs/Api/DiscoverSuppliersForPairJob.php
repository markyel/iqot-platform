<?php

namespace App\Jobs\Api;

use App\Models\Api\SupplierDiscoveryRun;
use App\Services\Discovery\SupplierDiscoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Discovery по одной паре (domain, product_type) — §7.2/§7.3.
 *
 * Реальный pipeline:
 *   queued → running → N итераций (query gen → Yandex search → fetch → AI extract → persist)
 *   → finalize (success_covered | success_partial | exhausted | failed).
 * Cooldown политика на уровне supplier_discovery_runs обеспечивает отсутствие
 * повторной работы для пары слишком часто.
 */
class DiscoverSuppliersForPairJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // до 30 минут на весь pipeline (5 итераций × до 15 страниц)
    public $tries = 1;

    public int $runId;

    public function __construct(int $runId)
    {
        $this->runId = $runId;
        $this->onConnection('database');
    }

    public function handle(SupplierDiscoveryService $discovery): void
    {
        /** @var SupplierDiscoveryRun|null $run */
        $run = SupplierDiscoveryRun::find($this->runId);
        if (!$run) {
            return;
        }
        if ($run->status !== 'queued') {
            Log::warning('DiscoverSuppliersForPair: run not in queued state', [
                'run_id' => $run->id,
                'status' => $run->status,
            ]);
            return;
        }

        $run->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $summary = $discovery->runFullDiscovery($run->product_type_id, $run->domain_id);

            $run->update([
                'status' => $summary['status'],
                'iterations_used' => $summary['iterations_used'],
                'suppliers_found' => $summary['total_new'],
                'finished_at' => now(),
            ]);

            Log::info('DiscoverSuppliersForPair: finished', [
                'run_id' => $run->id,
                'domain_id' => $run->domain_id,
                'product_type_id' => $run->product_type_id,
                'status' => $summary['status'],
                'new_suppliers' => $summary['total_new'],
                'iterations' => $summary['iterations_used'],
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error' => substr($e->getMessage(), 0, 2000),
            ]);
            Log::error('DiscoverSuppliersForPair: run failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // Запускаем следующую queued (FIFO) если есть.
        DiscoveryOrchestratorJob::dispatch();
    }
}
