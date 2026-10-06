# Specification 123: Generic Outbox Audit Adapter

Status: Decided — finite metric amendment independently Green; bounded correction authorized; BlackOps1.3 unreleased

The most-specific contract for [P23-003D5O3](../orchestration/tasks/P23-003D5O3-generic-outbox-audit-adapter.md)
and [D173](../decisions/173-generic-outbox-audit-adapter.md).
Accepted C/D1 are semantic prerequisites; D4B is required only for the coordinator's
Connection identity. O2/F1 are the actual Accepted scheduling baseline.
Follow specs105/106/109 and D154. The Task owns current evidence and resource
allocation; section references to source lines are historical inspection anchors.
This materializes reviewed draft a11005e7 with adoption c70ffb50 and review
cf38ab6a. The exact normative source/privacy/native proof requirements below are
retained. Q4 is now resolved by the explicit Outbox/E1 joint Task plan.

## 1. Adopted scope and authority boundaries

Root retains the original twelve paths and adopts seven finite helpers, for
exactly nineteen candidate paths. The original failed implementation is retained
as evidence; correction assignment follows independent amendment review. The
following behavior remains required:

- Exactly the three existing D154 generic-Outbox events. Catalog bytes unchanged.
- One default-null optional Store hook; no ordinary caller or composer activation.
- Enabled-only root admission; two automatic mutations gain owned roots. Enabled
  manual retry uses an explicit native root. Disabled legacy behavior remains.
- Native UPDATE RETURNING metadata owns Outbox ID, tenant and resulting version.
  A constructed claim's message, actors or correlation are not Audit provenance.
- Full positive PostgreSQL BIGINT decimal domain for source state_version;
  automatic action uses the resulting version. Manual action uses the existing
  once-allocated native UUIDv4 retry audit ID.
- One trusted current ActorContext/correlation/build/Audit clock binding; C3 owns
  partition derivation and persisted record identity/time. Native SQL timestamps
  remain a separate clock boundary.
- Public native exception family, Relay stale accounting and CLI success behavior
  stay compatible. No poison/expiry/max-attempt hardening, Relay/C3/C4 changes,
  migration, dependency change, public API/configuration or release activation.

The exact names, signatures and choices below are adopted. D173 records the
reasoning; the Task freezes the actual baseline, shared-file order and commands.
Independent issuance review precedes implementation permission.
Dependencies are Accepted C/D1 and D4B only for `usesConnection`; O2/F1
are scheduling/baseline predecessors, not invented semantic prerequisites.

## 2. Event contract — existing immutable definitions

Provider: `FrameworkOperationalAuditEventCatalog::catalog()`;
ID `blackops.framework.operations`, catalogVersion 1, owner Framework;
hash `9b04a5af68feb9091903282a5d99309bcd7a52cd0cbe2eb9e2901bfacbea083b`.

| Exact event/action type | Phase | Status / outcome code | Policy reason |
| --- | --- | --- | --- |
| `outbox.delivery.retry_scheduled` | committed | unknown / outbox_retry_scheduled | outbox_retry_scheduled |
| `outbox.delivery.dead_lettered` | failed | failed / outbox_dead_lettered | outbox_dead_lettered |
| `outbox.delivery.dead_letter_retry_requested` | requested | unknown / outbox_retry_requested | outbox_retry_requested |

All have version/evidenceVersion 1, category Outbox, resourceType `outbox_record`,
one action type/semantic tuple, policyDecision NotApplicable, Durable delivery,
evidenceFields empty, maxEvidenceBytes 13 and evidence exactly `{"version":1}`.
The writer verifies the provider identity, fixed hash/ID/version/owner and exact
definition tuple. It selects no event by caller-provided string.

Adopted source component and policy ID: both `blackops.outbox.delivery`;
policy version 1. These identify this Framework-owned source, not a new Catalog.
Anchor: `src/Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalog.php:29-58,180-209`;
D154 `:137-193`.

The operator event describes the committed native request that moved a dead
letter to retry_scheduled, not eventual delivery. It emits once in that root;
it does not also emit the automatic retry event. Dead-lettered means the actual
native failure state persisted together with its ingress, not any thrown error.

## 3. Adopted exact surface and fixed runtime binding

### Store constructor

Append a fifth optional promoted dependency after the existing schema parameter:

```php
public function __construct(
    #[\SensitiveParameter] Connection $connection,
    #[\SensitiveParameter] BopdEnvelopeCodec $protection,
    #[\SensitiveParameter] OperationCodec $codec,
    #[\SensitiveParameter] string $schema = 'blackops',
    #[\SensitiveParameter] ?PostgreSqlOutboxPhaseHook $phaseHook = null,
)
```

Existing parameter names, order, types and defaults remain. New hook property is
private readonly. No `#[PublicApi]` marker is added. The three existing public
mutation signatures/return types remain, with `#[SensitiveParameter]` added to
their existing parameters and relevant owned helper parameters. Existing
`src/Outbox/*` interfaces/value types remain untouched.
Current constructor anchor: `src/Transport/PostgreSql/PostgreSqlOutboxStore.php:29-36`.

### Transport interface — five finite methods

```php
interface PostgreSqlOutboxPhaseHook
{
    public function assertRelayEntry(#[\SensitiveParameter] Connection $connection): void;
    public function assertOperatorEntry(#[\SensitiveParameter] Connection $connection): void;
    public function retryScheduled(
        #[\SensitiveParameter] Connection $connection,
        #[\SensitiveParameter] array $row,
    ): void;
    public function deadLettered(
        #[\SensitiveParameter] Connection $connection,
        #[\SensitiveParameter] array $row,
    ): void;
    public function deadLetterRetryRequested(
        #[\SensitiveParameter] Connection $connection,
        #[\SensitiveParameter] array $row,
        #[\SensitiveParameter] string $retryAuditId,
    ): void;
}
```

The interface lives in `BlackOps\Transport\PostgreSql` and imports only DBAL
Connection. Its array parameter carries the unvalidated DBAL result, annotated
`array<string,mixed>`, with the exact expected four-field native projection from
section 4 documented as a runtime requirement. The Hook validates that full
projection after root admission through Row::read, whose return has a closed,
validated shape. No unchecked `@var` assertion, cropping of extra keys, cast,
or Store dependency on an Internal hydration helper may pretend the raw result
is already validated. Missing/extra keys and invalid values still fail closed.
No generic publish method, callback
registry, event-name parameter, actor/reason argument or claim/message argument.

