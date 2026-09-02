# P23-003C3: Audit Delivery Orchestration and Key Composition

Status: Bounded preflight correction applied — fresh General／Transaction／Documentation re-preflight required; Product work not started

Updated At: 2026-09-02T17:10:59+09:00

## Current Checkpoint

P23-003C1 and P23-003C2 are Accepted.
The reviewed C2 candidate is committed at
`a92bef70fdb843a4dd2b8b34243fc892b43939cc`, and its clean exact-SHA
Git／Composer package export is Green.
No push was performed.

D152 and this Task define the next serial child, P23-003C3.
C3 adds the unregistered delivery-policy, same-root transaction,
protected-read gate, and Application key-composition seams consumed later by
P23-003D／E and C4.
It changes no Accepted C1／C2／B1／B2 Production byte and activates no current
Application runtime.

This management packet must be committed by the Orchestrator and independently
reviewed from one clean immutable freeze before a configured Luna worker may
edit Product or Test.
The first preflight returned one implementation-blocking Store-failure
taxonomy conflict plus bounded transaction／package／management findings.
D152 and this Task now close those findings; all three lanes must restart from
the corrected clean freeze.
Stable `1.2.0` remains current; BlackOps `1.3` and every Audit capability remain
unreleased.

## Goal

Implement the D152 exact C3 boundary so that:

- delivery is selected from the trusted Catalog as exactly `required` or
  `durable` before a mutation or protected response path begins;
- a required canonical append or durable Outbox append participates in the
  same exact Framework Connection root as a successful business mutation;
- protected Operation reads commit the requested record before authorization
  and the terminal record before any allow result can be released;
- Applications can supply one dedicated opaque-ID key and exact Audit record／
  Outbox protection keys without adapting generic `StorageKeyProvider` or
  changing `StoragePurpose` exact9;
- the complete graph is directly testable but has zero current runtime
  registration or Production callsite.

## Model and Workflow

- Orchestrator／research／review:
  repository-configured `gpt-5.6-sol`／`max`.
- Production Worker: repository-configured `gpt-5.6-luna`／`max`.
- Documentation Reviewer:
  repository-configured `gpt-5.6-sol`／`xhigh`, read-only when used.
- Product work starts only after fresh General／Transaction／Documentation
  preflight is separately Green.
- The worker changes only exact Files Allowed, updates this Report／STATE, and
  does not stage, commit, push, publish, release, or deploy.
- A Product／Test change invalidates all earlier Iteration／Final evidence.
- The Orchestrator alone reviews, accepts, commits, and runs the clean
  same-SHA package export.

Worker Process model metadata absence alone is not a blocker. Explicit model
or effort rejection, unavailability, or fallback is a blocker.

## Prerequisites

1. Branch is `agent/p23-001-blackops-1-3-feasibility`.
2. Accepted C2 commit
   `a92bef70fdb843a4dd2b8b34243fc892b43939cc` is an ancestor of the clean
   Orchestrator-supplied C3 packet HEAD.
3. The packet HEAD is clean and staged0 before worker dispatch.
4. P23-003C1／C2, parent P23-003B, and their package evidence remain Accepted.
5. The ten immutable authority files and their ordered aggregate match the
   Orchestrator dispatch exactly.
6. Stable `1.2.0` remains current, Framework migration count remains `13`,
   Community Board total remains `18`, and BlackOps `1.3` remains unreleased.

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
11. `develop/decisions/148-audit-canonical-record-and-protected-store-boundary.md`
12. `develop/decisions/149-audit-postgresql-canonical-store-boundary.md`
13. `develop/decisions/150-audit-outbox-state-and-delivery-split.md`
14. `develop/decisions/151-audit-postgresql-outbox-persistence-boundary.md`
15. `develop/decisions/152-audit-delivery-orchestration-and-key-composition-boundary.md`
16. Accepted C1／C2 Reports
17. current Catalog, record, protection, Store, transaction, Application
    composition, Architecture, package, DBAL, and Container source named here

