# IQOT Platform — рабочие заметки

## Деплой на прод
- Сервер: `217.26.31.80`, путь `/var/www/iqot`, SSH-алиас `iqot-prod`.
- Стандартный деплой:
  ```bash
  ssh iqot-prod 'cd /var/www/iqot && git pull origin main && php artisan view:clear && php artisan config:clear'
  ```
- После изменений в Job'ах/моделях, которые исполняются воркерами, **обязательно** `php artisan queue:restart` — воркеры долгоживущие (supervised) и кешируют загруженный код.
- Прод-PHP 8.3, локальный dev — Herd PHP 8.4: `/c/Users/Boag/.config/herd/bin/php84/php.exe`.
- Git: `git@github.com:markyel/iqot-platform.git`, ветка `main`.

### SSH-пробы прода
- `php -r` по SSH ломается на бэкслешах (namespace-сепараторы) → писать временный `.php` в `/var/www/iqot` (НЕ в `/tmp` — там `__DIR__`/autoload не находится), запускать, удалять.
- `artisan tinker --execute` тоже падает на `\` в namespace → использовать временный файл с bootstrap.
- `artisan route:list` падает (отсутствует PasswordResetLinkController) → пробовать через `Route::getRoutes()`.

## Групповое добавление отправителей (senders)
БД: соединение `reports` (Beget), таблицы `senders` и `client_organizations`.
Админка: `/manage`, контроллер `App\Http\Controllers\Admin\SenderImportController`.

Три вкладки:
1. **Ручной** (`store`) — вставка блоков отправителей текстом.
2. **Помощник** (`wizard`) — список `email password` + общие SMTP/IMAP + Excel организаций.
3. **Генератор** (`generate` → `generateStore` → `generateStatus`) — Excel организаций (ExportBase) + список доменов → кандидаты email/пароль с чекбоксами → фоновый импорт.

### Архитектура генератора (фоновый импорт)
- `generateStore()` → PRG-редирект: формирует блоки, `runId = Str::uuid()`, кладёт meta в кеш, диспатчит `Bus::batch($jobs)->allowFailures()`, редиректит на статус. Решает проблему 504 (раньше синхронный AI-вызов на каждый блок рвал прокси по таймауту).
- `App\Jobs\ImportSenderBlockJob` (Batchable, ShouldQueue, timeout=120, tries=3, backoff=30) — импортирует один блок через `BulkSenderImporter::importBlocks()`, пишет результат строки в кеш `senders_gen:{runId}:row:{index}`.
- `generateStatus()` — опрашивает строки из кеша, агрегирует created/skipped/failed, страница автообновляется каждые 3с до завершения батча.
- Прод-очередь: драйвер `database`, 2 supervised воркера, кеш — `database`. Учётки (email/пароль) показываются сразу, не дожидаясь импорта.

### Важные детали схемы
- `senders`: пароль хранится в `smtp_password` / `imap_password` (отдельной колонки `password` НЕТ). Привязка к организации — `client_organization_id`.
- `client_organizations`: `inn`/`kpp`/`ogrn`/`phone` — varchar(50), `name` — varchar(500), `contact_person`/`email`/`director_name` — varchar(255), `legal_address`/`actual_address` — TEXT.
- AI-промпт НЕ запрашивает `ogrn` (он приходит из Excel-блока генератора).
- Весь AI+org+insert обёрнут в try/catch в `BulkSenderImporter` → SQL-ошибки становятся строками со статусом `failed`, а не падением задачи.

### Защита от 1406 (data too long)
`ClientOrganization::findOrCreateForImport()` вызывает `sanitizeImportAttributes()`:
- `inn`/`kpp`/`ogrn` — только цифры со строгой проверкой длины (10/12, 9, 13/15), иначе `null` (кривой телефон/адрес в колонке реквизита не валит insert).
- строковые поля обрезаются под лимит колонок.

## Рассылка: перенос из n8n в Laravel (заменяет воркфлоу «Send Emails v2»)
БД та же — соединение `reports`, таблицы `email_queue`, `email_batches`, `senders`, `request_items`, `request_item_attachments`.

Поток: планировщик → `emails:dispatch-pending` → claim (`status='sending'`) → очередь `emails` (8 воркеров) → `SendQueuedEmailJob` (по письму) → `QueuedEmailSender` (Symfony Mailer по SMTP отправителя, ssl/465).

### Многопоточность + строгая пауза на ящик (коммиты ec30583, 9e6ffb3)
Максимальная пропускная способность при соблюдении `send_delay_seconds` между письмами одного ящика. Параллелизм — по разным ящикам; внутри ящика паузу держит атомарный замок в БД.
- `App\Console\Commands\DispatchPendingEmails` (`{--limit=3000} {--tick=60} {--force}`): реклейм застрявших `sending` >30 мин → `pending`; **честный round-robin** по готовым ящикам — на каждый берётся `perSenderCap = ceil(tick/delay)+1` писем (сколько ящик успеет до след. тика), claim (`status='sending'`) + dispatch в очередь `emails` с пред-разносом delay.
- `App\Jobs\SendQueuedEmailJob` (очередь `emails`, timeout=120, **tries=0** + `retryUntil(30 мин)` — переносы по паузе через `release()` не жгут попытки): **`reserveSlot()`** — атомарный CAS `UPDATE senders SET last_send_at=NOW(3) WHERE id=? AND (last_send_at IS NULL OR last_send_at<=NOW(3)-INTERVAL ? SECOND)`. affected=1 → слот наш, шлём; affected=0 → рано → `release(delay)`. Row-lock гарантирует взаимоисключение, `TIMESTAMP(3)`+`NOW(3)` — строгий интервал ≥ delay (секундный floor давал просадку до ~1s). Ошибка: `+1 retry`, перенос `+5 мин`; ratelimit → блок ящика 30 мин, деактивация при 3-й блокировке/сутки.
- `App\Services\Senders\QueuedEmailSender`: вложения из `request_item_attachments.file_data` (BLOB) по `email_batches.request_items` (JSON-массив id).
- Расписание (`routes/console.php`): `->everyMinute()->weekdays()->between('8:00','20:00')->timezone('Europe/Riga')->withoutOverlapping()`.
- **Воркеры**: systemd-шаблон `iqot-email-worker@.service` (`queue:work database --queue=emails --tries=0 --timeout=120 --sleep=1`), 8 инстансов `@1..@8`. Очередь `default` (`iqot-queue-worker@1,2`) — для остальных джоб (импорт/discovery). Масштаб: `systemctl enable --now iqot-email-worker@N`.
- **Флаг-предохранитель** `EMAILS_DISPATCH_ENABLED` (`config/services.php → services.email_dispatch.enabled`): без него команда молчит, ручной прогон — `--force`.
- Админ-статистика: `/manage/emails/stats` (`EmailQueueStatsController` → `admin.emails.stats`), пункт сайдбара «Очередь рассылки».
- **Проверка пауз**: `sent_at` секундной точности + джиттер SMTP → ложные «gap<2s». Мерить агрегатно: per-sender `span` vs `(n-1)*delay` и max сендов в скользящем 60s-окне (потолок `60/delay+1`). Live-замер: 0 нарушений, max 31/60s при delay=2.

### Адаптивный пейсинг по ПОЛУЧАТЕЛЮ (to_email) (25.06.2026)
Проблема: раньше n8n слал неспешно (~1 письмо/30 мин на поставщика), теперь письма прилетают пачками → один `to_email` может получить всю пачку сразу. Нужно размазать рассылку по дню РАВНОМЕРНО, **не зная заранее дневной объём**, и без жёсткого дневного лимита (по решению пользователя — только интервал).
- **Алгоритм (`DispatchPendingEmails::eligibleRecipients()`):** каждый тик (раз в минуту) для каждого `to_email` с pending-письмами считаем `interval = clamp(остаток_рабочего_окна / N_pending_получателю, MIN, MAX)` и считаем получателя «созревшим», если `last_dispatched_at IS NULL` или с прошлой раздачи прошло ≥ interval. Переоценка каждый тик → объём сам адаптируется под нагрузку без знания итогового числа: низкая нагрузка → MAX (≈раз в час), выше → плавно чаще, но не ниже MIN. На каждом тике одному получателю уходит **не более одного** письма (`$reserved`-сет в раздаче).
- **Якорь — `recipient_mailboxes.last_dispatched_at`** (миграция `2026_06_25_190000_...`), ставится `RecipientMailbox::markDispatched()` **при клейме** письма (`status→sending`), а НЕ при успешной отправке: иначе между тиком и асинхронным `SendQueuedEmailJob` была бы гонка двойной раздачи. Метки app-времени (UTC через `now()`), читаем сырьём и парсим как UTC — коннект reports на UTC-сессии (см. «Таймзоны»).
- **Конфиг (`services.email_dispatch`):** `recipient_interval_min_seconds` (env `EMAILS_RECIPIENT_INTERVAL_MIN`, дефолт **300** = 5 мин), `recipient_interval_max_seconds` (`EMAILS_RECIPIENT_INTERVAL_MAX`, дефолт **3600** = 1 ч), `work_window_end_hour` (`EMAILS_WORK_WINDOW_END_HOUR`, дефолт **20**), `work_window_timezone` (`EMAILS_WORK_WINDOW_TZ`, дефолт `Europe/Riga`). Остаток окна = от now до `end_hour` сегодня; вне окна (ручной `--force` поздно вечером) горизонт = MAX → щадящий режим.
- **Раздача в round-robin:** диспетчер берёт с ящика `perSenderCap + 50` кандидатов (часть отсеется пейсингом по получателю), пропускает «не созревшие» и уже отоварённые в этом тике `to_email`. Заблокированные получатели (`is_blocked`) по-прежнему отсекаются на уровне SQL (не клеймятся).
- **Пример (окно 12 ч, MAX=60м, MIN=5м):** низкая нагрузка → 60 мин; N=20 → ~36 мин; N=200 → пол 5 мин, остаток переносится на след. день.

### Защита от зависаний: ядовитые job'ы + блокировка битых получателей (25.06.2026)
Симптом: очередь `emails` встала, письма зависли в `sending`, число не меняется.
- **Корень — переполнение `jobs.attempts` (TINYINT, макс. 255).** Когда у одного ящика много писем (delay=2с → 1 письмо/2с), их job'ы конкурируют за слот → `reserveSlot()=false` → `release()` каждые 2с. При `tries=0`+`retryUntil(30 мин)` голодающий job делает ~900 переносов → `attempts` переполняется на 256 → `SQLSTATE[22003]` **при pop'е** (до `handle()`) → job «ядовитый», валит воркер. Ядовитые в голове очереди обрушивают все воркеры → очередь колом. (255 «попыток» = переносы по слоту, НЕ попытки доставки; старый `[Errno -3]` в `error_message` — наследие n8n-микросервиса.)
- **Фикс 1 (потолок):** миграция `jobs.attempts` TINYINT→`INT UNSIGNED` (`2026_06_25_160000_widen_jobs_attempts_to_int.php`).
- **Фикс 2 (предохранитель):** `SendQueuedEmailJob::MAX_SLOT_DEFERRALS=25` — после 25 переносов по занятому слоту письмо возвращается в `pending` (un-claim) и job завершается БЕЗ `release()`; перепланирует диспетчер. `attempts` не растёт до потолка.
- **Блокировка получателя по ошибкам подряд:** таблица `reports.recipient_mailboxes` (ключ — нормализованный `to_email`), модель `App\Models\Reports\RecipientMailbox`. Любая НЕ-ratelimit ошибка отправки → `recordFailure()` (инкремент `consecutive_errors`); успех → `recordSuccess()` (сброс в 0). При пороге `services.email_dispatch.recipient_error_threshold` (env `EMAILS_RECIPIENT_ERROR_THRESHOLD`, дефолт **3**) → `is_blocked=1`. Диспетчер (`whereNotExists` по `recipient_mailboxes`) и сам джоб такие адреса пропускают. **Ratelimit получателя НЕ штрафует** (это проблема отправителя → `blockSender`). Разблокировка ручная: `UPDATE recipient_mailboxes SET is_blocked=0, consecutive_errors=0 WHERE email='...'`.
- **Расклинивание (разово, прод):** после `migrate` — `UPDATE email_queue SET status='cancelled' WHERE status='sending' AND sender_id=<залипший>`; `DELETE FROM jobs WHERE queue='emails'`; `config:clear` + `queue:restart`.

### Состояние перехода (25.06.2026)
- Коммит `5135c26`. n8n-воркфлоу «Send Emails v2» **отключён** пользователем; его последний прогон до отключения дослал свою пачку (~42 письма, MAX sent_at 12:26:54 МСК) и встал.
- Тест `--force --limit=1` дважды → письма ушли (id 98491 info@pkm2007.ru, 98492 sales@istlisft.ru, sender 66).
- `EMAILS_DISPATCH_ENABLED=true` в `.env` прода (строка 67), `config:clear`. Планировщик подтверждён (`schedule:list`, крон `* * * * * artisan schedule:run`). Боевая рассылка пошла под Laravel (дренаж здоровый, ошибок/блокировок нет).

### Таймзоны (вылечено, коммит 247dffd)
- **Причина была:** сервер reports-БД (Beget) живёт в МСК(+3), главный коннект `mysql` — на UTC-сервере. У `reports` не было session-таймзоны → Laravel слал UTC-строки в МСК-сессию, и app-метки (`sent_at`/`scheduled_at`/`updated_at`) ложились с epoch на −3ч. n8n-строки (МСК-сессия) — с верным epoch.
- **Фикс:** `config/database.php` → reports `'timezone' => env('REPORTS_DB_TIMEZONE','+00:00')`. Сессия reports стала UTC (как у главного коннекта); `NOW()`==`UTC_TIMESTAMP()`, новые записи — истинный момент. Все datetime-колонки `TIMESTAMP` (epoch), поэтому старые n8n-строки остались корректны, просто отображаются в UTC.
- **Отображение:** оператору показываем МСК (конвертация на выводе). `EmailQueueStatsController` «Отправлено сегодня» считает по МСК-суткам (границы переводятся в UTC).
- **Forward-only:** разовую правку «сдвинутых на −3ч» Laravel-строк за 25.06 (id≥98491) НЕ делали — на рассылку не влияет, только косметика отчётов за тот день.
- ⚠️ **Остаточный рассинхрон ЧАСОВ (не таймзона):** app-сервер опережает сервер reports-БД на ~3–5 мин (NTP-дрейф между хостами). `sent_at` (app-часы) может быть «в будущем» относительно `NOW()` БД на эти минуты. На паузу не влияет (она на `NOW(3)` самой БД). Эффект: новое письмо со `scheduled_at=now()` может ждать первой отправки до ~5 мин; мелкие сдвиги в отчётах у границы суток. Лечится синхронизацией NTP на хостах.

## Приём почты: перенос из n8n в Laravel (заменяет воркфлоу «Receive and Route Emails v3»)
n8n-воркфлоу авто-отключился из-за повторяющихся падений. Перенесён нативно (зеркало рассылки: ушли от внешнего микросервиса `45.146.167.20:8000/receive` к чистому PHP). БД та же — `reports`.

Поток: планировщик → `emails:receive-dispatch` → по job на активный ящик с IMAP-кредами в очередь `receive` → `ReceiveSenderEmailsJob` (опрос ящика) → `IncomingEmailRouter::route()` (по письму).

### IMAP без ext-imap (коммиты a939a77, 67152b4)
- **`webklex/php-imap`** (`^6.2`, чистый PHP — на проде НЕТ `ext-imap`, но есть openssl/mbstring/fileinfo/iconv).
- `App\Services\Senders\ImapMailboxReader`: строит клиент из IMAP-полей `Sender` (`imap_server/imap_port/imap_user/imap_password/imap_encryption`), INBOX, выборка UNSEEN (`leaveUnread()` — НЕ вешает `\Seen` при чтении), маппинг в `App\Support\Mail\ParsedEmail` (DTO). Пометку `\Seen` ставит вызывающий код через `markSeen($uid)` **только после успешной обработки** — упавшее письмо перечитается.
  - **Декод заголовков:** без `ext-imap` дефолтный webklex-декодер `'utf-8'` НЕ разворачивает MIME encoded-word (`=?utf-8?B?…?=`) — тема оставалась закодированной. Принудительно ставим `decoding.options.header => 'iconv'` в `ClientManager` (`iconv_mime_decode`).
  - **Тело:** у многих писем `text/plain` пустой, контент в `text/html` (≈9 КБ) — пишем оба поля, `supplier_response_preview` = `strip_tags(bodyText ?: bodyHtml)`.
  - **Даты:** `received_at` = `Date`-заголовок письма как есть (часто МСК), `last_activity`/`created_at` = `now()` (UTC). Расхождение ~3ч — это разные источники времени, не баг.
- `App\Services\Senders\IncomingEmailRouter::route($senderId, ParsedEmail): string` (исход: `duplicate|replied|conversation|unidentified|skipped`), всё через query builder `DB::connection('reports')`:
  1. Дедуп по `email_messages.message_id`.
  2. `matchBatch`: `email_batches.tracking_token` (status in queued/sending/sent/completed, `created_at >= -60d`, токен ≥5 симв.) подстрокой в `subject + bodyText[:3000] + bodyHtml[:3000]`.
  3. `matchQueue`: полный `email_queue.token` подстрокой → `[queueId, supplierId]`. Нет supplier → `findSupplierByEmail(from)` по `suppliers.email`.
  4. Привязано → беседа (`email_conversations` найти/создать + `email_messages` `direction='incoming'`), `queueId` → `email_queue.status='replied'` + `replied_at`/`supplier_response_*`. Не привязано → `unidentified_emails` (reason `no_token`/`no_supplier`).
  5. Вложения → `Storage::disk('public')` (`email-attachments/{msg|unident}/{id}/{idx}_{имя}`); локальный путь в `local_path`, в `file_path` — Drive-URL при включённом дублировании (см. ниже), иначе тот же локальный путь; строки в `email_attachments`/`unidentified_email_attachments`.
- **Воркеры**: systemd-шаблон `iqot-receive-worker@.service` (`queue:work database --queue=receive --tries=1 --timeout=180 --sleep=2`), 2 инстанса `@1,@2` — отдельный пул, чтобы медленный IMAP не голодил отправку.
- **Защита от наложения**: расписание `->everyFiveMinutes()->withoutOverlapping()` + `Cache::lock("receive:sender:{id}", 170)` на ящик внутри job (глобальный `workflow_locks` не нужен).
- **Флаг-предохранитель** `EMAILS_RECEIVE_ENABLED` (`services.email_receive.enabled`): без него `emails:receive-dispatch` молчит, ручной прогон — `--force`. Лимит писем за тик — `EMAILS_RECEIVE_LIMIT` (дефолт 20).

### Пофикшенные баги n8n (в порту)
- `Create Conversation` писал `status='active'` — такого значения НЕТ в enum (`waiting/partial/complete/...`) → падало при strict. Порт: `status='waiting'`.
- Тот же INSERT не задавал `items_total` (NOT NULL без дефолта) → падало. Порт: `items_total = email_batches.items_count`.
- Ветка `Save to Spam1` в графе была отключена (мёртвая) → спам не реализуем, всё непривязанное идёт в `unidentified_emails`.
- AI-классификация — отдельный downstream-шаг `emails:analyze-replies` (см. раздел «AI-анализ ответов поставщиков»), заполняет колонки `ai_*`.

### Состояние перехода (25.06.2026)
- Коммиты `a939a77` (порт), `67152b4` (iconv-декод заголовков). Таблицы приёма уже были в `reports` — миграций НЕ потребовалось.
- E2E на живых данных: ящик `sender_id=1` (imap.beget.com), 2 реальных ответа поставщика по токену `LS-reed+switch109-SI20` → `replied`, `email_messages` (incoming) вставлены, `email_queue 93565 → replied` с preview, вложение `0_DG12MOA-...pdf` легло на диск (`email_attachments` EXISTS), повторный прогон → `duplicate`. Декод темы и кириллицы — ок.
- Воркеры `iqot-receive-worker@{1,2}` — `enabled --now`. `schedule:list` показывает `emails:receive-dispatch` (каждые 5 мин, `--force` для ручного прогона).
- **`EMAILS_RECEIVE_ENABLED=true` ВКЛЮЧЁН (25.06.2026), параллельно с ещё активным n8n-приёмом** (живая сверка перед окончательным переключением). Дубли в БД не образуются — дедуп по `message_id` (`email_messages`/`unidentified_emails`). После окончательного отключения n8n-воркфлоу «Receive and Route Emails v3» параллельный режим прекращается.
- **Фикс 1406 на больших телах (25.06.2026).** При живом приёме часть писем (рассылки dkc.ru/bitrix24) имеет HTML-тело >64 КБ → вставка падала на `1406 Data too long for column 'body_html'`, письмо терялось (`route failed`). Миграция `2026_06_25_180000_widen_email_bodies_to_longtext_on_reports.php` расширяет `body_text`/`body_html` до `LONGTEXT` на `email_messages` и `unidentified_emails`. Проверено: тела >100 КБ сохраняются, новых 1406 нет.

### Статистика приёма в админке (25.06.2026)
- `App\Http\Controllers\Admin\ReceiveEmailStatsController` → `admin.emails.receive-stats` (`/manage/emails/receive-stats`), view `admin/emails/receive-stats.blade.php`. Ссылка «Приём писем» в сайдбаре рядом с «Очередь рассылки». Зеркало `EmailQueueStatsController`, но для входящего направления.
- Карточки: входящие сегодня/всего, неопознанные сегодня/всего, беседы по статусам (waiting/complete/needs_clarification/has_offers), ручной разбор, без токена, вложения, заблокированные ящики-получатели. Срез «сегодня» (МСК, границы суток → UTC) показывает активность нового пайплайна на фоне легаси-данных n8n. Запросы — напрямую через `DB::connection('reports')` (моделей для этих таблиц нет).

### Дублирование вложений в Google Drive (переходный период, 25.06.2026)
**Проблема рассинхрона.** Downstream-воркфлоу n8n «Process Email Conversations» читает `email_attachments.file_path` и ждёт там Google-Drive-URL (`.../d/{fileId}/...`): нода `Extract File ID` (regex `/\/d\/([a-zA-Z0-9_-]+)/`) → `Convert Word to Google Docs` (`drive/v2/files/{id}/copy`) → export `text/plain` → AI. Раньше приём в n8n заливал вложения в Drive; теперь Laravel кладёт их локально → `file_path` стал локальным путём → downstream ломается на извлечении fileId.

**Решение (на переход).** `App\Services\Senders\GoogleDriveUploader` дублирует вложение в Drive, в `file_path` пишется Drive-URL `https://drive.google.com/file/d/{id}/view`, локальная копия (источник истины) — в новой колонке `local_path` (миграция `2026_06_25_170000_add_local_path_to_email_attachments_on_reports.php`, добавляет nullable `local_path` в обе таблицы вложений). После правки downstream на чтение `local_path` дублирование выключается флагом — остаётся только локал.
- **Аутентификация — OAuth2 refresh-token** пользователя liftway.ru (проект Google Cloud `lwbot-470510`). Service Account не подошёл: орг-политика `iam.disableServiceAccountKeyCreation` запрещает скачивать JSON-ключи SA. n8n Cloud свой credential «Google Drive account 2» тоже не отдаёт (managed OAuth). Решение: своё OAuth-приложение типом **Internal** (домен Workspace) → refresh-token **не протухает** (нет 7-дневного лимита testing-режима). Файлы владеет пользователь (квота 180 ГБ), льются в `folder_id` (PQSFiles) этого же аккаунта → downstream-копирование имеет доступ без публичных прав.
- Заливка — `multipart/related` одним запросом (`uploadType=multipart`), access-token кэшируется. Ошибка Drive/токена → `file_path` остаётся локальным (письмо/вложение не теряем, лог `Log::warning`).
- **Разовая настройка в Google Cloud (`lwbot-470510`):**
  1. Включить **Google Drive API** (`console.cloud.google.com/apis/library/drive.googleapis.com`).
  2. OAuth consent screen → **User type = Internal**, scope `https://www.googleapis.com/auth/drive`.
  3. Credentials → Create credentials → **OAuth client ID** → тип **Web application** → в Authorized redirect URIs добавить `https://developers.google.com/oauthplayground` → `client_id` + `client_secret`.
  4. OAuth Playground (`developers.google.com/oauthplayground`) → ⚙ Settings → «Use your own OAuth credentials» → ввести client_id/secret → Step 1: scope `https://www.googleapis.com/auth/drive` → Authorize (consent под владельцем PQSFiles) → Step 2: Exchange authorization code → скопировать **refresh_token**.
  5. ID папки PQSFiles (из URL `…/folders/XXXX`) → `GOOGLE_DRIVE_FOLDER_ID`.