### Internal hook

`final class GenericOutboxAuditHook implements PostgreSqlOutboxPhaseHook`, under
`BlackOps\Internal\Audit\OperationalOutbox`.
Its constructor has exactly one sensitive `GenericOutboxAuditWriter $writer`.
The five interface methods are its only domain methods. `assertRelayEntry`
checks the writer is bound to Relay; `assertOperatorEntry` checks Cli. Both
check exact Connection identity, autoCommit=true, inactive and level 0 before
native SQL, transaction begin, entropy or Audit clock. Phase methods repeat
the relevant runtime/Connection check and require active level 1 before parsing
row metadata, constructing references, obtaining time or deriving opaque IDs.

Two entry methods are adopted instead of an ambiguous `assertEntry` plus
phase-name string: a Store bound for a Relay cannot perform operator retry
using the wrong principal and only discover it after native SQL.

### Internal writer

`final class GenericOutboxAuditWriter`, same namespace. Constructor:

```php
public function __construct(
    #[\SensitiveParameter] HmacAuditOpaqueIdDeriver $opaqueIds,
    #[\SensitiveParameter] AuditDeliverySelector $selector,
    #[\SensitiveParameter] AuditDeliveryCoordinator $delivery,
    #[\SensitiveParameter] ClockInterface $clock,
    #[\SensitiveParameter] ActorContext $actor,
    #[\SensitiveParameter] AuditRecordCorrelation $correlation,
    AuditSourceRuntime $runtime,
    #[\SensitiveParameter] string $buildId,
)
```

Exact writer domain methods:

```php
public function assertConnection(
    #[\SensitiveParameter] Connection $connection,
    AuditSourceRuntime $expectedRuntime,
): void;
public function retryScheduled(
    #[\SensitiveParameter] string $recordId,
    #[\SensitiveParameter] string $stateVersion,
    #[\SensitiveParameter] ?TenantRef $tenant,
): void;
public function deadLettered(
    #[\SensitiveParameter] string $recordId,
    #[\SensitiveParameter] string $stateVersion,
    #[\SensitiveParameter] ?TenantRef $tenant,
): void;
public function deadLetterRetryRequested(
    #[\SensitiveParameter] string $recordId,
    #[\SensitiveParameter] string $retryAuditId,
    #[\SensitiveParameter] ?TenantRef $tenant,
): void;
```

Only the hook calls `assertConnection`, using literal Relay for the automatic
methods and Cli for the operator method. Writer construction accepts only Relay
or Cli; all other enum values fail ConfigurationInvalid. `assertConnection`
rejects an unsupported expectedRuntime, a mismatch or a different Connection
object as TransactionInvalid. Each phase writer independently checks its
required runtime and validates raw identity/tenant again; no public generic
`write` or freely chosen tuple/type exists.

`AuditSourceRuntime::Relay` and `::Cli` already exist
(`src/Core/Audit/AuditSourceRuntime.php:7-14`). Selecting an enum is **not**
authentication. Runtime binding is a compatibility guard; authenticated/trusted
ActorContext and execution correlation must be supplied by future composition.
Construction freezes actor authorization principal (nullable) and current
execution Actor into `AuditRecordActor`, copies correlation via the existing
fromArray/toArray contract, validates buildId as a safe 1..256-byte string, and
retains all snapshots/dependencies in private SensitiveParameterValue storage.
Origin actor is not substituted for current execution/principal. No child
Operation ID, claim originActor, encoded context, CLI text or phase alone creates
provenance. A Relay-bound Store and a Cli-bound Store are distinct instances;
ordinary composition must arrange that later.

Hook/writer each have only these four public privacy magic methods in addition
to the specified domain surface: `__debugInfo(): array` and `__serialize(): array`
return `[]`; `__unserialize(#[SensitiveParameter] array $data): void` and
`__clone(): void` throw a fresh bounded StorageUnavailable. No getters expose
snapshots. Uninitialized/reflected/tampered snapshots fail closed before effects.
Internal fresh error mapping uses existing AuditDeliveryException/Failure only.

Existing compatible patterns, not permission to copy extra behavior:
`src/Internal/Audit/Rotation/StorageRotationAuditWriter.php:65-125,196-235,347-383,386-440`;
`src/Internal/Audit/Rotation/StorageRotationAuditHook.php:212-264,505-534`.

### Finite helper responsibilities

The seven helper classes in section 8 implement the following exact split.
The quality thresholds and baselines remain unchanged.

Each is `final`, has an empty private constructor, no properties or retained state, no PublicApi marker, and only the finite static operations listed below. Fixed private literal constants are allowed. The method totals include the private constructor; visibility below is part of the finite contract. No helper is a provider, service-registration point, native root owner, or public application API.

| Type | Exact operations other than private constructor | Methods total / responsibility |
| --- | --- | --- |
| `GenericOutboxAuditFailure` | public static `fresh(AuditDeliveryFailure): AuditDeliveryException`; public static `remap(AuditDeliveryException): AuditDeliveryException` | 3. Existing finite error enum only; fresh bounded exception, no previous. `remap` catches unreadable/corrupt failure state and returns fresh StorageUnavailable. |
| `GenericOutboxAuditHookGuard` | public static `writer(?SensitiveParameterValue): GenericOutboxAuditWriter`; public static `entry(Connection): void`; public static `root(Connection): void` | 4. Validate the one-key hook snapshot; separately check autoCommit / inactive level 0 or active level 1. Read-only DBAL state calls only. **It does not call Writer::assertConnection.** |
| `GenericOutboxAuditRow` | public static `read(array): array{record_id:string,state_version:string,tenant:?TenantRef}`; private static `tenant(mixed,mixed): ?TenantRef` | 3. Closed four-field actual RETURNING input, scalar/pair checks, unchanged whitespace, strict identities, and complete persisted-tenant hydration. No Connection, clock, HMAC, or delivery. |
| `GenericOutboxAuditSnapshot` | public static `read(?SensitiveParameterValue):` the exact eight-key typed Writer snapshot | 2. Validate and return explicit fixed keys for opaqueIds, selector, delivery, clock, actor, correlation, runtime, buildId. No key-name getter returning mixed, no optional tail or arbitrary property lookup, no effects. |
| `GenericOutboxAuditIdentity` | public static `recordId(string): string`; `stateVersion(string): string`; `retryAuditId(string): string`; `discriminator(AuditSourceRuntime,string): string`; `tenantReference(?TenantRef): ?AuditTypedReference` | 6. Non-normalizing UUIDv7 / full positive BIGINT / UUIDv4 and safe typed tenant validation. `discriminator` is the finite Relay→stateVersion / Cli→retryAuditId selection, with unsupported runtime rejected. It never creates an ID. |
| `GenericOutboxAuditCatalog` | public static `definition(string,AuditSourceRuntime): AuditEventDefinition`; private static `catalog(): AuditEventCatalog`; private static `matches(AuditEventDefinition,array,string): bool` | 4. Exact framework catalog id/version/hash, three literal event definitions, full nine-field declaration and seven-field tuple equality, and each event's required Relay/Cli runtime. No generic catalog/provider lookup. |
| `GenericOutboxAuditProjection` | public static `capture(ActorContext,AuditRecordCorrelation,AuditSourceRuntime,string):` exact four-key provenance; private static `reference(?ActorRef): ?AuditTypedReference`; public static `occurredAt(ClockInterface): string`; public static `opaqueId(HmacAuditOpaqueIdDeriver,AuditOpaqueIdPurpose,string): string`; public static `draft(AuditEventDefinition,AuditRecordActor,AuditRecordCorrelation,array,array): AuditRecordDraft` | 6. Freeze trusted provenance and construct the closed audit projection. The final two draft arguments are exact source `{runtime,buildId}` and material `{actionId,resourceId,occurredAt,tenant}` arrays, assembled literally by Writer from validated values. No selector/coordinator/Connection is passed to this helper. |

