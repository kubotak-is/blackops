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
cp "${CONSUMER}/.env.example" "${CONSUMER}/.env"

cat >"${INSTALL_OVERRIDE}" <<YAML
services:
  app:
    volumes:
      - ${ROOT}:/framework:ro
YAML

compose=(docker compose --project-directory "${CONSUMER}" --project-name "${PROJECT}" -f "${CONSUMER}/compose.yaml")
install_compose=("${compose[@]}" -f "${INSTALL_OVERRIDE}")

docker run --rm -v "${CONSUMER}:/app" -v "${ROOT}:/framework:ro" -w /app composer:2 \
    composer config repositories.framework '{"type":"path","url":"/framework","options":{"symlink":false,"versions":{"blackops/framework":"1.1.0"}}}'

HTTP_PORT="${PORT}" "${compose[@]}" build app http
HTTP_PORT="${PORT}" "${compose[@]}" up -d postgres
HTTP_PORT="${PORT}" "${install_compose[@]}" run --rm app composer install --no-interaction --prefer-dist
test ! -L "${CONSUMER}/vendor/blackops/framework"
test -f "${CONSUMER}/vendor/blackops/framework/src/Application/Application.php"
! HTTP_PORT="${PORT}" "${compose[@]}" config | grep -q '/framework'
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php -r '
require "/app/vendor/autoload.php";
unset($_ENV["SAMPLE_API_TOKEN"]);
try {
    new App\UserInterface\Http\SampleTokenAuthenticator();
    exit(1);
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== "SAMPLE_API_TOKEN must be configured with a non-empty value.") {
        throw $exception;
    }
}
'

HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops \
    make:operation Smoke/CreateSmoke --type=smoke.create
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops \
    make:migration CreateSmokeTable
test -f "${CONSUMER}/app/Feature/Smoke/CreateSmoke/CreateSmoke.php"
test -f "${CONSUMER}/app/Feature/Smoke/CreateSmoke/CreateSmokeValue.php"
test -f "${CONSUMER}/app/Feature/Smoke/CreateSmoke/CreateSmokeOutcome.php"
test -n "$(find "${CONSUMER}/migrations" -maxdepth 1 -type f -name 'Version*.php' -print -quit)"

operations=$(HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops operation:list)
grep -q 'welcome.show' <<<"${operations}"
grep -q 'report.generate' <<<"${operations}"
grep -q 'order.create' <<<"${operations}"
grep -q 'smoke.create' <<<"${operations}"

HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops build:compile
test -f "${CONSUMER}/var/build/operations.php"
test -f "${CONSUMER}/var/build/http.php"
test -f "${CONSUMER}/var/build/container.php"
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php -r '
$manifest = require "/app/var/build/operations.php";
foreach ($manifest["payload"]["operations"] ?? [] as $operation) {
    if (($operation["typeId"] ?? null) === "order.create"
        && ($operation["transactionConnection"] ?? null) === "app") {
        exit(0);
    }
}
exit(1);
'

schema_before=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM information_schema.schemata WHERE schema_name = 'blackops'")
test "${schema_before}" = "0"
order_table_before=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name IN ('quickstart_orders', 'quickstart_order_commits')")
test "${order_table_before}" = "0"
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops database:status | grep -q 'pending:'
schema_after_status=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM information_schema.schemata WHERE schema_name = 'blackops'")
test "${schema_after_status}" = "0"
order_table_after_status=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name IN ('quickstart_orders', 'quickstart_order_commits')")
test "${order_table_after_status}" = "0"
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops database:migrate

schema_after=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM information_schema.schemata WHERE schema_name = 'blackops'")
test "${schema_after}" = "1"
order_tables_after=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name IN ('quickstart_orders', 'quickstart_order_commits')")
test "${order_tables_after}" = "2"

HTTP_PORT="${PORT}" "${compose[@]}" up -d http
for _ in $(seq 1 30); do
    if curl --fail --silent "http://127.0.0.1:${PORT}/welcome" -H 'X-Sample-Token: local-example' >"${TEMP}/welcome.json"; then
        break
    fi
    sleep 1
done
grep -q '^{"message":"Welcome to BlackOps"}$' "${TEMP}/welcome.json"
! grep -q 'local-example' "${CONSUMER}/var/log/journal.jsonl"
grep -q '\[masked\]' "${CONSUMER}/var/log/journal.jsonl"

order_reference='consumer-order-001'
order_status=$(curl --silent --output "${CONSUMER}/var/order-response.json" --write-out '%{http_code}' \
    -X POST "http://127.0.0.1:${PORT}/orders" \
    -H 'Content-Type: application/json' \
    -H 'X-Sample-Token: local-example' \
    --data "{\"reference\":\"${order_reference}\"}")
