#!/usr/bin/env bash
#
# provision-relay.sh — развернуть universal-email-service (генерик SMTP/IMAP-релей)
# на НОВОМ релее одним прогоном. Запускать НА релее под root.
#
# Что делает:
#   1) ставит docker (если нет);
#   2) поднимает образ микросервиса — из docker save .tar (IMAGE_TAR) ЛИБО сборкой
#      из ./microservice;
#   3) пишет /root/ues.env (API_KEY) и запускает контейнер (docker run — compose v1
#      на хостах ломается на 'ContainerConfig');
#   4) закрывает :8000 фаерволом — пускает ТОЛЬКО с IP прода (DOCKER-USER DROP), persist;
#   5) health-check;
#   6) печатает шаги регистрации в Laravel + напоминание про rDNS.
#
# Использование (на релее):
#   export API_KEY='<секрет, тот же что в Laravel .env EMAILS_MICROSERVICE_API_KEY>'
#   export PROD_IP='217.26.31.80'          # IP прода (единственный, кому открыт :8000)
#   # опц. быстрый путь без сборки — перенести образ с релея #1:
#   #   на релее #1:  docker save universal_email_service_universal-email-service:latest | gzip > ues.tgz
#   #   на новый:     scp ues.tgz . ; gunzip ues.tgz ; export IMAGE_TAR=$PWD/ues.tar
#   ./provision-relay.sh
#
set -euo pipefail

API_KEY="${API_KEY:-}"
PROD_IP="${PROD_IP:-217.26.31.80}"
PORT="${PORT:-8000}"
IMAGE="${IMAGE:-universal_email_service_universal-email-service:latest}"
CONTAINER="${CONTAINER:-universal-email-service}"
IMAGE_TAR="${IMAGE_TAR:-}"
SRC_DIR="$(cd "$(dirname "$0")/microservice" && pwd)"

log() { printf '\n=== %s ===\n' "$*"; }

[ -n "$API_KEY" ] || { echo "ERROR: задай API_KEY (export API_KEY=...)"; exit 1; }
[ "$(id -u)" = "0" ] || { echo "ERROR: запускать под root"; exit 1; }

log "1. docker"
if ! command -v docker >/dev/null 2>&1; then
  curl -fsSL https://get.docker.com | sh
fi
systemctl enable --now docker >/dev/null 2>&1 || true

log "2. образ микросервиса"
if [ -n "$IMAGE_TAR" ] && [ -f "$IMAGE_TAR" ]; then
  echo "docker load из $IMAGE_TAR"
  docker load -i "$IMAGE_TAR"
elif docker image inspect "$IMAGE" >/dev/null 2>&1; then
  echo "образ $IMAGE уже есть — пропускаю сборку"
else
  echo "сборка из $SRC_DIR"
  docker build -t "$IMAGE" "$SRC_DIR"
fi

log "3. env + запуск контейнера"
umask 077
cat > /root/ues.env <<ENV
API_KEY=$API_KEY
TZ=Europe/Moscow
PYTHONUNBUFFERED=1
ENV
docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
docker run -d --name "$CONTAINER" --restart unless-stopped \
  -p "${PORT}:8000" --env-file /root/ues.env \
  --memory 512m --cpus 1.0 "$IMAGE"

log "4. firewall — :$PORT только с прода ($PROD_IP)"
# DOCKER-USER срабатывает ДО DNAT в контейнер (docker её не флашит). Пускаем только прод.
if ! iptables -C DOCKER-USER -p tcp --dport "$PORT" ! -s "$PROD_IP" -j DROP 2>/dev/null; then
  iptables -I DOCKER-USER -p tcp --dport "$PORT" ! -s "$PROD_IP" -j DROP
fi
mkdir -p /etc/iptables
if command -v netfilter-persistent >/dev/null 2>&1; then
  netfilter-persistent save
else
  apt-get install -y iptables-persistent >/dev/null 2>&1 || true
  iptables-save > /etc/iptables/rules.v4
fi
echo "правило DOCKER-USER:"; iptables -L DOCKER-USER -n | grep "dpt:$PORT" || true

log "5. health-check"
sleep 3
docker ps --filter "name=$CONTAINER" --format '{{.Names}} {{.Status}}'
curl -fsS "http://localhost:${PORT}/health" && echo "  <- health OK" || { echo "health FAIL"; docker logs --tail 30 "$CONTAINER"; exit 1; }
# авторизация должна работать: без ключа 401, с ключом на /send валидация 422
code_noauth=$(curl -s -o /dev/null -w '%{http_code}' -X POST "http://localhost:${PORT}/send" -H 'Content-Type: application/json' -d '{}')
code_auth=$(curl -s -o /dev/null -w '%{http_code}' -X POST "http://localhost:${PORT}/send" -H "X-API-Key: $API_KEY" -H 'Content-Type: application/json' -d '{}')
echo "  /send без ключа=$code_noauth (ждём 401), с ключом=$code_auth (ждём 422)"

RELAY_IP="$(curl -fsS -m5 https://api.ipify.org 2>/dev/null || hostname -I | awk '{print $1}')"
log "6. РЕГИСТРАЦИЯ в Laravel (выполнить на проде /var/www/iqot)"
cat <<NEXT
Публичный IP релея: ${RELAY_IP:-<определи вручную>}

  (а) Один релей (текущая схема) — в .env прода:
        EMAILS_MICROSERVICE_URL=http://${RELAY_IP:-<RELAY_IP>}:${PORT}
        EMAILS_MICROSERVICE_API_KEY=${API_KEY}
      затем: php artisan config:clear + рестарт iqot-email-worker@{1,2} и iqot-replies-worker@1

  (б) Пул релеев — см. README «Несколько релеев» (перечень endpoint'ов + выбор в RelayHttpMailer).

rDNS/PTR: запроси у хостера PTR для ${RELAY_IP:-<RELAY_IP>} (нужно для будущего MTA-этапа
и вообще для доставляемости). Проверка: dig -x ${RELAY_IP:-<RELAY_IP>} +short

socat-фолбэк (:465/:2465) на этом релее НЕ нужен — микросервис коннектится к провайдеру
сам. Если оставляешь socat как резерв — открой его порты аналогично (allow только прод).
NEXT

log "ГОТОВО"
