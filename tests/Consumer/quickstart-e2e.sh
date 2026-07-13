#!/usr/bin/env bash

set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
TEMP=$(mktemp -d)
PROJECT="blackops-consumer-${RANDOM}-$$"
PORT=$((18080 + RANDOM % 1000))
CONSUMER="${TEMP}/consumer"
INSTALL_OVERRIDE="${TEMP}/compose.install.yaml"
SOURCE_BEFORE=$(git -C "${ROOT}" status --short -- examples/quickstart)

cleanup() {
    if test -d "${CONSUMER}"; then
        docker compose --project-directory "${CONSUMER}" --project-name "${PROJECT}" \
            -f "${CONSUMER}/compose.yaml" down --volumes --remove-orphans --rmi local >/dev/null 2>&1 || true
    fi
    rm -rf "${TEMP}"
}
trap cleanup EXIT

mkdir -p "${CONSUMER}"
cp -a "${ROOT}/examples/quickstart/." "${CONSUMER}/"

cat >"${INSTALL_OVERRIDE}" <<YAML
services:
  app:
    volumes:
      - ${ROOT}:/framework:ro
YAML

compose=(docker compose --project-directory "${CONSUMER}" --project-name "${PROJECT}" -f "${CONSUMER}/compose.yaml")
install_compose=("${compose[@]}" -f "${INSTALL_OVERRIDE}")

docker run --rm -v "${CONSUMER}:/app" -v "${ROOT}:/framework:ro" -w /app composer:2 \
    composer config repositories.framework '{"type":"path","url":"/framework","options":{"symlink":false,"versions":{"blackops/framework":"1.0.0"}}}'

HTTP_PORT="${PORT}" "${compose[@]}" build app http
HTTP_PORT="${PORT}" "${compose[@]}" up -d postgres
HTTP_PORT="${PORT}" "${install_compose[@]}" run --rm app composer install --no-interaction --prefer-dist
test ! -L "${CONSUMER}/vendor/blackops/framework"
test -f "${CONSUMER}/vendor/blackops/framework/src/Application/Application.php"
! HTTP_PORT="${PORT}" "${compose[@]}" config | grep -q '/framework'

HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php bin/blackops \
    make:operation Smoke/CreateSmoke --type=smoke.create
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php bin/blackops \
    make:migration CreateSmokeTable
test -f "${CONSUMER}/app/Feature/Smoke/CreateSmoke/CreateSmoke.php"
test -f "${CONSUMER}/app/Feature/Smoke/CreateSmoke/CreateSmokeValue.php"
test -f "${CONSUMER}/app/Feature/Smoke/CreateSmoke/CreateSmokeOutcome.php"
test -n "$(find "${CONSUMER}/migrations" -maxdepth 1 -type f -name 'Version*.php' -print -quit)"

operations=$(HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php bin/blackops blackops:operation:list)
grep -q 'welcome.show' <<<"${operations}"
grep -q 'report.generate' <<<"${operations}"
grep -q 'smoke.create' <<<"${operations}"

HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php bin/blackops blackops:build:compile
test -f "${CONSUMER}/var/build/operations.php"
test -f "${CONSUMER}/var/build/http.php"
test -f "${CONSUMER}/var/build/container.php"

schema_before=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM information_schema.schemata WHERE schema_name = 'blackops'")
test "${schema_before}" = "0"
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php bin/blackops blackops:database:status | grep -q 'pending:'
schema_after_status=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM information_schema.schemata WHERE schema_name = 'blackops'")
test "${schema_after_status}" = "0"
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php bin/blackops blackops:database:migrate

schema_after=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM information_schema.schemata WHERE schema_name = 'blackops'")
test "${schema_after}" = "1"

HTTP_PORT="${PORT}" "${compose[@]}" up -d http
for _ in $(seq 1 30); do
    if curl --fail --silent "http://127.0.0.1:${PORT}/welcome" -H 'X-Sample-Token: consumer-sensitive-value' >"${TEMP}/welcome.json"; then
        break
    fi
    sleep 1
done
grep -q '^{"message":"Welcome to BlackOps"}$' "${TEMP}/welcome.json"
! grep -q 'consumer-sensitive-value' "${CONSUMER}/var/log/journal.jsonl"
grep -q '\[masked\]' "${CONSUMER}/var/log/journal.jsonl"

report_status=$(curl --silent --output "${CONSUMER}/var/report-response.json" --write-out '%{http_code}' \
    -X POST "http://127.0.0.1:${PORT}/reports" \
    -H 'Content-Type: application/json' \
    --data '{"reportName":"consumer","apiToken":"consumer-report-value"}')
test "${report_status}" = "202"
operation_id=$(HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php -r '
$data = json_decode(file_get_contents("/app/var/report-response.json"), true, 512, JSON_THROW_ON_ERROR);
$id = $data["operationId"] ?? null;
if (($data["status"] ?? null) !== "accepted" || !is_string($id) || preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/", $id) !== 1) {
    exit(1);
}
fwrite(STDOUT, $id);
')
test -n "${operation_id}"

sleep 1
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php bin/blackops blackops:worker:run --iterations=1 --idle-sleep-milliseconds=1
state=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT state FROM blackops.operations WHERE operation_id = '${operation_id}'::uuid")
test "${state}" = "retry_scheduled"
sleep 2
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php bin/blackops blackops:worker:run --iterations=1 --idle-sleep-milliseconds=1
state=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT state FROM blackops.operations WHERE operation_id = '${operation_id}'::uuid")
test "${state}" = "completed"

outcome=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM blackops.outcomes WHERE operation_id = '${operation_id}'::uuid AND octet_length(encoded_payload) > 0")
test "${outcome}" = "1"
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php bin/blackops blackops:retention:plan | grep -q 'Total:'
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php bin/blackops blackops:retention:purge --dry-run | grep -q 'dry run'

test "$(git -C "${ROOT}" status --short -- examples/quickstart)" = "${SOURCE_BEFORE}"
echo "Quickstart consumer E2E passed."
