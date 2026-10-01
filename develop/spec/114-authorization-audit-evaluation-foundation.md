# Specification 114: Authorization Audit Evaluation Foundation

Status: Accepted — D4A local; D4B pending; BlackOps 1.3 unreleased

## Authority and Delivery Boundary

This is the most-specific D4A contract under D154, D163 and specifications105/106.
D4 has two serial children: D4A establishes a typed, versioned evaluation; D4B
owns event preparation and delivery at each actual transaction boundary. D4 is
incomplete until both children are Accepted. D4B needs its own most-specific
authority and exact Packet before Product work. Ordinary runtime activation
remains in D-integration after the other ordinary children are Accepted.

D4A creates no AuditRecordDraft, delivery plan/request, UUID, clock reading,
opaque ID, database operation, lifecycle event or runtime registration. A typed
evaluation is not evidence that an Audit event was saved. No policy means no
Audit policy object, rather than a fabricated allow/not_applicable policy.

The existing public Authorize attribute, AuthorizationPolicy/Decision/Request,
OperationMetadata and RejectionReason remain unchanged. Existing evaluators,
dispatchers, acceptance, Worker, transaction coordinators and composers remain
byte-identical. This latent graph has no caller in current runtime composition.

## Trusted Policy Binding

Four final Internal types live in `BlackOps\Internal\Audit\Authorization`.
None receives a PublicApi marker. No new provider interface, public attribute,
configuration key, resolver hook, migration or dependency is introduced.

`AuthorizationAuditPolicyBinding` is immutable. Its constructor takes exactly:

```text
OperationMetadata metadata
string policyId
int policyVersion
AuditDelivery delivery
```

It copies only the metadata's `typeId`, `definition`, and non-null
`authorizationPolicy`, together with the three explicit policy values. Its six
readonly scalar/enum fields are `operationTypeId`, `operationClass`,
`policyClass`, `policyId`, `policyVersion`, and `delivery`. It retains no
OperationMetadata, Operation, OperationValue, context, policy service or request.
All four strings are validated using the existing safe-string validation;
policyVersion is 1..MAX_SAFE_INTEGER. Null/empty policy metadata is invalid.
Constructor inputs that pass PHP's declared types but violate these invariants
throw a fresh value-free AuditDeliveryException(ConfigurationInvalid), without
the validation exception as previous. There is no default ID, version or delivery.
Class strings are exact compiled identities, not inferred Audit policy IDs.
No autoload or policy/container lookup is performed by this value constructor.

`AuthorizationAuditPolicyRegistry` takes a list of bindings. It rejects a non-list,
non-binding entry, duplicate Operation definition or duplicate Operation type ID.
Repeated policy ID/version across different Operations is allowed only with the
same configured policy class and delivery. Conflicting identity semantics fail
construction with ConfigurationInvalid. It retains only validated bindings.
`resolve(OperationMetadata): AuthorizationAuditPolicyBinding` returns the one
binding whose definition, type ID and configured policy class all match exactly.
Missing or mismatched binding fails with ConfigurationInvalid; no normalization,
case folding, policy-class fallback, delivery downgrade or default version exists.

Bindings are curated by trusted composition from compiled metadata and explicit
versioned policy configuration. D4A does not add that configuration source or
assert that any arbitrary OperationMetadata supplied by a caller is trusted.
In particular, no-policy bypass cannot detect a caller that replaces canonical
metadata with a policyless forgery. D-integration must pass canonical metadata and
close that caller boundary; a request/payload cannot supply or override bindings.
The existing resolver continues to validate the actual policy service interface.

## Three-State Evaluation

`AuthorizationAuditEvaluation` is immutable with a private constructor. Its
public factories are `noPolicy(): self` and
`fromDecision(AuthorizationAuditPolicyBinding, ?RejectionReason): self`.
Its query methods are `hasPolicy(): bool`, `isAllowed(): bool`,
`isDenied(): bool`, `rejection(): ?RejectionReason`, and
`policy(): ?AuditRecordPolicy`. These are policy evaluation states, not permission
to run an Operation before the future delivery gate.

| State | hasPolicy / isAllowed / isDenied | rejection | policy |
| --- | --- | --- | --- |
| No policy | false / false / false | null | null |
| Allow | true / true / false | null | explicit binding + allow/authorization_allowed |
| Deny | true / false / true | original Unauthorized or Forbidden reason | explicit binding + deny/authorization_denied |

The policy object reuses the existing AuditRecordPolicy. Its ID/version/delivery
come only from the binding. Decision/reason use the fixed Catalog semantics.
Original public dotted/hyphenated rejection codes are never Audit reason codes.
Non-authorization rejection categories passed to fromDecision fail PolicyInvalid.
The original valid RejectionReason object, category and code are retained for
future public/lifecycle compatibility; a policy author still owns the existing
requirement that this public code contain no credential or backend detail.

No event version is assigned here. D147's existing event/evidence version1 means
durable, version2 means required; neither number supplies the policy version.
The exact30 security/access Catalog remains unchanged.

## Evaluator and Ordering

