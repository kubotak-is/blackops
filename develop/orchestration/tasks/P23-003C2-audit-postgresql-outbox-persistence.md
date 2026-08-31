# P23-003C2: Audit PostgreSQL Outbox Persistence

Status: Second Corrected Packet Commit Checkpoint — Fresh re-preflight required before Product work

Updated At: 2026-09-01T06:39:35+09:00

## Current Checkpoint

P23-003C1 is Accepted. Product／Test exact11 is
`d0bfa64e35ce8d113909a2aba710c57ed7bb33fd6711c51df207e71d33919fad`.
Reviewed candidate `bc12a67210d9cb132cf3246f96b00d79fb519656` and its clean
exact-SHA Git／Composer package export are Green. The management closeout is
committed at clean HEAD `4bebcb09d8e67e58c424d0c9543e594708d02872`.

D151 closes C2's exact API, two-table PostgreSQL schema, append idempotency,
ordinal allocation, FIFO selection, C1-backed state CAS, transaction
participation, and release boundary. The initial General／DB／Documentation
preflight lanes are Historical Changes Requested; their configuration,
physical-schema, ordering, scope, package-manifest, and callable-authority
roots are corrected in this packet. No Product work has begun.

This commit establishes the exact nine-file corrected management packet. Its
exact SHA is supplied in the preflight dispatch because a commit cannot record
its own identifier. Fresh re-preflight starts from that clean committed HEAD and
must return Green before the configured Luna worker edits Product.

The first clean-commit re-preflight at
`26527296da112e315c6a332d26282f52625bb7a7` is Historical: General and DB
separately returned `P1=1/P2=0/P3=0`; Documentation returned Green
`P1=0/P2=0/P3=0`. This second bounded correction adds exact retained-row
tenant binding and the current migration-command count test to scope. All
three lanes must re-run from the new clean commit before Product work.

Stable `1.2.0` remains current. BlackOps `1.3` and all Audit capabilities
remain unreleased.

## Goal

Implement one unregistered Internal PostgreSQL Audit Outbox persistence
component that:

- composes Accepted B1 `AuditProtectedIngress` with Accepted C1 state into the
  exact fifteen-field `AuditOutboxRecord v1`;
- appends durable Drafts idempotently with a contiguous per-partition ordinal;
- persists one immutable protected body and only mutates the six C1 state
  fields;
- claims only a due minimum unacknowledged head;
- recovers only an expired minimum head;
- applies retry／relay／poison through expected-version and matching-token CAS;
- participates in a caller-owned level-1 root for later C3 orchestration while
  remaining independently testable with an owned root.

C2 does not activate delivery policy selection, Application key wiring, a
business transaction adapter, or relay runtime.

## Model and Workflow

- Orchestrator／Reviewer: repository-configured `gpt-5.6-sol`／`max`.
- Production Worker: repository-configured `gpt-5.6-luna`／`max`.
- Documentation Reviewer: repository-configured `gpt-5.6-sol`／`xhigh`, read-only when used.
- Worker starts only after fresh re-preflight Green.
- Worker changes only exact Files Allowed.
- Worker updates Report／STATE and does not stage, commit, push, publish, or deploy.
- Any Product／Test change invalidates earlier Iteration／Final evidence.
- Orchestrator alone accepts and commits a reviewed candidate.

Worker Process model metadata absence alone is not a blocker. Explicit model
rejection, unavailability, or fallback is a blocker.

## Prerequisites

1. Branch is `agent/p23-001-blackops-1-3-feasibility`.
2. C1 closeout HEAD `4bebcb09d8e67e58c424d0c9543e594708d02872` is an ancestor of
   the Orchestrator-only corrected-packet commit supplied in the final
   preflight and worker dispatch.
3. Worktree and staged index are clean at the post-packet-commit preflight
   freeze. The dispatch records that exact HEAD and all immutable hashes.
4. P23-003C1 remains Accepted at exact11 aggregate
   `d0bfa64e35ce8d113909a2aba710c57ed7bb33fd6711c51df207e71d33919fad`.
5. Accepted B2 Production exact25 remains
   `4b605e2a23dd77b1c41f8aed95244a46776aef599d2607c88a7d63ee8a2f0354`.
6. Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased.

Drift stops Product work. Do not repair a failed prerequisite inside the
worker scope.

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
15. `develop/orchestration/reports/P23-003C1-audit-outbox-state-foundation.md`
16. `develop/orchestration/reports/P23-003C2-audit-postgresql-outbox-persistence.md`
17. current C1／B1／B2 source, DBAL／migration patterns, and tests named below

D151 is the most-specific C2 authority. D150 remains the parent split and C1
authority. If authority and current code conflict, stop and record a Blocker;
do not resolve it only in Product code.

## Release Documentation Impact

```text
stableVersion=1.2.0
candidateVersion=1.3.0-unreleased
capabilityId=audit.postgresql_outbox_persistence.v1
publicSourceInventory=[examples/community-board/README.md migration-count-only]
publicRouteInventory=[]
```

Framework migration count changes 12→13. Community Board current permanent
journey changes 17→18 (`13 Framework + 5 Application`). Replace exactly once:

```text
all 17 migrations (12 Framework + 5 Community Board Application migrations = 17)
```

with:

```text
all 18 migrations (13 Framework + 5 Community Board Application migrations = 18)
```

Replace consumer exact text `migrations: 17` with `migrations: 18` once.
No other README claim changes. Guide／Website source remains byte-identical and
must not claim Audit Trail as Stable. Generated artifacts and browser behavior
are Not Verified.

## In Scope

### 1. Values, failures, and exact row

Implement D151 §1 exact ten types and §2 exact row order.

`AuditOutboxRecord` may be constructed only from one
`AuditProtectedIngress` and one `AuditOutboxStateSnapshot`. It must verify that
their combined representation is exact and detached. Result types retain
sensitive values only through private `SensitiveParameterValue` storage and
return explicit trusted copies from getters.

The Store receives exactly one `AuditOutboxConfiguration`, constructs exactly
one `AuditOutboxTransition` from it, passes that same configuration to every
full-row hydration, and uses it for the one initial `pending()` construction.
The full-row hydrator does not retain configuration. A second or divergent
configuration source is forbidden.

`AuditOutboxStoreFailure` has exactly five cases. Every exception has fixed
message, code, null previous, and value-free debug／serialization surfaces.

### 2. Idempotency

Implement the D151 exact hasher and verify byte parity with the Accepted B1
envelope codec vectors.

`appendDurable(AuditRecordDraft,string)` validates a durable Draft and raw key
before resources. It derives the partition from the Draft tenant and looks up
the partition-scoped key hash under the partition lock before capacity or
resource work.

Existing-row success must authenticate and decode the stored protected body,
use the stored record ID and timestamp to re-finalize the incoming Draft, and
compare the rebuilt exact envelope. It returns the original row／ordinal／body
with `inserted=false`; it never re-encrypts. Every mismatch is fail-closed and
no mutation occurs.

New-row success reads Clock once, clamps timestamp high-water, generates one
record UUIDv7, acquires the record advisory lock, proves the generated ID is
store-wide absent, allocates ordinal `last+1`, protects once, creates initial
C1 state, and persists the state plus row atomically. Generated-ID collision
returns before Finalizer, envelope, key, nonce, encryption, or write.

### 3. Schema and migration

`PostgreSqlAuditOutboxSchema` owns D151 §3 exact two-table DDL, names, columns,
constraints, indexes, order, and validated qualification.

Migration `Version20260901000000` delegates to that schema and rejects down as
irreversible. Migration runner order ends at the new version and current count
is exactly 13.

Use the locale-independent exact Cc／Cf inventory from the Accepted canonical
Audit schema. Do not use POSIX `cntrl`, locale-sensitive regex behavior, or a
new public helper. Real PostgreSQL fixtures must prove U+2028／U+2029 valid and
U+200E invalid for tenant members.

### 4. Hydration and binary boundary

Implement D151 §2 exact physical eight- and sixteen-column hydrators.
Count and exact key order are checked before copying or extracting members.
Integers, timestamps, tenant pairs, UUIDs, hashes, state, and bytea are
validated before record construction.

