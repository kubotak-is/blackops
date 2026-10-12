# Specification 131: Ordinary Audit Sign Service

Status: Authority direction adopted — exact Product implementation remains unissued

[D179](../decisions/179-audit-signing-authority-and-production-provenance.md) adopts this ordinary scheduler admission,
real Operation/policy/handler and Required-before-release contract. Online and
provenance acquisition follow [129](129-audit-online-authority-and-seed-custody.md) and
[130](130-audit-control-authority-and-production-provenance.md). The [F5 Task](../orchestration/tasks/P23-003F5-audit-signing-authority-production-and-seal.md)
owns exact selector/F4 continuation APIs,files and proof before issuance.
BlackOps1.3 remains unreleased; scheduler activation still requires real D-control.

## 1. Entry,identity and preparation grant

Use the existing Framework **`scheduler:run` / `scheduler:daemon` entry family**,
with one new final `OrdinaryAuditSignMaintenanceTask`, constructed only by
`ApplicationRetentionRuntime`. Both commands use the same factory/runtime and
`MaintenanceScheduler::runTasks()`; this adds no command or HTTP route. The chain is:

```text
ApplicationConsoleKernel -> ApplicationRetentionCommandFactory
 -> ApplicationRetentionRuntime -> MaintenanceScheduler
 -> OrdinaryAuditSignMaintenanceTask::run(now)
 -> closed OrdinaryAuditSignRuntime::runOne()
 -> trusted selection/preparation -> real AuthorizationEvaluator
 -> real HandlerInvoker/OrdinaryAuditSignHandler -> held bytes -> C3 -> F continuation
```

The task name is fixed `audit.ordinary_sign`; `run(now)` ignores its argument for
identity/trust/time authority. Each call handles at most one work item. It accepts
no actor, tenant, segment, key, metadata, envelope, target, allow flag or callback.
Calling a Framework scheduler **does not** make this control production. The
separate control task/entry retains its own origin and exemption.

Add one closed optional installation object `audit.signing.ordinary`:
`{principal:{type,id},tenant:null|{type,id},artifactKinds:[...]}`. Presence enables
composition; absence leaves this task unregistered. Exact keys only; principal is
non-null; both refs use existing reference validation; artifactKinds is a nonempty,
duplicate-free ordered subset of `[audit_checkpoint,audit_anchor_receipt]`.
No policy/handler/provider class-string, user option or default system principal.
Changes require the established stopped configuration/build/restart procedure.

Require both existing `ConsoleActorProvider` and `ConsoleTenantProvider` services
in the composed application container. Once per actual task invocation, call
`actor()` and `tenant()` exactly once and validate the returned refs. Provider
absence/type failure/exception/null actor fails closed **before selecting work**.
Null tenant is allowed only when the installed pin is explicitly null. Compare
both actual provider results exactly to the installed principal/tenant pin.
This pin grants that authenticated maintenance principal permission to reserve
preparation for its partition; it is separate from the later sign decision.
An unmatched principal cannot permanently reserve an F2 ID/time/key before denial.

The identity source is the configured Console provider, not the pin or command name.
Installation must supply its authenticated console/service identity; a provider that blindly
echoes an argument/environment actor label does not satisfy this prerequisite.
There is no new authentication protocol or deployment qualification claim. The existing
Console identity seam becomes mandatory. No ScheduledActorProvider, ScheduleContext
or ScheduledOperation occurrence is invented for maintenance.

## 2. Concrete composition and real Operation

Extend the same `ApplicationRetentionRuntime` F composition selected by Control
section 6. It owns one operations composition, one same-connection C3 composition,
one provenance recorder/delegate holder and distinct ordinary/control entries.
Build `ApplicationOperationRuntimeComposer::compose(snapshot)` once and retain
its container, clock, identifiers, connection, lifecycle and **authorization**.
Use `ApplicationAuditRuntimeComposer::compose(snapshot, operations, catalogs)`;
its coordinator and both stores already share `operations.connection`.
Only F delivery is composed; ordinary-D activation remains separate.

