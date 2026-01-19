<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N8nReportService
{
    protected string $baseUrl;
    protected ?string $authToken;
    protected string $callbackBaseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.n8n.webhook_url', 'https://liftway.app.n8n.cloud/webhook');
        $this->authToken = config('services.n8n.report_auth_token');
        $this->callbackBaseUrl = config('app.production_url', config('app.url'));
    }

    /**
     * Запустить генерацию отчёта
     *
     * @param array $requestIds ID заявок
     * @param int $userId ID пользователя
     * @param array $options Опции генерации
     * @return array {success, report_id, status, message}
     */
    public function generateReport(
        array $requestIds,
        int $userId,
        array $options = []
    ): array {
        return $this->call([
            'action' => 'generate_report',
            'user_id' => $userId,
            'request_ids' => $requestIds,
            'callback_url' => rtrim($this->callbackBaseUrl, '/') . '/api/webhooks/report-ready-pdf',
            'report_options' => $options,
        ]);
    }

    /**
     * Получить статус отчёта (fallback)
     *
     * @param int $reportId ID отчёта
     * @return array {success, report_id, status, file?, metadata?}
     */
    public function getReportStatus(int $reportId): array
    {
        return $this->call([
            'action' => 'get_report_status',
            'report_id' => $reportId,
        ]);
    }

    /**
     * Выполнить запрос к n8n
     */
    protected function call(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->authToken,
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->post($this->baseUrl . '/report-management', $data);

            if (!$response->successful()) {
                Log::error('N8n Report API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'data' => $data
                ]);

                return [
                    'success' => false,
                    'error' => 'API_ERROR',
                    'message' => 'Ошибка API: ' . $response->status()
                ];
            }

            $result = $response->json();

            // n8n может возвращать массив с одним элементом
            if (is_array($result) && isset($result[0]) && is_array($result[0])) {
                $result = $result[0];
            }

            Log::info('N8n Report API response', [
                'action' => $data['action'] ?? 'unknown',
                'success' => $result['success'] ?? false
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('N8n Report API exception', [
                'message' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'error' => 'NETWORK_ERROR',
                'message' => 'Ошибка соединения с сервисом генерации отчетов'
            ];
        }
    }
}
