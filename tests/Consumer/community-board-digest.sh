#!/usr/bin/env bash

set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
PROJECT="community-board-digest-${RANDOM}-$$"
FRONTEND_PORT=$((23000 + RANDOM % 500))
BLACKOPS_PORT=$((23500 + RANDOM % 500))
COMPOSE=(
    docker compose
    --project-directory "${ROOT}/examples/community-board"
    --project-name "${PROJECT}"
    -f "${ROOT}/examples/community-board/compose.yaml"
)
CURL=(curl --connect-timeout 3 --max-time 15)
TEMP=$(mktemp -d)
ENVIRONMENT_CREATED=false

cleanup() {
    "${COMPOSE[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
    rm -rf \
        "${ROOT}/examples/community-board/var/build" \
        "${ROOT}/examples/community-board/var/log" \
        "${ROOT}/examples/community-board/var/phpunit" \
        "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/generated" \
        "${ROOT}/examples/community-board/frontend/.svelte-kit" \
        "${ROOT}/examples/community-board/frontend/build"
    if test "${ENVIRONMENT_CREATED}" = true; then
        rm -f "${ROOT}/examples/community-board/.env"
    fi
    rm -rf "${TEMP}"
}
trap cleanup EXIT
trap 'printf "Digest journey failed at line %s.\n" "${LINENO}" >&2' ERR

assert_key_preserved() {
    local response=$1
    local key=$2
    grep -Fq 'idempotencyKey' "${response}"
    test "$(grep -oF "${key}" "${response}" | wc -l)" -eq 1
}

export FRONTEND_PORT
export BLACKOPS_DEBUG_PORT="${BLACKOPS_PORT}"
export FRONTEND_ORIGIN="http://localhost:${FRONTEND_PORT}"
export SESSION_COOKIE_SECURE=false
export DIGEST_FAIL_FIRST_ATTEMPT=true

test -d "${ROOT}/examples/community-board/vendor"
test -d "${ROOT}/examples/community-board/frontend/node_modules"
if test ! -e "${ROOT}/examples/community-board/.env"; then
    ENVIRONMENT_CREATED=true
fi

"${COMPOSE[@]}" run --rm --no-deps app php bin/setup
"${COMPOSE[@]}" up -d postgres
"${COMPOSE[@]}" run --rm app php blackops database:migrate
"${COMPOSE[@]}" run --rm app php blackops build:compile
"${COMPOSE[@]}" run --rm app php blackops frontend:generate
"${COMPOSE[@]}" run --rm app php blackops frontend:check
"${COMPOSE[@]}" run --rm app vendor/bin/phpunit
mise exec -- pnpm --dir "${ROOT}/examples/community-board/frontend" run check
mise exec -- pnpm --dir "${ROOT}/examples/community-board/frontend" run test
mise exec -- pnpm --dir "${ROOT}/examples/community-board/frontend" run build

"${COMPOSE[@]}" up -d http frontend
for _ in $(seq 1 30); do
    if "${CURL[@]}" --fail --silent "http://localhost:${FRONTEND_PORT}/register" >"${TEMP}/register.html"; then
        break
    fi
    sleep 1
done
grep -Fq '<title>Register | BlackOps Board</title>' "${TEMP}/register.html"

PASSWORD_MARKER="digest-password-${RANDOM}-${RANDOM}-long"
POST_MARKER="digest-post-content-${RANDOM}"
COMMENT_MARKER="digest-comment-content-${RANDOM}"
ALICE_COOKIES="${TEMP}/alice-cookies"
BOB_COOKIES="${TEMP}/bob-cookies"

register_user() {
    local email=$1
    local display_name=$2
    local cookies=$3
    local output=$4
    "${CURL[@]}" --silent --output "${output}" --cookie-jar "${cookies}" \
        --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
        --data-urlencode "email=${email}" --data-urlencode "displayName=${display_name}" \
        --data-urlencode "password=${PASSWORD_MARKER}" "http://localhost:${FRONTEND_PORT}/register"
    grep -Fq '"type":"redirect","status":303,"location":"/me"' "${output}"
}

