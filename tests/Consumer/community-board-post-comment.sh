#!/usr/bin/env bash

set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
PROJECT="community-board-post-comment-${RANDOM}-$$"
BLACKOPS_PORT=$((21500 + RANDOM % 500))
COMPOSE=(
    docker compose
    --project-directory "${ROOT}/examples/community-board"
    --project-name "${PROJECT}"
    -f "${ROOT}/examples/community-board/compose.yaml"
)
CURL=(curl --connect-timeout 3 --max-time 15)
TEMP=$(mktemp -d)
ENVIRONMENT_CREATED=false

assert_no_fixed_marker() {
    local label=$1
    local marker=$2
    shift 2

    if rg --quiet --fixed-strings -- "${marker}" "$@"; then
        echo "Sensitive marker guard failed for ${label}." >&2
        exit 1
    else
        local status=$?
        if test "${status}" -ne 1; then
            echo "Sensitive marker guard could not inspect ${label}." >&2
            exit 1
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

export BLACKOPS_DEBUG_PORT="${BLACKOPS_PORT}"

test -d "${ROOT}/examples/community-board/vendor"
POST_COMMENT_MIGRATION='examples/community-board/migrations/Version20260720214000.php'
test -f "${ROOT}/${POST_COMMENT_MIGRATION}"
if test "${CI:-}" = 'true'; then
    git -C "${ROOT}" ls-files --error-unmatch "${POST_COMMENT_MIGRATION}" >/dev/null
elif ! git -C "${ROOT}" ls-files --error-unmatch "${POST_COMMENT_MIGRATION}" >/dev/null 2>&1; then
    git -C "${ROOT}" ls-files --others --exclude-standard -- "${POST_COMMENT_MIGRATION}" \
        | grep -Fxq "${POST_COMMENT_MIGRATION}"
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

for module in \
    operations/board/post/list-posts.ts \
    operations/board/post/show-post.ts \
    operations/board/post/create-post.ts \
    operations/board/post/update-post.ts \
    operations/board/post/delete-post.ts \
    operations/board/comment/add-comment.ts; do
    test -f "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/generated/${module}"
done
grep -Fq 'ReadonlyArray<PostSummary>' \
    "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/generated/operations/board/post/list-posts.ts"
grep -Fq 'ReadonlyArray<CommentDetail>' \
    "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/generated/operations/board/post/show-post.ts"

"${COMPOSE[@]}" up -d http
for _ in $(seq 1 30); do
    if "${CURL[@]}" --fail --silent "http://127.0.0.1:${BLACKOPS_PORT}/welcome" >"${TEMP}/welcome.json"; then
        break
    fi
    sleep 1
done
grep -Fq 'Welcome to BlackOps Board' "${TEMP}/welcome.json"

PASSWORD_MARKER="post-comment-password-${RANDOM}-${RANDOM}-long"
ALICE_EMAIL="post-alice-${RANDOM}-$$@example.test"
BOB_EMAIL="post-bob-${RANDOM}-$$@example.test"

"${CURL[@]}" --silent --fail \
    --header 'Content-Type: application/json' \
    --data "{\"email\":\"${ALICE_EMAIL}\",\"displayName\":\"Alice\",\"password\":\"${PASSWORD_MARKER}\"}" \
    "http://127.0.0.1:${BLACKOPS_PORT}/auth/users" >"${TEMP}/alice-register.json"
"${CURL[@]}" --silent --fail \
    --header 'Content-Type: application/json' \
    --data "{\"email\":\"${BOB_EMAIL}\",\"displayName\":\"Bob\",\"password\":\"${PASSWORD_MARKER}\"}" \
    "http://127.0.0.1:${BLACKOPS_PORT}/auth/users" >"${TEMP}/bob-register.json"

ALICE_TOKEN=$(sed -n 's/.*"sessionToken":"\([^"]*\)".*/\1/p' "${TEMP}/alice-register.json")
BOB_TOKEN=$(sed -n 's/.*"sessionToken":"\([^"]*\)".*/\1/p' "${TEMP}/bob-register.json")
test "${#ALICE_TOKEN}" -eq 43
test "${#BOB_TOKEN}" -eq 43
test "${ALICE_TOKEN}" != "${BOB_TOKEN}"
rm -f "${TEMP}/alice-register.json" "${TEMP}/bob-register.json"

"${CURL[@]}" --silent --output "${TEMP}/anonymous-list.json" --write-out '%{http_code}' \
    "http://127.0.0.1:${BLACKOPS_PORT}/posts" >"${TEMP}/anonymous-list.status"
test "$(<"${TEMP}/anonymous-list.status")" = '401'

"${CURL[@]}" --silent --output "${TEMP}/invalid-create.json" --write-out '%{http_code}' \
    --header "Authorization: Bearer ${ALICE_TOKEN}" \
    --header 'Content-Type: application/json' \
    --data '{"title":"","body":""}' \
    "http://127.0.0.1:${BLACKOPS_PORT}/posts" >"${TEMP}/invalid-create.status"
test "$(<"${TEMP}/invalid-create.status")" = '422'
grep -Eq '"operationId":"[0-9a-f-]{36}"' "${TEMP}/invalid-create.json"
grep -Fq '"field":"title"' "${TEMP}/invalid-create.json"
grep -Fq '"field":"body"' "${TEMP}/invalid-create.json"
grep -Fq '"code":"validation.failed"' "${TEMP}/invalid-create.json"

"${CURL[@]}" --silent --output "${TEMP}/create.json" --write-out '%{http_code}' \
    --header "Authorization: Bearer ${ALICE_TOKEN}" \
    --header 'Content-Type: application/json' \
    --data '{"title":"First post","body":"Hello from Alice"}' \
    "http://127.0.0.1:${BLACKOPS_PORT}/posts" >"${TEMP}/create.status"
test "$(<"${TEMP}/create.status")" = '200'
POST_ID=$(sed -n 's/.*"postId":"\([^"]*\)".*/\1/p' "${TEMP}/create.json")
test "${#POST_ID}" -eq 36
grep -Eq '"createdAt":"[0-9]{4}-[0-9]{2}-[0-9]{2}T.*Z"' "${TEMP}/create.json"

"${CURL[@]}" --fail --silent \
    --header "Authorization: Bearer ${ALICE_TOKEN}" \
    "http://127.0.0.1:${BLACKOPS_PORT}/posts" >"${TEMP}/feed.json"
grep -Fq "\"id\":\"${POST_ID}\"" "${TEMP}/feed.json"
grep -Fq '"authorDisplayName":"Alice"' "${TEMP}/feed.json"
grep -Fq '"bodyPreview":"Hello from Alice"' "${TEMP}/feed.json"
grep -Fq '"commentCount":0' "${TEMP}/feed.json"
grep -Fq '"page":1' "${TEMP}/feed.json"
grep -Fq '"perPage":20' "${TEMP}/feed.json"
grep -Fq '"total":1' "${TEMP}/feed.json"

"${CURL[@]}" --fail --silent \
    --header "Authorization: Bearer ${ALICE_TOKEN}" \
    "http://127.0.0.1:${BLACKOPS_PORT}/posts/${POST_ID}" >"${TEMP}/detail.json"
grep -Fq '"title":"First post"' "${TEMP}/detail.json"
grep -Fq '"comments":[]' "${TEMP}/detail.json"

UNKNOWN_POST='019b9999-9999-7999-8999-999999999999'
for target in "${POST_ID}" "${UNKNOWN_POST}" 'malformed-post-id'; do
    "${CURL[@]}" --silent --output "${TEMP}/bob-update-${target//\//_}.json" --write-out '%{http_code}' \
        --request PUT \
        --header "Authorization: Bearer ${BOB_TOKEN}" \
        --header 'Content-Type: application/json' \
        --data '{"title":"Hidden update","body":"Must not persist"}' \
        "http://127.0.0.1:${BLACKOPS_PORT}/posts/${target}" >"${TEMP}/bob-update-${target//\//_}.status"
    test "$(<"${TEMP}/bob-update-${target//\//_}.status")" = '404'
    grep -Fq '"category":"not_found"' "${TEMP}/bob-update-${target//\//_}.json"
    grep -Fq '"code":"board.post.not_found"' "${TEMP}/bob-update-${target//\//_}.json"
done

"${CURL[@]}" --silent --output "${TEMP}/bob-delete.json" --write-out '%{http_code}' \
    --request DELETE \
    --header "Authorization: Bearer ${BOB_TOKEN}" \
    "http://127.0.0.1:${BLACKOPS_PORT}/posts/${POST_ID}" >"${TEMP}/bob-delete.status"
test "$(<"${TEMP}/bob-delete.status")" = '404'
grep -Fq '"category":"not_found"' "${TEMP}/bob-delete.json"
grep -Fq '"code":"board.post.not_found"' "${TEMP}/bob-delete.json"

"${CURL[@]}" --silent --output "${TEMP}/comment.json" --write-out '%{http_code}' \
    --header "Authorization: Bearer ${BOB_TOKEN}" \
    --header 'Content-Type: application/json' \
    --data '{"body":"Bob was here"}' \
    "http://127.0.0.1:${BLACKOPS_PORT}/posts/${POST_ID}/comments" >"${TEMP}/comment.status"
test "$(<"${TEMP}/comment.status")" = '200'
COMMENT_ID=$(sed -n 's/.*"commentId":"\([^"]*\)".*/\1/p' "${TEMP}/comment.json")
test "${#COMMENT_ID}" -eq 36
grep -Fq "\"postId\":\"${POST_ID}\"" "${TEMP}/comment.json"

"${CURL[@]}" --fail --silent \
    --request PUT \
    --header "Authorization: Bearer ${ALICE_TOKEN}" \
    --header 'Content-Type: application/json' \
    --data '{"title":"Updated post","body":"Updated by Alice"}' \
    "http://127.0.0.1:${BLACKOPS_PORT}/posts/${POST_ID}" >"${TEMP}/update.json"
grep -Fq "\"postId\":\"${POST_ID}\"" "${TEMP}/update.json"
grep -Eq '"updatedAt":"[0-9]{4}-[0-9]{2}-[0-9]{2}T.*Z"' "${TEMP}/update.json"

"${CURL[@]}" --fail --silent \
    --header "Authorization: Bearer ${ALICE_TOKEN}" \
    "http://127.0.0.1:${BLACKOPS_PORT}/posts/${POST_ID}" >"${TEMP}/updated-detail.json"
grep -Fq '"title":"Updated post"' "${TEMP}/updated-detail.json"
grep -Fq '"body":"Updated by Alice"' "${TEMP}/updated-detail.json"
grep -Fq "\"id\":\"${COMMENT_ID}\"" "${TEMP}/updated-detail.json"
grep -Fq '"authorDisplayName":"Bob"' "${TEMP}/updated-detail.json"
grep -Fq '"body":"Bob was here"' "${TEMP}/updated-detail.json"

"${CURL[@]}" --silent --output "${TEMP}/delete.body" --write-out '%{http_code}' \
    --request DELETE \
    --header "Authorization: Bearer ${ALICE_TOKEN}" \
    "http://127.0.0.1:${BLACKOPS_PORT}/posts/${POST_ID}" >"${TEMP}/delete.status"
test "$(<"${TEMP}/delete.status")" = '204'
test ! -s "${TEMP}/delete.body"

ROW_COUNTS=$("${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
    'SELECT (SELECT count(*) FROM public.board_posts)::text || '"'"':'"'"' || (SELECT count(*) FROM public.board_comments)::text')
test "${ROW_COUNTS}" = '0:0'

for request in show comment; do
    if test "${request}" = show; then
        method=GET
        data=()
        url="http://127.0.0.1:${BLACKOPS_PORT}/posts/${POST_ID}"
    else
        method=POST
        data=(--header 'Content-Type: application/json' --data '{"body":"After delete"}')
        url="http://127.0.0.1:${BLACKOPS_PORT}/posts/${POST_ID}/comments"
    fi
    "${CURL[@]}" --silent --output "${TEMP}/after-delete-${request}.json" --write-out '%{http_code}' \
        --request "${method}" \
        --header "Authorization: Bearer ${ALICE_TOKEN}" \
        "${data[@]}" \
        "${url}" >"${TEMP}/after-delete-${request}.status"
    test "$(<"${TEMP}/after-delete-${request}.status")" = '404'
    grep -Fq '"code":"board.post.not_found"' "${TEMP}/after-delete-${request}.json"
done

"${CURL[@]}" --fail --silent \
    --header "Authorization: Bearer ${ALICE_TOKEN}" \
    "http://127.0.0.1:${BLACKOPS_PORT}/posts?page=2&perPage=1" >"${TEMP}/empty-feed.json"
test "$(<"${TEMP}/empty-feed.json")" = '{"page":2,"perPage":1,"posts":[],"total":0}'

PASSWORD_HASH=$("${COMPOSE[@]}" exec -T postgres psql -U blackops -d community_board -Atc \
    "SELECT password_hash FROM public.board_users WHERE email_canonical = '${ALICE_EMAIL}'")
test -n "${PASSWORD_HASH}"
printf 'guard-fixture-sensitive-value\n' >"${TEMP}/guard-fixture.txt"
if (assert_no_fixed_marker 'fixture' 'guard-fixture-sensitive-value' "${TEMP}/guard-fixture.txt") \
    >/dev/null 2>&1; then
    echo 'Sensitive marker guard fixture did not reject a matching marker.' >&2
    exit 1
fi
assert_no_fixed_marker 'clean fixture' 'guard-fixture-absent-value' "${TEMP}/guard-fixture.txt"
rm -f "${TEMP}/guard-fixture.txt"

"${COMPOSE[@]}" logs --no-color >"${TEMP}/all-containers.log"
"${COMPOSE[@]}" logs --no-color http >"${TEMP}/http-application.log"
test -d "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/generated"
test -d "${ROOT}/examples/community-board/var/log"
for surface in \
    "${TEMP}" \
    "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/generated" \
    "${ROOT}/examples/community-board/var/log"; do
    assert_no_fixed_marker 'Alice session token' "${ALICE_TOKEN}" "${surface}"
    assert_no_fixed_marker 'Bob session token' "${BOB_TOKEN}" "${surface}"
    assert_no_fixed_marker 'registration password' "${PASSWORD_MARKER}" "${surface}"
    assert_no_fixed_marker 'password hash' "${PASSWORD_HASH}" "${surface}"
done
if rg --quiet --glob '!all-containers.log' \
    'SELECT |INSERT |UPDATE public|DELETE FROM|/workspace/|POSTGRES_PASSWORD' \
    "${TEMP}" \
    "${ROOT}/examples/community-board/frontend/src/lib/server/blackops/generated" \
    "${ROOT}/examples/community-board/var/log"; then
    echo 'SQL, absolute path, or database configuration leaked through an application surface.' >&2
    exit 1
else
    SEARCH_STATUS=$?
    if test "${SEARCH_STATUS}" -ne 1; then
        echo 'Application surface guard could not inspect every required path.' >&2
        exit 1
    fi
fi

git -C "${ROOT}" diff --exit-code -- src examples/quickstart
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

printf 'Community Board post and comment journey passed.\n'
