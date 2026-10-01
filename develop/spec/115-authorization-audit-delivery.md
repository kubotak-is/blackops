# Specification 115: Authorization Audit Delivery

Status: Accepted (local) — D4B; canonical activation pending; BlackOps 1.3 unreleased

## Boundary

This is the most-specific D4B contract under specifications105/106/114 and
D147/D154/D163/D164. D4A's four evaluation types remain unchanged. D4B prepares
their authorization events and integrates explicit optional Internal dependencies
into InlineDispatcher, DeferredAcceptanceOrchestrator and DeferredWorkerRuntime.
The seven existing production constructor call sites pass no new dependency.
Canonical registration/configuration remains D-integration; this is not a public
feature or a claim that Stable1.2.1 emits these events.

No new Core type, public API, policy attribute, config key, provider interface,
migration, dependency, catalog version or public documentation claim is added.
Compiled metadata and explicitly configured policy bindings remain trusted inputs;
these Internal types do not authenticate arbitrary caller-created metadata.

## Event projection

Three final Internal types join the four accepted evaluation types in
`BlackOps\Internal\Audit\Authorization`: `AuthorizationAuditEventFactory`,
`AuthorizationAuditCoordinator`, and `AuthorizationAuditAttempt`.

The factory constructor requires HmacAuditOpaqueIdDeriver, BlackOps\Identifier\Uuidv7Generator,
ClockInterface, AuditSourceRuntime, build ID and ActorRef execution fallback, in
that order. All are explicit trusted composition inputs, with no defaults. The
fallback is used only when the ExecutionContext has no ActorContext; an existing
execution actor is preserved. It never supplies the authorization principal.

`create(AuthorizationAuditEvaluation, ExecutionContext): ?AuditDeliveryRequest`
returns null for no-policy before UUID, clock, context projection or catalog work.
For a real decision it revalidates/reconstructs bounded policy, actor and tenant
values; an instanceof check alone does not establish their invariants. Contradictory
evaluation states or invalid public rejection categories are PolicyInvalid.

| Field | Exact source/semantics |
| --- | --- |
| event | Existing Framework security/access catalog, exact authorization allow/deny definition; durable event/evidence1, required event/evidence2 |
| action | Type equals event type; succeeded for allow, denied for deny; fresh per-evaluation UUIDv7, domain-separated opaque Action ID |
| resource | Type operation; opaque Resource ID derived from context Operation ID |
| actor | Principal is context authorization actor or null; execution is actual context execution actor or explicit fallback |
| tenant | Typed context TenantRef copied exactly, or null; no inference from actor/payload |
| outcome/policy | Fixed authorization_allowed or authorization_denied; evaluated explicit policy ID/version/delivery, never response rejection code |
| source | Framework owner, component `operation-authorization`, explicit runtime and build ID |
| correlation | Exactly operationId, correlationId, causationId; nullable causation stays null; no payload, trace or invented attempt fields |
| time | One Clock reading per prepared decision, canonical UTC microsecond timestamp |
| evidence | Only `{ "version": 1 }` or `{ "version": 2 }` |

The catalog's30 definitions and hash
`cfbcb3335b27ac4506046216d7ff43a9802586b1e60fbb9cfc47dc12230b1d83`
remain unchanged. Policy version is independent of event/evidence version.
Required requests have null idempotency key; durable requests use
`operation-authorization:` plus the opaque Action ID. Repeated evaluations and
Worker retries have fresh action IDs/keys, including for the same Operation ID.
An Outbox relay retry retains the already persisted event identity.

## Prepared decision and delivery boundary

Coordinator constructor dependencies are the accepted AuthorizationAuditEvaluator,
new factory, AuditDeliverySelector, AuditDeliveryCoordinator and Framework
Connection, in that order. `evaluate(OperationMetadata, OperationEnvelope)` invokes
the accepted evaluator exactly once and returns a new attempt-local
AuthorizationAuditAttempt. It builds/validates the request and plan only for a real
policy. It never calls the old evaluator a second time or owns a transaction.

Attempt holds only evaluation, nullable request/plan and fixed delivery/Connection
dependencies in a protected snapshot; it retains no metadata, envelope, context,
payload, handler, closure or exception. Its ordinary boundary methods are:

```text
evaluation(): AuthorizationAuditEvaluation
assertMutation(OperationMetadata, ?OperationTransactionCoordinator): void
deliverStandalone(): void
deliverInMutationTransaction(): void
deliverInOwnedTransaction(Connection): void
```

Construction is private, with `prepare(evaluation, request, plan, delivery,
connection): self` as the one validated static factory, called only by Coordinator.
No-policy requires null request/plan; real policy requires both
and exact policy/decision/delivery agreement. Ordinary delivery with no-policy is
a no-op and does no DB/transaction work. Reusing or cloning a prepared real
decision must not perform a second append. Mark it consumed before entering the
append boundary, including failures; a rolled-back append is not a committed
event. No same-attempt retry or fallback occurs after an ambiguous failure.

