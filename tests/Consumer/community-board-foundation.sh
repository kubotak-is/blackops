#!/usr/bin/env bash

set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
PROJECT="community-board-foundation-${RANDOM}-$$"
FRONTEND_PORT=$((19000 + RANDOM % 500))
BLACKOPS_PORT=$((19500 + RANDOM % 500))
COMPOSE=(
    docker compose
    --project-directory "${ROOT}/examples/community-board"
    --project-name "${PROJECT}"
    -f "${ROOT}/examples/community-board/compose.yaml"
)
TEMP=$(mktemp -d)
ENVIRONMENT_CREATED=false

cleanup() {
    "${COMPOSE[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
    rm -rf \
        "${ROOT}/examples/community-board/var/build" \
        "${ROOT}/examples/community-board/var/log" \
        "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/generated" \
        "${ROOT}/examples/community-board/frontend/.svelte-kit" \
        "${ROOT}/examples/community-board/frontend/build"
    if test "${ENVIRONMENT_CREATED}" = true; then
        rm -f "${ROOT}/examples/community-board/.env"
    fi
    rm -rf "${TEMP}"
}
trap cleanup EXIT

export FRONTEND_PORT
export BLACKOPS_DEBUG_PORT="${BLACKOPS_PORT}"

test -f "${ROOT}/examples/community-board/composer.lock"
test -f "${ROOT}/examples/community-board/frontend/pnpm-lock.yaml"
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
    if curl --fail --silent "http://127.0.0.1:${FRONTEND_PORT}/" >"${TEMP}/landing.html"; then
        break
    fi
    sleep 1
done

grep -Fq '<title>BlackOps Board</title>' "${TEMP}/landing.html"
grep -Fq 'Welcome to BlackOps Board' "${TEMP}/landing.html"
grep -Fq 'A server-rendered reference application powered by BlackOps Operations.' "${TEMP}/landing.html"
! grep -Fq 'The board service is temporarily unavailable.' "${TEMP}/landing.html"

"${COMPOSE[@]}" stop http
curl --fail --silent "http://127.0.0.1:${FRONTEND_PORT}/" >"${TEMP}/unavailable.html"
grep -Fq 'The board service is temporarily unavailable.' "${TEMP}/unavailable.html"
! grep -Fq 'http://http' "${TEMP}/unavailable.html"
! grep -Fq 'ECONNREFUSED' "${TEMP}/unavailable.html"

wrapper_imports=$(rg -l "blackops/generated|\./generated" \
    "${ROOT}/examples/community-board/frontend/src" \
    --glob '!lib/server/blackops/generated/**' | sort || true)
expected_wrapper_imports=$(printf '%s\n%s\n%s' \
    "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/board.server.ts" \
    "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/digest.server.ts" \
    "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/operations.server.ts")
test "${wrapper_imports}" = "${expected_wrapper_imports}"

! rg -n 'BLACKOPS_BASE_URL|http://http|POSTGRES_PASSWORD|community-board-local' \
    "${ROOT}/examples/community-board/frontend/build/client"
! rg -n '/home/|/workspace/|ECONNREFUSED|raw-body' \
    "${ROOT}/examples/community-board/frontend/build/client" \
    "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/generated"

git -C "${ROOT}" diff --exit-code -- examples/quickstart
if git -C "${ROOT}" ls-files \
    examples/community-board/.env \
    examples/community-board/vendor \
    examples/community-board/var/build \
    examples/community-board/var/log \
    examples/community-board/frontend/node_modules \
    examples/community-board/frontend/src/lib/server/blackops/generated \
    examples/community-board/frontend/.svelte-kit \
    examples/community-board/frontend/build | grep -q .; then
    echo 'Community Board runtime and generated artifacts must not be tracked.' >&2
    exit 1
fi

printf 'Community Board foundation journey passed.\n'
