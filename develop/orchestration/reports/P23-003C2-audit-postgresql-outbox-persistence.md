# P23-003C2 Report: Audit PostgreSQL Outbox Persistence

Status: Changes Requested — General contract Green; DB Product P1=3 and Documentation P2=2 bounded correction required

Updated At: 2026-09-01T10:32:46+09:00

## Corrected-scope Review Checkpoint

Fresh General review is Green `P1=0/P2=0/P3=0`. Fresh DB review confirms the
authority／scope correction is Green and returns preserved Product Changes
Requested `P1=3/P2=0/P3=0`: expired recovery calls a CAS with the active-lease
predicate, the Outbox Cc／Cf inventory includes U+1D17B beyond Accepted B2,
and one branched append statement contains both bootstrap and existing-state
CTEs. Fresh Documentation review returns `P1=0/P2=2/P3=0` for the stale
Decision catalog and incomplete current Changed Files／Next Action.

The Documentation roots are corrected in this management freeze. Luna's next
scope is limited to the three DB Product roots, their direct and real
PostgreSQL fixtures, the exact B2 Architecture callsite map, and Report／STATE.
Prior focused Green evidence is Historical for changed Product bytes.
The preserved dirty set remains exactly Task Product／Test items 1–20 at sorted
aggregate
`29c6a9873ed3c0e1f9b6abdd551fde8e3e0d3f751f99a81522d408c81d971a88`;
the index is staged0. The amended management commit SHA and new immutable
exact7／committed exact9 hashes are supplied after commit because the commit
cannot self-record its own identifier or bytes.

## Bounded Architecture-scope Correction Checkpoint

The first corrected-scope General pass found one residual literal in Required
Fixtures: it still said B2 Architecture was unchanged, contradicting the exact
map exception already fixed by D151, the Architecture section, Files Allowed,
and the gates. That preliminary freeze is superseded. The Task now says B2
Production remains unchanged and B2 Architecture changes only the exact C2
Outbox protection-callsite map while every other guard and mutation negative
remains fixed. All fresh lanes restart from the amended management freeze.

At `2026-09-01T10:16:21+09:00`, the Orchestrator reproduced the compatibility
failure against current Source and authority. D151 requires exactly one direct
C2 Store callsite each for `AuditProtectedBodyCodec::protectOutbox()` and
`unprotectOutbox()`. The unchanged Accepted B2
`AuditStoreArchitectureTest.php` still requires both callsite maps to be empty,
so the frozen contract cannot satisfy its own broader Architecture suite.

The bounded correction adds only `AuditStoreArchitectureTest.php` to C2 Files
Allowed, changes Product／Test exact27→exact28 and worker exact29→exact30, and
authorizes only the two C2 Outbox direct-call map entries. Accepted B2 record
callsites, indirect／dynamic-call rejection, every other B2 guard, and every B2
Production byte remain fixed. The preserved partial implementation is not
discarded or committed. Prior preflight and Architecture evidence is
Historical after this authority change; Product work is paused until fresh
General／DB／Documentation re-preflight is Green.

## C2 Architecture Focused Checkpoint

At `2026-09-01T10:12:35+09:00`, the bounded architecture action changed only
the three authorized C2 architecture tests:
`tests/Architecture/AuditOutboxStorageArchitectureTest.php` was added, and
`tests/Architecture/AuditOutboxStateArchitectureTest.php` plus
`tests/Architecture/AuditRecordArchitectureTest.php` were updated for the
exact C2 Outbox inventory and call graph. The unchanged B2
`AuditStoreArchitectureTest.php` and public API guards were not modified.

Focused Mago format is PASS. The focused C2 PHPUnit command is PASS at
`24 tests`, `26,412 assertions`, with one existing warning. The requested
six-file PHPUnit command ran `42 tests`, `37,261 assertions`, with one failure:
unchanged `AuditStoreArchitectureTest::testB1ProtectedCallGraphRejectsIndirectCallsAcrossAllSource`
still requires empty `protectOutbox` and `unprotectOutbox` callsite maps, while
the authorized C2 Store has one callsite for each. This is recorded as a
compatibility blocker; the unchanged guard was not edited.

The three architecture file SHA-256 values are:

- `tests/Architecture/AuditOutboxStorageArchitectureTest.php`:
  `4855941d5bc230925064dd8fa826d4d8fbc4bb988f4c08ce52331acac04b9d25`
