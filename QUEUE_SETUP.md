# Настройка Queue Worker для асинхронной отправки рассылок

## Обзор изменений

Система email рассылок теперь использует **Laravel Queue** для асинхронной отправки писем. Это решает проблему зависания админки при больших рассылках.

## Преимущества

✅ **Не блокирует интерфейс** - admin может сразу продолжить работу после запуска рассылки
✅ **Масштабируемость** - поддержка рассылок на 100, 1000, 10000+ получателей
✅ **Автоматический retry** - 3 попытки отправки при ошибках
✅ **Мониторинг прогресса** - live обновление прогресс-бара
✅ **Задержка между письмами** - настраиваемая через delay

## Что изменилось

### 1. Новые файлы
- `app/Jobs/SendCampaignEmail.php` - Job для отправки одного письма
- `database/migrations/2026_01_28_000000_create_jobs_table.php` - таблицы для queue
- `QUEUE_SETUP.md` - этот файл

### 2. Измененные файлы
- `app/Http/Controllers/Admin/CampaignController.php` - метод `start()` теперь добавляет jobs в очередь
- `app/Http/Controllers/Admin/CampaignController.php` - добавлен метод `progress()` для API
- `routes/web.php` - добавлен роут `/campaigns/{id}/progress`
- `resources/views/admin/campaigns/show.blade.php` - прогресс-бар и auto-refresh

## Установка на сервере

### Шаг 1: Запустить миграции

```bash
php artisan migrate
```

Будут созданы таблицы:
- `jobs` - очередь задач
- `job_batches` - батчи задач
- `failed_jobs` - неудавшиеся задачи

### Шаг 2: Запустить Queue Worker

**Вариант A: Для разработки (временно)**

```bash
php artisan queue:work --queue=default --tries=3 --timeout=120
```

**Вариант B: Для продакшена (с Supervisor)**

Создайте файл `/etc/supervisor/conf.d/iqot-queue-worker.conf`:

```ini
[program:iqot-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/iqot-platform/artisan queue:work database --queue=default --tries=3 --timeout=120 --sleep=3 --max-jobs=1000 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/iqot-queue-worker.log
stopwaitsecs=3600
```

Замените:
- `/path/to/iqot-platform` на реальный путь
- `www-data` на пользователя веб-сервера
- `numprocs=2` - количество параллельных воркеров (2-4 для начала)

Запустите:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start iqot-queue-worker:*
```

Проверка статуса:

```bash
sudo supervisorctl status iqot-queue-worker:*
```

### Шаг 3: Настроить .env (если нужно)

По умолчанию используется `database` driver для queue:

```env
QUEUE_CONNECTION=database
```

Для высоких нагрузок можно использовать Redis:

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## Как это работает

### 1. Запуск рассылки

Когда admin нажимает "Запустить рассылку":

```php
// CampaignController::start()
$campaign->update(['status' => 'sending']);

foreach ($recipients as $recipient) {
    SendCampaignEmail::dispatch($recipient->id, $campaign->id)
        ->delay(now()->addSeconds($currentDelay));

    $currentDelay += $delaySeconds; // Задержка между письмами
}
```

- Статус меняется на `sending`
- Для каждого получателя создается отдельный Job
- Jobs добавляются с задержкой (delay_seconds)
- Admin сразу видит сообщение "Рассылка запущена!"

### 2. Обработка Jobs

Queue Worker берет jobs из таблицы `jobs` и выполняет:

```php
// SendCampaignEmail::handle()
- Подключается к SMTP
- Рендерит HTML с данными получателя
- Встраивает изображения как CID
- Отправляет письмо
- Помечает получателя как sent/failed
- Обновляет счетчики campaign
```

**Параметры Job:**
- `timeout = 120` - максимум 2 минуты на письмо
- `tries = 3` - 3 попытки при ошибках
- `backoff = 60` - 60 секунд между попытками

### 3. Мониторинг прогресса

Страница `/manage/campaigns/{id}` автоматически:

```javascript
// Каждые 3 секунды
fetch('/manage/campaigns/{id}/progress')
  .then(data => {
    // Обновляет прогресс-бар
    // Обновляет счетчики
    // При завершении перезагружает страницу
  });
```

API возвращает:

```json
{
  "status": "sending",
  "total": 1000,
  "sent": 450,
  "failed": 2,
  "pending": 548,
  "percent_complete": 45.2,
  "is_completed": false
}
```

### 4. Завершение

Когда `pending = 0`:
- Статус меняется на `completed`
- `completed_at` = now()
- Страница автоматически перезагружается
- Показывается финальная статистика

## Мониторинг и отладка

### Проверка очереди

```bash
# Количество задач в очереди
php artisan queue:monitor default

# Список неудавшихся задач
php artisan queue:failed

# Повторить неудавшуюся задачу
php artisan queue:retry <id>

# Повторить все неудавшиеся
php artisan queue:retry all

# Очистить неудавшиеся
php artisan queue:flush
```

### Логи

- **Queue worker**: `/var/log/iqot-queue-worker.log` (если используется Supervisor)
- **Laravel logs**: `storage/logs/laravel.log`
- **Job logs**: логи с префиксом `Campaign email sent` или `Campaign email failed`

### Проблемы и решения

**Проблема: Worker не запускается**

```bash
# Проверить ошибки
php artisan queue:work --verbose

# Проверить права
ls -la storage/logs
chmod -R 775 storage
```

**Проблема: Jobs не выполняются**

```bash
# Проверить таблицу jobs
SELECT * FROM jobs LIMIT 10;

# Проверить настройки
php artisan config:cache
php artisan queue:restart
```

**Проблема: Слишком медленно**

```ini
# Увеличить количество воркеров в supervisor
numprocs=4

# Или запустить несколько вручную
php artisan queue:work --queue=default &
php artisan queue:work --queue=default &
```

## Производительность

### Примерные скорости

| Получателей | Delay (сек) | Воркеры | Примерное время |
|-------------|-------------|---------|-----------------|
| 100         | 2           | 1       | ~3-4 минуты     |
| 1000        | 2           | 2       | ~16-20 минут    |
| 10000       | 1           | 4       | ~40-50 минут    |

### Оптимизация

1. **Уменьшить delay** - но не ниже 1 сек (риск бана от SMTP)
2. **Увеличить воркеры** - 2-4 оптимально для начала
3. **Использовать Redis** вместо database queue
4. **Батчи** - отправлять по 1000 писем за раз

## Откат к синхронной отправке

Если нужно вернуться к старому способу:

1. Остановить queue worker
2. В `.env` установить:

```env
QUEUE_CONNECTION=sync
```

3. Очистить кеш:

```bash
php artisan config:cache
```

**Примечание**: Синхронная отправка НЕ рекомендуется для рассылок больше 20 писем!

## Дата обновления
28 января 2026

## Автор
Реализовано с помощью Claude Code
