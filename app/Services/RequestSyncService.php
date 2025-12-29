<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Request as LocalRequest;

class RequestSyncService
{
    protected string $connection = 'n8n_mysql';
    protected N8nSenderService $senderService;

    public function __construct(N8nSenderService $senderService)
    {
        $this->senderService = $senderService;
    }

    /**
     * Синхронизировать заявку в основную БД
     */
    public function syncToMainDb(LocalRequest $localRequest): array
    {
        $mainDb = DB::connection($this->connection);

        try {
            $mainDb->beginTransaction();

            // Получаем данные organization и sender из n8n
            $senderData = $this->senderService->getUserSenderWithOrganization($localRequest->user->id);
            $organization = $senderData['organization'] ?? null;
            $sender = $senderData['sender'] ?? null;

            // 1. Создаём заявку
            $mainDb->table('requests')->insert([
                'user_id' => $this->getMainDbUserId($localRequest->user),
                'request_number' => $localRequest->request_number,
                'title' => $localRequest->title,
                'status' => 'new',
                'notes' => $localRequest->notes ?? 'Создано через iqot.ru',
                'total_items' => $localRequest->items->count(),
                'is_customer_request' => 1,
                'client_organization_id' => $localRequest->user->client_organization_id,
                'customer_company' => $organization['name'] ?? $localRequest->user->company_name,
                'customer_contact_person' => $organization['contact_person'] ?? $localRequest->user->contact_person,
                'customer_email' => $sender['email'] ?? $organization['email'] ?? $localRequest->user->email,
                'customer_phone' => $organization['phone'] ?? $localRequest->user->company_phone ?? $localRequest->user->phone,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $mainRequestId = $mainDb->getPdo()->lastInsertId();

            // 2. Создаём позиции
            $positionNumber = 1;
            foreach ($localRequest->items as $item) {
                $mainDb->table('request_items')->insert([
                    'request_id' => $mainRequestId,
                    'position_number' => $positionNumber++,
                    'name' => $item->name,
                    'brand' => $item->brand,
                    'article' => $item->article,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit ?? 'шт.',
                    'category' => $item->category ?? 'Другое',
                    'description' => $item->description,
                    'product_type_id' => $item->product_type_id,
                    'domain_id' => $item->domain_id,
                    'type_confidence' => $item->type_confidence,
                    'domain_confidence' => $item->domain_confidence,
                    'classification_needs_review' => $item->classification_needs_review ? 1 : 0,
                    'created_at' => now(),
                ]);
            }

            $mainDb->commit();

            // 3. Обновляем локальную заявку
            $localRequest->update([
                'synced_to_main_db' => true,
                'main_db_request_id' => $mainRequestId,
                'synced_at' => now(),
            ]);

            Log::info('Request synced to main DB', [
                'local_id' => $localRequest->id,
                'main_id' => $mainRequestId,
                'request_number' => $localRequest->request_number,
            ]);

            return [
                'success' => true,
                'main_request_id' => $mainRequestId,
            ];

        } catch (\Exception $e) {
            $mainDb->rollBack();

            Log::error('Failed to sync request to main DB', [
                'local_id' => $localRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Получить user_id в основной БД
     * Используем системного пользователя для всех веб-заявок
     */
    protected function getMainDbUserId($user): int
    {
        return config('services.n8n.system_user_id', 1);
    }

    /**
     * Проверить подключение к основной БД
     */
    public function testConnection(): array
    {
        try {
            $mainDb = DB::connection($this->connection);
            $mainDb->getPdo();

            return [
                'success' => true,
                'message' => 'Подключение к основной БД успешно',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
