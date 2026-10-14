# Ф0 «Фундамент» режима мониторинга цен — спецификация

**Статус:** v1 (2026-10-14), до кода. Основание: `docs/monitoring-design.md` v0.5 (+ §9.1 пилот, §11/§11a решения).
**Цель Ф0:** всё, на чём стоят Lite и Гибрид, — **без поиска поставщиков и без писем**. На выходе Ф0: программа мониторинга создаётся по API, номенклатура принимается и нормализуется в канонические товары, есть регулятор бюджетов и учёт расходов, есть рабочий **проверщик листинга** (загрузка → цена → идентичность → наблюдение), проверенный на эталонных страницах liftpages. Поиск листингов (Яндекс, поиск по сайту) — Ф1.

---

## 1. Состав работ (порядок реализации)

| # | Блок | Результат |
|---|---|---|
| 1 | Схема БД + модели | миграции §2, Eloquent-модели в `App\Models\Monitoring\*` |
| 2 | Конфиг и флаги | `config/monitoring.php` §8 |
| 3 | Нормализация | `ArticleNormalizer` (кириллица↔латиница), `UnitNormalizer`, `PriceNumberParser` §3 |
| 4 | Канонические товары | `ProductIdentityResolver` §4 |
| 5 | Приём номенклатуры | API + CSV/Excel, асинхронный импорт §5 |
| 6 | Регулятор и учёт расходов | `MonitoringGovernor`, `mp_budget_ledger`, учёт токенов LLM §6 |
| 7 | Загрузка страниц | `PageFetcher` (порт CE + доработки) + headless-фолбэк §7.1 |
| 8 | Извлечение цены | `StructuredPriceExtractor` (порт CE + правки), `LlmPriceExtractor` (новый промпт) §7.2 |
| 9 | Идентичность на странице | `ListingIdentityVerifier` (правила CE + LazyLift) §7.3 |
| 10 | Проверщик листинга | `ListingChecker` (каркас `PriceMonitor` CE + правки §8 дизайна) §7.4 |
| 11 | Яндекс отложенный режим | `YandexSearchClient::searchDeferred()` §9 (используется в Ф1, но клиент — в Ф0) |
| 12 | Тарифные поля | миграция `tariff_plans` (§11a дизайна), проверка потолка при приёме |
| 13 | Тесты + стенд | `phpunit.xml`, `tests/Unit/Monitoring/*`, команда сверки с CE §10 |

Код — в `app/Services/Monitoring/*`, команды — `app/Console/Commands/Monitoring/*`, джобы — `app/Jobs/Monitoring/*`. Очереди: `monitor` (проверка листингов), `monitor-import` (приём), headless — отдельный пул (§7.1).

## 2. Схема БД (основная БД `iqot`, MySQL 8, InnoDB, utf8mb4)

Все таблицы — в default-коннекте. Связи с `reports` (`suppliers`, `product_types`, `application_domains`, `request_items`) — логические, без FK, типы **строго** под реальную схему: `reports.suppliers.id` — `int unsigned`, `product_types.id`/`application_domains.id` — `int` signed. Миграции идемпотентны (`hasTable`/`hasColumn`).

### 2.1 `monitoring_programs`
| колонка | тип | примечание |
|---|---|---|
| id | bigint unsigned PK | |
| external_id | char(26) unique | ULID для API |
| api_client_id | bigint unsigned null, idx | FK `api_clients` |
| user_id | bigint unsigned, idx | FK `users` |
| name | varchar(255) | |
| price_mode | enum('lite','hybrid') default 'lite' | Ф0 работает только lite |
| target_freshness_days | smallint unsigned default 30 | целевая свежесть |
| site_refresh_days | smallint unsigned default 14 | |
| email_refresh_days | smallint unsigned default 60 | для гибрида (Ф3) |
| discovery_rate | int unsigned default 500 | позиций/сутки на первичный поиск (Ф1) |
| status | enum('draft','active','paused','archived') default 'draft' | |
| paused_reason | varchar(64) null | `balance`, `manual`, … |
| items_total / items_active / items_matched / items_covered | int unsigned default 0 | денормализованные счётчики (пересчёт джобом) |
| webhook_url | varchar(500) null | Ф4 |
| settings | json null | расширения без миграций |
| timestamps, soft deletes | | |

