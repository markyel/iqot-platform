# IQOT Public API — статус реализации

**Спека:** `C:\Users\Boag\Downloads\IQOT_API_Specification.md` (v1.0, 2026-04-22)
**Ориентир:** спека §15.1 «Этапы реализации» (10 этапов)
**Последнее обновление:** 2026-04-22

---

## Готово

### ✅ Этап 1 — Инфраструктура и data model

**Миграции iqot (применены):**
- `2026_04_22_100001_create_api_clients_table`
- `2026_04_22_100002_create_api_keys_table`
- `2026_04_22_100003_create_client_categories_table`
- `2026_04_22_100004_create_client_category_candidates_table`
- `2026_04_22_100005_create_user_senders_table`
- `2026_04_22_100006_migrate_users_sender_to_user_senders` (data-migration)
- `2026_04_22_100007_create_api_submissions_table`
- `2026_04_22_100008_create_api_inbox_table`
- `2026_04_22_100009_create_request_staging_tables` (staging + items)
- `2026_04_22_100010_extend_balance_holds_for_api` (+`request_item_id`, `api_submission_id`, `request_items_staging_id`)
- `2026_04_22_100011_add_api_access_feature_to_tariff_plans` (data-migration для features JSON)

**Миграции reports (применены на прод Beget):**
- `2026_04_22_100012_create_supplier_discovery_runs_table_on_reports`
- `2026_04_22_100013_extend_reports_requests_and_product_types`
  - `requests`: +`source ENUM(web,admin,api)`, +`api_submission_external_id CHAR(26)`, +`idx_source`, +`idx_api_submission`
  - `product_types`: +`min_suppliers_threshold SMALLINT DEFAULT 8`
  - `domain_product_types`: +`min_suppliers_threshold SMALLINT NULL`

**Модели `App\Models\Api\*`:**
- `ApiClient`, `ApiKey` (UPDATED_AT=null)
- `ClientCategory` (timestamps=false), `ClientCategoryCandidate`
- `ApiSubmission`, `ApiInbox` (`$table='api_inbox'`)
- `RequestStaging`, `RequestItemStaging`
- `UserSender`
- `SupplierDiscoveryRun` (`$connection='reports'`, UPDATED_AT=null)

**Типы логических cross-DB ссылок выровнены под схему reports:**
- `product_type_id`, `domain_id` → `integer()` (signed, как `int(11)` в reports)
- `request_item_id`, `promoted_request_item_id`, `internal_request_id`, `client_organization_id` → `unsignedInteger()` (как `int(10) unsigned`)
- `external_sender_id` в `user_senders` → `unsignedInteger()` (как `users.sender_id int unsigned`)

### ✅ Этап 10 — Документация

**Готово:**
- `docs/api/openapi.yaml` — полная OpenAPI 3.1 спецификация: 9 endpoints, все схемы, error-codes, security, параметры, примеры.
- `docs/api/README.md` — публичное руководство разработчика: быстрый старт (4 команды curl), идемпотентность, rate-limits, биллинг, статусы, polling, cancel, ошибки.
- `docs/api/test_stage3.sh` — e2e тест-скрипт (создан на Этапе 3, до сих пор актуален для core-сценариев).

**Отложено (требует настройки окружения):**
- Pest feature-тесты — проект не имеет директории `tests/`, не настроено тестовое соединение (`DB_CONNECTION_TESTING`), нет моков для AI-прокси. Для запуска тестов нужно:
  1. Создать `tests/` с `Pest.php` bootstrap.
  2. Настроить `phpunit.xml` / `testing` БД (sqlite in-memory или отдельная MySQL).
  3. Mocks для `OpenAIClassifierClient` (через `Http::fake()`).
  4. Фабрики для `User, ApiClient, ApiKey, UserSender, TariffPlan+api_access`.
  После этого написать базовый набор feature-тестов по e2e-сценариям из `test_stage3.sh`.
- Нагрузочный тест `InboxProcessingWorker` (500 позиций за волну).
- Sunset/Deprecation middleware — когда понадобится первый breaking-change под /v2/.

---

### ✅ Этап 9 — Cancel + Rate-limit + Balance-Warning + Cleanup

