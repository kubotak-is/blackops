# P21-003 Framework Proxy Generator and Artifact Contract

Status: Accepted

## Summary

Implemented a Framework-owned deterministic subclass emitter and artifact unit for the accepted P21-002 metadata seam. The generator emits only the Transactional/AfterCommit ABI, preserves supported PHP signatures and unrelated attributes, and gives readonly subclasses an external WeakMap-backed one-time initializer. Batch generation publishes all proxy files and a data-only JSON manifest from one staging directory.

## Changed Files

- `src/Internal/Aop/FrameworkProxyGenerator/FrameworkProxyGenerator.php`
- `src/Internal/Aop/FrameworkProxyGenerator/FrameworkProxyGenerationResult.php`
- `src/Internal/Aop/FrameworkProxyGenerator/FrameworkProxyInvocation.php`
- `src/Internal/Aop/FrameworkProxyGenerator/FrameworkProxyInvocationRegistry.php`
- `src/Internal/Aop/FrameworkProxyGenerator/FrameworkProxySourceEmitter.php`
- `src/Internal/Aop/FrameworkProxyArtifact/FrameworkProxyArtifactBuilder.php`
- `src/Internal/Aop/FrameworkProxyArtifact/FrameworkProxyArtifactDiagnosticCode.php`
- `src/Internal/Aop/FrameworkProxyArtifact/FrameworkProxyArtifactManifest.php`
- `src/Internal/Aop/FrameworkProxyGenerator/FrameworkProxyGenerationTarget.php`
- `src/Internal/Runtime/FrameworkProxyArtifactLoader.php`
- `tests/Internal/Aop/FrameworkProxyGenerator/FrameworkProxyGeneratorTest.php`
- `tests/Internal/Runtime/FrameworkProxyArtifactLoaderTest.php`
- `tests/Fixtures/Aop/FrameworkProxyGenerator/GeneratorFixtures.php`
- `develop/orchestration/tasks/P21-003-framework-proxy-generator-artifacts.md`
- `develop/orchestration/tasks/P21-004-framework-proxy-symfony-di-preservation.md`
- `develop/orchestration/tasks/P21-005-framework-proxy-runtime-ownership.md`
- `develop/orchestration/tasks/P21-006-framework-proxy-compatibility-migration.md`
- `develop/orchestration/tasks/P21-007-framework-proxy-ray-removal-closeout.md`
- `develop/orchestration/reports/P21-003-framework-proxy-generator-artifacts.md`
- `develop/STATE.md`
- `develop/TODO.md`

## Decisions and Assumptions

- P21-002 remains read-only: generator validation, profile identity, ownership, and diagnostics are consumed rather than duplicated.
- The generated ABI has exactly `transactional` and `afterCommit` invocation methods. Operation Transactional methods remain lifecycle pass-through; service methods and AfterCommit use the narrow hook with a captured proceed closure.
- Artifact directories contain a Build ID plus full input-hash identity. JSON manifests are data-only and are verified before any generated PHP file is required. `index.json` is atomically replaced and retains active plus previous complete units; older units and staging orphans are removed only after successful publication.
- Generator profile selection is guarded by the accepted P21-002 ownership seam; Framework-owned generation rejects Ray mode with the stable mode-conflict diagnostic. Caller input hashes and per-target context hashes are canonicalized as separate maps.
- Loader schema validation rejects malformed entry keys/types, duplicate source/proxy/path identities, invalid class/path names, symlinks, unlisted nested/dot files, and map/hash cardinality drift. All proxy classes are preflighted before requiring any generated file.
- Source paths are not persisted in manifests. `source_inputs` stores the target class hash plus each proxied method's semantic `declaringClass::method` key and declaring file hash; the loader binds each target entry hash to its corresponding source input. Source content must be hashable at build time.
- Builder verification runs isolated array-form PHP subprocesses with active Composer autoload, drains stdout/stderr without surfacing path/source/error output, and checks the actual reflected proxy class, exact parent, and staging file path. Staging creation and cleanup are contained by the failure guard.
- Runtime loading preflights every already-loaded proxy identity before requiring any file; repeated loads of the exact class/path/parent are idempotent, while the same FQCN from another path is rejected.

## Commands and Results

- Direct Docker generator/loader smoke for a multi-proxy batch: PASS (2 proxy entries, manifest-only loader validation).
- Direct Docker readonly proxy parse/initialization smoke: PASS.
- `docker compose run --rm app php vendor/bin/phpunit tests/Internal/Aop/FrameworkProxyGenerator tests/Internal/Runtime/FrameworkProxyArtifactLoaderTest.php`: PASS (59 tests / 120 assertions), covering the P21-002 reject matrix, supported type/default/attribute rows, external input and list/associative context identity, same-size/mtime source drift, inherited declaring-file drift with unchanged mtime/size, immutable reuse, active/previous retention and rollback, valid-PHP wrong-class `CLASS_MISMATCH` with last-known-good/index/staging preservation, invalid-new-build preservation with concurrent staging, tampered immutable preservation, exact loader codes for profile/version/schema/input/source-path/source-input/directory/file/map/inventory/symlink mutations, repeated load identity reuse and different-path rejection, empty/no-source-scan loading, exact actual/named-variadic ABI forwarding, Operation pass-through/AfterCommit, wrong expected manifest hash, Framework/Ray profile rejection, and pre-execution invalid-map tamper handling.
- `docker compose run --rm app php vendor/bin/phpunit`: PASS (2,275 tests / 9,275 assertions; existing deprecation/notices only).
- `docker compose run --rm app mago format --check src tests`: PASS.
- `docker compose run --rm app mago lint src/Internal/Aop/FrameworkProxyGenerator src/Internal/Aop/FrameworkProxyArtifact src/Internal/Runtime/FrameworkProxyArtifactLoader.php`: PASS with 54 warnings, 16 notes, and 7 help messages; no errors.
- `docker compose run --rm app mago analyze src/Internal/Aop/FrameworkProxyGenerator src/Internal/Aop/FrameworkProxyArtifact src/Internal/Runtime/FrameworkProxyArtifactLoader.php`: PASS with 0 errors, 13 warnings, and 15 help messages.
- `bash tests/Consumer/framework-package-export.sh`: PASS for the pre-commit Git/Composer package export contract.
- `git diff --check`: PASS.
- Management-ID guard over changed PHP paths: PASS.

## Acceptance Criteria

- [x] Focused support/reject matrix fixtures cover all P21-002 rejection rows plus supported defaults, attributes, unions/intersections/DNF, readonly, inherited, Operation, and ABI behavior.
- [x] Narrow Transactional/AfterCommit ABI with readonly-safe initializer and signature emission.
- [x] Multi-proxy staging, parse/class/hash checks, immutable Build ID/input-hash paths, JSON manifest, atomic publish, active/previous retention, and failed-build preservation are covered, including valid-PHP class mismatch and concurrent staging preservation.
- [x] Runtime verifies manifest/profile/Build ID/hash/path/class map before loading generated classes and performs no source scan.
- [x] Focused PHPUnit and full format/lint commands pass.
- [x] Changed-source Mago analyze has 0 errors; remaining output is advisory warnings/helpers.

## Remaining Issues

No blocking issue remains. Changed-source Mago lint/analyze are error-free with advisory warnings/helpers. The existing legacy Ray path and all DI/transaction runtime/Composer files remain untouched.

## Suggested Next Action

Commit the accepted P21-003 change, rerun the exact Git HEAD package export, then start P21-004 Symfony DI Definition preservation.
