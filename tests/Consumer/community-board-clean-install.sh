#!/usr/bin/env bash

set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
COMMUNITY_BOARD="${ROOT}/examples/community-board"
PROJECT="community-board-clean-${RANDOM}-$$"
FRONTEND_PORT=$((25000 + RANDOM % 500))
BLACKOPS_PORT=$((25500 + RANDOM % 500))
COMPOSE=(
    docker compose
    --project-directory "${COMMUNITY_BOARD}"
    --project-name "${PROJECT}"
    -f "${COMMUNITY_BOARD}/compose.yaml"
)
CURL=(curl --connect-timeout 3 --max-time 15)
TEMP=$(mktemp -d)
DEMO_EMAIL='ada@blackops.local'
DEMO_PASSWORD='BlackOpsBoardDemo!2026'
DEMO_PASSWORDS=(
    "${DEMO_PASSWORD}"
    'BlackOpsBoardGrace!2026'
    'BlackOpsBoardLinus!2026'
)
DATABASE_PASSWORD="clean-install-db-password-${RANDOM}-$$"
SEED_POST_ID='019b1000-0001-7000-8000-000000000101'

cleanup_artifacts() {
    rm -rf \
        "${COMMUNITY_BOARD}/vendor" \
        "${COMMUNITY_BOARD}/var/build" \
        "${COMMUNITY_BOARD}/var/log" \
        "${COMMUNITY_BOARD}/var/phpunit" \
        "${COMMUNITY_BOARD}/frontend/node_modules" \
        "${COMMUNITY_BOARD}/frontend/src/lib/server/blackops/generated" \
        "${COMMUNITY_BOARD}/frontend/.svelte-kit" \
        "${COMMUNITY_BOARD}/frontend/build" \
        "${COMMUNITY_BOARD}/frontend/test-results" \
        "${COMMUNITY_BOARD}/frontend/playwright-report"
    rm -f "${COMMUNITY_BOARD}/.env"
}

cleanup() {
    "${COMPOSE[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
    cleanup_artifacts
    rm -rf "${TEMP}"
}
trap cleanup EXIT
trap 'printf "Clean install journey failed at line %s.\n" "${LINENO}" >&2' ERR

assert_absent() {
    local label=$1
    local marker=$2
    shift 2
    if rg --quiet --fixed-strings -- "${marker}" "$@"; then
        printf 'Sensitive marker guard failed for %s.\n' "${label}" >&2
        return 1
    else
        local status=$?
        if test "${status}" -ne 1; then
            printf 'Sensitive marker guard could not inspect %s.\n' "${label}" >&2
            return 2
        fi
    fi
}

export FRONTEND_PORT
export BLACKOPS_DEBUG_PORT="${BLACKOPS_PORT}"
export FRONTEND_ORIGIN="http://localhost:${FRONTEND_PORT}"
export SESSION_COOKIE_SECURE=false
export HOST_UID="$(id -u)"
export HOST_GID="$(id -g)"
export POSTGRES_PASSWORD="${DATABASE_PASSWORD}"

cleanup_artifacts
for absent in \
    .env vendor var/build var/log var/phpunit frontend/node_modules \
    frontend/src/lib/server/blackops/generated frontend/.svelte-kit frontend/build; do
    test ! -e "${COMMUNITY_BOARD}/${absent}"
done

if command -v php >/dev/null 2>&1; then
    (cd "${COMMUNITY_BOARD}" && php bin/setup)
else
    docker run --rm \
        --user "$(id -u):$(id -g)" \
        --volume "${ROOT}:/workspace" \
        --workdir /workspace/examples/community-board \
        php:8.5-cli-bookworm php bin/setup
fi
test -f "${COMMUNITY_BOARD}/.env"
test -d "${COMMUNITY_BOARD}/var/build"
test -d "${COMMUNITY_BOARD}/var/log"

