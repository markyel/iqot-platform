# IQOT Public API — Руководство разработчика

**Base URL:** `https://iqot.ru/api/v1`
**Auth:** Bearer-токен (`Authorization: Bearer iqot_live_<...>`)
**Формат:** `application/json; charset=utf-8`
**Версионирование:** URL (`/v1/`). Breaking-changes только в `/v2/`.

OpenAPI 3.1 спецификация: [`openapi.yaml`](./openapi.yaml).

---

## Быстрый старт

### 1. Получить ключ

Зайдите в ЛК → `/cabinet/api-keys` → «Создать ключ». Сохраните выданный ключ — он показывается один раз.

### 2. Проверить ключ

```bash
curl -H "Authorization: Bearer $KEY" https://iqot.ru/api/v1/ping
# → {"ok":true,"api_client_id":…,"user_id":…,"server_time":"…"}
```

### 3. Создать submission

```bash
curl -X POST https://iqot.ru/api/v1/submissions \
  -H "Authorization: Bearer $KEY" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: po-2026-001" \
  --data-binary @body.json
```

`body.json`:
```json
{
  "client_ref": "PO-2026-001",
  "deadline": "2026-05-15T12:00:00Z",
  "items": [
    {
      "client_ref": "LINE-1",
      "name": "Подшипник SKF 6205-2RS",
      "article": "6205-2RS",
      "brand": "SKF",
      "quantity": 20,
      "unit": "шт",
      "client_category": {
        "code": "EL.REL.01",
        "path": ["Запчасти", "Электрика", "Релейная аппаратура"]
      }
    }
  ]
}
```

Ответ `202 Accepted`:
```json
{
  "submission_id": "sub_01HXYZ…",
  "status": "accepted",
  "stage": "inbox_buffered",
  "client_ref": "PO-2026-001",
  "items_count": 1,
  "created_at": "…",
  "estimated_ready_at": "…"
}
```

### 4. Опрашивать статус

```bash
curl -H "Authorization: Bearer $KEY" \
  https://iqot.ru/api/v1/submissions/sub_01HXYZ…
```

Используйте заголовок **`X-Next-Check-After`** чтобы не делать лишних запросов (он учитывает текущую стадию).

### 5. Получить отчёт

```bash
curl -H "Authorization: Bearer $KEY" \
  https://iqot.ru/api/v1/submissions/sub_01HXYZ…/report
```

Вернёт `409 report_not_ready`, пока ни одна позиция не набрала ≥3 КП. Когда отчёт готов — JSON с `items[].best_offer_by_price` и `all_offers`.

---

## Идемпотентность

- Присылайте `Idempotency-Key: <любая строка 1..128 chars>` в POST /submissions.
- Повтор с тем же ключом **и тем же телом** → `200 OK` с тем же `submission_id`.
- Повтор с тем же ключом **и другим телом** → `409 idempotency_key_conflict`.
- Если не прислать — сервер сгенерирует ключ сам (идемпотентность не работает для вашей retry-логики).

**Рекомендация:** используйте `Idempotency-Key` равный вашему внутреннему PO-номеру + timestamp, чтобы retry был безопасным.

---

## Rate Limits

| Эндпоинт | Лимит | При превышении |
|---|---|---|
| `POST /submissions` | 10 rpm на ключ | `429 rate_limit_exceeded` + `Retry-After: 60` |
| `GET /submissions/{id}` | 1 раз / 15 сек на пару `(ключ, id)` | `429` + `Retry-After: 15` |
| Всё вместе | 60 rpm на ключ | `429` + `Retry-After: 60` |

В каждом ответе приходят:
- `X-RateLimit-Limit`
- `X-RateLimit-Remaining`
- `X-RateLimit-Reset`

---

## Биллинг

**Попозиционный hold** создаётся при POST /submissions. Считается как сумма `price_per_item_over_limit` за каждую позицию сверх items_limit тарифа.

- Баланс = `user.balance - sum(active_holds)`.
- Доступный лимит = `balance + (required_hold * overdraft_percent/100)`.
- При недоборе: **`402 insufficient_balance`** с `details.required_hold`.
- Отклонена позиция → hold released.
- Собран минимум КП (3) → списание.
- Таймаут 7 дней без минимума → автоматическая разморозка.

Ручной просмотр: `GET /account/balance`.

---

## Статусы

### Submission-level

