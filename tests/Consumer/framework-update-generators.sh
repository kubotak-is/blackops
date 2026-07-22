#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
temporary_root="$(mktemp -d)"
framework_repository="${temporary_root}/framework"
current_stubs="${temporary_root}/current-stubs"
current_commands="${temporary_root}/current-commands"
current_application="${temporary_root}/current-application"
current_dependency_injection="${temporary_root}/current-dependency-injection"
current_frontend_generation="${temporary_root}/current-frontend-generation"
current_runtime="${temporary_root}/current-runtime"
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
    "${current_dependency_injection}" "${current_frontend_generation}" "${current_runtime}" \
    "${consumer_root}" "${composer_home}"
git -C "${repository_root}" archive HEAD | tar -x -C "${framework_repository}"
cp -a "${repository_root}/examples/quickstart/." "${consumer_root}/"
cp -a "${repository_root}/resources/stubs/." "${current_stubs}/"
cp "${repository_root}/src/Internal/Application/ApplicationConsoleKernel.php" \
    "${repository_root}/src/Internal/Application/ApplicationConsoleCommandFactory.php" \
    "${repository_root}/src/Internal/Application/ApplicationBuildConfiguration.php" \
    "${repository_root}/src/Internal/Application/ApplicationCommandContainerResolver.php" \
    "${repository_root}/src/Internal/Application/ApplicationCommandDiscovery.php" \
    "${repository_root}/src/Internal/Application/ApplicationCommandRuntimeManifest.php" \
    "${repository_root}/src/Internal/Application/ApplicationCommandRuntimeManifestLoader.php" \
    "${repository_root}/src/Internal/Application/ApplicationCommandValidator.php" \
    "${repository_root}/src/Internal/Application/ExplicitApplicationCommands.php" \
    "${current_application}/"
cp "${repository_root}/src/Internal/Console/ApplicationBuildCompileCommand.php" \
    "${repository_root}/src/Internal/Console/ApplicationCommandCollisionValidator.php" \
    "${repository_root}/src/Internal/Console/ApplicationCommandManifestArtifact.php" \
    "${repository_root}/src/Internal/Console/ApplicationCommandManifestFile.php" \
    "${repository_root}/src/Internal/Console/ApplicationCommandMetadata.php" \
    "${repository_root}/src/Internal/Console/ApplicationOperationListCommand.php" \
    "${repository_root}/src/Internal/Console/DatabaseMigrationMigrateCommand.php" \
    "${repository_root}/src/Internal/Console/DatabaseMigrationStatusCommand.php" \
    "${repository_root}/src/Internal/Console/LazyFrameworkCommand.php" \
    "${repository_root}/src/Internal/Console/FrontendCheckCommand.php" \
    "${repository_root}/src/Internal/Console/FrameworkCommandNames.php" \
    "${repository_root}/src/Internal/Console/MakeAuthCommand.php" \
    "${repository_root}/src/Internal/Console/MakeOperationCommand.php" \
    "${repository_root}/src/Internal/Console/MakeMigrationCommand.php" \
    "${repository_root}/src/Internal/Console/RetentionPlanCommand.php" \
    "${repository_root}/src/Internal/Console/RetentionPurgeCommand.php" \
    "${repository_root}/src/Internal/Console/SchedulerDaemonCommand.php" \
    "${repository_root}/src/Internal/Console/SchedulerRunCommand.php" \
    "${repository_root}/src/Internal/Console/WorkerRunCommand.php" \
    "${current_commands}/"
cp "${repository_root}/src/Internal/DependencyInjection/RuntimeContainerCompiler.php" \
    "${current_dependency_injection}/"
cp "${repository_root}/src/Internal/Frontend/Generation/FrontendTreeCheckInspectionException.php" \
    "${repository_root}/src/Internal/Frontend/Generation/FrontendTreeCheckFilesystem.php" \
    "${repository_root}/src/Internal/Frontend/Generation/FrontendTreeCheckState.php" \
    "${repository_root}/src/Internal/Frontend/Generation/FrontendTreeChecker.php" \
    "${repository_root}/src/Internal/Frontend/Generation/NativeFrontendTreeCheckFilesystem.php" \
    "${current_frontend_generation}/"
