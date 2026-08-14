# P21-004 Framework Proxy Symfony DI Preservation

Status: Accepted

## Summary

Implemented the Symfony Definition preservation compiler on the accepted Framework proxy generator and artifact-loader seams. Supported attributed definitions retain their original object and service/alias graph; only the class reference is changed after the complete target plan and artifact map are validated. Framework binding metadata is retained in a WeakMap registry for the runtime phase, without adding runtime initializer calls or source scanning.

## Changed Files

- `src/Internal/Aop/FrameworkProxyDefinition/FrameworkProxyDefinitionBinding.php`
- `src/Internal/Aop/FrameworkProxyDefinition/FrameworkProxyDefinitionCompilation.php`
- `src/Internal/Aop/FrameworkProxyDefinition/FrameworkProxyDefinitionDiagnosticCode.php`
- `src/Internal/Aop/FrameworkProxyDefinition/FrameworkProxyDefinitionException.php`
- `src/Internal/Aop/FrameworkProxyDefinition/FrameworkProxyDefinitionRegistry.php`
- `src/Internal/DependencyInjection/FrameworkProxyDefinitionCompiler.php`
- `tests/Internal/Aop/FrameworkProxyDefinition/FrameworkProxyDefinitionValueTest.php`
- `tests/Internal/DependencyInjection/FrameworkProxyDefinitionCompilerTest.php`
- `tests/Fixtures/DependencyInjection/FrameworkProxy/FrameworkProxyDefinitionFixtures.php`
- `tests/Fixtures/DependencyInjection/FrameworkProxy/GlobalFrameworkProxyFixture.php`
- `develop/orchestration/tasks/P21-004-framework-proxy-symfony-di-preservation.md`
- `develop/orchestration/tasks/P21-005-framework-proxy-runtime-ownership.md`
- `develop/orchestration/tasks/P21-006-framework-proxy-compatibility-migration.md`
- `develop/orchestration/tasks/P21-007-framework-proxy-ray-removal-closeout.md`
- `develop/orchestration/reports/P21-004-framework-proxy-symfony-di-preservation.md`
- `develop/STATE.md`
- `develop/TODO.md`

## Decisions and Assumptions

- The compiler consumes `FrameworkProxyGenerator::generateBatch()` and `FrameworkProxyArtifactLoader::load()` exactly; generator/artifact and Ray compiler paths remain unchanged.
- Definitions are inspected and unsupported/dual ownership is rejected before generation; the full class map is validated before any `Definition::setClass()` mutation.
- Synthetic definitions without a Framework target are skipped. An attributed synthetic definition emits `BO_PROXY_DEFINITION_SYNTHETIC`.
- Ray ownership is rejected only after a definition is confirmed as a Framework target. Both namespaced and global generated proxy prefixes are guarded.
- Binding metadata contains service ID, source/proxy classes, contract metadata, and ownership marker only; no secret values or source paths are copied.

## Commands and Results

- `docker compose run --rm app php vendor/bin/phpunit tests/Internal/Aop/FrameworkProxyDefinition tests/Internal/DependencyInjection/FrameworkProxyDefinitionCompilerTest.php` — PASS (13 tests, 49 assertions).
- `docker compose run --rm app mago format --check src tests` — PASS.
- `docker compose run --rm app mago lint src/Internal/Aop/FrameworkProxyDefinition src/Internal/DependencyInjection/FrameworkProxyDefinitionCompiler.php` — PASS, no issues.
- `docker compose run --rm app mago analyze src/Internal/Aop/FrameworkProxyDefinition src/Internal/DependencyInjection/FrameworkProxyDefinitionCompiler.php` — PASS, no issues.
- `docker compose run --rm app php vendor/bin/phpunit` — PASS (2,288 tests, 9,324 assertions; existing deprecation/notices only).
- `bash tests/Consumer/framework-package-export.sh` — PASS for the pre-commit Git/Composer package export contract.
- Management-ID PHP guard — PASS.
- `git diff --check` — PASS.

## Acceptance Criteria

- Definition state snapshot covers arguments, bindings, properties, visibility, shared scope, autowiring, tags, autoconfiguration, instanceof conditionals, ordered calls including returns-clone, configurator, file, deprecation, alias identity/deprecation, and shared service identity — PASS.
- Factory, lazy, synthetic, abstract, and decoration boundaries use stable diagnostic codes; unsupported targets do not fall back — PASS.
- Generated container resolves the proxy and public alias to the same shared instance, executes ordered calls, applies properties, invokes the valid configurator, and preserves the configured result — PASS.
- Required focused PHPUnit, Mago, management-ID, and diff checks — PASS.

## Remaining Issues

No blocking issue remains. The accepted Ray compatibility guard and exact DI test/fixture updates remain until P21-007 and are named by the follow-on removal-manifest contract.

## Suggested Next Action

Commit the accepted P21-004 change, rerun the exact Git HEAD package export, then start P21-005 Transaction Runtime and ownership binding.
