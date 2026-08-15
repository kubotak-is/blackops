#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
temporary_root="$(mktemp -d)"
framework_repository="${temporary_root}/framework"
current_stubs="${temporary_root}/current-stubs"
consumer_root="${temporary_root}/consumer"
composer_home="${temporary_root}/composer-home"
source_before="$(git -C "${repository_root}" status --short)"
current_commit="$(git -C "${repository_root}" rev-parse HEAD)"

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

mkdir -p "${framework_repository}" "${current_stubs}" "${consumer_root}" "${composer_home}"
git clone --quiet --no-hardlinks "${repository_root}" "${framework_repository}"
git -C "${framework_repository}" checkout --quiet 1.1.0
test "$(git -C "${framework_repository}" cat-file -t refs/tags/1.1.0)" = 'tag'
test "$(git -C "${framework_repository}" rev-parse 'refs/tags/1.1.0^{commit}')" \
    = "$(git -C "${repository_root}" rev-parse 'refs/tags/1.1.0^{commit}')"
git -C "${framework_repository}" archive 1.1.0:examples/quickstart | tar -x -C "${consumer_root}"
cp -a "${repository_root}/resources/stubs/." "${current_stubs}/"
candidate_tag_ref='refs/tags/1.2.0'
candidate_tag_type="$(git -C "${framework_repository}" cat-file -t "${candidate_tag_ref}" 2>/dev/null || true)"
if test -z "${candidate_tag_type}"; then
    git -C "${framework_repository}" tag 1.2.0 "${current_commit}"
    candidate_source_commit="${current_commit}"
else
    test "${candidate_tag_type}" = 'tag'
    published_candidate_commit="$(git -C "${framework_repository}" rev-parse "${candidate_tag_ref}^{commit}")"
    root_published_candidate_commit="$(git -C "${repository_root}" rev-parse "${candidate_tag_ref}^{commit}")"
    test "${published_candidate_commit}" = "${root_published_candidate_commit}"
    if ! git -C "${framework_repository}" diff --quiet "${published_candidate_commit}" "${current_commit}" -- \
        src composer.json examples/quickstart resources migrations; then
        printf 'Published 1.2.0 release-runtime Source drifted from current HEAD.\n' >&2
        exit 1
    fi
    candidate_source_commit="${published_candidate_commit}"
fi
test "$(git -C "${framework_repository}" rev-parse "${candidate_tag_ref}^{commit}")" = "${candidate_source_commit}"

run_php -r '
$path = "/smoke/consumer/composer.json";
$composer = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$composer["repositories"] = [["type" => "vcs", "url" => "/smoke/framework"]];
$composer["require"]["blackops/framework"] = "1.1.0";
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
if (($versions["blackops/framework"] ?? null) !== "1.1.0") {
    exit(1);
}
'

run_php blackops make:operation Upgrade/BeforeUpdate --type=upgrade.before \
    > "${temporary_root}/before-operation.out"
run_php blackops make:migration BeforeUpdateSchema \
    > "${temporary_root}/before-migration.out"

grep -q '^Created: app/Feature/Upgrade/BeforeUpdate/BeforeUpdate.php$' \
    "${temporary_root}/before-operation.out"
grep -q '^Created: app/Feature/Upgrade/BeforeUpdate/BeforeUpdateValue.php$' \
    "${temporary_root}/before-operation.out"
grep -q '^Created: app/Feature/Upgrade/BeforeUpdate/BeforeUpdateOutcome.php$' \
    "${temporary_root}/before-operation.out"
grep -Eq '^Created: migrations/Version[0-9]{14}\.php$' \
    "${temporary_root}/before-migration.out"

before_operation_directory="${consumer_root}/app/Feature/Upgrade/BeforeUpdate"
before_migration_relative="$(sed -n 's/^Created: //p' "${temporary_root}/before-migration.out")"
before_migration="${consumer_root}/${before_migration_relative}"
test -n "${before_migration}"

sha256sum "${consumer_root}/blackops" > "${temporary_root}/entrypoint.before.sha256"
stable_application_inventory=(
    "${consumer_root}/.env.example"
    "${consumer_root}/.gitignore"
    "${consumer_root}/Caddyfile"
    "${consumer_root}/Caddyfile.classic"
    "${consumer_root}/Dockerfile"
    "${consumer_root}/Dockerfile.frankenphp"
    "${consumer_root}/README.md"
    "${consumer_root}/bin/setup"
    "${consumer_root}/blackops"
    "${consumer_root}/bootstrap/app.php"
    "${consumer_root}/compose.yaml"
    "${consumer_root}/config/app.php"
    "${consumer_root}/config/database.php"
    "${consumer_root}/config/execution.php"
    "${consumer_root}/config/journal.php"
    "${consumer_root}/config/operations.php"
    "${consumer_root}/config/retention.php"
    "${consumer_root}/public/index.php"
    "${consumer_root}/public/worker.php"
    "${consumer_root}/app/Feature/Report"
    "${consumer_root}/app/Feature/Welcome"
)
for inventory_path in "${stable_application_inventory[@]}"; do
    test -e "${inventory_path}"
