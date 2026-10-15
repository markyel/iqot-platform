#!/usr/bin/env bash
# Исходящий HTTP-прокси для мониторинга цен IQOT (EgressPool) — запускать НА хосте, root.
# Хосты: релеи рассылки (45.146.167.20, 155.212.185.101, 45.143.93.23 — RUVDS), хостинг liftpages (45.12.239.216).
#
# tinyproxy :3128, ОТДЕЛЬНО от почтового контейнера (падение/нагрузка прокси не задевает
# /send). Доступ — только с IP прода, два слоя: Allow в tinyproxy + firewall (ufw, если
# активен, иначе iptables INPUT с persist). Без логина: Chrome (headless-рендер) не умеет
# передать креды прокси флагом --proxy-server. Служебные заголовки Via/X-Tinyproxy не шлём —
# сайт видит обычный браузерный запрос с IP хоста.
#
#   export PROD_IP='217.26.31.80'
#   export BIND_IP='<доп. IPv4>'   # опц.: выходить с отдельного IP (развязка с почтой)
#   ./provision-egress-proxy.sh
# Регистрация в Laravel — MONITORING_EGRESS (config/monitoring.php), проверка:
#   php artisan monitoring:egress --probe
set -euo pipefail

PROD_IP="${PROD_IP:-217.26.31.80}"
PORT="${PORT:-3128}"
BIND_IP="${BIND_IP:-}"
MAX_CLIENTS="${MAX_CLIENTS:-64}"

log() { echo "[$(date +%T)] $*"; }

log "1. tinyproxy"
if ! command -v tinyproxy >/dev/null 2>&1; then
  DEBIAN_FRONTEND=noninteractive apt-get update -qq
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq tinyproxy >/dev/null
fi

log "2. конфиг"
[ -f /etc/tinyproxy/tinyproxy.conf.orig ] || cp /etc/tinyproxy/tinyproxy.conf /etc/tinyproxy/tinyproxy.conf.orig
cat > /etc/tinyproxy/tinyproxy.conf <<CONF
# IQOT monitoring egress proxy (deploy/relay/provision-egress-proxy.sh)
User tinyproxy
Group tinyproxy
Port ${PORT}
Timeout 60
MaxClients ${MAX_CLIENTS}
DefaultErrorFile "/usr/share/tinyproxy/default.html"
StatFile "/usr/share/tinyproxy/stats.html"
LogFile "/var/log/tinyproxy/tinyproxy.log"
LogLevel Warning
PidFile "/run/tinyproxy/tinyproxy.pid"
Allow 127.0.0.1
Allow ${PROD_IP}
ConnectPort 443
ConnectPort 80
DisableViaHeader Yes
CONF
if [ -n "$BIND_IP" ]; then
  echo "Bind ${BIND_IP}" >> /etc/tinyproxy/tinyproxy.conf
fi
systemctl enable tinyproxy >/dev/null 2>&1 || true
systemctl restart tinyproxy
sleep 1
systemctl is-active tinyproxy

log "3. firewall — :$PORT только с прода ($PROD_IP)"
if ufw status 2>/dev/null | grep -q '^Status: active'; then
  ufw allow from "$PROD_IP" to any port "$PORT" proto tcp comment 'iqot egress proxy' >/dev/null
  ufw status | grep "$PORT" || true
else
  iptables -C INPUT -p tcp --dport "$PORT" -s "$PROD_IP" -j ACCEPT 2>/dev/null \
    || iptables -I INPUT -p tcp --dport "$PORT" -s "$PROD_IP" -j ACCEPT
  iptables -C INPUT -p tcp --dport "$PORT" -s 127.0.0.1 -j ACCEPT 2>/dev/null \
    || iptables -I INPUT -p tcp --dport "$PORT" -s 127.0.0.1 -j ACCEPT
  iptables -C INPUT -p tcp --dport "$PORT" -j DROP 2>/dev/null \
    || iptables -A INPUT -p tcp --dport "$PORT" -j DROP
  if command -v netfilter-persistent >/dev/null 2>&1; then
    netfilter-persistent save >/dev/null 2>&1 || true
  else
    mkdir -p /etc/iptables && iptables-save > /etc/iptables/rules.v4
  fi
  iptables -S INPUT | grep -- "--dport $PORT" || true
fi

log "4. проверка (локально)"
curl -s -m 15 -x "http://127.0.0.1:${PORT}" -o /dev/null -w "https через прокси → HTTP %{http_code}\n" https://ya.ru/ || true
log "готово. Канал для MONITORING_EGRESS: {\"name\":\"<имя>\",\"proxy\":\"http://<IP хоста>:${PORT}\"}"
