# P21-006 Framework Proxy Compatibility and Migration

Status: Accepted

## Summary

Added the explicit `build:compile --proxy-profile=ray|framework` surface with
Ray default, Framework compiled-container runtime wiring, immutable loader
identity checks, dual-profile golden service journeys, rollback identity
evidence, and corrected Ray removal inventory. Added a neutral immutable
Build-ID/content-hash profile artifact unit and prevalidation loader shared by
both profiles, with configuration/release/internal index guidance. Ray source
and Composer files remain untouched.

## Changed Files

- `src/Internal/Console/FrameworkProxyProfileOption.php`
- `src/Internal/Runtime/FrameworkProxyProfileLoader.php`
- `src/Internal/Runtime/ProxyProfileArtifactLoader.php`
- `src/Internal/Aop/ProxyProfileArtifact/ProxyProfileArtifactManifest.php`
- `src/Internal/Aop/ProxyProfileArtifact/ProxyProfileArtifactPublisher.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`
- `src/Internal/DependencyInjection/RuntimeContainerDumper.php`
- `tests/Internal/Console/FrameworkProxyProfileOptionTest.php`
- `tests/Internal/Runtime/FrameworkProxyProfileLoaderTest.php`
- `tests/Internal/Runtime/ProxyProfileArtifactLoaderTest.php`
- `tests/Internal/Aop/ProxyProfileArtifact/ProxyProfileArtifactPublisherTest.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- `tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php` (compiled-container process isolation only)
- `tests/Integration/ApplicationHttpRuntimeTest.php` (compiled-container process isolation only)
- `tests/Integration/MvpSampleEndToEndTest.php` (compiled-container process isolation only)
- `tests/Internal/Aop/FrameworkProxyCompatibility/FrameworkProxyCompatibilityTest.php`
- `tests/Internal/Aop/FrameworkProxyCompatibility/ray-named-build-runner.php` (bounded Legacy Ray value-loss harness)
- `tests/Internal/Aop/FrameworkProxyCompatibility/ray-never-build-runner.php` (bounded Legacy Ray failure harness)
- `tests/Internal/Aop/FrameworkProxyCompatibility/runtime-runner.php` (bounded fresh-process runtime harness)
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/CompatibilityService.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/CompatibilityDependency.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/CompatibilityOperation.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/CompatibilityOperationValue.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/CompatibilityOperationOutcome.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/SignatureMatrixContracts.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/SignatureMatrixService.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/ReadonlySignatureService.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/InheritedSignatureParent.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/InheritedSignatureService.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/NeverSignatureService.php`
- `tests/Consumer/framework-proxy-compatibility.sh`
- `docs/guide/project-cli.md`
- `docs/guide/configuration.md`
- `docs/guide/mvp-status.md`
- `docs/internal/README.md`
- `docs/internal/framework-proxy-compatibility.md`
- `develop/decisions/137-framework-owned-transaction-proxy.md`
- `develop/spec/101-framework-owned-transaction-proxy.md`
- `develop/spec/102-phase-21-delivery-plan.md`
- `develop/orchestration/reports/P21-006-ray-removal-manifest.md`
- `develop/orchestration/reports/P21-006-framework-proxy-compatibility-migration.md`
- `develop/STATE.md`
- `develop/TODO.md` (Orchestrator scope/status synchronization)
- `develop/orchestration/tasks/P21-006-framework-proxy-compatibility-migration.md` (Orchestrator scope synchronization)
- `develop/orchestration/tasks/P21-007-framework-proxy-ray-removal-closeout.md` (Orchestrator Files Allowed synchronization)

## Commands and Results

