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
"${CURL[@]}" --silent --output "${TEMP}/comment.action" --cookie "${BOB_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode "body=${COMMENT_MARKER}" \
    "http://localhost:${FRONTEND_PORT}/posts/${POST_ID}?/comment"
grep -Fq '"type":"redirect","status":303' "${TEMP}/comment.action"

WEEK=$(date -u +%G-W%V)
"${CURL[@]}" --silent --output "${TEMP}/invalid.action" --cookie "${ALICE_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode 'week=invalid' "http://localhost:${FRONTEND_PORT}/digests"
grep -Fq '"type":"failure","status":422' "${TEMP}/invalid.action"
grep -Fq 'Please enter a valid ISO week.' "${TEMP}/invalid.action"

start_digest() {
    local output=$1
    "${CURL[@]}" --silent --output "${output}" --cookie "${ALICE_COOKIES}" \
        --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
        --data-urlencode "week=${WEEK}" "http://localhost:${FRONTEND_PORT}/digests"
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

FIRST_OPERATION=$(start_digest "${TEMP}/first.action")
test "${#FIRST_OPERATION}" -eq 36
"${CURL[@]}" --fail --silent --cookie "${ALICE_COOKIES}" \
    "http://localhost:${FRONTEND_PORT}/digests/operations/${FIRST_OPERATION}" >"${TEMP}/accepted.html"
grep -Fq 'waiting for a worker' "${TEMP}/accepted.html"
"${CURL[@]}" --fail --silent --cookie "${ALICE_COOKIES}" \
    "http://localhost:${FRONTEND_PORT}/digests/operations/${FIRST_OPERATION}/wait" >"${TEMP}/wait.json"
grep -Fq '"state":"accepted"' "${TEMP}/wait.json"
run_to_completion "${FIRST_OPERATION}"

"${CURL[@]}" --fail --silent --header "Authorization: Bearer ${ALICE_TOKEN}" \
    "http://localhost:${BLACKOPS_PORT}/operations/${FIRST_OPERATION}" >"${TEMP}/first-status.json"
FIRST_DIGEST=$(sed -n 's/.*"digestId":"\([0-9a-f-]*\)".*/\1/p' "${TEMP}/first-status.json")
test "${#FIRST_DIGEST}" -eq 36
"${CURL[@]}" --fail --silent --cookie "${ALICE_COOKIES}" \
    "http://localhost:${FRONTEND_PORT}/digests/${FIRST_DIGEST}" >"${TEMP}/first-detail.html"
grep -Fq "Weekly digest for ${WEEK}: 1 post and 1 comment." "${TEMP}/first-detail.html"

SECOND_OPERATION=$(start_digest "${TEMP}/second.action")
run_to_completion "${SECOND_OPERATION}"
"${CURL[@]}" --fail --silent --header "Authorization: Bearer ${ALICE_TOKEN}" \
    "http://localhost:${BLACKOPS_PORT}/operations/${SECOND_OPERATION}" >"${TEMP}/second-status.json"
SECOND_DIGEST=$(sed -n 's/.*"digestId":"\([0-9a-f-]*\)".*/\1/p' "${TEMP}/second-status.json")
test "${SECOND_DIGEST}" != "${FIRST_DIGEST}"
test "$("${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
    'SELECT count(*) FROM public.board_digests')" = '2'

"${CURL[@]}" --fail --silent --request DELETE --header "Authorization: Bearer ${ALICE_TOKEN}" \
    "http://localhost:${BLACKOPS_PORT}/posts/${POST_ID}" >/dev/null
THIRD_OPERATION=$(start_digest "${TEMP}/third.action")
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
for marker in BLACKOPS_BASE_URL DIGEST_FAIL_FIRST_ATTEMPT community_board_session GenerateWeeklyDigest ShowDigest \
    'blackops/generated' 'Authorization' 'Bearer ' "${ALICE_TOKEN}" "${BOB_TOKEN}" "${PASSWORD_MARKER}"; do
    if rg --quiet --fixed-strings -- "${marker}" "${CLIENT_BUILD}"; then
        echo "Digest browser guard found ${marker}." >&2
        exit 1
    fi
done
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
