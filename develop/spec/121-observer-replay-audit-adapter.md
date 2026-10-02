# Specification 121: Observer Replay Audit Adapter

Status: Decided — bounded implementation Accepted (local); BlackOps1.3 unreleased

This is the most-specific contract for [P23-003D5O2-observer-replay-audit-adapter](../orchestration/tasks/P23-003D5O2-observer-replay-audit-adapter.md)
and [D171](../decisions/171-observer-replay-audit-adapter.md).
Accepted C/D1 and D5O1; D4B only for coordinator Connection identity. Preserve spec120/D170. Follow spec105/106/109 and D154.

## Adopted source contract

Preserve the four existing native roots and all four immutable replay definitions
from D154. Add one optional trailing Transport hook to the Store, default null.
The concrete hook and writer remain Internal, with no configured Production
constructor caller. Ordinary activation belongs to D integration, not this child.
No schema, query, CLI, current Application composition, Catalog, C3 source,
Core API, package dependency or native allocator change.

Choose historical selected-source lineage for progressed events. The sole native
Runtime carries the same immutable JournalRecord from Store.select through
projection/Observer/flush into advanceSelected. No re-read, decrypt, mutable selected-row
cache or public provenance token is introduced. This does not authenticate an
arbitrary constructible JournalRecord supplied by arbitrary PHP; native exact
producer/argument guards and real scoped selection establish the installed graph.
Deleting or changing the source row after selection does not retrospectively
change that selected subject. Do not claim current-row/effect-bound tenant proof.

Started/succeeded/failed describe the administrative checkpoint invocation in
system:global. Progressed describes the selected raw record, under its exact raw
tenant or explicit null/global tenant. Masked ObservedJournalRecord values,
historical actors/correlation and native CLI actor/reason are never authority for
the current Audit action. Invalid source is rejected, not replaced by global.

The future writer receives one trusted current ActorContext/correlation/build
binding per administrative invocation. Authorization must have happened in later
trusted composition. Source runtime is the fixed existing Replay enum value,
not a caller-selectable CLI/HTTP label. This child does not invent authentication
for the existing --actor string.

Writer constructor arguments, in order: HmacAuditOpaqueIdDeriver,
AuditDeliverySelector, AuditDeliveryCoordinator, ClockInterface, ActorContext,
AuditRecordCorrelation, string buildId. Keep only protected dependencies plus
bounded detached actor/correlation/build snapshots; derive the principal from
authorization (or null) and execution from actual execution. No historical
Journal Operation actor/correlation enters this binding. Build and all trusted
references obey their existing exact validation. Constructors do no effect work.

Its public operational methods are exactly:

```text
assertConnection(Connection): void
started(string checkpoint, string auditId): void
succeeded(string checkpoint, string auditId): void
failed(string checkpoint, string auditId): void
progressed(string auditId, string recordId, ?TenantRef tenant): void
```

All arguments are sensitive. Safe debug/serialize/clone/unserialize object hooks
are the only additional public methods. Private validation/projection helpers
have no independent extension API. Scope methods always use explicit global
tenant; progressed is the only selected-record tenant route. Writer revalidates
reduced values, so a direct helper call cannot bypass their structural contract;
it still does not authenticate arbitrary caller provenance. Exact private helper
inventory and capability AST are frozen with the reviewed implementation.

## Exact interface/caller proposal

Store constructor retains Connection, protection, schema and codec positions,
then gains `?PostgreSqlObserverReplayPhaseHook $hook = null`. All current Product
construction sites omit it or pass literal null; no new builder/registry exists.
Transport references only the finite interface. Sole concrete implementation is
Internal/Audit/Replay/ObserverReplayAuditHook with one protected writer snapshot.
The twelve exact static helpers below have no independent activation path.

Proposed exact void interface methods (all arguments SensitiveParameter):

```text
assertEntry(Connection, string checkpoint)
started(Connection, string checkpoint, string auditId)
progressed(Connection, string checkpoint, string auditId,
           string cursor, JournalRecord source)
finished(Connection, string checkpoint, string auditId, string state)
failed(Connection, string checkpoint, string auditId)
```

