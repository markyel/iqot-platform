<?php

namespace App\Services\Analysis;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Эскалация при обнаружении ОТПИСКИ поставщика (AI-сигнал unsubscribe=true в ответе).
 *
 * Политика (порог настраивается):
 *   1-я отписка   → пауза N дней (suppliers.unsubscribe_until) + увеличенный личный
 *                   интервал отправки (send_interval_override_seconds);
 *   повторная     → полное отключение от рассылки (is_active=0, notify_email=0).
 *
 * Учитывают: CampaignSupplierSelector (пауза/выкл на генерации) и DispatchPendingEmails
 * (пауза + личный интервал на пейсинге). НЕ путать с per-позиция rejection — отписка
 * это релейшеншип-сигнал «не пишите больше». Выполняется в транзакции персистера
 * (коннект reports). Идемпотентность обеспечивает Cache::lock в AnalyzeSupplierReplyJob.
 */
class SupplierUnsubscribeEscalator
{
    private const CONN = 'reports';

    /**
     * @return array{action:string,count:int}|null null — отписки нет или механизм выключен
     */
    public function apply(int $supplierId, bool $unsubscribe, ?string $reason): ?array
    {
        if (!$unsubscribe || $supplierId <= 0) {
            return null;
        }
        if (!(bool) config('services.email_unsubscribe.enabled', true)) {
            Log::info('Unsubscribe detected, auto-action disabled', ['supplier_id' => $supplierId, 'reason' => $reason]);
            return null;
        }

        $supplier = DB::connection(self::CONN)->table('suppliers')
            ->where('id', $supplierId)
            ->first(['id', 'unsubscribe_count', 'is_active']);
        if (!$supplier) {
            return null;
        }

        $count = (int) $supplier->unsubscribe_count + 1;
        $now = now();
        $reason = $reason !== null ? mb_substr($reason, 0, 255) : 'отписка (AI-сигнал)';

        $disableThreshold = max(1, (int) config('services.email_unsubscribe.disable_threshold', 2));

        $data = [
            'unsubscribe_count' => $count,
            'last_unsubscribe_at' => $now,
            'unsubscribe_reason' => $reason,
            'updated_at' => $now,
        ];

        if ($count >= $disableThreshold) {
            // Повторная отписка → полностью отключаем от рассылки.
            $data['is_active'] = 0;
            $data['notify_email'] = 0;
            $action = 'disabled';
        } else {
            // Первая отписка → пауза + увеличенный личный интервал.
            $pauseDays = max(1, (int) config('services.email_unsubscribe.pause_days', 7));
            $data['unsubscribe_until'] = $now->copy()->addDays($pauseDays);
            $data['send_interval_override_seconds'] = max(
                0,
                (int) config('services.email_unsubscribe.escalated_interval_seconds', 604800)
            );
            $action = 'paused';
        }

        DB::connection(self::CONN)->table('suppliers')->where('id', $supplierId)->update($data);

        Log::warning('SupplierUnsubscribeEscalator: applied', [
            'supplier_id' => $supplierId,
            'action' => $action,
            'count' => $count,
            'reason' => $reason,
        ]);

        return ['action' => $action, 'count' => $count];
    }
}
