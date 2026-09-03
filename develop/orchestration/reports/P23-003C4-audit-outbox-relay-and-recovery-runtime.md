# P23-003C4 Report: Audit Outbox Relay and Recovery Runtime

Status: Authority handoff correction committed — corrected clean-freeze re-preflight pending; Product work not started

Updated At: 2026-09-04T02:02:26+09:00

## Summary

P23-003C1, P23-003C2, and P23-003C3 are Accepted.
The reviewed C3 candidate is committed at
`b7b352a8e4c86a45822351c108772367515654bd`, and its clean same-SHA
Git／Composer package export is Green.
No push was performed.

The first C4 DB and Documentation preflight returned authority-only Changes
Requested before Product work.
It identified an undefined bridge between the protected Outbox row and the
canonical Store, missing transaction ownership and sender／settlement failure
rules, undefined final-attempt append-before-ack semantics, contradictory
poison FIFO wording, stale C3 current handoffs, and a missing C4 release
ledger.

D153 and the C4 Task now close those roots.
They define an exact seven-type Internal relay graph, a direct-test persistence
adapter, one protected-ingress canonicalizer, bounded `recoverOne()` and
`relayOne()` entries, three separate Store-owned transactions, a complete
failure matrix, conservative final-attempt poison semantics, poison FIFO
release, exact75 package inventory, and zero current activation.

The first corrected Transaction／PostgreSQL preflight is Green. The parallel
Documentation／Release preflight found one current-truth P1 before Product
work: the immutable C1／C2 Reports still exposed pre-C3 Remaining／Next Action
handoffs as current. Their current-facing closeouts now state C1／C2／C3
Accepted and C4 current; prior checkpoints remain under Historical headings.

No Product or Test file has changed.
The resulting exact13 management packet is committed at the current
Orchestrator HEAD. The next step is a clean exact immutable refreeze followed
by fresh General and Documentation closure plus a bounded Transaction freeze
confirmation.

## Changed Files

This authority correction changes exactly thirteen management, specification,
Decision, Task, and Report files:

- `develop/STATE.md`
- `develop/TODO.md`
- `develop/decisions/150-audit-outbox-state-and-delivery-split.md`
- `develop/decisions/151-audit-postgresql-outbox-persistence-boundary.md`
- `develop/decisions/152-audit-delivery-orchestration-and-key-composition-boundary.md`
- `develop/decisions/153-audit-outbox-relay-and-recovery-runtime-boundary.md`
- `develop/orchestration/tasks/P23-003C4-audit-outbox-relay-and-recovery-runtime.md`
- `develop/orchestration/reports/P23-003C1-audit-outbox-state-foundation.md`
- `develop/orchestration/reports/P23-003C2-audit-postgresql-outbox-persistence.md`
- this Report
- `develop/spec/105-blackops-1-3-capability-contract.md`
- `develop/spec/106-phase-23-delivery-plan.md`
- `develop/spec/README.md`

No Product, Test, package script, migration, public Documentation, Community
Board, generated artifact, or release file has changed.

## Decisions and Assumptions

- C4 exposes bounded Internal `recoverOne()` and `relayOne()` seams, not an
  unbounded loop, daemon, scheduler, worker, command, route, or registration.
- Claim, canonical append, and relay acknowledgement remain three separately
  committed Store-owned roots.
- A dedicated Internal persistence interface and PostgreSQL adapter make every
  failure branch directly testable without changing Accepted C1／C2 Store APIs.
- The claimed protected ingress is deliberately opened again after the claim
  transaction commits, because C2 authenticates before CAS but returns only the
  protected record.
- Canonical `resource_unavailable` and `storage_unavailable`, plus protection
  failure, are retryable below max; idempotency, segment, integrity, validation,
  and committed-result mismatch poison immediately.
- An unexpected throwable leaves the lease untouched and contracts to fixed
  `storage_unavailable`.
- A final-attempt recovered poison may already have a committed canonical row;
  no C1／C2 reconciliation API is added.
