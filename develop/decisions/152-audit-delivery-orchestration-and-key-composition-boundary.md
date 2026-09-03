# D152: Audit Delivery Orchestration and Key Composition Boundary

Status: Accepted for P23-003C3 — candidate committed and same-SHA package export Green; BlackOps 1.3 unreleased

Updated At: 2026-09-04T01:53:16+09:00

## Current Handoff

P23-003C1, P23-003C2, and P23-003C3 are Accepted.
The reviewed C3 candidate is committed at
`b7b352a8e4c86a45822351c108772367515654bd`, and its clean same-SHA
Git／Composer package export is Green.
No push was performed.

C3 now provides fixed Catalog delivery selection, same-Framework-Connection
mutation orchestration, protected Operation-read release gating, and
Application-facing opaque／protection key composition. The bounded
`HmacAuditOpaqueIdDeriver` representation correction retains its key behind
`SensitiveParameterValue` without changing derivation bytes or public API.

The C3 Application Audit composer remains uncalled by current Production.
It is not registered in the current Application, Container, Console, HTTP,
worker, scheduler, or relay runtime.

P23-003C4 is next. D153 consumes the same composed Connection, protection
codec, validator, PostgreSQL Stores, and Outbox configuration through a bounded
Internal relay seam. It does not change the C3 provider or transaction
contracts and does not activate a current caller.

Stable `1.2.0` remains current.
BlackOps `1.3`, Audit Trail delivery, the new key-provider interfaces, and all
Audit runtime behavior remain unreleased.

## Context

Accepted C2 exposes two transaction-participating stores.
`PostgreSqlAuditRecordStore::appendRequired()` and
`PostgreSqlAuditOutboxStore::appendDurable()` each own a root only at nesting
level `0` and join an existing root only at exact nesting level `1`.
Neither store chooses delivery policy or owns a business transaction.

The existing `OperationTransactionCoordinator` already invokes one completion
callback after a successful business result and before committing the
Framework Connection root.
C3 must reuse that seam rather than introduce a second transaction owner or a
savepoint protocol.

Four boundaries must be closed together.

1. A versioned policy requirement and the resolved Catalog definition must
   produce one immutable delivery plan before mutation or response.
2. A completed mutation must turn its result into exactly one Audit delivery
   request inside the same Framework Connection root.
3. A protected Operation read must commit its requested and terminal records
   before authorization lookup or result／denial release.
4. Application key providers must supply a dedicated opaque-ID key and two
   Audit protection purposes without adapting the generic
   `StorageKeyProvider` or changing public `StoragePurpose` exact9.

## Decision

### 1. Exact namespaces and Production types

C3 adds exactly fourteen Production types.

Public Application-facing types under `BlackOps\Audit`:

```text
AuditOpaqueIdKeyProvider
AuditProtectionKeyProvider
```

Internal orchestration types under `BlackOps\Internal\Audit\Orchestration`:

```text
AuditDeliveryFailure
AuditDeliveryException
AuditDeliveryPlan
AuditDeliveryRequest
AuditDeliverySelector
AuditDeliveryCoordinator
AuditMutationTransactionCoordinator
AuditProtectedReadGate
```

Internal key adapter under `BlackOps\Internal\Audit\Key`:

```text
ApplicationAuditProtectionKeySource
```

Internal Application composition types under `BlackOps\Internal\Application`:

```text
ApplicationAuditConfiguration
ApplicationAuditRuntime
ApplicationAuditRuntimeComposer
```

Only the two `BlackOps\Audit` interfaces carry `#[PublicApi]`.
Every Internal type has exact zero `#[PublicApi]` attributes.
No C3 type is added under `Core\Audit`, and the Accepted A1 exact-zero marker
inventory remains unchanged.

