#!/usr/bin/env bash

set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
TEMP=$(mktemp -d)
PROJECT="blackops-rotation-${RANDOM}-$$"
CONSUMER="${TEMP}/consumer"
OVERRIDE="${TEMP}/compose.override.yaml"
SCHEMA="blackops_rotation_${RANDOM}_$$"

cleanup() {
    docker compose --project-directory "${CONSUMER}" --project-name "${PROJECT}" \
        -f "${CONSUMER}/compose.yaml" -f "${OVERRIDE}" down --volumes --remove-orphans >/dev/null 2>&1 || true
    rm -rf "${TEMP}"
}
trap cleanup EXIT

mkdir -p "${CONSUMER}"
cp -a "${ROOT}/examples/quickstart/." "${CONSUMER}/"
cp "${CONSUMER}/.env.example" "${CONSUMER}/.env"

case $- in
    *x*) set +x ;;
esac
old_key=$(head -c 32 /dev/urandom | base64 -w 0)
new_key=$(head -c 32 /dev/urandom | base64 -w 0)
test -n "${old_key}" && test -n "${new_key}"
cat >>"${CONSUMER}/.env" <<EOF
BLACKOPS_SCHEMA=${SCHEMA}
BLACKOPS_STORAGE_OLD_KEY=${old_key}
BLACKOPS_STORAGE_NEW_KEY=${new_key}
BLACKOPS_STORAGE_ACTIVE_KEY_ID=old:v1
EOF
unset old_key new_key

# Keep the consumer application unchanged except for its application-owned key provider.
cat >"${CONSUMER}/app/Security/SampleStorageKeyProvider.php" <<'PHP'
<?php

declare(strict_types=1);

namespace App\Security;

use BlackOps\Core\TenantRef;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\StorageProtection\StorageKeyProvider;
use BlackOps\StorageProtection\StoragePurpose;
use InvalidArgumentException;

final readonly class SampleStorageKeyProvider implements StorageKeyProvider
{
    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return $this->key((string) ($_ENV['BLACKOPS_STORAGE_ACTIVE_KEY_ID'] ?? ''), $tenant, $purpose);
    }

    public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        $encoded = match ($keyId) {
            'old:v1' => $_ENV['BLACKOPS_STORAGE_OLD_KEY'] ?? '',
            'new:v1' => $_ENV['BLACKOPS_STORAGE_NEW_KEY'] ?? '',
            default => '',
        };
        $material = base64_decode((string) $encoded, true);
        if (!is_string($material) || strlen($material) !== 32) {
            throw new InvalidArgumentException('storage key fixture is invalid');
        }

        return new StorageKey($keyId, $material);
    }
}
PHP

cat >"${OVERRIDE}" <<YAML
services:
  app:
    volumes:
      - ${ROOT}:/framework:ro
    environment:
      BLACKOPS_STORAGE_OLD_KEY: \${BLACKOPS_STORAGE_OLD_KEY}
      BLACKOPS_STORAGE_NEW_KEY: \${BLACKOPS_STORAGE_NEW_KEY}
      BLACKOPS_STORAGE_ACTIVE_KEY_ID: \${BLACKOPS_STORAGE_ACTIVE_KEY_ID}
YAML

compose=(docker compose --project-directory "${CONSUMER}" --project-name "${PROJECT}" \
    -f "${CONSUMER}/compose.yaml" -f "${OVERRIDE}")

docker run --rm -v "${CONSUMER}:/app" -v "${ROOT}:/framework:ro" -w /app composer:2 \
    composer config repositories.framework '{"type":"path","url":"/framework","options":{"symlink":false,"versions":{"blackops/framework":"1.1.0"}}}'

"${compose[@]}" build app >/dev/null
"${compose[@]}" up -d postgres >/dev/null
"${compose[@]}" run --rm app composer install --no-interaction --prefer-dist >/dev/null
"${compose[@]}" run --rm app php blackops database:migrate >/dev/null
"${compose[@]}" run --rm app php blackops build:compile >/dev/null

psql_exec() {
    "${compose[@]}" exec -T postgres psql -U blackops -d blackops -At -v ON_ERROR_STOP=1 -c "$1"
}

run_cli() {
    "${compose[@]}" run --rm app php blackops "$@"
}

set_active_key() {
    sed -i "s/^BLACKOPS_STORAGE_ACTIVE_KEY_ID=.*/BLACKOPS_STORAGE_ACTIVE_KEY_ID=$1/" "${CONSUMER}/.env"
}

# order:create writes authentic protected journal records through the normal application path.
run_cli order:create --reference="rotation-old-1-${RANDOM}" --json >/dev/null
run_cli order:create --reference="rotation-old-2-${RANDOM}" --json >/dev/null

