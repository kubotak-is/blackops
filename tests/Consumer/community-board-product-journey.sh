#!/usr/bin/env bash

set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
PROJECT="community-board-product-${RANDOM}-$$"
FRONTEND_PORT=$((22000 + RANDOM % 500))
BLACKOPS_PORT=$((22500 + RANDOM % 500))
COMPOSE=(
    docker compose
    --project-directory "${ROOT}/examples/community-board"
    --project-name "${PROJECT}"
    -f "${ROOT}/examples/community-board/compose.yaml"
)
CURL=(curl --connect-timeout 3 --max-time 15)
TEMP=$(mktemp -d)
ENVIRONMENT_CREATED=false
git -C "${ROOT}" diff --binary -- src examples/quickstart examples/community-board/app examples/community-board/migrations \
    >"${TEMP}/source-before.diff"
git -C "${ROOT}" status --short -- src examples/quickstart examples/community-board/app examples/community-board/migrations \
    >"${TEMP}/source-before.status"

assert_absent() {
    local label=$1
    local marker=$2
    shift 2
    if rg --quiet --fixed-strings -- "${marker}" "$@"; then
        echo "Sensitive marker guard failed for ${label}." >&2
        return 1
    else
        local status=$?
        if test "${status}" -ne 1; then
            echo "Sensitive marker guard could not inspect ${label}." >&2
            return 2
        fi
    fi
}

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
trap 'printf "Product journey failed at line %s.\n" "${LINENO}" >&2' ERR

export FRONTEND_PORT
export BLACKOPS_DEBUG_PORT="${BLACKOPS_PORT}"
export FRONTEND_ORIGIN="http://localhost:${FRONTEND_PORT}"
export SESSION_COOKIE_SECURE=false

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
    if "${CURL[@]}" --fail --silent "http://localhost:${FRONTEND_PORT}/register" >"${TEMP}/register-page.html"; then
        break
    fi
    sleep 1
done
grep -Fq '<title>Register — BlackOps Board</title>' "${TEMP}/register-page.html"
printf 'Product runtime is ready.\n'

PASSWORD_MARKER="product-password-${RANDOM}-${RANDOM}-long"
ALICE_EMAIL="product-alice-${RANDOM}-$$@example.test"
BOB_EMAIL="product-bob-${RANDOM}-$$@example.test"
ALICE_COOKIES="${TEMP}/alice-cookies"
BOB_COOKIES="${TEMP}/bob-cookies"

"${CURL[@]}" --silent --output "${TEMP}/alice-register.action" --cookie-jar "${ALICE_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode "email=${ALICE_EMAIL}" --data-urlencode 'displayName=Alice' \
    --data-urlencode "password=${PASSWORD_MARKER}" "http://localhost:${FRONTEND_PORT}/register"
grep -Fq '"type":"redirect","status":303,"location":"/me"' "${TEMP}/alice-register.action"
ALICE_TOKEN=$(awk '$6 == "community_board_session" { print $7 }' "${ALICE_COOKIES}")
test "${#ALICE_TOKEN}" -eq 43
printf 'Alice registration passed.\n'

"${CURL[@]}" --fail --silent --cookie "${ALICE_COOKIES}" \
    "http://localhost:${FRONTEND_PORT}/posts?page=invalid" >"${TEMP}/empty-feed.html"
grep -Fq '<h1>Posts</h1>' "${TEMP}/empty-feed.html"
grep -Fq 'No posts yet. Start the conversation.' "${TEMP}/empty-feed.html"
grep -Fq 'Page 1' "${TEMP}/empty-feed.html"

LONG_VALUE=$(printf 'x%.0s' $(seq 1 160))
"${CURL[@]}" --silent --output "${TEMP}/invalid-create.action" --cookie "${ALICE_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode 'title=' --data-urlencode "body=${LONG_VALUE}" \
    "http://localhost:${FRONTEND_PORT}/posts/new"
