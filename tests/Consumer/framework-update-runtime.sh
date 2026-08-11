#!/usr/bin/env bash

set -euo pipefail

case $- in
    *x*) set +x ;;
esac

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
temporary_root="$(mktemp -d)"
framework_repository="${temporary_root}/framework"
consumer_root="${temporary_root}/consumer"
compose_override="${temporary_root}/compose.override.yaml"
project_name="blackops-runtime-${RANDOM}-$$"
http_port=$((18080 + RANDOM % 1000))
classic_http_port=$((http_port + 1))
source_before="$(git -C "${repository_root}" status --short)"
candidate_commit="$(git -C "${repository_root}" rev-parse HEAD)"
compose=()

fail() {
    printf 'Framework update runtime consumer failed.\n' >&2
    exit 1
}

fail_stage() {
    printf 'Provider-missing stage failed: %s\n' "$1" >&2
    exit 1
}

verify_runtime_bootstrap() {
    local stage="$1"
    local path

    for path in bootstrap/app.php public/index.php public/worker.php; do
        if ! cmp "${framework_repository}/examples/quickstart/${path}" "${consumer_root}/${path}" >/dev/null 2>&1; then
            fail_stage "${stage}-runtime-bootstrap-drift"
        fi
    done
}

cleanup() {
    status=$?
    set +e

    if test -e "${consumer_root}/.env"; then
        rm -f "${consumer_root}/.env"
    fi
    if test "${#compose[@]}" -gt 0; then
        "${compose[@]}" --profile worker --profile classic-mode down --volumes --remove-orphans --rmi local >/dev/null 2>&1 || status=1
        if docker ps -aq --filter "label=com.docker.compose.project=${project_name}" | grep -q .; then
            status=1
        fi
        if docker volume ls -q --filter "label=com.docker.compose.project=${project_name}" | grep -q .; then
            status=1
        fi
        if docker network ls -q --filter "label=com.docker.compose.project=${project_name}" | grep -q .; then
            status=1
        fi
    fi

    if test -e "${consumer_root}/.env"; then
        status=1
    fi
    rm -rf "${temporary_root}"

    if test "$(git -C "${repository_root}" status --short)" != "${source_before}"; then
        status=1
    fi

    exit "${status}"
}
trap cleanup EXIT
trap 'exit 130' INT TERM

mkdir -p "${consumer_root}"
git clone --quiet --no-hardlinks "${repository_root}" "${framework_repository}"
stable_commit="$(git -C "${framework_repository}" rev-parse 'refs/tags/1.1.0^{commit}')"
test "$(git -C "${framework_repository}" cat-file -t refs/tags/1.1.0)" = tag
test "${stable_commit}" = "$(git -C "${repository_root}" rev-parse 'refs/tags/1.1.0^{commit}')"
git -C "${framework_repository}" checkout --quiet 1.1.0
test -z "$(git -C "${framework_repository}" status --short)"
git -C "${framework_repository}" archive 1.1.0:examples/quickstart | tar -x -C "${consumer_root}"

snapshot_sources() {
    local output="$1"

    (
        cd "${consumer_root}"
        find . -type f \
            ! -path './vendor/*' \
            ! -path './var/build/*' \
            ! -path './var/log/*' \
            ! -path './.env' \
            ! -path './composer.json' \
            ! -path './composer.lock' \
            ! -path './config/app.php' \
            ! -path './app/ApplicationServiceProvider.php' \
            ! -path './app/Security/SampleStorageKeyProvider.php' \
            ! -path './bootstrap/app.php' \
            ! -path './public/index.php' \
            ! -path './public/worker.php' \
            -print0 \
            | sort -z \
            | xargs -0 sha256sum
    ) >"${output}"
}

snapshot_sources "${temporary_root}/sources.before.sha256"

umask 077
cp "${consumer_root}/.env.example" "${consumer_root}/.env"
chmod 600 "${consumer_root}/.env"
sed -i \
    -e "s/^HTTP_PORT=.*/HTTP_PORT=${http_port}/" \
    -e "s/^CLASSIC_HTTP_PORT=.*/CLASSIC_HTTP_PORT=${classic_http_port}/" \
    -e "s/^HOST_UID=.*/HOST_UID=$(id -u)/" \
    -e "s/^HOST_GID=.*/HOST_GID=$(id -g)/" \
    "${consumer_root}/.env"
