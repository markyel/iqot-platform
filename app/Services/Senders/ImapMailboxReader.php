<?php

namespace App\Services\Senders;

use App\Models\Reports\Sender;
use App\Support\Mail\ParsedEmail;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/**
 * Нативный IMAP-ридер на webklex/php-imap (чистый PHP, без ext-imap).
 * Замена внешнего микросервиса 45.146.167.20:8000/receive из n8n-воркфлоу.
 *
 * Подключается по IMAP-кредам конкретного отправителя, забирает непрочитанные
 * письма INBOX (НЕ помечая прочитанными при выборке) и отдаёт их как ParsedEmail.
 * Пометку \Seen вешает вызывающий код через markSeen() — только после успешной
 * обработки, чтобы упавшее письмо перечиталось на следующем тике.
 */
class ImapMailboxReader
{
    /** @var array<int, Message> uid => Message, чтобы пометить \Seen после обработки */
    private array $fetched = [];

    /**
     * @return array<int, ParsedEmail> ключ — IMAP uid письма
     */
    public function fetchUnseen(Sender $sender, int $limit = 20): array
    {
        if (!$sender->imap_server || !$sender->imap_user || !$sender->imap_password) {
            throw new \RuntimeException('Missing IMAP credentials for sender_id: ' . $sender->id);
        }

        $encryption = $this->normalizeEncryption($sender->imap_encryption, (int) $sender->imap_port);

        $cm = new ClientManager();
        $client = $cm->make([
            'host' => $sender->imap_server,
            'port' => (int) ($sender->imap_port ?: 993),
            'protocol' => 'imap',
            'encryption' => $encryption,        // ssl | tls | false
            'validate_cert' => false,           // самоподписанные у части хостингов
            'username' => $sender->imap_user,
            'password' => $sender->imap_password,
            'authentication' => null,
            'timeout' => 30,
        ]);

        $client->connect();

        try {
            $inbox = $client->getFolderByName('INBOX');
            if (!$inbox) {
                return [];
            }

            $messages = $inbox->query()
                ->leaveUnread()      // выборка не вешает \Seen
                ->whereUnseen()
                ->setFetchOrderDesc()
                ->limit(max(1, $limit))
                ->get();

            $result = [];
            foreach ($messages as $message) {
                /** @var Message $message */
                $uid = (int) $message->getUid();
                $this->fetched[$uid] = $message;
                $result[$uid] = $this->toParsed($message);
            }

            return $result;
        } finally {
            // соединение закроется в Client::__destruct, но markSeen ещё нужен —
            // храним $client живым через $this->fetched (Message держит client).
        }
    }

    /**
     * Пометить письмо прочитанным (после успешной обработки).
     */
    public function markSeen(int $uid): void
    {
        $message = $this->fetched[$uid] ?? null;
        if ($message) {
            $message->setFlag('Seen');
        }
    }

    private function toParsed(Message $message): ParsedEmail
    {
        $from = $message->getFrom();
        $to = $message->getTo();

        $attachments = [];
        foreach ($message->getAttachments() as $att) {
            $content = (string) $att->getContent();
            if ($content === '' || !$att->getName()) {
                continue;
            }
            $attachments[] = [
                'name' => (string) $att->getName(),
                'content' => $content,
                'mime' => $att->getMimeType() ?: 'application/octet-stream',
                'size' => (int) ($att->getSize() ?: strlen($content)),
            ];
        }

        $date = null;
        try {
            $raw = $message->getDate();
            if ($raw && (string) $raw !== '') {
                $date = $raw->toDate(); // Carbon
            }
        } catch (\Throwable) {
            $date = null;
        }

        return new ParsedEmail(
            messageId: $this->str($message->getMessageId()),
            fromEmail: $from && $from->first() ? ($from->first()->mail ?: '') : '',
            toEmail: $to && $to->first() ? ($to->first()->mail ?: '') : '',
            subject: $this->str($message->getSubject()),
            bodyText: (string) $message->getTextBody(),
            bodyHtml: (string) $message->getHTMLBody(),
            inReplyTo: $this->str($message->getInReplyTo()),
            references: $this->str($message->getReferences()),
            date: $date,
            attachments: $attachments,
        );
    }

    /**
     * webklex-геттеры возвращают Attribute (или строку) — приводим к строке безопасно.
     */
    private function str(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        return trim((string) $value);
    }

    /**
     * Карта encryption из senders.imap_encryption в формат webklex.
     * Пусто/«ssl» при 993 → ssl; «tls»/«starttls» → tls; «none»/143 без шифра → false.
     */
    private function normalizeEncryption(?string $enc, int $port): string|false
    {
        $enc = strtolower(trim((string) $enc));

        return match ($enc) {
            'ssl' => 'ssl',
            'tls', 'starttls' => 'tls',
            'none', 'false', '0' => false,
            default => $port === 143 ? 'tls' : 'ssl',
        };
    }
}
