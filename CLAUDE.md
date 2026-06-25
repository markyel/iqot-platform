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
  5. Вложения → `Storage::disk('public')` (`email-attachments/{msg|unident}/{id}/{idx}_{имя}`), относительный путь в `file_path`; строки в `email_attachments`/`unidentified_email_attachments`.
- **Воркеры**: systemd-шаблон `iqot-receive-worker@.service` (`queue:work database --queue=receive --tries=1 --timeout=180 --sleep=2`), 2 инстанса `@1,@2` — отдельный пул, чтобы медленный IMAP не голодил отправку.
- **Защита от наложения**: расписание `->everyFiveMinutes()->withoutOverlapping()` + `Cache::lock("receive:sender:{id}", 170)` на ящик внутри job (глобальный `workflow_locks` не нужен).
- **Флаг-предохранитель** `EMAILS_RECEIVE_ENABLED` (`services.email_receive.enabled`): без него `emails:receive-dispatch` молчит, ручной прогон — `--force`. Лимит писем за тик — `EMAILS_RECEIVE_LIMIT` (дефолт 20).

### Пофикшенные баги n8n (в порту)
- `Create Conversation` писал `status='active'` — такого значения НЕТ в enum (`waiting/partial/complete/...`) → падало при strict. Порт: `status='waiting'`.
- Тот же INSERT не задавал `items_total` (NOT NULL без дефолта) → падало. Порт: `items_total = email_batches.items_count`.
- Ветка `Save to Spam1` в графе была отключена (мёртвая) → спам не реализуем, всё непривязанное идёт в `unidentified_emails`.
- AI-классификация — НЕ здесь (колонки `ai_*` пустые, отдельный downstream).

### Состояние перехода (25.06.2026)
- Коммиты `a939a77` (порт), `67152b4` (iconv-декод заголовков). Таблицы приёма уже были в `reports` — миграций НЕ потребовалось.
- E2E на живых данных: ящик `sender_id=1` (imap.beget.com), 2 реальных ответа поставщика по токену `LS-reed+switch109-SI20` → `replied`, `email_messages` (incoming) вставлены, `email_queue 93565 → replied` с preview, вложение `0_DG12MOA-...pdf` легло на диск (`email_attachments` EXISTS), повторный прогон → `duplicate`. Декод темы и кириллицы — ок.
- Воркеры `iqot-receive-worker@{1,2}` — `enabled --now` (простаивают, пока флаг выключен). `schedule:list` показывает `emails:receive-dispatch`. **`EMAILS_RECEIVE_ENABLED` пока выключен** — включить после финальной сверки и окончательного отключения n8n-воркфлоу.

## История работы (июнь 2026)
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
