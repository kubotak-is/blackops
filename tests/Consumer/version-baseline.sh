#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

fail() {
    printf 'Version baseline guard failed: %s\n' "$1" >&2
    exit 1
}

contains() {
    local file="$1"
    local pattern="$2"
    grep -Fq -- "$pattern" "${repository_root}/${file}" \
        || fail "${file} does not contain expected version contract: ${pattern}"
}

absent() {
    local file="$1"
    local pattern="$2"
    ! grep -Fq -- "$pattern" "${repository_root}/${file}" \
        || fail "${file} contains a forbidden version claim: ${pattern}"
}

assert_storage_key_contract() {
    local file="$1"

    contains "${file}" 'test -n "${storage_key}"'
    contains "${file}" 'test "${decoded_storage_key_length}" -eq 32'
    contains "${file}" 'test "$(grep -c '\''^BLACKOPS_STORAGE_KEY='\'' "${CONSUMER}/.env")" -eq 1'
    contains "${file}" 'test "$(grep -c '\''^BLACKOPS_STORAGE_KEY=$'\'' "${CONSUMER}/.env")" -eq 0'
    contains "${file}" 'test "$(stat -c '\''%a'\'' "${CONSUMER}/.env")" = 600'
    contains "${file}" 'unset storage_key decoded_storage_key_length'

    awk '
        /^[[:space:]]*umask[[:space:]]+077[[:space:]]*$/ {
            umask_count++
            umask_line = NR
        }
        index($0, "cp \"${CONSUMER}/.env.example\" \"${CONSUMER}/.env\"") {
            env_copy_count++
            env_copy_line = NR
        }
        index($0, "storage_key=\"$(head -c 32 /dev/urandom | base64 -w 0)\"") {
            generation_count++
            generation_line = NR
        }
        index($0, "test -n \"${storage_key}\"") {
            nonempty_count++
            nonempty_line = NR
        }
        index($0, "decoded_storage_key_length=\"$(printf '\''%s'\'' \"${storage_key}\" | base64 --decode | wc -c)\"") {
            decoded_assignment_count++
            decoded_assignment_line = NR
        }
        index($0, "test \"${decoded_storage_key_length}\" -eq 32") {
            decoded_test_count++
            decoded_test_line = NR
        }
        index($0, "sed -i \"s|^BLACKOPS_STORAGE_KEY=.*|BLACKOPS_STORAGE_KEY=${storage_key}|\" \"${CONSUMER}/.env\"") {
            write_count++
            write_line = NR
        }
        index($0, "test \"$(grep -c '\''^BLACKOPS_STORAGE_KEY='\'' \"${CONSUMER}/.env\")\" -eq 1") {
            assignment_count_count++
            assignment_count_line = NR
        }
        index($0, "test \"$(grep -c '\''^BLACKOPS_STORAGE_KEY=$'\'' \"${CONSUMER}/.env\")\" -eq 0") {
            empty_count_count++
            empty_count_line = NR
        }
        index($0, "test \"$(stat -c '\''%a'\'' \"${CONSUMER}/.env\")\" = 600") {
            mode_count++
            mode_line = NR
        }
        /^[[:space:]]*unset[[:space:]]+storage_key[[:space:]]+decoded_storage_key_length[[:space:]]*$/ {
            unset_count++
            unset_line = NR
        }
        umask_line && !first_runtime_line {
            if (/docker[[:space:]]+(run|compose)/ ||
                /composer[[:space:]]/ ||
                /\$\{(COMPOSE|compose|INSTALL_COMPOSE|install_compose)\[@\]\}/) {
                first_runtime_line = NR
            }
        }
        END {
            if (umask_count != 1 || env_copy_count != 1 || generation_count != 1 ||
                nonempty_count != 1 || decoded_assignment_count != 1 || decoded_test_count != 1 ||
                write_count != 1 || assignment_count_count != 1 || empty_count_count != 1 ||
                mode_count != 1 || unset_count != 1 || !first_runtime_line ||
                !(umask_line < env_copy_line && env_copy_line < generation_line &&
                  generation_line < nonempty_line && nonempty_line < decoded_assignment_line &&
                  decoded_assignment_line < decoded_test_line && decoded_test_line < write_line &&
                  write_line < assignment_count_line && assignment_count_line < empty_count_line &&
                  empty_count_line < mode_line && mode_line < unset_line &&
                  unset_line < first_runtime_line)) {
                exit 1
            }
        }
    ' "${repository_root}/${file}" \
        || fail "${file} must preserve fail-closed Storage Key preparation order through its first Docker/Composer command"
}

contains Dockerfile 'COMPOSER_ROOT_VERSION=1.2.0@dev'
contains examples/quickstart/composer.json '"blackops/framework": "^1.2"'
contains src/Internal/Telemetry/TelemetryTracer.php "public const VERSION = '1.2.0';"
contains src/Internal/Telemetry/TelemetryMetrics.php "public const VERSION = '1.2.0';"

