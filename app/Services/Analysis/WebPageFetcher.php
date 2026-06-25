<?php

namespace App\Services\Analysis;

use Illuminate\Support\Facades\Http;

/**
 * Загрузка содержимого веб-страницы для 2-го прогона AI (замена Tavily).
 *
 * Если AI в ответе поставщика нашёл ссылки на товар, но без цены, он отдаёт
 * `fetch_urls`. Мы грузим эти страницы здесь, чистим до текста и скармливаем
 * во второй прогон анализатора. Любая ошибка/таймаут → null (ссылку пропускаем,
 * анализ продолжается на том, что есть).
 *
 * Стратегия: сначала дешёвый HTTP-запрос. Если ответ пустой/слишком короткий
 * (типичная JS-заглушка вроде Beget-антибота с set_cookie()+reload()) — fallback
 * на headless-рендер через Chromium (если передан HeadlessPageRenderer).
 */
class WebPageFetcher
{
    public function __construct(
        private readonly int $maxChars = 8000,
        private readonly int $timeout = 15,
        private readonly ?HeadlessPageRenderer $headless = null,
        private readonly int $httpMinChars = 200,
    ) {
    }

    public function fetch(string $url): ?string
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            return null;
        }

        $text = $this->fetchViaHttp($url);

        // HTTP вернул пусто/огрызок (JS-заглушка) → пробуем headless-рендер.
        if (($text === null || mb_strlen($text) < $this->httpMinChars) && $this->headless !== null) {
            $rendered = $this->headless->render($url);
            if ($rendered !== null && ($text === null || mb_strlen($rendered) > mb_strlen($text))) {
                $text = $rendered;
            }
        }

        if ($text === null || $text === '') {
            return null;
        }

        return mb_substr($text, 0, $this->maxChars);
    }

    private function fetchViaHttp(string $url): ?string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; IQOTBot/1.0)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($url);

            if (!$response->ok()) {
                return null;
            }

            $text = $this->htmlToText($response->body());
        } catch (\Throwable $e) {
            return null;
        }

        return $text === '' ? null : $text;
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<(script|style|noscript|head)[^>]*>[\s\S]*?<\/\1>/i', ' ', $html) ?? $html;
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }
}
