# P20-016B Storage Protection Core

## Summary

Implemented the framework-owned BOPD v1 XChaCha20-Poly1305 protection core and the application-owned storage key contract. New writes resolve `activeKey`; reads resolve the envelope key ID through `key()` and reject mismatched provider results. Canonical associated data binds version, algorithm, key ID, purpose, record identity, operation identity, schema version, and explicit tenant presence/type/ID.

Application Composition reuses the existing `ServiceProvider` binding and registers the codec in the three compiled-container command paths. Class-based providers remain metadata-only in build artifacts; resolved key material is not dumped. Existing adapters are intentionally not wired in this task.

## Changed Files

- `src/StorageProtection/StoragePurpose.php`
- `src/StorageProtection/StorageKey.php`
- `src/StorageProtection/StorageKeyProvider.php`
- `src/StorageProtection/StorageProtectionException.php`
- `src/Internal/StorageProtection/*`
- `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`
- Three internal compile command paths
- Storage protection, composition, and compile-command tests
- `composer.json` / `composer.lock` (`ext-sodium` platform requirement)
- `deptrac.yaml` and `develop/spec/16-namespace-dependencies.md`

## Decisions and Assumptions

- `ext-sodium` is a required PHP platform extension; no third-party crypto dependency or algorithm plugin was added.
- `StorageKey` stores material inside `SensitiveParameterValue`; debug output exposes only the key ID and serialization of the material-bearing object is rejected by PHP.
- `StorageProtectionContext` requires a positive integer schema version and non-empty record/operation identity fields.
- Canonical AAD distinguishes null tenant from present tenant using explicit presence and null length-prefix sentinels. `TenantRef` itself rejects empty type/ID, so an empty tenant identity cannot be constructed through the public contract.
- Existing `ServiceRegistry` class binding is the composition boundary. Provider instances containing resolved secrets are not a supported compiled-artifact input; class-based providers are resolved by the application runtime.
- The compiled-artifact regression uses constructor/key-call counters and confirms both remain zero through compile/dump; provider class metadata is emitted without resolved material.

## Commands and Results

- Focused PHPUnit: **PASS**, 30 tests / 175 assertions.
- Full PHPUnit: **PASS**, 2033 tests / 8090 assertions; 1 existing PHP 8.5 deprecation.
- `composer validate --strict`: **PASS**.
- `mago format --check src tests`: **PASS**.
- Changed-source `mago lint src/StorageProtection src/Internal/StorageProtection`: **PASS** after typed envelope constants, parser helpers, and readability corrections.
- Changed-source `mago analyze src/StorageProtection src/Internal/StorageProtection`: **PASS** after mixed-value and unpack-shape corrections.
- Management-ID guard and `git diff --check`: **PASS**.
- `bash tests/Consumer/framework-package-export.sh`: **BLOCKED by local workspace inventory** (the untracked empty `.claude` directory appears as an unexpected Composer archive root).
- Broad `mago lint`: **Repository baseline FAIL**, 88 findings. P20-016B Production-only lint is clean.
- Broad `mago analyze`: **PASS** with 1 existing warning in unchanged `JsonlJournalRecordEncoder`.
- `vendor/bin/deptrac`: **BLOCKED before graph generation** by the existing vendor PHP 8.5 parser error (`NikicFileReferenceVisitor.php:106`, unexpected token `(`).
- Independent libsodium known-answer recalculation: **PASS**, exact BOPD v1 envelope hex matched the test vector without invoking the framework codec.
- Final Full PHPUnit rerun after type-boundary hardening: **PASS**, 2033 tests / 8090 assertions; 1 existing deprecation.

## Acceptance Criteria

- Public purpose/key/provider contracts and strict key validation: covered by focused tests.
- BOPD v1 known-answer, empty/binary/large round trips, nonce uniqueness, strict header/length/trailing parsing, and tamper matrix: covered by focused tests.
- Wrong tenant/purpose/record/operation/schema/key and provider mismatch/failure: covered by focused tests.
- Secret/ciphertext/nonce/tag/raw tenant/provider detail exposure: safe exception, trace, debug, serialization, and compiled-artifact tests cover the boundary.
- Classic/Worker/CLI compiled-container paths resolve the same codec binding: compile-command regression tests cover all three command paths.
- Existing suite remains green: full PHPUnit passed.

## Remaining Issues

- Broad Mago lint retains 88 repository findings outside the P20-016B Production code. Deptrac remains blocked by its existing PHP 8.5 vendor parser.
- Framework package export consumer is blocked by the local empty `.claude` workspace directory being included by `composer archive`; it is not tracked by `HEAD` and is not part of the P20-016B diff.
- Protected adapter wiring, migrations, rotation CLI, and provider-required bootstrap enforcement remain in later P20-016 tasks by design.

## Suggested Next Action

Proceed to P20-016C PostgreSQL Tenant Metadata and Decode-before-Isolation after committing the accepted P20-016B change set.
