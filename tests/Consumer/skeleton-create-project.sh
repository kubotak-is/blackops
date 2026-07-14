#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
temporary_root="$(mktemp -d)"
package_root="${temporary_root}/package"
normal_project="${temporary_root}/normal"
no_scripts_project="${temporary_root}/no-scripts"
composer_home="${temporary_root}/composer-home"
source_before="$(git -C "${repository_root}" status --short -- examples/quickstart)"
containers_before="$(docker ps -aq | sort)"
images_before="$(docker image ls -aq | sort -u)"
networks_before="$(docker network ls -q | sort)"
volumes_before="$(docker volume ls -q | sort)"

cleanup() {
    rm -rf "${temporary_root}"
}
trap cleanup EXIT

container=(
    docker run --rm
    --user "$(id -u):$(id -g)"
    --volume "${temporary_root}:/smoke"
    --volume "${repository_root}:/framework:ro"
    --env COMPOSER_HOME=/smoke/composer-home
    --workdir /smoke
    blackops/framework:dev
)

run_php() {
    "${container[@]}" php "$@"
}

run_composer() {
    "${container[@]}" composer "$@"
}

mkdir -p "${package_root}" "${composer_home}"
cp -a "${repository_root}/examples/quickstart/." "${package_root}/"

test -f "${package_root}/composer.json"
test -x "${package_root}/bin/setup"
test -x "${package_root}/blackops"
test ! -e "${package_root}/bin/"'blackops'
test -f "${package_root}/bootstrap/app.php"
test -f "${package_root}/Caddyfile"
test -f "${package_root}/Caddyfile.classic"
test -f "${package_root}/app/Feature/Welcome/ShowWelcome/ShowWelcome.php"
test -f "${package_root}/app/Feature/Report/GenerateReport/GenerateReport.php"
test ! -e "${package_root}/.env"
test ! -e "${package_root}/composer.lock"
test ! -d "${package_root}/vendor"
test "$(find "${package_root}/var/build" -type f ! -name .gitignore -print -quit)" = ''
test "$(find "${package_root}/var/log" -type f ! -name .gitignore -print -quit)" = ''

run_php -r '
$composer = json_decode(file_get_contents("/smoke/package/composer.json"), true, 512, JSON_THROW_ON_ERROR);
if (($composer["name"] ?? null) !== "blackops/skeleton"
    || ($composer["type"] ?? null) !== "project"
    || ($composer["require"]["blackops/framework"] ?? null) !== "^1.0"
    || isset($composer["repositories"])
    || isset($composer["version"])) {
    exit(1);
}
'

cat > "${composer_home}/config.json" <<'JSON'
{
  "repositories": {
    "framework": {
      "type": "path",
      "url": "/framework",
      "options": {
        "symlink": false,
        "versions": {
          "blackops/framework": "1.0.0"
        }
      },
      "canonical": true,
      "only": ["blackops/framework"]
    }
  }
}
JSON

skeleton_repository='{"type":"path","url":"/smoke/package","options":{"symlink":false,"versions":{"blackops/skeleton":"1.0.1"}},"canonical":true}'

run_composer --working-dir=/smoke/package validate --strict
run_composer create-project blackops/skeleton /smoke/normal 1.0.1 --no-interaction --prefer-dist \
    --repository="${skeleton_repository}" \
    > "${temporary_root}/normal-install.out"

test ! -L "${normal_project}"
! test "${package_root}/composer.json" -ef "${normal_project}/composer.json"
test -f "${normal_project}/composer.lock"
test -f "${normal_project}/vendor/autoload.php"
test ! -L "${normal_project}/vendor/blackops/framework"
test -f "${normal_project}/vendor/blackops/framework/src/Application/Application.php"
test -f "${normal_project}/Caddyfile.classic"
cmp "${normal_project}/.env.example" "${normal_project}/.env"
test -d "${normal_project}/var/build"
test -d "${normal_project}/var/log"
test -x "${normal_project}/bin/setup"
test -x "${normal_project}/blackops"
test ! -e "${normal_project}/bin/"'blackops'
test ! -e "${normal_project}/var/build/operations.php"
test ! -e "${normal_project}/var/build/http.php"
test ! -e "${normal_project}/var/build/container.php"
test ! -e "${normal_project}/var/log/journal.jsonl"

