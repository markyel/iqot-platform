<?php

namespace App\Services\Senders;

use Illuminate\Support\Facades\Http;

/**
 * Генерик-транспорт отправки через микросервис релея (universal-email-service, FastAPI
 * :8000 POST /send). Вместо Symfony Mailer → socat (прошитый per-domain туннель) шлём
 * HTTP-запрос на релей с кредами ящика; сам SMTP наружу делает релей со СВОИМ IP —
 * боевой IP прода не светится, ноль per-domain настройки под провайдера.
 *
 * За флагом services.email_dispatch.via_microservice. Опциональный белый список
 * sender_id (microservice_sender_ids) — для обкатки на одном ящике перед полным вводом.
 *
 * Исключение при неуспехе пробрасывается наверх — вызывающий Job классифицирует ошибку
 * (ретрай/деактивация ящика) по её тексту, как и для прямого SMTP. Текст SMTP-ошибки
 * микросервис сохраняет в detail ответа (535/550/ratelimit/таймаут распознаются джобом).
 */
class RelayHttpMailer
{
    /**
     * Включён ли генерик-транспорт (флаг + настроенный URL релея).
     */
    public function isEnabled(): bool
    {
        return (bool) config('services.email_dispatch.via_microservice', false)
            && (string) config('services.email_dispatch.microservice_url', '') !== '';
    }

    /**
     * Слать ли ЭТОТ ящик через микросервис. Если белый список sender_id пуст —
     * все ящики; иначе только перечисленные (обкатка на одном перед полным вводом).
     */
    public function handlesSender(int $senderId): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        $ids = (array) config('services.email_dispatch.microservice_sender_ids', []);
        if ($ids === []) {
            return true;
        }

        return in_array($senderId, array_map('intval', $ids), true);
    }

    /**
     * POST /send на микросервис релея.
     *
     * @param array<string,mixed> $payload тело запроса (см. SendEmailRequest микросервиса):
     *   smtp_server/smtp_port/smtp_user/smtp_password/smtp_encryption, from_email/from_name,
     *   to_email/subject/body_html[/body_text], attachments[], reply_to,
     *   message_id/in_reply_to/references (threading), verify_cert.
     * @return string|null Message-ID отправленного письма (из ответа), если вернулся
     * @throws \RuntimeException при не-2xx ответе / success=false — с текстом ошибки SMTP
     */
    public function send(array $payload): ?string
    {
        $base = (string) config('services.email_dispatch.microservice_url', '');
        $key = (string) config('services.email_dispatch.microservice_api_key', '');
        $timeout = (int) config('services.email_dispatch.microservice_timeout', 60);

        if ($base === '') {
            throw new \RuntimeException('microservice_url не задан для генерик-транспорта');
        }

        // Микросервис сам ретраит внутри (retry_attempts) — отключаем, ретраем на уровне
        // Job (его логика блокировок/пейсинга). timeout запроса к SMTP чуть меньше HTTP.
        $payload['retry_attempts'] = 1;
        $payload['timeout'] = (int) ($payload['timeout'] ?? max(15, $timeout - 10));

        $response = Http::timeout($timeout)
            ->connectTimeout(15)
            ->withHeaders(['X-API-Key' => $key])
            ->acceptJson()
            ->asJson()
            ->post($base . '/send', $payload);

        if (!$response->successful()) {
            // Достаём detail FastAPI (там текст SMTP-ошибки: 535/550/ratelimit/таймаут) —
            // Job классифицирует по нему. HTTP 401/503 = проблема ключа/конфига релея.
            $detail = '';
            try {
                $json = $response->json();
                $detail = is_array($json) ? (string) ($json['detail'] ?? '') : '';
            } catch (\Throwable) {
                // не-JSON тело
            }
            $body = $detail !== '' ? $detail : mb_substr((string) $response->body(), 0, 500);
            throw new \RuntimeException('microservice /send HTTP ' . $response->status() . ': ' . $body);
        }

        $json = $response->json();
        if (!is_array($json) || !($json['success'] ?? false)) {
            $msg = is_array($json) ? (string) ($json['message'] ?? 'unknown') : 'non-json response';
            throw new \RuntimeException('microservice /send неуспех: ' . $msg);
        }

        $mid = $json['message_id'] ?? null;

        return is_string($mid) && $mid !== '' ? $mid : null;
    }
}
