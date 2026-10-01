# Specification 120: Observer Replay Transaction Outcomes

Status: Locally Accepted — native implementation and evidence complete; BlackOps1.3 unreleased

## Boundary

This is D154's Observer replay native prerequisite, P23-003D5O1, under
specifications80/99/105/106 and [D170](../decisions/170-observer-replay-transaction-outcomes.md).
D5O2 will separately define the four existing `replay.observer.*` events.
This prerequisite adds no event, hook, schema, key, public API or C3 caller.

Change only `PostgreSqlObserverReplayStore` and `ObserverReplayRuntime` plus
their dedicated regression fixture. Preserve selectors/cursors, raw canonical
Journal records, current Sensitive projection, target bindings, advisory-lock
identity, checkpoint/native audit schema, SQL predicates, counts, telemetry and
ordinary command/result surface. The change affects native replay in the
unreleased development tree, not published Stable1.2.1.

## Native roots and admission

The four existing mutation methods `begin`, `advance`, `finishInvocation` and
`fail` own separate native roots. Each must reject auto-commit disabled, an active
transaction, or nesting level other than0 before clock capture, SQL, advisory-lock
acquisition, entropy or mutation. A fixed value-free RuntimeException is enough;
do not add an exception family. Rejection must neither commit/roll back the
caller's root nor release a lock that this call has not acquired.
Read-only `select`/`load` and public `unlock` retain their existing behavior.

Once `begin` acquires its session advisory lock, its `beginTransaction()` belongs
inside the existing cleanup boundary. A begin failure must attempt active-root
rollback and then unlock even if rollback itself fails. All four mutation methods
roll back only when their Connection still reports an active transaction.
Preserve the existing bounded rollback/unlock wrappers and propagation precedence;
exact Throwable identity and driver Connection recovery are not promised.

No invocation-wide transaction is introduced. Earlier roots, Observer effects,
telemetry and session locks are not undone by a later database rollback.
Inactive DBAL bookkeeping after a commit exception is not proof of persistence
or rollback. Never infer a commit result, retry commit or return synthetic success.

## Runtime failure ordering

| Phase | Required behavior |
| --- | --- |
| Begin/admission fails, including commit-then-throw | Store cleanup runs; Runtime does not select, deliver, fail or finalize that invocation |
| Selection/decryption/projection/observe/flush fails | Preserve one native `fail` attempt, no later record or normal finalization, and fresh bounded `JournalObservationFailed` without original cause |
| Cursor preparation or `advance` fails before/during commit | Stop immediately, attempt Runtime lock release, do not call `fail`, normal finalization, or any later Observer; emit a fresh fixed-message `JournalObservationFailed` without previous cause |
| Normal complete/paused finalization fails | Preserve one finalization attempt and existing lock-release/exception behavior; no second finalization or `fail` |
| Native `fail` persistence fails, including commit-then-throw | Do not retry or finalize; preserve the existing safe failure result and attempt lock release |

Separate the delivery/selection failure catch from cursor/advance persistence.
The latter includes precommit SQL errors: preserving a running checkpoint and
requiring explicit resume is intentional. A new error-classification type or
transaction phase registry is unnecessary for this boundary.
The outer Runtime must not reclassify a persistence exception as an Observer
failure. Successful per-record IDs/counts remain unchanged.

The existing replay CLI does not have rotation's storage-error wrapper. Therefore
the newly separated cursor/advance path must remain value-free at Runtime:
use the existing `JournalObservationFailed` type with one fixed message and no
previous Throwable. This preserves safe output for a path previously normalized
by `recordFailure`. Direct Store callers retain native exceptions. Inherited
begin/finalize/unlock exceptions and general CLI exception rendering are not
newly redacted by this child; do not claim all-Throwable output safety.

## Persisted outcome and resume meaning

A commit exception remains unknown even when a fixture proves the actual effect.
After real commit-then-throw, `begin` may have persisted running/started rows,
`advance` may have persisted cursor/counts, `finishInvocation` may have persisted
paused-or-complete checkpoint plus complete invocation audit, and `fail` may have
persisted failed state. Do not overwrite those results with a second mutation.

Explicit resume uses existing persisted cursor and creates a new invocation audit
ID. Old started audit rows remain unchanged; no interrupted event or backfill is
invented. A paused checkpoint is not replay completion even though its invocation
audit says complete. Observer effects precede cursor persistence and remain
at-least-once: an unadvanced record may be delivered again under its original
Journal Record ID. This child does not add exactly-once or automatic recovery.

## Verification

Use real PostgreSQL with fault-injecting Connection wrappers, actual
`parent::commit()` before the after-effect exception, and separate observer
Connections. Prove both persisted checkpoint and invocation-audit rows, unchanged
canonical bytes, no later work and lock availability. Required cases cover:

- all four mutation entries with ambient/nested roots and auto-commit disabled,
  zero extra SQL/root work and preservation of caller state;
- beginTransaction before/after-effect faults after advisory acquisition, begin
  precommit/commit-before-effect/commit-after-effect and cleanup/unlock attempts;
- first/later advance SQL and commit faults, preserving prior roots, no synthetic
  failed state or finalization, fixed value-free Runtime error and explicit resume;
- complete and paused finalization plus persisted Observer failure, each with
  commit-before/after-effect, active-only rollback and no second mutation;
- existing selection/Observer/flush failure behavior, successful replay,
  read-only dry-run, stable selector/target resume and at-least-once Record IDs.

The frozen full suite covers the existing Store, Runtime, CLI and projection
fixtures; no dedicated replay Consumer script exists. Required final gates are
PHP format/identifier scan, current lint/baseline/analyzer/architecture checks,
full PHPUnit at unchanged512M and independent transaction/security Review.
No package inventory/export or Website rerun is selected for unchanged public
sources, signatures and package paths. Final release qualification remains006.
