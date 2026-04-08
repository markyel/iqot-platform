<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\ParseTask;
use App\Models\ProductType;
use App\Models\ApplicationDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ParseWebhookController extends Controller
{
    /**
     * Webhook для получения результата парсинга от n8n
     */
    public function callback(Request $request)
    {
        // Проверка авторизации
        $authToken = $request->header('X-Auth-Token');
        if ($authToken !== config('services.n8n.parse_auth_token')) {
            Log::warning('Parse webhook: unauthorized access attempt', [
                'ip' => $request->ip(),
                'token' => $authToken
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->all();

        Log::info('Parse webhook received', [
            'task_id' => $data['task_id'] ?? null,
            'success' => $data['success'] ?? null,
            'has_items' => isset($data['items'])
        ]);

        // Валидация данных
        if (empty($data['task_id'])) {
            Log::error('Parse webhook: missing task_id');
            return response()->json(['error' => 'Missing task_id'], 400);
        }

        // Находим задачу
        $task = ParseTask::where('task_id', $data['task_id'])->first();

        if (!$task) {
            Log::error('Parse webhook: task not found', ['task_id' => $data['task_id']]);
            return response()->json(['error' => 'Task not found'], 404);
        }

        // Обрабатываем результат
        if (isset($data['success']) && $data['success']) {
            // Успешный парсинг
            $items = $data['items'] ?? [];

            // Нормализуем поля (как в оригинальном сервисе)
            foreach ($items as &$item) {
                $item['product_type_id'] = $this->normalizeNull($item['product_type_id'] ?? null);
                $item['product_type_name'] = $this->normalizeNull($item['product_type_name'] ?? null);
                $item['domain_id'] = $this->normalizeNull($item['domain_id'] ?? null);
                $item['domain_name'] = $this->normalizeNull($item['domain_name'] ?? null);
                $item['brand'] = $this->normalizeNull($item['brand'] ?? null);
                $item['article'] = $this->normalizeNull($item['article'] ?? null);
            }
            unset($item);

            // Формируем результат
            $result = [
                'items' => $items,
                'items_count' => count($items),
                'has_new_classifications' => $data['has_new_classifications'] ?? false,
            ];

            // Если были созданы новые классификации - обновляем списки
            if ($result['has_new_classifications']) {
                $result['updated_product_types'] = ProductType::getActiveForSelect();
                $result['updated_application_domains'] = ApplicationDomain::getActiveForSelect();
            }

            $task->markAsCompleted($result);

            Log::info('Parse task completed', [
                'task_id' => $data['task_id'],
                'items_count' => count($items)
            ]);
        } else {
            // Ошибка парсинга
            $errorMessage = $data['message'] ?? $data['error'] ?? 'Неизвестная ошибка парсинга';
            $task->markAsFailed($errorMessage);

            Log::error('Parse task failed', [
                'task_id' => $data['task_id'],
                'error' => $errorMessage
            ]);
        }

        return response()->json(['success' => true]);
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
