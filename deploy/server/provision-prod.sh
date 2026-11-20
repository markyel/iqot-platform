#!/usr/bin/env bash
#
# provision-prod.sh — подготовить НОВЫЙ прод IQOT на чистой Ubuntu 24.04 (после потери Beget, 11.2026).
# Запускать на сервере под root. Повторный прогон безопасен: сделанное пропускается.
#
# Что делает:
#   0) проверка железа: ОС, CPU/RAM/диски, RAID (mdstat), SMART — только отчёт;
#   1) база: часовой пояс UTC, chrony (NTP — на старом проде часы уплывали на 3–5 мин), swap, ufw, fail2ban;
#   2) PHP 8.3 (+FPM) из репозитория Ubuntu, Composer, Node.js/npm, Google Chrome (рендер страниц);
#   3) MySQL 8.0: обе базы на этом сервере — iqot и price_quotation_system (бывшая управляемая reports
#      на Beget), свои пользователи, бинлог 2 дня, MINIMAL, UTC; пароли — в /root/iqot-secrets/db.env;
#   4) nginx + PHP-FPM под домен, certbot (сертификат — отдельным шагом, когда DNS смотрит сюда);
#   5) код: git clone, composer, npm (puppeteer без своего Chrome), сборка фронта, права;
#   6) .env-заготовка: БД, очереди, ВСЕ флаги рассылки/мониторинга/биллинга ВЫКЛЮЧЕНЫ;
#   7) systemd-юниты всех воркеров (созданы, НЕ запущены), crontab планировщика (закомментирован);
#   8) logrotate логов приложения и воркеров;
#   9) ночные копии обеих баз + storage + .env на хост другого провайдера (reserve, RUVDS).
#
# НЕ делает (сознательно, отдельные шаги):
#   - восстановление баз из дампов и migrate;
#   - ключи сервисов в .env (OpenAI, Beget, REG.RU, Telegram, Яндекс…);
#   - запуск воркеров и планировщика — только после проверки данных (см. итог в конце);
#   - доступ релея #3 и egress-прокси для нового IP (их фаервол пускает только старый IP прода).
#
# Использование:
#   scp deploy/server/provision-prod.sh root@<IP>:/root/
#   ssh root@<IP> 'DOMAIN=iqot.ru bash /root/provision-prod.sh'
# Переменные (необязательные):
#   DOMAIN=iqot.ru  APP_DIR=/var/www/iqot  REPO_HTTPS=https://github.com/markyel/iqot-platform.git
#   REPO_SSH=git@github.com:markyel/iqot-platform.git  BRANCH=main
#   BACKUP_HOST=194.135.20.158  BACKUP_USER=iqotbak  BACKUP_DIR=/backups/iqot
#   EMAIL_WORKERS=8  MONITOR_WORKERS=4  SWAP_GB=4
#
set -euo pipefail

DOMAIN="${DOMAIN:-iqot.ru}"
APP_DIR="${APP_DIR:-/var/www/iqot}"
REPO_HTTPS="${REPO_HTTPS:-https://github.com/markyel/iqot-platform.git}"
REPO_SSH="${REPO_SSH:-git@github.com:markyel/iqot-platform.git}"
BRANCH="${BRANCH:-main}"
BACKUP_HOST="${BACKUP_HOST:-194.135.20.158}"
BACKUP_USER="${BACKUP_USER:-iqotbak}"
BACKUP_DIR="${BACKUP_DIR:-/backups/iqot}"
EMAIL_WORKERS="${EMAIL_WORKERS:-8}"
MONITOR_WORKERS="${MONITOR_WORKERS:-4}"
SWAP_GB="${SWAP_GB:-4}"

DB_IQOT=iqot
DB_IQOT_USER=iqot_user
DB_REPORTS=price_quotation_system
DB_REPORTS_USER=price_quotation_system
SECRETS_DIR=/root/iqot-secrets
PHP_V=8.3

