<?php

namespace App\Services\Monitoring\Web;

use App\Support\Monitoring\HtmlText;
use App\Support\Monitoring\UrlCanonicalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Вежливый загрузчик страниц мониторинга (порт PageFetcher Content Engine).
 *
 * Отличия от CE:
 *  - лимит на домен — атомарный слот в БД (DomainSlots), без usleep: занят → deferred;
 *  - условный GET (If-None-Match / If-Modified-Since) реально используется;
 *  - финальный URL после редиректов отдаётся для детекта мягкого 404;
 *  - UA обычного браузера (решение владельца), robots.txt соблюдаем.
 */
class PageFetcher
{
    public function __construct(private readonly DomainSlots $slots)
    {
    }

    /**
     * @param array{etag?: ?string, last_modified?: ?string, respect_robots?: bool, rate_ms?: int, skip_slot?: bool,
     *              egress?: ?array{name: string, proxy: ?string}} $options egress — канал EgressPool (null → напрямую)
     */
    public function fetch(string $url, array $options = []): FetchResult
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return FetchResult::failed($url, 'invalid url');
        }
        $egress = $options['egress'] ?? null;
        $egressName = $egress['name'] ?? null;
        $proxy = $egress['proxy'] ?? null;

        if (($options['respect_robots'] ?? (bool) config('monitoring.fetch.respect_robots', true)) && !$this->robotsAllow($url, $proxy)) {
            return FetchResult::failed($url, 'disallowed by robots.txt', 0, true, $egressName);
        }

        if (empty($options['skip_slot'])) {
            $wait = $this->slots->reserve(
                UrlCanonicalizer::registrableDomain($host),
                (int) ($options['rate_ms'] ?? config('monitoring.fetch.default_rate_ms', 1000))
            );
            if ($wait > 0) {
                return FetchResult::deferredFor($url, $wait);
            }
        }

        $headers = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.6',
        ];
        if (!empty($options['etag'])) {
            $headers['If-None-Match'] = $options['etag'];
        }
        if (!empty($options['last_modified'])) {
            $headers['If-Modified-Since'] = $options['last_modified'];
        }

        $timeout = (int) config('monitoring.fetch.timeout', 20);
        $maxBytes = (int) config('monitoring.fetch.max_bytes', 5_000_000);

        try {
            $response = Http::withUserAgent((string) config('monitoring.user_agent'))
                ->withHeaders($headers)
                ->timeout($timeout)
                ->connectTimeout((int) config('monitoring.fetch.connect_timeout', 10))
                ->withOptions(array_filter([
                    'allow_redirects' => ['max' => 5, 'track_redirects' => true],
                    'proxy' => $proxy,
                ]))
                ->get($url);

            $body = $response->body();
            // Отказ по ACL прокси для http://-адреса приходит страницей самого tinyproxy, а не
            // ошибкой cURL — это сбой канала, не бан сайтом.
            if ($proxy !== null && in_array($response->status(), [403, 407], true) && stripos(substr($body, 0, 3000), 'tinyproxy') !== false) {
                return FetchResult::failed($url, "proxy refused (HTTP {$response->status()})", 0, false, $egressName, true);
            }
            if (strlen($body) > $maxBytes) {
                $body = substr($body, 0, $maxBytes);
            }
            $contentType = $response->header('Content-Type') ?: null;
            if ($body !== '' && !str_starts_with($body, '%PDF')
                && ($contentType === null || preg_match('~text/|xml|json|html~i', $contentType))) {
                $body = HtmlText::toUtf8($body, $contentType);
            }
            $redirects = $response->header('X-Guzzle-Redirect-History');
            $finalUrl = $redirects ? trim((string) last(explode(',', $redirects))) : $url;

            return new FetchResult(
                url: $url,
                finalUrl: $finalUrl ?: $url,
                status: $response->status(),
                body: $body,
                contentType: $contentType,
                headers: [
                    'etag' => $response->header('ETag') ?: null,
                    'last_modified' => $response->header('Last-Modified') ?: null,
                ],
                egress: $egressName,
            );
        } catch (ConnectionException $e) {
            $msg = $e->getMessage();

            return FetchResult::failed($url, 'connection: ' . mb_substr($msg, 0, 200), 0, false, $egressName, EgressPool::isProxyFailure($msg, $proxy));
        } catch (\Throwable $e) {
            Log::warning('Monitoring PageFetcher failed', ['url' => $url, 'egress' => $egressName, 'error' => $e->getMessage()]);

            return FetchResult::failed($url, mb_substr($e->getMessage(), 0, 200), 0, false, $egressName, EgressPool::isProxyFailure($e->getMessage(), $proxy));
        }
    }

    public function robotsAllow(string $url, ?string $proxy = null): bool
    {
        $parts = parse_url($url);
        $host = mb_strtolower($parts['host'] ?? '');
        $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        $robots = $this->robotsFor(($parts['scheme'] ?? 'https') . '://' . $host, $proxy);

        return $robots === null || $robots->isAllowed($path, 'iqotbot');
    }

    public function robotsFor(string $origin, ?string $proxy = null): ?RobotsTxt
    {
        $host = mb_strtolower((string) parse_url($origin, PHP_URL_HOST));
        $hours = (int) config('monitoring.fetch.robots_cache_hours', 12);

        $content = Cache::remember('mon:robots:' . $host, now()->addHours($hours), function () use ($origin, $proxy) {
            try {
                $response = Http::withUserAgent((string) config('monitoring.user_agent'))
                    ->timeout(10)->connectTimeout(5)
                    ->withOptions(array_filter(['proxy' => $proxy]))
                    ->get(rtrim($origin, '/') . '/robots.txt');
                if ($response->status() === 200 && strlen($response->body()) < 500_000
                    && !preg_match('/<html[\s>]/i', substr($response->body(), 0, 500))) {
                    return $response->body();
                }
            } catch (\Throwable) {
                // Нет robots — всё разрешено.
            }

            return '';
        });

        return $content === '' ? null : RobotsTxt::parse($content);
    }
}