The two public types are interfaces. `AuditDeliveryFailure` is an enum.
`AuditDeliveryException` and `AuditDeliveryRequest` are final classes.
`AuditDeliveryPlan`, `AuditDeliverySelector`, `AuditDeliveryCoordinator`,
`AuditMutationTransactionCoordinator`, `AuditProtectedReadGate`,
`ApplicationAuditProtectionKeySource`, `ApplicationAuditConfiguration`,
`ApplicationAuditRuntime`, and `ApplicationAuditRuntimeComposer` are final
readonly classes. Every unlisted public or protected callable is forbidden;
private helper decomposition remains implementation-owned inside the exact
dependency and sensitivity guards.

### 2. Application-facing key providers

The public interfaces are exact.

```text
AuditOpaqueIdKeyProvider
  key(): StorageKey

AuditProtectionKeyProvider
  activeRecordKey(string partitionId): StorageKey
  recordKey(string keyId, string partitionId): StorageKey
  activeOutboxKey(string partitionId): StorageKey
  outboxKey(string keyId, string partitionId): StorageKey
```

Every `keyId` and `partitionId` parameter is marked
`#[SensitiveParameter]`.
The returned `StorageKey::id()` is the configured key reference, and its
material is exactly 32 bytes by the existing value contract.

`AuditOpaqueIdKeyProvider` is a distinct Container service and is never
adapted from `StorageKeyProvider`.
Its one `key()` result is resolved exactly once per composed runtime and feeds
one `HmacAuditOpaqueIdDeriver`.
V1 has no fallback key, environment default, repository key, or silent
rotation.
Changing that key while cross-record correlation is retained requires a
future versioned migration contract.

`AuditProtectionKeyProvider` receives only the canonical opaque partition ID,
never a trimmed `TenantRef` and never the raw `AuditTypedReference`.
The Internal adapter derives `system:global` or
`tenant:<64 lowercase hex>` from the exact Audit tenant bytes before calling
the provider.
The four methods keep record and Outbox active／historical lookup authority
explicit without adding Audit cases to public `StoragePurpose` exact9.

Using the existing `StorageKey` value does not authorize reuse of a generic
provider or key material.
The opaque-ID key reference and material must both differ from every Audit
record／Outbox protection key returned in the composed runtime.
Equality is rejected before encryption or decryption.

### 3. Failure, plan, and request values

`AuditDeliveryFailure` is a string enum with exactly six cases.

```text
configuration_invalid
policy_invalid
transaction_invalid
key_unavailable
resource_unavailable
storage_unavailable
```

`AuditDeliveryException` is final and extends `RuntimeException`.
Its exact public surface is:

```text
__construct(AuditDeliveryFailure $failure)
failure(): AuditDeliveryFailure
code(): string
__debugInfo(): array
__serialize(): array
__unserialize(array): void
```

Its message is exactly `Audit delivery failed.` and its previous exception is
always null.
Debug, serialization, JSON, `var_export()`, and trace surfaces expose no Draft,
idempotency key, key reference, material, tenant, response, or rejected value.

`AuditDeliveryPlan` is final readonly with a private constructor.
Its sole factory is called only by `AuditDeliverySelector` and captures these
exact trusted values:

```text
catalogHash,eventType,eventVersion,evidenceVersion,delivery
```

Its exact public callables are:

```text
fromSelection(
  string $catalogHash,
  string $eventType,
  int $eventVersion,
  int $evidenceVersion,
  AuditDelivery $delivery
): self
catalogHash(): string
eventType(): string
eventVersion(): int
evidenceVersion(): int
delivery(): AuditDelivery
```

Every factory parameter is `#[SensitiveParameter]` except `delivery`.
The plan retains no whole Catalog or mutable lookup.

`AuditDeliveryRequest` is final.
Its exact constructor accepts one `AuditRecordDraft` and one nullable raw
idempotency key, both as `#[SensitiveParameter]` values.
Its exact public callables are:

```text
__construct(AuditRecordDraft $draft, ?string $idempotencyKey)
draft(): AuditRecordDraft
idempotencyKey(): ?string
__debugInfo(): array
__serialize(): array
__unserialize(array): void
```

