<?php

namespace App\Services\Senders;

use App\Models\Reports\OutgoingReply;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Отправляет один готовый ответ из reports.outgoing_replies напрямую через SMTP
 * отправителя (замена внешнего микросервиса 45.146.167.20:8000/send из n8n-воркфлоу
 * «Send Outgoing Replies»).
 *
 * В отличие от массовой рассылки (QueuedEmailSender) проставляет заголовки threading
 * (In-Reply-To / References — порт reply.in_reply_to / reply.references_header), чтобы
 * ответ лёг в ту же цепочку у поставщика, и сам генерит Message-ID (возвращается
 * вызывающему для записи в email_messages — раньше его отдавал микросервис).
 *
 * Вложения берутся из reports.outgoing_reply_attachments (BLOB file_data).
 * Исключение пробрасывается наверх — Job сам решает про failed/блокировку отправителя.
 */
class OutgoingReplySender
{
    /**
     * @return string Message-ID отправленного письма (в угловых скобках) для email_messages
     */
    public function send(OutgoingReply $reply): string
    {
        $sender = $reply->sender;

        if (!$sender || !$sender->smtp_server || !$sender->smtp_user || !$sender->smtp_password) {
            throw new \RuntimeException('Missing SMTP credentials for sender_id: ' . $reply->sender_id);
        }
        if (!$reply->to_email || !$reply->subject) {
            throw new \RuntimeException('Missing reply data (to/subject) for outgoing_replies id: ' . $reply->id);
        }

        $encryption = $sender->smtp_encryption ?: 'ssl';
        $port = (int) ($sender->smtp_port ?: 465);

        $transport = new EsmtpTransport($sender->smtp_server, $port, $encryption === 'ssl');

        if ($encryption === 'tls') {
            $transport->setStreamOptions([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ]);
        }

        $transport->setUsername($sender->smtp_user);
        $transport->setPassword($sender->smtp_password);

        $mailer = new Mailer($transport);

        $fromName = trim(preg_replace('/["\'`\\\\]/', '', (string) ($sender->sender_full_name ?: $sender->sender_name ?: '')));
        $fromEmail = $reply->from_email ?: $sender->email;

        // Генерим Message-ID сами (раньше его возвращал микросервис → email_messages).
        $domain = $this->domainFor($fromEmail);
        $messageId = bin2hex(random_bytes(16)) . '@' . $domain;

        $message = (new Email())
            ->from($fromName !== '' ? new Address($fromEmail, $fromName) : new Address($fromEmail))
            ->to($reply->to_email)
            ->subject($reply->subject);

        $bodyHtml = (string) ($reply->body_html ?? '');
        if ($bodyHtml !== '') {
            $message->html($bodyHtml);
        }
        $bodyText = (string) ($reply->body_text ?? '');
        if ($bodyText !== '') {
            $message->text($bodyText);
        }

        $headers = $message->getHeaders();
        $headers->addIdHeader('Message-ID', $messageId);

        // Threading: значения хранятся уже как Message-ID (с угловыми скобками) —
        // отдаём их как есть текстовыми заголовками (порт n8n Prepare Email Request).
        $inReplyTo = trim((string) ($reply->in_reply_to ?? ''));
        if ($inReplyTo !== '') {
            $headers->addTextHeader('In-Reply-To', $inReplyTo);
        }
        $references = trim((string) ($reply->references_header ?? ''));
        if ($references !== '') {
            $headers->addTextHeader('References', $references);
        }

        foreach ($this->attachmentsFor((int) $reply->id) as $att) {
            $message->addPart(new DataPart($att['data'], $att['name'], $att['mime']));
        }

        $mailer->send($message);

        return '<' . $messageId . '>';
    }

    private function domainFor(string $email): string
    {
        $at = strrpos($email, '@');
        $domain = $at !== false ? substr($email, $at + 1) : '';

        return $domain !== '' ? $domain : 'localhost';
    }

    /**
     * Порт «Get Attachments»: вложения ответа из outgoing_reply_attachments (BLOB).
     *
     * @return array<int, array{data:string, name:string, mime:string}>
     */
    private function attachmentsFor(int $replyId): array
    {
        $rows = DB::connection('reports')->table('outgoing_reply_attachments')
            ->where('outgoing_reply_id', $replyId)
            ->whereNotNull('file_data')
            ->get(['file_name', 'mime_type', 'file_data']);

        $attachments = [];
        foreach ($rows as $row) {
            if (!$row->file_data || !$row->file_name) {
                continue;
            }
            $attachments[] = [
                'data' => $row->file_data,
                'name' => $row->file_name,
                'mime' => $row->mime_type ?: 'application/octet-stream',
            ];
        }

        return $attachments;
    }
}