test "$(grep -c '^BLACKOPS_STORAGE_KEY=' "${consumer_root}/.env")" -eq 0

cat >"${compose_override}" <<YAML
services:
  app:
    volumes:
      - ${framework_repository}:/framework:ro
  http:
    volumes:
      - ${framework_repository}:/framework:ro
  http-classic:
    volumes:
      - ${framework_repository}:/framework:ro
YAML

compose=(docker compose --project-directory "${consumer_root}" --project-name "${project_name}" \
    -f "${consumer_root}/compose.yaml" -f "${compose_override}")

docker run --rm --user "$(id -u):$(id -g)" \
    --volume "${consumer_root}:/app" \
    --volume "${framework_repository}:/framework:ro" \
    --workdir /app composer:2 \
    composer config repositories.framework \
    '{"type":"path","url":"/framework","options":{"symlink":false,"versions":{"blackops/framework":"1.1.0"}}}'

"${compose[@]}" build app http http-classic >/dev/null
"${compose[@]}" up -d postgres >/dev/null
"${compose[@]}" run --rm app composer install --no-interaction --prefer-dist --no-progress \
    >"${temporary_root}/composer-install.log"

run_app() {
    "${compose[@]}" run --rm app php "$@"
}

psql() {
    "${compose[@]}" exec -T postgres psql -U blackops -d blackops -At -v ON_ERROR_STOP=1 -c "$1"
}

assert_migration_status() {
    local stage="$1"
    local expected_applied="$2"
    local expected_pending="$3"
    local status="$4"

    if ! grep -q "^applied: ${expected_applied}$" <<<"${status}" \
        || ! grep -q "^pending: ${expected_pending}$" <<<"${status}"; then
        printf 'Migration status mismatch at %s:\n%s\n' "${stage}" "${status}" >&2
        exit 1
    fi
}

stable_framework_version="$(run_app -r '
$lock = json_decode(file_get_contents("/app/composer.lock"), true, 512, JSON_THROW_ON_ERROR);
foreach ($lock["packages"] ?? [] as $package) {
    if (($package["name"] ?? null) === "blackops/framework") {
        echo (string) ($package["version"] ?? "");
        exit(0);
    }
}
exit(1);
')"
test "${stable_framework_version}" = 1.1.0

status_before="$(run_app blackops database:status)"
assert_migration_status stable-before-migrate 0 2 "${status_before}"
run_app blackops database:migrate >"${temporary_root}/stable-database-migrate.log"

# Preserve the reproduced Stable diagnostic without using it as migration
# evidence: the next Stable process may still report the same misleading count.
stable_status_after="$(run_app blackops database:status)"
if ! grep -q '^applied: 0$' <<<"${stable_status_after}" \
    || ! grep -q '^pending: 2$' <<<"${stable_status_after}"; then
    printf 'Stable post-migrate status diagnostic changed; catalog evidence remains authoritative.\n' >&2
fi

# Stable 1.1.0 can misreport current-schema metadata when the database role and
# schema share a name. Verify the one-time migration through read-only catalog
# queries instead of trusting that misleading status output or rerunning migrate.
stable_metadata_rows="$(psql "SELECT count(*) FROM blackops.schema_migrations WHERE version IN ('BlackOps\\Migrations\\PostgreSql\\Version20260712000000', 'BlackOps\\Migrations\\PostgreSql\\Version20260712010000')")"
test "${stable_metadata_rows}" = 2
test "$(psql 'SELECT count(*) FROM blackops.schema_migrations')" = 2
test "$(psql "SELECT count(*) FROM pg_tables WHERE schemaname = 'blackops' AND tablename IN ('operations', 'journal', 'outcomes', 'dead_letters', 'retention_holds', 'retention_purge_audits')")" = 6
test "$(psql "SELECT count(*) FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid JOIN pg_namespace n ON n.oid = t.relnamespace WHERE n.nspname = 'blackops' AND c.conname IN ('operations_payload_tombstone_check', 'outcomes_operation_id_fkey')")" = 2

