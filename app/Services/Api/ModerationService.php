<?php

namespace App\Services\Api;

use App\Models\Api\ApiSubmission;
use App\Models\Api\ClientCategoryCandidate;
use App\Models\Api\RequestItemStaging;
use App\Models\Api\RequestStaging;
use App\Models\BalanceHold;
use Illuminate\Support\Facades\DB;

/**
 * Сервис модерации API-заявок (§5 спеки).
 *
 * Ответственность:
 *  - Переводы позиций staging: classified → accepted | rejected.
 *  - Изменение классификации модератором (reclassify).
 *  - Feedback-loop в client_category_candidates (§4.4).
 *  - Финализация модерации: при полной обработке всех позиций →
 *    собрать rejected_summary, удалить rejected staging items, разморозить
 *    их holds, перевести submission в status=ready.
 */
class ModerationService
{
    /** Справочник причин отклонения (§11.8). */
    public const REJECT_REASONS = [
        'b2c_consumer_goods' => ['Заявки на товары массового потребления не обрабатываются', false],
        'out_of_scope' => ['Позиция не попадает в таксономию B2B-закупок', false],
        'insufficient_data' => ['Недостаточно данных для классификации', true],
        'duplicate' => ['Идентичная позиция уже в активной submission', true],
        'moderator_rejected' => ['Отклонено модератором', false],
    ];

    /**
     * Подтвердить позицию «как есть» (classified → accepted).
     * Feedback: hit_count++ если есть «совпавший» candidate.
     */
    public function approveItem(RequestItemStaging $item): void
    {
        DB::transaction(function () use ($item) {
            if ($item->item_status !== 'classified') {
                return;
            }
            $this->recordFeedbackForApprove($item);
            $item->update(['item_status' => 'accepted', 'needs_review' => false]);
        });
        $this->maybeFinalize($this->submissionForItem($item));
    }

    /**
     * Одобрить батчем все green-позиции одной submission.
     * Возвращает число обработанных позиций.
     */
    public function approveGreenBatch(ApiSubmission $submission): int
    {
        $n = 0;
        $items = RequestItemStaging::query()
            ->whereHas('staging', fn ($q) => $q->where('api_submission_id', $submission->id))
            ->where('item_status', 'classified')
            ->where('trust_level', 'green')
            ->get();
        foreach ($items as $item) {
            $this->approveItem($item);
            $n++;
        }
        return $n;
    }

    /**
     * Отклонить позицию с кодом причины из §11.8.
     */
    public function rejectItem(RequestItemStaging $item, string $reasonCode, ?string $message): void
    {
        if (!array_key_exists($reasonCode, self::REJECT_REASONS)) {
            throw new \InvalidArgumentException('Unknown rejection reason: ' . $reasonCode);
        }
        DB::transaction(function () use ($item, $reasonCode, $message) {
            if ($item->item_status !== 'classified') {
                return;
            }
            $item->update([
                'item_status' => 'rejected',
                'rejection_reason' => $reasonCode,
                'rejection_message' => $message ?: self::REJECT_REASONS[$reasonCode][0],
                'needs_review' => false,
            ]);
        });
        $this->maybeFinalize($this->submissionForItem($item));
    }

    /**
     * Изменить классификацию (или client_category) позиции модератором.
     * Feedback §4.4.
     */
    public function reclassifyItem(
        RequestItemStaging $item,
        ?int $newProductTypeId,
        ?int $newDomainId,
        ?int $newClientCategoryId = null,
    ): void {
        DB::transaction(function () use ($item, $newProductTypeId, $newDomainId, $newClientCategoryId) {
            $oldProductTypeId = $item->product_type_id;
            $oldDomainId = $item->domain_id;
            $oldClientCategoryId = $item->client_category_id;

            $resultingClientCategoryId = $newClientCategoryId ?: $oldClientCategoryId;

            // Применяем изменения к staging_item.
            $item->update([
                'product_type_id' => $newProductTypeId,
                'domain_id' => $newDomainId,
                'client_category_id' => $resultingClientCategoryId,
                'classification_source' => 'moderator',
                'needs_review' => false,
                'trust_level' => 'green',
            ]);

            // Feedback в candidates — только если есть client_category.
            if ($resultingClientCategoryId !== null) {
                $this->recordFeedbackForReclassify(
                    $resultingClientCategoryId,
                    $oldClientCategoryId === $resultingClientCategoryId ? $oldProductTypeId : null,
                    $oldClientCategoryId === $resultingClientCategoryId ? $oldDomainId : null,
                    $newProductTypeId,
                    $newDomainId,
                );
            }
        });
    }

