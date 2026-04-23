<?php

namespace App\Services\Api;

use App\Jobs\Api\CleanupPromotedStagingJob;
use App\Models\Api\ApiSubmission;
use App\Models\Api\RequestItemStaging;
use App\Models\Api\RequestStaging;
use App\Models\BalanceHold;
use App\Models\ExternalRequest;
use App\Models\ExternalRequestItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Промоушен API-submission из staging (iqot) в боевую заявку (reports).
 * Спека §6.4–6.5.
 *
 * Алгоритм:
 *   1. reports-транзакция: INSERT requests + request_items.
 *   2. iqot-транзакция: UPDATE api_submission.internal_request_id + promoted_at,
 *      UPDATE staging.item_status=promoted + promoted_request_item_id,
 *      UPDATE balance_holds.request_item_id.
 *   3. Dispatch CleanupPromotedStagingJob с задержкой 5 мин.
 *
 * Триггер (вызывается извне):
 *   - Когда все accepted-позиции submission перешли в pool_ready.
 */
class PromotionService
{
    public const CLEANUP_DELAY_MINUTES = 5;

    /**
     * Проверяет готовность submission и запускает промоушен.
     *
     * @return array{status:string, internal_request_id?:int, items?:int, reason?:string}
     */
    public function promoteIfReady(ApiSubmission $submission): array
    {
        if ($submission->internal_request_id !== null) {
            return ['status' => 'already_promoted', 'internal_request_id' => $submission->internal_request_id];
        }
        if (!in_array($submission->status, ['ready', 'ready_minimum', 'processing'], true)) {
            return ['status' => 'skipped', 'reason' => 'submission_status_not_ready'];
        }

        $items = RequestItemStaging::query()
            ->whereHas('staging', fn ($q) => $q->where('api_submission_id', $submission->id))
            ->whereIn('item_status', ['accepted', 'awaiting_suppliers', 'pool_ready'])
            ->get();

        if ($items->isEmpty()) {
            return ['status' => 'skipped', 'reason' => 'no_items'];
        }

        // Все позиции должны быть в pool_ready.
        $notReady = $items->filter(fn ($it) => $it->item_status !== 'pool_ready')->count();
        if ($notReady > 0) {
            return ['status' => 'skipped', 'reason' => 'some_items_not_pool_ready'];
        }

        return $this->promote($submission, $items);
    }