It retains both through private sensitive storage and has value-free debug／
serialization hooks.
It does not carry a caller-selected delivery mode.
When the key is non-null, construction accepts only an exact 1..256-byte UTF-8
NFC string with no Cc／Cf code point. It performs no trim, normalization,
case-fold, or numeric conversion. A rejected key is `policy_invalid` before
transaction or Store work.

The exact public/protected sensitivity matrix additionally marks:

- both Exception／Request `__unserialize()` arrays;
- Plan factory Catalog hash／event selector／version members;
- Selector `$requirement`, `$draft`, and `$request`;
- both coordinator `$request` parameters;
- mutation `$invoke` and `$deliveryRequest` closures;
- read-gate `$basis`／`$context` and every `$draft`;
- raw Application `$configuration` and all Runtime factory／composer objects;
- key-adapter `$opaqueKey`, tenant arguments, key ID, and every public provider
  key／partition argument.

Private helpers remain implementation-owned, but any parameter that carries or
can retain Draft, idempotency, actor, execution, tenant, key, provider,
configuration, response, DB row, or throwable data must be sensitive. The
Architecture rule is semantic and name-independent.

### 4. Fixed delivery selection

`AuditDeliverySelector` has the exact constructor:

```text
__construct(ImmutableAuditEventCatalogLookup $lookup, AuditRecordValidator $validator)
```

Its exact public callables are:

```text
select(AuditEventCatalog $catalog, AuditEventDefinition $definition, mixed $requirement): AuditDeliveryPlan
forDraft(AuditRecordDraft $draft): AuditDeliveryPlan
validate(AuditDeliveryPlan $plan, AuditDeliveryRequest $request): AuditDelivery
```

`requirement` is `#[SensitiveParameter]`.
`select()` accepts only the literal strings `required` and `durable`.
Null, omitted-as-null, unknown, `best_effort`, non-string, and an enum instance
supplied as raw policy input are `policy_invalid`.

The supplied definition must be the same trusted object contained by the
supplied Catalog.
The literal requirement must equal that definition's fixed delivery.
The resulting plan binds the Catalog hash and selector before any mutation or
response begins.

`forDraft()` is only the already-typed standalone path.
It validates the Draft against the immutable lookup, resolves the exact
Catalog definition, and returns its bound plan.
It does not accept a runtime override.

`validate()` revalidates the Draft and requires its Catalog hash, event type,
event version, evidence version, and `policy.auditDelivery` to equal the plan.
A mismatch is `policy_invalid` before either Store call.

### 5. Delivery coordinator and transaction states

`AuditDeliveryCoordinator` has the exact constructor:

```text
__construct(
  Connection $connection,
  AuditDeliverySelector $selector,
  PostgreSqlAuditRecordStore $records,
  PostgreSqlAuditOutboxStore $outbox
)
```

Its exact public callables are:

```text
deliverStandalone(AuditDeliveryRequest $request): void
deliverInCurrentTransaction(AuditDeliveryPlan $plan, AuditDeliveryRequest $request): void
```

Both methods select／validate delivery before transaction or Store work.
For `required`, the request idempotency key must be null and the coordinator
calls `appendRequired()` exactly once.
For `durable`, the key must be the already-validated non-null string and the
coordinator calls `appendDurable()` exactly once.
There is no default, retry, fallback, downgrade, observed-projection call, or
second Store call.

`deliverStandalone()` accepts only auto-commit enabled, inactive nesting level
`0` and returns only after the selected Store's owned root commits.
After success the Connection is inactive at level `0`.
It never joins an ambient root.

`deliverInCurrentTransaction()` accepts only auto-commit enabled, active exact
nesting level `1` before and after the Store call.
It never begins, commits, rolls back, creates a savepoint, or accepts nesting
level `0` or greater than `1`.

