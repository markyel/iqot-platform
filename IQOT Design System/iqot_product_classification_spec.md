# Система классификации товаров и профилирования поставщиков IQOT

## Обзор

Система использует **двумерную классификацию** товаров и автоматическое профилирование поставщиков на основе их ответов.

---

## 1. Двумерная классификация товаров

Каждый товар (`request_items`) классифицируется по двум осям:

| Ось | Таблица | Описание | Примеры |
|-----|---------|----------|---------|
| **Тип товара** | `product_types` | ЧТО это за товар | Платы управления, Приводы дверей, Ремни, Датчики |
| **Область применения** | `application_domains` | ДЛЯ ЧЕГО предназначен | Лифты, Эскалаторы, (будущее: Станки, Медоборудование) |

### Структура request_items

```sql
request_items:
  - id
  - name                 -- "Плата KONE LCE FCB"
  - product_type_id      -- FK → product_types (101 = Платы управления)
  - domain_id            -- FK → application_domains (1 = Лифты)
  - type_confidence      -- Уверенность классификации (0.00-1.00)
  - domain_confidence    -- Уверенность классификации (0.00-1.00)
  - classification_needs_review -- Флаг для ручной проверки
```

### Таблица product_types

Иерархическая структура типов товаров:

```sql
product_types:
  - id
  - slug                 -- "control_boards"
  - name                 -- "Платы управления"
  - parent_id            -- FK для иерархии (NULL = корневая категория)
  - keywords             -- JSON массив ключевых слов для AI классификации
  - description
  - is_active
  - sort_order
```

### Таблица application_domains

Области применения (расширяемые):

```sql
application_domains:
  - id
  - slug                 -- "elevators"
  - name                 -- "Лифты"
  - parent_id            -- FK для иерархии
  - keywords             -- JSON массив ключевых слов
  - description
  - is_active
  - sort_order
```

**Текущие домены:**
- Лифты (id=1)
- Эскалаторы (id=2)

---

## 2. Профилирование поставщиков

### 2.1 Scope поставщика

Каждый поставщик имеет два параметра scope:

```sql
suppliers:
  - scope_product_types  -- ENUM('all', 'specific')
  - scope_domains        -- ENUM('all', 'specific')
```

| scope | Значение |
|-------|----------|
| `all` | Работает со всеми (кроме явно исключённых) |
| `specific` | Работает только с явно указанными |

### 2.2 Таблицы профилей

**supplier_product_types** — связь поставщик ↔ тип товара:

```sql
supplier_product_types:
  - supplier_id
  - product_type_id
  - is_included          -- 1 = работает, 0 = не работает
  - positive_signals     -- Счётчик положительных сигналов
  - negative_signals     -- Счётчик отрицательных сигналов
  - confidence           -- Рассчитанная уверенность
  - source               -- 'manual', 'migrated', 'response_positive', 'response_negative'
  - is_manual            -- Флаг ручного решения (защита от перезаписи)
```

**supplier_domains** — связь поставщик ↔ область применения:

```sql
supplier_domains:
  - supplier_id
  - domain_id
  - is_included          -- 1 = работает, 0 = не работает
  - positive_signals     -- Счётчик положительных сигналов
  - negative_signals     -- Счётчик отрицательных сигналов
  - confidence           -- Рассчитанная уверенность
  - source
  - is_manual
```

---

## 3. Система сигналов

### 3.1 Источник сигналов

Сигналы собираются автоматически из ответов поставщиков на запросы.

**AI классификация писем** определяет:

```json
{
  "email_type": "offer | rejection | question | mixed | other",
  "rejection_reason": "not_our_profile | not_available | other | null",
  "has_offers": true/false,
  "offers": [...],
  "summary": "..."
}
```

### 3.2 Типы сигналов

| email_type | rejection_reason | Сигнал | Описание |
|------------|------------------|--------|----------|
| `offer` | — | **positive** | Поставщик дал цену |
| `mixed` | — | **positive** | Есть хотя бы одно предложение |
| `rejection` | `not_our_profile` | **negative** | "Не наш профиль" — не работает с этим типом |
| `rejection` | `not_available` | *игнорируется* | "Нет в наличии" — временно нет товара |
| `rejection` | `other` | *игнорируется* | Другие причины |