old_header_hex=424f5044010100066f6c643a7631
new_header_hex=424f5044010100066e65773a7631
mapfile -t old_rows < <(psql_exec "SELECT record_id::text || '|' || encode(encoded_record, 'hex') FROM ${SCHEMA}.journal WHERE substring(encode(encoded_record, 'hex') FROM 1 FOR 28) = '${old_header_hex}' ORDER BY record_id")
test "${#old_rows[@]}" -ge 4
row_one=${old_rows[0]%%|*}
row_two=${old_rows[1]%%|*}

record_bytes() {
    psql_exec "SELECT encode(encoded_record, 'hex') FROM ${SCHEMA}.journal WHERE record_id = '$1'"
}

before_one=$(record_bytes "${row_one}")
before_two=$(record_bytes "${row_two}")
test -n "${before_one}" && test -n "${before_two}"

set_active_key new:v1
plan_output=$(run_cli storage:protection:plan \
    --purpose=journal_record --old-key-id=old:v1 --new-key-id=new:v1 \
    --batch=2 --checkpoint=consumer-plan --json)
grep -Eq '"selected":2' <<<"${plan_output}"
! grep -Eq "${row_one}|${row_two}|payload|tenant|BOPD|cipher|nonce|tag|SQLSTATE|PDO|pg_|Exception|Trace|unavailable-key-material|${SCHEMA}|${old_header_hex}|${new_header_hex}" <<<"${plan_output}"
test "${before_one}" = "$(record_bytes "${row_one}")"
test "${before_two}" = "$(record_bytes "${row_two}")"

# Hold the first selected update long enough for the second process to contend for the checkpoint lock.
psql_exec "CREATE FUNCTION ${SCHEMA}.rotation_sleep_first() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF NEW.record_id = '${row_one}' THEN PERFORM pg_sleep(8); END IF; RETURN NEW; END \$\$"
psql_exec "CREATE TRIGGER rotation_sleep_first BEFORE UPDATE OF encoded_record ON ${SCHEMA}.journal FOR EACH ROW EXECUTE FUNCTION ${SCHEMA}.rotation_sleep_first()"
set +e
"${compose[@]}" run --rm --name "${PROJECT}-rotate-a" app php blackops storage:protection:rotate \
    --purpose=journal_record --old-key-id=old:v1 --new-key-id=new:v1 --batch=1 \
    --checkpoint=consumer-concurrency --actor=consumer --reason=concurrency --confirm --json \
    >"${TEMP}/rotate-a.json" 2>&1 &
rotate_a_pid=$!
set -e
audit_started=0
for _ in $(seq 1 100); do
    audit_started=$(psql_exec "SELECT count(*) FROM ${SCHEMA}.storage_protection_rotation_audits WHERE checkpoint_id = 'consumer-concurrency' AND state = 'started'")
    test "${audit_started}" -ge 1 && break
    sleep 0.1
done
test "${audit_started}" -ge 1
set +e
"${compose[@]}" run --rm --name "${PROJECT}-rotate-b" app php blackops storage:protection:rotate \
    --purpose=journal_record --old-key-id=old:v1 --new-key-id=new:v1 --batch=1 \
    --checkpoint=consumer-concurrency --actor=consumer --reason=concurrency --confirm --json \
    >"${TEMP}/rotate-b.json" 2>&1
rotate_b_status=$?
wait "${rotate_a_pid}"
rotate_a_status=$?
set -e
test "${rotate_a_status}" -eq 0
test "${rotate_b_status}" -eq 1
! grep -E "${row_one}|${row_two}|payload|tenant|BOPD|cipher|nonce|tag|SQLSTATE|PDO|pg_|Exception|Trace|unavailable-key-material|${old_header_hex}" "${TEMP}/rotate-a.json" "${TEMP}/rotate-b.json"
psql_exec "DROP TRIGGER rotation_sleep_first ON ${SCHEMA}.journal"
psql_exec "DROP FUNCTION ${SCHEMA}.rotation_sleep_first()"
test "$(psql_exec "SELECT count(*) FROM ${SCHEMA}.journal WHERE record_id IN ('${row_one}', '${row_two}') AND substring(encode(encoded_record, 'hex') FROM 1 FOR 28) = '${new_header_hex}'")" -eq 1
test "$(psql_exec "SELECT count(*) FROM ${SCHEMA}.journal WHERE record_id IN ('${row_one}', '${row_two}') AND substring(encode(encoded_record, 'hex') FROM 1 FOR 28) = '${old_header_hex}'")" -eq 1