Every SELECT projects timestamptz to exact UTC six-fraction text. Every write
binds timestamps as `ParameterType::STRING` and protected body as
`ParameterType::BINARY`. NUL-bearing body fixtures must round-trip from a
second Connection and non-UTC session.

Before decrypt, every path validates tenant-pair／partition consistency and the
full immutable clear binding. A read-side protection failure becomes
`integrity_invalid` without exposing raw cause.

The physical hydrators validate shape and scalars only. The Store uses its
injected `AuditPartitionIdDeriver` after hydration and before decrypt to derive
the hydrated tenant binding's partition, then requires it to equal both the
row's clear partition and the requested／locked partition. Mismatch is
`integrity_invalid` with zero key-source／decrypt calls; no hydrator constructor
or dependency is added.

### 5. Transaction, locks, and atomic append

Use the D151 exact auto-commit／nesting contract and Accepted B2 quarantine
behavior. `autoCommit=false`, inactive nonzero, active zero, or nesting greater
than one is rejected before any application resource.

All mutation lock order is partition advisory lock, partition state row, then
record advisory lock when required. Do not take a record lock before its
partition. Missing state requires an orphan-row probe before bootstrap.

After the partition lock, append validates the exact retained-row aggregate.
Its exact projection is
`row_count,min_ordinal,max_ordinal,tenant_mismatch_count`; the last member
counts retained rows whose tenant type or ID is `IS DISTINCT FROM` the
validated requested pair. Missing state requires `0,NULL,NULL,0`. Present
state at ordinal `N` requires the state tenant pair and derived partition to
match the request, `state_version=N`, and `N,1,N,0`. State ahead／behind,
interior gap, zero-row state, orphan row, any interior／head retained tenant
mismatch, or malformed aggregate fails before Clock, UUID, key, protection,
or write. The monotonic recorded-at scalar is trusted allocator state written
only by the same CTE; external superuser mutation is outside this boundary.

Bootstrap and existing-state append each use one dependency-ordered
data-modifying CTE with a transaction-aborting final count fence. There is no
separate reservation write and no committed ordinal gap. Caller-owned late
count mismatch must leave PostgreSQL root aborted even if the caller catches
the safe exception.

### 6. FIFO and transitions

Implement exact public operations from D151 §6.

`claimNext()` considers only due pending rows that are their partition's
minimum unacknowledged ordinal. `recoverNextExpired()` considers only expired
leased rows at that same head. Candidate selection is deterministic across
partitions but claims no global delivery order.

Claim and recovery capture one Clock before candidate selection, then use that
same value for selection, locked predicate recheck, C1, and CAS. A no-candidate
result still reads Clock exactly once. After selection, reacquire the boundary
under the partition advisory lock and row lock. Do not use same-partition
`SKIP LOCKED`. Authenticate the row before calling C1. Claim generates exactly
one token only after authenticated locked eligibility is known.

`retry`、`relay`、and `poison` require exact identity, tenant／partition,
expected version, and token. They apply only at the minimum unacknowledged head
and use C1 transition output. All state mutation is a one-row expected-version
CAS and changes only the six state columns.

Poison is acknowledged for FIFO. It is retained for evidence and does not
block a later ordinal. Any earlier wording that says poison permanently stops
the partition is superseded by D150／D151.

### 7. Architecture

Add `AuditOutboxStorageArchitectureTest` with mutation-resistant checks for:

- exact ten-file／ten-type Outbox Production inventory;
- exact class／enum modifiers and listed public／protected constructor,
  factory, callable, return, default, and nullability matrix;
- generic `SensitiveParameter` coverage for every data-bearing public or
  private parameter without making private helper names or decomposition an
  implementation authority;
- exact dependency and construction graph;
- exact C1 transition, B1 protection, hydrator, and finalizer call sites;
- no dynamic／indirect call, class-string, alias, FQCN, heredoc／nowdoc, trait,
  or outside caller／reference bypass;
- exact DDL column／constraint／index names and immutable SQL SET exclusion;
- no PublicApi, Application, Container, Console, HTTP, generic Outbox,
  scheduler, worker, relay, or runtime registration;
- no forbidden clear column;
- nested extra file, added type, missing type, API mutation, dependency
  substitution, call-count change, and outside-source canaries.