ALICE_EMAIL="digest-alice-${RANDOM}-$$@example.test"
BOB_EMAIL="digest-bob-${RANDOM}-$$@example.test"
register_user "${ALICE_EMAIL}" Alice "${ALICE_COOKIES}" "${TEMP}/alice-register.action"
register_user "${BOB_EMAIL}" Bob "${BOB_COOKIES}" "${TEMP}/bob-register.action"
ALICE_TOKEN=$(awk '$6 == "community_board_session" { print $7 }' "${ALICE_COOKIES}")
BOB_TOKEN=$(awk '$6 == "community_board_session" { print $7 }' "${BOB_COOKIES}")
test "${#ALICE_TOKEN}" -eq 43
test "${#BOB_TOKEN}" -eq 43
ALICE_USER_ID=$("${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
    "SELECT id FROM public.board_users WHERE email_canonical = '${ALICE_EMAIL}'")
test "${#ALICE_USER_ID}" -eq 36

"${CURL[@]}" --silent --output "${TEMP}/post.action" --cookie "${ALICE_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode 'title=Digest fixture' --data-urlencode "body=${POST_MARKER}" \
    "http://localhost:${FRONTEND_PORT}/posts/new"
POST_ID=$(sed -n 's/.*"location":"\/posts\/\([0-9a-f-]*\)".*/\1/p' "${TEMP}/post.action")
test "${#POST_ID}" -eq 36
"${CURL[@]}" --silent --output "${TEMP}/comment.action" --write-out '%{http_code}' >"${TEMP}/comment.http-status" --cookie "${BOB_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode "body=${COMMENT_MARKER}" \
    "http://localhost:${FRONTEND_PORT}/posts/${POST_ID}?/comment"
if ! grep -Fq '"type":"redirect","status":303' "${TEMP}/comment.action"; then
    printf 'Digest comment diagnostic (HTTP %s):\n' "$(<"${TEMP}/comment.http-status")" >&2
    sed -n '1,120p' "${TEMP}/comment.action" >&2
    find "${ROOT}/examples/community-board/var/log" -maxdepth 2 -type f -print 2>/dev/null \
        | while read -r log; do
            rg -n -i 'exception|error|sqlstate|transaction|outbox|board\.comment' "${log}" \
                | tail -80 | sed -E 's/[0-9a-f]{32,}/[redacted]/g; s/(Bearer|password)[^ ]*/\1 [redacted]/gi' >&2 || true
        done
    "${COMPOSE[@]}" logs --no-color http frontend >&2 || true
    exit 1
fi

WEEK=$(date -u +%G-W%V)
refresh_digest_key() {
    local page
    page=$(${CURL[@]} --fail --silent --cookie "${ALICE_COOKIES}" "http://localhost:${FRONTEND_PORT}/digests")
    DIGEST_KEY=$(sed -n 's/.*name="idempotencyKey" value="\([0-9a-f]*\)".*/\1/p' <<<"${page}")
    test "${#DIGEST_KEY}" -eq 48
}
refresh_digest_key
K1="${DIGEST_KEY}"
"${CURL[@]}" --silent --output "${TEMP}/invalid.action" --cookie "${ALICE_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode 'week=invalid' --data-urlencode "idempotencyKey=${DIGEST_KEY}" "http://localhost:${FRONTEND_PORT}/digests"
grep -Fq '"type":"failure","status":422' "${TEMP}/invalid.action"
grep -Fq 'Please enter a valid ISO week.' "${TEMP}/invalid.action"
assert_key_preserved "${TEMP}/invalid.action" "${K1}"

start_digest() {
    local output=$1
    local key=${2:-${DIGEST_KEY}}
    "${CURL[@]}" --silent --output "${output}" --write-out '%{http_code}' >"${output}.http-status" --cookie "${ALICE_COOKIES}" \
        --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
        --data-urlencode "week=${WEEK}" --data-urlencode "idempotencyKey=${key}" "http://localhost:${FRONTEND_PORT}/digests"
    test "$(<"${output}.http-status")" = '200'
    grep -Eq '"type":"redirect","status":303,"location":"/digests/operations/[0-9a-f-]{36}"' "${output}"
    sed -n 's/.*"location":"\/digests\/operations\/\([0-9a-f-]*\)".*/\1/p' "${output}"
}