    /**
     * @param \Illuminate\Support\Collection<int,RequestItemStaging> $items
     * @return array{status:string, internal_request_id:int, items:int}
     */
    public function promote(ApiSubmission $submission, $items): array
    {
        // === Шаг 1. reports-транзакция: INSERT request + items. ===
        /** @var array{request_id:int, items:array<int,array{staging_id:int, reports_id:int}>} $reportsResult */
        $reportsResult = DB::connection('reports')->transaction(function () use ($submission, $items) {
            /** @var ExternalRequest $request */
            $request = ExternalRequest::create([
                'user_id' => $submission->client->user_id,
                'client_organization_id' => $submission->client_organization_id,
                'request_number' => $this->generateRequestNumber(),
                'title' => $submission->client_ref
                    ? "API: {$submission->client_ref}"
                    : "API: sub_{$submission->external_id}",
                'status' => ExternalRequest::STATUS_ACTIVE,
                'collection_deadline' => $submission->deadline_at,
                'total_items' => $items->count(),
                'items_with_offers' => 0,
                'source' => 'api',
                'api_submission_external_id' => $submission->external_id,
            ]);

            $mapping = [];
            foreach ($items as $it) {
                /** @var ExternalRequestItem $ri */
                $ri = ExternalRequestItem::create([
                    'request_id' => $request->id,
                    'position_number' => $it->position_number,
                    'name' => $it->name,
                    'brand' => $it->brand,
                    'article' => $it->article,
                    'quantity' => $it->quantity,
                    'unit' => $it->unit,
                    'description' => $it->description,
                    'status' => ExternalRequestItem::STATUS_PENDING,
                    'offers_count' => 0,
                    'product_type_id' => $it->product_type_id,
                    'domain_id' => $it->domain_id,
                    'type_confidence' => $it->type_confidence,
                    'domain_confidence' => $it->domain_confidence,
                    'classification_needs_review' => (bool) $it->needs_review,
                ]);
                $mapping[] = ['staging_id' => $it->id, 'reports_id' => $ri->id];
            }

            return ['request_id' => $request->id, 'items' => $mapping];
        });

        // === Шаг 2. iqot-транзакция: связать submission/staging/holds с reports. ===
        try {
            DB::connection(config('database.default'))->transaction(function () use ($submission, $reportsResult) {
                $submission->update([
                    'internal_request_id' => $reportsResult['request_id'],
                    'promoted_at' => now(),
                    'stage' => 'dispatching',
                    'status_changed_at' => now(),
                ]);

                foreach ($reportsResult['items'] as $m) {
                    /** @var RequestItemStaging|null $staging */
                    $staging = RequestItemStaging::find($m['staging_id']);
                    if (!$staging) {
                        continue;
                    }
                    $staging->update([
                        'item_status' => 'promoted',
                        'promoted_request_item_id' => $m['reports_id'],
                    ]);

                    if ($staging->balance_hold_id) {
                        BalanceHold::query()
                            ->where('id', $staging->balance_hold_id)
                            ->update([
                                'request_item_id' => $m['reports_id'],
                            ]);
                    }
                }

                // Staging submission.
                RequestStaging::query()
                    ->where('api_submission_id', $submission->id)
                    ->update(['stage' => 'finalised']);
            });
        } catch (\Throwable $e) {
            // Шаг 1 успешен, Шаг 2 упал → ReconcilePromotionJob подберёт.
            Log::error('PromotionService: step 2 failed, reconciler will fix', [
                'submission_id' => $submission->id,
                'reports_request_id' => $reportsResult['request_id'],
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // === Шаг 3. Отложенная чистка promoted staging items. ===
        CleanupPromotedStagingJob::dispatch($submission->id)
            ->delay(now()->addMinutes(self::CLEANUP_DELAY_MINUTES));

        return [
            'status' => 'promoted',
            'internal_request_id' => $reportsResult['request_id'],
            'items' => count($reportsResult['items']),
        ];
    }

    /**
     * Повторяет Шаг 2 для «осиротевших» reports.requests (спека §6.5 сценарий B).
     * Вызывается из ReconcilePromotionJob.
     */
    public function reconcile(string $externalId, int $reportsRequestId): bool
    {
        $submission = ApiSubmission::where('external_id', $externalId)->first();
        if (!$submission) {
            Log::warning('Reconcile: submission not found', ['external_id' => $externalId]);
            return false;
        }
        if ($submission->internal_request_id === $reportsRequestId) {
            return true;
        }
        if ($submission->status === 'cancelled') {
            // Сценарий C: отменяем осиротевший reports.request.
            ExternalRequest::query()->where('id', $reportsRequestId)->update([
                'status' => ExternalRequest::STATUS_CANCELLED,
            ]);
            Log::info('Reconcile: cancelled orphan reports.request', [
                'submission_id' => $submission->id,
                'reports_request_id' => $reportsRequestId,
            ]);
            return true;
        }

        // Подгружаем позиции из reports.request_items чтобы построить mapping.
        $reportsItems = ExternalRequestItem::query()
            ->where('request_id', $reportsRequestId)
            ->orderBy('position_number')
            ->get();

        $stagingItems = RequestItemStaging::query()
            ->whereHas('staging', fn ($q) => $q->where('api_submission_id', $submission->id))
            ->orderBy('position_number')
            ->get()
            ->keyBy('position_number');

        DB::connection(config('database.default'))->transaction(function () use ($submission, $reportsRequestId, $reportsItems, $stagingItems) {
            $submission->update([
                'internal_request_id' => $reportsRequestId,
                'promoted_at' => now(),
                'stage' => 'dispatching',
                'status_changed_at' => now(),
            ]);

            foreach ($reportsItems as $ri) {
                $staging = $stagingItems[$ri->position_number] ?? null;
                if (!$staging) {
                    continue;
                }
                $staging->update([
                    'item_status' => 'promoted',
                    'promoted_request_item_id' => $ri->id,
                ]);
                if ($staging->balance_hold_id) {
                    BalanceHold::query()->where('id', $staging->balance_hold_id)->update([
                        'request_item_id' => $ri->id,
                    ]);
                }
            }

            RequestStaging::query()
                ->where('api_submission_id', $submission->id)
                ->update(['stage' => 'finalised']);
        });

        Log::info('Reconcile: linked orphan', [
            'submission_id' => $submission->id,
            'reports_request_id' => $reportsRequestId,
        ]);
        return true;
    }

    private function generateRequestNumber(): string
    {
        return 'API-' . now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
