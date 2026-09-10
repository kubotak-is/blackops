# Operation Status Audit Adapter

Status: Authority reviewed Green; Product implementation and verification pending.

## Scope and Precedence

This is the most-specific P23-003D2S contract after Accepted D2 at
`0cd699e5e1d031228bc4a05767164a8eb268a004`. D156 records the rationale.
D147/D154/D155 continue to govern the Catalog, session and delivery protocol.
D2S1 is Accepted at `70f9c4733bed8bb0960165cddf8c451b78b96184`, including
clean same-SHA export. Its Specification108 scoped source is mandatory. The
earlier adapter-only candidate was incomplete; this revised contract replaces
that candidate without modifying Accepted source behavior.
This specification authorizes no runtime registration. Existing Default
Status Query behavior, public Status types, HTTP handler/responder, source and
Application composers are preserved. The sole existing Product edit is the
annotation-only Default Query exception-trace correction below. D2J/D2X are not dependencies.

The new graph is Internal and uncalled by current Production entrypoints.
Application composition and actual HTTP activation remain D-integration.
Published Stable1.2.1 is unchanged; this is unreleased1.3 work.

## Exact New Types

All three types are final readonly and have no PublicApi marker. They live in
`BlackOps\Internal\Status`.

### OperationStatusAuditTenantAuthorizer

Implements `BlackOps\Status\OperationStatusAuthorizer`; constructor accepts
exactly one sensitive `OperationStatusAuthorizer $authorizer`. `decide()` accepts the
existing `OperationStatusAuthorizationRequest` and returns the existing
`OperationStatusAuthorizationDecision`.

Compare current and origin Tenant before invoking the Application authorizer.
Both null is equal; this is global scope, not an all-tenants wildcard. One null is unequal. Two non-null values are equal only
if their type and id strings are each strictly equal, with no case folding,
normalization, fallback or membership lookup. Unequal returns `deny()` with
zero Application-authorizer calls. Equal delegates the exact request once and
returns its decision unchanged. A thrown failure is left to the unchanged
Default Query's `authorization_failed` mapping. No data/store/audit access.

The guard does not change legacy Default Query behavior. It is mandatory
only within the new audited graph. The scoped source normally returns no foreign subject. The guard independently
rejects a foreign subject from any implementation of the scoped interface; a
permissive Application authorizer cannot override it. SQL isolation is proved
by the Accepted scoped source and is not inferred from this guard.

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
Invalid UUID, malformed dependency snapshot or generator failure becomes a fresh
`AuditDeliveryException(resource_unavailable)` without a previous cause. D2
preserves its own fixed failure boundary and the query maps begin failure to
Storage. No lower cause or value is exposed.

D2S owns this execution UUID only. D2 still owns one independent16-byte request
nonce and one Clock capture. The canonical Store still owns record/segment
UUIDs. There is no new idempotency key, durable Outbox or source mutation.

### AuditedOperationStatusQuery

Implements `TenantAwareOperationStatusQuery`. Constructor accepts, in order:

```php
TenantScopedOperationStatusSource $source,
OperationStatusAuthorizer $authorizer,
OperationReadAuditSessionFactory $sessions,
Doctrine\DBAL\Connection $connection,
```

The constructor captures the scoped source, exactly one
`OperationStatusAuditTenantAuthorizer($authorizer)`, sessions and Connection in
its sensitive snapshot. It creates no Default Query or tenant-bound view and
performs no read, authorization, UUID allocation, transaction or session begin.
It accepts neither an already-built Default Query nor an arbitrary delegate.

After requested succeeds, each call constructs exactly one
`TenantBoundOperationStatusSource($source, $currentTenant)` and exactly one
unchanged `DefaultOperationStatusQuery($boundSource, $guard)`. Neither object
is retained after the call. The same currentTenant goes unchanged to D2, the
bound source and Default's authorization request. Null always means scoped
global. Reusing one query for alternating tenants cannot reuse a bound view.

