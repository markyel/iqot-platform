<?php

namespace App\Services\Analysis;

use Illuminate\Support\Facades\DB;

/**
 * Сохранение результата AI-анализа ответа поставщика в боевые таблицы (порт
 * MySQL-узлов n8n «Process Email Conversations»). Всё в одной транзакции на
 * коннекте `reports`.
 *
 * Узлы-источники:
 *  - Save Classification → email_messages.ai_classification + ai_processed=1;
 *  - Save Offer → upsert request_item_responses по unique (request_item_id, supplier_id);
 *  - Check If Main Exists + Insert Multi → request_item_multi_responses (повторное
 *    предложение того же товара) + флаг has_multi_responses;
 *  - Save Question → supplier_questions (с дедупликацией, ключа в БД нет);
 *  - Update Conversation Status → items_covered + partial/pending.
 */
class SupplierReplyPersister
{
    private const CONN = 'reports';

    /**
     * @param array<string,mixed> $context ключи: message_id, conversation_id,
     *        supplier_id, batch_id
     * @param array<string,mixed> $classification нормализованный ответ анализатора
     */
    public function persist(array $context, array $classification): void
    {
        $messageId = (int) ($context['message_id'] ?? 0);
        $conversationId = (int) ($context['conversation_id'] ?? 0);
        $supplierId = (int) ($context['supplier_id'] ?? 0);
        $batchId = (int) ($context['batch_id'] ?? 0);

        DB::connection(self::CONN)->transaction(function () use (
            $messageId,
            $conversationId,
            $supplierId,
            $batchId,
            $classification
        ): void {
            $this->saveClassification($messageId, $classification);

            $emailQueueId = $this->resolveEmailQueueId($batchId, $supplierId);

            foreach (($classification['offers'] ?? []) as $offer) {
                if (!is_array($offer)) {
                    continue;
                }
                if ((float) ($offer['price_per_unit'] ?? 0) <= 0) {
                    continue; // в offers только реальные цены
                }
                $this->saveOffer($offer, $supplierId, $batchId, $emailQueueId);
            }

            foreach (($classification['questions'] ?? []) as $question) {
                if (is_array($question)) {
                    $this->saveQuestion($question, $conversationId, $supplierId, $batchId, $messageId);
                }
            }

            $this->updateConversationStatus($conversationId, $batchId);
        });
    }

