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

// Рассылка: диспетчер очереди писем (замена n8n «Send Emails»).
// Рабочее окно — Пн–Пт 08:00–20:00 по Europe/Riga (как в n8n Within Work Hours).
Schedule::command('emails:dispatch-pending')
    ->everyMinute()
    ->timezone('Europe/Riga')
    ->weekdays()
    ->between('8:00', '20:00')
    ->withoutOverlapping();

// Приём почты: диспетчер опроса IMAP активных ящиков (замена n8n «Receive and
// Route Emails v3»). Ответы приходят в любое время — без рабочего окна. Защита
// от наложения: withoutOverlapping + Cache::lock на ящик внутри job.
Schedule::command('emails:receive-dispatch')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// AI-анализ ответов поставщиков (замена n8n «Process Email Conversations», раз в
// 30 мин). По умолчанию молчит, пока флаг EMAILS_ANALYZE_ENABLED=false —
// включать ТОЛЬКО после отключения n8n-воркфлоу (multi/questions не идемпотентны).
Schedule::command('emails:analyze-replies')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

// Триаж вопросов поставщиков (замена n8n «Process Supplier Questions», каждые
// 120 мин). По умолчанию молчит, пока флаг EMAILS_QUESTIONS_ENABLED=false —
// включать ТОЛЬКО после отключения n8n-воркфлоу (author_questions/
// question_consolidation/outgoing_replies не идемпотентны).
Schedule::command('emails:process-questions')
    ->everyTwoHours()
    ->withoutOverlapping();

// Отправка готовых ответов поставщикам (замена n8n «Send Outgoing Replies»).
// Рабочее окно — Пн–Пт 08:00–20:00 по Europe/Riga (как массовая рассылка). По
// умолчанию молчит, пока флаг EMAILS_REPLIES_ENABLED=false — включать ТОЛЬКО после
// отключения n8n-воркфлоу (иначе двойная отправка).
Schedule::command('emails:dispatch-replies')
    ->everyFifteenMinutes()
    ->timezone('Europe/Riga')
    ->weekdays()
    ->between('8:00', '20:00')
    ->withoutOverlapping();

// Публичный API: оркестратор Discovery поставщиков — каждые 10 минут (§7).
Schedule::job(new \App\Jobs\Api\DiscoveryOrchestratorJob())->everyTenMinutes()->withoutOverlapping();

// Публичный API: перепроверка awaiting_suppliers — каждый час (§6.3).
Schedule::job(new \App\Jobs\Api\RecheckAwaitingSuppliersJob())->hourly()->withoutOverlapping();

// Публичный API: reconcile промоушена (heartbeat cross-DB) — каждые 5 минут (§6.5).
Schedule::job(new \App\Jobs\Api\ReconcilePromotionJob())->everyFiveMinutes()->withoutOverlapping();

// Публичный API: физическое удаление revoked ключей (>30 дней) — раз в сутки (§9.5).
Schedule::job(new \App\Jobs\Api\CleanupRevokedApiKeysJob())->daily();