All arguments carrying arrays, strings, references, actor/context/correlation, tenant, snapshot, Connection, clock/deriver, or a caught exception are `#[SensitiveParameter]`, including private methods and mixed raw tenant inputs. Only fixed enum-category arguments may be exempt. No untyped variadic bag, callback, reflection, magic dispatch, variable method/class construction, or generic validation pipeline replaces direct calls. New ordinary methods have at most five parameters; the existing eight-parameter Writer constructor is unchanged and remains within the existing constructor threshold of nine.

Hook stays at the specified 10 methods and has no private methods. Writer stays at its specified nine public methods plus **one private `write(string $eventType, string $recordId, string $discriminator, ?TenantRef $tenant): void`**; all four are marked sensitive. This private signature replaces two mutually exclusive nullable identity slots with the one identity that every phase actually possesses. The three public signatures stay byte-for-byte equivalent in parameter names, types, order and sensitivity. No public generic write method is introduced.

### Helper call sequence and existing validation reuse

Hook constructor still stores only its concrete Writer in private SensitiveParameterValue storage. Writer constructor still captures only the eight specified dependencies/provenance values in private SensitiveParameterValue storage. An absent-state null sentinel is permissible only in a private nullable SensitiveParameterValue slot initialized to null, and the helper must immediately map it to StorageUnavailable. It is not permission to keep unwrapped arrays/objects as owner properties. Constructor-bypassed, unserialized, uninitialized, or tampered owner state must fail with a fresh bounded StorageUnavailable before effects; guard/tests must cover the chosen storage representation rather than assume initialized property access.

Every Hook domain method first unwraps its Writer through HookGuard. **The Hook method itself then calls Writer::assertConnection with its literal Relay/Cli value**; this preserves spec123's exact caller rule. Entry methods next call `HookGuard::entry`; phase methods next call `HookGuard::root`, then `Row::read`, then the one matching Writer phase. Thus every phase still proves exact Connection identity, source runtime and the current level-1 root before parsing metadata or constructing references. HookGuard's entry/root methods never begin, commit, roll back, close, connect, query or mutate anything. The redundant old `entryRootConnection` wrapper disappears.

Writer::assertConnection stays in Writer. It uses the typed Snapshot result, rejects unsupported expected runtime or mismatch, and calls the retained Coordinator::usesConnection on the exact object. It does not create or repair a root. Only Hook calls this method.

The private Writer emission path is direct and finite:

1. Read and validate the sealed eight-key snapshot, including safe buildId and usable actor/correlation state, before effects.
2. Resolve the literal event through Catalog and independently enforce its required runtime: both automatic events Relay, manual retry Cli. A runtime mismatch is TransactionInvalid, not a new source chosen from the phase.
3. Validate canonical record UUIDv7, the selected discriminator and the tenant. `Identity::discriminator` returns the same bytes: positive BIGINT for Relay or native UUIDv4 for Cli. The public wrapper determines the literal event; caller input cannot choose another tuple.
4. Obtain occurredAt exactly once through Projection::occurredAt. Then derive Resource once from `generic-outbox:record:<recordId>` and Action once from `generic-outbox:<eventType>:<recordId>:<discriminator>`, using the two existing HMAC purposes. Derivation returns a safe bounded opaque value; it does not choose tenant partition or create a native UUID.
5. Build the existing fixed AuditRecordDraft and `AuditDeliveryRequest($draft, 'generic-outbox:' . $actionId)`; invoke exactly one Selector::forDraft and exactly one Coordinator::deliverInCurrentTransaction **in Writer::write**. No helper acquires either delivery operation.

Projection::capture accepts only Relay/Cli, validates buildId 1..256, copies the authorization principal (nullable) and current execution actor into AuditRecordActor, and copies correlation by its existing fromArray/toArray contract. It does not use origin actor, claim/message data, CLI text or a phase as authentication. ActorContext's final typed accessors already enforce ActorRef return types; unnecessary instanceof branches can be removed while keeping a Throwable→fresh ConfigurationInvalid boundary for constructor-bypassed/corrupt input. `reference` must be sensitive; the current Writer's unmarked ActorRef parameter at line 432 must not survive the move.

Catalog matching can use strict equality between a literal ordered projection of all nine declaration fields and its expected values, followed by a typed single tuple and all seven tuple fields. This is the existing `ObserverReplayAuditWriterDefinition::matches` approach, applied locally to the three Outbox definitions. It reduces duplicated boolean branches **without dropping a field or coercing a type**. The Replay helper itself is adapter-specific and must not become an Outbox dependency or be edited. Likewise, do not modify or invoke another accepted adapter's failure/binding/emission helpers merely to avoid adding a local type.

