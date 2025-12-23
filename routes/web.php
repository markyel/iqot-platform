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
Route::get('/privacy', [LandingController::class, 'privacy'])->name('privacy');

// Личный кабинет (требует авторизации)
Route::middleware(['auth', 'verified'])->prefix('cabinet')->name('cabinet.')->group(function () {
    
    // Дашборд
    Route::get('/', [CabinetController::class, 'dashboard'])->name('dashboard');
    
    // Заявки
    Route::get('/requests', [CabinetController::class, 'requests'])->name('requests');
    Route::get('/requests/{request}', [CabinetController::class, 'showRequest'])->name('requests.show');
    Route::post('/requests', [CabinetController::class, 'createRequest'])->name('requests.create');
    
    // Отчёты
    Route::get('/reports', [ReportController::class, 'index'])->name('reports');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');
    
    // Поставщики
    Route::get('/suppliers', [CabinetController::class, 'suppliers'])->name('suppliers');
    
    // Настройки профиля
    Route::get('/settings', [CabinetController::class, 'settings'])->name('settings');
    Route::put('/settings', [CabinetController::class, 'updateSettings'])->name('settings.update');
});

// Авторизация (Laravel Breeze добавит свои роуты)
require __DIR__.'/auth.php';
