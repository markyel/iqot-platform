<?php

namespace App\Services\Monitoring\Web;

/**
 * Результат загрузки страницы (порт FetchResult Content Engine + deferred: слот
 * домена занят — повторить через retryAfterMs, не ожидая в воркере).
 */
final class FetchResult
{
    public function __construct(
        public readonly string $url,
        public readonly string $finalUrl,
        public readonly int $status,
        public readonly string $body,
        public readonly ?string $contentType,
        public readonly array $headers = [],
        public readonly ?string $error = null,
        public readonly bool $blockedByRobots = false,
        public readonly int $retryAfterMs = 0,
        public readonly string $via = 'http',
        // Исходящий канал (EgressPool) и признак отказа САМОГО прокси (не сайта):
        // коннект к прокси, CONNECT-туннель, ACL-отказ прокси.
        public readonly ?string $egress = null,
        public readonly bool $proxyFailed = false,
    ) {
    }

    public function ok(): bool
    {
        return $this->error === null && $this->status >= 200 && $this->status < 300 && !$this->blockedByRobots && $this->retryAfterMs === 0;
    }

    public function notModified(): bool
    {
        return $this->status === 304;
    }

    public function deferred(): bool
    {
        return $this->retryAfterMs > 0;
    }

    public function isHtml(): bool
    {
        return str_contains((string) $this->contentType, 'html') || preg_match('/<html[\s>]/i', substr($this->body, 0, 2000)) === 1;
    }

    public static function failed(string $url, string $error, int $status = 0, bool $robots = false, ?string $egress = null, bool $proxyFailed = false): self
    {
        return new self($url, $url, $status, '', null, [], $error, $robots, egress: $egress, proxyFailed: $proxyFailed);
    }

    public static function deferredFor(string $url, int $retryAfterMs): self
    {
        return new self($url, $url, 0, '', null, [], null, false, $retryAfterMs);
    }
}