D152 is the most-specific C3 authority. D150 remains the parent split.
If authority and current code conflict, stop and record a Blocker; do not
resolve it only in Product code.

## Release Documentation Impact

```text
stableVersion=1.2.0
candidateVersion=1.3.0-unreleased
capabilityId=audit.delivery_orchestration.v1
publicSourceInventory=[
  src/Audit/AuditOpaqueIdKeyProvider.php,
  src/Audit/AuditProtectionKeyProvider.php
]
publicRouteInventory=[]
frameworkMigrationCount=13
communityMigrationCount=18
```

The two public interfaces are unreleased `1.3` proposal API.
No Stable `1.2` Guide／Website claim, route, command, example, Community Board
claim, generated artifact, release, or deploy changes in C3.
Public usage Documentation is deferred until the producer／registration journey
owned by P23-003E and the release boundary are accepted.
Guide／Website source must remain byte-identical and Audit Trail must not appear
as a Stable capability.

## In Scope

### 1. Exact Production API and failure boundary

Implement D152 exact fourteen Production types with exact modifiers,
constructors, factories, methods, defaults, nullability, attributes,
dependencies, and caller maps.

Only `BlackOps\Audit\AuditOpaqueIdKeyProvider` and
`BlackOps\Audit\AuditProtectionKeyProvider` carry `#[PublicApi]`.
Every Internal C3 type has exact zero marker.
`AuditDeliveryFailure` is exact six, and `AuditDeliveryException` has the fixed
message, null previous, exact code, and value-free representation surfaces.

`AuditDeliveryPlan` binds exactly Catalog hash, event selector, evidence
version, and delivery.
`AuditDeliveryRequest` holds exactly one Draft and nullable idempotency key
through private sensitive storage; it never carries a caller-selected mode.
A non-null key is exact 1..256-byte UTF-8 NFC with no Cc／Cf code point and is
never trimmed, normalized, case-folded, or coerced. Invalid input is
`policy_invalid` before transaction or Store work.

### 2. Catalog-fixed selection

Implement `AuditDeliverySelector` exactly as D152 §4.
Raw selection accepts only literal `required|durable`, proves the supplied
definition belongs by identity to the trusted Catalog, and requires equality
with the definition's fixed delivery.
Missing／null／unknown／`best_effort`／non-string／enum raw inputs, an untrusted
definition, and every plan／Draft substitution are `policy_invalid` before a
Store call.

The already-typed standalone path resolves through the injected immutable
Catalog lookup and has no runtime override.

### 3. Delivery and same-root mutation

Implement D152's exact `AuditDeliveryCoordinator` and
`AuditMutationTransactionCoordinator`.

Required routes once to `appendRequired()` with a null idempotency key.
Durable routes once to `appendDurable()` with a string key.
There is no fallback, downgrade, retry, projection, or second Store call.

Standalone accepts only auto-commit enabled level0 and returns after the
selected Store-owned root commits.
Current-transaction delivery accepts only auto-commit enabled active level1,
performs zero transaction-control call, and stays active level1.

The mutation wrapper prechecks transactional metadata and exact shared
Framework Connection before business invocation.
It delegates root ownership to the existing `OperationTransactionCoordinator`.
On one completed result, its completion callback creates one request and
appends once before commit.
Rejected results perform zero request／Store work.
Request or append failure rolls the business mutation back.
Request-factory throw／wrong return and transaction-runtime failures contract to
value-free `storage_unavailable`; plan mismatch is `policy_invalid`.
The business handler's own exact throwable is rethrown unchanged after
rollback.

### 4. Protected Operation-read gate

Implement D152's exact requested／terminal gate for Status, Journal, Outcome,
and Diagnostics.
The requested Draft binds purpose, requestRef, actor, execution, tenant, time,
event, action, and required policy, and commits before the future adapter may
authorize or look up a target.

