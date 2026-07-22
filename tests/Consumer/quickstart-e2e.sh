#!/usr/bin/env bash

set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
TEMP=$(mktemp -d)
PROJECT="blackops-consumer-${RANDOM}-$$"
PORT=$((18080 + RANDOM % 1000))
CONSUMER="${TEMP}/consumer"
INSTALL_OVERRIDE="${TEMP}/compose.install.yaml"
SOURCE_BEFORE=$(git -C "${ROOT}" status --short -- examples/quickstart)
viewer_process=''
viewer_container="${PROJECT}-viewer"
frontend_process=''

cleanup() {
    if test -n "${frontend_process}" && kill -0 "${frontend_process}" 2>/dev/null; then
        kill "${frontend_process}" >/dev/null 2>&1 || true
        wait "${frontend_process}" 2>/dev/null || true
    fi
    if test -n "${viewer_process}" && kill -0 "${viewer_process}" 2>/dev/null; then
        docker stop --time 5 "${viewer_container}" >/dev/null 2>&1 || true
        wait "${viewer_process}" 2>/dev/null || true
    fi
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
cp "${ROOT}/tests/Consumer/fixtures/viewer-request.php" "${CONSUMER}/tests/viewer-request.php"

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
mise exec -- pnpm --dir "${CONSUMER}" install --frozen-lockfile
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
grep -q 'diagnostics.failure.trigger' <<<"${operations}"
grep -q 'smoke.create' <<<"${operations}"

HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops build:compile
test -f "${CONSUMER}/var/build/operations.php"
test -f "${CONSUMER}/var/build/http.php"
test -f "${CONSUMER}/var/build/frontend.php"
test -f "${CONSUMER}/var/build/commands.php"
test -f "${CONSUMER}/var/build/container.php"
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops frontend:generate
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops frontend:check \
    | grep -Fx 'Frontend generated tree is fresh in resources/js/blackops.'
mise exec -- pnpm --dir "${CONSUMER}" run test
test -f "${CONSUMER}/resources/js/blackops/operations/welcome/show-welcome.ts"
test -f "${CONSUMER}/resources/js/blackops/operations/report/generate-report.ts"
test -f "${CONSUMER}/resources/js/blackops/operations/order/create-order.ts"
test -f "${CONSUMER}/resources/js/blackops/operations/diagnostics/failure/trigger-failure.ts"
test -f "${CONSUMER}/resources/js/blackops/index.ts"
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
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php -r '
$manifest = require "/app/var/build/commands.php";
exit(($manifest["schema_version"] ?? null) === 2
    && ($manifest["application_build_id"] ?? null) === "quickstart-local"
    && ($manifest["commands"] ?? null) === []
    && count($manifest["operation_commands"] ?? []) === 1
    && ($manifest["operation_commands"][0]["name"] ?? null) === "order:create" ? 0 : 1);
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
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops build:compile
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops database:seed \
    | grep -Fx 'Database seeding completed.'

console_reference="console-$RANDOM-$$"
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops order:create \
    --reference="${console_reference}" --json > "${CONSUMER}/var/console-order.json"
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php -r '
$data = json_decode(file_get_contents("/app/var/console-order.json"), true, 512, JSON_THROW_ON_ERROR);
if ($data !== [
    "schemaVersion" => 1,
    "status" => "completed",
    "outcome" => ["reference" => $argv[1], "status" => "created"],
]) {
    exit(1);
}
' "${console_reference}"
console_order_count=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM public.quickstart_orders WHERE reference = '${console_reference}'")
test "${console_order_count}" = "1"
console_operation_id=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT operation_id FROM blackops.journal WHERE event = 'operation.received' AND convert_from(encoded_record, 'UTF8') LIKE '%${console_reference}%' ORDER BY sequence LIMIT 1")
test -n "${console_operation_id}"
console_events=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT string_agg(event, ',' ORDER BY sequence) FROM blackops.journal WHERE operation_id = '${console_operation_id}'::uuid")
test "${console_events}" = "operation.received,attempt.started,attempt.succeeded,operation.completed"

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

failure_reference="failure-$RANDOM-$$"
failure_sentinel="sensitive-$RANDOM-$$"
failure_status=$(curl --silent --output "${CONSUMER}/var/failure-response.json" --write-out '%{http_code}' \
    -X POST "http://127.0.0.1:${PORT}/failures" \
    -H 'Content-Type: application/json' \
    -H 'X-Sample-Token: local-example' \
    --data "{\"reference\":\"${failure_reference}\",\"sensitiveNote\":\"${failure_sentinel}\"}")
test "${failure_status}" = "500"
failure_operation_id=$(HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php -r '
$data = json_decode(file_get_contents("/app/var/failure-response.json"), true, 512, JSON_THROW_ON_ERROR);
$id = $data["operationId"] ?? null;
if ($data !== ["status" => "error", "code" => "internal_error", "operationId" => $id]
    || !is_string($id)
    || preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/", $id) !== 1) {
    exit(1);
}
fwrite(STDOUT, $id);
')
test -n "${failure_operation_id}"
failure_events=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT string_agg(event, ',' ORDER BY sequence) FROM blackops.journal WHERE operation_id = '${failure_operation_id}'::uuid")
if test "${failure_events}" != "operation.received,attempt.started,attempt.failed,operation.failed"; then
    HTTP_PORT="${PORT}" "${compose[@]}" logs http >&2
    test ! -f "${CONSUMER}/var/log/application.jsonl" || tail -n 10 "${CONSUMER}/var/log/application.jsonl" >&2
    grep -n -C 8 'TriggerFailure' "${CONSUMER}/var/build/container.php" >&2 || true
    echo "Unexpected failure lifecycle: ${failure_events}" >&2
    exit 1
fi

HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops operation:inspect "${failure_operation_id}" \
    > "${CONSUMER}/var/failure-inspect.txt"
grep -Fxq '  Type: diagnostics.failure.trigger' "${CONSUMER}/var/failure-inspect.txt"
grep -Fxq '  Current: failed' "${CONSUMER}/var/failure-inspect.txt"
grep -Fxq '  Outcome: not_applicable' "${CONSUMER}/var/failure-inspect.txt"
grep -Fxq '  Availability: not_applicable' "${CONSUMER}/var/failure-inspect.txt"
grep -Fxq '  Value: none' "${CONSUMER}/var/failure-inspect.txt"
grep -Fq 'operation.received' "${CONSUMER}/var/failure-inspect.txt"
grep -Fq 'attempt.started' "${CONSUMER}/var/failure-inspect.txt"
grep -Fq 'attempt.failed' "${CONSUMER}/var/failure-inspect.txt"
grep -Fq 'operation.failed' "${CONSUMER}/var/failure-inspect.txt"
grep -Fq '[masked] (user)' "${CONSUMER}/var/failure-inspect.txt"
grep -Fq '"reference":"'"${failure_reference}"'"' "${CONSUMER}/var/failure-inspect.txt"
grep -Fq '"sensitiveNote":"[masked]"' "${CONSUMER}/var/failure-inspect.txt"

HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops operation:inspect "${failure_operation_id}" --json \
    > "${CONSUMER}/var/failure-inspect.json"
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php -r '
$data = json_decode(file_get_contents("/app/var/failure-inspect.json"), true, 512, JSON_THROW_ON_ERROR);
$expectedEvents = ["operation.received", "attempt.started", "attempt.failed", "operation.failed"];
if (($data["schemaVersion"] ?? null) !== 1
    || ($data["status"] ?? null) !== "found"
    || ($data["operation"]["operationId"] ?? null) !== $argv[1]
    || ($data["operation"]["type"] ?? null) !== "diagnostics.failure.trigger"
    || ($data["operation"]["strategy"] ?? null) !== "inline"
    || ($data["operation"]["actors"]["origin"]["id"] ?? null) !== "[masked]"
    || ($data["state"]["current"] ?? null) !== "failed"
    || ($data["state"]["terminal"] ?? null) !== true
    || ($data["availability"]["journal"] ?? null) !== "available"
    || ($data["availability"]["outcome"] ?? null) !== "not_applicable"
    || !array_key_exists("outcome", $data)
    || $data["outcome"] !== null
    || count($data["attempts"] ?? []) !== 1
    || array_column($data["timeline"] ?? [], "event") !== $expectedEvents) {
    exit(1);
}
' "${failure_operation_id}"

viewer_output="${TEMP}/viewer.out"
viewer_error="${TEMP}/viewer.err"
HTTP_PORT="${PORT}" "${compose[@]}" run --rm --no-deps --name "${viewer_container}" app \
    php blackops operation:viewer \
    > "${viewer_output}" 2> "${viewer_error}" &
viewer_process=$!
viewer_url=''
for _ in $(seq 1 30); do
    viewer_url=$(sed -n '1p' "${viewer_output}")
    if [[ "${viewer_url}" == http://127.0.0.1:8082/?token=* ]]; then
        break
    fi
    if ! kill -0 "${viewer_process}" 2>/dev/null; then
        cat "${viewer_error}" >&2
        exit 1
    fi
    sleep 0.2
done
[[ "${viewer_url}" == http://127.0.0.1:8082/?token=* ]]

viewer_request() {
    docker exec -i "${viewer_container}" php /app/tests/viewer-request.php "$@"
}

viewer_request GET "/operations/${failure_operation_id}" > "${CONSUMER}/var/viewer-no-token.http"
grep -Fq 'HTTP/1.1 404 Not Found' "${CONSUMER}/var/viewer-no-token.http"
viewer_request GET "${viewer_url}" > "${TEMP}/viewer-bootstrap.http"
grep -Fq 'HTTP/1.1 303 See Other' "${TEMP}/viewer-bootstrap.http"
grep -Fq 'Location: /' "${TEMP}/viewer-bootstrap.http"
viewer_cookie=$(sed -n 's/^Set-Cookie: \([^;]*\).*/\1/p' "${TEMP}/viewer-bootstrap.http" | tr -d '\r')
test -n "${viewer_cookie}"
rm -f "${viewer_output}" "${TEMP}/viewer-bootstrap.http"

viewer_request GET "/?operationId=${failure_operation_id}" "${viewer_cookie}" \
    > "${CONSUMER}/var/viewer-canonical.http"
grep -Fq 'HTTP/1.1 303 See Other' "${CONSUMER}/var/viewer-canonical.http"
grep -Fq "Location: /operations/${failure_operation_id}" "${CONSUMER}/var/viewer-canonical.http"
viewer_request GET "/operations/${failure_operation_id}" "${viewer_cookie}" \
    > "${CONSUMER}/var/viewer-operation.http"
grep -Fq 'HTTP/1.1 200 OK' "${CONSUMER}/var/viewer-operation.http"
grep -Fq "${failure_operation_id}" "${CONSUMER}/var/viewer-operation.http"
grep -Fq 'diagnostics.failure.trigger' "${CONSUMER}/var/viewer-operation.http"
grep -Fq '<dd>failed</dd>' "${CONSUMER}/var/viewer-operation.http"
grep -Fq 'operation.received' "${CONSUMER}/var/viewer-operation.http"
grep -Fq 'attempt.failed' "${CONSUMER}/var/viewer-operation.http"
grep -Fq '[masked] (user)' "${CONSUMER}/var/viewer-operation.http"
grep -Fq "${failure_reference}" "${CONSUMER}/var/viewer-operation.http"
grep -Fq '[masked]' "${CONSUMER}/var/viewer-operation.http"
viewer_request HEAD "/operations/${failure_operation_id}" "${viewer_cookie}" \
    > "${CONSUMER}/var/viewer-head.http"
grep -Fq 'HTTP/1.1 200 OK' "${CONSUMER}/var/viewer-head.http"
! grep -F '<!doctype html>' "${CONSUMER}/var/viewer-head.http"
viewer_request POST "/operations/${failure_operation_id}" "${viewer_cookie}" \
    > "${CONSUMER}/var/viewer-post.http"
grep -Fq 'HTTP/1.1 405 Method Not Allowed' "${CONSUMER}/var/viewer-post.http"
grep -Fq 'Allow: GET, HEAD' "${CONSUMER}/var/viewer-post.http"

docker stop --time 5 "${viewer_container}" >/dev/null
wait "${viewer_process}" || true
viewer_process=''

HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php -r '
$records = [];
foreach (file("/app/var/log/application.jsonl", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    if (($record["context"]["operation"]["id"] ?? null) === $argv[1]) {
        $records[] = $record;
    }
}
if (count($records) !== 2
    || array_column($records, "message") !== ["Quickstart diagnostics failure requested.", "Operation failed."]
    || ($records[0]["context"]["schemaVersion"] ?? null) !== 1
    || ($records[0]["context"]["kind"] ?? null) !== "application"
    || ($records[1]["context"]["schemaVersion"] ?? null) !== 1
    || ($records[1]["context"]["kind"] ?? null) !== "framework"
    || ($records[0]["context"]["context"]["reference"] ?? null) !== $argv[2]
    || array_key_exists("sensitiveNote", $records[0]["context"]["context"] ?? [])
    || ($records[0]["context"]["operation"]["actors"]["origin"]["id"] ?? null) !== "[masked]"
    || ($records[1]["context"]["context"]["failure"]["classification"] ?? null) !== "internal_error") {
    exit(1);
}
' "${failure_operation_id}" "${failure_reference}"

for artifact in \
    "${CONSUMER}/var/failure-response.json" \
    "${CONSUMER}/var/failure-inspect.txt" \
    "${CONSUMER}/var/failure-inspect.json" \
    "${CONSUMER}/var/viewer-no-token.http" \
    "${CONSUMER}/var/viewer-canonical.http" \
    "${CONSUMER}/var/viewer-operation.http" \
    "${CONSUMER}/var/viewer-head.http" \
    "${CONSUMER}/var/viewer-post.http" \
    "${CONSUMER}/var/log/application.jsonl"; do
    ! grep -Fq 'local-example' "${artifact}"
    ! grep -Fq "${failure_sentinel}" "${artifact}"
    ! grep -Fq 'Intentional quickstart diagnostics failure.' "${artifact}"
    ! grep -Fq 'quickstart-user' "${artifact}"
done

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

frontend_report_secret="frontend-sensitive-report-$RANDOM-$$@example.test"
frontend_failure_secret="frontend-sensitive-failure-$RANDOM-$$"
frontend_raw_error="raw-body-$RANDOM-$$"
BLACKOPS_FRONTEND_BASE_URL="http://127.0.0.1:${PORT}" \
BLACKOPS_FRONTEND_SAMPLE_TOKEN='local-example' \
BLACKOPS_FRONTEND_REPORT_SECRET="${frontend_report_secret}" \
BLACKOPS_FRONTEND_FAILURE_SECRET="${frontend_failure_secret}" \
BLACKOPS_FRONTEND_RAW_ERROR="${frontend_raw_error}" \
    mise exec -- pnpm --dir "${CONSUMER}" run test:http \
    > "${CONSUMER}/var/frontend-result.log" \
    2> "${CONSUMER}/var/frontend-error.log" &
frontend_process=$!

frontend_wait_operation_id=''
for _ in $(seq 1 100); do
    frontend_wait_operation_id=$(sed -n 's/^BLACKOPS_WAIT_STARTED://p' \
        "${CONSUMER}/var/frontend-result.log" | tail -n 1)
    if [[ "${frontend_wait_operation_id}" =~ ^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$ ]]; then
        break
    fi
    if ! kill -0 "${frontend_process}" 2>/dev/null; then
        cat "${CONSUMER}/var/frontend-error.log" >&2
        cat "${CONSUMER}/var/frontend-result.log" >&2
        exit 1
    fi
    sleep 0.1
done
[[ "${frontend_wait_operation_id}" =~ ^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$ ]]

frontend_status_code=$(curl --silent \
    --dump-header "${CONSUMER}/var/frontend-status-accepted.headers" \
    --output "${CONSUMER}/var/frontend-status-accepted.json" \
    --write-out '%{http_code}' \
    "http://127.0.0.1:${PORT}/operations/${frontend_wait_operation_id}" \
    -H 'X-Sample-Token: local-example' \
    -H 'X-Unrelated-Operation-Header: ignored')
test "${frontend_status_code}" = '200'
tr -d '\r' < "${CONSUMER}/var/frontend-status-accepted.headers" \
    | grep -Fiqx 'Cache-Control: private, no-store'
retry_after=$(sed -n 's/^Retry-After: //Ip' "${CONSUMER}/var/frontend-status-accepted.headers" | tr -d '\r')
[[ "${retry_after}" =~ ^[1-9][0-9]*$ ]]
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php -r '
$data = json_decode(file_get_contents("/app/var/frontend-status-accepted.json"), true, 512, JSON_THROW_ON_ERROR);
if (($data["schemaVersion"] ?? null) !== 1
    || ($data["operationId"] ?? null) !== $argv[1]
    || ($data["operationType"] ?? null) !== "report.generate"
    || ($data["state"] ?? null) !== "accepted"
    || array_key_exists("ignored", $data)) {
    exit(1);
}
' "${frontend_wait_operation_id}"

HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops worker:run \
    --iterations=1 --idle-sleep-milliseconds=1
frontend_retry_state=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT state FROM blackops.operations WHERE operation_id = '${frontend_wait_operation_id}'::uuid")
test "${frontend_retry_state}" = 'retry_scheduled'
frontend_retry_status_code=$(curl --silent \
    --dump-header "${CONSUMER}/var/frontend-status-retry.headers" \
    --output "${CONSUMER}/var/frontend-status-retry.json" \
    --write-out '%{http_code}' \
    "http://127.0.0.1:${PORT}/operations/${frontend_wait_operation_id}" \
    -H 'X-Sample-Token: local-example')
if test "${frontend_retry_status_code}" != '200'; then
    cat "${CONSUMER}/var/frontend-status-retry.json" >&2
    exit 1
fi
grep -Fq '"state":"retry_scheduled"' "${CONSUMER}/var/frontend-status-retry.json"
retry_after=$(sed -n 's/^Retry-After: //Ip' "${CONSUMER}/var/frontend-status-retry.headers" | tr -d '\r')
[[ "${retry_after}" =~ ^[1-9][0-9]*$ ]]

frontend_retry_ready='0'
for _ in $(seq 1 50); do
    frontend_retry_ready=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
        "SELECT CASE WHEN available_at <= clock_timestamp() THEN 1 ELSE 0 END FROM blackops.operations WHERE operation_id = '${frontend_wait_operation_id}'::uuid")
    if test "${frontend_retry_ready}" = '1'; then
        break
    fi
    sleep 0.1
done
test "${frontend_retry_ready}" = '1'
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops worker:run \
    --iterations=1 --idle-sleep-milliseconds=1
frontend_completed_state=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT state FROM blackops.operations WHERE operation_id = '${frontend_wait_operation_id}'::uuid")
test "${frontend_completed_state}" = 'completed'

if ! wait "${frontend_process}"; then
    cat "${CONSUMER}/var/frontend-error.log" >&2
    cat "${CONSUMER}/var/frontend-result.log" >&2
    exit 1
fi
frontend_process=''

frontend_timeout_operation_id=$(HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php -r '
$lines = file("/app/var/frontend-result.log", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$data = json_decode((string) end($lines), true, 512, JSON_THROW_ON_ERROR);
$id = $data["timeout"]["input"]["operationId"] ?? null;
if (!is_string($id) || preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/", $id) !== 1) {
    exit(1);
}
fwrite(STDOUT, $id);
')
test -n "${frontend_timeout_operation_id}"
frontend_timeout_state=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT state FROM blackops.operations WHERE operation_id = '${frontend_timeout_operation_id}'::uuid")
test "${frontend_timeout_state}" = 'accepted'

HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops worker:run \
    --iterations=1 --idle-sleep-milliseconds=1
frontend_timeout_retry_state=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT state FROM blackops.operations WHERE operation_id = '${frontend_timeout_operation_id}'::uuid")
test "${frontend_timeout_retry_state}" = 'retry_scheduled'
frontend_timeout_ready='0'
for _ in $(seq 1 50); do
    frontend_timeout_ready=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
        "SELECT CASE WHEN available_at <= clock_timestamp() THEN 1 ELSE 0 END FROM blackops.operations WHERE operation_id = '${frontend_timeout_operation_id}'::uuid")
    if test "${frontend_timeout_ready}" = '1'; then
        break
    fi
    sleep 0.1
done
test "${frontend_timeout_ready}" = '1'
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php blackops worker:run \
    --iterations=1 --idle-sleep-milliseconds=1
frontend_timeout_completed_state=$(HTTP_PORT="${PORT}" "${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT state FROM blackops.operations WHERE operation_id = '${frontend_timeout_operation_id}'::uuid")
test "${frontend_timeout_completed_state}" = 'completed'

terminal_status_code=$(curl --silent \
    --dump-header "${CONSUMER}/var/frontend-status-completed.headers" \
    --output "${CONSUMER}/var/frontend-status-completed.json" \
    --write-out '%{http_code}' \
    "http://127.0.0.1:${PORT}/operations/${frontend_wait_operation_id}" \
    -H 'X-Sample-Token: local-example')
test "${terminal_status_code}" = '200'
tr -d '\r' < "${CONSUMER}/var/frontend-status-completed.headers" \
    | grep -Fiqx 'Cache-Control: private, no-store'
! grep -Fiq '^Retry-After:' "${CONSUMER}/var/frontend-status-completed.headers"
HTTP_PORT="${PORT}" "${compose[@]}" run --rm app php -r '
$data = json_decode(file_get_contents("/app/var/frontend-status-completed.json"), true, 512, JSON_THROW_ON_ERROR);
if (($data["operationId"] ?? null) !== $argv[1]
    || ($data["operationType"] ?? null) !== "report.generate"
    || ($data["state"] ?? null) !== "completed"
    || !is_string($data["outcome"]["reportName"] ?? null)
    || !is_string($data["outcome"]["location"] ?? null)) {
    exit(1);
}
' "${frontend_wait_operation_id}"

for result_kind in completed accepted validation internal transport; do
    grep -Fq "\"kind\":\"${result_kind}\"" "${CONSUMER}/var/frontend-result.log"
done
grep -Fq '"code":"network_error"' "${CONSUMER}/var/frontend-result.log"
grep -Fq '"kind":"completed","status":200' "${CONSUMER}/var/frontend-result.log"
grep -Fq '"code":"poll_timeout"' "${CONSUMER}/var/frontend-result.log"

for forbidden in \
    'local-example' \
    "${frontend_report_secret}" \
    "${frontend_failure_secret}" \
    "${frontend_raw_error}" \
    'Intentional quickstart diagnostics failure.'; do
    ! grep -R -Fq "${forbidden}" "${CONSUMER}/resources/js/blackops"
    ! grep -R -Fq "${forbidden}" "${CONSUMER}/var/build"
    ! grep -Fq "${forbidden}" "${CONSUMER}/var/frontend-result.log"
    ! grep -Fq "${forbidden}" "${CONSUMER}/var/frontend-status-accepted.json"
    ! grep -Fq "${forbidden}" "${CONSUMER}/var/frontend-status-completed.json"
    ! grep -Fq "${forbidden}" "${CONSUMER}/var/log/application.jsonl"
done
! grep -Fq "${frontend_report_secret}" "${CONSUMER}/var/log/journal.jsonl"
! grep -Fq "${frontend_failure_secret}" "${CONSUMER}/var/log/journal.jsonl"
grep -Fq '"recipientEmail":"[masked]"' "${CONSUMER}/var/log/journal.jsonl"
grep -Fq '"sensitiveNote":"[masked]"' "${CONSUMER}/var/log/journal.jsonl"

mise exec -- pnpm --dir "${CONSUMER}" run clean
test ! -d "${CONSUMER}/resources/js/blackops"
test ! -d "${CONSUMER}/.build"

test "$(git -C "${ROOT}" status --short -- examples/quickstart)" = "${SOURCE_BEFORE}"
echo "Quickstart consumer E2E passed."
