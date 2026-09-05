# D155: Operation Read Audit Session Foundation

Status: Proposed — P23-003D1 Accepted; P23-003D2 authority review Green／clean post-commit implementation preflight required before Product work; BlackOps 1.3 unreleased

Updated At: 2026-09-05T12:20:14+09:00

## Current Handoff

P23-003C1 through P23-003C4 and parent P23-003C are Accepted. P23-003D1 is
Accepted at commit `35afacd9741272d4e1207d3a76829d85653e4f6f`; its final
tree is clean and staged0, and the Git／Composer package export is Green from
that exact SHA. No push was performed.

D154 splits P23-003D into bounded children. D1 added only the sealed
Framework operational Catalog and trusted lookup lineage. D2 now owns the
Operation-read request／session foundation. It does not yet modify or activate
the Status, Journal, Outcome, or Diagnostics readers.

Stable `1.2.0` remains current. BlackOps `1.3`, D2, and the parent P23-003D
remain unreleased.

## Context

The Accepted security／access Catalog already contains the exact twelve
`access.operation_{status,journal,outcome,diagnostics}` requested,
unavailable, and accessed definitions. Accepted Core also provides
`AuditReadRequestBasis`, `AuditReadContext`, `AuditReadPurpose`, the requestRef
preimage, and the three-state authorization decision.

The current readers cannot safely emit those records yet:

- Status, Journal, and Outcome locate a subject before or as part of their
  authorization path;
- Diagnostics has no typed authorization facade and reads its stores
  immediately;
- no current reader input carries a trusted execution reference, source
  runtime, or build identifier;
- the Storage Protection nonce source produces 24-byte BOPD nonces and is not
  the required 16-byte Operation-read request nonce; and
- exposing caller-supplied `AuditRecordDraft` values would allow a producer to
  choose resource, policy, source, or more than one terminal record.

There is also one Accepted C3 behavior that cannot satisfy the stronger
Operation-read persistence contract. `AuditProtectedReadGate::terminal()`
currently suppresses binding, transaction, and Store failures for `Deny` and
`ResourceUnavailable`. Specifications 105 and D147 require the selected
terminal record to commit before any denial, absence, cross-tenant result, or
failure is released. An outer wrapper cannot observe whether the suppressed
append succeeded. D2 therefore must explicitly supersede that bounded C3
behavior.

## Decision

### 1. D2 is a foundation followed by reader children

P23-003D2 implements only:

- one Internal writer port backed solely by `AuditProtectedReadGate`;
- one dedicated 16-byte nonce port and CSPRNG implementation;
- one trusted execution-source port with no fallback implementation;
- one factory that creates a basis and all three fixed drafts before the
  requested append;
- one private-construction, one-terminal session;
- one typed Diagnostics authorization request／port and default-unavailable
  implementation; and
- the fail-closed Gate correction described below.

The following integrations remain separate serial children:

```text
P23-003D2 foundation
  +-> P23-003D2S Status integration --------+
  +-> P23-003D2J Journal／Outcome integration +-> P23-003D-integration
  +-> P23-003D2X Diagnostics integration ----+
```

The children may proceed independently after the foundation is Accepted but
must not silently depend on each other. D2 foundation creates no current
Production caller of its factory.

### 2. Exact Internal foundation API

Add interface
`BlackOps\Internal\Audit\Read\AuditReadEventWriter` with exactly:

```php
public function requested(
    #[\SensitiveParameter] AuditReadRequestBasis $basis,
    #[\SensitiveParameter] AuditRecordDraft $draft,
): void;

public function terminal(
    #[\SensitiveParameter] AuditReadContext $context,
    #[\SensitiveParameter] AuditRecordDraft $draft,
): AuditReadAuthorizationDecision;
```

`AuditProtectedReadGate` is the only Production implementation. No other
class implements, decorates, proxies, dynamically invokes, or reflects this
port in D2.