Validation／policy failures map to `policy_invalid`.
Invalid transaction state maps to `transaction_invalid`.
An Accepted canonical or Outbox Store exception whose existing failure enum is
`ResourceUnavailable` maps to `resource_unavailable`. This includes capacity,
trusted Clock／UUID exhaustion, and write-side protection failures because the
Accepted Stores intentionally erase those lower causes before C3 sees them.
Every other Store failure, including integrity, idempotency, transition, and
DB failures, collapses to `storage_unavailable` without a previous exception.

The first-applicable order is fixed per entry point: configuration shape and
bounds; Catalog／plan／Draft／key-nullability policy; transaction state; opaque
key availability during composition; Accepted Store `ResourceUnavailable`;
then every other contracted Store failure. A later failure never masks an
earlier applicable category.

### 6. Same-root mutation orchestration

`AuditMutationTransactionCoordinator` has the exact constructor:

```text
__construct(OperationTransactionCoordinator $transactions, AuditDeliveryCoordinator $delivery)
```

Its exact method is:

```text
execute(
  AuditDeliveryPlan $plan,
  OperationMetadata $metadata,
  Closure $invoke,
  Closure $deliveryRequest
): OperationResult
```

Both closures are `#[SensitiveParameter]`.
`deliveryRequest` has the logical signature
`Closure(OperationResult): AuditDeliveryRequest`.

Before invoking business code, `execute()` requires transaction metadata and
requires that the selected transaction Connection is the exact Framework
Connection owned by the existing `OperationTransactionCoordinator`.
Non-transactional or cross-Connection metadata is `transaction_invalid` with
zero `invoke` and zero request-factory calls.

This is not a generic adapter for an arbitrarily assembled
`OperationTransactionCoordinator` or `DatabaseManager`.
Its sole Production construction is in
`ApplicationAuditRuntimeComposer::compose()` from the exact
`ApplicationOperationRuntimeComposition` returned by the existing
`ApplicationOperationRuntimeComposer`.
That current composition owns one `DoctrineDatabaseManager`, whose per-name
Connection cache makes the precheck and the existing callback-time
`sharesFrameworkConnection()` check resolve the same object.
An alternate manager, manually assembled operation composition in Production,
or additional Production constructor caller is forbidden by the Architecture
boundary and requires a later Decision before activation. Direct tests may
assemble a fixture only to prove the canonical identities; that fixture is not
a supported runtime path.

The existing coordinator remains the sole root owner.
On one completed result, the request factory runs exactly once in its existing
completion callback, then C3 validates the request against the preselected
plan and delivers it in the current root exactly once.
Only after that callback returns may the existing coordinator commit.

A non-completed `OperationResult` follows the existing rollback path and does
not call the request factory or an Audit Store.
An invalid request, append failure, request-factory failure, or transaction
failure rolls back the business mutation and releases no successful result.
A request-factory throwable or non-`AuditDeliveryRequest` result contracts to
`storage_unavailable`; a returned request that does not match the plan is
`policy_invalid`. Begin／commit／rollback or unexpected coordinator failure is
`storage_unavailable`. Every contracted exception has null previous.

C3 wraps the invocation only far enough to distinguish the exact business
handler throwable identity. That same throwable is rethrown unchanged after
the existing root rolls back; C3 does not catch or reinterpret it as an Audit
failure. No raw request-factory or transaction throwable escapes.

No cross-database, external, distributed, or exactly-once atomicity claim is
made.

### 7. Protected Operation-read gate

`AuditProtectedReadGate` has exact constructor
`__construct(AuditDeliveryCoordinator $delivery)` and exposes:

```text
requested(AuditReadRequestBasis $basis, AuditRecordDraft $draft): void
terminal(AuditReadContext $context, AuditRecordDraft $draft): AuditReadAuthorizationDecision
```

For `requested()`, the Draft must bind the basis exactly:

- event and action type are
  `access.operation_<purpose>.requested`;
- `action.id` equals the derived `requestRef`;
- actor principal, execution, tenant, and `occurredAt` equal the basis;
- Catalog and policy delivery validate as `required`.

