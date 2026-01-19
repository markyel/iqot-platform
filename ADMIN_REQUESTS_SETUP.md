# Настройка функционала создания заявок в админке

## Реализованный функционал

Система управления заявками через n8n API с поддержкой:
- ✅ **AI-парсинг** текста заявок
- ✅ **Анонимные** и **именные** заявки
- ✅ **Автоматическая рассылка** каждые 60 минут
- ✅ **Полный CRUD** для заявок
- ✅ **Интеграция** с БД reports через n8n

---

## Установка и настройка

### 1. Добавьте переменные окружения

Откройте файл `.env` и добавьте:

```env
# n8n Webhook URL
N8N_WEBHOOK_URL=https://liftway.app.n8n.cloud/webhook
N8N_AUTH_TOKEN=your_main_auth_token_here
N8N_PARSE_AUTH_TOKEN=your_parse_auth_token_here
```

**Где взять токены:**
- `N8N_AUTH_TOKEN` - токен для основного API (request-management)
- `N8N_PARSE_AUTH_TOKEN` - токен для AI-парсинга (parse-request endpoint)

### 2. Настройте БД reports

Убедитесь, что в `.env` настроено подключение к БД reports:

```env
# Основная БД (iqot)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=iqot
DB_USERNAME=root
DB_PASSWORD=your_password

# БД reports (n8n)
REPORTS_DB_HOST=127.0.0.1
REPORTS_DB_PORT=3306
REPORTS_DB_DATABASE=reports
REPORTS_DB_USERNAME=root
REPORTS_DB_PASSWORD=your_password
```

### 3. Проверьте конфигурацию

Файл `config/database.php` должен содержать подключение `reports`:

```php
'reports' => [
    'driver' => 'mysql',
    'host' => env('REPORTS_DB_HOST', '127.0.0.1'),
    'port' => env('REPORTS_DB_PORT', '3306'),
    'database' => env('REPORTS_DB_DATABASE', 'reports'),
    'username' => env('REPORTS_DB_USERNAME', 'root'),
    'password' => env('REPORTS_DB_PASSWORD', ''),
    // ... остальные настройки
],
```

### 4. Очистите кэш конфигурации

```bash
php artisan config:clear
php artisan cache:clear
```

---

## Структура таблиц в БД reports

Убедитесь, что в БД `reports` есть следующие таблицы:

### client_organizations
```sql
CREATE TABLE `client_organizations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `inn` varchar(12) DEFAULT NULL,
  `kpp` varchar(9) DEFAULT NULL,
  `address` text,
  `contact_person` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