step() { echo; echo "==== $*"; }
note() { echo "  · $*"; }
warn() { echo "  ⚠ $*"; }

[ "$(id -u)" -eq 0 ] || { echo "Запускать под root"; exit 1; }
. /etc/os-release
[ "${VERSION_ID:-}" = "24.04" ] || warn "ожидалась Ubuntu 24.04, здесь ${PRETTY_NAME:-?} — продолжаю"
export DEBIAN_FRONTEND=noninteractive

# ---------------------------------------------------------------------------
step "0. Железо"
note "CPU: $(nproc) потоков, $(grep -m1 'model name' /proc/cpuinfo | cut -d: -f2 | xargs)"
note "RAM: $(free -g | awk '/Mem:/{print $2}') ГБ"
lsblk -d -o NAME,SIZE,ROTA,MODEL | sed 's/^/    /'
if [ -r /proc/mdstat ] && grep -q '^md' /proc/mdstat; then
    note "RAID:"; grep -A2 '^md' /proc/mdstat | sed 's/^/    /'
    grep -q '\[U_\]\|\[_U\]' /proc/mdstat && warn "RAID деградирован — разобраться ДО переноса данных"
else
    warn "программного RAID нет: если дисков два, второй не зеркалит первый — собрать RAID1 до переноса данных"
fi
apt-get update -qq
apt-get install -y -qq smartmontools >/dev/null
for d in $(lsblk -dno NAME,TYPE | awk '$2=="disk"{print $1}'); do
    h=$(smartctl -H "/dev/$d" 2>/dev/null | grep -iE 'overall-health|SMART Health Status' | cut -d: -f2 | xargs || true)
    note "SMART /dev/$d: ${h:-нет данных}"
done

# ---------------------------------------------------------------------------
step "1. Система"
timedatectl set-timezone UTC
apt-get upgrade -y -qq
apt-get install -y -qq curl ca-certificates gnupg git unzip zip rsync zstd jq htop ufw fail2ban chrony \
    logrotate cron acl >/dev/null
systemctl enable --now chrony cron >/dev/null
note "время: $(date -u '+%F %T') UTC, NTP: $(timedatectl show -p NTPSynchronized --value)"

if ! swapon --show | grep -q .; then
    fallocate -l "${SWAP_GB}G" /swapfile && chmod 600 /swapfile && mkswap /swapfile >/dev/null && swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
    echo 'vm.swappiness=10' > /etc/sysctl.d/90-iqot.conf && sysctl -q -p /etc/sysctl.d/90-iqot.conf
    note "swap ${SWAP_GB} ГБ создан"
fi

ufw allow OpenSSH >/dev/null; ufw allow 80/tcp >/dev/null; ufw allow 443/tcp >/dev/null
ufw --force enable >/dev/null
systemctl enable --now fail2ban >/dev/null
note "ufw: открыты 22, 80, 443; MySQL слушает только 127.0.0.1"

# ---------------------------------------------------------------------------
step "2. PHP ${PHP_V}, Composer, Node.js, Chrome"
apt-get install -y -qq php${PHP_V}-fpm php${PHP_V}-cli php${PHP_V}-mysql php${PHP_V}-mbstring php${PHP_V}-xml \
    php${PHP_V}-bcmath php${PHP_V}-curl php${PHP_V}-zip php${PHP_V}-gd php${PHP_V}-intl php${PHP_V}-gmp \
    php${PHP_V}-opcache php${PHP_V}-readline composer nodejs npm >/dev/null

cat > /etc/php/${PHP_V}/fpm/conf.d/90-iqot.ini <<'INI'
memory_limit = 512M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 120
cgi.fix_pathinfo = 0
expose_php = Off
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 30000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
INI
cat > /etc/php/${PHP_V}/cli/conf.d/90-iqot.ini <<'INI'
memory_limit = 1024M
max_execution_time = 0
INI
sed -i -E 's/^pm.max_children = .*/pm.max_children = 24/; s/^pm.start_servers = .*/pm.start_servers = 4/; s/^pm.min_spare_servers = .*/pm.min_spare_servers = 2/; s/^pm.max_spare_servers = .*/pm.max_spare_servers = 8/; s/^;?pm.max_requests = .*/pm.max_requests = 500/' \
    /etc/php/${PHP_V}/fpm/pool.d/www.conf