Add interface
`BlackOps\Internal\Audit\Read\OperationReadNonceSource` with exactly:

```php
public function generate(): string;
```

Add final readonly implementation
`BlackOps\Internal\Audit\Read\RandomOperationReadNonceSource`. Its only
public method is `generate(): string`, and that method returns the result of
exactly one `random_bytes(16)` call. It has no retry, fallback, environment,
configuration, filesystem, network, clock, UUID, or shared BOPD nonce
dependency.

Add interface
`BlackOps\Internal\Audit\Read\OperationReadExecutionSource` with exactly:

```php
public function current(): AuditTypedReference;
```

D2 adds no Production implementation. Each reader child must bind an exact
trusted HTTP, Operation-scope, CLI, viewer, worker, or scheduler source. A
missing scope cannot become a generic or invented execution identity.

Add final class
`BlackOps\Internal\Audit\Read\OperationReadAuditSessionFactory` with exactly
one private `SensitiveParameterValue $snapshot` and the following exact public
API; it has no other property or public／protected callable:

```php
public function __construct(
    #[\SensitiveParameter] AuditReadEventWriter $writer,
    #[\SensitiveParameter] HmacAuditOpaqueIdDeriver $opaqueIds,
    #[\SensitiveParameter] OperationReadNonceSource $nonces,
    #[\SensitiveParameter] ClockInterface $clock,
    #[\SensitiveParameter] OperationReadExecutionSource $executions,
    AuditSourceRuntime $runtime,
    #[\SensitiveParameter] string $buildId,
);

public function begin(
    AuditReadPurpose $purpose,
    #[\SensitiveParameter] OperationId $operationId,
    #[\SensitiveParameter] ?ActorRef $currentActor,
    #[\SensitiveParameter] ?TenantRef $currentTenant,
): OperationReadAuditSession;

public function __debugInfo(): array;
public function __serialize(): array;
public function __unserialize(
    #[\SensitiveParameter] array $data,
): void;
```

The constructor validates the source build identifier without consulting a
Clock, nonce, key, database, container, or execution scope. It retains every
dependency and the build identifier only inside its
`SensitiveParameterValue` snapshot. Validation is exactly
`AuditValidationException::assertSafeString($buildId, 1, 256)`: no trim,
case-fold, normalization, or alternate grammar is applied. Empty, 257-byte,
Cc／Cf, invalid UTF-8, and non-NFC values become fixed
`AuditDeliveryException(configuration_invalid)`; valid 1- and 256-byte values
are retained byte-for-byte. `__debugInfo()` and `__serialize()` return exact
empty arrays. `__unserialize()` always throws a fixed value-free
`AuditDeliveryException(storage_unavailable)`. The class declares no
`__clone()`; default cloning performs no I/O and shares only the same opaque
immutable `SensitiveParameterValue` object.

Its exact private callable inventory is:

```php
private function value(#[\SensitiveParameter] string $key): mixed;
private function reference(
    #[\SensitiveParameter] ActorRef|TenantRef|null $reference,
): ?AuditTypedReference;
private function execution(): AuditTypedReference;
private function resourceId(
    #[\SensitiveParameter] OperationId $operationId,
): string;
private function requestedAt(): string;
private function nonce(): string;
private function definition(
    AuditEventCatalog $catalog,
    AuditReadPurpose $purpose,
    string $phase,
): AuditEventDefinition;
private function draft(
    AuditEventCatalog $catalog,
    AuditEventDefinition $definition,
    #[\SensitiveParameter] AuditReadRequestBasis $basis,
    #[\SensitiveParameter] string $resourceId,
): AuditRecordDraft;
private function component(AuditReadPurpose $purpose): string;
```

There is no additional factory helper, trait, inherited callable, dynamic
callable, closure, callback, reflection access, or class-string construction.

Add final class
`BlackOps\Internal\Audit\Read\OperationReadAuditSession`. It has private
construction, one private `SensitiveParameterValue $snapshot`, one private
boolean `$terminalAttempted = false`, and exactly these public methods:

