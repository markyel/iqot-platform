<?php

namespace App\Jobs\Api;

use App\Models\Api\ApiSubmission;
use App\Models\Api\RequestItemStaging;
use App\Models\Api\RequestStaging;
use App\Models\Api\SupplierDiscoveryRun;
use App\Models\BalanceHold;
use App\Services\Api\ModerationService;
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
 *   - product_type_id IS NULL → отклонить сразу (reason='unclassified'): без типа
 *     продукта coverage не считается и discovery-run по паре не создаётся
 *     (см. SupplierPoolService::applyToSubmission) → forward-пути нет, иначе позиция
 *     висит вечно и блокирует промоушен всей заявки;
 *   - coverage стал достаточным → item_status='pool_ready';
 *   - прошло >14 дней И все последние supplier_discovery_runs по паре exhausted
 *     → отклонить с rejection_reason='no_suppliers_available', hold release,
 *     записать в api_submissions.rejected_summary.
 *
 * После любого отклонения дёргаем PromotionService::promoteIfReady — снятие
 * блокирующей позиции может сделать заявку готовой к промоушену.
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
        // null-product_type позиции НЕ исключаем (раньше так и зависали): их
        // обрабатывает processItem отдельной веткой — отклоняет.
        $items = RequestItemStaging::query()
            ->where('item_status', 'awaiting_suppliers')
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
        // Без типа продукта позиция не имеет пути вперёд (coverage не считается,
        // discovery-run не создаётся) — отклоняем сразу и пробуем промоутить заявку.
        if ($item->product_type_id === null) {
            $submission = app(ModerationService::class)->dropAcceptedItem(
                $item,
                'unclassified',
                'Позиция не классифицирована (не задан тип продукта) — требуется ручная переклассификация.',
                BalanceHold::RELEASE_MODERATION_REJECTED
            );
            $this->promoteOrFinalize($submission);
            return;
        }

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

        $submission = app(ModerationService::class)->dropAcceptedItem($item, 'no_suppliers_available', 'Не удалось собрать пул поставщиков за 14 дней.', BalanceHold::RELEASE_NO_SUPPLIERS);
        $this->promoteOrFinalize($submission);
    }

    /**
     * После снятия блокирующей позиции: пробуем промоушен; если заявка не
     * промоутнулась И активных позиций не осталось — закрываем как rejected_all
     * (иначе пустая заявка вечно висит в фильтре status=ready).
     */
    private function promoteOrFinalize(?ApiSubmission $submission): void
    {
        if (!$submission) {
            return;
        }

        $result = app(\App\Services\Api\PromotionService::class)->promoteIfReady($submission);
        if (($result['status'] ?? null) === 'promoted') {
            return;
        }

        $submission->refresh();
        if ($submission->internal_request_id !== null) {
            return;
        }

        $remaining = RequestItemStaging::query()
            ->whereHas('staging', fn ($q) => $q->where('api_submission_id', $submission->id))
            ->whereIn('item_status', ['pending', 'classified', 'accepted', 'awaiting_suppliers', 'pool_ready'])
            ->count();

        if ($remaining === 0 && $submission->status === 'ready') {
            $submission->update([
                'stage' => 'rejected_all',
                'status_changed_at' => now(),
            ]);
        }
    }
}
