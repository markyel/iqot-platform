<?php

namespace App\Services\Discovery;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Клиент к Yandex Cloud Search API v2.
 *
 * Эндпоинт: https://searchapi.api.cloud.yandex.net/v2/web/search
 * Auth: header `Authorization: Api-Key <API_KEY>`.
 *
 * Порт из n8n-ноды (iqot/n8n workflows). Делает multi-query search,
 * парсит base64-encoded XML ответ, возвращает плоский список URL/title/
 * content/domain с дедупликацией.
 */
class YandexSearchClient
{
    /** @var array<int, array{query: string, results: int, error?: string}> исход запросов последнего multiSearch */
    private array $lastLog = [];

    public function __construct(
        private readonly string $endpoint,
        private readonly string $apiKey,
        private readonly string $folderId,
        private readonly int $resultsPerQuery,
        private readonly int $timeout,
    ) {
    }

    /**
     * Исход запросов последнего multiSearch: error (HTTP 429/400, исключение…) — запрос не
     * выполнен, пустая выдача при нём — не «ничего не найдено».
     *
     * @return array<int, array{query: string, results: int, error?: string}>
     */
    public function lastLog(): array
    {
        return $this->lastLog;
    }

    public static function fromConfig(): self
    {
        $cfg = config('services.yandex_search');
        return new self(
            endpoint: (string) ($cfg['endpoint'] ?? ''),
            apiKey: (string) ($cfg['api_key'] ?? ''),
            folderId: (string) ($cfg['folder_id'] ?? ''),
            resultsPerQuery: (int) ($cfg['results_per_query'] ?? 5),
            timeout: (int) ($cfg['timeout'] ?? 30),
        );
    }

    public function isConfigured(): bool
    {
        return $this->endpoint !== '' && $this->apiKey !== '' && $this->folderId !== '';
    }

    /**
     * Отложенный (асинхронный) поиск — в ~16 раз дешевле синхронного (≈30,5 ₽ против
     * ≈488 ₽ за 1000 запросов днём по тарифу Yandex Cloud). Для массового мониторинга
     * цен (docs/monitoring-f0-spec.md §9). Возвращает id операции или null.
     * Эндпоинты: <endpoint>Async → операция; результат — fetchDeferred().
     * ⚠ Сверить эндпоинты/квоты с документацией Yandex Cloud перед боевым включением.
     */
    public function searchDeferred(string $query): ?string
    {
        if (!$this->isConfigured() || trim($query) === '') {
            return null;
        }
        $asyncEndpoint = (string) (config('services.yandex_search.async_endpoint') ?: $this->endpoint . 'Async');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Api-Key ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout)->post($asyncEndpoint, [
                'query' => ['searchType' => 1, 'queryText' => trim($query)],
                'groupSpec' => [
                    'groupMode' => 1,
                    'groupsOnPage' => (string) $this->resultsPerQuery,
                    'docsInGroup' => '1',
                ],
                'folderId' => $this->folderId,
            ]);
            if (!$response->successful()) {
                Log::warning('YandexSearch: deferred non-200', ['query' => $query, 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 300)]);