- `tests/Architecture/AuditOutboxStateArchitectureTest.php`:
  `98b2ac6ec8c6b6fba474fa6b39e37a377bbb436f02699a8dd24c7f8df8bea9a7`
- `tests/Architecture/AuditRecordArchitectureTest.php`:
  `bc4caf343fc1c8aac0dab14913612583470a01ece5d15604d409b049e6014a5b`

Immutable exact7 remains byte-identical with ordered aggregate
`96155a87e12e61783cb15f30e7dc7bacbb9e5a7cbcad6b3380a2e03e72974845` and
staged0 remains. No stage, commit, push, network, deployment, or Complete
Final Gate occurred.

## PostgreSQL Outbox Store Real Concurrency and Failure Checkpoint

At `2026-09-01T09:51:20+09:00`, the bounded Store evidence batch changed only
`tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxStoreTest.php` and the
concrete rollback-uncertainty branch in
`src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php`. The test now covers
two-Connection same-key and different-key append races with contiguous
ordinals, idempotent retry, no deadlock leak, same-head claim with one winner,
partition-before-record advisory lock order with unrelated-tenant progress,
caller-owned late count-fence abort, owned rollback, rollback quarantine and
reconnect, retained-boundary state-ahead/state-behind/interior/head-tenant
tampering before resource work, and persisted protected-body/tenant tampering
with before-after equality. Child processes use readiness files, bounded lock
polls, timeout, SIGKILL, reap, and cleanup paths.

The focused Mago format check is PASS for the Store and Store test. The
focused PHPUnit suite is PASS: `25 tests, 783 assertions`. The test SHA-256 is
`d4b131ce0e70ee0e53ccc8997142e2c51445cc8369223ac75a1449fe3d5cc33c`; the
Store SHA-256 is
`d9cd8d4cde6d943904c37390980739575cccd884e75648aa21ffe219f87af1c3`.
The Store delta is limited to constructing the fixed storage failure before a
failed owned rollback is attempted, preventing the active rollback throwable
from entering the public exception's trace while preserving quarantine and
`StorageUnavailable` mapping. The lock-key correction and rollback canary
callback are test-only. An initial sandbox Docker-socket denial was retried
through the approved escalation route; the focused checks then completed.
No other Product or Test path changed; staged0 remains, immutable exact7
remains byte-identical, and no stage, commit, push, network, deployment, or
Complete Final Gate occurred.

## PostgreSQL Outbox Store Read and Transition Test Checkpoint

At `2026-09-01T09:15:50+09:00`, only
`tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxStoreTest.php` and the
Store's safe-failure mapping changed in this bounded batch. The test now
covers exact record／partition／tenant find predicates without transaction
control, not-found and tenant-before-key paths, one-Clock due selection and
head recheck, deterministic global FIFO order, same-partition lower-ordinal
blocking without `SKIP LOCKED`, microsecond recovery expiry boundaries,
retry-versus-poison at the attempt cap, relay／poison lease clearing and next
head selection, wrong state／tenant／partition／version／token, CAS affected
`0`／`2`, the six mutable CAS columns, and no-leak exception canaries. The
real PostgreSQL case also round-trips the protected binary record through a
second non-UTC connection and serially relays the first head before claiming
the next ordinal.

Focused Mago format is PASS. Focused PHPUnit is PASS: `18 tests, 612
assertions`. The test SHA-256 is
`0b74ec9dc6aea683a83e79f99cc2cdc4173f26af037a815639d4ac77927b7e98`.
The Store SHA-256 is
`9fa14a3bb647aa24be0f5f9743a3dca2858f5e12a4b2909266586cb2b11badd5`.
The Store delta is limited to `safeFailure()`: it classifies the throwable
before clearing it and constructs the fixed failure without retaining the raw
throwable in public exception trace／inspection surfaces. No other Product or
Test path changed; staged0 remains, immutable exact7 remains byte-identical,
and no stage, commit, push, network, deployment, or Complete Final Gate
occurred.

## PostgreSQL Outbox Store Test Batch Checkpoint