# A named container is killed after the first row commits; the same checkpoint then resumes safely.
mapfile -t remaining_rows < <(psql_exec "SELECT record_id::text || '|' || encode(encoded_record, 'hex') FROM ${SCHEMA}.journal WHERE substring(encode(encoded_record, 'hex') FROM 1 FOR 28) = '${old_header_hex}' ORDER BY record_id")
test "${#remaining_rows[@]}" -ge 2
crash_one=${remaining_rows[0]%%|*}
crash_two=${remaining_rows[1]%%|*}
psql_exec "CREATE FUNCTION ${SCHEMA}.rotation_sleep_second() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF NEW.record_id = '${crash_two}' THEN PERFORM pg_sleep(15); END IF; RETURN NEW; END \$\$"
psql_exec "CREATE TRIGGER rotation_sleep_second BEFORE UPDATE OF encoded_record ON ${SCHEMA}.journal FOR EACH ROW EXECUTE FUNCTION ${SCHEMA}.rotation_sleep_second()"
crash_name="${PROJECT}-rotate-crash"
set +e
"${compose[@]}" run --rm --name "${crash_name}" app php blackops storage:protection:rotate \
    --purpose=journal_record --old-key-id=old:v1 --new-key-id=new:v1 --batch=2 \
    --checkpoint=consumer-crash --actor=consumer --reason=crash --confirm --json \
    >"${TEMP}/crash.json" 2>&1 &
crash_pid=$!
set -e
cursor_seen=0
for _ in $(seq 1 150); do
    cursor=$(psql_exec "SELECT cursor_value FROM ${SCHEMA}.storage_protection_rotation_checkpoints WHERE checkpoint_id = 'consumer-crash'")
    if test "${cursor}" = "${crash_one}"; then
        cursor_seen=1
        break
    fi
    sleep 0.1
done
test "${cursor_seen}" -eq 1
first_after_crash=$(record_bytes "${crash_one}")
test -n "${first_after_crash}"
test "$(psql_exec "SELECT substring(encode(encoded_record, 'hex') FROM 1 FOR 28) FROM ${SCHEMA}.journal WHERE record_id = '${crash_one}'")" = "${new_header_hex}"
docker kill "${crash_name}" >/dev/null
set +e
wait "${crash_pid}"
set -e
test "$(psql_exec "SELECT substring(encode(encoded_record, 'hex') FROM 1 FOR 28) FROM ${SCHEMA}.journal WHERE record_id = '${crash_two}'")" = "${old_header_hex}"
test "$(record_bytes "${crash_one}")" = "${first_after_crash}"
psql_exec "DROP TRIGGER rotation_sleep_second ON ${SCHEMA}.journal"
psql_exec "DROP FUNCTION ${SCHEMA}.rotation_sleep_second()"

resume_output=''
for _ in $(seq 1 20); do
    set +e
    resume_output=$(run_cli storage:protection:rotate \
        --purpose=journal_record --old-key-id=old:v1 --new-key-id=new:v1 --batch=2 \
        --checkpoint=consumer-crash --actor=consumer --reason=resume --confirm --json 2>&1)
    resume_status=$?
    set -e
    test "${resume_status}" -eq 0
    grep -Eq '"status"|"state"' <<<"${resume_output}" || true
    grep -Eq '"state":"(running|complete)"' <<<"${resume_output}"
    grep -q '"state":"running"' <<<"${resume_output}" || break
done
grep -q '"state":"complete"' <<<"${resume_output}"
test "$(psql_exec "SELECT substring(encode(encoded_record, 'hex') FROM 1 FOR 28) FROM ${SCHEMA}.journal WHERE record_id = '${crash_two}'")" = "${new_header_hex}"
test "$(record_bytes "${crash_one}")" = "${first_after_crash}"
interrupted_count=$(psql_exec "SELECT count(*) FROM ${SCHEMA}.storage_protection_rotation_audits WHERE checkpoint_id = 'consumer-crash' AND state = 'failed' AND finished_at IS NOT NULL")
test "${interrupted_count}" -eq 1
interrupted=$(psql_exec "SELECT state || '|' || rotated_count || '|' || failed_count || '|' || failure_fingerprint FROM ${SCHEMA}.storage_protection_rotation_audits WHERE checkpoint_id = 'consumer-crash' AND state = 'failed' AND finished_at IS NOT NULL")
IFS='|' read -r interrupted_state interrupted_rotated interrupted_failed interrupted_fingerprint <<<"${interrupted}"
test "${interrupted_state}" = failed
test "${interrupted_rotated}" -eq 1
test "${interrupted_failed}" -ge 1
[[ "${interrupted_fingerprint}" =~ ^v1:[0-9a-f]{64}$ ]]
checkpoint_state=$(psql_exec "SELECT state FROM ${SCHEMA}.storage_protection_rotation_checkpoints WHERE checkpoint_id = 'consumer-crash'")
test "${checkpoint_state}" = complete
completed_audits=$(psql_exec "SELECT count(*) FROM ${SCHEMA}.storage_protection_rotation_audits WHERE checkpoint_id = 'consumer-crash' AND state = 'complete' AND finished_at IS NOT NULL AND failure_fingerprint IS NULL")
test "${completed_audits}" -ge 1
! grep -E "${row_one}|${row_two}|${crash_one}|${crash_two}|payload|tenant|BOPD|cipher|nonce|tag|SQLSTATE|PDO|pg_|Exception|Trace|unavailable-key-material|${old_header_hex}" <<<"${resume_output}"

printf 'Storage protection rotation consumer journey passed (plan, CAS, crash/resume, redaction).\n'
