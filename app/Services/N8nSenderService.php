<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N8nSenderService
{
    private ?string $webhookUrl;
    private ?string $authToken;

    public function __construct()
    {
        $this->webhookUrl = config('services.n8n.sender_webhook_url');
        $this->authToken = config('services.n8n.sender_auth_token');
    }

    /**
     * Получить список свободных резервных email
     */
    public function getAvailableEmails(): array
    {
        return $this->request('get_available_emails');
    }

    /**
     * Получить список шаблонов писем
     */
    public function getEmailTemplates(): array
    {
        return $this->request('get_email_templates');
    }

    /**
     * Получить Sender пользователя
     */
    public function getUserSender(int $userId): ?array
    {
        $response = $this->request('get_user_sender', ['user_id' => $userId]);
        return $response['sender'] ?? null;
    }

    /**
     * Получить Sender и организацию пользователя
     */
    public function getUserSenderWithOrganization(int $userId): array
    {
        return $this->request('get_user_sender', ['user_id' => $userId]);
    }

    /**
     * Создать Sender для пользователя
     */
    public function createSender(int $userId, array $data): array
    {
        return $this->request('create_sender', [
            'data' => array_merge(['user_id' => $userId], $data)
        ]);
    }

    /**
     * Обновить Sender
     */
    public function updateSender(int $senderId, array $data): array
    {
        return $this->request('update_sender', [
            'sender_id' => $senderId,
            'data' => $data
        ]);
    }

    /**
     * Деактивировать Sender
     */
    public function deactivateSender(int $senderId): bool
    {
        $response = $this->request('deactivate_sender', ['sender_id' => $senderId]);
        return $response['success'] ?? false;
    }

    /**
     * Выполнить запрос к n8n API
     */
    private function request(string $action, array $params = []): array
    {
        if (!$this->webhookUrl || !$this->authToken) {
            Log::error('N8nSenderService: webhook URL or auth token not configured', [
                'webhook_url' => $this->webhookUrl ? 'set' : 'not set',
                'auth_token' => $this->authToken ? 'set' : 'not set',
            ]);

            return [
                'success' => false,
                'error' => 'N8N Sender service not configured. Please set N8N_SENDER_WEBHOOK_URL and N8N_SENDER_AUTH_TOKEN in .env file.',
            ];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Auth-Token' => $this->authToken
                ])
                ->post($this->webhookUrl, array_merge([
                    'action' => $action,
                ], $params));

            if ($response->successful()) {
                $jsonData = $response->json();

                // Логируем успешные ответы для отладки
                Log::info('N8n Sender API success', [
                    'action' => $action,
                    'status' => $response->status(),
                    'response_type' => gettype($jsonData),
                    'response_preview' => is_array($jsonData) ? array_keys($jsonData) : $jsonData
                ]);

                // Если json() вернул null, значит ответ не JSON
                if ($jsonData === null) {
                    $body = $response->body();

                    Log::warning('N8n returned null JSON', [
                        'action' => $action,
                        'body' => $body,
                        'body_length' => strlen($body),
                        'content_type' => $response->header('Content-Type'),
                        'status' => $response->status()
                    ]);

                    // Если тело пустое, возвращаем специальное сообщение
                    if (empty($body)) {
                        return [
                            'success' => false,
                            'error' => 'Empty response from n8n',
                            'message' => 'n8n workflow вернул пустой ответ. Проверьте что в workflow есть "Respond to Webhook" node с JSON данными.',
                            'action' => $action,
                            'hint' => 'В n8n добавьте node "Respond to Webhook" и настройте его на возврат JSON'
                        ];
                    }

                    return [
                        'success' => false,
                        'error' => 'Invalid JSON response',
                        'message' => 'Server returned non-JSON response. Content-Type: ' . $response->header('Content-Type'),
                        'raw_body' => substr($body, 0, 500)
                    ];
                }

                // Гарантируем что возвращаем массив
                return is_array($jsonData) ? $jsonData : ['data' => $jsonData];
            }

            $statusCode = $response->status();
            $body = $response->body();

            Log::error('N8n Sender API error', [
                'action' => $action,
                'status' => $statusCode,
                'body' => $body,
                'headers' => $response->headers()
            ]);

            // Попробуем получить JSON ответ, если есть
            $jsonResponse = $response->json();

            return [
                'success' => false,
                'error' => 'API request failed',
                'message' => $jsonResponse['message'] ?? $jsonResponse['error'] ?? "HTTP {$statusCode}: " . substr($body, 0, 200),
                'status_code' => $statusCode,
                'raw_body' => substr($body, 0, 500) // Первые 500 символов для отладки
            ];

        } catch (\Exception $e) {
            Log::error('N8n Sender API exception', [
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Connection failed',
                'message' => $e->getMessage(),
                'exception' => get_class($e)
            ];
        }
    }
}