- **Флаг-предохранитель `ATTACHMENTS_DRIVE_ENABLED`** (`services.attachments_drive.enabled`) — по умолчанию выключен. Включать ПОСЛЕ настройки выше:
  ```
  ATTACHMENTS_DRIVE_ENABLED=true
  GOOGLE_DRIVE_CLIENT_ID=...
  GOOGLE_DRIVE_CLIENT_SECRET=...
  GOOGLE_DRIVE_REFRESH_TOKEN=...
  GOOGLE_DRIVE_FOLDER_ID=<id-папки-PQSFiles>
  ```
  Без любого из client_id/secret/refresh_token/folder_id → `isEnabled()` = false → дублирование не происходит (file_path = локальный путь).

## AI-анализ ответов поставщиков (заменяет воркфлоу n8n «Process Email Conversations»)
Следующий шаг конвейера после приёма: берёт необработанные входящие письма (`email_messages.ai_processed=0`), прогоняет тело + вложения (КП) через AI, извлекает ценовые офферы и вопросы, пишет в боевые таблицы (`request_item_responses`, `request_item_multi_responses`, `supplier_questions`), проставляет `email_messages.ai_classification`/`ai_processed=1` и обновляет статус беседы. БД та же — `reports`.

Поток: планировщик (`->everyThirtyMinutes()`) → `emails:analyze-replies` → по job на письмо в очередь `analyze` → `AnalyzeSupplierReplyJob` → `SupplierReplyAnalyzer` (AI) → `SupplierReplyPersister` (запись).