**Cancel (§11.5):**
- `SubmissionCancelService::cancel(submission, reason)` — транзакционно освобождает holds, удаляет staging/inbox, дополняет `rejected_summary` записями `cancelled_by_client`. Если submission уже promoted — ставит reports.request `status=cancelled`.
- Safety net: все `held` holds submission (не привязанные к reports.request_items) принудительно `released`.
- Идемпотентен для финальных статусов (`completed|cancelled`) → возвращает `already_final`.
- `POST /api/v1/submissions/{id}/cancel` — `SubmissionCancelController` (200 с `status, cancelled_at, was_promoted`).

**Rate-limit (§12.2):** `ApiRateLimit` middleware (`api.throttle` alias) с bucket/limit:
- `api.throttle:total,60/min` на всю v1-группу.
- `api.throttle:post_submissions,10/min` на POST /submissions.
- `api.throttle:get_submission,1/15s` на GET /submissions/{id} (ключ = `key_id + submission id`).
- Cache-based token bucket (file driver ок для MVP, на проде redis).
- Headers: `X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset`. При превышении — 429 + `Retry-After`.

**X-Balance-Warning (§10.5):** `ApiBalanceWarning` middleware на v1-группе — при `balance < 0` ставит `X-Balance-Warning: overdraft_<sum>_rub`.

**CleanupRevokedApiKeysJob (§9.5):** daily scheduled, удаляет `api_keys WHERE revoked_at < NOW() - 30 days`.

**E2E:**
- Ping → 200 + `X-RateLimit-Limit: 60, Remaining: 59`.
- Повторный GET /submissions/{id} < 15s → 429 + `Retry-After: 15`.
- Cancel submission в inbox-стадии → 200, holds released, inbox запись удалена, `rejected_summary` заполнен из raw_payload.
- Cancel уже cancelled submission → 200 `already_final`.

---

### ✅ Этап 8 — Read-API + Taxonomy

**`SubmissionReadService`:**
- `toStatusArray(submission)` — полный ответ §11.3: counts (total/accepted/rejected/awaiting_suppliers/dispatched/with_offers_minimum/completed), items с классификацией и offers_count, rejected_items из `rejected_summary`.
- `itemsArray(submission)` — только items для §11.4.
- `reportArray(submission)` — отчёт §11.9; возвращает null (→ 409 в контроллере) если ни одна позиция не достигла `MINIMUM_OFFERS=3`.
- **Cross-DB агрегация**: до промоушена items читаются из staging; после (staging удалён cleanup-джобом) — из `reports.request_items`. Offers агрегируются через `ExternalOffer` (`reports.request_item_responses`) по статусам `received|processed`.
- Mapping внутренних статусов в публичные §11.7 (`pending|accepted|awaiting_suppliers|dispatched|collecting|ready_minimum|completed|rejected`).
- `nextCheckAfter(submission)` — рекомендации polling'а §12.3.

**Контроллеры:**
- `SubmissionReadController@show|items|report` — 200/404/409, проверка принадлежности submission api_client.
- `TaxonomyController@domains|productTypes` — публичный вывод (id/slug/name/parent_id), фильтр productTypes по `domain_id` через `domain_product_types`.

**Polling headers (§11.1):** `X-Request-Id`, `X-Status-Changed-At`, `X-Next-Check-After`, `X-Submission-Stage`.

**Роуты:** `GET /api/v1/submissions/{id}`, `/items`, `/report`, `/taxonomy/domains`, `/taxonomy/product-types`.

**E2E:**
- `/taxonomy/domains` → 200, выдача `Лифты/Эскалаторы/...`.
- `/taxonomy/product-types?domain_id=1` → 200, leaf-типы для домена 1 (`pcb, vfd, controllers, ...`).
- `/submissions/sub_01KPWHG...` (promoted, без offers) → 200 с полной структурой, `status=ready, stage=dispatching, counts.dispatched=1`, headers установлены корректно.
- `/submissions/sub_BOGUS` → 404 `submission_not_found`.
- `/submissions/.../report` без offers → 409 `report_not_ready`.

---

### ✅ Этап 7 — Cross-DB промоушен

