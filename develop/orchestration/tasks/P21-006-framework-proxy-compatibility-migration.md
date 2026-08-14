# P21-006: Ray/Framework Compatibility and Migration

Status: Accepted

## Goal

Provide mutually exclusive `ray`／`framework` build profiles, migration checks, golden compatibility fixtures, previous-build rollback, and Consumer package/export evidence without removing Ray.

## Source of Truth

- `AGENTS.md`
- `develop/STATE.md`
- `develop/spec/101-framework-owned-transaction-proxy.md`
- `develop/spec/102-phase-21-delivery-plan.md`
- `develop/decisions/137-framework-owned-transaction-proxy.md`
- Accepted P21-002–P21-005 Reports
- Current build commands, artifact loader, Consumer scripts, Composer export contract

## Dependencies

P21-005 accepted with synchronized Report/STATE.

## In Scope

- Compile-time profile selection and manifest recording
- Same fixture matrix in Ray and Framework modes
- Application migration guidance and unsupported-signature handling
- Previous complete Container/manifest/artifact rollback
- Consumer clean-install/package-export compatibility evidence
- Detection that no Definition resolves both proxy modes

## Out of Scope

- New generator, DI preservation, or runtime binding implementation
- Ray Composer/source/fixture deletion (P21-007 only)
- External publication, Documentation Website, unrelated Public Guides, commit/push/deploy (`docs/guide/project-cli.md` is explicitly in scope)

## Files Allowed

