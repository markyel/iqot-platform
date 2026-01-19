# Решение проблемы 403 от n8n

## Причина

Ошибка 403 означает проблему с аутентификацией при обращении к n8n API.

---

## Диагностика

### Шаг 1: Проверьте токен в .env

Откройте `.env` и убедитесь, что токен настроен:

```env
N8N_PARSE_AUTH_TOKEN=iqot_parse_api_2024_secret
```

### Шаг 2: Очистите кэш конфигурации

```bash
php artisan config:clear
```

### Шаг 3: Запустите тест аутентификации

```bash
php test-n8n-auth.php
```

Этот скрипт протестирует 5 разных способов аутентификации и покажет, какой работает.

---

## Возможные методы аутентификации n8n

n8n может использовать разные методы аутентификации для webhook:

### 1. Authorization: Bearer (рекомендуется)

```php
$headers = [
    'Authorization' => 'Bearer ' . $token,
];
```

### 2. X-Auth-Token

```php
$headers = [
    'X-Auth-Token' => $token,
];
```

### 3. Query параметр

```
https://liftway.app.n8n.cloud/webhook/parse-request?token=iqot_parse_api_2024_secret
```

### 4. В теле запроса

```json
{
  "text": "...",
  "auth_token": "iqot_parse_api_2024_secret"
}
```

### 5. Без аутентификации

Webhook может быть публичным (без аутентификации).

---

## Как проверить настройки n8n

### В n8n Cloud:

1. Откройте workflow с вебхуком `parse-request`
2. Найдите узел "Webhook"
3. Проверьте секцию "Authentication"
4. Посмотрите, какой метод выбран:
   - None (без аутентификации)
   - Header Auth
   - Basic Auth
   - Query Auth

### Пример настройки Header Auth в n8n:

```
Authentication: Header Auth
Header Name: Authorization
Header Value: Bearer {{$credentials.token}}
```

Или:

```
Authentication: Header Auth
Header Name: X-Auth-Token
Header Value: {{$credentials.token}}
```

---

## Решение в коде

Я обновил `app/Services/N8nRequestService.php` чтобы использовать:

```php
$headers['Authorization'] = 'Bearer ' . $this->parseAuthToken;
```

### Если это не работает:

**Вариант A: Попробуйте X-Auth-Token**

Измените в `app/Services/N8nRequestService.php` строку 94:

```php
// Было:
$headers['Authorization'] = 'Bearer ' . $this->parseAuthToken;

// Попробуйте:
$headers['X-Auth-Token'] = $this->parseAuthToken;
```

**Вариант B: Добавьте токен в URL**

Измените строку 99:

```php
// Было:
->post("{$this->baseUrl}/parse-request", [

// Попробуйте:
->post("{$this->baseUrl}/parse-request?token={$this->parseAuthToken}", [
```

**Вариант C: Без аутентификации**

Если webhook публичный, закомментируйте строки 93-95:

```php
// if ($this->parseAuthToken) {
//     $headers['Authorization'] = 'Bearer ' . $this->parseAuthToken;
// }
```

---

## Проверка в Postman/curl

### Тест с Bearer:

```bash
curl -X POST https://liftway.app.n8n.cloud/webhook/parse-request \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer iqot_parse_api_2024_secret" \
  -d '{"text":"Кнопка OTIS 10шт"}'
```

### Тест с X-Auth-Token:

```bash
curl -X POST https://liftway.app.n8n.cloud/webhook/parse-request \
  -H "Content-Type: application/json" \
  -H "X-Auth-Token: iqot_parse_api_2024_secret" \
  -d '{"text":"Кнопка OTIS 10шт"}'
```

### Тест без токена:

```bash
curl -X POST https://liftway.app.n8n.cloud/webhook/parse-request \
  -H "Content-Type: application/json" \
  -d '{"text":"Кнопка OTIS 10шт"}'
```

Посмотрите, какой вариант вернёт успешный ответ (200 OK).

---

## Альтернативное решение

Если parse-request недоступен или не настроен, можно **временно отключить** AI-парсинг:

### В create.blade.php

Скройте секцию AI-парсинга:

```blade
{{-- Временно отключено
<div class="card">
    <div class="card-header">AI-парсинг</div>
    ...
</div>
--}}
```

Пользователи смогут добавлять позиции вручную, нажав "+ Добавить позицию".

---

## Проверка логов n8n

Если у вас есть доступ к n8n Cloud:

1. Откройте Executions
2. Найдите последние выполнения workflow с parse-request
3. Посмотрите, почему возвращается 403
4. Проверьте настройки аутентификации в webhook

---

## Итоговый чеклист

- [ ] Токен добавлен в `.env`
- [ ] Кэш очищен: `php artisan config:clear`
- [ ] Запущен тест: `php test-n8n-auth.php`
- [ ] Проверены настройки webhook в n8n Cloud
- [ ] Метод аутентификации соответствует настройкам n8n
- [ ] Токен правильный (без опечаток)

---

## Контакты для помощи

Если ничего не помогает:

1. Проверьте логи Laravel: `storage/logs/laravel.log`
2. Проверьте, что URL правильный: `https://liftway.app.n8n.cloud/webhook/parse-request`
3. Свяжитесь с администратором n8n для проверки настроек webhook
4. Убедитесь, что IP вашего сервера не заблокирован в n8n

---

## Временный workaround

До решения проблемы с n8n, можно использовать ручное добавление позиций:

1. Открыть `/manage/manage-requests/create`
2. Нажать "+ Добавить позицию"
3. Заполнить поля вручную
4. Создать заявку

Функционал создания заявок работает независимо от AI-парсинга!
