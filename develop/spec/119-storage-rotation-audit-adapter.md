# Specification 119: Storage Rotation Audit Adapter

Status: Accepted (local) — D5R2 implementation and evidence Green; BlackOps 1.3 unreleased

## Boundary and dependencies

This is the D5R2 source contract under specifications99/105/106/109/118 and
D154/D168/[D169](../decisions/169-storage-rotation-audit-adapter.md). It consumes
Accepted C/D1 and D5R1, plus Accepted D4B solely for C3's pure usesConnection
identity predicate. D5H/P are preserved and do not supply Product helpers.
Catalog bytes, C3 semantics, Core APIs, schema/migrations, key provider, native
query/CAS, command options/results and Application composition remain unchanged.

The adapter is explicitly opt-in and has no configured Production caller.
It adds three non-PublicApi types: the finite Transport interface
PostgreSqlStorageProtectionRotationPhaseHook, and final Internal/Audit/Rotation
StorageRotationAuditHook and StorageRotationAuditWriter. No new dependency layer,
arbitrary callback, public extension API, wrapper transaction or general event bus.
Existing native entry/Engine/Batch/RowExecutor/RowTransaction/Audit receive the
optional interface, default null. Transport does not depend on the concrete
Internal writer. This follows existing Deptrac edges without broadening them.

## Graph and entry admission

PostgreSqlStorageProtectionRotation retains its Connection, BopdEnvelopeCodec,
schema='blackops' constructor arguments and gains one trailing optional
PostgreSqlStorageProtectionRotationPhaseHook, default null. Its existing concrete
type remains accepted by the unchanged CLI. All existing constructor call sites
omit the new argument. Trusted later composition constructs the concrete hook
with a concrete writer; neither currently has a Production constructor caller.

The entry passes the same hook to Engine. Engine supplies it to native Audit and
Batch; Batch supplies it to RowExecutor, which supplies it to RowTransaction.
Every component still shares its native exact Connection. Optional arguments are
trailing, including after schema where present. No caller-provided native graph.

The interface has exactly these void methods (all inputs sensitive):

```text
assertEntry(Connection, StorageProtectionRotationScope)
assertRow(Connection, PostgreSqlStorageProtectionRotationRowRequest)
started(Connection, string auditId, StorageProtectionRotationScope)
interrupted(Connection, string auditId, StorageProtectionRotationScope)
progressed(Connection, PostgreSqlStorageProtectionRotationRowRequest, string cursor)
finished(Connection, StorageProtectionRotationAuditCompletion)
```

Hook constructor takes only StorageRotationAuditWriter in a protected snapshot.
It is the sole Production implementation. It validates/reduces native values and
delegates events to the writer; it owns no SQL, native graph, clock, IDs, retries
or transaction. It stores no per-row/current-run mutable state.

With a hook, Engine plan/rotate call assertEntry before native SQL/advisory lock,
native clock/entropy or projection. Preserve rotate's earlier explicit-confirmation
check. Admission requires auto-commit, inactive nesting0, exact writer/C3 Connection,
and revalidated native scope including canonical scope tenant. Construction does
no SQL, clock, native ID allocation or delivery. Plan remains read-only and emits
no event/reads no writer clock; it still obeys admission.

Each non-entry hook requires auto-commit, active nesting1 and the same writer/C3
Connection before subject/projection work. The writer's identity predicate checks
C3's stored Connection, not arbitrary recursively forged storage graphs. Trusted
same-Connection C3 composition remains a prerequisite.

## Trusted binding and subjects

Writer constructor matches the existing retention writer shape, in order:
HmacAuditOpaqueIdDeriver, AuditDeliverySelector, AuditDeliveryCoordinator,
ClockInterface, ActorContext, AuditRecordCorrelation, AuditSourceRuntime, build ID.
It retains only a bounded actor/correlation/runtime/build snapshot and protected
dependencies. Principal is ActorContext.authorization or null; execution is the
actual ActorContext.execution. Caller supplies real authenticated/infrastructure
provenance and administrative authorization for one invocation. Reuse of one
binding across distinct executions is unsupported. Native scope actor/reason
strings are neither authentication nor Audit provenance.

Start, terminal and interruption events describe the rotation scope. A non-null
scope tenant selects that tenant's partition. Null scope means an administrative
all-tenant scope and maps these scope events to system:global; it does not assert
that every processed row is global. Scope identity is the native scopeHash,
subsequently keyed as below; native checkpoint/audit tables keep their old meaning.

Progress describes consumption of one selected row by the native cursor/count
root, not necessarily a ciphertext change. Its tenant comes only from that
selected row's explicit tenant_type/tenant_id pair. Require both keys; accept
exact null/null or two nonempty canonical strings roundtripping through TenantRef
and AuditTypedReference. Reject partial/missing/wrong-type/normalized/control/NFC/
length-invalid pairs. A non-null scope tenant must equal that row tenant exactly.
Never fall back to global or another row/actor tenant.

