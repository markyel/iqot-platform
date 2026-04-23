# IQOT Public API — Deploy Checklist (production iqot `217.26.31.80`)

Выполнять по порядку через SSH на `root@217.26.31.80`.

---

## 0. Препроверки

```bash
cd /var/www/iqot
git status                       # должно быть clean
git log --oneline -3             # текущие 3 коммита
```

---

## 1. Backup iqot БД (ОБЯЗАТЕЛЬНО)

```bash
mysqldump -u iqot_user -p'Ij8uBLVBf+hGJ7HBjxFRNg==' iqot | gzip \
  > /tmp/backup_before_api_deploy_$(date +%Y%m%d_%H%M%S).sql.gz
ls -lh /tmp/backup_before_api_deploy_*.sql.gz
```

Оставь путь — понадобится при rollback.

---

## 2. Pull кода

```bash
git fetch origin
git log HEAD..origin/main --oneline    # должно быть 10 новых коммитов feat(api): Stage 1..10
git pull origin main
```

---

## 3. composer install

```bash
composer install --no-dev --optimize-autoloader
```

---

## 4. ⚠️ Пометить reports-миграции применёнными заранее

**Почему:** миграции `100012_create_supplier_discovery_runs_table_on_reports` и
`100013_extend_reports_requests_and_product_types` уже физически применены на Beget
(через локальный `php artisan migrate` 2026-04-22). Но в таблице `iqot.migrations`
на продакшне их нет. Без этого шага `migrate --force` попытается выполнить их и
упадёт с `1050 Table 'supplier_discovery_runs' already exists`.

```bash
# Узнать текущий max batch:
mysql -u iqot_user -p'Ij8uBLVBf+hGJ7HBjxFRNg==' iqot \
  -e "SELECT MAX(batch) FROM migrations;"
# Пусть это NEW_BATCH = max+1 (возьми сам):

mysql -u iqot_user -p'Ij8uBLVBf+hGJ7HBjxFRNg==' iqot <<SQL
INSERT INTO migrations (migration, batch) VALUES
  ('2026_04_22_100012_create_supplier_discovery_runs_table_on_reports', NEW_BATCH),
  ('2026_04_22_100013_extend_reports_requests_and_product_types', NEW_BATCH);
SQL
```

**Проверь:**
```bash
mysql -u iqot_user -p'Ij8uBLVBf+hGJ7HBjxFRNg==' iqot \
  -e "SELECT migration FROM migrations WHERE migration LIKE '%100012%' OR migration LIKE '%100013%';"
```

---

## 5. Migrate dry-run → реальный

```bash
# Сначала pretend — глянуть SQL без исполнения
php artisan migrate --pretend --force 2>&1 | tail -60

# Если SQL выглядит ожидаемо (создание api_clients, api_keys, ..., ALTER balance_holds, etc.):
php artisan migrate --force

php artisan migrate:status | grep "2026_04_22_1000"
# Все 13 миграций должны быть Ran (включая 100012/100013 из шага 4).
```

**Ожидаемые изменения в iqot:**
- NEW tables: `api_clients, api_keys, client_categories, client_category_candidates,
  api_submissions, api_inbox, request_staging, request_items_staging, user_senders`.
- ALTER `balance_holds` (+request_item_id, +api_submission_id, +request_items_staging_id, +3 index).
- Data migration `users.sender_id → user_senders` (для всех users со sender_id IS NOT NULL).
- tariff_plans.features JSON дополняется `api_access=false` во всех планах.

**Ничего не удаляется.**

---

## 6. .env — добавить переменные и поменять QUEUE

```bash
cat >> /var/www/iqot/.env <<'EOF'

# OpenAI-совместимый прокси для классификации API-заявок (Stage 4)
OPENAI_CLASSIFIER_BASE_URL=https://ai.lazylift.ru/v1
OPENAI_CLASSIFIER_API_KEY=<скопируй с локалки>
OPENAI_CLASSIFIER_PROXY_KEY=<скопируй с локалки>
OPENAI_CLASSIFIER_MODEL_MINI=gpt-4o-mini
OPENAI_CLASSIFIER_MODEL_FULL=gpt-4o
OPENAI_CLASSIFIER_TIMEOUT=30
EOF

# QUEUE_CONNECTION sync → database (нужно для async Jobs)
sed -i.bak 's/^QUEUE_CONNECTION=sync/QUEUE_CONNECTION=database/' .env
grep QUEUE_CONNECTION .env  # проверить что =database
```

**Проверь что `REPORTS_DB_*` уже есть в .env проды** (они нужны для cross-DB). Если нет — добавь те же значения что в локальной .env.

---

## 7. Cache rebuild

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 8. Queue worker через supervisor

**Проверить наличие:**
```bash
supervisorctl status 2>/dev/null || systemctl list-units --all | grep -i queue
```

Если воркер уже есть — `supervisorctl restart iqot-queue` (или аналог). Иначе создать supervisor-конфиг:

```ini
[program:iqot-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/iqot/artisan queue:work --queue=default --sleep=3 --tries=1 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/iqot-queue.log
stopwaitsecs=3600
```

```bash
supervisorctl reread && supervisorctl update && supervisorctl start iqot-queue
```

**Без воркера джобы (InboxProcessingWorker, Discovery, Reconcile, Cleanup) не будут исполняться.**

---

## 9. Cron scheduler (проверить)

В crontab root должна быть строка Laravel scheduler:
```
* * * * * cd /var/www/iqot && php artisan schedule:run >> /dev/null 2>&1
```

Проверь:
```bash
crontab -l | grep schedule:run
```

---

## 10. Smoke-тесты

### 10.1. Включить api_access для тестового тарифа
```bash
mysql -u iqot_user -p'Ij8uBLVBf+hGJ7HBjxFRNg==' iqot <<SQL
UPDATE tariff_plans
SET features = JSON_SET(COALESCE(features, '{}'), '$.api_access', true)
WHERE code = 'start';
SQL
```

### 10.2. Создать ключ для тестового пользователя
Залогинься под admin в ЛК → `/cabinet/api-keys` → создать ключ. Сохрани.

### 10.3. Curl-тесты
```bash
KEY="iqot_live_..."

# Ping
curl -H "Authorization: Bearer $KEY" https://iqot.ru/api/v1/ping

# Balance
curl -H "Authorization: Bearer $KEY" https://iqot.ru/api/v1/account/balance

# Taxonomy
curl -H "Authorization: Bearer $KEY" https://iqot.ru/api/v1/taxonomy/domains | head -c 300
```

Все — HTTP 200.

---

## Rollback (если что-то пошло не так)

```bash
# 1. Откат кода
cd /var/www/iqot
git reset --hard 771d36a    # до наших коммитов

# 2. Восстановить БД из backup (ТОЛЬКО если miграции применились неудачно!)
gunzip < /tmp/backup_before_api_deploy_<timestamp>.sql.gz | \
  mysql -u iqot_user -p'Ij8uBLVBf+hGJ7HBjxFRNg==' iqot

# 3. Cache clear
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 4. Restart queue worker
supervisorctl restart iqot-queue
```

**Внимание:** на reports БД (Beget) ALTER'ы 100012/100013 уже были применены локально вчера. Их rollback требует отдельного ручного SQL на Beget. Обычно не нужен — они аддитивные (ALTER … ADD COLUMN).

---

## После деплоя

- [ ] Проверить logs через 15 минут: `tail -50 /var/log/iqot-queue.log`
- [ ] Проверить что scheduler запускает `api:inbox:process` каждые 5 минут: `tail -20 storage/logs/laravel.log`
- [ ] Создать первого production api_client для партнёра.
