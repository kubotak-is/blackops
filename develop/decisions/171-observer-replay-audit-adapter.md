# D171: Observer Replay Audit Adapter

Status: Decided — bounded implementation Accepted (local); BlackOps1.3 unreleased

## Decision

Keep an optional default-null finite Transport hook, Internal hook/writer and
no ordinary composition change. Scope events use global administrative checkpoint;
progress uses the same immutable selected raw JournalRecord and its strict tenant.
This is historical selected-source lineage, not arbitrary-PHP authentication or
current-row provenance. Fixed current ActorContext/correlation/build and Replay
runtime exclude CLI text and historical actor authority. C3 alone owns partition
derivation and current-root append. Preserve O1 unknown outcomes, earlier roots,
paused-no-event and explicit resume without invented terminal backfill.

Rejected alternatives are a mutable provenance cache, new row re-query/token,
writer-owned tenant partition derivation, whole-invocation transaction and event
inference from commit exceptions. The source contract adopts none of them.

The [specification](../spec/121-observer-replay-audit-adapter.md) owns the exact contract;
the [Task](../orchestration/tasks/P23-003D5O2-observer-replay-audit-adapter.md) owns file/resources/gates.
The O2/F1 pair explicitly adopts disjoint source scopes and serialized package
script ownership with one common final frozen full. This shares identical-input
qualification, not semantics, and waives no child-specific proof. O1 keeps its
separate already-required native full. Parent006 retains final clean same-SHA
CI/export and immutable publication. Stable1.2.1 remains current.

## Bounded correction after initial feedback

The issued six-parameter progress API and monolithic helpers failed unchanged
metrics. Preserve the legacy five-parameter advance signature and add the finite
five-parameter advanceSelected entry with delivered fixed1. Keep native admission
and reject configured legacy source-less progress before effects. One owned
native root serves both paths; preserve every O1 transaction/cleanup outcome.

Adopt the twelve explicit one-class-per-file helpers in spec121/Task (25 paths
total), with protected Hook/Writer snapshots and unchanged public boundaries.
The helpers separate validation, current trusted binding/projection and native
root work; no nested declaration, mutable provenance, callback extension,
new public DTO or metric waiver. Exact A2/C3 owners move with the actual methods;
O2 alone also repairs the five exact sibling F1 timestamp call owners in A2.
The correction has independent authority review before implementation assignment.
Every initial native/privacy/AST finding still requires meaningful proof.