`AuthorizationAuditEvaluator` takes the existing concrete AuthorizationEvaluator
and the immutable registry, in that order. Its ordinary public method is:

```php
public function evaluate(
    #[\SensitiveParameter] OperationMetadata $metadata,
    #[\SensitiveParameter] OperationEnvelope $envelope,
): AuthorizationAuditEvaluation;
```

It first checks that the actual envelope definition's canonical class exactly
matches metadata.definition. A mismatch fails PolicyInvalid before delegation.
It reads neither envelope.value(), handler result, request body/header nor policy
request. With metadata.authorizationPolicy null, it returns noPolicy and does
zero registry resolution, delegate, actor lookup or other Audit work.

For configured policy it resolves the binding before invoking the existing
evaluator exactly once. A missing binding fails even when the authorization
Actor is absent: missing Audit configuration must not acquire a fallback policy.
The old evaluator preserves the actual policy behavior, including zero policy
calls when authorization Actor is absent. Its missing-Actor Unauthorized result
is represented as Deny under the explicit binding. No decision is inferred from
the execution Actor, Operation type, handler result or RejectionReason text.

A container/policy backend exception is not Deny. The latent wrapper maps any
delegate throwable to a fresh ResourceUnavailable exception with previous null,
code0 and fixed message; it never manufactures a policy decision, retries or
logs the original. Missing/bad binding remains ConfigurationInvalid. Invalid
evaluation shape/category remains PolicyInvalid. The undecorated evaluator's
existing throwable-identity contract remains unchanged. Future Worker activation
must explicitly preserve failure/supervision classification; D4A claims no new
HTTP response or exit mapping and cannot release an effect/response itself.

## Sensitive Surfaces and Trust Limits

The evaluator holds its two dependencies only in one private
SensitiveParameterValue snapshot with exact keys `evaluator` and `policies`;
validate keys/types on use and fail StorageUnavailable if corrupted/uninitialized.
Its __debugInfo/__serialize return empty arrays; __unserialize fails closed.
All envelope, metadata, dependency and throwable parameters in new methods/helper
frames receive SensitiveParameter. No original throwable is retained, chained,
serialized, included in a message or passed to a remap helper without protection.
The evaluator retains no last envelope/result. A repeated call evaluates afresh.

The binding, registry and evaluation contain only the stated trusted policy
metadata and public rejection result. They expose no provider/container graph or
Operation payload. They reject native unserialization to prevent bypassing their
constructors/factories. No redaction claim is made for deliberately readable
policy metadata/public rejection code. Existing caller/provider frames outside
this new graph can still retain arguments under zend.exception_ignore_args=0;
direct-wrapper privacy tests must distinguish this inherited limit. These types
are Internal trusted composition values, not a public authorization capability.

## Deferred Ordering Clarification and D4B Handoff

The existing Deferred acceptance evaluates inside its Framework root after ID,
Binding and Validation, but before idempotency claim, normal Received/Accepted
records and enqueue. Denial writes Received/Rejected in that root. A duplicate
may return before normal Received. Worker reevaluates after AttemptStarted but
before handler and its Operation transaction, using the original authorization
Actor. Specification06's older general Received-before-authorization sentence is
corrected to describe this existing behavior; no runtime ordering changes here.

D4B must fix and review a complete matrix before emitting any event:

- Inline unkeyed and keyed evaluation stay before the mutation root; keyed
  pre-evaluation is consumed once, including duplicate behavior.
- Deferred acceptance already owns the exact Framework level-1 root. Applicable
  event and acceptance/rejection state must share it; no post-commit atomic claim.
- Worker reevaluates each attempt outside the later mutation root. It uses the
  authorization Actor, not the Worker execution Actor, as principal.
- Privileged required allow must join the exact Framework mutation root, reject
  unsupported nontransactional/different-Connection composition, and roll back
  with business failure. Denial needs its own correct rejection root because
  OperationTransactionCoordinator's completion callback skips rejected results.
- D4B separately owns actor/tenant/execution projection, trusted runtime/build,
  raw identity/opaque derivation, Clock/UUID/idempotency, durable vs required plan,
  before-effect ordering, deny append outage, commit-then-throw and retry/crash
  semantics. It must not copy D3's invalid-authentication suppression rule.

This list is a constraint and handoff, not authority to implement an unspecified
delivery path. D4B and parent D remain incomplete after D4A Acceptance.

## Evidence and Release

The exact Packet owns positive, failure, privacy, immutable-binding, no-activation,
AST negative controls and package evidence. New evaluation tests use the actual
existing evaluator/resolver and verify policy call counts and safe failures.
The final frozen security candidate gets one complete PHPUnit run at the shared
512M setting; same-input successful checks are not repeated for another reviewer.

Framework/Skeleton/site remain Experimental Stable1.2.1; roadmap1.3.0 unreleased.
No public documentation source, route, search/LLM artifact, command, configuration
or version changes. Spec114 is the implementer contract for this latent graph;
public/runtime authoring documentation belongs to later activation. No deployment,
same-SHA CI or package export is claimed by local Acceptance.
