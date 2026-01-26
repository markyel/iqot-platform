# Интеграция отслеживания расходов по счетам

## Обзор

Система автоматически отслеживает расходование средств по оплаченным счетам (Invoice) методом FIFO (First In, First Out).

## Созданные компоненты

### 1. База данных
- **Миграция:** `database/migrations/2026_01_26_000001_add_spent_amount_to_invoices_table.php`
  - Добавлено поле `spent_amount` в таблицу `invoices`

### 2. Модель Invoice
- **Файл:** `app/Models/Invoice.php`
- **Новые поля:** `spent_amount`
- **Новые статусы:** `closed` (когда счет полностью израсходован)
- **Новые методы:**
  - `getRemainingAmountAttribute()` - остаток средств
  - `getIsClosedAttribute()` - проверка закрытия
  - `addSpending($amount)` - добавить расход
  - `getUsagePercentAttribute()` - процент использования

### 3. InvoiceTrackingService
- **Файл:** `app/Services/InvoiceTrackingService.php`
- **Основной метод:** `trackSpending(User $user, float $amount, string $type, ?int $relatedId)`
- **Специализированные методы:**
  - `trackSubscriptionCharge(SubscriptionCharge $charge)`
  - `trackBalanceCharge(BalanceCharge $charge)`
  - `trackReportAccess(ReportAccess $access)`
  - `trackItemPurchase(ItemPurchase $purchase)`

### 4. Filament Admin Resources
- **UserResource** - управление пользователями с вкладкой транзакций
- **InvoiceResource** - управление счетами с отчетностью

## Как это работает

### Логика FIFO (First In, First Out)

1. Когда пользователь оплачивает счет, средства зачисляются на баланс
2. При каждом списании средств система ищет первый оплаченный счет с остатком (по дате оплаты)
3. Списание привязывается к этому счету, увеличивается `spent_amount`
4. Когда `spent_amount >= subtotal`, статус меняется на `closed`

### Пример:
```
Оплачено:
- Счет №1: 10 000 ₽ (20.01.2026)
- Счет №2: 15 000 ₽ (25.01.2026)

Списания:
- 5 000 ₽ → Счет №1 (остаток 5 000)
- 7 000 ₽ → Счет №1 (остаток 0, счет закрыт) + Счет №2 (использовано 2 000, остаток 13 000)
```

## Интеграция в существующий код

### Шаг 1: Запустить миграцию

```bash
php artisan migrate
```

### Шаг 2: Интегрировать в места списания средств

#### A. Абонентская плата (SubscriptionCharge)

**Файл:** `app/Services/TariffService.php` или где создается `SubscriptionCharge`

**До:**
```php
$charge = SubscriptionCharge::create([
    'user_id' => $user->id,
    'tariff_plan_id' => $tariffPlan->id,
    'amount' => $tariffPlan->monthly_price,
    'charged_at' => now(),
    'description' => "...",
]);

$user->decrement('balance', $tariffPlan->monthly_price);
```

**После:**
```php
$charge = SubscriptionCharge::create([
    'user_id' => $user->id,
    'tariff_plan_id' => $tariffPlan->id,
    'amount' => $tariffPlan->monthly_price,
    'charged_at' => now(),
    'description' => "...",
]);

$user->decrement('balance', $tariffPlan->monthly_price);

// ДОБАВИТЬ:
app(InvoiceTrackingService::class)->trackSubscriptionCharge($charge);
```

#### B. Списание по позициям заявки (BalanceCharge)

**Файл:** где создается `BalanceCharge` (вероятно в Observer или Service)

**До:**
```php
$charge = BalanceCharge::create([
    'user_id' => $user->id,
    'balance_hold_id' => $hold->id,
    'external_request_item_id' => $item->id,
    'amount' => $amount,
    'description' => "...",
]);

$user->decrement('balance', $amount);
```

