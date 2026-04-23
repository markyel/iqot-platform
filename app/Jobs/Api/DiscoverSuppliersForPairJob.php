<?php

namespace App\Jobs\Api;

use App\Models\Api\SupplierDiscoveryRun;
use App\Services\Api\SupplierCoverageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Discovery по одной паре (domain, product_type) (§7.2).
 *
 * MVP-реализация: структура пайплайна зафиксирована (queued → running → finalize),
 * но сами итерации поиска поставщиков — заглушка. Реальный web-scrape + AI-валидация
 * будет добавлены отдельным этапом. На MVP run сразу переводится в success_partial
 * если coverage уже достаточен, либо exhausted — если недобор.
 *
 * Это даёт корректное поведение cooldown (30 дней exhausted) без реальных вызовов.
 */
class DiscoverSuppliersForPairJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 1;

    public int $runId;

    public function __construct(int $runId)
    {
        $this->runId = $runId;
        $this->onConnection('database');
    }

    public function handle(SupplierCoverageService $coverage): void
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
            // MVP: не делаем реальный поиск. Просто считаем текущее coverage и
            // фиксируем итог. Структура позволит заменить на реальный сбор,
            // не меняя интерфейс.
            $result = $coverage->checkCoverage($run->domain_id, $run->product_type_id);

            $status = $result['is_sufficient'] ? 'success_covered' : 'exhausted';
            $run->update([
                'status' => $status,
                'iterations_used' => 0,
                'suppliers_found' => 0,
                'finished_at' => now(),
            ]);

            Log::info('DiscoverSuppliersForPair: finished (stub)', [
                'run_id' => $run->id,
                'domain_id' => $run->domain_id,
                'product_type_id' => $run->product_type_id,
                'available' => $result['available'],
                'threshold' => $result['threshold'],
                'status' => $status,
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error' => substr($e->getMessage(), 0, 2000),
            ]);
            throw $e;
        }

        // Запускаем следующую queued (FIFO) если есть.
        DiscoveryOrchestratorJob::dispatch();
    }
}
