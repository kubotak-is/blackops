# Operation Status Audit Adapter

Status: Incomplete adapter proposal — D2S1 Accepted; revision/review required; NOT implementation authority.

## Current Disposition

The source prerequisite in [Specification108](108-tenant-scoped-status-source.md)
is Accepted; current evidence is in the [D2S1 Report](../orchestration/reports/P23-003D2S1-tenant-scoped-status-source.md).
Revise and independently review this adapter proposal before implementation.
The historical source gap below motivated D2S1; it is not a current claim that
the new scoped source is missing. Legacy wiring remains unchanged; the new
scoped source and adapter remain unactivated.

## Historical Blocking Correction Required

The user approved the separate source prerequisite. Its exact proposed
contract is [Specification108](108-tenant-scoped-status-source.md), with
P23-003D2S1 as the active Task. This adapter remains deferred until Acceptance.

The initial three-type proposal below is retained as a design candidate, not
an accepted or runnable contract. It is insufficient: a tenant authorizer guard
does not satisfy Specification99's tenant-qualified SQL requirement.
The existing PostgreSQL Status source uses unscoped subject and detail paths;
Journal/Outcome, deferred state, existence and retention queries also require
an explicit scoped path before this adapter can be implemented.

Do not implement the constructor/scope/package counts below. They describe the
initial, rejected-as-incomplete three-type boundary and must be re-derived
after a separately scoped Status-source prerequisite is reviewed. No D2 or
legacy source change is authorized by this proposal. In particular, neither
adding only the tenant guard nor changing only subject lookup closes the gap.

The prerequisite must settle exact tenant-qualified SQL for every detail
source, before-decode validation under a read-only snapshot, null/same/cross
semantics, races between subject and detail, expired/retention behavior and
same-Connection ownership. Existing Journal/Outcome `*ForTenant` methods and
`PostgreSqlStatusReader::findSubjectForTenant()` are reusable, but do not yet
form a complete Status query graph. The D2S Report records source evidence.

## Initial Adapter Candidate — Not Accepted

## Scope and Precedence

This is the most-specific proposed P23-003D2S contract after Accepted D2 at
`0cd699e5e1d031228bc4a05767164a8eb268a004`. D156 records the rationale.
D147/D154/D155 continue to govern the Catalog, session and delivery protocol.
This specification authorizes no runtime registration. Existing Default
Status Query, public Status types, HTTP handler/responder, source and
Application composers remain byte-identical. D2J/D2X are not dependencies.

The new graph is Internal and uncalled by current Production entrypoints.
Application composition and actual HTTP activation remain D-integration.
Stable1.2.0 is unchanged; this is unreleased1.3 work.

## Exact New Types

All three types are final and have no PublicApi marker. They live in
`BlackOps\Internal\Status`.

### OperationStatusAuditTenantAuthorizer

Implements `BlackOps\Status\OperationStatusAuthorizer`; constructor accepts
exactly one `OperationStatusAuthorizer $authorizer`. `decide()` accepts the
existing `OperationStatusAuthorizationRequest` and returns the existing
`OperationStatusAuthorizationDecision`.

Compare current and origin Tenant before invoking the Application authorizer.
Both null is equal. One null is unequal. Two non-null values are equal only
if their type and id strings are each strictly equal, with no case folding,
normalization, fallback or membership lookup. Unequal returns `deny()` with
zero Application-authorizer calls. Equal delegates the exact request once and
returns its decision unchanged. A thrown failure is left to the unchanged
Default Query's `authorization_failed` mapping. No data/store/audit access.

The guard does not change legacy Default Query behavior. It is mandatory
only within the new audited graph. Thus a permissive Application authorizer
cannot cause audited cross-tenant detail access, including null/present cases.

### OperationStatusReadExecutionSource

Implements D2 `OperationReadExecutionSource`. Constructor accepts exactly
`BlackOps\Identifier\Uuidv7Generator $identifiers` and wraps that source in
the existing `ValidatedUuidv7Generator`. No UUID is generated on construction.
`current(): AuditTypedReference` calls the validated generator once and returns
`new AuditTypedReference('operation_status_read', $uuid)`.

