# Структура системы тарификации и биллинга

## Обзор

Система тарификации платформы IQOT отвечает за управление тарифными планами, учет использования ресурсов, заморозку и списание средств.

---

## Основные компоненты

### 1. Модели данных

#### `TariffPlan` - Тарифный план
**Файл:** `app/Models/TariffPlan.php`

**Назначение:** Определяет условия тарифного плана

**Ключевые поля:**
- `code` - уникальный код (start, basic, extended, professional)
- `name` - название тарифа
- `monthly_price` - абонентская плата
- `items_limit` - лимит позиций в заявках в месяц
- `reports_limit` - лимит открытых отчетов в месяц
- `price_per_item_over_limit` - цена за позицию сверх лимита
- `price_per_report_over_limit` - цена за отчет сверх лимита
- `pdf_reports_enabled` - доступ к экспорту PDF
- `is_active` - активность тарифа

**Основные методы:**
- `getItemCost(User $user)` - стоимость позиции с учетом лимитов
- `getReportCost(User $user)` - стоимость отчета с учетом лимитов
- `canGeneratePdfReports()` - проверка доступности PDF

---

#### `UserTariff` - Подписка пользователя на тариф
**Файл:** `app/Models/UserTariff.php`

**Назначение:** Связь между пользователем и его текущим тарифом

**Ключевые поля:**
- `user_id` - ID пользователя
- `tariff_plan_id` - ID тарифного плана
- `started_at` - дата начала действия
- `expires_at` - дата окончания (для платных тарифов)
- `items_used` - использовано позиций
- `reports_used` - использовано отчетов
- `is_active` - активность подписки

**Основные методы:**
- `useItems($count)` - увеличить счетчик позиций
- `useReport()` - увеличить счетчик отчетов
- `canCreateRequest($itemsCount)` - проверка возможности создания заявки
- `calculateRequestCost($itemsCount)` - расчет стоимости заявки

---

#### `BalanceHold` - Заморозка средств
**Файл:** `app/Models/BalanceHold.php`

**Назначение:** Заморозка средств на балансе при создании заявки

**Ключевые поля:**
- `user_id` - ID пользователя
- `request_id` - ID заявки
- `amount` - сумма заморозки
- `status` - статус (held, charged, released)
- `charged_at` - дата списания
- `released_at` - дата разморозки

**Основные методы:**
- `release()` - разморозить средства (вернуть на баланс)
- `charge()` - списать все замороженные средства (УСТАРЕВШИЙ)
- `chargeForItem($externalRequestItemId, $itemCost, $description)` - списать за конкретную позицию
- `getChargedAmount()` - сумма уже списанных средств
- `getRemainingAmount()` - остаток замороженных средств

---

#### `BalanceCharge` - Списание средств по позиции
**Файл:** `app/Models/BalanceCharge.php`

**Назначение:** Детализация списаний с замороженных средств по позициям

**Ключевые поля:**
- `balance_hold_id` - ID заморозки
- `user_id` - ID пользователя
- `request_id` - ID заявки
- `external_request_item_id` - ID позиции из БД reports
- `amount` - сумма списания
- `description` - описание

---

#### `ReportAccess` - Доступ к отчетам
**Файл:** `app/Models/ReportAccess.php`

**Назначение:** Учет открытия чужих отчетов пользователями

**Ключевые поля:**
- `user_id` - ID пользователя
- `request_id` - ID заявки (из основной БД)
- `report_number` - номер отчета
- `price` - стоимость доступа
- `accessed_at` - дата открытия

---

#### `ItemPurchase` - Покупка доступа к позиции
**Файл:** `app/Models/ItemPurchase.php`

**Назначение:** Учет покупки доступа к отдельной позиции

**Ключевые поля:**
- `user_id` - ID пользователя
- `item_id` - ID позиции (external_request_item_id)
- `amount` - стоимость покупки

---

### 2. Сервисы

#### `TariffService`
**Файл:** `app/Services/TariffService.php`

**Назначение:** Бизнес-логика работы с тарифами

**Основные методы:**
- `getUserLimitsInfo(User $user)` - получить информацию о лимитах
- `switchUserTariff(User $user, TariffPlan $tariffPlan)` - сменить тариф
- `renewExpiredTariffs()` - продлить истекшие тарифы (для крона)

---

### 3. Контроллеры

#### `TariffController`
**Файл:** `app/Http/Controllers/TariffController.php`