Each of the four Store mutation methods retains its D5O1 native admission first,
then calls hook.assertEntry before native clock/SQL/advisory/entropy. Native
ambient-root error behavior stays D5O1; a hook does not bypass or weaken it.
Entry additionally verifies exact writer/C3 Connection identity and canonical
checkpoint. Construction and entry perform no clock/HMAC/append/native work.
Read-only select/load/dryRun and unlock do not invoke the hook or writer.

Keep Store.advance(checkpoint, cursor, delivered, auditId, now=null): void with
its exact original five names/types/default and null-hook behavior. Introduce
advanceSelected(checkpoint, cursor, auditId, JournalRecord source, now=null): void;
cursor remains nullable string and now remains nullable DateTimeImmutable. This
entry fixes delivered=1. The sole Production producer is the same raw record in
Runtime's existing selected loop. Both entries call the same owned native root.
With a configured hook, legacy source-less advance retains ambient admission,
then assertEntry, then throws a fresh RuntimeException with fixed message
`Replay audit progress requires a selected record.` before clock/SQL/entropy.
Transport acquires no Internal Audit exception dependency. No selector/cursor
algorithm, source reconstruction, query or current-row lineage is introduced.

All event methods require autoCommit, active nesting1 and exact writer/C3
Connection before projection. They check checkpoint plus auditId. Progress
receives one non-null source from advanceSelected's fixed delivered=1; invalid source can cause
the already started native root to roll back but may not reach clock/HMAC/append.
The hook owns no SQL/transaction/time/random/retry/current-row state.

## Native truth and ordering

| Native boundary after successful required SQL, before its one commit | Hook/event |
| --- | --- |
| Checkpoint claimed/inserted/resumed and new native invocation inserted | started / replay.observer.started |
| Running checkpoint cursor/count updated and matching started invocation count updated, both affected1 | progressed / replay.observer.progressed |
| Checkpoint complete and native invocation complete, both affected1 | finished(complete) / replay.observer.succeeded |
| Checkpoint paused and native invocation complete, both affected1 | finished(paused): validate admission/state only, no clock/HMAC/draft/append |
| Checkpoint failed and matching invocation failed, both affected1 | failed / replay.observer.failed |

Finished accepts only paused/complete; no event from native audit complete alone.
A failed event requires committed failure state. An event attempt rolls back
with its own native root on append failure. No standalone fallback, outer root,
blind retry or second fail/finalization. Empty exhausted selection can complete.
Success means no more rows in the selected bounded observation, not absence of
future append or exactly-once external Observer delivery.

The inherited D5O1 stage boundary remains: selection/decrypt/project/observe/flush
failure tries native fail once; cursor/advance/hook/commit failure does not.
Actual commit-then-throw can leave both native and C3 ingress durable, while the
response stays failed/unknown. Earlier roots and external Observer effects stay.
Resume uses persisted cursor and a new native invocation ID; old started rows
stay untouched, with no invented interrupted/requested/backfill event. Paused
invocations may have no terminal Audit event. No terminal-pair completeness claim.

## Subject and identity validation

Checkpoint is exact existing native lowercase stable grammar, 1..128 bytes.
Audit ID is exactly32 lowercase hex. Record ID is exact canonical lowercase
UUIDv7, using the existing JournalRecordId::fromString(value)->toString()===value
roundtrip or exactly equivalent native grammar. Writer.progressed revalidates
the reduced string under the same rule; generic RFC4122/Uid acceptance is too
broad. Raw source is the selected immutable JournalRecord. Recheck the consumed
initialized JournalRecordId, positive native integer sequence, usable immutable
timestamp with its native UTC representation, initialized JournalOperation and
its explicit null-or-TenantRef tenant. Do not inspect unused historical actor,
correlation, attempt or payload fields, or impose a new time/sequence range.
Tenant must be explicit null or exact type/id strings roundtripping through
TenantRef and AuditTypedReference, including UTF8/NFC/control/byte requirements;
no trimming or fallback. Progress cursor must equal exactly one existing source
cursor representation: record ID, positive source sequence + `|` + record ID,
or source UTC `Y-m-d H:i:s.uP` + `|` + record ID. The sole Runtime chooses the
correct representation for its selector; a shape-valid direct caller does not
prove persisted selector identity or correspondence to arbitrarily changed clear
ordering columns. Source sequence/date/ID are
not promoted into evidence or current execution provenance.

