# P21-006: Ray/Framework Compatibility and Migration

Status: Planned

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
- `src/Internal/Runtime/FrameworkProxyProfileLoader.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/DependencyInjection/RuntimeContainerDumper.php`
- `tests/Internal/Console/FrameworkProxyProfileOptionTest.php`
- `tests/Internal/Runtime/FrameworkProxyProfileLoaderTest.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- `tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Consumer/framework-proxy-compatibility.sh`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/**`
- `docs/guide/project-cli.md`
- `docs/internal/framework-proxy-compatibility.md`
- `develop/orchestration/reports/P21-006-ray-removal-manifest.md`
- `develop/orchestration/reports/P21-006-framework-proxy-compatibility-migration.md`
- `develop/STATE.md`

The central Application-aware build integration MUST expose exactly `build:compile --proxy-profile=ray|framework`; compatibility default is `ray`. The standalone legacy `blackops:build:compile` command is out of this profile surface because it does not invoke AOP. P21-004 and P21-005 provide seams only; central wiring and profile selection are intentionally deferred to this Task. RuntimeContainerDumper must load the immutable Build-ID/manifest artifact unit rather than hardcoded direct `aop` paths. The accepted `BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile` is the sole profile identity and is read-only in this Task; the CLI option and loader consume it instead of adding another Build-layer profile type. Do not edit `composer.json`, `composer.lock`, Ray source adapters, or P21-002–P21-005 implementation files except the explicitly listed central Application-aware command/test, Dumper, and help-evidence files.

The accepted removal manifest MUST name the P21-004 Ray compatibility guard in `FrameworkProxyDefinitionCompiler` and its exact DI test/fixture updates. Those references remain required during dual-profile compatibility and become explicit P21-007 removal targets.

## Constraints

- Exactly one profile per build; no runtime chaining or unproxied fallback.
- Rollback selects a complete matching artifact set, never a second interceptor.
- Unsupported Framework signatures remain explicitly on Ray until refactored.
- Keep secrets out of compatibility diagnostics and artifacts.

## Acceptance Criteria

- [ ] Ray and Framework modes pass the same supported signature, DI, Transaction, AfterCommit, Operation, and failure fixture matrix.
- [ ] Profile/manifest mismatch and dual-proxy detection fail safely.
- [ ] Previous-build rollback and OPcache-safe artifact identity are evidenced.
- [ ] Consumer clean install and package export pass while Ray remains present.
- [ ] `build:compile --proxy-profile=ray|framework` is explicit, defaults to `ray`, and central command/test/docs wiring is verified.
- [ ] Accepted `develop/orchestration/reports/P21-006-ray-removal-manifest.md` names every Ray source/test/Composer/central integration removal target for P21-007.
- [ ] Focused PHPUnit/Consumer/Mago/format/management-ID/diff checks pass.

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