for consumer in \
    tests/Consumer/quickstart-e2e.sh \
    tests/Consumer/auth-generator-fresh.sh \
    tests/Consumer/scheduled-operation.sh \
    tests/Consumer/storage-protection-rotation.sh \
    tests/Consumer/frankenphp-worker-mode.sh; do
    contains "${consumer}" 'blackops/framework":"1.2.0'
done

for consumer in \
    tests/Consumer/auth-generator-fresh.sh \
    tests/Consumer/frankenphp-worker-mode.sh \
    tests/Consumer/scheduled-operation.sh; do
    contains "${consumer}" 'umask 077'
    contains "${consumer}" 'storage_key="$(head -c 32 /dev/urandom | base64 -w 0)"'
    contains "${consumer}" 'decoded_storage_key_length="$(printf'
    contains "${consumer}" 'base64 --decode | wc -c)"'
    contains "${consumer}" 'chmod 600 "${CONSUMER}/.env"'
    assert_storage_key_contract "${consumer}"
done

contains tests/Consumer/skeleton-create-project.sh '"blackops/framework": "1.2.0"'
contains tests/Consumer/skeleton-create-project.sh 'blackops/skeleton":"1.2.0"'
contains tests/Consumer/skeleton-publication.sh 'version=1.2.0'
contains tests/Consumer/skeleton-publication-workflow.sh 'run_publication "${new_remote}" 1.2.0 false'

# Stable onboarding and its published CTA remain pinned to the immutable 1.1.0 lane.
contains README.md 'Latest StableはFramework／Skeleton `1.1.0`です。'
contains README.md 'composer create-project blackops/skeleton my-app 1.1.0'
contains docs/website/pages/index.astro 'Stable 1.1.0'
contains docs/website/pages/index.astro 'composer create-project blackops/skeleton my-app 1.1.0'
contains docs/guide/mvp-status.md 'Repository `main`は未公開の`1.2.0` Release Candidateです。'
contains docs/guide/mvp-sample.md 'Repository `main`の`1.2.0` Preview Application'
contains docs/guide/mvp-sample.md '"blackops/framework":"1.2.0"'
contains docs/guide/observability.md 'VersionはRepository `main` candidateの`1.2.0`です。'

contains CHANGELOG.md '## [Unreleased]'
test "$(grep -c '^## \[Unreleased\]$' "${repository_root}/CHANGELOG.md")" -eq 1 \
    || fail 'CHANGELOG.md must contain exactly one Unreleased section'
contains CHANGELOG.md '未公開の`1.2.0` Release Candidate'
contains CHANGELOG.md '## [1.1.0] - 2026-07-16'
contains CHANGELOG.md 'Skeletonは`blackops/framework: ^1.1`を要求する。'
for section in '### Added' '### Changed' '### Removed' '### Fixed' '### Known Limitations'; do
    contains CHANGELOG.md "${section}"
done
for contract in \
    'Version20260808000000.php' \
    'Version20260808010000.php' \
    'CanonicalJournalReader' \
    'OutcomeReader' \
    '9つのCandidate PostgreSQL Migration'; do
    contains CHANGELOG.md "${contract}"
done
contains UPGRADE.md '## 1.0.0から1.1.0'
contains UPGRADE.md '## 1.1.0から1.2.0 Preview'
contains UPGRADE.md 'Repository `main`の未公開`1.2.0` candidate'
for section in \
    '### 1. BackupとRollback境界を固定する' \
    '### 2. Candidate SourceとComposerを準備する' \
    '### 5. Database MigrationをBackup後に順序実行する'; do
    contains UPGRADE.md "${section}"