### Сервисы (`app/Services/Analysis/`)
- **`DocumentTextExtractor`** — локальный парсинг вложений (БЕЗ Google Docs/Drive): Excel→`phpoffice/phpspreadsheet` (листы в TSV), PDF→`smalot/pdfparser`, Word→`phpoffice/phpword` (рекурсивный обход), html/text — как есть. Читает с диска `public` по `email_attachments.local_path`. Каждый файл в своём try/catch (битое вложение не валит письмо). Итог обрезается до `doc_max_chars` (начало 70% + конец 30%).
- **`EmailBodyCleaner`** — порт n8n «Prepare for AI»: `body_text` или извлечение из `body_html` (срез blockquote/style/script, теги, сущности), срезка цитат (рус./англ. маркеры, форварды, `>`-строки). Осмысленного текста <15 симв. → маркер `[ПИСЬМО БЕЗ НОВОГО СОДЕРЖАНИЯ]`.
- **`SupplierReplyAnalyzer`** — собирает userPrompt (очищенное тело + список позиций батча с `item_id` + текст вложений) и системный промпт (дословный порт из n8n: логика цены/НДС/количества/сроков/сопоставления/multi). Зовёт `OpenAIClassifierClient::jsonCompletion` (модель из `EMAILS_ANALYSIS_MODEL`, прокси `ai.lazylift.ru`, json_object, temperature 0). Нормализует ответ (округление цен, фолбэк при провале — письмо всё равно помечается обработанным).
- **`WebPageFetcher`** — замена Tavily 2-шаговым сёрфингом: если AI вернул `fetch_urls` и в офферах нет цены → грузим до `fetch_max` страниц, дописываем их текст в userPrompt и делаем второй прогон AI. Не tool-calling. **HTTP-first + headless-fallback:** сначала дешёвый `Http::get` (только http/https, `strip_tags`, обрезка `fetch_chars`); если ответ пустой/короче `http_min_chars` (типичная JS-заглушка) — fallback на `HeadlessPageRenderer`.
- **`HeadlessPageRenderer`** — рендер страницы через Chromium (Spatie Browsershot + puppeteer + Google Chrome stable). Решает кейс, где цена поставщика НЕ в письме, а на сайте за JS/cookie-анти-ботом (напр. Beget отдаёт 274-байт заглушку с `set_cookie()`+`location.reload()`; обычный `Http::get` видит только её). Browsershot исполняет JS, дожидается `networkIdle`, возвращает `document.body.innerText`. Конфиг: `EMAILS_ANALYZE_HEADLESS` (дефолт ON), `EMAILS_ANALYZE_CHROME_PATH` (`/usr/bin/google-chrome-stable`), `headless_home`, `headless_timeout`, `http_min_chars`.
  - **⚠ Прод-нюанс (HOME для www-data).** Воркеры крутятся под `www-data`, чей HOME (`/var/www`) не пишется → Chrome падает (crashpad / `mkdir ~/.local`). `Browsershot::setEnvironmentOptions(['HOME'=>...])` HOME для chrome НЕ перекрывает. Фикс: `putenv('HOME=...')` (+`$_SERVER`/`$_ENV`) на писчий каталог `storage/app/headless` ДО запуска Browsershot (Symfony Process наследует env родителя). Каталог должен быть writable для www-data.
  - **⚠ Память.** Chrome прожорлив; прод после апгрейда RAM 3.8 ГБ (было 2 ГБ — словил бы OOM). Воркер `analyze` запускает по 1 рендеру за письмо.
  - Прод-зависимости: `google-chrome-stable` (apt .deb), `puppeteer` (npm, `PUPPETEER_SKIP_DOWNLOAD=true` — используем системный Chrome), `spatie/browsershot` (composer). `node`+`npm` на проде есть.
