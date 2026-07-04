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
     * @param array{host?:string,port?:int,peer_name?:string,bindto?:string}|null $route
     *        маршрут канала релея (Phase 3c) — только для beget-ящика
     * @return string Message-ID отправленного письма (в угловых скобках) для email_messages
     */
    public function send(OutgoingReply $reply, ?array $route = null): string
    {
        $sender = $reply->sender;

        if (!$sender || !$sender->smtp_server || !$sender->smtp_user || !$sender->smtp_password) {
            throw new \RuntimeException('Missing SMTP credentials for sender_id: ' . $reply->sender_id);
        }
        if (!$reply->to_email || !$reply->subject) {
            throw new \RuntimeException('Missing reply data (to/subject) for outgoing_replies id: ' . $reply->id);
        }

        $encryption = $sender->smtp_encryption ?: 'ssl';
        $isBeget = ($sender->smtp_server === 'smtp.beget.com');

        $fromName = trim(preg_replace('/["\'`\\\\]/', '', (string) ($sender->sender_full_name ?: $sender->sender_name ?: '')));
        $fromEmail = $reply->from_email ?: $sender->email;

        // Свой Message-ID генерим ЗАРАНЕЕ (нужен и для микросервиса, и для прямого пути) —
        // возвращаем вызывающему для записи в email_messages (дедуп беседы на приёме).
        $domain = $this->domainFor($fromEmail);
        $messageId = bin2hex(random_bytes(16)) . '@' . $domain;

        // ГЕНЕРИК-ТРАНСПОРТ через микросервис релея (за флагом via_microservice). Threading
        // (In-Reply-To/References) и свой Message-ID передаём кастомными заголовками — /send
        // их поддерживает (доработано). Значения in_reply_to/references хранятся уже с <>.
        // ТОЛЬКО beget (не-beget ящики пиньены под socat) — зеркало QueuedEmailSender.
        $relayMailer = new RelayHttpMailer();
        if ($isBeget && $relayMailer->handlesSender((int) $reply->sender_id)) {
            $payload = [
                'smtp_server' => (string) $sender->smtp_server,
                'smtp_port' => (int) ($sender->smtp_port ?: 465),
                'smtp_user' => (string) $sender->smtp_user,
                'smtp_password' => (string) $sender->smtp_password,
                'smtp_encryption' => $encryption,
                'from_email' => (string) $fromEmail,
                'from_name' => $fromName !== '' ? $fromName : null,
                'to_email' => (string) $reply->to_email,
                'subject' => (string) $reply->subject,
                'body_html' => (string) ($reply->body_html ?? ''),
                'body_text' => (string) ($reply->body_text ?? '') ?: null,
                'attachments' => $this->microserviceAttachments((int) $reply->id),
                'message_id' => '<' . $messageId . '>',
                'verify_cert' => true, // beget: сертификат smtp.beget.com сходится
            ];
            $inReplyTo = trim((string) ($reply->in_reply_to ?? ''));
            if ($inReplyTo !== '') {
                $payload['in_reply_to'] = $inReplyTo;
            }
            $references = trim((string) ($reply->references_header ?? ''));
            if ($references !== '') {
                $payload['references'] = $references;
            }

            $relayMailer->send($payload, (int) $reply->id);

            return '<' . $messageId . '>';
        }

        // Мультиканальность релея (Phase 3c): подмена host/port + bindto источника —
        // только для beget-ящика (зеркало QueuedEmailSender).
        $useDirect = $isBeget && is_array($route) && !empty($route['host']);
        $host = $useDirect ? (string) $route['host'] : $sender->smtp_server;
        $port = (int) (($isBeget && is_array($route) && !empty($route['port']))
            ? $route['port']
            : ($sender->smtp_port ?: 465));
        $bindTo = ($isBeget && is_array($route) && !empty($route['bindto'])) ? (string) $route['bindto'] : null;

        $transport = new EsmtpTransport($host, $port, $encryption === 'ssl');

        // ВАЖНО: опции потока ставим только через поток транспорта — на EsmtpTransport
        // метода setStreamOptions нет (зеркало логики QueuedEmailSender).
        $opts = [];
        if ($useDirect) {
            $opts['ssl'] = [
                'peer_name' => (string) ($route['peer_name'] ?? 'smtp.beget.com'),
                'SNI_enabled' => true,
            ];
        } elseif ($encryption === 'ssl' && !$isBeget) {
            // Не-beget провайдеры отдают ОБЩИЙ сертификат, не совпадающий с smtp.<домен>
            // (Sprinthost в окне parked/UNVERIFIED: CN=from.sh, SAN=*.from.sh). Терпим
            // mismatch хостнейма — verify_peer (CA-валидность) остаётся, TLS шифрует, но
            // строгую проверку имени отключаем, иначе ssl-коннект ответа падает.
            $opts['ssl'] = [
                'verify_peer_name' => false,
                'SNI_enabled' => true,
            ];
        } elseif ($encryption === 'tls') {
            $opts['ssl'] = [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ];
        }
        if ($bindTo !== null) {
            $opts['socket'] = ['bindto' => $bindTo . ':0'];
        }
        if ($opts !== []) {
            $transport->getStream()->setStreamOptions($opts);
        }

        $transport->setUsername($sender->smtp_user);
        $transport->setPassword($sender->smtp_password);

        $mailer = new Mailer($transport);

        // $fromName / $fromEmail / $messageId уже вычислены выше (нужны и для микросервиса).
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

    /**
     * Вложения ответа в формате микросервиса (/send): base64-контент + имя + MIME.
     *
     * @return array<int, array{filename:string, content:string, content_type:string}>
     */
    private function microserviceAttachments(int $replyId): array
    {
        $out = [];
        foreach ($this->attachmentsFor($replyId) as $att) {
            $out[] = [
                'filename' => $att['name'],
                'content' => base64_encode($att['data']),
                'content_type' => $att['mime'],
            ];
        }

        return $out;
    }
}