Use the existing concrete HMAC deriver and its separate Resource/Action purposes:

```text
Scope Resource = observer-replay:checkpoint:<checkpoint>
Progress Resource = observer-replay:record:<recordId>
Scope Action = observer-replay:<eventType>:<auditId>
Progress Action = observer-replay:replay.observer.progressed:<auditId>:<recordId>
Durable idempotency key = observer-replay:<opaque Action ID>
```

All inputs fit256 bytes. Components have unambiguous fixed colon-free grammars;
there is no new length-prefix encoding or opaque-ID wire. The existing HMAC
deriver still owns its existing purpose/domain framing. Record resource is the
stable selected Journal record, independently of which checkpoint replays it;
Action distinguishes invocation and phase. IDs are never raw logical Audit data.

Writer validates binding/subject/exact Catalog tuple, captures its trusted clock
exactly once per emitted event, derives one Action and Resource, creates one
draft with the strict typed tenant-or-null, one request/plan and calls C3
current-root delivery exactly once. Partition derivation/binding validation and
record/outbox IDs/storage stamping stay entirely with unchanged C3 and its
AuditPartitionIdDeriver. Writer never derives a partition or substitutes an
HMAC tenant ID. Native clock and32hex invocation entropy remain native.
No clock read for paused or dry-run.
Manual repetition with changed time/provenance is content conflict, not safe
retry. Relay retries preserve stored bytes. Writer has no ambient context lookup.

All events use fixed source/policy component blackops.replay.observer, version1,
runtime Replay, explicit build/correlation/current actor, lifecycle_journal
resource and exact D154 semantic tuples. Evidence is exactly {"version":1}; no
counts, selector, cursor, raw IDs, exception, actor/reason or payload leak.
Use the existing FrameworkOperationalAuditEventCatalog identity/version/hash;
no duplicate provider or tuple recreation that weakens catalog provenance.

## Failure/privacy and exact dependency edges

Use fresh value-free AuditDeliveryException; keep only known delivery failure
classification and no prior cause. TransactionInvalid for root/Connection,
PolicyInvalid for source/phase/shape, ConfigurationInvalid for constructor
binding, ResourceUnavailable for clock, KeyUnavailable for derivation,
StorageUnavailable for corrupt protected snapshot. C3 errors remap safely.
Protect hook/writer dependencies and snapshots and their sensitive owned native
call arguments. Debug/serialization empty, cloning/unserialization rejected;
test actual owned frames and corrupt snapshots. Do not claim privacy for every
unchanged driver/Observer/provider/CLI frame. Inherited native exception limits
and D5O1 fixed cursor error remain explicit.

The formal source-frame inventory marks all Store constructor arguments and
all arguments of begin, advance, advanceSelected, finishInvocation and fail SensitiveParameter.
Runtime marks replay's request, every resume argument, deliver's request/target
bindings/native binding, every recordFailure argument, every finalize argument
and release's checkpoint. Preserve names/signatures from final O1 when issuing;
no new helper is implied by these names. These are the owned active call frames
that can retain a request, raw source, IDs or Throwable during the introduced
failure path. Test actual caught traces from begin/progress/finish/fail and
replay/resume, including the local helper frames. Read-only select/load/unlock
and arbitrary inherited native/external exception graphs gain no blanket claim.

