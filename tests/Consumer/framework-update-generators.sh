#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
temporary_root="$(mktemp -d)"
framework_repository="${temporary_root}/framework"
current_stubs="${temporary_root}/current-stubs"
current_commands="${temporary_root}/current-commands"
current_application="${temporary_root}/current-application"
consumer_root="${temporary_root}/consumer"
composer_home="${temporary_root}/composer-home"
source_before="$(git -C "${repository_root}" status --short)"

cleanup() {
    rm -rf "${temporary_root}"
}
trap cleanup EXIT

container=(
    docker run --rm
    --user "$(id -u):$(id -g)"
    --volume "${temporary_root}:/smoke"
    --env COMPOSER_HOME=/smoke/composer-home
    --workdir /smoke/consumer
    blackops/framework:dev
)

run_php() {
    "${container[@]}" php "$@"
}

run_composer() {
    "${container[@]}" composer "$@"
}

mkdir -p "${framework_repository}" "${current_stubs}" "${current_commands}" "${current_application}" \
    "${consumer_root}" "${composer_home}"
git -C "${repository_root}" archive HEAD | tar -x -C "${framework_repository}"
cp -a "${repository_root}/examples/quickstart/." "${consumer_root}/"
cp -a "${repository_root}/resources/stubs/." "${current_stubs}/"
cp "${repository_root}/src/Internal/Application/ApplicationConsoleKernel.php" "${current_application}/"
cp "${repository_root}/src/Internal/Console/ApplicationBuildCompileCommand.php" \
    "${repository_root}/src/Internal/Console/ApplicationOperationListCommand.php" \
    "${repository_root}/src/Internal/Console/DatabaseMigrationMigrateCommand.php" \
    "${repository_root}/src/Internal/Console/DatabaseMigrationStatusCommand.php" \
    "${repository_root}/src/Internal/Console/LazyFrameworkCommand.php" \
    "${repository_root}/src/Internal/Console/MakeOperationCommand.php" \
    "${repository_root}/src/Internal/Console/MakeMigrationCommand.php" \
    "${repository_root}/src/Internal/Console/RetentionPlanCommand.php" \
    "${repository_root}/src/Internal/Console/RetentionPurgeCommand.php" \
    "${repository_root}/src/Internal/Console/SchedulerDaemonCommand.php" \
    "${repository_root}/src/Internal/Console/SchedulerRunCommand.php" \
    "${repository_root}/src/Internal/Console/WorkerRunCommand.php" \
    "${current_commands}/"

git -C "${framework_repository}" init --quiet --initial-branch=main
git -C "${framework_repository}" config user.name 'BlackOps Consumer Test'
git -C "${framework_repository}" config user.email 'consumer-test@blackops.invalid'

for stub in operation.php.stub operation-value.php.stub operation-outcome.php.stub; do
    sed -i '/^final readonly class/i /** Legacy fixture stub. */' \
        "${framework_repository}/resources/stubs/${stub}"
done
sed -i '/^final class/i /** Legacy fixture stub. */' \
    "${framework_repository}/resources/stubs/migration.php.stub"
sed -i "s/'Created: '/'Legacy Created: '/" \
    "${framework_repository}/src/Internal/Console/MakeOperationCommand.php" \
    "${framework_repository}/src/Internal/Console/MakeMigrationCommand.php"

git -C "${framework_repository}" add .
git -C "${framework_repository}" commit --quiet -m 'Legacy framework fixture'
git -C "${framework_repository}" tag 1.0.0

rm -rf "${framework_repository}/resources/stubs"
mkdir -p "${framework_repository}/resources/stubs"
cp -a "${current_stubs}/." "${framework_repository}/resources/stubs/"
cp "${current_application}/ApplicationConsoleKernel.php" \
    "${framework_repository}/src/Internal/Application/ApplicationConsoleKernel.php"
