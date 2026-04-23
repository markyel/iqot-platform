#!/usr/bin/env bash
# E2E тесты Этапа 3. Использование:
#   bash docs/api/test_stage3.sh <iqot_live_...>
#
# ВАЖНО: JSON с кириллицей передаётся через временные файлы, так как
# Git Bash на Windows портит UTF-8 при подстановке переменных через -d "$VAR".

set -u
KEY="${1:-}"
if [[ -z "$KEY" ]]; then
  echo "Usage: $0 <iqot_live_...>"
  exit 1
fi
BASE="https://iqot-platform.test/api/v1"
AUTH="Authorization: Bearer $KEY"
CT="Content-Type: application/json"
DEADLINE=$(date -u -d "+15 days" +%Y-%m-%dT%H:%M:%SZ)
TMPDIR=$(mktemp -d)

write_payload_happy() {
  cat > "$1" <<EOF
{"client_ref":"PO-TEST-001","deadline":"$DEADLINE","items":[{"client_ref":"L-1","name":"Подшипник SKF 6205-2RS","article":"6205-2RS","brand":"SKF","quantity":20,"unit":"шт","client_category":{"code":"EL.REL.01","path":["Запчасти","Электрика","Релейная"]}}]}
EOF
}

write_payload_other() {
  cat > "$1" <<EOF
{"items":[{"name":"Другая позиция","quantity":1,"unit":"шт"}]}
EOF
}

echo "=== 1. ping ==="
curl -sk -H "$AUTH" "$BASE/ping" -w "\nHTTP %{http_code}\n\n"

echo "=== 2. GET /account/balance ==="
curl -sk -H "$AUTH" "$BASE/account/balance" -w "\nHTTP %{http_code}\n\n"

P1="$TMPDIR/p1.json"; write_payload_happy "$P1"
P2="$TMPDIR/p2.json"; write_payload_other "$P2"

echo "=== 3. POST /submissions (happy path) ==="
curl -sk -H "$AUTH" -H "$CT" -H "Idempotency-Key: test-$(date +%s)-a" --data-binary "@$P1" "$BASE/submissions" -w "\nHTTP %{http_code}\n\n"

IDEM="idem-fixed-$(date +%s)"

echo "=== 4a. POST /submissions first call (Idempotency=$IDEM) ==="
curl -sk -H "$AUTH" -H "$CT" -H "Idempotency-Key: $IDEM" --data-binary "@$P1" "$BASE/submissions" -w "\nHTTP %{http_code}\n\n"

echo "=== 4b. POST /submissions replay (same key, same payload -> 200) ==="
curl -sk -H "$AUTH" -H "$CT" -H "Idempotency-Key: $IDEM" --data-binary "@$P1" "$BASE/submissions" -w "\nHTTP %{http_code}\n\n"

echo "=== 5. POST /submissions conflict (same key, different payload -> 409) ==="
curl -sk -H "$AUTH" -H "$CT" -H "Idempotency-Key: $IDEM" --data-binary "@$P2" "$BASE/submissions" -w "\nHTTP %{http_code}\n\n"

echo "=== 6. Валидация: пустые items (400) ==="
curl -sk -H "$AUTH" -H "$CT" -H "Idempotency-Key: bad-empty-$(date +%s)" -d '{"items":[]}' "$BASE/submissions" -w "\nHTTP %{http_code}\n\n"

echo "=== 7. Валидация: deadline за пределами окна (400) ==="
curl -sk -H "$AUTH" -H "$CT" -H "Idempotency-Key: bad-dl-$(date +%s)" -d '{"deadline":"2030-01-01T00:00:00Z","items":[{"name":"X","quantity":1,"unit":"pcs"}]}' "$BASE/submissions" -w "\nHTTP %{http_code}\n\n"

echo "=== 8. GET /account/balance после submission (holds ожидается > 0 если есть сверхлимит) ==="
curl -sk -H "$AUTH" "$BASE/account/balance" -w "\nHTTP %{http_code}\n\n"

rm -rf "$TMPDIR"