- **`SupplierReplyPersister`** — транзакция на `reports`, порт SQL-узлов: классификация → `email_messages`; офферы upsert в `request_item_responses` по **unique `(request_item_id, supplier_id)`** (БЕЗ batch_id!); повторный оффер того же товара в этом письме (batch-scoped «main_exists»: status≠pending + price NOT NULL) → вариант в `request_item_multi_responses` + `has_multi_responses=1`; вопросы в `supplier_questions` с дедупом по `(email_message_id, question_text)`; `email_conversations.items_covered`/status (partial/pending).

### Идемпотентность (ВАЖНО)
Upsert офферов идемпотентен, но вставки в `request_item_multi_responses` и `supplier_questions` — НЕТ. Поэтому:
- **Флаг `EMAILS_ANALYZE_ENABLED` по умолчанию OFF** (`services.email_analysis.enabled`). Включать `true` в `.env` прода ТОЛЬКО ПОСЛЕ отключения n8n-воркфлоу «Process Email Conversations» — параллельная работа двух систем плодит дубли. Без флага `emails:analyze-replies` молчит, ручной/точечный прогон — `--force`.
- Внутри Laravel двойная обработка отсекается `Cache::lock("analyze:msg:{id}")` + повторной проверкой `ai_processed=0`; multi/questions дедуплицируются при записи.