**Основные методы:**
- `index()` - страница "Мой тариф" `/cabinet/tariff`
- `switch()` - смена тарифа
- `transactions()` - детализация расходов `/cabinet/tariff/transactions`
- `limitsUsage()` - детализация лимитов `/cabinet/tariff/limits-usage`

---

#### `ItemController`
**Файл:** `app/Http/Controllers/Cabinet/ItemController.php`

**Назначение:** Управление доступом к чужим позициям/отчетам

**Основные методы:**
- `index()` - список всех позиций `/cabinet/items`
- `show($item)` - просмотр позиции `/cabinet/items/{id}`
- `purchase($item)` - покупка доступа к позиции

---

### 4. Observers

#### `ExternalRequestItemObserver`
**Файл:** `app/Observers/ExternalRequestItemObserver.php`

**Назначение:** Автоматическое списание средств при получении 3+ ответов на позицию

**Регистрация:** `app/Providers/AppServiceProvider.php`

**Логика:**
1. Срабатывает при изменении `offers_count` в `ExternalRequestItem`
2. Проверяет: `offers_count >= 3`
3. Находит заявку в основной БД по `request_number`
4. Проверяет наличие активной заморозки (`BalanceHold` со статусом `held`)
5. Рассчитывает стоимость позиции с учетом тарифа пользователя
6. Списывает средства за позицию (только 1 раз, есть проверка в `chargeForItem`)

---

### 5. Команды (Artisan)

#### `RenewUserTariffsCommand`
**Файл:** `app/Console/Commands/RenewUserTariffsCommand.php`

**Назначение:** Продление тарифов и списание абонентской платы

**Команда:** `php artisan tariffs:renew`

**Расписание:** Ежедневно

---

#### `ReleaseExpiredBalanceHoldsCommand`
**Файл:** `app/Console/Commands/ReleaseExpiredBalanceHoldsCommand.php`

**Назначение:** Разморозка средств за невыполненные заявки

**Команда:** `php artisan balance:release-expired`

**Расписание:** Ежедневно

**Логика:**
1. Находит все заморозки (`status='held'`) старше 7 дней
2. Для каждой заявки проверяет позиции в БД reports
3. **Если НИ ОДНА позиция не набрала 3+ ответов:**
   - Размораживает ВСЮ оставшуюся сумму
   - Возвращает средства на баланс
   - Статус → `released`
4. **Если ЧАСТЬ позиций набрала 3+ ответов:**
   - Рассчитывает сумму за невыполненные позиции
   - Размораживает только эту часть
   - Возвращает средства на баланс
   - Уменьшает сумму заморозки
5. **Если ВСЕ позиции набрали 3+ ответов:**
   - Ждёт полного списания через Observer
   - После списания статус → `charged`

---

## Потоки работы

### Поток 1: Создание заявки

**Путь:** `/cabinet/my/requests/create`
**Контроллер:** `UserRequestController::store()`

**Шаги:**
1. Пользователь заполняет форму создания заявки
2. Система рассчитывает стоимость:
   - Если есть тариф → проверяет лимиты
   - Если лимит исчерпан → берет `price_per_item_over_limit`
   - Если лимит не исчерпан → стоимость 0
3. Создает заявку в основной БД
4. Замораживает средства: `$user->holdBalance($totalCost, $request->id)`
5. Увеличивает счетчик использованных позиций: `$tariff->useItems($itemsCount)`

**Файлы:**
- `app/Http/Controllers/UserRequestController.php:113-244`
- `app/Models/User.php:225-233` (метод `holdBalance`)

---

### Поток 2: Списание при получении ответов

**Триггер:** Обновление `offers_count` в `external_request_items` (БД reports)
**Observer:** `ExternalRequestItemObserver`

**Шаги:**
1. В БД reports обновляется `offers_count` у позиции
2. Срабатывает Observer `ExternalRequestItemObserver::updated()`
3. Проверка: `offers_count >= 3`
4. Поиск заявки в основной БД по `request_number`
5. Проверка наличия активной заморозки
6. Расчет стоимости позиции с учетом тарифа
7. Списание: `$balanceHold->chargeForItem($itemId, $cost, $description)`
8. В таблице `balance_charges` создается запись о списании
9. С баланса пользователя списывается сумма

**Важно:** Списание происходит **только 1 раз** на позицию (проверка в `BalanceHold::chargeForItem`)

