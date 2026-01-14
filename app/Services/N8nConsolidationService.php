<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N8nConsolidationService
{
    protected ?string $webhookUrl;
    protected ?string $authToken;

    public function __construct()
    {
        $this->webhookUrl = config('services.n8n.webhook_url');
        $this->authToken = config('services.n8n.auth_token');
    }

    /**
     * Консолидация вопросов через n8n webhook
     *
     * @param array $itemsForConsolidation
     * @return array
     */
    public function consolidateQuestions(array $itemsForConsolidation): array
    {
        try {
            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->authToken) {
                $headers['X-Auth-Token'] = $this->authToken;
            }

            $response = Http::withHeaders($headers)
                ->timeout(60) // увеличенный таймаут для AI обработки
                ->post("{$this->webhookUrl}/consolidate-questions", [
                    'items_for_consolidation' => $itemsForConsolidation
                ]);

            if ($response->successful()) {
                $jsonData = $response->json();
                if ($jsonData === null) {
                    Log::error("N8n consolidation response is not valid JSON", [
                        'body' => $response->body()
                    ]);
                    return [
                        'success' => false,
                        'error' => 'INVALID_JSON',
                        'message' => 'Получен некорректный JSON от n8n'
                    ];
                }
                return array_merge(['success' => true], $jsonData);
            }

            Log::error("N8n consolidation request failed", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'N8N_ERROR',
                'message' => 'Ошибка при обращении к n8n: ' . $response->status()
            ];

        } catch (\Exception $e) {
            Log::error("N8n consolidation exception", [
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