### Команда и воркер
- `emails:analyze-replies {--force} {--limit=} {--message=ID}` — порт «Get Unprocessed Messages» (`direction=incoming AND ai_processed=0 AND ec.status NOT IN (complete,rejected,no_response)`, ORDER BY `received_at`, LIMIT `batch_limit`). `--message=ID` — точечный прогон одного письма.
- **Очередь `analyze`** — нужен отдельный systemd-воркер на проде (`queue:work database --queue=analyze --tries=1 --timeout=180`), по аналогии с `iqot-receive-worker@`.
- Конфиг (`services.email_analysis`): `EMAILS_ANALYSIS_MODEL` (дефолт `gpt-4o`), `EMAILS_ANALYSIS_TIMEOUT` (120), `EMAILS_ANALYSIS_MAX_TOKENS` (4096), `EMAILS_ANALYZE_BATCH_LIMIT` (50), `EMAILS_ANALYZE_DOC_MAX_CHARS` (30000), `EMAILS_ANALYZE_FETCH_URLS` (true), `EMAILS_ANALYZE_FETCH_MAX` (3), `EMAILS_ANALYZE_FETCH_CHARS` (8000), `EMAILS_ANALYZE_FETCH_TIMEOUT` (15).
- Composer-пакеты для парсинга добавлены: `phpoffice/phpspreadsheet`, `smalot/pdfparser`, `phpoffice/phpword` (на проде `composer install` из lock; в composer.json `config.audit.block-insecure=false` чтобы резолвер не блокировал уже запиненные пакеты).

## Триаж вопросов поставщиков (заменяет воркфлоу n8n «Process Supplier Questions»)
Следующий шаг после AI-анализа: AI-анализ раскладывает входящие письма на офферы и вопросы (`supplier_questions.status='pending'`); триаж берёт эти вопросы и решает — ответить поставщику **автоматически** или направить вопрос **автору заявки**. БД та же — `reports`. Авто-ответы складываются в `outgoing_replies` (status='pending'); **их отправку делает отдельный шаг `emails:dispatch-replies`** (см. раздел «Отправка готовых ответов»).

Поток: планировщик (`->everyTwoHours()`) → `emails:process-questions` → по job на вопрос в очередь `questions` → `ProcessSupplierQuestionJob` → AI #1 → ветка Auto/Author.

