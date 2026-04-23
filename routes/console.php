<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Расписание задач
Schedule::command('queue:work --stop-when-empty')->everyMinute()->withoutOverlapping();

// Проверка и списание средств за выполненные позиции (каждый час)
Schedule::command('balance:check-completed-items')->hourly();

// Разморозка средств за невыполненные заявки (меньше 3 ответов за неделю)
Schedule::command('balance:release-expired')->daily();

// Продление тарифов пользователей
Schedule::command('tariffs:renew')->daily();

// Синхронизация публичного каталога
Schedule::command('catalog:sync')->hourly();

// Публичный API: обработка inbox (классификация) — каждые 5 минут.
Schedule::command('api:inbox:process')->everyFiveMinutes()->withoutOverlapping();

// Публичный API: оркестратор Discovery поставщиков — каждые 10 минут (§7).
Schedule::job(new \App\Jobs\Api\DiscoveryOrchestratorJob())->everyTenMinutes()->withoutOverlapping();

// Публичный API: перепроверка awaiting_suppliers — каждый час (§6.3).
Schedule::job(new \App\Jobs\Api\RecheckAwaitingSuppliersJob())->hourly()->withoutOverlapping();

// Публичный API: reconcile промоушена (heartbeat cross-DB) — каждые 5 минут (§6.5).
Schedule::job(new \App\Jobs\Api\ReconcilePromotionJob())->everyFiveMinutes()->withoutOverlapping();
