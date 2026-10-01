# Operation Diagnostics Read Audit Adapter

Status: Authority Green — Product progress in D2X Report; unreleased 1.3

## Scope and Precedence

This is the most-specific P23-003D2X contract, with rationale in D161.
D154/D155 retain the fixed Catalog, requested/terminal session and required
Store contracts. Spec65 retains the global Internal Diagnostics aggregate,
safe projection, Found/Unavailable values and three failure codes. The current
Framework/Skeleton release remains Experimental Stable1.2.1.

This child adds an unactivated audited adapter and one execution source. It
does not change OperationDiagnosticsQuery, its readers/projectors, public API,
SQL, CLI/viewer wiring, runtime composition, schema, dependency or configuration.
The current local CLI/Viewer continues to use its existing query until parent
integration. Status/Journal/Outcome tenant-scoped readers remain unchanged.

## Global Infrastructure Authorization

Diagnostics is a privileged global view for trusted local infrastructure. Its
existing sources do not implement per-tenant Diagnostics queries, and the safe
aggregate does not contain a target TenantRef. Do not infer a target tenant,
use a Status subject lookup as an existence filter, or describe the global
query as tenant-isolated. Such a filter would also suppress existing dangling
Dead Letter integrity errors and change aggregate semantics.

The new adapter accepts only a null currentTenant for an authorized read. Null
here denotes the existing global Diagnostics infrastructure scope, including
operations belonging to a tenant; it does not change null-scope semantics in
Status/Journal/Outcome. A non-null currentTenant produces the same Unavailable
as denial, after requested commit, with no authorizer or Diagnostics reader call.
There is no matching-tenant exception or permissive fallback in this child.

For a null currentTenant, call the existing OperationDiagnosticsAuthorizer once
with a fresh OperationDiagnosticsAuthorizationRequest containing the same
OperationId/currentActor/currentTenant objects. Only AuditReadAuthorizationDecision
Allow permits the aggregate call. Deny and ResourceUnavailable both produce
OperationDiagnosticsUnavailable. The existing default-unavailable authorizer
is unchanged; no always-Allow production authorizer is added.

Parent composition must bind an explicitly trusted policy behind the CLI's
local OS authority or the Viewer's enable/loopback/session gates. It must not
derive authority from a raw request ActorRef, from null tenant alone, or reuse
the Viewer token as Application authentication. The policy port is not a new
public Application/Tenant Diagnostics surface. Its activation remains a parent
review gate, including the correct runtime/build and same Connection binding.

## Internal Types and State

Add final readonly AuditedOperationDiagnosticsQuery in Internal/Diagnostics.
It has no parent, implemented interface or PublicApi marker. Its constructor
arguments, in order, are:

```text
CanonicalJournalReader journal
OutcomeReader outcomes
DiagnosticsSourceReader sources
OperationDiagnosticsAuthorizer authorizer
OperationReadAuditSessionFactory sessions
Doctrine\DBAL\Connection connection
```

Construct one concrete OperationDiagnosticsQuery(journal, outcomes, sources),
using its existing default lifecycle/projector/time collaborators. No constructor
I/O, prebuilt query, arbitrary callback or subclass is accepted. Retain only one
private SensitiveParameterValue snapshot with exact ordered keys query,
authorizer, sessions, connection and their concrete/interface type checks.

Its only query method is:

```php
public function find(
    #[\SensitiveParameter] OperationId $operationId,
    #[\SensitiveParameter] ?ActorRef $currentActor,
    #[\SensitiveParameter] ?TenantRef $currentTenant,
): OperationDiagnosticsResult;
```

All three arguments are required; the nullable references are explicit. This
new Internal method does not modify the legacy find(OperationId) method.
Other public methods are __construct, __debugInfo, __serialize and __unserialize;
the only private helpers are assertIdle(Connection): void and value(string): mixed.
Every declared parameter has native SensitiveParameter. Debug and serialization
return empty arrays; unserialization always throws a fresh storageFailed.
Unreadable/uninitialized/corrupt snapshot or unexpected key/type is storageFailed
with no I/O. Default clone performs no I/O and shares only opaque dependency
state. No caller, request, decision, result or exception is retained between calls.

Add final readonly OperationDiagnosticsReadExecutionSource in Internal/Diagnostics,
implementing OperationReadExecutionSource. Follow the accepted per-read source
pattern: __construct(Uuidv7Generator), current(): AuditTypedReference, the three
safe magic methods and private value(string): mixed. All parameters are sensitive;
its sole snapshot has exact key identifiers containing a ValidatedUuidv7Generator.
current() generates exactly once per invocation and returns type
operation_diagnostics_read with that validated canonical UUIDv7. Reuse/clone does
not cache a reference. Target, actor, tenant, environment and client input cannot
supply it. Invalid generator output, throw or snapshot failure becomes a fresh
AuditDeliveryException(ResourceUnavailable), with no previous cause. This UUID
is independent of the D2 nonce and Store-owned record/segment UUIDs.

## Ordering and Transaction Boundary

