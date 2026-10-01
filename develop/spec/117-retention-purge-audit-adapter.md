# Specification 117: Retention Purge Audit Adapter

Status: Accepted (local) — D5P source/evidence Green; BlackOps 1.3 unreleased

## Boundary and dependencies

This is the D5P purge child under specifications38/39/105/106 and D154/D166.
D5H hold place/release is locally Accepted and unchanged. D5P consumes Accepted
C/D1 and explicitly depends on Accepted D4B only for the coordinator's pure
`usesConnection()` identity predicate. It does not consume D4 evaluation types
or D5H Product helpers. Rotation, replay, generic Outbox, ordinary composition
and post-F control remain separate children.

Add three final non-PublicApi Internal types: `AuditedRetentionPurgeService` and
`AuditedRetentionPurgeAuditPort` under Internal/Retention, and
`RetentionPurgeAuditWriter` under Internal/Audit/Retention. Preserve Core Ports,
native planner/deleters/stores, Catalog bytes, schema/migrations, configuration,
CLI/Scheduler and canonical Application composition. No current caller selects
the new facade. Existing operation-data deletion is distinct from future deletion
of sealed Audit data/keys/manifests under P23-003H.

The canonical PostgreSQL schema is a prerequisite. In particular, operation IDs
are unique/non-null where native delete paths require exactly one affected row,
and tenant metadata obeys its paired-column constraints. This adapter is not a
schema verifier or a defense for databases whose required constraints were removed.
The existing native `count !== 1` behavior cannot provide audit coverage for
malformed-schema multirow deletions; changing it requires a separate native-source
scope. Journal intentionally supports multiple affected rows.

## Construction and trusted binding

The facade implements the existing RetentionPurgeService signature. Its constructor
receives, in order, Connection, RetentionPurgeAuditWriter, PSR LoggerInterface,
schema='blackops', ClockInterface with PostgreSqlSystemClock default, and
PostgreSqlRetentionPurgeAuditIdGenerator with SymfonyRetentionPurgeAuditIdGenerator
default. It privately constructs one concrete hook and the unchanged native graph:

```text
PostgreSqlRetentionPurgeService(
  PostgreSqlRetentionPlanner,
  PostgreSqlTransportPayloadTombstoneService,
  PostgreSqlOutcomeRetentionDeleteService,
  PostgreSqlDeadLetterRetentionDeleteService,
  PostgreSqlJournalRetentionDeleteService,
  PostgreSqlIdempotencyRetentionDeleteService
)
```

Every native component shares the exact Connection/schema. The five mutation
services share the supplied native clock and ID generator and the same hook.
Constructor argument order above differs from execution order below. The optional
native idempotency service is always supplied by this facade. Native planner
limits/defaults remain unchanged; the facade accepts no caller-supplied plan,
native service, arbitrary audit Port or store. Construction does no SQL, clock
read, native ID allocation or Audit delivery.

The hook implements RetentionPurgeAuditPort and receives Connection, concrete
writer, PSR logger and schema='blackops', in that order. It privately constructs
the existing schema helper and
LoggingRetentionPurgeAuditPort(PostgreSqlRetentionPurgeAuditStore(Connection,schema),logger).
It exposes no mutable dependency/native graph. Canonical Application composition
currently uses the native DB audit store directly; this explicit opt-in facade
selects the existing logging decorator as part of its own fixed graph.

Writer constructor order matches the established hold writer's binding shape:
HmacAuditOpaqueIdDeriver, AuditDeliverySelector, AuditDeliveryCoordinator,
ClockInterface, ActorContext, AuditRecordCorrelation, AuditSourceRuntime, build ID.
Public event methods are `assertConnection(Connection): void` and
`committed(RetentionPurgeAuditId, OperationId, RetentionPurgeTarget, ?TenantRef): void`,
plus safe debug/serialization hooks. Private helper names are implementation
details, frozen with the final source graph. No new public Port or allocator.

The trusted composition caller supplies actual actor/execution/correlation/runtime/
build provenance and owns one binding for one purge invocation. All its effects
may share that binding; do not share it across distinct actor/execution scopes.
Principal is ActorContext.authorization() or null; execution is ActorContext.execution().
An explicit null principal or empty correlation is valid. The writer reduces
these values to a bounded immutable snapshot. Legacy RetentionActorRef, policy
string and caller-supplied cutoff time do not authenticate actor/execution or
become Audit provenance. Administrative authorization remains the caller's duty.
C3's record/outbox dependencies must obey trusted same-Connection composition;
`usesConnection()` checks the coordinator field, not arbitrary recursive graphs.

## Native roots and per-effect subject

