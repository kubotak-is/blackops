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
    'composer init --name=blackops/framework-only-consumer --require=blackops/framework:@dev --no-interaction >/dev/null && composer config --json repositories.blackops '\''{"type":"path","url":"/repository","options":{"symlink":false}}'\'' && composer update --no-interaction --prefer-dist >/dev/null && test ! -L vendor/blackops/framework && test ! -d vendor/ray && php -r '\''require "vendor/autoload.php"; foreach (["BlackOps\\Internal\\Aop\\FrameworkProxyContract\\FrameworkProxyProfile", "BlackOps\\Internal\\Runtime\\FrameworkProxyProfileLoader"] as $class) { if (!class_exists($class)) { throw new RuntimeException("missing autoload: $class"); } } $profileClass = "BlackOps\\Internal\\Aop\\FrameworkProxyContract\\FrameworkProxyProfile"; if ($profileClass::from("framework")->value !== "framework") { throw new RuntimeException("Framework profile missing"); } '\'''

printf 'Framework-only removal clean-install journey passed.\n'
test "$(git -C "${repository_root}" status --short)" = "${source_before}"
