<?php

namespace App\Services\Senders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Контейнмент бана отправителя на лету (Phase 3b, за флагом EMAILS_WARMUP_ENABLED).
 *
 * Когда ящик выпадает из отправки (spam-реджекты → sending_disabled, повторный бан
 * рампы, авто-деактивация по auth/550/ratelimit), его УЖЕ сгенерированные письма
 * нельзя ни слать (горящий ящик), ни бросать висеть (диспетчер деактивированного
 * не берёт — pending-зомби). Делаем un-claim + переброс:
 *   1) все pending-письма ящика (включая придержанную волну 2) → cancelled;
 *   2) по каждому затронутому батчу — строка deferred_batches
 *      (reason='ban_containment', status='accumulating') с пином снятых поставщиков
 *      (only_supplier_ids): emails:process-capacity-deferred перегенерит письма
 *      ДРУГИМИ отправителями (свой стиль/токен/тело — анти-фингерпринт), только
 *      снятым адресатам, в пределах их дневных лимитов.
 *
 * Письма в статусе 'sending' не трогаем — job в полёте сам довершит/зафейлит.
 * Ошибки глотаются с логом: контейнмент не должен валить вызывающий поток
 * (receive/email-воркеры).
 */
class SenderBanContainment
{
    private const CONN = 'reports';

    public static function contain(int $senderId, string $trigger): void
    {
        if ($senderId <= 0 || !(bool) config('services.email_warmup.enabled', false)) {
            return;
        }
        try {
            (new self())->run($senderId, $trigger);
        } catch (\Throwable $e) {
            Log::error('SenderBanContainment: контейнмент упал', [
                'sender_id' => $senderId,
                'trigger' => $trigger,
                'error' => mb_substr($e->getMessage(), 0, 400),
            ]);
        }
    }

    private function run(int $senderId, string $trigger): void
    {
        $candidateIds = DB::connection(self::CONN)->table('email_queue')
            ->where('sender_id', $senderId)
            ->where('status', 'pending')
            ->pluck('id')->all();

        if ($candidateIds === []) {
            return;
        }

        $marker = mb_substr("ban containment ({$trigger})", 0, 255);
        $cancelled = DB::connection(self::CONN)->table('email_queue')
            ->whereIn('id', $candidateIds)
            ->where('status', 'pending')
            ->update([
                'status' => 'cancelled',
                'error_message' => $marker,
                'updated_at' => now(),
            ]);
        if ($cancelled === 0) {
            return;
        }

        // Пин строим ТОЛЬКО по реально отменённым строкам: письмо, которое диспетчер
        // успел заклеймить в 'sending' между SELECT и UPDATE, уйдёт само — его
        // адресату регенерация не нужна (иначе дубль).
        $rows = DB::connection(self::CONN)->table('email_queue')
            ->whereIn('id', $candidateIds)
            ->where('status', 'cancelled')
            ->where('error_message', $marker)
            ->get(['batch_id', 'supplier_id']);

        // Группируем снятых адресатов по батчу → отложка на регенерацию.
        $byBatch = [];
        foreach ($rows as $r) {
            $batchId = (int) $r->batch_id;
            $supplierId = (int) $r->supplier_id;
            if ($batchId > 0 && $supplierId > 0 && !in_array($supplierId, $byBatch[$batchId] ?? [], true)) {
                $byBatch[$batchId][] = $supplierId;
            }
        }

        $deferred = 0;
        foreach ($byBatch as $batchId => $supplierIds) {
            if ($this->deferBatch($senderId, $batchId, $supplierIds, $trigger)) {
                $deferred++;
            }
        }

        Log::warning('SenderBanContainment: письма забаненного ящика сняты и отложены на переброс', [
            'sender_id' => $senderId,
            'trigger' => $trigger,
            'cancelled' => $cancelled,
            'batches_deferred' => $deferred,
        ]);
    }

    /**
     * @param array<int,int> $supplierIds
     */
    private function deferBatch(int $senderId, int $batchId, array $supplierIds, string $trigger): bool
    {
        $batch = DB::connection(self::CONN)->table('email_batches')
            ->where('id', $batchId)
            ->first(['id', 'request_items']);
        if (!$batch) {
            return false;
        }

        $itemIds = array_values(array_filter(array_map('intval', (array) (json_decode((string) $batch->request_items, true) ?: []))));
        if ($itemIds === []) {
            return false;
        }

        $items = DB::connection(self::CONN)->table('request_items')
            ->whereIn('id', $itemIds)
            ->get(['id', 'request_id', 'product_type_id', 'domain_id']);
        if ($items->isEmpty()) {
            return false;
        }

        $requestIds = $items->pluck('request_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
        $first = $items->first();

        DB::connection(self::CONN)->table('deferred_batches')->insert([
            'request_ids' => json_encode($requestIds, JSON_UNESCAPED_UNICODE),
            'item_ids' => json_encode($itemIds, JSON_UNESCAPED_UNICODE),
            'sender_id' => $senderId,
            'product_type_id' => (int) ($first->product_type_id ?? 0) ?: null,
            'domain_id' => (int) ($first->domain_id ?? 0) ?: null,
            'only_supplier_ids' => json_encode(array_values($supplierIds), JSON_UNESCAPED_UNICODE),
            'found_domains' => json_encode([], JSON_UNESCAPED_UNICODE),
            'candidates_total' => 0,
            'candidates_done' => 0,
            'status' => 'accumulating',
            'reason' => 'ban_containment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }
}
