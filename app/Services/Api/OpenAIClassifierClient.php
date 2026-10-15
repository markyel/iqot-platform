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
        $response = null;
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
        }

        return $response;
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
    }
}
