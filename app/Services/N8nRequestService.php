<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N8nRequestService
{
    protected string $baseUrl;
    protected string $authToken;
    protected string $parseAuthToken;

    public function __construct()
    {
        $this->baseUrl = config('services.n8n.webhook_url', 'https://liftway.app.n8n.cloud/webhook');
        $this->authToken = config('services.n8n.auth_token');
        $this->parseAuthToken = config('services.n8n.parse_auth_token');
    }

    /**
     * Создать заявку
     */
    public function createRequest(array $data): array
    {
        return $this->call('request-management', [
            'action' => 'create_request',
            'source' => 'web_admin',
            ...$data
        ]);
    }

    /**
     * Получить заявку
     */
    public function getRequest(int $requestId): array
    {
        return $this->call('request-management', [
            'action' => 'get_request',
            'request_id' => $requestId
        ]);
    }

    /**
     * Список заявок
     */
    public function listRequests(array $filters = [], array $sort = [], array $pagination = []): array
    {
        return $this->call('request-management', [
            'action' => 'list_requests',
            'filters' => $filters,
            'sort' => $sort,
            'pagination' => $pagination
        ]);
    }

    /**
     * Обновить заявку
     */
    public function updateRequest(int $requestId, array $data): array
    {
        return $this->call('request-management', [
            'action' => 'update_request',
            'request_id' => $requestId,
            'data' => $data
        ]);
    }

    /**
     * Отменить заявку
     */
    public function cancelRequest(int $requestId, ?string $reason = null): array
    {
        return $this->call('request-management', [
            'action' => 'cancel_request',
            'request_id' => $requestId,
            'reason' => $reason
        ]);
    }

    /**
     * AI-парсинг текста заявки
     */
    public function parseRequestText(string $text): array
    {
        // Для parse-request используем специальный заголовок авторизации
        try {
            $headers = [
                'Content-Type' => 'application/json',
            ];

            // Для parse-request используется заголовок X-Auth-Token
            if ($this->parseAuthToken) {
                $headers['X-Auth-Token'] = $this->parseAuthToken;
            }

            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post("{$this->baseUrl}/parse-request", [
                    'text' => $text
                ]);

            if ($response->successful()) {
                $jsonData = $response->json();
                if ($jsonData === null) {
                    Log::error("N8n parse response is not valid JSON", [
                        'body' => $response->body()
                    ]);
                    return [
                        'success' => false,
                        'error' => 'INVALID_JSON',
                        'message' => 'Получен некорректный JSON от n8n'
                    ];
                }
                return $jsonData;
            }

            Log::error("N8n parse request failed", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'N8N_PARSE_ERROR',
                'message' => 'Ошибка при парсинге текста: ' . $response->status()
            ];

        } catch (\Exception $e) {
            Log::error("N8n parse exception", [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'PARSE_CONNECTION_ERROR',
                'message' => 'Не удалось подключиться к сервису парсинга: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Выполнить запрос к n8n
     */
    protected function call(string $endpoint, array $data, ?string $authToken = null): array
    {
        try {
            $headers = [
                'Content-Type' => 'application/json',
            ];

            // Используем переданный токен или дефолтный
            $token = $authToken ?? $this->authToken;
            if ($token) {
                $headers['X-Auth-Token'] = $token;
            }

            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post("{$this->baseUrl}/{$endpoint}", $data);

            if ($response->successful()) {
                $jsonData = $response->json();
                if ($jsonData === null) {
                    Log::error("N8n response is not valid JSON", [
                        'endpoint' => $endpoint,
                        'body' => $response->body()
                    ]);
                    return [
                        'success' => false,
                        'error' => 'INVALID_JSON',
                        'message' => 'Получен некорректный JSON от n8n'
                    ];
                }
                return $jsonData;
            }

            Log::error("N8n request failed", [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'N8N_ERROR',
                'message' => 'Ошибка при обращении к n8n: ' . $response->status()
            ];

        } catch (\Exception $e) {
            Log::error("N8n request exception", [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'CONNECTION_ERROR',
                'message' => 'Не удалось подключиться к n8n: ' . $e->getMessage()
            ];
        }
    }
}
