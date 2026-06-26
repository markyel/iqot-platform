<?php

namespace App\Services\Questions;

use Illuminate\Support\Facades\DB;

/**
 * Сохранение результата триажа вопроса поставщика в боевые таблицы (порт
 * MySQL-узлов n8n «Process Supplier Questions»). Две ветки, каждая в одной
 * транзакции на коннекте `reports`.
 *
 * AUTO (можно ответить автоматически) — узлы Insert Outgoing Reply / Copy
 * Attachments SQL / Update Question Status (Auto) / Update Conversation (Auto):
 *  - outgoing_replies (status='pending', отправку делает ОТДЕЛЬНЫЙ воркфлоу);
 *  - при наличии — копирование вложений из original_reply_id;
 *  - supplier_questions.status='auto_answered';
 *  - email_conversations.has_pending_question=0.
 *
 * AUTHOR (нужен человек) — узлы Insert Author Question / Update Question Status
 * (Author) / Update Conversation (Author):
 *  - author_questions (status='pending', request_item_ids=[related] или []);
 *  - supplier_questions.status='forwarded_to_author' + request_item_id + consolidation_id;
 *  - email_conversations.has_pending_question=1, status='needs_clarification'.
 */
class SupplierQuestionPersister
{
    private const CONN = 'reports';

    /**
     * Ветка авто-ответа.
     *
     * @param array<string,mixed> $reply результат ReplyEmailBuilder::build
     */
    public function persistAuto(array $reply): void
    {
        $questionId = (int) ($reply['question_id'] ?? 0);
        $conversationId = (int) ($reply['conversation_id'] ?? 0);

        DB::connection(self::CONN)->transaction(function () use ($reply, $questionId, $conversationId): void {
            $newReplyId = $this->insertOutgoingReply($reply);

            $originalReplyId = $this->nullableInt($reply['original_reply_id'] ?? null);
            if ($newReplyId !== null && ($reply['has_files_to_copy'] ?? false) && $originalReplyId !== null) {
                $this->copyAttachments($newReplyId, $originalReplyId);
            }

            DB::connection(self::CONN)->table('supplier_questions')
                ->where('id', $questionId)
                ->update(['status' => 'auto_answered']);

            DB::connection(self::CONN)->table('email_conversations')
                ->where('id', $conversationId)
                ->update(['has_pending_question' => 0]);
        });
    }

    /**
     * Ветка направления автору.
     *
     * @param array<string,mixed> $context ключи: question_id, conversation_id,
     *        batch_id, related_item_id, consolidation_id, author_user_id
     */
    public function persistAuthor(array $context): void
    {
        $questionId = (int) ($context['question_id'] ?? 0);
        $conversationId = (int) ($context['conversation_id'] ?? 0);
        $batchId = (int) ($context['batch_id'] ?? 0);
        $relatedItemId = $this->nullableInt($context['related_item_id'] ?? null);
        $consolidationId = $this->nullableInt($context['consolidation_id'] ?? null);
        $authorUserId = $this->nullableInt($context['author_user_id'] ?? null);

        DB::connection(self::CONN)->transaction(function () use (
            $questionId,
            $conversationId,
            $batchId,
            $relatedItemId,
            $consolidationId,
            $authorUserId
        ): void {
            $this->insertAuthorQuestion($questionId, $batchId, $relatedItemId, $authorUserId);

            DB::connection(self::CONN)->table('supplier_questions')
                ->where('id', $questionId)
                ->update([
                    'status' => 'forwarded_to_author',
                    'request_item_id' => $relatedItemId,
                    'consolidation_id' => $consolidationId,
                ]);

            DB::connection(self::CONN)->table('email_conversations')
                ->where('id', $conversationId)
                ->update([
                    'has_pending_question' => 1,
                    'status' => 'needs_clarification',
                ]);
        });
    }

    /**
     * Порт «Insert Outgoing Reply»: вставка письма + возврат id. Дедуп по
     * supplier_question_id (в БД ключа нет, повторный прогон не должен плодить
     * письма) — если ответ уже есть, возвращаем его id и не вставляем заново.
     *
     * @param array<string,mixed> $reply
     */
    private function insertOutgoingReply(array $reply): ?int
    {
        $questionId = (int) ($reply['supplier_question_id'] ?? $reply['question_id'] ?? 0);

        $existing = DB::connection(self::CONN)->table('outgoing_replies')
            ->where('supplier_question_id', $questionId)
            ->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::connection(self::CONN)->table('outgoing_replies')->insertGetId([
            'conversation_id' => (int) ($reply['conversation_id'] ?? 0),
            'supplier_question_id' => $questionId,
            'sender_id' => (int) ($reply['sender_id'] ?? 0),
            'supplier_id' => (int) ($reply['supplier_id'] ?? 0),
            'from_email' => (string) ($reply['from_email'] ?? ''),
            'to_email' => (string) ($reply['to_email'] ?? ''),
            'subject' => (string) ($reply['subject'] ?? ''),
            'body_text' => (string) ($reply['body_text'] ?? ''),
            'body_html' => (string) ($reply['body_html'] ?? ''),
            'in_reply_to' => (string) ($reply['in_reply_to'] ?? ''),
            'references_header' => (string) ($reply['references_header'] ?? ''),
            'status' => 'pending',
            'created_at' => now(),
        ]);
    }

    /**
     * Порт «Copy Attachments SQL»: копирование вложений образцового ответа на новый.
     * Дедуп: если у нового ответа уже есть вложения — не копируем повторно.
     */
    private function copyAttachments(int $newReplyId, int $originalReplyId): void
    {
        $already = DB::connection(self::CONN)->table('outgoing_reply_attachments')
            ->where('outgoing_reply_id', $newReplyId)
            ->exists();
        if ($already) {
            return;
        }

        DB::connection(self::CONN)->statement(
            'INSERT INTO outgoing_reply_attachments
                (outgoing_reply_id, file_id, file_name, mime_type, file_size, file_type, file_data)
             SELECT ?, file_id, file_name, mime_type, file_size, file_type, file_data
             FROM outgoing_reply_attachments
             WHERE outgoing_reply_id = ?',
            [$newReplyId, $originalReplyId]
        );
    }

    /**
     * Порт «Insert Author Question»: request_item_ids — JSON-массив `[id]` либо `[]`.
     * Дедуп по supplier_question_id (ключа в БД нет).
     */
    private function insertAuthorQuestion(int $questionId, int $batchId, ?int $relatedItemId, ?int $authorUserId): void
    {
        $exists = DB::connection(self::CONN)->table('author_questions')
            ->where('supplier_question_id', $questionId)
            ->exists();
        if ($exists) {
            return;
        }

        DB::connection(self::CONN)->table('author_questions')->insert([
            'supplier_question_id' => $questionId,
            'batch_id' => $batchId,
            'request_item_ids' => $relatedItemId !== null ? '[' . $relatedItemId . ']' : '[]',
            'author_user_id' => $authorUserId,
            'status' => 'pending',
            'created_at' => now(),
        ]);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