At `2026-09-01T09:02:28+09:00`, only
`tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxStoreTest.php` was added in
this bounded batch. It covers the frozen Store construction/input and
transaction boundary, owned and caller-level-one append, system and tenant
ordinal/high-water behavior, authenticated same-key idempotency with zero new
resource work, key/hash/raw-key/Draft/tenant/partition/content/body/ID
mismatches, retained aggregate corruption, generated-ID collision ordering,
capacity, quarantine, count-fence, and no-leak canaries. The test also runs a
real PostgreSQL system/tenant append roundtrip. Focused Mago format and
PHPUnit are PASS: `11 tests, 359 assertions`. The test SHA-256 is
`89208dfc0c3b4ebbfc8f05b8d21858fb23d3b173c32807c3baa10b7cd13c8ecb`.

The test exposed two concrete Store defects, both corrected only in
`src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php`: missing-state
orphan probing and strict PHP-array comparison rejecting a semantically
identical authenticated envelope whose nested key order was canonicalized on
decode. The Store SHA-256 is now
`0fde0fe9a7bd212ac0a6116789e5afa2d990d4623456dd0e08ef820c8b25e4e0`.
No other Product/Test path changed; no stage, commit, push, network,
deployment, or Complete Final Gate occurred.

## PostgreSQL Outbox Store Batch Checkpoint

At `2026-09-01T08:47:02+09:00`, only
`src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php` was implemented in
this bounded batch. The Store now exposes the frozen constructor and public
operations, owns the single configuration and transition, enforces the
transaction and lock order, validates the retained aggregate, performs
authenticated idempotent append resolution, uses the count-fenced append
statement, selects FIFO heads, authenticates before C1 transitions, and
applies six-column CAS mutations with safe failure mapping. Focused Mago format
check is PASS. The Store SHA-256 is
`04a5a5a665fd236b5fa2d8811a5a5903dea6b03dac00d74bef63e19980f12669`.
No other Product/Test path changed in this batch; no stage, commit, push,
network, deployment, or Complete Final Gate occurred.

## PostgreSQL Outbox Schema Test Batch Checkpoint

At `2026-09-01T08:30:23+09:00`, only
`tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchemaTest.php` was added in
this bounded batch. It covers pre-SQL schema-name rejection, exact five
statement order, both table column order/type/null/default metadata, all
named constraints and explicit indexes, unexpected catalog objects, the
Accepted Cc/Cf inventory, U+2028/U+2029 positives, isolated U+200E
constraint negatives, state/UUID/hash/ordinal/attempt/body/version/FK/
uniqueness SQLSTATE mutations with before/after snapshots, and NUL body plus
UTC rendering across a second non-UTC PostgreSQL connection. Focused Mago
format is PASS and focused PHPUnit is PASS: `7 tests, 443 assertions`.
The focused test SHA-256 is
`e0a0a03013d18962e3ccfd0a98f33bcb9356539eff80a4a4d0d6bd40119ef067`.
No stage, commit, push, network, deployment, or Complete Final Gate occurred.

## Luna Takeover Checkpoint

At `2026-09-01T07:34:52+09:00`, the replacement configured Luna Max worker
verified the mandated branch／HEAD, exact7 immutable manifest and aggregate,
and staged0. The only moving paths are the eight preserved in-scope
`src/Internal/Audit/Outbox` files plus this Report and `develop/STATE.md`.
No immutable or outside path drift was found. The partial Product files are
being audited and completed in Task order; no commit, stage, push, network,
runtime activation, or Complete Final Gate has occurred.

## Worker Start Checkpoint

At `2026-09-01T07:12:59+09:00`, the configured Luna worker verified the
fail-closed dispatch freeze before Product/Test edits: branch
`agent/p23-001-blackops-1-3-feasibility`, HEAD
`fd7671b87a2e498a8b45d7f863b0e341a5a73f70`, clean worktree, and staged index
`0`. The second-corrected General, PostgreSQL/DB, and Documentation/Release
preflight lanes are separately Green (`0/0/0` each). The exact7 immutable
manifest matches all supplied hashes and ordered aggregate
`96155a87e12e61783cb15f30e7dc7bacbb9e5a7cbcad6b3380a2e03e72974845`.
Starting moving hashes matched Report
`4d2b0820e5f429e124a88dacbaf91b585417afb72f8196f4293227c7359a7b27` and
STATE `88e086ab2c06c7af6cd935045aca19f34ec55a489617c9c708c4213992878884`.
No Product/Test file had changed at this checkpoint; implementation is now
authorized only within exact27 plus this Report and `develop/STATE.md`.

