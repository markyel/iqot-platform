# Решение проблемы 404 для /manage/manage-requests

## Причина

Laravel кэширует маршруты. После добавления новых маршрутов нужно очистить кэш.

## Решение

### Способ 1: Через батник (Windows)

Запустите файл `clear-cache.bat` который находится в корне проекта:

```bash
clear-cache.bat
```

### Способ 2: Вручную через командную строку

Откройте командную строку в папке проекта и выполните:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### Способ 3: Через браузер (если есть доступ к Tinker)

Если у вас есть веб-интерфейс для Artisan команд:

1. Перейдите в раздел "Console" или "Artisan"
2. Выполните команды из способа 2

---

## Проверка

После очистки кэша:

1. **Обновите страницу** в браузере (Ctrl+F5)
2. Перейдите на `/manage/manage-requests`
3. Вы должны увидеть страницу со списком заявок

---

## Если проблема сохраняется

### 1. Проверьте, что вы авторизованы как администратор

Убедитесь, что:
- Вы залогинены в системе
- У вашего пользователя `is_admin = 1` в БД

Проверить можно в phpMyAdmin:

```sql
SELECT id, email, is_admin FROM users WHERE email = 'ваш@email.com';
```

Если `is_admin = 0`, обновите:

```sql
UPDATE users SET is_admin = 1 WHERE email = 'ваш@email.com';
```

### 2. Проверьте, что файлы созданы

Убедитесь, что существуют следующие файлы:

- `app/Http/Controllers/Admin/ManageRequestController.php`
- `app/Services/N8nRequestService.php`
- `app/Models/ClientOrganization.php`
- `app/Models/Category.php`
- `app/Models/ProductType.php`
- `app/Models/ApplicationDomain.php`

### 3. Проверьте логи Laravel

Откройте `storage/logs/laravel.log` и посмотрите последние ошибки.

### 4. Проверьте, что маршруты зарегистрированы

Выполните команду:

```bash
php artisan route:list | findstr manage-requests
```

Вы должны увидеть что-то вроде:

```
GET|HEAD   manage/manage-requests .................. admin.manage.requests.index
GET|HEAD   manage/manage-requests/create ........... admin.manage.requests.create
POST       manage/manage-requests .................. admin.manage.requests.store
GET|HEAD   manage/manage-requests/{id} ............. admin.manage.requests.show
GET|HEAD   manage/manage-requests/{id}/edit ........ admin.manage.requests.edit
PUT        manage/manage-requests/{id} ............. admin.manage.requests.update
POST       manage/manage-requests/{id}/cancel ...... admin.manage.requests.cancel
POST       manage/manage-requests/parse-text ....... admin.manage.requests.parse-text
```

Если маршрутов нет, значит есть синтаксическая ошибка в `routes/web.php`.

---

## Настройка переменных окружения

Не забудьте добавить в `.env`:

```env
# n8n API
N8N_WEBHOOK_URL=https://liftway.app.n8n.cloud/webhook
N8N_AUTH_TOKEN=ваш_токен_здесь
N8N_PARSE_AUTH_TOKEN=iqot_parse_api_2024_secret

# БД reports (если отличается от основной)
REPORTS_DB_HOST=127.0.0.1
REPORTS_DB_DATABASE=reports
REPORTS_DB_USERNAME=root
REPORTS_DB_PASSWORD=
```

**Важно:** Я вижу из вложенных файлов, что токен для парсинга: `iqot_parse_api_2024_secret`

После изменения `.env` снова выполните:

```bash
php artisan config:clear
```

---

## Быстрый тест

После всех действий проверьте:

1. Откройте браузер в режиме инкогнито (Ctrl+Shift+N)
2. Войдите в систему как администратор
3. Перейдите на `/manage/manage-requests`

Если всё работает - проблема решена! ✅

---

## Контакты для помощи

Если проблема не решается:

1. Проверьте логи: `storage/logs/laravel.log`
2. Проверьте логи веб-сервера (nginx/apache)
3. Убедитесь, что PHP может выполнять artisan команды