At facade entry, before native planning, require auto-commit enabled, nesting0
and writer/C3 exact Connection identity. Reject ambient/nested/non-autocommit or
wrong-Connection use before SQL, native IDs/clocks or writer clock work. Delegate
once without an outer transaction. The native planner builds the whole plan first.
Malformed planning fails before any category mutation. Category execution order is
transport payload, dead letter, outcome, journal, idempotency; each owns its
existing separate root over that category's planned items, including empty roots.

The hook is called only for positive native effects. It requires nesting1,
auto-commit enabled and exact writer Connection before subject SQL or delivery.
It owns no begin/commit/rollback/savepoint and performs no retry. Only the writer
targets C3. Native source ownership remains:

| Target | Native effect and event unit | Native clock/ID ownership |
| --- | --- | --- |
| transport_payload | One operations row tombstoned; payload and context cleared together; one record/event, count1 | Clock per attempted item; ID/hook only after exactly one effect |
| dead_letter | One deleted row per operation/tenant; one record/event, count1 | Clock per attempted item; ID/hook only after exactly one effect |
| outcome | One deleted row per operation/tenant; one record/event, count1 | Clock per attempted item; ID/hook only after exactly one effect |
| journal | All matching journal rows for one operation/tenant and unchanged planned basis; one record/event with actual positive row count | Clock/ID/hook only after positive effect |
| idempotency_record | One expired terminal row per operation/tenant; one record/event, count1 | Clock/ID/hook only after exactly one effect |

All native hold predicates, state/cutoff checks and planner limits remain.
Native purge never deletes an operations row. Result counts remain native row
counts; a multirow Journal event is not multiple events, and no event represents
success of the whole purge invocation. No-effect paths emit no Audit event and
read no writer clock; native clock behavior in the table remains unchanged.

For transport payload only, the native record omits tenant. Treat it as incomplete
source metadata, not proof of global tenancy. After its successful UPDATE, use one
bounded parameterized SELECT from the schema helper's operations table in that
same root, keyed by the record's OperationId. Select exactly operation_id cast to
text, tenant_type and tenant_id. Require one row, exactly those keys, the exact
canonical returned OperationId and either null/null or two nonempty canonical
strings. Reject missing, mismatched, partial, whitespace-normalized, control/NFC/
length-invalid identities; do not normalize or fall back to global. The updated
row survives and its write lock protects this read within the root.

Rebuild the native RetentionPurgeAuditRecord with that persisted tenant, preserving
its ID, operation, target/count, policy, native timestamp and legacy actor. The
corrected record is used for both the new event and legacy store/log. This fixes
identity in the explicit adapter, leaving the original native class unchanged.
It does not add the planned tenant to the native UPDATE predicate, authorize a
tenant-scoped purge or strengthen hold-versus-purge locking.

For the other four targets, use record tenant without an operations lookup:
their DELETE binds operation ID and both tenant columns with IS NOT DISTINCT FROM.
Positive effect under the canonical schema proves that plan tenant matched the
deleted source. This supports Inline Journal and rows whose Operations source is
absent. No deleted-payload decoding, arbitrary subject callback or extra source
scan is permitted. Validate record ID/OperationId/target/positive native count and
canonical typed tenant before any writer clock; reflected/corrupt values fail closed.

## Exact event and identity projection

Use only Accepted `blackops.framework.operations` v1, owner framework, hash
`9b04a5af68feb9091903282a5d99309bcd7a52cd0cbe2eb9e2901bfacbea083b`.
Definition `retention.purge.committed` is event/evidence v1, category retention,
action type retention.purge.committed, phase committed, resource type operation_data,
outcome succeeded/retention_purged, policy decision not_applicable and reason
retention_purged. Delivery is always durable. Evidence is exactly `{"version":1}`.

Policy is fixed id `blackops.retention.purge`, version1, Durable; source is
framework/component `blackops.retention.purge` with explicit runtime/build.
Legacy retention policy identifiers, actor strings, row counts, cutoff/native
timestamps and payloads do not enter logical Audit JSON. Their existing bounded
operational record remains a separate legacy surface; its ID is not an execution ID.

The native source already allocates a RetentionPurgeAuditId before calling the
hook. It is the shared native-generated identity before either insert, not an ID
returned by already-persisted storage. Validate both IDs by canonical typed
roundtrip, then derive with the concrete HMAC deriver:

```text
Resource purpose input = retention-purge:<target.value>:<operationId>
Action purpose input = retention-purge:retention.purge.committed:<nativeAuditId>
Durable idempotency key = retention-purge:<actionId>
```

Each native positive-effect record supplies one action; Journal's grouped deletion
shares one action. Different targets for the same operation get distinct resources.
There is no whole-run ID, extra action/execution UUID or replay registry. C3 keeps
record/outbox ID allocation ownership. A new successful native effect may receive
a new native ID; no exactly-once purge/recovery guarantee is added.