For scalar identity validation, reuse `AuditValidationException::assertGrammarString` with the literal fully anchored canonical UUIDv7 / UUIDv4 / positive-decimal patterns and the existing 36/19-byte upper bounds. The exact UUID regex fixes the lower length as well. For BIGINT retain the 19-digit lexical upper-bound comparison with `9223372036854775807`; no integer/float conversion, normalizing native UUID parser, JCS safe-integer check, trim replacement, or lossy cast is allowed. Keep AuditTypedReference/TenantRef's existing validation, after rejecting whitespace changes before TenantRef construction. Existing Core/A2/C3/HMAC/Clock APIs remain unchanged.

Row::read must reject extra/missing keys and wrong types before returning a fixed literal three-key result. Key order from the driver is not semantic: count four plus exact key-set comparison is sufficient; do not impose constructor-array order on native rows. Validate recordId **and stateVersion on every row, including manual retry**, then validate both-null versus both-valid persisted tenant scalars and their complete typed reference constraints. Do not discard extra keys by rebuilding an unchecked row and do not normalize or cast DBAL values merely to silence analysis. Raw input DocBlocks must not claim that mixed driver data has already been hydrated. Any required raw-input annotation correction must describe the same closed expected four-field runtime contract; it cannot weaken its rejection matrix.

Snapshot returns explicit validated keys with real closed array-shape annotations. Projection receives only its typed provenance/source/material values, never the complete dependency snapshot. Constructor/tampered-snapshot validation must complete before clock/HMAC, and pure draft construction must reconstruct a fresh PolicyInvalid on invalid projection rather than leaking a received object. These contracts help analysis, but the detailed 12-error report remains required; the amendment Report retains the detailed twelve-error diagnostic; no correction is presumed passed.

All failures retain spec123's existing classification and fresh mapping: ConfigurationInvalid for construction, TransactionInvalid for binding/root/runtime mismatch, PolicyInvalid for metadata/catalog/tuple, ResourceUnavailable for clock, KeyUnavailable for opaque derivation, StorageUnavailable for missing/corrupt dependencies/snapshot. Received known Audit failures are preserved only by fresh reconstruction. Moving a catch boundary must not change this mapping or expose a raw throwable from a new public static helper.

Native SQL, begin/commit/root assertions, UUIDv4 generation, legacy retry-row insertion, actual UPDATE RETURNING ownership, owned rollback and unconditional owned close stay in PostgreSqlOutboxStore. C3 still owns durable ingress, partition, recordedAt and its Audit UUID/clock. No changes to Relay/commands, default-null activation, trusted composition, Core/Claim/schema/migrations, Catalog provider, C3/C4, dependencies or release/public docs are authorized by this split.

## 4. Exact native row and source identity

For all enabled target UPDATEs use the existing SET/WHERE semantics and append:

```sql
RETURNING record_id::text AS record_id, tenant_type, tenant_id,
          state_version::text AS state_version
```

Use the actual returned row directly; do not fabricate it from parameters or
from claim.message. Exact key set: record_id, tenant_type, tenant_id,
state_version, each present once; order is not a security property. No extra
operation_id, payload, encoded context, actor/reason or failure fingerprint is
passed to the hook. A missing returned row is the native stale/non-target case,
not an Audit event. Validate all four fields before clock/opaque derivation.

- record_id is a string exactly matching lowercase canonical UUIDv7:
  `\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z`.
  Reject uppercase/noncanonical strings, including values a permissive ID parser
  would normalize. Native IDs use UUIDv7 but their parser lowercases
  (`src/Core/Identifier/IdentifierBehavior.php:44-52`). PostgreSQL text output is
  expected canonical; tests must not turn normalization into source authority.
- state_version is **string only**, canonical positive decimal in
  `1..9223372036854775807`. Require `\A[1-9][0-9]{0,18}\z`; for length 19 compare
  bytes lexicographically with `9223372036854775807` using `strcmp`, not numeric
  string comparison. Never cast, float-convert, parse as JSON number or apply the
  Audit JCS safe-integer limit. Keep it a string through Action derivation.
  SQL owns increment/overflow; an UPDATE from maximum raises and rolls back,
  emitting no event. Values above 9007199254740991 are valid native identity.
- tenant_type/tenant_id are both null for global, or both strings with unchanged
  trim bytes, safe UTF-8/NFC/control/1..256-byte rules via the Accepted Audit
  validators, and valid AuditTypedReference. No fallback to global, no trimming
  or NFC normalization. Construct TenantRef only after checks and preserve bytes.
  Writer converts it back to AuditTypedReference under the same restrictions;
  C3 derives partition from that reference, never from a caller partition string.
- Native retryAuditId is exactly canonical lowercase UUIDv4, with variant
  `[89ab]`, generated once by the existing native uuid4 method after locked row
  selection. Reuse that same local ID in the legacy retry audit INSERT and hook.
  No second UUID, nonce, request ID or newly persisted identity ledger.

The typed Claim is constructible (`PostgreSqlOutboxClaim.php:18-28`) and current
settlement WHERE predicates only use record/relay/fence/state
(`PostgreSqlOutboxStore.php:328,354`); therefore even a correctly fenced claim
with another tenant/Operation message must audit the actual returned row. The
manual native SELECT still reads operation_id/attempt_count only for its legacy
audit INSERT. Neither becomes current Audit correlation. Partial malformed
returned metadata fails the root; a valid different tenant carried solely in a
forged message does not override or invalidate the authoritative database tenant.
This adapter does not authenticate the database against arbitrary administrator
tampering; its boundary is strict projection of the actual native mutation row.

## 5. Opaque identity, occurrence and delivery ownership

Adopt these exact ASCII contexts (event means the full literal type above):

| Purpose / path | Context |
| --- | --- |
| Resource, all events | `generic-outbox:record:<recordId>` |
| Action, automatic | `generic-outbox:<event>:<recordId>:<resultingStateVersion>` |
| Action, operator | `generic-outbox:outbox.delivery.dead_letter_retry_requested:<recordId>:<retryAuditId>` |
| Durable idempotency key | `generic-outbox:<derivedOpaqueActionId>` |

Use `HmacAuditOpaqueIdDeriver::derive` separately with Resource and Action
purposes. Do not HMAC a synthesized partition/tenant or reuse Resource as Action.
The full-domain decimal is inside a bounded string, not an Audit numeric field.
Contexts are bounded (Resource 58 bytes; longest automatic 103; manual 132),
and the current opaque Action produces an 86-byte durable key, within the
existing 1..256-byte C3 key contract
(`src/Internal/Audit/HmacAuditOpaqueIdDeriver.php:31-36,141-151`;
`src/Internal/Audit/Orchestration/AuditDeliveryRequest.php:14-24`).