```php
public static function open(
    #[\SensitiveParameter] AuditReadEventWriter $writer,
    #[\SensitiveParameter] AuditReadRequestBasis $basis,
    #[\SensitiveParameter] AuditRecordDraft $requested,
    #[\SensitiveParameter] AuditRecordDraft $accessed,
    #[\SensitiveParameter] AuditRecordDraft $unavailable,
): self;

public function accessed(): void;
public function unavailable(): void;
public function __clone(): void;
public function __debugInfo(): array;
public function __serialize(): array;
public function __unserialize(
    #[\SensitiveParameter] array $data,
): void;
```

Its exact private callable inventory is:

```php
private function __construct(
    #[\SensitiveParameter] AuditReadEventWriter $writer,
    #[\SensitiveParameter] AuditReadRequestBasis $basis,
    #[\SensitiveParameter] AuditRecordDraft $accessed,
    #[\SensitiveParameter] AuditRecordDraft $unavailable,
);
private function terminal(
    AuditReadAuthorizationDecision $decision,
    #[\SensitiveParameter] string $draftKey,
): void;
private function value(#[\SensitiveParameter] string $key): mixed;
```

`open()` first invokes the private constructor and completes the session's
`SensitiveParameterValue` snapshot. Only after that local construction has
succeeded does it call `writer->requested()` exactly once. After the writer
call begins, `open()` performs no validation, construction, allocation, or
other callable invocation; a successful call is followed only by the pure
`return $session`. A local construction throwable becomes fixed
`storage_unavailable` with zero writer／Store work. A writer that commits and
then throws, or a process crash after its commit, remains the explicit crash
window below; it is not reclassified as local construction failure.

`open()` therefore commits the requested record before returning the already-
constructed session. Only the factory calls `open()` in Production. `accessed()` selects
`AuditReadAuthorizationDecision::Allow`; `unavailable()` selects
`AuditReadAuthorizationDecision::ResourceUnavailable`. There is no public or
private Deny terminal producer.

The session marks itself terminal-attempted before reading its snapshot,
constructing the context, or calling the writer. A second terminal call or
clone throws fixed `policy_invalid` before writer／Store work. `__debugInfo()`
and `__serialize()` return exact empty arrays without changing lifecycle state;
serialization therefore succeeds only as an empty representation.
`__unserialize()` throws fixed `storage_unavailable` before writer／Store work.
A malformed or unreadable snapshot also becomes fixed `storage_unavailable`
after permanently closing the terminal attempt. The class declares no
destructor and performs no synthetic terminal recovery. Its snapshot contains
only the writer, basis, and two prebuilt terminal drafts; it never contains the
raw Operation ID or raw opaque key. Debug, serialize, JSON, trace, and
`var_export()` surfaces expose none of those retained values.

After a terminal append succeeds, the session requires the decision returned
by `AuditReadEventWriter::terminal()` to be identical to the selected decision.
A mismatch becomes fixed `policy_invalid`; the session remains closed and no
reader result or failure may be released.

### 3. Exact basis and draft construction

`begin()` performs one closed construction in this order:

1. Convert the optional current actor and tenant without trim, case-fold, or
   normalization:
   `new AuditTypedReference($ref->type(), $ref->id())`.
2. Call the injected execution source exactly once, then copy its public
   `type`／`id` into a new `AuditTypedReference`. This revalidates reflected or
   otherwise malformed returned state; object identity is not retained.
3. Materialize the raw Operation ID only as the sensitive second argument of
   `HmacAuditOpaqueIdDeriver::derive(AuditOpaqueIdPurpose::Resource,
   $operationId->toString())`; call it exactly once.
4. Capture the injected Clock exactly once, convert to UTC, and require exact
   `YYYY-MM-DDTHH:MM:SS.ffffffZ` with a four-digit year.
