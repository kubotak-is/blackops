# P21-005: Framework Proxy Transaction Runtime and Ownership

Status: Planned

## Goal

Connect Framework proxy bindings to Transactional／AfterCommit runtime semantics while proving Operation Lifecycle ownership and preventing double interception.

## Source of Truth

- `AGENTS.md`
- `develop/STATE.md`
- `develop/spec/101-framework-owned-transaction-proxy.md`
- `develop/spec/102-phase-21-delivery-plan.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/decisions/096-phase-13-database-and-transaction-runtime.md`
- Accepted P21-002–P21-004 Reports
- Current Transaction Runtime, Inline/Deferred runtime, and AOP tests

## Dependencies

P21-004 accepted with synchronized Report/STATE.

## In Scope

- Framework Transactional and AfterCommit proxy runtime bindings
- Class/method precedence and named connection resolution
- Required nested scope, rollback-only, manual transaction fail-fast, callback queue
- Operation Inline/Deferred/self-handled pass-through ownership
- General Service one-owner interception and Ray/Framework mode conflict
- Runtime failure and safe diagnostic fixtures

## Out of Scope

- Generator/artifact/DI preservation implementation
- Compatibility migration/consumer package export
- Composer/Ray removal, public docs, commit/push/deploy

## Files Allowed

- `src/Internal/Aop/FrameworkProxyRuntime/**`
- `src/Internal/Transaction/FrameworkProxyTransactionBinding.php`
- `src/Internal/Transaction/FrameworkProxyAfterCommitBinding.php`
- `src/Internal/Execution/FrameworkProxyOperationOwnershipGuard.php`
- `tests/Internal/Aop/FrameworkProxyRuntime/**`
- `tests/Internal/Transaction/FrameworkProxyTransactionBindingTest.php`
- `tests/Internal/Execution/FrameworkProxyOperationOwnershipGuardTest.php`
- `tests/Fixtures/Aop/FrameworkProxyRuntime/**`
- `develop/orchestration/reports/P21-005-framework-proxy-runtime-ownership.md`
- `develop/STATE.md`

Do not modify P21-002–P21-004 implementation files, Composer files, or delete Ray adapters.

## Constraints

- Operation Transactional is Lifecycle-owned exactly once; proxy binding is pass-through.
- AfterCommit queues receiver/method/arguments in current scope and does not open a second transaction.
- Original Throwable and committed Outcome semantics remain unchanged.
- No dual interceptor chain, Runtime Source Scan, or arbitrary interceptor API.

## Acceptance Criteria

- [ ] Inline, Deferred, self-handled, and general Service tests prove one Transaction owner.
- [ ] Commit/rollback/rollback-only/manual-mixing/AfterCommit ordering and failure isolation pass.
- [ ] Operation Terminal/Outcome atomicity and Service guarantee difference are preserved.
- [ ] Ray+Framework mode conflict is a Build Error; no runtime fallback.
- [ ] Focused PHPUnit/Mago/format/management-ID/diff checks pass; Ray remains.

## Required Commands

```bash
docker compose run --rm app php vendor/bin/phpunit tests/Internal/Aop/FrameworkProxyRuntime tests/Internal/Transaction/FrameworkProxyTransactionBindingTest.php tests/Internal/Execution/FrameworkProxyOperationOwnershipGuardTest.php
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint src/Internal/Aop/FrameworkProxyRuntime src/Internal/Transaction/FrameworkProxyTransactionBinding.php src/Internal/Transaction/FrameworkProxyAfterCommitBinding.php src/Internal/Execution/FrameworkProxyOperationOwnershipGuard.php
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P21-005-framework-proxy-runtime-ownership.md`
