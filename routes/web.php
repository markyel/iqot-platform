<?php

use App\Http\Controllers\LandingController;
use App\Http\Controllers\CabinetController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

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
});

// Управление демо-заявками (требует is_admin) - переименовано из /admin в /manage чтобы не конфликтовать с Filament
Route::middleware(['auth', 'verified', 'admin'])->prefix('manage')->name('admin.')->group(function () {
    Route::get('/demo-requests', [\App\Http\Controllers\Admin\DemoRequestController::class, 'index'])->name('demo-requests.index');
    Route::get('/demo-requests/{demoRequest}', [\App\Http\Controllers\Admin\DemoRequestController::class, 'show'])->name('demo-requests.show');
    Route::post('/demo-requests/{demoRequest}/approve', [\App\Http\Controllers\Admin\DemoRequestController::class, 'approve'])->name('demo-requests.approve');
    Route::post('/demo-requests/{demoRequest}/reject', [\App\Http\Controllers\Admin\DemoRequestController::class, 'reject'])->name('demo-requests.reject');
    Route::post('/demo-requests/{demoRequest}/add-note', [\App\Http\Controllers\Admin\DemoRequestController::class, 'addNote'])->name('demo-requests.add-note');
    Route::patch('/demo-requests/{demoRequest}/status', [\App\Http\Controllers\Admin\DemoRequestController::class, 'updateStatus'])->name('demo-requests.update-status');

    // Заявки из внешней базы
    Route::get('/requests', [\App\Http\Controllers\Admin\ExternalRequestController::class, 'index'])->name('external-requests.index');
    Route::get('/requests/{externalRequest}', [\App\Http\Controllers\Admin\ExternalRequestController::class, 'show'])->name('external-requests.show');

    // Мониторинг позиций (админ)
    Route::get('/items', [\App\Http\Controllers\Admin\ExternalRequestController::class, 'items'])->name('items.index');
    Route::get('/items/{item}', [\App\Http\Controllers\Admin\ExternalRequestController::class, 'itemShow'])->name('items.show');

    // Управление пользователями
    Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
    Route::post('/users/{user}/balance', [\App\Http\Controllers\Admin\UserController::class, 'updateBalance'])->name('users.balance');

    // Настройки системы
    Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');
});

// Filament админка доступна по /admin

// Авторизация (Laravel Breeze добавит свои роуты)
require __DIR__.'/auth.php';
