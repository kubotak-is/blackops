# D170: Observer Replay Transaction Outcomes

Status: Decided — D5O1 Locally Accepted; BlackOps1.3 unreleased

## Decision

Split D154's Observer replay child into P23-003D5O1 native transaction outcomes
followed by P23-003D5O2 phase-adapter authority and implementation. Adopt
[spec120](../spec/120-observer-replay-transaction-outcomes.md) for D5O1.
It consumes existing replay only; no C3 helper, hook, key composition or Catalog
change is introduced. Other D/E/F/control/G and release dependencies are unchanged.

`ObserverReplayRuntime::replay` currently catches every `deliver` exception and
calls `fail`, while `deliver` includes cursor advancement. A real advance
commit-then-throw can therefore convert committed progress into a persisted
failure and later an untruthful failed phase event. The native Store also rolls
back unconditionally after commit errors and acquires its session lock before
an unprotected `beginTransaction`. Fix these bounded native edges first.

Keep the four existing roots and current failure persistence for actual
selection/projection/Observer/flush failures. Cursor/advance errors, including
precommit errors, instead stop and release the lock without failure finalization.
Preserve bounded Runtime output for this newly separated path with a fresh
fixed-message `JournalObservationFailed` and no previous cause. Guard mutation
entry at auto-commit/level0, clean up only active roots, and cover transaction
begin failure after lock acquisition. No error category registry is needed.

## Alternatives and Limits

- Append events without correcting Runtime: a database commit exception can
  produce a misleading persisted failed phase after committed progress.
- Treat inactive DBAL state as success or rollback: neither establishes the
  underlying commit result and it can trigger incorrect automatic continuation.
- Add a whole-invocation root or new recovery table: breaks partial progress and
  cannot roll back already delivered external Observer effects.
- Add a new exception hierarchy to retain precommit failure finalization:
  unnecessary for the selected conservative cursor-persistence boundary.

Explicit resume and original Journal Record IDs preserve existing at-least-once
delivery. Old started invocation rows stay started; D154 has no interrupted or
requested replay definition. A paused checkpoint is not a succeeded replay.
Later D5O2 must define trusted current execution, raw selected-row tenant,
administrative scope, identities, clocks/keys and same-root append separately.
No inherited all-Throwable CLI redaction or driver recovery guarantee is added.

The [Task](../orchestration/tasks/P23-003D5O1-observer-replay-transaction-outcomes.md)
allows exactly two Product files and one new real-DB fixture. Independent authority
Review precedes implementation; source Review and a frozen full/static gate
precede Acceptance. The parent [release Task](../orchestration/tasks/P23-006-blackops-1-3-release.md)
owns later reviewed commit integration/publication. Stable1.2.1 remains current.
