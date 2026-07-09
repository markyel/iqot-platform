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
// Рабочее окно — Пн–Пт 08:00–21:00 по Europe/Riga (как в n8n Within Work Hours).
Schedule::command('emails:dispatch-pending')
    ->everyMinute()
    ->timezone('Europe/Riga')
    ->weekdays()
    ->between('8:00', '21:00')
    ->withoutOverlapping();

// Досыл по пулу расширения (волна 2): раз в день в начале рабочего окна проверяем
// заявки с придержанной волной 2 (старше followup_delay_days) — при малом отклике
// отпускаем письма в рассылку, иначе отменяем. Флаг EMAILS_POOL_FOLLOWUP_ENABLED.
Schedule::command('emails:dispatch-followup')
    ->dailyAt('8:05')
    ->timezone('Europe/Riga')
    ->weekdays()
    ->withoutOverlapping();

// Повтор отложенных гейтом качества батчей (discovery-first): когда discovery добрал
// поставщиков (deferred_batches.status=ready) — генерим повторно без гейта. Каждые 15
// мин. Флаг EMAILS_POOL_GATE_ENABLED (команда сама проверяет).
Schedule::command('emails:retry-deferred')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// Накопительная отсрочка по загрузке получателей (Version A): выпуск накопленных
// анонимных батчей (reason='recipient_load'), когда набралось target однородных позиций
// / пул разгрузился / истёк max_hold. Каждые 15 мин. Флаг EMAILS_LOAD_DEFER_ENABLED
// (команда сама проверяет).
Schedule::command('emails:process-load-deferred')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// Прогрев отправителей (Phase 3): суточный пересчёт senders.daily_limit — рампа за
// успешный день / сброс+блок при бане. Флаг EMAILS_WARMUP_ENABLED (команда сама
// проверяет). Раз в сутки рано утром по МСК (до рабочего окна рассылки).
Schedule::command('emails:warmup-ramp')
    ->dailyAt('04:30')
    ->timezone('Europe/Moscow')
    ->withoutOverlapping();

// Адаптивный дневной cap получателей по вовлечённости (ответил → к max 15; нет
// реакции/баунсы → к min 5; иначе база 10). Раз в сутки рано утром по МСК.
Schedule::command('emails:recompute-recipient-caps')
    ->dailyAt('04:45')
    ->timezone('Europe/Moscow')
    ->withoutOverlapping();

// Выпуск отсрочек по капасити прогрева (Phase 3b): остатки батчей, не влезшие в
// дневные лимиты (sender_capacity), и адресаты, снятые при бане ящика
// (ban_containment), — перегенерация другими ящиками, когда лимиты освободились.
// Каждые 30 мин. Флаг EMAILS_WARMUP_ENABLED (команда сама проверяет).
Schedule::command('emails:process-capacity-deferred')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

// Гвард спам-реджекта: отключение/возврат отправителей по ДОЛЕ спам-реджекта за окно
// (корректная атрибуция по ящику, на чей IMAP пришёл NDR). Каждые 2 часа.
// Флаг EMAILS_SPAM_GUARD_ENABLED (команда сама проверяет).
Schedule::command('emails:spam-reject-guard')
    ->everyTwoHours()
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

// Триаж вопросов поставщиков (замена n8n «Process Supplier Questions»). Гоним
// каждую минуту — ответы генерим оперативно, без искусственной задержки (в n8n
// был крон раз в 120 мин). Безопасно при частом запуске: ProcessSupplierQuestionJob
// клеймит вопрос (Cache::lock + повторная проверка status='pending'), повторный
// диспатч того же вопроса отваливается. По умолчанию молчит, пока флаг
// EMAILS_QUESTIONS_ENABLED=false — включать ТОЛЬКО после отключения n8n-воркфлоу
// (author_questions/question_consolidation/outgoing_replies не идемпотентны).
Schedule::command('emails:process-questions')
    ->everyMinute()
    ->withoutOverlapping();

