<?php

namespace App\Http\Controllers;

use App\Services\N8nParseService;
use App\Models\SystemSetting;
use App\Models\Request as RequestModel;
use App\Models\RequestItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserRequestController extends Controller
{
    private N8nParseService $parseService;

    public function __construct(N8nParseService $parseService)
    {
        $this->parseService = $parseService;
    }

    /**
     * Страница создания заявки
     */
    public function create()
    {
        $user = Auth::user();

        // Проверяем наличие sender
        if (!$user->sender_id) {
            return redirect()->route('cabinet.dashboard')
                ->with('error', 'Для создания заявок необходимо настроить email-отправителя. Обратитесь к администратору для активации вашего аккаунта.');
        }

        $pricePerItem = (float) SystemSetting::get('price_per_item', 50);

        return view('requests.create', [
            'user' => $user,
            'pricePerItem' => $pricePerItem,
            'availableBalance' => $user->available_balance
        ]);
    }

    /**
     * AJAX: Парсинг текста через n8n AI
     */
    public function parse(Request $request)
    {
        $request->validate([
            'text' => 'required|string|min:3|max:10000'
        ]);

        $result = $this->parseService->parseRequest($request->text);

        if ($result['success'] ?? false) {
            $pricePerItem = (float) SystemSetting::get('price_per_item', 50);
            $itemsCount = count($result['items'] ?? []);
            $totalCost = $itemsCount * $pricePerItem;
            $availableBalance = Auth::user()->available_balance;

            $result['cost_info'] = [
                'price_per_item' => $pricePerItem,
                'items_count' => $itemsCount,
                'total_cost' => $totalCost,
                'available_balance' => $availableBalance,
                'can_afford' => $availableBalance >= $totalCost
            ];
        }

        return response()->json($result);
    }

    /**
     * AJAX: Создание заявки
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:500',
            'items.*.quantity' => 'required|integer|min:1|max:9999',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.brand' => 'nullable|string|max:100',
            'items.*.article' => 'nullable|string|max:100',
            'items.*.category' => 'nullable|string|max:100',
            'items.*.product_type_id' => 'nullable|integer',
            'items.*.domain_id' => 'nullable|integer',
        ]);

        $user = Auth::user();

        // Проверка sender
        if (!$user->sender_id || !$user->client_organization_id) {
            return response()->json([
                'success' => false,
                'error' => 'no_sender',
                'message' => 'Профиль организации не настроен'
            ], 400);
        }

        // Расчёт стоимости
        $pricePerItem = (float) SystemSetting::get('price_per_item', 50);
        $itemsCount = count($request->items);
        $totalCost = $itemsCount * $pricePerItem;

        // Проверка баланса
        if (!$user->canAfford($totalCost)) {
            return response()->json([
                'success' => false,
                'error' => 'insufficient_balance',
                'message' => "Недостаточно средств. Требуется: {$totalCost} руб., доступно: {$user->available_balance} руб.",
                'required' => $totalCost,
                'available' => $user->available_balance
            ], 400);
        }

        // Создаём заявку в транзакции
        try {
            $result = DB::transaction(function () use ($user, $request, $itemsCount, $totalCost) {

                // 1. Генерируем номер заявки
                $requestNumber = 'REQ-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

                // 2. Формируем title
                $firstItem = $request->items[0];
                $title = $itemsCount === 1
                    ? $firstItem['name']
                    : "Заявка на {$itemsCount} позиций";

                // 3. Создаём заявку
                $newRequest = RequestModel::create([
                    'user_id' => $user->id,
                    'client_organization_id' => $user->client_organization_id,
                    'code' => $requestNumber,
                    'request_number' => $requestNumber,
                    'title' => mb_substr($title, 0, 255),
                    'status' => RequestModel::STATUS_PENDING,
                    'is_customer_request' => 1,
                    'total_items' => $itemsCount,
                    'items_count' => $itemsCount,
                    'notes' => 'Создано через личный кабинет iqot.ru',
                ]);

                // 4. Создаём позиции заявки
                $positionNumber = 1;
                foreach ($request->items as $item) {
                    RequestItem::create([
                        'request_id' => $newRequest->id,
                        'position_number' => $positionNumber++,
                        'name' => $item['name'],
                        'brand' => $item['brand'] ?? null,
                        'article' => $item['article'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit' => $item['unit'] ?? 'шт.',
                        'category' => $item['category'] ?? 'Другое',
                        'product_type_id' => $item['product_type_id'] ?? null,
                        'domain_id' => $item['domain_id'] ?? null,
                        'type_confidence' => $item['type_confidence'] ?? null,
                        'domain_confidence' => $item['domain_confidence'] ?? null,
                        'classification_needs_review' => $item['needs_review'] ?? false,
                    ]);
                }

                // 5. Замораживаем средства
                $user->holdBalance($totalCost, $newRequest->id, "Заморозка для заявки {$requestNumber}");

                return [
                    'request_id' => $newRequest->id,
                    'request_number' => $requestNumber,
                    'items_count' => $itemsCount,
                    'total_cost' => $totalCost,
                ];
            });

            return response()->json([
                'success' => true,
                'request_id' => $result['request_id'],
                'request_number' => $result['request_number'],
                'items_count' => $result['items_count'],
                'total_cost' => $result['total_cost'],
                'message' => 'Заявка успешно создана'
            ]);

        } catch (\Exception $e) {
            \Log::error('Request creation failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'creation_failed',
                'message' => 'Ошибка при создании заявки'
            ], 500);
        }
    }

    /**
     * AJAX: Проверка баланса
     */
    public function checkBalance()
    {
        $user = Auth::user();
        $heldAmount = $user->balanceHolds()->where('status', 'held')->sum('amount');

        return response()->json([
            'success' => true,
            'total_balance' => (float) $user->balance,
            'held_amount' => (float) $heldAmount,
            'available_balance' => $user->available_balance
        ]);
    }

    /**
     * Список заявок пользователя
     */
    public function index()
    {
        $requests = Auth::user()->requests()
            ->with(['items', 'balanceHold'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('requests.index', compact('requests'));
    }

    /**
     * Просмотр заявки
     */
    public function show($id)
    {
        $request = Auth::user()->requests()
            ->with(['items', 'balanceHold'])
            ->findOrFail($id);

        return view('requests.show', compact('request'));
    }

    /**
     * Просмотр отчета по заявке
     */
    public function showReport($id)
    {
        // Получаем заявку пользователя
        $request = Auth::user()->requests()->findOrFail($id);

        // Проверяем, что заявка синхронизирована с главной БД
        if (!$request->synced_to_main_db || !$request->main_db_request_id) {
            return redirect()->route('cabinet.my.requests.show', $id)
                ->with('error', 'Отчет еще не готов. Заявка находится на модерации.');
        }

        // Получаем заявку из БД reports по request_number (как это делает админ)
        $externalRequest = \App\Models\ExternalRequest::with([
            'items' => function ($query) {
                $query->orderBy('position_number');
            },
            'items.offers' => function ($query) {
                $query->whereIn('status', ['received', 'processed'])
                      ->whereNotNull('price_per_unit')
                      ->orderByRaw('CASE WHEN currency = "RUB" THEN price_per_unit ELSE price_per_unit * 100 END')
                      ->orderBy('price_per_unit', 'asc');
            },
            'items.offers.supplier'
        ])->where('request_number', $request->request_number)->first();

        if (!$externalRequest) {
            return redirect()->route('cabinet.my.requests.show', $id)
                ->with('error', 'Отчет не найден. Возможно, заявка еще обрабатывается.');
        }

        return view('requests.report', compact('externalRequest', 'request'));
    }
}
