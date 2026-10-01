# Specification 113: HTTP Authentication Audit Adapter

Status: Accepted locally — Product/evidence in D3 Report; BlackOps 1.3 unreleased

## Boundary and Authority

This is the most-specific D3 contract under D154 and specifications105/106.
It records an explicit `HttpAuthenticator` result before AuthenticationMiddleware
releases that result to the next handler or returns401. It does not activate the
Application Audit composer or change the existing public authenticator/result,
middleware, Session implementation, generated Operations, or runtime composition.
Rationale and the precise source-boundary clarification are in D162.

The event describes completion of a credential check, not a Session mutation or
an Application login/register/logout Operation. The current Session authenticator
may commit `last_used_at` before returning its result. That source mutation keeps
its existing transaction; D3 neither rolls it back nor claims atomicity with it.
The D3 result event uses a separate Store-owned standalone root after the provider
returns idle. Later mutation events, if selected, require their own same-root
source adapter. Anonymous login/register HTTP requests therefore produce no D3
event merely because their Application Operation later succeeds.

## Exact Internal Types

All three new types live in `BlackOps\Internal\Audit\Authentication`, have no PublicApi
marker, and have no runtime constructor caller outside this bounded graph.

`AuthenticationAuditEventWriter` is an interface with one method:

```php
public function write(#[\SensitiveParameter] AuthenticationResult $result): void;
```

`DurableAuthenticationAuditEventWriter` is final and the sole Production
implementation. Its constructor receives these exact dependencies:

```text
AuditDeliveryCoordinator delivery
HmacAuditOpaqueIdDeriver opaqueIds
BlackOps\Identifier\Uuidv7Generator executions
Psr\Clock\ClockInterface clock
string buildId
```

Its `write()` creates one fixed draft and one `AuditDeliveryRequest`, then calls
`delivery->deliverStandalone()` exactly once. It never calls a Store, relay,
selector override, Operation-read session, transaction control, or response API.
There is no response callback, retry, alternate append, or mutation wrapper.

`AuditedHttpAuthenticator` is final and implements the existing public
`HttpAuthenticator`. Its constructor takes, in order, `HttpAuthenticator
authenticator`, `AuthenticationAuditEventWriter writer`, and Doctrine DBAL
`Connection connection`. Its sole ordinary public method is
`authenticate(#[\SensitiveParameter] ServerRequestInterface $request): AuthenticationResult`.
It invokes the delegate once and the writer at most once. It never reads request
headers/body/attributes, generates an Operation ID, emits HTTP, or calls a handler.
The existing AuthenticationMiddleware remains the only response/actor-attribute
boundary in tests of this composition.

## Result and Failure Ordering

Constructors perform no I/O. The adapter first validates its dependency snapshot
and requires auto-commit true, no active transaction, and nesting level0 on its
injected Framework Connection. It then calls the delegate once. Unexpected
delegate throwable, wrong return type, or an unreadable/inconsistent result is
a fresh `AuditDeliveryException(ResourceUnavailable)` for delegate failure or
`PolicyInvalid` for result shape. Neither is converted into Invalid credentials.
No writer is called. This fresh exception deliberately replaces inherited raw
provider frames only inside the new opt-in adapter; the old public middleware
and undecorated authenticator keep their existing behavior.

A valid result has exactly one true state predicate. Authenticated has a non-null
ActorRef and optional TenantRef; Anonymous/Invalid have null actor and tenant.
The adapter/writer never call `code()` or infer semantics from its value. They
return the original valid result object after the applicable gate.

| Result | Work after delegate | Audit/idle failure | Released result |
| --- | --- | --- | --- |
| Anonymous | Check idle; zero writer/UUID/Clock/key/Store work | Fresh bounded exception | Same Anonymous only when idle |
| Authenticated | Check idle; one writer; check idle | Fresh bounded exception; no downstream handler | Same Authenticated only after durable append commits |
| Invalid | Check idle; one writer if idle; check idle | Suppress only this post-result gate failure; no retry | Same Invalid, preserving the existing safe401/code and no Operation ID |

Entry/dependency failure occurs before a trustworthy result exists and therefore
throws. For recognized Invalid, a post-delegate non-idle connection does zero
writer work and remains Invalid. No layer repairs, commits, or rolls back a
provider-owned transaction. For Authenticated/Anonymous, idle-check failures are
`TransactionInvalid`. A writer's known AuditDeliveryFailure is preserved only as
the enum in a fresh exception; an unexpected writer throwable becomes
`StorageUnavailable`. Original throwables are not chained or rethrown, retained
in fields, interpolated, logged, or passed to another layer.

The table governs the adapter and AuthenticationMiddleware. On detecting a leaked
active Connection, the existing outer Application lifecycle attempts to close it
and returns safe500, including when close succeeds. D3 does not bypass that guard to force401.
An ordinary append failure that rolls back to idle preserves Invalid401; a
provider/writer transaction leak preserves the bounded Invalid result at this
adapter, then may become the existing outer safe500, never an allow. Both cases
are distinct fixtures, including next-request recovery after the outer close.

## Fixed Draft and Identity

The writer rejects Anonymous or malformed result with PolicyInvalid before any
Clock/UUID/deriver/delivery call. It never reads the request or invalid code.
AuthenticationResult currently has no typed provider provenance; none is invented
from service class, header scheme, Actor type, tenant, or configuration. Catalog
bytes and evidence fields remain exact. No new provider API is introduced.

