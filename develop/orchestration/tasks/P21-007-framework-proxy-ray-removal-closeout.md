# P21-007: Ray Removal, Package Export, and Phase 21 Closeout

Status: Accepted

## Goal

After P21-006 acceptance, remove Ray.Aop and `ext-tokenizer` only through the complete Specification 101 removal gate, then prove clean package export and close Phase 21.

## Source of Truth

- `AGENTS.md`
- `develop/STATE.md`
- `develop/spec/101-framework-owned-transaction-proxy.md`
- `develop/spec/102-phase-21-delivery-plan.md`
- `develop/decisions/137-framework-owned-transaction-proxy.md`
- Accepted P21-002–P21-006 Reports
- Composer/package export contract and all historical Ray references

## Dependencies

P21-006 accepted with synchronized Report/STATE and all removal gates green.

## In Scope

- Remove Ray source adapters/interceptors, Ray fixtures, Composer dependency and lock entries
- Remove `ext-tokenizer` requirement when no other package requires it
- Remove compatibility profile only after the final framework profile is selected
- Namespace/artifact/source scan, clean install, package export, focused/full regression
- Update internal specification/roadmap/TODO/STATE/report references for closeout

## Out of Scope

- New proxy behavior or generator changes
- Runtime Source Scan, fallback compatibility, external Issue/PR, public deployment
- Removing historical D096/D108/P17 evidence; historical decisions remain traceable

## Files Allowed

- `src/Internal/Aop/AopArtifactDirectory.php`
- `src/Internal/Aop/AopAttributeReader.php`
- `src/Internal/Aop/AopAttributeTargetValidator.php`
- `src/Internal/Aop/AopBindingFactory.php`
- `src/Internal/Aop/AopClassValidator.php`
- `src/Internal/Aop/AopCompilationContext.php`
- `src/Internal/Aop/AopConnectionValidator.php`
- `src/Internal/Aop/AopMethodBindingFactory.php`
- `src/Internal/Aop/AopMethodValidator.php`
- `src/Internal/Aop/AopRuntimeBindingRegistrar.php`
- `src/Internal/Aop/AopServiceDefinitionCompiler.php`
- `src/Internal/Aop/AfterCommitBindingInterceptor.php`
- `src/Internal/Aop/AfterCommitMethodInterceptor.php`
- `src/Internal/Aop/FoundationMethodInterceptor.php`
- `src/Internal/Aop/RuntimeAopCompilation.php`
- `src/Internal/Aop/RuntimeAopCompiler.php`
- `src/Internal/Aop/TransactionalBindingInterceptor.php`
- `src/Internal/Aop/TransactionalMethodInterceptor.php`
- `tests/Internal/Aop/FoundationMethodInterceptorTest.php`
- `tests/Internal/Aop/RuntimeAopCompilerTest.php`
- `tests/Fixtures/Aop/AfterCommitService.php`
- `tests/Fixtures/Aop/ClassTransactionalService.php`
- `tests/Fixtures/Aop/FoundationTransactionalOperation.php`
- `tests/Fixtures/Aop/PlainService.php`
- `tests/Fixtures/Aop/ReadonlyTransactionalService.php`
- `tests/Fixtures/Aop/TransactionalService.php`
- `src/Internal/Aop/FrameworkProxyContract/FrameworkProxyProfile.php` (remove the accepted legacy `ray` value/factory only; retain Framework identity)
- `tests/Internal/Aop/FrameworkProxyContract/FrameworkProxyOwnershipGuardTest.php`
- `tests/Internal/Aop/FrameworkProxyGenerator/FrameworkProxyGeneratorTest.php`
- `tests/Internal/Aop/FrameworkProxyCompatibility/FrameworkProxyCompatibilityTest.php`
- `tests/Internal/Aop/FrameworkProxyCompatibility/ray-never-build-runner.php`
- `tests/Internal/Aop/FrameworkProxyCompatibility/ray-named-build-runner.php`
- `tests/Internal/Aop/FrameworkProxyCompatibility/runtime-runner.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/CompatibilityService.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/CompatibilityDependency.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/CompatibilityOperation.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/CompatibilityOperationValue.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/CompatibilityOperationOutcome.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/SignatureMatrixContracts.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/SignatureMatrixService.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/NeverSignatureService.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/ReadonlySignatureService.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/InheritedSignatureParent.php`
- `tests/Fixtures/Aop/FrameworkProxyCompatibility/InheritedSignatureService.php`
- `tests/Internal/Execution/FrameworkProxyOperationOwnershipGuardTest.php`
- `src/Internal/DependencyInjection/FrameworkProxyDefinitionCompiler.php` (remove the accepted Ray `WeavedInterface` compatibility guard only)
- `tests/Internal/DependencyInjection/FrameworkProxyDefinitionCompilerTest.php`
- `tests/Fixtures/DependencyInjection/FrameworkProxy/FrameworkProxyDefinitionFixtures.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`（accepted compatibility option definitionの除去だけ）
- `src/Internal/Console/FrameworkProxyProfileOption.php`
- `src/Internal/Runtime/FrameworkProxyProfileLoader.php`
- `src/Internal/Aop/ProxyProfileArtifact/ProxyProfileArtifactManifest.php`
- `src/Internal/Aop/ProxyProfileArtifact/ProxyProfileArtifactPublisher.php`
- `src/Internal/Runtime/ProxyProfileArtifactLoader.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- `tests/Internal/Console/FrameworkProxyProfileOptionTest.php`
- `tests/Internal/Runtime/FrameworkProxyProfileLoaderTest.php`
- `tests/Internal/Aop/ProxyProfileArtifact/ProxyProfileArtifactPublisherTest.php`
- `tests/Internal/Runtime/ProxyProfileArtifactLoaderTest.php`
- `src/Internal/DependencyInjection/RuntimeContainerDumper.php`
- `tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Consumer/framework-proxy-compatibility.sh`
- `tests/Consumer/framework-proxy-removal-clean-install.sh`
- `docs/guide/project-cli.md`
- `docs/guide/configuration.md`
- `docs/guide/mvp-status.md`
- `docs/internal/framework-proxy-compatibility.md`
- `composer.json`
- `composer.lock`
- `develop/spec/README.md`
- `develop/spec/101-framework-owned-transaction-proxy.md`（Active profile／compatibility／removal-gate closeoutだけ）
- `develop/spec/102-phase-21-delivery-plan.md`（Task status／rollback invariant／Phase closeoutだけ）
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P21-007-framework-proxy-ray-removal-closeout.md`