run_php -r '
require "/smoke/normal/vendor/autoload.php";
if (!class_exists(BlackOps\Application\Application::class)
    || !class_exists(App\Feature\Welcome\ShowWelcome\ShowWelcome::class)
    || !class_exists(App\Feature\Report\GenerateReport\GenerateReport::class)) {
    exit(1);
}
$composer = json_decode(file_get_contents("/smoke/normal/composer.json"), true, 512, JSON_THROW_ON_ERROR);
if (isset($composer["repositories"]) || isset($composer["version"])) {
    exit(1);
}
$lock = json_decode(file_get_contents("/smoke/normal/composer.lock"), true, 512, JSON_THROW_ON_ERROR);
$versions = array_column($lock["packages"] ?? [], "version", "name");
if (($versions["blackops/framework"] ?? null) !== "1.0.0") {
    exit(1);
}
'

run_php /smoke/normal/blackops make:operation Smoke/CreateSmoke --type=smoke.create \
    > "${temporary_root}/normal-operation-generator.out"
run_php /smoke/normal/blackops make:migration CreateSmokeTable \
    > "${temporary_root}/normal-migration-generator.out"
test -f "${normal_project}/app/Feature/Smoke/CreateSmoke/CreateSmoke.php"
test -f "${normal_project}/app/Feature/Smoke/CreateSmoke/CreateSmokeValue.php"
test -f "${normal_project}/app/Feature/Smoke/CreateSmoke/CreateSmokeOutcome.php"
test -n "$(find "${normal_project}/migrations" -maxdepth 1 -type f -name 'Version*.php' -print -quit)"
run_php /smoke/normal/blackops build:compile \
    > "${temporary_root}/normal-build.out"
test -f "${normal_project}/var/build/operations.php"

test ! -d "${package_root}/resources/stubs"
test ! -d "${normal_project}/resources/stubs"

run_composer create-project blackops/skeleton /smoke/no-scripts 1.0.1 --no-interaction --prefer-dist --no-scripts \
    --repository="${skeleton_repository}" \
    > "${temporary_root}/no-scripts-install.out"

test ! -L "${no_scripts_project}"
! test "${package_root}/composer.json" -ef "${no_scripts_project}/composer.json"
test ! -e "${no_scripts_project}/.env"
test -f "${no_scripts_project}/composer.lock"
test -f "${no_scripts_project}/vendor/autoload.php"
test ! -L "${no_scripts_project}/vendor/blackops/framework"
test -f "${no_scripts_project}/vendor/blackops/framework/src/Application/Application.php"
test -f "${no_scripts_project}/Caddyfile.classic"
test ! -e "${no_scripts_project}/var/build/operations.php"
test ! -e "${no_scripts_project}/var/log/journal.jsonl"

run_php -r '
require "/smoke/no-scripts/vendor/autoload.php";
if (!class_exists(BlackOps\Application\Application::class)
    || !class_exists(App\Feature\Welcome\ShowWelcome\ShowWelcome::class)) {
    exit(1);
}
$composer = json_decode(file_get_contents("/smoke/no-scripts/composer.json"), true, 512, JSON_THROW_ON_ERROR);
if (isset($composer["repositories"]) || isset($composer["version"])) {
    exit(1);
}
$lock = json_decode(file_get_contents("/smoke/no-scripts/composer.lock"), true, 512, JSON_THROW_ON_ERROR);
$versions = array_column($lock["packages"] ?? [], "version", "name");
if (($versions["blackops/framework"] ?? null) !== "1.0.0") {
    exit(1);
}
'

rm -rf "${no_scripts_project}/var/build" "${no_scripts_project}/var/log"
run_php /smoke/no-scripts/bin/setup > "${temporary_root}/manual-setup.out"
cmp "${no_scripts_project}/.env.example" "${no_scripts_project}/.env"
test -d "${no_scripts_project}/var/build"
test -d "${no_scripts_project}/var/log"
test "$(find "${no_scripts_project}/var/build" "${no_scripts_project}/var/log" -type f -print -quit)" = ''

environment_before="$(stat -c '%a:%Y:%s' "${no_scripts_project}/.env")"
environment_contents="$(sha256sum "${no_scripts_project}/.env")"
run_php /smoke/no-scripts/bin/setup > "${temporary_root}/manual-repeat.out"
test "${environment_before}" = "$(stat -c '%a:%Y:%s' "${no_scripts_project}/.env")"
test "${environment_contents}" = "$(sha256sum "${no_scripts_project}/.env")"

test "$(git -C "${repository_root}" status --short -- examples/quickstart)" = "${source_before}"
test "$(docker ps -aq | sort)" = "${containers_before}"
test "$(docker image ls -aq | sort -u)" = "${images_before}"
test "$(docker network ls -q | sort)" = "${networks_before}"
test "$(docker volume ls -q | sort)" = "${volumes_before}"

cleanup
trap - EXIT
test ! -e "${temporary_root}"

echo 'Skeleton create-project smoke passed.'
