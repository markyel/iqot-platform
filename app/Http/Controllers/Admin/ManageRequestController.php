<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientOrganization;
use App\Models\Category;
use App\Models\ProductType;
use App\Models\ApplicationDomain;
use App\Services\N8nRequestService;
use App\Services\N8nParseService;
use App\Services\N8nQuestionsService;
use Illuminate\Http\Request;

class ManageRequestController extends Controller
{
    private N8nRequestService $n8nService;
    private N8nParseService $parseService;
    private N8nQuestionsService $questionsService;

    public function __construct(N8nRequestService $n8nService, N8nParseService $parseService, N8nQuestionsService $questionsService)
    {
        $this->n8nService = $n8nService;
        $this->parseService = $parseService;
        $this->questionsService = $questionsService;
    }

    /**
     * Список заявок из n8n
     */
    public function index(Request $request)
    {
        $filters = [];
        $sort = ['created_at' => 'desc'];
        $pagination = [
            'page' => $request->get('page', 1),
            'per_page' => 20
        ];

        // Фильтры
        if ($request->filled('status')) {
            $filters['status'] = $request->status;
        }

        if ($request->filled('type')) {
            $filters['is_customer_request'] = $request->type === 'customer';
        }

        if ($request->filled('search')) {
            $filters['search'] = $request->search;
        }

        if ($request->filled('date_from')) {
            $filters['date_from'] = $request->date_from;
        }

        if ($request->filled('date_to')) {
            $filters['date_to'] = $request->date_to;
        }

        if ($request->filled('has_questions') && $request->has_questions === '1') {
            $filters['has_questions'] = true;
        }

        // Получаем заявки из n8n
        $result = $this->n8nService->listRequests($filters, $sort, $pagination);

        // n8n возвращает массив с одним элементом
        if (is_array($result) && isset($result[0])) {
            $result = $result[0];
        }

        if (!($result['success'] ?? false)) {
            return view('admin.manage.requests.index', [
                'requests' => [],
                'total' => 0,
                'currentPage' => 1,
                'lastPage' => 1,
                'error' => $result['message'] ?? 'Ошибка получения списка заявок'
            ]);
        }

        $requests = $result['requests'] ?? [];

        // n8n возвращает pagination внутри объекта pagination, а не напрямую total
        $paginationInfo = $result['pagination'] ?? [];
        $total = $paginationInfo['total'] ?? 0;
        $currentPage = $paginationInfo['page'] ?? $pagination['page'];
        $perPage = $paginationInfo['per_page'] ?? $pagination['per_page'];
        $lastPage = $paginationInfo['total_pages'] ?? ceil($total / $perPage);

        // Получаем количество неотвеченных вопросов для каждой заявки
        $questionsCounts = [];
        if (!empty($requests)) {
            $requestIds = array_column($requests, 'id');
            $summaryResult = $this->questionsService->getQuestionsSummary($requestIds);

            // Логируем результат для отладки
            \Log::info('Questions summary result', [
                'request_ids' => $requestIds,
                'result' => $summaryResult
            ]);

            // n8n может возвращать массив с одним элементом
            if (is_array($summaryResult) && isset($summaryResult[0])) {
                $summaryResult = $summaryResult[0];
            }

            if (($summaryResult['success'] ?? false) && isset($summaryResult['summary'])) {
                // summary может быть массивом или объектом с ключами request_id
                $summary = $summaryResult['summary'];

                foreach ($summary as $requestId => $item) {
                    // $item может быть массивом с данными
                    if (is_array($item)) {
                        // API возвращает total_questions и другие поля
                        $totalQuestions = $item['total_questions'] ?? 0;
                        $unansweredCount = $item['unanswered_questions'] ?? 0;

                        // Показываем количество всех вопросов (не только неотвеченных)
                        if ($totalQuestions > 0) {
                            $questionsCounts[$requestId] = $totalQuestions;
                        }
                    }
                }
            }

            \Log::info('Questions counts final', ['counts' => $questionsCounts]);
        }

        return view('admin.manage.requests.index', compact('requests', 'total', 'currentPage', 'lastPage', 'questionsCounts'));
    }