### 2.2 `program_items`
| колонка | тип | примечание |
|---|---|---|
| id | bigint unsigned PK | |
| program_id | bigint unsigned, FK cascade | |
| client_ref | varchar(191) | **unique (program_id, client_ref)** |
| raw_name | varchar(1000) | как прислал клиент |
| raw_brand / raw_article | varchar(255) null | |
| raw_unit | varchar(32) null | |
| raw_attributes | json null | |
| product_id | bigint unsigned null, idx | FK `mp_products` |
| match_status | enum('pending','matched','ambiguous','rejected') default 'pending' | |
| match_note | varchar(255) null | почему ambiguous/rejected |
| active | bool default 1 | клиент может выключить позицию |
| priority | tinyint default 0 | |
| last_priced_at | datetime null | свежесть для покрытия/биллинга |
| price_source | enum('site','kp','none') default 'none' | |
| next_due_at | datetime null, idx | |
| timestamps | | |
Индексы: `(program_id, active, match_status)`, `(product_id)`.

### 2.3 `mp_products` (канонический товар, общий для всех клиентов)
| колонка | тип | примечание |
|---|---|---|
| id | bigint unsigned PK | |
| name | varchar(1000) | лучшее известное наименование |
| brand | varchar(255) null | отображаемое |
| brand_norm | varchar(191) null, idx | нормализованный бренд (§3.1) |
| unit_kind | enum('piece','meter','kg','liter','set','pack','unknown') default 'unknown' | |
| product_type_id | int null, idx | таксономия IQOT |
| domain_id | int null | |
| classification_source | varchar(32) null | |
| search_state | enum('new','searching','found','not_found','exhausted') default 'new', idx | Ф1 |
| last_search_at / next_search_at | datetime null | Ф1 |
| listings_active / listings_priced | smallint unsigned default 0 | |
| timestamps | | |

### 2.4 `mp_identifiers`
| колонка | тип | примечание |
|---|---|---|
| id | bigint unsigned PK | |
| product_id | bigint unsigned, FK cascade | |
| kind | enum('oem','mpn','ean','alias') | |
| value | varchar(255) | как есть |
| value_norm | varchar(191) | §3.1 |
| brand_norm | varchar(191) default '' | '' = бренд неизвестен |
| source | varchar(32) | `client:{program_id}`, `ce`, `page`, `manual` |
| timestamps | | |
**unique (kind, value_norm, brand_norm)** — урок CE: один артикул у разных брендов. Индекс `(value_norm)` для поиска без бренда.

### 2.5 `mp_sites`
| колонка | тип | примечание |
|---|---|---|
| id | bigint unsigned PK | |
| domain | varchar(191) **unique** | registrable domain |
| supplier_id | int unsigned null, idx | `reports.suppliers.id` |
| kind | enum('supplier','manufacturer','marketplace','aggregator','unknown') default 'unknown' | маркетплейсы/агрегаторы — в стоп-листе |
| publishes_prices / publishes_stock / has_structured_markup | bool null | профиль (заполняется наблюдениями) |
| search_url_template | varchar(500) null | Ф2 |
| sitemap_url | varchar(500) null | Ф2 |
| fetch_mode | enum('http','headless') default 'http' | |
| robots_cached_at | datetime null | |
| blocked_until | datetime null | капча/403/429 |
| rate_limit_ms | int unsigned default 1000 | пауза между запросами к домену |
| trust_level | tinyint unsigned default 0 | Ф3 |
| trust_scope | enum('all','in_stock','availability','none') default 'none' | Ф3 |
| sample_every | smallint unsigned default 1 | Ф3 |
| verified_ok / verified_mismatch / consecutive_ok | int unsigned default 0 | Ф3 |
| last_verified_at / next_verification_due_at | datetime null | Ф3 |
| stats_30d | json null | ok/fail/soft404/suspicious/structured_share |
| timestamps | | |
Поля доверия заводим сразу (без логики) — чтобы не мигрировать горячую таблицу в Ф3.

