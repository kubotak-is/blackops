# P23-003D2: Operation Read Audit Session Foundation

Status: Authority Review Green — clean post-commit implementation preflight required; Product work not started

Updated At: 2026-09-05T12:20:14+09:00

## Current Checkpoint

P23-003C1 through P23-003C4 and parent P23-003C are Accepted. P23-003D1 is
Accepted at commit `35afacd9741272d4e1207d3a76829d85653e4f6f`; the final
tree is clean／staged0 and the Git／Composer package export is Green from that
same SHA. No push was performed.

D154 makes P23-003D a bounded DAG. D155 is the most-specific D2 authority. D2
adds only the Operation-read request／session foundation and a Diagnostics
authorization facade. It does not modify or activate a reader. Status,
Journal／Outcome, and Diagnostics integration remain separate child Tasks.

Stable `1.2.0` remains current. BlackOps `1.3`, D2, and parent P23-003D remain
unreleased.

## Goal

Implement D155 so that:

- every future Operation read can create one trusted request basis outside the
  reader and commit `.requested` before authorization or lookup;
- one 16-byte CSPRNG nonce, one trusted execution source, one Clock capture,
  and one HMAC-opaque Operation resource produce all three fixed drafts;
- a private-construction one-shot session commits exactly one `.accessed` or
  `.unavailable` terminal before a result or failure may be released;
- `AuditProtectedReadGate` becomes the sole writer implementation and fails
  closed for terminal delivery failure on every authorization decision;
- Diagnostics obtains a typed default-unavailable authorization facade without
  touching data or activating the facade;
- exact PHP／Node／Architecture／real-PostgreSQL evidence fixes lifecycle,
  transaction, failure, no-leak, package exact76→85, and nonactivation; and
- no current reader, runtime composition, public source, migration, route,
  command, Documentation, or Stable behavior changes.

## Model and Workflow

- Orchestrator／research／review:
  repository-configured `gpt-5.6-sol`／`max`.
- Production Worker:
  repository-configured `gpt-5.6-luna`／`max`.
- Documentation Reviewer:
  repository-configured `gpt-5.6-sol`／`xhigh`, read-only when used.
- Product work starts only after the authority packet is independently
  reviewed, corrected, committed without unrelated paths, re-frozen clean,
  and fresh General／Transaction／Documentation preflight lanes are separately
  Green.
- The worker changes only exact Files Allowed, updates this Report／STATE, and
  does not stage, commit, push, publish, release, deploy, or run package export
  from an uncommitted tree.
- Every Product／Test change invalidates prior Iteration／Final evidence.
- The Orchestrator alone reviews, accepts, commits, and runs the clean same-SHA
  package export.

Worker Process model metadata absence alone is not a blocker. Explicit model／
effort rejection, unavailability, or fallback is a blocker.

## Prerequisites

1. Branch is `agent/p23-001-blackops-1-3-feasibility`.
2. Accepted D1 commit `35afacd9741272d4e1207d3a76829d85653e4f6f`
   is an ancestor of the Orchestrator-supplied clean D2 authority commit.
3. Worktree and index are clean before fresh preflight and worker dispatch.
4. The fourteen immutable authority paths match the Orchestrator-supplied
   individual and ordered aggregate SHA-256 freeze.
5. Accepted C3／D1 source, tests, Catalog identities, package scripts, and
   required inventory match the supplied freeze.
6. Stable `1.2.0` remains current; Framework migrations are `13`, Community
   Board Application migrations are `5`, total `18`; BlackOps `1.3` remains
   unreleased.
7. Package required inventory is exact76 before D2.

Any drift stops Product work. Do not repair a prerequisite inside worker scope.

