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

### Состояние перехода (25.06.2026)
- Коммит `5135c26`. n8n-воркфлоу «Send Emails v2» **отключён** пользователем; его последний прогон до отключения дослал свою пачку (~42 письма, MAX sent_at 12:26:54 МСК) и встал.
- Тест `--force --limit=1` дважды → письма ушли (id 98491 info@pkm2007.ru, 98492 sales@istlisft.ru, sender 66).
- `EMAILS_DISPATCH_ENABLED=true` в `.env` прода (строка 67), `config:clear`. Планировщик подтверждён (`schedule:list`, крон `* * * * * artisan schedule:run`). Боевая рассылка пошла под Laravel (дренаж здоровый, ошибок/блокировок нет).

### ⚠️ Рассинхрон таймзон (известно, на отправку не влияет)
- `config('app.timezone')='UTC'`, а reports-БД `@@time_zone=SYSTEM`=МСК(+3). n8n писал `sent_at`/`scheduled_at` в МСК (12:xx), Laravel пишет в UTC (09:xx) — метки на 3ч «раньше».
- Сейчас функционально безвредно: все pending имеют `scheduled_at` в реальном прошлом (`stuck_by_tz_skew=0`). Влияет только на отчётность по времени и сравнение разнотаймзонных меток в таблице. Если появятся письма с `scheduled_at` «на будущее по МСК» — диспетчер увидит их на 3ч позже; тогда сравнивать через `NOW()` БД, а не `now()` приложения.
- При диагностике sent/updated по reports-БД учитывать: Laravel-строки ищи в UTC-окне (~09:xx), n8n-строки — в МСК (~12:xx).

## История работы (июнь 2026)
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