git -C "${framework_repository}" checkout --quiet "${candidate_commit}"
test "$(git -C "${framework_repository}" rev-parse HEAD)" = "${candidate_commit}"
test -z "$(git -C "${framework_repository}" status --short)"
git -C "${framework_repository}" tag -a -m 'local runtime candidate' 1.2.0 "${candidate_commit}"
docker run --rm --user "$(id -u):$(id -g)" \
    --volume "${consumer_root}:/app" \
    --workdir /app composer:2 \
    composer require --no-update --no-interaction blackops/framework:^1.2
docker run --rm --user "$(id -u):$(id -g)" \
    --volume "${consumer_root}:/app" \
    --volume "${framework_repository}:/framework:ro" \
    --workdir /app composer:2 \
    composer config repositories.framework \
    '{"type":"vcs","url":"/framework"}'
"${compose[@]}" run --rm app composer update --no-interaction --prefer-dist --no-progress blackops/framework \
    >"${temporary_root}/candidate-framework-update.log"
candidate_framework_version="$(run_app -r '
$lock = json_decode(file_get_contents("/app/composer.lock"), true, 512, JSON_THROW_ON_ERROR);
foreach ($lock["packages"] ?? [] as $package) {
    if (($package["name"] ?? null) === "blackops/framework") {
        echo (string) ($package["version"] ?? "");
        exit(0);
    }
}
exit(1);
')"
test "${candidate_framework_version}" = 1.2.0

for runtime_path in bootstrap/app.php public/index.php public/worker.php; do
    cp "${framework_repository}/examples/quickstart/${runtime_path}" "${consumer_root}/${runtime_path}"
done
verify_runtime_bootstrap candidate-initial

storage_key="$(openssl rand -base64 32)"
test "$(printf '%s' "${storage_key}" | base64 --decode | wc -c)" -eq 32
printf 'BLACKOPS_STORAGE_KEY=%s\n' "${storage_key}" >>"${consumer_root}/.env"
unset storage_key
test "$(grep -c '^BLACKOPS_STORAGE_KEY=' "${consumer_root}/.env")" -eq 1
test "$(stat -c '%a' "${consumer_root}/.env")" = 600

mkdir -p "${consumer_root}/app/Security"
cp "${framework_repository}/examples/quickstart/app/Security/SampleStorageKeyProvider.php" \
    "${consumer_root}/app/Security/SampleStorageKeyProvider.php"
cat >"${consumer_root}/app/ApplicationServiceProvider.php" <<'PHP'
<?php

declare(strict_types=1);

namespace App;

use App\Security\SampleStorageKeyProvider;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\StorageProtection\StorageKeyProvider;

final readonly class ApplicationServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(StorageKeyProvider::class, SampleStorageKeyProvider::class);
    }
}
PHP

docker run --rm --user "$(id -u):$(id -g)" \
    --volume "${consumer_root}:/app" \
    --workdir /app composer:2 php -r '
$path = "config/app.php";
$source = file_get_contents($path);
if (!is_string($source)) {
    fwrite(STDERR, "config merge failed: unable to read config/app.php\n");
    exit(1);
}
$quote = chr(39);
$httpManifest = "        {$quote}http_manifest{$quote} => dirname(__DIR__) . {$quote}/var/build/http.php{$quote},";
$frontendManifest = "        {$quote}frontend_manifest{$quote} => dirname(__DIR__) . {$quote}/var/build/frontend.php{$quote},";
$httpCount = substr_count($source, $httpManifest);
$frontendCount = substr_count($source, $frontendManifest);
if ($httpCount !== 1 || $frontendCount > 1) {
    fwrite(STDERR, "config merge failed: expected unique HTTP/frontend manifest markers\n");
    exit(2);
}
if ($frontendCount === 0) {
    $source = str_replace($httpManifest, $httpManifest . "\n" . $frontendManifest, $source, $replacements);
    if ($replacements !== 1) {
        fwrite(STDERR, "config merge failed: frontend manifest insertion was not unique\n");
        exit(3);
    }
}
$services = "    {$quote}services{$quote} => [\n        App\\ApplicationServiceProvider::class,\n    ],\n";
$serviceCount = substr_count($source, $services);
if ($serviceCount > 1) {
    fwrite(STDERR, "config merge failed: services section is not unique\n");
    exit(4);
}
if ($serviceCount === 0) {
    $updated = preg_replace("/\\n\\];\\n?\\z/", "\n" . $services . "];\n", $source, 1, $rootReplacements);
    if (!is_string($updated) || $rootReplacements !== 1) {
        fwrite(STDERR, "config merge failed: final root closure was not uniquely located\n");
        exit(5);
    }
    $source = $updated;
}
$written = file_put_contents($path, $source);
if (!is_int($written) || $written !== strlen($source)) {
    fwrite(STDERR, "config merge failed: config/app.php write was incomplete\n");
    exit(6);
}
'

