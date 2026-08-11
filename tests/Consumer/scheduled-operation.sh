#!/usr/bin/env bash

set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
TEMP=$(mktemp -d)
PROJECT="blackops-scheduled-${RANDOM}-$$"
CONSUMER="${TEMP}/consumer"
OVERRIDE="${TEMP}/compose.override.yaml"

cleanup() {
    if [[ "${KEEP_TEMP:-0}" == '1' ]]; then
        printf 'Keeping consumer fixture at %s\n' "${TEMP}" >&2
        return
    fi
    docker compose --project-directory "${CONSUMER}" --project-name "${PROJECT}" \
        -f "${CONSUMER}/compose.yaml" -f "${OVERRIDE}" down --volumes --remove-orphans >/dev/null 2>&1 || true
    rm -rf "${TEMP}"
}
trap cleanup EXIT

mkdir -p "${CONSUMER}"
cp -a "${ROOT}/examples/quickstart/." "${CONSUMER}/"
mkdir -p "${CONSUMER}/app/Feature/Scheduled"
cp -a "${ROOT}/tests/Fixtures/ScheduledOperation/." "${CONSUMER}/app/Feature/Scheduled/"
mkdir -p "${CONSUMER}/app/Feature/Scheduled/InlineProbe" "${CONSUMER}/app/Feature/Scheduled/DeferredProbe"
mv "${CONSUMER}/app/Feature/Scheduled/ScheduledInlineProbe.php" \
    "${CONSUMER}/app/Feature/Scheduled/InlineProbe/"
mv "${CONSUMER}/app/Feature/Scheduled/ScheduledInlineProbeValue.php" \
    "${CONSUMER}/app/Feature/Scheduled/InlineProbe/"
mv "${CONSUMER}/app/Feature/Scheduled/ScheduledInlineProbeOutcome.php" \
    "${CONSUMER}/app/Feature/Scheduled/InlineProbe/"
mv "${CONSUMER}/app/Feature/Scheduled/ScheduledDeferredProbe.php" \
    "${CONSUMER}/app/Feature/Scheduled/DeferredProbe/"
mv "${CONSUMER}/app/Feature/Scheduled/ScheduledDeferredProbeValue.php" \
    "${CONSUMER}/app/Feature/Scheduled/DeferredProbe/"
mv "${CONSUMER}/app/Feature/Scheduled/ScheduledDeferredProbeOutcome.php" \
    "${CONSUMER}/app/Feature/Scheduled/DeferredProbe/"
umask 077
cp "${CONSUMER}/.env.example" "${CONSUMER}/.env"
chmod 600 "${CONSUMER}/.env"
case $- in
    *x*) set +x ;;
esac
storage_key="$(head -c 32 /dev/urandom | base64 -w 0)"
test -n "${storage_key}"
decoded_storage_key_length="$(printf '%s' "${storage_key}" | base64 --decode | wc -c)"
test "${decoded_storage_key_length}" -eq 32
sed -i "s|^BLACKOPS_STORAGE_KEY=.*|BLACKOPS_STORAGE_KEY=${storage_key}|" "${CONSUMER}/.env"
test "$(grep -c '^BLACKOPS_STORAGE_KEY=' "${CONSUMER}/.env")" -eq 1
test "$(grep -c '^BLACKOPS_STORAGE_KEY=$' "${CONSUMER}/.env")" -eq 0
test "$(stat -c '%a' "${CONSUMER}/.env")" = 600
unset storage_key decoded_storage_key_length

cat >"${OVERRIDE}" <<YAML
services:
  app:
    volumes:
      - ${ROOT}:/framework:ro
YAML

compose=(docker compose --project-directory "${CONSUMER}" --project-name "${PROJECT}" \
    -f "${CONSUMER}/compose.yaml" -f "${OVERRIDE}")

docker run --rm -v "${CONSUMER}:/app" -v "${ROOT}:/framework:ro" -w /app composer:2 \
    composer config repositories.framework '{"type":"path","url":"/framework","options":{"symlink":false,"versions":{"blackops/framework":"1.2.0"}}}'

"${compose[@]}" build app >/dev/null
"${compose[@]}" up -d postgres >/dev/null
"${compose[@]}" run --rm app composer install --no-interaction --prefer-dist >/dev/null
"${compose[@]}" run --rm app php blackops database:migrate >/dev/null
"${compose[@]}" run --rm app php blackops build:compile >/dev/null

commands=$("${compose[@]}" run --rm app php blackops list --raw)
grep -Eq '^operation:schedule:run[[:space:]]' <<<"${commands}"
! grep -Eq '^scheduler:run[[:space:]].*application' <<<"${commands}"

if ! "${compose[@]}" run --rm app php blackops operation:schedule:run --json >"${TEMP}/first.json" 2>"${TEMP}/first.err"; then
    cat "${TEMP}/first.err" >&2
    cat "${TEMP}/first.json" >&2
    exit 1
