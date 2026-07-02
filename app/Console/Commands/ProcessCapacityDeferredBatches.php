<?php

namespace App\Console\Commands;

use App\Jobs\GenerateCampaignJob;
use App\Services\Generate\SenderDailyCapacity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Выпуск отсрочек по капасити прогрева (Phase 3b, за флагом EMAILS_WARMUP_ENABLED).
 *
 * Генератор откладывает в deferred_batches то, на что не хватило дневных лимитов:
 *   - reason='sender_capacity' — остаток пула батча (лимиты ящиков / глобальный потолок);
 *   - reason='ban_containment' — адресаты, чьи письма сняты при бане ящика.
 * Здесь, когда капасити освободилось (новые сутки / рампа подняла лимиты), строки
 * клеймятся accumulating→processing и уходят в GenerateCampaignJob (retry-режим):
 * свежий таргетинг, пин пула only_supplier_ids (без дублей уже отправленным),
 * нарезка по лимитам внутри — не влезшее снова отложится.
 */
class ProcessCapacityDeferredBatches extends Command
{
    private const CONN = 'reports';

    protected $signature = 'emails:process-capacity-deferred {--limit=20 : Максимум отсрочек за тик}';

    protected $description = 'Выпуск отложенных по дневным лимитам батчей (sender_capacity/ban_containment)';

    public function handle(): int
    {
        $capacity = new SenderDailyCapacity();
        if (!$capacity->enabled()) {
            $this->warn('Прогрев выключен (EMAILS_WARMUP_ENABLED=false).');
            return self::SUCCESS;
        }

        $budget = min($capacity->globalRemaining(), $capacity->poolRemainingTotal());
        if ($budget <= 0) {
            $this->info('Капасити нет (лимиты ящиков / глобальный потолок исчерпаны) — ждём.');
            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $rows = DB::connection(self::CONN)->table('deferred_batches')
            ->whereIn('reason', ['sender_capacity', 'ban_containment'])
            ->where('status', 'accumulating')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'only_supplier_ids']);

        if ($rows->isEmpty()) {
            $this->info('Нет отсрочек по капасити.');
            return self::SUCCESS;
        }

        $released = 0;
        foreach ($rows as $row) {
            if ($budget <= 0) {
                break;
            }
            // Грубая оценка стоимости выпуска — число пин-поставщиков (точная нарезка
            // по лимитам произойдёт внутри retry-джоба).
            $need = count((array) (json_decode((string) $row->only_supplier_ids, true) ?: [])) ?: 30;

            $claimed = DB::connection(self::CONN)->table('deferred_batches')
                ->where('id', $row->id)->where('status', 'accumulating')
                ->update(['status' => 'processing', 'updated_at' => now()]);
            if ($claimed === 0) {
                continue; // гонка — другой тик уже забрал
            }

            GenerateCampaignJob::dispatch([], false, (int) $row->id);
            $budget -= $need;
            $released++;
        }

        $this->info('Отсрочек по капасити: ' . $rows->count() . ", выпущено: {$released}.");
        return self::SUCCESS;
    }
}