done
contains UPGRADE.md '**Compatibility-first Lane**'
contains UPGRADE.md '**Opt-in Candidate-Skeleton Lane**'
contains UPGRADE.md "'frontend_manifest' => dirname(__DIR__) . '/var/build/frontend.php'"
contains UPGRADE.md 'Application configuration key "app.build.frontend_manifest" must be a non-empty absolute path.'
contains UPGRADE.md 'Candidate HTTP／Worker Runtimeへ進むOpt-in Laneでは'
contains UPGRADE.md 'Storage protection provider is required for application bootstrap.'
contains UPGRADE.md "'services' => ["
contains UPGRADE.md '`app/ApplicationServiceProvider.php`へ次の完全なApplication-owned Provider'
contains UPGRADE.md 'namespace App;'
contains UPGRADE.md 'final readonly class ApplicationServiceProvider implements ServiceProvider'
contains UPGRADE.md 'app/Security/SampleStorageKeyProvider.php'
contains UPGRADE.md 'cp .env.example .env'
contains UPGRADE.md 'docker compose --profile worker up -d worker'
contains UPGRADE.md 'docker compose build app http'
contains UPGRADE.md 'docker compose run --rm app php blackops database:migrate'
contains UPGRADE.md 'Provider-presentのHTTP／Worker Positive'
absent UPGRADE.md 'Provider-presentのDatabase Migration／HTTP／Worker Positive lane'
contains UPGRADE.md 'set -euo pipefail'
contains UPGRADE.md 'cleanup() { rm -f .env; docker compose down >/dev/null 2>&1 || true; }'
absent UPGRADE.md 'cleanup() { docker compose down; rm -f .env; }'
contains UPGRADE.md '同じDisposable Application RootのShellで順に実行します'
contains UPGRADE.md 'HTTP／Worker safe Negative'
contains UPGRADE.md '両lane共通のDatabase migration/setup（DDL guard evidence）'
contains UPGRADE.md 'Fresh Disposable laneでは、まずStable `1.1.0`の`database:status`が`applied: 0`／`pending: 2`'
contains UPGRADE.md 'Do not run Stable database:status after this migrate.'
contains UPGRADE.md 'Framework-only Candidate update／strict validate'
contains UPGRADE.md 'Candidate status 2/9'
contains UPGRADE.md 'Candidate dry-run／migrate'
contains UPGRADE.md 'Runtime Consumerで検証済みのmerge'
contains UPGRADE.md 'blackops`、Caddyfile、ComposeはStable `1.1.0`のまま保持し、コピー／上書きしない。'
contains UPGRADE.md 'tests/Consumer/framework-update-runtime.sh'
contains UPGRADE.md 'blackops.schema_migrations'
contains UPGRADE.md 'Version20260712000000'
contains UPGRADE.md 'operations_payload_tombstone_check'
contains UPGRADE.md 'cmp ../blackops/examples/quickstart/bootstrap/app.php bootstrap/app.php'
contains UPGRADE.md 'git -C ../blackops diff 1.1.0..main -- examples/quickstart'
contains UPGRADE.md '-v ON_ERROR_STOP=1'
contains docs/website/tests/guide-code.test.mjs 'P22-003 upgrade order and runtime merge matrix stay executable'
contains UPGRADE.md 'exact body `{"message":"Welcome to BlackOps"}`'
contains UPGRADE.md 'docker compose ps --status running --services | grep -Fxq worker'
contains UPGRADE.md "grep -Eiq '^HTTP/[^[:space:]]+[[:space:]]+200([[:space:]]|$)'"
contains UPGRADE.md "grep -Eiq '^content-type:[[:space:]]*application/json([;[:space:]]|$)'"
contains UPGRADE.md 'for attempt in 1 2 3 4 5; do'
contains UPGRADE.md "curl -fsS -H 'X-Sample-Token: local-example' -D \"\${response_headers}\" -o \"\${response_body}\" http://127.0.0.1:8080/welcome"
contains docs/guide/installation.md "curl -i -H 'X-Sample-Token: local-example' http://127.0.0.1:8080/welcome"
contains docs/guide/runtime-bootstrap.md 'Stable `1.1.0`の`/welcome`は`#[Authorize]`を持たない認可匿名'
contains docs/guide/mvp-sample.md 'Stable Tagの`WelcomeValue`は必須の機密`X-Sample-Token` Header Value'
contains docs/guide/mvp-sample.md "#[FromHeader('X-Sample-Token')]"
contains docs/website/tests/guide-code.test.mjs 'required value header'
absent UPGRADE.md "grep -Fiq '^HTTP/.* 200'"
absent UPGRADE.md "grep -Fiq '^content-type: application/json'"
absent UPGRADE.md 'sed -i "s/^BLACKOPS_STORAGE_KEY='
test "$(git -C "${repository_root}" show 1.1.0:examples/quickstart/.env.example | grep -c '^BLACKOPS_STORAGE_KEY=')" -eq 0 \
    || fail 'Stable 1.1.0 unexpectedly contains a storage key environment line'
test "$(grep -c '^BLACKOPS_STORAGE_KEY=' "${repository_root}/examples/quickstart/.env.example")" -eq 1 \
    || fail 'Current quickstart must contain exactly one storage key environment line'
absent UPGRADE.md 'Consumer後は同じApplication-owned SourceをComposeへ手動で配置'
contains docs/internal/installed-application-status.md "'frontend_manifest' => dirname(__DIR__) . '/var/build/frontend.php'"
contains docs/internal/installed-application-status.md 'P22-003 fixed-SHA Full Gate'
for contract in \
    'blackops/framework:^1.2' \
    'Version20260808000000.php' \
    'tests/Consumer/framework-update-generators.sh'; do
    contains UPGRADE.md "${contract}"