Update `AuditOutboxStateArchitectureTest` only to authorize exact C1 calls from
the C2 Outbox directory while keeping every other outside caller forbidden.
Update `AuditRecordArchitectureTest` only for the exact new Outbox directory
and protected／finalizer call graph in D151. Accepted B2
`AuditStoreArchitectureTest` and all B2 Production bytes remain unchanged.

### 8. Package and current migration inventory

Update both package scripts from exact37 to the exact ordered 54-path manifest
in D151 §8. The two scripts must reproduce that manifest byte-for-byte. Keep
the execution-contract and mutation oracles non-vacuous; comment,
heredoc, wrapper, early-return, call-removal, later-redefinition, order-swap,
duplicate, missing, and repinned-self-authority mutations must fail before a
whole-file hash can mask semantics.

Update current migration runner／kernel／Community Board inventory to 13／18.
Do not change historical Stable `1.2` version baseline or framework-update
fixtures.

## Out of Scope

- Catalog delivery selection or runtime override handling
- required canonical append orchestration
- same-root business mutation adapter or protected-read response gate
- Application／Container protection-key or opaque-key composition
- relay transport call, canonical append during relay, retry worker, scheduler,
  poison operational command, or process lifecycle
- checkpoint／anchor／query／retention／hold work
- changes to generic operation Outbox types or tables
- changes to Accepted B2 Production or `AuditStoreArchitectureTest`
- public API, CLI, HTTP route, Guide, Website, release, deploy, browser, or push
- Worker stage／commit／push

## Files Allowed

Production／Test exact27:

1. `src/Internal/Audit/Outbox/AuditOutboxStoreFailure.php`
2. `src/Internal/Audit/Outbox/AuditOutboxStoreException.php`
3. `src/Internal/Audit/Outbox/AuditOutboxRecord.php`
4. `src/Internal/Audit/Outbox/AuditOutboxAppendResult.php`
5. `src/Internal/Audit/Outbox/AuditOutboxLookupResult.php`
6. `src/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasher.php`
7. `src/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydrator.php`
8. `src/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydrator.php`
9. `src/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchema.php`
10. `src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php`
11. `migrations/postgresql/Version20260901000000.php`
12. `tests/Internal/Audit/Outbox/AuditOutboxValueTypesTest.php`
13. `tests/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasherTest.php`
14. `tests/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydratorTest.php`
15. `tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydratorTest.php`
16. `tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchemaTest.php`
17. `tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxStoreTest.php`
18. `tests/Architecture/AuditOutboxStorageArchitectureTest.php`
19. `tests/Architecture/AuditOutboxStateArchitectureTest.php`
20. `tests/Architecture/AuditRecordArchitectureTest.php`
21. `tests/Internal/Migration/DatabaseMigrationRunnerTest.php`
22. `tests/Internal/Console/DatabaseMigrationCommandTest.php`
23. `tests/Integration/ApplicationConsoleKernelTest.php`
24. `tests/Consumer/framework-package-required-inventory.sh`
25. `tests/Consumer/framework-package-export.sh`
26. `tests/Consumer/community-board-clean-install.sh`
27. `examples/community-board/README.md`

Worker management exact2:

28. `develop/orchestration/reports/P23-003C2-audit-postgresql-outbox-persistence.md`
29. `develop/STATE.md`

Orchestrator-only immutable exact7:

- `develop/TODO.md`
- `develop/decisions/150-audit-outbox-state-and-delivery-split.md`
- `develop/decisions/151-audit-postgresql-outbox-persistence-boundary.md`
- `develop/orchestration/tasks/P23-003C2-audit-postgresql-outbox-persistence.md`
- `develop/spec/105-blackops-1-3-capability-contract.md`
- `develop/spec/106-phase-23-delivery-plan.md`
- `develop/spec/README.md`

Before worker start, the Orchestrator supplies all immutable exact7 individual
SHA-256 values, their ordered aggregate, and Report／STATE starting hashes.
Only exact27 plus Report／STATE may move. Any other path is a Blocker.

## Required Fixtures

### Values and hydration

