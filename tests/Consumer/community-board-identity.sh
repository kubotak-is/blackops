#!/usr/bin/env bash

set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
PROJECT="community-board-identity-${RANDOM}-$$"
FRONTEND_PORT=$((20000 + RANDOM % 500))
BLACKOPS_PORT=$((20500 + RANDOM % 500))
CLASSIC_PORT=$((21000 + RANDOM % 500))
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
    "${COMPOSE[@]}" --profile classic-mode --profile worker down --volumes --remove-orphans >/dev/null 2>&1 || true
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
trap 'printf "Community Board identity journey failed at line %s.\n" "${LINENO}" >&2' ERR

export FRONTEND_PORT
export BLACKOPS_DEBUG_PORT="${BLACKOPS_PORT}"
export BLACKOPS_CLASSIC_DEBUG_PORT="${CLASSIC_PORT}"
export FRONTEND_ORIGIN="http://localhost:${FRONTEND_PORT}"
export SESSION_COOKIE_SECURE=false

test -d "${ROOT}/examples/community-board/vendor"
test -d "${ROOT}/examples/community-board/frontend/node_modules"
IDENTITY_MIGRATION='examples/community-board/migrations/Version20260720023000.php'
test -f "${ROOT}/${IDENTITY_MIGRATION}"
if test "${CI:-}" = 'true'; then
    git -C "${ROOT}" ls-files --error-unmatch "${IDENTITY_MIGRATION}" >/dev/null
elif ! git -C "${ROOT}" ls-files --error-unmatch "${IDENTITY_MIGRATION}" >/dev/null 2>&1; then
    git -C "${ROOT}" ls-files --others --exclude-standard -- "${IDENTITY_MIGRATION}" \
        | grep -Fxq "${IDENTITY_MIGRATION}"
fi
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
    if "${CURL[@]}" --fail --silent "http://localhost:${FRONTEND_PORT}/register" >"${TEMP}/register-page.html" \
        && grep -Fq '<title>Register | BlackOps Board</title>' "${TEMP}/register-page.html"; then
        break
    fi
    sleep 1
done

"${COMPOSE[@]}" --profile classic-mode up -d http-classic
for _ in $(seq 1 30); do
    if "${CURL[@]}" --fail --silent "http://127.0.0.1:${CLASSIC_PORT}/welcome" >"${TEMP}/classic-welcome.json" \
        && grep -Fq 'Welcome to BlackOps Board' "${TEMP}/classic-welcome.json"; then
        break
    fi
    sleep 1
done

"${COMPOSE[@]}" --profile worker up -d worker
for _ in $(seq 1 30); do
    if "${COMPOSE[@]}" --profile worker ps --status running --services | grep -Fxq worker; then
        break
    fi
    sleep 1
done
"${COMPOSE[@]}" --profile worker ps --status running --services | grep -Fxq worker
! "${COMPOSE[@]}" --profile worker logs worker | grep -Fq 'could not open the JSONL journal'

grep -Fq '<title>Register | BlackOps Board</title>' "${TEMP}/register-page.html"
grep -Fq 'type="password"' "${TEMP}/register-page.html"
grep -Fq 'Welcome to BlackOps Board' "${TEMP}/classic-welcome.json"
printf 'Identity runtime is ready.\n'

PASSWORD_MARKER="consumer-password-${RANDOM}-${RANDOM}-long"
EMAIL="identity-${RANDOM}-$$@example.com"
DISPLAY_NAME="Identity Consumer"
COOKIE_JAR="${TEMP}/cookies"

"${CURL[@]}" --silent \
    --output "${TEMP}/csrf-body" \
    --write-out '%{http_code}' \
    --header 'Origin: https://untrusted.example' \
    --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode "email=csrf-${EMAIL}" \
    --data-urlencode "displayName=${DISPLAY_NAME}" \
    --data-urlencode "password=${PASSWORD_MARKER}" \
    "http://localhost:${FRONTEND_PORT}/register" >"${TEMP}/csrf-status"
test "$(<"${TEMP}/csrf-status")" = '403'
! grep -Fq "${PASSWORD_MARKER}" "${TEMP}/csrf-body"

