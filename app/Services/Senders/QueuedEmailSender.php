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
        $isBeget = ($sender->smtp_server === 'smtp.beget.com');

        // dual-path / мультиканальность (Phase 3c): job передаёт route ТОЛЬКО для
        // beget-ящика (коннект на IP релея/прямой IP, peer_name=smtp.beget.com, чтобы
        // TLS-сертификат сошёлся; опц. port и bindto источника). Для не-beget отправителя
        // подмену host игнорируем: иначе он полез бы на IP beget со своими кредами и упал
        // бы на авторизации. По умолчанию — smtp_server:smtp_port самого отправителя.
        $useDirect = $isBeget && is_array($route) && !empty($route['host']);
        $host = $useDirect ? (string) $route['host'] : $sender->smtp_server;
        $port = (int) (($isBeget && is_array($route) && !empty($route['port']))
            ? $route['port']
            : ($sender->smtp_port ?: 465));
        $bindTo = ($isBeget && is_array($route) && !empty($route['bindto'])) ? (string) $route['bindto'] : null;
        $transport = new EsmtpTransport($host, $port, $encryption === 'ssl');

        // ВАЖНО: опции потока ставим только через поток транспорта — на EsmtpTransport
        // метода setStreamOptions нет. bindto (source_ip канала) применяем для ЛЮБОГО
        // beget-пути (в т.ч. без подмены host), чтобы канал «только source_ip» не терялся.
        $opts = [];
        if ($useDirect) {
            $opts['ssl'] = [
                'peer_name' => (string) ($route['peer_name'] ?? 'smtp.beget.com'),
                'SNI_enabled' => true,
            ];
        } elseif ($encryption === 'ssl' && !$isBeget) {
            // Не-beget провайдеры отдают ОБЩИЙ сертификат, не совпадающий с smtp.<домен>
            // (Sprinthost: CN=from.sh, SAN=*.from.sh). Терпим mismatch хостнейма —
            // verify_peer (CA-валидность) остаётся включён, TLS шифрует, но строгую
            // проверку имени отключаем, иначе ssl-коннект падает.
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
