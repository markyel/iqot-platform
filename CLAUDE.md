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

## История работы (июнь 2026)
- `296227e` — вкладка «Генератор» адресов + раздел/отдел в ФИО.
- `5b2dcf5` — именные логины (фамилия/имя.фамилия) при пустом ФИО директора (ExportBase не отдаёт ФИО → синтез из пула SURNAMES в `SenderAddressGenerator`).
- `61862e2` — фоновый импорт генератора (фикс 504).
- `667dc64` — санитизация реквизитов организации (фикс 1406 на `ogrn`).

### Состояние данных
- Синхронный прогон до фикса очереди (15:01–15:05) создал 46 отправителей (ID 85–130), у всех привязана организация. Пароли — в `smtp_password`.
- Прогон 15:15 словил 4 ошибки `1406 ogrn`, т.к. воркеры тогда ещё крутили старый код (перезапущены на новом в 15:18). Эти 4 организации не сохранились, ИНН свободны.
- Повторный запуск генератора подхватывает только новые ИНН (пул исключает уже импортированные), битые `kpp`/`ogrn` обнуляются вместо падения.
