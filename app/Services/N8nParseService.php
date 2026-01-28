<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N8nParseService
{
    private ?string $webhookUrl;
    private ?string $authToken;

    public function __construct()
    {
        $this->webhookUrl = config('services.n8n.parse_webhook_url') ?: config('services.n8n.webhook_url') . '/parse-request';
        $this->authToken = config('services.n8n.parse_auth_token');
    }

    /**
     * Парсинг текста заявки через AI
     *
     * @param string $text Текст заявки от пользователя
     * @return array ['success' => bool, 'items' => [...], 'items_count' => int]
     */
    public function parseRequest(string $text): array
    {
        // Проверка конфигурации
        if (empty($this->webhookUrl)) {
            return [
                'success' => false,
                'error' => 'Configuration error',
                'message' => 'N8N_PARSE_WEBHOOK_URL не настроен. Обратитесь к администратору.',
                'items' => []
            ];
        }

        if (empty($this->authToken)) {
            return [
                'success' => false,
                'error' => 'Configuration error',
                'message' => 'N8N_PARSE_AUTH_TOKEN не настроен. Обратитесь к администратору.',
                'items' => []
            ];
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders(['X-Auth-Token' => $this->authToken])
                ->post($this->webhookUrl, ['text' => $text]);

            if ($response->successful()) {
                $jsonData = $response->json();

                if ($jsonData === null) {
                    $body = $response->body();
                    if (empty($body)) {
                        Log::error('N8n Parse empty response', [
                            'url' => $this->webhookUrl,
                            'text_length' => strlen($text)
                        ]);

                        return [
                            'success' => false,
                            'error' => 'Empty response from n8n',
                            'message' => 'n8n workflow вернул пустой ответ. Убедитесь что добавлен node "Respond to Webhook"',
                            'items' => []
                        ];
                    }

                    Log::error('N8n Parse invalid JSON', [
                        'url' => $this->webhookUrl,
                        'body' => substr($body, 0, 500)
                    ]);

                    return [
                        'success' => false,
                        'error' => 'Invalid JSON response',
                        'message' => 'Сервер вернул некорректный ответ. Проверьте настройки workflow в n8n.',
                        'items' => []
                    ];
                }

                // Нормализуем данные от n8n
                $result = is_array($jsonData) ? $jsonData : ['data' => $jsonData];

                // Исправляем строки "null" в реальные null для каждого item
                if (isset($result['items']) && is_array($result['items'])) {
                    foreach ($result['items'] as &$item) {
                        // Нормализуем поля классификации
                        $item['product_type_id'] = $this->normalizeNull($item['product_type_id'] ?? null);
                        $item['product_type_name'] = $this->normalizeNull($item['product_type_name'] ?? null);
                        $item['domain_id'] = $this->normalizeNull($item['domain_id'] ?? null);
                        $item['domain_name'] = $this->normalizeNull($item['domain_name'] ?? null);
                        $item['brand'] = $this->normalizeNull($item['brand'] ?? null);
                        $item['article'] = $this->normalizeNull($item['article'] ?? null);
                    }
                    unset($item); // Разрываем ссылку
                }

                return $result;
            }

            $statusCode = $response->status();
            $errorBody = $response->body();

            Log::error('N8n Parse API error', [
                'url' => $this->webhookUrl,
                'status' => $statusCode,
                'body' => substr($errorBody, 0, 500),
                'text_length' => strlen($text)
            ]);

            if ($statusCode === 401 || $statusCode === 403) {
                return [
                    'success' => false,
                    'error' => 'Authorization failed',
                    'message' => 'Ошибка авторизации на сервере парсинга (HTTP ' . $statusCode . '). Проверьте N8N_PARSE_AUTH_TOKEN.',
                    'items' => []
                ];
            }

            if ($statusCode === 404) {
                return [
                    'success' => false,
                    'error' => 'Workflow not found',
                    'message' => 'Workflow парсинга не найден (HTTP 404). Проверьте N8N_PARSE_WEBHOOK_URL.',
                    'items' => []
                ];
            }

            return [
                'success' => false,
                'error' => 'API request failed',
                'message' => 'Ошибка сервера парсинга (HTTP ' . $statusCode . '). Обратитесь к администратору.',
                'items' => []
            ];

        } catch (\Exception $e) {
            Log::error('N8n Parse API exception', [
                'url' => $this->webhookUrl,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Connection failed',
                'message' => 'Не удалось связаться с сервером: ' . $e->getMessage(),
                'items' => []
            ];
        }
    }

    /**
     * Преобразует строку "null" в реальный null
     */
    private function normalizeNull($value)
    {
        if ($value === 'null' || $value === 'NULL' || $value === '') {
            return null;
        }
        return $value;
    }
}
