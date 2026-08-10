#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
temporary_root="$(mktemp -d)"
source_before="$(git -C "${repository_root}" status --short)"
trap 'rm -rf "${temporary_root}"' EXIT

bash "${repository_root}/tests/Consumer/framework-package-export.sh"

mkdir -p "${temporary_root}/consumer"
docker run --rm \
    --user "$(id -u):$(id -g)" \
    --volume "${repository_root}:/repository:ro" \
    --volume "${temporary_root}/consumer:/consumer" \
    --workdir /consumer \
    blackops/framework:dev sh -c \
    'composer init --name=blackops/compatibility-consumer --require=blackops/framework:@dev --no-interaction >/dev/null && composer config --json repositories.blackops '\''{"type":"path","url":"/repository","options":{"symlink":false}}'\'' && composer update --no-interaction --prefer-dist >/dev/null && test ! -L vendor/blackops/framework && php -r '\''require "vendor/autoload.php"; foreach (["BlackOps\\Internal\\Aop\\FrameworkProxyContract\\FrameworkProxyProfile", "BlackOps\\Internal\\Runtime\\FrameworkProxyProfileLoader"] as $class) { if (!class_exists($class)) { throw new RuntimeException("missing autoload: $class"); } } foreach (["Ray\\Aop\\WeavedInterface"] as $interface) { if (!interface_exists($interface)) { throw new RuntimeException("missing interface autoload: $interface"); } } '\'''

docker run --rm \
    --user "$(id -u):$(id -g)" \
    --volume "${temporary_root}/consumer:/consumer" \
    --workdir /consumer \
    blackops/framework:dev php -r '
require "vendor/autoload.php";
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Internal\Console\FrameworkProxyProfileOption;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
$command = new class extends Command {
    protected function configure(): void { FrameworkProxyProfileOption::configure($this); }
};
if ($command->getDefinition()->getOption("proxy-profile")->getDefault() !== FrameworkProxyProfile::RAY) { throw new RuntimeException("Ray default missing"); }
$input = new ArrayInput(["--proxy-profile" => FrameworkProxyProfile::FRAMEWORK], $command->getDefinition());
if (!FrameworkProxyProfileOption::fromInput($input)->equals(FrameworkProxyProfile::FRAMEWORK)) { throw new RuntimeException("Framework option missing"); }
'

printf 'Framework proxy compatibility journey passed.\n'
test "$(git -C "${repository_root}" status --short)" = "${source_before}"
