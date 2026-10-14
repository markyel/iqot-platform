<?php

namespace App\Services\Monitoring\Web;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Пул исходящих каналов мониторинга: «direct» (IP сервера IQOT) и HTTP-прокси на наших
 * хостах (релеи рассылки, хостинг liftpages). Сайт видит ОДИН стабильный IP:
 * канал закреплён за доменом (mp_sites.egress), а при первичном выборе — детерминированный
 * хеш домена по основным каналам. Резервные (fallback) — только когда основные забанены.
 *
 *  - бан сайтом (401/403/капча) → канал выключается для ЭТОГО домена на ban_hours,
 *    домен переезжает на следующий канал; сайт блокируется целиком, только когда
 *    забанены все каналы;
 *  - отказ самого прокси (коннект/туннель/ACL) → канал «down» на down_seconds для всех
 *    доменов (как предохранитель RelayHttpMailer), такие идут в хвост очереди.
 *
 * Политику вежливости (слот домена, rate_limit_ms) пул НЕ меняет: интервал к сайту общий
 * на все каналы — смена IP не повод ходить чаще.
 */
final class EgressPool
{
    public const DIRECT = 'direct';

    /** @var array<string, array{name: string, proxy: ?string, fallback: bool}> */
    private array $channels = [];

    /** @param array<int, array<string, mixed>>|null $channels */
    public function __construct(?array $channels = null)
    {
        foreach ($channels ?? (array) config('monitoring.egress.channels', []) as $c) {
            $name = trim((string) ($c['name'] ?? ''));
            if ($name === '' || isset($this->channels[$name])) {
                continue;
            }
            $proxy = trim((string) ($c['proxy'] ?? ''));
            $this->channels[$name] = [
                'name' => $name,
                'proxy' => $proxy === '' ? null : $proxy,
                'fallback' => (bool) ($c['fallback'] ?? false),
            ];
        }
        if ($this->channels === []) {
            $this->channels[self::DIRECT] = ['name' => self::DIRECT, 'proxy' => null, 'fallback' => false];
        }
    }

    public static function make(): self
    {
        return new self();
    }

    /** @return array{name: string, proxy: ?string, fallback: bool}|null */
    public function get(?string $name): ?array
    {
        return $name !== null ? ($this->channels[$name] ?? null) : null;
    }

    /** @return array<int, string> */
    public function names(): array
    {
        return array_keys($this->channels);
    }

    /**
     * Каналы для домена в порядке предпочтения, без забаненных этим доменом.
     * Порядок: закреплённый за сайтом → основные по хешу домена → резервные; «down» — в хвост.
     *
     * @return array<int, array{name: string, proxy: ?string, fallback: bool}>
     */
    public function candidates(string $domain, ?string $preferred = null): array
    {
        $domain = mb_strtolower($domain);
        $primary = array_values(array_filter($this->channels, fn ($c) => !$c['fallback']));
        $fallback = array_values(array_filter($this->channels, fn ($c) => $c['fallback']));
        if ($primary !== []) {
            // Сдвиг по хешу: разные домены равномерно расходятся по основным каналам.
            $shift = crc32($domain) % count($primary);
            $primary = array_merge(array_slice($primary, $shift), array_slice($primary, 0, $shift));
        }
        $ordered = array_merge($primary, $fallback);
        if ($preferred !== null && isset($this->channels[$preferred])) {
            $ordered = array_merge([$this->channels[$preferred]], array_filter($ordered, fn ($c) => $c['name'] !== $preferred));
        }

        $alive = [];
        $down = [];
        foreach ($ordered as $c) {
            if ($this->isBanned($c['name'], $domain)) {
                continue;
            }
            if ($this->isDown($c['name'])) {
                $down[] = $c;
            } else {
                $alive[] = $c;
            }
        }

        return array_values(array_merge($alive, $down));
    }

    /** @return array{name: string, proxy: ?string, fallback: bool}|null null — все каналы забанены доменом */
    public function pick(string $domain, ?string $preferred = null): ?array
    {
        return $this->candidates($domain, $preferred)[0] ?? null;
    }

    public function markBanned(string $name, string $domain, string $reason): void
    {
        $hours = (int) config('monitoring.egress.ban_hours', 72);
        Cache::put($this->banKey($name, $domain), $reason, now()->addHours(max(1, $hours)));
        Log::info('Monitoring egress: канал забанен сайтом', ['egress' => $name, 'domain' => $domain, 'reason' => $reason, 'hours' => $hours]);
    }

    public function isBanned(string $name, string $domain): bool
    {
        return Cache::has($this->banKey($name, mb_strtolower($domain)));
    }

    /** Сколько каналов домен уже забанил. */
    public function bannedCount(string $domain): int
    {
        return count(array_filter($this->names(), fn ($n) => $this->isBanned($n, $domain)));
    }

    /**
     * Домен забанил достаточно каналов, чтобы считать его антиботом против IP дата-центров
     * («Проверка безопасности», Aliyun/SmartCaptcha) — дальше не перебираем: смена IP не
     * поможет, а резервные каналы (почтовый релей) светить незачем.
     */
    public function exhaustedFor(string $domain): bool
    {
        $max = (int) config('monitoring.egress.max_bans_per_site', 2);

        return $max > 0 && $this->bannedCount($domain) >= $max;
    }

    public function markDown(string $name, string $reason): void
    {
        $ttl = (int) config('monitoring.egress.down_seconds', 300);
        if ($ttl <= 0 || $name === self::DIRECT) {
            return;
        }
        if (!Cache::has($this->downKey($name))) {
            Log::warning('Monitoring egress: прокси помечен down', ['egress' => $name, 'ttl' => $ttl, 'reason' => mb_substr($reason, 0, 200)]);
        }
        Cache::put($this->downKey($name), 1, now()->addSeconds($ttl));
    }

    public function markUp(string $name): void
    {
        Cache::forget($this->downKey($name));
    }

    public function isDown(string $name): bool
    {
        return Cache::has($this->downKey($name));
    }

    /**
     * Ошибка — отказ самого прокси, а не сайта: коннект к хосту:порту прокси, сбой
     * CONNECT-туннеля, нерезолвящийся прокси.
     */
    public static function isProxyFailure(string $error, ?string $proxy): bool
    {
        if ($proxy === null) {
            return false;
        }
        // CONNECT 403/407 — отказ ACL/авторизации прокси. 5xx на CONNECT — прокси не достучался
        // до САЙТА (tinyproxy «500 Unable to connect»), это сбой сайта, не канала.
        if (preg_match('~CONNECT tunnel failed, response 40[37]|Received HTTP code 40[37] from proxy|Could not resolve proxy~i', $error)) {
            return true;
        }
        if (preg_match('~CONNECT tunnel failed|from proxy after CONNECT~i', $error)) {
            return false;
        }
        $host = (string) parse_url($proxy, PHP_URL_HOST);
        $port = (string) parse_url($proxy, PHP_URL_PORT);

        return $host !== '' && str_contains($error, $host) && ($port === '' || str_contains($error, $port));
    }

    private function banKey(string $name, string $domain): string
    {
        return 'mon:egress:ban:' . $name . ':' . $domain;
    }

    private function downKey(string $name): string
    {
        return 'mon:egress:down:' . $name;
    }
}