**`PromotionService` (§6.4):**
- `promoteIfReady(submission)` — валидация готовности (все accepted в `pool_ready`, нет `internal_request_id`).
- `promote(submission, items)`:
  - **Шаг 1** (reports tx): INSERT `ExternalRequest` (`source='api', api_submission_external_id, request_number='API-YYYYMMDD-XXX'`) + INSERT `ExternalRequestItem`-ов с классификацией; mapping `staging_id → reports_id`.
  - **Шаг 2** (iqot tx): UPDATE `api_submissions.internal_request_id, promoted_at, stage=dispatching`; staging items `status=promoted + promoted_request_item_id`; `balance_holds.request_item_id` на реальный reports id.
  - **Шаг 3**: `CleanupPromotedStagingJob::dispatch()->delay(5 min)`.
- `reconcile(external_id, reports_request_id)` — Сценарий B: повтор Шага 2 для осиротевших reports.requests; Сценарий C: отмена reports.request если submission `cancelled`.

**`CleanupPromotedStagingJob`:** удаляет promoted staging items и пустой request_staging (`stage=finalised`); holds сохраняют `request_item_id` для биллинга.

**`ReconcilePromotionJob` (§6.5):** scheduler every 5 min; ищет `reports.requests.source='api'` старше 2 минут без связи; вызывает `PromotionService::reconcile()`.

**Триггеры:**
- `SupplierPoolService::applyToSubmission` — если все позиции `pool_ready` → `promoteIfReady()`.
- `RecheckAwaitingSuppliersJob::processItem` — при последнем `awaiting_suppliers → pool_ready` → `promoteIfReady()`.

**Побочные правки:**
- `ExternalRequest::$fillable` расширен: `source`, `api_submission_external_id`.

**E2E (3 сценария):**
- **Happy path**: sub #6 → reports.requests #234 (`API-20260423-97F085, source=api, status=active`) + request_items #731; staging_item → `promoted`; hold.request_item_id=731.
- **Cleanup**: promoted staging item удалён; пустой request_staging удалён; hold с request_item_id=731 сохранился.
- **Reconcile**: orphan reports.request #235 (created 10 min ago) → reconciler связал sub #5 (internal_request_id=235, promoted_at=…), staging_item #2 → `promoted, reports_id=732`.

---

### ✅ Этап 6 — Supplier Discovery + пул

**`SupplierCoverageService` (§6.1):**
- `checkCoverage(?domainId, productTypeId)` → `['available', 'threshold', 'is_sufficient']`.
- SQL на reports: `is_active=1 AND notify_email=1 AND profile_confidence>=0.3`, match через pivot `supplier_domains / supplier_product_types` с `is_included=1` либо `scope_*='all'`.
- `last_response_at` в reports отсутствует — фильтр опущен.
- Threshold: `COALESCE(dpt.min_suppliers_threshold, pt.min_suppliers_threshold, 8)`.

**`SupplierPoolService` (§6.2):**
- `applyToSubmission(submission)` — для каждой accepted позиции: sufficient → `pool_ready`; недобор → `awaiting_suppliers` + `ensureDiscoveryRun`.
- `ensureDiscoveryRun(domain, pt, external_id)` — создаёт queued run только если нет active на паре И cooldown истёк.
- Cooldown: success 7 дней, exhausted 30, failed 1.

**Jobs:**
- `DiscoveryOrchestratorJob` — scheduler every 10 min; parallelism=1; берёт FIFO queued → диспатчит `DiscoverSuppliersForPairJob`.
- `DiscoverSuppliersForPairJob` — **MVP stub**: queued → running → (success_covered | exhausted) по результату текущего coverage. Реальный web-scrape/AI отложен, интерфейс совместим с будущей полноценной реализацией.
- `RecheckAwaitingSuppliersJob` — hourly; coverage стал достаточным → `pool_ready`; прошло >14 дней + всё exhausted → reject `no_suppliers_available` (hold release + запись в rejected_summary).

**Интеграция:** `ModerationService::maybeFinalize` после `status=ready` вызывает `SupplierPoolService::applyToSubmission` (вне транзакции).

**Scheduler:** `everyTenMinutes()` для Orchestrator, `hourly()` для Recheck.