"${COMPOSE[@]}" build app http frontend
"${COMPOSE[@]}" run --rm --no-deps app composer validate --strict
! rg -n "command_discovery|app/Console" "${COMMUNITY_BOARD}/config/app.php"
"${COMPOSE[@]}" run --rm --no-deps app php -r '
$composer = json_decode(file_get_contents("composer.json"), true, 512, JSON_THROW_ON_ERROR);
$required = $composer["require"] ?? [];
foreach (["vlucas/phpdotenv", "nyholm/psr7", "nyholm/psr7-server", "laminas/laminas-httphandlerrunner", "symfony/uid"] as $dependency) {
    if (array_key_exists($dependency, $required)) {
        fwrite(STDERR, "unused runtime dependency remains: {$dependency}\n");
        exit(1);
    }
}
ksort($required);
if (array_keys($required) !== ["blackops/framework", "doctrine/dbal", "doctrine/migrations", "php"]) {
    fwrite(STDERR, "unexpected application direct dependencies\n");
    exit(1);
}
'
grep -Fq -- '->withEnvironmentFile()' "${COMMUNITY_BOARD}/bootstrap/app.php"
! rg -n 'Dotenv\\|Nyholm\\|Laminas\\|Symfony\\Component\\Uid' "${COMMUNITY_BOARD}/bootstrap" "${COMMUNITY_BOARD}/public" "${COMMUNITY_BOARD}/app"
"${COMPOSE[@]}" run --rm --no-deps app \
    composer install --no-interaction --prefer-dist --no-progress
mise exec -- pnpm --dir "${COMMUNITY_BOARD}/frontend" install --frozen-lockfile

"${COMPOSE[@]}" up -d postgres
"${COMPOSE[@]}" run --rm app php blackops build:compile
if PRE_MIGRATION_OUTPUT=$("${COMPOSE[@]}" run --rm app php blackops database:seed 2>&1); then
    PRE_MIGRATION_STATUS=0
else
    PRE_MIGRATION_STATUS=$?
fi
test "${PRE_MIGRATION_STATUS}" -ne 0
grep -Fq 'Database seeding failed.' <<<"${PRE_MIGRATION_OUTPUT}"
! grep -Fq "${DATABASE_PASSWORD}" <<<"${PRE_MIGRATION_OUTPUT}"
for password in "${DEMO_PASSWORDS[@]}"; do
    ! grep -Fq "${password}" <<<"${PRE_MIGRATION_OUTPUT}"
done

MIGRATION_OUTPUT=$("${COMPOSE[@]}" run --rm app php blackops database:migrate)
grep -Fq 'migrations: 6' <<<"${MIGRATION_OUTPUT}"
"${COMPOSE[@]}" run --rm app php blackops build:compile
"${COMPOSE[@]}" run --rm app php blackops frontend:generate
"${COMPOSE[@]}" run --rm app php blackops frontend:check
mise exec -- pnpm --dir "${COMMUNITY_BOARD}/frontend" run check
mise exec -- pnpm --dir "${COMMUNITY_BOARD}/frontend" run test
mise exec -- pnpm --dir "${COMMUNITY_BOARD}/frontend" run build

FIRST_SEED_OUTPUT=$("${COMPOSE[@]}" run --rm app php blackops database:seed)
SECOND_SEED_OUTPUT=$("${COMPOSE[@]}" run --rm app php blackops database:seed)
grep -Fxq 'Database seeding completed.' <<<"${FIRST_SEED_OUTPUT}"
grep -Fxq 'Database seeding completed.' <<<"${SECOND_SEED_OUTPUT}"
for password in "${DEMO_PASSWORDS[@]}"; do
    ! grep -Fq "${password}" <<<"${FIRST_SEED_OUTPUT}${SECOND_SEED_OUTPUT}"
done