cp "${repository_root}/src/Internal/Runtime/ProductionRuntimeArtifactLoader.php" \
    "${repository_root}/src/Internal/Runtime/RuntimeContainerArtifactLoader.php" \
    "${current_runtime}/"

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
cp "${current_application}"/*.php "${framework_repository}/src/Internal/Application/"
cp "${current_commands}"/*.php "${framework_repository}/src/Internal/Console/"
cp "${current_dependency_injection}"/*.php "${framework_repository}/src/Internal/DependencyInjection/"
cp "${current_frontend_generation}"/*.php "${framework_repository}/src/Internal/Frontend/Generation/"
cp "${current_runtime}"/*.php "${framework_repository}/src/Internal/Runtime/"
cp -a "${repository_root}/src/." "${framework_repository}/src/"
git -C "${framework_repository}" add resources/stubs src
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
$config = <<<'"'"'PHP'"'"'
<?php

return [
    "output" => dirname(__DIR__) . "/resources/js/blackops",
];
PHP;
file_put_contents("/smoke/consumer/config/frontend.php", $config . "\n");
if (!is_dir("/smoke/consumer/resources/js/application")) {
    mkdir("/smoke/consumer/resources/js/application", 0777, true);
}
file_put_contents(
    "/smoke/consumer/resources/js/application/client.ts",
    "export const applicationOwned = true;\n",
);
'

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
    "${consumer_root}/app/Security/SampleOperationStatusAuthorizer.php" \
    "${consumer_root}/app/UserInterface/Console/SampleConsoleActorProvider.php" \
    "${consumer_root}/app/UserInterface/Http/SampleTokenAuthenticator.php" \
    "${consumer_root}/app/Feature/Welcome" \
    "${consumer_root}/app/Feature/Report" \
    "${consumer_root}/app/Feature/Order" \
    "${consumer_root}/app/Feature/Diagnostics" \
    "${consumer_root}/config/diagnostics.php" \
    "${consumer_root}/config/frontend.php" \
    "${consumer_root}/config/logging.php" \
    "${consumer_root}/package.json" \
    "${consumer_root}/pnpm-lock.yaml" \
    "${consumer_root}/resources/js/application" \
    "${consumer_root}/tests/Frontend" \
    "${consumer_root}/tsconfig.json" \
    "${consumer_root}/tsconfig.runtime.json" \
    "${consumer_root}/README.md" \
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
cmp "${current_stubs}/seeder.php.stub" \
    "${consumer_root}/vendor/blackops/framework/resources/stubs/seeder.php.stub"
cmp "${current_stubs}/auth-config.php.stub" \
    "${consumer_root}/vendor/blackops/framework/resources/stubs/auth-config.php.stub"
cmp "${current_stubs}/auth-register.php.stub" \
    "${consumer_root}/vendor/blackops/framework/resources/stubs/auth-register.php.stub"
cmp "${repository_root}/src/Internal/Console/MakeOperationCommand.php" \
    "${consumer_root}/vendor/blackops/framework/src/Internal/Console/MakeOperationCommand.php"
cmp "${repository_root}/src/Internal/Console/MakeMigrationCommand.php" \
    "${consumer_root}/vendor/blackops/framework/src/Internal/Console/MakeMigrationCommand.php"
cmp "${repository_root}/src/Internal/Console/MakeAuthCommand.php" \
    "${consumer_root}/vendor/blackops/framework/src/Internal/Console/MakeAuthCommand.php"
cmp "${repository_root}/src/Internal/Console/MakeSeederCommand.php" \
    "${consumer_root}/vendor/blackops/framework/src/Internal/Console/MakeSeederCommand.php"
cmp "${repository_root}/src/Internal/Generator/AuthGenerator.php" \
    "${consumer_root}/vendor/blackops/framework/src/Internal/Generator/AuthGenerator.php"
cmp "${repository_root}/src/Internal/Generator/SeederGenerator.php" \
    "${consumer_root}/vendor/blackops/framework/src/Internal/Generator/SeederGenerator.php"

run_php blackops make:operation Upgrade/AfterUpdate --type=upgrade.after \
    > "${temporary_root}/after-operation.out"
sleep 1
run_php blackops make:migration AfterUpdateSchema \
    > "${temporary_root}/after-migration.out"
run_php blackops make:seeder Upgrade/AfterUpdateSeeder \
    > "${temporary_root}/after-seeder.out"

! grep -q 'Legacy Created:' "${temporary_root}/after-operation.out" "${temporary_root}/after-migration.out"
grep -q '^Created: app/Feature/Upgrade/AfterUpdate/AfterUpdate.php$' \
    "${temporary_root}/after-operation.out"
grep -q '^Created: app/Feature/Upgrade/AfterUpdate/AfterUpdateValue.php$' \
    "${temporary_root}/after-operation.out"
grep -q '^Created: app/Feature/Upgrade/AfterUpdate/AfterUpdateOutcome.php$' \
    "${temporary_root}/after-operation.out"
grep -Eq '^Created: migrations/Version[0-9]{14}\.php$' \
    "${temporary_root}/after-migration.out"
grep -q '^Created: app/Infrastructure/Seed/Upgrade/AfterUpdateSeeder.php$' \
    "${temporary_root}/after-seeder.out"

after_operation_directory="${consumer_root}/app/Feature/Upgrade/AfterUpdate"
after_migration_relative="$(sed -n 's/^Created: //p' "${temporary_root}/after-migration.out")"
after_migration="${consumer_root}/${after_migration_relative}"
after_seeder="${consumer_root}/app/Infrastructure/Seed/Upgrade/AfterUpdateSeeder.php"
test -n "${after_migration}"
! grep -R -q 'Legacy fixture stub' "${after_operation_directory}" "${after_migration}"
grep -q "#\[OperationType('upgrade.after')\]" "${after_operation_directory}/AfterUpdate.php"
grep -q 'handle(AfterUpdateValue \$value): AfterUpdateOutcome' "${after_operation_directory}/AfterUpdate.php"
grep -q "return 'AfterUpdateSchema';" "${after_migration}"
grep -q 'namespace App\\Infrastructure\\Seed\\Upgrade;' "${after_seeder}"
grep -q 'final readonly class AfterUpdateSeeder implements Seeder' "${after_seeder}"
grep -q 'public function run(): void {}' "${after_seeder}"

run_php blackops make:auth > "${temporary_root}/after-auth.out"
test "$(grep -c '^Created: ' "${temporary_root}/after-auth.out")" = '27'
grep -q '^Created: app/Domain/Identity/User.php$' "${temporary_root}/after-auth.out"
grep -q '^Created: app/Infrastructure/Identity/ApplicationSessionIdentityProvider.php$' \
    "${temporary_root}/after-auth.out"
grep -q '^Created: app/Feature/Identity/Register/Register.php$' "${temporary_root}/after-auth.out"
grep -q '^Created: config/auth.php$' "${temporary_root}/after-auth.out"
grep -q '^Created: migrations/Version20260722000100.php$' "${temporary_root}/after-auth.out"
run_php blackops make:auth > "${temporary_root}/after-auth-noop.out"
test "$(<"${temporary_root}/after-auth-noop.out")" = 'Authentication starter is already current.'

run_php blackops build:compile > "${temporary_root}/build.out"
test -f "${consumer_root}/var/build/commands.php"
run_php blackops frontend:generate > "${temporary_root}/frontend-generate.out"
run_php blackops frontend:check > "${temporary_root}/frontend-check.out"
grep -q '^Frontend generated tree is fresh in resources/js/blackops\.$' \
    "${temporary_root}/frontend-check.out"
run_php blackops operation:list > "${temporary_root}/operations.out"
grep -q 'diagnostics.failure.trigger' "${temporary_root}/operations.out"
grep -q 'auth.register' "${temporary_root}/operations.out"
grep -q 'auth.login' "${temporary_root}/operations.out"
grep -q 'auth.logout' "${temporary_root}/operations.out"
run_php -r '
$operations = require "/smoke/consumer/var/build/operations.php";
$http = require "/smoke/consumer/var/build/http.php";
$commands = require "/smoke/consumer/var/build/commands.php";
$typeFound = false;
foreach ($operations["payload"]["operations"] ?? [] as $operation) {
    $typeFound = $typeFound || ($operation["typeId"] ?? null) === "diagnostics.failure.trigger";
}
$routeFound = ($http["payload"]["routes"]["POST"]["/failures"] ?? null)
    === "diagnostics.failure.trigger";
exit($typeFound
    && $routeFound
    && ($commands["schema_version"] ?? null) === 2
    && ($commands["commands"] ?? null) === []
    && count($commands["operation_commands"] ?? []) === 1
    && ($commands["operation_commands"][0]["name"] ?? null) === "order:create" ? 0 : 1);
'

test "$(git -C "${repository_root}" status --short)" = "${source_before}"

cleanup
trap - EXIT
test ! -e "${temporary_root}"

echo 'Framework update generator smoke passed.'
