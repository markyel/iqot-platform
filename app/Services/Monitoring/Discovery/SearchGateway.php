<?php

namespace App\Services\Monitoring\Discovery;

use App\Services\Discovery\YandexSearchClient;
use App\Services\Monitoring\MonitoringGovernor;
use Illuminate\Support\Facades\Log;

/**
 * Поиск в Яндексе для мониторинга: синхронный или отложенный режим
 * (config monitoring.search.mode), квота и учёт через MonitoringGovernor.
 *
 * Отложенный режим в ~16 раз дешевле, но требует квоты в Yandex Cloud: без неё
 * searchAsync отвечает 429 «search rate limit exceeded» (проверено 14.10.2026).
 * Если отложенный запрос не принят — фолбэк на синхронный (поиск не встаёт).
 */
class SearchGateway
{
    public function __construct(
        private readonly YandexSearchClient $client,
        private readonly MonitoringGovernor $governor,
    ) {
    }

    public static function make(): self
    {
        $cfg = config('services.yandex_search');

        return new self(
            new YandexSearchClient(
                endpoint: (string) ($cfg['endpoint'] ?? ''),
                apiKey: (string) ($cfg['api_key'] ?? ''),
                folderId: (string) ($cfg['folder_id'] ?? ''),
                resultsPerQuery: (int) config('monitoring.search.results_per_query', 10),
                timeout: (int) ($cfg['timeout'] ?? 30),
            ),
            app(MonitoringGovernor::class),
        );
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * @return array{results: array<int, array{url:string, title:string, content:string, domain:string, query:string}>, quota: bool, error?: ?string}
     */
    public function search(string $query): array
    {
        if (!$this->governor->reserve('yandex_query')) {
            return ['results' => [], 'quota' => false];
        }

        $mode = (string) config('monitoring.search.mode', 'sync');
        $results = null;
        if ($mode === 'deferred') {
            $results = $this->deferred($query);
            if ($results === null) {
                $mode = 'sync'; // отложенный не принят (429 / нет квоты) — синхронно
            }
        }
        $error = null;
        if ($results === null) {
            $results = $this->client->multiSearch([$query]);
            $error = $this->client->lastLog()[0]['error'] ?? null;
            // Синхронный лимит Яндекса — 1 запрос/с на каталог; при параллельных воркерах
            // 429 — штатно: одна повторная попытка через секунду.
            if ($error === 'HTTP 429') {
                usleep(1_200_000);
                $results = $this->client->multiSearch([$query]);
                $error = $this->client->lastLog()[0]['error'] ?? null;
            }
        }

        // Неудачный запрос не тарифицируется и не считается пустой выдачей.
        if ($error === null) {
            $rub = (float) (config("monitoring.search.rub_per_1000.{$mode}") ?? 0) / 1000;
            $this->governor->record('yandex_query', 0, 0.0, 0, $rub);
        }

        return ['results' => $error === null ? $results : [], 'quota' => true, 'error' => $error];
    }

    /** @return array<int, array<string,string>>|null null — запрос не принят */
    private function deferred(string $query): ?array
    {
        $op = $this->client->searchDeferred($query);
        if ($op === null) {
            return null;
        }
        $poll = max(1, (int) config('monitoring.search.deferred_poll_seconds', 5));
        $deadline = time() + (int) config('monitoring.search.deferred_max_wait_seconds', 180);
        while (time() < $deadline) {
            sleep($poll);
            $r = $this->client->fetchDeferred($op);
            if ($r['done']) {
                if ($r['error'] !== null) {
                    Log::warning('Monitoring SearchGateway: deferred op error', ['op' => $op, 'error' => $r['error']]);
                }

                return array_map(fn ($x) => $x + ['query' => $query], $r['results']);
            }
        }
        Log::warning('Monitoring SearchGateway: deferred op timeout', ['op' => $op, 'query' => $query]);

        return [];
    }
}