Public methods match Default's `find()` and `findForTenant()` signatures,
including optional actor and tenant parameters. `find()` delegates once to
`findForTenant()`; it does not begin another session. Every `findForTenant()`
uses exactly this order:

1. Assert the supplied Connection is auto-commit, inactive and nesting0.
2. Call `sessions->begin(Status, operationId, currentActor, currentTenant)` once.
   A begin failure becomes fresh `OperationStatusQueryException::storageFailed()`;
   there is no delegate read and no terminal attempt without a returned session.
3. Assert level0 again, construct the per-call bound view/Default Query, then
   call `findForTenant()` exactly once with unchanged operationId/actor/tenant.
   Construction and query failures enter the matrix below. There is no result
   callback passed to Audit and no exception retry.
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
Tenant-qualified restricted subject lookup and authorization run at level0
between them. The Accepted scoped source owns its REPEATABLE READ READ ONLY
detail root, including its before-decode subject recheck, which
must commit/roll back before terminal starts. Thus a successful detailed read
has two Audit write roots plus one source read-only root, not only two total
database transactions. No decrypted data enters an Audit draft.

Crash before requested produces no reader work; after requested it may leave
a requested-only record. Crash/failure before terminal never releases a
result; terminal commit-then-throw may leave a committed terminal but still
returns storageFailed. No compensation or synthetic recovery is attempted.

## Existing Default Query Trace Correction

The preflight PHP8.5.7 probe with `zend.exception_ignore_args=0` confirmed that
Default's unchanged exception captures raw Operation ID, actor and tenant
through unannotated inner trace arguments, even with null previous cause.
Identical exception rethrow must therefore be paired with this narrow
prerequisite inside D2S, not a weaker non-disclosure promise.

Add `#[\SensitiveParameter]` to exactly all14 parameters of
`DefaultOperationStatusQuery`: constructor2, find3, findForTenant3,
findSubject1, isAllowed3, readDetail1 and sourceFailure1. Preserve parameter
names/types/defaults/order/promotion, properties, bodies, control flow, return
values and exception creation/identity. Formatting required for those
attributes is allowed; no snapshot conversion, helper, API/algorithm or
composition change. No other existing Status type changes.

The new adapter tests run canaries with exception argument capture explicitly
enabled and restored afterward, covering the four contracted failure codes
and retained source-failure traces. New annotations hide dependency/subject/
actor/tenant/source-exception arguments on Framework-owned frames, including
legacy direct Default calls; they do not claim to sanitize application-owned
unannotated caller frames. Fixtures mark their own sensitive boundary arguments
so test scaffolding cannot manufacture an unrelated leak. Preserve the fixed
message/code/null-previous contract and identical exception release after the
terminal. Reusing a safe exception must not depend on an environment default
that globally suppresses exception arguments.

## Sensitive State

All input/dependency/request/response/failure parameters carrying sensitive
state use SensitiveParameter. New objects must not expose retained dependency
graphs, raw UUID/reference data or read objects through debug/serialization.
Retain dependencies only in SensitiveParameterValue snapshots; empty
`__debugInfo()` and `__serialize()`, fail-closed `__unserialize()` with the
owner's fixed safe exception. No cloning of a session is introduced. The query
does not retain the operationId, result, session or exception after a call.
No extra runtime port, callback, trait or reflective dispatch is added.

Each new type has exactly one declared property, private
`\SensitiveParameterValue $snapshot`; no trait, superclass, promoted/static
property or extra public/protected method. Query public methods are constructor,
`find`, `findForTenant` and the three safe magic methods. Authorizer replaces
those two read methods with `decide`; execution source with `current`.
All dependency constructor parameters, all find/findForTenant parameters, the
authorization request and `__unserialize` data carry SensitiveParameter.
Private parameters carrying dependencies, requests/results or failures also
carry it. Empty debug/serialization returns use `array<never, never>`.
Unserialize always throws Storage for query, Authorization for authorizer and
ResourceUnavailable AuditDeliveryException for execution source. Private
helpers may only validate/unpack the snapshot, enforce idle state and support
the stated protocol; they cannot add external I/O or a second dispatch path.
Default cloning shares only immutable dependency snapshots and never a session,
result or per-call tenant; no clone override or mutable current-scope field.

