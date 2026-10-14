<?php

namespace App\Console\Commands;

use App\Models\Api\ApiSubmission;
use App\Models\Api\RequestItemStaging;
use App\Models\BalanceHold;
use App\Services\Api\ModerationService;
use App\Services\Api\PromotionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * SLA модерации API-заявок (решение 2026-10-14). Позиции не должны месяцами висеть
 * на модерации с замороженными деньгами клиента и без рассылки.
 *
 *  1. Истёк срок сбора (deadline_at submission) до рассылки позиции → снять позицию
 *     с разморозкой (deadline_expired): classified — отклонить, принятые, но не
 *     разосланные (accepted/awaiting_suppliers/pool_ready) — убрать из заявки.
 *  2. Позиция в classified дольше sla_hours → авто-решение: тип товара определён —
 *     принять (уйдёт в пул/рассылку частичным промоушеном), не определён — отклонить
 *     с разморозкой (moderation_timeout). Авто-решения классификатор не «учат».
 *
 * Флаг API_MODERATION_SLA_ENABLED (services.api_moderation.sla_enabled); --force
 * игнорирует флаг, --dry-run только считает.
 */
class ApiModerationSlaCommand extends Command
{
    protected $signature = 'api:moderation-sla
        {--dry-run : Ничего не менять, только посчитать}
        {--force : Запустить даже при выключенном флаге}';

    protected $description = 'SLA модерации API-заявок: авто-решение по неразобранным позициям, снятие позиций с истёкшим сроком';

    private const PRE_DISPATCH = ['accepted', 'awaiting_suppliers', 'pool_ready'];

    public function handle(ModerationService $moderation, PromotionService $promotion): int
    {
        $dry = (bool) $this->option('dry-run');
        if (!$dry && !$this->option('force') && !config('services.api_moderation.sla_enabled')) {
            $this->warn('api:moderation-sla выключен (API_MODERATION_SLA_ENABLED=false). --force / --dry-run для ручного запуска.');
            return self::SUCCESS;
        }

        $slaHours = max(1, (int) config('services.api_moderation.sla_hours', 24));
        $defaultDays = max(1, (int) config('services.billing.api_hold.default_lifetime_days', 30));
        $slaCutoff = now()->subHours($slaHours);

        $items = RequestItemStaging::query()
            ->whereIn('item_status', array_merge(['classified'], self::PRE_DISPATCH))
            ->with('staging.submission')
            ->get();

        $stats = ['deadline_rejected' => 0, 'deadline_dropped' => 0, 'auto_approved' => 0, 'timeout_rejected' => 0, 'held_released' => 0.0];
        $touched = [];

        foreach ($items as $item) {
            /** @var ApiSubmission|null $submission */
            $submission = $item->staging?->submission;
            if (!$submission || !in_array($submission->status, ['processing', 'ready', 'ready_minimum'], true)) {
                continue;
            }
            $deadline = $submission->deadline_at
                ? Carbon::parse($submission->deadline_at)
                : Carbon::parse($submission->created_at)->addDays($defaultDays);
            $holdAmount = $item->balance_hold_id ? (float) (BalanceHold::find($item->balance_hold_id)?->amount ?? 0) : 0.0;

            try {
                if ($deadline->isPast()) {
                    if ($item->item_status === 'classified') {
                        $stats['deadline_rejected']++;
                        if (!$dry) {
                            $moderation->rejectItem($item, 'deadline_expired', null, false);
                        }
                    } else {
                        $stats['deadline_dropped']++;
                        if (!$dry) {
                            $moderation->dropAcceptedItem(
                                $item,
                                'deadline_expired',
                                ModerationService::AUTO_REJECT_REASONS['deadline_expired'][0],
                                BalanceHold::RELEASE_DEADLINE_EXPIRED
                            );
                        }
                    }
                    $stats['held_released'] += $holdAmount;
                    $touched[$submission->id] = $submission;
                    continue;
                }

                if ($item->item_status !== 'classified' || $item->created_at === null || $item->created_at->gt($slaCutoff)) {
                    continue;
                }

                if ($item->product_type_id !== null) {
                    $stats['auto_approved']++;
                    if (!$dry) {
                        $moderation->approveItem($item, false, false);
                    }
                } else {
                    $stats['timeout_rejected']++;
                    $stats['held_released'] += $holdAmount;
                    if (!$dry) {
                        $moderation->rejectItem($item, 'moderation_timeout', null, false);
                    }
                }
                $touched[$submission->id] = $submission;
            } catch (\Throwable $e) {
                $this->error("staging item #{$item->id}: " . $e->getMessage());
                Log::error('api:moderation-sla: item failed', ['staging_item_id' => $item->id, 'error' => $e->getMessage()]);
            }
        }

        // Продвигаем затронутые заявки один раз: пул → промоушен готовых → финализация.
        if (!$dry) {
            foreach ($touched as $submission) {
                try {
                    $submission->refresh();
                    $moderation->advance($submission);
                    $this->closeIfEmpty($submission->refresh());
                    $promotion->cleanupIfSettled($submission);
                } catch (\Throwable $e) {
                    Log::error('api:moderation-sla: advance failed', ['submission_id' => $submission->id, 'error' => $e->getMessage()]);
                }
            }
        }

        $this->info(($dry ? '[dry-run] ' : '') . sprintf(
            'Срок истёк: отклонено %d, снято принятых %d; SLA: авто-принято %d, отклонено без типа %d; разморозка %.2f ₽; заявок затронуто %d.',
            $stats['deadline_rejected'], $stats['deadline_dropped'], $stats['auto_approved'],
            $stats['timeout_rejected'], $stats['held_released'], count($touched)
        ));
        if (!$dry && $touched !== []) {
            Log::info('api:moderation-sla', $stats + ['submissions' => count($touched)]);
        }

        return self::SUCCESS;
    }

    /**
     * Заявка без единой позиции в пути и без промоушена — закрываем как rejected_all
     * (иначе пустая заявка вечно висит в фильтре status=ready).
     */
    private function closeIfEmpty(ApiSubmission $submission): void
    {
        if ($submission->internal_request_id !== null || $submission->status !== 'ready') {
            return;
        }
        $remaining = RequestItemStaging::query()
            ->whereHas('staging', fn ($q) => $q->where('api_submission_id', $submission->id))
            ->whereIn('item_status', ModerationService::IN_FLIGHT_STATUSES)
            ->exists();
        if (!$remaining) {
            $submission->update(['stage' => 'rejected_all', 'status_changed_at' => now()]);
        }
    }
}
