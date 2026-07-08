<?php

namespace App\Services\Senders;

use App\Models\Reports\Sender;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Webklex\PHPIMAP\ClientManager;

/**
 * Проверка подключаемости ящика-отправителя: реальный SMTP-AUTH + IMAP-LOGIN по
 * сохранённым кредам. Отлавливает неверный пароль/хост/порт ДО ввода ящика в
 * рассылку (иначе «фейл отправки» маскируется под спам). Сертификат намеренно
 * не валидируем — проверяем ИМЕННО логин/пароль, а не цепочку доверия.
 *
 * Переиспользуется веб-кнопкой на странице импорта и командой emails:check-senders.
 */
class SenderConnectivityChecker
{
    public function __construct(private readonly int $timeout = 15)
    {
    }

    /**
     * @return array{smtp:bool, imap:bool, smtp_error:?string, imap_error:?string, ok:bool}
     */
    public function check(Sender $sender): array
    {
        [$smtpOk, $smtpErr] = $this->checkSmtp($sender);
        [$imapOk, $imapErr] = $this->checkImap($sender);

        return [
            'smtp' => $smtpOk,
            'imap' => $imapOk,
            'smtp_error' => $smtpErr,
            'imap_error' => $imapErr,
            'ok' => $smtpOk && $imapOk,
        ];
    }

    /** Короткая человекочитаемая сводка ошибок (пусто, если всё ок). */
    public function summary(array $result): string
    {
        $parts = [];
        if (!($result['smtp'] ?? false)) {
            $parts[] = 'SMTP: ' . ($result['smtp_error'] ?: 'ошибка');
        }
        if (!($result['imap'] ?? false)) {
            $parts[] = 'IMAP: ' . ($result['imap_error'] ?: 'ошибка');
        }
        return implode(' | ', $parts);
    }

    /** @return array{0:bool,1:?string} */
    private function checkSmtp(Sender $s): array
    {
        if (!$s->smtp_server || !$s->smtp_user || !$s->smtp_password) {
            return [false, 'нет SMTP-кредов'];
        }
        try {
            $tls = ((string) $s->smtp_encryption === 'ssl') || (int) $s->smtp_port === 465;
            $t = new EsmtpTransport((string) $s->smtp_server, (int) ($s->smtp_port ?: 465), $tls);
            $t->setUsername((string) $s->smtp_user);
            $t->setPassword((string) $s->smtp_password);
            $stream = $t->getStream();
            if (method_exists($stream, 'setStreamOptions')) {
                $stream->setStreamOptions(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
            }
            if (method_exists($stream, 'setTimeout')) {
                $stream->setTimeout($this->timeout);
            }
            $t->start();
            $t->stop();
            return [true, null];
        } catch (\Throwable $e) {
            return [false, $this->clean($e->getMessage())];
        }
    }

    /** @return array{0:bool,1:?string} */
    private function checkImap(Sender $s): array
    {
        if (!$s->imap_server || !($s->imap_user ?: $s->email) || !$s->imap_password) {
            return [false, 'нет IMAP-кредов'];
        }
        try {
            $client = (new ClientManager())->make([
                'host' => (string) $s->imap_server,
                'port' => (int) ($s->imap_port ?: 993),
                'encryption' => ((string) $s->imap_encryption ?: 'ssl'),
                'validate_cert' => false,
                'username' => (string) ($s->imap_user ?: $s->email),
                'password' => (string) $s->imap_password,
                'protocol' => 'imap',
                'timeout' => $this->timeout,
            ]);
            $client->connect();
            $client->getFolders();
            $client->disconnect();
            return [true, null];
        } catch (\Throwable $e) {
            return [false, $this->clean($e->getMessage())];
        }
    }

    private function clean(string $msg): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $msg)), 0, 140);
    }
}