Construct a final fixed `OrdinaryAuditSignPolicy` from the validated installation
object and install it under that exact class ID in the existing Symfony runtime
container. Reject any pre-existing application definition/alias/service at that
ID; `Container::set()` failure also aborts composition. Verify `get(ID)` is the
same exact object before evaluation. This uses the existing runtime injection
pattern and the already-created evaluator's resolver/container; it neither
replaces the evaluator nor accepts an application-selected policy implementation.
No global container-compiler change or generic wrapper container is required.

Define a real final `OrdinaryAuditSignOperation implements Operation` with fixed
`OperationType("audit.sign.internal")`, `Accepts(OrdinaryAuditSignValue::class)`,
`Returns(HeldAuditArtifactOutcome::class)`, `HandledBy(OrdinaryAuditSignHandler::class)`
and `Authorize(OrdinaryAuditSignPolicy::class)`. Strategy is Inline; no transaction,
Route, ConsoleCommand or ScheduledBy attribute. Compile that exact definition with
existing `OperationMetadataCompiler::compile()`. Assert the whole expected tuple,
including the non-null policy, before any work. Do not add it to the application
discovery/transport registry, deserialize its Value or accept metadata from callers.

Value contains only the exact R9 target, constructed from the actual prepared
unsigned body using the matching F1 codec. The handler is real: it signs or
recovers the bound complete candidate, performs the required immutable persistence
and returns `OperationResult::completed(HeldAuditArtifactOutcome)`, or a rejection.
Outcome implements `Outcome`; it privately holds the same bytes/kind/target for
this call. No public serialization/JSON/debug byte surface or application service
registration. Keep sensitive owned data under the existing safe-representation
pattern. Exact internal accessors are only for this runtime's release path.

Use `ExecutionContextFactory(operations.identifiers, operations.clock)` to receive/start
attempt 1 for this **actual new service invocation**, with
`ActorContext(actualPrincipal,actualPrincipal,ActorRef("console-runtime","system"))`
and the actual tenant. These are service-attempt IDs, not claimed Journal records.
Run inside `operations.lifecycle`; accept no envelope or `scope.current()` as authority.
After preparation, construct one envelope for both evaluator and invoker; no second
context merely for authorization or fake outer Operation.

This internal service directly executes evaluator and handler, **not InlineDispatcher**:
no Dispatcher state machine, Journal lifecycle, automatic authorization audit,
observer, retry or transactional Operation behavior is claimed. Lifecycle supplies cleanup;
existing F owners retain their separate root transactions. Fixed handler instances
are created only after allow/requested success, capture that envelope and its
prepared work privately, and accept no reusable authorization/capability object.

## 3. Preparation, exact policy and invocation order

1. Validate composition/admission, start the real service attempt, derive the
   partition with `AuditPartitionIdDeriver` from the actual tenant. A new F-owned
   selector is required; no existing ready queue or public F2 getter is assumed.
   Resume authenticated pending ordinary work in that partition before new work:
   accepted receipt continuations first (intent ID order), then checkpoint work
   (firstSequence, segmentId order). Otherwise select the earliest closed unsealed
   segment by firstSequence/segmentId, with its immediate predecessor already
   complete. Never skip a blocked earlier checkpoint, infer control from contents,
   accept a target argument, or create an ordinary reservation over existing
   control/unattributed work. No eligible work yields a byte-free no-work result.
2. New checkpoint work acquires current online authority, commits the ordinary-only
   signed reservation with its selected ref/fingerprint, then calls **existing F2
   prepare**. Recovery authenticates the existing reservation and uses its original
   key. F2 alone samples its one new-preparation Clock/UUID; replay samples neither
   and retains its immutable ID/time/body/key. A preparation commit ambiguity returns
   no target. Never create a replacement ID/time/key or claim storage replay proves
   current trust. The actual unsigned hash is unavailable until this step returns.