    private function saveClassification(int $messageId, array $classification): void
    {
        $json = json_encode([
            'email_type' => $classification['email_type'] ?? 'other',
            'rejection_reason' => $classification['rejection_reason'] ?? null,
            'has_offers' => (bool) ($classification['has_offers'] ?? false),
            'offers' => $classification['offers'] ?? [],
            'has_questions' => (bool) ($classification['has_questions'] ?? false),
            'questions' => $classification['questions'] ?? [],
            'summary' => $classification['summary'] ?? '',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        DB::connection(self::CONN)->table('email_messages')
            ->where('id', $messageId)
            ->update([
                'ai_classification' => $json,
                'ai_processed' => 1,
                'processed_at' => now(),
            ]);
    }

    private function resolveEmailQueueId(int $batchId, int $supplierId): ?int
    {
        $row = DB::connection(self::CONN)->table('email_queue')
            ->where('batch_id', $batchId)
            ->where('supplier_id', $supplierId)
            ->value('id');

        return $row !== null ? (int) $row : null;
    }

    /**
     * @param array<string,mixed> $offer
     */
    private function saveOffer(array $offer, int $supplierId, int $batchId, ?int $emailQueueId): void
    {
        $itemId = (int) ($offer['item_id'] ?? 0);
        if ($itemId <= 0) {
            return;
        }

        $main = DB::connection(self::CONN)->table('request_item_responses')
            ->where('request_item_id', $itemId)
            ->where('supplier_id', $supplierId)
            ->where('batch_id', $batchId)
            ->first(['id', 'status', 'price_per_unit']);

        $mainExists = $main !== null
            && $main->status !== 'pending'
            && $main->price_per_unit !== null;

        if ($mainExists) {
            // Повторное предложение того же товара → вариант в multi-таблице.
            $this->insertMultiResponse((int) $main->id, $offer, $itemId, $supplierId, $batchId);
            return;
        }

        // Первое предложение по товару → главная строка (upsert по unique item+supplier).
        $this->upsertMainResponse($offer, $itemId, $supplierId, $batchId, $emailQueueId);
    }

    /**
     * @param array<string,mixed> $offer
     */
    private function upsertMainResponse(array $offer, int $itemId, int $supplierId, int $batchId, ?int $emailQueueId): void
    {
        DB::connection(self::CONN)->statement(
            'INSERT INTO request_item_responses
                (request_item_id, supplier_id, email_queue_id, batch_id, status,
                 price_per_unit, total_price, currency, price_includes_vat,
                 delivery_days, payment_terms, notes, response_received_at)
             VALUES (?, ?, ?, ?, "received", ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                status = "received",
                price_per_unit = VALUES(price_per_unit),
                total_price = VALUES(total_price),
                currency = VALUES(currency),
                price_includes_vat = VALUES(price_includes_vat),
                delivery_days = VALUES(delivery_days),
                payment_terms = VALUES(payment_terms),
                notes = VALUES(notes),
                response_received_at = VALUES(response_received_at)',
            [
                $itemId,
                $supplierId,
                $emailQueueId,
                $batchId,
                $offer['price_per_unit'] ?? null,
                $offer['total_price'] ?? null,
                (string) ($offer['currency'] ?? 'RUB'),
                $this->vatToInt($offer['price_includes_vat'] ?? null),
                $offer['delivery_days'] ?? null,
                $this->nullableString($offer['payment_terms'] ?? null),
                $this->nullableString($offer['notes'] ?? null),
            ]
        );
    }

    /**
     * @param array<string,mixed> $offer
     */
    private function insertMultiResponse(int $mainResponseId, array $offer, int $itemId, int $supplierId, int $batchId): void
    {
        // Дедупликация: ключа в БД нет, повторный прогон не должен плодить варианты.
        $exists = DB::connection(self::CONN)->table('request_item_multi_responses')
            ->where('request_item_response_id', $mainResponseId)
            ->where('request_item_id', $itemId)
            ->where('price_per_unit', $offer['price_per_unit'] ?? null)
            ->where('currency', (string) ($offer['currency'] ?? 'RUB'))
            ->exists();

        if ($exists) {
            return;
        }

        DB::connection(self::CONN)->table('request_item_multi_responses')->insert([
            'request_item_response_id' => $mainResponseId,
            'request_item_id' => $itemId,
            'supplier_id' => $supplierId,
            'batch_id' => $batchId,
            'price_per_unit' => $offer['price_per_unit'] ?? null,
            'total_price' => $offer['total_price'] ?? null,
            'currency' => (string) ($offer['currency'] ?? 'RUB'),
            'price_includes_vat' => $this->vatToInt($offer['price_includes_vat'] ?? null),
            'delivery_days' => $offer['delivery_days'] ?? null,
            'payment_terms' => $this->nullableString($offer['payment_terms'] ?? null),
            'notes' => $this->nullableString($offer['notes'] ?? null),
        ]);

        DB::connection(self::CONN)->table('request_item_responses')
            ->where('id', $mainResponseId)
            ->update(['has_multi_responses' => 1]);
    }

    /**
     * @param array<string,mixed> $question
     */
    private function saveQuestion(array $question, int $conversationId, int $supplierId, int $batchId, int $messageId): void
    {
        $text = trim((string) ($question['question_text'] ?? ''));
        if ($text === '') {
            return;
        }

        // Дедупликация по (email_message_id, question_text) — в БД ключа нет.
        $exists = DB::connection(self::CONN)->table('supplier_questions')
            ->where('email_message_id', $messageId)
            ->where('question_text', $text)
            ->exists();

        if ($exists) {
            return;
        }

        DB::connection(self::CONN)->table('supplier_questions')->insert([
            'conversation_id' => $conversationId,
            'supplier_id' => $supplierId,
            'batch_id' => $batchId,
            'email_message_id' => $messageId,
            'question_text' => $text,
            'question_type' => (string) ($question['question_type'] ?? 'general'),
            'request_item_id' => $this->nullableInt($question['related_item_id'] ?? null),
            'status' => 'pending',
            'created_at' => now(),
        ]);
    }

    private function updateConversationStatus(int $conversationId, int $batchId): void
    {
        $covered = (int) DB::connection(self::CONN)->table('request_item_responses')
            ->where('batch_id', $batchId)
            ->where('status', 'received')
            ->distinct()
            ->count('request_item_id');

        DB::connection(self::CONN)->table('email_conversations')
            ->where('id', $conversationId)
            ->update([
                'items_covered' => $covered,
                'status' => $covered > 0 ? 'partial' : 'waiting',
                'updated_at' => now(),
            ]);
    }

    private function vatToInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return $value ? 1 : 0;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