test "${order_status}" = "200"
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php -r '
$data = json_decode(file_get_contents("/app/var/order-response.json"), true, 512, JSON_THROW_ON_ERROR);
if ($data !== ["reference" => "consumer-order-001", "status" => "created"]) {
    exit(1);
}
'
order_rows=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM quickstart_orders WHERE reference = '${order_reference}'")
test "${order_rows}" = "1"
order_commit_rows=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM quickstart_order_commits WHERE reference = '${order_reference}'")
test "${order_commit_rows}" = "1"
order_operation_id=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT operation_id FROM blackops.journal WHERE event = 'operation.received' AND convert_from(encoded_record, 'UTF8') LIKE '%${order_reference}%' ORDER BY sequence LIMIT 1")
test -n "${order_operation_id}"
order_events=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT string_agg(event, ',' ORDER BY sequence) FROM blackops.journal WHERE operation_id = '${order_operation_id}'::uuid")
test "${order_events}" = "operation.received,attempt.started,attempt.succeeded,operation.completed"
! grep -q 'local-example' "${CONSUMER}/var/order-response.json"
order_credential_rows=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT (SELECT count(*) FROM quickstart_orders WHERE reference LIKE '%local-example%') + (SELECT count(*) FROM quickstart_order_commits WHERE reference LIKE '%local-example%')")
test "${order_credential_rows}" = "0"

missing_status=$(curl --silent --output "${CONSUMER}/var/missing-token-response.json" --write-out '%{http_code}' \
    "http://127.0.0.1:${PORT}/welcome")
test "${missing_status}" = "401"
missing_operation_id=$(HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php -r '
$data = json_decode(file_get_contents("/app/var/missing-token-response.json"), true, 512, JSON_THROW_ON_ERROR);
$id = $data["operationId"] ?? null;
if (($data["status"] ?? null) !== "rejected"
    || ($data["category"] ?? null) !== "unauthorized"
    || ($data["code"] ?? null) !== "authorization.authentication_required"
    || !is_string($id)
) {
    exit(1);
}
fwrite(STDOUT, $id);
')
test -n "${missing_operation_id}"
missing_events=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT string_agg(event, ',' ORDER BY sequence) FROM blackops.journal WHERE operation_id = '${missing_operation_id}'::uuid")
test "${missing_events}" = "operation.received,attempt.started,operation.rejected"

journal_count_before_invalid=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    'SELECT count(*) FROM blackops.journal')
invalid_status=$(curl --silent --output "${CONSUMER}/var/invalid-token-response.json" --write-out '%{http_code}' \
    -H 'X-Sample-Token: invalid-consumer-token' \
    "http://127.0.0.1:${PORT}/welcome")
test "${invalid_status}" = "401"
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php -r '
$data = json_decode(file_get_contents("/app/var/invalid-token-response.json"), true, 512, JSON_THROW_ON_ERROR);
if ($data !== [
    "status" => "error",
    "category" => "unauthorized",
    "code" => "authentication.invalid_sample_token",
] || array_key_exists("operationId", $data)) {
    exit(1);
}
'
journal_count_after_invalid=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    'SELECT count(*) FROM blackops.journal')
test "${journal_count_after_invalid}" = "${journal_count_before_invalid}"
! grep -q 'invalid-consumer-token' "${CONSUMER}/var/log/journal.jsonl"

validation_recipient='validation-recipient@example.com'
validation_status=$(curl --silent --output "${CONSUMER}/var/validation-response.json" --write-out '%{http_code}' \
    -X POST "http://127.0.0.1:${PORT}/reports" \
    -H 'Content-Type: application/json' \
    -H 'X-Sample-Token: local-example' \
    --data "{\"reportName\":\"\",\"recipientEmail\":\"${validation_recipient}\"}")
