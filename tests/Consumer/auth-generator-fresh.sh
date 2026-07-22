#!/usr/bin/env bash

set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
TEMP=$(mktemp -d)
CONSUMER="${TEMP}/consumer"
PROJECT="blackops-auth-fresh-${RANDOM}-$$"
PORT=$((27000 + RANDOM % 1000))
OVERRIDE="${TEMP}/compose.install.yaml"
SOURCE_BEFORE=$(git -C "${ROOT}" status --short)
COMMUNITY_BOARD_DIFF_BEFORE=$(git -C "${ROOT}" diff --binary -- examples/community-board)
COMMUNITY_BOARD_STATUS_BEFORE=$(git -C "${ROOT}" status --short -- examples/community-board)
PASSWORD="FreshAuth!${RANDOM}Secure"
EMAIL="fresh-${RANDOM}-$$@blackops.test"

cleanup() {
    if test -d "${CONSUMER}"; then
        docker compose --project-directory "${CONSUMER}" --project-name "${PROJECT}" \
            -f "${CONSUMER}/compose.yaml" down --volumes --remove-orphans --rmi local >/dev/null 2>&1 || true
    fi
    rm -rf "${TEMP}"
}
trap cleanup EXIT
trap 'printf "Auth generator fresh consumer failed at line %s.\n" "${LINENO}" >&2' ERR

json_token() {
    sed -n 's/.*"token":"\([A-Za-z0-9_-]*\)".*/\1/p' "$1"
}

mkdir -p "${CONSUMER}/app/Feature/AuthProbe"
cp -a "${ROOT}/examples/quickstart/." "${CONSUMER}/"
cp -a "${ROOT}/tests/Consumer/fixtures/auth-fresh/CurrentActor" "${CONSUMER}/app/Feature/AuthProbe/"
cp "${CONSUMER}/.env.example" "${CONSUMER}/.env"

cat >"${OVERRIDE}" <<YAML
services:
  app:
    volumes:
      - ${ROOT}:/framework:ro
YAML

COMPOSE=(docker compose --project-directory "${CONSUMER}" --project-name "${PROJECT}" -f "${CONSUMER}/compose.yaml")
INSTALL_COMPOSE=("${COMPOSE[@]}" -f "${OVERRIDE}")

docker run --rm -v "${CONSUMER}:/app" -v "${ROOT}:/framework:ro" -w /app composer:2 \
    composer config repositories.framework \
    '{"type":"path","url":"/framework","options":{"symlink":false,"versions":{"blackops/framework":"1.1.0"}}}'
docker run --rm -v "${CONSUMER}:/app" -w /app composer:2 \
    composer require --no-update --no-interaction doctrine/dbal:^4.4 doctrine/migrations:^3.9

HTTP_PORT="${PORT}" "${COMPOSE[@]}" build app http
HTTP_PORT="${PORT}" "${COMPOSE[@]}" up -d postgres
HTTP_PORT="${PORT}" "${INSTALL_COMPOSE[@]}" run --rm app composer install --no-interaction --prefer-dist
HTTP_PORT="${PORT}" "${COMPOSE[@]}" run --rm app composer validate --strict
test ! -L "${CONSUMER}/vendor/blackops/framework"