**E2E прошли:**
- Coverage по реальным pt=1101/1002 + domain=1/NULL возвращает корректные цифры (194/339/179 suppliers, threshold=8, sufficient=true).
- `approveItem` green → submission → `pool_ready` (coverage sufficient).
- `ensureDiscoveryRun` при искусственном threshold=50000 — создаёт queued run в reports, блокирует дубли, cooldown активируется после finished.
- Stub `DiscoverSuppliersForPairJob` — queued → exhausted с `finished_at`, переход корректный.

---

### ✅ Этап 5 — Модерация

**`ModerationService`:**
- `approveItem(item)` → classified → accepted + feedback (hit_count++ кандидата, при hit≥10 → source=learned).
- `approveGreenBatch(submission)` → массово approveItem для trust=green.
- `rejectItem(item, reasonCode, message)` — справочник §11.8 жёстко валидируется.
- `reclassifyItem(item, productTypeId, domainId, ?clientCategoryId)` — меняет классификацию; feedback §4.4 (hit_count-- старому, ++ новому либо создание нового manual candidate).
- `maybeFinalize(submission)` — когда все items ∈ {accepted, rejected}: собирает `rejected_summary` (JSON `{client_ref, name, reason, message, retryable}`), размораживает holds отклонённых, удаляет rejected staging_items, переводит submission в `status=ready`.

**`Manage\ApiSubmissionController`:**
- `GET /manage/api-submissions` — список с бейджами green/yellow/red/accepted/rejected.
- `GET /manage/api-submissions/{submission}` — детали, actions (approve/reject/reclassify).
- `POST …/approve-batch` — массовое одобрение green.
- `POST …/items/{item}/approve|reject|reclassify`.

**Views:** `admin.api-submissions.{index,show}` в `layouts.cabinet` с inline details-форумами для reject/reclassify.

**E2E прошли:**
- Approve green → submission `status=ready, items_accepted=1`, candidate `hit_count` вырос с 2→3 (feedback loop).
- Reject red (`out_of_scope`) → `rejected_summary` с корректной структурой, `hold released`, staging_item удалён.
- Третий submission не тронут — финализация не сработала пока есть classified позиции (что правильно).

---

### ✅ Этап 4 — Пайплайн классификации

**Интеграция AI:**
- OpenAI-совместимый прокси `ai.lazylift.ru/v1` (headers `Authorization: Bearer` + `X-Proxy-Key`).
- Модели: `gpt-4o-mini` (mini-classifier), `gpt-4o` (full AI). Конфиг в `config/services.php` → `openai_classifier`, .env: `OPENAI_CLASSIFIER_*`.

**`OpenAIClassifierClient`:**
- Фабрика `fromConfig()` (singleton в `AppServiceProvider::register`).
- `jsonCompletion(model, systemPrompt, userPrompt)` с `response_format: json_object`, temperature=0.

**`ClientCategoryClassifierService` (§4.1):**
- **п.1** manual × 1 → `classification_source=manual_mapping`, trust=green, без AI.
- **п.2/п.3** >1 manual или manual+learned (hit_count≥10) → `mini_classifier` (AI с pool кандидатов).
- **п.4/п.5** нет кандидатов / нет client_category / AI упал → `full_ai` fallback: product_type=NULL, trust=red, needs_review=1.
- Trust: manual → green; ai_suggested/learned ≥20 hits → green, иначе yellow.

**`InboxProcessingWorker` (§8):**
- `App\Jobs\Api\InboxProcessingWorker` (ShouldQueue, `onConnection('database')` в конструкторе).
- Волна: watchdog (просроченный lock → pending+retry++) → retry≥3 → failed → batch 500 lockForUpdate → processing → классификация → INSERT staging → привязка hold к staging_item → stage=awaiting_moderation → DELETE api_inbox.
- Self-reschedule при остатке pending.

**Запуск:** `php artisan api:inbox:process [--queue]`. Scheduler: `everyFiveMinutes()->withoutOverlapping()`.

**E2E прошли:**
- Fallback (нет candidate) → `trust=red, needs_review=1, product_type=NULL`.
- Manual single → `trust=green, source=manual_mapping, product_type=1101, domain=1` (без AI).
- Mini-classifier (2 candidates: Подшипники vs Реле) → GPT-4o-mini выбрал Подшипники, `confidence=0.90, trust=green`.