grep -Fq '"type":"failure","status":422' "${TEMP}/invalid-create.action"
grep -Fq 'Please correct the highlighted fields.' "${TEMP}/invalid-create.action"
grep -Fq 'Please check title.' "${TEMP}/invalid-create.action"
! grep -Fq "${LONG_VALUE}" "${TEMP}/invalid-create.action"
printf 'Feed and validation passed.\n'

"${CURL[@]}" --silent --output "${TEMP}/create.action" --cookie "${ALICE_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode 'title=First post' --data-urlencode 'body=Hello from Alice' \
    "http://localhost:${FRONTEND_PORT}/posts/new"
grep -Eq '"type":"redirect","status":303,"location":"/posts/[0-9a-f-]{36}"' "${TEMP}/create.action"
POST_ID=$(sed -n 's/.*"location":"\/posts\/\([0-9a-f-]*\)".*/\1/p' "${TEMP}/create.action")
test "${#POST_ID}" -eq 36

"${CURL[@]}" --fail --silent --cookie "${ALICE_COOKIES}" \
    "http://localhost:${FRONTEND_PORT}/posts/${POST_ID}" >"${TEMP}/alice-detail.html"
grep -Fq '<h1>First post</h1>' "${TEMP}/alice-detail.html"
grep -Fq 'Hello from Alice' "${TEMP}/alice-detail.html"
grep -Fq 'Edit post' "${TEMP}/alice-detail.html"
grep -Fq 'Delete post' "${TEMP}/alice-detail.html"
"${CURL[@]}" --fail --silent --cookie "${ALICE_COOKIES}" \
    "http://localhost:${FRONTEND_PORT}/posts" >"${TEMP}/alice-feed.html"
grep -Fq 'First post' "${TEMP}/alice-feed.html"
grep -Fq 'Hello from Alice' "${TEMP}/alice-feed.html"
printf 'Create, detail, and feed passed.\n'

"${CURL[@]}" --silent --output "${TEMP}/bob-register.action" --cookie-jar "${BOB_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode "email=${BOB_EMAIL}" --data-urlencode 'displayName=Bob' \
    --data-urlencode "password=${PASSWORD_MARKER}" "http://localhost:${FRONTEND_PORT}/register"
grep -Fq '"type":"redirect","status":303,"location":"/me"' "${TEMP}/bob-register.action"
BOB_TOKEN=$(awk '$6 == "community_board_session" { print $7 }' "${BOB_COOKIES}")
test "${#BOB_TOKEN}" -eq 43

"${CURL[@]}" --silent --output "${TEMP}/comment.action" --cookie "${BOB_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode 'body=Bob was here' "http://localhost:${FRONTEND_PORT}/posts/${POST_ID}?/comment"
grep -Fq "\"type\":\"redirect\",\"status\":303,\"location\":\"/posts/${POST_ID}\"" "${TEMP}/comment.action"

"${CURL[@]}" --fail --silent --cookie "${BOB_COOKIES}" \
    "http://localhost:${FRONTEND_PORT}/posts/${POST_ID}" >"${TEMP}/bob-detail.html"
grep -Fq 'Bob was here' "${TEMP}/bob-detail.html"
grep -Fq 'By Bob' "${TEMP}/bob-detail.html"
! grep -Fq 'Edit post' "${TEMP}/bob-detail.html"
! grep -Fq 'Delete post' "${TEMP}/bob-detail.html"
printf 'Comment and non-owner view passed.\n'

"${CURL[@]}" --silent --output "${TEMP}/bob-edit.action" --cookie "${BOB_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode 'title=Hidden edit' --data-urlencode 'body=Must not persist' \
    "http://localhost:${FRONTEND_PORT}/posts/${POST_ID}/edit"
"${CURL[@]}" --silent --output "${TEMP}/bob-delete.action" --cookie "${BOB_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' --data '' \
    "http://localhost:${FRONTEND_PORT}/posts/${POST_ID}?/delete"
for response in "${TEMP}/bob-edit.action" "${TEMP}/bob-delete.action"; do
    grep -Fq '"type":"failure","status":404' "${response}"
    grep -Fq 'This post could not be found.' "${response}"
    ! rg -q 'authorId|not.owner|forbidden|operationId' "${response}"
