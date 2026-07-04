# Релей рассылки — микросервис `universal-email-service`

Генерик SMTP/IMAP-релей (FastAPI, `:8000`). Прод шлёт HTTP `POST /send` на релей с
кредами ящика — релей сам коннектится к SMTP-провайдеру **со своим IP**. Итог: боевой
IP прода (`217.26.31.80`) не светится в отправке, ноль per-domain туннелей (в отличие от
socat, где под каждого провайдера — свой прошитый listener).

Laravel-сторона: `App\Services\Senders\RelayHttpMailer` + ветки в `QueuedEmailSender` /
`OutgoingReplySender`, за флагом `EMAILS_SEND_VIA_MICROSERVICE`. Приём (webklex) — нативный,
релеем не занимаемся (эндпоинт `/receive` есть, но в проде не используется).

> Важно (доставляемость): и микросервис, и socat шлют **через submission провайдера**
> (beget/sprinthost), финальный MTA — провайдер. Диверсификацию отправляющего IP и
> репутацию пула это НЕ лечит — то отдельная задача «свой MTA» (postfix/haraka, direct-MX
> + rDNS/SPF/DKIM). Этот релей защищает боевой IP и убирает per-domain, не более.

## Состав каталога
- `microservice/` — исходники образа (актуальная версия с X-API-Key и кастомными
  заголовками). `docker build -t universal_email_service_universal-email-service:latest microservice/`.
- `provision-relay.sh` — развернуть на новом релее одним прогоном (запускать НА релее, root).

## Авторизация
Все операционные эндпоинты (`/send`, `/receive`, `/test-smtp`, `/test-imap`, `/html-to-pdf*`)
требуют заголовок `X-API-Key`, равный env `API_KEY` контейнера. Fail-closed: без ключа на
сервере — `503`; неверный/пустой ключ — `401`. `/` и `/health` открыты (docker healthcheck).
Ключ в Laravel — `EMAILS_MICROSERVICE_API_KEY`, на релее — `API_KEY` в `/root/ues.env`
(и `microservice/.env` при локальном compose). Оба должны совпадать. Генерация: `openssl rand -hex 32`.

## Развёртывание НОВОГО релея

### 0. Доступ (bootstrap)
Модель доступа: `ssh iqot-prod 'ssh root@<relay> ...'` — мой ключ релеями не принимается,
ходим root-ключом прода. На новом релее один раз добавить публичный ключ прода
(`~/.ssh/id_ed25519` пользователя, от которого ходим) в `root@<relay>:~/.ssh/authorized_keys`.

### 1. Перенести файлы на релей
```bash
# с машины, где лежит репо, через прод как прыжок (или напрямую scp, если есть доступ):
scp -r deploy/relay root@<relay>:/root/relay-provision
```
Быстрый путь БЕЗ сборки — перенести готовый образ с релея #1:
```bash
# на релее #1:
docker save universal_email_service_universal-email-service:latest | gzip > /root/ues.tgz
# скопировать /root/ues.tgz на новый релей, там: gunzip ues.tgz  → /root/ues.tar
```

### 2. Запустить провижн
```bash
# на новом релее:
cd /root/relay-provision
export API_KEY='<тот же ключ, что EMAILS_MICROSERVICE_API_KEY в Laravel>'
export PROD_IP='217.26.31.80'
export IMAGE_TAR=/root/ues.tar        # опц.: загрузить образ вместо сборки
./provision-relay.sh
```
Скрипт: ставит docker → грузит/собирает образ → пишет `/root/ues.env` → `docker run`
(restart unless-stopped, mem 512M) → firewall `DOCKER-USER DROP !PROD_IP dport 8000`
(+persist) → health + проверка авторизации (401 без ключа, 422 с ключом) → печатает
шаги регистрации и публичный IP.

### 3. Зарегистрировать релей в Laravel (на проде `/var/www/iqot`)
Текущая схема — один endpoint:
```
EMAILS_MICROSERVICE_URL=http://<RELAY_IP>:8000
EMAILS_MICROSERVICE_API_KEY=<ключ>
```
Затем `php artisan config:clear` + рестарт `iqot-email-worker@{1,2}`, `iqot-replies-worker@1`
(`queue:restart` их НЕ перезапускает — они долгоживущие; нужен `systemctl restart`).

Обкатка на ОДНОМ ящике перед полным вводом:
```
EMAILS_SEND_VIA_MICROSERVICE=true
EMAILS_MICROSERVICE_SENDER_IDS=<один beget sender_id>   # пусто → ВСЕ ящики
```
Убедился (status=sent, ответ пришёл) — очисти `SENDER_IDS`, чтобы пошли все.
Откат: `EMAILS_SEND_VIA_MICROSERVICE=false` + config:clear + рестарт воркеров → путь socat.

### 4. rDNS/PTR
Запроси у хостера PTR для IP релея (`dig -x <RELAY_IP> +short`). Для форвард-микросервиса
не критично (наружу всё равно уходит через beget), но обязательно для будущего своего MTA.

## Несколько релеев (пул микросервисов) — точка расширения
Сейчас `RelayHttpMailer` бьёт в единственный `EMAILS_MICROSERVICE_URL`. Для пула:
добавить `EMAILS_MICROSERVICE_URLS` (JSON/CSV endpoint'ов) и в `RelayHttpMailer::send()`
выбор узла по routing-ключу (id письма/ответа), взвешенно — как `RelayChannelSelector`
делает для socat-каналов (распределение per-send по всему пулу, а не привязка ящик→IP).
Не включено намеренно: у нас пока микросервис только на релее #1; вводить нетестируемый
мультивыбор рано. Каждый релей регистрируется отдельным endpoint'ом с тем же `API_KEY`
(или своим — тогда ключ на узел).

## Обновление микросервиса на СУЩЕСТВУЮЩЕМ релее
Исходники в `microservice/` — правишь, копируешь на релей в
`/opt/price-quotation-system/services/universal_email_service/`, затем:
```bash
docker build -t universal_email_service_universal-email-service:latest <src-dir>
docker rm -f universal-email-service
docker run -d --name universal-email-service --restart unless-stopped \
  -p 8000:8000 --env-file /root/ues.env --memory 512m --cpus 1.0 \
  universal_email_service_universal-email-service:latest
```
(`docker compose` v2 нет, v1 падает на `ContainerConfig` — только `docker run`.)
`.env`/`.bak_*`/`*copy.py`/`__pycache__` в образ не пекутся (`.dockerignore`).

## Секреты
- `microservice/.env` и `/root/ues.env` (`API_KEY`) — НЕ коммитить (в `.dockerignore`;
  в репо-`.gitignore` держать `deploy/relay/microservice/.env`).
- Ключ хранится в Laravel `.env` прода (`EMAILS_MICROSERVICE_API_KEY`) и на релее.

## Проверка (снаружи vs с прода)
```bash
# с прода — работает:
ssh iqot-prod "curl -s -o /dev/null -w '%{http_code}' http://<RELAY_IP>:8000/health"   # 200
# снаружи (не с прода) :8000 недоступен (DOCKER-USER DROP).
```
