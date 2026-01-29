# Email Validation для рассылок

Система валидации email адресов перед отправкой рассылок.

## Возможности

### Базовая валидация (без API)
- Проверка синтаксиса email
- Проверка MX записей домена
- Фильтрация одноразовых email (temporary/disposable)

### Интеграция с внешними API
- **NeverBounce** - https://neverbounce.com
- **EmailListVerify** - https://emaillistverify.com
- **DataValidation** - http://datavalidation.com

## Настройка

### 1. Добавьте в `.env` ключи API (опционально)

```env
# Провайдер для валидации (neverbounce, emaillistverify, datavalidation)
EMAIL_VALIDATION_PROVIDER=neverbounce

# API ключи (выберите один)
NEVERBOUNCE_API_KEY=your_api_key_here
EMAILLISTVERIFY_API_KEY=your_api_key_here
DATAVALIDATION_API_KEY=your_api_key_here
```

### 2. Запустите миграцию

```bash
php artisan migrate
```

## Использование

### Вариант 1: Проверка перед отправкой (автоматически)

Валидация происходит автоматически при отправке каждого письма в `SendCampaignEmail` Job.

```bash
# Просто запустите рассылку как обычно
php artisan campaign:send 1
```

### Вариант 2: Предварительная проверка всех адресов

```bash
# Проверить все email в рассылке до отправки
php artisan campaign:validate 1

# С указанием провайдера
php artisan campaign:validate 1 --provider=neverbounce
```

**Преимущества предварительной проверки:**
- Можно сразу увидеть статистику валидных/невалидных email
- Быстрее отправка (не нужно проверять во время отправки)
- Невалидные email сразу помечаются как failed

## Как это работает

### 1. Базовая валидация (всегда включена)

```php
$validator = app(EmailValidationService::class);

$result = $validator->validate('test@example.com');
// [
//     'valid' => true,
//     'reason' => null,
//     'provider' => 'basic'
// ]
```

Проверяет:
- Корректность синтаксиса email
- Существование MX записей домена
- Не является ли email одноразовым

### 2. Валидация через API (если настроено)

```php
// Использует провайдер из config
$result = $validator->validate('test@example.com');

// Или явно указываем провайдера
$result = $validator->validate('test@example.com', 'neverbounce');
```

### 3. Кеширование результатов

Результаты проверки кешируются на 30 дней, чтобы:
- Не проверять один email дважды
- Сэкономить на API запросах
- Ускорить отправку

```php
// Очистить кеш для email
$validator->clearCache('test@example.com');
```

### 4. Массовая проверка

```php
$emails = ['email1@test.com', 'email2@test.com', 'email3@test.com'];
$results = $validator->validateBulk($emails);

// [
//     'email1@test.com' => ['valid' => true, ...],
//     'email2@test.com' => ['valid' => false, 'reason' => 'no_mx_records'],
//     ...
// ]
```

## Статусы валидации

В таблице `campaign_recipients` хранятся поля:

- `email_validated` - был ли email проверен
- `validation_status` - статус: `valid`, `invalid`, `skipped`
- `validation_reason` - причина невалидности
- `validation_provider` - провайдер проверки
- `validated_at` - дата проверки

### Причины невалидности

**Базовая валидация:**
- `invalid_syntax` - неправильный формат email
- `no_mx_records` - домен не имеет MX записей
- `disposable_email` - одноразовый email

**API провайдеры:**
- `invalid` - email не существует
- `unknown` - не удалось проверить
- `catch_all` - catch-all домен (может принять любой email)
- `role_based` - ролевой email (info@, admin@, etc)
- `temporary` - временный/одноразовый email

## Примеры использования

### Создание рассылки с валидацией

```bash
# 1. Создайте рассылку через админку
# 2. Загрузите получателей из CSV
# 3. Проверьте все email перед отправкой
php artisan campaign:validate 1

# 4. Запустите отправку (невалидные будут пропущены)
php artisan campaign:send 1
```

### Программная проверка в коде

```php
use App\Services\EmailValidationService;

$validator = app(EmailValidationService::class);

// Одиночная проверка
$result = $validator->validate('test@example.com');

if ($result['valid']) {
    // Email валидный, можно отправлять
} else {
    // Email невалидный
    echo "Причина: " . $result['reason'];
}

// Массовая проверка
$emails = CampaignRecipient::pluck('email')->toArray();
$results = $validator->validateBulk($emails);
```

## Стоимость API провайдеров

### NeverBounce
- От $0.008 за проверку (bulk)
- https://neverbounce.com/pricing

### EmailListVerify
- От $0.004 за проверку (bulk)
- https://www.emaillistverify.com/pricing

### DataValidation
- От $0.0025 за проверку (bulk)
- http://www.datavalidation.com/pricing.html

**Рекомендация:** Используйте предварительную проверку через `campaign:validate` для больших списков, чтобы получить bulk цены.

## Мониторинг

Логи валидации сохраняются в Laravel Log:

```php
// При ошибке валидации API
Log::warning('Email validation API error', [
    'email' => $email,
    'provider' => $provider,
    'error' => $e->getMessage()
]);

// При пропуске отправки из-за невалидного email
Log::info('Email validation failed, skipping send', [
    'campaign_id' => $campaignId,
    'recipient_id' => $recipientId,
    'email' => $email,
    'reason' => $reason
]);
```

## Расширение списка одноразовых доменов

В `EmailValidationService::isDisposableEmail()` можно добавить дополнительные домены:

```php
private function isDisposableEmail(string $email): bool
{
    $disposableDomains = [
        'tempmail.com',
        'guerrillamail.com',
        // ... добавьте свои домены
    ];

    [$user, $domain] = explode('@', $email);
    return in_array($domain, $disposableDomains);
}
```

Или можно использовать внешний список:
- https://github.com/disposable-email-domains/disposable-email-domains