SEED_COUNTS=$("${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
    "SELECT
        (SELECT count(*) FROM public.board_users WHERE id::text LIKE '019b1000-0000-%'),
        (SELECT count(*) FROM public.board_posts WHERE id::text LIKE '019b1000-0001-%'),
        (SELECT count(*) FROM public.board_comments WHERE id::text LIKE '019b1000-0002-%'),
        (SELECT count(DISTINCT email_canonical) FROM public.board_users WHERE id::text LIKE '019b1000-0000-%'),
        (SELECT count(*) FROM public.blackops_sessions),
        (SELECT count(*) FROM blackops.operations),
        (SELECT count(*) FROM blackops.journal),
        (SELECT count(*) FROM blackops.outcomes);")
test "${SEED_COUNTS}" = '3|3|4|3|0|0|0|0'
"${COMPOSE[@]}" exec -T postgres pg_dump -U blackops -d community_board --data-only >"${TEMP}/database.sql"
for password in "${DEMO_PASSWORDS[@]}"; do
    assert_absent 'database dump' "${password}" "${TEMP}/database.sql"
done

"${COMPOSE[@]}" up -d http frontend
for _ in $(seq 1 60); do
    if "${CURL[@]}" --fail --silent "${FRONTEND_ORIGIN}/login" >"${TEMP}/login.html"; then
        break
    fi
    sleep 1
done
grep -Fq '<title>Log in | BlackOps Board</title>' "${TEMP}/login.html"

COOKIES="${TEMP}/cookies"
"${CURL[@]}" --silent --output "${TEMP}/login.action" --cookie-jar "${COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" \
    --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode "email=${DEMO_EMAIL}" \
    --data-urlencode "password=${DEMO_PASSWORD}" \
    "${FRONTEND_ORIGIN}/login"
grep -Fq '"type":"redirect","status":303,"location":"/me"' "${TEMP}/login.action"
SESSION_TOKEN=$(awk '$6 == "community_board_session" { print $7 }' "${COOKIES}")
test "${#SESSION_TOKEN}" -eq 43

"${CURL[@]}" --fail --silent --cookie "${COOKIES}" \
    "${FRONTEND_ORIGIN}/posts" >"${TEMP}/feed.html"
grep -Fq '3 posts' "${TEMP}/feed.html"
grep -Fq 'Welcome to BlackOps Board' "${TEMP}/feed.html"
grep -Fq 'How do you keep operations observable?' "${TEMP}/feed.html"
grep -Fq 'Transaction boundary lessons' "${TEMP}/feed.html"

"${CURL[@]}" --fail --silent --cookie "${COOKIES}" \
    "${FRONTEND_ORIGIN}/posts/${SEED_POST_ID}" >"${TEMP}/detail.html"
grep -Fq '<h1>Welcome to BlackOps Board</h1>' "${TEMP}/detail.html"
grep -Fq 'By Ada Lovelace' "${TEMP}/detail.html"
grep -Fq 'The separate application and framework boundaries make the example easy to follow.' \
    "${TEMP}/detail.html"
grep -Fq 'I also like that the browser only talks to the SvelteKit boundary.' "${TEMP}/detail.html"

"${COMPOSE[@]}" logs --no-color >"${TEMP}/containers.log"
for surface in \
    "${TEMP}/login.action" "${TEMP}/feed.html" "${TEMP}/detail.html" \
    "${TEMP}/containers.log" "${COMMUNITY_BOARD}/var/build" "${COMMUNITY_BOARD}/var/log" \
    "${COMMUNITY_BOARD}/frontend/src/lib/server/blackops/generated" \
    "${COMMUNITY_BOARD}/frontend/build/client"; do
    for password in "${DEMO_PASSWORDS[@]}"; do
        assert_absent 'public demo password' "${password}" "${surface}"
    done
    assert_absent 'raw session token' "${SESSION_TOKEN}" "${surface}"
done

test "$("${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
    'SELECT count(*) FROM public.blackops_sessions')" = '1'
if git -C "${ROOT}" ls-files \
    examples/community-board/.env examples/community-board/vendor examples/community-board/var \
    examples/community-board/frontend/node_modules \
    examples/community-board/frontend/src/lib/server/blackops/generated \
    examples/community-board/frontend/.svelte-kit examples/community-board/frontend/build | grep -q .; then
    echo 'Community Board runtime and generated artifacts must not be tracked.' >&2
    exit 1
fi

printf 'Community Board clean install journey passed.\n'