The terminal Draft recomputes the same context and maps Allow to `.accessed`
and Deny／ResourceUnavailable to `.unavailable`.
Allow returns only after its required append commits; any failure withholds an
allow decision.
Every non-allow failure remains the same non-allow decision.
The gate accepts no response/data callback and releases no protected bytes.

### 5. Application key and runtime composition

Implement both exact public provider interfaces and the sole
`ApplicationAuditProtectionKeySource` adapter.
The opaque key is resolved once, is exactly 32 bytes through `StorageKey`, and
feeds one `HmacAuditOpaqueIdDeriver`.
The protection provider sees only canonical partition IDs.
Record／Outbox and active／historical calls remain separate.

Never reference or adapt `StorageKeyProvider` or `StoragePurpose`.
Reject opaque-key reference or material reuse against every returned Audit
protection key before crypto.
Provider errors and values must not appear in any failure or representation
surface.

`ApplicationAuditConfiguration` consumes only closed `audit.outbox` snake-case
members and creates one Accepted C1 configuration.
`ApplicationAuditRuntimeComposer` reuses the exact operation runtime
Connection, Clock, Container, and transaction coordinator; reparses one schema
name; constructs D152's exact one graph; and returns one
`ApplicationAuditRuntime` carrying the same stores and Outbox configuration.
The composer has zero Production callsites and no registration.
The operation composition is only the canonical value constructed by the
existing `ApplicationOperationRuntimeComposer`; its cached
`DoctrineDatabaseManager`, Framework Connection, and transaction coordinator
remain one identity graph. A manually assembled Production composition,
alternate DatabaseManager, or additional Production constructor caller is
forbidden. Direct test assembly may prove identities but is not activation.

### 6. Architecture, package, and release guard

Add one mutation-resistant C3 Architecture oracle and change the four Accepted
Audit Architecture tests only for D152's exact new reference／caller maps.
Every other C1／C2／B1／B2 invariant and Production byte remains unchanged.

Add the `Audit` Deptrac layer. It may depend only on `Core` and
`StorageProtection`. Add only the exact necessary `Audit` edge to Internal and
InternalApplication; do not create a reverse or peer cycle.

Insert the exact fourteen Production paths after
`src/Core/Exception/ConfigurationFailure.php` and before existing Internal
Audit paths, in the D152 order, in both the required-inventory oracle's
expected list and the export script's single `validate_package()` required-path
loop. Required inventory becomes exact68. Preserve exactly two top-level
`validate_package()` calls for Git and Composer, the execution contract, the
C3-new-entry omission negative, and the locally repinned mutation oracles.

## Out of Scope

- C4 relay loop, lease/retry/recovery scheduler, worker, signal, or daemon
- P23-003D operation/authentication/authorization/control adapters
- P23-003E Application event registration, Draft projector authoring, or
  runtime activation
- P23-003F checkpoint／anchor and P23-003G query／export／verify
- any new migration, table, row, CLI command, route, HTTP middleware, Console
  command, Container registration, scheduler, worker, or public docs claim
- generic StorageProtection provider/purpose changes or Audit ciphertext
  re-encryption
- cross-database, external, distributed, or exactly-once atomicity
- commit, push, package publication, release, deploy, generated artifacts, or
  browser verification

## Files Allowed

Production／Test／configuration is exact33 in this order:

