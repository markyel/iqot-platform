<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Расписание задач
Schedule::command('queue:work --stop-when-empty')->everyMinute()->withoutOverlapping();

// Разморозка средств за невыполненные заявки (меньше 3 ответов за неделю)
Schedule::command('balance:release-expired')->daily();

// Продление тарифов пользователей
Schedule::command('tariffs:renew')->daily();

// Синхронизация публичного каталога
Schedule::command('catalog:sync')->hourly();
