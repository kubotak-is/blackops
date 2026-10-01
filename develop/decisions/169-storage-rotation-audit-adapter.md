# D169: Storage Rotation Audit Adapter

Status: Decided — D5R2 Accepted (local); BlackOps 1.3 unreleased

## Decision

Adopt independently reviewed [spec119](../spec/119-storage-rotation-audit-adapter.md).
D5R2 depends on Accepted C/D1/D5R1 and explicitly on Accepted
D4B for C3 Connection identity only. The rest of D154's DAG is unchanged.

Use a finite optional Transport PhaseHook interface, one final Internal hook and
one final writer. Pass the optional interface through the six native construction
owners, default null; retain the CLI's concrete rotation type. No current caller
enables it. Transport's existing dependency layer cannot reference the concrete
Internal writer, so a typed boundary preserves architecture without adding a broad
Deptrac allowance or an incompatible facade/CLI type.

Join started/interrupted, progressed, and terminal delivery to their existing
begin, per-row and finish roots. An outer wrapper cannot append after commit and
call that atomic. Native D5R1 guarantees prevent ambiguous commit from being
reclassified or retried. Audit append faults abort their own root and invocation;
ordinary precommit native row faults can still persist a later failed state.

Treat scope-level null tenancy as administrative all-tenant scope in system:global.
Per-row progress retains the selected row's strict paired tenant, validated before
decryption. CAS skips still commit cursor/count progress; the event describes the
selected subject, not proof of a current row rewrite. Never infer a tenant from
scope null, actor, another row or incomplete metadata.

Emit succeeded only for native checkpoint complete with zero failures; running
batch completion has no terminal event. Native complete is cursor exhaustion,
including zero-row and skipped cases, and is explicitly not key-retirement proof.
Changing native success/remaining-scan semantics would enlarge this source task
without establishing replica/backup/retention safety anyway.

For interrupted legacy runs, retrieve at most101 old IDs inside the locked begin
root, reject more than100 or invalid source, update exactly the selected IDs and
emit one failed event per persisted transition. Record current recovery actor,
execution and time. Do not invent historical provenance or backfill started events.
The fixed cap bounds work and allocation independently of row batchSize. Overflow
fails before old audit mutations; an operational legacy cleanup policy is outside
this child. The null-hook native path keeps its existing behavior.

Native run IDs, canonical scope hashes and consumed row cursors feed explicitly
domain-separated keyed references; only the opaque references enter Audit JSON.
Keep Catalog/evidence exact, native ID/time ownership, one writer Clock capture
and selector per event, C3 record/outbox ownership and exact idempotency rules.
Native actor/reason input strings never authenticate or populate the new record.

Keep native exception compatibility and documented commit/cleanup ambiguity.
New hook/writer errors are safe bounded AuditDeliveryException values. Sensitive
annotations in all touched native caller frames prevent those new exceptions
from exposing Scope/row contents through traces; this is not a universal redaction
guarantee for unchanged native/provider/external code or arbitrary exceptions.
The C3 guard permits the hook's two bounded error types for that contract; only
the writer gains selector/request/coordinator delivery edges. This does not grant
the hook a C3 store or delivery capability.

## Alternatives and consequences

- A concrete Internal dependency in Transport would require changing the declared
  layer boundary. The finite typed interface carries only reviewed phase methods.
- An outer facade after native rotate would lose same-root atomicity and require
  changing the CLI's concrete service contract.
- A transaction around the whole invocation would erase native partial-progress
  semantics and retain unrelated roots/locks longer.
- Unbounded recovery materialization or a broad UPDATE followed by a partial ID
  list could mutate legacy rows with no matching event. Use a bounded exact list.
- Mapping every native complete audit to succeeded would claim success while the
  checkpoint is running. Mapping it to global key retirement would be stronger
  than the native protocol can prove.

## Bounded quality-metric decision

After independent source review, Root accepts class-scoped Mago expectations for
Hook/Writer's cyclomatic-complexity, kan-defect and too-many-methods, and native
Audit's cyclomatic-complexity and kan-defect only. The finite phase/object surface
and strict input checks stay together in the reviewed three-type graph; native
SQL and phase delivery keep one transaction owner. Writer's redundant tuple
arguments were removed and native begin was split into cohesive helpers. Its
trivial started helper is inlined to meet the method limit without an exception.

This decision does not change global thresholds, baselines or analysis scope and
does not authorize other rule/class exceptions. Guard mutations, transaction
faults and static gates remain required. The measured earlier diagnostics and
the source-specific review rationale are recorded in the Task Report; these
aggregate metrics are not evidence of behavioral correctness.

The [Task](../orchestration/tasks/P23-003D5R2-storage-rotation-audit-adapter.md)
freezes exact Product/Test/package changes, real DB failure evidence, one full512M
gate and independent transaction/security review. This is local internal opt-in
development; canonical activation, release documentation and1.3 release remain later.
