<?php

namespace App\Services\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Тонкий HTTP-клиент к OpenAI-совместимому прокси (ai.lazylift.ru).
 *
 * Выдаёт JSON-структурированный результат (через `response_format: json_object`)
 * и валидирует корректность структуры на стороне вызывающего сервиса.
 */
class OpenAIClassifierClient
{
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
        $response = Http::withHeaders($headers)
            ->connectTimeout(15)
            ->timeout($this->timeout)
            ->post($this->baseUrl . '/chat/completions', [
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
        $response = Http::withHeaders($headers)
            ->connectTimeout(15)
            ->timeout($this->timeout)
            ->post($this->baseUrl . '/chat/completions', [
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
        $content = $data['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || $content === '') {
            throw new \RuntimeException('OpenAIClassifier: empty text completion content.');
        }
        return $content;
    }
}