### 2.6 `mp_listings`
| колонка | тип | примечание |
|---|---|---|
| id | bigint unsigned PK | |
| product_id | bigint unsigned, FK cascade | |
| site_id | bigint unsigned, FK | |
| url | varchar(2000) | канонизированный |
| url_hash | char(40) | sha1(canonical); **unique (product_id, url_hash)** |
| match_confidence | decimal(3,2) null | |
| match_method | enum('oem_exact','oem_brand','structured_sku','llm_verified','manual') null | |
| match_evidence | varchar(400) null | цитата, по которой опознан товар |
| price | decimal(14,2) null | null = сейчас цены нет |
| currency | char(3) null | RUB/USD/EUR/CNY; RUR→RUB |
| price_kind | enum('retail','from','wholesale_tier','on_request') null | |
| vat_included | bool null | null = неизвестно |
| price_unit | enum('piece','meter','kg','liter','set','pack','unknown') null | за что цена |
| price_unit_qty | decimal(12,3) null | «за отрезок 190 м» → meter, 190 |
| min_order_qty | decimal(12,3) null | |
| availability | enum('in_stock','on_order','out_of_stock','unknown') null | |
| stock_qty | decimal(12,3) null | |
| lead_days | smallint unsigned null | |
| price_evidence | varchar(400) null | цитата с ценой |
| status | enum('active','suspicious','gone','failed','mismatch','blocked') default 'active', idx | |
| fail_count | smallint unsigned default 0 | подряд |
| observed_at | datetime null | когда цена последний раз подтверждена страницей |
| checked_at / next_check_at | datetime null, idx(next_check_at) | |
| final_url_hash | char(40) null | для детекта мягкого 404 |
| content_hash | char(40) null | хеш **блока товара** |
| etag / last_modified | varchar(255) null | условный GET |
| confirmed_price / confirmed_at | decimal(14,2) / datetime null | Ф3 |
| timestamps | | |
Индексы: `(site_id, status)`, `(product_id, status)`, `(status, next_check_at)`.

### 2.7 `mp_price_observations`
| колонка | тип | примечание |
|---|---|---|
| id | bigint unsigned PK | |
| listing_id | bigint unsigned, FK cascade | |
| product_id | bigint unsigned | денормализация для отчётов |
| price / currency / price_kind / vat_included / price_unit / availability | как в listings | |
| method | enum('structured','llm','unchanged','not_modified','kp','manual') | |
| status | enum('ok','suspicious','gone','failed','soft404','blocked','mismatch','no_price') | **сбои пишем** |
| note | varchar(255) null | |
| content_hash | char(40) null | |
| cost_usd | decimal(10,6) default 0 | LLM на это наблюдение |
| observed_at | datetime, idx(listing_id, observed_at) | |
Объём: ≈2–3 млн строк/год на 30 тыс. позиций → партиционирование не нужно в Ф0; ретеншн сырых `failed`/`unchanged` старше 180 дней — команда `monitoring:prune` (Ф1).

### 2.8 `mp_imports`
`id`, `program_id`, `external_id` (ULID), `source` enum('api','file'), `status` enum('queued','processing','done','failed'), `rows_total`, `rows_ok`, `rows_failed`, `errors` json (первые 100), `payload_path` (файл в `storage/app/monitoring/imports/` — для CSV/Excel и для больших API-пачек), `timestamps`.

### 2.9 `mp_budget_ledger`
`id`, `day` date, `program_id` bigint null (null = общий, без программы), `resource` enum('yandex_query','fetch_http','fetch_headless','llm_call','email'), `units` int unsigned, `cost_usd` decimal(12,6), `cost_rub` decimal(12,2) null. **unique (day, program_id, resource)** — инкремент `INSERT … ON DUPLICATE KEY UPDATE units=units+?, cost_usd=cost_usd+?` (атомарно).

### 2.10 `mp_domain_slots` (атомарный лимит на домен)
`domain` varchar(191) PK, `next_allowed_at` datetime(3). Резерв слота — CAS-UPDATE, как `reserveSlot()` у ящиков: `UPDATE … SET next_allowed_at = GREATEST(next_allowed_at, NOW(3)) + INTERVAL ? MICROSECOND WHERE domain=? AND next_allowed_at <= NOW(3) + INTERVAL ? SECOND` (см. §7.1). Заменяет `usleep`+Cache CE.

### 2.11 `mp_verifications`, `mp_asks`
Заводятся в Ф3 (не в Ф0) — их форма зависит от интеграции с планом дня.

### 2.12 `tariff_plans` (новые поля, §11a дизайна)
`monitoring_items_included` int unsigned default 0, `monitoring_items_max` int unsigned default 0 (0 = мониторинг недоступен), `monitoring_price_per_item` decimal(8,2) default 0, `monitoring_kp_multiplier` decimal(4,2) default 3.00. Заполнение миграцией: Старт 0 / 1000 / 25; Базовый 100 / 5000 / 22; Расширенный 500 / 15000 / 20; Профессиональный 2000 / 50000 / 17.

## 3. Нормализация (`app/Support/Monitoring/*`, чистые функции, покрыты тестами)