For each valid non-anonymous write, obtain one server-generated lowercase UUIDv7
from the injected public generator, validate the exact version7/variant grammar,
then capture one Clock value as UTC `YYYY-MM-DDTHH:MM:SS.ffffffZ`. This UUID names
the new authentication evaluation, not an Operation or an inherited request ID.
Do not accept client IDs or reuse a prior call's identity. The existing private
Store UUID generator continues to own record/segment/Outbox identities separately.

Use raw context `http_authentication:` followed by that UUID for exactly two
existing HMAC derivations: Action and Resource. The draft is fixed as follows:

| Field | Value |
| --- | --- |
| event | Accepted security/access Catalog's exact v1 succeeded or invalid definition |
| action.id / resource.id | Respective Action/Resource opaque derivation of the raw context |
| action.type / phase | Selected definition type / its sole semantic tuple phase |
| resource.type | `authentication` |
| actor.principal | Authenticated Actor's exact validated typed ref; Invalid null |
| actor.execution | `{type: "http_authentication", id: <server UUIDv7>}` |
| tenant | Authenticated result's exact validated typed ref or null; Invalid null |
| outcome | Selected definition's exact status/code |
| policy | id=`http-authentication`, version1, tuple decision/reason, Durable |
| occurredAt | The single captured Clock value |
| source | Framework / `http-authentication` / Http / injected validated buildId |
| correlation | Empty object; no Operation/trace/client/request ID inference |
| evidence | Exactly `{"version":1}` |

Success is `security.authentication.succeeded`, succeeded/authenticated,
allow/authenticated. Invalid is `security.authentication.invalid`, denied/
authentication_invalid, deny/authentication_invalid. Both remain durable-only.
`AuditDeliveryCoordinator`/selector own the final fixed delivery validation.
The non-null idempotency key is exactly `http-authentication:` + action.id.
It is derived once per write; D3 does not retry. Repeated HTTP evaluations have
fresh UUIDs and keys. This is not HTTP request deduplication or exactly-once.

The writer maps generator/Clock failures to ResourceUnavailable, invalid generated
UUID to ConfigurationInvalid, reference/draft/tuple failure to PolicyInvalid,
opaque derivation failure to KeyUnavailable, and unexpected delivery failure to
StorageUnavailable. Known delivery failure enums are reconstructed freshly.
An invalid buildId fails construction with ConfigurationInvalid; malformed stored
dependencies or buildId fail use with StorageUnavailable. No fallback identity,
Clock, key, catalog, provider, or delivery mode is allowed.

## Transaction and Crash Semantics

Future parent composition must supply the exact Framework Connection to the
adapter and its coordinator/Stores; no second Connection or nested wrapper may
be substituted. D3's native fixtures use that same identity and a separate
read-only probe connection to prove committed visibility.

The provider finishes before the Audit root starts. The Outbox append, encrypted
ingress, identity and immutable key binding commit together under the existing
Store's standalone root. Canonical Store rows remain absent until separate relay.
Before append, a crash leaves no D3 record; a rolled-back append leaves no record.
After append commit, a crash may leave the durable event without an HTTP response.
Commit-then-throw still withholds authenticated success and leaves the one
committed event; Invalid still returns401. Never append a compensating failure
event or rerun the provider. Delivery does not assert that the subsequent handler
or Operation succeeded. Existing C4 relay retry semantics remain unchanged.

## Sensitive Surfaces

Both concrete types store dependencies only inside one private
SensitiveParameterValue snapshot, validated for exact keys/types at use. Mark
constructor dependencies, request/result parameters and sensitive private method
parameters with SensitiveParameter. Constructors retain no request/result.
`__debugInfo()`/`__serialize()` return empty arrays; `__unserialize()` throws a
fresh StorageUnavailable. Corrupt/uninitialized snapshots fail closed before
dependency calls. Clones are stateless and carry no previous request/result.

Test real request/token/body/actor/tenant/dependency canaries with
zend.exception_ignore_args=0, restored afterward. D3's outward exceptions are
fixed message, code0, null previous, and contain no original provider/writer
frames. SensitiveParameter protection covers D3-owned frames. Unchanged upstream
Framework callers, including AuthenticationMiddleware::process(), and Application/
test-runner callers can still retain their own request arguments in a trace.
The adapter does not sanitize those caller frames. A positive canary through the
actual middleware demonstrates this limit, separately from direct-adapter privacy.
The outer ApplicationHttpRequestHandler fixture proves safe500/class-name-only
logging exposes no credential values. Do not claim whole-call-chain sanitization.
The existing successful result intentionally still exposes its typed Actor/Tenant
to the middleware. That required return value is not an Audit evidence leak.

## Verification and Delivery

The D3 Task owns exact files, tests and final gates. Include actual middleware
401/next-handler ordering, two-Connection encrypted Outbox visibility, success
outage withholding, Invalid outage401, Anonymous zero work, source Session-touch
non-atomicity, distinct tenant/global partitions, fresh request identity, rollback
and commit-then-throw, snapshot/exception privacy and scoped architecture negatives.
Extend only the precise A2 record-reference and C3 reference/call allowances and package inventory for the
new graph. Preserve every old control and D2 input. No production caller is added.

Framework/Skeleton/site remain Experimental Stable1.2.1; this is Internal
unreleased1.3 work. No public source, route, version, dependency, migration,
configuration, Search/LLM artifact, CI, export, tag or deploy is selected. Public
docs and current internal runtime explanations need no change while unactivated;
this specification provides the new implementer contract. Parent activation owns
public usage/docs and same-Connection composition proof.