D4B dependency is only the existing coordinator usesConnection predicate.
Exact A2/C3 edges follow the helper responsibility table below. WriterEmission
alone directly selects and appends in the current C3 root; WriterBinding checks
the trusted coordinator binding. Hook/HookGuard use Writer.assertConnection
and acquire no direct coordinator/selector/store edge. No broad directory
allowlist or relaxed architecture/baseline threshold is permitted.
If the bounded type design cannot satisfy current static gates, return a concrete
finding for an explicit design review rather than suppressing a failing gate.

## Corrected finite helper inventory

The initial monolithic Hook/Writer and six-argument progress API failed unchanged
quality gates. Keep Hook and Writer's original ten-method public boundaries
(constructor, five operations, four object hooks), with protected snapshots and
no new private methods. Use exactly twelve additional final PSR-4 classes,
one per listed file in the Task. Static helpers retain no object or global state;
no public constructor or inherited helper graph. Any private constructor counts
toward the unchanged method/complexity rules. Each variable snapshot, closed
array, record, tenant, identity, connection, ref and time input is sensitive.
Document exact closed array shapes; no raw untyped dependency bag is authorized.
Hook/Writer __debugInfo and __serialize return empty arrays; clone/unserialize
reject with the existing bounded failure. No blanket serialization rejection
replaces the empty-output contract.

| Helper | Exact public static surface | Responsibility |
| --- | --- | --- |
| ObserverReplayAuditFailure | fresh(AuditDeliveryFailure): AuditDeliveryException; remap(AuditDeliveryException): AuditDeliveryException | Fresh value-free finite failures; no prior cause. |
| ObserverReplayAuditHookGuard | writer(SensitiveParameterValue): ObserverReplayAuditWriter; entry(SensitiveParameterValue,Connection): ObserverReplayAuditWriter; root(SensitiveParameterValue,Connection): ObserverReplayAuditWriter; checkpoint(string): void; auditId(string): void | Protected writer snapshot, exact Connection and entry/root admission, strict IDs. |
| ObserverReplayAuditHookLineage | validate(string cursor,JournalRecord source): array{recordId:string,tenant:?TenantRef} | Every consumed source field, native UTC range, UUID/sequence/tenant and exact cursor relation before effects. |
| ObserverReplayAuditWriterBinding | capture(array input): SensitiveParameterValue; values(SensitiveParameterValue): array; reference(?ActorRef): ?AuditTypedReference | Detached current trusted actor/correlation/build and protected dependencies; no historical actor. |
| ObserverReplayAuditWriterEmission | write(SensitiveParameterValue,string eventType,string subject,string auditId,?TenantRef): void | One validated projection and sole direct C3 current-root delivery. |
| ObserverReplayAuditWriterPolicy | assertSubject(string eventType,string subject): void; assertAuditId(string): void; assertRecordId(string): void | Strict reduced values even on direct Writer entry. |
| ObserverReplayAuditWriterCatalog | catalog(): AuditEventCatalog | Existing exact Framework catalog identity/version/hash/owner. |
| ObserverReplayAuditWriterDefinition | definition(AuditEventCatalog,string eventType): AuditEventDefinition; matches(AuditEventDefinition,array expected,string eventType): bool | Four closed immutable definitions/semantic tuples, no caller-selected catalog policy. |
| ObserverReplayAuditWriterRecord | occurredAt(array values): string; opaqueId(array values,AuditOpaqueIdPurpose,string context): string; selector(array values): AuditDeliverySelector; tenantReference(?TenantRef): ?AuditTypedReference; draft(array input): AuditRecordDraft | One current clock, fixed HMAC purposes/contexts, explicit tenant, fixed provenance and safe immutable draft. |
| PostgreSqlObserverReplayAdvanceRoot | run(array input): void | Both progress entries, native admission, both affected rows, hook and commit/cleanup. |
| PostgreSqlObserverReplayBeginRoot | run(array input): PostgreSqlObserverReplayBinding | Begin admission/identity/entry, advisory ownership and transaction cleanup. |
| PostgreSqlObserverReplayBeginTransaction | run(array input): PostgreSqlObserverReplayBinding | Existing insert/resume/native invocation, started hook, commit and binding. |

