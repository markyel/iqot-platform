<?php

namespace App\Services\Senders;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
 * УСТОЙЧИВОСТЬ К ПАДЕНИЮ РЕЛЕЯ (инцидент 30.07–31.08.2026: один из двух релеев месяц
 * висел на чтении → 48% писем терялись после 3 ретраев, т.к. ретрай письма шёл в ТОТ ЖЕ
 * узел по тому же id, а read-таймаут не фейловерился):
 *   1. ПРЕДОХРАНИТЕЛЬ (circuit breaker) в кеше: любой транспортный сбой узла (connect-
 *      ошибка ИЛИ таймаут чтения) помечает его «down» на microservice_down_ttl секунд;
 *      порядок обхода ставит живые узлы первыми. Следующие письма сразу идут в живой
 *      релей, а не ждут по 60 с каждое. По истечении TTL узел пробуется снова (half-open);
 *      успех снимает метку досрочно.
 *   2. РОТАЦИЯ РЕТРАЕВ: $attempt (retry_count письма) сдвигает выбор узла — повторная
 *      попытка того же письма стартует с ДРУГОГО релея.
 * Таймаут чтения по-прежнему НЕ фейловерится в рамках одного вызова (SMTP мог уйти —
 * дубль недопустим); он пробрасывается джобу как транзиентный, а ретрай джоба (через
 * 5 мин, с attempt+1) уже уйдёт в другой узел.
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
     * @param int $attempt номер повторной попытки этого письма (retry_count) — сдвигает
     *   выбор узла, чтобы ретрай не упирался в тот же (возможно, висящий) релей.
     * @return string|null Message-ID отправленного письма (из ответа), если вернулся
     * @throws \RuntimeException при не-2xx / success=false (текст SMTP-ошибки) или если
     *   все релеи недоступны
     */
    public function send(array $payload, ?int $routingKey = null, int $attempt = 0): ?string
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

        // Порядок обхода: живые узлы первыми; среди них — выбранный по ключу (со сдвигом
        // на номер попытки), остальные — резерв для failover; помеченные «down» — в хвосте.
        $order = $this->failoverOrder($pool, $routingKey, $attempt);
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
                // Любой транспортный сбой узла взводит предохранитель: следующие письма
                // пойдут в другие релеи, не дожидаясь таймаута на этом.
                $this->markDown($ep['url'], $e->getMessage());

                // Различаем НЕДОСТУПНОСТЬ релея (failover на следующий) и таймаут ЧТЕНИЯ
                // (SMTP уже мог уйти — НЕ фейловерим, иначе дубль; пробрасываем джобу,
                // его ретрай с attempt+1 стартует с другого узла).
                if ($this->isRelayDown($e->getMessage())) {
                    $lastConnError = $e;
                    continue;
                }
                throw new \RuntimeException('microservice /send transport: ' . $e->getMessage(), 0, $e);
            }

            // Узел ответил по HTTP — жив; снимаем метку «down», если была.
            $this->markUp($ep['url']);

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
     * Проверка SMTP-кредов С РЕЛЕЯ (POST /test-smtp — коннект + AUTH без отправки).
     * Тот же хост/порт/креды, что при боевой отправке → результат отражает реальный
     * путь письма (прямой AUTH с app-сервера у ряда провайдеров ложно даёт 535).
     * Обход пула: живые узлы первыми; транспортный сбой узла взводит предохранитель
     * и переводит на следующий.
     *
     * @param array{smtp_server:string,smtp_port:int,smtp_user:string,smtp_password:string,smtp_encryption?:string,timeout?:int} $creds
     * @return array{ok:bool,error:?string}|null null — ни один релей не ответил
     */
    public function testSmtp(array $creds): ?array
    {
        $pool = $this->endpoints();
        if ($pool === []) {
            return null;
        }
        $key = (string) config('services.email_dispatch.microservice_api_key', '');
        $smtpTimeout = max(5, (int) ($creds['timeout'] ?? 15));
        $creds['timeout'] = $smtpTimeout;

        foreach ($this->failoverOrder($pool, null) as $ep) {
            try {
                $response = Http::timeout($smtpTimeout + 15)
                    ->connectTimeout(12)
                    ->withHeaders(['X-API-Key' => $key])
                    ->acceptJson()
                    ->asJson()
                    ->post($ep['url'] . '/test-smtp', $creds);
            } catch (ConnectionException $e) {
                $this->markDown($ep['url'], $e->getMessage());
                continue;
            }
            $this->markUp($ep['url']);

            $json = $response->json();
            if (!$response->successful() || !is_array($json)) {
                // Ответ есть, но не по контракту (401/500/не-JSON) — узел жив, проверка не удалась.
                return ['ok' => false, 'error' => 'relay HTTP ' . $response->status() . ': ' . mb_substr((string) $response->body(), 0, 200)];
            }
            $ok = (bool) ($json['success'] ?? false);

            return ['ok' => $ok, 'error' => $ok ? null : (string) ($json['message'] ?? 'SMTP connection failed')];
        }

        return null;
    }

    /**
     * Порядок обхода пула: сначала выбранный по routing-ключу (взвешенно, стабильно —
     * поток ящика ровно раскладывается по релеям; $attempt сдвигает выбор), затем
     * остальные как резерв failover. Узлы с меткой «down» (предохранитель) — в хвосте
     * в том же относительном порядке: их всё равно пробуем, если живые не ответили.
     *
     * @param array<int, array{url:string, weight:int}> $pool
     * @return array<int, array{url:string, weight:int}>
     */
    private function failoverOrder(array $pool, ?int $routingKey, int $attempt = 0): array
    {
        // Сдвиг на попытку — на «единицу пула», а не на единицу веса: при весах {3,1}
        // ретрай гарантированно уходит в ДРУГОЙ узел, а не в следующий слот того же.
        $picked = $this->pickIndex($pool, $routingKey);
        $n = count($pool);
        $picked = ($picked + max(0, $attempt)) % $n;

        $order = [$pool[$picked]];
        foreach ($pool as $i => $ep) {
            if ($i !== $picked) {
                $order[] = $ep;
            }
        }

        $alive = [];
        $down = [];
        foreach ($order as $ep) {
            if ($this->isDown($ep['url'])) {
                $down[] = $ep;
            } else {
                $alive[] = $ep;
            }
        }

        return array_merge($alive, $down);
    }

    /**
     * Предохранитель: метка «узел down» в кеше на microservice_down_ttl секунд.
     * Кеш общий для всех воркеров → один таймаут уводит от узла весь поток.
     */
    private function markDown(string $url, string $reason): void
    {
        $ttl = max(0, (int) config('services.email_dispatch.microservice_down_ttl', 180));
        if ($ttl === 0) {
            return;
        }
        $wasDown = $this->isDown($url);
        Cache::put($this->downKey($url), now()->toDateTimeString(), $ttl);
        if (!$wasDown) {
            Log::warning('RelayHttpMailer: релей помечен down (предохранитель)', [
                'relay' => $url,
                'ttl' => $ttl,
                'reason' => mb_substr($reason, 0, 200),
            ]);
        }
    }

    private function markUp(string $url): void
    {
        if ($this->isDown($url)) {
            Cache::forget($this->downKey($url));
            Log::info('RelayHttpMailer: релей снова отвечает, метка down снята', ['relay' => $url]);
        }
    }

    /**
     * Помечен ли узел как недоступный (предохранитель взведён и TTL не истёк).
     */
    public function isDown(string $url): bool
    {
        return Cache::has($this->downKey($url));
    }

    private function downKey(string $url): string
    {
        return 'relay:down:' . md5(rtrim($url, '/'));
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