run_app blackops build:compile >"${temporary_root}/candidate-build.log"
candidate_status="$(run_app blackops database:status)"
assert_migration_status candidate-before-migrate 2 9 "${candidate_status}"
run_app blackops database:migrate --dry-run >"${temporary_root}/candidate-database-dry-run.log"
run_app blackops database:migrate >"${temporary_root}/candidate-database-migrate.log"
status_after="$(run_app blackops database:status)"
assert_migration_status candidate-after-migrate 11 0 "${status_after}"
grep -q 'Version20260808100000' <<<"${status_after}"

test "$(psql "SELECT count(*) FROM information_schema.columns WHERE table_schema = 'blackops' AND table_name = 'journal' AND column_name = 'operation_schema_version'")" = 1
test "$(psql "SELECT count(*) FROM pg_tables WHERE schemaname = 'blackops' AND tablename IN ('storage_protection_rotation_checkpoints', 'storage_protection_rotation_audits')")" = 2
ddl_constraints="$(psql "SELECT count(*) FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid JOIN pg_namespace n ON n.oid = t.relnamespace WHERE n.nspname = 'blackops' AND c.conname IN ('journal_bopd_envelope_check', 'operations_bopd_payload_check', 'outcomes_bopd_payload_check', 'outbox_records_bopd_payload_check', 'dead_letters_bopd_reason_check', 'idempotency_record_response_bopd_check', 'idempotency_record_result_bopd_check')")"
test "${ddl_constraints}" -ge 7

"${compose[@]}" up -d http >/dev/null
"${compose[@]}" --profile worker up -d worker >/dev/null

worker_running=0
for _ in $(seq 1 30); do
    if "${compose[@]}" --profile worker ps --status running --services | grep -Fxq worker; then
        worker_running=1
        break
    fi
    sleep 1
done
test "${worker_running}" -eq 1

worker_output="$("${compose[@]}" --profile worker run --rm worker php blackops worker:run --iterations 1 --idle-sleep-milliseconds 1)"
grep -q '^Worker stopped\. Processed claims: 0$' <<<"${worker_output}"

positive_headers="${temporary_root}/positive.headers"
positive_body="${temporary_root}/positive.body"
http_ready=0
for _ in $(seq 1 30); do
    if curl --fail --silent --show-error --max-time 5 \
        -D "${positive_headers}" -o "${positive_body}" \
        -H 'X-Sample-Token: local-example' \
        "http://127.0.0.1:${http_port}/welcome"; then
        http_ready=1
        break
    fi
    sleep 1
done
test "${http_ready}" -eq 1
grep -Eiq '^HTTP/[^[:space:]]+[[:space:]]+200([[:space:]]|$)' "${positive_headers}"
grep -Eiq '^content-type:[[:space:]]*application/json([;[:space:]]|$)' "${positive_headers}"
test "$(<"${positive_body}")" = '{"message":"Welcome to BlackOps"}'

snapshot_sources "${temporary_root}/sources.after-positive.sha256"
cmp "${temporary_root}/sources.before.sha256" "${temporary_root}/sources.after-positive.sha256"
verify_runtime_bootstrap after-positive

"${compose[@]}" stop http worker >/dev/null 2>&1 || true
docker run --rm --user "$(id -u):$(id -g)" \
    --volume "${consumer_root}:/app" \
    --workdir /app composer:2 php -r '
$path = "config/app.php";
$source = file_get_contents($path);
$quote = chr(39);
$needle = "    {$quote}services{$quote} => [\n        App\\ApplicationServiceProvider::class,\n    ],\n";
if (!is_string($source) || substr_count($source, $needle) !== 1) {
    exit(1);
}
file_put_contents($path, str_replace($needle, "", $source));
'
grep -q "'services' =>" "${consumer_root}/config/app.php" \
    && fail_stage provider-missing-services-removal