systemctl restart php${PHP_V}-fpm
note "PHP $(php -r 'echo PHP_VERSION;'), Composer $(composer --version 2>/dev/null | awk '{print $3}'), Node $(node -v)"

if ! command -v google-chrome-stable >/dev/null; then
    install -d -m 0755 /etc/apt/keyrings
    curl -fsSL https://dl.google.com/linux/linux_signing_key.pub | gpg --dearmor -o /etc/apt/keyrings/google-chrome.gpg
    echo "deb [arch=amd64 signed-by=/etc/apt/keyrings/google-chrome.gpg] https://dl.google.com/linux/chrome/deb/ stable main" \
        > /etc/apt/sources.list.d/google-chrome.list
    apt-get update -qq && apt-get install -y -qq google-chrome-stable >/dev/null
fi
note "$(google-chrome-stable --version)"

# ---------------------------------------------------------------------------
step "3. MySQL 8.0"
apt-get install -y -qq mysql-server >/dev/null
RAM_MB=$(free -m | awk '/Mem:/{print $2}')
POOL_MB=$(( RAM_MB / 4 ))
cat > /etc/mysql/mysql.conf.d/90-iqot.cnf <<CNF
[mysqld]
bind-address              = 127.0.0.1
mysqlx                    = OFF
skip-name-resolve
default-time-zone         = '+00:00'
character-set-server      = utf8mb4
collation-server          = utf8mb4_unicode_ci
max_connections           = 300
innodb_buffer_pool_size   = ${POOL_MB}M
innodb_redo_log_capacity  = 1G
innodb_flush_method       = O_DIRECT
max_allowed_packet        = 256M
# Бинлог: 2 дня, MINIMAL — инцидент 15.10.2026 (бинлог за 30 дней забил диск). Нужен и для разбора, и как
# возможность докатить изменения после ночной копии.
log_bin                   = /var/lib/mysql/binlog
binlog_expire_logs_seconds = 172800
binlog_row_image          = MINIMAL
# Хранимые функции reports (calculate_request_completion для триггера) создаёт миграция под пользователем базы,
# а при включённом бинлоге без этого MySQL требует SUPER.
log_bin_trust_function_creators = 1
CNF
systemctl restart mysql

mkdir -p "$SECRETS_DIR" && chmod 700 "$SECRETS_DIR"
if [ ! -f "$SECRETS_DIR/db.env" ]; then
    umask 077
    cat > "$SECRETS_DIR/db.env" <<ENV
DB_IQOT_PASSWORD=$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-28)
DB_REPORTS_PASSWORD=$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-28)
ENV
    umask 022