**После:**
```php
$charge = BalanceCharge::create([
    'user_id' => $user->id,
    'balance_hold_id' => $hold->id,
    'external_request_item_id' => $item->id,
    'amount' => $amount,
    'description' => "...",
]);

$user->decrement('balance', $amount);

// ДОБАВИТЬ:
app(InvoiceTrackingService::class)->trackBalanceCharge($charge);
```

#### C. Покупка отчета (ReportAccess)

**Файл:** `app/Http/Controllers/CatalogController.php` или где создается `ReportAccess`

**До:**
```php
$access = ReportAccess::create([
    'user_id' => auth()->id(),
    'report_id' => $report->id,
    'price' => $price,
    'accessed_at' => now(),
]);

$user->decrement('balance', $price);
```

**После:**
```php
$access = ReportAccess::create([
    'user_id' => auth()->id(),
    'report_id' => $report->id,
    'price' => $price,
    'accessed_at' => now(),
]);

$user->decrement('balance', $price);

// ДОБАВИТЬ:
app(InvoiceTrackingService::class)->trackReportAccess($access);
```

#### D. Покупка позиции (ItemPurchase)

**Файл:** где создается `ItemPurchase`

**До:**
```php
$purchase = ItemPurchase::create([
    'user_id' => $user->id,
    'item_id' => $item->id,
    'amount' => $amount,
]);

$user->decrement('balance', $amount);
```

**После:**
```php
$purchase = ItemPurchase::create([
    'user_id' => $user->id,
    'item_id' => $item->id,
    'amount' => $amount,
]);

$user->decrement('balance', $amount);

// ДОБАВИТЬ:
app(InvoiceTrackingService::class)->trackItemPurchase($purchase);
```

### Шаг 3: Использование Observer (Рекомендуется)

Более элегантное решение - использовать Eloquent Observers.

**Создать файл:** `app/Observers/ChargeObserver.php`

```php
<?php

namespace App\Observers;

use App\Models\SubscriptionCharge;
use App\Models\BalanceCharge;
use App\Models\ReportAccess;
use App\Models\ItemPurchase;
use App\Services\InvoiceTrackingService;

class ChargeObserver
{
    protected $trackingService;

    public function __construct(InvoiceTrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    // Для SubscriptionCharge
    public function created(SubscriptionCharge|BalanceCharge|ReportAccess|ItemPurchase $model)
    {
        if ($model instanceof SubscriptionCharge) {
            $this->trackingService->trackSubscriptionCharge($model);
        } elseif ($model instanceof BalanceCharge) {
            $this->trackingService->trackBalanceCharge($model);
        } elseif ($model instanceof ReportAccess) {
            $this->trackingService->trackReportAccess($model);
        } elseif ($model instanceof ItemPurchase) {
            $this->trackingService->trackItemPurchase($model);
        }
    }
}
```

**Зарегистрировать в:** `app/Providers/AppServiceProvider.php`

```php
use App\Models\SubscriptionCharge;
use App\Models\BalanceCharge;
use App\Models\ReportAccess;
use App\Models\ItemPurchase;
use App\Observers\ChargeObserver;

public function boot()
{
    $observer = new ChargeObserver(app(InvoiceTrackingService::class));

    SubscriptionCharge::observe($observer);
    BalanceCharge::observe($observer);
    ReportAccess::observe($observer);
    ItemPurchase::observe($observer);
}
```

## Использование в админке

### Просмотр счетов пользователя

1. Перейти в **Пользователи** (`/manage/users`)
2. Выбрать пользователя
3. Вкладка **Транзакции** - полная история движения средств
4. Вкладка **Счета** (если добавить InvoicesRelationManager)

### Управление счетами

1. Перейти в **Счета** (`/manage/invoices`)
2. Видна статистика:
   - Общая сумма счетов
   - Оплачено всего
   - Израсходовано всего
   - К оплате
3. Фильтры:
   - По статусу
   - По дате
   - По пользователю
   - Только закрытые
   - Только с остатком

### Отчетность