fi
grep -Eq '"schemaVersion":1' "${TEMP}/first.json"
grep -Eq '"status":"ok"' "${TEMP}/first.json"
grep -Eq '"evaluated":2' "${TEMP}/first.json"
grep -Eq '"accepted":2' "${TEMP}/first.json"
grep -Eq '"failed":0' "${TEMP}/first.json"

"${compose[@]}" run --rm app php blackops worker:run --iterations=3 --idle-sleep-milliseconds=1 >/dev/null

completed_inline=$("${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM blackops.schedule_occurrences WHERE schedule_name = 'consumer.inline' AND state = 'completed'")
test "${completed_inline}" -ge 1
completed_deferred=$("${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM blackops.schedule_occurrences WHERE schedule_name = 'consumer.deferred' AND state = 'completed'")
test "${completed_deferred}" -ge 1

inline_operation_id=$("${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT operation_id::text FROM blackops.schedule_occurrences WHERE schedule_name = 'consumer.inline' AND state = 'completed' ORDER BY scheduled_at DESC LIMIT 1")
inline_events=$("${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT string_agg(event, ',' ORDER BY sequence) FROM blackops.journal WHERE operation_id = '${inline_operation_id}'::uuid")
test "${inline_events}" = 'operation.received,attempt.started,attempt.succeeded,operation.completed'

deferred_operation_id=$("${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT operation_id::text FROM blackops.schedule_occurrences WHERE schedule_name = 'consumer.deferred' AND state = 'completed' ORDER BY scheduled_at DESC LIMIT 1")
deferred_events=$("${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT string_agg(event, ',' ORDER BY sequence) FROM blackops.journal WHERE operation_id = '${deferred_operation_id}'::uuid")
test "${deferred_events}" = 'operation.received,operation.accepted,attempt.started,attempt.succeeded,operation.completed'

# A claimed row from a prior slot simulates a process stopping after claim.
crash_operation_id='019fa956-7000-7000-8000-000000000001'
"${compose[@]}" exec -T postgres psql -U blackops -d blackops -v ON_ERROR_STOP=1 -c \
    "INSERT INTO blackops.schedule_occurrences
        (schedule_name, scheduled_at, evaluated_at, state, category, operation_id, accepted_at, created_at, updated_at)
     VALUES ('consumer.inline', date_trunc('minute', now()) - interval '10 minutes', now(), 'claimed', NULL,
        '${crash_operation_id}', NULL, now(), now())" >/dev/null
"${compose[@]}" run --rm app php blackops operation:schedule:run --json >"${TEMP}/recovery.json"
recovered_state=$("${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT state FROM blackops.schedule_occurrences WHERE operation_id = '${crash_operation_id}'::uuid")
test "${recovered_state}" = completed
recovered_events=$("${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT string_agg(event, ',' ORDER BY sequence) FROM blackops.journal WHERE operation_id = '${crash_operation_id}'::uuid")
test "${recovered_events}" = 'operation.received,attempt.started,attempt.succeeded,operation.completed'

# Reset one schedule to the previous minute and run two evaluators concurrently.
"${compose[@]}" exec -T postgres psql -U blackops -d blackops -v ON_ERROR_STOP=1 -c \
    "DELETE FROM blackops.schedule_occurrences WHERE schedule_name = 'consumer.deferred';
     UPDATE blackops.schedule_states SET cursor_at = date_trunc('minute', now()) - interval '1 minute'
     WHERE schedule_name = 'consumer.deferred'" >/dev/null
"${compose[@]}" run --rm app php blackops operation:schedule:run --json >"${TEMP}/race-a.json" &
first_pid=$!
"${compose[@]}" run --rm app php blackops operation:schedule:run --json >"${TEMP}/race-b.json" &
second_pid=$!
wait "${first_pid}"
wait "${second_pid}"
"${compose[@]}" run --rm app php blackops worker:run --iterations=2 --idle-sleep-milliseconds=1 >/dev/null

race_operations=$("${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(DISTINCT operation_id) FROM blackops.schedule_occurrences WHERE schedule_name = 'consumer.deferred' AND operation_id IS NOT NULL")
test "${race_operations}" = 1
race_occurrences=$("${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM blackops.schedule_occurrences WHERE schedule_name = 'consumer.deferred' AND operation_id IS NOT NULL")
test "${race_occurrences}" = 1
race_operation_id=$("${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT operation_id::text FROM blackops.schedule_occurrences WHERE schedule_name = 'consumer.deferred' AND operation_id IS NOT NULL")
race_events=$("${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT string_agg(event, ',' ORDER BY sequence) FROM blackops.journal WHERE operation_id = '${race_operation_id}'::uuid")
test "${race_events}" = 'operation.received,operation.accepted,attempt.started,attempt.succeeded,operation.completed'
duplicate_slots=$("${compose[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM (SELECT schedule_name, scheduled_at FROM blackops.schedule_occurrences WHERE schedule_name = 'consumer.deferred' GROUP BY schedule_name, scheduled_at HAVING count(*) > 1) duplicates")
test "${duplicate_slots}" = 0

printf 'Scheduled operation CLI, recovery, and concurrency journey passed.\n'