The clean authority-commit individual hashes and aggregates are deliberately
not embedded in this Task or another member of the hashed immutable set: doing
so would create a self-referential hash. After the management-only commit, the
Orchestrator computes the exact14 individual hashes and ordered aggregate, plus
the exact package／Accepted-source hashes, and supplies them out-of-band in the
fresh preflight and worker dispatch. A missing, partial, or mismatched dispatch
freeze is a blocker; a worker must not infer or repin it.

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
13. `develop/decisions/152-audit-delivery-orchestration-and-key-composition-boundary.md`
14. `develop/decisions/154-framework-operational-audit-catalog-and-adapter-split.md`
15. `develop/decisions/155-operation-read-audit-session-foundation.md`
16. Accepted P23-003A／B／C／D1 Tasks and Reports named by those authorities
17. current Core Audit read types, security／access Catalog, C3 delivery／key
    composition, four Operation reader surfaces, Architecture, package,
    migration, release, and public-source files named here

D155 is the most-specific D2 authority. It supersedes only D152's bounded
non-Allow terminal-failure suppression. If authority and current code conflict,
stop and record a Blocker; do not invent a Product-only resolution.

## Release Documentation Impact

```text
stableVersion=1.2.0
candidateVersion=1.3.0-unreleased
capabilityId=audit.operation_read_session_foundation.v1
publicSourceInventory=[]
publicRouteInventory=[]
publicCliInventory=[]
frameworkMigrationCount=13
communityApplicationMigrationCount=5
communityTotalMigrationCount=18
packageRequiredBefore=76
packageRequiredAfter=85
currentReaderActivation=[]
```

Public Guide／Website／Landing／release-note source remains unchanged. D2 is an
unreleased Internal package foundation, not an advertised Stable capability.
Generated artifacts and browser behavior remain Not Verified unless actually
generated or exercised.

## In Scope

### 1. Exact writer, nonce, and execution ports

Add exact Internal interfaces:

```text
AuditReadEventWriter
  requested(AuditReadRequestBasis, AuditRecordDraft): void
  terminal(AuditReadContext, AuditRecordDraft): AuditReadAuthorizationDecision

OperationReadNonceSource
  generate(): string

OperationReadExecutionSource
  current(): AuditTypedReference
```

Every writer argument and every data-bearing input has exact
`#[SensitiveParameter]` attributes fixed by D155. `AuditProtectedReadGate` is
the only Production writer implementation. D2 has no Production execution-
source implementation.

`RandomOperationReadNonceSource` is final readonly, has no property／
constructor, and returns one `random_bytes(16)` result from its only method.
The existing 24-byte Storage Protection nonce source is byte-identical and is
not referenced from D2.

### 2. Exact factory API and construction order

Implement the D155 exact constructor and `begin()` signature for
`OperationReadAuditSessionFactory`. It has one private
`SensitiveParameterValue $snapshot`; no direct dependency, build ID, raw key,
or caller value property is permitted.

Constructor validation is resource-free. `begin()` executes exactly:

```text
actor／tenant conversion
-> execution source once
-> HMAC Resource derivation once
-> Clock once／UTC six-fraction validation
-> nonce source once／exact16／lower-hex32
-> one basis
-> requested, accessed, unavailable drafts
-> session constructor／snapshot completion
-> requested writer once
-> pure session return
```

No writer call occurs until all three drafts are valid. Raw Operation ID
materializes only inside the one sensitive `derive(Resource, toString())`
call. The factory stores no raw Operation ID and creates no UUID or idempotency
key.

The build ID uses exactly
`AuditValidationException::assertSafeString($buildId, 1, 256)`, without trim,
case-fold, or alternate normalization. The factory and session expose the
exact public magic methods and private helper signatures enumerated by D155;
no extra helper, property, trait, parent, interface, or indirect callable is
permitted. Factory serialization returns an empty representation and
unserialization fails with fixed `StorageUnavailable`; default factory clone
is I/O-free. Session serialization returns an empty representation without
changing lifecycle state, clone fails with fixed `PolicyInvalid`, and
unserialization fails with fixed `StorageUnavailable`.

The exact HMAC and four requestRef vectors in D155 are normative. PHP and the
independent Node fixture must match exact preimage lengths and output bytes.

