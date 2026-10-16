# IQOT Public API v1 — справочник

Версия документа: 16.10.2026 · OpenAPI 3.1: [`openapi.yaml`](./openapi.yaml) · Изменения: [CHANGELOG.md](./CHANGELOG.md)

IQOT принимает две задачи:

- **заявки на закупку** (`/submissions`): поставщики присылают коммерческие предложения (КП), из них собирается отчёт;
- **мониторинг цен** (`/monitoring`): регулярный сбор цен поставщиков по вашей номенклатуре.

Содержание:

1. [Общие сведения](#1-общие-сведения)
2. [Аутентификация](#2-аутентификация)
3. [Формат ошибок](#3-формат-ошибок)
4. [Идемпотентность](#4-идемпотентность)
5. [Ограничения частоты (rate limits)](#5-ограничения-частоты-rate-limits)
6. [Служебные методы](#6-служебные-методы)
7. [Заявки на закупку — /submissions](#7-заявки-на-закупку--submissions)
8. [Баланс — /account](#8-баланс--account)
9. [Справочники — /taxonomy](#9-справочники--taxonomy)
10. [Мониторинг цен — /monitoring](#10-мониторинг-цен--monitoring)
11. [Справочник значений](#11-справочник-значений)
12. [Версионирование и поддержка](#12-версионирование-и-поддержка)

---

## 1. Общие сведения

| | |
|---|---|
| Base URL | `https://iqot.ru/api/v1` |
| Протокол | HTTPS |
| Формат | `application/json; charset=utf-8` (выгрузка мониторинга — `text/csv`) |
| Даты | ISO 8601 с часовым поясом, например `2026-10-15T09:30:00+00:00` |
| Деньги | число, рубли (`currency: "RUB"`), если не указано иное |
| Идентификаторы | `sub_<ULID>` — заявка, `mon_<ULID>` — программа мониторинга, `imp_<ULID>` — импорт позиций. Префикс в пути можно опустить |

В каждом ответе есть заголовок **`X-Request-Id`**. Указывайте его при обращении в поддержку.

---

## 2. Аутентификация

Ключ создаётся в личном кабинете: **Кабинет → API-ключи** (`/cabinet/api-keys`). Ключ показывается один раз — сохраните его сразу. Формат: `iqot_live_` и 48 шестнадцатеричных символов.

Каждый запрос передаёт ключ в заголовке:

```http
Authorization: Bearer iqot_live_0123456789abcdef...
```

Если в настройках ключа задан список разрешённых IP-адресов, запросы с других адресов отклоняются (`401 ip_not_whitelisted`).

Для доступа к API в тарифе должна быть включена опция «Доступ по API». Мониторинг цен доступен на тарифах с мониторингом.

---

## 3. Формат ошибок

Все ошибки возвращаются в одном формате:

```json
{
  "error": {
    "code": "invalid_payload",
    "message": "Request payload failed validation.",
    "details": [
      { "field": "items[0].quantity", "message": "Must be greater than 0" }
    ],
    "request_id": "8f1c2d3e-…"
  }
}
```

`details` есть не всегда. В методах мониторинга `details` — объект «поле → список сообщений».

| HTTP | `code` | Когда |
|---|---|---|
| 400 | `invalid_payload` | Тело заявки не прошло проверку |
| 400 | `sender_not_configured` | Для указанной `client_organization_id` не настроен ящик-отправитель |
| 401 | `invalid_api_key` | Ключ не найден или заголовок неверен |
| 401 | `key_revoked` | Ключ отозван |
| 401 | `ip_not_whitelisted` | IP-адрес не входит в список разрешённых для ключа |
| 402 | `insufficient_balance` | Недостаточно средств с учётом овердрафта |
| 403 | `api_access_denied` | В тарифе нет доступа по API |
| 403 | `api_client_disabled` | API-клиент отключён |
| 403 | `monitoring_not_available` | В тарифе нет мониторинга цен |
| 404 | `submission_not_found` | Заявка не найдена или принадлежит другому клиенту |
| 404 | `not_found` | Программа, позиция или импорт мониторинга не найдены |
| 409 | `idempotency_key_conflict` | Повтор `Idempotency-Key` с другим телом запроса |
| 409 | `report_not_ready` | Отчёт запрошен до набора минимума КП |
| 422 | `validation_error` | Ошибка в полях запроса (методы мониторинга) |
| 429 | `rate_limit_exceeded` | Превышено ограничение частоты; ждите `Retry-After` секунд |
| 500 | `internal_error` | Внутренняя ошибка. Повторите позже, сообщите `request_id` |

---

## 4. Идемпотентность

Методы, которые создают данные (`POST /submissions`, `POST /monitoring/programs/{id}/items`), принимают заголовок `Idempotency-Key` — строку длиной 1–128 символов.

- Повтор с тем же ключом **и тем же телом** возвращает исходный результат (`200`) без создания дубля.
- Повтор с тем же ключом **и другим телом** возвращает `409 idempotency_key_conflict` (для `/submissions`).
- Без заголовка защиты от дублей при повторах нет.

Рекомендация: формируйте ключ из вашего внутреннего номера документа, например `PO-2026-001` или `catalog-2026-10-15-part3`.

---

## 5. Ограничения частоты (rate limits)

| Метод | Лимит |
|---|---|
| `POST /submissions` | 10 запросов в минуту на ключ |
| `GET /submissions/{id}` | 1 запрос в 15 секунд на пару «ключ + заявка» |
| `POST /monitoring/programs/{id}/items` | 10 запросов в минуту на ключ |
| `GET /monitoring/programs/{id}/export` | 6 запросов в минуту на ключ |
| Все методы вместе | 60 запросов в минуту на ключ |

В ответах приходят заголовки `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`. При превышении — `429` и заголовок `Retry-After` (секунды).

---

## 6. Служебные методы

### `GET /ping` — проверка ключа

```bash
curl -H "Authorization: Bearer $KEY" https://iqot.ru/api/v1/ping
```

```json
{ "ok": true, "api_client_id": 12, "user_id": 345, "server_time": "2026-10-15T09:30:00+00:00" }
```

---

## 7. Заявки на закупку — /submissions

Порядок работы: создать заявку, опрашивать её статус, получить отчёт по КП. Позиции модерируются, затем рассылаются профильным поставщикам; ответы разбираются автоматически.

### `POST /submissions` — создать заявку

Заголовки: `Idempotency-Key` (рекомендуется).

| Поле | Тип | Обяз. | Описание |
|---|---|---|---|
| `client_ref` | string ≤128 | нет | Ваш номер заявки |
| `client_organization_id` | integer | нет | Организация, от имени которой идёт рассылка (именная заявка). Без поля заявка анонимная |
| `deadline` | date-time | нет | Срок сбора КП: от 7 до 60 дней от текущего момента. По умолчанию +30 дней |
| `items` | array, 1–300 | да | Позиции |

Позиция (`items[]`):

| Поле | Тип | Обяз. | Описание |
|---|---|---|---|
| `client_ref` | string ≤128 | нет | Ваш код строки |
| `name` | string ≤500 | да | Наименование |
| `article` | string ≤255 | нет | Артикул производителя |
| `brand` | string ≤255 | нет | Бренд / производитель |
| `quantity` | number > 0 | да | Количество |
| `unit` | string ≤32 | да | Единица измерения |
| `description` | string ≤5000 | нет | Дополнительные требования |
| `client_category` | object | нет | Ваша категория: `code` (обязателен, ≤128), `path` (до 10 уровней), `metadata` (любой объект) |

```bash
curl -X POST https://iqot.ru/api/v1/submissions \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -H "Idempotency-Key: PO-2026-001" \
  -d '{
    "client_ref": "PO-2026-001",
    "deadline": "2026-11-15T12:00:00Z",
    "items": [{
      "client_ref": "LINE-1", "name": "Подшипник SKF 6205-2RS", "article": "6205-2RS", "brand": "SKF",
      "quantity": 20, "unit": "шт",
      "client_category": { "code": "EL.REL.01", "path": ["Запчасти", "Электрика", "Релейная аппаратура"] }
    }]
  }'
```

Ответ `202 Accepted` (при идемпотентном повторе — `200`):

```json
{
  "submission_id": "sub_01HXYZ…",
  "status": "accepted",
  "stage": "inbox_buffered",
  "client_ref": "PO-2026-001",
  "items_count": 1,
  "created_at": "2026-10-15T09:30:00+00:00",
  "estimated_ready_at": "2026-10-16T09:30:00+00:00"
}
```

Ошибки: `400 invalid_payload`, `400 sender_not_configured`, `402 insufficient_balance` (в `details`: `required_hold`, `balance`, `held`, `overdraft_percent`), `409 idempotency_key_conflict`, `429`.

### `GET /submissions/{id}` — статус заявки

Заголовки ответа:

- `X-Status-Changed-At` — когда статус менялся последний раз;
- `X-Next-Check-After` — рекомендуемое время следующего запроса.

| Поле | Описание |
|---|---|
| `submission_id`, `client_ref`, `client_organization_id` | Идентификаторы |
| `status` | `accepted`, `processing`, `ready`, `ready_minimum`, `completed`, `cancelled` (см. §11) |
| `stage` | Детальная стадия обработки |
| `created_at`, `status_changed_at`, `ready_at`, `deadline` | Даты |
| `counts` | `total`, `accepted`, `rejected`, `awaiting_suppliers`, `dispatched`, `with_offers_minimum`, `completed` |
| `items[]` | Позиции (см. ниже) |
| `rejected_items[]` | Отклонённые: `client_ref`, `name`, `reason`, `message`, `retryable` |

Позиция (`items[]`): `item_id`, `client_ref`, `name`, `quantity`, `unit`, `status`, `classification` (`product_type` и `domain`: `{id, slug, name}`), `offers_count`, `minimum_threshold` (3), `report_available`, `collection_deadline`.

Рекомендуемая частота опроса (её же отражает `X-Next-Check-After`):

| Стадия | Интервал |
|---|---|
| Приём, классификация, модерация | 2 мин |
| Подбор поставщиков | 30 мин |
| Рассылка | 5 мин |
| Сбор КП, первые сутки | 15 мин |
| Сбор КП, далее | 1 ч |
| Минимум собран | 1 ч |

### `GET /submissions/{id}/items` — только позиции

Ответ: `{ "items": [ … ] }` в формате позиций из `GET /submissions/{id}`.

### `GET /submissions/{id}/report` — отчёт по КП

Доступен, когда хотя бы одна позиция набрала минимум (3 КП от разных поставщиков). До этого — `409 report_not_ready`.

```json
{
  "submission_id": "sub_01HXYZ…",
  "client_ref": "PO-2026-001",
  "generated_at": "2026-10-20T10:00:00+00:00",
  "status": "ready_minimum",
  "deadline": "2026-11-15T12:00:00+00:00",
  "items": [{
    "item_id": "…", "name": "Подшипник SKF 6205-2RS", "quantity": 20, "unit": "шт",
    "classification": { "product_type": { "id": 41, "slug": "bearings", "name": "Подшипники" }, "domain": null },
    "offers_count": 4, "minimum_threshold": 3, "status": "ready_minimum",
    "best_offer_by_price": {
      "supplier_id": 755, "price_per_unit": 312.5, "total_price": 6250, "currency": "RUB",
      "price_includes_vat": true, "delivery_days": 5, "payment_terms": "100% предоплата",
      "notes": null, "received_at": "2026-10-18T07:12:00+00:00"
    },
    "all_offers": [ … ]
  }]
}
```

### `POST /submissions/{id}/cancel` — отменить заявку

Тело (необязательно): `{ "reason": "строка ≤255" }`.

Ответ: `{ "submission_id", "status": "cancelled" | "completed", "cancelled_at", "was_promoted" }`.

- До начала рассылки все позиции отклоняются с причиной `cancelled_by_client`, резерв средств снимается.
- После начала рассылки заявка отменяется, а уже полученные КП и резервы проходят обычный цикл.
- Если заявка уже завершена, вернётся `"status": "completed"`.

### Оплата заявок

- При создании заявки резервируется сумма по позициям сверх включённых в тариф.
- Доступно для новых заявок: баланс минус активные резервы плюс разрешённый овердрафт.
- Позиция отклонена или отменена до рассылки — резерв снимается сразу.
- Позиция набрала минимум (≥3 КП от разных поставщиков) — резерв списывается.
- Срок сбора истёк без минимума — резерв снимается. Если минимум набран позже (в течение 90 дней), сумма списывается постфактум; баланс может уйти в овердрафт (заголовок `X-Balance-Warning`).
- Позиция, не разобранная модерацией за 24 часа, решается автоматически: если тип товара определён — принимается, если нет — отклоняется с `moderation_timeout`.

---

## 8. Баланс — /account

### `GET /account/balance`

```json
{
  "balance": 15000.00,
  "currency": "RUB",
  "active_holds": 2370.00,
  "overdraft_limit_percent": 10,
  "overdraft_limit_absolute": 1500.00,
  "warning": null
}
```

`warning` — например `overdraft_123.45_rub`, если баланс ушёл в овердрафт.

---

## 9. Справочники — /taxonomy

| Метод | Ответ |
|---|---|
| `GET /taxonomy/domains` | `{ "items": [{ "id", "slug", "name", "parent_id" }] }` — области применения |
| `GET /taxonomy/product-types` | `{ "items": [...], "filter", "count" }` — типы товаров (листья). Фильтр: `?domain_id=1` |

---

## 10. Мониторинг цен — /monitoring

Вы передаёте номенклатуру (код, название, бренд, артикул, категорию). IQOT сам находит поставщиков и страницы товаров на их сайтах, сопоставляет их с вашими позициями и регулярно обновляет цены. Для каждой цены видны поставщик и страница, откуда она взята.

**Режимы (`price_mode`)**

| Режим | Что делает |
|---|---|
| `lite` | Цены с сайтов поставщиков. Режим по умолчанию |
| `hybrid` | Всё, что в `lite`, плюс работа с поставщиками по почте: подтверждение цен сайтов, запрос КП там, где на сайте «цена по запросу», и запрос КП профильным поставщикам, если цен меньше трёх. Подробно — в [инструкции по Гибриду](./Monitoring_Hybrid_Guide.md) |

**Жизненный цикл программы:** `draft` → загрузка позиций → `active`. Программу можно поставить на паузу (`paused`) и снова запустить.

### 10.1. `POST /monitoring/programs` — создать программу

| Поле | Тип | Обяз. | По умолч. | Описание |
|---|---|---|---|---|
| `name` | string 2–255 | да | | Название |
| `price_mode` | `lite` \| `hybrid` | нет | `lite` | Режим |
| `target_freshness_days` | integer 7–180 | нет | 30 | Цена считается свежей, если обновлена не раньше, чем столько дней назад. От этого зависит покрытие |
| `site_refresh_days` | integer 7–90 | нет | 14 | Как часто перепроверять цены на сайтах |
| `settings` | object | нет | | Произвольные настройки |

Ответ `201` — объект программы (§10.3). Ошибка `403 monitoring_not_available` — в тарифе нет мониторинга.

### 10.2. `GET /monitoring/programs` — список программ

Ответ: `{ "programs": [ <программа>, … ] }`.

### 10.3. `GET /monitoring/programs/{id}` — программа

```json
{
  "program_id": "mon_01M4WTQKJV2RGHZ49NYJ15E38H",
  "name": "Каталог лифтовых запчастей",
  "status": "active",
  "price_mode": "hybrid",
  "target_freshness_days": 30,
  "site_refresh_days": 14,
  "items": { "total": 1052, "active": 1052, "matched": 1051, "ambiguous": 1, "covered": 812 },
  "plan": { "items_max": 15000, "items_included": 500, "price_per_item": 20, "kp_price_multiplier": 3 },
  "billing": {
    "accrued_unbilled": 4180.27, "unbilled_days": 5,
    "last_charge": { "amount": 5852.40, "period_from": "2026-10-15", "period_to": "2026-10-21", "charged_at": "2026-10-22T00:20:05+03:00" },
    "warning": null
  },
  "paused_reason": null,
  "created_at": "2026-10-14T08:00:00+00:00"
}
```

| Поле | Описание |
|---|---|
| `items.total` / `active` | Загружено позиций / из них на мониторинге |
| `items.matched` | Однозначно сопоставлены с товаром |
| `items.ambiguous` | Сопоставлены неоднозначно — уточните данные |
| `items.covered` | Позиции со свежей ценой (в пределах `target_freshness_days`) |
| `plan.items_max` | Максимум позиций на мониторинге по тарифу |
| `plan.items_included` | Позиций, входящих в абонентскую плату |
| `plan.price_per_item` | ₽ в месяц за покрытую позицию сверх включённых |
| `plan.kp_price_multiplier` | Множитель ставки для позиции со свежим КП (режим `hybrid`) |
| `billing.accrued_unbilled` / `unbilled_days` | Начислено и ещё не списано, ₽ / за сколько суток |
| `billing.last_charge` | Последнее списание: сумма и период |
| `billing.warning` | Не хватает средств на списание: `due` — сумма, `pause_after` — когда программа встанет на паузу. Иначе `null` |
| `paused_reason` | `billing` — программа на паузе из-за нехватки средств (снимется автоматически после пополнения и списания) |

### 10.4. `PATCH /monitoring/programs/{id}` — изменить программу

Можно передать любые из полей: `name`, `status` (`active` \| `paused`), `price_mode` (`lite` \| `hybrid`), `target_freshness_days`, `site_refresh_days`. Ответ — обновлённая программа.

```bash
curl -X PATCH https://iqot.ru/api/v1/monitoring/programs/mon_01M4… \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"status":"active","price_mode":"hybrid"}'
```

### 10.5. `POST /monitoring/programs/{id}/items` — загрузить или обновить позиции

Upsert по `client_ref`, до 5 000 позиций за вызов; больший список отправляйте несколькими вызовами. Обработка асинхронная: ответ `202` с `import_id`. Повтор с тем же `Idempotency-Key` вернёт тот же импорт (`200`).

| Поле позиции | Тип | Обяз. | Описание |
|---|---|---|---|
| `client_ref` | string ≤191 | да | Ваш уникальный код позиции — ключ обновления |
| `name` | string 2–1000 | да | Наименование |
| `brand` | string ≤255 | нет | Бренд / производитель |
| `article` | string ≤255 | нет | **Артикул производителя (OEM/MPN) — главный ключ поиска.** Внутренние коды учётных систем (1С, SAP и т. п.) передавайте в `client_ref`, а не сюда |
| `oem_articles` | array of string, до 20 | нет | Все OEM-артикулы позиции (кросс-номера), например `["KM247024", "KM85869H01", "PC-04L=600"]`. См. «Какие страницы видит позиция» ниже |
| `unit` | string ≤32 | нет | Единица измерения |
| `attributes` | object | нет | Произвольные характеристики |
| `active` | boolean | нет | `false` снимает позицию с мониторинга, `true` возвращает |
| `client_category` | object | нет | Ваша категория, формат как в заявках: `code` (обязателен), `path`, `metadata`. **В режиме `hybrid` настоятельно рекомендуется**: по ней подбираются профильные поставщики |

```bash
curl -X POST https://iqot.ru/api/v1/monitoring/programs/mon_01M4…/items \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -H "Idempotency-Key: catalog-2026-10-15-part1" \
  -d '{"items":[
    {"client_ref":"LW-0001547","name":"Ролик натяжной","brand":"KONE","article":"KM713150G01",
     "client_category":{"code":"cat-114","path":["Лифты","Запчасти","Ролики"]}}
  ]}'
```

```json
{ "import_id": "imp_01J9Z8K3…", "status": "queued", "rows_total": 1, "rows_ok": 0, "rows_failed": 0, "errors": [] }
```

Строки сверх лимита тарифа отклоняются построчно с ошибкой `plan_limit_exceeded`.

**Какие страницы видит позиция.** Позиции показываются страницы поставщиков, на которых найден хотя бы один её артикул (`article` или `oem_articles`). Страницы других вариантов товара не показываются, даже если поставщики указывают их под общими кросс-номерами. Пример: позиция «Ремень тахометра D4×600, KM247024» не получит страницы ремня D4×550 (FI264123). Поэтому передавайте **все** OEM-артикулы позиции: страница, где указан только кросс-номер, которого нет в вашем списке, не попадёт в выдачу.

### 10.6. `GET /monitoring/programs/{id}/imports/{import_id}` — ход импорта

```json
{
  "import_id": "imp_01J9Z8K3…",
  "status": "done",
  "rows_total": 5000, "rows_ok": 4998, "rows_failed": 2,
  "errors": [ { "row": 17, "client_ref": "LW-17", "error": "plan_limit_exceeded" } ]
}
```

`status`: `queued`, `processing`, `done`, `failed`.

### 10.7. `GET /monitoring/programs/{id}/items` — позиции и сопоставление

Параметры: `match_status` (`pending` \| `matched` \| `ambiguous` \| `rejected`), `cursor`, `limit` (1–1000, по умолчанию 500).

Ответ: `{ "items": [...], "next_cursor": 12345 | null }`. Позиция: `client_ref`, `name`, `brand`, `article`, `oem_articles`, `active`, `match_status`, `match_note`, `last_priced_at`.

Значения `match_status`:

- `pending` — позиция ещё сопоставляется;
- `matched` — однозначно сопоставлена с товаром;
- `ambiguous` — данных недостаточно (например, один артикул у нескольких вариантов товара); уточните название или бренд и загрузите позицию заново;
- `rejected` — позиция не принята, причина в `match_note`.

### 10.8. `GET /monitoring/programs/{id}/items/{client_ref}` — карточка позиции

`client_ref` передаётся в URL-кодировке. Параметр `history_days` (1–365, по умолчанию 90) задаёт глубину истории цен.

Ответ — объект цены позиции (§10.10) плюс поля `brand`, `article`, `oem_articles`, `active`, `match_status`, `match_note` и `product` (сопоставленный товар: `name`, `brand`, `identifiers[]` с `kind` из `oem`/`mpn`/`gtin` и `value`). У каждого источника есть `history[]`: `observed_at`, `price`, `currency`, `source` (`web` \| `kp`).

### 10.9. `DELETE /monitoring/programs/{id}/items/{client_ref}` — снять позицию

Ответ: `{ "client_ref": "LW-0001547", "active": false }`. Вернуть позицию — загрузить её с `"active": true`.

### 10.10. `GET /monitoring/programs/{id}/prices` — цены (синхронизация)

Параметры: `updated_since` (date-time), `cursor`, `limit` (1–500, по умолчанию 200).

Ответ: `{ "items": [...], "next_cursor": … | null, "server_time": "…" }`.

```json
{
  "client_ref": "LW-0001547",
  "name": "Ролик натяжной",
  "prices_changed_at": "2026-10-15T06:10:00+00:00",
  "last_priced_at": "2026-10-15T06:10:00+00:00",
  "best_price": {
    "price": 4180, "currency": "RUB", "supplier_domain": "liftsale.ru",
    "url": "https://liftsale.ru/…/km713150g01", "price_source": "web",
    "flags": { "web_price": true, "email_confirmed": true, "kp": false }
  },
  "summary": { "best_price": 4180, "min": 4180, "median": 4650, "max": 5200, "sources_priced": 3, "currency": "RUB" },
  "sources": [
    {
      "source_type": "site",
      "supplier_domain": "liftsale.ru",
      "url": "https://liftsale.ru/…/km713150g01",
      "price": 4180, "currency": "RUB", "price_kind": "retail", "vat_included": true,
      "price_unit": "piece", "price_unit_qty": 1, "availability": "in_stock",
      "observed_at": "2026-10-15T06:10:00+00:00", "status": "active",
      "flags": { "web_price": true, "email_confirmed": true, "kp": false },
      "email_confirmed_at": "2026-10-12T11:00:00+00:00",
      "kp": null,
      "site_trust_level": 2
    },
    {
      "source_type": "kp",
      "supplier_domain": "lift-komplekt.ru",
      "url": null, "price": null, "currency": "RUB", "price_kind": null, "vat_included": true,
      "price_unit": null, "price_unit_qty": null, "availability": null,
      "observed_at": "2026-10-14T13:20:00+00:00", "status": "active",
      "flags": { "web_price": false, "email_confirmed": false, "kp": true },
      "email_confirmed_at": null,
      "kp": { "price": 4650, "currency": "RUB", "vat_included": true, "received_at": "2026-10-14T13:20:00+00:00" },
      "site_trust_level": null
    }
  ]
}
```

**Источники (`sources[]`):**

| Поле | Описание |
|---|---|
| `source_type` | `site` — страница сайта поставщика; `kp` — только КП поставщика без страницы товара (режим `hybrid`) |
| `supplier_domain`, `url` | Поставщик и страница товара. У `source_type=kp` поле `url` пустое |
| `price` | Цена с сайта. Пусто, если на сайте «цена по запросу» или источник — только КП |
| `price_kind` | `retail`, `from` («цена от»), `wholesale_tier` (оптовый уровень), `on_request` |
| `vat_included`, `price_unit`, `price_unit_qty`, `availability` | НДС, единица цены, количество в единице, наличие |
| `status` | `active`; `suspicious` — резкий скачок цены, идёт перепроверка |
| `flags` | См. ниже |
| `kp` | КП поставщика: `price`, `currency`, `vat_included`, `received_at` |
| `site_trust_level` | Доверие к ценам сайта, 0–3 (растёт с подтверждениями поставщика). Справочно |

**Признаки цены (`flags`)** независимы, у одного источника их может быть несколько:

| Признак | Значение |
|---|---|
| `web_price` | Цена получена со страницы сайта поставщика |
| `email_confirmed` | Поставщик подтвердил цену сайта по почте (`email_confirmed_at`) |
| `kp` | Есть КП / счёт поставщика по этому товару |

**Сводка (`summary`)** считается по сопоставимым ценам: рубли, розница или «от», без оптовых уровней, только источники в статусе `active`. Для источника берётся цена сайта, а если её нет — цена КП. `best_price.price_source` показывает, откуда лучшая цена: `web` или `kp`.

Источник, у которого цена на сайте пропала, выпадает из сводки — устаревшие цены не «залипают». Страница, оказавшаяся другим товаром (аналог, другой типоразмер), в выдачу не попадает.

**Схема синхронизации:**

```text
1. GET …/prices                       → items, next_cursor, server_time   (полная выгрузка)
   GET …/prices?cursor=<next_cursor>  … пока next_cursor не станет null
2. Сохраните server_time из ПЕРВОГО ответа обхода.
3. Через N часов: GET …/prices?updated_since=<server_time> (+ cursor) — только изменившиеся позиции.
```

Синхронизируйтесь не чаще раза в час: цены на сайтах перепроверяются раз в несколько дней.

### 10.11. `GET /monitoring/programs/{id}/export` — выгрузка в CSV

Кодировка UTF-8 с BOM, разделитель `;` — файл открывается в Excel. Одна строка на пару «позиция × источник с ценой или КП»; позиция без цен — одной строкой.

Колонки: `client_ref`, `name`, `brand`, `article`, `match_status`, `best_price`, `min_price`, `median_price`, `max_price`, `sources_priced`, `supplier_domain`, `url`, `price`, `currency`, `vat_included`, `price_unit`, `availability`, `observed_at`, `web_price`, `email_confirmed`, `kp`, `kp_price`, `site_trust_level`.

### 10.12. Покрытие и тарификация мониторинга

- Платная единица — **покрытая позиция в месяц**: позиция, у которой есть свежая цена (не старше `target_freshness_days`). Позиции без найденной цены не оплачиваются.
- `plan.items_included` позиций входят в абонентскую плату. Сверх них — `plan.price_per_item` ₽ в месяц за позицию.
- **Позиция со свежим КП поставщика** (режим `hybrid`, признак `kp`) оплачивается по ставке × `plan.kp_price_multiplier` (3), даже если у неё есть и цены с сайтов. КП считается свежим 60 дней с момента получения.
- Первичный поиск и сопоставление отдельно не оплачиваются.
- **Начисление — ежедневно:** (позиции без свежего КП × `price_per_item` + позиции со свежим КП × `price_per_item` × `kp_price_multiplier`) / 30, за вычетом `items_included` (сначала в счёт позиций без КП). Включённые позиции общие для всех программ клиента. Свежее КП — полученное по запросу этой программы.
- **Списание с баланса — раз в 7 дней** и в конце периода подписки, одной строкой на программу за период. Детализация — в `billing` программы.
- **Не хватает средств:** в `billing.warning` появляется сумма и дата паузы; через 3 дня без пополнения программы клиента встают на паузу (`status: paused`, `paused_reason: billing`) — найденные цены сохраняются, перепроверка и письма останавливаются. После пополнения списание проходит при следующем расчёте, пауза снимается автоматически.

### 10.13. Сроки

Первые цены появляются в течение нескольких часов после запуска программы. Первичный поиск по большой номенклатуре — порядка 600–1 900 новых позиций в сутки: 10 тыс. позиций — 1–2 недели, 30 тыс. — 2–7 недель. Затем цены перепроверяются каждые `site_refresh_days` дней. Ход работы виден в `GET /monitoring/programs/{id}` (`items.matched`, `items.covered`).

---

## 11. Справочник значений

### Статусы заявки (`status`)

| Значение | Смысл |
|---|---|
| `accepted` | Принята, ещё не классифицируется |
| `processing` | Классификация, модерация, подбор поставщиков |
| `ready` | Модерация завершена |
| `ready_minimum` | По всем принятым позициям набран минимум (3 КП) |
| `completed` | Сбор завершён |
| `cancelled` | Отменена |

### Статусы позиции заявки

| Значение | Смысл |
|---|---|
| `pending` | Классификация / модерация |
| `accepted` | Принята, ждёт подбора поставщиков |
| `awaiting_suppliers` | Поставщиков не хватает, идёт их поиск |
| `dispatched` | Запрос отправлен поставщикам |
| `collecting` | КП собираются, минимум пока не набран |
| `ready_minimum` | Минимум набран (≥3 КП) |
| `completed` | Сбор завершён |
| `rejected` | Отклонена |

### Причины отклонения позиции

| Код | Можно повторить | Смысл |
|---|---|---|
| `b2c_consumer_goods` | нет | Потребительский товар |
| `out_of_scope` | нет | Вне тематики сервиса |
| `insufficient_data` | да | Недостаточно данных — дополните и отправьте снова |
| `duplicate` | да | Дубль |
| `no_suppliers_available` | да | Поставщики не найдены (могут появиться позже) |
| `moderator_rejected` | нет | Отклонено модератором |
| `unclassified` | да | Не удалось классифицировать |
| `moderation_timeout` | да | Не разобрана за 24 ч, тип товара не определён |
| `deadline_expired` | да | Срок сбора истёк до рассылки позиции |
| `cancelled_by_client` | — | Отменено вами |

---

## 12. Версионирование и поддержка

- Версия задаётся в URL (`/v1/`). Без смены версии могут появиться новые необязательные поля, новые значения перечислений (неизвестные значения нужно игнорировать) и новые методы.
- Удаление и переименование полей, смена типа или смысла — только в `/v2/`. Об устаревании предупредим заголовками `Deprecation: true` и `Sunset` и письмом за 6 месяцев.
- Поддержка: api@iqot.ru. В обращении укажите `X-Request-Id` проблемного ответа.
