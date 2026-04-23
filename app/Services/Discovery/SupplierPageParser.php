<?php

namespace App\Services\Discovery;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Загружает HTML страницы и выдаёт «очищенный» текст для AI-экстракции контактов.
 *
 * Не пытается быть умным парсером — для этого работает LLM. Здесь:
 *   - HTTP GET с коротким таймаутом + user-agent;
 *   - strip <script>/<style>/<nav>/<footer> тяжёлых блоков;
 *   - нормализация whitespace;
 *   - limit по символам (чтобы в промпт не лезть с HTML мегабайтами).
 */
class SupplierPageParser
{
    public const MAX_CHARS = 12000;
    public const TIMEOUT = 10;

    /**
     * Маркеры, по которым детектим «contact / контакты» — и в anchor-тексте, и в href.
     */
    private const CONTACT_MARKERS = [
        'контакт', 'связаться', 'связь', 'обратная связь', 'о компании', 'о нас', 'реквизит',
        'contact', 'contacts', 'kontakty', 'kontakti', 'about', 'reach us', 'get in touch',
        'impressum', 'svyaz',
    ];

    public function fetch(string $url): ?string
    {
        $raw = $this->fetchRaw($url);
        return $raw === null ? null : $this->clean($raw);
    }

    /**
     * Возвращает ['text' => string, 'contact_links' => array<string>] или null при ошибке.
     * contact_links — абсолютные URL страниц-кандидатов на «Контакты» (до 3 шт).
     *
     * @return array{text:string, contact_links:array<string>}|null
     */
    public function fetchWithContactLinks(string $url): ?array
    {
        $raw = $this->fetchRaw($url);
        if ($raw === null) {
            return null;
        }
        return [
            'text' => $this->clean($raw),
            'contact_links' => $this->extractContactLinks($raw, $url),
        ];
    }

    private function fetchRaw(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; IQOT/1.0; +https://iqot.ru)',
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'ru,en;q=0.7',
            ])
                ->timeout(self::TIMEOUT)
                ->withOptions(['verify' => false, 'allow_redirects' => ['max' => 5]])
                ->get($url);

            if (!$response->successful()) {
                return null;
            }
            $contentType = strtolower((string) $response->header('Content-Type'));
            if ($contentType !== '' && !str_contains($contentType, 'text/html') && !str_contains($contentType, 'application/xhtml')) {
                return null;
            }
            return $response->body();
        } catch (\Throwable $e) {
            Log::info('SupplierPageParser: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Находит ссылки вида «Контакты / Contact us / Связаться» — по тексту или href.
     *
     * @return array<string> абсолютные URL, уникальные, до 3 штук
     */
    private function extractContactLinks(string $html, string $baseUrl): array
    {
        // Вырезаем тяжёлые блоки чтобы regex не работал на 500KB мусора.
        $stripped = preg_replace('/<(script|style|svg|noscript)\b[^>]*>[\s\S]*?<\/\1>/i', ' ', $html) ?? $html;

        if (!preg_match_all('/<a\b[^>]*href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))[^>]*>([\s\S]*?)<\/a>/i', $stripped, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $candidates = [];
        foreach ($matches as $m) {
            $href = $m[1] !== '' ? $m[1] : ($m[2] !== '' ? $m[2] : $m[3]);
            if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) {
                continue;
            }
            $anchorText = mb_strtolower(trim(html_entity_decode(strip_tags($m[4]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $hrefLower = mb_strtolower($href);

            $matched = false;
            foreach (self::CONTACT_MARKERS as $mk) {
                if ($anchorText !== '' && str_contains($anchorText, $mk)) {
                    $matched = true;
                    break;
                }
                if (str_contains($hrefLower, $mk)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                continue;
            }

            $abs = $this->resolveUrl($href, $baseUrl);
            if ($abs === null) {
                continue;
            }
            // Оставим только http(s) и только тот же хост что у базы.
            $absHost = parse_url($abs, PHP_URL_HOST);
            $baseHost = parse_url($baseUrl, PHP_URL_HOST);
            if (!$absHost || !$baseHost) {
                continue;
            }
            if (mb_strtolower(preg_replace('/^www\./i', '', $absHost)) !== mb_strtolower(preg_replace('/^www\./i', '', $baseHost))) {
                continue;
            }

            $candidates[$abs] = true;
            if (count($candidates) >= 3) {
                break;
            }
        }
        return array_keys($candidates);
    }

    private function resolveUrl(string $href, string $baseUrl): ?string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $base = parse_url($baseUrl);
        if (!$base || empty($base['scheme']) || empty($base['host'])) {
            return null;
        }
        $origin = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');
        if (str_starts_with($href, '//')) {
            return $base['scheme'] . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }
        // Относительный к директории.
        $path = $base['path'] ?? '/';
        $dir = substr($path, 0, strrpos($path, '/') + 1);
        return $origin . $dir . $href;
    }

    private function clean(string $html): string
    {
        // Убираем тяжёлые служебные блоки.
        $html = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', ' ', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', ' ', $html) ?? $html;
        $html = preg_replace('/<noscript\b[^>]*>[\s\S]*?<\/noscript>/i', ' ', $html) ?? $html;
        $html = preg_replace('/<svg\b[^>]*>[\s\S]*?<\/svg>/i', ' ', $html) ?? $html;
        $html = preg_replace('/<!--[\s\S]*?-->/', ' ', $html) ?? $html;

        // Strip tags, нормализация пробелов.
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text, 'UTF-8') > self::MAX_CHARS) {
            $text = mb_substr($text, 0, self::MAX_CHARS, 'UTF-8');
        }
        return $text;
    }
}