- Both terminal states acknowledge the current ordinal and release the next.
  Earlier persistent-partition poison wording is superseded.
- The Stable generic Outbox runtime and `outbox:relay:*` commands remain
  byte-identical and are not Audit relay activation.
- Stable `1.2.0` remains current; BlackOps `1.3` remains unreleased.

## Commands and Results

### Authority research and initial preflight

- `git status --short`, `git branch --show-current`, and `git rev-parse HEAD`
  confirmed the clean accepted C3 starting point on branch
  `agent/p23-001-blackops-1-3-feasibility` at
  `b7b352a8e4c86a45822351c108772367515654bd`, staged0.
- Read-only source and authority inspection confirmed the Accepted Outbox
  transition API, canonical durable append API, protection decode seam,
  configuration identity, package exact68 baseline, Framework migration13,
  Community total18, and zero C3 runtime caller.
- Fresh Transaction／PostgreSQL preflight returned authority Changes Requested
  with two P1 candidates and one P2 candidate.
- Fresh Documentation／Release preflight returned authority Changes Requested
  with two P1 candidates and one P2 candidate.
- `git diff --check`, initial exact11 status, Product／Test delta0, Files Allowed
  exact23, worker exact25, and immutable exact12 static guards passed.
- `git commit -m "docs(audit): define C4 relay runtime boundary"` exited 0;
  the truth-only checkpoint and C1／C2 current-handoff correction were folded
  into that same management commit.
- The first corrected Transaction／PostgreSQL preflight returned Green
  `P1=0／P2=0／P3=0` on the original clean immutable freeze.
- The first corrected Documentation／Release preflight returned Changes
  Requested `P1=1／P2=0／P3=0` solely for stale current-facing C1／C2 Report
  handoffs. Product work remained stopped and those handoffs were corrected.
- No test, build, Docker, package export, browser, network, stage, commit, push,
  release, or deploy command was run during that read-only preflight.

### Not Run

- corrected fresh preflight
- configured Luna implementation
- focused checks and Iteration Gate
- implementation reviews and unchanged refreeze
- Complete Final Gate
- Acceptance and candidate commit
- clean same-SHA package export
- push, release, deploy, publication, generated artifact, browser, and CI

Generated artifacts and browser behavior are Not Verified.

## Acceptance Criteria

- [x] Accepted C3 commit and clean same-SHA package baseline identified
- [x] initial C4 DB and Documentation preflight completed before Product work
- [x] relay bridge and exact Connection／Store identity defined
- [x] sender and settlement failure matrix defined
- [x] final-attempt append-before-ack meaning defined
- [x] poison FIFO meaning aligned with Accepted C1／C2
- [x] bounded nonactivation and Stable generic Outbox separation defined
- [x] exact23 Product／Test, exact25 worker, immutable exact12, and package
  exact75 targets drafted
- [x] management packet final static review
- [x] management-only commit and clean immutable freeze
- [x] first corrected Transaction／PostgreSQL preflight Green
- [x] first corrected Documentation／Release preflight stopped on stale C1／C2
  current handoffs
- [x] stale C1／C2 handoffs corrected before Product work
- [ ] fresh three-lane preflight Green
- [ ] configured Luna exact25 implementation
- [ ] Iteration Gate Green
- [ ] fresh implementation reviews and unchanged refreeze
- [ ] Complete Final Gate Green
- [ ] P23-003C4 and parent P23-003C Acceptance
- [ ] candidate commit and clean same-SHA package export

## Remaining Issues

No Product implementation is authorized yet.
The corrected C1／C2 handoff bytes require a new immutable exact12 freeze and
fresh closure review before worker dispatch.

Within the larger BlackOps 1.3 Audit roadmap, P23-003D through P23-003G,
P23-004, P23-005, P23-006, release, runtime activation, generated artifacts,
and browser verification remain downstream.

## Suggested Next Action

From the corrected committed management packet, obtain fresh General and
Documentation／Release closure plus a bounded Transaction freeze confirmation
before dispatching the configured Luna worker.
