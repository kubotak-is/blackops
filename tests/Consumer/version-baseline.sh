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
contains CHANGELOG.md '未公開の`1.2.0` Release Candidate'
contains CHANGELOG.md '## [1.1.0] - 2026-07-16'
contains UPGRADE.md '## 1.0.0から1.1.0'
contains UPGRADE.md '## 1.1.0から1.2.0 Preview'
contains UPGRADE.md '未公開のRepository `main` candidate'

# Candidate metadata must not be presented as Latest Stable or published.
for file in README.md docs/guide/mvp-status.md docs/guide/mvp-sample.md docs/guide/observability.md CHANGELOG.md UPGRADE.md docs/website/pages/index.astro; do
    absent "${file}" 'Latest Stable `1.2.0`'
    absent "${file}" 'Latest StableはFramework／Skeleton `1.2.0`'
    absent "${file}" '公開済みStable `1.2.0`'
done

printf 'Version baseline guard passed: stable=1.1.0 candidate=1.2.0\n'
