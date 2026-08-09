# P21-002: Framework Proxy Contract and Ownership Guard

Status: Accepted

## Goal

Implement the normative metadata, Attribute precedence, Signature Matrix validator, safe diagnostic codes, and Operation／Service ownership/profile guard required by Specification 101. This Task does not generate a proxy or change the runtime.

## Source of Truth

- `AGENTS.md`
- `develop/STATE.md`
- `develop/decisions/137-framework-owned-transaction-proxy.md`
- `develop/spec/101-framework-owned-transaction-proxy.md`
- `develop/spec/102-phase-21-delivery-plan.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- Current `src/Internal/Aop/**` and AOP tests

## Dependencies

None. This is the only immediately Ready Phase 21 Production Task.

## In Scope

- Immutable proxy metadata and `ray`／`framework` profile identity
- Class/method Attribute precedence and Transactional＋AfterCommit conflict
- Normative support/reject/N/A Signature Matrix, including generator/reference rejection
- Stable safe diagnostic codes and secret-free fields
- Source-class Operation ownership and no-double-intercept marker contract
- Focused unit fixtures for every validator branch

## Out of Scope

- Proxy code generation, artifact files, manifest, OPcache, or staging
- Symfony Definition mutation or Container compiler integration
- Transaction runtime/interceptor changes
- Composer/Ray removal, migration, public docs, commit, push, deploy

## Files Allowed

- `src/Internal/Aop/FrameworkProxyContract/**`
- `tests/Internal/Aop/FrameworkProxyContract/**`
- `tests/Fixtures/Aop/FrameworkProxyContract/**`
- `develop/orchestration/reports/P21-002-framework-proxy-contract-guard.md`
- `develop/STATE.md`

Legacy `src/Internal/Aop/AopAttributeReader.php`, `AopAttributeTargetValidator.php`, and `AopMethodValidator.php` are read-only evidence for Ray and are explicitly outside this Task's write scope. Do not modify any existing Ray generator/interceptor/validator path; if an existing file is required outside the new FrameworkProxyContract seam, stop and report a blocker.

## Constraints

- No Runtime Source Scan or unproxied fallback.
- Diagnostics expose stable code, service ID/source class/method/Attribute/Build ID only; never secrets, payload, generated source, or full Throwable.
- Production comments must not contain management IDs.
- Preserve D096/D108 public Attribute semantics.

## Acceptance Criteria

- [x] Matrix rows compile to deterministic support/reject/N/A outcomes.
- [x] Generator, by-reference return, and by-reference parameter reject for both Attributes.
- [x] final/protected/private/static/constructor/destructor/property/parameter/repeated/conflict cases reject with stable codes.
- [x] readonly/non-final/variadic/union/intersection/DNF/default-value rows have metadata fixtures.
- [x] Operation is marked Lifecycle-owned and general Service is proxy-owned; profile conflict is rejected.
- [x] Focused PHPUnit, changed-source Mago, format, management-ID, and diff checks pass.
- [x] Report and STATE are Review Pending; Ray and dependencies remain unchanged.

## Required Commands

```bash
docker compose run --rm app php vendor/bin/phpunit tests/Internal/Aop/FrameworkProxyContract
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint src/Internal/Aop/FrameworkProxyContract tests/Internal/Aop/FrameworkProxyContract
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P21-002-framework-proxy-contract-guard.md`