"${CURL[@]}" --silent \
    --output "${TEMP}/register-action.json" \
    --dump-header "${TEMP}/register-headers" \
    --cookie-jar "${COOKIE_JAR}" \
    --header "Origin: ${FRONTEND_ORIGIN}" \
    --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode "email=${EMAIL}" \
    --data-urlencode "displayName=${DISPLAY_NAME}" \
    --data-urlencode "password=${PASSWORD_MARKER}" \
    "http://localhost:${FRONTEND_PORT}/register"

grep -Fq '"type":"redirect","status":303,"location":"/me"' "${TEMP}/register-action.json"
grep -Eiq '^set-cookie: community_board_session=[^;]+;.*Max-Age=28800;.*Path=/;.*HttpOnly;.*SameSite=Strict' "${TEMP}/register-headers"
! grep -Fq "${PASSWORD_MARKER}" "${TEMP}/register-action.json"
REGISTER_TOKEN=$(awk '$6 == "community_board_session" { print $7 }' "${COOKIE_JAR}")
test "${#REGISTER_TOKEN}" -eq 43
! grep -Fq "${REGISTER_TOKEN}" "${TEMP}/register-action.json"

"${CURL[@]}" --fail --silent --cookie "${COOKIE_JAR}" \
    "http://localhost:${FRONTEND_PORT}/me" >"${TEMP}/current-user.html"
grep -Fq "${DISPLAY_NAME}" "${TEMP}/current-user.html"
grep -Fq "${EMAIL}" "${TEMP}/current-user.html"
! grep -Fq "${REGISTER_TOKEN}" "${TEMP}/current-user.html"
! grep -Fq "${PASSWORD_MARKER}" "${TEMP}/current-user.html"
printf 'Registration and current user passed.\n'

"${CURL[@]}" --fail --silent \
    --header "Authorization: Bearer ${REGISTER_TOKEN}" \
    "http://127.0.0.1:${BLACKOPS_PORT}/me" >"${TEMP}/debug-current-user.json"
grep -Fq "\"email\":\"${EMAIL}\"" "${TEMP}/debug-current-user.json"
! grep -Fq "${REGISTER_TOKEN}" "${TEMP}/debug-current-user.json"

"${CURL[@]}" --silent \
    --output "${TEMP}/logout-action.json" \
    --dump-header "${TEMP}/logout-headers" \
    --cookie "${COOKIE_JAR}" \
    --cookie-jar "${COOKIE_JAR}" \
    --header "Origin: ${FRONTEND_ORIGIN}" \
    --header 'Content-Type: application/x-www-form-urlencoded' \
    --data '' \
    "http://localhost:${FRONTEND_PORT}/logout"
grep -Fq '"type":"redirect","status":303,"location":"/login"' "${TEMP}/logout-action.json"
grep -Eiq '^set-cookie: community_board_session=;.*Max-Age=0;.*Path=/;.*HttpOnly;.*SameSite=Strict' "${TEMP}/logout-headers"

"${CURL[@]}" --silent --output "${TEMP}/revoked.json" --write-out '%{http_code}' \
    --header "Authorization: Bearer ${REGISTER_TOKEN}" \
    "http://127.0.0.1:${BLACKOPS_PORT}/me" >"${TEMP}/revoked-status"
test "$(<"${TEMP}/revoked-status")" = '401'
grep -Fq '"code":"authentication.invalid_session"' "${TEMP}/revoked.json"
printf 'Logout revocation passed.\n'

"${CURL[@]}" --silent \
    --output "${TEMP}/login-action.json" \
    --dump-header "${TEMP}/login-headers" \
    --cookie "${COOKIE_JAR}" \
    --cookie-jar "${COOKIE_JAR}" \
    --header "Origin: ${FRONTEND_ORIGIN}" \
    --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode "email=${EMAIL}" \
    --data-urlencode "password=${PASSWORD_MARKER}" \
    "http://localhost:${FRONTEND_PORT}/login"
if ! grep -Fq '"type":"redirect","status":303,"location":"/me"' "${TEMP}/login-action.json"; then
    sed -E 's/[A-Za-z0-9_-]{43}/[masked]/g' "${TEMP}/login-action.json" >&2
    false