**Побочные фиксы:**
- `BalanceHold::$fillable` расширен: `request_item_id, api_submission_id, request_items_staging_id`.
- Job: нельзя `public string $connection` — конфликт типа с `Queueable::$connection` (`?string`). Решение — `$this->onConnection('database')` в конструкторе.
- Контейнер не резолвит string-only конструкторы — singleton через `fromConfig()` в `AppServiceProvider`.

---

### ✅ Этап 3 — Endpoint приёма

**Сервис (`app/Services/Api/SubmissionService.php`):**
- `create(ApiClient, payload, ?idempotencyKey)` — атомарно создаёт `api_submission` + `api_inbox` + попозиционные `balance_holds` + upsert `client_categories`.
- Идемпотентность: `hashPayload()` = SHA-256 нормализованного JSON; сравнение с payload из `api_inbox.raw_payload`.
- Sender selection: `client_organization_id` → default → единственный активный.
- Per-position pricing: в рамках `tariffPlan.items_limit` → 0 ₽, сверх → `price_per_item_over_limit`. Hold создаётся только для >0.
- Overdraft: `balance + (required_hold * overdraft_percent/100) >= required_hold`.
- ULID (26 chars) для `external_id`, публичный префикс `sub_` в response.
- Server-generated `idempotency_key` (`srv_<uuid>`) если клиент не прислал.

**Валидация (`CreateSubmissionRequest`):**
- items 1–300, `quantity > 0`, name 1–500, description ≤5000, `client_category.path` depth ≤10.
- deadline 7–60 дней; default +30 дней если не прислан.
- **Переопределён `validationData()`** — `$this->json()->all()` для корректной работы с `application/json` (в Laravel 11 FormRequest может терять JSON body).
- Ошибки в формате спеки §14.1 с `code: invalid_payload`, `details`, `request_id`.

**Контроллеры:**
- `SubmissionController@store` → `POST /api/v1/submissions` (202/200 replay/400/402/409).
- `AccountController@balance` → `GET /api/v1/account/balance`.

**E2E прошли (8 сценариев):**
- ping 200, balance 200, happy path 202, replay 200, conflict 409, валидация items/deadline 400, balance отражает holds.

**Тест-скрипт:** `docs/api/test_stage3.sh <bearer>`. JSON передаётся через файлы + `--data-binary` (Git Bash портит UTF-8 в `-d "$VAR"`).

---

### ✅ Этап 2 — Аутентификация и ЛК

**Сервисы (`app/Services/Api/`):**
- `UserAccessService::hasApiAccess(int $userId): bool` — читает `tariff_plans.features.api_access`
- `ApiKeyService`:
  - `generate(ApiClient, name, ?ipWhitelist)` → `['plain_key','record']`, лимит 3 активных ключа
  - `lookup(string $plainKey): ?ApiKey` — по SHA-256
  - `isRevokedWithinGrace(ApiKey): bool` (30 дней)
  - `revoke(ApiKey)`, `touchUsage(ApiKey, ip)`
  - `ipMatchesWhitelist(?array, string): bool` — IPv4 + CIDR

**Middleware:**
- `App\Http\Middleware\AuthenticateApiKey` — алиас `api.auth` (в `bootstrap/app.php`)
- Цепочка: Bearer → lookup → revoke grace → IP whitelist → client.is_active → user has api_access → touchUsage → inject `api_client` / `api_key` в `$request->attributes`
- Ошибки: 401 `invalid_api_key`/`key_revoked`/`ip_not_whitelisted`, 403 `api_client_disabled`/`api_access_denied`
- На каждый запрос — уникальный `X-Request-Id`

**Контроллеры и роуты:**
- `Api\V1\PingController` → `GET /api/v1/ping` (проверка ключа; middleware `api.auth`)
- `Cabinet\ApiKeyController` → `/cabinet/api-keys` index/store/destroy
- `Cabinet\SenderController` → `/cabinet/senders` index/store/update/default/destroy

**Views:**
- `resources/views/cabinet/api_keys/index.blade.php`
- `resources/views/cabinet/senders/index.blade.php`

