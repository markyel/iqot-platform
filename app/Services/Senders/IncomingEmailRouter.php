<?php

namespace App\Services\Senders;

use App\Models\Reports\RecipientMailbox;
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

    // Отбойники (NDR / delivery status notifications). Локальная часть адреса
    // отправителя и подстроки в теме — без них письмо о недоставке прилетает как
    // «неопознанное no_token» (тему/тело не на чем матчить) и тащит свои MIME-куски
    // (возвращённый оригинал message/rfc822 + delivery-status) в вложения.
    private const BOUNCE_FROM_LOCAL = ['mailer-daemon', 'postmaster'];
    private const BOUNCE_SUBJECTS = [
        'undeliverable',
        'mail delivery failed',
        'mail delivery failure',
        'returning message to sender',
        'returned to sender',
        'undelivered mail returned',
        'delivery status notification',
        'delivery has failed',
        'failure notice',
        'не удается доставить',
        'не удалось доставить',
        'недоставленное',
        'сообщение не доставлено',
        'возврат сообщения',
    ];

    // MIME → расширение для безымянных частей (webklex даёт им имя-хеш без
    // расширения → файл в Drive нечитаем). Symfony MimeTypes — фолбэк.
    private const MIME_EXT = [
        'message/rfc822' => 'eml',
        'text/plain' => 'txt',
        'text/html' => 'html',
        'text/csv' => 'csv',
        'text/xml' => 'xml',
        'application/xml' => 'xml',
        'application/json' => 'json',
        'application/pdf' => 'pdf',
        'application/rtf' => 'rtf',
        'application/zip' => 'zip',
        'application/x-7z-compressed' => '7z',
        'application/x-rar-compressed' => 'rar',
        'application/vnd.rar' => 'rar',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/octet-stream' => 'bin',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'image/tiff' => 'tiff',
    ];

    public function __construct(private GoogleDriveUploader $driveUploader)
    {
    }

    /**
     * @return string исход: duplicate | replied | conversation | unidentified | bounce | skipped
     */
    public function route(int $senderId, ParsedEmail $email): string
    {
        if ($email->messageId === '') {
            return 'skipped';
        }

        if ($this->isDuplicate($email->messageId)) {
            return 'duplicate';
        }

        // Отбойник о недоставке: фиксируем как 'bounce' и НЕ матчим батч (иначе
        // токен из возвращённого оригинала создаёт ложную беседу) и НЕ грузим
        // вложения (см. saveUnidentified).
        if ($this->isBounce($email)) {
            $this->saveUnidentified($senderId, $email, 'bounce');
            $this->recordBounceFailure($email);
            return 'bounce';
        }

        $batchId = $this->matchBatch($senderId, $email);

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
     *
     * Скоуп — ТОЛЬКО батчи ящика-отправителя, на который пришёл ответ ($senderId):
     * поставщик отвечает на адрес, с которого его контактировали, поэтому нужный
     * батч всегда среди рассылок этого отправителя. У одного отправителя за 60д
     * единицы батчей (макс ~9), поэтому прежний глобальный limit(200) не нужен — он
     * лишь молча выталкивал батчи 8–60-дневной давности при глобальном поиске и
     * плодил межотправительские коллизии токенов (843 уникальных из 894).
     */
    private function matchBatch(int $senderId, ParsedEmail $email): ?int
    {
        $haystack = $this->searchText($email);

        $batches = DB::connection('reports')->table('email_batches')
            ->where('sender_id', $senderId)
            ->whereIn('status', self::ACTIVE_BATCH_STATUSES)
            ->where('created_at', '>=', now()->subDays(60))
            ->whereNotNull('tracking_token')
            ->where('tracking_token', '!=', '')
            ->orderByDesc('created_at')
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

        // Вложения отбойников (возвращённый оригинал + delivery-status) — мусор:
        // не сохраняем ни локально, ни в Drive, в счётчике их не показываем.
        $storeAttachments = $reason !== 'bounce' && $email->hasAttachments();

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
            'has_attachments' => $storeAttachments ? 1 : 0,
            'attachments_count' => $storeAttachments ? count($email->attachments) : 0,
            'processing_attempts' => 0,
            'received_at' => $receivedAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($storeAttachments) {
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
            // Безымянным частям (имя-хеш без расширения) добавляем расширение по
            // MIME — чтобы файл в Drive/на диске был узнаваем.
            $displayName = $this->displayName((string) $att['name'], (string) $att['mime'], $index);
            $safeName = $this->sanitizeFilename($displayName, $index);
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
                    $displayName,
                    (string) $att['content'],
                    (string) $att['mime']
                );
                if ($driveUrl !== null) {
                    $filePath = $driveUrl;
                }
            }

            $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION)) ?: null;

            DB::connection('reports')->table($table)->insert(array_merge($ownerColumns, [
                'file_name' => Str::limit($displayName, 500, ''),
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

    /**
     * Отбойник о недоставке (NDR): по адресу отправителя (mailer-daemon@/postmaster@)
     * либо по характерной теме письма о недоставке.
     */
    private function isBounce(ParsedEmail $email): bool
    {
        $from = mb_strtolower(trim($email->fromEmail));
        if ($from !== '') {
            $local = explode('@', $from)[0];
            if (in_array($local, self::BOUNCE_FROM_LOCAL, true)) {
                return true;
            }
            foreach (self::BOUNCE_FROM_LOCAL as $needle) {
                if (str_contains($from, $needle . '@')) {
                    return true;
                }
            }
        }

        $subject = mb_strtolower($email->subject);
        if ($subject !== '') {
            foreach (self::BOUNCE_SUBJECTS as $needle) {
                if (str_contains($subject, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Учёт отбойника по битому адресу получателя: вытаскиваем из NDR исходный
     * адрес, на который письмо не доставилось, и копим по нему отбойники через
     * RecipientMailbox::recordBounce(). При достижении порога подряд адрес
     * помечается is_blocked — рассылка перестаёт слать на него (как при ошибках
     * отправки). Порог — общий с отправкой (recipient_error_threshold).
     */
    private function recordBounceFailure(ParsedEmail $email): void
    {
        $failed = $this->extractFailedRecipient($email);
        if ($failed === null) {
            return;
        }

        // Шлём ли мы вообще на этот адрес? Защита от backscatter (подделанный
        // отправитель) и ложного парса: блокируем только то, что сами слали.
        $known = DB::connection('reports')->table('email_queue')
            ->whereRaw('LOWER(to_email) = ?', [$failed])
            ->exists();
        if (!$known) {
            return;
        }

        // Классифицируем ПРИЧИНУ недоставки по телу NDR. Блокируем получателя ТОЛЬКО
        // при постоянной ошибке адресата (ящик не существует). Спам-реджект/репутация
        // («550 spam message rejected» и т.п.) и временные отказы — это проблема НАШЕЙ
        // доставляемости/отправителя, а НЕ вина получателя: живой поставщик не должен
        // получать блок из-за того, что mail.ru забраковал наше письмо как спам.
        // Сендер-сигнал (пометка spam) обрабатывается отдельно (см. sender-механизм).
        $reason = $this->classifyBounceReason($email);
        if ($reason !== 'permanent') {
            \Illuminate\Support\Facades\Log::info('IncomingEmailRouter: bounce НЕ блокирует получателя', [
                'to' => $failed,
                'reason' => $reason,
                'subject' => mb_substr($email->subject, 0, 80),
            ]);
            return;
        }

        RecipientMailbox::recordBounce(
            $failed,
            $email->subject !== '' ? $email->subject : 'NDR',
            (int) config('services.email_dispatch.recipient_error_threshold', 3),
        );
    }

    /**
     * Причина недоставки из тела NDR (+ вложения delivery-status/возвращённый оригинал):
     *   - 'permanent'  — ящик получателя мёртв (user unknown / no such mailbox) → блок ок;
     *   - 'spam'       — наше письмо отклонено как спам/по репутации → НЕ вина получателя;
     *   - 'temporary'  — грейлист/переполнен/временный отказ → не блокируем;
     *   - 'unknown'    — не распознали → консервативно НЕ блокируем.
     * Порядок проверки: сначала постоянная ошибка адресата (она перекрывает всё).
     */
    private function classifyBounceReason(ParsedEmail $email): string
    {
        $h = mb_strtolower($email->bodyText . "\n" . $email->bodyHtml . "\n" . $email->subject);
        foreach ($email->attachments as $att) {
            $h .= "\n" . mb_strtolower((string) ($att['content'] ?? ''));
        }

        if (preg_match('/user unknown|no such user|no such mailbox|does not exist|mailbox unavailable|invalid recipient|recipient address rejected|user not found|550 5\.1\.1|нет такого (?:адреса|пользоват|ящик)|не существует|адрес не найден|неизвестн(?:ый|ому) адрес/u', $h)) {
            return 'permanent';
        }
        if (preg_match('/spam|blacklist|black ?list|listed|reputation|policy reasons|abuse@|blocked using|\brbl\b|dnsbl|554[ -].*(reject|spam|policy)|спам|репутац|заблокирован/u', $h)) {
            return 'spam';
        }
        if (preg_match('/greylist|grey ?list|\b451\b|4\.\d\.\d|try again|temporar|over.?quota|quota exceeded|mailbox full|временн|переполнен|превышен(?:а)? квота/u', $h)) {
            return 'temporary';
        }

        return 'unknown';
    }

    /**
     * Достаём из отбойника (NDR) исходный адрес, на который письмо не дошло.
     * Источник истины — поля DSN (RFC 3464) в теле/части message/delivery-status:
     * Final-Recipient / Original-Recipient, плюс заголовок X-Failed-Recipients
     * возвращённого оригинала. Возвращаем нормализованный адрес или null.
     */
    private function extractFailedRecipient(ParsedEmail $email): ?string
    {
        // Тело + содержимое вложений (delivery-status и возвращённый оригинал
        // несут DSN-поля даже когда сами вложения не сохраняем).
        $haystack = $email->bodyText . "\n" . $email->bodyHtml;
        foreach ($email->attachments as $att) {
            $haystack .= "\n" . (string) ($att['content'] ?? '');
        }

        $patterns = [
            '/(?:Final|Original)-Recipient:\s*[^;\r\n]*;\s*<?([^\s<>,;]+@[^\s<>,;]+)>?/i',
            '/X-Failed-Recipients:\s*<?([^\s<>,;]+@[^\s<>,;]+)>?/i',
        ];

        foreach ($patterns as $re) {
            if (preg_match($re, $haystack, $m) === 1) {
                $addr = mb_strtolower(trim($m[1], " \t<>\"'"));
                if (str_contains($addr, '@')) {
                    return $addr;
                }
            }
        }

        return null;
    }

    /**
     * Имя файла для хранения/Drive: если у части нет расширения — добавляем по MIME.
     */
    private function displayName(string $name, string $mime, int $index): string
    {
        $name = trim($name);
        if ($name === '') {
            $name = "attachment_{$index}";
        }

        if (pathinfo($name, PATHINFO_EXTENSION) !== '') {
            return $name;
        }

        $ext = $this->extensionForMime($mime);

        return $ext !== null ? "{$name}.{$ext}" : $name;
    }

    private function extensionForMime(string $mime): ?string
    {
        $mime = strtolower(trim($mime));
        if ($mime === '') {
            return null;
        }

        // Отсекаем параметры, например "text/plain; charset=utf-8".
        $mime = trim(explode(';', $mime)[0]);

        if (isset(self::MIME_EXT[$mime])) {
            return self::MIME_EXT[$mime];
        }

        $exts = \Symfony\Component\Mime\MimeTypes::getDefault()->getExtensions($mime);

        return $exts[0] ?? null;
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