The required standalone append commits before `requested()` returns.
An append or binding failure throws `AuditDeliveryException`, so a future D
adapter performs zero authorization and zero target lookup.

For `terminal()`, the context's basis must recompute the same `requestRef`.
Actor, execution, tenant, and `action.id` remain bound.
Decision `allow` requires the exact `.accessed` event.
Decisions `deny` and `resource_unavailable` require the exact `.unavailable`
event.
Exactly one terminal standalone append attempt occurs before the method
returns.

An `allow` binding, policy, transaction, or append failure throws and returns
no decision, so no protected data may be disclosed.
For `deny` or `resource_unavailable`, any binding, policy, transaction, or
append failure returns the original bounded non-allow decision and never
becomes `allow`.
The gate returns only the decision; it accepts no response／data callback and
cannot release protected bytes itself.

P23-003D owns the Operation Status／Journal／Outcome／Diagnostics adapters and
must call this gate before authorization lookup and before releasing any
response, denial, or failure.
P23-003G later owns its separate service-access context and consumes the
generic C3 delivery coordinator rather than widening this Operation-read gate.

### 8. Application configuration and runtime composition

`ApplicationAuditConfiguration::fromConfiguration(array)` reads only
`audit.outbox`.
Missing `audit` or `outbox` uses the Accepted C1 defaults.
When present, `outbox` is a closed object with only:

```text
lease_duration_seconds
max_attempts
backoff_base_seconds
backoff_cap_seconds
```

Every supplied value is a native integer and is passed once to the Accepted
`AuditOutboxConfiguration` bounds.
Unknown members, non-array parents, numeric strings, floats, null, booleans,
or invalid bounds are `configuration_invalid`.
Other future `audit` siblings are not consumed or defaulted by C3.
Its exact public callables are:

```text
private __construct(AuditOutboxConfiguration $outbox)
fromConfiguration(array $configuration): self
outbox(): AuditOutboxConfiguration
```

The raw configuration parameter is `#[SensitiveParameter]`.
`outbox()` returns the one retained
`AuditOutboxConfiguration` instance.

`ApplicationAuditRuntime` is final readonly and carries exactly:

```text
outboxConfiguration
opaqueIds
deliverySelector
delivery
mutations
protectedReads
records
outbox
```

Its constructor is private and its sole static `fromComposition(...)` factory
is called only by `ApplicationAuditRuntimeComposer`.
It exposes one getter named after each exact member and no other public or
protected callable.
It contains no raw key material or key-reference property.
The same Outbox configuration and Store instances are the future C4 handoff.

Its exact public callables are:

```text
fromComposition(
  AuditOutboxConfiguration $outboxConfiguration,
  HmacAuditOpaqueIdDeriver $opaqueIds,
  AuditDeliverySelector $deliverySelector,
  AuditDeliveryCoordinator $delivery,
  AuditMutationTransactionCoordinator $mutations,
  AuditProtectedReadGate $protectedReads,
  PostgreSqlAuditRecordStore $records,
  PostgreSqlAuditOutboxStore $outbox
): self
outboxConfiguration(): AuditOutboxConfiguration
opaqueIds(): HmacAuditOpaqueIdDeriver
deliverySelector(): AuditDeliverySelector
delivery(): AuditDeliveryCoordinator
mutations(): AuditMutationTransactionCoordinator
protectedReads(): AuditProtectedReadGate
records(): PostgreSqlAuditRecordStore
outbox(): PostgreSqlAuditOutboxStore
```

The exact factory caller map contains only
`ApplicationAuditRuntimeComposer::compose()`.

`ApplicationAuditRuntimeComposer::compose()` has the exact signature:

```text
compose(
  ApplicationConfigurationSnapshot $configuration,
  ApplicationOperationRuntimeComposition $operations,
  ImmutableAuditEventCatalogLookup $catalogs
): ApplicationAuditRuntime
```