| Status | Смысл |
|---|---|
| `accepted` | В inbox, ещё не классифицируется |
| `processing` | Классификация / модерация / ожидание пула |
| `ready` | Модерация пройдена, accepted/rejected финализирован |
| `ready_minimum` | По всем accepted достигнут минимум (3 КП) |
| `completed` | Сбор завершён |
| `cancelled` | Отменено |

### Item-level (публичные)

| Status | Смысл |
|---|---|
| `pending` | Классификация/модерация |
| `accepted` | Модератор принял, ждёт пула |
| `awaiting_suppliers` | Недобор, запущен Discovery |
| `dispatched` | Заявка отправлена поставщикам |
| `collecting` | КП собираются, минимума пока нет |
| `ready_minimum` | Минимум собран (≥3) |
| `completed` | Сбор завершён |
| `rejected` | Отклонена |

---

## Причины отклонения

| Код | Retryable |
|---|---|
| `b2c_consumer_goods` | нет |
| `out_of_scope` | нет |
| `insufficient_data` | **да** (можно доработать и повторить) |
| `duplicate` | **да** |
| `no_suppliers_available` | **да** (возможно позже появятся) |
| `moderator_rejected` | нет |
| `cancelled_by_client` | — |

---

## Ошибки

Единый формат (§14.1):

```json
{
  "error": {
    "code": "invalid_payload",
    "message": "Request payload failed validation.",
    "details": [
      { "field": "items[0].quantity", "message": "Must be greater than 0" }
    ],
    "request_id": "…"
  }
}
```

В каждом ответе API — заголовок `X-Request-Id` (тот же UUID). Сообщайте его в саппорт.

| HTTP | Код | Когда |
|---|---|---|
| 400 | `invalid_payload` | Валидация схемы |
| 400 | `sender_not_configured` | Нет sender для указанного `client_organization_id` |
| 401 | `invalid_api_key` | Ключ не найден / заголовок неверен |
| 401 | `key_revoked` | Ключ отозван < 30 дней назад |
| 401 | `ip_not_whitelisted` | IP не в whitelist ключа |
| 402 | `insufficient_balance` | Недостаточно средств с учётом overdraft |
| 403 | `api_access_denied` | Нет фичи `api_access` в тарифе |
| 403 | `api_client_disabled` | api_client.is_active = 0 |
| 404 | `submission_not_found` | submission_id не принадлежит ключу |
| 409 | `idempotency_key_conflict` | Повтор ключа с другим payload |
| 409 | `report_not_ready` | Отчёт запрошен до `ready_minimum` |
| 429 | `rate_limit_exceeded` | Превышен rate limit |
| 500 | `internal_error` | Внутренняя ошибка |

---

## Polling

Рекомендуемый интервал вычисляется сервером и отдаётся в `X-Next-Check-After`:

| Stage | Интервал |
|---|---|
| inbox / classifying / moderation | +2 мин |
| awaiting_suppliers | +30 мин |
| dispatching | +5 мин |
| collecting (первые сутки) | +15 мин |
| collecting (далее) | +1 ч |
| ready_minimum | +1 ч |

---

## Cancel

```bash
curl -X POST https://iqot.ru/api/v1/submissions/sub_01HXYZ…/cancel \
  -H "Authorization: Bearer $KEY" \
  -H "Content-Type: application/json" \
  -d '{"reason":"Client changed mind"}'
```

Семантика:
- До промоушена: все позиции → `rejected` с `cancelled_by_client`, holds размораживаются.
- После промоушена: reports.request тоже помечается cancelled. Существующие holds/КП проходят обычный lifecycle (см. §10).

---

## Версионирование

- **Allowed без bump**: новое опциональное поле в request/response; новый enum-значение (клиент должен игнорировать неизвестные); новый endpoint.
- **Требует /v2/**: удаление/переименование поля, изменение типа, превращение опционального в обязательное, изменение семантики enum.
- Deprecation-заголовок `Sunset` + `Deprecation: true` + email-уведомления за 6 месяцев.

---

## Справочники

```bash
GET /taxonomy/domains            # → [{id, slug, name, parent_id}, …]
GET /taxonomy/product-types      # → leaf-типы
GET /taxonomy/product-types?domain_id=1
```

Используйте эти id чтобы заранее отфильтровать товары перед отправкой.

---

## Контакты

- API саппорт: api@iqot.ru
- Отчёты о багах: используйте `X-Request-Id` из проблемного ответа.
