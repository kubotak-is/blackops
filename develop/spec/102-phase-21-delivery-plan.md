# Specification 102: Phase 21 Delivery Plan

Status: Decided

Phase 21 replaces Ray.Aop only after the Framework-owned proxy contract in Specification 101 is implemented and verified. Production work is split into six non-overlapping slices. Ray.Aop remains in `composer.json`, `composer.lock`, Source, and compatibility fixtures until P21-007 is accepted.

## Dependency order

```text
P21-002 Contract / metadata / signature / ownership guard (Ready)
  -> P21-003 Generator / artifact / manifest / drift (Planned)
    -> P21-004 Symfony DI preservation (Planned)
      -> P21-005 Transaction / AfterCommit runtime and no-double-intercept (Planned)
        -> P21-006 Ray/framework compatibility and migration (Planned)
          -> P21-007 Ray removal / package export / closeout (Planned)
```

Only P21-002 is immediately Ready. Each downstream Task becomes Ready only after its direct dependency is accepted and its predecessor Report／STATE checkpoint is synchronized.

## Task responsibilities and acceptance

| Task | Responsibility | Depends on | Completion evidence |
| --- | --- | --- | --- |
| P21-002 | Normative metadata model, deterministic Attribute precedence, Signature Matrix validator, safe diagnostic codes, source-class/Operation ownership and profile guard | None | Focused validator/metadata tests, reject matrix, no production dependency removal, Report/STATE Review Pending |
| P21-003 | Framework-owned subclass generator, signature emitter, content-hash manifest, Build ID, staging, atomic publish, stale cleanup, drift loader, OPcache-safe artifact identity; defensive checks consume P21-002 metadata and do not re-own validation | P21-002 | Generator fixtures for every support/reject row, manifest/hash/staging failure tests, Runtime no-scan proof, Ray path preserved |
| P21-004 | Symfony Definition class replacement and preservation for supported features; alias/shared/tags/calls/configurator; explicit factory/lazy/synthetic/abstract/decoration boundaries | P21-003 | Definition snapshot and generated Container tests, unsupported-feature Build Errors, alias/shared identity proof |
| P21-005 | Transactional／AfterCommit Framework bindings, Operation Lifecycle pass-through, nested Required and callback semantics, one-owner/no-double-intercept proof | P21-004 | Inline/Deferred/self-handled/general Service runtime tests, rollback/commit/failure matrix, no Ray+Framework chain |
| P21-006 | Central Application-aware `build:compile --proxy-profile=ray|framework` selector (default `ray`), manifest-aware RuntimeContainerDumper integration, mutually exclusive profiles, application migration, golden compatibility fixtures, previous-build rollback, consumer package/export matrix, and accepted Ray removal manifest | P21-005 | Both-mode fixture parity, Application command/help/Dumper/docs wiring, migration/rollback evidence, no unproxied fallback, clean Consumer package checks, reviewed removal manifest |
| P21-007 | Removal gate and closeout: delete manifest-named Ray source adapters/fixtures/Composer entries/profile/loader/Dumper/docs wiring, verify namespace/artifact absence, isolated clean-install/package export and docs/internal references | P21-006 | Full focused/full suite, Composer validation, isolated Consumer clean-install/export, `rg` removal scan, clean artifact load, Report/STATE Accepted |

Each Task owns only its listed production surface. Cross-slice changes require a Report blocker and Orchestrator approval. No Task may introduce a general-purpose interceptor, Runtime Source Scan, or external Issue/PR.

The legacy Ray validator files remain read-only evidence through P21-006. P21-002 uses a new FrameworkProxyContract seam; P21-003's emitter performs only defensive consistency checks against that seam. The Application-aware build command/profile selector and manifest-aware RuntimeContainerDumper are intentionally deferred until P21-006 after P21-004 and P21-005 have supplied their integration seams; the standalone legacy `blackops:build:compile` command remains outside this surface.

## Rollback invariant

P21-002 through P21-006 keep the legacy Ray profile selectable. Rollback is a complete previous Container＋manifest＋artifact directory selection. A Task MUST NOT stack Ray and Framework proxies or remove Ray before P21-007.

## Traceability

- [D137 Framework-owned Transaction Proxy Contract](../decisions/137-framework-owned-transaction-proxy.md)
- [Specification 101](101-framework-owned-transaction-proxy.md)
- [D096 Phase 13 Database and Transaction Runtime](../decisions/096-phase-13-database-and-transaction-runtime.md)
- [D108 Ray.Aop Upstream and Phase Order](../decisions/108-ray-aop-upstream-and-phase-order.md)