```

### categories
```sql
CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
```

### product_types
```sql
CREATE TABLE `product_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `parent_id` int DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `is_leaf` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`)
);
```

### application_domains
```sql
CREATE TABLE `application_domains` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
```

---

## Использование

### Доступ к функционалу

После авторизации как администратор (`is_admin = true`), перейдите по адресу:

```
https://iqot.ru/manage/manage-requests
```

### Основные URL

| Страница | URL | Описание |
|----------|-----|----------|
| Список заявок | `/manage/manage-requests` | Все заявки из n8n |
| Создать заявку | `/manage/manage-requests/create` | Форма создания с AI-парсингом |
| Просмотр | `/manage/manage-requests/{id}` | Детали заявки |
| Редактирование | `/manage/manage-requests/{id}/edit` | Только для draft/new |

---

## Создание заявки

### Шаг 1: AI-парсинг (опционально)

Введите текст заявки в свободной форме:

```
Кнопка вызова OTIS AAA123 10шт
Датчик уровня KONE DEF456 5шт
Кабель лифтовой 100м
```

Нажмите **"Распознать позиции"** - AI автоматически:
- Извлечёт названия позиций
- Определит бренды и артикулы
- Распознает количество и единицы измерения
- Классифицирует по категориям и типам

### Шаг 2: Выбор типа заявки

**Анонимная заявка:**
- Без привязки к клиенту
- Sender выбирается автоматически из общего пула

**Именная заявка:**
- Для конкретной организации
- Укажите:
  - Название компании (обязательно)
  - Контактное лицо
  - Email
  - Телефон
  - ID существующей организации (опционально)

### Шаг 3: Статус

**draft** (Черновик):
- Заявка сохраняется, но **НЕ** попадает в рассылку
- Можно редактировать
- Подходит для тестирования

**new** (В работу):
- Заявка **автоматически** попадёт в очередь на рассылку
- Рассылка происходит **каждые 60 минут**
- Воркфлоу n8n сам подхватит заявку

### Шаг 4: Позиции заявки

Заполните таблицу позиций:
- **Название** (обязательно)
- **Количество** (обязательно)
- **Единица измерения** (обязательно)
- Бренд, артикул (опционально)
- Категория (обязательно)
- Тип товара, область применения (опционально для классификации)

Можно:
- Добавить позицию вручную (кнопка "+ Добавить позицию")
- Удалить позицию (кнопка "×")

### Шаг 5: Заметки

Любые комментарии, особые условия, примечания к заявке.

---

## Жизненный цикл заявки

```
draft → new → queued_for_sending → emails_sent → collecting →
responses_received → completed
```

### Статусы

| Статус | Описание | Можно редактировать |
|--------|----------|---------------------|
| `draft` | Черновик, не в рассылке | ✅ Да |
| `new` | В очереди на рассылку | ✅ Да |
| `active` | Активна | ❌ Нет |
| `queued_for_sending` | В очереди на отправку | ❌ Нет |
| `emails_sent` | Письма отправлены | ❌ Нет |
| `collecting` | Сбор ответов | ❌ Нет |
| `responses_received` | Ответы получены | ❌ Нет |
| `completed` | Завершена | ❌ Нет |
| `cancelled` | Отменена | ❌ Нет |

---

## API n8n

### Эндпоинты

#### 1. Создание заявки

**POST** `https://liftway.app.n8n.cloud/webhook/request-management`

**Headers:**
```
Content-Type: application/json
X-Auth-Token: your_token
```

**Body (анонимная):**
```json
{
  "action": "create_request",
  "source": "web_admin",
  "status": "new",
  "is_customer_request": false,
  "items": [
    {
      "name": "Кнопка вызова OTIS AAA123",
      "quantity": 10,
      "unit": "шт",
      "brand": "OTIS",
      "article": "AAA123",
      "category": "Кнопки",
      "product_type_id": 701,
      "domain_id": 1
    }
  ],
  "notes": "Срочно"
}
```

**Body (именная):**
```json
{
  "action": "create_request",
  "source": "web_admin",
  "status": "new",
  "is_customer_request": true,
  "client_organization_id": 45,
  "customer_company": "ООО Лифтсервис",
  "customer_contact_person": "Иванов И.И.",
  "customer_email": "ivanov@liftservice.ru",
  "customer_phone": "+7 999 123-45-67",
  "items": [...],
  "notes": "Для клиента"
}
```

**Response:**
```json
{
  "success": true,
  "request_id": 156,
  "request_number": "REQ-20260112-4521",
  "status": "new",
  "total_items": 1,
  "message": "Заявка создана. Рассылка начнётся автоматически."
}
```

#### 2. AI-парсинг текста

**POST** `https://liftway.app.n8n.cloud/webhook/parse-request`

**Headers:**
```
Content-Type: application/json
X-Auth-Token: your_parse_token
```

**Body:**
```json
{
  "text": "Кнопка вызова OTIS 10шт, датчик SALSIS 5шт"
}
```