**Прошедшие E2E тесты:**
- `curl -k -H "Authorization: Bearer iqot_live_…" https://iqot-platform.test/api/v1/ping` → `200 {"ok":true,"api_client_id":2,"user_id":4,…}`
- Без header → 401 `invalid_api_key`
- Невалидный Bearer → 401 `invalid_api_key`
- UI-страницы отвечают 200 (после login)

---

## Следующие этапы

### ~~🔜 Этап 3 — Endpoint приёма~~ ✅ Готово (см. выше)

**Цель:** клиент может создать submission и увидеть свой баланс.

**Шаги:**
1. `POST /api/v1/submissions` — контроллер `Api\V1\SubmissionController@store`
   - Validation через FormRequest: items 1–300, payload ≤ 10MB, quantity>0, deadline 7–60 дней, client_category структура
   - Idempotency-Key: если прислан → проверить конфликт с уникальным индексом `(api_client_id, idempotency_key)`; если нет → сервер генерирует UUID
   - Выбор sender'а (§9.3): по `client_organization_id` → `user_senders`. Не нашёл → 400 `sender_not_configured`
   - Расчёт `required_hold = sum(TariffService::priceForPosition(user_id, position_index))`
   - Проверка баланса с учётом overdraft: `user.balance + (hold * overdraft_percent/100) >= required_hold` → иначе 402 `insufficient_balance`
   - Генерация `external_id` (ULID-like, например `sub_01HXYZ…`)
   - Транзакция в iqot:
     - INSERT `api_submissions`
     - INSERT `api_inbox` с raw_payload
     - upsert `client_categories` из payload
     - INSERT `balance_holds` по позиционно, `api_submission_id` set, `request_items_staging_id=NULL` (появится после обработки inbox)
   - Ответ 202:
     ```json
     {"submission_id":"sub_…","status":"accepted","stage":"inbox_buffered","client_ref":"…","items_count":N,"created_at":"…","estimated_ready_at":"…"}
     ```
   - При повторе с тем же Idempotency-Key и ТЕМ ЖЕ payload → 200 тот же submission_id
   - При повторе с тем же Idempotency-Key и ДРУГИМ payload → 409 `idempotency_key_conflict`

2. `GET /api/v1/account/balance` — `Api\V1\AccountController@balance`
   - balance, active_holds (sum где status='held'), overdraft_limit_percent, overdraft_limit_absolute, warning

3. FormRequest классы: `CreateSubmissionRequest`, плюс кастомные правила для `client_category.path`

**Нюансы:**
- `idempotency_key` NOT NULL в БД. Если клиент не прислал header — сервер генерирует случайный UUID и кладёт в БД (тогда идемпотентность для этого запроса не работает, но БД консистентна).
- Структурно payload валидируется синхронно, классификация — асинхронно на этапе 4 (InboxProcessingWorker).
- Баланс проверяем в транзакции с `SELECT … FOR UPDATE` для защиты от гонок.

### ~~🔜 Этап 4 — Пайплайн классификации~~ ✅ Готово (см. выше)

**Цель:** InboxProcessingWorker берёт `api_inbox.pending`, классифицирует позиции, кладёт в staging.

**Перед стартом:** сменить `QUEUE_CONNECTION=sync → database` в `.env` (таблица jobs уже есть).

**Шаги:**
1. `InboxProcessingWorker` Job + schedule (cron раз в 5 мин, self-rescheduling).
   - Watchdog зависших `processing` (§8.2 шаг 1)
   - Failover: retry_count ≥ 3 → status=failed + алерт
   - Batch lock 500 позиций
   - Для каждой inbox-записи:
     - Распарсить payload
     - upsert `client_categories`
     - Классификация (см. ниже)
     - INSERT в `request_items_staging`
     - INSERT/UPDATE `request_staging` с stage='awaiting_moderation'
     - Привязка `balance_hold.request_items_staging_id`
     - DELETE из `api_inbox`
2. `ClientCategoryClassifierService`:
   - Стратегии (§4.1): manual→single→mini→ai. Trust levels green/yellow/red
3. `MiniClassifierService` — Haiku 4.5 через Anthropic SDK
   - Батчинг по client_category + candidates set
4. `FullAiClassifierService` — полный AI-проход без architect-mode
5. Feedback loop — на этапе 5 (модерация)

### ~~🔜 Этап 5 — Модерация~~ ✅ Готово (см. выше)