### 3.1 `ArticleNormalizer::normalize(string): string`
1. `mb_strtoupper`, NFKC.
2. **Похожие кириллические → латиница:** А→A, В→B, Е→E, Ё→E, К→K, М→M, Н→H, О→O, Р→P, С→C, Т→T, У→Y, Х→X (только если в токене есть латиница или цифра — чтобы не ломать чисто русские слова в алиасах).
3. Удалить `\s - _ . / \ , : ; ( ) [ ] « » " '`.
4. Пусто или длина < 3 → не артикул.
Плюс `tokens(string $text): array` — разбиение текста страницы на токены-кандидаты (для сравнения по токену, не подстроке, §7.3).
`BrandNormalizer::normalize()`: upper, без юр. форм и кавычек, словарь синонимов (`config/monitoring.php: brand_aliases`, напр. THYSSENKRUPP/THYSSEN/TK ELEVATOR → TKE).

### 3.2 `PriceNumberParser::parse(string|float|null): ?float`
Порт `RawPriceImportService::parseNumber` LazyLift: «13 903,91», «13,903.91», «269,500» (тысячи), `₽`, `руб.`, неразрывные пробелы; отрицательное/ноль → null.

### 3.3 `UnitNormalizer::normalize(?string): string`
шт/штук/ед/pcs → piece; м/м.п./п.м./пог.м/метр → meter; кг → kg; л → liter; компл/к-т/набор → set; уп/упак → pack; иначе unknown.

### 3.4 Внутренние коды клиента — не артикул
При приёме `raw_article`, похожий на внутренний код (`config/monitoring.php: internal_code_patterns` — напр. «КСУ НСИ», «УПП», «МТР», «ЕИСЗ», чисто числовые > 9 цифр без бренда), в `oem` не идёт — пишется в `alias`.

## 4. Канонические товары — `ProductIdentityResolver`

Вход: позиция программы (`raw_*`). Выход: `product_id` + `match_status`.

1. Артикулы позиции: `raw_article` (если не внутренний код) + коды из `raw_name` по шаблону модели (буквы+цифры, длина ≥ 4, не «код серии» — список `config('monitoring.series_tokens')`, урок LazyLift ECO3000) → `value_norm`.
2. Поиск в `mp_identifiers`:
   - `(oem, value_norm, brand_norm)` точно → **matched**;
   - бренд позиции неизвестен, `value_norm` найден ровно у 1 товара → matched;
   - найден у ≥2 товаров разных брендов → **ambiguous** (не гадаем; позиция ждёт бренда или ручного решения);
   - не найден → новый `mp_products` + идентификаторы, matched.
3. Позиция без артикула (только наименование) → новый товар с `alias` = нормализованное имя; `match_note='no_article'` (поиск в Ф1 пойдёт по имени+бренду; качество ниже — отдельная метрика).
4. Классификация `product_type`/`domain` — `ClientCategoryClassifierService::classify()` (тот же, что для API-заявок) при создании нового товара; асинхронно, не блокирует импорт.
5. Слияние дублей канонических товаров (два товара оказались одним) — `mp_products.merged_into_id` + перенос листингов; в Ф0 только ручная команда `monitoring:merge-products`.

Эмбеддинги в Ф0 **не вводим** (MySQL, без pgvector; LazyLift показал, что эмбеддинг не различает размеры).

## 5. Приём номенклатуры

### 5.1 API (группа `v1`, middleware `api.auth`, `api.throttle`)
| метод | путь | тело / ответ |
|---|---|---|
| POST | `/v1/monitoring/programs` | `{name, price_mode?, target_freshness_days?, site_refresh_days?, settings?}` → 201 `{id, status:'draft'}`; проверка: план клиента с `monitoring_items_max > 0`, иначе 403 `monitoring_not_available` |
| GET | `/v1/monitoring/programs/{id}` | статус, счётчики (`items_total/active/matched/ambiguous/covered`), расход за 30 дней |
| PATCH | `/v1/monitoring/programs/{id}` | `status: active|paused`, параметры свежести |
| POST | `/v1/monitoring/programs/{id}/items` | `{items:[{client_ref, name, brand?, article?, unit?, attributes?, active?}]}` до **5 000** → 202 `{import_id}`; `Idempotency-Key` поддерживается (как у submissions) |
| GET | `/v1/monitoring/programs/{id}/imports/{import_id}` | прогресс, ошибки по строкам |
| GET | `/v1/monitoring/programs/{id}/items?cursor=&match_status=` | позиции и статус сопоставления (цены — Ф1) |

Валидация строки: `client_ref` 1..191, `name` 2..1000; дубли `client_ref` внутри пачки — последняя побеждает. Upsert: новая строка → создать; существующая → обновить `raw_*` и, если изменился артикул/бренд, сбросить `product_id`/`match_status=pending`.
**Потолок плана:** `items_active` после импорта > `monitoring_items_max` → лишние строки получают ошибку `plan_limit_exceeded` (не вся пачка).

