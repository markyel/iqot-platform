<?php

namespace App\Services\Analysis;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        // Позиции батча (в порядке промпта). item_id от AI сверяем с этим списком:
        // выдуманный/порядковый id не должен лечь оффером в чужую позицию.
        $itemIds = array_values(array_filter(
            array_map('intval', (array) ($context['item_ids'] ?? [])),
            static fn (int $v) => $v > 0
        ));

        DB::connection(self::CONN)->transaction(function () use (
            $messageId,
            $conversationId,
            $supplierId,
            $batchId,
            $itemIds,
            $classification
        ): void {
            $this->saveClassification($messageId, $classification);

            // Отписка от рассылки (релейшеншип-сигнал) → пауза с нашего ящика, на который ответили,
            // повторно на домен → пауза домена. НЕ зависит от email_type (может прийти с offer/rejection).
            if ((bool) ($classification['unsubscribe'] ?? false)) {
                (new SupplierUnsubscribeEscalator())->apply(
                    $supplierId,
                    true,
                    $classification['unsubscribe_reason'] ?? null,
                    $this->replySenderId($messageId),
                );
            }

            $emailQueueId = $this->resolveEmailQueueId($batchId, $supplierId);

            foreach (($classification['offers'] ?? []) as $offer) {
                if (!is_array($offer)) {
                    continue;
                }
                if ((float) ($offer['price_per_unit'] ?? 0) <= 0) {
                    continue; // в offers только реальные цены
                }
                $itemId = $this->resolveItemId($offer['item_id'] ?? null, $itemIds);
                if ($itemId === null) {
                    Log::warning('SupplierReplyPersister: offer item_id not in batch, skipped', [
                        'message_id' => $messageId,
                        'batch_id' => $batchId,
                        'ai_item_id' => $offer['item_id'] ?? null,
                    ]);
                    continue;
                }
                $offer['item_id'] = $itemId;
                $this->saveOffer($offer, $supplierId, $batchId, $emailQueueId);
            }

            // Отказ обычно не содержит вопроса к нам — не сохраняем questions, даже если AI вытащил их из
            // процитированного исходного запроса (header-less цитата). Исключение — «нет в наличии» со встречным
            // вопросом («под заказ рассматриваете?», Эскалайн 20.10.2026): на него нужно ответить.
            if (($classification['email_type'] ?? '') !== 'rejection'
                || ($classification['rejection_reason'] ?? '') === 'not_available') {
                foreach (($classification['questions'] ?? []) as $question) {
                    if (is_array($question)) {
                        $question['related_item_id'] = $this->resolveItemId($question['related_item_id'] ?? null, $itemIds);
                        $this->saveQuestion($question, $conversationId, $supplierId, $batchId, $messageId);
                    }
                }
            }

            $this->updateConversationStatus($conversationId, $supplierId, $itemIds);
        });
    }

    /**
     * item_id из ответа AI → id позиции ЭТОГО батча или null. Если AI вопреки
     * инструкции вернул порядковый номер (1..N) — переводим в id по порядку промпта
     * (реальные id — тысячи, с порядковыми номерами не пересекаются). Без списка
     * позиций (старый вызов) — доверяем как раньше.
     *
     * @param array<int,int> $itemIds
     */
    private function resolveItemId(mixed $raw, array $itemIds): ?int
    {
        $id = (int) ($raw ?? 0);
        if ($id <= 0) {
            return null;
        }
        if ($itemIds === [] || in_array($id, $itemIds, true)) {
            return $id;
        }
        if ($id <= count($itemIds)) {
            return $itemIds[$id - 1];
        }

        return null;
    }

    /** Наш ящик, на который пришёл ответ (адрес «Кому» входящего письма), или null. */
    private function replySenderId(int $messageId): ?int
    {
        if ($messageId <= 0) {
            return null;
        }
        $to = DB::connection(self::CONN)->table('email_messages')->where('id', $messageId)->value('to_email');
        if (!$to) {
            return null;
        }
        $id = DB::connection(self::CONN)->table('senders')->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $to))])->value('id');

        return $id ? (int) $id : null;
    }

    private function saveClassification(int $messageId, array $classification): void
    {
        $json = json_encode([
            'email_type' => $classification['email_type'] ?? 'other',
            'rejection_reason' => $classification['rejection_reason'] ?? null,
            'unsubscribe' => (bool) ($classification['unsubscribe'] ?? false),
            'unsubscribe_reason' => $classification['unsubscribe_reason'] ?? null,
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

    /**
     * Исходное письмо очереди для пары (батч, поставщик). Может вернуть NULL:
     * поставщик опознан по адресу отправителя (IncomingEmailRouter::findSupplierByEmail),
     * а не по email_queue.token — тогда строки очереди для этой пары нет. Колонка
     * request_item_responses.email_queue_id nullable именно под этот кейс
     * (миграция 2026_07_03_120000): оффер сохраняем без исходного письма очереди.
     */
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

    /**
     * Покрытие беседы = позиции её батча, по которым ИМЕННО этот поставщик прислал
     * цену (раньше считалось по всему батчу — беседа молчащего поставщика
     * становилась partial из-за чужих офферов). Беседа с открытым вопросом к
     * автору (needs_clarification + has_pending_question) статус не теряет.
     *
     * @param array<int,int> $itemIds
     */
    private function updateConversationStatus(int $conversationId, int $supplierId, array $itemIds): void
    {
        $covered = $itemIds === [] ? 0 : (int) DB::connection(self::CONN)->table('request_item_responses')
            ->whereIn('request_item_id', $itemIds)
            ->where('supplier_id', $supplierId)
            ->where('status', 'received')
            ->distinct()
            ->count('request_item_id');

        $conn = DB::connection(self::CONN);
        $conn->table('email_conversations')
            ->where('id', $conversationId)
            ->update([
                'items_covered' => $covered,
                'status' => $conn->raw(
                    "CASE WHEN status = 'needs_clarification' AND has_pending_question = 1"
                    . " THEN 'needs_clarification' ELSE '" . ($covered > 0 ? 'partial' : 'waiting') . "' END"
                ),
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
