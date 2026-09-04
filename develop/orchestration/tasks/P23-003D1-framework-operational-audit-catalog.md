# P23-003D1: Framework Operational Audit Catalog

Status: Authority packet committed — fresh clean-freeze preflight pending; Product work not started

Updated At: 2026-09-04T14:10:16+09:00

## Current Checkpoint

P23-003C1 through P23-003C4 and parent P23-003C are Accepted.
The final C candidate is committed at
`d5ab3476181058c5fb87b7c2c9476b6db76fef2e`; its clean same-SHA
Git／Composer package export is Green. No push was performed.

D154 splits P23-003D into bounded children. D1 is the first ordinary child.
It adds one sealed Framework operational Catalog and the exact trusted lookup
lineage needed to resolve it. It does not add any event producer, draft
factory, source adapter, Application composition, or runtime activation.

Stable `1.2.0` remains current. BlackOps `1.3`, D1, and the parent P23-003D
remain unreleased.

## Goal

Implement D154's D1 boundary so that:

- the Accepted `blackops.framework.security_access` version 1 exact30 bytes,
  hash, provider identity, and lookup behavior remain byte-identical;
- a second exact Framework Catalog named `blackops.framework.operations`
  version 1 exposes the exact14 retention／rotation／replay／generic-Outbox
  definitions fixed by D154;
- `ImmutableAuditEventCatalogLookup` trusts only the singleton object returned
  by either exact Framework provider, never arbitrary owner=`framework` bytes;
- PHP and independent Node evidence pin all canonical bytes, lengths, hashes,
  order, tuples, delivery, evidence bounds, and mutation negatives;
- Architecture and package evidence close the exact Internal API, caller,
  dependency, nonactivation, PublicApi, and exact76 inventory boundaries; and
- no runtime, database, public source, route, CLI, configuration, migration,
  Documentation, release, or deployment behavior changes.

## Model and Workflow

- Orchestrator／research／review:
  repository-configured `gpt-5.6-sol`／`max`.
- Production Worker:
  repository-configured `gpt-5.6-luna`／`max`.
- Documentation Reviewer:
  repository-configured `gpt-5.6-sol`／`xhigh`, read-only when used.
- Product work starts only after this authority packet is independently
  reviewed, committed without unrelated paths, re-frozen clean, and fresh
  General／Catalog／Documentation preflight lanes are separately Green.
- The worker changes only exact Files Allowed, updates this Report／STATE, and
  does not stage, commit, push, publish, release, deploy, or run the package
  export from an uncommitted tree.
- Any Product／Test change invalidates prior Iteration／Final evidence.
- The Orchestrator alone reviews, accepts, commits, and runs the clean
  same-SHA package export.

Worker Process model metadata absence alone is not a blocker.
Explicit model／effort rejection, unavailability, or fallback is a blocker.

## Prerequisites

1. Branch is `agent/p23-001-blackops-1-3-feasibility`.
2. Accepted parent C commit
   `d5ab3476181058c5fb87b7c2c9476b6db76fef2e` is an ancestor of the clean
   Orchestrator-supplied D1 packet HEAD.
3. The packet HEAD is clean and staged0 before worker dispatch.
4. The twelve immutable authority files and their individual and ordered
   aggregate hashes match the Orchestrator dispatch exactly.
5. The Accepted security／access Catalog identity is exact30 with hash
   `cfbcb3335b27ac4506046216d7ff43a9802586b1e60fbb9cfc47dc12230b1d83`,
   raw bytes with LF `13960`, and unchanged provider／fixture bytes.
6. Stable `1.2.0` remains current, Framework migration count remains `13`,
   Community Board total remains `18`, and BlackOps `1.3` remains unreleased.
7. Accepted package required inventory is exact75 before D1.

Any drift stops Product work. Do not repair a prerequisite inside worker
scope.

## Source of Truth

Read in this order:

