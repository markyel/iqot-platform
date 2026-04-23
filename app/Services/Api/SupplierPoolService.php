<?php

namespace App\Services\Api;

use App\Jobs\Api\DiscoveryOrchestratorJob;
use App\Models\Api\ApiSubmission;
use App\Models\Api\RequestItemStaging;
use App\Models\Api\SupplierDiscoveryRun;
use Illuminate\Support\Facades\DB;

/**
 * Применение пайплайна пула к accepted-позициям submission (§6.2, §7.4).
 *
 * Алгоритм по позиции:
 *   coverage.is_sufficient → item_status='pool_ready'
 *   иначе → item_status='awaiting_suppliers' + создать queued supplier_discovery_run
 *           (с учётом cooldown — не дублируем работу).
 */
class SupplierPoolService
{
    public const COOLDOWN_SUCCESS_DAYS = 7;
    public const COOLDOWN_EXHAUSTED_DAYS = 30;
    public const COOLDOWN_FAILED_DAYS = 1;

    public function __construct(
        private readonly SupplierCoverageService $coverage,
    ) {
    }

    /**
     * Применить пайплайн ко всем accepted-позициям submission.
     * Возвращает сводку.
     *
     * @return array{pool_ready:int, awaiting_suppliers:int, discovery_runs_started:int}
     */
    public function applyToSubmission(ApiSubmission $submission): array
    {
        $items = RequestItemStaging::query()
            ->whereHas('staging', fn ($q) => $q->where('api_submission_id', $submission->id))
            ->where('item_status', 'accepted')
            ->get();

        $poolReady = 0;
        $awaiting = 0;
        $startedRuns = 0;

        foreach ($items as $item) {
            if ($item->product_type_id === null) {
                // Без product_type coverage не считается; отправляем в awaiting с пометкой.
                $item->update(['item_status' => 'awaiting_suppliers']);
                $awaiting++;
                continue;
            }

            $result = $this->coverage->checkCoverage($item->domain_id, $item->product_type_id);
            if ($result['is_sufficient']) {
                $item->update(['item_status' => 'pool_ready']);
                $poolReady++;
            } else {
                $item->update(['item_status' => 'awaiting_suppliers']);
                $awaiting++;

                if ($this->ensureDiscoveryRun($item->domain_id, $item->product_type_id, $submission->external_id)) {
                    $startedRuns++;
                }
            }
        }

        return [
            'pool_ready' => $poolReady,
            'awaiting_suppliers' => $awaiting,
            'discovery_runs_started' => $startedRuns,
        ];
    }

    /**
     * Создаёт queued supplier_discovery_run если нет активного и cooldown истёк.
     * Возвращает true — создали (или уже был queued/running и ждёт обработки).
     */
    public function ensureDiscoveryRun(?int $domainId, int $productTypeId, ?string $triggeringExternalId): bool
    {
        return (bool) DB::connection('reports')->transaction(function () use ($domainId, $productTypeId, $triggeringExternalId) {
            // Есть ли активный run на эту пару — не дублируем.
            $active = DB::connection('reports')
                ->table('supplier_discovery_runs')
                ->where('product_type_id', $productTypeId)
                ->when($domainId === null, fn ($q) => $q->whereNull('domain_id'), fn ($q) => $q->where('domain_id', $domainId))
                ->whereIn('status', ['queued', 'running'])
                ->exists();
            if ($active) {
                return false; // уже в работе
            }

            // Cooldown: последний завершённый run.
            $last = DB::connection('reports')
                ->table('supplier_discovery_runs')
                ->where('product_type_id', $productTypeId)
                ->when($domainId === null, fn ($q) => $q->whereNull('domain_id'), fn ($q) => $q->where('domain_id', $domainId))
                ->whereIn('status', ['success_covered', 'success_partial', 'exhausted', 'failed'])
                ->orderByDesc('finished_at')
                ->first();

            if ($last && $last->finished_at) {
                $finishedAt = new \DateTimeImmutable($last->finished_at);
                $cooldownDays = match ($last->status) {
                    'success_covered', 'success_partial' => self::COOLDOWN_SUCCESS_DAYS,
                    'exhausted' => self::COOLDOWN_EXHAUSTED_DAYS,
                    'failed' => self::COOLDOWN_FAILED_DAYS,
                    default => 0,
                };
                $cooldownUntil = $finishedAt->modify("+{$cooldownDays} days");
                if (new \DateTimeImmutable() < $cooldownUntil) {
                    return false; // cooldown ещё не истёк
                }
            }

            SupplierDiscoveryRun::create([
                'domain_id' => $domainId,
                'product_type_id' => $productTypeId,
                'status' => 'queued',
                'trigger_source' => 'api_submission',
                'triggering_submission_external_id' => $triggeringExternalId,
            ]);

            // Асинхронный оркестратор.
            DiscoveryOrchestratorJob::dispatch();
            return true;
        });
    }
}
