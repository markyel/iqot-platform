#!/usr/bin/env bash
# Обновление кода релея БЕЗ полной пересборки образа (запускать НА релее, root).
#
# Слой поверх текущего образа: копируем только изменённые .py — сборка за секунды,
# без apt/pip и без лишних сотен МБ на диске (релей #1 — 8.7 ГБ диска).
# Контейнер пересоздаётся с --init: без него uvicorn — PID 1 и не пожинает зомби
# healthcheck-curl'ов; на релее #2 их накопилось 4644 → упёрлись в pids.max, контейнер
# не мог создать ни поток, ни процесс («Cannot fork», статус unhealthy). 14.10.2026.
#
# Использование (из каталога с app.py/email_sender.py/email_receiver.py/models.py/pdf_generator.py):
#   TAG=2.2 ./upgrade-relay.sh
# Env-файл /root/ues.env (API_KEY, TZ, PYTHONUNBUFFERED) — если его нет, снимается с
# работающего контейнера.
set -euo pipefail

SRC="${SRC:-$(cd "$(dirname "$0")" && pwd)/microservice}"
CONTAINER="${CONTAINER:-universal-email-service}"
BASE_IMAGE="${BASE_IMAGE:-universal_email_service_universal-email-service:latest}"
TAG="${TAG:-$(date +%Y%m%d%H%M)}"
IMAGE="ues:${TAG}"
PORT="${PORT:-8000}"
ENV_FILE="${ENV_FILE:-/root/ues.env}"

log() { echo "[$(date +%T)] $*"; }

log "1. env-файл"
if [ ! -s "$ENV_FILE" ]; then
  umask 077
  docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' "$CONTAINER" \
    | grep -E '^(API_KEY|TZ|PYTHONUNBUFFERED)=' > "$ENV_FILE"
  log "   снят с контейнера → $ENV_FILE"
fi
grep -q '^API_KEY=.\+' "$ENV_FILE" || { echo "нет API_KEY в $ENV_FILE"; exit 1; }

log "2. сборка $IMAGE поверх $BASE_IMAGE"
BUILD=$(mktemp -d)
cp "$SRC"/app.py "$SRC"/email_sender.py "$SRC"/email_receiver.py "$SRC"/models.py "$SRC"/pdf_generator.py "$BUILD"/
cat > "$BUILD/Dockerfile" <<DOCKER
FROM ${BASE_IMAGE}
COPY *.py /app/
DOCKER
docker build -q -t "$IMAGE" "$BUILD" >/dev/null
rm -rf "$BUILD"

log "3. проверка синтаксиса"
docker run --rm --entrypoint python "$IMAGE" -m py_compile /app/app.py /app/email_sender.py /app/email_receiver.py

log "4. пересоздание контейнера (graceful stop 30 с — uvicorn дорабатывает текущие /send)"
docker stop -t 30 "$CONTAINER" >/dev/null 2>&1 || true
docker rm "$CONTAINER" >/dev/null 2>&1 || true
docker run -d --init --name "$CONTAINER" --restart unless-stopped \
  -p "${PORT}:8000" --env-file "$ENV_FILE" \
  --memory 512m --cpus 1.0 \
  --log-opt max-size=10m --log-opt max-file=3 \
  "$IMAGE" >/dev/null

log "5. health + авторизация"
for i in $(seq 1 20); do
  curl -sf -m 3 "http://127.0.0.1:${PORT}/health" >/dev/null && break
  sleep 1
done
curl -sf -m 3 "http://127.0.0.1:${PORT}/health"; echo
code=$(curl -s -o /dev/null -w '%{http_code}' -m 5 -X POST "http://127.0.0.1:${PORT}/test-smtp" -H 'Content-Type: application/json' -d '{}')
echo "без ключа → HTTP $code (ожидается 401)"
docker inspect -f 'init={{.HostConfig.Init}} image={{.Config.Image}}' "$CONTAINER"
log "готово. Откат: BASE_IMAGE-образ остаётся — docker run тем же набором флагов с $BASE_IMAGE"
