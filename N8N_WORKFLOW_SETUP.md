# Настройка n8n Workflow для Sender Management

## Проблема

n8n workflow получает запросы от Laravel (HTTP 200), но **возвращает пустой ответ**. Это приводит к ошибке "Server returned non-JSON response".

## Причина

В n8n workflow отсутствует node **"Respond to Webhook"** который должен возвращать JSON данные обратно в Laravel.

---

## Решение: Пошаговая настройка n8n Workflow

### 1. Базовая структура workflow

```
[Webhook] → [Switch (по action)] → [Обработка данных] → [Respond to Webhook]
```

### 2. Настройка Webhook Node

1. Добавьте node **"Webhook"**
2. Настройте:
   - **HTTP Method**: POST
   - **Path**: `sender-management`
   - **Authentication**: Header Auth
     - Header Name: `X-Auth-Token`
     - Header Value: `iqot_sender_api_2024...` (ваш токен)
   - **Response Mode**: "When Last Node Finishes"

### 3. Добавьте Switch Node

После Webhook добавьте **Switch** node для маршрутизации по `action`:

```javascript
// Conditions
{{ $json.body.action === 'get_available_emails' }}
{{ $json.body.action === 'get_email_templates' }}
{{ $json.body.action === 'get_user_sender' }}
{{ $json.body.action === 'create_sender' }}
{{ $json.body.action === 'update_sender' }}
{{ $json.body.action === 'deactivate_sender' }}
```

### 4. Обработка каждого action

#### Action: get_available_emails

**Цель**: Вернуть список свободных резервных email

**Пример обработки:**
```javascript
// Code node или HTTP Request к вашей БД
// Получаем список свободных email из таблицы reserved_emails
// где is_assigned = false
```

**Respond to Webhook:**
```json
{
  "success": true,
  "emails": [
    {
      "id": 1,
      "email": "sender1@iqot.ru"
    },
    {
      "id": 2,
      "email": "sender2@iqot.ru"
    }
  ]
}
```

#### Action: get_email_templates

**Цель**: Вернуть список шаблонов писем

**Respond to Webhook:**
```json
{
  "success": true,
  "templates": [
    {
      "id": 1,
      "name": "Стандартный шаблон"
    },
    {
      "id": 2,
      "name": "Расширенный шаблон"
    }
  ]
}
```

#### Action: get_user_sender

**Входные данные:**
```json
{
  "action": "get_user_sender",
  "user_id": 123
}
```

**Respond to Webhook:**
```json
{
  "success": true,
  "sender": {
    "id": 1,
    "email": "sender@iqot.ru",
    "sender_name": "Иван Петров",
    "sender_full_name": "Петров Иван Сергеевич",
    "phone": "+79001234567",
    "is_active": true,
    "is_verified": true,
    "template_id": 1
  },
  "organization": {
    "id": 1,
    "name": "ООО Компания",
    "inn": "1234567890",
    "kpp": "123456789",
    "legal_address": "г. Москва",
    "contact_person": "Иван Петров",
    "phone": "+79001234567",
    "email": "info@company.ru"
  }
}
```

**Если sender не найден:**
```json
{
  "success": true,
  "sender": null,
  "organization": null
}
```

#### Action: create_sender

**Входные данные:**
```json
{
  "action": "create_sender",
  "data": {
    "user_id": 123,
    "reserved_email_id": 5,
    "template_id": 1,
    "sender_name": "Иван Петров",
    "sender_full_name": "Петров Иван Сергеевич",
    "phone": "+79001234567",
    "organization": {
      "name": "ООО Компания",
      "inn": "1234567890",
      "kpp": "123456789",
      "legal_address": "г. Москва",
      "contact_person": "Иван Петров",
      "phone": "+79001234567",
      "email": "info@company.ru"
    }
  }
}
```

**Respond to Webhook:**
```json
{
  "success": true,
  "sender_id": 123,
  "client_organization_id": 456,
  "message": "Sender created successfully"
}
```

#### Action: update_sender

**Входные данные:**
```json
{
  "action": "update_sender",
  "sender_id": 123,
  "data": {
    "sender_name": "Новое имя",
    "organization": { ... }
  }
}
```

**Respond to Webhook:**
```json
{
  "success": true,
  "message": "Sender updated successfully"
}
```

#### Action: deactivate_sender

**Входные данные:**
```json
{
  "action": "deactivate_sender",
  "sender_id": 123
}
```

**Respond to Webhook:**
```json
{
  "success": true,
  "message": "Sender deactivated successfully"
}
```

---

## 5. ВАЖНО: Respond to Webhook Node

**Для КАЖДОГО action** в конце должен быть node **"Respond to Webhook"**:

### Настройка Respond to Webhook:

1. **Respond With**: JSON
2. **Response Code**: 200
3. **Response Body**:
   ```json
   {{ $json }}
   ```
   или
   ```json
   {
     "success": true,
     "data": {{ $json }}
   }
   ```

### Пример настройки для get_available_emails:

```
[Webhook]
  → [Switch по action]
    → [get_available_emails]
      → [MySQL Query: SELECT * FROM reserved_emails WHERE is_assigned = 0]
        → [Function: Format Response]
          → [Respond to Webhook: { success: true, emails: [...] }]
```

---

## 6. Обработка ошибок

Для каждого пути добавьте обработку ошибок:

```json
{
  "success": false,
  "error": "Database connection failed",
  "message": "Не удалось подключиться к базе данных"
}
```

---

## 7. Тестирование

После настройки протестируйте через:

1. **curl запрос:**
```bash
curl -X POST https://liftway.app.n8n.cloud/webhook/sender-management \
  -H "Content-Type: application/json" \
  -H "X-Auth-Token: iqot_sender_api_2024..." \
  -d '{"action": "get_available_emails"}'
```

**Ожидаемый результат:**
```json
{
  "success": true,
  "emails": [...]
}
```

2. **Laravel тест:** `/manage/sender/test-connection`

---

## Чеклист

- [ ] Webhook node настроен с Header Auth
- [ ] Switch node маршрутизирует по action
- [ ] Для каждого action есть обработчик
- [ ] В КОНЦЕ каждого пути есть "Respond to Webhook"
- [ ] Все ответы возвращают JSON с полем "success"
- [ ] Workflow активирован (включён)
- [ ] Протестировано через curl
- [ ] Протестировано через Laravel

---

## Минимальный рабочий пример

Для быстрого старта можно создать заглушки:

```
[Webhook]
  → [Function Node]:
      const action = $input.item.json.body.action;

      if (action === 'get_available_emails') {
        return {
          success: true,
          emails: [
            { id: 1, email: 'test1@iqot.ru' },
            { id: 2, email: 'test2@iqot.ru' }
          ]
        };
      }

      if (action === 'get_email_templates') {
        return {
          success: true,
          templates: [
            { id: 1, name: 'Default Template' }
          ]
        };
      }

      if (action === 'get_user_sender') {
        return {
          success: true,
          sender: null,
          organization: null
        };
      }

      return { success: false, message: 'Unknown action' };

  → [Respond to Webhook]
```

Это позволит быстро проверить работу интеграции, а затем заменить на реальную логику с БД.
