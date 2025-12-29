# Настройка управления Sender для пользователей

## Выполненная работа

Реализован полный функционал управления персональными отправителями (Sender) для пользователей системы IQOT.

### Созданные файлы:

1. **Миграция**: `database/migrations/2025_12_29_000001_add_sender_fields_to_users_table.php`
   - Добавлены поля `sender_id` и `client_organization_id` в таблицу `users`

2. **Сервис**: `app/Services/N8nSenderService.php`
   - Методы для работы с n8n API:
     - `getAvailableEmails()` - получение свободных email
     - `getEmailTemplates()` - получение шаблонов
     - `getUserSenderWithOrganization()` - получение данных sender
     - `createSender()` - создание sender
     - `updateSender()` - обновление sender
     - `deactivateSender()` - деактивация sender

3. **Контроллер**: `app/Http/Controllers/Admin/UserSenderController.php`
   - CRUD операции для управления sender

4. **Blade-шаблоны**:
   - `resources/views/admin/users/sender/show.blade.php` - просмотр
   - `resources/views/admin/users/sender/create.blade.php` - создание
   - `resources/views/admin/users/sender/edit.blade.php` - редактирование

5. **Обновлённые файлы**:
   - `config/services.php` - добавлена конфигурация n8n sender
   - `routes/web.php` - добавлены маршруты
   - `app/Providers/AppServiceProvider.php` - зарегистрирован сервис
   - `resources/views/admin/users/index.blade.php` - добавлена кнопка "Sender"

---

## Инструкция по настройке

### 1. Настроить переменные окружения

Переменные уже добавлены в файл `.env`, но необходимо **заменить placeholder токена на реальный**:

```env
# N8n Sender Management
N8N_SENDER_WEBHOOK_URL=https://liftway.app.n8n.cloud/webhook/sender-management
N8N_SENDER_AUTH_TOKEN=your_real_secret_token_here  # ← ЗАМЕНИТЕ НА РЕАЛЬНЫЙ ТОКЕН
```

**⚠️ Важно:**
- Токен `__n8n_BLANK_VALUE_...` является placeholder и **не будет работать**
- Получите реальный токен из настроек n8n webhook
- Токен должен совпадать с тем, что настроено в n8n workflow для авторизации запросов

### 2. Выполнить миграцию

```bash
php artisan migrate
```

### 3. Настроить n8n Webhook

⚠️ **ВАЖНО**: n8n workflow должен возвращать JSON ответ! См. подробную инструкцию в **[N8N_WORKFLOW_SETUP.md](N8N_WORKFLOW_SETUP.md)**

В n8n необходимо создать webhook, который будет обрабатывать следующие действия (`action`):

- `get_available_emails` - вернуть список свободных резервных email
- `get_email_templates` - вернуть список шаблонов писем
- `get_user_sender` - получить sender и организацию пользователя (параметр: `user_id`)
- `create_sender` - создать sender (параметр: `data`)
- `update_sender` - обновить sender (параметры: `sender_id`, `data`)
- `deactivate_sender` - деактивировать sender (параметр: `sender_id`)

**Формат ответа для `get_available_emails`:**
```json
{
  "success": true,
  "emails": [
    {"id": 1, "email": "sender1@example.com"},
    {"id": 2, "email": "sender2@example.com"}
  ]
}
```

**Формат ответа для `get_email_templates`:**
```json
{
  "success": true,
  "templates": [
    {"id": 1, "name": "Шаблон 1"},
    {"id": 2, "name": "Шаблон 2"}
  ]
}
```

**Формат ответа для `get_user_sender`:**
```json
{
  "success": true,
  "sender": {
    "id": 123,
    "email": "sender@example.com",
    "sender_name": "Иван Петров",
    "sender_full_name": "Петров Иван Сергеевич",
    "phone": "+79001234567",
    "is_active": true,
    "is_verified": true,
    "template_id": 1
  },
  "organization": {
    "id": 456,
    "name": "ООО Компания",
    "inn": "1234567890",
    "kpp": "123456789",
    "legal_address": "г. Москва, ул. Примерная, д. 1",
    "contact_person": "Петров И.С.",
    "phone": "+79001234567",
    "email": "info@company.ru"
  }
}
```