test "${validation_status}" = "422"
validation_operation_id=$(HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php -r '
$data = json_decode(file_get_contents("/app/var/validation-response.json"), true, 512, JSON_THROW_ON_ERROR);
$id = $data["operationId"] ?? null;
$violations = $data["violations"] ?? null;
if (($data["status"] ?? null) !== "rejected"
    || ($data["category"] ?? null) !== "validation"
    || ($data["code"] ?? null) !== "validation.failed"
    || !is_string($id)
    || $violations !== [["field" => "reportName", "rule" => "not_blank", "code" => "validation.not_blank"]]
) {
    exit(1);
}
fwrite(STDOUT, $id);
')
test -n "${validation_operation_id}"
! grep -q "${validation_recipient}" "${CONSUMER}/var/validation-response.json"
! grep -q "${validation_recipient}" "${CONSUMER}/var/log/journal.jsonl"
validation_state_rows=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM blackops.operations WHERE operation_id = '${validation_operation_id}'::uuid")
test "${validation_state_rows}" = "0"
validation_events=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT string_agg(event, ',' ORDER BY sequence) FROM blackops.journal WHERE operation_id = '${validation_operation_id}'::uuid")
test "${validation_events}" = "operation.received,operation.rejected"

report_status=$(curl --silent --output "${CONSUMER}/var/report-response.json" --write-out '%{http_code}' \
    -X POST "http://127.0.0.1:${PORT}/reports" \
    -H 'Content-Type: application/json' \
    -H 'X-Sample-Token: local-example' \
    --data '{"reportName":"consumer","recipientEmail":"consumer-report@example.com"}')
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
! grep -q "${operation_id}" "${CONSUMER}/var/log/journal.jsonl"

transport_actors=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT concat(
        convert_from(encoded_context, 'UTF8')::jsonb #>> '{actors,origin,id}', ':',
        convert_from(encoded_context, 'UTF8')::jsonb #>> '{actors,authorization,id}', ':',
        convert_from(encoded_context, 'UTF8')::jsonb #>> '{actors,execution,id}'
    ) FROM blackops.operations WHERE operation_id = '${operation_id}'::uuid")
test "${transport_actors}" = "quickstart-user:quickstart-user:quickstart-user"
transport_payload=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT convert_from(encoded_payload, 'UTF8') FROM blackops.operations WHERE operation_id = '${operation_id}'::uuid")
grep -q 'consumer-report@example.com' <<<"${transport_payload}"
! grep -q 'local-example' <<<"${transport_payload}"

sleep 1
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
state=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT state FROM blackops.operations WHERE operation_id = '${operation_id}'::uuid")
test "${state}" = "retry_scheduled"
sleep 2
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
state=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT state FROM blackops.operations WHERE operation_id = '${operation_id}'::uuid")
test "${state}" = "completed"

canonical_actors=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT string_agg(
        sequence::text || ':' ||
        (convert_from(encoded_record, 'UTF8')::jsonb #>> '{operation,actors,authorization,id}') || ':' ||
        (convert_from(encoded_record, 'UTF8')::jsonb #>> '{operation,actors,execution,id}'),
        ',' ORDER BY sequence
    ) FROM blackops.journal WHERE operation_id = '${operation_id}'::uuid")
test "${canonical_actors}" = \
    "1:quickstart-user:quickstart-user,2:quickstart-user:quickstart-user,3:quickstart-user:quickstart-worker-1,4:quickstart-user:quickstart-worker-1,5:quickstart-user:quickstart-worker-1,6:quickstart-user:quickstart-worker-1,7:quickstart-user:quickstart-worker-1,8:quickstart-user:quickstart-worker-1"
! grep -q "${operation_id}" "${CONSUMER}/var/log/journal.jsonl"

credential_rows=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT
        (SELECT count(*) FROM blackops.operations
            WHERE coalesce(convert_from(encoded_payload, 'UTF8'), '') LIKE '%local-example%'
               OR coalesce(convert_from(encoded_context, 'UTF8'), '') LIKE '%local-example%')
        + (SELECT count(*) FROM blackops.journal
            WHERE convert_from(encoded_record, 'UTF8') LIKE '%local-example%')
        + (SELECT count(*) FROM blackops.outcomes
            WHERE convert_from(encoded_payload, 'UTF8') LIKE '%local-example%')")
test "${credential_rows}" = "0"
! grep -q 'local-example' "${CONSUMER}/var/log/journal.jsonl"
! grep -q 'quickstart-user' "${CONSUMER}/var/log/journal.jsonl"
! grep -q 'quickstart-worker-1' "${CONSUMER}/var/log/journal.jsonl"
! grep -q 'consumer-report@example.com' "${CONSUMER}/var/log/journal.jsonl"
grep -q '"recipientEmail":"\[masked\]"' "${CONSUMER}/var/log/journal.jsonl"
grep -q '"id":"\[masked\]","type":"user"' "${CONSUMER}/var/log/journal.jsonl"

outcome=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM blackops.outcomes WHERE operation_id = '${operation_id}'::uuid AND octet_length(encoded_payload) > 0")
test "${outcome}" = "1"
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops retention:plan | grep -q 'Total:'
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops retention:purge --dry-run | grep -q 'dry run'

test "$(git -C "${ROOT}" status --short -- examples/quickstart)" = "${SOURCE_BEFORE}"
echo "Quickstart consumer E2E passed."