### 5.2 Файл (кабинет/админка)
CSV/XLSX: колонки `client_ref`, `name`, `brand`, `article`, `unit` (заголовки — по имени, порядок произвольный; `PhpSpreadsheet` уже в зависимостях). Тот же конвейер, `source='file'`.

### 5.3 Конвейер импорта
`ImportProgramItemsJob` (очередь `monitor-import`, timeout 600, tries 1): чанки по 500 → upsert `program_items` → `ProductIdentityResolver` на изменённые → счётчики программы. Классификация новых товаров — отдельный `ClassifyMonitoringProductsJob` пачками по 50 (AI, учёт в ledger).

### 5.4 Пилотная загрузка
`monitoring:import-ce-pilot {program} {--dry-run}` — тянет ~1050 эталонов из Content Engine через **его API** (`GET /api/v1/products/{id}` или выгрузка `POST /api/v1/market/lookup` по LW-кодам; API-ключ CE в `.env` IQOT) и прогоняет через **тот же** конвейер §5.3 (`client_ref` = LW-код, `article` = основной OEM, `brand`, `name`). Листинги CE в Ф0/Ф1 **не засеваем** (слепой замер, дизайн §9.1). Альтернатива без ключа CE — CSV-выгрузка из БД CE и загрузка файлом §5.2.

## 6. Регулятор бюджетов и учёт расходов

### 6.1 `MonitoringGovernor`
- `reserve(string $resource, int $units = 1, ?int $programId = null): bool` — проверяет суточную квоту ресурса (общую и программы) и атомарно увеличивает `mp_budget_ledger`. Нет квоты → false, вызывающий откладывает работу на завтра (листинг: `next_check_at` +1 день).
- Квоты в `config/monitoring.php` (§8): `yandex_queries_per_day`, `fetches_per_day`, `headless_per_day`, `llm_usd_per_day`. Доля программы — пропорционально `discovery_rate`/числу позиций (Ф1; в Ф0 одна программа — вся квота).
- `record(resource, units, costUsd, programId)` — факт расхода (после вызова).

### 6.2 Учёт токенов LLM
`OpenAIClassifierClient::jsonCompletion()` сейчас не отдаёт usage. Добавить в клиент `lastUsage(): array{model, prompt_tokens, completion_tokens}` (из ответа прокси `usage` + `model`) — **без изменения сигнатур** существующих вызовов. `LlmCostCalculator::usd(model, in, out)`: цены в `config/monitoring.php: llm_prices`; поиск модели — **сначала точное имя, затем самый длинный префикс** (урок CE: короткий префикс `gpt-4o` перехватывал `gpt-4o-mini` → ×5).

## 7. Проверка листинга

### 7.1 `PageFetcher` (порт CE `App\Services\Web\PageFetcher` + доработки)
- robots.txt (кэш 12 ч, в `Cache`), таймауты 20/10 с, лимит 5 МБ, редиректы ≤5 **с сохранением финального URL**, cp1251→UTF-8, условные заголовки `If-None-Match`/`If-Modified-Since` — **используются** (в CE не использовались).
- **UA браузера** (решение §11): тот же, что `EMAILS_ANALYZE_UA` (`config('services.email_analysis.user_agent')`).
- Лимит на домен — `mp_domain_slots` (атомарно, §2.10) вместо `usleep`: если слот занят, `fetch()` не ждёт, а возвращает `FetchResult::deferred(retryAfterMs)` — джоб делает `release()`, воркер не блокируется.
- `blocked_until` сайта соблюдается; 401/403/429/капча → `blocked_until = now()+7d`, листинг `status=blocked`.
- **Headless-фолбэк:** если HTML < `http_min_chars` текста **или** `mp_sites.fetch_mode='headless'` → `HeadlessPageRenderer::render()` (уже есть в IQOT). Headless — только в очереди `monitor-headless` с 1 воркером (память Chrome); сайт, где headless дал цену, а http — нет, помечается `fetch_mode='headless'`.
- Учёт: `fetch_http`/`fetch_headless` в ledger.

### 7.2 Извлечение цены
**`StructuredPriceExtractor`** (порт CE) с правками:
- одиночный товар в JSON-LD принимается **только если** его `sku/mpn/gtin/name` содержит артикул товара по токену (§3.1); иначе — как будто разметки нет;
- `RUR`→`RUB`; `priceCurrency` пустой → RUB только на `.ru/.рф/.su`;
- AggregateOffer с low≠high → `price_kind='from'` + `price=low` (а не отбрасывать);
- `priceSpecification` с `valueAddedTaxIncluded` → `vat_included`;
- `eligibleQuantity`/`unitCode` → `price_unit`/`price_unit_qty`, если есть.