No file may be deleted or edited unless the exact P21-006 removal manifest names the action and the manifest is reviewed at P21-007 start. The manifest MUST explicitly name removal of the accepted P21-002 profile's `ray` value/factory, the P21-004 Ray Definition guard/fixture, and their exact test updates; it MUST NOT replace that type with a duplicate profile identity. P21-007 MUST amend this Files Allowed section at start if the accepted manifest identifies an additional exact compatibility-profile target. Historical Decision/Report text must remain.

## Constraints

- Do not remove Ray before every Specification 101 gate passes.
- Package export must prove the working-tree and committed archive contract separately.
- Never run `composer install` in the main worktree. Clean-install verification MUST be performed only by the isolated `tests/Consumer/framework-proxy-removal-clean-install.sh` script (or an equivalent explicitly reviewed temporary consumer directory).
- No secret, generated source, or full vendor dump in reports.
- Worker does not commit, push, or deploy before Orchestrator review.

## Acceptance Criteria

- [x] Full signature/DI/artifact/lifecycle/compatibility/removal gates pass.
- [x] No Ray namespace, Composer dependency, `WeavedInterface`, Ray fixture, or legacy artifact remains outside historical Decision/Report references.
- [x] Composer strict audit, isolated Consumer clean install, package export, focused and full PHPUnit pass.
- [x] Framework profile is the sole selected profile; Runtime has no fallback or Source Scan.
- [x] TODO/STATE/Decision index/Report mark Phase 21 closeout Review Pending; Orchestrator performs final Acceptance.

## Required Commands

```bash
docker compose run --rm app php vendor/bin/phpunit
composer validate --strict
test -f develop/orchestration/reports/P21-006-ray-removal-manifest.md
bash tests/Consumer/framework-package-export.sh
bash tests/Consumer/framework-proxy-removal-clean-install.sh
! rg -n 'Ray\\Aop|Ray\.Aop|ray/aop|WeavedInterface|FrameworkProxyProfile.*ray' src tests composer.json composer.lock --glob '*.php' --glob '*.json' --glob '*.lock'
! rg -n "FrameworkProxyProfile::RAY|FrameworkProxyProfile::ray|const RAY = 'ray'" src tests --glob '*.php'
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P21-007-framework-proxy-ray-removal-closeout.md`