## Architecture and Package Boundary

Preserve exact D2 declarations, signatures, helpers, nonce grammar and all its
callback/mutation/false-positive controls. Extend its outside-reference policy
only for the exact new query's factory/session/purpose references and the exact
new execution source's interface/reference types. Do not whitelist an entire
directory or exempt a new file from dependency/call validation. The query may
begin/terminate a session but never call session open, writer or Store directly.
The execution source may only generate its execution identity. The guard has
no Audit dependency. New production constructors of the session factory, audited query, execution
source and ApplicationAuditRuntimeComposer remain zero. The tenant guard is
constructed only by the new audited query constructor; the bound view only by
its per-call `findForTenant()`. No external caller of scoped source methods is
added beyond the already Accepted bound-view delegations. Existing reader/composer paths stay unchanged.

A dedicated Architecture Test fixes all three new declarations, public methods,
dependencies and executable callsites, the exact tenant comparison/order,
zero activation and no alternate terminal/writer path. It must reject mutations
of the actual oracle: missing tenant guard, different tenant member comparison,
app-authorizer-before-guard, duplicate begin/terminal, accessed-to-unavailable
fallback, extra caller, source-derived/constant execution, and indirect writer/
session-open references. Include matching positive/false-positive controls.

Add exactly the three new Production paths to both package inventories,
immediately after `src/Internal/Audit/Read/OperationReadAuditSession.php`, in
query/tenant-authorizer/execution-source order. Preserve all Accepted87 paths and
their relative order: exact90 unique. Retain all35 existing negative package
controls. Add four independent controls: omission of each new path and reversal
of the three-path block (exact39 total). Update the whole-export SHA pin only
after both inventories and the complete changed export script pass independent
review. No other package/export-policy change.

## Required Evidence

- Unit event-order and zero-work sentinels for both public entries and every
  dynamically reachable matrix branch; all four contracted query codes; result/exception identity;
  requested/terminal failures and commit-then-throw; no second terminal.
  Since Default is final and constructed internally, do not add a mock delegate
  or Product seam to observe its intermediate result. Unit tests prove returned
  status identity and each contracted code; the executable AST oracle proves
  the returned result/caught contracted exception is released unchanged.
  The sealed Default returns only Found/Expired/Unavailable and converts
  dependency throwables. Defensive unknown-result and adapter catch-all rows
  therefore use actual AST/mutation evidence; no unreachable end-to-end unit
  branch or mock delegate seam is required.
- Tenant matrix null/null, null/present, present/null, same/different type,
  same/different id; permissive Application authorizer cannot override mismatch.
  Validate no detail/authorizer call on mismatch and unchanged legacy tests.
  Alternate null/tenant-A/tenant-B reads through the same query and source.
  Nonexistent/foreign/denied and foreign-ciphertext fixtures perform zero decode.
  Connect subject move/disappearance to unavailable and same-scope identity
  drift to Integrity, each with its correct committed terminal.
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
  Product/Test bytes; local commit/export after Orchestrator Acceptance. The
  Task selects checks under Specification109: Final full PHPUnit subsumes the
  same-input focused tests, rather than repeating every successful iteration.

## Release and Documentation

No public source/route/config/API change or new Stable claim. No Guide/Website
rewrite is needed for an unactivated Internal adapter. This specification is
the implementation-facing contract; public/internal usage instructions wait
for the composition Task. Generated HTML/Search/raw Markdown/LLM, browser,
CI/delivery and Production deployment must not be inferred from source checks.