run_to_completion() {
    local operation_id=$1
    "${COMPOSE[@]}" run --rm app php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
    local state
    state=$("${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
        "SELECT state FROM blackops.operations WHERE operation_id = '${operation_id}'::uuid")
    test "${state}" = 'retry_scheduled'
    "${CURL[@]}" --fail --silent --cookie "${ALICE_COOKIES}" \
        "http://localhost:${FRONTEND_PORT}/digests/operations/${operation_id}" >"${TEMP}/${operation_id}-retry.html"
    grep -Fq 'Generation will retry shortly.' "${TEMP}/${operation_id}-retry.html"
    for _ in $(seq 1 50); do
        if test "$("${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
            "SELECT CASE WHEN available_at <= clock_timestamp() THEN 1 ELSE 0 END FROM blackops.operations WHERE operation_id = '${operation_id}'::uuid")" = '1'; then
            break
        fi
        sleep 0.1
    done
    "${COMPOSE[@]}" run --rm app php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
    state=$("${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
        "SELECT state FROM blackops.operations WHERE operation_id = '${operation_id}'::uuid")
    test "${state}" = 'completed'
}

run_backend_to_completion() {
    local operation_id=$1
    "${COMPOSE[@]}" run --rm app php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1 >/dev/null
    test "$("${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
        "SELECT state FROM blackops.operations WHERE operation_id = '${operation_id}'::uuid")" = 'retry_scheduled'
    for _ in $(seq 1 50); do
        if test "$("${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
            "SELECT CASE WHEN available_at <= clock_timestamp() THEN 1 ELSE 0 END FROM blackops.operations WHERE operation_id = '${operation_id}'::uuid")" = '1'; then
            break
        fi
        sleep 0.1
    done
    "${COMPOSE[@]}" run --rm app php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1 >/dev/null
    test "$("${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
        "SELECT state FROM blackops.operations WHERE operation_id = '${operation_id}'::uuid")" = 'completed'
}

FIRST_OPERATION=$(start_digest "${TEMP}/first.action" "${K1}")
test "${#FIRST_OPERATION}" -eq 36
"${CURL[@]}" --fail --silent --cookie "${ALICE_COOKIES}" \
    "http://localhost:${FRONTEND_PORT}/digests/operations/${FIRST_OPERATION}" >"${TEMP}/accepted.html"
grep -Fq 'waiting for a worker' "${TEMP}/accepted.html"
"${CURL[@]}" --fail --silent --cookie "${ALICE_COOKIES}" \
    "http://localhost:${FRONTEND_PORT}/digests/operations/${FIRST_OPERATION}/wait" >"${TEMP}/wait.json"
grep -Fq '"state":"accepted"' "${TEMP}/wait.json"
run_to_completion "${FIRST_OPERATION}"

REPLAY_OPERATION=$(start_digest "${TEMP}/first-replay.action" "${K1}")
test "${REPLAY_OPERATION}" = "${FIRST_OPERATION}"

"${CURL[@]}" --fail --silent --header "Authorization: Bearer ${ALICE_TOKEN}" \
    "http://localhost:${BLACKOPS_PORT}/operations/${FIRST_OPERATION}" >"${TEMP}/first-status.json"
FIRST_DIGEST=$(sed -n 's/.*"digestId":"\([0-9a-f-]*\)".*/\1/p' "${TEMP}/first-status.json")
test "${#FIRST_DIGEST}" -eq 36
"${CURL[@]}" --fail --silent --cookie "${ALICE_COOKIES}" \
    "http://localhost:${FRONTEND_PORT}/digests/${FIRST_DIGEST}" >"${TEMP}/first-detail.html"
grep -Fq "Weekly digest for ${WEEK}: 1 post and 1 comment." "${TEMP}/first-detail.html"

refresh_digest_key
K2="${DIGEST_KEY}"
test "${K2}" != "${K1}"
SECOND_OPERATION=$(start_digest "${TEMP}/second.action" "${K2}")
run_to_completion "${SECOND_OPERATION}"
"${CURL[@]}" --fail --silent --header "Authorization: Bearer ${ALICE_TOKEN}" \
    "http://localhost:${BLACKOPS_PORT}/operations/${SECOND_OPERATION}" >"${TEMP}/second-status.json"
SECOND_DIGEST=$(sed -n 's/.*"digestId":"\([0-9a-f-]*\)".*/\1/p' "${TEMP}/second-status.json")
test "${SECOND_DIGEST}" != "${FIRST_DIGEST}"
test "$("${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
    'SELECT count(*) FROM public.board_digests')" = '2'