    /**
     * Финализация модерации (§5.3).
     * Срабатывает когда все staging-items submission в (accepted|rejected).
     */
    public function maybeFinalize(ApiSubmission $submission): bool
    {
        $still = RequestItemStaging::query()
            ->whereHas('staging', fn ($q) => $q->where('api_submission_id', $submission->id))
            ->whereIn('item_status', ['pending', 'classified'])
            ->count();
        if ($still > 0) {
            return false;
        }

        DB::transaction(function () use ($submission) {
            $rejected = RequestItemStaging::query()
                ->whereHas('staging', fn ($q) => $q->where('api_submission_id', $submission->id))
                ->where('item_status', 'rejected')
                ->get();

            $summary = $rejected->map(fn (RequestItemStaging $it) => [
                'client_ref' => $it->client_item_ref,
                'name' => $it->name,
                'reason' => $it->rejection_reason,
                'message' => $it->rejection_message,
                'retryable' => (bool) (self::REJECT_REASONS[$it->rejection_reason][1] ?? false),
            ])->values()->all();

            // Разморозка holds для rejected staging items.
            foreach ($rejected as $it) {
                if ($it->balance_hold_id) {
                    /** @var BalanceHold|null $hold */
                    $hold = BalanceHold::find($it->balance_hold_id);
                    if ($hold && $hold->status === 'held') {
                        $hold->update([
                            'status' => 'released',
                            'released_at' => now(),
                        ]);
                    }
                }
                // Отвязываем hold от staging_item чтобы потом безопасно удалить item.
                $it->update(['balance_hold_id' => null]);
            }

            // Удаляем rejected staging items (спека §2.3).
            $rejected->each(fn (RequestItemStaging $it) => $it->delete());

            $acceptedCount = RequestItemStaging::query()
                ->whereHas('staging', fn ($q) => $q->where('api_submission_id', $submission->id))
                ->where('item_status', 'accepted')
                ->count();

            $submission->update([
                'status' => 'ready',
                'status_changed_at' => now(),
                'ready_at' => now(),
                'items_accepted' => $acceptedCount,
                'items_rejected' => count($summary),
                'rejected_summary' => $summary,
            ]);

            // request_staging переход (accepted позиции пойдут в pool_ready / awaiting_suppliers).
            $staging = RequestStaging::where('api_submission_id', $submission->id)->first();
            if ($staging) {
                $staging->update([
                    'stage' => $acceptedCount > 0 ? 'moderation_done' : 'finalised',
                ]);
            }
        });

        // Пул-пайплайн (§6.2): для accepted позиций проверяем coverage и
        // при необходимости запускаем Discovery. Вне транзакции — т.к. пишет
        // в reports (cross-DB) и стартует jobs.
        $submission->refresh();
        if ($submission->items_accepted > 0) {
            app(SupplierPoolService::class)->applyToSubmission($submission);
        }

        return true;
    }

    /**
     * Hit-count candidate при approve. Пытаемся найти кандидата,
     * соответствующего текущей классификации позиции.
     */
    private function recordFeedbackForApprove(RequestItemStaging $item): void
    {
        if ($item->client_category_id === null || $item->product_type_id === null) {
            return;
        }
        $candidate = ClientCategoryCandidate::query()
            ->where('client_category_id', $item->client_category_id)
            ->where('product_type_id', $item->product_type_id)
            ->where(function ($q) use ($item) {
                $q->where('domain_id', $item->domain_id)
                  ->orWhereNull('domain_id');
            })
            ->first();

        if (!$candidate) {
            return;
        }
        $candidate->increment('hit_count');
        $candidate->last_hit_at = now();
        // §4.4: ai_suggested с hit_count≥10 → становится learned.
        if ($candidate->source === 'ai_suggested' && $candidate->hit_count >= 10) {
            $candidate->source = 'learned';
        }
        $candidate->save();
    }

    /**
     * Feedback при изменении классификации.
     *  - Уменьшаем hit_count старого candidate.
     *  - Увеличиваем hit_count нового candidate, либо создаём новый manual.
     */
    private function recordFeedbackForReclassify(
        int $clientCategoryId,
        ?int $oldProductTypeId,
        ?int $oldDomainId,
        ?int $newProductTypeId,
        ?int $newDomainId,
    ): void {
        // -1 старому (если был).
        if ($oldProductTypeId !== null) {
            ClientCategoryCandidate::query()
                ->where('client_category_id', $clientCategoryId)
                ->where('product_type_id', $oldProductTypeId)
                ->where(function ($q) use ($oldDomainId) {
                    $q->where('domain_id', $oldDomainId)->orWhereNull('domain_id');
                })
                ->update([
                    'hit_count' => DB::raw('GREATEST(0, hit_count - 1)'),
                ]);
        }

        // +1 новому или создать manual.
        if ($newProductTypeId === null) {
            return;
        }
        $candidate = ClientCategoryCandidate::query()
            ->where('client_category_id', $clientCategoryId)
            ->where('product_type_id', $newProductTypeId)
            ->where(function ($q) use ($newDomainId) {
                $q->where('domain_id', $newDomainId)->orWhereNull('domain_id');
            })
            ->first();
        if ($candidate) {
            $candidate->increment('hit_count');
            $candidate->last_hit_at = now();
            $candidate->is_active = true;
            $candidate->save();
        } else {
            ClientCategoryCandidate::create([
                'client_category_id' => $clientCategoryId,
                'product_type_id' => $newProductTypeId,
                'domain_id' => $newDomainId,
                'priority' => 1,
                'confidence' => 0.95,
                'source' => 'manual',
                'is_active' => true,
                'hit_count' => 1,
                'last_hit_at' => now(),
            ]);
        }
    }

    private function submissionForItem(RequestItemStaging $item): ApiSubmission
    {
        $staging = RequestStaging::findOrFail($item->request_staging_id);
        return ApiSubmission::findOrFail($staging->api_submission_id);
    }
}