### 3. Exact draft matrix

For each of the four purposes and three phases, assert every D155 field:

- Accepted Catalog identity/hash and definition version;
- purpose-derived event/action/component;
- requestRef action ID;
- converted actor, trusted execution, converted tenant, and one timestamp;
- HMAC-opaque resource type／ID;
- exact Catalog phase／outcome／policy tuple;
- policy `operation-read` version 1 and required delivery;
- trusted Framework source runtime/build;
- empty correlation and evidence version 1; and
- required request idempotency key null.

No raw Operation ID, query subject, origin actor／tenant, result, diagnostic,
journal, outcome, token, credential, or payload appears in a draft.

### 4. One-shot session

Implement the exact D155 `OperationReadAuditSession` API and private state.
`open()` is called only by the factory and commits requested before returning.
It must first complete `new self` and the session snapshot, then invoke the
requested writer exactly once, then do nothing except return the already-built
session. Construction failure maps to fixed `StorageUnavailable` with zero
writer／Store work. Swapping construction and writer order or adding any
post-writer validation／construction／call is forbidden.
`accessed()` selects Allow; `unavailable()` selects ResourceUnavailable. There
is no Deny producer.

The terminal-attempt flag changes before context creation or writer call.
Success, explicit writer failure, unexpected writer failure, and malformed
snapshot all permanently close the session. A second terminal call and clone／
unserialize attempts perform zero writer／Store work; serialize succeeds only
as an empty representation and also performs zero work. The destructor performs
no work. A successful writer return must be identical to the selected decision
or the session throws fixed `PolicyInvalid`, remains closed, and releases no
reader result／failure.

### 5. Fail-closed Gate correction

Make `AuditProtectedReadGate` implement `AuditReadEventWriter`. Preserve its
public method names and return types. Separate binding and delivery catches so
that policy and storage failures cannot be confused.

Remove the Accepted C3 non-Allow suppression. For Allow, Deny, and
ResourceUnavailable, `terminal()` returns only after successful append and
otherwise throws the contracted `AuditDeliveryException`.

Expand binding to the exact D155 Catalog, resource, policy, source,
correlation, and evidence matrix. Binding fails before transaction／Store.
Delivery failure retains C3 Store mapping and no previous throwable.

### 6. Diagnostics authorization facade

Implement the exact D155 request, interface, and default implementation:

```text
OperationDiagnosticsAuthorizationRequest
OperationDiagnosticsAuthorizer
UnavailableOperationDiagnosticsAuthorizer
```

The request stores Operation ID／actor／tenant only inside one
`SensitiveParameterValue`, exposes only D155's exact constructor, three typed
getters, debug／serialize／unserialize methods, and one private `value()` helper.
Debug and serialization return empty arrays; unserialization throws the exact
fixed `LogicException`, while a malformed snapshot throws D155's distinct
fixed unavailable `LogicException`. Default clone is I/O-free and preserves
the three value object identities. The default authorizer has no state and always returns
ResourceUnavailable without reading the request or performing I/O.

Do not modify `OperationDiagnosticsQuery`, console or viewer factories,
routes, commands, or runtime composition. D2X owns integration and a trusted
policy.

### 7. Architecture and mutation resistance

Add one dedicated D2 Architecture test and update only the three shared
Architecture files listed in Files Allowed.

Evidence must fix:

- exact nine new source files and one modified Gate;
- declarations, parents／interfaces, properties, public／private callables,
  modifiers, types, nullability, defaults, return types, and all
  SensitiveParameter attributes;
- D155's complete per-source direct project／built-in dependency table and
  exact factory／session／Gate direct-call counts across all `src`;
- only Gate implements writer, only factory calls session `open()`, no current
  factory caller, and no direct writer use outside the D2 foundation;
- exact one random_bytes16, Clock, execution source, HMAC Resource derive,
  requested call, and selected terminal call;
- no dynamic call, `call_user_func`, callable array, class-string,
  Reflection, variable construction, alias／group-use／FQCN bypass, two-hop
  identity, extra file／type／method／property／dependency, or same-namespace
  bypass;
