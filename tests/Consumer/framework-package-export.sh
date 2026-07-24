#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
temporary_root="$(mktemp -d)"
git_package="${temporary_root}/git-package"
composer_output="${temporary_root}/composer-output"
composer_package="${temporary_root}/composer-package"
composer_home="${temporary_root}/composer-home"
source_before="$(git -C "${repository_root}" status --short)"

cleanup() {
    rm -rf "${temporary_root}"
}
trap cleanup EXIT

fail() {
    printf 'Framework package export validation failed: %s\n' "$1" >&2
    exit 1
}

container=(
    timeout --signal=TERM 120
    docker run --rm
    --user "$(id -u):$(id -g)"
    --volume "${repository_root}:/repository:ro"
    --volume "${temporary_root}:/package-test"
    --env COMPOSER_HOME=/package-test/composer-home
)

mkdir -p "${git_package}" "${composer_output}" "${composer_package}" "${composer_home}"

attribute_excludes="$({
    awk '$2 == "export-ignore" { print $1 }' "${repository_root}/.gitattributes"
} | sort)"
composer_excludes="$(
    "${container[@]}" --workdir /repository blackops/framework:dev php -r '
$composer = json_decode(file_get_contents("composer.json"), true, 512, JSON_THROW_ON_ERROR);
$excludes = $composer["archive"]["exclude"] ?? [];
sort($excludes, SORT_STRING);
foreach ($excludes as $exclude) {
    echo $exclude, "\n";
}
'
)"
if [[ "${attribute_excludes}" != "${composer_excludes}" ]]; then
    printf 'gitattributes export-ignore paths:\n%s\n' "${attribute_excludes}" >&2
    printf 'Composer archive.exclude paths:\n%s\n' "${composer_excludes}" >&2
    fail '.gitattributes and composer.json archive exclusions differ'
fi

git -C "${repository_root}" archive --worktree-attributes --format=tar HEAD \
    | tar -xf - -C "${git_package}"

"${container[@]}" --workdir /repository blackops/framework:dev composer archive \
    --format=tar \
    --dir=/package-test/composer-output \
    --file=framework-composer >/dev/null
composer_archive="${composer_output}/framework-composer.tar"
test -f "${composer_archive}" || fail 'Composer archive was not created'
tar -xf "${composer_archive}" -C "${composer_package}"

validate_package() {
    local package_name="$1"
    local package_root="$2"
    local required_path
    local excluded_path
    local actual_roots
    local allowed_roots

    for required_path in \
        composer.json \
        LICENSE \
        README.md \
        CHANGELOG.md \
        UPGRADE.md \
        migrations/postgresql/Version20260712000000.php \
        migrations/postgresql/Version20260712010000.php \
        migrations/postgresql/Version20260724010000.php \
        resources/stubs/operation.php.stub \
        resources/stubs/operation-value.php.stub \
        resources/stubs/operation-outcome.php.stub \
        resources/stubs/migration.php.stub \
        resources/stubs/auth-config.php.stub \
        resources/stubs/auth-service-provider.php.stub \
        resources/stubs/auth-register.php.stub \
        resources/stubs/auth-login.php.stub \
        resources/stubs/auth-logout.php.stub \
        resources/stubs/auth-user-migration.php.stub \
        resources/stubs/auth-session-migration.php.stub; do
        test -f "${package_root}/${required_path}" \
            || fail "${package_name} archive is missing required path: ${required_path}"
    done

    test -d "${package_root}/src" || fail "${package_name} archive is missing src/"
    test -n "$(find "${package_root}/src" -type f -name '*.php' -print -quit)" \
        || fail "${package_name} archive has no PHP source"

    for excluded_path in \
        .gitattributes \
        .agents \
        .codex \
        .github \
        develop \
        docs \
        examples \
        tests \
        .deptrac.cache \
        .dockerignore \
        .env \
        .env.example \
        .gitignore \
        .phpunit.cache \
        AGENTS.md \
        Dockerfile \
        Dockerfile.frankenphp \
        compose.yaml \
        composer.lock \
        deptrac.yaml \
        mago.toml \
        mise.toml \
        phpunit.xml \
        runtime \
        vendor; do
        test ! -e "${package_root}/${excluded_path}" \
            || fail "${package_name} archive contains excluded path: ${excluded_path}"
    done

    test -z "$(find "${package_root}" -type l -print -quit)" \
        || fail "${package_name} archive contains a symbolic link"

    allowed_roots=$'CHANGELOG.md\nLICENSE\nREADME.md\nUPGRADE.md\ncomposer.json\nmigrations\nresources\nsrc'
    actual_roots="$(find "${package_root}" -mindepth 1 -maxdepth 1 -printf '%f\n' | sort)"
    if [[ "${actual_roots}" != "${allowed_roots}" ]]; then
        printf '%s archive root inventory:\n%s\n' "${package_name}" "${actual_roots}" >&2
        fail "${package_name} archive contains an unexpected root path"
    fi

    "${container[@]}" --workdir "/package-test/${package_name}-package" \
        blackops/framework:dev composer validate --strict >/dev/null
    "${container[@]}" --workdir "/package-test/${package_name}-package" \
        blackops/framework:dev \
        composer dump-autoload --no-dev --classmap-authoritative --no-interaction >/dev/null
    test -f "${package_root}/vendor/autoload.php" \
        || fail "${package_name} archive did not generate a production autoloader"
}

validate_package git "${git_package}"
validate_package composer "${composer_package}"

test "$(git -C "${repository_root}" status --short)" = "${source_before}" \
    || fail 'main working tree changed during package validation'

cleanup
trap - EXIT
test ! -e "${temporary_root}" || fail 'temporary package tree was not removed'

echo 'Framework Git and Composer package export contract passed.'