For either configured delivery mode, an allow that will invoke a business handler
requires attributed transaction metadata and the exact shared Framework
Connection. `assertMutation` fails TransactionInvalid before handler effects if
the transaction coordinator is absent, the metadata is nontransactional, the
business connection differs, or the expected connection is already active or not
auto-commit at level0. No-policy retains the existing nontransactional behavior.
Durable also requires the mutation root: its protected Outbox ingress must commit
with the business mutation. Neither mode silently degrades to standalone delivery.

Two existing Internal classes gain only pure object-identity predicates:
`AuditDeliveryCoordinator::usesConnection(Connection): bool` and
`OperationTransactionCoordinator::usesFrameworkConnection(Connection): bool`.
They perform no DB I/O and change no existing method semantics. Attempt requires
both identities in addition to sharesFrameworkConnection(metadata). Current-root
delivery requires auto-commit, active level1. Owned-root delivery additionally
compares its explicit caller Connection by object identity. Two simultaneously
active but different connections must fail before append/effect. Store/delivery
construction still uses the accepted trusted composition contract; this does not
authenticate arbitrary manually forged store graphs.

Standalone delivery uses C3's own level0 commit boundary. Owned/mutation delivery
uses C3's existing current-root append. No afterCommit hook, savepoint fallback,
cross-DB claim or implicit root is introduced. Existing transaction coordinator
execute signatures and completion callbacks remain unchanged.

## Native path matrix

Each runtime gains a trailing optional sensitive Coordinator dependency, default
null. Null follows existing behavior. With it present, use its typed evaluation
instead of the old evaluator. No policy creates no event and changes no root.
Prepared state is a local variable passed through private methods/closures; no
last-result property may be placed on a long-lived runtime.

| Path after a real decision | Delivery and ordering |
| --- | --- |
| Inline fresh allow | Assert supported mutation before invocation. Append inside the attributed root immediately before handler, preserving completeCanonical and its returned terminal-record list. |
| Inline deny | Standalone commit before OperationRejected and before returning the original category/code; no handler invocation. Existing Received/AttemptStarted writes are not claimed atomic with this dedicated Audit commit. |
| Inline keyed replay/conflict | Preserve one evaluation before idempotency claim; prepare duplicate result, commit standalone Audit, then disclose it. Recovery/idempotency persistence is not claimed atomic with this Audit commit. |
| Deferred acceptance allow/deny/duplicate/conflict | Evaluate at the current position after validation, append immediately in the existing acceptance root before deny/idempotency/enqueue branches. Every evaluated normal return commits the event with that root. Validation failure before evaluation emits none. |
| Worker allow | Reevaluate each attempt; assert supported mutation, append inside its attributed root immediately before handler. Preserve fencing, Journal, outcome and schedule completion callback. |
| Worker authorization deny | Carry the typed attempt to reject's existing root; append there before reserveRejected/Journal/schedule transition. A handler business rejection is not authorization deny. |

An allowed handler that rejects or throws rolls back its authorization append and
business DB changes. No replacement allow/deny is appended in later rejection,
failure or supervision roots. This intentionally records committed authorized
mutations, not every rolled-back execution attempt. The same received Deferred
Operation can have one acceptance event plus later Worker attempt events; those
are separate real evaluations, not relay duplicates. An evaluation never changes
from allow to deny based on the handler result.

Preserve Inline keyed denial's null Operation ID, once-per-call evaluation,
duplicate behavior and terminalization. Its idempotency claim/terminalization
remain outside the business transaction as today; failure handling must not leave
a newly claimed invocation without the existing cleanup/failure path. Preserve
Worker claim fencing, retries, schedule transitions and existing observation order.
External file/network effects cannot be rolled back; do not claim otherwise.
If Inline duplicateResult throws IdempotencyReplayFailure, result preparation did
not complete: this chosen order emits no event and discloses no replay result.
Normal duplicate/conflict returns each have one committed authorization event;
there is no claim of one event for every evaluated exceptional/rolled-back path.

## Failures and sensitive surfaces

No typed decision is synthesized for policy/container failure. New boundaries use
fresh bounded AuditDeliveryException with code0, previous null and fixed message.
Preserve known AuditDeliveryFailure enums when safely remapping; projection shape
is PolicyInvalid, bad trusted configuration ConfigurationInvalid, UUID/clock
outage ResourceUnavailable, corrupted snapshots StorageUnavailable, wrong root
TransactionInvalid. Never catch an outage and return a normal allow or deny.

Inline audit failure withholds a normal result and uses its existing invocation
failure/claim cleanup when that lifecycle has started; a duplicate-path outage
withholds the duplicate result. Deferred acceptance rolls back its existing root
and retains its established failureBeforeAttempt path after rollback. Worker
evaluation/pre-handler delivery failure follows HandlerInvocationFailedException
and existing supervision. An AuditDeliveryException from the authorization-deny
reject callback is explicitly routed through that same supervision path after
rollback; unrelated existing reject errors retain their existing classification.
Supervision writes are a later failure root, not an atomic Audit replacement.

