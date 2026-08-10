# P21-006 Ray Removal Manifest

Status: reviewed inventory for P21-007; no removal is performed by P21-006.
The default `ray` profile remains the rollback path until every item below is
removed in one accepted closeout.

## Composer and lock actions

- `composer.json`: remove only the direct `ray/aop` requirement. There is no
  root `ext-tokenizer` requirement to remove.
- `composer.lock`: regenerate the lock so the `ray/aop` package entry and its
  own `ext-tokenizer` requirement disappear; retain unrelated packages and
  their `ext-tokenizer` requirements. Record `composer why ext-tokenizer`
  evidence instead of treating every remaining reference as Ray residue.

## Ray runtime source actions

Delete these exact files and their autoload references:

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

In `src/Internal/Console/ApplicationBuildCompileCommand.php`, remove only the
Ray branch and the compatibility option after Framework is sole profile. In
`src/Internal/DependencyInjection/RuntimeContainerDumper.php`, remove only the
legacy Ray `requiredFiles` path after no caller supplies it. The standalone
`src/Internal/Console/CompileBuildArtifactsCommand.php` is not a Ray target and
must remain unchanged.

## Exact tests and fixtures

- Delete `tests/Internal/Aop/FoundationMethodInterceptorTest.php` and
  `tests/Internal/Aop/RuntimeAopCompilerTest.php`.
- In `tests/Internal/Aop/FrameworkProxyContract/FrameworkProxyOwnershipGuardTest.php`, remove Ray
  conflict cases or replace them with generic ownership mismatch cases.
- In `tests/Internal/Aop/FrameworkProxyGenerator/FrameworkProxyGeneratorTest.php`,
  replace tampered `ray` profile strings with a generic unsupported-profile
  value while retaining Framework generator coverage.
- In `tests/Internal/Execution/FrameworkProxyOperationOwnershipGuardTest.php`,
  remove Ray-specific conflict assertions or convert them to generic mismatch.
- In `tests/Internal/Runtime/FrameworkProxyProfileLoaderTest.php`, retain
  immutable loader checks while removing Ray profile cases after the option is
  gone.
- Delete these Ray fixtures: `tests/Fixtures/Aop/AfterCommitService.php`,
  `ClassTransactionalService.php`, `FoundationTransactionalOperation.php`,
  `PlainService.php`, `ReadonlyTransactionalService.php`, and
  `TransactionalService.php`.
- In `tests/Internal/Aop/FrameworkProxyCompatibility/FrameworkProxyCompatibilityTest.php`,
  remove the Ray data-provider case and both bounded Legacy Ray exception
  assertions; retain Framework-only golden behavior, including `never` and
  named variadic support.
- Delete the Ray-only compatibility runners
  `tests/Internal/Aop/FrameworkProxyCompatibility/ray-never-build-runner.php`
  and
  `tests/Internal/Aop/FrameworkProxyCompatibility/ray-named-build-runner.php`.
  Retain `runtime-runner.php` for the Framework-only golden journey.
- Retain these neutral fixtures for that Framework-only golden behavior:
  `tests/Fixtures/Aop/FrameworkProxyCompatibility/CompatibilityService.php`,
  `CompatibilityDependency.php`, `CompatibilityOperation.php`,
  `CompatibilityOperationValue.php`, `CompatibilityOperationOutcome.php`,
  `SignatureMatrixContracts.php`, `SignatureMatrixService.php`,
  `NeverSignatureService.php`, `ReadonlySignatureService.php`,
  `InheritedSignatureParent.php`, and `InheritedSignatureService.php`.
- In `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`, remove
  only Ray imports/assertions and the Ray build journey; retain Framework
  profile coverage.
- In `tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php`,
  remove only `RuntimeAopCompiler`, `WeavedInterface`, and the Ray dump test;
  retain manifest-aware Framework tests.
- In `tests/Internal/DependencyInjection/FrameworkProxyDefinitionCompilerTest.php`,
  remove the `Ray\Aop\WeavedInterface` import, the
  `testRayOwnedDefinitionCannotEnterFrameworkMode` method, and the
  `RayOwnedFrameworkService` use only. Keep
  `testGlobalGeneratedPrefixCannotEnterFrameworkMode` and its
  `__BlackOpsProxy_` fixture.