**Response:**
```json
{
  "success": true,
  "is_purchase_request": true,
  "confidence": 0.95,
  "items": [
    {
      "index": 1,
      "name": "Кнопка вызова OTIS",
      "quantity": 10,
      "unit": "шт",
      "brand": "OTIS",
      "category": "Кнопки",
      "product_type_id": 701,
      "domain_id": 1
    },
    {
      "index": 2,
      "name": "Датчик SALSIS",
      "quantity": 5,
      "unit": "шт",
      "brand": "SALSIS",
      "category": "Датчики",
      "product_type_id": 201,
      "domain_id": 1
    }
  ]
}
```

#### 3. Получение заявки

**POST** `https://liftway.app.n8n.cloud/webhook/request-management`

**Body:**
```json
{
  "action": "get_request",
  "request_id": 156
}
```

#### 4. Список заявок

**POST** `https://liftway.app.n8n.cloud/webhook/request-management`

**Body:**
```json
{
  "action": "list_requests",
  "filters": {
    "status": "new",
    "is_customer_request": true,
    "search": "ООО Лифтсервис",
    "date_from": "2026-01-01",
    "date_to": "2026-01-31"
  },
  "sort": {
    "created_at": "desc"
  },
  "pagination": {
    "page": 1,
    "per_page": 20
  }
}
```

#### 5. Отмена заявки

**POST** `https://liftway.app.n8n.cloud/webhook/request-management`

**Body:**
```json
{
  "action": "cancel_request",
  "request_id": 156,
  "reason": "Отменена по запросу клиента"
}
```

---

## Тестирование

### 1. Проверка подключения к n8n

```bash
php artisan tinker
```

```php
$service = app(\App\Services\N8nRequestService::class);

// Тест парсинга
$result = $service->parseRequestText("Кнопка OTIS 10шт");
dd($result);
```

### 2. Создание тестовой заявки

Создайте заявку со статусом **draft** - она НЕ попадёт в рассылку:

1. Перейдите в `/manage/manage-requests/create`
2. Выберите статус "Черновик"
3. Добавьте 1-2 позиции
4. Сохраните
5. Проверьте в `/manage/manage-requests`

### 3. Проверка справочников

```bash
php artisan tinker
```

```php
// Категории
\App\Models\Category::all();

// Типы товаров
\App\Models\ProductType::where('is_leaf', true)->get();

// Области применения
\App\Models\ApplicationDomain::all();

// Организации клиентов
\App\Models\ClientOrganization::all();
```

---

## Частые вопросы

### Q: Как работает автоматическая рассылка?

**A:** Воркфлоу n8n запускается **каждые 60 минут** и подхватывает все заявки со статусом `new`. Никаких дополнительных действий не требуется - достаточно создать заявку со статусом "В работу".

### Q: Можно ли редактировать заявку после отправки?

**A:** Нет. Редактирование доступно только для заявок в статусе `draft` или `new`. После начала обработки заявку можно только отменить (если поддерживается воркфлоу).

### Q: Где хранятся данные заявок?

**A:** Данные хранятся в БД `reports` (n8n). Laravel работает с ними через API и модели с `connection = 'reports'`.

### Q: Как добавить новую категорию/тип товара?

**A:** Добавьте запись напрямую в БД `reports`:

```sql
-- Категория
INSERT INTO categories (name, sort_order, is_active)
VALUES ('Новая категория', 100, 1);

-- Тип товара
INSERT INTO product_types (name, is_leaf, sort_order, is_active)
VALUES ('Новый тип', 1, 100, 1);
```

### Q: Как узнать токены для n8n?

**A:** Обратитесь к администратору n8n. Токены настраиваются в воркфлоу n8n Cloud.

---

## Техническая поддержка

При возникновении проблем:

1. Проверьте логи Laravel: `storage/logs/laravel.log`
2. Проверьте переменные окружения: `php artisan config:show services.n8n`
3. Проверьте подключение к БД reports: `php artisan tinker` → `DB::connection('reports')->getPdo();`

---

## Changelog

- **2026-01-12**: Первая версия функционала создания заявок в админке