5. Call the nonce source exactly once, require exactly 16 raw bytes, and use
   `bin2hex()` to obtain exactly 32 lowercase hexadecimal characters.
6. Construct one `AuditReadRequestBasis` from the caller-selected purpose,
   converted actor／tenant, trusted execution, one timestamp, and one nonce.
7. Resolve the exact requested, accessed, and unavailable version-1
   definitions from the singleton `FrameworkAuditEventCatalog::catalog()` and
   construct all three drafts before any writer call.
8. Call `OperationReadAuditSession::open()` once.

The raw Operation ID is not copied into basis, action, correlation, evidence,
source, exception, or session state. The HMAC vector is fixed:

```text
key=0123456789abcdef0123456789abcdef
purpose=resource
context=0194a000-0000-7000-8000-0000000000d2
preimageBytes=83
opaque=oid:v1:e697b9f4332f3532bd84ba2bbe553152c5d223663d3bdec36e8b38e420f1d115
```

For every purpose and phase, the draft is exact:

```text
event.type=access.operation_<purpose>.<phase>
event.version=1
event.category=access
event.catalogHash=cfbcb3335b27ac4506046216d7ff43a9802586b1e60fbb9cfc47dc12230b1d83
action.id=<basis.requestRef>
action.type=<event.type>
actor.principal=<basis.actor>
actor.execution=<basis.execution>
resource.type=operation
resource.id=<opaque Operation ID>
policy.id=operation-read
policy.version=1
policy.auditDelivery=required
tenant=<basis.tenant>
occurredAt=<basis.requestedAt>
source.owner=framework
source.component=operation-<purpose>
source.runtime=<trusted constructor runtime>
source.buildId=<trusted constructor buildId>
correlation={}
evidence={version:1}
```

Phase, outcome, policy decision, and reason are copied from the one exact
Catalog tuple. Requested is `requested/unknown/read_requested/not_applicable/
read_requested`; accessed is
`succeeded/succeeded/read_accessed/allow/authorization_allowed`; unavailable
is `unknown/unknown/resource_unavailable/not_applicable/
resource_unavailable`.

The canonical requestRef vectors for actor `user:actor-1`, execution
`process:blackops-cli`, tenant `account:tenant-1`, requestedAt
`2026-09-05T02:00:00.123456Z`, and nonce
`00112233445566778899aabbccddeeff` are:

| purpose | JCS bytes | LP preimage bytes | requestRef |
| --- | ---: | ---: | --- |
| status | 241 | 283 | `oid:v1:5a83343aa8fcf1e3baba2147571a92fa35c5424507c0b27407a0ca19766a3b3a` |
| journal | 242 | 284 | `oid:v1:2726a66731f81f741ccb83a471637381cd80b944687aa9889b6975f100eb5096` |
| outcome | 242 | 284 | `oid:v1:3ac6231d80a97591552b92f933f4a1e50efd69e93c149961da65855f1c4b2e67` |
| diagnostics | 246 | 288 | `oid:v1:aa763c94292e005ea5dc420909a1b90372602265d3fe9318ae053b1b214fca4c` |

### 4. Fail-closed writer correction

This Decision supersedes only D152's rule that a non-Allow terminal decision
survives Gate binding／transaction／Store failure. Every other Accepted C3 API,
transaction, selector, Store, key, composition, and failure contract remains
unchanged.

`AuditProtectedReadGate` implements `AuditReadEventWriter`. Both methods split
binding from delivery:

- an explicit binding `AuditDeliveryException` is rethrown unchanged;
- an unexpected binding throwable becomes fixed `policy_invalid` with null
  previous;
- an explicit delivery `AuditDeliveryException` is rethrown unchanged; and
- an unexpected delivery throwable becomes fixed `storage_unavailable` with
  null previous.

`terminal()` returns the input decision only after a successful required
append, for Allow, Deny, and ResourceUnavailable alike. It never suppresses a
failure.

The Gate binding expands to require the exact version-1 Framework read shape:

