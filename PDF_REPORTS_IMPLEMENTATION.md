# Реализация генерации PDF отчетов

## Обзор

Добавлен функционал генерации PDF отчетов по заявкам с интеграцией n8n Report Management API.

## Что реализовано

### 1. База данных

#### Миграции:
- `2026_01_19_000001_add_pdf_reports_enabled_to_tariff_plans_table.php` - добавляет поле `pdf_reports_enabled` в таблицу `tariff_plans`
- `2026_01_19_000002_add_pdf_fields_to_reports_table.php` - расширяет таблицу `reports` полями для PDF

#### Новые поля в `tariff_plans`:
- `pdf_reports_enabled` (boolean) - включена ли генерация PDF для тарифа

#### Новые поля в `reports`:
- `report_type` (string) - тип отчета (request | combined)
- `callback_url` (string) - URL для webhook
- `error_code` (string) - код ошибки
- `error_message` (text) - сообщение об ошибке
- `pdf_content` (longblob) - содержимое PDF
- `pdf_expires_at` (timestamp) - срок истечения PDF (7 дней)

### 2. Модели

#### TariffPlan
- Добавлено поле `pdf_reports_enabled` в `$fillable` и `$casts`
- Метод `canGeneratePdfReports()` - проверка доступности PDF отчетов

#### Report
- Обновлены `$fillable` и `$casts` для поддержки новых полей

### 3. Сервисы

#### N8nReportService (`app/Services/N8nReportService.php`)
- `generateReport($requestIds, $userId, $options)` - запуск генерации PDF
- `getReportStatus($reportId)` - получение статуса генерации (fallback)

### 4. Контроллеры

#### WebhookController (`app/Http/Controllers/Api/WebhookController.php`)
- `pdfReportReady()` - обработка webhook от n8n с готовым PDF

#### UserRequestController
- `generatePdfReport($id)` - запуск генерации PDF для пользователя
- `downloadPdfReport($id)` - скачивание готового PDF

#### ManageRequestController (админ)
- `generatePdfReport($id)` - запуск генерации PDF для админа
- `downloadPdfReport($id)` - скачивание готового PDF

### 5. Маршруты

#### API (`routes/api.php`):
```php
Route::post('/webhooks/report-ready-pdf', [WebhookController::class, 'pdfReportReady']);
```

#### Пользователи (`routes/web.php`):
```php
Route::post('/cabinet/my/requests/{id}/generate-pdf', [UserRequestController::class, 'generatePdfReport']);
Route::get('/cabinet/my/requests/{id}/download-pdf', [UserRequestController::class, 'downloadPdfReport']);
```

#### Админ (`routes/web.php`):
```php
Route::post('/manage/manage-requests/{id}/generate-pdf', [ManageRequestController::class, 'generatePdfReport']);
Route::get('/manage/manage-requests/{id}/download-pdf', [ManageRequestController::class, 'downloadPdfReport']);
```

## Workflow

1. **Запуск генерации:**
   - Пользователь/админ нажимает кнопку "Сгенерировать PDF"
   - Проверяется тариф (для пользователя)
   - Отправляется запрос в n8n через `N8nReportService`
   - Создается запись в таблице `reports` со статусом `generating`

2. **Обработка в n8n:**
   - n8n получает запрос
   - Собирает данные по заявкам
   - Генерирует PDF
   - Отправляет webhook на `/api/webhooks/report-ready-pdf`

3. **Получение результата:**
   - Laravel получает webhook
   - Декодирует PDF из base64
   - Сохраняет в БД и файловой системе
   - Устанавливает срок истечения (7 дней)
   - Обновляет статус на `ready`

4. **Скачивание:**
   - Пользователь нажимает "Скачать PDF"
   - Проверяется срок истечения
   - Отдается PDF файл

## Настройка

### Конфигурация n8n

Добавьте в `config/services.php`:
```php
'n8n' => [
    'webhook_url' => env('N8N_WEBHOOK_URL', 'https://liftway.app.n8n.cloud/webhook'),
    'report_auth_token' => env('N8N_REPORT_AUTH_TOKEN', env('N8N_AUTH_TOKEN_2')),
],
```

Добавьте в `.env`:
```
N8N_REPORT_AUTH_TOKEN=your_token_here
```

### Настройка тарифного плана

В админке перейдите в `/manage/tariff-plans` и установите флаг `pdf_reports_enabled` для тарифов, которым доступна генерация PDF.

## Следующие шаги

1. **Обновить интерфейс настройки тарифных планов** - добавить чекбокс для `pdf_reports_enabled`
2. **Добавить кнопки генерации PDF** на страницы отчетов:
   - `/cabinet/my/requests/{id}/report` (для пользователей)
   - `/manage/manage-requests/{id}/report` (для админа)
3. **Запустить миграции:**
   ```bash
   php artisan migrate
   ```
4. **Настроить n8n workflow** для Report Management API

## Техническая документация

API спецификация находится в файле `report_management_api_spec.md`