Store may inline only its existing private tenant parser into select to keep its
ten-method inventory when adding advanceSelected. Preserve every native decode,
metadata tenant/record identity check, immutable raw return and cursor rule; this
is a mechanical movement, not an algorithm change. The two begin helpers split
the existing Halstead-heavy body without changing advisory ownership, selected
Connection, O1 rollback/unknown outcome or cleanup. No other Store refactor.
These helper names do not introduce additional physical transaction roots.
BeginRoot owns the sole beginTransaction inside the try after advisory-lock
acquisition and the existing rollback/unlock cleanup; BeginTransaction owns the
existing SQL, started hook, sole commit and returned binding. AdvanceRoot owns
the sole shared progress begin/SQL/hook/commit/cleanup. No additional begin,
hidden retry, nested root or transaction per helper is allowed.

| Exact moved owner/callable | Permitted A2/C3/provider operation |
| --- | --- |
| Writer.__construct | Retain typed correlation/coordinator/selector constructor inputs and delegate detached capture. |
| Writer.assertConnection | Exact bound coordinator usesConnection predicate only. |
| WriterBinding.capture / reference / values | Validate/copy the current actor and correlation; construct AuditRecordActor/Correlation and typed references, protect/check the finite dependency snapshot. No append/selection/provider lookup. |
| WriterCatalog.catalog | The sole Replay direct FrameworkOperationalAuditEventCatalog::catalog call, exact identity/hash checks. |
| WriterDefinition.definition / matches | Validate the fixed catalog definitions/tuples; no second provider call or reconstructed lookup. |
| WriterRecord.draft / tenantReference | Construct the fixed A2 draft and record components/source runtime and validated typed tenant; receive detached actor/correlation and the fixed definition. No partition or current actor inference. |
| WriterRecord.selector | Return only the typed selector from the validated protected values; no selection side effect. |
| WriterEmission.write | Construct AuditDeliveryRequest, call selector.forDraft and the sole direct coordinator.deliverInCurrentTransaction. Never derive partition or commit. |
| ObserverReplayAuditFailure.fresh / remap | Construct a fresh AuditDeliveryException with the finite category and null previous; no other C3 effect. |

The table's short Writer names refer only to the full ObserverReplayAuditWriter*
types listed above. Type-only references and catches may follow these bounded
responsibilities, but no additional executable A2/C3/provider operation is
implied. Freeze actual resolved constructor/static/receiver inventories and
reject a moved call, additional method owner, sibling path or directory exemption.

The resolved source guard enumerates all helper declarations, callable surfaces,
private helpers and actual edges; no helper is directly called or registered by
other Production. A2/C3 guard maps follow the exact constructor/static-call owners
above and preserve all inherited negative controls. Helpers do not gain caller-
selected policy, tenant partition derivation, clock substitutes, callback seams
or mutable provenance. Actual owned trace proof includes their sensitive frames.

## Exact scope and verification

Store and Runtime; new Transport/PostgreSqlObserverReplayPhaseHook;
new Internal/Audit/Replay/ObserverReplayAuditHook and ObserverReplayAuditWriter;
new matching HookTest and WriterTest; new PostgreSqlAuditedObserverReplayTest;
new ObserverReplayAuditArchitectureTest; exact additions to existing
AuditRecordArchitectureTest, AuditDeliveryArchitectureTest and the two framework
package inventory scripts and the twelve helpers above. The corrected exact25 paths
and sibling scope are listed in the Task. No changes to
the Accepted native D5O1 fixture; include it unchanged in the full suite.

Root freezes actual inventory counts/hashes and final native predecessor bytes
only after O1 Acceptance. Unit, real native+C3 DB and AST mutation checks own
separate fixtures; all shared gates are Root-serialized. One final frozen512M
full includes every O1 predecessor identity plus the recorded O2+F1 union when
the shared qualification plan is adopted; identify each child's exact own subset.
Both permanent Packets must name the same sibling file scope, input freeze and
serialized package handoff before Product work. Static/format/
identifier, package ordered inventory and negative controls are required.
Current dirty-tree inventory is not committed-SHA export; parent006 owns export.