## Summary

The Orchestrator completed read-only C2 authority／source research from clean
HEAD `4bebcb09d8e67e58c424d0c9543e594708d02872`, drafted D151 plus the exact
P23-003C2 Task Packet, and routed three independent preflight lanes.

Those first lanes are Historical Changes Requested and are not combined:
General／API／Architecture／Package returned `P1=3/P2=2/P3=0`, PostgreSQL／DB
returned `P1=4/P2=0/P3=0`, and Documentation／Release returned
`P1=2/P2=2/P3=1`. D151 and the Task now close the overlapping roots before a
fresh review: one configuration source, complete DDL, retained-row boundary,
generated-ID lock／probe order, one Clock order, exact public callable
authority, exact54 package manifest, correct model profiles, and a runnable
clean-HEAD scope gate.

D151 fixes the exact Internal Outbox namespace, fifteen-field row, two-table
PostgreSQL schema, migration version, protected and full-row hydration,
same-key authenticated idempotency, no-gap ordinal allocation, minimum-head
FIFO selection, C1-backed fenced CAS, transaction participation, failure
precedence, Architecture boundary, package inventory, and unreleased boundary.

The preserved worker partial now contains the bounded C2 Production,
migration, direct tests, and three C2 Architecture tests. Focused Product and
C2 Architecture checks are Green at the checkpoints above. Consumer／README／
runtime／Application／Container／CLI／HTTP／Guide／Website／generated-artifact／
deployment bytes remain unchanged. Product work is paused at the first broader
Architecture compatibility failure; prior Green evidence is not promoted to
an Iteration or Final Gate.

The fresh review of committed packet
`26527296da112e315c6a332d26282f52625bb7a7` is now Historical and remains
separate by lane: General `P1=1/P2=0/P3=0`, PostgreSQL／DB
`P1=1/P2=0/P3=0`, Documentation／Release `P1=0/P2=0/P3=0`. General proved
that the migration 12→13 change also requires the current
`DatabaseMigrationCommandTest`, which was outside exact26. DB proved that
`count／min／max` alone accepts an interior retained row whose tenant pair does
not match the locked state. This second bounded management correction adds
that test to exact27 and fixes the retained aggregate at
`row_count／min／max／tenant_mismatch_count`; no Product byte is changed.

## Changed Files

Orchestrator management／normative drafting and the preserved Luna partial:

- `develop/decisions/151-audit-postgresql-outbox-persistence-boundary.md`
- `develop/orchestration/tasks/P23-003C2-audit-postgresql-outbox-persistence.md`
- `develop/orchestration/reports/P23-003C2-audit-postgresql-outbox-persistence.md`
- `develop/STATE.md`
- `develop/TODO.md`
- `develop/decisions/150-audit-outbox-state-and-delivery-split.md`
- `develop/spec/105-blackops-1-3-capability-contract.md`
- `develop/spec/106-phase-23-delivery-plan.md`
- `develop/spec/README.md`

- `src/Internal/Audit/Outbox/AuditOutboxStoreFailure.php`
- `src/Internal/Audit/Outbox/AuditOutboxStoreException.php`
- `src/Internal/Audit/Outbox/AuditOutboxRecord.php`
- `src/Internal/Audit/Outbox/AuditOutboxAppendResult.php`
- `src/Internal/Audit/Outbox/AuditOutboxLookupResult.php`
- `src/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasher.php`
- `src/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydrator.php`
- `src/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydrator.php`
- `src/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchema.php`
- `src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php`
- `migrations/postgresql/Version20260901000000.php`
- `tests/Internal/Audit/Outbox/AuditOutboxValueTypesTest.php`
- `tests/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasherTest.php`
- `tests/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydratorTest.php`
- `tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydratorTest.php`
- `tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchemaTest.php`
- `tests/Internal/Audit/Outbox/PostgreSqlAuditOutboxStoreTest.php`
- `tests/Architecture/AuditOutboxStorageArchitectureTest.php`
- `tests/Architecture/AuditOutboxStateArchitectureTest.php`
- `tests/Architecture/AuditRecordArchitectureTest.php`

## Decisions and Assumptions

- C2 uses a new `BlackOps\Internal\Audit\Outbox` namespace, preserving the
  Accepted C1 Delivery and B2 Storage Production inventories.