- exact purpose-derived event/action type and terminal phase;
- event category `access`, version 1, and the Accepted security／access Catalog
  hash;
- exact requestRef, actor, execution, tenant, and requestedAt basis binding;
- resource type `operation` and lowercase `oid:v1:<64-hex>` resource ID;
- policy ID `operation-read`, version 1, exact tuple decision／reason, and
  required delivery;
- source owner Framework and component `operation-<purpose>`;
- empty correlation and exact evidence `{version:1}`; and
- the exact Catalog phase／outcome tuple for the selected event.

Source runtime and build ID are typed／validated values supplied by the
factory constructor. The Gate does not receive or reconstruct the raw
Operation ID and therefore does not claim to recompute its HMAC. It validates
that runtime is the typed `AuditSourceRuntime` already carried by the draft and
that build ID still satisfies exact safe-string `1..256` bytes. It does not
compare them with separately injected expected values because its constructor
has no such values. Exact factory construction, draft-field, and sole
Production caller guards prove that the runtime／build pair came from the
factory's validated constructor snapshot. A valid-but-different runtime or
build mutation is therefore rejected by the factory golden／Architecture
oracles, not by a direct Gate expectation that the Gate cannot possess.

### 5. Diagnostics authorization facade

Add final Internal class
`BlackOps\Internal\Diagnostics\OperationDiagnosticsAuthorizationRequest`.
It retains its values only inside one private `SensitiveParameterValue` and
has exactly:

```php
public function __construct(
    #[\SensitiveParameter] OperationId $operationId,
    #[\SensitiveParameter] ?ActorRef $currentActor,
    #[\SensitiveParameter] ?TenantRef $currentTenant,
);
public function operationId(): OperationId;
public function currentActor(): ?ActorRef;
public function currentTenant(): ?TenantRef;
```

Its debug／serialize surfaces are empty and unserialization always fails with a
fixed value-free `LogicException`. Its exact remaining public and private API
is:

```php
public function __debugInfo(): array;
public function __serialize(): array;
public function __unserialize(
    #[\SensitiveParameter] array $data,
): void;
private function value(#[\SensitiveParameter] string $key): mixed;
```

Both debug and serialization return exact empty arrays. Unserialization throws
`LogicException('Operation diagnostics authorization request cannot be unserialized.', 0, null)`.
The private `value()` helper maps an unreadable or structurally corrupt
snapshot to
`LogicException('Operation diagnostics authorization request is unavailable.', 0, null)`.
The class declares no `__clone()`; default cloning performs no I/O and retains
the same Operation ID／actor／tenant object identities through the shared opaque
`SensitiveParameterValue`. It has no policy, origin identity, subject, result,
Store, or Audit dependency.

Add interface
`BlackOps\Internal\Diagnostics\OperationDiagnosticsAuthorizer` with exactly:

```php
public function decide(
    #[\SensitiveParameter] OperationDiagnosticsAuthorizationRequest $request,
): AuditReadAuthorizationDecision;
```

Add final readonly
`BlackOps\Internal\Diagnostics\UnavailableOperationDiagnosticsAuthorizer`, with no
properties or constructor and exactly one public `decide()` method. It always
returns `AuditReadAuthorizationDecision::ResourceUnavailable`. It does not
inspect the request and performs no I/O.

D2X later injects this port before any Diagnostics Store read and fixes the
trusted CLI／viewer authorization policy. Foundation D2 does not modify
`OperationDiagnosticsQuery`, the console factory, or the viewer.

### 6. Failure and transaction precedence

The existing six-case `AuditDeliveryFailure` enum is the only failure type;
D2 adds no enum or case. The first applicable boundary and mapping are exact:

1. constructor build-ID validation rejection -> `ConfigurationInvalid`;
2. actor／tenant getter or copied-reference validation rejection ->
   `PolicyInvalid`;
3. execution-source invocation throwable, including a return `TypeError` ->
   `ResourceUnavailable`;
