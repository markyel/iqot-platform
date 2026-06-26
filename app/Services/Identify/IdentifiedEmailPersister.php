<?php

namespace App\Services\Identify;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Запись результатов идентификации неопознанного письма — порт SQL-узлов n8n
 * «Update as Identified / Find+Create Conversation / Save Email Message / Migrate
 * Attachments» и веток «Update Not Identified» / «No Candidates Found».
 *
 * Успех (identified): транзакцией на reports
 *  1. unidentified_emails.status='identified' + identified_* + метод/уверенность/details;
 *  2. беседа: найти по (batch_id, supplier_id) или создать ('waiting', items_total из
 *     email_batches.items_count — как в IncomingEmailRouter, не хардкод 1 из n8n);
 *  3. email_messages (direction='incoming') — дедуп по message_id (NOT EXISTS);
 *  4. при создании нового сообщения: миграция вложений в email_attachments (с local_path,
 *     чтобы DocumentTextExtractor мог их прочесть в шаге AI-анализа) + email_queue='replied'.
 *
 * Неуспех: status='manual_review' (+reason='no_match' для ветки без кандидатов),
 * identification_details с reasoning/confidence.
 *
 * Идемпотентность: email_messages деду­плицируется по message_id; вложения мигрируем
 * ТОЛЬКО при фактическом создании нового сообщения (повторный прогон не плодит).
 */
class IdentifiedEmailPersister
{
    /**
     * Письмо идентифицировано — мигрируем в боевую беседу.
     *
     * @param array{identified_batch_id:int,identified_queue_id:?int,identified_supplier_id:int,confidence:float,reasoning:string,matched_items:array<int,string>,is_price_offer:bool,validation_passed:bool} $decision
     */
    public function persistIdentified(object $email, array $decision): void
    {
        $emailId = (int) $email->id;
        $batchId = (int) $decision['identified_batch_id'];
        $supplierId = (int) $decision['identified_supplier_id'];
        $queueId = $decision['identified_queue_id'] !== null ? (int) $decision['identified_queue_id'] : null;
        $now = now();

        DB::connection('reports')->transaction(function () use ($email, $emailId, $batchId, $supplierId, $queueId, $decision, $now) {
            // 1. Помечаем неопознанное письмо идентифицированным.
            DB::connection('reports')->table('unidentified_emails')
                ->where('id', $emailId)
                ->update([
                    'status' => 'identified',
                    'identified_batch_id' => $batchId,
                    'identified_queue_id' => $queueId,
                    'identified_supplier_id' => $supplierId,
                    'identification_method' => 'ai_analysis',
                    'identification_confidence' => $decision['confidence'],
                    'identification_details' => json_encode([
                        'reasoning' => $decision['reasoning'],
                        'matched_items' => $decision['matched_items'],
                        'is_price_offer' => $decision['is_price_offer'],
                        'validation_passed' => $decision['validation_passed'],
                    ], JSON_UNESCAPED_UNICODE),
                    'updated_at' => $now,
                ]);

            // 2. Беседа.
            $conversationId = $this->findOrCreateConversation($batchId, $supplierId, $queueId, $now);

            // 3. email_messages с дедупом по message_id.
            $messageId = $this->saveEmailMessage($email, $conversationId, $now);
            if ($messageId === null) {
                return; // уже мигрировано ранее (message_id присутствует) — вложения не дублируем
            }

            // 4. Вложения + статус исходящего письма.
            $this->migrateAttachments($emailId, $messageId, $now);

            if ($queueId !== null) {
                DB::connection('reports')->table('email_queue')
                    ->where('id', $queueId)
                    ->update(['status' => 'replied', 'replied_at' => $now, 'updated_at' => $now]);
            }
        });
    }

