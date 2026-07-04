<?php

namespace App\Services\Senders;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Генерик-транспорт отправки через микросервис релея (universal-email-service, FastAPI
 * :8000 POST /send). Вместо Symfony Mailer → socat (прошитый per-domain туннель) шлём
 * HTTP-запрос на релей с кредами ящика; сам SMTP наружу делает релей со СВОИМ IP —
 * боевой IP прода не светится, ноль per-domain настройки под провайдера.
 *
 * ПУЛ РЕЛЕЕВ: отправка раскладывается per-send по всему пулу микросервисов (по routing-
 * ключу = id письма/ответа, взвешенно — как RelayChannelSelector для socat-каналов). Если
 * выбранный релей НЕДОСТУПЕН (connect-ошибка/refused/DNS) — пробуем следующий узел
 * (failover). ВАЖНО: failover ТОЛЬКО на недоступности релея. SMTP-ошибка провайдера
 * (535/550/ratelimit) приходит как HTTP 500 с текстом — её НЕ фейловерим и НЕ ретраим
 * здесь (иначе двойная отправка / повторная неудачная авторизация → бан IP); её
 * пробрасываем джобу, он классифицирует (деактивация ящика/ретрай).
 *
 * За флагом services.email_dispatch.via_microservice. Опциональный белый список
 * sender_id (microservice_sender_ids) — для обкатки. Применяется вызывающим ТОЛЬКО к
 * beget-ящикам (не-beget пинятся под socat: их smtp_port/host релей-специфичны).
 */
class RelayHttpMailer
{
    /**
     * Пул релеев: [['url'=>'http://ip:8000','weight'=>1], ...]. Источник — JSON
     * microservice_urls; при пустом — одиночный microservice_url (fallback).
     *
     * @return array<int, array{url:string, weight:int}>
     */
    public function endpoints(): array
    {
        $list = [];
        foreach ((array) config('services.email_dispatch.microservice_urls', []) as $e) {
            if (is_string($e)) {
                $url = trim($e);
                $weight = 1;
            } elseif (is_array($e)) {
                $url = trim((string) ($e['url'] ?? ''));
                $weight = max(1, (int) ($e['weight'] ?? 1));
            } else {
                continue;
            }
            if ($url !== '') {
                $list[] = ['url' => rtrim($url, '/'), 'weight' => $weight];
            }
        }

        if ($list === []) {
            $single = (string) config('services.email_dispatch.microservice_url', '');
            if ($single !== '') {
                $list[] = ['url' => rtrim($single, '/'), 'weight' => 1];
            }
        }

        return $list;
    }