- `src/Internal/Console/FrameworkProxyProfileOption.php`
- `src/Internal/Aop/ProxyProfileArtifact/**`
- `src/Internal/Runtime/FrameworkProxyProfileLoader.php`
- `src/Internal/Runtime/ProxyProfileArtifactLoader.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`（`build:compile` lazy commandの`--proxy-profile`定義だけ）
- `src/Internal/DependencyInjection/RuntimeContainerDumper.php`
- `tests/Internal/Console/FrameworkProxyProfileOptionTest.php`
- `tests/Internal/Runtime/FrameworkProxyProfileLoaderTest.php`
- `tests/Internal/Aop/ProxyProfileArtifact/**`
- `tests/Internal/Runtime/ProxyProfileArtifactLoaderTest.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- `tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`（compiled Ray Container journeyのprocess isolationだけ）
- `tests/Integration/ApplicationHttpRuntimeTest.php`（compiled Ray Container journeyのprocess isolationだけ）
- `tests/Integration/MvpSampleEndToEndTest.php`（compiled Ray Container journeyのprocess isolationだけ）
- `tests/Consumer/framework-proxy-compatibility.sh`
- `tests/Internal/Aop/FrameworkProxyCompatibility/**`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/**`
- `docs/guide/project-cli.md`
- `docs/guide/configuration.md`（profile別artifact deployment unitの説明だけ）
- `docs/guide/mvp-status.md`（Stable 1.1.0／main availability rowだけ）
- `docs/internal/framework-proxy-compatibility.md`
- `docs/internal/README.md`（上記internal guideへのindex linkだけ）
- `develop/decisions/137-framework-owned-transaction-proxy.md`（User-selected legacy-Ray `never`／named variadic compatibility exceptionsだけ）
- `develop/spec/101-framework-owned-transaction-proxy.md`（上記exceptionsのnormative boundaryだけ）
- `develop/spec/102-phase-21-delivery-plan.md`（P21-006／P21-007 acceptance boundary同期だけ）
- `develop/orchestration/reports/P21-006-ray-removal-manifest.md`
- `develop/orchestration/reports/P21-006-framework-proxy-compatibility-migration.md`
- `develop/orchestration/tasks/P21-007-framework-proxy-ray-removal-closeout.md`（Orchestratorによるaccepted removal manifestとのexact scope同期だけ）
- `develop/TODO.md`（Orchestrator status同期だけ）
- `develop/STATE.md`

The central Application-aware build integration MUST expose exactly `build:compile --proxy-profile=ray|framework`; compatibility default is `ray`. The standalone legacy `blackops:build:compile` command is out of this profile surface because it does not invoke AOP. P21-004 and P21-005 provide seams only; central wiring and profile selection are intentionally deferred to this Task. RuntimeContainerDumper must load the immutable Build-ID/manifest artifact unit rather than hardcoded direct `aop` paths. The accepted `BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile` is the sole profile identity and is read-only in this Task; the CLI option and loader consume it instead of adding another Build-layer profile type. Do not edit `composer.json`, `composer.lock`, Ray source adapters, or P21-002–P21-005 implementation files except the explicitly listed central Application-aware command/test, Dumper, and help-evidence files.

The accepted removal manifest MUST name the P21-004 Ray compatibility guard in `FrameworkProxyDefinitionCompiler` and its exact DI test/fixture updates. Those references remain required during dual-profile compatibility and become explicit P21-007 removal targets.

The selected profile MUST be recorded for both Ray and Framework in one immutable Build-ID-bound profile artifact unit. The unit MUST list and hash the exact Ray proxy files or bind the exact Framework manifest/directory, and RuntimeContainerDumper MUST bootstrap only through a loader that validates the embedded expected manifest hash, Build ID, profile, inventory, and file/delegated Framework identity before requiring proxy code. The legacy Ray compiler itself remains read-only; a compatibility artifact adapter may copy its returned files into an immutable Build-ID/content-hash unit. The prior complete unit must remain selectable and no directory scan fallback is allowed.

Integration tests that compile and load Ray Containers from temporary release paths MUST isolate each journey from other tests and execute the Ray build phase in a child process before the parent process loads the immutable Profile Unit. Do not weaken the runtime class-path identity collision guard to accommodate in-process build/runtime reuse or test-order reuse that Production forbids.

## Constraints

- Exactly one profile per build; no runtime chaining or unproxied fallback.
- Rollback selects a complete matching artifact set, never a second interceptor.
- Unsupported Framework signatures remain explicitly on Ray until refactored.
- The only shared-matrix exceptions are legacy Ray 2.20.0 `never` compilation on PHP 8.5 and named-variadic forwarding. Framework support for both and every other supported row remain required; do not patch Ray or broaden the exceptions.
- Keep secrets out of compatibility diagnostics and artifacts.

## Acceptance Criteria

- [x] Ray and Framework modes pass the same supported signature, DI, Transaction, AfterCommit, Operation, and failure fixture matrix except the explicit legacy-Ray `never` compilation and named-variadic forwarding rows; Framework compile/runtime support and bounded Ray evidence for both are required.
- [x] Profile/manifest mismatch and dual-proxy detection fail safely.
- [x] Previous-build rollback and OPcache-safe artifact identity are evidenced.
- [x] Consumer clean install and package export pass while Ray remains present.
- [x] `build:compile --proxy-profile=ray|framework` is explicit, defaults to `ray`, and central command/test/docs wiring is verified.
- [x] Accepted `develop/orchestration/reports/P21-006-ray-removal-manifest.md` names every Ray source/test/Composer/central integration removal target for P21-007.
- [x] Focused PHPUnit/Consumer/Mago/format/management-ID/diff checks pass.

## Required Commands

```bash
docker compose run --rm app php vendor/bin/phpunit tests/Internal/Console/FrameworkProxyProfileOptionTest.php tests/Internal/Runtime/FrameworkProxyProfileLoaderTest.php tests/Internal/Console/ApplicationBuildCompileCommandTest.php tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php tests/Internal/Application/ApplicationConsoleKernelTest.php tests/Internal/Aop/FrameworkProxyCompatibility
bash tests/Consumer/framework-proxy-compatibility.sh
rg -n "build:compile|proxy-profile|framework-proxy" docs/guide/project-cli.md docs/internal/framework-proxy-compatibility.md src/Internal/Console src/Internal/DependencyInjection tests/Internal/Console tests/Internal/DependencyInjection tests/Internal/Application
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P21-006-framework-proxy-compatibility-migration.md`
