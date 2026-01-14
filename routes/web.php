<?php

use App\Http\Controllers\LandingController;
use App\Http\Controllers\CabinetController;
use App\Http\Controllers\ReportController;
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
Route::get('/pricing', [LandingController::class, 'pricing'])->name('pricing');

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
        Route::get('/requests/{id}/questions', [\App\Http\Controllers\Cabinet\QuestionController::class, 'requestQuestions'])->name('requests.questions');
    });

    // Вопросы пользователя
    Route::prefix('questions')->name('questions.')->group(function () {
        Route::post('/{id}/answer', [\App\Http\Controllers\Cabinet\QuestionController::class, 'answer'])->name('answer');
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
    Route::post('/users/{user}/balance', [\App\Http\Controllers\Admin\UserController::class, 'updateBalance'])->name('users.balance');

    // Настройки системы
    Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');

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
});

// Filament админка доступна по /admin

// Авторизация (Laravel Breeze добавит свои роуты)
require __DIR__.'/auth.php';