    /**
     * Страница создания заявки
     */
    public function create()
    {
        $organizations = ClientOrganization::getActiveForSelect();
        $categories = Category::getActiveForSelect();
        $productTypes = ProductType::getActiveForSelect();
        $applicationDomains = ApplicationDomain::getActiveForSelect();

        $statuses = [
            'draft' => 'Черновик',
            'new' => 'В работу'
        ];

        return view('admin.manage.requests.create', compact(
            'organizations',
            'categories',
            'productTypes',
            'applicationDomains',
            'statuses'
        ));
    }

    /**
     * Сохранение новой заявки
     */
    public function store(Request $request)
    {
        $isCustomerRequest = $request->boolean('is_customer_request');

        $validated = $request->validate([
            'status' => 'required|in:draft,new',
            'is_customer_request' => 'nullable',
            'client_organization_id' => 'nullable|integer',
            'customer_company' => ($isCustomerRequest ? 'required' : 'nullable') . '|string|max:255',
            'customer_contact_person' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:500',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit' => 'required|string|max:50',
            'items.*.brand' => 'nullable|string|max:100',
            'items.*.article' => 'nullable|string|max:100',
            'items.*.category' => 'required|string|max:100',
            'items.*.product_type_id' => 'nullable|integer',
            'items.*.domain_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:2000',
        ]);

        // Преобразуем типы данных для items
        $items = array_map(function($item) {
            return [
                'name' => $item['name'],
                'quantity' => (int) $item['quantity'],
                'unit' => $item['unit'],
                'brand' => $item['brand'] ?? null,
                'article' => $item['article'] ?? null,
                'category' => $item['category'],
                'product_type_id' => !empty($item['product_type_id']) ? (int) $item['product_type_id'] : null,
                'domain_id' => !empty($item['domain_id']) ? (int) $item['domain_id'] : null,
            ];
        }, $validated['items']);

        $data = [
            'status' => $validated['status'],
            'is_customer_request' => $isCustomerRequest,
            'items' => $items,
            'notes' => $validated['notes'] ?? null,
        ];

        if ($isCustomerRequest) {
            $data['client_organization_id'] = $validated['client_organization_id'] ?? null;
            $data['customer_company'] = $validated['customer_company'];
            $data['customer_contact_person'] = $validated['customer_contact_person'] ?? null;
            $data['customer_email'] = $validated['customer_email'] ?? null;
            $data['customer_phone'] = $validated['customer_phone'] ?? null;
        }

        $result = $this->n8nService->createRequest($data);

        // n8n возвращает массив с одним элементом: [{"success": true, "request": {...}, "items": [...}}]
        if (is_array($result) && isset($result[0])) {
            $result = $result[0];
        }

        if (($result['success'] ?? false) && isset($result['request']['id'])) {
            return redirect()
                ->route('admin.manage.requests.show', $result['request']['id'])
                ->with('success', 'Заявка успешно создана');
        }

        return back()
            ->withInput()
            ->with('error', $result['message'] ?? 'Ошибка при создании заявки');
    }

    /**
     * Просмотр заявки
     */
    public function show($id)
    {
        $result = $this->n8nService->getRequest($id);

        // n8n возвращает массив с одним элементом
        if (is_array($result) && isset($result[0])) {
            $result = $result[0];
        }

        if (!($result['success'] ?? false) || !isset($result['request'])) {
            return redirect()
                ->route('admin.manage.requests.index')
                ->with('error', $result['message'] ?? 'Заявка не найдена');
        }

        $request = $result['request'];
        $request['items'] = $result['items'] ?? [];

        return view('admin.manage.requests.show', compact('request'));
    }