1. UI `/manage/api-submissions` (Filament или Blade)
2. Экшены: approve_batch (green), approve_item, reject_item, reclassify_item
3. Rejected → тонкий след в `api_submissions.rejected_summary`, hold released
4. Финализация → `status=ready`, `ready_at=NOW()`
5. Feedback loop в `client_category_candidates` (§4.4)

### ~~🔜 Этап 6 — Пул поставщиков + Discovery~~ ✅ Готово (см. выше)

1. `SupplierCoverageService::checkCoverage(domain_id, product_type_id)`
2. `DiscoveryOrchestratorJob` + `DiscoverSuppliersForPairJob` (последовательный, 1 активный run)
3. Cooldown: 7 дней success, 30 дней exhausted
4. `RecheckAwaitingSuppliersJob` (cron раз в час)
5. При 14 дней в `awaiting_suppliers` + все runs exhausted → reject `no_suppliers_available`, разморозка hold

### ~~🔜 Этап 7 — PromoteSubmissionJob~~ ✅ Готово (см. выше)

1. Cross-DB промоушен (§6.4): INSERT в reports → UPDATE iqot mapping
2. `ReconcilePromotionJob` (heartbeat каждые 5 мин) для orphans
3. `CleanupPromotedStagingJob` — DELETE staging через 5 мин после promoted

### ~~🔜 Этап 8 — Статусы, отчёты~~ ✅ Готово (см. выше)

1. `GET /api/v1/submissions/{id}` — агрегация из iqot+reports
2. `GET /api/v1/submissions/{id}/items`
3. `GET /api/v1/submissions/{id}/report` — JSON-рендерер `ReportBuilderService` (общий с web)
4. `GET /api/v1/taxonomy/domains`, `/product-types`

### ~~🔜 Этап 9 — Операционные возможности~~ ✅ Готово (см. выше)

1. `POST /api/v1/submissions/{id}/cancel`
2. Rate limiting (Redis token bucket):
   - POST /submissions: 10 rpm на ключ
   - GET /submissions/{id}: 1 раз в 15 сек
   - Суммарно: 60 rpm
3. `X-Balance-Warning` header + 402 `overdraft_exceeded`
4. `CleanupRevokedApiKeysJob` — физическое удаление > 30 дней после revoke
5. Sunset / Deprecation (Laravel middleware на response headers)

### ~~🔜 Этап 10 — Документация и тесты~~ ✅ Готово (docs). Тесты — открытая работа, см. выше.

1. OpenAPI 3.1 спец
2. Публичная документация (docs сайт)
3. Pest-тесты: happy-path + ключевые ошибки + cross-DB reconcile
4. Нагрузочный тест InboxProcessingWorker (500 позиций за волну)

---

## Открытые вопросы (перенос из §16 спеки)

- Индивидуальный overdraft по истории
- Sandbox (`iqot_test_*`)
- HMAC-подписи
- Scoping ключей
- Webhook'и
- CSV-импорт >300 позиций в ЛК
- Приоритизация клиентов в волнах
- Глобальный справочник типовых клиентских категорий

---

## Работа с окружением (памятка)

```bash
# PHP
PHP=/c/Users/Boag/.config/herd/bin/php83/php.exe
cd /c/Users/Boag/PhpstormProjects/iqot-platform

# Миграции
$PHP artisan migrate --force
$PHP artisan migrate:status

# Tinker (через файл — Windows bash глотает \$)
$PHP artisan tinker
$PHP path/to/script.php  # после require bootstrap/app.php

# MySQL iqot local
"/c/Program Files/MySQL/MySQL Workbench 8.0/mysql.exe" -h 127.0.0.1 -uroot iqot_platform

# API
curl -k -H "Authorization: Bearer iqot_live_…" https://iqot-platform.test/api/v1/ping
```

## Известные шероховатости окружения
- `php artisan route:list` падает на `PasswordResetLinkController` (предсуществующее).
- `QUEUE_CONNECTION=sync` — сменить на `database` перед Этапом 4.
- `CACHE_DRIVER=file` — подойдёт до Этапа 9 (rate limit лучше на redis).
- Reports БД = **прод Beget**. Не применять деструктивные миграции без подтверждения.