run_app blackops build:compile >"${temporary_root}/provider-missing-build.log"

missing_message_status=0
"${compose[@]}" run --rm app php -r '
require "/app/vendor/autoload.php";
$application = require "/app/bootstrap/app.php";
try {
    $application->http();
    exit(1);
} catch (Throwable $exception) {
    for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
        if ($current->getMessage() === "Storage protection provider is required for application bootstrap.") {
            exit(0);
        }
    }
    exit(2);
}
' || missing_message_status=$?
test "${missing_message_status}" -eq 0 \
    || fail_stage provider-missing-http-preflight

"${compose[@]}" --profile classic-mode up -d http-classic >/dev/null
negative_headers="${temporary_root}/negative.headers"
negative_body="${temporary_root}/negative.body"
classic_http_log="${temporary_root}/classic-http.log"
negative_status=''
for _ in $(seq 1 30); do
    negative_status=$(curl --silent --show-error --max-time 5 \
        -D "${negative_headers}" -o "${negative_body}" \
        -H 'X-Sample-Token: local-example' \
        --write-out '%{http_code}' "http://127.0.0.1:${classic_http_port}/welcome" || true)
    if test "${negative_status}" = 500; then
        break
    fi
    sleep 1
done
test "${negative_status}" = 500 || fail_stage provider-missing-classic-http-readiness
grep -Eiq '^HTTP/[^[:space:]]+[[:space:]]+500([[:space:]]|$)' "${negative_headers}" \
    || fail_stage provider-missing-classic-http-status
grep -Eiq '^content-type:[[:space:]]*application/json([;[:space:]]|$)' "${negative_headers}" \
    || fail_stage provider-missing-classic-http-content-type
test "$(<"${negative_body}")" = '{"status":"error","code":"internal_error"}' \
    || fail_stage provider-missing-classic-http-body
"${compose[@]}" --profile classic-mode logs --no-color http-classic >"${classic_http_log}" 2>&1 || true

"${compose[@]}" --profile classic-mode stop http-classic >/dev/null 2>&1 || true
worker_message_status=0
"${compose[@]}" --profile worker run --rm worker php -r '
require "/app/vendor/autoload.php";
$application = require "/app/bootstrap/app.php";
try {
    $application->http();
    exit(1);
} catch (Throwable $exception) {
    for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
        if ($current->getMessage() === "Storage protection provider is required for application bootstrap.") {
            exit(0);
        }
    }
    exit(2);
}
' || worker_message_status=$?
test "${worker_message_status}" -eq 0 \
    || fail_stage provider-missing-worker-preflight
worker_negative_status=0
"${compose[@]}" --profile worker run --rm --no-deps worker \
    php -d display_errors=0 blackops worker:run --iterations 1 --idle-sleep-milliseconds 1 \
    >"${temporary_root}/worker-negative.log" 2>&1 || worker_negative_status=$?
test "${worker_negative_status}" -ne 0 || fail_stage provider-missing-worker-cli
! "${compose[@]}" --profile worker ps --status running --services | grep -Fxq worker \
    || fail_stage provider-missing-worker-not-running

if grep -R -E 'BLACKOPS_STORAGE_KEY|BOPD|SQLSTATE|PDO|Stack trace|Traceback|vendor/blackops|tenant_id|actor_id' \
    "${consumer_root}/var/log" "${temporary_root}/worker-negative.log" \
    "${negative_headers}" "${negative_body}" "${classic_http_log}" >/dev/null 2>&1; then
    fail_stage provider-missing-redaction
fi

snapshot_sources "${temporary_root}/sources.after-negative.sha256"
verify_runtime_bootstrap after-negative
while read -r hash path; do
    test "$(sha256sum "${consumer_root}/${path#./}" | awk '{print $1}')" = "${hash}" \
        || fail_stage provider-missing-source-invariant
done <"${temporary_root}/sources.before.sha256"

printf 'Framework update runtime consumer passed: stable=%s candidate=%s migrations=11 provider-present=http-worker provider-missing=classic-http-worker-safe-negative.\n' \
    "${stable_commit}" "${candidate_commit}"