fi
. "$SECRETS_DIR/db.env"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_IQOT}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS \`${DB_REPORTS}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- skip-name-resolve: TCP-подключение к 127.0.0.1 приходит как '127.0.0.1', не 'localhost' — заводим оба
CREATE USER IF NOT EXISTS '${DB_IQOT_USER}'@'localhost' IDENTIFIED BY '${DB_IQOT_PASSWORD}';
CREATE USER IF NOT EXISTS '${DB_IQOT_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_IQOT_PASSWORD}';
CREATE USER IF NOT EXISTS '${DB_REPORTS_USER}'@'localhost' IDENTIFIED BY '${DB_REPORTS_PASSWORD}';
CREATE USER IF NOT EXISTS '${DB_REPORTS_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_REPORTS_PASSWORD}';
ALTER USER '${DB_IQOT_USER}'@'localhost' IDENTIFIED BY '${DB_IQOT_PASSWORD}', '${DB_IQOT_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_IQOT_PASSWORD}';
ALTER USER '${DB_REPORTS_USER}'@'localhost' IDENTIFIED BY '${DB_REPORTS_PASSWORD}', '${DB_REPORTS_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_REPORTS_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_IQOT}\`.* TO '${DB_IQOT_USER}'@'localhost', '${DB_IQOT_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${DB_REPORTS}\`.* TO '${DB_REPORTS_USER}'@'localhost', '${DB_REPORTS_USER}'@'127.0.0.1';
-- миграции iqot пишут и в reports (Schema::connection('reports')) под пользователем reports — отдельный доступ не нужен
FLUSH PRIVILEGES;
SQL
note "базы ${DB_IQOT}, ${DB_REPORTS}; пароли — ${SECRETS_DIR}/db.env; buffer pool ${POOL_MB} МБ"

# ---------------------------------------------------------------------------
step "4. nginx, certbot"
apt-get install -y -qq nginx certbot python3-certbot-nginx >/dev/null
cat > /etc/nginx/sites-available/iqot <<NGINX
server {
    listen 80;
    server_name ${DOMAIN} www.${DOMAIN};
    root ${APP_DIR}/public;
    index index.php;
    charset utf-8;
    client_max_body_size 64M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php${PHP_V}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 180;
        fastcgi_hide_header X-Powered-By;
    }
    location ~ /\.(?!well-known).* { deny all; }

    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml image/svg+xml;
}
NGINX
ln -sf /etc/nginx/sites-available/iqot /etc/nginx/sites-enabled/iqot
rm -f /etc/nginx/sites-enabled/default
nginx -t -q && systemctl reload nginx
note "сайт ${DOMAIN} на :80; HTTPS — после переноса DNS: certbot --nginx -d ${DOMAIN} -d www.${DOMAIN}"

# ---------------------------------------------------------------------------
step "5. Код"
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true
if [ ! -d "$APP_DIR/.git" ]; then
    mkdir -p "$(dirname "$APP_DIR")"
    if GIT_TERMINAL_PROMPT=0 git clone -q -b "$BRANCH" "$REPO_HTTPS" "$APP_DIR" 2>/dev/null; then
        note "клон по HTTPS"
    else
        KEY=/root/.ssh/id_ed25519_github
        [ -f "$KEY" ] || ssh-keygen -q -t ed25519 -N '' -C "iqot-prod-deploy@$(hostname)" -f "$KEY"
        grep -q 'Host github.com' /root/.ssh/config 2>/dev/null || cat >> /root/.ssh/config <<CFG
Host github.com
    IdentityFile $KEY
    IdentitiesOnly yes
CFG
        ssh-keyscan -q github.com >> /root/.ssh/known_hosts 2>/dev/null
        if ! git clone -q -b "$BRANCH" "$REPO_SSH" "$APP_DIR" 2>/dev/null; then
            echo
            echo "Репозиторий закрыт. Добавьте ключ в GitHub → markyel/iqot-platform → Settings → Deploy keys (read-only):"
            cat "$KEY.pub"
            echo "и запустите скрипт ещё раз."
            exit 2
        fi
        note "клон по SSH (deploy key)"
    fi
fi
cd "$APP_DIR"
note "ветка $(git rev-parse --abbrev-ref HEAD), коммит $(git log -1 --format='%h %s' | cut -c1-80)"

# Каталоги, которых нет в git: без bootstrap/cache падает package:discover в composer install.
mkdir -p storage/app/headless storage/app/public storage/logs bootstrap/cache \
    storage/framework/cache/data storage/framework/sessions storage/framework/views
COMPOSER_ALLOW_SUPERUSER=1 composer install -q --no-dev --optimize-autoloader --no-interaction
PUPPETEER_SKIP_DOWNLOAD=true npm ci --no-audit --no-fund --loglevel=error
npm run build --silent >/dev/null
chown -R www-data:www-data storage bootstrap/cache
setfacl -R -m u:www-data:rwX -m d:u:www-data:rwX storage bootstrap/cache

# ---------------------------------------------------------------------------
step "6. .env (заготовка)"
if [ ! -f .env ]; then
    umask 077
    cat > .env <<ENV
APP_NAME=IQOT
APP_ENV=production
APP_KEY=base64:$(openssl rand -base64 32)
APP_DEBUG=false
APP_URL=https://${DOMAIN}

LOG_CHANNEL=daily
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_IQOT}
DB_USERNAME=${DB_IQOT_USER}
DB_PASSWORD=${DB_IQOT_PASSWORD}

REPORTS_DB_CONNECTION=mysql
REPORTS_DB_HOST=127.0.0.1
REPORTS_DB_PORT=3306
REPORTS_DB_DATABASE=${DB_REPORTS}
REPORTS_DB_USERNAME=${DB_REPORTS_USER}
REPORTS_DB_PASSWORD=${DB_REPORTS_PASSWORD}
REPORTS_DB_TIMEZONE=+00:00

QUEUE_CONNECTION=database
# > самого долгого --timeout воркера (monitor-index 900). ⚠ DiscoverSuppliersForPairJob (default) живёт до 1800 с —
# при 960 его может подхватить второй воркер; было так и на старом проде, решение — за владельцем.
DB_QUEUE_RETRY_AFTER=960
CACHE_STORE=database
SESSION_DRIVER=database
FILESYSTEM_DISK=local
MAIL_MAILER=log

# ---- Всё, что пишет наружу или списывает деньги, — ВЫКЛЮЧЕНО до проверки восстановленных данных ----
EMAILS_DISPATCH_ENABLED=false
EMAILS_RECEIVE_ENABLED=false
EMAILS_ANALYZE_ENABLED=false
EMAILS_QUESTIONS_ENABLED=false
EMAILS_REPLIES_ENABLED=false
EMAILS_IDENTIFY_ENABLED=false
EMAILS_GENERATE_ENABLED=false
EMAILS_PLANNER_ENABLED=false
EMAILS_DAYPLAN_ENABLED=false
EMAILS_POOL_FOLLOWUP_ENABLED=false
EMAILS_POOL_GATE_ENABLED=false
EMAILS_LOAD_DEFER_ENABLED=false
EMAILS_WARMUP_ENABLED=false
EMAILS_SPAM_GUARD_ENABLED=false
EMAILS_REVIVAL_ENABLED=false
EMAILS_MAILBOX_REPLENISH_ENABLED=false
EMAILS_UNSUBSCRIBE_ENABLED=false
EMAILS_AUTOCLOSE_ENABLED=false
ALIAS_EXP_ENABLED=false
MONITORING_ENABLED=false
MONITORING_HYBRID_SEND=false
MONITORING_BILLING_ENABLED=false
BILLING_API_RECONCILE_ENABLED=false
BILLING_API_HOLD_EXPIRY_ENABLED=false
API_MODERATION_SLA_ENABLED=false
ATTACHMENTS_DRIVE_ENABLED=false

EMAILS_ANALYZE_CHROME_PATH=/usr/bin/google-chrome-stable

# ---- Ключи сервисов — заполнить отдельно (OpenAI, ИИ-шлюз Beget, Beget API, REG.RU, Telegram, Яндекс, релеи) ----
ENV
    umask 022
    chown root:www-data .env && chmod 640 .env
    note ".env создан, APP_KEY новый (старый потерян; им шифровался только пароль ящика в alias_domains)"
else
    note ".env уже есть — не трогаю"
fi
php artisan storage:link -q 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache
sudo -u www-data php artisan list >/dev/null && note "artisan грузится (от www-data)"

# ---------------------------------------------------------------------------
step "7. Воркеры (systemd) и планировщик"
# имя юнита | очереди | --timeout | --tries | --sleep
WORKERS=(
    "email|emails|120|0|1"
    "queue|default|1800|1|3"
    "receive|receive|180|1|2"
    "analyze|analyze|180|1|3"
    "identify|identify|180|1|3"
    "questions|questions|120|1|3"
    "replies|replies|120|0|3"
    "generate|generate|200|1|3"
    "monitor|monitor,monitor-import|900|0|3"
    "monitor-index|monitor-index|900|1|3"
)
for w in "${WORKERS[@]}"; do
    IFS='|' read -r name queues timeout tries sleep <<<"$w"
    cat > "/etc/systemd/system/iqot-${name}-worker@.service" <<UNIT
[Unit]
Description=IQOT queue worker ${name} #%i (${queues})
After=network-online.target mysql.service
Wants=network-online.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=${APP_DIR}
# Chrome (Browsershot) пишет в HOME; у www-data HOME=/var/www не пишется
Environment=HOME=${APP_DIR}/storage/app/headless
ExecStart=/usr/bin/php ${APP_DIR}/artisan queue:work database --queue=${queues} --tries=${tries} --timeout=${timeout} --sleep=${sleep} --max-time=3600 --memory=512
Restart=always
RestartSec=5
KillSignal=SIGTERM
TimeoutStopSec=$(( timeout + 30 ))
StandardOutput=append:/var/log/iqot-${name}-worker-%i.log
StandardError=append:/var/log/iqot-${name}-worker-%i.log

[Install]
WantedBy=multi-user.target
UNIT
done
systemctl daemon-reload
# Набор экземпляров для запуска (после восстановления):
{
    echo "# Экземпляры воркеров — запуск: iqot-workers start"
    for i in $(seq 1 "$EMAIL_WORKERS");   do echo "iqot-email-worker@$i"; done
    echo "iqot-queue-worker@1"; echo "iqot-queue-worker@2"
    echo "iqot-receive-worker@1"; echo "iqot-receive-worker@2"
    for n in analyze identify questions replies generate monitor-index; do echo "iqot-${n}-worker@1"; done
    for i in $(seq 1 "$MONITOR_WORKERS"); do echo "iqot-monitor-worker@$i"; done
} > /etc/iqot-workers.list
cat > /usr/local/bin/iqot-workers <<'SH'
#!/usr/bin/env bash
# iqot-workers start|stop|restart|status — все экземпляры из /etc/iqot-workers.list
set -euo pipefail
units=$(grep -v '^#' /etc/iqot-workers.list)
case "${1:-status}" in
    start)   systemctl enable --now $units ;;
    stop)    systemctl disable --now $units ;;
    restart) systemctl restart $units ;;
    status)  systemctl is-active $units | paste <(echo "$units") - | column -t ;;
esac
SH
chmod +x /usr/local/bin/iqot-workers
note "юниты созданы ($(grep -vc '^#' /etc/iqot-workers.list) экземпляров), НЕ запущены: iqot-workers start"

# Планировщик — от www-data (на старом проде был root-crontab → файлы кеша/логов от root).
if ! crontab -u www-data -l 2>/dev/null | grep -q 'schedule:run'; then
    echo "#* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1" | crontab -u www-data -
fi
note "crontab www-data: schedule:run ЗАКОММЕНТИРОВАН — включить после восстановления (crontab -u www-data -e)"

# ---------------------------------------------------------------------------
step "8. Ротация логов"
cat > /etc/logrotate.d/iqot <<ROT
${APP_DIR}/storage/logs/*.log /var/log/iqot-*-worker-*.log {
    daily
    rotate 14
    maxsize 200M
    missingok
    notifempty
    compress
    delaycompress
    copytruncate
    su root root
}
ROT
note "логи приложения и воркеров: ежедневно, 14 дней, сжатие (старый laravel.log дорос до 318 МБ)"

# ---------------------------------------------------------------------------
step "9. Ночные копии на ${BACKUP_HOST}"
BKEY=/root/.ssh/id_ed25519_backup
[ -f "$BKEY" ] || ssh-keygen -q -t ed25519 -N '' -C "iqot-prod-backup@$(hostname)" -f "$BKEY"
cat > /etc/iqot-backup.conf <<CONF
APP_DIR=${APP_DIR}
DBS="${DB_IQOT} ${DB_REPORTS}"
LOCAL_DIR=/var/backups/iqot
LOCAL_KEEP_DAYS=3
REMOTE=${BACKUP_USER}@${BACKUP_HOST}
REMOTE_DIR=${BACKUP_DIR}
REMOTE_KEEP_DAYS=14
SSH_KEY=${BKEY}
CONF
cat > /usr/local/bin/iqot-backup <<'SH'
#!/usr/bin/env bash
# Ночная копия: обе базы (консистентный снимок InnoDB), storage/app, .env → локально и на хост другого провайдера.
set -uo pipefail
. /etc/iqot-backup.conf
STAMP=$(date -u +%F-%H%M); OUT="$LOCAL_DIR/$STAMP"; mkdir -p "$OUT"; chmod 700 "$LOCAL_DIR"
fail=0
for db in $DBS; do
    if mysqldump --single-transaction --quick --routines --triggers --events --hex-blob \
        --set-gtid-purged=OFF --source-data=2 "$db" 2>"$OUT/$db.err" | zstd -q -T0 -3 -o "$OUT/$db.sql.zst"; then
        [ -s "$OUT/$db.err" ] || rm -f "$OUT/$db.err"
    else fail=1; fi
done
tar -C "$APP_DIR" --exclude='storage/app/headless' -cf - storage/app | zstd -q -T0 -3 -o "$OUT/storage-app.tar.zst" || fail=1
install -m 600 "$APP_DIR/.env" "$OUT/env"
( cd "$OUT" && sha256sum * > SHA256SUMS )
find "$LOCAL_DIR" -mindepth 1 -maxdepth 1 -type d -mtime +"$LOCAL_KEEP_DAYS" -exec rm -rf {} +
SSH="ssh -i $SSH_KEY -o BatchMode=yes -o StrictHostKeyChecking=accept-new"
if rsync -a -e "$SSH" "$OUT" "$REMOTE:$REMOTE_DIR/"; then
    $SSH "$REMOTE" "find '$REMOTE_DIR' -mindepth 1 -maxdepth 1 -type d -mtime +$REMOTE_KEEP_DAYS -exec rm -rf {} +" || true
else fail=1; fi
echo "$(date -u '+%F %T') $STAMP fail=$fail size=$(du -sh "$OUT" | cut -f1)"
exit $fail
SH
chmod 700 /usr/local/bin/iqot-backup
# 22:30 UTC = 01:30 МСК — рассылка и мониторинг затихают
echo "30 22 * * * root /usr/local/bin/iqot-backup >> /var/log/iqot-backup.log 2>&1" > /etc/cron.d/iqot-backup
note "копия каждую ночь в 01:30 МСК → ${BACKUP_USER}@${BACKUP_HOST}:${BACKUP_DIR} (14 дней), локально 3 дня"
note "ключ для ${BACKUP_HOST} (добавить пользователю ${BACKUP_USER}):"
echo "    $(cat "$BKEY.pub")"

# ---------------------------------------------------------------------------
step "Готово. Дальше (по порядку)"
cat <<TXT
  1. RAID/SMART — проверить отчёт шага 0.
  2. Восстановить базы из дампов → php artisan migrate (iqot + довосстановитель reports 2026_06_30).
  3. Заполнить ключи сервисов в ${APP_DIR}/.env, затем: sudo -u www-data php artisan config:cache
  4. DNS ${DOMAIN} → $(curl -fsS4 https://ifconfig.me 2>/dev/null || echo '<IP>'), затем certbot --nginx -d ${DOMAIN} -d www.${DOMAIN}
  5. Релей #3 (45.143.93.23) и egress-прокси: открыть :8000/:3128 новому IP (сейчас пускают только 217.26.31.80).
  6. Ключ копий — на ${BACKUP_HOST}; проверить: /usr/local/bin/iqot-backup
  7. Запуск: iqot-workers start; crontab -u www-data -e (раскомментировать schedule:run); флаги в .env — по одному.
TXT