cp "${current_commands}"/*.php "${framework_repository}/src/Internal/Console/"
git -C "${framework_repository}" add resources/stubs src/Internal/Application/ApplicationConsoleKernel.php \
    src/Internal/Console
git -C "${framework_repository}" commit --quiet -m 'Current framework fixture'
git -C "${framework_repository}" tag 1.1.0

run_php -r '
$path = "/smoke/consumer/composer.json";
$composer = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$composer["repositories"] = [["type" => "vcs", "url" => "/smoke/framework"]];
$composer["require"]["blackops/framework"] = "1.0.0";
file_put_contents($path, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
'
run_composer install --no-interaction --prefer-dist > "${temporary_root}/install.out"

run_php -r '
$lock = json_decode(file_get_contents("/smoke/consumer/composer.lock"), true, 512, JSON_THROW_ON_ERROR);
$versions = array_column($lock["packages"] ?? [], "version", "name");
if (($versions["blackops/framework"] ?? null) !== "1.0.0") {
    exit(1);
}
'

run_php blackops make:operation Upgrade/BeforeUpdate --type=upgrade.before \
    > "${temporary_root}/before-operation.out"
run_php blackops make:migration BeforeUpdateSchema \
    > "${temporary_root}/before-migration.out"

grep -q '^Legacy Created: app/Feature/Upgrade/BeforeUpdate/BeforeUpdate.php$' \
    "${temporary_root}/before-operation.out"
grep -q '^Legacy Created: app/Feature/Upgrade/BeforeUpdate/BeforeUpdateValue.php$' \
    "${temporary_root}/before-operation.out"
grep -q '^Legacy Created: app/Feature/Upgrade/BeforeUpdate/BeforeUpdateOutcome.php$' \
    "${temporary_root}/before-operation.out"
grep -Eq '^Legacy Created: migrations/Version[0-9]{14}\.php$' \
    "${temporary_root}/before-migration.out"

before_operation_directory="${consumer_root}/app/Feature/Upgrade/BeforeUpdate"
before_migration_relative="$(sed -n 's/^Legacy Created: //p' "${temporary_root}/before-migration.out")"
before_migration="${consumer_root}/${before_migration_relative}"
test -n "${before_migration}"
grep -q 'Legacy fixture stub' "${before_operation_directory}/BeforeUpdate.php"
grep -q 'Legacy fixture stub' "${before_migration}"

sha256sum "${consumer_root}/blackops" > "${temporary_root}/entrypoint.before.sha256"
find \
    "${consumer_root}/app/ApplicationServiceProvider.php" \
    "${consumer_root}/app/Security/SampleUserAuthorizationPolicy.php" \
    "${consumer_root}/app/UserInterface/Http/SampleTokenAuthenticator.php" \
    "${consumer_root}/app/Feature/Welcome" \
    "${consumer_root}/app/Feature/Report" \
    "${consumer_root}/app/Feature/Order" \
    "${consumer_root}/migrations/Version20260718000000.php" \
    -type f -print0 | sort -z | xargs -0 sha256sum \
    > "${temporary_root}/application-authentication.before.sha256"
find "${before_operation_directory}" -maxdepth 1 -type f -print0 | sort -z | xargs -0 sha256sum \
    > "${temporary_root}/operation.before.sha256"
sha256sum "${before_migration}" > "${temporary_root}/migration.before.sha256"

run_php -r '
$lock = json_decode(file_get_contents("/smoke/consumer/composer.lock"), true, 512, JSON_THROW_ON_ERROR);
$packages = [];
foreach ($lock["packages"] ?? [] as $package) {
    if (($package["name"] ?? null) !== "blackops/framework") {
        $packages[$package["name"]] = $package["version"];
    }
}
ksort($packages);
file_put_contents("/smoke/dependencies.before.json", json_encode($packages, JSON_THROW_ON_ERROR));
'

run_composer require --no-update --no-interaction blackops/framework:1.1.0
run_composer update --no-interaction --prefer-dist blackops/framework \
    > "${temporary_root}/update.out"

run_php -r '
$lock = json_decode(file_get_contents("/smoke/consumer/composer.lock"), true, 512, JSON_THROW_ON_ERROR);
$versions = array_column($lock["packages"] ?? [], "version", "name");
if (($versions["blackops/framework"] ?? null) !== "1.1.0") {
    exit(1);
}
$packages = [];
foreach ($lock["packages"] ?? [] as $package) {
    if (($package["name"] ?? null) !== "blackops/framework") {
        $packages[$package["name"]] = $package["version"];
    }
}
ksort($packages);
file_put_contents("/smoke/dependencies.after.json", json_encode($packages, JSON_THROW_ON_ERROR));
'
cmp "${temporary_root}/dependencies.before.json" "${temporary_root}/dependencies.after.json"

sha256sum --check "${temporary_root}/entrypoint.before.sha256"
sha256sum --check "${temporary_root}/application-authentication.before.sha256"
sha256sum --check "${temporary_root}/operation.before.sha256"
sha256sum --check "${temporary_root}/migration.before.sha256"
cmp "${current_stubs}/operation.php.stub" \
    "${consumer_root}/vendor/blackops/framework/resources/stubs/operation.php.stub"
cmp "${current_stubs}/migration.php.stub" \
    "${consumer_root}/vendor/blackops/framework/resources/stubs/migration.php.stub"
cmp "${repository_root}/src/Internal/Console/MakeOperationCommand.php" \
    "${consumer_root}/vendor/blackops/framework/src/Internal/Console/MakeOperationCommand.php"
cmp "${repository_root}/src/Internal/Console/MakeMigrationCommand.php" \
    "${consumer_root}/vendor/blackops/framework/src/Internal/Console/MakeMigrationCommand.php"

run_php blackops make:operation Upgrade/AfterUpdate --type=upgrade.after \
    > "${temporary_root}/after-operation.out"
sleep 1
run_php blackops make:migration AfterUpdateSchema \
    > "${temporary_root}/after-migration.out"

! grep -q 'Legacy Created:' "${temporary_root}/after-operation.out" "${temporary_root}/after-migration.out"
grep -q '^Created: app/Feature/Upgrade/AfterUpdate/AfterUpdate.php$' \
    "${temporary_root}/after-operation.out"
grep -q '^Created: app/Feature/Upgrade/AfterUpdate/AfterUpdateValue.php$' \
    "${temporary_root}/after-operation.out"
grep -q '^Created: app/Feature/Upgrade/AfterUpdate/AfterUpdateOutcome.php$' \
    "${temporary_root}/after-operation.out"
grep -Eq '^Created: migrations/Version[0-9]{14}\.php$' \
    "${temporary_root}/after-migration.out"

after_operation_directory="${consumer_root}/app/Feature/Upgrade/AfterUpdate"
after_migration_relative="$(sed -n 's/^Created: //p' "${temporary_root}/after-migration.out")"
after_migration="${consumer_root}/${after_migration_relative}"
test -n "${after_migration}"
! grep -R -q 'Legacy fixture stub' "${after_operation_directory}" "${after_migration}"
grep -q "#\[OperationType('upgrade.after')\]" "${after_operation_directory}/AfterUpdate.php"
grep -q 'handle(AfterUpdateValue \$value): AfterUpdateOutcome' "${after_operation_directory}/AfterUpdate.php"
grep -q "return 'AfterUpdateSchema';" "${after_migration}"

run_php blackops build:compile > "${temporary_root}/build.out"

test "$(git -C "${repository_root}" status --short)" = "${source_before}"

cleanup
trap - EXIT
test ! -e "${temporary_root}"

echo 'Framework update generator smoke passed.'
