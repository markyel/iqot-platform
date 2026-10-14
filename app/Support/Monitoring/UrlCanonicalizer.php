<?php

namespace App\Support\Monitoring;

/**
 * Канонический URL для дедупа листингов (порт UrlCanonicalizer Content Engine):
 * https, без www/utm/якоря/завершающего слэша, параметры отсортированы.
 */
final class UrlCanonicalizer
{
    private const TRACKING_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_referrer',
        'yclid', 'gclid', 'fbclid', 'dclid', 'msclkid', 'ysclid', '_openstat', 'from', 'ref',
        'referrer', 'erid', 'mc_cid', 'mc_eid', 'igshid', 'rb_clickid', 'spm', 'frommarket',
    ];

    public static function canonicalize(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return mb_strtolower($url);
        }

        $host = mb_strtolower($parts['host']);
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = $parts['path'] ?? '/';
        $path = preg_replace('~/+~', '/', $path) ?? $path;
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        $path = preg_replace('~/index\.(html?|php)$~i', '', $path) ?? $path;
        if ($path === '/') {
            $path = '';
        }

        $query = '';
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $params);
            $params = array_filter(
                $params,
                fn ($k) => !in_array(mb_strtolower((string) $k), self::TRACKING_PARAMS, true)
                    && !str_starts_with(mb_strtolower((string) $k), 'utm_'),
                ARRAY_FILTER_USE_KEY,
            );
            ksort($params);
            $query = $params ? '?' . http_build_query($params) : '';
        }

        $port = isset($parts['port']) && !in_array($parts['port'], [80, 443], true) ? ':' . $parts['port'] : '';

        return 'https://' . $host . $port . $path . $query;
    }

    public static function hash(string $url): string
    {
        return sha1(self::canonicalize($url));
    }

    /** Регистрируемый домен (две метки; три — для co.uk / msk.ru / spb.ru-подобных). */
    public static function registrableDomain(string $urlOrHost): string
    {
        $host = str_contains($urlOrHost, '/') ? (parse_url($urlOrHost, PHP_URL_HOST) ?? $urlOrHost) : $urlOrHost;
        $host = mb_strtolower(preg_replace('/^www\./', '', trim((string) $host)) ?? '');
        $labels = explode('.', $host);
        $n = count($labels);
        if ($n <= 2) {
            return $host;
        }
        $secondLevel = ['com', 'org', 'net', 'gov', 'edu', 'co', 'msk', 'spb', 'nov', 'kiev'];
        if (in_array($labels[$n - 2], $secondLevel, true) && mb_strlen($labels[$n - 1]) === 2) {
            return implode('.', array_slice($labels, -3));
        }

        return implode('.', array_slice($labels, -2));
    }

    /** Путь URL «похож на корень/категорию», а не на карточку товара (для мягкого 404). */
    public static function looksLikeNonProduct(string $url): bool
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') {
            return true;
        }

        return (bool) preg_match('~^(catalog|katalog|shop|products?|search|poisk|404|not-?found)/?$~i', $path);
    }
}