- exact fifteen-field logical order and physical sixteen-field order
- exact eight-field protected-ingress subset
- system／tenant null-present pair, partial pair, wrong partition, NFD,
  U+2028／U+2029, U+200E, control, byte bounds
- native and canonical-decimal integer minima／maxima; sign, space, leading
  zero, float, unsafe, cap+1
- all four state/nullability matrices at configured attempts 1 and 32
- one-configuration construction and roundtrip; an alternate max-attempts
  configuration cannot enter hydration or transition through any callable
- timestamp UTC six-fraction, non-UTC wire, infinity, missing／extra／reordered／
  list／wide rows
- string and resource bytea, embedded NUL, body min／max／cap+1 and wrong magic
- maximum valid metadata JCS+LF below 64 KiB with body excluded
- detached input mutation and value-free debug／serialize／JSON／var_export

### Schema and migration

- exact two tables, column order/type/null/default, named constraints, named
  indexes, FK, unexpected-catalog rejection, and irreversible down
- schema invalid before SQL; identifier quoting and same-63-byte-prefix
  rejection remain Green
- real U+2028／U+2029 positives and isolated U+200E constraint negatives for
  tenant type／ID
- state matrix, UUIDv7, hash, ordinal, attempt, body, version, tenant, FK, and
  uniqueness SQLSTATE negatives with before／after row equality
- current migration exact13, first／last order, status read-only, apply exact
- second Connection and non-UTC session persistence roundtrip
- current migration-command framework-only exact 13 and
  Framework-plus-one-Application exact 14 for pending／migrations／applied;
  no current 12／13 assertion remains in that test

### Append and idempotency

- new system and tenant row: ordinal 1, pending attempt0/version1,
  availableAt=recordedAt, exact one Clock／record UUID／active key／nonce／protect
- second new key in same partition ordinal2; other partition starts ordinal1
- Clock behind／equal／ahead high-water and exact six-fraction clamp
- same key＋same Draft returns inserted=false and byte-identical row/body with
  zero Clock／UUID／active key／nonce／encrypt／write
- same hash raw-key substitution, Draft field change, tenant／partition,
  reconstructed logical bytes, content hash, record ID, and body tamper fail
- idempotency hash B1 parity, 1／256 key bounds, cap+1, NFD/control, U+2028／2029
- global generated record-ID collision, ordinal MAX, state-version MAX,
  malformed persisted state, orphan row
- retained-row aggregate exact `row_count／min／max／tenant_mismatch_count`;
  present state requires `N／1／N／0`, while missing state requires
  `0／null／null／0`
- state ahead, state behind, interior gap, zero-row state, orphan row, and
  state-tenant／interior-row／head-row tenant mismatch for system-null and
  tenant-present pairs, each with zero Clock／UUID／Finalizer／key／nonce／
  protect／write and before／after row equality
- generated record-ID collision after partition lock with zero
  Finalizer／envelope／active-key／nonce／encrypt／write
- owned root and caller-owned level1; every invalid transaction state
- late CTE count mismatch leaves caller-owned root aborted; owned rollback and
  uncertain cleanup quarantine
- two-Connection same-key and different-key races prove one row per key,
  ordinal contiguity, no committed gap, no deadlock leak, safe retry

### FIFO, claim, recovery, and CAS

- single pending due claim; not-yet-due exclusion and equality due
- claim／recovery no-candidate and candidate paths each read Clock exactly once;
  selection, recheck, C1, and CAS receive that same bound value
- two rows same partition: ordinal2 never claims while ordinal1 is pending or
  leased; no same-partition SKIP LOCKED
- two partitions may progress independently; candidate order deterministic
- concurrent same-head claim has one winner, one token, +1 attempt/version
- token generated only after authenticated eligibility and from the one Clock
- active lease blocks recovery; expiry-1us／equal／+1us exact boundary
- expired recovery below max returns pending with saturated backoff; at max
  returns poisoned
- poisoned／relayed row retained and acknowledged; later ordinal becomes head
- retry／relay／poison valid owner and every wrong state, tenant, partition,
  version, token, expired／active boundary, double settlement, max+1 negative
- each successful CAS changes exact six state columns only and version +1;
  protectedBody／identity／hash／ordinal byte-identical