- Existing-key resolution occurs before Clock, UUID, active key, nonce,
  encryption, capacity, or mutation.
- One Store-owned `AuditOutboxConfiguration` constructs the only transition,
  is passed into every full-row hydration, and creates initial pending state;
  no hidden second configuration is retained.
- A same-key retry is authenticated by rebuilding the incoming Draft with the
  stored record ID／recordedAt and comparing the exact envelope.
- A new append proves the retained-row boundary, captures one Clock, generates
  one record UUID, takes its record lock, proves store-wide absence, allocates
  one ordinal, protects once, and commits state／row through one count-fenced
  CTE.
- FIFO selects only the minimum pending／leased ordinal in a partition.
  Same-partition `SKIP LOCKED` is forbidden. Poison is acknowledged.
- C2 supports owned root level 0 and joins caller-owned active level 1. C3
  later owns the Application-facing same-root orchestration and key composition.
- C4 later owns the active relay loop; C2 only provides persistent operations.
- Framework migration count will become 13, Community total 18, and required
  package inventory exact54 only after implementation.
- Physical hydrators validate shape only; the Store-owned partition deriver
  binds hydrated tenant to clear／requested partition before decrypt.
- Current migration-command counts move framework-only 12→13 and
  Framework-plus-one-Application 13→14 inside exact27 scope.

## Commands and Results

Read-only research completed:

- branch／HEAD／worktree inspection: PASS; clean HEAD `4bebcb09...2872`
- current D145／D148／D149／D150 and Specification 105／106 contract scan: PASS
- current C1／B1／B2 source, migration, Architecture, package, and consumer
  inventory scan: PASS
- three bounded Luna read-only research lanes: completed; no edits
- initial independent preflight lanes: Historical Changes Requested with the
  separate counts recorded above; no Product edits or command execution
- clean-commit fresh lanes: Historical General `P1=1/P2=0/P3=0`, DB
  `P1=1/P2=0/P3=0`, Documentation `P1=0/P2=0/P3=0`; read-only, no Product
  edits or command execution
- repository profile check: PASS (`sol/max`, `luna/max`, `sol/xhigh`)
- corrected Task all fenced Bash blocks extracted and parsed by `bash -n`: PASS
- D151 exact ordered package manifest count: PASS (`54`)
- corrected exact-nine worktree path set: PASS
- `git diff --check`: PASS

Not Run or not yet complete for the current corrected candidate:

- repository-wide lint, analysis, PHPUnit, and Deptrac
- complete Iteration and Final Gates
- Community Board clean install
- package export
- generated Documentation／browser verification
- push, release, deploy

## Acceptance Criteria

- [x] C2 unresolved physical／callable／transaction boundaries identified.
- [x] D151 drafted with one implementation-unique contract.
- [x] Exact P23-003C2 Files Allowed and required fixtures drafted.
- [x] Stable `1.2`／unreleased `1.3` and no-partial-activation boundary retained.
- [x] Initial preflight findings corrected in Decision／Task／management scope.
- [x] First clean-commit re-preflight findings corrected in Decision／Task／
  management scope.
- [x] Corrected nine-file management packet committed by the Orchestrator;
  exact self-commit SHA is supplied after commit in the preflight dispatch.
- [x] Second bounded nine-file management correction is this commit checkpoint;
  its self-commit SHA is likewise supplied only after commit.
- [x] Second-corrected General／DB／Documentation preflight Green separately;
  Historical after the Architecture-scope correction.
- [ ] Corrected-scope fresh General／DB／Documentation preflight Green.
- [ ] Luna implementation and Iteration Gate Green.
- [ ] Complete Final Gate Green.
- [ ] Fresh independent implementation reviews Green.
- [ ] P23-003C2 Acceptance, candidate commit, and clean same-SHA package export.

## Remaining Issues

- Fresh re-preflight remains pending from the bounded Architecture-scope
  correction freeze.
- Product implementation is preserved but paused; remaining inventory／
  migration-count／package／Community updates and full gates remain pending.
- C3, C4, and parent P23-003C remain pending after C2.
- Generated Documentation artifacts and Browser remain Not Verified and are not
  C2 acceptance surfaces.

## Suggested Next Action

Dispatch only the three DB Product／fixture corrections and exact Architecture
map to the configured Luna worker. Then refreeze, run focused real-PostgreSQL
and Architecture checks, and obtain fresh DB／General implementation review.