3. Receipt work uses F4's authenticated restored A5 accepted intent and exact
   checkpoint/triple/version/receipt slot. Reserve after external acceptance and
   before A5 inputPersist, passing the acquired anchor ref to that owner. It returns
   the frozen real unsigned receipt before evaluation. Do not apply “F2 absent” or change
   A5's wire, claim/Clock ownership or hashes. Automatic receipt class inherits the
   checkpoint; the ordinary task cannot manufacture a control receipt. Its concrete
   F4 adapter binding must be fixed with the F4 packet before implementation.
4. Build `{schemaVersion:1,kind:"audit_sign_target",artifactKind,partitionId,
   segmentId,artifactId,artifactHash}` from that body; artifactHash is F1's unsigned
   hash, not complete-byte SHA. Capture requestedAt once from the trusted Clock
   after preparation, canonical UTC microseconds, and `bin2hex(random_bytes(16))`.
   Failure before a valid target/context exists emits no invented sign Draft.
5. Call `operations.authorization.evaluate(compiledMetadata, sameEnvelope)` once.
   The fixed policy validates exact Operation/Value classes, target/tenant partition,
   installed principal/tenant and allowed artifact kind; valid eligible targets in
   that pinned partition are its explicitly granted resource set. It returns the
   real AuthorizationDecision through the existing evaluator. Missing/null/wrong
   metadata policy is admission denial, never evaluator-null allow. A non-null
   RejectionReason means deny; a resolver/policy exception means unavailable; null
   means allow only after the mandatory fixed-binding checks. No target probing or
   key/seed/sign/persist follows deny. Previously committed preparation remains.
6. Freeze the post-decision context below; commit Durable requested. Deny or
   unavailable then commits Durable unavailable and returns byte-free failure.
   Allow invokes the exact handler via `HandlerInvoker::invoke(metadata,handler,
   sameEnvelope)`. The handler compares identity/target with its captured work,
   reacquires trust before seed/sign and before persistence, and preserves the
   online/control candidate-before-artifact/outcome ordering. F2 receives exact
   complete bytes and its fresh snapshot; receipt persistence uses the real F4
   receipt claim. Never accept a caller's complete bytes or unsigned proof.
7. Inspect `OperationResult::isRejected()` **before** reading an outcome. Rejection,
   wrong outcome, exception, current-trust/binding failure or persistence ambiguity
   releases no bytes. Handler rejection after allow keeps authorizationDecision
   **allow** and that same requestRef; append unavailable, never rewrite it to deny.
   No second evaluator/handler call or automatic retry inside this attempt.
   Deny/unavailable returns no success result: throw a finite sanitized sign failure
   after terminal delivery, fixed message/code and no previous exception/raw data.
   No-work returns count 0/`no_work`; success returns count 1 and artifact-kind status.

## 4. Fixed audit identity and held-byte release

Context is the separately versioned,closed `audit_sign_context`:

```text
{schemaVersion:1,kind:"audit_sign_context",actor,tenant,purpose:"sign",target,
 authorizationDecision,requestedAt,nonce,requestRef}
```

Actor is exactly `{principal,execution}` from this same execution context,with
both members validated non-null existing typed references. Tenant is the same
Console provider's validated null-or-typed reference. This explicitly replaces
R9's preliminary single-reference actor choice; the target and hash algorithm
are unchanged. The target's partition equals the partition derived from this
same tenant. Decision is exactly allow/deny/unavailable,requestedAt is the owned
canonical UTC-microsecond sample,and nonce is32 lowercase hex from16 CSPRNG bytes.
No extra keys,coercion,serialized allow or caller-supplied context is admitted.
Only the real evaluator path constructs this post-decision context.
Use the specified post-decision formula, unchanged:

```text
oid:v1: + lowerhex SHA256(
 LP("blackops.audit.access.request-ref.v1") ||
 LP(JCS({purpose:"sign",actor,tenant,target,authorizationDecision,requestedAt,nonce})))
```