OTHER_WEEK=$(date -u -d '7 days ago' +%G-W%V)
test "${OTHER_WEEK}" != "${WEEK}"
"${CURL[@]}" --silent --output "${TEMP}/different-week.action" --cookie "${ALICE_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode "week=${OTHER_WEEK}" --data-urlencode "idempotencyKey=${K1}" "http://localhost:${FRONTEND_PORT}/digests"
grep -Fq '"type":"failure","status":503' "${TEMP}/different-week.action"
grep -Fq 'The digest service is temporarily unavailable.' "${TEMP}/different-week.action"
assert_key_preserved "${TEMP}/different-week.action" "${K1}"
! grep -Fq "${FIRST_OPERATION}" "${TEMP}/different-week.action"

# Stop the backend briefly to prove an unavailable transport preserves K1 in
# the returned form values, then restore it before the actor-scope case.
"${COMPOSE[@]}" stop http >/dev/null
"${CURL[@]}" --silent --output "${TEMP}/transport-unavailable.action" --cookie "${ALICE_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode "week=${WEEK}" --data-urlencode "idempotencyKey=${K1}" "http://localhost:${FRONTEND_PORT}/digests"
grep -Fq '"type":"failure","status":503' "${TEMP}/transport-unavailable.action"
grep -Fq 'The digest service is temporarily unavailable.' "${TEMP}/transport-unavailable.action"
assert_key_preserved "${TEMP}/transport-unavailable.action" "${K1}"
"${COMPOSE[@]}" start http >/dev/null
for _ in $(seq 1 30); do
    if test "$(${CURL[@]} --silent --output /dev/null --write-out '%{http_code}' \
        "http://localhost:${BLACKOPS_PORT}/operations/malformed")" = '404'; then
        break
    fi
    sleep 1
done
test "$(${CURL[@]} --silent --output /dev/null --write-out '%{http_code}' \
    "http://localhost:${BLACKOPS_PORT}/operations/malformed")" = '404'

"${CURL[@]}" --silent --output "${TEMP}/bob-replay.action" --cookie "${BOB_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode "week=${WEEK}" --data-urlencode "idempotencyKey=${K1}" "http://localhost:${FRONTEND_PORT}/digests"
grep -Eq '"type":"redirect","status":303,"location":"/digests/operations/[0-9a-f-]{36}"' "${TEMP}/bob-replay.action"
BOB_OPERATION=$(sed -n 's/.*"location":"\/digests\/operations\/\([0-9a-f-]*\)".*/\1/p' "${TEMP}/bob-replay.action")
test "${#BOB_OPERATION}" -eq 36
test "${BOB_OPERATION}" != "${FIRST_OPERATION}"
run_backend_to_completion "${BOB_OPERATION}"

"${CURL[@]}" --fail --silent --request DELETE --header "Authorization: Bearer ${ALICE_TOKEN}" \
    "http://localhost:${BLACKOPS_PORT}/posts/${POST_ID}" >/dev/null
refresh_digest_key
K3="${DIGEST_KEY}"
test "${K3}" != "${K1}"
test "${K3}" != "${K2}"
THIRD_OPERATION=$(start_digest "${TEMP}/third.action" "${K3}")
run_to_completion "${THIRD_OPERATION}"
"${CURL[@]}" --fail --silent --header "Authorization: Bearer ${ALICE_TOKEN}" \
    "http://localhost:${BLACKOPS_PORT}/operations/${THIRD_OPERATION}" >"${TEMP}/third-status.json"
THIRD_DIGEST=$(sed -n 's/.*"digestId":"\([0-9a-f-]*\)".*/\1/p' "${TEMP}/third-status.json")
"${CURL[@]}" --fail --silent --cookie "${ALICE_COOKIES}" \
    "http://localhost:${FRONTEND_PORT}/digests/${THIRD_DIGEST}" >"${TEMP}/third-detail.html"
grep -Fq "Weekly digest for ${WEEK}: 0 posts and 0 comments." "${TEMP}/third-detail.html"
grep -Fq "Weekly digest for ${WEEK}: 1 post and 1 comment." "${TEMP}/first-detail.html"

for target in \
    "http://localhost:${BLACKOPS_PORT}/operations/${FIRST_OPERATION}" \
    "http://localhost:${BLACKOPS_PORT}/digests/${FIRST_DIGEST}"; do
    code=$("${CURL[@]}" --silent --output "${TEMP}/bob-denied" --write-out '%{http_code}' \
        --header "Authorization: Bearer ${BOB_TOKEN}" "${target}")
    test "${code}" = '404'