- In `tests/Fixtures/DependencyInjection/FrameworkProxy/FrameworkProxyDefinitionFixtures.php`,
  remove only `RayOwnedFrameworkService`; keep
  `tests/Fixtures/DependencyInjection/FrameworkProxy/GlobalFrameworkProxyFixture.php`
  and its global generated-prefix guard.
- In `src/Internal/DependencyInjection/FrameworkProxyDefinitionCompiler.php`,
  remove only the `is_a(..., 'Ray\\Aop\\WeavedInterface', ...)` condition from
  `assertSingleOwnership`; retain both Framework/global `__BlackOpsProxy_`
  guards.

## Central profile and documentation actions

- Delete `src/Internal/Console/FrameworkProxyProfileOption.php` and
  `tests/Internal/Console/FrameworkProxyProfileOptionTest.php`; remove their
  import/configurer/help assertion from
  `src/Internal/Application/ApplicationConsoleKernel.php` and
  `tests/Internal/Application/ApplicationConsoleKernelTest.php`.
- In `src/Internal/Aop/FrameworkProxyContract/FrameworkProxyProfile.php`,
  remove only the `RAY` constant and `ray()` factory; retain the canonical
  Framework identity and reject every other value.
- In `src/Internal/Runtime/FrameworkProxyProfileLoader.php`, remove the
  runtime-selected profile argument while retaining exact Framework
  Build-ID/manifest-hash loading.
- `src/Internal/Console/ApplicationBuildCompileCommand.php`,
  `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`,
  `src/Internal/DependencyInjection/RuntimeContainerDumper.php`, and
  `tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php`: remove
  compatibility branching while preserving Framework loader identity checks.
- Replace `tests/Consumer/framework-proxy-compatibility.sh` with the isolated
  Framework-only `tests/Consumer/framework-proxy-removal-clean-install.sh`
  journey.
- `docs/guide/project-cli.md` and `docs/internal/framework-proxy-compatibility.md`:
  remove `--proxy-profile=ray`, rollback compatibility wording, and the two
  Legacy Ray exception notices after their evidence runners are deleted.
- `docs/guide/configuration.md`: replace the Ray／Framework selector and Ray
  inventory wording with the sole Framework Profile Unit deployment contract.
- `docs/guide/mvp-status.md`: replace the experimental Ray／Framework profile
  row with the accepted Framework-only Build Profile Artifact surface.

## Normative specification closeout actions

- `develop/spec/101-framework-owned-transaction-proxy.md`: make `framework`
  the sole active build profile; remove the active Ray default, compatibility
  selector, rollback-to-Ray wording, and both temporary Legacy Ray exceptions.
  Retain the Framework Signature Matrix, no-fallback rule, immutable Profile
  Unit, complete-release rollback, and a closeout statement that the removal
  gate was accepted by P21-007.
- `develop/spec/102-phase-21-delivery-plan.md`: synchronize P21-002 through
  P21-007 as accepted, mark Phase 21 complete, and replace the active
  compatibility rollback invariant with the Framework-only artifact invariant.
  Retain the ordered Task history and traceability; do not rewrite historical
  Decision evidence.

The accepted target remains one Framework-owned artifact/runtime chain. The
global generated-prefix guard, its test, and its fixture are intentionally not
Ray removal targets.

## Immutable profile artifact closeout action

Retain `src/Internal/Aop/ProxyProfileArtifact/**` and
`src/Internal/Runtime/ProxyProfileArtifactLoader.php` as the neutral
Build-ID/content-hash artifact boundary. Framework-only migration removes the
Ray publisher branch and Ray inventory assertions, but keeps the common
manifest unit and Framework directory/hash delegation. The Framework loader
must continue to prevalidate the exact unit manifest and identity before
delegating to `FrameworkProxyArtifactLoader`; no source scan or glob fallback
may be introduced. The corresponding publisher/loader tests remain in the
Framework-only verification set until the common boundary is retired by a
separate decision.