Writer captures its trusted clock exactly once for each phase after strict
input validation, formats UTC `Y-m-d\TH:i:s.u\Z`, and uses that one occurredAt.
The native dead_lettered_at CURRENT_TIMESTAMP and supplied retry schedule now
remain native semantics and never substitute for Audit occurredAt. C3 owns its
recordedAt/ingress clock and UUID allocation separately. Clock/key failure emits
nothing and aborts the native root.

Writer obtains the fixed Catalog definition, derives two references, constructs
the fixed Draft and one bounded request, calls `selector->forDraft` once itself,
then `deliverInCurrentTransaction` exactly once. Existing C3 may revalidate the
plan internally; do not falsely claim only one physical selector evaluation in
the complete graph. No standalone delivery, fallback, after-commit callback,
business authoring, direct canonical/Audit Store call or Audit relay call.

Identity uniqueness: repeated stale/native non-target attempts emit nothing;
another committed automatic cycle uses a new native resulting state_version;
another accepted operator request uses a new native retryAuditId. After a
rolled-back root, a new actual attempt may use a new occurrence or manual UUID
because no original ingress committed. After commit-then-throw, native state and
ingress remain together; no synthetic resend or terminal event is generated.
Changing provenance/time while directly repeating the same durable key is not
silently treated as identical content; Accepted C3 duplicate/conflict rules apply.

## 6. Native roots and enabled/disabled behavior

Disabled means exactly phaseHook=null. Preserve legacy mutation validation,
SQL/transaction behavior, native retry audit table/UUID, messages and exception
chain behavior. No disabled admission check, extra SELECT, new clock/entropy,
new root or audit write. Additive sensitive attributes may hide owned arguments;
do not claim that the unchanged legacy Throwable graph is retroactively safe.

Enabled automatic flow, separately for scheduleRetry and moveToDeadLetter:

1. Enter enabled branch. `assertRelayEntry` checks trusted binding/identity/idle
   admission before native effects. Validate native fingerprint using its
   existing finite grammar, before begin.
2. Mark that this admitted method is attempting its own begin; begin once.
   Require autoCommit=true, active level 1 before native SQL. Foreign state on
   entry is rejected without rollback, close, SQL, time or entropy.
3. Execute the existing fenced UPDATE with RETURNING, preserving every native
   SET and ownership predicate; do not introduce an expiry predicate. Require
   a returned row. Invoke exactly the matching phase method with that row.
4. Hook repeats binding/root checks, strictly projects, and writer performs one
   real C3 durable ingress inside that same root. C3 is exact Connection object
   bound and joins level 1 (`AuditDeliveryCoordinator.php:26-29,40-64,98-110`;
   `PostgreSqlAuditOutboxStore.php:1393-1416`).
5. Verify root remained valid, commit once, require idle autoCommit=true level 0
   before returning void. Any throw follows the owned cleanup boundary below.

Enabled manual flow:

1. `assertOperatorEntry`, then validate native nonempty actor/reason before begin.
   These strings only remain native legacy audit fields and never become trusted
   actor, policy reason, evidence or opaque input.
2. Explicitly begin one admitted level-1 root; do not use DBAL transactional in
   the enabled branch. Keep the existing SELECT dead_lettered FOR UPDATE and its
   operation_id/attempt_count native audit use.
3. Allocate uuid4 exactly once to retryAuditId, INSERT the unchanged legacy retry
   audit using it, then execute the existing UPDATE with the exact RETURNING
   projection. Missing native target/UPDATE row is failure, with no event.
4. Hook deadLetterRetryRequested with actual returned row and that same UUIDv4;
   real C3 ingress, commit, idle check. All three effects — legacy audit row,
   native mutation, new ingress — are atomic in this root.

Existing roots/SQL anchors: `src/Transport/PostgreSql/PostgreSqlOutboxStore.php:317-416,432-437`.
No event/hook/admission is added to insert, claimBatch (including expired lease
reclaim), heartbeat, markSent or migrate. Those methods retain current native
behavior. No nested transaction/savepoint is a valid enabled target entry.

### Owned cleanup and unknown outcome — Root decision Q1 below

Track entry admission and begin-attempt ownership independently of begin return,
so begin-then-throw can clean the root it actually opened. On any enabled failure,
rollback at most once and only when this method attempted begin from admitted
idle state, autoCommit remains true and the connection is active at owned level 1.
Never rollback a rejected foreign root, inactive connection, unexpected nested
scope or autoCommit=false connection. Guard cleanup probes and rollback errors;
neither may leak their raw Throwable or mask the bounded public failure contract.

**Adopted closure policy:** after **every enabled owned-root
failure once begin was attempted**, close the owned Connection, whether begin
returned, rollback succeeded, DBAL now reports idle, or commit may have happened.
First attempt at most the eligible owned active rollback above, then close once.
This includes begin-before-effect and begin-after-effect exceptions, native/C3/
metadata failures, stale/no-row failures inside the root, cleanup probe/rollback
errors, commit exceptions and post-commit state-check failure. It avoids trying
to classify a driver exception by its message/class or logical nesting state.
Never close an entry-rejected foreign connection or a scalar-input failure before
begin was attempted. Do not retry, reconnect or start another transaction inside
the failing method. The unchanged Relay may subsequently reconnect through its
normal next operation after close.
If close itself fails, emit the same fresh bounded failure and do not claim
cleanup succeeded. Final integration must not promise recovery from arbitrary
driver failure; that inherited limit remains explicit.

This bounded close is needed because unchanged Relay continues after settlement
failure. Leaving an active owned root could make later heartbeat/markSent join
the failed mutation. Also, installed DBAL commit decrements nesting in finally
even after some driver commit failures (`vendor/doctrine/dbal/src/Connection.php:1060-1098`):
logical idle after a commit exception does not prove physical connection reuse is
safe. DBAL close clears its connection reference and nesting (`:411-415`). The
close policy does not justify claiming that an exception proved rollback: a real
commit may already have persisted both sides. Always observe real state through
another Connection in evidence. Earlier committed roots remain durable across
this close; only uncommitted owned work is eligible for rollback/discard.
D173 adopts this close policy and its stated driver-failure limits.

## 7. Failures, privacy and public behavior

