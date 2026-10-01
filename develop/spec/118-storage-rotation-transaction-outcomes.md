# Specification 118: Storage Rotation Transaction Outcomes

Status: Accepted (local) — D5R1 source/evidence Green; BlackOps 1.3 unreleased

## Boundary

This is the native prerequisite for D154's storage-rotation source child,
under specifications99/105/106 and [D168](../decisions/168-storage-rotation-transaction-outcomes.md).
It fixes transaction failure ordering before a separate D5R2 defines and implements
the four `rotation.storage.*` adapters. It emits no new AuditRecord, adds no hook,
allocator or schema, and does not activate C3 or change its callers.

Change only `PostgreSqlStorageProtectionRotationRowTransaction` and
`PostgreSqlStorageProtectionRotationBatch`. Preserve their signatures and concrete
composition. Native Audit, queries, columns, CAS, tenant predicates, checkpoint
identity, advisory lock, CLI, key provider, Catalog and all earlier D adapters
remain unchanged. This correction affects ordinary native rotation in the
unreleased development tree; it is not an opt-in adapter or a Stable release.

## Transaction outcome rules

The native graph retains independent begin, per-row, and finish roots. There is
no transaction covering the whole invocation. Database rollback does not undo
earlier roots, key-provider calls, entropy, telemetry or session lock effects.
These guarantees concern ordinary auto-commit entry; ambient/nested admission
is not added here and must be fixed by the later audited facade.

| Failure point | Required behavior |
| --- | --- |
| Native begin | Preserve current cleanup/propagation. Do not process rows or finalize when begin throws, including actual commit-then-throw. |
| Column/CAS/protection failure before a row commit attempt | Preserve native per-row rollback and failed-row result. The existing batch can persist its failure in a later finish root. |
| Cursor/count advancement before a row commit attempt | Preserve rollback and failed-row result, including existing count semantics. |
| Row `commit()` throws | Best-effort rollback if the Connection is still active, then propagate. Do not convert to failed-row counts, execute later rows, scan remaining data, or finalize in that invocation. |
| Remaining scan before finalization throws | Preserve current one best-effort failure finalization for confirmed rotation, then propagate the original scan exception. A failure-finalization exception remains suppressed by the existing helper. Plan remains read-only. |
| Normal finalization SQL or `commit()` throws | Invoke native `finish()` only once. Its existing cleanup/propagation runs; do not call `finishFailure()` afterward, regardless of transaction activity or whether commit took effect. |

The row commit boundary must be outside the catch that translates advancement
failure to a failed-row result. Cleanup is conditional on active transaction;
if cleanup itself throws, a failure still escapes and no synthetic result or
finalization is permitted. Exact Throwable identity during rollback or Engine
unlock failure is not promised. Do not add a new exception type, attach source
data, suppress commit failure, retry commit or infer success from nesting level.
DBAL changes its nesting state even when a driver commit throws; inactive DBAL
state is not proof of an underlying rollback or recovered Connection. No new
Connection close/recovery policy is introduced.

A commit exception is an unknown outcome, even when a test driver demonstrably
throws before committing. A real commit-then-throw can leave ciphertext,
cursor/counts and the started native audit persisted. A terminal commit-then-throw
can leave complete or failed terminal rows persisted. Returning a storage failure
must not rewrite that committed state. A precommit finalization failure can leave
the preceding started audit intact; it no longer triggers a second finalization.

No automatic recovery or reconciliation is added. A subsequent explicit rotation
invocation retains native checkpoint resume and interrupted-started-audit handling.
It must not re-encrypt rows already advanced to the new key. Native `complete`
still concerns the batch/cursor contract; it is not proof that all old keys are
absent from every scope, replica, backup or retention window.

## Response and safety

Successful, dry-run, ordinary failed-row and input-error behavior stay unchanged.
An unknown row commit outcome propagates instead of returning invented row-failure
counts. Native DBAL storage/driver failure families reach the existing CLI
storage-error branch (exit1; JSON schemaVersion1/status failed/code storage_error)
and its fixed safe human sentence. Test redaction for these storage failures.
Command names, options, ordinary result schema and exit-code meanings stay exact.

The unchanged CLI catches PHP `InvalidArgumentException` first: input_error/exit2,
with its message in human output. A caller-supplied Connection/middleware or a
cleanup/unlock path throwing that category retains this branch. D5R1 does not
normalize arbitrary Throwables or promise all-Throwable output redaction. Include
the category distinction in regression evidence, without treating this inherited
CLI classification as evidence that a failed commit was an input problem.
No new exception payload or logging operation is introduced; arbitrary IAE
messages remain outside the output-redaction guarantee.

## Verification

Use real PostgreSQL and a Connection wrapper that calls actual `parent::commit()`
before throwing, plus independent observation after failure. Cover native begin,
first and later row roots, and terminal complete/failed roots. Prove no later SQL
mutation/finalization and no misleading count overwrite; earlier committed rows
survive. Also cover commit-before-effect failure with active rollback, advancement
precommit failure, finalization SQL failure, remaining-scan recovery and its own
failure, read-only plan, CLI redaction, and explicit resume without re-encryption.

Reuse existing nine-purpose matrix, CAS, kill/resume and consumer coverage; do not
replace it with mocks. Freeze all Product/Test/config/dependency inputs for the
full PHPUnit gate at the unchanged 512M limit, static/architecture gates and
rotation consumer journey. Independent transaction/security review is required.

## Subsequent adapter authority

D5R1 does not settle event subject/projection, strict tenant hydration, all-tenant
scope versus global event identity, interrupted audit IDs/trusted recovery actor,
clock/UUID/idempotency ownership, callback payload or same-Connection admission.
D5R2 must decide these before Product changes and join each event to its actual
native root. An outer decorator is insufficient. D154's four definitions remain
exact; native audit `complete` alone cannot select `rotation.storage.succeeded`.