The composer requires both public key-provider services from the already
compiled Application Container.
It resolves each provider object once and the opaque key once.
Missing, wrong-type, throwing, malformed, or reused opaque key material is
`key_unavailable` with no raw cause.

The composer reparses only the existing database schema name through
`ApplicationDatabaseConfiguration`, then creates both Audit schema values from
that one validated name.
It reuses the existing Framework Connection, Clock, and
`OperationTransactionCoordinator` from the supplied operation runtime.
It constructs exactly one `AuditJcsCodec`, validator, record codec, finalizer,
partition deriver, `BopdBinaryEnvelopeCodec`, `RandomNonceSource`, associated
data value, ingress codec, Audit-protection codec, `SymfonyUuidv7Generator`,
canonical schema／hydrator／Store, Outbox schema／idempotency hasher／hydrators／
Store, selector, delivery coordinator, mutation wrapper, and read gate.
It passes the exact same configuration instance to the Outbox Store and the
returned runtime.

The supplied operation composition must be the canonical Production value
from `ApplicationOperationRuntimeComposer`; its `DoctrineDatabaseManager`,
Framework Connection, and `OperationTransactionCoordinator` identities are
reused without replacement. The composer validates that the configured
Framework connection resolves to the exact composition Connection before
constructing any C3 coordinator.

The caller supplies the immutable Catalog lookup.
C3 does not construct a Framework-only hidden default because P23-003E later
supplies the trusted combined Framework／Application registry.

### 9. Key adapter and no-leak boundary

`ApplicationAuditProtectionKeySource` is the sole C3 Production implementation
of Accepted `AuditProtectionKeySource`.
Its exact constructor is
`__construct(AuditProtectionKeyProvider $provider, AuditPartitionIdDeriver
$partitions, StorageKey $opaqueKey)`.

`activeKey()` and `key()` derive the canonical partition and dispatch through
an exhaustive match over Accepted `AuditProtectionPurpose` exact2.
The adapter never calls or references `StorageKeyProvider` or
`StoragePurpose`.
It validates returned key identity and rejects equality with the opaque key
reference or material before returning it to the B1 codec.

Provider throwables, wrong returns, key mismatch, unavailable historical keys,
or role reuse become the existing fixed `AuditProtectionException` with null
previous.
The adapter and composer expose no raw key, key material, tenant, provider
throwable, or environment value through message, previous, trace arguments／
string, debug, serialization, JSON, or `var_export()`.

The composed runtime returns the existing concrete `HmacAuditOpaqueIdDeriver`,
so the same no-leak boundary applies to that object's representations. The
deriver stores the validated 32-byte key only in `SensitiveParameterValue` and
retrieves a string solely at the HMAC call. Its constructor, public callables,
validation failures, domains, length-prefixes, and derived bytes remain
unchanged. Direct and composed-runtime fixtures use a unique raw-key canary and
must prove it absent from debug, serialization, JSON, exception traces, and
`var_export()` while preserving the existing known answers.

### 10. Architecture and activation boundary

C3 adds one mutation-resistant `AuditDeliveryArchitectureTest` that fixes:

- exact fourteen Production paths and types;
- exact modifiers, `#[PublicApi]` inventory, callable signatures, defaults,
  nullability, and `SensitiveParameter` matrix;
- exact Catalog plan／Draft validation and Store-call graph;
- exact one direct `appendRequired()` and `appendDurable()` caller in the C3
  delivery coordinator;
- exact one Production `HmacAuditOpaqueIdDeriver` construction in the C3
  Application composer;
- exact one `AuditProtectionKeySource` Production implementation;
- exact one current Production `ApplicationOperationRuntimeComposition`
  construction and exact cached `DoctrineDatabaseManager` identity path into
  the C3 mutation coordinator;
- token-aware alias／group-use／FQCN／same-namespace／class-string／dynamic／
  reflection reference and call detection. Consumed class strings include
  `class_exists()`／`interface_exists()`／`enum_exists()`／`is_a()`／
  `is_subclass_of()` and literal／one-hop／two-hop variables;