This is an explicitly allocated, server-owned Status-read execution identity,
not a claimed HTTP request ID or a fallback for a missing Operation scope.
It deliberately specializes D155's source enumeration: no existing scope is
consulted or synthesized. There is no constant ID, target-derived ID, header,
actor, tenant, request body, URL, configuration/environment lookup or retry.
Two reads allocate independently even on one reused source. The trusted
composition supplies the server UUID generator, never a caller-provided ID.
Invalid UUID or generator failure propagates to D2's fixed
`AuditDeliveryException(resource_unavailable)` boundary and then the query's
fixed storage failure. No lower cause or value is exposed.

D2S owns this execution UUID only. D2 still owns one independent16-byte request
nonce and one Clock capture. The canonical Store still owns record/segment
UUIDs. There is no new idempotency key, durable Outbox or source mutation.

### AuditedOperationStatusQuery

Implements `TenantAwareOperationStatusQuery`. Constructor accepts, in order:

```php
OperationStatusSource $source,
OperationStatusAuthorizer $authorizer,
OperationReadAuditSessionFactory $sessions,
Doctrine\DBAL\Connection $connection,
```

It constructs exactly one unchanged `DefaultOperationStatusQuery` using the
source and exactly one `OperationStatusAuditTenantAuthorizer($authorizer)`.
It does not accept an already-built Default Query or arbitrary query delegate.
The constructor performs no read, authorization, transaction or session begin.

Public methods match Default's `find()` and `findForTenant()` signatures,
including optional actor and tenant parameters. `find()` delegates once to
`findForTenant()`; it does not begin another session. Every `findForTenant()`
uses exactly this order:

1. Assert the supplied Connection is auto-commit, inactive and nesting0.
2. Call `sessions->begin(Status, operationId, currentActor, currentTenant)` once.
   A begin failure becomes fresh `OperationStatusQueryException::storageFailed()`;
   there is no delegate read and no terminal attempt without a returned session.
3. Assert level0 again, then call the owned Default Query exactly once with
   the unchanged operationId/actor/tenant. There is no result callback passed
   to Audit and no exception retry.
4. After delegate completion or caught query failure, assert level0 before
   selecting exactly one terminal. If the guard fails, withhold everything as
   storageFailed; do not commit or roll back a foreign transaction.
5. Commit the selected terminal, assert level0 after it, then return the exact
   result object or rethrow the exact contracted exception object.

The adapter owns no transaction control and must not repair invalid Connection
state. Guard failures, guard inspection throwables, begin failures and terminal
failures all produce the existing value-free storageFailed exception, with no
previous cause, input value, response data or diagnostic logging.

### Result and Failure Matrix

| Delegate outcome | Terminal | Release after terminal success |
| --- | --- | --- |
| `OperationStatusFound` | accessed | same object; existing200/polling behavior |
| `OperationStatusExpired` | accessed | same object; existing410 |
| `OperationStatusUnavailable` | unavailable | same object; existing404 |
| `OperationStatusQueryException` | unavailable | rethrow identical exception |
| Other Throwable | unavailable | fresh storageFailed, no cause |
| Unexpected result implementation | unavailable | fresh integrityFailed |

Found and Expired are possible only after Default's authorization/detail path.
Expired exposes an authorized tombstone fact, so accessed does not mean an
Outcome payload exists. Unknown, denied, tenant mismatch and ephemeral detail
remain indistinguishable unavailable records with no reason/existence detail.

Keep terminal dispatch outside the delegate try/catch: a terminal failure
must never be caught as a query failure and trigger a second terminal. A
failed accessed append never falls back to unavailable. A failed unavailable
append masks the pending result/exception with storageFailed. There is no
retry, alternate session, writer access or direct draft construction.

## Ownership and Transaction Contract

Trusted request actor/tenant are passed unchanged to D2 and the Default Query.
Resource is D2's HMAC-opaque target; action is the D2 requestRef. The fixed
Catalog tuples and version1 evidence remain unchanged. Future Status HTTP
composition supplies runtime Http and its validated Application build ID to
the factory; these values are not inferred by this adapter. The new execution
source identifies each Status read regardless of HTTP connection reuse.

The supported graph uses the identical DBAL Connection for the injected guard,
the `PostgreSqlOperationStatusSource` and canonical Audit delivery/Store. No
cross-Connection atomicity or arbitrary manually assembled graph is claimed.
The adapter cannot introspect private connections in supplied dependencies.
Real-PG tests construct the same-Connection graph and prove it; actual
Application composition identity remains Not Verified until D-integration.