- no database, transaction control, Container, environment, filesystem,
  network, UUID, 24-byte nonce, Application composition, reader, route, CLI,
  worker, scheduler, or registration reference; and
- false-positive controls for comments, docblocks, unconsumed strings,
  heredoc／nowdoc, enum `::class`, unrelated callables, and allowed internal
  helper calls.

The factory private inventory is exact nine helpers: `value`, `reference`,
`execution`, `resourceId`, `requestedAt`, `nonce`, `definition`, `draft`, and
`component`. The session private inventory is its constructor, `terminal`, and
`value`; the Diagnostics request private inventory is `value`. The scanner
must reject added, removed, renamed, visibility-changed, or signature-mutated
helpers and dependencies. It must also prove the modified Gate adds only the
D155 writer／Core binding edges while all other Accepted edges stay exact.

Every mutation must execute the actual scanner and prove the intended rule
before any whole-file hash pin. Shared A1／A2／B／C／D1 exact maps receive only
the D2 edges explicitly named by D155.

### 8. Golden, real database, and no-leak evidence

Add one exact JSON fixture and one independent Node oracle for the D155 HMAC,
basis, requestRef, event/action/resource/policy/source/correlation/evidence
matrix. The Node oracle must reconstruct bytes rather than copy PHP output.

Add a real PostgreSQL session fixture with independently visible connections.
It proves requested commit visibility before an authorization sentinel and
terminal commit visibility before release, two separately completed Store-
owned roots, exact record count/order/requestRef, and inactive level0 before,
between, and after.

Raw Operation ID, actor, tenant, execution, nonce bytes, opaque key, provider,
draft, and injected canaries must be absent from exception message／code／
previous／trace arguments／trace string, debug, `print_r`, serialize, JSON, and
`var_export()` for direct factory, session, Diagnostics request, and failure
surfaces.

### 9. Package, release, and nonactivation

Insert the exact nine new source paths once in both package inventories,
growing exact76→85 without changing any other order or root. The six Read
paths go immediately after `AuditProtectedReadGate.php` and before
`AuditRelayOutcome.php`; the three Diagnostics paths go immediately after
`ApplicationAuditRuntimeComposer.php` and before `AuditStoreFailure.php`, each
block in Files Allowed source order. Independently assert count85 and unique85.

Retain the existing exact30 required-inventory mutations and add exactly one
`d2-entry-omission` mutation that removes
`src/Internal/Audit/Read/AuditReadEventWriter.php`. The final result is exact31
rejections: 17 inventory plus unchanged 14 call-contract. Existing add／
duplicate／order mutations cover those dimensions of the inserted blocks.
Update the reviewed export-script SHA only after executable call-contract and
local-repin mutations remain Green.

Prove PublicApi zero, Deptrac zero violations／skipped／uncovered, zero
Production factory caller, zero reader／Diagnostics／Application composer／
Container／route／CLI activation, Stable and public source zero delta,
Framework migration13／Community Application migration5／total18, and unchanged Stable generic Outbox／C4 Audit Outbox
boundaries.

## Out of Scope

- Status, Journal, Outcome, or Diagnostics reader implementation／constructor
  changes;
- HTTP handler, CLI factory, viewer, Container, Application runtime or
  `ApplicationAuditRuntimeComposer` changes;
- a Production `OperationReadExecutionSource` implementation;
- actual authorization policy, subject/result mapping, or reader exception
  mapping;
- D3 authentication, D4 authorization, D5 phase sources, D-control, or
  D-integration;
- Core Audit types, Accepted Catalog bytes, HMAC algorithm/key storage,
  canonical／Outbox Store, migration, configuration, route, command, worker,
  scheduler, maintenance, or public API;
- public Documentation, example, release note, generated artifact, browser,
  CI, release, deploy, stage, commit, push, or package export from the worker
  tree.

## Files Allowed