```text
src/Audit/AuditOpaqueIdKeyProvider.php
src/Audit/AuditProtectionKeyProvider.php
src/Internal/Audit/Orchestration/AuditDeliveryFailure.php
src/Internal/Audit/Orchestration/AuditDeliveryException.php
src/Internal/Audit/Orchestration/AuditDeliveryPlan.php
src/Internal/Audit/Orchestration/AuditDeliveryRequest.php
src/Internal/Audit/Orchestration/AuditDeliverySelector.php
src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php
src/Internal/Audit/Orchestration/AuditMutationTransactionCoordinator.php
src/Internal/Audit/Orchestration/AuditProtectedReadGate.php
src/Internal/Audit/Key/ApplicationAuditProtectionKeySource.php
src/Internal/Application/ApplicationAuditConfiguration.php
src/Internal/Application/ApplicationAuditRuntime.php
src/Internal/Application/ApplicationAuditRuntimeComposer.php
tests/Audit/AuditKeyProviderContractTest.php
tests/Internal/Audit/Orchestration/AuditDeliveryValueTypesTest.php
tests/Internal/Audit/Orchestration/AuditDeliverySelectorTest.php
tests/Internal/Audit/Orchestration/AuditDeliveryCoordinatorTest.php
tests/Internal/Audit/Orchestration/AuditMutationTransactionCoordinatorTest.php
tests/Internal/Audit/Orchestration/AuditProtectedReadGateTest.php
tests/Internal/Audit/Orchestration/AuditDeliveryTransactionTest.php
tests/Internal/Audit/Key/ApplicationAuditProtectionKeySourceTest.php
tests/Internal/Application/ApplicationAuditConfigurationTest.php
tests/Internal/Application/ApplicationAuditRuntimeTest.php
tests/Internal/Application/ApplicationAuditRuntimeComposerTest.php
tests/Architecture/AuditDeliveryArchitectureTest.php
tests/Architecture/AuditOutboxStorageArchitectureTest.php
tests/Architecture/AuditOutboxStateArchitectureTest.php
tests/Architecture/AuditRecordArchitectureTest.php
tests/Architecture/AuditStoreArchitectureTest.php
deptrac.yaml
tests/Consumer/framework-package-required-inventory.sh
tests/Consumer/framework-package-export.sh
```

Worker-managed exact2:

```text
develop/orchestration/reports/P23-003C3-audit-delivery-orchestration-and-key-composition.md
develop/STATE.md
```

Worker scope is exact35. No other path may move.

Orchestrator-only immutable exact10:

```text
develop/TODO.md
develop/decisions/150-audit-outbox-state-and-delivery-split.md
develop/decisions/151-audit-postgresql-outbox-persistence-boundary.md
develop/decisions/152-audit-delivery-orchestration-and-key-composition-boundary.md
develop/orchestration/tasks/P23-003C3-audit-delivery-orchestration-and-key-composition.md
develop/spec/105-blackops-1-3-capability-contract.md
develop/spec/106-phase-23-delivery-plan.md
develop/spec/README.md
develop/orchestration/reports/P23-003C1-audit-outbox-state-foundation.md
develop/orchestration/reports/P23-003C2-audit-postgresql-outbox-persistence.md
```

The worker must not change an immutable path.

## Exact Failure Precedence

Follow D152's entry-point-specific first-applicable order:

1. configuration shape／type／bounds;
2. Catalog／definition／plan／Draft／idempotency-key nullability policy;
3. transaction auto-commit／nesting／Connection ownership;
4. opaque provider availability during composition;
5. an Accepted canonical／Outbox Store `ResourceUnavailable`, including
   capacity, Clock／UUID exhaustion, and write-side protection;
6. every other contracted Store integrity／idempotency／transition／DB failure.

Business-handler throwables are not contracted or wrapped by C3.
Protection-provider failures inside a Store path have already been collapsed
to the Accepted Store `ResourceUnavailable`; the C3 key adapter exposes only
Accepted `AuditProtectionException`.

## Required Fixtures

### Value and selector

- exact enum values, messages, previous=null, callable/modifier/attribute and
  no-leak surfaces;
- trusted Framework and Application Catalog selections for required and
  durable;
- missing/null/unknown/`best_effort`/number/bool/array/enum raw requirement;
- definition not present by identity, same-selector substitute, wrong Catalog
  hash, event type/version/evidence version/category/delivery, Draft policy,
  and nullability substitution;
- compound invalids proving policy before transaction／Store.

### Coordinator and transaction