Requested and terminal are separately committed Store-owned write roots.
Restricted subject lookup and authorization run at level0 between them. The
unchanged source may open its own REPEATABLE READ READ ONLY detail root, which
must commit/roll back before terminal starts. Thus a successful detailed read
has two Audit write roots plus one source read-only root, not only two total
database transactions. No decrypted data enters an Audit draft.

Crash before requested produces no reader work; after requested it may leave
a requested-only record. Crash/failure before terminal never releases a
result; terminal commit-then-throw may leave a committed terminal but still
returns storageFailed. No compensation or synthetic recovery is attempted.

## Sensitive State

All input/dependency/request/response/failure parameters carrying sensitive
state use SensitiveParameter. New objects must not expose retained dependency
graphs, raw UUID/reference data or read objects through debug/serialization.
Retain dependencies only in SensitiveParameterValue snapshots; empty
`__debugInfo()` and `__serialize()`, fail-closed `__unserialize()` with the
owner's fixed safe exception. No cloning of a session is introduced. The query
does not retain the operationId, result, session or exception after a call.
No extra runtime port, callback, trait or reflective dispatch is added.

## Architecture and Package Boundary

Preserve exact D2 declarations, signatures, helpers, nonce grammar and all its
callback/mutation/false-positive controls. Extend its outside-reference policy
only for the exact new query's factory/session/purpose references and the exact
new execution source's interface/reference types. Do not whitelist an entire
directory or exempt a new file from dependency/call validation. The query may
begin/terminate a session but never call session open, writer or Store directly.
The execution source may only generate its execution identity. The guard has
no Audit dependency. Factory constructors and ApplicationAuditRuntimeComposer
Production callers remain zero. Existing reader/composer paths stay unchanged.

A dedicated Architecture Test fixes all three new declarations, public methods,
dependencies and executable callsites, the exact tenant comparison/order,
zero activation and no alternate terminal/writer path. It must reject mutations
of the actual oracle: missing tenant guard, different tenant member comparison,
app-authorizer-before-guard, duplicate begin/terminal, accessed-to-unavailable
fallback, extra caller, source-derived/constant execution, and indirect writer/
session-open references. Include matching positive/false-positive controls.

Add exactly the three new Production paths to both package inventories,
immediately after `src/Internal/Audit/Read/OperationReadAuditSession.php`, in
query/tenant-authorizer/execution-source order. Preserve all old85 paths and
their order: exact88 unique. Retain all31 existing negative package controls;
add independent omission/reordering checks for the new block, and update the
whole-export SHA pin only after both inventories pass independently.

## Required Evidence

- Unit event-order and zero-work sentinels for both public entries and every
  matrix branch; all four contracted query codes; result/exception identity;
  requested/terminal failures and commit-then-throw; no second terminal.
- Tenant matrix null/null, null/present, present/null, same/different type,
  same/different id; permissive Application authorizer cannot override mismatch.
  Validate no detail/authorizer call on mismatch and unchanged legacy tests.
- Execution generate-once, separate-read identities, invalid/throwing source,
  no nonce reuse, no headers/scope/target dependency and no-secret surfaces.
- Real PostgreSQL requested visibility from a second Connection before lookup/
  authorization, detail read-only root completion before terminal visibility,
  and terminal visibility before success/404/410/exception release. Include
  rollback, requested/terminal append failure, ambient/auto-commit/poststate
  negatives, tenant partition and no-plaintext/no-outbox assertions.
- Existing Status/HTTP/PostgreSQL suites, D2 foundation and Architecture,
  six goldens, package/version/migration/nonactivation guards, full quality,
  full PHPUnit/Deptrac and Community Board clean-install.
- Fresh General, Transaction and Documentation reviews; full Final on unchanged
  Product/Test bytes; local commit/export only after separate Acceptance.

## Release and Documentation

No public source/route/config/API change or new Stable claim. No Guide/Website
rewrite is needed for an unactivated Internal adapter. This specification is
the implementation-facing contract; public/internal usage instructions wait
for the composition Task. Generated HTML/Search/raw Markdown/LLM, browser,
CI/delivery and Production deployment must not be inferred from source checks.