**Формат ответа для `create_sender`:**
```json
{
  "success": true,
  "sender_id": 123,
  "client_organization_id": 456,
  "message": "Sender created successfully"
}
```

**Формат ответа для `update_sender` и `deactivate_sender`:**
```json
{
  "success": true,
  "message": "Operation completed"
}
```

### 4. Тестирование подключения

**Перед использованием функционала проверьте подключение к n8n:**

Откройте в браузере: `/manage/sender/test-connection`

Вы получите JSON-ответ с результатами тестирования:
```json
{
  "config": {
    "webhook_url": "https://...",
    "auth_token_set": true
  },
  "tests": {
    "get_available_emails": {
      "success": true/false,
      "response": {...}
    },
    "get_email_templates": {
      "success": true/false,
      "response": {...}
    }
  }
}
```

Если тесты не проходят, проверьте:
- n8n workflow активен
- URL webhook правильный
- Токен авторизации настроен корректно
- n8n может принимать входящие запросы

### 5. Доступ к функционалу

После успешного тестирования функционал доступен по следующим URL:

- `/manage/users` - список пользователей с кнопкой "Sender"
- `/manage/users/{user}/sender` - просмотр sender пользователя (показывает диагностику при ошибках)
- `/manage/users/{user}/sender/create` - создание sender
- `/manage/users/{user}/sender/edit` - редактирование sender

---

## Пошаговое тестирование

1. **Проверьте конфигурацию**: откройте `/manage/sender/test-connection`
2. **Убедитесь в успешности тестов**: оба теста должны показывать `"success": true`
3. **Добавьте тестовые данные в n8n**: резервный email и шаблон письма
4. **Перейдите в управление пользователями**: `/manage/users`
5. **Выберите пользователя**: нажмите кнопку "Sender"
6. **Создайте sender**: заполните форму и сохраните
7. **Проверьте функционал**: редактирование и деактивация

---

## Структура базы данных

### Таблица `users` (новые поля):

| Поле | Тип | Описание |
|------|-----|----------|
| `sender_id` | `unsignedInteger` | ID отправителя в системе n8n |
| `client_organization_id` | `unsignedInteger` | ID организации клиента |

---

## API запросы к n8n

Все запросы выполняются методом POST с заголовком:
```
X-Auth-Token: {N8N_SENDER_AUTH_TOKEN}
```

Тело запроса:
```json
{
  "action": "имя_действия",
  "параметры": "..."
}
```

---

## Безопасность

- Все запросы к n8n защищены токеном аутентификации
- Доступ к функционалу только для администраторов (middleware `admin`)
- Все входные данные валидируются перед отправкой в n8n

---

## Тестирование n8n webhook напрямую

Для отладки можно отправить тестовый запрос напрямую к n8n webhook:

### Пример curl-запроса:

```bash
curl -X POST https://liftway.app.n8n.cloud/webhook/sender-management \
  -H "Content-Type: application/json" \
  -H "X-Auth-Token: YOUR_TOKEN_HERE" \
  -d '{
    "action": "get_available_emails"
  }'
```

### Ожидаемый ответ:
```json
{
  "success": true,
  "emails": [
    {"id": 1, "email": "sender1@example.com"}
  ]
}
```

### Типичные ошибки:

- **401 Unauthorized** - неверный токен авторизации
- **404 Not Found** - неверный URL webhook или workflow не активен
- **500 Internal Server Error** - ошибка в n8n workflow
- **Timeout** - n8n не отвечает (проверьте доступность)

---

## Поддержка

При возникновении ошибок:

1. **Проверьте тестовый эндпоинт**: `/manage/sender/test-connection`
2. **Проверьте переменные окружения** `.env`:
   - `N8N_SENDER_WEBHOOK_URL` должен быть доступен
   - `N8N_SENDER_AUTH_TOKEN` не должен быть placeholder
3. **Проверьте логи Laravel**: `storage/logs/laravel.log`
4. **Тестируйте webhook напрямую** через curl (см. выше)
5. **Проверьте n8n**:
   - Workflow активен и запущен
   - Webhook node настроен правильно
   - Авторизация настроена (Header Auth с именем `X-Auth-Token`)
   - Workflow правильно обрабатывает поле `action`