Enabled Store releases only **fresh** `DeferredTransportException`, code 0,
previous null. It must not rethrow a received native, DBAL, Audit, policy/key or
cleanup Throwable or copy its message/class/code into a user-visible string.
`DeferredTransportException` is the existing final PublicApi exception
(`src/Core/Exception/DeferredTransportException.php:10-11`).

Adopted closed message mapping:

| Known local condition | Exact fresh native message |
| --- | --- |
| Invalid fingerprint | `Outbox failure fingerprint is invalid.` |
| Invalid operator actor/reason | `Dead-letter retry actor and reason are required.` |
| Automatic retry returned no row | `Outbox relay retry claim is stale.` |
| Dead-letter returned no row | `Outbox relay dead-letter claim is stale.` |
| Manual SELECT missing | `Outbox record is not dead-lettered.` |
| Manual UPDATE missing | `Outbox dead-letter retry claim is stale.` |
| Any other enabled scheduleRetry failure | `Failed to schedule PostgreSQL outbox retry.` |
| Any other enabled moveToDeadLetter failure | `Failed to dead-letter PostgreSQL outbox record.` |
| Any other enabled retryDeadLetter failure | `Failed to retry dead-lettered outbox record.` |

Choose a known condition through local control flow/owned sentinel or finite tag,
not Throwable message comparison. Root/admission/runtime/metadata/clock/key/C3/
native SQL/commit/cleanup failures use the per-method generic existing message.
If cleanup fails, the generic per-method message takes precedence even over an
earlier locally known input/stale failure. Native validation can occur before
begin, so invalid scalar calls do not create a root merely to preserve messages.

Every parameter in new/changed owned Store helpers that can carry Connection,
claim/message, row, tenant, record/audit ID/version, native actor/reason/now,
fingerprint, projection, snapshot or caught Throwable is SensitiveParameter.
New hook/writer public and private sensitive data arguments are likewise marked.
Do not retain raw failure objects, log/debug them, serialize snapshots or chain
them behind safe exceptions. Tests examine previous plus trace/debug/serialization
with exception arguments enabled. Native Store itself is not newly claimed safe
to serialize; inherited caller/driver/telemetry stack frames are outside this
child's universal privacy guarantee and must be stated as limits.

Hook/writer reconstruct existing AuditDeliveryException with finite reason codes
and no previous: configuration snapshot/runtime input invalid -> ConfigurationInvalid;
binding/root invalid -> TransactionInvalid; native metadata/tuple invalid ->
PolicyInvalid; clock -> ResourceUnavailable; opaque derivation -> KeyUnavailable;
dependency/snapshot unavailable -> StorageUnavailable. Preserve a received known
Audit failure code only by constructing a new exception, never rethrow its object.
C3 safe delivery failure codes may be reconstructed the same way; the Store then
maps them to its existing generic native message.

Unchanged Relay catches settlement failures, increments stale and proceeds to
later claims; it must not increment retried/deadLettered on failure. An Audit
append failure leaves the native row leased/recoverable if rollback succeeds.
The existing run command still prints counters and returns Command::SUCCESS,
including stale > 0. Neither the command nor its metrics becomes an Audit health
acknowledgement (`src/Internal/Outbox/OutboxRelayRuntime.php:127-147`;
`src/Internal/Console/OutboxRelayRunCommand.php:45-62`). CLI --actor/--reason and
success text stay unchanged. No operational reason/fingerprint enters the closed
Audit evidence, outcome code or policy reason.

## 8. Exact 19-path implementation and immutable boundaries

1. `src/Transport/PostgreSql/PostgreSqlOutboxStore.php`
2. `src/Transport/PostgreSql/PostgreSqlOutboxPhaseHook.php` (new)
3. `src/Internal/Audit/OperationalOutbox/GenericOutboxAuditHook.php` (new)
4. `src/Internal/Audit/OperationalOutbox/GenericOutboxAuditWriter.php` (new)
5. `tests/Internal/Audit/OperationalOutbox/GenericOutboxAuditHookTest.php` (new)
6. `tests/Internal/Audit/OperationalOutbox/GenericOutboxAuditWriterTest.php` (new)
7. `tests/Transport/PostgreSql/PostgreSqlAuditedOutboxStoreTest.php` (new)
8. `tests/Architecture/GenericOutboxAuditArchitectureTest.php` (new)
9. `tests/Architecture/AuditRecordArchitectureTest.php` (bounded exact references)
10. `tests/Architecture/AuditDeliveryArchitectureTest.php` (bounded exact references)
11. `tests/Consumer/framework-package-export.sh` (required inventory only)
12. `tests/Consumer/framework-package-required-inventory.sh` (matching inventory/controls)

13. `src/Internal/Audit/OperationalOutbox/GenericOutboxAuditFailure.php` (new stateless helper)
14. `src/Internal/Audit/OperationalOutbox/GenericOutboxAuditHookGuard.php` (new stateless helper)
15. `src/Internal/Audit/OperationalOutbox/GenericOutboxAuditRow.php` (new stateless helper)
16. `src/Internal/Audit/OperationalOutbox/GenericOutboxAuditSnapshot.php` (new stateless helper)
17. `src/Internal/Audit/OperationalOutbox/GenericOutboxAuditIdentity.php` (new stateless helper)
18. `src/Internal/Audit/OperationalOutbox/GenericOutboxAuditCatalog.php` (new stateless helper)
19. `src/Internal/Audit/OperationalOutbox/GenericOutboxAuditProjection.php` (new stateless helper)

Ten new Product types enter required package inventory: the original three
followed immediately by these seven, before the retained F1 helper tail. The exact prior count,
ordered list, negative-control count and hashes must use Root's post-O2/F1 shared
baseline, not old 115/66 or another sibling's predicted values. Never weaken
existing reference guards or broad-allow the new namespace. OperationalOutbox is
separate from the exact closed `Internal/Audit/Outbox` persistence directory.

No source edits to Relay/commands/Application composition, Claim/message/Core,
Outbox schema/migrations, C3/C4, Catalog/provider/lookup, other source adapters,
public docs, dependencies/configuration, thresholds/baselines or existing native
fixtures. Root owns formal management files separately. If quality requires a
further helper file beyond these seven, return a concrete scope amendment
before adding it; no implicit
extra class inside an unrelated accepted file and no metric waiver inferred here.

## 9. Mandatory evidence matrix for formal Acceptance

