#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
temporary_root="$(mktemp -d)"

cleanup() {
    rm -rf "${temporary_root}"
}
trap cleanup EXIT

compose=(docker compose --project-directory "${repository_root}" -f "${repository_root}/compose.yaml")

run_php() {
    "${compose[@]}" run --rm --no-deps -v "${temporary_root}:${temporary_root}" app php "$@"
}

run_composer() {
    "${compose[@]}" run --rm --no-deps -v "${temporary_root}:${temporary_root}" app composer "$@"
}

copy_project() {
    local destination="$1"
    mkdir -p "${destination}"
    cp -a "${repository_root}/examples/quickstart/." "${destination}/"
    rm -f "${destination}/.env"
    rm -rf "${destination}/var/build" "${destination}/var/log"
}

project="${temporary_root}/direct"
copy_project "${project}"
printf '%s\n' 'untouched' > "${project}/existing.txt"

before="${temporary_root}/before.txt"
after="${temporary_root}/after.txt"
find "${project}" -type f -printf '%P\t%s\t%T@\n' | sort > "${before}"

(
    cd "${temporary_root}"
    run_php -d disable_functions=exec,shell_exec,system,passthru,proc_open,popen "${project}/bin/setup"
) > "${temporary_root}/direct.out"

cmp "${project}/.env.example" "${project}/.env"
test -d "${project}/var/build"
test -d "${project}/var/log"
test -z "$(find "${project}/var/build" "${project}/var/log" -type f -print -quit)"
grep -F 'docker compose build app http' "${temporary_root}/direct.out" > /dev/null
grep -F 'build:compile' "${temporary_root}/direct.out" > /dev/null
grep -F 'database:migrate' "${temporary_root}/direct.out" > /dev/null

find "${project}" -type f ! -path "${project}/.env" -printf '%P\t%s\t%T@\n' | sort > "${after}"
cmp "${before}" "${after}"

printf '%s\n' 'EXISTING_ENV=preserved' > "${project}/.env"
chmod 0640 "${project}/.env"
touch -t 202601020304.05 "${project}/.env"
environment_before="$(stat -c '%a:%Y:%s' "${project}/.env")"
environment_contents="$(sha256sum "${project}/.env")"
run_php "${project}/bin/setup" > "${temporary_root}/repeat.out"
test "${environment_before}" = "$(stat -c '%a:%Y:%s' "${project}/.env")"
test "${environment_contents}" = "$(sha256sum "${project}/.env")"
grep -F 'Kept existing .env unchanged.' "${temporary_root}/repeat.out" > /dev/null

invalid_directory="${temporary_root}/invalid-directory"
copy_project "${invalid_directory}"
mkdir -p "${invalid_directory}/var"
printf '%s\n' 'not a directory' > "${invalid_directory}/var/build"
if run_php "${invalid_directory}/bin/setup" > "${temporary_root}/invalid.out" 2> "${temporary_root}/invalid.err"; then
    echo 'Expected setup to reject a non-directory var/build path.' >&2
    exit 1
fi
grep -Fx 'Setup failed: var/build must be a directory.' "${temporary_root}/invalid.err" > /dev/null

missing_example="${temporary_root}/missing-example"
copy_project "${missing_example}"
rm "${missing_example}/.env.example"
if run_php "${missing_example}/bin/setup" > "${temporary_root}/missing.out" 2> "${temporary_root}/missing.err"; then
    echo 'Expected setup to reject a missing environment example.' >&2
    exit 1
fi
grep -Fx 'Setup failed: .env.example must be a readable file.' "${temporary_root}/missing.err" > /dev/null
test ! -e "${missing_example}/.env"

copy_failure="${temporary_root}/copy-failure"
copy_project "${copy_failure}"
chmod 0555 "${copy_failure}"
if run_php "${copy_failure}/bin/setup" > "${temporary_root}/copy.out" 2> "${temporary_root}/copy.err"; then
    echo 'Expected setup to report an environment copy failure.' >&2
    chmod 0755 "${copy_failure}"
    exit 1
fi
chmod 0755 "${copy_failure}"
grep -Fx 'Setup failed: .env could not be copied from .env.example.' "${temporary_root}/copy.err" > /dev/null
test ! -e "${copy_failure}/.env"

composer_project="${temporary_root}/composer"
copy_project "${composer_project}"
run_composer --working-dir="${composer_project}" run-script post-create-project-cmd --no-interaction > "${temporary_root}/composer.out"
cmp "${composer_project}/.env.example" "${composer_project}/.env"
test -d "${composer_project}/var/build"
test -d "${composer_project}/var/log"
grep -F 'Created .env from .env.example.' "${temporary_root}/composer.out" > /dev/null

test ! -e "${repository_root}/examples/quickstart/.env"
test ! -e "${repository_root}/examples/quickstart/composer.lock"
test ! -d "${repository_root}/examples/quickstart/vendor"

echo 'Quickstart setup tests passed.'