test ! -e "${CONSUMER}/config/auth.php"
test ! -e "${CONSUMER}/app/Domain/Identity"
test ! -e "${CONSUMER}/app/Feature/Identity"
test ! -e "${CONSUMER}/migrations/Version20260722000100.php"
HTTP_PORT="${PORT}" "${COMPOSE[@]}" run --rm app php blackops build:compile
HTTP_PORT="${PORT}" "${COMPOSE[@]}" run --rm app php -r '
$manifest = require "/app/var/build/operations.php";
foreach ($manifest["payload"]["operations"] ?? [] as $operation) {
    if (in_array(($operation["typeId"] ?? null), ["auth.register", "auth.login", "auth.logout"], true)) {
        exit(1);
    }
}
'
test "$(HTTP_PORT="${PORT}" "${COMPOSE[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name IN ('users', 'blackops_sessions')")" = '0'

HTTP_PORT="${PORT}" "${COMPOSE[@]}" run --rm app php blackops make:auth >"${TEMP}/created.out"
grep -Fxq 'Created: app/Domain/Identity/User.php' "${TEMP}/created.out"
grep -Fxq 'Created: app/Infrastructure/Identity/DoctrineUserRepository.php' "${TEMP}/created.out"
grep -Fxq 'Created: app/Feature/Identity/Register/Register.php' "${TEMP}/created.out"
grep -Fxq 'Created: config/auth.php' "${TEMP}/created.out"
grep -Fxq 'Created: migrations/Version20260722000100.php' "${TEMP}/created.out"
! grep -Fq "${CONSUMER}" "${TEMP}/created.out"

HTTP_PORT="${PORT}" "${COMPOSE[@]}" run --rm app php blackops make:auth >"${TEMP}/noop.out"
test "$(<"${TEMP}/noop.out")" = 'Authentication starter is already current.'

mv "${CONSUMER}/app/Feature/Identity/Logout/LogoutCompleted.php" "${TEMP}/LogoutCompleted.php"
find "${CONSUMER}/app/Domain/Identity" "${CONSUMER}/app/Infrastructure/Identity" \
    "${CONSUMER}/app/Feature/Identity" "${CONSUMER}/config/auth.php" \
    "${CONSUMER}/migrations/Version20260722000000.php" \
    "${CONSUMER}/migrations/Version20260722000100.php" \
    -type f -print0 | sort -z | xargs -0 sha256sum >"${TEMP}/partial.before"
if HTTP_PORT="${PORT}" "${COMPOSE[@]}" run --rm app php blackops make:auth >"${TEMP}/partial.out" 2>&1; then
    echo 'Partial authentication starter unexpectedly succeeded.' >&2
    exit 1
fi
grep -Fq 'Authentication starter is incomplete' "${TEMP}/partial.out"
! grep -Fq "${CONSUMER}" "${TEMP}/partial.out"
find "${CONSUMER}/app/Domain/Identity" "${CONSUMER}/app/Infrastructure/Identity" \
    "${CONSUMER}/app/Feature/Identity" "${CONSUMER}/config/auth.php" \
    "${CONSUMER}/migrations/Version20260722000000.php" \
    "${CONSUMER}/migrations/Version20260722000100.php" \
    -type f -print0 | sort -z | xargs -0 sha256sum >"${TEMP}/partial.after"
cmp "${TEMP}/partial.before" "${TEMP}/partial.after"
mv "${TEMP}/LogoutCompleted.php" "${CONSUMER}/app/Feature/Identity/Logout/LogoutCompleted.php"

printf '\n// application-owned user customization\n' >>"${CONSUMER}/app/Domain/Identity/User.php"
printf '\n// immutable application migration\n' >>"${CONSUMER}/migrations/Version20260722000000.php"
sha256sum "${CONSUMER}/app/Domain/Identity/User.php" >"${TEMP}/user.before"
sha256sum "${CONSUMER}/migrations/Version20260722000000.php" >"${TEMP}/migration.before"
sed -i "s/'generator_version' => 1/'generator_version' => 0/" "${CONSUMER}/config/auth.php"
HTTP_PORT="${PORT}" "${COMPOSE[@]}" run --rm app php blackops make:auth --force >"${TEMP}/force.out"
test "$(grep -c '^Updated: ' "${TEMP}/force.out")" = '3'
grep -Fxq 'Updated: config/auth.php' "${TEMP}/force.out"
sha256sum --check "${TEMP}/user.before"
sha256sum --check "${TEMP}/migration.before"

cp -a "${ROOT}/tests/Consumer/fixtures/auth-fresh/RotateSession" "${CONSUMER}/app/Feature/AuthProbe/"
cp -a "${ROOT}/tests/Consumer/fixtures/auth-fresh/CleanupSessions" "${CONSUMER}/app/Feature/AuthProbe/"
HTTP_PORT="${PORT}" "${COMPOSE[@]}" run --rm app composer dump-autoload --classmap-authoritative
HTTP_PORT="${PORT}" "${COMPOSE[@]}" run --rm app php blackops database:migrate >"${TEMP}/migrate.out"
test "$(HTTP_PORT="${PORT}" "${COMPOSE[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name IN ('users', 'blackops_sessions')")" = '2'
HTTP_PORT="${PORT}" "${COMPOSE[@]}" run --rm app php blackops build:compile
HTTP_PORT="${PORT}" "${COMPOSE[@]}" run --rm app php blackops frontend:generate
HTTP_PORT="${PORT}" "${COMPOSE[@]}" run --rm app php blackops frontend:check

REGISTER_MODULE=$(find "${CONSUMER}/resources/js/blackops/operations" -type f -path '*/auth/register.ts' -print -quit)
LOGIN_MODULE=$(find "${CONSUMER}/resources/js/blackops/operations" -type f -path '*/auth/login.ts' -print -quit)
LOGOUT_MODULE=$(find "${CONSUMER}/resources/js/blackops/operations" -type f -path '*/auth/logout.ts' -print -quit)
test -n "${REGISTER_MODULE}"
test -n "${LOGIN_MODULE}"
test -n "${LOGOUT_MODULE}"
for module in "${REGISTER_MODULE}" "${LOGIN_MODULE}" "${LOGOUT_MODULE}"; do
    grep -Fq 'fetch(' "${module}"
    ! grep -Fq 'status(' "${module}"
    ! grep -Fq 'wait(' "${module}"
done

HTTP_PORT="${PORT}" "${COMPOSE[@]}" up -d http
for _ in $(seq 1 40); do
    if curl --fail --silent "http://127.0.0.1:${PORT}/auth-probe/current" >"${TEMP}/ready.json"; then
        break
    fi
    sleep 1
done
test "$(<"${TEMP}/ready.json")" = '{"id":null,"type":null}'

REGISTER_STATUS=$(curl --silent --output "${TEMP}/register.json" --write-out '%{http_code}' \
    -X POST "http://127.0.0.1:${PORT}/auth/register" \
    -H 'Content-Type: application/json' \
    --data "{\"email\":\"${EMAIL}\",\"displayName\":\"Fresh User\",\"password\":\"${PASSWORD}\"}")
test "${REGISTER_STATUS}" = '200'
REGISTER_TOKEN=$(json_token "${TEMP}/register.json")
test "${#REGISTER_TOKEN}" = '43'

DUPLICATE_STATUS=$(curl --silent --output "${TEMP}/duplicate.json" --write-out '%{http_code}' \
    -X POST "http://127.0.0.1:${PORT}/auth/register" -H 'Content-Type: application/json' \
    --data "{\"email\":\"${EMAIL}\",\"displayName\":\"Duplicate\",\"password\":\"${PASSWORD}\"}")
test "${DUPLICATE_STATUS}" = '409'
grep -Fq '"code":"auth.email_unavailable"' "${TEMP}/duplicate.json"

LOGIN_STATUS=$(curl --silent --output "${TEMP}/login.json" --write-out '%{http_code}' \
    -X POST "http://127.0.0.1:${PORT}/auth/login" -H 'Content-Type: application/json' \
    --data "{\"email\":\"${EMAIL}\",\"password\":\"${PASSWORD}\"}")
test "${LOGIN_STATUS}" = '200'
LOGIN_TOKEN=$(json_token "${TEMP}/login.json")
test "${#LOGIN_TOKEN}" = '43'
test "${LOGIN_TOKEN}" != "${REGISTER_TOKEN}"

for case in wrong missing; do
    case "${case}" in
        wrong) INVALID_EMAIL="${EMAIL}" ;;
        missing) INVALID_EMAIL="missing-${EMAIL}" ;;
    esac
    STATUS=$(curl --silent --output "${TEMP}/invalid-${case}.json" --write-out '%{http_code}' \
        -X POST "http://127.0.0.1:${PORT}/auth/login" -H 'Content-Type: application/json' \
        --data "{\"email\":\"${INVALID_EMAIL}\",\"password\":\"${PASSWORD}-wrong\"}")
    test "${STATUS}" = '401'
done
WRONG_SAFE=$(sed -E 's/,"operationId":"[^"]*"//' "${TEMP}/invalid-wrong.json")
MISSING_SAFE=$(sed -E 's/,"operationId":"[^"]*"//' "${TEMP}/invalid-missing.json")
test "${WRONG_SAFE}" = "${MISSING_SAFE}"
grep -Fq '"code":"auth.invalid_credentials"' "${TEMP}/invalid-wrong.json"

USER_ID=$(HTTP_PORT="${PORT}" "${COMPOSE[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT id FROM public.users WHERE email = '${EMAIL}'")
curl --fail --silent "http://127.0.0.1:${PORT}/auth-probe/current" \
    -H "Authorization: Bearer ${LOGIN_TOKEN}" >"${TEMP}/authenticated.json"
test "$(<"${TEMP}/authenticated.json")" = "{\"id\":\"${USER_ID}\",\"type\":\"user\"}"

ROTATE_STATUS=$(curl --silent --output "${TEMP}/rotate.json" --write-out '%{http_code}' \
    -X POST "http://127.0.0.1:${PORT}/auth-probe/rotate" -H 'Content-Type: application/json' \
    --data "{\"token\":\"${LOGIN_TOKEN}\"}")
test "${ROTATE_STATUS}" = '200'
ROTATED_TOKEN=$(json_token "${TEMP}/rotate.json")
test "${#ROTATED_TOKEN}" = '43'
test "$(curl --silent --output "${TEMP}/rotated-old.json" --write-out '%{http_code}' \
    "http://127.0.0.1:${PORT}/auth-probe/current" -H "Authorization: Bearer ${LOGIN_TOKEN}")" = '401'
grep -Fq '"code":"authentication.invalid_session"' "${TEMP}/rotated-old.json"
curl --fail --silent "http://127.0.0.1:${PORT}/auth-probe/current" \
    -H "Authorization: Bearer ${ROTATED_TOKEN}" >"${TEMP}/rotated-current.json"
test "$(<"${TEMP}/rotated-current.json")" = "{\"id\":\"${USER_ID}\",\"type\":\"user\"}"

ROTATED_HASH=$(printf '%s' "${ROTATED_TOKEN}" | sha256sum | cut -d' ' -f1)
HTTP_PORT="${PORT}" "${COMPOSE[@]}" exec -T postgres psql -U blackops -d blackops -v ON_ERROR_STOP=1 -c \
    "UPDATE public.blackops_sessions SET expires_at = issued_at + interval '1 microsecond', last_used_at = issued_at WHERE token_hash = '${ROTATED_HASH}'" >/dev/null
test "$(curl --silent --output "${TEMP}/expired.json" --write-out '%{http_code}' \
    "http://127.0.0.1:${PORT}/auth-probe/current" -H "Authorization: Bearer ${ROTATED_TOKEN}")" = '401'

for attempt in 1 2; do
    test "$(curl --silent --output "${TEMP}/logout-${attempt}.json" --write-out '%{http_code}' \
        -X POST "http://127.0.0.1:${PORT}/auth/logout" -H 'Content-Type: application/json' \
        --data "{\"token\":\"${REGISTER_TOKEN}\"}")" = '200'
    test "$(<"${TEMP}/logout-${attempt}.json")" = '{}'
done
test "$(curl --silent --output "${TEMP}/logged-out.json" --write-out '%{http_code}' \
    "http://127.0.0.1:${PORT}/auth-probe/current" -H "Authorization: Bearer ${REGISTER_TOKEN}")" = '401'

REGISTER_HASH=$(printf '%s' "${REGISTER_TOKEN}" | sha256sum | cut -d' ' -f1)
LOGIN_HASH=$(printf '%s' "${LOGIN_TOKEN}" | sha256sum | cut -d' ' -f1)
HTTP_PORT="${PORT}" "${COMPOSE[@]}" exec -T postgres psql -U blackops -d blackops -v ON_ERROR_STOP=1 -c \
    "UPDATE public.blackops_sessions SET revoked_at = now() - interval '2 days' WHERE token_hash IN ('${REGISTER_HASH}', '${LOGIN_HASH}')" >/dev/null
CUTOFF=$(date --utc --date='1 day ago' --iso-8601=seconds)
curl --fail --silent -X POST "http://127.0.0.1:${PORT}/auth-probe/cleanup" \
    -H 'Content-Type: application/json' --data "{\"retentionCutoff\":\"${CUTOFF}\"}" >"${TEMP}/cleanup.json"
DELETED=$(sed -n 's/.*"deleted":\([0-9][0-9]*\).*/\1/p' "${TEMP}/cleanup.json")
test -n "${DELETED}"
test "${DELETED}" -ge 2

EPHEMERAL_OUTCOMES=$(HTTP_PORT="${PORT}" "${COMPOSE[@]}" exec -T postgres psql -U blackops -d blackops -Atc \
    "SELECT count(*) FROM blackops.outcomes AS outcomes JOIN blackops.operations AS operations USING (operation_id) WHERE operations.operation_type IN ('auth.register', 'auth.login', 'auth.logout', 'auth.probe.rotate')")
test "${EPHEMERAL_OUTCOMES}" = '0'
HTTP_PORT="${PORT}" "${COMPOSE[@]}" exec -T postgres pg_dump -U blackops -d blackops --data-only >"${TEMP}/database.dump"
HTTP_PORT="${PORT}" "${COMPOSE[@]}" logs --no-color >"${TEMP}/containers.log"
for surface in "${TEMP}/database.dump" "${CONSUMER}/var/build" "${CONSUMER}/var/log" \
    "${CONSUMER}/resources/js/blackops" "${TEMP}/containers.log" "${TEMP}/created.out" \
    "${TEMP}/noop.out" "${TEMP}/force.out"; do
    ! rg --fixed-strings "${PASSWORD}" "${surface}"
    ! rg --fixed-strings "${REGISTER_TOKEN}" "${surface}"
    ! rg --fixed-strings "${LOGIN_TOKEN}" "${surface}"
    ! rg --fixed-strings "${ROTATED_TOKEN}" "${surface}"
done

HTTP_PORT="${PORT}" "${COMPOSE[@]}" run --rm app composer validate --strict
test "$(git -C "${ROOT}" status --short)" = "${SOURCE_BEFORE}"
test "${COMMUNITY_BOARD_DIFF_BEFORE}" = "$(git -C "${ROOT}" diff --binary -- examples/community-board)"
test "${COMMUNITY_BOARD_STATUS_BEFORE}" = "$(git -C "${ROOT}" status --short -- examples/community-board)"

cleanup
trap - EXIT
test ! -e "${TEMP}"
printf 'Auth generator fresh consumer journey passed.\n'