- affected count 0／2, stale concurrent CAS, lower-head mutation, immutable SET
  injection, CURRENT_TIMESTAMP, random backoff, and unbounded-loop mutations

### Read, security, architecture, and release

- find system／tenant success and absent／cross-tenant indistinguishable
- tenant／partition predicate and immutable clear binding before hydration,
  key lookup, or decrypt
- actual unique canaries absent from message, previous, trace args／string,
  debug, serialize, JSON, var_export, SQL-facing safe errors
- exact ten-type Outbox architecture and all named mutation oracles
- C1／A2／B1 call graph updated only for C2; B2 Production and Architecture
  unchanged
- no Application／Container／CLI／HTTP／generic Outbox／runtime registration
- Framework migration13, Community total18, package exact54 positive and all
  semantic mutation negatives
- README exact-one migration-count delta; Guide／Website no diff; Stable
  baseline unchanged; no `1.3` current-capability claim

## Acceptance Criteria

- [ ] D151 exact types, row, schema, APIs, transaction, locks, idempotency,
  ordinal, FIFO, CAS, failures, and no-leak behavior are implemented.
- [ ] Required direct and real PostgreSQL fixtures are mutation-resistant and Green.
- [ ] C1 state logic and B1 envelope／protection logic are reused, not duplicated.
- [ ] Existing same-key retry performs no Clock／UUID／active-key／nonce／encrypt／write.
- [ ] New append is atomic and cannot commit an ordinal gap or partial state.
- [ ] Same-partition FIFO cannot be bypassed; poison is acknowledged.
- [ ] Only six state columns mutate after insert; protected bytes never rotate.
- [ ] Exact27 scope, immutable exact7, B2 Production preservation, and staged0 hold.
- [ ] Iteration Gate is Green from the final Product freeze.
- [ ] Complete Final Gate is Green from that unchanged freeze.
- [ ] Fresh independent DB and General Sol reviews return no blocking finding.
- [ ] Report／STATE truthfully record commands, counts, hashes, warnings, and Not Verified surfaces.
- [ ] Stable `1.2.0` remains current; BlackOps `1.3` remains unreleased.

## Iteration Gate

The corrected nine-file management packet is committed by the Orchestrator
before final preflight and worker dispatch. The worker therefore starts from a
clean worktree; after the exact27 Product／Test plus Report／STATE work exists,
the status set is exactly the listed 29 paths.

Run after each coherent exact27 batch and stop at first failure. The dispatch
provides newline-delimited `<sha256> <path>` as
`P23_C2_IMMUTABLE_SHA256_MANIFEST`. Verify exact7 path set, every live hash,
and a synthetic wrong-hash negative before Product work and at ending freeze.

The scope guard must compare `git status --short --untracked-files=all` to the
exact29 Files Allowed list. It must also prove that removing or adding one
synthetic path fails the comparison. No ancient aggregate is a substitute for
the committed clean packet HEAD and exact current scope.