done
find "${stable_application_inventory[@]}" -type f -print0 | sort -z | xargs -0 sha256sum \
    > "${temporary_root}/application-authentication.before.sha256"
find "${before_operation_directory}" -maxdepth 1 -type f -print0 | sort -z | xargs -0 sha256sum \
    > "${temporary_root}/operation.before.sha256"
sha256sum "${before_migration}" > "${temporary_root}/migration.before.sha256"

run_php -r '
$composer = json_decode(file_get_contents("/smoke/consumer/composer.json"), true, 512, JSON_THROW_ON_ERROR);
unset($composer["require"]["blackops/framework"]);
ksort($composer["require"]);
file_put_contents("/smoke/application-require.before.json", json_encode($composer["require"], JSON_THROW_ON_ERROR));
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

run_composer require --no-update --no-interaction blackops/framework:1.2.0
run_composer update --no-interaction --prefer-dist blackops/framework \
    > "${temporary_root}/update.out"

run_php -r '
$lock = json_decode(file_get_contents("/smoke/consumer/composer.lock"), true, 512, JSON_THROW_ON_ERROR);
$versions = array_column($lock["packages"] ?? [], "version", "name");
if (($versions["blackops/framework"] ?? null) !== "1.2.0") {
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
$composer = json_decode(file_get_contents("/smoke/consumer/composer.json"), true, 512, JSON_THROW_ON_ERROR);
unset($composer["require"]["blackops/framework"]);
ksort($composer["require"]);
file_put_contents("/smoke/application-require.after.json", json_encode($composer["require"], JSON_THROW_ON_ERROR));
$before = json_decode(file_get_contents("/smoke/dependencies.before.json"), true, 512, JSON_THROW_ON_ERROR);
$after = json_decode(file_get_contents("/smoke/dependencies.after.json"), true, 512, JSON_THROW_ON_ERROR);
foreach ($before as $name => $version) {
    if (($after[$name] ?? null) !== $version) {
        fwrite(STDERR, "Application dependency changed during framework-only update: {$name}\n");
        exit(1);
    }
}
foreach (["vlucas/phpdotenv", "open-telemetry/api"] as $name) {
    if (!array_key_exists($name, $after)) {
        fwrite(STDERR, "Expected framework runtime dependency missing after update: {$name}\n");
        exit(1);
    }
}
'
cmp "${temporary_root}/application-require.before.json" "${temporary_root}/application-require.after.json"

sha256sum --check "${temporary_root}/entrypoint.before.sha256"
sha256sum --check "${temporary_root}/application-authentication.before.sha256"
sha256sum --check "${temporary_root}/operation.before.sha256"
sha256sum --check "${temporary_root}/migration.before.sha256"

# Stable 1.1.0 has no frontend_manifest key. Add only the documented candidate
# build boundary after Composer/source invariants have been checked.
run_php -r '
$path = "/smoke/consumer/config/app.php";
$source = file_get_contents($path);
$quote = chr(39);
$needle = "        {$quote}http_manifest{$quote} => dirname(__DIR__) . {$quote}/var/build/http.php{$quote},";
$replacement = $needle . "\n        {$quote}frontend_manifest{$quote} => dirname(__DIR__) . {$quote}/var/build/frontend.php{$quote},";
if (!is_string($source) || !str_contains($source, $needle)) {
    fwrite(STDERR, "Stable configuration did not contain the expected build boundary.\n");
    exit(1);
}
file_put_contents($path, str_replace($needle, $replacement, $source, $count));
if ($count !== 1) {
    exit(1);
}
'
grep -q "'frontend_manifest' => dirname(__DIR__) . '/var/build/frontend.php'" \
    "${consumer_root}/config/app.php"

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
run_php blackops build:compile > "${temporary_root}/build-after.out"
run_php blackops operation:list > "${temporary_root}/operation-list-after.out"

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
grep -q "#\[OperationType('upgrade.after')\]" "${after_operation_directory}/AfterUpdate.php"
grep -q 'handle(AfterUpdateValue \$value): AfterUpdateOutcome' "${after_operation_directory}/AfterUpdate.php"
grep -q "return 'AfterUpdateSchema';" "${after_migration}"
grep -q 'namespace App\\Infrastructure\\Seed\\Upgrade;' "${after_seeder}"
grep -q 'final readonly class AfterUpdateSeeder implements Seeder' "${after_seeder}"
grep -q 'public function run(): void {}' "${after_seeder}"
grep -q 'welcome.show' "${temporary_root}/operation-list-after.out"

test "$(git -C "${repository_root}" status --short)" = "${source_before}"

cleanup
trap - EXIT
test ! -e "${temporary_root}"

echo 'Framework update generator smoke passed.'
