# P21-005 Framework Proxy Transaction Runtime and Ownership

Status: Accepted

## Summary

Connected the accepted Framework proxy invocation ABI to the existing `TransactionRuntimeAccessor` without duplicating transaction or connection resolution. Service bindings use the already-resolved method connection for Required transaction ownership; Operation bindings pass Transactional methods through to Inline/Deferred lifecycle owners. AfterCommit bindings queue a readonly receiver/method/argument/proceed value in the current runtime scope, including Operation callbacks, and rely on the existing commit ordering and failure reporter.

The runtime invocation factory and initializer are an explicit composition seam for the later compiled-container owner. They do not scan source, discover definitions, generate proxies, or modify P21-004.

## Changed Files

- `src/Internal/Aop/FrameworkProxyRuntime/FrameworkProxyAfterCommitInvocation.php`
- `src/Internal/Aop/FrameworkProxyRuntime/FrameworkProxyRuntimeInvocation.php`
- `src/Internal/Aop/FrameworkProxyRuntime/FrameworkProxyRuntimeInvocationFactory.php`
- `src/Internal/Aop/FrameworkProxyRuntime/FrameworkProxyRuntimeInitializer.php`
- `src/Internal/Execution/FrameworkProxyOperationOwnershipGuard.php`
- `src/Internal/Transaction/FrameworkProxyTransactionBinding.php`
- `src/Internal/Transaction/FrameworkProxyAfterCommitBinding.php`
- `tests/Fixtures/Aop/FrameworkProxyRuntime/FrameworkProxyRuntimeFixtures.php`
- `tests/Internal/Aop/FrameworkProxyRuntime/FrameworkProxyRuntimeInvocationTest.php`
- `tests/Internal/Transaction/FrameworkProxyTransactionBindingTest.php`
- `tests/Internal/Execution/FrameworkProxyOperationOwnershipGuardTest.php`
- `develop/orchestration/tasks/P21-005-framework-proxy-runtime-ownership.md`
- `develop/orchestration/tasks/P21-006-framework-proxy-compatibility-migration.md`
- `develop/orchestration/reports/P21-005-framework-proxy-runtime-ownership.md`
- `develop/STATE.md`
- `develop/TODO.md`

## Decisions and Assumptions

- Existing `TransactionRuntime` remains the sole transaction owner. Its Required nesting, rollback-only, manual collision/leak cleanup, callback ordering, callback failure isolation, and original Throwable behavior are consumed rather than reimplemented.
- Binding accepts the connection emitted by FrameworkProxyInvocation and requires it to exactly match accepted method metadata. No default/name resolver is introduced at runtime; unresolved or tampered connections fail with `BO_PROXY_OWNERSHIP_CONFLICT`.
- Operation metadata is checked against the resolved `handle` Transactional method connection, preserving the registry's lifecycle connection decision without scanning or re-resolving attributes.
- `FrameworkProxyOwnershipGuard::assertCompatible()` remains the canonical metadata/marker invariant check. Ray profile, lifecycle marker, source, and ownership mismatches fail without fallback.
- Operation Transactional methods pass through once to lifecycle. Operation AfterCommit callbacks still queue in the current TransactionRuntime scope, as required by the callback contract.
- P21-006 owns compiled-container wiring that calls the initializer for each resolved Definition binding. The initializer seam here accepts a known binding and generated proxy instance only.

## Commands and Results

- `docker compose run --rm app php vendor/bin/phpunit tests/Internal/Aop/FrameworkProxyRuntime tests/Internal/Transaction/FrameworkProxyTransactionBindingTest.php tests/Internal/Execution/FrameworkProxyOperationOwnershipGuardTest.php` — PASS (26 tests, 55 assertions).
- `docker compose run --rm app mago format --check src tests` — PASS.
- `docker compose run --rm app mago lint src/Internal/Aop/FrameworkProxyRuntime src/Internal/Transaction/FrameworkProxyTransactionBinding.php src/Internal/Transaction/FrameworkProxyAfterCommitBinding.php src/Internal/Execution/FrameworkProxyOperationOwnershipGuard.php` — PASS, no issues.
- `docker compose run --rm app mago analyze src/Internal/Aop/FrameworkProxyRuntime src/Internal/Transaction/FrameworkProxyTransactionBinding.php src/Internal/Transaction/FrameworkProxyAfterCommitBinding.php src/Internal/Execution/FrameworkProxyOperationOwnershipGuard.php` — PASS, no issues.
- `docker compose run --rm app php vendor/bin/phpunit` — PASS (2,314 tests, 9,379 assertions; existing deprecation/notices only).
- `bash tests/Consumer/framework-package-export.sh` — PASS for the pre-commit Git/Composer package export contract.
- Management-ID PHP guard — PASS.
- `git diff --check` — PASS.

## Acceptance Criteria

- Inline, Deferred, self-handled, and general Service ownership tests — PASS; each strategy proves exactly-once pass-through, and the lifecycle-owned transaction path proves nesting level one.
- Required transaction commit, Throwable rollback identity, nested rollback-only, manual collision/leak, named connection selection, and AfterCommit ordering/rollback discard/failure isolation — PASS.
- Shared-connection Operation business/terminal success and failure rollback are distinguished from Service method commit when a later terminal step fails — PASS.
- Ray/Framework marker/profile conflict, tampered source/metadata/connection diagnostics, and wrong proxy initializer identity — PASS; no runtime fallback.
- Generated proxy initializer and actual Framework invocation ABI integration — PASS.

## Remaining Issues

Compiled-container registration of the runtime initializer remains intentionally deferred to P21-006. No existing Transaction Runtime, P21-002–004 source, Composer, or Ray adapter was changed.

## Orchestrator Acceptance

Accepted at `2026-08-10T03:51:21+09:00`. Independent review corrected Operation AfterCommit queue ownership, canonical ownership-guard reuse, exact resolved connection matching, queued receiver/method/argument retention, rollback and failure matrices, binding source integrity, and generated proxy initializer identity. Independent focused, full PHPUnit, format, scoped lint/analyze, management-ID guard, diff check, and pre-commit package export all passed. P21-006 is Ready and owns central compiled-container/profile wiring.

## Suggested Next Action

Commit the accepted P21-005 change, rerun the exact package export against Git HEAD, then start P21-006 compatibility and migration wiring. No push or deploy was performed.