done
printf 'Non-owner mutation concealment passed.\n'

"${CURL[@]}" --fail --silent --cookie "${ALICE_COOKIES}" \
    "http://localhost:${FRONTEND_PORT}/posts/${POST_ID}/edit" >"${TEMP}/edit-page.html"
grep -Fq 'value="First post"' "${TEMP}/edit-page.html"
"${CURL[@]}" --silent --output "${TEMP}/edit.action" --cookie "${ALICE_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode 'title=Updated post' --data-urlencode 'body=Updated by Alice' \
    "http://localhost:${FRONTEND_PORT}/posts/${POST_ID}/edit"
grep -Fq "\"type\":\"redirect\",\"status\":303,\"location\":\"/posts/${POST_ID}\"" "${TEMP}/edit.action"
"${CURL[@]}" --fail --silent --cookie "${ALICE_COOKIES}" \
    "http://localhost:${FRONTEND_PORT}/posts/${POST_ID}" >"${TEMP}/updated-detail.html"
grep -Fq 'Updated post' "${TEMP}/updated-detail.html"
grep -Fq 'Updated by Alice' "${TEMP}/updated-detail.html"
grep -Fq 'Bob was here' "${TEMP}/updated-detail.html"
printf 'Owner edit passed.\n'

"${CURL[@]}" --silent --output "${TEMP}/delete.action" --cookie "${ALICE_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' --data '' \
    "http://localhost:${FRONTEND_PORT}/posts/${POST_ID}?/delete"
grep -Fq '"type":"redirect","status":303,"location":"/posts"' "${TEMP}/delete.action"
"${CURL[@]}" --fail --silent --cookie "${ALICE_COOKIES}" \
    "http://localhost:${FRONTEND_PORT}/posts" >"${TEMP}/deleted-feed.html"
grep -Fq 'No posts yet. Start the conversation.' "${TEMP}/deleted-feed.html"
! grep -Fq 'Bob was here' "${TEMP}/deleted-feed.html"
printf 'Owner delete passed.\n'

for target in "${POST_ID}" malformed-post-id; do
    "${CURL[@]}" --silent --output "${TEMP}/missing-${target}.html" --write-out '%{http_code}' \
        --cookie "${ALICE_COOKIES}" "http://localhost:${FRONTEND_PORT}/posts/${target}" >"${TEMP}/missing-${target}.status"
    test "$(<"${TEMP}/missing-${target}.status")" = '404'
    grep -Fq 'This post could not be found.' "${TEMP}/missing-${target}.html"
    ! rg -q 'operationId|authorId|SQLSTATE|/workspace/' "${TEMP}/missing-${target}.html"
done

"${CURL[@]}" --silent --output "${TEMP}/missing-cookie.body" --dump-header "${TEMP}/missing-cookie.headers" \
    "http://localhost:${FRONTEND_PORT}/posts"
grep -Eiq '^location: /login' "${TEMP}/missing-cookie.headers"
"${CURL[@]}" --silent --output "${TEMP}/invalid-cookie.body" --dump-header "${TEMP}/invalid-cookie.headers" \
    --cookie 'community_board_session=invalid-session-marker' "http://localhost:${FRONTEND_PORT}/posts"
grep -Eiq '^location: /login' "${TEMP}/invalid-cookie.headers"
grep -Eiq '^set-cookie: community_board_session=;.*Max-Age=0' "${TEMP}/invalid-cookie.headers"
printf 'Not-found and session behavior passed.\n'

"${COMPOSE[@]}" logs --no-color frontend >"${TEMP}/frontend.log"
CLIENT_BUILD="${ROOT}/examples/community-board/frontend/build/client"
for marker in BLACKOPS_BASE_URL community_board_session 'blackops/generated' ListPosts ShowPost CreatePost UpdatePost DeletePost AddComment '$env/dynamic/private' 'Authorization' 'Bearer '; do
    assert_absent 'browser build' "${marker}" "${CLIENT_BUILD}"
done