fi
LOGIN_TOKEN=$(awk '$6 == "community_board_session" { print $7 }' "${COOKIE_JAR}")
test "${#LOGIN_TOKEN}" -eq 43
test "${LOGIN_TOKEN}" != "${REGISTER_TOKEN}"

"${CURL[@]}" --silent \
    --output "${TEMP}/rotation-action.json" \
    --cookie "${COOKIE_JAR}" \
    --cookie-jar "${COOKIE_JAR}" \
    --header "Origin: ${FRONTEND_ORIGIN}" \
    --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode "email=${EMAIL}" \
    --data-urlencode "password=${PASSWORD_MARKER}" \
    "http://localhost:${FRONTEND_PORT}/login"
ROTATED_TOKEN=$(awk '$6 == "community_board_session" { print $7 }' "${COOKIE_JAR}")
test "${#ROTATED_TOKEN}" -eq 43
test "${ROTATED_TOKEN}" != "${LOGIN_TOKEN}"
! grep -Fq "${ROTATED_TOKEN}" "${TEMP}/rotation-action.json"

"${CURL[@]}" --silent --output "${TEMP}/old-rotation.json" --write-out '%{http_code}' \
    --header "Authorization: Bearer ${LOGIN_TOKEN}" \
    "http://127.0.0.1:${BLACKOPS_PORT}/me" >"${TEMP}/old-rotation-status"
test "$(<"${TEMP}/old-rotation-status")" = '401'

"${CURL[@]}" --fail --silent \
    --header "Authorization: Bearer ${ROTATED_TOKEN}" \
    "http://127.0.0.1:${BLACKOPS_PORT}/me" >"${TEMP}/rotated-current-user.json"
grep -Fq "\"displayName\":\"${DISPLAY_NAME}\"" "${TEMP}/rotated-current-user.json"
printf 'Login rotation passed.\n'

TOKEN_HASH=$(printf '%s' "${ROTATED_TOKEN}" | sha256sum | cut -d ' ' -f 1)
STORED_HASH=$("${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
    "SELECT token_hash FROM public.blackops_sessions WHERE token_hash = '${TOKEN_HASH}'")
test "${STORED_HASH}" = "${TOKEN_HASH}"
test "${STORED_HASH}" != "${ROTATED_TOKEN}"

"${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -v ON_ERROR_STOP=1 -c \
    "UPDATE public.blackops_sessions SET issued_at = now() - interval '2 seconds', expires_at = now() - interval '1 second', last_used_at = now() - interval '2 seconds' WHERE token_hash = '${TOKEN_HASH}'" >/dev/null
"${CURL[@]}" --fail --silent \
    --output "${TEMP}/expired.html" \
    --dump-header "${TEMP}/expired-headers" \
    --cookie "community_board_session=${ROTATED_TOKEN}" \
    "http://localhost:${FRONTEND_PORT}/me"
grep -Fq 'You are not logged in.' "${TEMP}/expired.html"
grep -Eiq '^set-cookie: community_board_session=;.*Max-Age=0;.*Path=/;.*HttpOnly;.*SameSite=Strict' "${TEMP}/expired-headers"
! grep -Fq "${ROTATED_TOKEN}" "${TEMP}/expired.html"
printf 'Expiry and cookie removal passed.\n'

"${CURL[@]}" --silent --output "${TEMP}/classic-invalid.json" --write-out '%{http_code}' \
    --header "Authorization: Bearer ${ROTATED_TOKEN}" \
    "http://127.0.0.1:${CLASSIC_PORT}/me" >"${TEMP}/classic-invalid-status"
test "$(<"${TEMP}/classic-invalid-status")" = '401'
grep -Fq '"code":"authentication.invalid_session"' "${TEMP}/classic-invalid.json"

"${CURL[@]}" --silent \
    --output "${TEMP}/duplicate.json" \
    --dump-header "${TEMP}/duplicate-headers" \
    --header 'Content-Type: application/json' \
    --data "{\"email\":\"${EMAIL}\",\"displayName\":\"${DISPLAY_NAME}\",\"password\":\"${PASSWORD_MARKER}\"}" \
    --write-out '%{http_code}' \
    "http://127.0.0.1:${BLACKOPS_PORT}/auth/register" >"${TEMP}/duplicate-status"
