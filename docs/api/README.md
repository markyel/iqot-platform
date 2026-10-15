# IQOT Public API — Руководство разработчика

**Base URL:** `https://iqot.ru/api/v1`
**Auth:** Bearer-токен (`Authorization: Bearer iqot_live_<...>`)
**Формат:** `application/json; charset=utf-8`
**Версионирование:** URL (`/v1/`). Breaking-changes только в `/v2/`.

OpenAPI 3.1 спецификация: [`openapi.yaml`](./openapi.yaml). Разделы: заявки на закупку (`/submissions`)
и [мониторинг цен](#мониторинг-цен) (`/monitoring`).

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

- Доступно под новые заявки = `balance − sum(active_holds) + required_hold * overdraft_percent/100` (уже замороженное учитывается, с 2026-10-14).
- При недоборе: **`402 insufficient_balance`** с `details.required_hold`, `details.balance`, `details.held`.
- Отклонена позиция (модератором, SLA, `no_suppliers_available`, отмена до рассылки) → hold released сразу.
- Собран минимум КП (≥3 цены от разных поставщиков) → списание суммы hold.
- Истёк срок сбора (`deadline_at` submission; если не задан — 30 дней от промоушена) без минимума → разморозка.
- Минимум собран **после** разморозки по сроку → списание постфактум (в течение 90 дней; баланс может уйти в овердрафт, см. `X-Balance-Warning`).
- Модерация: принятые позиции уходят в рассылку сразу, не дожидаясь остальных; позиция, не разобранная за 24 ч, решается автоматически (тип товара определён → принята, нет → `moderation_timeout`); не разосланная к `deadline_at` → `deadline_expired`.

Ручной просмотр: `GET /account/balance` (`balance`, `active_holds`, `available_balance`).

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
| `unclassified` | **да** (нужна ручная переклассификация) |
| `moderation_timeout` | **да** (не разобрана за 24 ч, тип товара не определён) |
| `deadline_expired` | **да** (срок сбора истёк до рассылки позиции) |
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

## Мониторинг цен

Регулярный сбор цен поставщиков по вашей номенклатуре — от сотен до десятков тысяч позиций.
Вы передаёте позиции (код, название, бренд, артикул) — IQOT сам находит поставщиков и страницы
товаров на их сайтах, сопоставляет их с позициями и регулярно обновляет цены. Поставщиков и ссылки
вы видите полностью: у каждой цены — страница поставщика, откуда она получена.

### Режимы (`price_mode`)

| Режим | Что делает |
|---|---|
| `lite` | Цены с сайтов поставщиков (по умолчанию). |
| `hybrid` | То же + работа с поставщиками по почте: (1) выборочное подтверждение цен сайтов («цена с вашего сайта актуальна?») — подтверждённые получают признак `email_confirmed`, растёт доверие к сайту (`site_trust_level`, 0–3); (2) запрос КП у поставщиков, у которых товар на сайте есть, но цена «по запросу»; (3) если цен по позиции меньше трёх — запрос КП профильным поставщикам IQOT. Присланные КП — признак `kp`. |

### Быстрый старт

```bash
# 1. Создать программу (статус draft)
curl -X POST https://iqot.ru/api/v1/monitoring/programs \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{"name":"Каталог лифтовых запчастей","price_mode":"lite","target_freshness_days":30}'
# → 201 {"program_id":"mon_01J…","status":"draft","plan":{"items_max":10000,…},…}

# 2. Загрузить позиции (upsert по client_ref, до 5000 за вызов; больше — несколькими вызовами)
curl -X POST https://iqot.ru/api/v1/monitoring/programs/mon_01J…/items \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -H "Idempotency-Key: catalog-2026-10-15-part1" \
  -d '{"items":[
        {"client_ref":"LW-0001547","name":"Ролик натяжной","brand":"KONE","article":"KM713150G01",
         "client_category":{"code":"cat-114","path":["Лифты","Запчасти","Ролики"]}},
        {"client_ref":"LW-0002478","name":"Кнопка СТОП с фиксацией","brand":"Schaefer","article":"P1EC400E40-K/E",
         "client_category":{"code":"cat-201","path":["Лифты","Электрика","Кнопки"]}}
      ]}'
# → 202 {"import_id":"imp_01J…","status":"queued",…}

# 3. Дождаться импорта
curl -H "Authorization: Bearer $KEY" https://iqot.ru/api/v1/monitoring/programs/mon_01J…/imports/imp_01J…
# → {"status":"done","rows_ok":2,"rows_failed":0,"errors":[]}

# 4. Запустить
curl -X PATCH https://iqot.ru/api/v1/monitoring/programs/mon_01J… \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" -d '{"status":"active"}'

# 5. Забирать цены (инкрементально, см. ниже)
curl -H "Authorization: Bearer $KEY" "https://iqot.ru/api/v1/monitoring/programs/mon_01J…/prices?limit=200"
```

Первые цены появляются в течение часов. Скорость первичного поиска по большой номенклатуре —
порядка 600–1 900 новых позиций в сутки (зависит от загрузки сервиса): 10 тыс. позиций — около
1–2 недель, 30 тыс. — 2–7 недель. Позиции, которые продают уже известные IQOT поставщики,
находятся быстрее — по каталогам их сайтов. Поиск идёт вежливо, с ограничением нагрузки на сайты
поставщиков. Дальше цены перепроверяются каждые `site_refresh_days` дней; ход виден в
`GET /monitoring/programs/{id}` (`items.matched`, `items.covered`).

### Методы

| Метод | Назначение |
|---|---|
| `GET /monitoring/programs` | Список программ |
| `POST /monitoring/programs` | Создать программу (`draft`) |
| `GET /monitoring/programs/{id}` | Программа: режим, свежесть, счётчики позиций, покрытие, лимиты тарифа |
| `PATCH /monitoring/programs/{id}` | `status` (`active`/`paused`), `price_mode`, `name`, `target_freshness_days`, `site_refresh_days` |
| `POST /monitoring/programs/{id}/items` | Загрузить/обновить позиции (асинхронно, 202 + `import_id`) |
| `GET /monitoring/programs/{id}/imports/{import_id}` | Ход импорта, ошибки по строкам |
| `GET /monitoring/programs/{id}/items` | Позиции и статус сопоставления (курсор `next_cursor`) |
| `GET /monitoring/programs/{id}/items/{client_ref}` | Карточка позиции: товар, сводка цен, все источники, история цен (`history_days`, по умолчанию 90) |
| `DELETE /monitoring/programs/{id}/items/{client_ref}` | Снять позицию с мониторинга |
| `GET /monitoring/programs/{id}/prices` | Цены, изменившиеся с `updated_since` (курсор) |
| `GET /monitoring/programs/{id}/export` | Вся программа в CSV (UTF-8, `;`) — для Excel и разовых выгрузок |

### Позиции и сопоставление

- `client_ref` — ваш уникальный код позиции; повторная загрузка с тем же `client_ref` обновляет позицию.
- **Артикул производителя (`article`) — главный ключ поиска.** Внутренние коды учётных систем
  (1С, SAP, «КСУ НСИ» и т. п.) артикулом не являются — передайте их в `client_ref`.
- `client_category` (необязательно, формат как в заявках) — ваша категория товара. IQOT один раз
  сопоставляет каждую вашу категорию со своей таксономией и использует это для подбора поставщиков;
  те же соответствия работают и для ваших заявок через `/submissions`.
- `match_status`: `pending` — ещё сопоставляется; `matched` — однозначно сопоставлена с товаром;
  `ambiguous` — данных недостаточно (например, один артикул у нескольких вариантов товара) —
  уточните название/бренд и загрузите позицию заново; `rejected` — не принята (см. `match_note`).
- `active: false` в загрузке или `DELETE …/items/{client_ref}` снимает позицию с мониторинга;
  `active: true` возвращает.
- Лимит позиций на мониторинге — по тарифу (`plan.items_max`); строки сверх лимита отклоняются
  в импорте с ошибкой `plan_limit_exceeded`.

### Цены: источники и признаки

Каждая позиция отдаётся с массивом `sources` — цена каждого найденного поставщика.
`source_type`: `site` — страница сайта поставщика; `kp` — только КП поставщика без страницы товара
(режим `hybrid`: `url` и `price` пусты, цена в `kp`). Поля:
`supplier_domain`, `url` (страница товара у поставщика), `price`, `currency`, `vat_included`,
`price_unit` (штука/метр/комплект…), `price_kind` (`retail`, `from` — «цена от», `wholesale_tier`,
`on_request`), `availability`, `observed_at`, `status` (`active`; `suspicious` — резкий скачок цены,
перепроверяется) и признаки:

| Признак (`flags`) | Значение |
|---|---|
| `web_price` | Цена получена со страницы сайта поставщика |
| `email_confirmed` | Поставщик подтвердил цену сайта по почте (`email_confirmed_at`) |
| `kp` | Есть КП / счёт поставщика по этому товару (`kp.price`, `kp.received_at`) |

`summary` — сводка по сопоставимым ценам (рубли, розница или «от», без оптовых уровней):
`best_price`, `min`, `median`, `max`, `sources_priced`. Цена источника — цена сайта, а где её нет
(«по запросу», КП без страницы) — цена КП. `best_price` — лучшая цена с поставщиком, ссылкой и
`price_source` (`web` / `kp`).

Цены, которые сайт перестал показывать, не «залипают»: источник без актуальной цены уходит из
сводки. Найденная страница, оказавшаяся другим товаром (аналог, другой типоразмер), в выдачу
не попадает.

### Синхронизация цен

```text
1. GET …/prices                          → items[], next_cursor, server_time  (первая полная выгрузка)
   GET …/prices?cursor=<next_cursor>     … пока next_cursor != null
2. Сохраните server_time из ПЕРВОГО ответа обхода.
3. Через N часов: GET …/prices?updated_since=<server_time>  (+ cursor) — только изменившиеся позиции.
```

Рекомендуемая частота — не чаще раза в час: цены на сайтах перепроверяются раз в несколько дней.
Для разовых и офлайн-задач удобнее `GET …/export` (CSV).

### Покрытие и тарификация

`items.covered` — позиции со свежей ценой (обновлена за `target_freshness_days`). Мониторинг
доступен на тарифах с мониторингом цен; `plan.items_included` позиций входят в абонентскую плату,
сверх них — `plan.price_per_item` ₽ в месяц за **покрытую** позицию (позиции без найденной цены
не тарифицируются). Позиция, по которой есть свежее КП поставщика (режим `hybrid`, признак `kp`),
тарифицируется по ставке × `plan.kp_price_multiplier` (3) — независимо от того, есть ли у неё ещё
и цены с сайтов: КП собирается почтой и требует отдельной работы с поставщиками.

### Ошибки мониторинга

| HTTP | `code` | Когда |
|---|---|---|
| 403 | `monitoring_not_available` | Мониторинг не входит в текущий тариф |
| 404 | `not_found` | Программа/позиция/импорт не найдены (или принадлежат другому ключу клиента) |
| 422 | `validation_error` | Некорректные поля (`details` — по полям) |
| 429 | `rate_limit_exceeded` | Загрузка позиций — 10 rpm, выгрузка CSV — 6 rpm |

---

## Контакты

- API саппорт: api@iqot.ru
- Отчёты о багах: используйте `X-Request-Id` из проблемного ответа.