4. an execution object returned successfully but rejected while copying its
   `type`／`id` -> `PolicyInvalid`;
5. opaque-ID derive／cast throwable -> `KeyUnavailable`;
6. Clock invocation, UTC conversion, four-digit-year／six-fraction timestamp
   capacity, or nonce-source／`random_bytes` invocation throwable ->
   `ResourceUnavailable`;
7. a successfully returned nonce whose byte length is not exactly 16 ->
   `ConfigurationInvalid`;
8. Catalog identity／definition／tuple, basis, Draft, or writer binding mismatch
   -> `PolicyInvalid`;
9. local session／snapshot construction throwable before requested writer
   invocation -> `StorageUnavailable`, with zero writer／Store work;
10. caller-owned／invalid ambient transaction or successful-Store post-state
   drift -> `TransactionInvalid`;
11. Accepted Store `ResourceUnavailable` -> `ResourceUnavailable`;
12. any other writer／Store／database throwable -> `StorageUnavailable`;
13. unreadable or structurally corrupt factory／session
    `SensitiveParameterValue` state -> `StorageUnavailable`; and
14. duplicate terminal, clone, or writer-returned decision mismatch ->
    `PolicyInvalid`.

An explicit `AuditDeliveryException` from the writer is rethrown unchanged at
the requested and terminal boundaries. Unexpected writer throwables are fixed
`StorageUnavailable`. Actor／tenant conversion occurs before the first factory
snapshot read; otherwise corrupt internal snapshot state wins before any
resource call. The terminal flag is set before session snapshot validation, so
every terminal-path failure is one-shot.

All new failures have a fixed message, null previous, and no raw target,
principal, tenant, execution, nonce, key, provider, draft, query result, or
throwable detail on message／code／previous／trace argument／trace string／debug／
serialize／JSON／`var_export()` surfaces.

`T-requested` and `T-terminal` are two separate Store-owned standalone
transactions. The writer and session call no begin, commit, rollback,
savepoint, retry, fallback, or downgrade. Each entry requires auto-commit true,
inactive level0 before the Store and the same state afterward. Authorization
and lookup occur between them at level0. A caller-owned level1 root is invalid
for either append.

Terminal failure always supersedes the selected query result or existing
contracted query failure. It never stores that result or failure as previous.
Only after terminal success may a reader child return its result or rethrow the
same already-value-free query exception object.

### 7. Crash and retry semantics

- crash before requested commit: no authorization, lookup, result, or Audit
  record is released;
- crash after requested commit and before terminal: exactly the requested
  record remains, no result is released, and no synthetic recovery record is
  written;
- crash after terminal commit and before response: exactly requested plus one
  terminal record remain;
- a client retry creates a new nonce, basis, requestRef, requested record, and
  terminal record; and
- the same session never retries a terminal attempt.

Record and segment UUID allocation remain exclusively inside the Accepted
required Store. D2 creates no UUID or idempotency key. Every required
`AuditDeliveryRequest` carries `null` idempotency key.

### 8. Architecture, package, and nonactivation

The nine new source paths are Internal and have no `#[PublicApi]` marker. D2
adds no migration, configuration key, Application runtime member, composer
call, Container service, route, command, middleware, reader constructor,
worker, scheduler, maintenance registration, public Documentation, or release
claim.

Architecture evidence fixes exact declarations, parents／interfaces,
properties, public and private callables, parameter／return types, nullability,
defaults, modifiers, SensitiveParameter attributes, direct call graph,
dependency graph, and no dynamic／class-string／reflection invocation. It scans
all Production source and includes non-vacuous mutation and false-positive
fixtures. Accepted A1／A2／B／C／D1 caller and dependency maps change only for the
listed D2 direct edges.

The project-type dependency inventory of each new source is exact:

