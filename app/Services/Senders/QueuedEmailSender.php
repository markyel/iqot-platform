<?php

namespace App\Services\Senders;

use App\Models\Reports\EmailBatch;
use App\Models\Reports\EmailQueue;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Отправляет одно письмо очереди напрямую через SMTP конкретного отправителя
 * (замена внешнего микросервиса 45.146.167.20:8000/send из n8n-воркфлоу).
 *
 * Вложения берутся из reports.request_item_attachments по позициям батча.
 * Исключение пробрасывается наверх — Job сам решает про ретрай/блокировку.
 */
class QueuedEmailSender
{
    public function send(EmailQueue $email, ?array $route = null): void
    {
        $sender = $email->sender;

        if (!$sender || !$sender->smtp_server || !$sender->smtp_user || !$sender->smtp_password) {
            throw new \RuntimeException('Missing SMTP credentials for sender_id: ' . $email->sender_id);
        }
        if (!$email->to_email || !$email->subject) {
            throw new \RuntimeException('Missing email data (to/subject) for email_queue id: ' . $email->id);
        }

        $encryption = $sender->smtp_encryption ?: 'ssl';
        $port = (int) ($sender->smtp_port ?: 465);

        // Маршрут: по умолчанию smtp_server отправителя (через /etc/hosts → прокси).
        // dual-path: job может передать прямой IP beget — коннектимся на IP, а
        // peer_name=smtp.beget.com, чтобы TLS-сертификат сошёлся.
        $host = (is_array($route) && !empty($route['host'])) ? (string) $route['host'] : $sender->smtp_server;
        $transport = new EsmtpTransport($host, $port, $encryption === 'ssl');

        if (is_array($route) && !empty($route['host'])) {
            $transport->getStream()->setStreamOptions([
                'ssl' => [
                    'peer_name' => (string) ($route['peer_name'] ?? 'smtp.beget.com'),
                    'SNI_enabled' => true,
                ],
            ]);
        } elseif ($encryption === 'tls') {
            $transport->getStream()->setStreamOptions([
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

        // from_name — очищаем кавычки/слеши (как Prepare Request в n8n).
        $fromName = trim(preg_replace('/["\'`\\\\]/', '', (string) ($sender->sender_full_name ?: $sender->sender_name ?: '')));
        $fromEmail = $email->from_email ?: $sender->email;

        $message = (new Email())
            ->from($fromName !== '' ? new Address($fromEmail, $fromName) : new Address($fromEmail))
            ->to($email->to_email)
            ->subject($email->subject)
            ->html($email->body_html ?? '');

        foreach ($this->attachmentsFor($email->batch_id) as $att) {
            $message->addPart(new DataPart($att['data'], $att['name'], $att['mime']));
        }

        $mailer->send($message);
    }

    /**
     * @return array<int, array{data:string, name:string, mime:string}>
     */
    private function attachmentsFor($batchId): array
    {
        if (!$batchId) {
            return [];
        }

        $batch = EmailBatch::find($batchId);
        $itemIds = is_array($batch?->request_items) ? $batch->request_items : [];
        if (empty($itemIds)) {
            return [];
        }

        $rows = DB::connection('reports')->table('request_item_attachments')
            ->whereIn('request_item_id', $itemIds)
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
