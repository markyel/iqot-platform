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
     * Форма редактирования заявки
     */
    public function edit($id)
    {
        $request = RequestModel::with(['user', 'items'])->findOrFail($id);

        // Проверка что заявку можно редактировать
        if ($request->synced_to_main_db) {
            return redirect()
                ->route('admin.requests.show', $id)
                ->with('error', 'Нельзя редактировать заявку, которая уже отправлена в обработку');
        }

        return view('admin.requests.edit', compact('request'));
    }

    /**
     * Обновление заявки
     */
    public function update($id, Request $request)
    {
        $requestModel = RequestModel::with(['user', 'items'])->findOrFail($id);

        // Проверка что заявку можно редактировать
        if ($requestModel->synced_to_main_db) {
            return back()->with('error', 'Нельзя редактировать заявку, которая уже отправлена в обработку');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:request_items,id',
            'items.*.name' => 'required|string|max:500',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit' => 'required|string|max:50',
            'items.*.brand' => 'nullable|string|max:100',
            'items.*.article' => 'nullable|string|max:100',
            'items.*.category' => 'required|string|max:100',
            'items.*.product_type_id' => 'nullable|integer',
            'items.*.domain_id' => 'nullable|integer',
            'items.*.description' => 'nullable|string|max:1000',
        ]);

        // Обновляем заявку
        $requestModel->update([
            'title' => $validated['title'],
            'notes' => $validated['notes'],
        ]);

        // Обновляем позиции
        foreach ($validated['items'] as $itemData) {
            $requestModel->items()->where('id', $itemData['id'])->update([
                'name' => $itemData['name'],
                'quantity' => $itemData['quantity'],
                'unit' => $itemData['unit'],
                'brand' => $itemData['brand'] ?? null,
                'article' => $itemData['article'] ?? null,
                'category' => $itemData['category'],
                'product_type_id' => !empty($itemData['product_type_id']) ? $itemData['product_type_id'] : null,
                'domain_id' => !empty($itemData['domain_id']) ? $itemData['domain_id'] : null,
                'description' => $itemData['description'] ?? null,
            ]);
        }

        return redirect()
            ->route('admin.requests.show', $id)
            ->with('success', 'Заявка успешно обновлена');
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
            // Средства остаются замороженными до выполнения позиций (3+ предложения)
            // Списание произойдет автоматически при получении достаточного количества предложений

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