| Source | Direct project dependencies |
| --- | --- |
| `AuditReadEventWriter` | `AuditReadRequestBasis`, `AuditRecordDraft`, `AuditReadContext`, `AuditReadAuthorizationDecision` |
| `OperationReadNonceSource` | none |
| `RandomOperationReadNonceSource` | `OperationReadNonceSource` |
| `OperationReadExecutionSource` | `AuditTypedReference` |
| `OperationReadAuditSessionFactory` | `ActorRef`, `TenantRef`, `OperationId`, `AuditDelivery`, `AuditEventCatalog`, `AuditEventDefinition`, `AuditOpaqueIdPurpose`, `AuditReadPurpose`, `AuditReadRequestBasis`, `AuditRecordAction`, `AuditRecordActor`, `AuditRecordCorrelation`, `AuditRecordDraft`, `AuditRecordEvent`, `AuditRecordEvidence`, `AuditRecordOutcome`, `AuditRecordPolicy`, `AuditRecordResource`, `AuditRecordSource`, `AuditSourceOwner`, `AuditSourceRuntime`, `AuditTypedReference`, `AuditValidationException`, `HmacAuditOpaqueIdDeriver`, `FrameworkAuditEventCatalog`, `AuditDeliveryException`, `AuditDeliveryFailure`, `AuditReadEventWriter`, `OperationReadNonceSource`, `OperationReadExecutionSource`, `OperationReadAuditSession`, `ClockInterface` |
| `OperationReadAuditSession` | `AuditReadAuthorizationDecision`, `AuditReadContext`, `AuditReadRequestBasis`, `AuditRecordDraft`, `AuditDeliveryException`, `AuditDeliveryFailure`, `AuditReadEventWriter` |
| `OperationDiagnosticsAuthorizationRequest` | `OperationId`, `ActorRef`, `TenantRef` |
| `OperationDiagnosticsAuthorizer` | `AuditReadAuthorizationDecision`, `OperationDiagnosticsAuthorizationRequest` |
| `UnavailableOperationDiagnosticsAuthorizer` | `AuditReadAuthorizationDecision`, `OperationDiagnosticsAuthorizationRequest`, `OperationDiagnosticsAuthorizer` |

The only non-project class dependencies permitted by that table are
`Psr\Clock\ClockInterface`, `SensitiveParameterValue`, `DateTimeZone`,
`SensitiveParameter`, `LogicException`, and `Throwable`; the table's
`ClockInterface` means that PSR type. The modified Gate adds only the writer interface plus direct Core
binding dependencies `AuditActionPhase`, `AuditDelivery`, `AuditEventCategory`,
`AuditOutcomeStatus`, `AuditPolicyDecision`, `AuditSourceOwner`, and
`AuditValidationException`; all of its Accepted dependencies remain otherwise
exact.

The exact direct-call graph is also closed: factory `begin()` calls actor／
tenant `reference()` once per supplied axis, `execution()`, `resourceId()`,
`requestedAt()`, and `nonce()` once each, the Framework Catalog once,
`definition()` and `draft()` exactly three times each, and session `open()`
once. Only factory calls session `open()`. Session `open()` calls writer
`requested()` once, strictly after private construction／snapshot completion
and strictly before a pure return. One selected terminal method calls writer `terminal()`
once and verifies its returned decision. Gate `requested()` and `terminal()`
each call coordinator `deliverStandalone()` once only after binding succeeds.
No other Production caller, receiver, helper, dependency, or implementation is
allowed. Architecture mutations cover added／removed／renamed helper or
dependency, alias／group-use／FQCN, same-namespace and vendor FQCN references,
literal／one-hop／two-hop class strings, callable arrays, `call_user_func`,
variable construction, Reflection, extra type in an existing file, and an
outside-file caller. They also fix the `open()` statement order and reject
moving writer invocation before `new self`／snapshot completion or adding any
post-writer work before return. False-positive controls must traverse the same
scanner.