**`LlmPriceExtractor`** (новый промпт `monitoring-price-extract-v1`, gpt-4o-mini, json, temperature 0):
- вход — **окно текста** вокруг упоминаний артикула/названия (±2 500 символов вокруг каждого вхождения, склейка, ≤ 8 000) — не первые 7 000 символов (урок CE);
- `removeChrome` из CE **не применять** к `<form>`/`product-header` (риск потери блока цены) — чистим только `script/style/nav/footer`;
- выход: `{found, price, currency, price_kind(retail|from|wholesale_tier|on_request), vat_included(true|false|null), price_unit, price_unit_qty, min_order_qty, availability, stock_qty, lead_days, evidence_price, evidence_identity, is_same_product(true|false|null), is_analog(bool)}`;
- правила промпта (объединение `PriceCheckPrompt` CE, правила 5 `research-extract-v2`, LazyLift `EmailReplyParserService`): доставка/МКАД/самовывоз/порог заказа/«при заказе от» — **не цена**; старая зачёркнутая — не цена; «руб/м», «за п.м.» → meter; «за отрезок N м» → meter + qty N; «по запросу/договорная» → on_request, price=null; несколько цен (розница/опт) → розничная, опт → `wholesale_tier`; другой производитель/«аналог/замена» → `is_analog=true`;
- постфильтр `isNonItemPriceEvidence` (порт `FactMerger` CE) по `evidence_price` — сработал → `found=false`.

### 7.3 `ListingIdentityVerifier` — «это наш товар?»
Правила по порядку (первое сработавшее решает):
1. **Разметка/текст содержит артикул товара как отдельный токен** (§3.1, не подстрокой: `ABC123` ≠ `ABC1234`) **и** бренд совпадает или на странице бренд не указан → `oem_exact`/`structured_sku`, confidence 0.95.
2. Бренд на странице явно **другой** (нормализованный, не синоним) → **отказ** (`mismatch`), даже при совпадении артикула (урок LazyLift: бренд только повышал score).
3. LLM сказал `is_analog=true` → отказ (аналог ≠ наш товар; в Ф1 — пометка `analog_of`).
4. Числовые признаки наименования (`ItemNameSignals` LazyLift: «D=240», «10 ручьёв», «6 мм», «левый/правый») расходятся со страницей → отказ.
5. Иначе (серая зона) → `is_same_product` от LLM: `true` → `llm_verified`, confidence 0.8; `false`/`null` → отказ (урок LazyLift: в серой зоне 95% привязок чужие).
В Ф0 верификатор работает на готовых URL (стенд §10); в Ф1 — на кандидатах из поиска.

### 7.4 `ListingChecker` (каркас `PriceMonitor` CE)
Порядок:
1. `governor->reserve('fetch_http')`; нет квоты → `next_check_at += 1d`.
2. Загрузка с `etag/last_modified` → **304** → наблюдение `not_modified`, цена прежняя, `observed_at=now`.
3. 404/410 → `gone`; **мягкий 404**: финальный URL ≠ исходного и (путь стал корнем/категорией, или текст содержит «товар не найден|нет такой страницы|404») → `soft404` → `gone`.
4. Капча/403/429 → `blocked` (сайт `blocked_until`).
5. `content_hash` = sha1 **блока товара** (окно текста §7.2, а не вся страница) == прошлый → `unchanged` (кроме случая, когда прошлое значение пришло из LLM, а сейчас есть разметка — разметка исправляет, урок CE).
6. Разметка → LLM (§7.2) → идентичность (§7.3): не наш товар → листинг `mismatch` (в Ф1: товар — на повторный поиск на сайте).
7. Цены нет (`on_request`/`found=false`) → наблюдение `no_price`, **`listing.price = null`** (старую не держим).
8. Скачок ×3 к текущей цене → `suspicious`, **повторная проверка через 24–48 ч** (`next_check_at`), не через цикл; совпало ±5% → принять.
9. Выброс ×4 от медианы ≥4 активных цен товара → `suspicious` с отметкой в БД.
10. Сбой → наблюдение `failed`, `fail_count++`, повтор 1 ч → 6 ч → 24 ч; после 5 подряд → `failed` (в Ф1 — повторный поиск товара на сайте).
11. Принято → наблюдение `ok`, обновление листинга, `next_check_at = now + site_refresh_days` (± джиттер 10%, чтобы не было пиков по домену).
Джоб `CheckListingJob` (очередь `monitor`, tries=0 + `retryUntil 6h`, release по слоту домена — без роста `attempts`, как у `SendQueuedEmailJob`). Диспетчер `monitoring:dispatch-checks` (каждые 5 мин): листинги `next_check_at <= now`, `status in (active,suspicious,failed)`, не `blocked`, в пределах квоты, round-robin по доменам.

