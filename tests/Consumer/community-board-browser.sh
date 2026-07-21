#!/usr/bin/env bash

set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
PROJECT="community-board-browser-${RANDOM}-$$"
FRONTEND_PORT=$((24000 + RANDOM % 500))
BLACKOPS_PORT=$((24500 + RANDOM % 500))
COMPOSE=(
    docker compose
    --project-directory "${ROOT}/examples/community-board"
    --project-name "${PROJECT}"
    -f "${ROOT}/examples/community-board/compose.yaml"
)
CURL=(curl --connect-timeout 3 --max-time 15)
TEMP=$(mktemp -d)
ENVIRONMENT_CREATED=false
CONTROLLER_PID=

cleanup() {
    if test -n "${CONTROLLER_PID}"; then
        kill "${CONTROLLER_PID}" >/dev/null 2>&1 || true
        wait "${CONTROLLER_PID}" >/dev/null 2>&1 || true
    fi
    "${COMPOSE[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
    rm -rf \
        "${ROOT}/examples/community-board/var/build" \
        "${ROOT}/examples/community-board/var/log" \
        "${ROOT}/examples/community-board/var/phpunit" \
        "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/generated" \
        "${ROOT}/examples/community-board/frontend/.svelte-kit" \
        "${ROOT}/examples/community-board/frontend/build" \
        "${ROOT}/examples/community-board/frontend/test-results" \
        "${ROOT}/examples/community-board/frontend/playwright-report"
    if test "${ENVIRONMENT_CREATED}" = true; then
        rm -f "${ROOT}/examples/community-board/.env"
    fi
    rm -rf "${TEMP}"
}
trap cleanup EXIT
trap 'printf "Browser journey failed at line %s.\n" "${LINENO}" >&2' ERR

export FRONTEND_PORT
export BLACKOPS_DEBUG_PORT="${BLACKOPS_PORT}"
export FRONTEND_ORIGIN="http://localhost:${FRONTEND_PORT}"
export SESSION_COOKIE_SECURE=false
export DIGEST_FAIL_FIRST_ATTEMPT=true
export COMMUNITY_BOARD_BASE_URL="${FRONTEND_ORIGIN}"
export COMMUNITY_BOARD_SYNC_DIRECTORY="${TEMP}/sync"
export COMMUNITY_BOARD_SCREENSHOT_PATH="../../../docs/guide/assets/community-board/blackops-board.png"

mkdir -p "${COMMUNITY_BOARD_SYNC_DIRECTORY}"
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
mise exec -- pnpm --dir "${ROOT}/examples/community-board/frontend" run check
mise exec -- pnpm --dir "${ROOT}/examples/community-board/frontend" run test
mise exec -- pnpm --dir "${ROOT}/examples/community-board/frontend" run build

"${COMPOSE[@]}" up -d http frontend
for _ in $(seq 1 30); do
    if "${CURL[@]}" --fail --silent "${FRONTEND_ORIGIN}/" >"${TEMP}/landing.html"; then
        break
    fi
    sleep 1
done
grep -Fq '<title>BlackOps Board</title>' "${TEMP}/landing.html"

wait_for_signal() {
    local signal=$1
    for _ in $(seq 1 3000); do
        test -f "${COMMUNITY_BOARD_SYNC_DIRECTORY}/${signal}" && return 0
        sleep 0.1
    done
    printf 'Timed out waiting for browser signal %s.\n' "${signal}" >&2
    return 1
}

coordinate_digest_worker() {
    wait_for_signal digest-requested
    "${COMPOSE[@]}" run --rm app php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
    wait_for_signal retry-observed
    for _ in $(seq 1 100); do
        if test "$("${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
            "SELECT CASE WHEN available_at <= clock_timestamp() THEN 1 ELSE 0 END FROM blackops.operations ORDER BY created_at DESC LIMIT 1")" = '1'; then
            break
        fi
        sleep 0.1
    done
    "${COMPOSE[@]}" run --rm app php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
}

coordinate_digest_worker &
CONTROLLER_PID=$!
docker run --rm --init --ipc=host --network host \
    --user "$(id -u):$(id -g)" \
    --volume "${ROOT}:/workspace" \
    --volume "${TEMP}:${TEMP}" \
    --workdir /workspace/examples/community-board/frontend \
    --env HOME=/tmp \
    --env COMMUNITY_BOARD_BASE_URL \
    --env COMMUNITY_BOARD_SYNC_DIRECTORY \
    --env COMMUNITY_BOARD_SCREENSHOT_PATH \
    mcr.microsoft.com/playwright:v1.61.1-noble \
    node_modules/.bin/playwright test
wait "${CONTROLLER_PID}"
CONTROLLER_PID=

test -s "${ROOT}/docs/guide/assets/community-board/blackops-board.png"
printf 'Community Board browser journey passed.\n'
