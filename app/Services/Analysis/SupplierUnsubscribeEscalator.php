<?php

namespace App\Services\Analysis;

use App\Services\Senders\SenderPauses;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Эскалация при обнаружении ОТПИСКИ поставщика (AI-сигнал unsubscribe=true в ответе).
 *
 * Политика (решение владельца 28.10.2026 — вместо паузы, недельного интервала и отключения поставщика целиком):
 *   сигнал               → пауза с НАШЕГО ЯЩИКА, на который ответил поставщик (pause_days, 7);
 *   повторный на домен   → пауза на весь наш почтовый домен (с любого его ящика);
 *   после паузы — пишем снова; каждое повторение — пауза на неделю длиннее (pause_step_days, +7).
 * Паузы — SenderPauses (таблица supplier_sender_pauses). Поставщик целиком не отключается; на нём только счётчик
 * и последняя причина (видимость в админке). НЕ путать с per-позиция rejection. Выполняется в транзакции
 * персистера (коннект reports). Идемпотентность обеспечивает Cache::lock в AnalyzeSupplierReplyJob.
 */
class SupplierUnsubscribeEscalator
{
    private const CONN = 'reports';

    /**
     * @param int|null $senderId наш ящик, на который пришёл ответ (null — последний ящик, писавший поставщику)
     * @return array<string,mixed>|null null — отписки нет или механизм выключен
     */
    public function apply(int $supplierId, bool $unsubscribe, ?string $reason, ?int $senderId = null): ?array
    {
        if (!$unsubscribe || $supplierId <= 0) {
            return null;
        }
        if (!(bool) config('services.email_unsubscribe.enabled', true)) {
            Log::info('Unsubscribe detected, auto-action disabled', ['supplier_id' => $supplierId, 'reason' => $reason]);
            return null;
        }

        $db = DB::connection(self::CONN);
        $supplier = $db->table('suppliers')->where('id', $supplierId)->first(['id', 'email', 'unsubscribe_count']);
        if (!$supplier) {
            return null;
        }

        $now = now();
        $reason = $reason !== null ? mb_substr($reason, 0, 255) : 'отписка (AI-сигнал)';
        $count = (int) $supplier->unsubscribe_count + 1;
        $db->table('suppliers')->where('id', $supplierId)->update([
            'unsubscribe_count' => $count,
            'last_unsubscribe_at' => $now,
            'unsubscribe_reason' => $reason,
            'updated_at' => $now,
        ]);

        $senderId = $senderId ?: (int) $db->table('email_queue')->where('supplier_id', $supplierId)->whereNotNull('sent_at')
            ->orderByDesc('sent_at')->value('sender_id');
        $senderEmail = $senderId ? (string) $db->table('senders')->where('id', $senderId)->value('email') : '';
        if (!$senderId || $senderEmail === '') {
            Log::warning('SupplierUnsubscribeEscalator: ящик ответа не найден — пауза не поставлена', [
                'supplier_id' => $supplierId, 'reason' => $reason,
            ]);

            return ['action' => 'no_sender', 'count' => $count];
        }

        $pause = (new SenderPauses())->record($supplierId, (string) $supplier->email, $senderId, $senderEmail, $reason);

        Log::warning('SupplierUnsubscribeEscalator: applied', [
            'supplier_id' => $supplierId,
            'sender_id' => $senderId,
            'count' => $count,
            'reason' => $reason,
        ] + $pause);

        return ['action' => $pause['domain_until'] ? 'paused_domain' : 'paused_mailbox', 'count' => $count] + $pause;
    }
}