                return null;
            }
            $id = $response->json('id');

            return is_string($id) && $id !== '' ? $id : null;
        } catch (\Throwable $e) {
            Log::warning('YandexSearch: deferred exception', ['query' => $query, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Результат отложенного поиска.
     *
     * @return array{done: bool, results: array<int, array{url:string, title:string, content:string, domain:string}>, error: ?string}
     */
    public function fetchDeferred(string $operationId): array
    {
        $opEndpoint = rtrim((string) (config('services.yandex_search.operation_endpoint') ?: 'https://operation.api.cloud.yandex.net/operations'), '/');
        try {
            $response = Http::withHeaders(['Authorization' => 'Api-Key ' . $this->apiKey])
                ->timeout($this->timeout)->get($opEndpoint . '/' . rawurlencode($operationId));
            if (!$response->successful()) {
                return ['done' => false, 'results' => [], 'error' => 'HTTP ' . $response->status()];
            }
            $body = $response->json();
            if (empty($body['done'])) {
                return ['done' => false, 'results' => [], 'error' => null];
            }
            if (isset($body['error'])) {
                return ['done' => true, 'results' => [], 'error' => mb_substr(json_encode($body['error'], JSON_UNESCAPED_UNICODE), 0, 300)];
            }
            $raw = $body['response']['rawData'] ?? null;
            $xml = is_string($raw) ? base64_decode($raw, true) : false;

            return ['done' => true, 'results' => $xml === false ? [] : $this->parseXml($xml), 'error' => $xml === false ? 'no_rawData' : null];
        } catch (\Throwable $e) {
            return ['done' => false, 'results' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Поиск по массиву запросов.
     *
     * @param array<string> $queries
     * @return array<int, array{url:string, title:string, content:string, domain:string, query:string}>
     */
    public function multiSearch(array $queries): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('YandexSearchClient is not configured.');
        }

        $allResults = [];
        $log = [];

        foreach ($queries as $query) {
            $query = trim((string) $query);
            if ($query === '') {
                continue;
            }

            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Api-Key ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->timeout($this->timeout)->post($this->endpoint, [
                    'query' => [
                        'searchType' => 1, // SEARCH_TYPE_RU
                        'queryText' => $query,
                    ],
                    'groupSpec' => [
                        'groupMode' => 1,
                        'groupsOnPage' => (string) $this->resultsPerQuery,
                        'docsInGroup' => '1',
                    ],
                    'folderId' => $this->folderId,
                ]);

                if (!$response->successful()) {
                    $log[] = ['query' => $query, 'results' => 0, 'error' => 'HTTP ' . $response->status()];
                    Log::warning('YandexSearch: non-200', ['query' => $query, 'status' => $response->status()]);
                    continue;
                }

                $body = $response->json();
                $raw = $body['rawData'] ?? null;
                if (!is_string($raw) || $raw === '') {
                    $log[] = ['query' => $query, 'results' => 0, 'error' => 'no_rawData'];
                    continue;
                }

                $xml = base64_decode($raw, true);
                if ($xml === false) {
                    $log[] = ['query' => $query, 'results' => 0, 'error' => 'bad_base64'];
                    continue;
                }

                $items = $this->parseXml($xml);
                foreach ($items as $it) {
                    $it['query'] = $query;
                    $allResults[] = $it;
                }
                $log[] = ['query' => $query, 'results' => count($items)];
            } catch (\Throwable $e) {
                $log[] = ['query' => $query, 'results' => 0, 'error' => $e->getMessage()];
                Log::warning('YandexSearch: exception', ['query' => $query, 'error' => $e->getMessage()]);
            }

            // Пауза между запросами чтобы не перегружать API.
            usleep(500_000);
        }

        // Дедупликация по URL.
        $unique = [];
        $seenUrls = [];
        foreach ($allResults as $r) {
            if (isset($seenUrls[$r['url']])) {
                continue;
            }
            $seenUrls[$r['url']] = true;
            $unique[] = $r;
        }

        $this->lastLog = $log;

        Log::info('YandexSearch: multiSearch done', [
            'queries' => count($queries),
            'total_raw' => count($allResults),
            'after_dedup' => count($unique),
            'log' => $log,
        ]);

        return $unique;
    }

    /**
     * Парсит XML из Yandex Search API в массив {url,title,content,domain}.
     *
     * @return array<int, array{url:string, title:string, content:string, domain:string}>
     */
    private function parseXml(string $xml): array
    {
        $results = [];

        if (!preg_match_all('/<group\b[^>]*>([\s\S]*?)<\/group>/i', $xml, $groups)) {
            return $results;
        }

        foreach ($groups[1] as $groupContent) {
            if (!preg_match_all('/<doc\b[^>]*>([\s\S]*?)<\/doc>/i', $groupContent, $docs)) {
                continue;
            }
            foreach ($docs[1] as $docContent) {
                $url = '';
                if (preg_match('/<url\b[^>]*>([^<]+)<\/url>/i', $docContent, $m)) {
                    $url = trim($m[1]);
                }

                $title = '';
                if (preg_match('/<title\b[^>]*>([\s\S]*?)<\/title>/i', $docContent, $m)) {
                    $title = trim(preg_replace('/<\/?hlword[^>]*>/i', '', $m[1]));
                }

                $domain = '';
                if (preg_match('/<domain\b[^>]*>([^<]+)<\/domain>/i', $docContent, $m)) {
                    $domain = trim($m[1]);
                }

                $content = '';
                if (preg_match('/<passages\b[^>]*>([\s\S]*?)<\/passages>/i', $docContent, $m)) {
                    $passages = [];
                    if (preg_match_all('/<passage\b[^>]*>([\s\S]*?)<\/passage>/i', $m[1], $pm)) {
                        foreach ($pm[1] as $p) {
                            $p = trim(preg_replace('/<\/?hlword[^>]*>/i', '', $p));
                            if ($p !== '') {
                                $passages[] = $p;
                            }
                        }
                    }
                    $content = implode(' ', $passages);
                }

                if ($url !== '' && $title !== '') {
                    $results[] = [
                        'url' => $url,
                        'title' => $title,
                        'content' => $content,
                        'domain' => $domain,
                    ];
                }
            }
        }

        return $results;
    }
}
