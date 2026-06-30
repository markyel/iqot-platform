<?php

namespace App\Services\Generate;

use Illuminate\Support\Facades\DB;

/**
 * Сохранение сгенерированного батча рассылки в боевые таблицы (порт MySQL-узлов
 * n8n «Create Email Queue v4 (AI)»). Всё в одной транзакции на коннекте `reports`.
 *
 * Узлы-источники:
 *  - Create Batch        → INSERT email_batches (status='pending') → batch_id;
 *  - Save AI Text        → UPDATE email_batches (email_body_text, ai_model,
 *                          ai_generated_at, status='ai_generated');
 *  - Insert Email Queue  → INSERT email_queue (status='pending') на поставщика;
 *  - Insert Item Responses → upsert request_item_responses на позицию×поставщика
 *                          по unique (request_item_id, supplier_id) — идемпотентно;
 *  - Update Batch Status → UPDATE email_batches status='queued'.
 *
 * Флип requests→queued_for_sending делает оркестратор (GenerateCampaignJob) после
 * всех батчей, а не здесь (порт «Update Request Status»).
 */
class CampaignPersister
{
    private const CONN = 'reports';

    /** Sentinel scheduled_at для «придержанных» писем волны 2 (диспетчер их не берёт).
     *  В пределах TIMESTAMP (макс 2038-01-19), но далеко в будущем. */
    public const HELD_UNTIL = '2037-12-31 00:00:00';

    /**
     * @param array<int,array<string,mixed>> $emails результаты CampaignEmailBuilder::build()
     *        на каждого поставщика батча
     * @return array{batch_id:int,queue_ids:array<int,int>}
     */
    public function persist(Batch $batch, array $emails): array
    {
        return DB::connection(self::CONN)->transaction(function () use ($batch, $emails): array {
            // 1. Create Batch → email_batches (pending).
            $requestItems = $this->batchItemIds($batch);
            $supplierIds = $this->dedupSupplierIds($batch);

            $batchId = (int) DB::connection(self::CONN)->table('email_batches')->insertGetId([
                'sender_id' => (int) ($batch->sender['id'] ?? 0),
                'request_items' => json_encode($requestItems, JSON_UNESCAPED_UNICODE),
                'supplier_ids' => json_encode($supplierIds, JSON_UNESCAPED_UNICODE),
                'is_customer_request' => $batch->isCustomerRequest ? 1 : 0,
                'items_count' => $batch->itemsCount,
                'status' => 'pending',
                'tracking_token' => (string) $batch->trackingToken,
            ]);
            $batch->batchId = $batchId;

            // 2. Save AI Text → email_batches (ai_generated). gen_context — снимок
            // для пересборки писем на волне 2 (новым поставщикам из discovery).
            DB::connection(self::CONN)->table('email_batches')
                ->where('id', $batchId)
                ->update([
                    'email_body_text' => $this->bodyText($batch->aiBody ?? []),
                    'ai_model' => (string) $batch->aiModel,
                    'ai_generated_at' => now(),
                    'status' => 'ai_generated',
                    'gen_context' => json_encode([
                        'ai_body' => $batch->aiBody,
                        'sender' => $batch->sender,
                        'email_template' => $batch->emailTemplate,
                        'request_numbers' => $batch->requestNumbers,
                        'request_ids' => $batch->requestIds,
                        'is_customer_request' => $batch->isCustomerRequest,
                        'customer' => [
                            'company' => $batch->customerCompany,
                            'contact_person' => $batch->customerContactPerson,
                            'email' => $batch->customerEmail,
                            'phone' => $batch->customerPhone,
                        ],
                        'items_count' => $batch->itemsCount,
                    ], JSON_UNESCAPED_UNICODE),
                ]);

            // 3. Insert Email Queue + Insert Item Responses на каждого поставщика.
            $queueIds = [];
            foreach ($emails as $email) {
                if (!is_array($email)) {
                    continue;
                }
                $supplierId = (int) ($email['supplier_id'] ?? 0);
                if ($supplierId <= 0) {
                    continue;
                }

                $token = (string) ($email['tracking_token'] ?? '');
                // Волна 2 (пул расширения) держится: status=pending, но scheduled_at в
                // далёком будущем — диспетчер берёт только scheduled_at <= NOW(), поэтому
                // не тронет, пока follow-up не «отпустит» (scheduled_at=now) или не отменит.
                $wave = (int) ($email['wave'] ?? 1);
                $scheduledAt = $wave === 2 ? self::HELD_UNTIL : now();
                $emailQueueId = (int) DB::connection(self::CONN)->table('email_queue')->insertGetId([
                    'batch_id' => $batchId,
                    'token' => $token,
                    'sender_id' => (int) ($email['sender_id'] ?? 0),
                    'supplier_id' => $supplierId,
                    'from_email' => (string) ($email['from_email'] ?? ''),
                    'to_email' => (string) ($email['to_email'] ?? ''),
                    'subject' => (string) ($email['subject'] ?? ''),
                    'body_html' => (string) ($email['body_html'] ?? ''),
                    'tracking_token' => $token,
                    'priority' => 0,
                    'scheduled_at' => $scheduledAt,
                    'status' => 'pending',
                    'wave' => $wave,
                ]);
                $queueIds[] = $emailQueueId;

                foreach ($this->itemIds($email['request_item_ids'] ?? []) as $itemId) {
                    $this->upsertItemResponse($itemId, $supplierId, $emailQueueId, $batchId);
                }
            }

            // 4. Update Batch Status → queued.
            DB::connection(self::CONN)->table('email_batches')
                ->where('id', $batchId)
                ->update(['status' => 'queued']);

            return ['batch_id' => $batchId, 'queue_ids' => $queueIds];
        });
    }

    /**
     * Порт «Insert Item Responses»: idempotent upsert по unique
     * (request_item_id, supplier_id).
     */
    private function upsertItemResponse(int $itemId, int $supplierId, int $emailQueueId, int $batchId): void
    {
        DB::connection(self::CONN)->statement(
            'INSERT INTO request_item_responses
                (request_item_id, supplier_id, email_queue_id, batch_id, status, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                email_queue_id = VALUES(email_queue_id),
                batch_id = VALUES(batch_id)',
            [$itemId, $supplierId, $emailQueueId, $batchId, 'pending']
        );
    }

    /**
     * email_body_text не строится отдельным узлом — собираем из AI-частей
     * (greeting/introduction/closing) для колонки email_batches.email_body_text.
     *
     * @param array<string,mixed> $body
     */
    private function bodyText(array $body): string
    {
        $parts = [
            trim((string) ($body['greeting'] ?? '')),
            trim((string) ($body['introduction'] ?? '')),
            trim((string) ($body['closing'] ?? '')),
        ];
        return trim(implode("\n\n", array_filter($parts, static fn ($p) => $p !== '')));
    }

    /**
     * Порт Prepare Batch: request_items = items.map(id), целочисленные.
     *
     * @return array<int,int>
     */
    private function batchItemIds(Batch $batch): array
    {
        $ids = [];
        foreach ($batch->items as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Порт Prepare Batch: дедуп supplier_ids.
     *
     * @return array<int,int>
     */
    private function dedupSupplierIds(Batch $batch): array
    {
        $ids = [];
        foreach ($batch->supplierIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            foreach ($batch->suppliers as $supplier) {
                $id = (int) ($supplier['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * @param mixed $raw
     * @return array<int,int>
     */
    private function itemIds(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }
}