Production／Test is exact22 in this order:

```text
src/Internal/Audit/Read/AuditReadEventWriter.php
src/Internal/Audit/Read/OperationReadNonceSource.php
src/Internal/Audit/Read/RandomOperationReadNonceSource.php
src/Internal/Audit/Read/OperationReadExecutionSource.php
src/Internal/Audit/Read/OperationReadAuditSessionFactory.php
src/Internal/Audit/Read/OperationReadAuditSession.php
src/Internal/Diagnostics/OperationDiagnosticsAuthorizationRequest.php
src/Internal/Diagnostics/OperationDiagnosticsAuthorizer.php
src/Internal/Diagnostics/UnavailableOperationDiagnosticsAuthorizer.php
src/Internal/Audit/Orchestration/AuditProtectedReadGate.php
tests/Internal/Audit/Read/OperationReadAuditSessionTest.php
tests/Internal/Audit/Read/PostgreSqlOperationReadAuditSessionTest.php
tests/Internal/Diagnostics/OperationDiagnosticsAuthorizationTest.php
tests/Internal/Audit/Orchestration/AuditProtectedReadGateTest.php
tests/Architecture/AuditOperationReadArchitectureTest.php
tests/Architecture/AuditDeliveryArchitectureTest.php
tests/Architecture/AuditRecordArchitectureTest.php
tests/Architecture/AuditPublicApiTest.php
tests/Fixtures/Audit/audit-operation-read-foundation-v1.json
tests/Fixtures/Audit/audit-operation-read-foundation-golden.mjs
tests/Consumer/framework-package-required-inventory.sh
tests/Consumer/framework-package-export.sh
```

All exact22 paths must move. New paths are exact15: the nine source paths,
three new PHP tests, one new Architecture test, and two fixtures. Existing
paths are exact7: Gate, Gate test, three shared Architecture tests, and two
package scripts. Every other Product／Test path remains byte-identical.

Worker-managed exact2:

```text
develop/orchestration/reports/P23-003D2-operation-read-audit-session-foundation.md
develop/STATE.md
```

Worker scope is exact24. No other path may move.

Orchestrator-only immutable exact14:

```text
develop/TODO.md
develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md
develop/decisions/147-audit-catalog-and-safe-projection-boundary.md
develop/decisions/152-audit-delivery-orchestration-and-key-composition-boundary.md
develop/decisions/154-framework-operational-audit-catalog-and-adapter-split.md
develop/decisions/155-operation-read-audit-session-foundation.md
develop/orchestration/tasks/P23-003C3-audit-delivery-orchestration-and-key-composition.md
develop/orchestration/tasks/P23-003D1-framework-operational-audit-catalog.md
develop/orchestration/tasks/P23-003D2-operation-read-audit-session-foundation.md
develop/orchestration/reports/P23-003C3-audit-delivery-orchestration-and-key-composition.md
develop/orchestration/reports/P23-003D1-framework-operational-audit-catalog.md
develop/spec/105-blackops-1-3-capability-contract.md
develop/spec/106-phase-23-delivery-plan.md
develop/spec/README.md
```

The worker must not change an immutable path.

## Exact Failure Precedence

Follow D155's exact first-applicable boundary table. Use only the existing
six-case `AuditDeliveryFailure` enum—`ConfigurationInvalid`, `PolicyInvalid`,
`TransactionInvalid`, `KeyUnavailable`, `ResourceUnavailable`, and
`StorageUnavailable`—and add no enum or case. In particular, distinguish an
execution-source invocation failure from a successfully returned but malformed
execution reference, distinguish a nonce-source failure from a wrong-length
string, preserve explicit writer `AuditDeliveryException` identity, map corrupt
factory／session snapshots to `StorageUnavailable`, and map duplicate terminal,
clone, or returned-decision mismatch to `PolicyInvalid`.

Map local session／snapshot construction failure before requested invocation to
fixed `StorageUnavailable` with zero writer／Store work. After requested
invocation begins, only pure return is permitted; writer commit-then-throw and
process-crash windows remain governed by the explicit crash contract.

