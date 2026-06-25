<?php

namespace App\Services\Senders;

use App\Support\Mail\ParsedEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Маршрутизация входящего письма — порт n8n-воркфлоу «Receive and Route Emails v3».
 *
 * Шаги (как в графе n8n):
 *  1. Дедуп по message_id (email_messages).
 *  2. Матч батча по email_batches.tracking_token (подстрока в теме/теле).
 *  3. Матч полного токена email_queue.token внутри батча → supplier_id/queue_id.
 *  4. Привязано → беседа (email_conversations + email_messages), исходящее → 'replied'.
 *     Не привязано → unidentified_emails.
 *  5. Вложения — на локальный диск public + строки в email_attachments /
 *     unidentified_email_attachments.
 *
 * Чиним баги n8n: email_conversations.status='waiting' (а не несуществующий 'active'),
 * items_total из email_batches.items_count (NOT NULL без дефолта валил INSERT).
 *
 * Всё через reports-коннект (как QueuedEmailSender). AI-классификация не здесь —
 * колонки ai_* остаются пустыми, их обрабатывает отдельный downstream.
 */
class IncomingEmailRouter
{
    private const ACTIVE_BATCH_STATUSES = ['queued', 'sending', 'sent', 'completed'];
    private const ATTACH_DISK = 'public';
    private const ATTACH_ROOT = 'email-attachments';

    public function __construct(private GoogleDriveUploader $driveUploader)
    {
    }

    /**
     * @return string исход: duplicate | replied | conversation | unidentified | skipped
     */
    public function route(int $senderId, ParsedEmail $email): string
    {
        if ($email->messageId === '') {
            return 'skipped';
        }

        if ($this->isDuplicate($email->messageId)) {
            return 'duplicate';
        }

        $batchId = $this->matchBatch($email);

        if (!$batchId) {
            $this->saveUnidentified($senderId, $email, 'no_token');
            return 'unidentified';
        }

        [$queueId, $supplierId] = $this->matchQueue($batchId, $email);

        // Батч есть, но конкретный поставщик не вычислен по токену —
        // пробуем по адресу отправителя (ветка «Find Supplier by Email» из n8n).
        if (!$supplierId) {
            $supplierId = $this->findSupplierByEmail($email->fromEmail);
        }

        if (!$supplierId) {
            $this->saveUnidentified($senderId, $email, 'no_supplier');
            return 'unidentified';
        }

        $this->handleConversation($batchId, $supplierId, $queueId, $email);

        return $queueId ? 'replied' : 'conversation';
    }

    private function isDuplicate(string $messageId): bool
    {
        return DB::connection('reports')->table('email_messages')
            ->where('message_id', $messageId)
            ->exists();
    }