These are mandatory proof requirements, not results. Root owns the common
baseline/freeze, serial shared-resource leases and final gates. The Task may
allocate bounded iteration commands to the owning Luna worker with separate
output/cache paths; no unallocated shared command or concurrent DB use is allowed.
Independent issued authority must be Green before Product implementation.

### Real native/C3 transaction proof

- For each of the three enabled roots, prove native mutation and authenticated
  real C3 Audit Outbox ingress commit together, with tenant A, tenant B and
  null/global. Manual proves legacy retry audit is the third atomic effect.
- For every root: begin-before-effect and actual begin-then-throw; native
  statement failure; C3 ingress failure before write; failure injected after
  **real accepted C3 ingress wrote**, before native commit; commit-before-effect;
  actual parent commit then throw. Use a separate observer Connection and
  inspect native/Audit state after return/error, not exception-derived inference.
- Explicit rollback before-effect/after-effect failures, cleanup probe/close
  handling under adopted Q1, at-most-once active owned rollback, no inactive or
  foreign cleanup. Fault counters prove target was reached. Do not catch test
  assertions inside the same Throwable block intended to catch production errors.
- Prove actual parent close/reconnect behavior for owned begin-before/after and
  commit-before/after exceptions, including logical-idle commit failure; no
  close at rejected foreign entry or malformed scalar input before begin.
  Use independent observer reads to prove prior committed rows/ingresses survive,
  failed uncommitted effects disappear when cleanup succeeds, and later native
  work reconnects without joining the failed physical transaction. This is
  evidence for controlled real DBAL/driver faults, not arbitrary-driver recovery.
- Reject same-name different Connection, autoCommit=false, manual active root,
  nested root and runtime mismatch before SQL/entropy/Audit clock. Valid foreign
  state is untouched; begin-then-throw is demonstrably this method's owned root.
- No compensating retry, new failed event, second append or additional UUID after
  commit exception; commit-after-effect leaves both sides present. A later
  explicit stale call emits nothing; a valid later cycle gets a distinct key.
- Use actual OutboxRelayRuntime with real opted-in Store/C3 and a sender that
  fails one record. A forced Audit append failure increments stale and does not
  increment retried/deadLettered; a later claim succeeds in the same batch.
  Verify native/Audit persistence and no leftover root. Repeat for retry and
  dead-letter thresholds, and cleanup-failure closure under adopted Q1. Existing CLI
  counter/success behavior is checked without modifying command/runtime sources.

### Source metadata, identity and no-event proof

- Correct record/fence but forged claim.message Operation ID/tenant/actor/
  correlation cannot alter actual returned-row tenancy or current trusted Audit
  provenance. Native retry preserves original record/child Operation identity.
- Row shape missing/extra keys; wrong scalar types; UUIDv4/uppercase/invalid
  UUIDv7; tenant partial, whitespace-changing, invalid UTF-8/control/NFC,
  empty/257-byte inputs fail before clock/opaque/delivery, with root rollback.
- Version strings 1, 9007199254740991, 9007199254740992 and 9223372036854775807
  accepted. Real SQL from 9223372036854775806 -> max produces the exact context.
  SQL overflow from max rolls back without Audit. Reject zero/sign/leading zero/
  whitespace/fraction/exponent, 9223372036854775808, 20+ digits, int/float/bool/null.
  Prove no action collision due to numeric conversion or JCS safe-int cap.
- Manual native UUIDv4 is reused once between legacy row and Action context;
  reject malformed UUID in direct hook/writer tests. No second native nonce.
- Exact HMAC purpose/context bytes and durable key; all fixed tuple/source/policy
  values and evidence; repeated stale call emits nothing while another valid
  automatic/manual transition is distinct. C3 alone chooses tenant partition.
- Insert/registration, claim, expired reclaim, heartbeat, sent, migrate, wrong
  target/non-dead manual request and native rollback do not emit these events.
  Disabled path retains existing nested/manual transaction and exception behavior;
  do not accidentally apply enabled admission to all Store methods.

### Unit/privacy/structural/package proof

- Constructor runtime restrictions, snapshot isolation, current actor/correlation
  binding, separate Relay/Cli source guards, one writer clock and two purpose
  derivations, writer-owned one plan/delivery call, failure finite-code mapping.
- Sensitive raw marker values in native actor/reason/fingerprint, message
  payload/context, tenant/IDs, driver/hook/clock/key errors absent from new safe
  message/previous/owned trace/debug/serialize. Inspect active/disabled limits
  honestly; never assert all existing Throwable paths are safe.
- Name-resolved AST/source guards, with meaningful negative controls: exact
  interface/implementation/caller graph; default-null constructor positive
  fixtures; all normal composers remain null; entry occurs before begin/native
  effects; three phase calls only between matching native mutation and commit;
  no phase on no-row path; actual RETURNING metadata; forbidden claim.message
  projection; finite writer runtime dispatch; native UUID sharing; no numeric
  conversion/trim fallback/partition override; owned active cleanup checks.
- Exact Catalog provider and A2/C3 consumer allowances; no direct Audit Store,
  AuditOutboxRelayRuntime, external callback/generic publisher or unrelated
  source adapter dependency. Include aliases/class-string/dynamic use cases
  where current guard machinery supports them, with bounded runtime cost.
- Required inventory +10 exact types and +12 inventory controls: the original
  three omissions/reverse-three plus seven helper omissions/reverse-seven.
  Both reversals retain valid nonterminal shell continuations and extraction
  shape, and prove the exact intended list mutation before oracle rejection.
  Preserve order/hash and existing omitted-entry/duplicate/
  basename-spoof/call-contract controls based on the actual post-O2/F1 baseline;
  shell syntax and archive/export distinction unchanged.
- Frozen full512M plus appropriate unchanged-threshold static/architecture,
  format/identifier, package, scope and cleanup gates per formal packet; existing
  native Outbox/Relay/TransactionalOutbox/command/signal cases are included in
  the full. Do not rerun identical focused tests after that full merely for
  review. A local inventory check is not committed-HEAD export or same-SHA CI.

### Proof for the finite decomposition

Use the existing two O3 unit files, native fixture and three architecture guards. No new test file is needed. The source/call graph now contains nine exact OperationalOutbox types plus the one Transport interface and the existing native Store; do not convert that into a namespace or directory exemption.