Every new `AuditDeliveryException` has the existing fixed message, zero code, null previous,
and no sensitive value on any surface. Compound fixtures arm all later
resources and prove zero calls after the first applicable failure. The session
sets its terminal flag before snapshot validation; an internal corrupt-snapshot
failure cannot be retried.

## Required Fixtures

### Factory and nonce

- exact constructor／begin signatures, property and helper inventory;
- build ID exact safe-string 1／256 pass, empty／257／invalid UTF-8／Cc／Cf／
  non-NFC fail as ConfigurationInvalid before every dependency call, with
  byte-preserving valid Unicode;
- actor and tenant null/present plus all four one-axis mismatch／invalid
  conversions;
- execution source exact once, invocation throw／return TypeError maps resource,
  malformed reflected `AuditTypedReference` state maps policy, and no fallback;
- HMAC exact vector, wrong key state/throw, exact Resource role, and no other
  purpose;
- Clock exact once, non-UTC normalization, six fractions, expanded-year／
  boundary failure, and no DB time;
- nonce exact one call; exact16, 15, 17, empty, NUL-containing exact16,
  throwable, and lowercase bin2hex;
- all four exact requestRef vectors and actor／tenant null variants;
- all three drafts built before requested writer; mutation of every field
  independently fails before Store.
- direct factory empty debug／serialize, failed unserialize, default-clone
  behavior, corrupt snapshot, and value-free failure surfaces.

### Session and writer

- four purposes × requested／accessed and requested／unavailable;
- `open()` constructs the session／snapshot before requested, then performs only
  a pure return; an actual statement-order oracle rejects writer-before-`new
  self` and every post-writer validation／construction／call mutation;
- the source oracle fixes local-construction failure contraction to
  StorageUnavailable with zero writer／Store calls, without attempting to
  synthesize an allocator failure at runtime;
- requested commit before the returned session and before an authorization
  sentinel;
- terminal commit before result／failure release;
- one terminal success, second accessed, second unavailable, accessed after
  unavailable failure, and unavailable after accessed failure all perform zero
  second writer work;
- explicit writer exception identity, unexpected requested／terminal failure
  contraction, malformed snapshots, decision-return mismatch, clone,
  empty-representation serialize, failed unserialize, and destructor no-I/O;
- Gate binding mutation for every D155 event/action/actor/execution/tenant/
  time/resource/outcome/policy/source/correlation/evidence field;
- Gate accepts each typed runtime with a valid factory-supplied build and
  rejects unsafe build values; valid-but-different runtime／build is owned by
  factory golden／sole-caller Architecture mutations rather than an impossible
  uninjected Gate comparison;
- Deny and ResourceUnavailable delivery failure now throw exactly like Allow;
  no terminal record means no decision release;
- required key null and no durable／Outbox route.

### Real PostgreSQL and crash boundary

- system and tenant sessions, exact two roots, exact sequence/order/ref and
  separately visible commits;
- auto-commit false, ambient level1, inactive nonzero, level2, requested and
  terminal post-state drift;
- requested append rollback leaves zero records; terminal append rollback
  leaves requested only; next new session succeeds with a new ref;
- Store capacity and Clock/UUID/write-protection resource failures versus
  integrity／DB storage failures;
- crash-window simulation for before requested, requested-only, and exact-two
  committed states; no synthetic recovery or same-session retry.

### Diagnostics facade

- exact request API, value identity, nullability quadrants, SPV-only storage,
  default clone identity, empty debug／serialize, fixed failed unserialize, and
  no-leak canaries;
- default authorizer always ResourceUnavailable for all inputs and does not
  invoke getters or I/O;
- exact one interface implementation and zero current Production caller;
- current Diagnostics query／CLI／viewer bytes and behavior unchanged.

### Golden, Architecture, release, and package

- PHP and independent Node exact vectors, all 4×3 field matrices, byte lengths,
  HMAC role/domain, and mutation negatives;
