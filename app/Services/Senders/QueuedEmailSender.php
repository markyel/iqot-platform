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

        // ГЕНЕРИК-ТРАНСПОРТ через микросервис релея (за флагом via_microservice, опц.
        // белый список sender_id). Провайдер-НЕЗАВИСИМ: релей сам коннектится к
        // smtp_server:smtp_port ящика со своим IP — боевой IP прода не светится, ноль
        // per-domain туннелей. Скрытый трекинг-токен уже вшит в body_html
        // (CampaignEmailBuilder). verify_cert=false для не-beget (общий/несовпадающий
        // сертификат провайдера, напр. wwwsend/sprinthost) — зеркало прямого ssl-пути.
        $relayMailer = new RelayHttpMailer();
        if ($relayMailer->handlesSender((int) $email->sender_id)) {
            $fromName = trim(preg_replace('/["\'`\\\\]/', '', (string) ($sender->sender_full_name ?: $sender->sender_name ?: '')));
            $fromEmail = (string) ($email->from_email ?: $sender->email);
            $bodyHtml = (string) ($email->body_html ?? '');
            $relayMailer->send([
                'smtp_server' => (string) $sender->smtp_server,
                'smtp_port' => (int) ($sender->smtp_port ?: 465),
                'smtp_user' => (string) $sender->smtp_user,
                'smtp_password' => (string) $sender->smtp_password,
                'smtp_encryption' => $encryption,
                'from_email' => $fromEmail,
                'from_name' => $fromName !== '' ? $fromName : null,
                'to_email' => (string) $email->to_email,
                'subject' => $this->sanitizeHeader((string) $email->subject),
                'body_html' => $bodyHtml,
                // Плейн-текстовая альтернатива: HTML-only письмо без text-части — спам-сигнал.
                'body_text' => $this->htmlToText($bodyHtml),
                // Свой Message-ID (иначе письмо уходит вовсе без него → gmail reject,
                // спам-очки у mail.ru/Яндекса). Формат <hex@домен-отправителя>.
                'message_id' => $this->generateMessageId($fromEmail),
                'reply_to' => $fromEmail,
                'attachments' => $this->microserviceAttachments($email->batch_id),
                'verify_cert' => ($encryption === 'ssl' && $isBeget),
            ], (int) $email->id);

            return;
        }

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

        // Symfony Mailer сам ставит Message-ID/Date/MIME-структуру и text-часть по HTML —
        // этот путь корректен; чиним только тему (срез CR/LF от инъекции второго заголовка).
        $message = (new Email())
            ->from($fromName !== '' ? new Address($fromEmail, $fromName) : new Address($fromEmail))
            ->to($email->to_email)
            ->subject($this->sanitizeHeader((string) $email->subject))
            ->text($this->htmlToText((string) ($email->body_html ?? '')))
            ->html($email->body_html ?? '');

        foreach ($this->attachmentsFor($email->batch_id) as $att) {
            $message->addPart(new DataPart($att['data'], $att['name'], $att['mime']));
        }

        $mailer->send($message);
    }

    /**
     * Убрать из значения заголовка (тема) CR/LF и управляющие символы — иначе перенос
     * строки в теме инъектит второй заголовок (gmail: «multiple Subject headers»).
     */
    private function sanitizeHeader(string $value): string
    {
        $value = preg_replace('/[\r\n\t]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', '', $value) ?? $value;
        return trim(preg_replace('/\s{2,}/u', ' ', $value) ?? $value);
    }

    /**
     * Плейн-текстовая версия HTML-письма (для multipart/alternative). Разворачивает
     * <br>/<p>/</div> в переводы строк, срезает теги, декодирует сущности, схлопывает
     * пустые строки. Скрытый 1px-токен («Ref: …») остаётся в тексте — помогает матчингу
     * ответов и не виден в HTML-предпочтительном отображении.
     */
    private function htmlToText(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $t = preg_replace('/<\s*(br|\/p|\/div|\/tr|\/h[1-6]|\/li)\s*>/i', "\n", $html) ?? $html;
        $t = preg_replace('/<\s*(p|div|tr|h[1-6]|li)[^>]*>/i', "\n", $t) ?? $t;
        $t = preg_replace('/<\s*td[^>]*>/i', "\t", $t) ?? $t;
        $t = preg_replace('/<\s*(style|script|head)[^>]*>.*?<\/\s*\1\s*>/is', '', $t) ?? $t;
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('/[ \t]+/', ' ', $t) ?? $t;
        $t = preg_replace('/\n{3,}/', "\n\n", $t) ?? $t;
        $lines = array_map('trim', explode("\n", $t));
        return trim(implode("\n", $lines));
    }

    /**
     * Уникальный Message-ID вида <hex@домен-отправителя> (RFC 5322). Без него письмо
     * уходит вообще без Message-ID (релей ставит только если передан) → gmail reject.
     */
    private function generateMessageId(string $fromEmail): string
    {
        $domain = 'localhost';
        if (str_contains($fromEmail, '@')) {
            $domain = strtolower(trim(substr(strrchr($fromEmail, '@'), 1))) ?: 'localhost';
        }
        return '<' . bin2hex(random_bytes(16)) . '@' . $domain . '>';
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

    /**
     * Вложения батча в формате микросервиса (/send): base64-контент + имя + MIME.
     *
     * @return array<int, array{filename:string, content:string, content_type:string}>
     */
    private function microserviceAttachments($batchId): array
    {
        $out = [];
        foreach ($this->attachmentsFor($batchId) as $att) {
            $out[] = [
                'filename' => $att['name'],
                'content' => base64_encode($att['data']),
                'content_type' => $att['mime'],
            ];
        }

        return $out;
    }
}