- required null key and durable exact 1..256-byte key route exactly once;
- required non-null and durable null/invalid key fail before Store;
- standalone level0 commit and post-state; ambient level1 rejection;
- current level1 participation and zero begin／commit／rollback／savepoint;
- auto-commit false, inactive nonzero, level0 current, level2, and Store
  post-state drift;
- canonical `ApplicationOperationRuntimeComposition` exact-one Production
  construction, cached `DoctrineDatabaseManager` identity across precheck and
  completion, and Architecture rejection of alternate-manager／manual
  Production-composition callers;
- same-Connection real PostgreSQL business row + required Audit row commit;
- same-Connection business row + durable Outbox row commit;
- request-factory, required append, and durable append failure roll back the
  business row;
- capacity and throwing write-protection providers both map the Accepted Store
  `ResourceUnavailable` to C3 `resource_unavailable`; integrity／idempotency／
  transition／DB failures remain `storage_unavailable`;
- request-factory throw／wrong type and begin／commit failure contract without
  previous/canary, while the business handler's same throwable identity is
  rethrown after rollback;
- completed callback exact-one; rejected result, nontransactional metadata,
  and cross-Connection metadata exact-zero;
- no retry, fallback, downgrade, projection, or second Store call.

### Protected read

- all four purposes and exact requested/accessed/unavailable event names;
- basis/context requestRef, event/action, actor/execution/tenant/time bindings;
- requested commit before an authorization sentinel;
- terminal commit before an allow-release sentinel;
- Allow failure throws and release count is zero;
- Deny and ResourceUnavailable preserve the exact decision on every binding,
  policy, transaction, and Store failure;
- mismatched purpose, requestRef, principal, execution, tenant, occurredAt,
  action, terminal event, and decision; no protected bytes in gate input/output.

### Key and composition

- public exact-two interface marker/signature matrix and Internal exact-zero;
- opaque provider missing/wrong/throw, malformed key, and exact-one resolution;
- record／Outbox active and historical selection for system and tenant,
  canonical partition argument, key-ID mismatch, missing history, throw;
- opaque reference or material reused by either protection purpose, and no
  crypto call on rejection;
- raw key/key ID/tenant/provider canaries absent from exception message,
  previous, trace arguments/string, debug, serialize, JSON, and `var_export()`;
- default and every 1/32 C1 bound, unknown members, non-array parents,
  numeric strings, float, bool, and null;
- one config instance, one opaque deriver, one protection adapter, one UUID
  source, one nonce source, one canonical Store, one Outbox Store, and same
  C4 handoff identities;
- missing／wrong Container provider and composer throwable contraction;
- composer zero Production callsites and zero Container/runtime registration.

### Architecture, package, and release

- exact14 inventory including nested/extra/missing/type/modifier mutations;
- exact signatures, defaults, nullability, attributes, public markers,
  dependencies, constructor/factory/caller and direct-call counts;
- alias, group-use, FQCN, same-namespace, class-string one-hop/two-hop,
  heredoc/nowdoc, dynamic, reflection, parenthesized callable, and decoy
  mutations across all `src`;
- generic provider/purpose, environment/default key, transaction owner,
  savepoint, downgrade, projection, relay, response callback, outside caller,
  and runtime activation negatives;
- exact68 order in the required-inventory expected list and the one export
  validation loop, exact-two Git／Composer top-level calls, and missing／add／
  reorder／C3-new-entry omission／call-removal／early-return／alias／shopt／
  repinned mutation rejection;
- PublicApi Architecture, Deptrac `0/0/0`, Stable `1.2.0`, migration13,
  Community18, `StoragePurpose::cases()` exact9, docs no-diff, no new route,
  and no forbidden management IDs in PHP comments.

## Iteration Gate

Run once after all exact33 Product／Test changes and Report／STATE updates are
complete. Stop on first failure.

1. Freeze branch, HEAD, staged0, status exact35, exact33 ordered SHA aggregate,
   immutable exact10 individual hashes/aggregate, Accepted prerequisite hashes,
   and package-script hashes.
