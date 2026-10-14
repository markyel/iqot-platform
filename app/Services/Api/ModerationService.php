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
     * Автоматические причины (SLA модерации / срок сбора). В выпадающий список
     * модератора не попадают, но допустимы в rejectItem и в rejected_summary.
     */
    public const AUTO_REJECT_REASONS = [
        'moderation_timeout' => ['Позиция не прошла модерацию за отведённое время (тип товара не определён)', true],
        'deadline_expired' => ['Срок сбора предложений истёк до начала рассылки по позиции', true],
    ];

    /** Статусы staging-позиции «ещё в пути» (не промоутнута и не отклонена). */
    public const IN_FLIGHT_STATUSES = ['pending', 'classified', 'accepted', 'awaiting_suppliers', 'pool_ready'];

    /**
     * Подтвердить позицию «как есть» (classified → accepted).
     * Feedback: hit_count++ если есть «совпавший» candidate (кроме авто-решений SLA —
     * их не учим, иначе классификатор «выучит» сам себя).
     *
     * $runPipeline=false — для батчей: вызывающий сам запустит пул/промоушен один раз.
     */
    public function approveItem(RequestItemStaging $item, bool $runPipeline = true, bool $learn = true): void
    {
        DB::transaction(function () use ($item, $learn) {
            if ($item->item_status !== 'classified') {
                return;
            }
            if ($learn) {
                $this->recordFeedbackForApprove($item);
            }
            $item->update(['item_status' => 'accepted', 'needs_review' => false]);
        });
        if ($runPipeline) {
            $this->advance($this->submissionForItem($item));
        }
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
            $this->approveItem($item, false);
            $n++;
        }
        if ($n > 0) {
            $this->advance($submission);
        }
        return $n;
    }

    /**
     * Отклонить позицию с кодом причины из §11.8 (или авто-причины SLA).
     * Заморозка снимается СРАЗУ (а не при финализации всей заявки): деньги клиента
     * не должны ждать, пока модерируют соседние позиции.
     */
    public function rejectItem(RequestItemStaging $item, string $reasonCode, ?string $message, bool $runPipeline = true): void
    {
        $reasons = self::REJECT_REASONS + self::AUTO_REJECT_REASONS;
        if (!array_key_exists($reasonCode, $reasons)) {
            throw new \InvalidArgumentException('Unknown rejection reason: ' . $reasonCode);
        }
        DB::transaction(function () use ($item, $reasonCode, $message, $reasons) {
            if ($item->item_status !== 'classified') {
                return;
            }
            $item->update([
                'item_status' => 'rejected',
                'rejection_reason' => $reasonCode,
                'rejection_message' => $message ?: $reasons[$reasonCode][0],
                'needs_review' => false,
            ]);
            $this->releaseItemHold($item, $this->releaseReasonFor($reasonCode));
        });
        if ($runPipeline) {
            $this->advance($this->submissionForItem($item));
        }
    }

    /**
     * Снять позицию, уже прошедшую модерацию, но ещё не разосланную (accepted /
     * awaiting_suppliers / pool_ready): разморозка, запись в rejected_summary,
     * удаление staging item (как при финализации §5.3). Для истечения срока сбора
     * и отсутствия поставщиков. Возвращает submission.
     */
    public function dropAcceptedItem(RequestItemStaging $item, string $reasonCode, string $message, string $releaseReason): ?ApiSubmission
    {
        return DB::transaction(function () use ($item, $reasonCode, $message, $releaseReason) {
            $this->releaseItemHold($item, $releaseReason);

            $staging = RequestStaging::find($item->request_staging_id);
            $submission = $staging ? ApiSubmission::find($staging->api_submission_id) : null;

            if ($submission) {
                $summary = is_array($submission->rejected_summary) ? $submission->rejected_summary : [];
                $summary[] = [
                    'client_ref' => $item->client_item_ref,
                    'name' => $item->name,
                    'reason' => $reasonCode,
                    'message' => $message,
                    'retryable' => true,
                ];
                $submission->update([
                    'rejected_summary' => $summary,
                    'items_rejected' => ($submission->items_rejected ?? 0) + 1,
                    'items_accepted' => max(0, ($submission->items_accepted ?? 0) - 1),
                ]);
            }

            $item->update(['balance_hold_id' => null]);
            $item->delete();

            return $submission;
        });
    }

    /**
     * Продвинуть submission после изменения статусов позиций: принятые → пул →
     * промоушен готовых (частично, если включено), затем финализация модерации.
     */
    public function advance(ApiSubmission $submission): void
    {
        if ((bool) config('services.api_moderation.partial_promotion', true)) {
            app(SupplierPoolService::class)->applyToSubmission($submission);
        }
        $this->maybeFinalize($submission->refresh());
    }

    private function releaseItemHold(RequestItemStaging $item, string $releaseReason): void
    {
        if (!$item->balance_hold_id) {
            return;
        }
        /** @var BalanceHold|null $hold */
        $hold = BalanceHold::find($item->balance_hold_id);
        if ($hold && $hold->status === 'held') {
            $hold->release($releaseReason);
        }
    }

    private function releaseReasonFor(string $reasonCode): string
    {
        return match ($reasonCode) {
            'moderation_timeout' => BalanceHold::RELEASE_MODERATION_TIMEOUT,
            'deadline_expired' => BalanceHold::RELEASE_DEADLINE_EXPIRED,
            default => BalanceHold::RELEASE_MODERATION_REJECTED,
        };
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

        // Уже финализирована/отменена (повторный вызов из advance/SLA) — не переписываем
        // rejected_summary и stage второй раз.
        if ($submission->status !== 'processing') {
            return false;
        }

        DB::transaction(function () use ($submission) {
            $rejected = RequestItemStaging::query()
                ->whereHas('staging', fn ($q) => $q->where('api_submission_id', $submission->id))
                ->where('item_status', 'rejected')
                ->get();

            $reasons = self::REJECT_REASONS + self::AUTO_REJECT_REASONS;
            $newEntries = $rejected->map(fn (RequestItemStaging $it) => [
                'client_ref' => $it->client_item_ref,
                'name' => $it->name,
                'reason' => $it->rejection_reason,
                'message' => $it->rejection_message,
                'retryable' => (bool) ($reasons[$it->rejection_reason][1] ?? false),
            ])->values()->all();
            // Дописываем к уже накопленному (dropAcceptedItem мог внести записи раньше).
            $summary = array_merge(
                is_array($submission->rejected_summary) ? $submission->rejected_summary : [],
                $newEntries
            );

            // Разморозка holds для rejected staging items (обычно уже снята в rejectItem —
            // здесь подстраховка для старых данных).
            foreach ($rejected as $it) {
                $this->releaseItemHold($it, $this->releaseReasonFor((string) $it->rejection_reason));
                // Отвязываем hold от staging_item чтобы потом безопасно удалить item.
                $it->update(['balance_hold_id' => null]);
            }

            // Удаляем rejected staging items (спека §2.3).
            $rejected->each(fn (RequestItemStaging $it) => $it->delete());

            $acceptedCount = RequestItemStaging::query()
                ->whereHas('staging', fn ($q) => $q->where('api_submission_id', $submission->id))
                ->whereIn('item_status', ['accepted', 'awaiting_suppliers', 'pool_ready', 'promoted'])
                ->count();

            // stage после финализации:
            //   уже частично промоутнута — dispatching (не откатываем)
            //   all-rejected     — rejected_all
            //   all-accepted / mixed — moderated (пойдёт в pool через applyToSubmission)
            $newStage = $submission->internal_request_id !== null
                ? 'dispatching'
                : ($acceptedCount > 0 ? 'moderated' : 'rejected_all');

            $submission->update([
                'status' => 'ready',
                'stage' => $newStage,
                'status_changed_at' => now(),
                'ready_at' => now(),
                'items_accepted' => $acceptedCount,
                'items_rejected' => count($summary),
                'rejected_summary' => $summary,
            ]);

            // request_staging переход (accepted позиции пойдут в pool_ready / awaiting_suppliers).
            $staging = RequestStaging::where('api_submission_id', $submission->id)->first();
            if ($staging) {
                $inFlight = RequestItemStaging::query()
                    ->where('request_staging_id', $staging->id)
                    ->whereIn('item_status', self::IN_FLIGHT_STATUSES)
                    ->exists();
                $staging->update([
                    'stage' => $inFlight ? 'moderation_done' : 'finalised',
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
