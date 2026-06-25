<?php

namespace App\Support\Mail;

/**
 * Нормализованное входящее письмо — общий формат между IMAP-ридером и роутером
 * (замена JSON-ответа внешнего микросервиса 45.146.167.20:8000/receive из n8n).
 *
 * attachments: array<int, array{name:string, content:string, mime:string, size:int}>
 * (content — бинарь, уже декодированный из base64/transfer-encoding).
 */
class ParsedEmail
{
    /**
     * @param array<int, array{name:string, content:string, mime:string, size:int}> $attachments
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $fromEmail,
        public readonly string $toEmail,
        public readonly string $subject,
        public readonly string $bodyText,
        public readonly string $bodyHtml,
        public readonly string $inReplyTo,
        public readonly string $references,
        public readonly ?\DateTimeInterface $date,
        public readonly array $attachments = [],
    ) {
    }

    public function hasAttachments(): bool
    {
        return $this->attachments !== [];
    }
}