## 8. Конфиг `config/monitoring.php`
```php
'enabled' => env('MONITORING_ENABLED', false),          // диспетчер проверок молчит без флага
'user_agent' => env('MONITORING_UA', /* = EMAILS_ANALYZE_UA */),
'fetch' => ['timeout'=>20, 'connect_timeout'=>10, 'max_bytes'=>5_000_000, 'respect_robots'=>true,
            'default_rate_ms'=>1000, 'http_min_chars'=>300, 'block_days'=>7],
'quotas' => ['yandex_queries_per_day'=>env('MONITORING_YANDEX_PER_DAY',2000),
             'fetches_per_day'=>env('MONITORING_FETCHES_PER_DAY',20000),
             'headless_per_day'=>env('MONITORING_HEADLESS_PER_DAY',1500),
             'llm_usd_per_day'=>env('MONITORING_LLM_USD_PER_DAY',5)],
'llm' => ['model'=>env('MONITORING_LLM_MODEL','gpt-4o-mini'), 'window_chars'=>2500, 'max_chars'=>8000],
'llm_prices' => ['gpt-4o-mini'=>[0.15,0.60], 'gpt-4o'=>[2.50,10.00], 'gpt-5-nano'=>[0.05,0.40]], // $/1M in,out
'sanity' => ['jump_ratio'=>3.0, 'jump_recheck_hours'=>36, 'outlier_ratio'=>4.0, 'outlier_min_prices'=>4],
'retry' => ['backoff_minutes'=>[60,360,1440], 'max_fails'=>5],
'blocked_domains' => [/* маркетплейсы и агрегаторы: ozon, wildberries, market.yandex, avito, tiu, pulscen, satom, blizko, all.biz, … (список CE engine.discovery.blocked_domains + CompanyKind::Marketplace) */],
'internal_code_patterns' => [...], 'series_tokens' => [...], 'brand_aliases' => [...],
```

## 9. Яндекс: отложенный режим (клиент — в Ф0, использование — Ф1)
Сейчас `YandexSearchClient` ходит в синхронный `/v2/web/search` (≈488 ₽ за 1 000 запросов днём). Отложенный режим (`/v2/web/searchAsync` → операция → результат) ≈ **30,5 ₽** за 1 000 (ночью 25,4 ₽) — в 16 раз дешевле. Добавить `searchDeferred(array $queries): array<operationId>` и `fetchDeferred(operationId): ?array` (тот же парсинг XML), учёт `yandex_query` в ledger. Существующий синхронный путь (discovery заявок) **не трогаем**. Точные эндпоинты и квоты — сверить с документацией Yandex Cloud при реализации.

## 10. Тесты и стенд
- Завести `phpunit.xml` + `tests/Unit` (в проекте тестов нет; PHPUnit 11 в зависимостях). Тесты Ф0 — **без БД**, на чистой логике:
  - `ArticleNormalizerTest` (кириллица↔латиница, токены, `ABC123`≠`ABC1234`);
  - `PriceNumberParserTest`, `UnitNormalizerTest`;
  - `StructuredPriceExtractorTest` — перенести кейсы CE + новые (одиночный товар без артикула → null, RUR, AggregateOffer→from, НДС);
  - `NonItemPriceEvidenceTest` — кейсы CE (vent7 «1800 руб в пределах МКАД», «200RUB»);
  - `ListingIdentityVerifierTest` — правила §7.3 на HTML-фикстурах;
  - `LlmCostCalculatorTest` — `gpt-4o-mini-2024-07-18` считается по mini.
- **Стенд сверки с CE** — `monitoring:bench-ce {--limit=200}`: берёт из CE (API) N товаров с известными предложениями, прогоняет `ListingChecker` **на их URL** во временном листинге (флаг `bench`, в выдачу не идёт) и сравнивает: цена (±1%), наличие, идентичность, метод (разметка/LLM/headless), стоимость. Это проверка извлечения, **не** замер поиска (поиск Ф1 — вслепую). Критерии приёмки Ф0 — §11.

