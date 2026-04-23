<?php

namespace App\Jobs\Api;

use App\Models\Api\ApiSubmission;
use App\Models\Api\RequestItemStaging;
use App\Models\Api\RequestStaging;
use App\Models\Api\SupplierDiscoveryRun;
use App\Models\BalanceHold;
use App\Services\Api\SupplierCoverageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RecheckAwaitingSuppliersJob (§6.3).
 *
 * Hourly cron. По позициям в `awaiting_suppliers`:
 *   - coverage стал достаточным → item_status='pool_ready';
 *   - прошло >14 дней И все последние supplier_discovery_runs по паре exhausted
 *     → отклонить с rejection_reason='no_suppliers_available', hold release,
 *     записать в api_submissions.rejected_summary.
 */
class RecheckAwaitingSuppliersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const NO_SUPPLIERS_TIMEOUT_DAYS = 14;

    public $timeout = 300;
    public $tries = 1;

    public function __construct()
    {
        $this->onConnection('database');
    }

    public function handle(SupplierCoverageService $coverage): void
    {
        $items = RequestItemStaging::query()
            ->where('item_status', 'awaiting_suppliers')
            ->whereNotNull('product_type_id')
            ->get();

        foreach ($items as $item) {
            try {
                $this->processItem($item, $coverage);
            } catch (\Throwable $e) {
                Log::error('RecheckAwaitingSuppliers: item failed', [
                    'item_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function processItem(RequestItemStaging $item, SupplierCoverageService $coverage): void
    {
        $result = $coverage->checkCoverage($item->domain_id, $item->product_type_id);
        if ($result['is_sufficient']) {
            $item->update(['item_status' => 'pool_ready']);
            // Проверяем — возможно это была последняя awaiting_suppliers позиция submission,
            // и теперь можно запускать промоушен.
            $staging = RequestStaging::find($item->request_staging_id);
            if ($staging) {
                $submission = ApiSubmission::find($staging->api_submission_id);
                if ($submission) {
                    app(\App\Services\Api\PromotionService::class)->promoteIfReady($submission);
                }
            }
            return;
        }

        // Проверяем таймаут: >14 дней в awaiting_suppliers И все runs exhausted/failed.
        $acceptedAt = $item->updated_at; // момент последнего статус-апдейта (достаточная аппроксимация)
        if (!$acceptedAt || $acceptedAt->gt(now()->subDays(self::NO_SUPPLIERS_TIMEOUT_DAYS))) {
            return;
        }

        $recentOk = SupplierDiscoveryRun::query()
            ->where('product_type_id', $item->product_type_id)
            ->when(
                $item->domain_id === null,
                fn ($q) => $q->whereNull('domain_id'),
                fn ($q) => $q->where('domain_id', $item->domain_id)
            )
            ->whereIn('status', ['success_covered', 'success_partial', 'running', 'queued'])
            ->exists();
        if ($recentOk) {
            return;
        }

        $this->rejectItemAndRelease($item, 'no_suppliers_available', 'Не удалось собрать пул поставщиков за 14 дней.');
    }

    /**
     * Отклонение позиции после ready (§6.3).
     * - item status='rejected', reason='no_suppliers_available', hold release.
     * - Дописываем запись в api_submissions.rejected_summary.
     */
    private function rejectItemAndRelease(RequestItemStaging $item, string $reason, string $message): void
    {
        DB::transaction(function () use ($item, $reason, $message) {
            if ($item->balance_hold_id) {
                /** @var BalanceHold|null $hold */
                $hold = BalanceHold::find($item->balance_hold_id);
                if ($hold && $hold->status === 'held') {
                    $hold->update([
                        'status' => 'released',
                        'released_at' => now(),
                    ]);
                }
            }

            $staging = RequestStaging::find($item->request_staging_id);
            $submissionId = $staging?->api_submission_id;
            $submission = $submissionId ? ApiSubmission::find($submissionId) : null;

            $entry = [
                'client_ref' => $item->client_item_ref,
                'name' => $item->name,
                'reason' => $reason,
                'message' => $message,
                'retryable' => true,
            ];

            if ($submission) {
                $summary = $submission->rejected_summary ?? [];
                if (!is_array($summary)) {
                    $summary = [];
                }
                $summary[] = $entry;
                $submission->update([
                    'rejected_summary' => $summary,
                    'items_rejected' => ($submission->items_rejected ?? 0) + 1,
                    'items_accepted' => max(0, ($submission->items_accepted ?? 0) - 1),
                ]);
            }

            // Удаляем сам staging item (аналогично финализации §5.3).
            $item->update(['balance_hold_id' => null]);
            $item->delete();
        });
    }
}
