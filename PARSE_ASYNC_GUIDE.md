# Инструкция по обновлению n8n workflow для асинхронного парсинга

## Проблема
Cloudflare обрывал соединение через 100 секунд (HTTP 524), из-за чего большие заявки не успевали распарситься.

## Решение
Реализована асинхронная архитектура:
1. Laravel отправляет задачу в n8n и сразу возвращает `task_id`
2. n8n обрабатывает задачу (сколько угодно времени)
3. n8n отправляет результат обратно на webhook
4. Фронтенд периодически проверяет статус через polling

## Что нужно изменить в n8n workflow

### 1. Убрать старую ноду "Respond to Webhook"
Старая нода:
```json
{
  "name": "Respond",
  "type": "n8n-nodes-base.respondToWebhook",
  "parameters": {
    "respondWith": "json",
    "responseBody": "={{ JSON.stringify($json) }}"
  }
}
```
**Удалить её!** Теперь мы не отвечаем сразу.

### 2. Добавить HTTP Request ноду для callback

Добавьте в конец workflow:

```json
{
  "name": "Send Result to Laravel",
  "type": "n8n-nodes-base.httpRequest",
  "parameters": {
    "method": "POST",
    "url": "={{ $('Webhook').item.json.callback_url }}",
    "authentication": "predefinedCredentialType",
    "nodeCredentialType": "httpHeaderAuth",
    "sendHeaders": true,
    "headerParameters": {
      "parameters": [
        {
          "name": "X-Auth-Token",
          "value": "={{ $credentials.n8nParseAuthToken }}"
        }
      ]
    },
    "sendBody": true,
    "bodyParameters": {
      "parameters": [
        {
          "name": "task_id",
          "value": "={{ $('Webhook').item.json.task_id }}"
        },
        {
          "name": "success",
          "value": "={{ $json.success }}"
        },
        {
          "name": "items",
          "value": "={{ JSON.stringify($json.items) }}"
        },
        {
          "name": "has_new_classifications",
          "value": "={{ $json.has_new_classifications }}"
        }
      ]
    }
  }
}
```

### 3. Обработка ошибок

Если парсинг провалился, отправьте:
```json
{
  "task_id": "{{ task_id из webhook }}",
  "success": false,
  "message": "Описание ошибки"
}
```

## Входные данные от Laravel

Теперь webhook получает:
```json
{
  "text": "текст заявки",
  "task_id": "parse_uuid-xxx-xxx",
  "callback_url": "https://iqot.ru/api/webhooks/parse-callback"
}
```

## Выходные данные для callback

### Успешный парсинг:
```json
{
  "task_id": "parse_uuid-xxx-xxx",
  "success": true,
  "items": [
    {
      "name": "Кнопка вызова",
      "brand": "OTIS",
      "article": "AAA123",
      "quantity": 10,
      "unit": "шт",
      "category": "Кнопки и индикаторы",
      "product_type_id": 1,
      "product_type_name": "Кнопки вызова",
      "domain_id": 1,
      "domain_name": "Лифты"
    }
  ],
  "has_new_classifications": false,
  "items_count": 1
}
```

### Ошибка парсинга:
```json
{
  "task_id": "parse_uuid-xxx-xxx",
  "success": false,
  "message": "Не удалось распознать текст"
}
```

## Маршруты Laravel

- **Webhook callback**: `POST /api/webhooks/parse-callback`
- **Проверка статуса**: `POST /manage/manage-requests/parse-status`

## База данных

Создана таблица `parse_tasks`:
```sql
CREATE TABLE parse_tasks (
  id BIGINT PRIMARY KEY,
  task_id VARCHAR(100) UNIQUE,
  user_id BIGINT,
  text TEXT,
  status ENUM('pending', 'processing', 'completed', 'failed'),
  result JSON,
  error_message TEXT,
  started_at TIMESTAMP,
  completed_at TIMESTAMP,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

## Миграция на продакшн

1. Запустить миграцию: `php artisan migrate`
2. Обновить n8n workflow (удалить Respond, добавить HTTP Request)
3. Проверить работу на тестовой заявке

## Преимущества

✅ Нет лимита Cloudflare в 100 секунд
✅ Можно обрабатывать заявки любого размера
✅ Пользователь видит прогресс ("Обрабатываю...")
✅ Если n8n упадет, задача останется в БД
✅ Можно добавить retry механизм