The writer validates binding, IDs, enum, exact Catalog definition and AuditTypedReference
before capturing its clock exactly once. The captured value supplies Audit occurredAt,
independent of native clock/caller cutoff. It builds one draft/request, calls the
Accepted selector once and deliverInCurrentTransaction once. It performs no SQL,
transaction management, logging, native store call or standalone delivery.

## Ordering, failure and privacy

Within each native hook, sequence is: owned-root/identity checks; bounded subject
validation/hydration; writer committed; legacy logging decorator record. That
decorator inserts through its private same-Connection native store, then logs.
Both database audit surfaces and deletion commit together in the existing category
root. Reuse the normalized record for legacy persistence; no parallel delivery.

Native mutation/clock/ID failure before hook performs no writer work. Invalid
source/binding fails before writer clock. Writer clock/projection/selector/C3
failure skips legacy store/log and rolls back the current category. Legacy INSERT
or logger failure after C3 delivery propagates and rolls back that category's
effects and both database audit surfaces; clock/ID allocations already attempted
are not claimed reversible. Earlier items of a failed category roll back together.
Previously committed categories survive and later categories are not started.

A successful external System Log cannot be rolled back if a later item or root
commit fails. It retains existing `retention.purge.completed`/`kind=audit` meaning
and is not relabeled as the canonical new AuditRecord or proof of final commit.
The new event is not written to the source's Lifecycle Journal.

Distinguish confirmed precommit rollback from actual commit-then-throw. In the
latter case the completed root's mutation/native audit/C3 rows remain; the response
fails and no blind mutation/append retry occurs. A rollback attempt may mask the
original commit exception in native code; do not promise exception identity or
that every thrown commit rolled back. Controlled commit-then-throw tests are not
process/network fault or exactly-once recovery proofs. A fresh invocation replans
current data; it is not automatic reconciliation of an ambiguous prior response.

Exercise both native root families with real PostgreSQL `parent::commit()` followed
by a controlled throw: the manual dead-letter root and DBAL transactional outcome
root. The former must preserve the earlier transport root and current dead-letter
effect/native audit/C3 ingress; the latter must preserve earlier transport/dead-letter
roots and the current outcome triple. Pin exact per-target persisted rows and
untouched later category rows, and assert no later mutation/clock/ID/delivery work
or retries. Initial plan queries are intentionally earlier and are not later
category mutation work. Keep actual rollback behavior after the throw; a double
that merely suppresses rollback cannot be the evidence for either root family.

New facade/hook boundaries map failures to value-free DeferredTransportException
without retaining the original exception chain. Writer preserves only bounded
AuditDeliveryFailure classifications, similarly discarding raw causes. Protect
raw inputs/object graphs crossing owned frames with SensitiveParameter and sealed
SensitiveParameterValue snapshots. Debug, serialization, cloning/unserialization
and corrupt snapshots must not disclose graphs or turn invalid state into success.
No raw native IDs, legacy actor/policy/cutoff, payload or source Throwable enters
logical Audit JSON. The legacy table/log retain their own established field and
masking contract. Unmarked external caller/provider/native frames are outside the
owned-frame privacy guarantee; test actual owned traces rather than exporting an
arbitrary full exception graph.

## Graph, validation and release

Facade is the sole Production constructor/caller of the new hook; hook is the
sole Production caller of writer.committed. Facade and hook may call writer's
Connection assertion; neither references C3 types. No current Production caller
constructs the facade or writer. Provider references become exact lookup + existing
hold writer catalog helper + new purge writer catalog helper; Catalog bytes and
trusted lookup lineage remain unchanged. Guard all instance/nullsafe/function/
static/new call kinds and native construction/subject SQL ownership, with bounded
syntax-valid negative fixtures and inert/alias/case positives. This is a closed
reviewed source graph, not universal analysis of arbitrary PHP.

Validation includes real canonical PostgreSQL schema, independent probe reads,
all five target units, global/tenant/Inline/no-source subjects, holds/no-op/cutoff,
same-category rollback versus earlier-category persistence, logging/store/C3 faults,
actual committed-then-throw without retry, malformed subject/privacy and exact
key/time/binding projection. Preserve3,551 predecessor identities plus the exact
new set in one frozen full. Exact scope/gates and reuse rules are in the D5P Packet.

No Core/native API, composition, command/config, public capability or release is
activated here. Stable1.2.1 remains the Release Authority;1.3 is unreleased.
Public guide/internal explanation, local user review and delivery remain later
integration/release work. This child does not complete parent D or BlackOps1.3.
