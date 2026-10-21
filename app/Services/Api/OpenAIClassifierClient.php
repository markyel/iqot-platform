<?php

namespace App\Services\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Тонкий HTTP-клиент к OpenAI-совместимому прокси (nginx ai3.lazylift.ru:8443, Алматы KZ;
 * ранее ai2.lazylift.ru, Cloudflare Worker, ai.lazylift.ru).
 *
 * Выдаёт JSON-структурированный результат (через `response_format: json_object`)
 * и валидирует корректность структуры на стороне вызывающего сервиса.
 */
class OpenAIClassifierClient
{
    /** @var array{model:string, prompt_tokens:int, completion_tokens:int}|null usage последнего вызова */
    private ?array $lastUsage = null;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $proxyKey,
        private readonly string $modelMini,
        private readonly string $modelFull,
        private readonly int $timeout,
    ) {
    }

    public static function fromConfig(): self
    {
        $cfg = config('services.openai_classifier');
        return new self(
            baseUrl: rtrim((string) ($cfg['base_url'] ?? ''), '/'),
            apiKey: (string) ($cfg['api_key'] ?? ''),
            proxyKey: (string) ($cfg['proxy_key'] ?? ''),
            modelMini: (string) ($cfg['model_mini'] ?? 'gpt-4o-mini'),
            modelFull: (string) ($cfg['model_full'] ?? 'gpt-4o'),
            timeout: (int) ($cfg['timeout'] ?? 30),
        );
    }

    /**
     * Вернёт true, если прокси сконфигурирован (есть URL и ключ).
     */
    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }

    public function modelMini(): string
    {
        return $this->modelMini;
    }

    public function modelFull(): string
    {
        return $this->modelFull;
    }

    /**
     * Вызывает /chat/completions с enforced JSON response.
     *
     * @param string $model
     * @param string $systemPrompt
     * @param string $userPrompt
     * @param int $maxTokens
     * @param float $temperature Дефолт 0 (детерминизм для analyze/identify/questions);
     *                           генерация тела рассылки зовёт с 0.7 для уникальности.
     * @return array<string,mixed> распарсенный JSON из message.content
     * @throws \RuntimeException
     */
    public function jsonCompletion(string $model, string $systemPrompt, string $userPrompt, int $maxTokens = 1024, float $temperature = 0.0): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('OpenAIClassifierClient is not configured.');
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
        if ($this->proxyKey !== '') {
            $headers['X-Proxy-Key'] = $this->proxyKey;
        }

        // connectTimeout отдельно от общего timeout: наблюдали вечный SYN-SENT к прокси
        // (коннект не устанавливается и не рвётся) — общий timeout его не оборвал,
        // рендер вставал навсегда. Коннект дольше 15с = мёртвая точка, рвём сразу.
        $response = $this->post($headers, [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'response_format' => ['type' => 'json_object'],
        ]);

        if (!$response->successful()) {
            $body = $response->body();
            Log::warning('OpenAIClassifier: non-200 response', [
                'status' => $response->status(),
                'body' => substr($body, 0, 500),
            ]);
            throw new \RuntimeException('OpenAIClassifier HTTP ' . $response->status());
        }

        $data = $response->json();
        $this->rememberUsage($data, $model);
        $content = $data['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || $content === '') {
            throw new \RuntimeException('OpenAIClassifier: empty completion content.');
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OpenAIClassifier: completion is not valid JSON.');
        }
        return $decoded;
    }

    /**
     * POST к прокси с повтором на временных отказах. Прокси (nginx limit_req) под пиковой нагрузкой
     * отвечает 503 HTML-заглушкой: 15.10.2026 до 23% вызовов за час (мониторинг 14 индекс-воркеров +
     * дискавери + анализ ответов) — без повтора это сбой проверки/разбора. 2 повтора с паузой 2–3 и
     * 5–7 с (джиттер, чтобы воркеры не били синхронно); 4xx, кроме 429, не повторяем.
     */
    private function post(array $headers, array $payload): \Illuminate\Http\Client\Response
    {
        $this->lastProvider = 'primary';
        $backup = self::backupConfig();
        // Основной уже признан недоступным (кончился бюджет / 5xx / нет связи) — сразу в резерв, без пауз повтора.
        if ($backup !== null && \Illuminate\Support\Facades\Cache::get(self::PRIMARY_DOWN_KEY)) {
            $b = $this->postBackup($backup, $payload);
            if ($b !== null && $b->successful()) {
                $this->lastProvider = 'beget';

                return $b;
            }
        }

        $response = null;
        try {
            foreach ([0, 2000, 5000] as $pauseMs) {
                if ($pauseMs > 0) {
                    usleep(($pauseMs + random_int(0, 2000)) * 1000);
                }
                // Сетевые сбои (таймаут чтения) не повторяем: три таймаута подряд пережили бы таймаут воркера.
                $response = Http::withHeaders($headers)
                    ->connectTimeout(15)
                    ->timeout($this->timeout)
                    ->post($this->baseUrl . '/chat/completions', $payload);
                if ($response->successful() || !in_array($response->status(), [429, 500, 502, 503, 504, 520, 522, 524], true)) {
                    return $response;
                }
                if ($response->status() === 429 && str_contains($response->body(), 'insufficient_quota')) {
                    break; // бюджет кончился — повторять бессмысленно
                }
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            if ($backup === null) {
                throw $e;
            }
            $response = null;
        }

        if ($backup !== null) {
            // Бюджет/5xx/нет связи — основной недоступен на 5 мин (дальше сразу резерв); обычный 429 перегрузки — резерв
            // только для этого вызова. 21.10.2026: кончился бюджет OpenAI, ИИ стоял 00:00–09:45 МСК.
            $hard = $response === null || $response->status() >= 500
                || ($response->status() === 429 && str_contains($response->body(), 'insufficient_quota'));
            if ($hard && \Illuminate\Support\Facades\Cache::add(self::PRIMARY_DOWN_KEY, 1, 300)) {
                Log::warning('OpenAIClassifier: основной ИИ недоступен — 5 мин через резерв', ['status' => $response?->status(), 'backup' => $backup['base_url']]);
            }
            $b = $this->postBackup($backup, $payload);
            if ($b !== null && $b->successful()) {
                $this->lastProvider = 'beget';

                return $b;
            }
        }

        if ($response === null) {
            throw new \RuntimeException('OpenAIClassifier: connection failed (primary and backup)');
        }

        return $response;
    }

    private const PRIMARY_DOWN_KEY = 'ai:primary:down';

    /**
     * Резервный OpenAI-совместимый шлюз (ИИ-Шлюз Beget): BEGET_LLM_BASE_URL + BEGET_LLM_API_KEY, соответствие моделей
     * BEGET_LLM_MODEL_MAP («gpt-4o=gpt-6.1-sol,gpt-4o-mini=gpt-6-luna»). Без ключа — резерва нет.
     *
     * @return array{base_url:string, api_key:string, models:array<string,string>, effort:string}|null
     */
    public static function backupConfig(): ?array
    {
        $b = (array) config('services.openai_classifier.backup', []);
        if (!($b['enabled'] ?? true) || empty($b['base_url']) || empty($b['api_key'])) {
            return null;
        }
        $models = [];
        foreach (array_filter(array_map('trim', explode(',', (string) ($b['models'] ?? '')))) as $pair) {
            [$from, $to] = array_pad(array_map('trim', explode('=', $pair, 2)), 2, '');
            if ($from !== '' && $to !== '') {
                $models[$from] = $to;
            }
        }

        return ['base_url' => rtrim((string) $b['base_url'], '/'), 'api_key' => (string) $b['api_key'], 'models' => $models,
            'effort' => (string) ($b['reasoning_effort'] ?? 'low')];
    }

    /** Модель резерва для нашей: точное соответствие, иначе «лёгкая» для mini/nano, иначе «полная». */
    public static function backupModel(array $backup, string $model): string
    {
        if (isset($backup['models'][$model])) {
            return $backup['models'][$model];
        }
        $light = str_contains($model, 'mini') || str_contains($model, 'nano');

        return $light ? ($backup['models']['gpt-4o-mini'] ?? 'gpt-6-luna') : ($backup['models']['gpt-4o'] ?? 'gpt-6.1-sol');
    }

    private function postBackup(array $backup, array $payload): ?\Illuminate\Http\Client\Response
    {
        $payload['model'] = self::backupModel($backup, (string) ($payload['model'] ?? ''));
        // Модели шлюза «рассуждающие»: без низкого усилия бюджет max_tokens уходит на рассуждения, ответ пустой.
        $payload['reasoning_effort'] = $backup['effort'];
        $payload['max_tokens'] = max(300, (int) ($payload['max_tokens'] ?? 0));
        try {
            return Http::withHeaders(['Authorization' => 'Bearer ' . $backup['api_key'], 'Content-Type' => 'application/json'])
                ->connectTimeout(15)
                ->timeout(max($this->timeout, 60))
                ->post($backup['base_url'] . '/chat/completions', $payload);
        } catch (\Throwable $e) {
            Log::warning('OpenAIClassifier: резервный ИИ недоступен', ['error' => mb_substr($e->getMessage(), 0, 200)]);

            return null;
        }
    }

    /**
     * Вызывает /chat/completions БЕЗ enforced JSON — возвращает сырой текст message.content.
     *
     * Нужен для генерации трекинг-токена рассылки (system «generate ONLY the token string»):
     * модель отдаёт короткую строку-токен, а не JSON-объект.
     *
     * @throws \RuntimeException
     */
    public function textCompletion(string $model, string $systemPrompt, string $userPrompt, int $maxTokens = 100, float $temperature = 0.0): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('OpenAIClassifierClient is not configured.');
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
        if ($this->proxyKey !== '') {
            $headers['X-Proxy-Key'] = $this->proxyKey;
        }

        // connectTimeout отдельно от общего timeout: наблюдали вечный SYN-SENT к прокси
        // (коннект не устанавливается и не рвётся) — общий timeout его не оборвал,
        // рендер вставал навсегда. Коннект дольше 15с = мёртвая точка, рвём сразу.
        $response = $this->post($headers, [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ]);

        if (!$response->successful()) {
            $body = $response->body();
            Log::warning('OpenAIClassifier: non-200 response (text)', [
                'status' => $response->status(),
                'body' => substr($body, 0, 500),
            ]);
            throw new \RuntimeException('OpenAIClassifier HTTP ' . $response->status());
        }

        $data = $response->json();
        $this->rememberUsage($data, $model);
        $content = $data['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || $content === '') {
            throw new \RuntimeException('OpenAIClassifier: empty text completion content.');
        }
        return $content;
    }

    /**
     * Расход токенов последнего вызова (для учёта стоимости, напр. мониторинг цен).
     * model — фактическое имя модели из ответа прокси (может быть с датой версии).
     *
     * @return array{model:string, prompt_tokens:int, completion_tokens:int}|null
     */
    public function lastUsage(): ?array
    {
        return $this->lastUsage;
    }

    private function rememberUsage(mixed $data, string $requestedModel): void
    {
        $usage = is_array($data) ? ($data['usage'] ?? null) : null;
        $this->lastUsage = [
            'model' => is_array($data) && is_string($data['model'] ?? null) && $data['model'] !== '' ? $data['model'] : $requestedModel,
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
        ];
        $this->recordUsage(is_array($usage) ? $usage : []);
    }

    /** Где сейчас был ответ: основной прокси или резерв (Beget). */
    private string $lastProvider = 'primary';

    /**
     * Учёт расхода по задачам за МСК-сутки (ai_usage_daily, 21.10.2026). Задача — класс, вызвавший клиент.
     * Цена OpenAI — по monitoring.llm_prices (закэшированный вход — половина цены входа), у Beget — его cost_rub.
     * Сбой учёта вызов не роняет.
     */
    private function recordUsage(array $usage): void
    {
        try {
            $task = 'unknown';
            foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8) as $f) {
                if (isset($f['class']) && $f['class'] !== self::class) {
                    $task = class_basename($f['class']);
                    break;
                }
            }
            $model = mb_substr((string) ($this->lastUsage['model'] ?? ''), 0, 60);
            $prompt = (int) ($usage['prompt_tokens'] ?? 0);
            $completion = (int) ($usage['completion_tokens'] ?? 0);
            $cached = (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0);
            $rub = (float) ($usage['cost_rub'] ?? 0);
            $usd = 0.0;
            if ($this->lastProvider === 'primary') {
                $price = (new \App\Services\Monitoring\LlmCostCalculator())->priceFor($model);
                if ($price !== null) {
                    $usd = (($prompt - $cached) * $price[0] + $cached * $price[0] * 0.5 + $completion * $price[1]) / 1_000_000;
                }
            }
            \Illuminate\Support\Facades\DB::statement(
                'INSERT INTO ai_usage_daily (day, task, model, provider, calls, prompt_tokens, cached_tokens, completion_tokens, cost_usd, cost_rub, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE calls = calls + 1, prompt_tokens = prompt_tokens + VALUES(prompt_tokens),
                   cached_tokens = cached_tokens + VALUES(cached_tokens), completion_tokens = completion_tokens + VALUES(completion_tokens),
                   cost_usd = cost_usd + VALUES(cost_usd), cost_rub = cost_rub + VALUES(cost_rub), updated_at = NOW()',
                [now('Europe/Moscow')->toDateString(), $task, $model, $this->lastProvider, $prompt, $cached, $completion, round($usd, 4), round($rub, 2)]
            );
        } catch (\Throwable) {
            // учёт не критичен
        }
    }
}