### 3.3 Хранение сырых сигналов

```sql
supplier_response_signals:
  - id
  - supplier_id
  - product_type_id
  - domain_id            -- NULL если domain неизвестен
  - signal_type          -- 'positive', 'negative'
  - response_type        -- 'price_offered', 'not_our_profile'
  - request_id
  - item_name
  - weight               -- Вес сигнала (default 1.00)
  - created_at
```

### 3.4 Логика агрегации

При записи сигнала автоматически обновляются:
- `supplier_product_types.positive_signals` / `negative_signals`
- `supplier_domains.positive_signals` / `negative_signals`

**Правила обновления is_included:**

```
ЕСЛИ is_manual = 0 (не ручное решение):
  ЕСЛИ positive > 0 → is_included = 1
  ИНАЧЕ ЕСЛИ negative >= 5 → is_included = 0
  ИНАЧЕ → без изменений
```

**Confidence:**
```
confidence = positive / (positive + negative)
```

---

## 4. Логика отбора поставщиков

### 4.1 Матрица включения

При формировании запроса с `product_type_id` и `domain_id`:

| scope | Запись в таблице | is_included | Результат |
|-------|------------------|-------------|-----------|
| `all` | Нет записи | — | ✅ Включён |
| `all` | Есть | 1 | ✅ Включён |
| `all` | Есть | 0 | ❌ Исключён |
| `specific` | Нет записи | — | ❌ Исключён |
| `specific` | Есть | 1 | ✅ Включён |
| `specific` | Есть | 0 | ❌ Исключён |

### 4.2 SQL запрос отбора

```sql
SELECT DISTINCT s.id, s.name, s.email
FROM suppliers s
WHERE s.is_active = 1
  AND s.notify_email = 1
  
  -- Фильтр по domain
  AND (
    (s.scope_domains = 'all' 
     AND NOT EXISTS (
       SELECT 1 FROM supplier_domains sd 
       WHERE sd.supplier_id = s.id 
       AND sd.domain_id IN (${domainIds})
       AND sd.is_included = 0
     )
    )
    OR EXISTS (
      SELECT 1 FROM supplier_domains sd 
      WHERE sd.supplier_id = s.id 
      AND sd.domain_id IN (${domainIds})
      AND sd.is_included = 1
    )
  )
  
  -- Фильтр по product_type
  AND (
    (s.scope_product_types = 'all' 
     AND NOT EXISTS (
       SELECT 1 FROM supplier_product_types spt 
       WHERE spt.supplier_id = s.id 
       AND spt.product_type_id IN (${typeIds})
       AND spt.is_included = 0
     )
    )
    OR EXISTS (
      SELECT 1 FROM supplier_product_types spt 
      WHERE spt.supplier_id = s.id 
      AND spt.product_type_id IN (${typeIds})
      AND spt.is_included = 1
    )
  )
```

---

## 5. Процедуры и автоматизация

### 5.1 Основные процедуры

| Процедура | Назначение |
|-----------|------------|
| `collect_response_signals()` | Собирает сигналы из необработанных писем |
| `process_offer_signals()` | Обрабатывает positive сигналы из offers |
| `process_rejection_signal()` | Обрабатывает negative сигналы (только not_our_profile) |
| `update_supplier_signal()` | Записывает сигнал и обновляет агрегаты |

### 5.2 Автоматический запуск

**Status Manager** (каждые 2 часа):
1. Вызывает `CALL collect_response_signals()`
2. Обрабатывает до 100 писем за запуск
3. Обновляет статусы заявок

### 5.3 Telegram интерфейс

| Команда | Действие |
|---------|----------|
| `/blocked` | Показать заблокированные пары поставщик-тип |
| `/review` | Показать пары требующие проверки |
| `✅ Включить` | Установить is_included=1, is_manual=1 |
| `❌ Исключить` | Установить is_included=0, is_manual=1 |

---

## 6. Примеры работы

### Пример 1: Положительный сигнал