printf 'guard-fixture-sensitive-value\n' >"${TEMP}/guard-fixture.txt"
if assert_absent fixture guard-fixture-sensitive-value "${TEMP}/guard-fixture.txt" >/dev/null 2>&1; then
    echo 'Sensitive marker guard fixture did not reject a matching marker.' >&2
    exit 1
fi
assert_absent fixture guard-fixture-absent-value "${TEMP}/guard-fixture.txt"

for surface in \
    "${TEMP}/alice-register.action" "${TEMP}/invalid-create.action" "${TEMP}/create.action" \
    "${TEMP}/alice-detail.html" "${TEMP}/alice-feed.html" "${TEMP}/bob-register.action" \
    "${TEMP}/comment.action" "${TEMP}/bob-detail.html" "${TEMP}/bob-edit.action" \
    "${TEMP}/bob-delete.action" "${TEMP}/edit.action" "${TEMP}/updated-detail.html" \
    "${TEMP}/delete.action" "${TEMP}/deleted-feed.html" "${TEMP}/frontend.log" "${CLIENT_BUILD}"; do
    assert_absent 'Alice token' "${ALICE_TOKEN}" "${surface}"
    assert_absent 'Bob token' "${BOB_TOKEN}" "${surface}"
    assert_absent 'password' "${PASSWORD_MARKER}" "${surface}"
    assert_absent 'internal base URL' 'http://http' "${surface}"
done
! rg -n 'SQLSTATE|SELECT |INSERT |UPDATE public|DELETE FROM|/workspace/|/home/' \
    "${TEMP}"/*.html "${TEMP}"/*.action "${CLIENT_BUILD}"
printf 'Browser and sensitive guards passed.\n'

"${COMPOSE[@]}" stop http >/dev/null
"${CURL[@]}" --silent --output "${TEMP}/backend-down.html" --write-out '%{http_code}' \
    --cookie "${ALICE_COOKIES}" "http://localhost:${FRONTEND_PORT}/posts" >"${TEMP}/backend-down.status"
test "$(<"${TEMP}/backend-down.status")" = '503'
grep -Fq 'The board service is temporarily unavailable.' "${TEMP}/backend-down.html"
! rg -q 'http://http|ECONNREFUSED|operationId|SQLSTATE|/workspace/|community_board_session' "${TEMP}/backend-down.html"
"${CURL[@]}" --silent --output "${TEMP}/backend-down.action" --cookie "${ALICE_COOKIES}" \
    --header "Origin: ${FRONTEND_ORIGIN}" --header 'Content-Type: application/x-www-form-urlencoded' \
    --data-urlencode 'title=Offline post' --data-urlencode 'body=Must not persist' \
    "http://localhost:${FRONTEND_PORT}/posts/new"
grep -Fq '"type":"failure","status":503' "${TEMP}/backend-down.action"
grep -Fq 'The board service is temporarily unavailable.' "${TEMP}/backend-down.action"
! rg -q 'http://http|ECONNREFUSED|operationId|SQLSTATE|/workspace/|community_board_session' "${TEMP}/backend-down.action"

git -C "${ROOT}" diff --binary -- src examples/quickstart examples/community-board/app examples/community-board/migrations \
    >"${TEMP}/source-after.diff"
cmp "${TEMP}/source-before.diff" "${TEMP}/source-after.diff"
git -C "${ROOT}" status --short -- src examples/quickstart examples/community-board/app examples/community-board/migrations \
    >"${TEMP}/source-after.status"
cmp "${TEMP}/source-before.status" "${TEMP}/source-after.status"
if git -C "${ROOT}" ls-files \
    examples/community-board/.env examples/community-board/vendor examples/community-board/var \
    examples/community-board/frontend/node_modules \
    examples/community-board/frontend/src/lib/server/blackops/generated \
    examples/community-board/frontend/.svelte-kit examples/community-board/frontend/build | grep -q .; then
    echo 'Community Board runtime and generated artifacts must not be tracked.' >&2
    exit 1
fi

printf 'Community Board product journey passed.\n'