```bash
set -euo pipefail

expected_scope="$({
  printf '%s\n' \
    src/Internal/Audit/Outbox/AuditOutboxStoreFailure.php \
    src/Internal/Audit/Outbox/AuditOutboxStoreException.php \
    src/Internal/Audit/Outbox/AuditOutboxRecord.php \
    src/Internal/Audit/Outbox/AuditOutboxAppendResult.php \
    src/Internal/Audit/Outbox/AuditOutboxLookupResult.php \
    src/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasher.php \
    src/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydrator.php \
    src/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydrator.php \
    src/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchema.php \
    src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php \
    migrations/postgresql/Version20260901000000.php \
    tests/Internal/Audit/Outbox/AuditOutboxValueTypesTest.php \
    tests/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasherTest.php \
    tests/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydratorTest.php \
    tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydratorTest.php \
    tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchemaTest.php \
    tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxStoreTest.php \
    tests/Architecture/AuditOutboxStorageArchitectureTest.php \
    tests/Architecture/AuditOutboxStateArchitectureTest.php \
    tests/Architecture/AuditRecordArchitectureTest.php \
    tests/Internal/Migration/DatabaseMigrationRunnerTest.php \
    tests/Internal/Console/DatabaseMigrationCommandTest.php \
    tests/Integration/ApplicationConsoleKernelTest.php \
    tests/Consumer/framework-package-required-inventory.sh \
    tests/Consumer/framework-package-export.sh \
    tests/Consumer/community-board-clean-install.sh \
    examples/community-board/README.md \
    develop/orchestration/reports/P23-003C2-audit-postgresql-outbox-persistence.md \
    develop/STATE.md
} | LC_ALL=C sort)"
actual_scope="$(git status --short --untracked-files=all | cut -c4- | LC_ALL=C sort)"
test "$(printf '%s\n' "$expected_scope" | sed '/^$/d' | wc -l)" -eq 29
test "$actual_scope" = "$expected_scope"
test "$actual_scope" != "$(printf '%s\n%s\n' "$expected_scope" __p23_c2_extra__ | LC_ALL=C sort)"
test "$actual_scope" != "$(printf '%s\n' "$expected_scope" | sed '1d')"

test -n "${P23_C2_IMMUTABLE_SHA256_MANIFEST:-}"
expected_immutable="$({
  printf '%s\n' \
    develop/TODO.md \
    develop/decisions/150-audit-outbox-state-and-delivery-split.md \
    develop/decisions/151-audit-postgresql-outbox-persistence-boundary.md \
    develop/orchestration/tasks/P23-003C2-audit-postgresql-outbox-persistence.md \
    develop/spec/105-blackops-1-3-capability-contract.md \
    develop/spec/106-phase-23-delivery-plan.md \
    develop/spec/README.md
} | LC_ALL=C sort)"
actual_immutable="$(printf '%s\n' "$P23_C2_IMMUTABLE_SHA256_MANIFEST" | awk 'NF == 2 { print $2 }' | LC_ALL=C sort)"
test "$(printf '%s\n' "$actual_immutable" | sed '/^$/d' | wc -l)" -eq 7
test "$actual_immutable" = "$expected_immutable"
printf '%s\n' "$P23_C2_IMMUTABLE_SHA256_MANIFEST" | sha256sum --check --strict
wrong_immutable="$(printf '%s\n' "$P23_C2_IMMUTABLE_SHA256_MANIFEST" | awk 'NR == 1 { $1 = "0000000000000000000000000000000000000000000000000000000000000000" } { print }')"
if printf '%s\n' "$wrong_immutable" | sha256sum --check --strict >/dev/null 2>&1; then
  exit 1
fi

test -f develop/decisions/151-audit-postgresql-outbox-persistence-boundary.md
test -f develop/orchestration/tasks/P23-003C2-audit-postgresql-outbox-persistence.md
test -f src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php
test -f migrations/postgresql/Version20260901000000.php

docker compose run --rm app mago format --check \
  src/Internal/Audit/Outbox \
  tests/Internal/Audit/Outbox \
  tests/Architecture/AuditOutboxStorageArchitectureTest.php \
  tests/Architecture/AuditOutboxStateArchitectureTest.php \
  tests/Architecture/AuditRecordArchitectureTest.php \
  migrations/postgresql/Version20260901000000.php \
  tests/Internal/Migration/DatabaseMigrationRunnerTest.php \
  tests/Internal/Console/DatabaseMigrationCommandTest.php \
  tests/Integration/ApplicationConsoleKernelTest.php

docker compose run --rm app php vendor/bin/phpunit \
  tests/Internal/Audit/Outbox \
  tests/Internal/Audit/Delivery \
  tests/Architecture/AuditOutboxStorageArchitectureTest.php \
  tests/Architecture/AuditOutboxStateArchitectureTest.php \
  tests/Architecture/AuditRecordArchitectureTest.php \
  tests/Architecture/AuditStoreArchitectureTest.php \
  tests/Architecture/AuditPublicApiTest.php \
  tests/Architecture/PublicApiArchitectureTest.php \
  tests/Internal/Migration/DatabaseMigrationRunnerTest.php \
  tests/Internal/Console/DatabaseMigrationCommandTest.php \
  tests/Integration/ApplicationConsoleKernelTest.php

docker compose run --rm app php vendor/bin/deptrac analyse \
  --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered

node tests/Fixtures/Audit/audit-event-catalog-golden.mjs
node tests/Fixtures/Audit/observed-audit-record-golden.mjs
node tests/Fixtures/Audit/audit-canonical-record-golden.mjs
node tests/Fixtures/Audit/audit-postgresql-store-golden.mjs

bash tests/Consumer/version-baseline.sh
bash -n tests/Consumer/framework-package-export.sh
bash -n tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/framework-package-required-inventory.sh
bash -n tests/Consumer/community-board-clean-install.sh

test -d docs/guide
test -d docs/website
test -z "$(git status --short --untracked-files=all -- docs/guide docs/website)"
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' \
  src tests migrations/postgresql/Version20260901000000.php --glob '*.php'
git diff --check
test -z "$(git diff --cached --name-only)"
```

