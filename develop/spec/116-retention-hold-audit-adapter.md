# Specification 116: Retention Hold Audit Adapter

Status: Accepted (local) — implementation and verification complete; BlackOps 1.3 unreleased

## Boundary and dependencies

This is the most-specific D5H contract under specifications38/39/105/106 and
D147/D154/D165. D5 retention is split into hold place/release (this child) and a
later purge child. Rotation, observer replay and generic Outbox remain separate.
D5H additionally depends on locally Accepted D4B solely for the C3 coordinator's
pure `usesConnection()` identity predicate. D165 makes this DAG edge explicit.

Add two final, non-PublicApi Internal types: `AuditedRetentionHoldPort` under
Internal/Retention and `RetentionHoldAuditWriter` under Internal/Audit/Retention.
Keep Core RetentionHoldPort, the existing PostgreSqlRetentionHoldStore, schema,
Catalog bytes, Deptrac layers, configuration and canonical composition unchanged.
The new adapter is an explicit internal composition surface; no current caller
or default registration selects it. Activation remains D-integration.

## Construction and trusted binding

The adapter implements the existing RetentionHoldPort methods. Its constructor
receives Connection, RetentionHoldAuditWriter, schema name and the existing native
Clock/RetentionHoldIdGenerator dependencies, with the native defaults for the last
three arguments. It privately constructs PostgreSqlRetentionHoldStore and the
table-name schema helper using the exact same Connection/schema. It accepts no
arbitrary injected Port/Store and exposes no wrapped store or mutable dependency.
Construction performs no SQL, clock read, ID allocation or Audit delivery.

The writer constructor receives concrete HmacAuditOpaqueIdDeriver,
AuditDeliverySelector, AuditDeliveryCoordinator, ClockInterface, ActorContext,
AuditRecordCorrelation, AuditSourceRuntime and build ID. The trusted composition
caller supplies actual actor/execution/correlation/runtime/build provenance and
owns one binding per source invocation. Multiple hold mutations within that same
binding are allowed; sharing it across distinct actor/execution bindings is not.
This child supplies no context resolver, ambient state, service locator or new
canonical binding owner. D-integration must name that owner before activation.
Typed input validation is not authentication or proof of correct binding scope.
The C3 dependencies must use the existing trusted composition contract, including
same-Connection record/outbox stores. The identity predicate checks the coordinator's
Connection field; it does not recursively certify arbitrary injected dependencies.

The writer reduces the actor/correlation values to a bounded immutable snapshot;
it retains no ExecutionContext, operation payload, RetentionHold, reason, legacy
RetentionActorRef or original Throwable. Principal is ActorContext.authorization()
or null; execution is ActorContext.execution(). An explicit null principal or
empty AuditRecordCorrelation is valid. Neither a subject OperationId nor a
free-form placedBy/releasedBy reference is inferred to be execution provenance.
Hold authorization remains the caller's obligation from spec39; this adapter
neither grants authorization nor claims to verify the legacy actor string.

All object graphs/raw inputs crossing owned privacy boundaries use sensitive
parameters/storage and value-free exceptions. Corrupt snapshots, debug output and
serialization must not expose binding data or turn a malformed graph into success.
Unmarked caller/provider trace frames are outside the owned-frame guarantee.

## Exact event projection

Use the Accepted `blackops.framework.operations` Catalog v1/hash
`9b04a5af68feb9091903282a5d99309bcd7a52cd0cbe2eb9e2901bfacbea083b`.
Only the following exact definitions are emitted; both are event/evidence v1,
retention/committed, resourceType operation, outcome succeeded and durable.

| Mutation | Event/action type | Outcome code and policy reason |
| --- | --- | --- |
| successful place | retention.hold.placed | retention_hold_placed |
| successful release | retention.hold.released | retention_hold_released |

Policy is fixed id `blackops.retention.hold`, version1, not_applicable and Durable.
Evidence is exactly `{"version":1}`. Source owner is framework, component
`blackops.retention.hold`, with the explicitly bound runtime/build. No retention
category, reason, legacy actor string, caller timestamp or payload enters evidence.

Resource ID uses HmacAuditOpaqueIdDeriver with purpose Resource on the returned
hold's OperationId string. Action ID uses purpose Action on
`retention-hold:<event.type>:<holdId>`; this identifies the persisted hold phase,
not an inferred execution. The durable idempotency key is `retention-hold:<actionId>`.
Different phases/holds get distinct action IDs. No new action/execution UUID is
allocated. Native hold IDs retain their existing generator/clock ownership; C3
record/outbox IDs retain their existing store ownership.

Writer `placed(holdId, operationId, tenant)` and `released(...)` are the only event
entry methods. Each captures its clock exactly once, after a successful native
mutation and trusted subject read. It constructs the draft/request, obtains the
plan through the Accepted selector and calls deliverInCurrentTransaction once.
There is no standalone append, required-mode override, fallback or append retry.
The fixed Catalog provider is referenced only in one writer-owned pure helper;
the existing ImmutableAuditEventCatalogLookup reference remains unchanged.

