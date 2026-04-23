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
     * @return array<string,mixed> распарсенный JSON из message.content
     * @throws \RuntimeException
     */
    public function jsonCompletion(string $model, string $systemPrompt, string $userPrompt, int $maxTokens = 1024): array
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

        $response = Http::withHeaders($headers)
            ->timeout($this->timeout)
            ->post($this->baseUrl . '/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0,
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
}