Package required inventory grows from exact76 to exact85 with two canonical
insertion blocks in both scripts. Insert the six `Internal/Audit/Read` paths in
the D155 source order immediately after
`src/Internal/Audit/Orchestration/AuditProtectedReadGate.php` and immediately
before `src/Internal/Audit/Relay/AuditRelayOutcome.php`. Insert the three
`Internal/Diagnostics` paths in the D155 source order immediately after
`src/Internal/Application/ApplicationAuditRuntimeComposer.php` and immediately
before `src/Internal/Audit/Storage/AuditStoreFailure.php`. Every prior exact76
entry retains its relative order; count is 85 and uniqueness is 85.

The required-inventory suite retains all existing exact30 mutations and adds
exactly one inventory mutation, `d2-entry-omission`, which removes
`src/Internal/Audit/Read/AuditReadEventWriter.php`. The D2 result is exact31:
17 inventory mutations plus the unchanged 14 call-contract mutations. Existing
missing／added／duplicate／order mutations plus the D2-specific omission prove
the new block; the suite also retains unreachable-loop, call-omission,
local-pin-repin, alias／shopt, early-return, and pre-SHA rejection. Both scripts
must independently prove exact85 order／count／uniqueness before the final
whole-file SHA pin is accepted.

### 9. Release and Documentation boundary

Stable `1.2.0` and its public Guide／Website remain unchanged. The candidate is
BlackOps `1.3.0` unreleased. Framework migrations remain `13`; Community Board
Application migrations remain `5`, total `18`. The Stable generic Outbox and
the C4 Audit Outbox remain distinct and unchanged.

Foundation D2 has zero current Production factory callers and zero reader,
route, CLI, HTTP, Diagnostics, or Application-composer activation. Generated
Documentation, Search, raw Markdown, LLM artifacts, and browser behavior are
Not Verified when not generated or exercised.

## Reader-child contract reserved by this Decision

Each D2 reader child must preserve its existing public interface, response
value, exception code, and tenant indistinguishability while changing the
internal order to:

```text
session begin and requested commit
-> authorization／subject lookup at level0
-> exactly one unavailable or accessed terminal commit
-> result or failure release
```

Denied, absent, cross-tenant, and safe unavailable branches all call
`session->unavailable()` and expose no existence distinction. Authorized data
calls `session->accessed()` before release. An existing contracted query
exception also requires unavailable terminal success before the same exception
identity may be rethrown. Terminal failure wins and maps to the reader's fixed
storage failure. Each child must fix its exact trusted execution-source
implementation and cannot invent a generic fallback.

## Rejected Alternatives

### Reuse the 24-byte Storage Protection nonce source

That nonce belongs to BOPD encryption and has a different length, role, and
key-lifecycle boundary. Operation-read request identity owns a dedicated
16-byte source.

### Let readers construct drafts

That would duplicate policy, source, resource, and terminal rules across four
surfaces and permit more than one terminal event. The factory constructs all
three drafts and the one-shot session chooses exactly one terminal.

### Preserve non-Allow results when terminal delivery fails

That releases a result without the required terminal record. D155 makes every
terminal branch fail closed.

### Use `ExecutionScopeProvider::current()` directly with a fallback

That provider may return null and cannot represent every HTTP／CLI／viewer
provenance. D2 defines a strict port; each reader child owns one exact source.

### Activate the factory from `ApplicationAuditRuntimeComposer`

The readers and their source-specific failure mappings are not Accepted yet.
Composition remains a D-integration responsibility.

## Consequences

- A requested record is committed before any future reader authorization or
  lookup.
- The same validated basis binds exactly one terminal attempt.
- No raw Operation ID enters the Audit record or retained session.
- Non-Allow terminal failures can no longer release unaudited results.
- Diagnostics gains a typed default-unavailable policy seam without reading
  data or activating it.
- Foundation implementation and evidence are reviewable without changing any
  current public reader behavior.
- P23-003D remains incomplete after D2; its reader children, D3, D4, D5,
  D-integration, and post-F D-control still require separate Acceptance.
