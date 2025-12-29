<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Request as RequestModel;
use App\Models\BalanceHold;
use App\Services\RequestSyncService;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    private RequestSyncService $syncService;

    public function __construct(RequestSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Список всех заявок пользователей
     */
    public function index(Request $request)
    {
        $query = RequestModel::with(['user', 'items', 'balanceHold'])
            ->where('is_customer_request', 1);

        // Фильтр по статусу
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Фильтр по синхронизации
        if ($request->filled('synced')) {
            $query->where('synced_to_main_db', $request->synced === 'yes');
        }

        // Поиск
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $requests = $query->orderByDesc('created_at')->paginate(20);

        return view('admin.requests.index', compact('requests'));
    }

    /**
     * Просмотр заявки
     */
    public function show($id)
    {
        $request = RequestModel::with(['user', 'items', 'balanceHold'])
            ->findOrFail($id);

        // Получаем данные sender для отображения email
        $senderService = app(\App\Services\N8nSenderService::class);
        $senderData = $senderService->getUserSenderWithOrganization($request->user->id);
        $senderEmail = $senderData['sender']['email'] ?? null;

        return view('admin.requests.show', compact('request', 'senderEmail'));
    }

    /**
     * Одобрить заявку и отправить в работу (синхронизация в основную БД)
     */
    public function approve($id)
    {
        $request = RequestModel::with(['user', 'items'])->findOrFail($id);

        // Проверка что заявка ещё не синхронизирована
        if ($request->synced_to_main_db) {
            return back()->with('error', 'Заявка уже отправлена в обработку');
        }

        // Проверка статуса
        if ($request->status !== RequestModel::STATUS_PENDING) {
            return back()->with('error', 'Можно отправить в работу только заявки в статусе "Ожидает отправки"');
        }

        // Меняем статус на "Отправка запросов" перед синхронизацией
        $request->update([
            'status' => RequestModel::STATUS_SENDING,
        ]);

        // Синхронизация в основную БД
        $result = $this->syncService->syncToMainDb($request);

        if ($result['success']) {
            // Списываем средства с баланса (заморозка → списание)
            if ($request->balanceHold && $request->balanceHold->status === 'held') {
                $request->balanceHold->charge();
            }

            return back()->with('success', "Заявка #{$request->request_number} отправлена в обработку (ID в основной БД: {$result['main_request_id']})");
        }

        // В случае ошибки возвращаем статус обратно
        $request->update([
            'status' => RequestModel::STATUS_PENDING,
        ]);

        return back()->with('error', 'Ошибка синхронизации: ' . $result['error']);
    }

    /**
     * Отклонить заявку
     */
    public function reject($id, Request $request)
    {
        $requestModel = RequestModel::with(['user', 'balanceHold'])->findOrFail($id);

        // Проверка что заявка ещё не синхронизирована
        if ($requestModel->synced_to_main_db) {
            return back()->with('error', 'Нельзя отклонить заявку, которая уже отправлена в обработку');
        }

        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        // Возвращаем средства
        if ($requestModel->balanceHold && $requestModel->balanceHold->status === 'held') {
            $requestModel->balanceHold->release();
        }

        // Меняем статус
        $requestModel->update([
            'status' => RequestModel::STATUS_CANCELLED,
            'notes' => ($requestModel->notes ? $requestModel->notes . "\n\n" : '') . "Отклонена администратором: " . $request->reason,
        ]);

        return back()->with('success', "Заявка #{$requestModel->request_number} отклонена. Средства возвращены пользователю.");
    }

    /**
     * Тестирование подключения к основной БД
     */
    public function testConnection()
    {
        $result = $this->syncService->testConnection();

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', 'Ошибка подключения: ' . $result['error']);
    }
}
