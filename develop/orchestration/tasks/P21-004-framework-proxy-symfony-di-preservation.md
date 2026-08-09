# P21-004: Framework Proxy Symfony DI Preservation

Status: Accepted

## Goal

Replace supported Symfony DI Definition classes with Framework proxies while preserving the Definition and alias contract, and reject unsupported factory/lazy/synthetic/abstract/decoration boundaries.

## Source of Truth

- `AGENTS.md`
- `develop/STATE.md`
- `develop/spec/101-framework-owned-transaction-proxy.md`
- `develop/spec/102-phase-21-delivery-plan.md`
- `develop/decisions/137-framework-owned-transaction-proxy.md`
- Accepted P21-002 and P21-003 Reports
- Symfony `Definition`/`ContainerBuilder` contract and current DI tests

## Dependencies

P21-002 and P21-003 accepted with synchronized Reports/STATE.

## In Scope

- Definition class replacement through the P21-003 generator seam
- Constructor arguments, autowiring/bindings, properties, visibility, shared scope
- Tags/autoconfigured/instanceof conditionals, method calls/configurator, file/deprecation
- Alias target and service identity preservation
- Explicit Build Errors for factory, lazy, abstract, decoration; synthetic N/A
- Generated Container load and alias/shared identity fixtures

## Out of Scope

- Generator signature/artifact internals
- Transaction/AfterCommit runtime semantics
- Profile migration or Ray removal
- Public docs, external publication, commit/push/deploy

## Files Allowed

- `src/Internal/Aop/FrameworkProxyDefinition/**`
- `src/Internal/DependencyInjection/FrameworkProxyDefinitionCompiler.php`
- `tests/Internal/Aop/FrameworkProxyDefinition/**`
- `tests/Internal/DependencyInjection/FrameworkProxyDefinitionCompilerTest.php`
- `tests/Fixtures/DependencyInjection/FrameworkProxy/**`
- `develop/orchestration/reports/P21-004-framework-proxy-symfony-di-preservation.md`
- `develop/STATE.md`

Do not modify P21-003 generator/artifact classes or existing Ray DI compiler paths; integration must use the accepted seam.

## Constraints

- Same service ID and alias graph; no visibility/shared normalization.
- Unsupported Definition features fail before unproxied selection.
- No dual Ray/Framework proxy and no Runtime Source Scan.

## Acceptance Criteria

- [x] Snapshot tests prove exact preservation of arguments, bindings, properties, visibility, shared, tags, conditionals, calls, configurator, file/deprecation, alias and identity.
- [x] Factory/lazy/abstract/decoration reject with stable safe codes; synthetic is N/A.
- [x] Generated Container resolves supported proxy and alias to the same shared instance.
- [x] Unsupported feature never falls back to original class.
- [x] Focused PHPUnit/Mago/format/management-ID/diff checks pass; Ray remains.

## Required Commands

```bash
docker compose run --rm app php vendor/bin/phpunit tests/Internal/Aop/FrameworkProxyDefinition tests/Internal/DependencyInjection/FrameworkProxyDefinitionCompilerTest.php
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint src/Internal/Aop/FrameworkProxyDefinition src/Internal/DependencyInjection/FrameworkProxyDefinitionCompiler.php
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P21-004-framework-proxy-symfony-di-preservation.md`
