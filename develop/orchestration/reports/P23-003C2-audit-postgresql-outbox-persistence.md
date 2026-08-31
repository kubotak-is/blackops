# P23-003C2 Report: Audit PostgreSQL Outbox Persistence

Status: Second Corrected Task Packet Commit Checkpoint — Fresh re-preflight required; Product work not started

Updated At: 2026-09-01T06:39:35+09:00

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

No Product, Test, migration, consumer, README, runtime, Application, Container,
CLI, HTTP, Guide, Website, generated artifact, or deployment byte has been
changed for C2. This commit establishes the exact nine-file corrected
management packet; its exact SHA is captured after commit and supplied in the
fresh re-preflight dispatch. Product work waits for Green from that clean HEAD.

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

Orchestrator management／normative drafting only:

- `develop/decisions/151-audit-postgresql-outbox-persistence-boundary.md`
- `develop/orchestration/tasks/P23-003C2-audit-postgresql-outbox-persistence.md`
- `develop/orchestration/reports/P23-003C2-audit-postgresql-outbox-persistence.md`
- `develop/STATE.md`
- `develop/TODO.md`
- `develop/decisions/150-audit-outbox-state-and-delivery-split.md`
- `develop/spec/105-blackops-1-3-capability-contract.md`
- `develop/spec/106-phase-23-delivery-plan.md`
- `develop/spec/README.md`

No Product／Test file is changed at this checkpoint.

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

Not Run because Product work has not started:

- format, lint, analysis, PHPUnit, Deptrac
- PostgreSQL integration／concurrency tests
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
- [ ] Fresh Sol preflight Green.
- [ ] Luna implementation and Iteration Gate Green.
- [ ] Complete Final Gate Green.
- [ ] Fresh independent implementation reviews Green.
- [ ] P23-003C2 Acceptance, candidate commit, and clean same-SHA package export.

## Remaining Issues

- Fresh re-preflight remains pending from the second corrected-packet commit.
- Product implementation and all implementation evidence remain pending.
- C3, C4, and parent P23-003C remain pending after C2.
- Generated Documentation artifacts and Browser remain Not Verified and are not
  C2 acceptance surfaces.

## Suggested Next Action

Capture the second corrected-packet commit SHA, verify a clean worktree, and
run fresh independent Sol／Documentation re-preflight from that exact commit.
Do not dispatch Product work until every lane is Green.
