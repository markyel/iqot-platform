<?php

namespace App\Services\Api;

use App\Jobs\Api\CleanupPromotedStagingJob;
use App\Models\Api\ApiSubmission;
use App\Models\Api\RequestItemStaging;
use App\Models\Api\RequestStaging;
use App\Models\BalanceHold;
use App\Models\ExternalRequest;
use App\Models\ExternalRequestItem;
use Illuminate\Support\Facades\Cache;
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
        if ((bool) config('services.api_moderation.partial_promotion', true)) {
            return $this->promoteReadyItems($submission);
        }

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
     * Частичный промоушен: все pool_ready-позиции submission — в reports сейчас.
     * Первая партия создаёт reports.request (promote), следующие дописываются в ту же
     * заявку (append). Лок на submission: модерация, SLA и RecheckAwaitingSuppliers
     * могут прийти одновременно — без него первая партия создала бы две заявки.
     *
     * @return array{status:string, internal_request_id?:int, items?:int, reason?:string}
     */
    public function promoteReadyItems(ApiSubmission $submission): array
    {
        $lock = Cache::lock("api:promote:{$submission->id}", 120);
        if (!$lock->block(30)) {
            return ['status' => 'skipped', 'reason' => 'locked'];
        }

        try {
            $submission->refresh();
            if (!in_array($submission->status, ['ready', 'ready_minimum', 'processing'], true)) {
                return ['status' => 'skipped', 'reason' => 'submission_status_not_ready'];
            }

            $items = RequestItemStaging::query()
                ->whereHas('staging', fn ($q) => $q->where('api_submission_id', $submission->id))
                ->where('item_status', 'pool_ready')
                ->orderBy('position_number')
                ->get();
            if ($items->isEmpty()) {
                $this->cleanupIfSettled($submission);
                return ['status' => 'skipped', 'reason' => 'no_pool_ready_items'];
            }

            return $submission->internal_request_id === null
                ? $this->promote($submission, $items)
                : $this->append($submission, $items);
        } finally {
            $lock->release();
        }
    }

    /**
     * Дописать позиции в уже созданную reports.request этой submission.
     * Идемпотентно по position_number: если шаг 2 прошлого append упал, позиции в
     * reports уже есть — переиспользуем их, а не создаём дубли.
     *
     * @param \Illuminate\Support\Collection<int,RequestItemStaging> $items
     * @return array{status:string, internal_request_id:int, items:int}
     */
    private function append(ApiSubmission $submission, $items): array
    {
        $requestId = (int) $submission->internal_request_id;

        $mapping = DB::connection('reports')->transaction(function () use ($requestId, $items) {
            $existing = ExternalRequestItem::query()
                ->where('request_id', $requestId)
                ->get()
                ->keyBy('position_number');

            $mapping = [];
            foreach ($items as $it) {
                $ri = $existing[$it->position_number] ?? ExternalRequestItem::create(
                    $this->reportsItemAttributes($requestId, $it)
                );
                $mapping[] = ['staging_id' => $it->id, 'reports_id' => $ri->id];
            }

            ExternalRequest::query()->where('id', $requestId)->update([
                'total_items' => ExternalRequestItem::query()->where('request_id', $requestId)->count(),
            ]);

            return $mapping;
        });

        DB::connection(config('database.default'))->transaction(function () use ($submission, $mapping) {
            $this->linkStaging($mapping);
            $submission->update(['status_changed_at' => now()]);
        });

        $this->cleanupIfSettled($submission);

        return [
            'status' => 'appended',
            'internal_request_id' => $requestId,
            'items' => count($mapping),
        ];
    }

    /**
     * staging → promoted + привязка заморозки к позиции reports.
     *
     * @param array<int,array{staging_id:int, reports_id:int}> $mapping
     */
    private function linkStaging(array $mapping): void
    {
        foreach ($mapping as $m) {
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
    }

    /**
     * Все позиции submission дошли до финала (promoted/удалены) → staging можно
     * закрывать и чистить. Пока какие-то позиции в пути — staging нужен
     * SubmissionReadService (смешанное состояние читается из staging).
     */
    public function cleanupIfSettled(ApiSubmission $submission): void
    {
        $staging = RequestStaging::query()->where('api_submission_id', $submission->id)->first();
        if (!$staging) {
            return;
        }
        $inFlight = RequestItemStaging::query()
            ->where('request_staging_id', $staging->id)
            ->whereIn('item_status', ModerationService::IN_FLIGHT_STATUSES)
            ->exists();
        if ($inFlight) {
            return;
        }
        if ($submission->internal_request_id !== null) {
            $staging->update(['stage' => 'finalised']);
            CleanupPromotedStagingJob::dispatch($submission->id)
                ->delay(now()->addMinutes(self::CLEANUP_DELAY_MINUTES));
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function reportsItemAttributes(int $requestId, RequestItemStaging $it): array
    {
        return [
            'request_id' => $requestId,
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
        ];
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
                $ri = ExternalRequestItem::create($this->reportsItemAttributes($request->id, $it));
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

                $this->linkStaging($reportsResult['items']);
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

        // === Шаг 3. Отложенная чистка promoted staging items — когда в пути не осталось
        // позиций (частичный промоушен: остальные ещё на модерации/в ожидании пула). ===
        $this->cleanupIfSettled($submission->refresh());

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