done
code=$("${CURL[@]}" --silent --output "${TEMP}/malformed.html" --write-out '%{http_code}' \
    --cookie "${ALICE_COOKIES}" "http://localhost:${FRONTEND_PORT}/digests/operations/malformed")
test "${code}" = '404'

FIRST_EVENTS=$("${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
    "SELECT string_agg(event, ',' ORDER BY sequence) FROM blackops.journal WHERE operation_id = '${FIRST_OPERATION}'::uuid")
test "${FIRST_EVENTS}" = \
    'operation.received,operation.accepted,attempt.started,attempt.failed,attempt.retry_scheduled,attempt.started,attempt.succeeded,operation.completed'
FIRST_ACTORS=$("${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
    "SELECT string_agg(
        sequence::text || ':' ||
        (convert_from(encoded_record, 'UTF8')::jsonb #>> '{operation,actors,origin,id}') || ':' ||
        (convert_from(encoded_record, 'UTF8')::jsonb #>> '{operation,actors,authorization,id}') || ':' ||
        (convert_from(encoded_record, 'UTF8')::jsonb #>> '{operation,actors,execution,id}'),
        ',' ORDER BY sequence
    ) FROM blackops.journal WHERE operation_id = '${FIRST_OPERATION}'::uuid")
test "${FIRST_ACTORS}" = \
    "1:${ALICE_USER_ID}:${ALICE_USER_ID}:${ALICE_USER_ID},2:${ALICE_USER_ID}:${ALICE_USER_ID}:${ALICE_USER_ID},3:${ALICE_USER_ID}:${ALICE_USER_ID}:community-board-worker-1,4:${ALICE_USER_ID}:${ALICE_USER_ID}:community-board-worker-1,5:${ALICE_USER_ID}:${ALICE_USER_ID}:community-board-worker-1,6:${ALICE_USER_ID}:${ALICE_USER_ID}:community-board-worker-1,7:${ALICE_USER_ID}:${ALICE_USER_ID}:community-board-worker-1,8:${ALICE_USER_ID}:${ALICE_USER_ID}:community-board-worker-1"

CLIENT_BUILD="${ROOT}/examples/community-board/frontend/build/client"
for marker in BLACKOPS_BASE_URL DIGEST_FAIL_FIRST_ATTEMPT community_board_session GenerateWeeklyDigest ShowDigest ListNotifications \
    'blackops/generated' 'Authorization' 'Bearer ' "${ALICE_TOKEN}" "${BOB_TOKEN}" "${PASSWORD_MARKER}" "${K1}" "${K2}" "${K3}"; do
    if rg --quiet --fixed-strings -- "${marker}" "${CLIENT_BUILD}"; then
        echo "Digest browser guard found ${marker}." >&2
        exit 1
    fi
done
"${COMPOSE[@]}" logs --no-color frontend >"${TEMP}/frontend.log"
! rg -Fq "${K1}" "${TEMP}/frontend.log"
! rg -Fq "${K2}" "${TEMP}/frontend.log"
! rg -Fq "${K3}" "${TEMP}/frontend.log"
"${COMPOSE[@]}" exec -T postgres pg_dump -U blackops -d community_board >"${TEMP}/database.dump"
! rg -Fq "${K1}" "${TEMP}/database.dump"
! rg -Fq "${K2}" "${TEMP}/database.dump"
! rg -Fq "${K3}" "${TEMP}/database.dump"
for surface in "${TEMP}"/*.html "${TEMP}"/*.action "${TEMP}"/*.json; do
    ! rg -q "${PASSWORD_MARKER}|${ALICE_TOKEN}|${BOB_TOKEN}|http://http|SQLSTATE|/workspace/" "${surface}"
done
! "${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
    "SELECT convert_from(encoded_record, 'UTF8') FROM blackops.journal WHERE operation_id IN ('${FIRST_OPERATION}'::uuid, '${SECOND_OPERATION}'::uuid, '${THIRD_OPERATION}'::uuid)" \
    | rg -Fq "${POST_MARKER}"
! "${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
    "SELECT convert_from(encoded_record, 'UTF8') FROM blackops.journal WHERE operation_id IN ('${FIRST_OPERATION}'::uuid, '${SECOND_OPERATION}'::uuid, '${THIRD_OPERATION}'::uuid)" \
    | rg -Fq "${COMMENT_MARKER}"
! rg -Fq 'DIGEST_FAIL_FIRST_ATTEMPT' "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/generated"

printf 'Community Board digest journey passed.\n'