**Файлы:**
- `app/Observers/ExternalRequestItemObserver.php:15-74`
- `app/Models/BalanceHold.php:72-105`

---

### Поток 3: Разморозка средств

#### 3.1 Ручная разморозка (админ)

**Путь:** `/admin/requests/{id}/reject`
**Контроллер:** `Admin\RequestController::reject()`

**Шаги:**
1. Админ отклоняет заявку
2. Проверяется наличие активной заморозки
3. Вызывается `$balanceHold->release()`
4. Средства возвращаются на баланс пользователя
5. Статус заявки → `cancelled`

**Файлы:**
- `app/Http/Controllers/Admin/RequestController.php:194-212`
- `app/Models/BalanceHold.php:44-51`

---

#### 3.2 Автоматическая разморозка (крон)

**Команда:** `php artisan balance:release-expired`
**Расписание:** Ежедневно

**Шаги:**
1. Находит заморозки со статусом `held` старше 7 дней
2. Для каждой заявки проверяет позиции в БД reports
3. Если **НИ ОДНА** позиция не набрала 3+ ответов:
   - Размораживает ВСЮ сумму
   - Возвращает средства на баланс
   - Статус заморозки → `released`
4. Если **хотя бы одна** позиция набрала 3+ ответов:
   - Оставляет заморозку
   - Ожидает частичного списания через Observer

**Файлы:**
- `app/Console/Commands/ReleaseExpiredBalanceHoldsCommand.php`
- `routes/console.php:14`

---

### Поток 4: Открытие чужого отчета

**Путь:** `/cabinet/items/{id}`
**Контроллер:** `Cabinet\ItemController::purchase()`

**Шаги:**
1. Пользователь открывает позицию из чужой заявки
2. Проверяется: есть ли уже доступ (`User::hasAccessToItem()`)
3. Если доступа нет:
   - Рассчитывается стоимость с учетом тарифа
   - Проверяется баланс
   - Списываются средства с баланса
   - Создается запись `ItemPurchase`
   - Увеличивается счетчик `reports_used` в тарифе

**Файлы:**
- `app/Http/Controllers/Cabinet/ItemController.php:78-119`
- `app/Models/User.php:238-257` (метод `hasAccessToItem`)

---

### Поток 5: Открытие своего отчета

**Путь:** `/cabinet/my/requests/{id}/report`
**Контроллер:** `UserRequestController::showReport()`

**Шаги:**
1. Пользователь открывает отчет по своей заявке
2. Проверяется, что заявка синхронизирована с БД reports
3. **НЕ СПИСЫВАЮТСЯ** средства (пользователь уже оплатил при создании)
4. Отображается отчет с предложениями поставщиков

**Важно:** В предыдущей версии здесь ошибочно списывались средства. Это исправлено.

**Файлы:**
- `app/Http/Controllers/UserRequestController.php:313-354`

---

## Страницы для пользователя

### Тарифы

- **Мой тариф**: `/cabinet/tariff` - просмотр текущего тарифа и смена
- **Детализация расходов**: `/cabinet/tariff/transactions` - история операций
- **Детализация лимитов**: `/cabinet/tariff/limits-usage` - использование лимитов

**Контроллер:** `TariffController`
**Представления:** `resources/views/cabinet/tariff/`

---

### Заявки

- **Список заявок**: `/cabinet/my/requests`
- **Создание заявки**: `/cabinet/my/requests/create`
- **Просмотр заявки**: `/cabinet/my/requests/{id}`
- **Отчет по заявке**: `/cabinet/my/requests/{id}/report`

**Контроллер:** `UserRequestController`
**Представления:** `resources/views/cabinet/requests/`, `resources/views/requests/`

---

### Чужие отчеты

- **Список позиций**: `/cabinet/items`
- **Просмотр позиции**: `/cabinet/items/{id}` - покупка доступа

**Контроллер:** `Cabinet\ItemController`
**Представления:** `resources/views/cabinet/items/`

---

## Структура базы данных

### Основная БД (iqot-platform)

| Таблица | Назначение |
|---------|------------|
| `tariff_plans` | Тарифные планы |
| `user_tariffs` | Подписки пользователей |
| `balance_holds` | Заморозки средств |
| `balance_charges` | Списания по позициям |
| `report_accesses` | Доступ к чужим отчетам |
| `item_purchases` | Покупка доступа к позициям |
| `requests` | Заявки пользователей |
| `request_items` | Позиции заявок |