```
Запрос: "Плата KONE LCE FCB" (product_type=101, domain=1)
Ответ поставщика: Цена 15000 руб
AI классификация: email_type = "offer"

Результат:
→ supplier_response_signals: positive, price_offered
→ supplier_product_types: positive_signals +1
→ supplier_domains: positive_signals +1
→ is_included = 1 (потому что positive > 0)
```

### Пример 2: Отказ "не наш профиль"

```
Запрос: "Концевой выключатель Bernstein" (product_type=1003, domain=NULL)
Ответ поставщика: "Это не наш профиль, мы не работаем с выключателями"
AI классификация: email_type = "rejection", rejection_reason = "not_our_profile"

Результат:
→ supplier_response_signals: negative, not_our_profile
→ supplier_product_types: negative_signals +1
→ После 5 таких отказов: is_included = 0
```

### Пример 3: Отказ "нет в наличии"

```
Запрос: "Ремень эскалатора Schindler" (product_type=401, domain=2)
Ответ поставщика: "К сожалению, сейчас нет в наличии"
AI классификация: email_type = "rejection", rejection_reason = "not_available"

Результат:
→ Сигнал НЕ записывается
→ Профиль поставщика не меняется
→ Поставщик продолжит получать запросы на ремни
```

---

## 7. Типы поставщиков

### Специалист по области

```
scope_domains = 'specific'
scope_product_types = 'all'

Пример: "ЛифтСервис" — всё для лифтов
- supplier_domains: domain=1 (Лифты), is_included=1
- Получает запросы на любые типы товаров, но только для лифтов
```

### Специалист по типу товара

```
scope_domains = 'all'
scope_product_types = 'specific'

Пример: "РемниПром" — ремни для всего
- supplier_product_types: type=401 (Ремни), is_included=1
- Получает запросы на ремни для любых областей
```

### Универсальный поставщик

```
scope_domains = 'all'
scope_product_types = 'all'

Получает все запросы (кроме явно исключённых)
```

---

## 8. Диаграмма потока данных

```
┌─────────────────────────────────────────────────────────────────┐
│                    ВХОДЯЩЕЕ ПИСЬМО                               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                 AI КЛАССИФИКАЦИЯ                                 │
│  email_type: offer/rejection/question/mixed                      │
│  rejection_reason: not_our_profile/not_available/other           │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│               collect_response_signals()                         │
│  - Фильтрует: только offer/mixed/rejection(not_our_profile)     │
│  - Вызывает process_offer_signals или process_rejection_signal   │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                update_supplier_signal()                          │
│  1. Записывает в supplier_response_signals                       │
│  2. Обновляет supplier_product_types (positive/negative +1)      │
│  3. Обновляет supplier_domains (positive/negative +1)            │
│  4. Пересчитывает is_included и confidence                       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│              ПРОФИЛЬ ПОСТАВЩИКА ОБНОВЛЁН                         │
│  - Влияет на будущие запросы                                     │
│  - Доступен для review через Telegram                            │
└─────────────────────────────────────────────────────────────────┘
```

---

## 9. Конфигурация

### Пороговые значения

| Параметр | Значение | Описание |
|----------|----------|----------|
| Порог блокировки | 5 negative | При 0 positive и 5+ negative → is_included=0 |
| Порог включения | 1 positive | Любой positive → is_included=1 |
| LIMIT обработки | 100 писем | За один запуск collect_response_signals |

### Флаги миграции

```sql
migration_flags:
  - use_new_routing: 1/0  -- Использовать новую маршрутизацию
```

---

## 10. Файлы и процедуры

### SQL процедуры

- `collect_response_signals()` — основная процедура сбора
- `process_offer_signals()` — обработка offers
- `process_rejection_signal()` — обработка rejections
- `update_supplier_signal()` — запись сигнала и агрегация

### n8n Workflows

- **Create Email Queue v4 (AI)** — формирование очереди с отбором поставщиков
- **Process Email Conversations1** — AI классификация входящих писем
- **Status Manager** — автоматический сбор сигналов (каждые 2 часа)
- **Telegram Bot - AI Agent** — интерфейс управления

---

*Документ создан: 2025-12-26*
*Версия системы: 3.0 (двумерная классификация)*