1. `AGENTS.md`
2. `develop/STATE.md`
3. this Task
4. `develop/spec/README.md`
5. `develop/spec/92-documentation-review-agent.md`
6. `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
7. `develop/spec/105-blackops-1-3-capability-contract.md`
8. `develop/spec/106-phase-23-delivery-plan.md`
9. `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`
10. `develop/decisions/147-audit-catalog-and-safe-projection-boundary.md`
11. `develop/decisions/150-audit-outbox-state-and-delivery-split.md`
12. `develop/decisions/153-audit-outbox-relay-and-recovery-runtime-boundary.md`
13. `develop/decisions/154-framework-operational-audit-catalog-and-adapter-split.md`
14. Accepted P23-003A／B／C Reports named by those authorities
15. current Catalog, lookup, golden, Architecture, package, version,
    migration, Stable generic Outbox, activation, and public-source files
    named here

D154 is the most-specific D1 authority. D147 remains authoritative for the
Accepted exact30 Catalog and canonical Catalog validation. If authority and
current code conflict, stop and record a Blocker; do not invent a Product-only
resolution.

## Release Documentation Impact

```text
stableVersion=1.2.0
candidateVersion=1.3.0-unreleased
capabilityId=audit.framework_operational_catalog.v1
publicSourceInventory=[]
publicRouteInventory=[]
publicCliInventory=[]
frameworkMigrationCount=13
communityMigrationCount=18
packageRequiredBefore=75
packageRequiredAfter=76
```

Public Guide／Website／Landing／release-note source is unchanged. The new
Catalog is an unreleased Internal package artifact and does not claim that any
producer or runtime integration exists. Generated artifacts and browser
behavior remain Not Verified unless independently generated or exercised.

## In Scope

### 1. Exact provider API

Add final Internal class
`BlackOps\Internal\Audit\Catalog\FrameworkOperationalAuditEventCatalog`
with exactly:

- private static `?AuditEventCatalog $instance = null`;
- private `__construct()`;
- public static `catalog(): AuditEventCatalog`; and
- private static `event(string $type, AuditActionPhase $phase,
  string $resource, AuditOutcomeStatus $status, string $outcome,
  AuditPolicyDecision $policy, string $reason,
  AuditDelivery $delivery): AuditEventDefinition`.

No method accepts caller input. `catalog()` constructs through the Accepted
`AuditEventCatalogCodec::catalog()` path, UTF-16 sorts the exact definitions,
caches one process-local singleton, and performs no configuration, Clock,
UUID, database, filesystem, environment, Container, network, Application, or
secret access.

Its Core dependency set is exactly the nine Accepted Audit types used by the
existing Framework provider, plus the same-namespace
`AuditEventCatalogCodec` construction dependency:

```text
AuditActionPhase
AuditDelivery
AuditEventCatalog
AuditEventCategory
AuditEventDefinition
AuditOutcomeStatus
AuditPolicyDecision
AuditSemanticTuple
AuditSourceOwner
```

### 2. Exact Catalog identity and bytes

```text
schemaVersion=1
kind=audit_event_catalog
catalogId=blackops.framework.operations
catalogVersion=1
owner=framework
eventCount=14
catalogHash=9b04a5af68feb9091903282a5d99309bcd7a52cd0cbe2eb9e2901bfacbea083b
unsignedJcsBytes=6448
hashPreimageBytes=6485
completeJcsBytes=6529
rawBytesWithLf=6530
```

Every definition has `version=1`, `evidenceVersion=1`,
`actionTypes=[event.type]`, one exact semantic tuple, `evidenceFields=[]`,
`maxEvidenceBytes=13`, and `delivery=durable`. Exact order and tuples are:

| event.type | category | phase | resourceType | outcomeStatus / outcomeCode | policyDecision / reasonCode |
| --- | --- | --- | --- | --- | --- |
| `outbox.delivery.dead_letter_retry_requested` | outbox | requested | outbox_record | unknown / outbox_retry_requested | not_applicable / outbox_retry_requested |
| `outbox.delivery.dead_lettered` | outbox | failed | outbox_record | failed / outbox_dead_lettered | not_applicable / outbox_dead_lettered |
| `outbox.delivery.retry_scheduled` | outbox | committed | outbox_record | unknown / outbox_retry_scheduled | not_applicable / outbox_retry_scheduled |
| `replay.observer.failed` | replay | failed | lifecycle_journal | failed / replay_failed | not_applicable / replay_failed |
| `replay.observer.progressed` | replay | committed | lifecycle_journal | unknown / replay_progressed | not_applicable / replay_progressed |
| `replay.observer.started` | replay | started | lifecycle_journal | unknown / replay_started | not_applicable / replay_started |
| `replay.observer.succeeded` | replay | succeeded | lifecycle_journal | succeeded / replay_succeeded | not_applicable / replay_succeeded |
| `retention.hold.placed` | retention | committed | operation | succeeded / retention_hold_placed | not_applicable / retention_hold_placed |
| `retention.hold.released` | retention | committed | operation | succeeded / retention_hold_released | not_applicable / retention_hold_released |
| `retention.purge.committed` | retention | committed | operation_data | succeeded / retention_purged | not_applicable / retention_purged |
| `rotation.storage.failed` | rotation | failed | protected_storage | failed / rotation_failed | not_applicable / rotation_failed |
| `rotation.storage.progressed` | rotation | committed | protected_storage | unknown / rotation_progressed | not_applicable / rotation_progressed |
| `rotation.storage.started` | rotation | started | protected_storage | unknown / rotation_started | not_applicable / rotation_started |
| `rotation.storage.succeeded` | rotation | succeeded | protected_storage | succeeded / rotation_succeeded | not_applicable / rotation_succeeded |

The only valid evidence value is exact `{"version":1}`.

### 3. Trusted lookup lineage

For owner `framework`, `ImmutableAuditEventCatalogLookup` accepts only exact
object identity with either:

```text
FrameworkAuditEventCatalog::catalog()
FrameworkOperationalAuditEventCatalog::catalog()
```

Both may coexist and resolve independently by full identity and hash. Decoded
clones, reflected or serialized reconstructions, changed hashes／events／IDs,
and a third Framework Catalog fail `semantic_invalid` before hash validation.
Application trust, reserved prefix, duplicate identity, same-hash/different-
bytes, collection shape／cap, and hash-validation precedence remain Accepted.

### 4. Golden, Architecture, and package evidence

- PHP compares provider arrays, canonical unsigned／complete／raw bytes,
  lengths, hash preimage, hash, LF, order, singleton identity, and all fields
  against exact literals and the checked-in JSON fixture.
- The Node golden independently reconstructs the exact14 from literals and
  implements JCS／UTF-16 ordering plus the exact
  `ENC_STRING("blackops.audit.catalog.v1") || ENC_BYTES(unsigned JCS)` decimal-
  length hash preimage without reading PHP. It
  compares the fixture byte-for-byte and rejects isolated mutations to every
  identity, definition, tuple, action, evidence, delivery, sort, hash, length,
  duplicate, omission, addition, LF, noncanonical-byte, and U32BE-LP-
  substitution family.
- Architecture pins the exact class modifier, property, callable signatures,
  visibility, staticness, return types, dependencies, provider reference
  path／receiver／method／count, and zero PublicApi／activation behavior. Valid
  synthetic mutation fixtures must reject alias, group-use, FQCN,
  same-namespace, class-string, Reflection, callable-array, dynamic,
  second-declaration, extra dependency／caller／callable／property, missing
  reference, and whole-path bypass forms without self-derived baselines.
- Required-inventory and export lists increase from exact75 to exact76 solely
  by adding
  `src/Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalog.php`
  immediately after `src/Audit/AuditProtectionKeyProvider.php` and before
  `src/Internal/Audit/Orchestration/AuditDeliveryFailure.php`.
  The existing exact-one validation function, exact-one ordered loop,
  exact-two top-level Git／Composer calls, bounded cleanup, and locally
  repinned semantic mutation contract remain closed. The inventory oracle
  rejects the new D1 entry omission and reports exact30 total mutations
  (`16` inventory plus `14` call-contract).

## Out of Scope

- any D2 read, D3 authentication, D4 authorization, D5 phase-source, D-control,
  or D-integration Product work;
- draft factories, trusted actor／execution／tenant／source construction,
  opaque resource projection, Clock, UUID, idempotency, or delivery selection;
- retention, hold, rotation, replay, generic Outbox, HTTP, authorization,
  Operation reader, Diagnostics, or Audit Outbox runtime edits;
- changing the Accepted exact30 Catalog, Catalog codec, Core Audit types,
  C1／C2／C3／C4 Production, Application composition, Container, configuration,
  migration, database, public API, route, command, worker, scheduler, or
  maintenance registration;
- checkpoint／anchor definitions before Accepted P23-003F;
- public Documentation, example, release note, generated artifact, browser,
  release, deploy, stage, commit, push, or package export from the worker tree.

## Files Allowed

Production／Test is exact10 in this order:

```text
src/Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalog.php
src/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php
tests/Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalogTest.php
tests/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookupTest.php
tests/Architecture/AuditRecordArchitectureTest.php
tests/Architecture/AuditPublicApiTest.php
tests/Fixtures/Audit/framework-operational-audit-event-catalog-v1.json
tests/Fixtures/Audit/audit-operational-event-catalog-golden.mjs
tests/Consumer/framework-package-required-inventory.sh
tests/Consumer/framework-package-export.sh
```

All exact10 paths must move. Production exact2 is the new provider and the
existing lookup; every other Product／Test path remains byte-identical.

Worker-managed exact2:

```text
develop/orchestration/reports/P23-003D1-framework-operational-audit-catalog.md
develop/STATE.md
```

Worker scope is exact12. No other path may move.

Orchestrator-only immutable exact12:

```text
develop/TODO.md
develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md
develop/decisions/147-audit-catalog-and-safe-projection-boundary.md
develop/decisions/150-audit-outbox-state-and-delivery-split.md
develop/decisions/153-audit-outbox-relay-and-recovery-runtime-boundary.md
develop/decisions/154-framework-operational-audit-catalog-and-adapter-split.md
develop/orchestration/tasks/P23-003C4-audit-outbox-relay-and-recovery-runtime.md
develop/orchestration/tasks/P23-003D1-framework-operational-audit-catalog.md
develop/orchestration/reports/P23-003C4-audit-outbox-relay-and-recovery-runtime.md
develop/spec/105-blackops-1-3-capability-contract.md
develop/spec/106-phase-23-delivery-plan.md
develop/spec/README.md
```

The worker must not change an immutable path.

## Failure Precedence

The new provider has no caller-input failure surface. Lookup validation keeps
this Accepted collection order:

1. outer list and member shape;
2. all Catalog semantic provenance, reserved-prefix, and duplicate-identity
   checks across the complete collection;
3. canonical unsigned／complete／raw byte caps across the complete collection;
4. per-Catalog hash validation;
5. same-hash／different-complete-bytes validation;
6. immutable identity／hash indexing.

Adding the second provider must not cause an earlier wrong hash to mask a
later semantic or cap failure.

## Required Fixtures

### Provider and exact bytes

- exact class/property/callable/dependency surface and one singleton;
- exact Catalog identity, exact14 count/order, every event field and tuple,
  exact actionTypes, empty evidence fields, maxEvidenceBytes13, durable-only;
- exact unsigned/hash-preimage/complete/raw lengths and hash;
- exactly one terminal LF, no BOM/CR/trailing space, canonical JCS bytes;
- old exact30 provider, fixture, lengths, hash, known answers, and singleton
  remain byte-identical.

### Lookup

- old-only, new-only, and both-provider lookup by full identity and by hash;
- unknown identity/hash returns null without mutation;
- same singleton duplicate is idempotent;
- decoded clone of either Framework Catalog, third Framework provider,
  swapped old/new identity or hash, valid rehash after a one-field mutation,
  same identity/different bytes, and same hash/different bytes fail exactly;
- Application owner positive and every reserved Framework prefix negative
  remain Accepted;
- compound semantic/cap/hash cases prove the full precedence.

### Golden and mutation resistance

- independent Node exact-byte positive and exact mutation matrix described in
  D154 and this Task;
- PHP mutation matrix proves omission/addition/duplicate/order and every
  identity/event/tuple/action/evidence/delivery field independently;
- Architecture negatives are non-vacuous, execute the actual scanners, prove
  the expected rule before any whole-file hash pin, and include false-positive
  controls for comments, unconsumed strings, unrelated classes, and valid
  Core enum `::class` use.

### Release and nonactivation

- exact one Production reference to the new provider, only from
  `ImmutableAuditEventCatalogLookup`; zero lookup construction that supplies
  it and zero Application／Container／CLI／HTTP／worker／scheduler registration;
- PublicApi exact zero, Deptrac violations／skipped／uncovered all zero;
- package exact76 order and exact30 mutation result;
- Stable version `1.2.0`, Framework migration13, Community18,
  `StoragePurpose::cases()` exact9, Stable generic Outbox positives and bytes;
- no public source／route／CLI／Guide／Website delta and no forbidden management
  IDs in PHP comments.

## Iteration Gate

Run once after all exact10 Product／Test changes and Report／STATE updates are
complete. Stop on first failure.

1. Freeze branch, HEAD, staged0, status exact12, exact10 ordered SHA aggregate,
   immutable exact12 individual hashes／aggregate, Accepted C prerequisite and
   exact30 Catalog hashes, and package-script hashes.
2. Prove exact10／exact12 counts, unique paths, existence, exact state, all
   exact10 paths moved, and add／remove／duplicate／wrong-hash negatives.
3. Run:

   ```bash
   docker compose run --rm app mago format --check src/Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalog.php src/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookup.php tests/Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalogTest.php tests/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookupTest.php tests/Architecture/AuditRecordArchitectureTest.php tests/Architecture/AuditPublicApiTest.php
   docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalogTest.php tests/Internal/Audit/Catalog/ImmutableAuditEventCatalogLookupTest.php tests/Internal/Audit/Catalog/FrameworkAuditEventCatalogTest.php tests/Internal/Audit/Catalog/AuditEventCatalogCodecTest.php tests/Architecture/AuditRecordArchitectureTest.php tests/Architecture/AuditPublicApiTest.php
   node tests/Fixtures/Audit/audit-operational-event-catalog-golden.mjs
   node tests/Fixtures/Audit/audit-event-catalog-golden.mjs
   docker compose run --rm app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
   bash tests/Consumer/version-baseline.sh
   bash tests/Consumer/framework-package-required-inventory.sh
   bash -n tests/Consumer/framework-package-required-inventory.sh
   bash -n tests/Consumer/framework-package-export.sh
   ```

4. Prove Stable generic Outbox positives／bytes, migration13／Community18,
   `StoragePurpose` exact9, PublicApi/nonactivation/public-source/route/CLI/docs
   zero deltas, Guide／Website no-diff/status, forbidden-ID scan,
   `git diff --check`, staged0, and ending exact freezes.

Record exact command lines, exits, test／assertion counts, Deptrac counters,
mutation counts, individual and aggregate hashes, and Not Verified boundaries
in the Report.

Do not run `framework-package-export.sh` from the uncommitted worker tree.
Its Git side archives HEAD and cannot prove the uncommitted new provider.

## Complete Final Gate

After Iteration Green, fresh independent General／Catalog／Documentation
implementation reviews, and an unchanged exact12 refreeze, restart at command
1 and stop on first failure:

1. full `docker compose run --rm app mago format --check src tests`;
2. `docker compose run --rm app composer validate --strict`;
3. full Mago lint and baseline verification;
4. full Mago analyzer at error level and count format;
5. full PHPUnit;
6. Deptrac `--report-uncovered --fail-on-uncovered`;
7. all five Audit Node goldens, including the new operational Catalog;
8. version baseline and package exact76 required-inventory oracle;
9. Community Board clean-install only if the unchanged current journey is
   available without changing scope;
10. shell syntax, Stable generic Outbox positives, docs directory／no-diff,
    nonactivation, forbidden-ID, `git diff --check`, staged0, exact12,
    exact10, immutable exact12, prerequisite, and package post-guards.

No evidence may be reused after a Product／Test change.

## Acceptance Criteria

- D154 exact14 provider and trusted two-provider Framework lineage are complete.
- Accepted security／access exact30 bytes, hash, provider, records, and lookup
  behavior remain byte-identical.
- Exact PHP／Node canonical bytes and mutation-resistant lookup／Architecture
  evidence are Green.
- Exact76 package, PublicApi, Deptrac, Stable／release, migration, generic
  Outbox preservation, docs, and nonactivation guards are Green.
- No D2／D3／D4／D5／D-control／D-integration Product work or activation occurred.
- Iteration and Complete Final Gate are Green on one unchanged freeze.
- Fresh General／Catalog／Documentation reviews are separately Green.
- Report and STATE truthfully separate current, Historical, Not Run, and Not
  Verified evidence.

## Post-Acceptance

After Orchestrator Acceptance only:

1. accept P23-003D1 but keep parent P23-003D incomplete;
2. commit the reviewed exact candidate without unrelated paths;
3. confirm clean tree／staged0 and exact commit SHA;
4. restart and run `tests/Consumer/framework-package-export.sh` from that exact
   committed SHA;
5. record Git／Composer same-inventory and autoload proof;
6. do not push unless the user separately authorizes it.

Next ordinary work is a separately authorized D2／D3／D4／D5 child. D-control
remains blocked on Accepted P23-003F.

## Blocker Rule

Stop and update Report／STATE without broadening scope when:

- authority conflicts with Accepted Catalog bytes, codec, provenance,
  validation precedence, API, package, or release boundary;
- any exact file, fixture, hash, count, mutation, or nonactivation guard is
  missing or cannot run;
- a fix needs an unlisted Product／Test／management file;
- a source adapter, draft factory, composition, registration, public surface,
  or runtime activation is required;
- branch／HEAD／index／status／hash freeze drifts; or
- the configured worker model／effort is explicitly rejected, unavailable, or
  falls back.