done
contains tests/Consumer/framework-update-generators.sh "cat-file -t refs/tags/1.1.0"
contains tests/Consumer/framework-update-generators.sh 'blackops/framework:1.2.0'
contains tests/Consumer/framework-update-generators.sh 'tag 1.2.0'
contains tests/Consumer/framework-update-generators.sh 'blackops build:compile'
contains tests/Consumer/framework-update-generators.sh 'blackops operation:list'
test -x "${repository_root}/tests/Consumer/framework-update-runtime.sh" \
    || fail 'Runtime consumer must be executable'
contains tests/Consumer/framework-update-runtime.sh "cat-file -t refs/tags/1.1.0"
contains tests/Consumer/framework-update-runtime.sh 'migrations=11'
contains tests/Consumer/framework-update-runtime.sh 'Migration status mismatch at %s:'
contains tests/Consumer/framework-update-runtime.sh 'assert_migration_status stable-before-migrate 0 2'
contains tests/Consumer/framework-update-runtime.sh 'Stable post-migrate status diagnostic changed'
contains tests/Consumer/framework-update-runtime.sh 'blackops.schema_migrations'
contains tests/Consumer/framework-update-runtime.sh 'Version20260712000000'
contains tests/Consumer/framework-update-runtime.sh 'operations_payload_tombstone_check'
contains tests/Consumer/framework-update-runtime.sh 'assert_migration_status candidate-before-migrate 2 9'
contains tests/Consumer/framework-update-runtime.sh 'assert_migration_status candidate-after-migrate 11 0'
contains tests/Consumer/framework-update-runtime.sh 'config merge failed: expected unique HTTP/frontend manifest markers'
contains tests/Consumer/framework-update-runtime.sh 'final root closure was not uniquely located'
contains tests/Consumer/framework-update-runtime.sh 'file_put_contents($path, $source)'
contains tests/Consumer/framework-update-runtime.sh "-H 'X-Sample-Token: local-example'"
contains tests/Consumer/framework-update-runtime.sh '$quote = chr(39);'
contains tests/Consumer/framework-update-runtime.sh 'http_port=$((18080 + RANDOM % 1000))'
contains tests/Consumer/framework-update-runtime.sh 'provider-present=http-worker'
contains tests/Consumer/framework-update-runtime.sh 'classic_http_port=$((http_port + 1))'
contains tests/Consumer/framework-update-runtime.sh 'provider-missing=classic-http-worker-safe-negative'
contains tests/Consumer/framework-update-runtime.sh '--profile classic-mode up -d http-classic'
contains tests/Consumer/framework-update-runtime.sh 'classic-http.log'
contains tests/Consumer/framework-update-runtime.sh 'fail_stage()'
contains tests/Consumer/framework-update-runtime.sh 'provider-missing-classic-http-readiness'
contains tests/Consumer/framework-update-runtime.sh 'provider-missing-redaction'
contains tests/Consumer/framework-update-runtime.sh 'provider-missing-services-removal'
contains tests/Consumer/framework-update-runtime.sh 'verify_runtime_bootstrap()'
contains tests/Consumer/framework-update-runtime.sh 'bootstrap/app.php'
contains tests/Consumer/framework-update-runtime.sh 'public/index.php'
contains tests/Consumer/framework-update-runtime.sh 'public/worker.php'
contains tests/Consumer/framework-update-runtime.sh 'runtime-bootstrap-drift'
contains tests/Consumer/framework-update-runtime.sh 'Storage protection provider is required for application bootstrap.'
contains tests/Consumer/framework-update-runtime.sh 'BLACKOPS_STORAGE_KEY|BOPD|SQLSTATE|PDO'
contains tests/Consumer/framework-update-runtime.sh 'rm -f "${consumer_root}/.env"'
contains tests/Consumer/framework-update-runtime.sh 'down --volumes --remove-orphans --rmi local'
contains tests/Consumer/framework-update-runtime.sh 'display_errors=0 blackops worker:run'
contains tests/Consumer/framework-update-runtime.sh 'trap '\''exit 130'\'' INT TERM'
contains .github/workflows/ci.yml 'framework-update-runtime:'
contains .github/workflows/ci.yml 'bash tests/Consumer/framework-update-runtime.sh'
contains .github/workflows/ci.yml 'fetch-depth: 0'
contains .github/workflows/ci.yml 'HOST_UID=%s\n'

# Candidate metadata must not be presented as Latest Stable or published.
for file in README.md docs/guide/mvp-status.md docs/guide/mvp-sample.md docs/guide/observability.md CHANGELOG.md UPGRADE.md docs/website/pages/index.astro; do
    absent "${file}" 'Latest Stable `1.2.0`'
    absent "${file}" 'Latest StableはFramework／Skeleton `1.2.0`'
    absent "${file}" '公開済みStable `1.2.0`'
done

printf 'Version baseline guard passed: stable=1.1.0 candidate=1.2.0\n'
