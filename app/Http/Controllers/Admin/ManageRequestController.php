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
use App\Services\N8nReportService;
use App\Models\Report;
use Illuminate\Http\Request;

class ManageRequestController extends Controller
{
    private N8nRequestService $n8nService;
    private N8nParseService $parseService;
    private N8nQuestionsService $questionsService;
    private N8nReportService $reportService;

    public function __construct(N8nRequestService $n8nService, N8nParseService $parseService, N8nQuestionsService $questionsService, N8nReportService $reportService)
    {
        $this->n8nService = $n8nService;
        $this->parseService = $parseService;
        $this->questionsService = $questionsService;
        $this->reportService = $reportService;
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

        // Фильтр по неотвеченным вопросам будет применен на стороне приложения после получения summary
        // т.к. API не поддерживает фильтр has_unanswered

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

            // n8n может возвращать массив с одним элементом
            if (is_array($summaryResult) && isset($summaryResult[0])) {
                $summaryResult = $summaryResult[0];
            }

            if (($summaryResult['success'] ?? false) && isset($summaryResult['summary'])) {
                // summary возвращается как объект с ключами request_id
                $summary = $summaryResult['summary'];

                foreach ($summary as $requestId => $item) {
                    if (is_array($item)) {
                        // Показываем только неотвеченные вопросы (ожидают ответа автора)
                        $unansweredCount = $item['unanswered_questions'] ?? 0;
                        if ($unansweredCount > 0) {
                            $questionsCounts[$requestId] = $unansweredCount;
                        }
                    }
                }

                // Если фильтр по неотвеченным вопросам активен,
                // фильтруем заявки на стороне приложения (т.к. API не поддерживает has_unanswered)
                if ($request->filled('has_questions') && $request->has_questions === '1') {
                    $requests = array_filter($requests, function($req) use ($questionsCounts) {
                        return isset($questionsCounts[$req['id']]);
                    });
                    // Пересчитываем total
                    $total = count($requests);
                    $lastPage = 1;
                }
            }
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

    /**
     * Генерация PDF отчета по заявке (для админа)
     */
    public function generatePdfReport($id)
    {
        // Получаем заявку из n8n
        $result = $this->n8nService->getRequest($id);

        // n8n возвращает массив с одним элементом
        if (is_array($result) && isset($result[0])) {
            $result = $result[0];
        }

        if (!($result['success'] ?? false) || !isset($result['request'])) {
            return back()->with('error', 'Заявка не найдена');
        }

        $requestData = $result['request'];

        // Вызываем API генерации отчета
        $reportResult = $this->reportService->generateReport(
            [$id],
            auth()->id(),
            [
                'include_supplier_profiles' => true,
                'include_price_comparison' => true,
            ]
        );

        if (!($reportResult['success'] ?? false)) {
            return back()->with('error', $reportResult['message'] ?? 'Ошибка при запуске генерации отчета.');
        }

        // Создаем запись о генерации отчета
        $reportCode = 'PDF-' . date('Ymd') . '-' . str_pad($reportResult['report_id'], 6, '0', STR_PAD_LEFT);

        Report::create([
            'id' => $reportResult['report_id'],
            'user_id' => auth()->id(),
            'code' => $reportCode,
            'title' => "PDF отчет по заявке {$requestData['request_number']}",
            'type' => 'single',
            'report_type' => 'request',
            'status' => 'generating',
            'callback_url' => route('api.webhooks.report-ready-pdf'),
        ]);

        return back()->with('success', 'Генерация PDF отчета запущена.');
    }

    /**
     * Скачать PDF отчет (для админа)
     */
    public function downloadPdfReport($id)
    {
        // Находим готовый отчет по request_id из n8n
        $report = Report::where('callback_url', route('api.webhooks.report-ready-pdf'))
            ->where('status', 'ready')
            ->whereNotNull('pdf_content')
            ->whereHas('request_id', function($query) use ($id) {
                // Здесь нужно найти связь с n8n request
            })
            ->first();

        if (!$report) {
            return back()->with('error', 'PDF отчет не найден или еще не готов.');
        }

        // Проверяем срок истечения
        if ($report->pdf_expires_at && $report->pdf_expires_at->isPast()) {
            return back()->with('error', 'Срок действия PDF истек. Запустите генерацию повторно.');
        }

        // Отдаем PDF (декодируем из base64)
        $pdfContent = base64_decode($report->pdf_content);
        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . basename($report->file_path) . '"');
    }
}