Real DB proof: all four events plus paused/dryRun/no-event; zero-row completion;
interleaved tenantA/B/global raw subjects; strict malformed source; current versus
historical actor/correlation; deletion and tenant-metadata change after selection;
enabled successful progress through all three selectors/cursor renderings.
For each started/progressed/succeeded/failed root, inject append failure after
real C3 ingress write but before native commit; independent reads prove rollback
of that root's two native tables and C3 ingress, while earlier roots survive.
Fail append failure retains one best-effort fail attempt and the bounded delivery
error, no retry/finish; progress append failure retains the bounded cursor error,
no fail/finish/later Observer. A pre-C3 throw alone is insufficient evidence.
Also prove actual parent::commit then throw for all four emitted roots, with
no inference of success. Paused independently proves admission/state validation
and zero clock/HMAC/append/succeeded event. Preserve native earlier-root durability, resume
new-ID/no-backfill and original Record ID at-least-once behavior. Both native
tables and actual C3 ingress inspected from an independent connection. Include
wrong Connection, autoCommit=false and nested roots before prohibited effects.
Source unit negatives include UUIDv4/noncanonical casing, zero/negative native
sequence, uninitialized/corrupt consumed fields and mismatched cursor/record/time,
without narrowing the native timestamp range.

AST guard closes sole producer/implementation, exact optional propagation and
default null, SQL -> hook -> commit placements and no configured activation.
Freeze the actual Store.select decode/metadata-identity checks and returned raw
record lineage, exact Runtime loop variable, cursorFor(selector,same record), and
advanceSelected(same record,current binding.auditId), with native delivered fixed1.
A narrowly frozen select/cursor body, including only the authorized equivalent
tenant-parser inlining, plus resolved forwarding guard is sufficient;
this is a bounded installed graph, not arbitrary-PHP verification.
Negative controls must detect additional/alternate production advanceSelected producers,
reassignment, newly reconstructed shape-valid record substitution, projected/
previous subjects, selector/cursor input substitution or bypassed native decode/
tenant/record identity validation. Forged-record substitutions are source
negatives, not an impossible runtime claim about consistent arbitrary PHP calls.
Also reject writer-owned partition derivation/HMAC tenant substitution,
swapped Connection, skipped/dropped hook, wrong actor, wrong phase/count, event
before native SQL/after commit, and reintroduced fail-after-advance. Include
syntax-valid alias/case/named-null/inert text positives; no string-only proof.
Package controls retain the original three additions and cover each of the twelve
helper omissions and their ordered group, with the explicit serial handoff in the Task.


## Concrete owned argument inventory

- `Store.__construct`: `connection`, `protection`, `schema`, `codec`, `hook (new)`.
- `Store.begin`: `request`.
- `Store.advance`: `checkpoint`, `cursor`, `delivered`, `auditId`, `now`.
- `Store.advanceSelected`: `checkpoint`, `cursor`, `auditId`, `source`, `now`.
- All three native-root helpers: their closed `input` array and sensitive local helper arguments.
- `Store.finishInvocation`: `checkpoint`, `state`, `now`, `auditId`.
- `Store.fail`: `checkpoint`, `exception`, `now`, `auditId`.
- `Runtime.replay`: `request`.
- `Runtime.resume`: `checkpoint`, `actor`, `reason`, `batchSize`, `now`.
- `Runtime.deliver`: `request`, `targets`, `binding`.
- `Runtime.recordFailure`: `checkpoint`, `exception`, `now`, `auditId`.
- `Runtime.finalize`: `request`, `result`, `auditId`.
- `Runtime.release`: `checkpoint`.

This inventory preserves the actual Accepted O1 entry shapes and adds only the
explicit selected-source entry and enumerated helper frames above.