1. Validate dependency state and require auto-commit true, inactive transaction,
   nesting level0. No audit/authorization/read work occurs on entry failure.
2. Call sessions->begin exactly once with fixed AuditReadPurpose::Diagnostics
   and the unchanged target/actor/tenant. A failure maps to storageFailed and
   performs no authorization, query or terminal attempt.
3. Recheck idle state, then apply the global-scope guard and typed authorization
   above. Any authorizer throwable, including a Diagnostics exception or return
   TypeError, selects DiagnosticsFailureCode::StorageFailed and zero query calls.
4. Recheck idle state after the authorization phase, before any query call.
   On Allow only, invoke the concrete aggregate once and fully materialize its
   result. No lazy Journal prefix can escape. Keep only the recognized safe
   result or a DiagnosticsFailureCode; discard the original exception after
   reading its code. An unreadable code is StorageFailed.
5. Recheck idle state after the query phase. Invoke one accessed terminal for
   Found, otherwise one unavailable terminal, outside the authorization/query
   catch blocks. Recheck idle state after terminal success.
6. Return the original recognized result object, or construct and throw a fresh
   matching Diagnostics exception only now. No result or failure escapes earlier.

Every idle inspection/guard failure is a fresh storageFailed. If authorization
or a reader leaves the Connection non-idle, withhold the result/failure and do
not attempt a terminal on that invalid Connection. The adapter owns no begin,
commit, rollback, savepoint, repair, retry or fallback. Terminal failure always
wins as fresh storageFailed, even if a terminal actually committed before throwing.
Never retry it or switch to another terminal. The actual committed prefix remains.

Requested and terminal are two separate required Store-owned write roots with
global auto-commit aggregate reads between them. This is not a Status-style
repeatable-read snapshot and introduces no tenant-specific aggregate SQL.

## Result and Failure Mapping

| Selected path | Query work | Terminal | Released value after terminal success |
| --- | --- | --- | --- |
| Non-null requester tenant, Deny or ResourceUnavailable | none | unavailable | OperationDiagnosticsUnavailable / operation.unavailable |
| Allow, missing/fully purged identity | existing full aggregate lookup | unavailable | Original OperationDiagnosticsUnavailable |
| Allow, safe Found | existing full aggregate lookup | accessed | Original OperationDiagnosticsFound |
| Allow, existing StorageFailed/DecodeFailed/IntegrityFailed | existing aggregate | unavailable | Fresh same-type, same-code OperationDiagnosticsException |
| Authorizer throwable or unexpected query throwable | zero query for authorizer failure | unavailable | Fresh storageFailed |
| Unexpected result implementation | one aggregate call | unavailable | Fresh integrityFailed |
| Begin/idle/terminal failure | bounded by phase above | no extra attempt | Fresh storageFailed |

This explicitly specializes D155's reader-child exception identity rule for
Diagnostics only. Type, diagnosticsCode, fixed message, code0 and null previous
remain compatible; original exception object identity is deliberately not kept.
The three existing static factories construct the outward exception. No original
Throwable, its previous chain, raw record, payload, outcome or reader dependency
may cross the terminal boundary or enter Audit evidence. The legacy query and
its existing exception behavior are not modified by this child.

Native trace-argument fixtures must set zend.exception_ignore_args=0 and restore
it afterward. Exercise actual legacy readerFailure and raw Journal/projector
frames with canaries, then inspect outward message/code/previous/trace arguments/
trace string/debug/serialization/JSON/var_export. Snapshot and generator fixtures
use actual retained dependency canaries and serialization roundtrips. Arbitrary
unannotated Application frames above this adapter remain the Application boundary.

## Architecture and Package

The existing D2 outside-target allowance may admit exactly these two files for
their required factory/session/execution and Diagnostics request/authorizer edges.
Reject external callers, writer access, session open, factory construction,
dynamic dispatch and runtime activation; keep all previous controls. The scoped
new oracle parses only the two new sources, uses installed AST/name resolution,
and has bounded syntax-valid mutation/false-positive fixtures. Runtime tests own
branch behavior. No new parser engine, broad directory exception or token cache.

Append these two source paths after the accepted D2J block, adapter then execution
source. Preserve all95 prior paths' relative order:97 unique ordered entries.
Retain all45 prior package controls and add each new path's omission plus reversed
new block:48 controls. Pinning remains the scripts' existing responsibility.

## Verification and Delivery

The Task freezes exact9 Product/Test paths, owners and commands. Use focused
Diagnostics units, the new oracle, the existing D2 graph check without its heavy
mutation method, and real two-Connection PostgreSQL fixtures during iteration.
The final frozen candidate requires one full PHPUnit under shared512M, quality
checks and independent security/transaction review. Do not duplicate same-input
subsets at Final. Reuse unchanged Catalog/Store/Composer/Schema and public website
evidence; local acceptance does not assert clean-SHA package export or CI delivery.

No public capability/version text, route, Search/LLM artifact or runtime binding
is changed. This specification is the implementer contract. Runtime documentation,
same-SHA CI/export and public proof accompany later selected composition/delivery.