---

### БД reports (внешняя БД)

| Таблица | Назначение |
|---------|------------|
| `requests` | Синхронизированные заявки |
| `request_items` | Позиции заявок (с `offers_count`) |
| `offers` | Предложения поставщиков |

**Связь между БД:**
- Поле `requests.request_number` в обеих БД
- `ExternalRequestItem::observe()` следит за изменениями в БД reports

---

## Важные особенности

### 1. Списание происходит по позициям

Средства списываются **не всей суммой**, а **по каждой позиции** при получении 3+ ответов.

**Пример:**
- Заявка на 4 позиции × 50₽ = заморожено 200 ₽
- Позиция №1 набрала 3 ответа → списано 50 ₽ (остаток заморозки: 150₽)
- Позиция №2 набрала 3 ответа → списано 50 ₽ (остаток заморозки: 100₽)
- Через 7 дней позиции №3,4 не набрали 3 ответа:
  - Рассчитывается: 2 позиции × 50₽ = 100₽
  - Размораживается 100₽ и возвращается на баланс
  - Заморозка закрывается со статусом `charged` (списано 100₽) + частично разморожено (100₽)

---

### 2. Двойное списание исключено

В `BalanceHold::chargeForItem()` есть проверка на существующее списание по `external_request_item_id`.

---

### 3. Пользователь не платит за свои отчеты

При открытии `/cabinet/my/requests/{id}/report` списания НЕ происходит (оплата была при создании заявки).

---

### 4. Частичная разморозка

Система поддерживает **частичную разморозку средств**:
- Средства за выполненные позиции (3+ ответов) списываются через Observer
- Средства за невыполненные позиции возвращаются через 7 дней
- Заморозка корректно учитывает уже списанные суммы

---

## Ключевые файлы

### Модели
- `app/Models/TariffPlan.php`
- `app/Models/UserTariff.php`
- `app/Models/BalanceHold.php`
- `app/Models/BalanceCharge.php`
- `app/Models/ReportAccess.php`
- `app/Models/ItemPurchase.php`
- `app/Models/ExternalRequestItem.php`
- `app/Models/ExternalRequest.php`

### Контроллеры
- `app/Http/Controllers/TariffController.php`
- `app/Http/Controllers/UserRequestController.php`
- `app/Http/Controllers/Cabinet/ItemController.php`
- `app/Http/Controllers/Admin/RequestController.php`

### Сервисы
- `app/Services/TariffService.php`

### Observers
- `app/Observers/ExternalRequestItemObserver.php`

### Команды
- `app/Console/Commands/RenewUserTariffsCommand.php`
- `app/Console/Commands/ReleaseExpiredBalanceHoldsCommand.php`

### Конфигурация
- `routes/console.php` - расписание задач
- `app/Providers/AppServiceProvider.php` - регистрация Observer

### Представления
- `resources/views/cabinet/tariff/` - страницы тарифов
- `resources/views/cabinet/requests/` - заявки пользователя
- `resources/views/cabinet/items/` - чужие отчеты
- `resources/views/requests/report.blade.php` - отчет по заявке

---

## Исправленные проблемы

### 1. Двойное списание за свою заявку ✅
**Было:** При открытии `/cabinet/my/requests/{id}/report` списывались средства
**Исправлено:** Убрана логика списания для своих отчетов
**Файл:** `app/Http/Controllers/UserRequestController.php:348-374`

---

### 2. Observer не находил связь с заявкой ✅
**Было:** Observer искал `internal_request_id` (не существует)
**Исправлено:** Теперь ищет по `request_number`
**Файл:** `app/Observers/ExternalRequestItemObserver.php:26-74`

---

### 3. Отсутствовала автоматическая разморозка ✅
**Было:** Нет команды для разморозки старых заявок
**Создано:** `ReleaseExpiredBalanceHoldsCommand`
**Файлы:**
- `app/Console/Commands/ReleaseExpiredBalanceHoldsCommand.php`
- `routes/console.php:14`

---

## Дополнительно

Для тестирования системы используйте команды:

```bash
# Проверить списание за позиции вручную
php artisan tinker
>>> $item = App\Models\ExternalRequestItem::find(123);
>>> $item->update(['offers_count' => 3]);

# Запустить разморозку вручную
php artisan balance:release-expired

# Продлить тарифы вручную
php artisan tariffs:renew

# Посмотреть расписание задач
php artisan schedule:list
```