**Закрытые счета (полностью израсходованы):**
- Фильтр "Только закрытые счета"
- Статус: `closed` (фиолетовый badge)
- Остаток: 0 ₽

**Счета с остатком:**
- Фильтр "Только с остатком"
- Статус: `paid` (зеленый badge)
- Остаток > 0 ₽
- Прогресс-бар использования

## Проверка работоспособности

### Тест 1: Выставление и оплата счета

```php
// В tinker или тесте
$user = User::find(1);
$invoice = Invoice::create([
    'user_id' => $user->id,
    'number' => Invoice::generateNumber(),
    'invoice_date' => now(),
    'subtotal' => 10000,
    'spent_amount' => 0,
    'vat_rate' => 5,
    'vat_amount' => 500,
    'total' => 10500,
    'status' => 'draft',
]);

// Отметить как оплаченный
$invoice->markAsPaid();

// Проверить баланс пользователя
echo $user->fresh()->balance; // Должно увеличиться на 10000
```

### Тест 2: Списание средств

```php
// Создать списание
$charge = SubscriptionCharge::create([
    'user_id' => $user->id,
    'tariff_plan_id' => 1,
    'amount' => 5000,
    'charged_at' => now(),
    'description' => 'Тест',
]);

// Если Observer настроен, отслеживание произойдет автоматически
// Иначе вызвать вручную:
app(InvoiceTrackingService::class)->trackSubscriptionCharge($charge);

// Проверить счет
$invoice->refresh();
echo $invoice->spent_amount; // 5000
echo $invoice->remaining_amount; // 5000
echo $invoice->usage_percent; // 50%
echo $invoice->status; // paid (т.к. еще не закрыт)
```

### Тест 3: Полное израсходование

```php
// Создать еще одно списание на оставшуюся сумму
$charge2 = BalanceCharge::create([
    'user_id' => $user->id,
    'amount' => 5000,
    'description' => 'Тест 2',
]);

app(InvoiceTrackingService::class)->trackBalanceCharge($charge2);

// Проверить счет
$invoice->refresh();
echo $invoice->spent_amount; // 10000
echo $invoice->remaining_amount; // 0
echo $invoice->usage_percent; // 100%
echo $invoice->status; // closed (автоматически изменен!)
```

## Статистика и отчеты

### Получить статистику пользователя

```php
$stats = app(InvoiceTrackingService::class)->getUserInvoiceStats($user);

/*
[
    'total_received' => 10000,      // Всего получено по счетам
    'total_spent' => 10000,         // Всего израсходовано
    'remaining' => 0,               // Остаток
    'open_invoices' => 0,           // Открытых счетов
    'closed_invoices' => 1,         // Закрытых счетов
]
*/
```

### Отчет в админке

В InvoiceResource доступны:
- Виджеты со статистикой
- Фильтры для аналитики
- Экспорт в Excel (если настроить)

## Миграция существующих данных

Если у вас уже есть оплаченные счета и списания, нужно:

1. Создать миграцию данных
2. Для каждого оплаченного счета вычислить spent_amount
3. Обновить статус на closed если израсходован полностью

**Пример скрипта миграции:**

```php
// database/migrations/2026_01_26_000002_migrate_invoice_spending_data.php

public function up(): void
{
    $invoices = Invoice::where('status', 'paid')->get();

    foreach ($invoices as $invoice) {
        // Здесь нужна логика подсчета spent_amount
        // на основе существующих BalanceCharge, SubscriptionCharge и т.д.

        // Это сложно реализовать ретроспективно, т.к. нет привязки
        // Рекомендуется начать отслеживание с текущего момента

        // Или можно просто обнулить:
        $invoice->update(['spent_amount' => 0]);
    }
}
```

## Заключение

После интеграции система будет автоматически:
- Отслеживать расходы по каждому счету
- Закрывать счета при полном израсходовании
- Предоставлять детальную отчетность в админке
- Показывать пользователю историю транзакций с привязкой к счетам