// Отправка готовых ответов поставщикам (замена n8n «Send Outgoing Replies»).
// Каждую минуту в рабочем окне Пн–Пт 08:00–21:00 по Europe/Riga (как массовая
// рассылка) — чтобы сгенерённый триажем ответ уходил оперативно, а не ждал тика.
// Диспетчер клеймит ответ (status='sending'), повторный тик его не подхватит. По
// умолчанию молчит, пока флаг EMAILS_REPLIES_ENABLED=false — включать ТОЛЬКО после
// отключения n8n-воркфлоу (иначе двойная отправка).
Schedule::command('emails:dispatch-replies')
    ->everyMinute()
    ->timezone('Europe/Riga')
    ->weekdays()
    ->between('8:00', '21:00')
    ->withoutOverlapping();

// Идентификация неопознанных писем (второй проход, замена n8n «Process Unidentified
// Emails v4»). Письма с потерянным токеном, не привязанные на приёме. В n8n был крон
// раз в 120 мин; гоним каждые 30 мин, чтобы быстрее разгребать бэклог pending. По
// умолчанию молчит, пока флаг EMAILS_IDENTIFY_ENABLED=false — включать ТОЛЬКО после
// отключения n8n-воркфлоу (миграция письма создаёт боевые строки — иначе дубли).
Schedule::command('emails:identify-unidentified')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

// Генерация рассылок (замена n8n «Create Email Queue v4 (AI)», каждые 5 мин).
// Собирает заявки draft/new/active, бьёт позиции на батчи, AI-генерит тело и токен
// по стилю отправителя (анти-фингерпринтинг), рендерит уникальный HTML на каждого
// поставщика и пишет письма в email_queue(pending) (их потребляет emails:dispatch-
// pending). По умолчанию молчит, пока флаг EMAILS_GENERATE_ENABLED=false — включать
// ТОЛЬКО после отключения n8n-воркфлоу (INSERT'ы email_batches/email_queue не
// идемпотентны — иначе двойная рассылка). Очередь `generate` (нужен воркер на проде).
Schedule::command('emails:generate-queue')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Фаза 2 (планировщик, за флагом EMAILS_PLANNER_ENABLED — команды сами молчат, пока
// off). Билдер backlog интентов + ленивый рендер под ёмкость получателей. Включать
// ВМЕСТЕ с выключением EMAILS_GENERATE_ENABLED (иначе оба обрабатывают заявки).
Schedule::command('emails:build-intents')
    ->everyTenMinutes()
    ->runInBackground()
    ->withoutOverlapping();
Schedule::command('emails:plan-render')
    ->everyFiveMinutes()
    ->timezone('Europe/Riga')
    ->weekdays()
    ->between('8:00', '20:00')
    ->runInBackground()
    ->withoutOverlapping();

// Авто-закрытие зависших вопросов к автору: спустя N дней (по умолч. 4) без ответа
// автора шлём поставщику «информации нет» и закрываем вопрос. Дозированно (--limit).
// Молчит, пока EMAILS_AUTOCLOSE_ENABLED=false.
Schedule::command('emails:auto-close-questions')
    ->dailyAt('09:00')
    ->timezone('Europe/Riga')
    ->weekdays()
    ->withoutOverlapping();

// Публичный API: оркестратор Discovery поставщиков — каждые 10 минут (§7).
Schedule::job(new \App\Jobs\Api\DiscoveryOrchestratorJob())->everyTenMinutes()->withoutOverlapping();

// Публичный API: перепроверка awaiting_suppliers — каждый час (§6.3).
Schedule::job(new \App\Jobs\Api\RecheckAwaitingSuppliersJob())->hourly()->withoutOverlapping();

// Публичный API: reconcile промоушена (heartbeat cross-DB) — каждые 5 минут (§6.5).
Schedule::job(new \App\Jobs\Api\ReconcilePromotionJob())->everyFiveMinutes()->withoutOverlapping();

// Публичный API: физическое удаление revoked ключей (>30 дней) — раз в сутки (§9.5).
Schedule::job(new \App\Jobs\Api\CleanupRevokedApiKeysJob())->daily();