### Сервисы (`app/Services/Questions/`)
- **`QuestionContextLoader`** — сбор контекста (порт MySQL-узлов Get Pending/Batch/Request Items/Sender/Author Answers/Author User ID/Original Message/Template). Всё через query builder на `reports`. `loadQuestion` повторно проверяет `status='pending'` (claim). `loadAuthorAnswers` — до `history_limit` прошлых ответов автора по заявке с COALESCE `original_reply_id` (reply с файлами → любой reply) и `files_count` (образец для похожих вопросов + копирование вложений).
- **`QuestionAutoAnswerClassifier`** (AI #1) — дословный порт системного промпта n8n (v5.1: организационные данные/условия/история ответов автора/определение позиции). Зовёт `OpenAIClassifierClient::jsonCompletion` (модель `EMAILS_QUESTIONS_MODEL`, дефолт mini). Возвращает `can_auto_answer` + `answer_text` + `related_item_id` (резолв по `related_item_index`) + `original_reply_id`/`has_files_to_copy` (резолв по `used_history_index`). Фолбэк при провале парса — `can_auto_answer=false`.
- **`ReplyEmailBuilder`** — порт «Build Reply Email» v2.3: HTML по блокам `email_templates` (signature по форматам, скрытый 1px-белый токен, `items_display` = текст ответа AI), цитирование исходного письма (gmail_quote), `Re:`-тема, `references_header`, plain-text. Отдаёт массив полей для INSERT в `outgoing_replies`.
- **`QuestionConsolidator`** (AI #2, ветка Author) — дедуп вопроса по позиции: порт Get Existing Consolidations / Prepare Consolidation Check / AI Compare (gpt-4o-mini) / ветвей Is Similar?/Has Existing Answer?. Группы вопросов по `request_item_id` из `question_consolidation` + `supplier_questions.consolidation_id`. Если новый вопрос похож на группу с уже имеющимся ответом автора (`status='author_answered'`) → **апгрейд до авто-ответа** (`auto_answer_source='consolidation'`). Иначе — цепляет к существующей группе или заводит новую (INSERT `question_consolidation`).
- **`SupplierQuestionPersister`** — две ветки, транзакция на `reports`. **AUTO**: INSERT `outgoing_replies` (status='pending') + копирование вложений из `original_reply_id` (если есть) → `supplier_questions.status='auto_answered'` + `email_conversations.has_pending_question=0`. **AUTHOR**: `author_user_id` (по `requests.user_id`) → INSERT `author_questions` (status='pending', `request_item_ids`=`[id]`/`[]`) → `supplier_questions.status='forwarded_to_author'` + `request_item_id` + `consolidation_id`, `email_conversations.has_pending_question=1` + `status='needs_clarification'`.

### Идемпотентность (ВАЖНО)
Вставки `outgoing_replies`/`author_questions`/`question_consolidation` НЕ идемпотентны на уровне БД. Поэтому:
- **Флаг `EMAILS_QUESTIONS_ENABLED` по умолчанию OFF** (`services.email_questions.enabled`). Включать `true` в `.env` прода ТОЛЬКО ПОСЛЕ отключения n8n-воркфлоу «Process Supplier Questions» — параллельная работа двух систем плодит дубли. Без флага `emails:process-questions` молчит, ручной/точечный прогон — `--force`.
- Внутри Laravel двойная обработка отсекается `Cache::lock("questions:q:{id}")` + повторной проверкой `status='pending'`; вставки в персистере дедуплицируются по `supplier_question_id` (outgoing_replies/author_questions) и вложения — по наличию у нового reply.

### Команда и воркер
- `emails:process-questions {--force} {--limit=} {--question=ID}` — порт «Get Pending Questions» (`supplier_questions.status='pending'` JOIN беседы/поставщика, ORDER BY `created_at`, LIMIT `batch_limit`=10). `--question=ID` — точечный прогон одного вопроса.
- **Очередь `questions`** — нужен отдельный systemd-воркер на проде (`queue:work database --queue=questions --tries=1 --timeout=120`), по аналогии с `iqot-receive-worker@`. Заводить ТОЛЬКО когда флаг ON.
- Конфиг (`services.email_questions`): `EMAILS_QUESTIONS_MODEL` (дефолт `gpt-4o-mini`), `EMAILS_QUESTIONS_TIMEOUT` (60), `EMAILS_QUESTIONS_MAX_TOKENS` (1024), `EMAILS_QUESTIONS_BATCH_LIMIT` (10), `EMAILS_QUESTIONS_HISTORY_LIMIT` (15).

## Отправка готовых ответов поставщикам (заменяет n8n «Send Outgoing Replies»)
Последний шаг конвейера вопросов: триаж (`emails:process-questions`) складывает готовые ответы в `reports.outgoing_replies` (status='pending'), а этот шаг их отправляет по SMTP, пишет в `email_messages` (direction='outgoing') и переводит ответ в 'sent'/'failed'. БД та же — `reports`.

Поток: планировщик → `emails:dispatch-replies` → claim (status='sending') → по job на ответ в очередь `replies` → `SendOutgoingReplyJob` → `OutgoingReplySender` (Symfony Mailer по SMTP отправителя, ssl/465).

### Отличия от массовой рассылки
- **Threading.** `OutgoingReplySender` проставляет `In-Reply-To`/`References` (порт `reply.in_reply_to`/`reply.references_header` — значения хранятся уже как Message-ID с угловыми скобками, отдаём текстовыми заголовками как есть), чтобы ответ лёг в ту же цепочку у поставщика. Сам генерит `Message-ID` (`bin2hex(random_bytes(16)).'@'.domain`, в `<>`) — раньше его возвращал внешний микросервис `45.146.167.20:8000/send`; теперь отдаём вызывающему для записи в `email_messages`.
- **Save to Email Messages.** На успехе INSERT в `email_messages` (`direction='outgoing'`, `conversation_id`, from/to/subject/body, `message_id`, `in_reply_to`, `references_header`, `received_at=NOW()`) — порт одноимённого узла n8n, чтобы ответ попал в историю беседы и приём не счёл его новым.
- **Вложения** — из `reports.outgoing_reply_attachments` (BLOB `file_data`), а не из `request_item_attachments`.
- **Обработка ошибок (4 ветки, `handleFailure`).** `outgoing_replies` получил поля `retry_count`+`error_message` (миграция `2026_06_26_130000`), зеркало `email_queue`:
  - **ratelimit** → блок ящика 30 мин (общая логика с `SendQueuedEmailJob`: `block_count`, деактивация при 3-й блокировке/сутки) + ответ обратно в 'pending'.
  - **550 «sending is disabled for mailbox»** (ящик-отправитель отключён провайдером Beget) → **авто-деактивация отправителя** (`is_active=0`, `block_reason`/`last_block_at`) + ответ terminal 'failed'. Ретрай бесполезен.
  - **транзиентный коннект** (`connection could not be established`/`timed out`/…) — round-robin DNS `smtp.beget.com` (6 IP, часть периодически мёртвая) отдал нерабочий IP. Копим `retry_count`, ответ → 'pending' (диспетчер перезаберёт на след. тике, почти всегда другой живой IP), пока `retry_count < max_retries` (`EMAILS_REPLIES_MAX_RETRIES`, дефолт 3); исчерпали → 'failed'. **Без ретрая ~треть ответов падала по таймауту коннекта.**
  - **прочее** (битый адрес, отклонение контента) — terminal 'failed'.

### Общая пауза на ящик
Отправители (`senders`) общие с массовой рассылкой → паузу держит тот же атомарный «замок интервала» `reserveSlot()` (`UPDATE senders SET last_send_at=NOW(3) WHERE id=? AND (last_send_at IS NULL OR last_send_at<=NOW(3)-INTERVAL ? SECOND)`). Ответы и массовые письма с одного ящика не уйдут чаще `send_delay_seconds`. `tries=0` + `retryUntil(30 мин)` + `MAX_SLOT_DEFERRALS=25` (после 25 переносов по занятому слоту — ответ обратно в 'pending', un-claim) — защита от переполнения `attempts`, как в `SendQueuedEmailJob`.

### Claim через status='sending' (миграция enum)
Диспетчер берёт ответ в работу через `status='sending'` (повторный тик его не подхватит); реклейм застрявших 'sending' >30 мин → 'pending'. **Исходный ENUM был `('pending','sent','failed')` БЕЗ 'sending'** — n8n-узел «Update Status to Sending» молча писал `''` (MySQL в нестрогом режиме усекал недопустимый enum до пустой строки). Миграция `2026_06_26_120000_add_sending_to_outgoing_replies_status_enum.php` добавляет 'sending' → claim корректен.

### Идемпотентность (ВАЖНО)
Отправка письма побочна и НЕ идемпотентна. Поэтому:
- **Флаг `EMAILS_REPLIES_ENABLED` по умолчанию OFF** (`services.email_replies.enabled`). Включать `true` в `.env` прода ТОЛЬКО ПОСЛЕ отключения n8n-воркфлоу «Send Outgoing Replies» (сейчас `active=true`) — иначе двойная отправка одного ответа. Без флага `emails:dispatch-replies` молчит, ручной/точечный прогон — `--force`.
- Внутри Laravel двойную отправку отсекает claim (`status='sending'`) на уровне диспетчера + проверка статуса в начале job.

### Команда и воркер
- `emails:dispatch-replies {--force} {--limit=} {--reply=ID}` — порт «Get Pending Replies» (`outgoing_replies.status='pending'` ORDER BY `created_at`, LIMIT `batch_limit`=30). `--reply=ID` — точечный прогон одного ответа (claim + dispatch).
- **Очередь `replies`** — нужен отдельный systemd-воркер на проде (`queue:work database --queue=replies --tries=0 --timeout=120`), по аналогии с `iqot-receive-worker@`. Заводить ТОЛЬКО когда флаг ON.
- Расписание (`routes/console.php`): `->everyFifteenMinutes()->timezone('Europe/Riga')->weekdays()->between('8:00','20:00')->withoutOverlapping()` — рабочее окно как у массовой рассылки.
- Конфиг (`services.email_replies`): `EMAILS_REPLIES_ENABLED` (дефолт false), `EMAILS_REPLIES_BATCH_LIMIT` (30), `EMAILS_REPLIES_MAX_RETRIES` (дефолт 3 — потолок ретраев транзиентного коннекта).

## Идентификация неопознанных писем (заменяет воркфлоу n8n «Process Unidentified Emails v4»)
Второй проход после приёма: письма с потерянным/искажённым токеном, которые `IncomingEmailRouter` не смог привязать на приёме, ложатся в `unidentified_emails` (`status='pending'`, `reason` = `no_token`/`no_supplier`/`bounce`). Этот шаг пытается идентифицировать их сильнее и, при успехе, мигрирует письмо в боевую беседу (`email_messages` как `incoming` + `email_attachments`), создаёт/находит `email_conversations` и помечает `email_queue.status='replied'`. Дальше мигрированное письмо (`ai_processed=0`) подхватывает `emails:analyze-replies` — цепочка конвейера. БД та же — `reports`.

Поток: планировщик (`->everyThirtyMinutes()`) → `emails:identify-unidentified` → по job на письмо в очередь `identify` → `IdentifyUnidentifiedEmailJob` → сервисы `app/Services/Identify/*`.

### Сервисы (`app/Services/Identify/`)
- **`MailboxTokenMatcher`** — мягкий матч токена (порт «Get Tokens for Mailbox» + «Match Token in Subject»): берёт токены писем, отправленных С ТОГО ЯЩИКА, на который пришёл ответ (`email_queue.from_email = unidentified_emails.to_email`) за `lookback_days` по активным статусам (sent/opened/replied/in_conversation), чистит эмодзи (🛠️/⚙️/🔧). Ищет полный `token_clean` (≥5 симв.) в `subject + body_text[:3000]`, иначе базовую часть до первого дефиса (≥4 симв.). Это второй проход: на приёме матчится только точный токен, здесь добираем письма с искажённым хвостом.
- **`CandidateBatchLoader`** — кандидат-заявки (порт «Find Batches by Domain»): письма с того же ящика за окно по активным статусам, чей поставщик имеет ТОТ ЖЕ домен, что отправитель ответа, ЛИБО совпавший токен (queue_id/batch_id). С подгруженными позициями (`request_items` по JSON-массиву `email_batches.request_items` через `JSON_CONTAINS`) и метаданными для промпта. Совпавший по токену кандидат — первым (`ORDER BY CASE`).
- **`IdentificationAnalyzer`** — AI-сопоставление (порт «Prepare AI Prompt» + «AI Agent» + активного «Parse AI Response»; усиленный «Parse AI Response1» в графе n8n — орфан, не подключён). Системный промпт — дословный порт: классификация письма (автоответ/вопрос/КП/отказ/**запрос реквизитов**), сопоставление ПО НАЗВАНИЮ товара (артикулы поставщика ≠ наши), учёт токен-метки ⭐. Зовёт `OpenAIClassifierClient::jsonCompletion` (`EMAILS_IDENTIFY_MODEL`, json_object, temperature 0). Валидация: `queue_id` обязан быть среди кандидатов И `confidence ≥ min_confidence` → `validation_passed=true`; токен-матч поднимает confidence до 0.9. Возвращает `email_type` ∈ {`auto_reply`,`price_offer`,`rejection`,`question`,`requisites_request`,`unknown`} (нормализуется `normalizeType()`). Фолбэк при ошибке AI — `validation_passed=false`, `email_type='unknown'`.
- **`IdentifiedEmailPersister`** — запись результата (порт «Update as Identified / Find+Create Conversation / Save Email Message / Migrate Attachments» + ветки manual_review/spam). Успех: транзакцией `unidentified_emails.status='identified'` + `identified_*` + метод/уверенность; беседа (найти по `(batch_id, supplier_id)` или создать `status='waiting'`, `items_total` из `email_batches.items_count`); `email_messages` (`direction='incoming'`, дедуп по `message_id`); при создании нового сообщения — миграция вложений в `email_attachments` (с `local_path`) + `email_queue.status='replied'`. Неуспех: `persistManualReview` → `status='manual_review'` (+`reason='no_match'` для ветки без кандидатов); `persistSpam` → `status='spam'` (терминально, без ретраев/ручного разбора).

### Маршрутизация по типу письма (фикс 26.06.2026)
Не каждое неопознанное письмо нужно на ручной разбор. После AI-классификации `IdentifyUnidentifiedEmailJob` ветвит результат:
- **Опознано** (`validation_passed && identified_batch_id`) → `persistIdentified` (миграция в беседу). Сюда же попадает **`requisites_request`** (запрос наших реквизитов): AI обязан опознать его по кандидату-поставщику (единственный → он; несколько → самый недавний по «Дней назад», confidence 0.6–0.8). После миграции письмо (`ai_processed=0`) подхватит `emails:analyze-replies` → вопрос ляжет в `supplier_questions` → `emails:process-questions` (`QuestionAutoAnswerClassifier`) автоответит данными организации из `client_organizations` (ИНН/КПП/название/адрес) и `OutgoingReplySender` отправит. Отдельный код отправки реквизитов НЕ нужен — механизм уже есть в конвейере вопросов.
- **Не опознано + `auto_reply`** → `persistSpam` (`status='spam'`). Автоответы/приветствия без действий не идут в ручной разбор и не ретраятся.
- **Не опознано + прочее** (отказ/вопрос без совпадения) → `persistManualReview` (`status='manual_review'`).

### Решения порта (отличия от n8n)
- **Документы парсим ЛОКАЛЬНО** через переиспользуемый `DocumentTextExtractor` (читает `unidentified_email_attachments.local_path`), а НЕ через Google Drive-конвертацию n8n — консистентно с шагом анализа.
- **Бэунсы исключены из выборки** (`reason='bounce'` — NDR от Mailer-Daemon, ~70% pending, к беседе не привязываются) → не жжём попытки/AI зря.
- **`items_total`** беседы — из `email_batches.items_count` (как в `IncomingEmailRouter`), а не хардкод `1` из n8n.
- Дефолтная модель `gpt-4o` (n8n был `gpt-3.5-turbo`; сопоставление по названию сложнее — берём сильнее).

### Идемпотентность (ВАЖНО)
Миграция письма создаёт боевые строки (`email_messages`/`email_attachments`/беседа) и трогает `email_queue`. Поэтому:
- **Флаг `EMAILS_IDENTIFY_ENABLED` по умолчанию OFF** (`services.email_identify.enabled`). Включать `true` в `.env` прода ТОЛЬКО ПОСЛЕ отключения n8n-воркфлоу «Process Unidentified Emails v4» — параллельная работа плодит дубли. Без флага команда молчит, ручной/точечный прогон — `--force`.
- Внутри Laravel двойная обработка отсекается `Cache::lock("identify:ue:{id}")` + повторной проверкой `status='pending'` и `processing_attempts < max` внутри лока; `email_messages` дедуп по `message_id`; вложения мигрируются ТОЛЬКО при создании нового сообщения.

### Команда и воркер
- `emails:identify-unidentified {--force} {--limit=} {--email=ID}` — порт «Get Pending Unidentified» (`status='pending' AND processing_attempts < max_attempts AND reason<>'bounce'`, ORDER BY `created_at`, LIMIT `batch_limit`). `--email=ID` — точечный прогон одного письма.
- **Очередь `identify`** — нужен отдельный systemd-воркер на проде (`queue:work database --queue=identify --tries=1 --timeout=180`), по аналогии с `iqot-receive-worker@`/`analyze`. Пока флаг OFF — воркер не обязателен.
- Конфиг (`services.email_identify`): `EMAILS_IDENTIFY_MODEL` (дефолт `gpt-4o`), `EMAILS_IDENTIFY_TIMEOUT` (120), `EMAILS_IDENTIFY_MAX_TOKENS` (1024), `EMAILS_IDENTIFY_BATCH_LIMIT` (50), `EMAILS_IDENTIFY_MAX_ATTEMPTS` (5), `EMAILS_IDENTIFY_LOOKBACK_DAYS` (60), `EMAILS_IDENTIFY_CANDIDATE_LIMIT` (50), `EMAILS_IDENTIFY_MIN_CONFIDENCE` (0.5), `EMAILS_IDENTIFY_DOC_MAX_CHARS` (30000).

## Генерация рассылок (emails:generate-queue, заменяет n8n «Create Email Queue v4 (AI)»)
Последний и самый ответственный шаг конвейера. Каждые 5 мин собирает заявки, бьёт позиции на батчи (≤5), подбирает профильный список поставщиков, назначает ящик-отправитель, AI-генерит уникальное тело письма и трекинг-токен, рендерит **уникальный HTML на каждого поставщика** (со скрытым 1px-токеном) и пишет строки в `email_queue` (`status='pending'`) — их уже потребляет `emails:dispatch-pending`. Перенос завершает цикл и убирает зависимость от n8n. БД та же — `reports`.

Поток: планировщик (`->everyFiveMinutes()`) → `emails:generate-queue` → claim заявок (`draft/new/active → queued_for_sending`) → **один** `GenerateCampaignJob` на весь перехваченный набор в очередь `generate` → сервисы `app/Services/Generate/*`.

### Анти-фингерпринтинг (ключевое требование)
Письма от **разных** отправителей не должны быть похожи; стиль каждого отправителя **стабилен из рассылки в рассылку**. Заложено структурно — всё привязано к sender:
- **Шаблон/вёрстка** — `email_templates` по `senders.preferred_template_id` (blocks, signature_format, items_format, items_display_config, style_preset, ai_tone, subject_template).
- **Стиль общения (тон)** — `email_tones` по `email_templates.ai_tone` (code).
- **Стиль токена** — `token_templates` по `senders.token_template_id` (`prompt_template`+`example`).
НЕЛЬЗЯ вводить единый генератор тела/токена/вёрстки — один общий генератор «добавит похожести».

### Сервисы (`app/Services/Generate/`)
- **`Batch`** (DTO) — сгруппированные позиции + ключи маршрутизации; дозаполняется sender'ом, поставщиками, токеном/телом, batch_id.
- **`CampaignItemGrouper`** — порт «Group Items»: режим из `migration_flags.use_new_routing`; NEW — по `product_type_id`+`domain_id`; OLD — по `category`→`categories.routing` (large/medium/small). Именные заявки — per-request; обычные — кросс-заявочно, чанк по `items_per_batch`.
- **`CampaignSupplierSelector`** — порт «Build Supplier SQL»: NEW — join `supplier_product_types`/`supplier_domains` (`is_included`); OLD — `JSON_CONTAINS` по routing-категориям. Только `notify_email=1`.
- **`CampaignSenderAssigner`** — порт «Get Sender IDs»+«Assign Senders»+«Get Sender»: именная заявка → персональный sender орг.; анонимная → round-robin по общему пулу. Грузит полный профиль sender+орг.
- **`CampaignTokenGenerator`** — порт «Get Token Template»+«Generate Token Prompt»+«AI Generate Token»+«Clean Token». `textCompletion(token_model, …, temp 0.7)` по `token_templates.prompt_template` отправителя; очистка (markdown/кавычки/первое слово, 3–200 симв.); фолбэк `FB-PREFIX-MMDD-RAND`. **Стиль токена различается per-sender — НЕ упрощать до общего random.**
- **`CampaignBodyGenerator`** — порт «Get Email Template»+«Get Email Tone»+«Prepare AI Prompt»+«AI Agent»+«Parse AI Response». `jsonCompletion(body_model='gpt-4o', …, temp 0.7)` → `{greeting, introduction, closing}`. **Тело — модель выше качеством (gpt-4o, temp 0.7), 1 AI-вызов на батч** (на всю рассылку по всем поставщикам одно тело → стоимость не критична). Фолбэк-строки при провале.
- **`CampaignEmailBuilder`** — порт «Generate Emails» (~900 строк JS) на ОДНОГО поставщика → `{subject, body_html}`. Блоки/подпись/таблица/скрытый токен (`<p style="color:#fff;font-size:1px">Ref: TOKEN-suffix</p>`) — дословно; per-supplier суффикс токена. Вёрстка/тон из `email_templates`+`email_tones` отправителя → уникальность.
- **`CampaignPersister`** — транзакция на `reports`, порт INSERT/UPDATE-узлов: INSERT `email_batches`(pending)→batch_id; UPDATE (email_body_text=greeting+intro+closing, ai_model, ai_generated_at, status=ai_generated); на поставщика INSERT `email_queue`(pending, `token`=`tracking_token`)→email_queue_id + upsert `request_item_responses` на позицию×поставщика (UNIQUE → идемпотентно); UPDATE `email_batches` status=queued.

### Идемпотентность (КРИТИЧНО — гонит реальные рассылки)
- **Флаг `EMAILS_GENERATE_ENABLED` по умолчанию OFF** (`services.email_generate.enabled`). Включать `true` ТОЛЬКО после отключения n8n «Create Email Queue v4 (AI)» — INSERT'ы `email_batches`/`email_queue` НЕ идемпотентны, параллельная работа = дубли писем. Без флага команда молчит, ручной прогон — `--force`.
- **Claim заявок командой** — атомарный per-row `UPDATE requests SET status='queued_for_sending' WHERE id=? AND status IN ('draft','new','active')`; джоб получает только реально перехваченные id. Второй тик/n8n уже не подхватят. Внутри джоба — `Cache::lock("generate:req:{id}")` (анти-двойной-дисптач).
- Частично упавший батч: per-batch try/catch логирует и продолжает; заявка остаётся `queued_for_sending` (повторно не возьмётся). Полная регенерация — ручной сброс статуса заявки.
- **`--dry-run`** НЕ флипает статус заявки (остаётся повторно-прогоняемым), но ВСЁ РАВНО пишет `email_queue`(pending)/`email_batches`/`request_item_responses` — до инспекции pending-строки держать `cancelled`/удалять, чтобы диспетчер не забрал.

### Команда, джоб, воркер
- `emails:generate-queue {--force} {--limit=} {--request=ID} {--dry-run}` — порт «Get Requests» (`status IN (draft,new,active) ORDER BY is_customer_request DESC, created_at ASC LIMIT request_limit`). `--request=ID` — точечный прогон одной заявки.
- `GenerateCampaignJob` (очередь `generate`, tries=1, timeout=180) — оркестратор над набором заявок: загрузка позиций (Get All Items) → grouper → senderAssigner (глобально за прогон) → per-batch (supplierSelector → tokenGenerator → bodyGenerator → loop emailBuilder → persister) → флип `requests→queued_for_sending` (не в dry-run).
- **Очередь `generate`** — нужен отдельный systemd-воркер на проде (`queue:work database --queue=generate --tries=1 --timeout=180`), по аналогии с `iqot-receive-worker@`/`analyze`/`identify`. Пока флаг OFF — воркер не обязателен.
- Конфиг (`services.email_generate`): `EMAILS_GENERATE_ENABLED` (OFF), `EMAILS_GENERATE_BODY_MODEL` (`gpt-4o`), `EMAILS_GENERATE_BODY_TEMP` (0.7), `EMAILS_GENERATE_TOKEN_MODEL` (`gpt-4o-mini`), `EMAILS_GENERATE_TOKEN_USE_AI` (true), `EMAILS_GENERATE_TIMEOUT` (60), `EMAILS_GENERATE_MAX_TOKENS` (1500), `EMAILS_GENERATE_REQUEST_LIMIT` (20), `EMAILS_GENERATE_ITEMS_PER_BATCH` (5).

## История работы (июнь 2026)
- Генерация рассылок (порт n8n «Create Email Queue v4 (AI)») — сервисы `app/Services/Generate/*` (Batch/CampaignItemGrouper/CampaignSupplierSelector/CampaignSenderAssigner/CampaignTokenGenerator/CampaignBodyGenerator/CampaignEmailBuilder/CampaignPersister), `GenerateCampaignJob`, команда `emails:generate-queue`, расписание `everyFiveMinutes`. Расширен `OpenAIClassifierClient` (опц. `temperature` в `jsonCompletion` + новый `textCompletion` для токена). Анти-фингерпринтинг per-sender (template/tone/token). Тело — `gpt-4o` temp 0.7, 1 вызов на батч. Флаг `EMAILS_GENERATE_ENABLED` OFF (планировщик молчит); воркер очереди `generate` на проде ещё не заведён (не нужен, пока флаг OFF). Включать ТОЛЬКО после отключения n8n-воркфлоу.
- Идентификация неопознанных писем (порт n8n «Process Unidentified Emails v4») — сервисы `app/Services/Identify/*` (MailboxTokenMatcher/CandidateBatchLoader/IdentificationAnalyzer/IdentifiedEmailPersister), `IdentifyUnidentifiedEmailJob`, команда `emails:identify-unidentified`, расписание `everyThirtyMinutes`. Флаг `EMAILS_IDENTIFY_ENABLED` OFF (планировщик молчит). Воркер очереди `identify` на проде ещё не заведён (не нужен, пока флаг OFF). Локальный парсинг вложений (не Google Drive), бэунсы исключены из выборки. Тест на проде (n8n-воркфлоу отключён): 10966 → identified conf 0.90 (полная миграция: беседа 15634, message 20975, PDF с local_path, `email_queue 76927→replied`), повторный прогон идемпотентен (дублей нет). **Фикс по фидбэку:** AI возвращает `email_type`; `auto_reply` без совпадения → `spam` (10413/10288), а не manual_review; `requisites_request` опознаётся и мигрирует (10200 → batch 748, supplier 755, conf 0.60, беседа 15638, `email_queue 98573→replied`) — реквизиты вышлет цепочка вопросов. Коммиты `d8e558b` (порт), `966bc3f` (email_type→spam/requisites).
- AI-анализ ответов поставщиков (порт n8n «Process Email Conversations») — сервисы `app/Services/Analysis/*`, `AnalyzeSupplierReplyJob`, команда `emails:analyze-replies`, расписание `everyThirtyMinutes`. Фиксы по ходу теста: `email_messages.from_name` не существует (sender_name из `suppliers.name`); `email_conversations.status` ENUM не содержит `pending` → `waiting`. Флаг `EMAILS_ANALYZE_ENABLED` остаётся OFF (планировщик молчит). Воркер очереди `analyze` на проде ЕЩЁ НЕ заведён (не нужен, пока флаг OFF). Тест на 6 реальных письмах (20391/20389/20387/19693/20376/19661, помечены `ai_processed=1`): классификация/вопросы/статус/дедуп/идемпотентность офферов — OK; извлечение цены по ссылкам не работает (см. ⚠ ограничение WebPageFetcher).
- `a939a77`/`67152b4` — приём почты на webklex (нативный IMAP, маршрутизация в беседы/unidentified, вложения на диск, iconv-декод заголовков без ext-imap).
- `9e6ffb3` — миллисекундный замок интервала (`TIMESTAMP(3)`/`NOW(3)`): строгая пауза ≥ delay без секундного off-by-one.
- `ec30583` — многопоточная рассылка: round-robin диспетчер, атомарный `reserveSlot`, очередь `emails` + 8 systemd-воркеров, `everyMinute`.
- `5135c26` — перенос рассылки из n8n в Laravel + статистика очереди (диспетчер, джоба, Symfony Mailer, флаг-предохранитель).
- `296227e` — вкладка «Генератор» адресов + раздел/отдел в ФИО.
- `5b2dcf5` — именные логины (фамилия/имя.фамилия) при пустом ФИО директора (ExportBase не отдаёт ФИО → синтез из пула SURNAMES в `SenderAddressGenerator`).
- `61862e2` — фоновый импорт генератора (фикс 504).
- `667dc64` — санитизация реквизитов организации (фикс 1406 на `ogrn`).

### Состояние данных
- Синхронный прогон до фикса очереди (15:01–15:05) создал 46 отправителей (ID 85–130), у всех привязана организация. Пароли — в `smtp_password`.
- Прогон 15:15 словил 4 ошибки `1406 ogrn`, т.к. воркеры тогда ещё крутили старый код (перезапущены на новом в 15:18). Эти 4 организации не сохранились, ИНН свободны.
- Повторный запуск генератора подхватывает только новые ИНН (пул исключает уже импортированные), битые `kpp`/`ogrn` обнуляются вместо падения.