- exact API／dependency／caller and false-positive Architecture matrix from
  D155;
- PublicApi exact zero and Deptrac zero violations／skipped／uncovered;
- package exact85 ordered／unique paths and exact31 mutation results
  (`17 inventory + 14 call-contract`);
- Stable version, Framework migration13／Community Application migration5／
  total18, StoragePurpose exact9, Stable generic
  Outbox, C4 Outbox, and Accepted D1 Catalog/lookup preservation;
- public source／route／CLI／Guide／Website delta zero, nonactivation zero, and
  no forbidden management IDs in PHP comments.

## Iteration Gate

Run once after all exact22 Product／Test changes and Report／STATE updates are
complete. Stop on first failure.

1. Freeze branch, HEAD, staged0, status exact24, exact22 ordered SHA aggregate,
   immutable exact14 individual hashes／aggregate, Accepted D1 ancestor and
   source hashes, package-script hashes, and exact76 baseline.
2. Prove exact22／24 counts, uniqueness, path states, all exact22 moved, exact15
   added, exact7 modified, and add／remove／duplicate／wrong-state／wrong-hash
   negatives.
3. Run these exact commands:

   ```bash
   docker compose run --rm app mago format --check src/Internal/Audit/Read/AuditReadEventWriter.php src/Internal/Audit/Read/OperationReadNonceSource.php src/Internal/Audit/Read/RandomOperationReadNonceSource.php src/Internal/Audit/Read/OperationReadExecutionSource.php src/Internal/Audit/Read/OperationReadAuditSessionFactory.php src/Internal/Audit/Read/OperationReadAuditSession.php src/Internal/Diagnostics/OperationDiagnosticsAuthorizationRequest.php src/Internal/Diagnostics/OperationDiagnosticsAuthorizer.php src/Internal/Diagnostics/UnavailableOperationDiagnosticsAuthorizer.php src/Internal/Audit/Orchestration/AuditProtectedReadGate.php tests/Internal/Audit/Read/OperationReadAuditSessionTest.php tests/Internal/Audit/Read/PostgreSqlOperationReadAuditSessionTest.php tests/Internal/Diagnostics/OperationDiagnosticsAuthorizationTest.php tests/Internal/Audit/Orchestration/AuditProtectedReadGateTest.php tests/Architecture/AuditOperationReadArchitectureTest.php tests/Architecture/AuditDeliveryArchitectureTest.php tests/Architecture/AuditRecordArchitectureTest.php tests/Architecture/AuditPublicApiTest.php
   docker compose run --rm app php vendor/bin/phpunit --display-warnings tests/Internal/Audit/Read/OperationReadAuditSessionTest.php tests/Internal/Audit/Read/PostgreSqlOperationReadAuditSessionTest.php tests/Internal/Diagnostics/OperationDiagnosticsAuthorizationTest.php tests/Internal/Audit/Orchestration/AuditProtectedReadGateTest.php tests/Internal/Audit/Orchestration/AuditDeliveryTransactionTest.php tests/Architecture/AuditOperationReadArchitectureTest.php tests/Architecture/AuditDeliveryArchitectureTest.php tests/Architecture/AuditRecordArchitectureTest.php tests/Architecture/AuditPublicApiTest.php
   node tests/Fixtures/Audit/audit-operation-read-foundation-golden.mjs
   node tests/Fixtures/Audit/audit-event-catalog-golden.mjs
   docker compose run --rm app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
   bash tests/Consumer/version-baseline.sh
   bash tests/Consumer/framework-package-required-inventory.sh
   bash -n tests/Consumer/framework-package-required-inventory.sh
   bash -n tests/Consumer/framework-package-export.sh
   ```

4. Prove Accepted C3/D1 source and focused tests outside exact7 are
   byte-identical; Stable generic Outbox positives, Framework migration13／
   Community Application migration5／total18,
   `StoragePurpose` exact9, package85, PublicApi／nonactivation／public-source／
   route／CLI／docs zero deltas, Guide／Website no-diff/status, forbidden-ID
   scan, `git diff --check`, staged0, and ending exact freezes.