    /**
     * Страница редактирования заявки
     */
    public function edit($id)
    {
        $result = $this->n8nService->getRequest($id);

        // n8n возвращает массив с одним элементом
        if (is_array($result) && isset($result[0])) {
            $result = $result[0];
        }

        if (!($result['success'] ?? false) || !isset($result['request'])) {
            return redirect()
                ->route('admin.manage.requests.index')
                ->with('error', $result['message'] ?? 'Заявка не найдена');
        }

        $request = $result['request'];
        $request['items'] = $result['items'] ?? [];

        // Проверка что заявку можно редактировать
        if (!in_array($request['status'], ['draft', 'new'])) {
            return redirect()
                ->route('admin.manage.requests.show', $id)
                ->with('error', 'Редактирование доступно только для заявок в статусе "Черновик" или "В работу"');
        }

        $organizations = ClientOrganization::getActiveForSelect();
        $categories = Category::getActiveForSelect();
        $productTypes = ProductType::getActiveForSelect();
        $applicationDomains = ApplicationDomain::getActiveForSelect();

        $statuses = [
            'draft' => 'Черновик',
            'new' => 'В работу'
        ];

        return view('admin.manage.requests.edit', compact(
            'request',
            'organizations',
            'categories',
            'productTypes',
            'applicationDomains',
            'statuses'
        ));
    }

    /**
     * Обновление заявки
     */
    public function update(Request $request, $id)
    {
        $isCustomerRequest = $request->boolean('is_customer_request');

        // API update_request не поддерживает редактирование items
        // Валидируем только поля, которые можно изменить через API: status, title, notes, customer_company, customer_contact_person, customer_email, customer_phone
        $validated = $request->validate([
            'status' => 'required|in:draft,new',
            'is_customer_request' => 'nullable',
            'customer_company' => ($isCustomerRequest ? 'required' : 'nullable') . '|string|max:255',
            'customer_contact_person' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
        ]);
        $data = [
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ];

        if ($isCustomerRequest) {
            $data['customer_company'] = $validated['customer_company'];
            $data['customer_contact_person'] = $validated['customer_contact_person'] ?? null;
            $data['customer_email'] = $validated['customer_email'] ?? null;
            $data['customer_phone'] = $validated['customer_phone'] ?? null;
        }

        $result = $this->n8nService->updateRequest($id, $data);

        // n8n возвращает массив с одним элементом
        if (is_array($result) && isset($result[0])) {
            $result = $result[0];
        }

        if ($result['success'] ?? false) {
            return redirect()
                ->route('admin.manage.requests.show', $id)
                ->with('success', 'Заявка успешно обновлена');
        }

        return back()
            ->withInput()
            ->with('error', $result['message'] ?? 'Ошибка при обновлении заявки');
    }

    /**
     * Отмена заявки
     */
    public function cancel(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500'
        ]);

        $result = $this->n8nService->cancelRequest($id, $validated['reason'] ?? null);

        // n8n возвращает массив с одним элементом
        if (is_array($result) && isset($result[0])) {
            $result = $result[0];
        }

        if ($result['success'] ?? false) {
            return redirect()
                ->route('admin.manage.requests.show', $id)
                ->with('success', 'Заявка отменена');
        }

        return back()->with('error', $result['message'] ?? 'Ошибка при отмене заявки');
    }

    /**
     * Просмотр отчета по заявке (из БД reports)
     */
    public function showReport($id)
    {
        // Получаем заявку из n8n
        $result = $this->n8nService->getRequest($id);

        // n8n возвращает массив с одним элементом
        if (is_array($result) && isset($result[0])) {
            $result = $result[0];
        }

        if (!($result['success'] ?? false) || !isset($result['request'])) {
            return redirect()
                ->route('admin.manage.requests.index')
                ->with('error', 'Заявка не найдена');
        }

        $requestNumber = $result['request']['request_number'];

        // Получаем детальную информацию из БД reports
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
        ])->where('request_number', $requestNumber)->first();

        if (!$externalRequest) {
            return redirect()
                ->route('admin.manage.requests.show', $id)
                ->with('error', 'Отчет не найден. Заявка ещё не обработана в системе.');
        }

        return view('admin.manage.requests.report', compact('externalRequest'));
    }

    /**
     * AJAX: Парсинг текста заявки
     */
    public function parseText(Request $request)
    {
        $validated = $request->validate([
            'text' => 'required|string|min:3|max:10000'
        ]);

        $result = $this->parseService->parseRequest($validated['text']);

        return response()->json($result);
    }
}
