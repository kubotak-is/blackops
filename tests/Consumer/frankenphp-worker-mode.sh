#!/usr/bin/env bash

set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
TEMP=$(mktemp -d)
PROJECT="blackops-worker-${RANDOM}-$$"
WORKER_PORT=$((19080 + RANDOM % 500))
CLASSIC_PORT=$((20080 + RANDOM % 500))
CONSUMER="${TEMP}/consumer"
INSTALL_OVERRIDE="${TEMP}/compose.install.yaml"
SOURCE_BEFORE=$(git -C "${ROOT}" status --short -- examples/quickstart)

cleanup() {
    status=$?
    if test -d "${CONSUMER}"; then
        if test "${status}" -ne 0; then
            curl --silent --show-error --include --max-time 5 \
                -H 'X-Sample-Token: worker-diagnostic-token' \
                "http://127.0.0.1:${WORKER_PORT}/welcome" >&2 || true
            docker compose --project-directory "${CONSUMER}" --project-name "${PROJECT}" --profile worker-mode \
                -f "${CONSUMER}/compose.yaml" exec -T --workdir /app http-worker \
                sh -lc 'php -l public/worker.php; ls -la public/worker.php var/build var/log' >&2 || true
            docker compose --project-directory "${CONSUMER}" --project-name "${PROJECT}" --profile worker-mode \
                -f "${CONSUMER}/compose.yaml" logs --no-color http-worker >&2 || true
            if test "${BLACKOPS_E2E_KEEP_FAILED:-0}" = "1"; then
                printf 'Preserved failed worker E2E at %s (project %s).\n' "${CONSUMER}" "${PROJECT}" >&2
                return
            fi
        fi
        docker compose --project-directory "${CONSUMER}" --project-name "${PROJECT}" --profile worker-mode \
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

export HTTP_PORT="${CLASSIC_PORT}"
export WORKER_HTTP_PORT="${WORKER_PORT}"
export FRANKENPHP_MAX_REQUESTS=8
export BLACKOPS_WORKER_BOOT_EVIDENCE_FILE=/app/var/log/worker-boots.log
export BLACKOPS_WORKER_MEMORY_EVIDENCE_FILE=/app/var/log/worker-memory.jsonl

compose=(docker compose --project-directory "${CONSUMER}" --project-name "${PROJECT}" -f "${CONSUMER}/compose.yaml")
install_compose=("${compose[@]}" -f "${INSTALL_OVERRIDE}")

docker run --rm -v "${CONSUMER}:/app" -v "${ROOT}:/framework:ro" -w /app composer:2 \
    composer config repositories.framework '{"type":"path","url":"/framework","options":{"symlink":false,"versions":{"blackops/framework":"1.0.0"}}}'

"${compose[@]}" --profile worker-mode build app http http-worker
"${compose[@]}" up -d postgres
"${install_compose[@]}" run --rm app composer install --no-interaction --prefer-dist
"${compose[@]}" run --rm app php blackops blackops:build:compile
"${compose[@]}" run --rm app php blackops blackops:database:migrate
"${compose[@]}" --profile worker-mode up -d http-worker

for _ in $(seq 1 "${BLACKOPS_E2E_READY_ATTEMPTS:-30}"); do
    if curl --fail --silent --max-time 5 -H 'X-Sample-Token: worker-ready-token' \
        "http://127.0.0.1:${WORKER_PORT}/welcome" >"${TEMP}/ready.json"; then
        break
    fi
    sleep 1
done
grep -q '^{"message":"Welcome to BlackOps"}$' "${TEMP}/ready.json"
test "$(wc -l <"${CONSUMER}/var/log/worker-boots.log")" -ge 1
echo "Worker process bootstrap verified."

journal_before=$(wc -l <"${CONSUMER}/var/log/journal.jsonl")
first_secret="worker-first-${RANDOM}-sensitive"
curl --fail --silent --max-time 5 -H "X-Sample-Token: ${first_secret}" \
    "http://127.0.0.1:${WORKER_PORT}/welcome" >"${TEMP}/first.json"
journal_after=$(wc -l <"${CONSUMER}/var/log/journal.jsonl")
test "${journal_after}" -gt "${journal_before}"
grep -q '^{"message":"Welcome to BlackOps"}$' "${TEMP}/first.json"
echo "Per-request journal flush verified."

rejected_secret="worker-rejected-${RANDOM}-sensitive"
rejected_status=$(curl --silent --max-time 5 --output "${TEMP}/rejected.json" --write-out '%{http_code}' \
    -X POST "http://127.0.0.1:${WORKER_PORT}/reports" \
    -H 'Content-Type: application/json' \
    --data "{\"reportName\":\"\",\"apiToken\":\"${rejected_secret}\"}")
test "${rejected_status}" = "422"
grep -q '"status":"rejected"' "${TEMP}/rejected.json"
curl --fail --silent --max-time 5 -H 'X-Sample-Token: worker-after-rejected-token' \
    "http://127.0.0.1:${WORKER_PORT}/welcome" >"${TEMP}/after-rejected.json"
grep -q '^{"message":"Welcome to BlackOps"}$' "${TEMP}/after-rejected.json"
echo "Rejected request isolation verified."

"${compose[@]}" stop -t 0 postgres
database_failure_status=$(curl --silent --max-time 20 --output "${TEMP}/database-failure.json" --write-out '%{http_code}' \
    -H 'X-Sample-Token: worker-database-failure-token' \
    "http://127.0.0.1:${WORKER_PORT}/welcome")
test "${database_failure_status}" = "500"
grep -q '^{"status":"error","code":"internal_error"}$' "${TEMP}/database-failure.json"
echo "Disconnected database failure verified."
"${compose[@]}" up -d --wait postgres

for _ in $(seq 1 30); do
    if curl --fail --silent --max-time 5 -H 'X-Sample-Token: worker-after-reconnect-token' \
        "http://127.0.0.1:${WORKER_PORT}/welcome" >"${TEMP}/after-reconnect.json"; then
        break
    fi
    sleep 1
done
grep -q '^{"message":"Welcome to BlackOps"}$' "${TEMP}/after-reconnect.json"
echo "Database reconnect verified."

for sequence in $(seq 1 32); do
    secret="worker-loop-${sequence}-${RANDOM}-sensitive"
    curl --fail --silent --max-time 5 -H "X-Sample-Token: ${secret}" \
        "http://127.0.0.1:${WORKER_PORT}/welcome" >"${TEMP}/loop-${sequence}.json"
    grep -q '^{"message":"Welcome to BlackOps"}$' "${TEMP}/loop-${sequence}.json"
    ! grep -Fq "${secret}" "${CONSUMER}/var/log/journal.jsonl"
done
echo "Multi-request isolation verified."

"${compose[@]}" run --rm app php -r '
$boots = file("/app/var/log/worker-boots.log", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$lines = file("/app/var/log/worker-memory.jsonl", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!is_array($boots) || count($boots) < 2 || count($boots) !== count(array_unique($boots))) {
    exit(1);
}
$memory = [];
foreach ($lines ?: [] as $line) {
    $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $boot = $record["bootId"] ?? null;
    $bytes = $record["memoryBytes"] ?? null;
    if (!is_string($boot) || !is_int($bytes) || ($record["environmentRestored"] ?? true) !== false) {
        exit(2);
    }
    $memory[$boot][] = $bytes;
}
if (count($memory) < 2) {
    exit(3);
}
$reusedBoot = false;
$maxRequestsReached = false;
foreach ($memory as $samples) {
    if (count($samples) > 8) {
        exit(4);
    }
    $reusedBoot = $reusedBoot || count($samples) > 1;
    $maxRequestsReached = $maxRequestsReached || count($samples) === 8;
    if (max($samples) - min($samples) > 16 * 1024 * 1024) {
        exit(5);
    }
}
if (!$reusedBoot || !$maxRequestsReached) {
    exit(6);
}
$journal = file("/app/var/log/journal.jsonl", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$operationIds = [];
foreach ($journal ?: [] as $line) {
    $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    if (($record["event"] ?? null) === "operation.received") {
        $operationIds[] = $record["operation"]["id"] ?? null;
    }
}
if (count($operationIds) < 10 || count($operationIds) !== count(array_unique($operationIds))) {
    exit(7);
}
'
echo "Restart and memory bounds verified."

! grep -Fq "${first_secret}" "${CONSUMER}/var/log/journal.jsonl"
! grep -Fq "${rejected_secret}" "${CONSUMER}/var/log/journal.jsonl"
! grep -q 'sensitive' "${CONSUMER}/var/log/worker-memory.jsonl"

"${compose[@]}" up -d http
for _ in $(seq 1 30); do
    if curl --fail --silent --max-time 5 -H 'X-Sample-Token: classic-fallback-token' \
        "http://127.0.0.1:${CLASSIC_PORT}/welcome" >"${TEMP}/classic.json"; then
        break
    fi
    sleep 1
done
grep -q '^{"message":"Welcome to BlackOps"}$' "${TEMP}/classic.json"
echo "Classic fallback verified."

test "$(git -C "${ROOT}" status --short -- examples/quickstart)" = "${SOURCE_BEFORE}"
echo "FrankenPHP worker mode consumer E2E passed."