Row selection precedes its transaction. CAS can skip after deletion, replacement,
tenant change or envelope change. The event then names the selected subject and
its historical selection tenant; it does not prove the current row tenant or
successful encryption. A key-mismatch skip also advances native cursor/counts.
There is one progressed event for each successfully committed row root, including
those skips, and none for a rolled-back or failed row root. All nine native
storage purposes are supported; Audit Outbox/sealed Audit storage are not added
to native rotation targets.

Hook assertRow runs immediately after row begin and before columns/decryption.
It validates write mode, non-null native audit ID, scope, exact target shape for
the selected StoragePurpose, tenant and cursor identity. The identity is a
canonical lowercase RFC4122 UUID for non-composite targets, or positive canonical
PostgreSQL integer scope_version (1..2147483647) plus colon plus64 lowercase hex
scope_hash for idempotency. Accept driver int or exact decimal string version;
do not normalize arbitrary input. Validate canonical UUID by installed Uid
roundtrip. The row's operation_id is not promoted to execution/correlation.

## Native roots and event truth

| Native persisted transition | Event and meaning |
| --- | --- |
| New started native audit and claimed running checkpoint | rotation.storage.started for this scope/run, before begin commit |
| Cursor/count advancement after a non-failed row | rotation.storage.progressed for the consumed selected row, before row commit |
| Native failed checkpoint/audit finalization with positive failed count | rotation.storage.failed for this scope/run, before finish commit |
| Native complete checkpoint, zero failed count, native complete audit | rotation.storage.succeeded for cursor/batch exhaustion, before finish commit |
| Native running checkpoint with complete batch audit, zero failed count | No terminal event; the run is still incomplete at scope level |
| Recovery marks a prior unfinished started native audit failed | rotation.storage.failed for that old run under the current recovery execution, in the new begin root |

All four exact definitions use durable delivery, evidence exactly {"version":1},
their existing phase/outcome/policy tuples and protected_storage resource type.
No counts, keys, cursor, raw IDs, raw scope hash, actor/reason, exception, payload,
or invented old execution metadata enter evidence. Counts stay in native output.

Native complete uses selected row count versus batch size, not remaining old-key
count. A zero-row exhausted cursor can succeed while old-key references still
exist behind that cursor or in concurrent writes. A CAS skip can precede complete.
The succeeded event therefore means the stated cursor exhaustion only; it is
never key retirement, global rewrite coverage, backup/replica safety or absence
of retained/dead-letter references. Existing explicit re-plan/retirement duties
remain. Hook validates completion state, nonnegative counts, failed>0 exactly
for failed state, zero failures for running/complete, and native fingerprint
null for running/complete or canonical v1:64hex for failed. It makes no stronger
count equality assertion because native skipped counts include key mismatches.

For begin interruption recovery only when a hook is present: after native
checkpoint validation/upsert, select audit_id for the existing checkpoint/scope,
state started and finished_at IS NULL, ordered by audit_id, LIMIT101 FOR UPDATE.
Materialize at most101 rows; maximum accepted prior records is100, independent of
batchSize. Require each canonical32-lowercase-hex ID and reject overflow/invalid
source before changing old audit rows. With the existing checkpoint advisory lock
held, update each selected ID using the same checkpoint/scope/state predicates;
require affected count exactly1, preserve the existing failed-count/fingerprint/
finished-at assignments, then invoke interrupted for that ID. Do not perform the
old broad UPDATE in the enabled branch. Any mismatch or append failure rolls back
the whole begin root. No claim covers concurrent writers outside the native lock
protocol or a database with required schema constraints removed.

Only after all selected old records are updated/emitted does the existing new
started INSERT occur, followed by started and commit. A current actor records the
new recovery decision; old actor/time/runtime/build are not recovered or invented.
No prior started AuditRecord is fabricated for legacy runs. Null-hook recovery
retains the existing broad UPDATE and imposes no new cap.

Native Audit.finish calls finished only after both native updates and before
commit. RowTransaction.advance computes the cursor once, performs existing cursor
and count SQL, and returns that cursor. progressed runs afterward, before commit,
outside the catch converting native advancement errors to failed-row results.

## Identity, time and delivery ownership

Native begin retains its existing random16-byte lowercase-hex audit_id allocator
and native wall-clock ownership. No new UUID/execution allocation. Hook/writer
revalidate audit ID32hex, scope hash64hex and cursor grammar before projection.
Writer public methods are assertConnection(Connection), started/failed/succeeded
(string auditId,string scopeHash,?TenantRef), and progressed(string auditId,
string scopeHash,string cursor,?TenantRef), plus safe object hooks.

Using the existing concrete HMAC deriver, derive:

```text
Scope Resource input = rotation-storage:scope:<scopeHash>
Row Resource input = rotation-storage:row:<scopeHash>:<cursor>
Scope Action input = rotation-storage:<eventType>:<auditId>
Progress Action input = rotation-storage:rotation.storage.progressed:<auditId>:<cursor>
Durable idempotency key = rotation-storage:<opaque Action ID>
```