    /**
     * Включён ли генерик-транспорт (флаг + хотя бы один релей в пуле).
     */
    public function isEnabled(): bool
    {
        return (bool) config('services.email_dispatch.via_microservice', false)
            && $this->endpoints() !== [];
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
     * POST /send на микросервис релея (с failover по пулу при недоступности узла).
     *
     * @param array<string,mixed> $payload тело запроса (см. SendEmailRequest микросервиса):
     *   smtp_server/smtp_port/smtp_user/smtp_password/smtp_encryption, from_email/from_name,
     *   to_email/subject/body_html[/body_text], attachments[], reply_to,
     *   message_id/in_reply_to/references (threading), verify_cert.
     * @param int|null $routingKey ключ распределения по пулу (id письма/ответа) — поток
     *   каждого ящика ровно раскладывается по всем релеям. null → первый узел.
     * @return string|null Message-ID отправленного письма (из ответа), если вернулся
     * @throws \RuntimeException при не-2xx / success=false (текст SMTP-ошибки) или если
     *   все релеи недоступны
     */
    public function send(array $payload, ?int $routingKey = null): ?string
    {
        $pool = $this->endpoints();
        if ($pool === []) {
            throw new \RuntimeException('микросервис: пул релеев пуст (microservice_urls/url не заданы)');
        }

        $key = (string) config('services.email_dispatch.microservice_api_key', '');
        $timeout = (int) config('services.email_dispatch.microservice_timeout', 60);

        // Микросервис сам ретраит внутри (retry_attempts) — отключаем, ретраем на уровне
        // Job (его логика блокировок/пейсинга). timeout SMTP чуть меньше HTTP-таймаута.
        $payload['retry_attempts'] = 1;
        $payload['timeout'] = (int) ($payload['timeout'] ?? max(15, $timeout - 10));

        // Порядок обхода: выбранный по ключу узел первым, остальные — резерв для failover.
        $order = $this->failoverOrder($pool, $routingKey);
        $lastConnError = null;

        foreach ($order as $ep) {
            try {
                $response = Http::timeout($timeout)
                    ->connectTimeout(12)
                    ->withHeaders(['X-API-Key' => $key])
                    ->acceptJson()
                    ->asJson()
                    ->post($ep['url'] . '/send', $payload);
            } catch (ConnectionException $e) {
                // Различаем НЕДОСТУПНОСТЬ релея (failover на следующий) и таймаут ЧТЕНИЯ
                // (SMTP уже мог уйти — НЕ фейловерим, иначе дубль; пробрасываем джобу).
                if ($this->isRelayDown($e->getMessage())) {
                    $lastConnError = $e;
                    continue;
                }
                throw new \RuntimeException('microservice /send transport: ' . $e->getMessage(), 0, $e);
            }

            // Получили HTTP-ответ от релея → обрабатываем как есть (SMTP-ошибки НЕ фейловерим).
            if (!$response->successful()) {
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

        // Все релеи недоступны.
        throw new \RuntimeException(
            'microservice /send: все релеи недоступны ('
            . ($lastConnError ? $lastConnError->getMessage() : 'unknown') . ')'
        );
    }

    /**
     * Порядок обхода пула: сначала выбранный по routing-ключу (взвешенно, стабильно —
     * поток ящика ровно раскладывается по релеям), затем остальные как резерв failover.
     *
     * @param array<int, array{url:string, weight:int}> $pool
     * @return array<int, array{url:string, weight:int}>
     */
    private function failoverOrder(array $pool, ?int $routingKey): array
    {
        $picked = $this->pickIndex($pool, $routingKey);
        $order = [$pool[$picked]];
        foreach ($pool as $i => $ep) {
            if ($i !== $picked) {
                $order[] = $ep;
            }
        }

        return $order;
    }

    /**
     * Взвешенный выбор индекса узла по ключу (abs(key) % суммарный вес, кумулятивно).
     *
     * @param array<int, array{url:string, weight:int}> $pool
     */
    private function pickIndex(array $pool, ?int $routingKey): int
    {
        $total = 0;
        foreach ($pool as $ep) {
            $total += max(1, (int) $ep['weight']);
        }
        if ($total <= 0) {
            return 0;
        }
        $pos = abs((int) ($routingKey ?? 0)) % $total;
        $acc = 0;
        foreach ($pool as $i => $ep) {
            $acc += max(1, (int) $ep['weight']);
            if ($pos < $acc) {
                return $i;
            }
        }

        return 0;
    }

    /**
     * Недоступность релея на этапе КОННЕКТА — можно фейловерить на другой узел (по HTTP
     * ничего не отправлено, дубля не будет). Сюда входит и connect-таймаут («молчащий»
     * релей: connection timed out / timeout was reached при connectTimeout). НЕ входит
     * таймаут ЧТЕНИЯ ответа («operation timed out» на трансфере) — там SMTP мог уже уйти,
     * дубль недопустим, такую ошибку пробрасываем джобу как транзиентную.
     */
    private function isRelayDown(string $message): bool
    {
        return (bool) preg_match(
            '/connection refused|could ?n.?t resolve|failed to connect|could not connect|'
            . 'no route to host|network is unreachable|connection reset|couldn.t connect to server|'
            . 'empty reply from server|connection timed out|timeout was reached/i',
            $message
        );
    }
}
