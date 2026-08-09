# P21-003: Framework Proxy Generator and Artifact Contract

Status: Ready

## Goal

Implement the Framework-owned subclass generator and deterministic artifact lifecycle from Specification 101, using the metadata/guard seam accepted by P21-002.

## Source of Truth

- `AGENTS.md`
- `develop/STATE.md`
- `develop/spec/101-framework-owned-transaction-proxy.md`
- `develop/spec/102-phase-21-delivery-plan.md`
- `develop/decisions/137-framework-owned-transaction-proxy.md`
- Accepted P21-002 Report and source
- Current `src/Internal/Aop/**`, build artifact loader, and relevant tests

## Dependencies

P21-002 accepted; P21-002 Report/STATE checkpoint synchronized.

## In Scope

- Framework-owned PHP signature emitter for supported rows
- Content-hash source/signature metadata and Build ID manifest
- Unique staging directory, parse/class/hash verification, atomic publish
- Post-success stale cleanup and failed-build last-known-good preservation
- Runtime manifest/hash/profile loader with no Source Scan
- Build ID/path identity that prevents stale OPcache class selection
- Generator/artifact focused fixtures

## Out of Scope

- Symfony Definition replacement or alias/tag preservation
- Transaction/AfterCommit runtime binding
- Ray/framework migration selector
- Composer/Ray removal or public docs
- General-purpose AOP or arbitrary interceptors

## Files Allowed

- `src/Internal/Aop/FrameworkProxyGenerator/**`
- `src/Internal/Aop/FrameworkProxyArtifact/**`
- `src/Internal/Runtime/FrameworkProxyArtifactLoader.php`
- `tests/Internal/Aop/FrameworkProxyGenerator/**`
- `tests/Internal/Runtime/FrameworkProxyArtifactLoaderTest.php`
- `tests/Fixtures/Aop/FrameworkProxyGenerator/**`
- `develop/orchestration/reports/P21-003-framework-proxy-generator-artifacts.md`
- `develop/STATE.md`

P21-003 must not modify P21-002 contract classes, Symfony DI compiler files, Transaction Runtime, Composer files, or Ray adapters. Defensive generated-signature checks MUST consume P21-002's accepted metadata/diagnostic seam; they MUST NOT duplicate or re-own P21-002 validator rules.

## Constraints

- Content hashes, not mtime/size alone; stable manifest fields and safe diagnostics.
- Atomic same-filesystem publication; stale cleanup only after successful publish.
- Runtime verifies matching Build ID/profile/manifest/file hashes and never scans source or regenerates.
- Keep Ray artifact path and compatibility fixtures untouched.

## Acceptance Criteria

- [ ] Every support/reject Matrix row is generated or rejected deterministically.
- [ ] Manifest records Build ID, profile, generator/PHP version, source/signature/proxy hashes and class map.
- [ ] Staging, parse/class/hash verification, atomic publish, failed-build preservation, and post-success stale cleanup are tested.
- [ ] Source drift with unchanged mtime/size invalidates artifacts.
- [ ] Runtime loader rejects mismatched profile/Build ID/hash and performs no Source Scan.
- [ ] OPcache-safe immutable path identity is demonstrated.
- [ ] Focused PHPUnit/Mago/format/management-ID/diff checks pass; no Ray removal.

## Required Commands

```bash
docker compose run --rm app php vendor/bin/phpunit tests/Internal/Aop/FrameworkProxyGenerator tests/Internal/Runtime/FrameworkProxyArtifactLoaderTest.php
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint src/Internal/Aop/FrameworkProxyGenerator src/Internal/Aop/FrameworkProxyArtifact src/Internal/Runtime/FrameworkProxyArtifactLoader.php
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P21-003-framework-proxy-generator-artifacts.md`