test "$(<"${TEMP}/duplicate-status")" = '409'
grep -Fq '"code":"auth.email_unavailable"' "${TEMP}/duplicate.json"
! grep -Fq "${PASSWORD_MARKER}" "${TEMP}/duplicate.json"

"${CURL[@]}" --silent --output "${TEMP}/malformed.json" --write-out '%{http_code}' \
    --header 'Content-Type: application/json' --data '{' \
    "http://127.0.0.1:${BLACKOPS_PORT}/auth/login" >"${TEMP}/malformed-status"
test "$(<"${TEMP}/malformed-status")" = '400'
grep -Fq '"code":"http.malformed_json"' "${TEMP}/malformed.json"

"${CURL[@]}" --silent --output "${TEMP}/unsupported.json" --write-out '%{http_code}' \
    --header 'Content-Type: application/json' --data '"credentials"' \
    "http://127.0.0.1:${BLACKOPS_PORT}/auth/login" >"${TEMP}/unsupported-status"
test "$(<"${TEMP}/unsupported-status")" = '400'
grep -Fq '"code":"http.body_not_object"' "${TEMP}/unsupported.json"

"${CURL[@]}" --silent --output "${TEMP}/invalid-credentials.json" --write-out '%{http_code}' \
    --header 'Content-Type: application/json' \
    --data '{"email":"unknown@example.com","password":"a sufficiently wrong password"}' \
    "http://127.0.0.1:${BLACKOPS_PORT}/auth/login" >"${TEMP}/invalid-credentials-status"
test "$(<"${TEMP}/invalid-credentials-status")" = '401'
grep -Fq '"code":"auth.invalid_credentials"' "${TEMP}/invalid-credentials.json"
printf 'Authentication HTTP failure contract passed.\n'

"${COMPOSE[@]}" exec -T postgres pg_dump -U blackops -d community_board \
    --data-only --no-owner --no-privileges >"${TEMP}/database.dump"
"${COMPOSE[@]}" logs >"${TEMP}/containers.log"

for surface in \
    "${TEMP}/database.dump" \
    "${ROOT}/examples/community-board/var/build" \
    "${ROOT}/examples/community-board/var/log" \
    "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/generated" \
    "${ROOT}/examples/community-board/frontend/build/client" \
    "${TEMP}/containers.log"; do
    ! rg -F "${REGISTER_TOKEN}" "${surface}"
    ! rg -F "${LOGIN_TOKEN}" "${surface}"
    ! rg -F "${ROTATED_TOKEN}" "${surface}"
    ! rg -F "${PASSWORD_MARKER}" "${surface}"
done

! rg -n 'sessionToken|community_board_session' \
    "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/generated"
! rg -n 'BLACKOPS_BASE_URL|http://http|POSTGRES_PASSWORD|community-board-local' \
    "${ROOT}/examples/community-board/frontend/build/client"
! rg -n '/home/|/workspace/|ECONNREFUSED|raw-body' \
    "${ROOT}/examples/community-board/frontend/build/client" \
    "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/generated"
printf 'Sensitive surface guards passed.\n'

"${COMPOSE[@]}" stop postgres >/dev/null
"${CURL[@]}" --silent --output "${TEMP}/database-failure.json" --write-out '%{http_code}' \
    --header 'Content-Type: application/json' \
    --data '{"email":"unknown@example.com","password":"a sufficiently wrong password"}' \
    "http://127.0.0.1:${BLACKOPS_PORT}/auth/login" >"${TEMP}/database-failure-status"
test "$(<"${TEMP}/database-failure-status")" = '500'
test "$(<"${TEMP}/database-failure.json")" = '{"status":"error","code":"internal_error"}'

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

wrapper_imports=$(rg -l 'blackops/generated|\./generated' \
    "${ROOT}/examples/community-board/frontend/src" \
    --glob '!lib/server/blackops/generated/**' | sort)
expected_wrapper_imports=$(printf '%s\n%s\n%s\n%s' \
    "${ROOT}/examples/community-board/frontend/src/lib/server/auth/auth-client.server.ts" \
    "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/board.server.ts" \
    "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/client.server.ts" \
    "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/digest.server.ts")
test "${wrapper_imports}" = "${expected_wrapper_imports}"

printf 'Community Board identity journey passed.\n'
