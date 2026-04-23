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

    public function fetch(string $url): ?string
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

            return $this->clean($response->body());
        } catch (\Throwable $e) {
            Log::info('SupplierPageParser: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
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