2. Prove exact33 and exact35 counts, unique paths, file existence, and both
   add/remove/wrong-hash negatives.
3. Run scoped `mago format --check` for every changed PHP source/test.
4. Run focused PHPUnit for every new direct/real test, all five Audit
   Architecture tests, `PublicApiArchitectureTest`, and affected Application／
   transaction tests.
5. Run the four existing Audit Node golden scripts.
6. Run Deptrac with `--report-uncovered --fail-on-uncovered` and require
   violations/skipped/uncovered/warnings/errors all zero.
7. Run `version-baseline.sh`, required-inventory exact68, Bash syntax, and
   package mutation oracles.
8. Prove migration13／Community18, `StoragePurpose` exact9, zero C3 composer
   Production caller, no runtime registration, docs no-diff/status, forbidden
   ID scan, `git diff --check`, staged0, and post-gate exact freezes.

Record exact command lines, exits, test/assertion counts, Deptrac counters,
mutation counts, hashes, and Not Verified boundaries in the Report.

Do not run `framework-package-export.sh` from the uncommitted worker tree.
Its Git side archives HEAD and cannot prove newly uncommitted C3 files.

## Complete Final Gate

After Iteration Green, independent implementation review, and an unchanged
exact35 refreeze, restart from command 1 and stop on first failure:

1. full `mago format --check src tests`;
2. `composer validate --strict`;
3. full Mago lint and baseline verification;
4. full Mago analyzer at error level and count format;
5. full PHPUnit;
6. Deptrac `--report-uncovered --fail-on-uncovered`;
7. all Audit Node goldens;
8. version baseline and package exact68 required-inventory oracle;
9. Community Board clean-install only if the unchanged current journey is
   already available without changing scope;
10. shell syntax, docs directory/no-diff, forbidden-ID, `git diff --check`,
    staged0, exact35, exact33, immutable exact10, prerequisite and package
    post-guards.

No evidence may be reused after a source/test change.

## Acceptance Criteria

- D152 exact14 Product graph and exact33 Product／Test scope are complete.
- Delivery policy is Catalog-fixed with no override or downgrade.
- Required and durable routes are exact-one and correctly transactional.
- Same-Connection business + Audit commit and rollback failure paths are
  proven on real PostgreSQL.
- Protected-read ordering and allow-data withholding are proven.
- Dedicated opaque/protection key composition is role-separated and no-leak.
- One configuration and one composed graph are identity-proven.
- C1／C2／B1／B2 Production and immutable authority remain byte-identical.
- Exact68 package, PublicApi, Deptrac, Stable/release, migration, docs, and
  nonactivation guards are Green.
- Iteration and Complete Final Gate are Green on one unchanged freeze.
- Fresh General／Transaction／Documentation reviews are separately Green.
- Report and STATE truthfully record verified, historical, Not Run, and Not
  Verified evidence.

## Post-Acceptance

After Orchestrator Acceptance only:

1. commit the reviewed exact candidate without unrelated paths;
2. confirm clean tree/staged0 and exact commit SHA;
3. restart and run `tests/Consumer/framework-package-export.sh` from that exact
   committed SHA;
4. record Git／Composer same-inventory and autoload proof;
5. do not push unless the user separately authorizes it.

Parent P23-003C remains incomplete after C3 Acceptance.
C4 is still required, followed by parent Acceptance and P23-003D..G.

## Blocker Rule

Stop and update the Report／STATE without broadening scope when:

- authority conflicts with an Accepted API or callable graph;
- an exact File, fixture, package path, or required guard is missing;
- a fix needs an unlisted Product／Test／management file;
- transaction, response-release, key-role, or no-leak behavior is not
  implementation-unique;
- branch/HEAD/index/status/hash freeze drifts;
- the configured worker model/effort is explicitly rejected, unavailable, or
  falls back;
- any focused or complete gate fails.