Record literal command lines, exit codes, test／assertion counts, Deptrac
counters, mutation counts, individual and aggregate hashes, and Not Verified
boundaries in the Report.

Do not run `framework-package-export.sh` from the uncommitted worker tree. Its
Git side archives HEAD and cannot prove uncommitted new files.

## Complete Final Gate

After Iteration Green, fresh independent General／Transaction／Documentation
implementation reviews, and an unchanged exact24 refreeze, restart at command
1 and stop on first failure:

1. `docker compose run --rm app mago format --check src tests`;
2. `docker compose run --rm app composer validate --strict`;
3. full Mago lint and lint-baseline verification;
4. full Mago analyzer at error level and count format;
5. full PHPUnit;
6. Deptrac `--report-uncovered --fail-on-uncovered`;
7. all six Audit Node goldens, exactly
   `audit-canonical-record-golden.mjs`, `audit-event-catalog-golden.mjs`,
   `audit-operational-event-catalog-golden.mjs`,
   `audit-postgresql-store-golden.mjs`, `observed-audit-record-golden.mjs`, and
   `audit-operation-read-foundation-golden.mjs`;
8. version baseline and package exact85 required-inventory oracle;
9. Community Board clean-install only when the unchanged journey is available
   without changing scope;
10. shell syntax, Stable generic Outbox, C4 Outbox, D1 Catalog, migration,
    StoragePurpose, docs/no-diff, nonactivation, forbidden-ID, diff-check,
    staged0, exact24, exact22, immutable exact14, prerequisite, and package
    post-guards.

No evidence may be reused after a Product／Test change.

## Acceptance Criteria

- D155 exact ports, factory, session, Gate correction, and Diagnostics facade
  are complete.
- Requested and one terminal required record persist in exact order before
  release; terminal failure is fail-closed for every decision.
- Exact HMAC／requestRef／draft bytes, lifecycle, transaction, failure, no-leak,
  and mutation evidence are Green.
- Accepted Core／Catalog／C3／D1 behavior changes only at the explicit Gate
  correction and shared Architecture edges.
- Exact85 package, PublicApi, Deptrac, Stable／release, migration, Outbox,
  public-source, docs, and nonactivation guards are Green.
- No reader child, D3／D4／D5／D-control／D-integration Product work or activation
  occurred.
- Iteration and Complete Final Gate are Green on one unchanged freeze.
- Fresh General／Transaction／Documentation reviews are separately Green.
- Report and STATE truthfully separate current, Historical, Not Run, and Not
  Verified evidence.

## Post-Acceptance

After Orchestrator Acceptance only:

1. accept P23-003D2 foundation but keep parent P23-003D incomplete;
2. commit the reviewed exact candidate without unrelated paths;
3. confirm clean tree／staged0 and exact commit SHA;
4. restart and run `tests/Consumer/framework-package-export.sh` from that exact
   committed SHA;
5. record Git／Composer same-inventory and autoload proof; and
6. do not push unless the user separately authorizes it.

Next work is a separately authorized D2S, D2J, or D2X reader child. D-control
remains blocked on Accepted P23-003F.

## Blocker Rule

Stop and update Report／STATE without broadening scope when:

- D155 conflicts with an Accepted Core／Catalog／C3／D1 API, byte, transaction,
  or release invariant outside the explicit Gate correction;
- a trusted execution source or reader integration is needed to make the
  foundation pass;
- any exact file, fixture, hash, count, mutation, transaction, no-leak, or
  nonactivation guard is missing or cannot run;
- a fix needs an unlisted Product／Test／management file;
- a reader, composer, Container, route, CLI, migration, public API, or runtime
  activation is required;
- branch／HEAD／index／status／hash freeze drifts; or
- the configured worker model／effort is explicitly rejected, unavailable, or
  falls back.
