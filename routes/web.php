<?php

use App\Http\Controllers\LandingController;
use App\Http\Controllers\CabinetController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Api\Admin\TaxonomyController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Лендинг (публичные страницы)
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::post('/demo-request', [LandingController::class, 'demoRequest'])->name('demo.request');
Route::post('/api/check-email', [LandingController::class, 'checkEmail'])->name('api.check-email');
Route::get('/set-password/{token}', [LandingController::class, 'showSetPassword'])->name('set-password');
Route::post('/set-password', [LandingController::class, 'storePassword'])->name('set-password.store');
Route::get('/privacy', [LandingController::class, 'privacy'])->name('privacy');
Route::get('/terms', [LandingController::class, 'terms'])->name('terms');
Route::get('/contract', [LandingController::class, 'contract'])->name('contract');
Route::get('/pricing', [LandingController::class, 'pricing'])->name('pricing');
Route::get('/why-it-works', [LandingController::class, 'whyItWorks'])->name('why-it-works');
Route::get('/faq', [LandingController::class, 'faq'])->name('faq');

// Публичный каталог
Route::get('/catalog', [\App\Http\Controllers\CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalog/{id}', [\App\Http\Controllers\CatalogController::class, 'show'])->name('catalog.show');

// Личный кабинет (требует авторизации)
Route::middleware(['auth', 'verified'])->prefix('cabinet')->name('cabinet.')->group(function () {

    // Дашборд
    Route::get('/', [CabinetController::class, 'dashboard'])->name('dashboard');

    // Заявки
    Route::get('/requests', [CabinetController::class, 'requests'])->name('requests');
    Route::get('/requests/create', [CabinetController::class, 'createRequestForm'])->name('requests.create');
    Route::post('/requests', [CabinetController::class, 'createRequest'])->name('requests.store');
    Route::get('/requests/{request}', [CabinetController::class, 'showRequest'])->name('requests.show');

    // Отчёты
    Route::get('/reports', [ReportController::class, 'index'])->name('reports');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');

    // Поставщики
    Route::get('/suppliers', [CabinetController::class, 'suppliers'])->name('suppliers');

    // Настройки профиля
    Route::get('/settings', [CabinetController::class, 'settings'])->name('settings');
    Route::put('/settings', [CabinetController::class, 'updateSettings'])->name('settings.update');

    // Тарифы
    Route::get('/tariff', [\App\Http\Controllers\TariffController::class, 'index'])->name('tariff.index');
    Route::get('/tariff/transactions', [\App\Http\Controllers\TariffController::class, 'transactions'])->name('tariff.transactions');
    Route::get('/tariff/limits-usage', [\App\Http\Controllers\TariffController::class, 'limitsUsage'])->name('tariff.limits-usage');
    Route::post('/tariff/switch', [\App\Http\Controllers\TariffController::class, 'switch'])->name('tariff.switch');
    Route::post('/tariff/apply-promo-code', [\App\Http\Controllers\TariffController::class, 'applyPromoCode'])->name('tariff.apply-promo-code');

    // Мониторинг позиций
    Route::get('/items', [\App\Http\Controllers\Cabinet\ItemController::class, 'index'])->name('items.index');
    Route::get('/items/{item}', [\App\Http\Controllers\Cabinet\ItemController::class, 'show'])->name('items.show');
    Route::post('/items/{item}/purchase', [\App\Http\Controllers\Cabinet\ItemController::class, 'purchase'])->name('items.purchase');

    // Создание заявок пользователем
    Route::prefix('my')->name('my.')->group(function () {
        Route::get('/requests', [\App\Http\Controllers\UserRequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/create', [\App\Http\Controllers\UserRequestController::class, 'create'])->name('requests.create');
        Route::post('/requests/parse', [\App\Http\Controllers\UserRequestController::class, 'parse'])->name('requests.parse');
        Route::post('/requests', [\App\Http\Controllers\UserRequestController::class, 'store'])->name('requests.store');
        Route::get('/requests/balance', [\App\Http\Controllers\UserRequestController::class, 'checkBalance'])->name('requests.balance');
        Route::get('/requests/{id}', [\App\Http\Controllers\UserRequestController::class, 'show'])->name('requests.show');
        Route::get('/requests/{id}/report', [\App\Http\Controllers\UserRequestController::class, 'showReport'])->name('requests.report');
        Route::post('/requests/{id}/generate-pdf', [\App\Http\Controllers\UserRequestController::class, 'generatePdfReport'])->name('requests.generate-pdf');
        Route::get('/requests/{id}/download-pdf', [\App\Http\Controllers\UserRequestController::class, 'downloadPdfReport'])->name('requests.download-pdf');
        Route::get('/requests/{id}/questions', [\App\Http\Controllers\Cabinet\QuestionController::class, 'requestQuestions'])->name('requests.questions');
    });

    // Вопросы пользователя
    Route::prefix('questions')->name('questions.')->group(function () {
        Route::post('/{id}/answer', [\App\Http\Controllers\Cabinet\QuestionController::class, 'answer'])->name('answer');
    });

    // Счета на оплату
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::post('/request', [\App\Http\Controllers\Cabinet\InvoiceController::class, 'request'])->name('request');
        Route::get('/', [\App\Http\Controllers\Cabinet\InvoiceController::class, 'index'])->name('index');
        Route::get('/{id}', [\App\Http\Controllers\Cabinet\InvoiceController::class, 'show'])->name('show');
        Route::get('/{id}/download', [\App\Http\Controllers\Cabinet\InvoiceController::class, 'download'])->name('download');
    });
});

// Тестовая страница проверки прав (удалить после отладки)
Route::get('/test-auth', function() {
    $user = Auth::user();
    if (!$user) {
        return 'Не авторизован. <a href="/login">Войти</a>';
    }

    $html = '<h1>Проверка авторизации</h1>';
    $html .= '<p><strong>Email:</strong> ' . $user->email . '</p>';
    $html .= '<p><strong>Имя:</strong> ' . $user->name . '</p>';
    $html .= '<p><strong>ID:</strong> ' . $user->id . '</p>';
    $html .= '<p><strong>Email подтвержден:</strong> ' . ($user->email_verified_at ? '✓ Да' : '✗ Нет') . '</p>';
    $html .= '<p><strong>Администратор:</strong> ' . ($user->is_admin ? '✓ Да' : '✗ Нет') . '</p>';

    $canAccess = $user->is_admin && $user->email_verified_at;
    $html .= '<hr>';
    $html .= '<p><strong>Доступ к /manage/*:</strong> ' . ($canAccess ? '✓ Разрешен' : '✗ Запрещен') . '</p>';

    if (!$canAccess) {
        $html .= '<h2>Причины отказа:</h2><ul>';
        if (!$user->is_admin) {
            $html .= '<li>Не является администратором (is_admin = 0)</li>';
        }
        if (!$user->email_verified_at) {
            $html .= '<li>Email не подтвержден</li>';
        }
        $html .= '</ul>';
    }

    $html .= '<hr>';
    $html .= '<p><a href="/manage/manage-requests">Попробовать открыть /manage/manage-requests</a></p>';

    return $html;
});

// Публичный роут для отписки от рассылки
Route::get('/unsubscribe/{recipient}', [\App\Http\Controllers\Admin\CampaignController::class, 'unsubscribe'])->name('campaign.unsubscribe');

// Управление демо-заявками (требует is_admin) - переименовано из /admin в /manage чтобы не конфликтовать с Filament
Route::middleware(['auth', 'verified', 'admin'])->prefix('manage')->name('admin.')->group(function () {
    // Редирект с корневого пути /manage на управление заявками
    Route::get('/', function () {
        return redirect()->route('admin.manage.requests.index');
    });

    Route::get('/demo-requests', [\App\Http\Controllers\Admin\DemoRequestController::class, 'index'])->name('demo-requests.index');
    Route::get('/demo-requests/{demoRequest}', [\App\Http\Controllers\Admin\DemoRequestController::class, 'show'])->name('demo-requests.show');
    Route::post('/demo-requests/{demoRequest}/approve', [\App\Http\Controllers\Admin\DemoRequestController::class, 'approve'])->name('demo-requests.approve');
    Route::post('/demo-requests/{demoRequest}/reject', [\App\Http\Controllers\Admin\DemoRequestController::class, 'reject'])->name('demo-requests.reject');
    Route::post('/demo-requests/{demoRequest}/add-note', [\App\Http\Controllers\Admin\DemoRequestController::class, 'addNote'])->name('demo-requests.add-note');
    Route::patch('/demo-requests/{demoRequest}/status', [\App\Http\Controllers\Admin\DemoRequestController::class, 'updateStatus'])->name('demo-requests.update-status');

    // Мониторинг позиций (админ)
    Route::get('/items', [\App\Http\Controllers\Admin\ExternalRequestController::class, 'items'])->name('items.index');
    Route::get('/items/{item}', [\App\Http\Controllers\Admin\ExternalRequestController::class, 'itemShow'])->name('items.show');

    // Управление пользователями
    Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/invoices', [\App\Http\Controllers\Admin\UserController::class, 'invoices'])->name('users.invoices');
    Route::get('/users/{user}/acts', [\App\Http\Controllers\Admin\ActController::class, 'userActs'])->name('users.acts');
    Route::post('/users/{user}/balance', [\App\Http\Controllers\Admin\UserController::class, 'updateBalance'])->name('users.balance');

    // Настройки системы
    Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');

    // Промокоды
    Route::get('/promo-codes', [\App\Http\Controllers\Admin\PromoCodeController::class, 'index'])->name('promo-codes.index');
    Route::get('/promo-codes/create', [\App\Http\Controllers\Admin\PromoCodeController::class, 'create'])->name('promo-codes.create');
    Route::post('/promo-codes', [\App\Http\Controllers\Admin\PromoCodeController::class, 'store'])->name('promo-codes.store');
    Route::delete('/promo-codes/{promoCode}', [\App\Http\Controllers\Admin\PromoCodeController::class, 'destroy'])->name('promo-codes.destroy');
    Route::get('/promo-codes/export', [\App\Http\Controllers\Admin\PromoCodeController::class, 'export'])->name('promo-codes.export');

    // Рассылки
    Route::get('/campaigns', [\App\Http\Controllers\Admin\CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/create', [\App\Http\Controllers\Admin\CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('/campaigns', [\App\Http\Controllers\Admin\CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/campaigns/{campaign}/edit', [\App\Http\Controllers\Admin\CampaignController::class, 'edit'])->name('campaigns.edit');
    Route::put('/campaigns/{campaign}', [\App\Http\Controllers\Admin\CampaignController::class, 'update'])->name('campaigns.update');
    Route::get('/campaigns/{campaign}/upload', [\App\Http\Controllers\Admin\CampaignController::class, 'upload'])->name('campaigns.upload');
    Route::post('/campaigns/{campaign}/upload', [\App\Http\Controllers\Admin\CampaignController::class, 'processUpload'])->name('campaigns.process-upload');
    Route::get('/campaigns/{campaign}/mapping', [\App\Http\Controllers\Admin\CampaignController::class, 'mapping'])->name('campaigns.mapping');
    Route::post('/campaigns/{campaign}/mapping', [\App\Http\Controllers\Admin\CampaignController::class, 'saveMapping'])->name('campaigns.save-mapping');
    Route::get('/campaigns/{campaign}', [\App\Http\Controllers\Admin\CampaignController::class, 'show'])->name('campaigns.show');
    Route::get('/campaigns/{campaign}/progress', [\App\Http\Controllers\Admin\CampaignController::class, 'progress'])->name('campaigns.progress');
    Route::post('/campaigns/{campaign}/send-test', [\App\Http\Controllers\Admin\CampaignController::class, 'sendTest'])->name('campaigns.send-test');
    Route::post('/campaigns/{campaign}/start', [\App\Http\Controllers\Admin\CampaignController::class, 'start'])->name('campaigns.start');
    Route::delete('/campaigns/{campaign}', [\App\Http\Controllers\Admin\CampaignController::class, 'destroy'])->name('campaigns.destroy');

    // Диагностика
    Route::get('/diagnostics', [\App\Http\Controllers\Admin\DiagnosticsController::class, 'index'])->name('diagnostics.index');
    Route::post('/diagnostics/test-parse', [\App\Http\Controllers\Admin\DiagnosticsController::class, 'testParse'])->name('diagnostics.test-parse');
    Route::get('/diagnostics/test-connection', [\App\Http\Controllers\Admin\DiagnosticsController::class, 'testConnection'])->name('diagnostics.test-connection');

    // Управление Sender пользователей
    Route::get('/sender/test-connection', [\App\Http\Controllers\Admin\UserSenderController::class, 'testConnection'])->name('sender.test');
    Route::prefix('users/{user}/sender')->name('users.sender.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\UserSenderController::class, 'show'])->name('show');
        Route::get('/create', [\App\Http\Controllers\Admin\UserSenderController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\UserSenderController::class, 'store'])->name('store');
        Route::get('/edit', [\App\Http\Controllers\Admin\UserSenderController::class, 'edit'])->name('edit');
        Route::put('/', [\App\Http\Controllers\Admin\UserSenderController::class, 'update'])->name('update');
        Route::delete('/', [\App\Http\Controllers\Admin\UserSenderController::class, 'deactivate'])->name('deactivate');
    });

    // Модерация заявок пользователей
    Route::prefix('requests')->name('requests.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\RequestController::class, 'index'])->name('index');
        Route::get('/{id}', [\App\Http\Controllers\Admin\RequestController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Admin\RequestController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Admin\RequestController::class, 'update'])->name('update');
        Route::post('/{id}/approve', [\App\Http\Controllers\Admin\RequestController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [\App\Http\Controllers\Admin\RequestController::class, 'reject'])->name('reject');
        Route::get('/test-connection', [\App\Http\Controllers\Admin\RequestController::class, 'testConnection'])->name('test-connection');
    });

    // Управление заявками через n8n (анонимные + именные)
    Route::prefix('manage-requests')->name('manage.requests.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ManageRequestController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Admin\ManageRequestController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\ManageRequestController::class, 'store'])->name('store');
        Route::get('/{id}', [\App\Http\Controllers\Admin\ManageRequestController::class, 'show'])->name('show');
        Route::get('/{id}/report', [\App\Http\Controllers\Admin\ManageRequestController::class, 'showReport'])->name('report');
        Route::post('/{id}/generate-pdf', [\App\Http\Controllers\Admin\ManageRequestController::class, 'generatePdfReport'])->name('generate-pdf');
        Route::get('/{id}/download-pdf', [\App\Http\Controllers\Admin\ManageRequestController::class, 'downloadPdfReport'])->name('download-pdf');
        Route::get('/{id}/questions', [\App\Http\Controllers\Admin\QuestionController::class, 'requestQuestions'])->name('questions');
        Route::get('/{id}/edit', [\App\Http\Controllers\Admin\ManageRequestController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Admin\ManageRequestController::class, 'update'])->name('update');
        Route::post('/{id}/cancel', [\App\Http\Controllers\Admin\ManageRequestController::class, 'cancel'])->name('cancel');
        Route::post('/parse-text', [\App\Http\Controllers\Admin\ManageRequestController::class, 'parseText'])->name('parse-text');
    });

    // Вопросы от поставщиков
    Route::prefix('questions')->name('questions.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\QuestionController::class, 'index'])->name('index');

        // Консолидированные вопросы (должны быть ПЕРЕД роутами с параметром {id})
        Route::get('/consolidated', [\App\Http\Controllers\Admin\ConsolidatedQuestionController::class, 'index'])->name('consolidated');
        Route::post('/consolidated/answer', [\App\Http\Controllers\Admin\ConsolidatedQuestionController::class, 'answer'])->name('consolidated.answer');

        // Роуты с параметром {id} должны быть в конце
        Route::post('/{id}/answer', [\App\Http\Controllers\Admin\QuestionController::class, 'answer'])->name('answer');
        Route::post('/{id}/skip', [\App\Http\Controllers\Admin\QuestionController::class, 'skip'])->name('skip');
    });

    // Настройки системы
    Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');

    // Управление тарифными планами
    Route::prefix('tariff-plans')->name('tariff-plans.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\TariffPlanController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Admin\TariffPlanController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\TariffPlanController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [\App\Http\Controllers\Admin\TariffPlanController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Admin\TariffPlanController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\TariffPlanController::class, 'destroy'])->name('destroy');
    });

    // Модерация таксономии (классификация товаров)
    Route::prefix('taxonomy')->name('taxonomy.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\TaxonomyModerationController::class, 'index'])->name('index');
        Route::get('/domains', [\App\Http\Controllers\Admin\TaxonomyModerationController::class, 'domains'])->name('domains');
        Route::get('/product-types', [\App\Http\Controllers\Admin\TaxonomyModerationController::class, 'productTypes'])->name('product-types');
    });

    // Биллинг
    Route::prefix('billing')->name('billing.')->group(function () {
        // Счета
        Route::get('/invoices', [\App\Http\Controllers\Admin\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{id}', [\App\Http\Controllers\Admin\InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/invoices/{id}/download', [\App\Http\Controllers\Admin\InvoiceController::class, 'download'])->name('invoices.download');
        Route::get('/invoices/{id}/download-act', [\App\Http\Controllers\Admin\InvoiceController::class, 'downloadAct'])->name('invoices.download-act');
        Route::post('/invoices/{id}/mark-as-paid', [\App\Http\Controllers\Admin\InvoiceController::class, 'markAsPaid'])->name('invoices.mark-as-paid');
        Route::post('/invoices/{id}/mark-as-unpaid', [\App\Http\Controllers\Admin\InvoiceController::class, 'markAsUnpaid'])->name('invoices.mark-as-unpaid');
        Route::post('/invoices/{id}/cancel', [\App\Http\Controllers\Admin\InvoiceController::class, 'cancel'])->name('invoices.cancel');

        // Акты
        Route::get('/acts', [\App\Http\Controllers\Admin\ActController::class, 'index'])->name('acts.index');
        Route::get('/acts/create', [\App\Http\Controllers\Admin\ActController::class, 'create'])->name('acts.create');
        Route::post('/acts', [\App\Http\Controllers\Admin\ActController::class, 'store'])->name('acts.store');
        Route::get('/acts/{id}', [\App\Http\Controllers\Admin\ActController::class, 'show'])->name('acts.show');
        Route::get('/acts/{id}/download', [\App\Http\Controllers\Admin\ActController::class, 'download'])->name('acts.download');

        // Реквизиты
        Route::get('/settings', [\App\Http\Controllers\Admin\BillingSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [\App\Http\Controllers\Admin\BillingSettingsController::class, 'update'])->name('settings.update');
    });

    // API для модерации таксономии
    Route::prefix('api/taxonomy')->name('api.taxonomy.')->group(function () {
        // Модерация
        Route::get('/pending', [TaxonomyController::class, 'pending'])->name('pending');
        Route::get('/stats', [TaxonomyController::class, 'stats'])->name('stats');

        // Домены (Application Domains)
        Route::get('/domains', [TaxonomyController::class, 'domains'])->name('domains.index');
        Route::get('/domains/{id}', [TaxonomyController::class, 'showDomain'])->name('domains.show');
        Route::put('/domains/{id}', [TaxonomyController::class, 'updateDomain'])->name('domains.update');
        Route::post('/domains/{id}/approve', [TaxonomyController::class, 'approveDomain'])->name('domains.approve');
        Route::post('/domains/{id}/reject', [TaxonomyController::class, 'rejectDomain'])->name('domains.reject');

        // Типы товаров (Product Types)
        Route::get('/product-types', [TaxonomyController::class, 'productTypes'])->name('product-types.index');
        Route::get('/product-types/{id}', [TaxonomyController::class, 'showProductType'])->name('product-types.show');
        Route::put('/product-types/{id}', [TaxonomyController::class, 'updateProductType'])->name('product-types.update');
        Route::post('/product-types/{id}/approve', [TaxonomyController::class, 'approveProductType'])->name('product-types.approve');
        Route::post('/product-types/{id}/reject', [TaxonomyController::class, 'rejectProductType'])->name('product-types.reject');
    });
});

// Filament админка доступна по /admin

// Авторизация (Laravel Breeze добавит свои роуты)
require __DIR__.'/auth.php';
