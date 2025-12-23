# IQOT Platform

**Intelligent Quotation & Offer Tracking** — платформа автоматизации сбора и анализа коммерческих предложений.

## Архитектура

```
┌─────────────────────────────────────────────────────┐
│                    IQOT Platform                     │
├─────────────────────────────────────────────────────┤
│                                                      │
│   n8n (автоматизация)                               │
│     ├── Рассылка запросов поставщикам               │
│     ├── Сбор и парсинг ответов (AI)                 │
│     ├── Генерация отчётов                           │
│     └── Webhooks ←→ Laravel API                     │
│                                                      │
│   Laravel (веб-интерфейс)                           │
│     ├── Лендинг (/)                                 │
│     ├── Личный кабинет (/cabinet)                   │
│     ├── Админ-панель (/admin) — Filament            │
│     └── API (/api) — для n8n                        │
│                                                      │
│   MySQL (общая база данных)                         │
│                                                      │
└─────────────────────────────────────────────────────┘
```

## Требования

- PHP 8.2+
- Composer
- MySQL 8.0+ (или существующая БД от n8n)
- Node.js 18+ (для сборки assets)

## Установка

### 1. Клонирование и зависимости

```bash
cd /var/www
git clone <repo> iqot
cd iqot

# PHP зависимости
composer install

# Node зависимости
npm install
```

### 2. Конфигурация

```bash
# Копируем конфиг
cp .env.example .env

# Генерируем ключ
php artisan key:generate
```

Отредактируйте `.env`:

```env
APP_URL=https://iqot.ai

# Ваша MySQL база (та же, что использует n8n)
DB_HOST=127.0.0.1
DB_DATABASE=iqot
DB_USERNAME=iqot_user
DB_PASSWORD=your_password

# n8n вебхуки
N8N_WEBHOOK_URL=http://localhost:5678/webhook
```

### 3. База данных

```bash
# Если новая база
php artisan migrate

# Создать админа
php artisan make:filament-user
```

### 4. Сборка assets

```bash
# Для разработки
npm run dev

# Для продакшена
npm run build
```

### 5. Права доступа

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## Запуск

### Разработка

```bash
php artisan serve
# Откройте http://localhost:8000
```

### Продакшен (Nginx)

```nginx
server {
    listen 80;
    server_name iqot.ai;
    root /var/www/iqot/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Структура проекта

```
iqot/
├── app/
│   ├── Http/Controllers/
│   │   ├── LandingController.php    # Лендинг
│   │   ├── CabinetController.php    # Личный кабинет
│   │   ├── ReportController.php     # Отчёты
│   │   └── Api/
│   │       └── WebhookController.php # n8n вебхуки
│   └── Models/
│       ├── User.php
│       ├── Request.php              # Заявки
│       ├── RequestItem.php          # Позиции
│       ├── Supplier.php             # Поставщики
│       ├── Offer.php                # КП
│       └── Report.php               # Отчёты
├── routes/
│   ├── web.php                      # Веб-роуты
│   └── api.php                      # API для n8n
├── resources/views/
│   ├── landing/                     # Лендинг
│   └── cabinet/                     # Личный кабинет
└── public/images/                   # Логотипы, иконки
```

## API для n8n

### Вебхуки (входящие от n8n)

```
POST /api/webhooks/request-update   # Обновление статуса заявки
POST /api/webhooks/offer-received   # Новое КП от поставщика
POST /api/webhooks/report-ready     # Отчёт готов
POST /api/webhooks/email-status     # Статус email
```

### Защищённые эндпоинты (с Sanctum токеном)

```
GET  /api/requests                  # Список заявок
POST /api/requests                  # Создать заявку
GET  /api/suppliers                 # Список поставщиков
GET  /api/reports/{id}/pdf          # Скачать PDF
```

## Filament Admin

Админ-панель доступна по адресу `/admin` для пользователей с `is_admin = true`.

```bash
# Создать админа
php artisan make:filament-user
```

## Очереди (для уведомлений)

```bash
# Запуск воркера
php artisan queue:work

# Или через Supervisor (продакшен)
```

## Лицензия

Proprietary © IQOT 2025
