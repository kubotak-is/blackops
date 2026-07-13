#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
version="${1:-}"
source_ref="${2:-}"

fail() {
    printf 'Skeleton publication validation failed: %s\n' "$1" >&2
    exit 1
}

validate_version() {
    [[ "$1" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]
}

for invalid_version in '' 1 1.0 v1.0.0 01.0.0 1.0.0-alpha 1.0.0+build; do
    if validate_version "${invalid_version}"; then
        fail "the bare SemVer validator accepted '${invalid_version}'"
    fi
done

validate_version "${version}" || fail "version must be a bare MAJOR.MINOR.PATCH SemVer"
[[ "${source_ref}" =~ ^[A-Za-z0-9][A-Za-z0-9._/-]*$ ]] || fail "source ref contains unsupported characters"
[[ "${source_ref}" != *..* && "${source_ref}" != */ && "${source_ref}" != *. ]] \
    || fail "source ref is not canonical"

source_commit="$(git -C "${repository_root}" rev-parse --verify "${source_ref}^{commit}" 2>/dev/null)" \
    || fail "source ref does not resolve to a commit"

temporary_root="$(mktemp -d)"
source_clone="${temporary_root}/source"
distribution_root="${temporary_root}/distribution"
source_before="$(git -C "${repository_root}" status --short)"
containers_before="$(docker ps -aq | sort)"
images_before="$(docker image ls -aq | sort -u)"
networks_before="$(docker network ls -q | sort)"
volumes_before="$(docker volume ls -q | sort)"

cleanup() {
    rm -rf "${temporary_root}"
}
trap cleanup EXIT

git clone --quiet --no-hardlinks "${repository_root}" "${source_clone}"
git -C "${source_clone}" cat-file -e "${source_commit}^{commit}" \
    || fail "source commit is not available in the committed clone"

split_commit="$(git -C "${source_clone}" subtree split --prefix=examples/quickstart "${source_commit}" 2> "${temporary_root}/split.log")"
repeat_split_commit="$(git -C "${source_clone}" subtree split --prefix=examples/quickstart "${source_commit}" 2> "${temporary_root}/repeat-split.log")"
test "${split_commit}" = "${repeat_split_commit}" \
    || fail "repeated subtree split produced a different commit"

mkdir -p "${distribution_root}"
git -C "${source_clone}" archive "${split_commit}" | tar -x -C "${distribution_root}"

for required_path in composer.json README.md bin/setup bin/blackops bootstrap/app.php; do
    test -f "${distribution_root}/${required_path}" \
        || fail "required distribution path is missing: ${required_path}"
done
test -x "${distribution_root}/bin/setup" || fail 'bin/setup is not executable'
test -x "${distribution_root}/bin/blackops" || fail 'bin/blackops is not executable'

allowed_roots=$'.env.example\n.gitignore\nCaddyfile\nDockerfile\nDockerfile.frankenphp\nREADME.md\napp\nbin\nbootstrap\ncompose.yaml\ncomposer.json\nconfig\npublic\ntests\nvar'
actual_roots="$(find "${distribution_root}" -mindepth 1 -maxdepth 1 -printf '%f\n' | sort)"
test "${actual_roots}" = "${allowed_roots}" || fail 'distribution root allowlist does not match'

test -z "$(find "${distribution_root}" -type l -print -quit)" \
    || fail 'distribution contains a symbolic link'
test -z "$(find "${distribution_root}" -type f -name composer.lock -print -quit)" \
    || fail 'distribution contains composer.lock'
test -z "$(find "${distribution_root}" -type d -name vendor -print -quit)" \
    || fail 'distribution contains vendor/'
test -z "$(find "${distribution_root}" -type f -name .env -print -quit)" \
    || fail 'distribution contains .env'
test "$(find "${distribution_root}/var/build" "${distribution_root}/var/log" -type f ! -name .gitignore -print -quit)" = '' \
    || fail 'distribution contains generated build or log state'

major="${version%%.*}"
minor_patch="${version#*.}"
minor="${minor_patch%%.*}"
expected_constraint="^${major}.${minor}"

docker run --rm \
    --user "$(id -u):$(id -g)" \
    --volume "${distribution_root}:/distribution:ro" \
    --workdir /distribution \
    blackops/framework:dev \
    php -r '
$composer = json_decode(file_get_contents("composer.json"), true, 512, JSON_THROW_ON_ERROR);
$expectedConstraint = $argv[1];
if (($composer["name"] ?? null) !== "blackops/skeleton"
    || ($composer["type"] ?? null) !== "project"
    || ($composer["require"]["php"] ?? null) !== ">=8.5"
    || ($composer["require"]["blackops/framework"] ?? null) !== $expectedConstraint
    || ($composer["scripts"]["post-create-project-cmd"] ?? null) !== "@php bin/setup"
    || array_key_exists("repositories", $composer)
    || array_key_exists("version", $composer)) {
    exit(1);
}
' "${expected_constraint}" || fail 'Composer metadata or framework constraint is invalid'

docker run --rm \
    --user "$(id -u):$(id -g)" \
    --volume "${distribution_root}:/distribution:ro" \
    --workdir /distribution \
    blackops/framework:dev \
    composer validate --strict

if git -C "${source_clone}" show-ref --verify --quiet "refs/tags/${version}"; then
    git -C "${source_clone}" tag --delete "${version}" > /dev/null
fi
git -C "${source_clone}" tag "${version}" "${split_commit}"
test "$(git -C "${source_clone}" rev-list -n 1 "refs/tags/${version}")" = "${split_commit}" \
    || fail 'release tag does not resolve to the split commit'

test "$(git -C "${repository_root}" status --short)" = "${source_before}" \
    || fail 'main working tree changed during publication validation'
test "$(docker ps -aq | sort)" = "${containers_before}" || fail 'Docker container state changed'
test "$(docker image ls -aq | sort -u)" = "${images_before}" || fail 'Docker image state changed'
test "$(docker network ls -q | sort)" = "${networks_before}" || fail 'Docker network state changed'
test "$(docker volume ls -q | sort)" = "${volumes_before}" || fail 'Docker volume state changed'

cleanup
trap - EXIT
test ! -e "${temporary_root}" || fail 'temporary publication tree was not removed'

printf 'Skeleton publication dry run passed: version=%s source=%s split=%s\n' \
    "${version}" "${source_commit}" "${split_commit}"