All three Drafts use action.id=requestRef, this actor/tenant, occurredAt=requestedAt,
policy.id=`audit-sign`, policy.version=1, source owner Framework,
component=`audit-sign`, runtime=`scheduler`, trusted operations.applicationBuildId,
empty correlation, and evidence `{version:1}` only. Resource type is `audit_trail`;
resource.id is existing Resource HMAC of
`audit.sign.v1:<artifactKind>:<unsignedHash>`. Use the accepted exact Catalog tuples:

| Event suffix | Outcome / policy | Delivery and key |
| --- | --- | --- |
| requested | unknown/audit_access_requested; not_applicable/audit_access_requested | Durable; `audit.sign.v1:requested:<requestRef>` |
| unavailable | unknown/resource_unavailable; not_applicable/resource_unavailable | Durable; `audit.sign.v1:unavailable:<requestRef>` |
| accessed | succeeded/audit_accessed; allow/authorization_allowed | Required; null |

Action type is the full event name; phases are respectively requested, unknown,
succeeded. The fixed builder uses accepted selector/coordinator and cannot receive
arbitrary Drafts. Retransmission may reuse a Durable key only with the identical
in-memory Draft; restart/new invocation reevaluates with fresh time/nonce/reference.
Never resume an allow from a persisted requestRef or Required record lookup.

Only the closed runtime holds the handler's complete outcome. Revalidate the exact
held bytes against the authorized kind/ID/partition/segment/unsigned hash and the
matching receipt/checkpoint pair; reacquire and verify current trust immediately
before release. Revalidate the actual F4 claim/call marker for an external call.
Call `delivery.deliverStandalone(accessedRequiredNullKeyRequest)` on the same
physical connection after all preparation/persistence roots are closed. Only its
normal committed return permits the fixed F4 checkpoint external-submission
continuation, or handing the receipt result to its exact F4 acknowledgement
continuation. No application callback/output path can obtain the held outcome.
The scheduler receives only fixed counts/status, never bytes. Receipt persistence
before this barrier is internal durability, not an external release claim.

This covers both artifact kinds and every later ordinary file/response/export/
external-anchor handoff, including ordinary access to already control-produced
bytes: each requires a fresh ordinary invocation and this barrier, without changing
production origin. Future G consumers need exact closed caller integration; this
contract does not add an export endpoint or grant them an access-token API.
Required append/post-check/commit ambiguity has precedence over a terminal audited
denial: throw the finite sanitized audit failure; no unavailable retry on a broken
connection, no catch/finally output, no durable downgrade, no exactly-once claim.
Unavailable delivery failure surfaces as audit failure. Preserve original decision/context;
only normal return from both Durable deliveries permits an audited-denial claim.

## 5. Required implementation joins and verification

The installed matching principal is explicitly granted immutable preparation
before exact-target sign evaluation. Artifact kinds govern signing; denial does
not undo a previously committed preparation. Exact selector,phase recovery,F4
receipt APIs and both fixed post-Required continuations must be frozen together
with files,caller maps,migrations,resources and gates. No generic callback or
fixture-only target/Operation may replace these joins.

Required later proof: real built application scheduler/provider/policy/handler path;
unmatched/missing identities before preparation; F2 replay Clock/UUID counts; exact
post-prepare target and same-envelope evaluation; missing-policy denial; both kinds;
handler rejection after allow; request-ref vectors/execution substitution; exact
Durable retry/conflict; actual zero emitted bytes/external calls on Required append,
post-check and commit-response loss; restart/new authorization/current revocation;
receipt/F4 and ordinary/control origin isolation; exact caller/package/strict/full
gates and independent Transaction/Security review.

Public configuration and Internal architecture documentation must be qualified
with the implementation. Whole F,actual D-control ingress/activation,F6,G and
parent release gates remain mandatory; this contract proves no runtime result.