After Green, record every exact27 individual SHA and the ordered
`sha256sum <exact27 paths> | sha256sum` aggregate. Any exact27 change
invalidates the freeze.

## Complete Final Gate

Run once from the unchanged exact27 freeze after Orchestrator pre-gate review.
Stop at first failure. Re-run the Iteration scope／immutable／package-inventory
guards before command 1 and after the final command.

```bash
set -euo pipefail

docker compose run --rm app mago format --check \
  src tests migrations/postgresql/Version20260901000000.php
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze --reporting-format=count
docker compose run --rm app php vendor/bin/phpunit
docker compose run --rm app php vendor/bin/deptrac analyse \
  --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
node tests/Fixtures/Audit/audit-event-catalog-golden.mjs
node tests/Fixtures/Audit/observed-audit-record-golden.mjs
node tests/Fixtures/Audit/audit-canonical-record-golden.mjs
node tests/Fixtures/Audit/audit-postgresql-store-golden.mjs
bash tests/Consumer/version-baseline.sh
bash tests/Consumer/community-board-clean-install.sh
bash -n tests/Consumer/framework-package-export.sh
bash -n tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/framework-package-required-inventory.sh
test -d docs/guide
test -d docs/website
git diff --exit-code -- docs/guide docs/website
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' \
  src tests migrations/postgresql/Version20260901000000.php --glob '*.php'
git diff --check
test -z "$(git diff --cached --name-only)"
```

`framework-package-export.sh` execution is intentionally deferred because it
compares `git archive HEAD` with the live Composer archive and cannot validate
an uncommitted new-file candidate. Its syntax and required-inventory semantics
are part of both gates.

## Post-Acceptance Commit and Package Gate

Only the Orchestrator performs these steps after fresh implementation review
Green and Acceptance.

1. Commit the reviewed exact candidate.
2. Verify clean worktree and exact commit SHA.
3. Run `bash tests/Consumer/framework-package-export.sh` on that exact SHA.
4. If any later commit changes package-visible bytes, rerun from command 1 on
   the new clean SHA.
5. Do not push unless the user separately requests it.

## Report Requirements

Report must contain:

- Summary
- Changed Files exact list
- Decisions and Assumptions
- Commands and Results with exit codes and counts
- exact27 individual hashes and ordered aggregate
- committed corrected-packet start HEAD and clean preflight freeze
- exact29 scope and immutable exact7 start／end hashes
- Accepted C1 and B2 preservation evidence
- schema／migration／transaction／idempotency／ordinal／FIFO／CAS evidence
- no-leak／tenant-before-decrypt／binary／UTC evidence
- package exact54 and Community migration18 evidence
- Stable／nonactivation／public-doc and generated-artifact boundary
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

Unrun commands and their reasons must be explicit. Do not infer Artifact,
Browser, runtime, release, or deployment Green from Source tests.

## Remaining Issues

- Fresh Sol／Documentation re-preflight from the second corrected-packet commit.
- Luna implementation and Iteration Gate.
- Orchestrator pre-gate review and Complete Final Gate.
- Fresh independent DB／General implementation review.
- Acceptance, candidate commit, and clean same-SHA package export.
- P23-003C3／C4 and parent P23-003C Acceptance.

## Suggested Next Action

Capture this second corrected-packet commit SHA, then run fresh Sol／
Documentation re-preflight against that clean HEAD, this Task, and D151.
Product work waits for Green; no push, runtime activation, or release action.
