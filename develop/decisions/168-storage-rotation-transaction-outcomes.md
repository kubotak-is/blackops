# D168: Storage Rotation Transaction Outcomes

Status: Decided — D5R1 Accepted (local); D5R2 and release pending

## Decision

Split D154's storage-rotation child into D5R1 native transaction-outcome correction
followed by D5R2 phase-hook/adapter authority and implementation. The remaining
replay/generic-Outbox and ordinary integration DAG is unchanged. D5R1 consumes
the existing native rotation only; it does not depend on D4B's Connection helper
or consume C3. D5R2 must declare its actual dependencies separately.

Adopt [spec118](../spec/118-storage-rotation-transaction-outcomes.md). Existing row
transaction handling turns a commit exception into failed-row counts; existing
batch finalization can call `finish()` again after its first commit throws. An
actual commit-then-throw can therefore overwrite committed progress or terminal
state as a new failure. Fix those two error boundaries before adding immutable
phase events; retaining that overwrite would create misleading audit semantics.

Move row commit out of the advancement failure conversion. Clean up an active
transaction and propagate commit failure without completing that invocation.
Limit best-effort `finishFailure()` to the remaining-scan failure before normal
finalization. Execute normal `finish()` once; any SQL/commit error escapes after
its existing cleanup. Keep recoverable precommit failed-row behavior, native
transaction divisions, explicit resume, and all success/dry-run behavior.

This intentionally propagates unknown row-commit failures instead of fabricated
failure counts. Native DBAL storage/driver exceptions select the CLI's existing
storage_error/exit1 result. The unchanged earlier InvalidArgumentException branch
still selects input_error/exit2 (and exposes its message in human output); no
normalization or universal redaction of caller-supplied exception categories is claimed.
Precommit finalization errors also cease retrying finalization. Persisted state
must be inspected/resumed explicitly; no exactly-once, automatic recovery,
whole-invocation atomicity or Throwable-identity promise is added.

## Alternatives and consequences

- Keep native behavior and append phase events: a committed success may be
  reclassified by exception handling. This is unsuitable as an immutable event source.
- Infer commit outcome from an inactive Connection: transport failures cannot
  prove persistence. All commit exceptions stay unknown outcomes.
- Wrap the entire rotation or add a recovery registry: changes partial-progress
  semantics and expands schema/protocol scope unnecessarily.
- Add hooks now: tenant scope, interruption identity and trusted provenance need
  their own source contract. D5R1 deliberately emits no new event.

The [Task](../orchestration/tasks/P23-003D5R1-storage-rotation-transaction-outcomes.md)
allows two existing Product files and one focused regression file, with real
commit-after-effect evidence, full/static/consumer gates and independent review.
Stable1.2.1 and its public documentation remain current. This development-tree
correction must be recorded in 1.3 release/migration documentation at integration.
