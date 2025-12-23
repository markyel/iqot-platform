<?php

use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\RequestController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Эти роуты используются для интеграции с n8n и внешними системами.
| Все роуты защищены через Laravel Sanctum токены.
|
*/

// Публичные вебхуки от n8n (с проверкой подписи)
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    // n8n отправляет сюда обновления по заявкам
    Route::post('/request-update', [WebhookController::class, 'requestUpdate'])->name('request.update');
    
    // n8n отправляет сюда новые КП от поставщиков
    Route::post('/offer-received', [WebhookController::class, 'offerReceived'])->name('offer.received');
    
    // n8n отправляет сюда готовые отчёты
    Route::post('/report-ready', [WebhookController::class, 'reportReady'])->name('report.ready');
    
    // Статус email-рассылки
    Route::post('/email-status', [WebhookController::class, 'emailStatus'])->name('email.status');
});

// Защищённые API роуты (требуют Sanctum токен)
Route::middleware('auth:sanctum')->group(function () {
    
    // Заявки
    Route::apiResource('requests', RequestController::class);
    Route::post('requests/{request}/resend', [RequestController::class, 'resend'])->name('requests.resend');
    Route::post('requests/{request}/cancel', [RequestController::class, 'cancel'])->name('requests.cancel');
    
    // Поставщики
    Route::apiResource('suppliers', SupplierController::class);
    Route::get('suppliers/search', [SupplierController::class, 'search'])->name('suppliers.search');
    
    // Отчёты
    Route::apiResource('reports', ReportController::class)->only(['index', 'show']);
    Route::get('reports/{report}/pdf', [ReportController::class, 'downloadPdf'])->name('reports.pdf');
    Route::get('reports/{report}/excel', [ReportController::class, 'downloadExcel'])->name('reports.excel');
    
    // Статистика для дашборда
    Route::get('stats/overview', [RequestController::class, 'statsOverview'])->name('stats.overview');
    Route::get('stats/requests', [RequestController::class, 'statsRequests'])->name('stats.requests');
    Route::get('stats/suppliers', [SupplierController::class, 'stats'])->name('stats.suppliers');
});

// Внутренний API для n8n (с API ключом)
Route::middleware('api.key')->prefix('internal')->name('internal.')->group(function () {
    // n8n запрашивает данные для обработки
    Route::get('pending-requests', [RequestController::class, 'pending'])->name('requests.pending');
    Route::get('suppliers-for-request/{request}', [SupplierController::class, 'forRequest'])->name('suppliers.for-request');
    
    // n8n обновляет статусы
    Route::patch('requests/{request}/status', [RequestController::class, 'updateStatus'])->name('requests.status');
    Route::post('offers', [RequestController::class, 'storeOffer'])->name('offers.store');
});