## Actual mutation and tenant boundary

Before any mutation, ID/clock call or SQL, the adapter validates writer/C3
Connection identity and requires auto-commit enabled, no active transaction and
nesting level0. Ambient, nested, auto-commit-disabled and distinct-Connection
composition fail before work. It then owns one Connection transactional root.
Inside the callback the root must be active at level1 with auto-commit enabled.

Within that exact root: invoke the private native place/release; obtain its hold;
read operation_id, tenant_type and tenant_id from the persisted hold row by its
hold ID; validate the subject; append the corresponding durable event; return the
hold only after the owned root commits. The subject SELECT uses the table helper
constructed from the exact same schema name as the private native store.
It is the adapter's sole new SQL statement; it does not copy native mutation SQL.

The stored operation ID must match the returned hold. Missing rows, partial-null
tenant pairs, invalid/noncanonical values or mismatches fail and roll back the
root. Only an existing row whose two tenant fields are null yields a global null
tenant. Non-null tenant values are preserved exactly through a typed TenantRef;
trimming/coercion may not silently change stored identity. Place retains native
canonical subject checks; release uses the hold row's persisted tenant, even if
the original operation/journal rows are no longer available.

`activeFor()` delegates to the private native store with no mutation root or Audit
event. It does not become a new audited-read capability. Existing unaudited native
construction and public signatures/default behavior remain exact.

## Failure, repeat and concurrency semantics

Confirmed pre-commit failure in native mutation, tenant hydration, projection,
plan selection or append rolls back both hold mutation and durable ingress.
The audited adapter withholds the RetentionHold and exposes a fresh fixed-message
DeferredTransportException without retaining the caught Throwable. It preserves
the public result shape/exception type; detailed error text of this new Internal
path is not the legacy Store's error-text contract. Native default behavior is
unchanged. Rollback/commit failures must not be misreported as confirmed rollback.

A commit-then-throw can leave both rows committed while the response fails. Never
blindly repeat the append or mutation, invent a failed phase, or claim exactly-once
recovery. An explicit later place remains a new native hold/action; an explicit
later release of an already released/missing hold fails without another event.
There is no new idempotency/replay API or repair of inherited ambiguous commits.
The stable action/key does not make a later clock capture an equivalent retry:
the Accepted outbox requires the complete logical draft to match on duplicate keys.
Tests distinguish pre-commit failure, acknowledged success and committed-then-
throw by independently reading actual PostgreSQL rows. Controlled injected faults
are not network/process-kill evidence or universal exception-identity guarantees.

This child does not alter hold-versus-purge locking, add a purge-wide root or claim
a stronger concurrent deletion guarantee. The later purge authority owns its
actual batch/root/concurrency contract. Audit delivery joins the hold mutation;
external logs and side effects are not made transactional by this adapter.

## Closed graph and verification

The adapter's domain/dependency graph may construct only the existing native
Store/schema helper and typed subject values, inspect/use its owned Connection
and call the writer's explicit identity/placed/released methods. Bounded supporting
construction also permits SensitiveParameterValue snapshots, fresh fixed-message
DeferredTransportException and the native system-clock/hold-ID-generator defaults.
It references no C3 orchestration/store/selector type and performs no direct Audit
projection or Outbox SQL.

The writer owns only projection, one exact operational Catalog helper, plan
selection, C3 Connection identity and current-root delivery. It owns no transaction,
native hold SQL, handler invocation or Application/runtime registration. Extend
Accepted A2/C3 owner/type/method allowlists only for these exact dependencies;
retain all old negative controls. The operational Catalog's former one-reference
rule expands only to the existing lookup call and this writer helper. No whole-
file/directory exemption, self-derived authority map or unrelated caller allowance.

New architecture evidence must cover both concrete shapes/non-PublicApi status,
closed references/callers, no activation, private native construction/Connection
identity, bounded SQL ownership and constructor purity. Use syntax-valid targeted
negatives with aliases, qualified/case-insensitive and dynamic/indirect references
where relevant. Do not add a new unbounded mutation Cartesian product.
Package inventory gains exactly these two types,107→109, preserving existing
omission/call-contract controls and adding one omission check for each new type.

Focused units/native/architecture checks precede independent source/security
review. Freeze before one full; preserve all3,513 accepted D4B test identities.
Reuse unchanged successful gates and do not repeat full subsets at final closeout.

## Release boundary

Proposed internal capability audit.retention_hold_delivery.v1 only. Stable
Framework/Skeleton/site remain Experimental1.2.1;1.3 is unreleased. No config,
public registration, schema, CLI or public documentation behavior changes here.
This specification documents the new internal architecture; public guide/internal
site updates wait for canonical composition and its user-facing semantics.
D5 purge/rotation/replay/generic Outbox, ordinary composition, E/F/G, D-control
after Accepted F, hardening and release remain. The final documentation candidate
must run locally and receive user review before release.