    /**
     * Ищем активный батч, чей tracking_token встречается в теме/теле письма.
     */
    private function matchBatch(ParsedEmail $email): ?int
    {
        $haystack = $this->searchText($email);

        $batches = DB::connection('reports')->table('email_batches')
            ->whereIn('status', self::ACTIVE_BATCH_STATUSES)
            ->where('created_at', '>=', now()->subDays(60))
            ->whereNotNull('tracking_token')
            ->where('tracking_token', '!=', '')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'tracking_token']);

        foreach ($batches as $batch) {
            $token = (string) $batch->tracking_token;
            if (strlen($token) >= 5 && str_contains($haystack, $token)) {
                return (int) $batch->id;
            }
        }

        return null;
    }

    /**
     * Внутри батча матчим полный email_queue.token → [queueId, supplierId].
     *
     * @return array{0:?int,1:?int}
     */
    private function matchQueue(int $batchId, ParsedEmail $email): array
    {
        $haystack = $this->searchText($email);

        $rows = DB::connection('reports')->table('email_queue')
            ->where('batch_id', $batchId)
            ->get(['id', 'token', 'supplier_id']);

        foreach ($rows as $row) {
            $token = (string) ($row->token ?? '');
            if ($token !== '' && str_contains($haystack, $token)) {
                return [(int) $row->id, $row->supplier_id ? (int) $row->supplier_id : null];
            }
        }

        return [null, null];
    }

    private function findSupplierByEmail(string $fromEmail): ?int
    {
        if ($fromEmail === '') {
            return null;
        }

        $id = DB::connection('reports')->table('suppliers')
            ->where('email', $fromEmail)
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function handleConversation(int $batchId, int $supplierId, ?int $queueId, ParsedEmail $email): void
    {
        $now = now();
        $receivedAt = $email->date ? $email->date->format('Y-m-d H:i:s') : $now->format('Y-m-d H:i:s');

        $conversationId = $this->findOrCreateConversation($batchId, $supplierId, $queueId, $now);

        $messageId = (int) DB::connection('reports')->table('email_messages')->insertGetId([
            'conversation_id' => $conversationId,
            'direction' => 'incoming',
            'from_email' => Str::limit($email->fromEmail, 255, ''),
            'to_email' => Str::limit($email->toEmail, 255, ''),
            'subject' => $email->subject !== '' ? $email->subject : null,
            'body_text' => $email->bodyText !== '' ? $email->bodyText : null,
            'body_html' => $email->bodyHtml !== '' ? $email->bodyHtml : null,
            'message_id' => Str::limit($email->messageId, 255, ''),
            'in_reply_to' => $email->inReplyTo !== '' ? Str::limit($email->inReplyTo, 255, '') : null,
            'references_header' => $email->references !== '' ? $email->references : null,
            'ai_processed' => 0,
            'signals_processed' => 0,
            'received_at' => $receivedAt,
            'created_at' => $now,
        ]);

        // Беседа «ожила» — двигаем активность.
        DB::connection('reports')->table('email_conversations')
            ->where('id', $conversationId)
            ->update(['last_activity' => $now, 'updated_at' => $now]);

        if ($queueId) {
            DB::connection('reports')->table('email_queue')
                ->where('id', $queueId)
                ->update([
                    'status' => 'replied',
                    'replied_at' => $now,
                    'supplier_response_subject' => Str::limit($email->subject, 500, ''),
                    'supplier_response_preview' => Str::limit(strip_tags($email->bodyText ?: $email->bodyHtml), 1000, ''),
                    'updated_at' => $now,
                ]);
        }

        if ($email->hasAttachments()) {
            $this->storeAttachments('email_attachments', 'msg', $messageId, $email, [
                'email_message_id' => $messageId,
            ]);
        }
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

    private function saveUnidentified(int $senderId, ParsedEmail $email, string $reason): void
    {
        $now = now();
        $receivedAt = $email->date ? $email->date->format('Y-m-d H:i:s') : $now->format('Y-m-d H:i:s');

        // Гонка/повтор: уникальный message_id защищает от дублей.
        $exists = DB::connection('reports')->table('unidentified_emails')
            ->where('message_id', $email->messageId)
            ->exists();
        if ($exists) {
            return;
        }

        $id = (int) DB::connection('reports')->table('unidentified_emails')->insertGetId([
            'sender_id' => $senderId,
            'message_id' => Str::limit($email->messageId, 255, ''),
            'from_email' => Str::limit($email->fromEmail, 255, ''),
            'to_email' => Str::limit($email->toEmail, 255, ''),
            'subject' => $email->subject !== '' ? $email->subject : null,
            'body_text' => $email->bodyText !== '' ? $email->bodyText : null,
            'body_html' => $email->bodyHtml !== '' ? $email->bodyHtml : null,
            'reason' => $reason,
            'status' => 'pending',
            'has_attachments' => $email->hasAttachments() ? 1 : 0,
            'attachments_count' => count($email->attachments),
            'processing_attempts' => 0,
            'received_at' => $receivedAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($email->hasAttachments()) {
            $this->storeAttachments('unidentified_email_attachments', 'unident', $id, $email, [
                'unidentified_email_id' => $id,
            ]);
        }
    }

    /**
     * Кладём вложения на диск public и пишем строки в таблицу вложений.
     *
     * @param array<string, int> $ownerColumns столбец-владелец → id
     */
    private function storeAttachments(string $table, string $subdir, int $ownerId, ParsedEmail $email, array $ownerColumns): void
    {
        $now = now();

        foreach ($email->attachments as $index => $att) {
            $safeName = $this->sanitizeFilename($att['name'], $index);
            $localPath = self::ATTACH_ROOT . "/{$subdir}/{$ownerId}/{$safeName}";

            try {
                Storage::disk(self::ATTACH_DISK)->put($localPath, $att['content']);
            } catch (\Throwable $e) {
                // Файл не записался — строку вложения не создаём, но письмо не валим.
                continue;
            }

            // Переходный период: дублируем в Google Drive и кладём Drive-URL в
            // file_path (его читает downstream-воркфлоу). Локальная копия —
            // источник истины (local_path). Если Drive выключен/упал — file_path
            // остаётся локальным путём.
            $filePath = $localPath;
            if ($this->driveUploader->isEnabled()) {
                $driveUrl = $this->driveUploader->upload(
                    (string) $att['name'],
                    (string) $att['content'],
                    (string) $att['mime']
                );
                if ($driveUrl !== null) {
                    $filePath = $driveUrl;
                }
            }

            $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION)) ?: null;

            DB::connection('reports')->table($table)->insert(array_merge($ownerColumns, [
                'file_name' => Str::limit($att['name'], 500, ''),
                'file_path' => Str::limit($filePath, 1000, ''),
                'local_path' => $localPath,
                'file_type' => $ext,
                'file_size' => (int) $att['size'],
                'mime_type' => Str::limit($att['mime'], 255, ''),
                'is_processed' => 0,
                'created_at' => $now,
            ]));
        }
    }

    private function sanitizeFilename(string $name, int $index): string
    {
        $name = str_replace(['/', '\\', "\0"], '', $name);
        $name = preg_replace('/[\x00-\x1F]/', '', $name) ?: '';
        $name = trim($name);

        if ($name === '' || $name === '.' || $name === '..') {
            $name = "attachment_{$index}";
        }

        // Префикс индексом — защита от коллизий имён в одной папке.
        return $index . '_' . Str::limit($name, 180, '');
    }

    private function searchText(ParsedEmail $email): string
    {
        return $email->subject
            . ' ' . mb_substr($email->bodyText, 0, 3000)
            . ' ' . mb_substr($email->bodyHtml, 0, 3000);
    }
}