- no generic provider／purpose, environment, default key, second transaction
  owner, savepoint, downgrade, observed projection, relay, or response callback;
- no Application operation／HTTP／Console／worker／scheduler／Container runtime
  registration or C3 composer callsite;
- nested file, extra type, missing type, signature／attribute／dependency／
  call-count／outside-caller mutation negatives.

Accepted shared Architecture tests change only to authorize the exact C3
reference and callsite maps. They may not skip a complete C3 path: every
exception is an exact path／target／reference-or-call count, with consumed
class-string and same-namespace mutation negatives.
Except for the bounded `HmacAuditOpaqueIdDeriver` sensitive-storage change,
C1／C2／B1／B2 Production remains byte-identical.
Every other prior guard and mutation negative remains active.

The C3 composer itself has exact zero Production callsites.
Constructing it in a direct test is not runtime activation.
P23-003D／E and C4 must receive separate accepted packets before any current
Application path may call it.

### 11. Package and release boundary

The existing package required inventory grows from exact54 to exact68 by
inserting the fourteen C3 Production paths in the same order in the
required-inventory oracle's expected list and the export script's single
`validate_package()` required-path loop.
The export script retains exactly two top-level calls of that one function,
once for the Git archive and once for the Composer archive.
Their execution-contract and repinned mutation oracles remain non-vacuous.

C3 adds no migration, table, CLI command, route, public Guide, Documentation
Website source, Community Board claim, generated artifact, release, or deploy.
Framework migration count remains `13` and Community Board total remains
`18`.
The Stable version baseline remains `1.2.0`, and the new public interfaces are
unreleased `1.3` proposal API only.

### 12. Required proof

Direct and real-PostgreSQL fixtures must prove:

- explicit required／durable selection and every missing／null／unknown／
  `best_effort`／mismatch／override negative;
- plan Catalog hash／selector／evidence-version substitution rejection;
- required and durable exact-one Store routing with key nullability;
- standalone level0 owned commit and current level1 participation;
- level0／inactive-nonzero／level2／auto-commit-false transaction negatives;
- same-Framework-Connection business mutation plus required record or durable
  Outbox row commit in one root;
- append／request-factory failure rolls back the business row;
- non-completed result and cross-Connection metadata perform zero Audit work;
- requested-before-authorization and terminal-before-release ordering;
- allow append outage withholds data, while deny／unavailable outage remains
  the same bounded denial;
- exact basis／context／requestRef／event／actor／execution／tenant bindings;
- provider missing／wrong／throw, opaque key wrong length, record／Outbox
  selection, historical lookup, and opaque-key reference／material reuse;
- paired Accepted Store `ResourceUnavailable` cases for capacity and
  write-side protection, plus non-resource Store failure contraction;
- canonical `DoctrineDatabaseManager` connection caching, exact operation
  composition identity, and zero alternate Production construction;
- one opaque provider call, one deriver, one Outbox configuration, and one
  composed graph;
- C4 receives the same Store／configuration instances;
- existing opaque derivation known answers remain byte-identical while a raw
  key canary is absent from direct-deriver and composed-runtime exception and
  representation surfaces, including `var_export()`;
- consumed literal／one-hop／two-hop class strings, same-namespace executable
  references, and shared C1／C2／A2／B2 reference maps have non-vacuous mutation
  rejection without rejecting unconsumed string decoys;
- exact68 package inventory, Stable `1.2`, migration13／Community18, no public
  Documentation diff, and zero runtime activation.

## Consequences

C3 establishes the local policy, transaction, response-gate, and key
composition seams without claiming that any current runtime emits Audit
records.
P23-003D／E own producer adapters and trusted registry integration.
P23-003C4 owns relay and recovery over the same C2 rows and C3 runtime handoff.
Parent P23-003C remains incomplete until C4 is Accepted.
