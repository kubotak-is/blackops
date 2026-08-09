# P21-002 Framework Proxy Contract and Ownership Guard

Status: Accepted

## Summary

Implemented the isolated Framework proxy contract seam. It provides immutable profile and ownership identity, source-class metadata, deterministic class/method Attribute precedence, safe diagnostics, initial Signature Matrix classification, connection validation, and Operation/service ownership guards. Ray validators, generators, interceptors, Composer dependencies, and runtime wiring were not changed.

## Changed Files

- `src/Internal/Aop/FrameworkProxyContract/FrameworkProxyAttributeResolver.php`
- `src/Internal/Aop/FrameworkProxyContract/FrameworkProxyConnectionGuard.php`
- `src/Internal/Aop/FrameworkProxyContract/FrameworkProxyContract.php`
- `src/Internal/Aop/FrameworkProxyContract/FrameworkProxyContractException.php`
- `src/Internal/Aop/FrameworkProxyContract/FrameworkProxyDiagnostic.php`
- `src/Internal/Aop/FrameworkProxyContract/FrameworkProxyDiagnosticCode.php`
- `src/Internal/Aop/FrameworkProxyContract/FrameworkProxyMetadata.php`
- `src/Internal/Aop/FrameworkProxyContract/FrameworkProxyMethodMetadata.php`
- `src/Internal/Aop/FrameworkProxyContract/FrameworkProxyOwnership.php`
- `src/Internal/Aop/FrameworkProxyContract/FrameworkProxyOwnershipGuard.php`
- `src/Internal/Aop/FrameworkProxyContract/FrameworkProxyOwnershipMarker.php`
- `src/Internal/Aop/FrameworkProxyContract/FrameworkProxyProfile.php`
- `src/Internal/Aop/FrameworkProxyContract/FrameworkProxySignatureClassification.php`
- `src/Internal/Aop/FrameworkProxyContract/FrameworkProxySignatureValidator.php`
- `tests/Fixtures/Aop/FrameworkProxyContract/ContractFixtures.php`
- `tests/Internal/Aop/FrameworkProxyContract/FrameworkProxyContractTest.php`
- `tests/Internal/Aop/FrameworkProxyContract/FrameworkProxyOwnershipGuardTest.php`

## Decisions and Assumptions

- Framework metadata is immutable and records `ray` or `framework`, source class, service/Operation ownership, lifecycle ownership, readonly class identity, effective method Attribute connection, signatures, default-presence/constant identity, and unrelated source Attribute names.
- Method-level `Transactional` overrides class-level metadata even when its connection is omitted; omitted connection remains null until a later build context resolves the default.
- Both Attributes reject generator, by-reference return, and by-reference parameter signatures. Visibility, static, final, constructor/destructor, conflict, non-void AfterCommit, non-concrete target, property/parameter target, object default, and repeated-Attribute branches use stable `BO_PROXY_*` codes.
- Scalar, array, global/core constant, enum-case constant, variadic, union/intersection/DNF, nullable, `never`, `mixed`, `static`, `self`, `parent`, readonly, inherited, and unrelated-Attribute fixtures are executable. Repeated `Transactional` and `AfterCommit` fixtures prove `BO_PROXY_ATTRIBUTE_DUPLICATE` with service/build context.
- `FrameworkProxyProfile` under the new contract seam is the profile identity for downstream generator/compatibility tasks to consume; no duplicate profile value was added elsewhere.

## Commands and Results

- `docker compose run --rm app php vendor/bin/phpunit tests/Internal/Aop/FrameworkProxyContract` — PASS, 40 tests / 130 assertions.
- `docker compose run --rm app mago format --check src tests` — PASS.
- `docker compose run --rm app mago lint src/Internal/Aop/FrameworkProxyContract tests/Internal/Aop/FrameworkProxyContract` — PASS, no issues.
- `docker compose run --rm app mago analyze src/Internal/Aop/FrameworkProxyContract` — PASS, no issues.
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'` — PASS.
- `git diff --check` — PASS.

Docker commands required escalation solely for access to the repository Docker socket; no test or quality command was skipped.

## Acceptance Criteria

- [x] Deterministic support/reject/N/A metadata and Attribute precedence.
- [x] Generator/reference return/reference parameter rejection for both Attributes.
- [x] final/protected/private/static/constructor/destructor/property/parameter/conflict/repeated cases have stable diagnostics.
- [x] readonly, inherited, variadic, union/intersection/DNF, nullable, `never`, `mixed`, `static`/`self`/`parent`, default scalar/array/constant/enum identity, inaccessible private/inherited default rejection, explicit constant-owner collision coverage, unrelated Attribute metadata, and repeated Attribute fixtures.
- [x] Operation lifecycle ownership, general Service ownership, source/marker mismatch, lifecycle marker, and profile conflict guards.
- [x] Focused PHPUnit, changed-source Mago lint/analyze, format, management-ID, and diff checks pass.
- [x] Ray paths and dependencies remain unchanged; no proxy generation, artifact, DI, runtime, Composer, or Ray removal work was performed.

## Remaining Issues

None within the Task scope. Downstream generator/DI/runtime tasks must consume this seam and must not duplicate its validation or profile identity.

## Orchestrator Acceptance

Accepted at `2026-08-09T23:38:06+09:00`. Independent review corrected method-level bare Transactional precedence, exact default presence and constant ownership, real repeated-Attribute fixtures, safe error context, complete ownership-marker branches, and supported Signature Matrix evidence. Legacy Ray validators／generator／interceptors remained untouched.

Independent final evidence:

- Focused PHPUnit: `40 tests / 130 assertions`, PASS.
- Changed-source Mago lint and analyze: PASS; full format check: PASS.
- Full PHPUnit first run: `2,215 tests / 9,151 assertions`, one unrelated existing heartbeat timing failure at `OutboxRelayRuntimeTest.php:110`.
- Exact unchanged heartbeat test: `1 test / 4 assertions`, PASS.
- Clean full PHPUnit rerun: `2,215 tests / 9,152 assertions`, PASS with the existing deprecation／notice inventory; no assertion edit.
- Pre-commit Framework Composer/worktree package export, management-ID guard, and `git diff --check`: PASS.
- `git status --short`: only P21-002 allowed contract seam／fixtures／tests／Report plus Orchestrator Task／TODO／STATE synchronization; no legacy Ray or Composer changes.

## Suggested Next Action

Commit P21-002, rerun the exact package export against the committed Git archive, then start P21-003 using `FrameworkProxyMetadata`, `FrameworkProxyProfile`, diagnostics, and ownership markers without duplicating validation.