- GenericOutboxAuditArchitectureTest: pin the seven new filenames/classes, final/private-constructor/stateless/no-PublicApi shape, exact visibility/signatures/method inventories and sensitivity. Pin the allowed per-method new/static/function/instance-call graph with name resolution. Hook alone calls Writer::assertConnection using the five correct literal runtime sites; Writer alone owns usesConnection, forDraft and current-transaction delivery; HookGuard alone has the three allowed read-only DBAL admission probes; Projection alone owns the one clock and HMAC projection edges; Store alone has native entropy/SQL/root/cleanup. Validate actual ordering and values, not just occurrence counts or presence strings.
- Extend meaningful parsed negative controls for an extra type or state field; unlisted method/dependency/caller; a helper acquiring SQL/transaction/delivery/entropy/activation; wrong or bypassed runtime; row validation moved after time; dropped manual version validation; changed exact scalar bounds/tuple fields; raw/native identity used as opaque output; unsensitive moved parameter; lost close or foreign cleanup. Preserve parse-valid alias/FQCN and inert-text positives plus supported consumed class-string/callback negatives. No claim of a universal static proof beyond the guarded forms.
- A2 shared guard: replace the old Writer-only consumption mapping with exact references actually moved to Identity, Snapshot, Catalog and Projection, retaining only real remaining Writer/Hook references. Include another A2 type in an allowed file and the same allowed type in an unlisted sibling as negatives. No whole OperationalOutbox directory exemption and no changing A2 Product for this adapter.
- C3 O3-only guard: retain one Writer current-transaction edge and forbid all other emission routes across every new helper. Snapshot may only validate/store/recover exact dependency types; it may not invoke them. Failure's reconstruction is the only new common exception-construction edge. Keep the optional interface/native caller boundary and ordinary composition inactive.
- Functional tests preserve all existing required assertions and directly exercise the two corrections above, reordered-valid native keys, full persisted tenant rejection before effects, full BIGINT positives/bounds, exact UUID versions, known/unknown failure reconstruction and tampered/absent snapshots. Moved helpers must appear in owned-frame privacy checks with exception arguments enabled, including reference/mixed tenant/projection/snapshot/error markers. Validate meaningful positive paths alongside faults.
- The **actual unchanged OutboxRelayRuntime continuation/counter proof is mandatory (the initial implementation lacks it)**: real opted-in Store/C3, failed settlement causes stale without retried/deadLettered, later claim succeeds in the same batch, for retry and dead-letter thresholds and the adopted cleanup-failure case. A hand-written loop or Store-only test cannot replace it. No Runtime/command source edit is granted.

All real-root requirements remain: all three targets and tenant A/B/global; once-generated manual UUIDv4 shared with the actual legacy row; begin-before/after and real C3 write faults; actual parent commit then throw; rollback/close faults; exact observer-Connection persisted effects; close/reconnect and later native work; at-most-one eligible owned rollback followed by owned close even when logically idle; no foreign/pre-admission cleanup or retry/second UUID/second append. This decomposition makes no recovery or activation claim.

## 10. Root adoption items and consequences

| Adopted item | Decision and consequence |
| --- | --- |
| Q1: enabled failure cleanup/quarantine | Adopt close after every enabled failure once an admitted begin was attempted, following at most one eligible active owned rollback; never after rejected foreign entry or pre-begin scalar failure. Logical idle is insufficient after driver commit failure. Avoids leaking a physical transaction into unchanged Relay continuation and removes error-class heuristics. Costs reconnection even for cleanly rolled-back stale/native failures, only on the opt-in path. Prove earlier durable effects and actual later reconnect/Relay work; still no guarantee when driver close itself fails. |
| Q2: exact five-method hook and writer runtime guard | Adopt the specified surfaces; auto requires Relay, manual requires Cli, with separate entry methods. Blocks accidental use of Relay principal for operator retry before SQL. Future integration needs separately bound Store instances. |
| Q3: component/policy literal and fixed native safe-message mapping | Adopt `blackops.outbox.delivery` / version 1 and closed table in section 7. Existing known native input/stale messages survive as fresh exceptions; enabled underlying causes are intentionally unavailable. CLI success/stale semantics remain unchanged. |
| Q4: formal Task and common baseline | P23-003D5O3 follows actual Accepted O2/F1 and shares a new explicitly reviewed qualification with P23-003E1. Its amended Task fixes the exact19/22/38 manifests, three serialized files, all3756 actual predecessor identities and child-specific proof. Concrete new case identities are frozen after implementation. This does not reopen or join the completed O2/F1 full. |

No poison/quarantine-row state, lease-expiry change, max-attempt recovery repair,
generic Outbox schema, extra Catalog definition or C4 behavior is needed for
these three adopted events. Those remain separate native hardening choices if
ever adopted; Q1 concerns connection cleanup, not record poison/quarantine.

## 11. Release and completion boundary

Public Release Authority remains Experimental Stable 1.2.1; BlackOps 1.3 is
unreleased. This internal adapter introduces no public capability activation,
command/config/API or route; normal source composition, trusted providers,
Documentation/local Owner preview, committed-SHA export/same-SHA CI and release
delivery remain parent work. Formal Report must record unchanged Source/Search/
LLM artifact hashes when not rebuilding, with no claim that unchanged generated
artifacts were newly exercised. Most-specific spec/internal architecture content
is updated by Root's formal packet; public release docs belong to 005/006.

Remaining work is implementation, assigned verification, independent source and
evidence review, then Root Acceptance under the linked Task. Parent integration,
Documentation and release remain separate. Only Root's issued Task assignment
supplies Product permission; this specification does not expand its exact scope.

### Metadata declaration inventory ownership — 2026-10-05T13:03:05+09:00

Independent authority review c3-metadata-inventory-review.md
SHA2563b97b9bba48dde520279c34a44797c4e0f2686d68620069125b229c018475f57
is Green. C3's18 owned declarations remain exact. Exclude exactly
`src/Audit/AuditCatalogDefinition.php` and `src/Audit/AuditCatalogProvider.php`
from the C3-owned declaration inventory only; E1 guards own their declarations.
Retain both files in all-source C3 reference/call/constructor/dependency/security
scans, without adding any approved C3 edge. All other unknown src/Audit paths
remain rejected. Prove a parsed unlisted declaration fails; a parsed forbidden
C3 edge in each excluded file fails for that edge; and the unchanged18 declaration
checks/structural negatives work with these two metadata files present or absent.
This ownership clarification changes no Product/public contract, scopes19/22/38
or shared3. Only the existing O3 guard owner implements the correction.