## 11. Критерии приёмки Ф0
1. Миграции применяются на проде (`iqot`) и откатываются; в `reports` Ф0 ничего не пишет.
2. API: создать программу, загрузить 1 050 позиций пилота (через `monitoring:import-ce-pilot`), получить прогресс и статусы сопоставления. ≥ 98% позиций `matched`; `ambiguous` — со списком причин.
3. Потолок плана срабатывает построчно (`plan_limit_exceeded`).
4. Стенд `bench-ce` на 200 листингах CE: цена совпала с CE (±1%) **≥ 90%** там, где CE-цена получена разметкой; расхождения разобраны вручную (кто прав). Доля «разметка / LLM / headless / не удалось» — измерена.
5. Ни одного «цена доставки как цена товара» и ни одного «аналог как наш товар» в выборке ручной проверки 50 листингов.
6. Учёт расходов: стоимость стенда в ledger сходится с фактическим расходом прокси (±10%).
7. Флаг `MONITORING_ENABLED=false` — на проде ничего не запускается по расписанию.
8. Unit-тесты §10 зелёные.

## 12. Что вне Ф0 (сознательно)
Поиск листингов (Яндекс/поиск по сайту) — Ф1; выдача цен курсором и вебхуки — Ф1/Ф4; профили сайтов (шаблоны поиска, sitemap) — Ф2; доверие, сверки, письма, `mp_asks`/`mp_verifications` — Ф3; начисления по тарифу — Ф4 (поля плана заводим сейчас); эскалация в RFQ — Ф5; эмбеддинги — по необходимости.

## 13. Риски Ф0
- **Ключ API Content Engine** для пилотной загрузки и стенда: у LazyLift он уже есть (`CONTENT_ENGINE_API_KEY`); для IQOT — выпустить отдельный ключ в CE (или CSV-выгрузка из БД CE как фолбэк).
- **Headless на app-сервере** (7 ГБ, Chrome) — 1 воркер, квота; если не хватит — вынести рендер на отдельную машину (Ф1).
- **robots.txt** может запрещать каталоги у части поставщиков — такие сайты сразу в почтовый канал (Гибрид); долю измерим на стенде.

## 14. Результаты Ф0 (прод, 2026-10-14)
**Пилот:** программа #1, 1052 позиции liftpages → **1051 matched, 1 ambiguous** (красная/синяя кнопка Otis с одним OEM — помечены, не склеены). Критерий ≥98% — выполнен.

**Стенд сверки с CE** (`monitoring:bench-ce`, 200 случайных предложений CE, 2 прогона):

| | прогон 1 | прогон 2 (после правок) |
|---|---|---|
| цена принята (ok) | 142 | 142 |
| отклонено «не тот товар» | 49 | 42 |
| цена из разметки / из LLM | 88 / 58 | 112 / 40 |
| совпадение с CE ±1% (все) | 79,6% | 82,4% |
| совпадение с CE ±1% (CE-цена из разметки) | 93,8% (15/16) | 93,8% (15/16) — **критерий ≥90% выполнен** |
| LLM, $ на 200 страниц | 0,045 | 0,043 (≈ $0,0002 на проверку) |

**Разбор расхождений (ручная выборка):**
- Отклонения «не тот товар» — в основном **ошибки CE**: левая/правая (lift-market `…levaya…` для «ПРАВАЯ», yamagi `step-comb-left` для «правая», apexx `…pravaya…` для «ЛЕВАЯ»), чужой товар (asldiesel — шкив вместо «троса кевларового»), страницы категорий. Аналоги (revator RR-коды, «аналог») отсекаются.
- Расхождения цены — в основном **устаревшие цены CE** (исследование сентября, 1–5%) и **ошибки CE**: yamagi — на странице оригинал Otis 31 153 ₽ и китайский аналог 1 318 ₽, CE взял аналог, IQOT — оригинал из разметки; allbelts — разметка 900 ₽, CE 1 800 ₽.
- Найденные и исправленные ошибки IQOT (коммит после прогона 1): LLM эхом вернула пример «12345» из шаблона JSON (td-scala, категория); LLM взяла цену соседнего товара при верной разметке (nlp-group: разметка 268 ₽ = CE, LLM 600 ₽) → цена разметки приоритетна; s-mechanics.ru ставит в sku каталожный LW-код клиента → ложные отказы → `client_ref` вида `LW-…` стал алиасом товара (s-mechanics: 8 из 10 ok).
- Остаются в работе: цены-заглушки в разметке (siemenspro.ru 999 ₽ за контактор Siemens) — ловится только выбросом от медианы при ≥4 ценах; страницы-категории в CE-листингах (в Ф1 листинги ищем сами — на карточки).

**Не проверено в Ф0:** построчный потолок плана на живом импорте (покрыт логикой, не прогоном); сверка учёта LLM с биллингом прокси (нет доступа к счёту прокси); полная ручная выборка 50 листингов (сделан разбор всех отклонений и расхождений обоих прогонов).
