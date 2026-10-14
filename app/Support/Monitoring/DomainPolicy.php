<?php

namespace App\Support\Monitoring;

/**
 * Стоп-лист доменов мониторинга (маркетплейсы, агрегаторы, соцсети, справочники).
 * Запись с точкой на конце («aliexpress.») — префикс метки в любом месте хоста;
 * иначе — точный домен или его поддомен (без ложных срабатываний подстроки:
 * «x.com» не блокирует «liftmax.com»).
 */
final class DomainPolicy
{
    /** @param array<int,string>|null $blocked */
    public static function isBlocked(string $urlOrHost, ?array $blocked = null): bool
    {
        $host = str_contains($urlOrHost, '/') ? (string) parse_url($urlOrHost, PHP_URL_HOST) : $urlOrHost;
        $host = mb_strtolower(preg_replace('/^www\./', '', trim($host)) ?? '');
        if ($host === '') {
            return true;
        }
        $blocked ??= (array) config('monitoring.blocked_domains', []);
        foreach ($blocked as $entry) {
            $entry = mb_strtolower(trim((string) $entry));
            if ($entry === '') {
                continue;
            }
            if (str_ends_with($entry, '.')) {
                if (str_starts_with($host, $entry) || str_contains($host, '.' . $entry)) {
                    return true;
                }
                continue;
            }
            if ($host === $entry || str_ends_with($host, '.' . $entry)) {
                return true;
            }
        }

        return false;
    }
}