Resource and Action use their separate existing purpose domains. Inputs stay
within256 bytes. ScopeHash includes native purpose, tenant scope, key transition
and checkpoint; a row resource is that row within that rotation scope. Raw values
appear only inside keyed derivation/native persistence, not logical Audit JSON.
An interrupted old run and its new recovery run have different native/action IDs.
Failed terminal and interrupted failure for the same old run share a failure
identity; the native source never legally emits both for an already finished run.

Writer checks bounded binding/IDs/tenant and exact Catalog definition, then reads
its clock exactly once per event. It creates one draft/request, selects one plan
and calls deliverInCurrentTransaction once. C3 owns record/outbox IDs. Source and
policy component are blackops.rotation.storage, policy version1, explicit runtime/
build, caller correlation unchanged. No SQL, native effect, transaction management,
standalone fallback or parallel logging. Running finish and configured plan still
perform Connection-identity admission through writer.assertConnection; they do
no writer clock/HMAC/projection/delivery work.

No retry registry is added. C3 exact idempotency/content-conflict rules remain:
manually repeating a key with a different time/provenance is not automatic safe
recovery. A new explicit invocation has a new native run ID; previously advanced
rows remain skipped by native checkpoint/key behavior. Relay retry retains its
stored event. No exactly-once whole-invocation claim.

## Failures and privacy

assertRow failure rolls back its row root and propagates. Native column/CAS/
advancement failure retains the spec118 failed-row conversion and can lead to
one later persisted failed terminal event. A progressed hook failure rolls back
the current row and propagates without failed-row conversion, later rows, remaining
scan or finish. Begin/finish hook failure rolls back that root and propagates;
there is no replacement event for a rolled-back transition.

Keep spec118 commit-outcome behavior: actual commit-then-throw can preserve the
native effect and C3 ingress together, with the response failed/unknown. No second
finalization or blind retry. Earlier independent roots survive later failure.
Remaining-scan failure retains one best-effort failed finalization; if its append
fails, that finish root rolls back and the original scan error remains. Native
conditional cleanup/DBAL nesting does not prove underlying driver recovery; existing
rollback/unlock exceptions can replace an earlier failure. External clocks,
entropy, provider effects and advisory lock interactions are not rolled back.

New hook/writer boundaries reconstruct bounded AuditDeliveryException without
source message/code/previous chain. Preserve only known AuditDeliveryFailure
classification; use TransactionInvalid for root/Connection rejection, PolicyInvalid
for invalid subject/shape, ConfigurationInvalid for constructor binding,
ResourceUnavailable for writer clock, KeyUnavailable for derivation and
StorageUnavailable for corrupt snapshots. Do not synthesize a normal result.

Protect dependencies/source values with SensitiveParameter and protected snapshots;
empty debug/serialize, rejecting clone/unserialize and exact corrupt-state checks.
Mark sensitive Scope/RowRequest/BatchExecution/Completion and related ID/cursor
arguments in the six touched native files too: otherwise a fresh hook exception
would retain native caller arguments. Tests inspect actual owned trace frames.
Unchanged native/provider/external frames and arbitrary exception graphs remain
outside this promise. There is no blanket remap of native errors; spec118's
inherited CLI IAE input_error/exit2 versus native storage_error/exit1 limit stays.

## Guards, evidence and release

Close exact interface implementation, optional propagation, native hook placement,
root/SQL ownership, concrete hook-to-writer calls, writer-only C3/selector/provider
delivery edges and absence of configured callers. The hook separately references
only AuditDeliveryException and AuditDeliveryFailure for its specified bounded
error construction/remapping; close that exact error-only type edge in C3 guards.
It may not reference selector/coordinator/plan/request/store or invoke delivery.
Extend existing A2/C3 allowlists only
by reviewed type/method, not a file/directory exemption. Use name-resolved AST
checks and bounded syntax-valid negative controls for new/static/instance/nullsafe/
function/dynamic calls, inert/alias/case positives and accidental activation.
This is an executable reviewed graph contract, not arbitrary-PHP verification.
Package inventory gains exactly three types; no dependency/baseline/Deptrac edits.

Real PostgreSQL tests use the native concrete graph, canonical schema, C3 ingress
and an independent observer. Cover all nine purpose identities, scoped/all/global
subjects, malformed tenant before decode, stale/CAS-skip subject truth, native
failure versus append failure, interruption0/1/multiple/100/overflow/invalid ID,
running/complete/zero-row truth, ambient/non-autocommit/wrong Connection before
side effects, and begin/row/finish real commit-after-effect plus rollback faults.
Keep D5R1's existing18 tests unchanged. Unit projection/privacy and source negative
guards supplement, not replace, DB evidence. Exact files/commands are in the Task.

The local child leaves public Source/routes/Search/LLM/version occurrences frozen.
Internal source architecture is this spec; user-facing operational guidance and
ordinary activation belong to integration/Documentation before1.3 release. Public
capability authority stays Stable1.2.1, with1.3 unreleased. Local acceptance cannot
substitute for owner preview, same-SHA CI/export or delivery.