    /**
     * AI не идентифицировал — на ручной разбор.
     *
     * @param array{reasoning:string,confidence:float,validation_passed:bool} $decision
     */
    public function persistManualReview(int $emailId, array $decision): void
    {
        DB::connection('reports')->table('unidentified_emails')
            ->where('id', $emailId)
            ->update([
                'status' => 'manual_review',
                'identification_details' => json_encode([
                    'reasoning' => $decision['reasoning'] ?: 'No match found',
                    'confidence' => $decision['confidence'],
                    'validation_passed' => $decision['validation_passed'],
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    /**
     * Кандидатов по домену/токену не нашлось — на ручной разбор (порт «No Candidates Found»).
     */
    public function persistNoCandidates(int $emailId): void
    {
        DB::connection('reports')->table('unidentified_emails')
            ->where('id', $emailId)
            ->update([
                'status' => 'manual_review',
                'reason' => 'no_match',
                'identification_details' => json_encode([
                    'reasoning' => 'No matching batches found by domain or token',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    private function findOrCreateConversation(int $batchId, int $supplierId, ?int $queueId, \DateTimeInterface $now): int
    {
        $existing = DB::connection('reports')->table('email_conversations')
            ->where('batch_id', $batchId)
            ->where('supplier_id', $supplierId)
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        $itemsTotal = (int) (DB::connection('reports')->table('email_batches')
            ->where('id', $batchId)
            ->value('items_count') ?: 0);

        return (int) DB::connection('reports')->table('email_conversations')->insertGetId([
            'batch_id' => $batchId,
            'supplier_id' => $supplierId,
            'outgoing_email_id' => $queueId,
            'status' => 'waiting',
            'items_total' => $itemsTotal,
            'items_covered' => 0,
            'last_activity' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Вставляет email_messages из unidentified_emails. Дедуп по message_id: если
     * сообщение с таким message_id уже есть — возвращаем null (миграция была).
     *
     * @return int|null id нового сообщения либо null если уже существует
     */
    private function saveEmailMessage(object $email, int $conversationId, \DateTimeInterface $now): ?int
    {
        $msgIdHeader = (string) ($email->message_id ?? '');

        if ($msgIdHeader !== '') {
            $dup = DB::connection('reports')->table('email_messages')
                ->where('message_id', $msgIdHeader)
                ->exists();
            if ($dup) {
                return null;
            }
        }

        $receivedAt = $email->received_at ?: $now->format('Y-m-d H:i:s');

        return (int) DB::connection('reports')->table('email_messages')->insertGetId([
            'conversation_id' => $conversationId,
            'direction' => 'incoming',
            'from_email' => Str::limit((string) ($email->from_email ?? ''), 255, ''),
            'to_email' => Str::limit((string) ($email->to_email ?? ''), 255, ''),
            'subject' => ($email->subject ?? null) ?: null,
            'body_text' => ($email->body_text ?? null) ?: null,
            'body_html' => ($email->body_html ?? null) ?: null,
            'message_id' => $msgIdHeader !== '' ? Str::limit($msgIdHeader, 255, '') : null,
            'ai_processed' => 0,
            'signals_processed' => 0,
            'received_at' => $receivedAt,
            'created_at' => $now,
        ]);
    }

    private function migrateAttachments(int $unidentifiedId, int $messageId, \DateTimeInterface $now): void
    {
        $rows = DB::connection('reports')->table('unidentified_email_attachments')
            ->where('unidentified_email_id', $unidentifiedId)
            ->get(['file_name', 'file_path', 'local_path', 'file_type', 'file_size', 'mime_type']);

        foreach ($rows as $r) {
            DB::connection('reports')->table('email_attachments')->insert([
                'email_message_id' => $messageId,
                'file_name' => $r->file_name,
                'file_path' => $r->file_path,
                'local_path' => $r->local_path,
                'file_type' => $r->file_type,
                'file_size' => $r->file_size,
                'mime_type' => $r->mime_type,
                'is_processed' => 0,
                'created_at' => $now,
            ]);
        }
    }
}