- `docker compose run --rm app php vendor/bin/phpunit tests/Internal/Console/FrameworkProxyProfileOptionTest.php tests/Internal/Runtime/FrameworkProxyProfileLoaderTest.php tests/Internal/Console/ApplicationBuildCompileCommandTest.php tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php tests/Internal/Application/ApplicationConsoleKernelTest.php tests/Internal/Aop/FrameworkProxyCompatibility`: Orchestrator rerun after both exception fixtures PASS, 45 tests, 274 assertions.
- `docker compose run --rm app php vendor/bin/phpunit tests/Internal/Aop/ProxyProfileArtifact/ProxyProfileArtifactPublisherTest.php tests/Internal/Runtime/ProxyProfileArtifactLoaderTest.php`: PASS, 13 tests, 32 assertions (first-publish Framework sibling/hash, unit/source symlinks, build/profile/hash mismatch, identity collision, class-constant/anonymous token safety, syntax-invalid preflight, nested/extra inventory, zero-target and rollback units).
- `docker compose run --rm app php vendor/bin/phpunit tests/Integration/ApplicationConsoleKernelTest.php tests/Integration/ApplicationHttpRuntimeTest.php tests/Integration/MvpSampleEndToEndTest.php`: Orchestrator final PASS, 10 tests, 262 assertions. Each compiled Ray journey isolates the PHPUnit process and executes the build in a child process before the parent loads the immutable Profile Unit; the Production identity collision guard remains strict.
- `docker compose run --rm app php vendor/bin/phpunit --no-progress`: Orchestrator full regression PASS, 2,346 tests, 9,569 assertions. One dependency deprecation, two PHPUnit deprecations, and thirteen PHPUnit notices remain non-blocking.
- Combined focused command before strict realpath collision correction: PASS, 42 tests, 207 assertions. After strict realpath enforcement, the same-process Ray build journey requires test isolation because generated class identities are already loaded from a prior temporary path.
- `docker compose run --rm app mago format --check src tests`: PASS.
- `docker compose run --rm app mago analyze src/Internal/Aop/ProxyProfileArtifact src/Internal/Runtime/ProxyProfileArtifactLoader.php src/Internal/Console/ApplicationBuildCompileCommand.php src/Internal/DependencyInjection/RuntimeContainerDumper.php`: completed with nonfatal mixed-assignment warnings from strict JSON decoding; no blocking errors.
- `rg -n "build:compile|proxy-profile|framework-proxy" docs/guide/project-cli.md docs/internal/framework-proxy-compatibility.md src/Internal/Console src/Internal/DependencyInjection tests/Internal/Console tests/Internal/DependencyInjection tests/Internal/Application`: PASS.
- `bash tests/Consumer/framework-proxy-compatibility.sh`: PASS under Orchestrator-approved Docker access; exact package export, isolated Composer 43-package install with `Mirroring from /repository`, Production autoload for Framework/Ray compatibility classes, profile option behavior, non-symlink vendor copy, and unchanged worktree all passed. An earlier run exposed overescaped namespace literals in the Consumer probe; the script was corrected and the exact journey rerun successfully.
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\\.md:[0-9]+' src tests --glob '*.php'`: PASS.
- `git diff --check`: PASS.
- `docker compose run --rm app php vendor/bin/phpunit tests/Internal/Aop/ProxyProfileArtifact/ProxyProfileArtifactPublisherTest.php tests/Internal/Runtime/ProxyProfileArtifactLoaderTest.php tests/Internal/Aop/FrameworkProxyGenerator/FrameworkProxyGeneratorTest.php tests/Internal/Aop/FrameworkProxyDefinition/FrameworkProxyDefinitionValueTest.php`: Orchestrator final rerun PASS, 60 tests, 131 assertions (artifact identity plus Framework signature/DI coverage).
- Ray never-signature reproduction: bounded `ray-never-build-runner.php` executes `build:compile --proxy-profile=ray` with `NeverSignatureServiceProvider`, asserts nonzero PHP 8.5 output containing `A never-returning method must not return`, excludes generated method/source content from diagnostics, and removes its temporary build tree. A separate Framework-only fresh-process fixture compiles and invokes the same `never` contract.
- The shared fresh-process Ray/Framework matrix now executes union, intersection, DNF, nullable, mixed, `static`, `self`, `parent`, positional variadic, defaults, unrelated class/method/parameter attributes, readonly, inherited method, DI, Transaction, AfterCommit, Operation, and equivalent failure behavior.
- Two real Application-aware builds with distinct Build IDs generate complete Container and Operation/HTTP/Frontend/Command manifest/Profile Unit trees. After the current build, a fresh runner loads the previous Container and resolves its scheduled actor; a cross-Build mutated Container is rejected before service execution.
- User selected a second Legacy Ray-only compatibility exception for named variadic forwarding. Framework fresh-process invocation preserves `variadic(prefix: 'named', values: 4)` as `named4`; bounded Ray build/runtime returns `named`, excludes generated source/sensitive fixture text from diagnostics, and cleans its temporary tree. Both process boundaries enforce a 15-second timeout. `docker compose run --rm app php vendor/bin/phpunit tests/Internal/Aop/FrameworkProxyCompatibility/FrameworkProxyCompatibilityTest.php`: Orchestrator PASS, 6 tests, 86 assertions.
- Documentation Reviewer final re-review: P1 0, P2 0, P3 0; Acceptance permitted after the P21-007 Specification closeout scope and P21-006 Changed Files inventory were synchronized.

## Acceptance Criteria

- [x] Ray and Framework execute the same supported Signature rows except the two explicit Legacy Ray rows (`never` compilation and named-variadic forwarding), plus constructor-DI/singleton, transactional, nested, rollback, AfterCommit, and proxied Operation behavior in bounded fresh runners. Framework support and bounded Ray evidence pass for both exceptions.
- [x] Framework loader and dumper reject Build/profile/hash and mixed-input mismatches.
- [x] Two complete Build IDs remain independently loadable; cross-identity load fails.
- [x] Consumer clean install and package export pass while Ray remains installed.
- [x] Explicit profile option/default and outer lazy-command help are covered.
- [x] Ray removal manifest names exact source, test, fixture, Composer, central, and documentation actions.
- [x] Complete shared Signature/DI parity under the decided boundary: all supported rows run in both profiles except the two recorded Legacy Ray-only exceptions; Framework compile/runtime support and bounded Ray evidence pass for both.

## Remaining Issues

No P21-006 blocker remains. The two explicitly bounded Legacy Ray compatibility
exceptions (`never` compilation and named-variadic forwarding) remain only until
the Ray profile is removed by P21-007; Framework support and no-fallback behavior
remain required throughout.

## Suggested Next Action

Commit the accepted P21-006 unit, prove its committed package archive, then
start P21-007 from the accepted Ray removal manifest.