Confirmed pre-commit failure and commit-then-throw are distinct. In the first
case the actual open root rolls back Audit ingress/record and business writes.
If commit has already happened, or its outcome is unknown, an exception does not
prove rollback: withhold the normal result, do not append again, and do not rerun
the handler within that attempt. After-commit process loss likewise leaves the
committed root intact. A fresh request/claim is subject to the existing recovery
and fencing contracts; it is not permission for unconditional business replay.

The existing Inline fallback is an explicit limit: its catch can persist
AttemptFailed/OperationFailed after the already committed Completed records and
terminalize a keyed claim to an internal-failure snapshot. The same-key replay
then throws IdempotencyReplayFailure without invoking the handler again. D4B
preserves and tests this bounded failure behavior; it does not claim terminal
Journal consistency or successful response recovery after an ambiguous commit.
Lifecycle/idempotency reconciliation is a named D-integration/hardening obligation
before release, not a defect solved by this Audit append adapter.

For Deferred acceptance, a post-commit throw leaves the queued operation and Audit
event committed; the subsequent failureBeforeAttempt callback may itself fail on
existing Journal identities. Preserve the original failure plus recording-failure
behavior and avoid a second enqueue. For Worker completion/denial, a post-commit
throw leaves the already committed terminal claim; later supervision must obey
existing state fences and may fail rather than rewrite it to retry. Native tests
must observe these actual rows/failure results, not infer rollback from a thrown
exception. Dedicated standalone delivery has the same ambiguity: one committed
event can exist even when the response was withheld. Its prepared attempt remains
consumed. No exactly-once or generalized crash-recovery claim is added.
In particular, a Worker deny-root commit exception occurs after its callback and
uses the existing generic failure classification. The new supervision routing
applies to AuditDeliveryException raised by the append inside that callback, not
to all exceptions that can occur after it.

Factory/Coordinator/Attempt use private SensitiveParameterValue snapshots with
exact key/type validation, empty __debugInfo/__serialize, rejecting __unserialize,
and sensitive dependency/context/evaluation/metadata/request/throwable parameters.
Reconstruct fresh safe failures at their boundaries; do not retain original
exceptions in helpers. Retained draft data is bounded Audit projection only.
Own-frame privacy does not erase pre-existing caller/provider trace arguments.
Tests must retain this explicit limitation rather than assert global redaction.

## Proof and activation obligations

Real PostgreSQL native-path tests cover both modes, identity/root rejection,
append/commit failure, business rollback, denial and duplicates, Worker retry and
no-policy zero-audit behavior. Use real existing evaluator/resolver, stores,
delivery and runtimes; do not mock the final evaluation types or replace the
native execution path with a model. Catalog, existing completion and isolation
guards remain effective. A bounded AST/name-resolved oracle closes exactly the
seven authorization types, the two identity predicates and three runtime edges;
canonical composers and public activation remain forbidden. Preserve alias,
fully-qualified, nullsafe and case-insensitive method-call handling and targeted
negative controls; avoid unbounded mutation combinations.

Specification114's four foundation types remain exact; its earlier no-runtime-edge
rule is superseded only for these explicit D4B edges. A2/C3 allowlists are expanded
by exact caller/type/method, never by a whole-file or directory exemption. Package
inventory gains exactly the three new types. Same-input checks are reused;
one full run follows frozen Product/tests and source/security review.

The executable ownership map is closed as follows:

| Caller | Allowed new cross-boundary calls |
| --- | --- |
| Coordinator::evaluate | Accepted Evaluator::evaluate, Factory::create, Selector::forDraft/validate, Attempt::prepare |
| Factory | New bounded Core Audit projection construction, fixed Framework Catalog lookup, UUID/Clock/opaque derivation; no delivery, Connection or native runtime calls |
| Attempt | C3 deliverStandalone/deliverInCurrentTransaction, new connection-identity predicates, existing transaction metadata/shared-connection predicates and Connection state inspection; no evaluator/factory/native runtime calls |
| Three native runtimes | Coordinator::evaluate, the five declared Attempt boundary methods and accepted Evaluation read-only queries/rejection/policy; no Evaluation factories, new direct store/selector/delivery or Factory/Attempt construction |
| Seven canonical composer sites | No new Audit construction, registration or injected dependency |

Existing D4A intra-graph edges remain unchanged. Each new identity predicate body
is only the object-identity comparison against its stored Connection, without
existing method/body changes. Private helper names within permitted ownership
are implementation details; the architecture oracle freezes the resolved exact
caller/type/method inventory before final review. Catalog and public/composer
activation cannot acquire an allowance merely by sharing an Internal directory.

D4B local acceptance does not activate ordinary Audit composition. D-integration
must provide canonical compiled metadata/policy configuration, source runtime,
build and execution fallback through the trusted application graph. D5, E/F/G,
hardening, documentation and release remain; D-control waits for Accepted F.
The final documentation candidate must be served locally and reviewed by the user
before release.
