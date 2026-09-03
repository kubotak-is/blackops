# D151: Audit PostgreSQL Outbox Persistence Boundary

Status: Accepted for P23-003C2 — candidate committed and same-SHA package export Green; BlackOps 1.3 unreleased

Updated At: 2026-09-04T01:53:16+09:00

## Current Handoff

P23-003C1 and P23-003C2 are Accepted.
The reviewed C2 candidate is committed at
`a92bef70fdb843a4dd2b8b34243fc892b43939cc`. Complete Final Gate and fresh
General／DB／Documentation review are Green, and the clean exact-SHA
Git／Composer package export is Green. No push was performed.

C2 now provides the accepted two-table PostgreSQL Outbox persistence seam,
including authenticated idempotent append, contiguous ordinal, FIFO selection,
C1-backed fenced transitions, tenant binding, and owned-level0／joined-level1
transaction behavior. Its Application policy selection, key composition, and
relay exclusions remain normative.

P23-003C3 is Accepted at committed candidate
`b7b352a8e4c86a45822351c108772367515654bd` with clean same-SHA package
export Green. D152 consumes this Store without changing any C2 Production byte
and owns Catalog-fixed required／durable selection, same-root mutation
orchestration, protected-read gating, and Application key composition.

P23-003C4 is next. D153 keeps this exact C2 Store API unchanged and places one
Internal persistence adapter above it. The bounded C4 runtime uses separate
Store-owned claim, canonical append, and acknowledgement roots, rejects ambient
level1, and performs a deliberate second protected open after claim commit.
Both terminal states continue to acknowledge their ordinal and release the next
FIFO position. C4 adds no current runtime registration.

Stable `1.2.0` remains current. BlackOps `1.3` and the Audit Trail remain
unreleased.

## Context

D150 fixes C2 ownership at PostgreSQL row, migration, hydration, idempotency,
per-partition ordinal, FIFO claim, and fenced CAS. It does not fix the physical
schema, callable graph, lock order, or transaction participation needed to
implement those requirements without guessing.

Two boundaries must be resolved together.

1. `ingressOrdinal` is part of the protected plaintext and AAD, so it must be
   allocated before encryption while still being committed without a gap.
2. An idempotent retry must return the original row and ciphertext without
   reading a new Clock, generating a new identifier, or selecting a new key.

This Decision closes those boundaries while preserving C3 ownership of
required／durable policy orchestration and Application key composition, and C4
ownership of the active relay loop.

## Decision

### 1. Namespace and exact internal types

C2 adds exactly ten Internal Production types under
`BlackOps\Internal\Audit\Outbox` and one migration. None carries
`#[PublicApi]`. The modifiers and callable surface below are exact. Every
unlisted public or protected callable is forbidden. Private helper names and
decomposition are implementation-owned; Architecture checks their dependency,
construction, sensitivity, no-dynamic-call, and no-outside-caller invariants
without treating a worker-selected private layout as normative authority.

```text
AuditOutboxStoreFailure: string enum
  integrity_invalid
  idempotency_conflict
  transition_conflict
  resource_unavailable
  storage_unavailable

AuditOutboxStoreException: final class extends RuntimeException
  __construct(AuditOutboxStoreFailure)
  failure(): AuditOutboxStoreFailure
  code(): string
  message: "Audit outbox persistence failed."
  previous: null

AuditOutboxRecord: final class
  private __construct(AuditProtectedIngress, AuditOutboxStateSnapshot)
  fromParts(AuditProtectedIngress, AuditOutboxStateSnapshot): self
  protectedIngress(): AuditProtectedIngress
  stateSnapshot(): AuditOutboxStateSnapshot
  toArray(): exact ordered fifteen-field array
  value-free debug／serialization hooks

AuditOutboxAppendResult: final class
  __construct(AuditOutboxRecord, bool inserted)
  record(): AuditOutboxRecord
  inserted(): bool
  value-free debug／serialization hooks

AuditOutboxLookupResult: final class
  private __construct(?AuditOutboxRecord)
  notFound(): self
  fromRecord(AuditOutboxRecord): self
  isFound(): bool
  record(): ?AuditOutboxRecord
  value-free debug／serialization hooks

AuditOutboxIdempotencyKeyHasher: final readonly class, no constructor
  hash(string partitionId, string idempotencyKey): lowercase 64-hex

PostgreSqlAuditProtectedIngressHydrator: final readonly class, no constructor
  hydrate(array): AuditProtectedIngress

PostgreSqlAuditOutboxRecordHydrator: final readonly class
  __construct(PostgreSqlAuditProtectedIngressHydrator)
  hydrate(array, AuditOutboxConfiguration): AuditOutboxRecord

PostgreSqlAuditOutboxSchema: final readonly class
  __construct(PostgreSqlMigrationSchema)
  schemaName(): string
  partitionStateTable(): string
  recordsTable(): string
  statements(): list<string>

PostgreSqlAuditOutboxStore: final class
  exact constructor and operations in §6
```

All record, result, row, Draft, tenant, partition, idempotency-key, token,
timestamp, protected-body, and DB-scalar parameters are
`#[SensitiveParameter]`; configuration, Connection, Schema, Clock, generator,
codec, validator, hydrator, transition construction, boolean, and integer
version parameters are not sensitive unless they carry one of those values.
Value and result classes retain sensitive state only through private
`SensitiveParameterValue` storage and expose only the listed trusted getters.

The separate `Outbox` namespace avoids changing the Accepted B2 Storage type
inventory and keeps the Accepted C1 pure `Delivery` directory free of DBAL,
Clock, UUID, protection, and migration dependencies.

### 2. Exact logical and physical row

`AuditOutboxRecord::toArray()` returns exactly this order:

```text
schemaVersion,kind,recordId,partitionId,tenantBinding,ingressOrdinal,
idempotencyKeyHash,contentHash,state,attempt,availableAt,leaseFencingToken,
leaseExpiresAt,protectedBody,version
```

`schemaVersion=1` and `kind=audit_outbox_record`. The first immutable group is
the Accepted B1 `AuditProtectedIngress` exact seven fields. The mutable group
is the Accepted C1 state exact six fields. Only those two trusted value objects
may construct a record. `protectedBody` remains raw binary in the internal
array and is never implicitly JSON-encoded. An explicit future JSON projection
must base64url-encode it and is outside C2.

The PostgreSQL physical row has exact sixteen-column projection order because
the logical tenant union is split into two nullable columns:

```text
schema_version,kind,record_id,partition_id,tenant_type,tenant_id,
ingress_ordinal,idempotency_key_hash,content_hash,state,attempt,available_at,
lease_fencing_token,lease_expires_at,protected_body,version
```

`PostgreSqlAuditProtectedIngressHydrator` receives only the exact ordered
physical eight-field subset:

```text
record_id,partition_id,tenant_type,tenant_id,ingress_ordinal,
idempotency_key_hash,content_hash,protected_body
```

It folds the tenant pair to the closed union, normalizes bytea through the
existing `PostgreSqlBytea::string()`, and calls
`AuditProtectedIngress::fromArray()` exactly once. The full row hydrator takes
the operation's one `AuditOutboxConfiguration`, calls the protected hydrator
and `AuditOutboxStateSnapshot::fromArray()` exactly once each, then calls
`AuditOutboxRecord::fromParts()` exactly once. It does not retain a second
configuration. Missing, extra,
reordered, list, wide, wrong-type, unsafe integer, partial tenant, noncanonical
timestamp／UUID, malformed bytea, or invalid state is `integrity_invalid`.

Both hydrators own physical shape and scalar validation only. They do not
derive a partition from the tenant pair. The Store owns the one injected
`AuditPartitionIdDeriver`; after hydration and before every
`unprotectOutbox()` call, it derives the partition from the hydrated tenant
binding and requires byte equality with the hydrated clear `partitionId` and
the operation's requested／locked partition. A mismatch is
`integrity_invalid` with zero key-source and decrypt calls. This rule applies
to append retry, find, claim, recovery, retry, relay, and poison and does not
add a hydrator dependency or constructor.

DB integers accept only native `int` or canonical unsigned decimal
`0|[1-9][0-9]*`, with digit-length and lexical bounds checked before cast.
DB timestamps are projected only with
`to_char(<column> AT TIME ZONE 'UTC','YYYY-MM-DD"T"HH24:MI:SS.US"Z"')`.
No DB session TimeZone or DateStyle is a wire authority.

The metadata member set excluding `protectedBody` is canonical-JCS bounded to
64 KiB including one final LF. Existing constituent bounds make cap+1
unconstructable; the maximum valid shape must be measured and asserted below
the cap. This is not permission to widen any constituent field.

### 3. Exact PostgreSQL schema

Migration `BlackOps\Migrations\PostgreSql\Version20260901000000` adds exactly
two tables through `PostgreSqlAuditOutboxSchema::statements()` and is
irreversible.

`statements()` returns exactly five statements in this order: partition-state
table, records table, unacknowledged index, pending-due index, leased-expiry
index. `audit_outbox_partition_state` has this exact column matrix:

```text
partition_id varchar(71) NOT NULL
tenant_type varchar(256) NULL
tenant_id varchar(256) NULL
last_ingress_ordinal bigint NOT NULL
last_assigned_recorded_at timestamptz NOT NULL
state_version bigint NOT NULL DEFAULT 1
```

All unshown defaults are absent. Its exact named constraints and expressions
are:

```text
audit_outbox_partition_state_pk
  PRIMARY KEY (partition_id)
audit_outbox_partition_state_partition_check
  CHECK (partition_id ~ '^(system:global|tenant:[0-9a-f]{64})$')
audit_outbox_partition_state_tenant_pair_check
  CHECK ((partition_id = 'system:global' AND tenant_type IS NULL AND tenant_id IS NULL)
      OR (partition_id LIKE 'tenant:%' AND tenant_type IS NOT NULL AND tenant_id IS NOT NULL))
audit_outbox_partition_state_tenant_bounds_check
  CHECK ((tenant_type IS NULL AND tenant_id IS NULL)
      OR (octet_length(tenant_type) BETWEEN 1 AND 256
          AND octet_length(tenant_id) BETWEEN 1 AND 256
          AND CONTROL_FREE(tenant_type) AND CONTROL_FREE(tenant_id)))
audit_outbox_partition_state_ordinal_check
  CHECK (last_ingress_ordinal BETWEEN 1 AND 9007199254740991)
audit_outbox_partition_state_version_check
  CHECK (state_version BETWEEN 1 AND 9007199254740991
      AND state_version = last_ingress_ordinal)
```

An existing state row always represents at least one committed Outbox row, so
`last_ingress_ordinal` is `1..9007199254740991` and
`last_assigned_recorded_at` is non-null. Missing state is represented by no
row, not by ordinal zero.

`audit_outbox_records` has this exact column matrix. Every column has no
default.

```text
schema_version smallint NOT NULL
kind text NOT NULL
record_id uuid NOT NULL
partition_id varchar(71) NOT NULL
tenant_type varchar(256) NULL
tenant_id varchar(256) NULL
ingress_ordinal bigint NOT NULL
idempotency_key_hash char(64) NOT NULL
content_hash char(64) NOT NULL
state text NOT NULL
attempt smallint NOT NULL
available_at timestamptz NULL
lease_fencing_token uuid NULL
lease_expires_at timestamptz NULL
protected_body bytea NOT NULL
version bigint NOT NULL
```

Its exact named constraints and expressions are:

```text
audit_outbox_records_pk
  PRIMARY KEY (partition_id, ingress_ordinal)
audit_outbox_records_record_id_uq
  UNIQUE (record_id)
audit_outbox_records_idempotency_uq
  UNIQUE (partition_id, idempotency_key_hash)
audit_outbox_records_partition_fk
  FOREIGN KEY (partition_id)
  REFERENCES audit_outbox_partition_state (partition_id)
  ON UPDATE NO ACTION ON DELETE RESTRICT NOT DEFERRABLE
audit_outbox_records_schema_kind_check
  CHECK (schema_version = 1 AND kind = 'audit_outbox_record')
audit_outbox_records_record_id_check
  CHECK (record_id::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')
audit_outbox_records_partition_tenant_check
  CHECK (partition_id ~ '^(system:global|tenant:[0-9a-f]{64})$'
      AND ((partition_id = 'system:global' AND tenant_type IS NULL AND tenant_id IS NULL)
        OR (partition_id LIKE 'tenant:%' AND tenant_type IS NOT NULL AND tenant_id IS NOT NULL
          AND octet_length(tenant_type) BETWEEN 1 AND 256
          AND octet_length(tenant_id) BETWEEN 1 AND 256
          AND CONTROL_FREE(tenant_type) AND CONTROL_FREE(tenant_id))))
audit_outbox_records_ordinal_check
  CHECK (ingress_ordinal BETWEEN 1 AND 9007199254740991)
audit_outbox_records_hash_check
  CHECK (idempotency_key_hash ~ '^[0-9a-f]{64}$'
      AND content_hash ~ '^[0-9a-f]{64}$')
audit_outbox_records_state_check
  CHECK (
    (state = 'pending' AND attempt BETWEEN 0 AND 31
      AND available_at IS NOT NULL AND lease_fencing_token IS NULL AND lease_expires_at IS NULL)
    OR (state = 'leased' AND attempt BETWEEN 1 AND 32
      AND available_at IS NULL AND lease_fencing_token IS NOT NULL AND lease_expires_at IS NOT NULL)
    OR (state IN ('relayed','poisoned') AND attempt BETWEEN 1 AND 32
      AND available_at IS NULL AND lease_fencing_token IS NULL AND lease_expires_at IS NULL))
audit_outbox_records_lease_token_check
  CHECK (lease_fencing_token IS NULL OR
    lease_fencing_token::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')
audit_outbox_records_body_check
  CHECK (octet_length(protected_body) BETWEEN 53 AND 1048576
    AND substring(protected_body FROM 1 FOR 4) = decode('424f5044','hex'))
audit_outbox_records_version_check
  CHECK (version BETWEEN 1 AND 9007199254740991)
```

The FK uses the same schema-qualified table returned by
`partitionStateTable()`; the unqualified name above denotes that exact target.
`CONTROL_FREE(x)` is not a SQL function and no function is installed. It is
normative shorthand for
`translate(x, <Cc/Cf inventory>, '') = x`, where the inventory expression is
byte-identical to the Accepted `PostgreSqlAuditSchema` `chr(1)` through
`chr(917631)` concatenation, excluding NUL and excluding valid U+2028／U+2029.
The new schema duplicates that literal expression locally; it does not add or
call a public helper.

The exact explicit indexes, after the two tables, are:

```text
audit_outbox_records_unacknowledged_idx
  (partition_id, ingress_ordinal) WHERE state IN ('pending','leased')

audit_outbox_records_pending_due_idx
  (available_at, partition_id, ingress_ordinal) WHERE state = 'pending'

audit_outbox_records_leased_expiry_idx
  (lease_expires_at, partition_id, ingress_ordinal) WHERE state = 'leased'
```

The state CHECK encodes the D150 matrix at the global maxima: pending attempt
`0..31`, leased／terminal attempt `1..32`, exact availability／lease
nullability, and the four exact state literals. Runtime configuration applies
the tighter configured maximum. Every UUID is canonical lowercase v7, every
hash is lowercase 64-hex, ordinal／attempt／version are JSON-safe, and
`protected_body` is BOPD magic with length `53..1048576`.

Tenant type／ID and every clear text predicate use the same locale-independent
Unicode Cc／Cf exclusion inventory already accepted in
`PostgreSqlAuditSchema`; POSIX character classes are forbidden. No raw
idempotency key, logical record, actor, policy, evidence, correlation,
plaintext, key ID, nonce, ciphertext component, or exception detail is stored
as a clear column.

`PostgreSqlMigrationSchema` validates the schema before SQL. DDL qualification,
table getters, advisory lock keys, tests, and migration all use that same
validated name.

### 4. Idempotency and ordinal allocation

`AuditOutboxIdempotencyKeyHasher` validates the canonical partition grammar
and an already-NFC, control-free UTF-8 key of `1..256` bytes. It computes:

```text
SHA-256(
  ENC_STRING("blackops.audit.outbox.idempotency.v1") ||
  ENC_STRING(partitionId) ||
  ENC_STRING(idempotencyKey)
)
```

where `ENC_STRING(x)` is the existing Audit `s:<byte-length>:<bytes>` encoding.
Its result must equal `AuditIngressEnvelopeCodec::idempotencyKeyHash()` for the
accepted system and tenant vectors and U+2028／U+2029 positives.

Append locks a partition with:

```text
pg_advisory_xact_lock(hashtextextended(
  "blackops.audit.outbox.partition.v1:<schemaName>:<partitionId>", 0
))
```

After the partition lock it locks the partition-state row `FOR UPDATE`, then
runs one exact aggregate over retained rows selected by the requested
`partition_id`. Its closed four-column projection order is:

```text
row_count = count(*)
min_ordinal = min(ingress_ordinal)
max_ordinal = max(ingress_ordinal)
tenant_mismatch_count = count(*) FILTER (
  WHERE tenant_type IS DISTINCT FROM :expected_tenant_type
     OR tenant_id IS DISTINCT FROM :expected_tenant_id
)
```

The two expected tenant parameters are the validated caller pair: both null
for `system:global`, otherwise the exact requested tenant type and ID. Count
members use the same bounded nonnegative DB integer parser as other aggregate
scalars; minima／maxima are nullable canonical DB integers. Missing state is a
valid bootstrap only when the result is exactly `0／null／null／0`. Present
state with ordinal `N` is valid only when its tenant pair matches the requested
pair and derived partition, `state_version=N`, and the aggregate is exactly
`N／1／N／0`. Thus every retained row, including an interior non-head row, is
bound to the locked state tenant. State-ahead, state-behind, interior gap,
zero-row state, orphan row, state／retained tenant mismatch, or malformed
aggregate is `integrity_invalid` before Clock, UUID, key, protection, or
write.

`last_assigned_recorded_at` is the trusted monotonic allocator scalar written
only by the same append CTE and validated as a finite canonical timestamp when
read. It is not recomputed by decrypting the last retained row; superuser
mutation outside the framework connection and migration boundary is outside
the C2 threat model. The record-ID lock domain is
`blackops.audit.outbox.record.v1:<schemaName>:<recordId>` and is acquired only
after the partition lock.

The idempotency lookup by `(partitionId,idempotencyKeyHash)` occurs before
ordinal capacity, Clock, UUID, Finalizer, active-key lookup, nonce, encryption,
or mutation.

For an existing row, C2:

1. hydrates and authenticates the row through `unprotectOutbox()`;
2. verifies the protected raw key equals the caller key;
3. re-finalizes the caller Draft with the stored envelope's record ID and
   recorded timestamp;
4. rebuilds the ingress envelope at the stored ordinal and compares exact
   logical bytes, content hash, bindings, and idempotency hash;
5. returns the same row with `inserted=false`.

This path performs zero Clock reads, UUID generation, active-key lookup,
nonce generation, encryption, ordinal allocation, or write. Any same-hash key
substitution, Draft difference, record／tenant／partition／ordinal／content
substitution, or authenticated body mismatch is `idempotency_conflict` unless
the stored row cannot be authenticated, which is `integrity_invalid`.

For a new row, C2 checks ordinal／state-version capacity, captures Clock once,
clamps `recordedAt=max(canonicalNow,lastAssignedRecordedAt)`, and generates one
record UUIDv7 from that timestamp. It then acquires the record advisory lock
and performs one store-wide `record_id` probe. A collision returns
`resource_unavailable` with zero Finalizer, envelope, active-key, nonce,
protection, or write calls. Only after the zero-row probe does C2 finalize the
Draft, build the envelope at `last_ingress_ordinal+1`, protect it once, call
`AuditOutboxStateSnapshot::pending(recordedAt,configuration)` exactly once,
and construct `AuditOutboxRecord::fromParts()` exactly once.

Bootstrap and existing-state append use separate exact SQL statements, each
with one dependency-ordered data-modifying CTE and one invalid-cast abort
fence.

- Bootstrap: `inserted_state` inserts ordinal/version `1`; `inserted_record`
  selects from `inserted_state` and inserts ordinal `1`; the fence requires
  both counts to equal `1`.
- Existing: `advanced_state` updates only the locked row whose partition,
  tenant pair, `last_ingress_ordinal=N`, `last_assigned_recorded_at`, and
  `state_version=N` match; it sets ordinal/version to `N+1` and the clamped
  timestamp. `inserted_record` selects from `advanced_state` and inserts
  ordinal `N+1`; the fence requires both counts to equal `1`.

Every physical insert binds all sixteen columns explicitly. Both fences cast
the success literal `1` or a nonnumeric fixed abort literal to `bigint`, as in
the Accepted B2 store. A mismatch therefore aborts the PostgreSQL root in the
same statement even when a caller catches the safe exception. No reservation
row exists, and every committed partition remains exact ordinals `1..N`.

### 5. FIFO selection and fenced state mutation

`claimNext()` selects only a pending row that is due and is the minimum
unacknowledged ordinal of its partition. Candidate order across partitions is
`available_at,partition_id,ingress_ordinal`. C2 may return no result after a
race and let the caller poll again; it does not loop without a bound.

`recoverNextExpired()` selects only an expired leased row that is the minimum
unacknowledged ordinal of its partition. Candidate order is
`lease_expires_at,partition_id,ingress_ordinal`.

After opening or joining the root, claim and recovery capture one Clock value
before their initial read-only candidate query. The same bound value is used
by candidate selection, the later predicate recheck, C1, and CAS. Even a
no-candidate result has exactly one Clock read. C2 then acquires the partition
advisory lock, locks the state and exact head row, and re-evaluates the same
predicate before mutation. `FOR UPDATE SKIP LOCKED` is forbidden for a row
within one partition because it can bypass a lower ordinal. No cross-partition
fairness or total order is claimed.

Every selected persisted row is hydrated, tenant／partition validated, and
authenticated through `unprotectOutbox()` before C1 transition computation.
`claimNext()` generates one UUIDv7 lease token from the already captured Clock
value only after authentication and the locked eligibility recheck, then calls
C1 `claim()`. Before that point token generation is zero. `recoverNextExpired()`
calls C1 `recover()` with the same captured time and the stored version and
token. At max attempt recovery becomes
`poisoned`; that ordinal is acknowledged and a later ordinal may be selected
on the next operation.

`retry()`、`relay()`、and `poison()` take exact record ID, expected tenant,
expected partition, expected version, and expected token. Their SQL read
predicate applies tenant／partition before protected bytes are returned. Each
requires the row to remain the partition's minimum unacknowledged position,
captures one Clock value after authentication, invokes the matching C1 pure
transition, then performs one state-only CAS.

Every CAS predicate contains partition, record ID, prior state, expected
version, and the applicable stored token／expiry boundary. Claim also proves
due time and no lower unacknowledged row; leased operations prove no lower
unacknowledged row. The SET list contains only
`state,attempt,available_at,lease_fencing_token,lease_expires_at,version`.
It never writes an immutable column. Affected rows must be exactly one;
zero is `transition_conflict`, and more than one is `integrity_invalid`.

All timestamps are caller-independent bound parameters from the single Clock
capture. SQL `CURRENT_TIMESTAMP`, database-generated tokens, random backoff,
or ciphertext replacement is forbidden.

### 6. Exact Store API and transaction ownership

The exact constructor is:

```text
__construct(
  Doctrine\DBAL\Connection,
  PostgreSqlAuditOutboxSchema,
  Psr\Clock\ClockInterface,
  BlackOps\Internal\Identifier\Uuidv7Generator,
  AuditRecordValidator,
  AuditRecordFinalizer,
  AuditPartitionIdDeriver,
  AuditIngressEnvelopeCodec,
  AuditProtectedBodyCodec,
  AuditOutboxIdempotencyKeyHasher,
  PostgreSqlAuditOutboxRecordHydrator,
  AuditOutboxConfiguration
)
```

The Store retains that one configuration, constructs one
`AuditOutboxTransition(configuration)` in its constructor, passes the same
configuration to every full-row hydration, and passes it to the exact one
initial `AuditOutboxStateSnapshot::pending()` call. The full-row hydrator does
not retain configuration and no second configuration source may enter the C2
graph. This makes mixed-maxAttempts construction impossible without changing
the frozen public constructor or call graph.

The exact public operations are:

```text
appendDurable(AuditRecordDraft, string idempotencyKey): AuditOutboxAppendResult
find(string recordId, ?AuditTypedReference expectedTenant, string expectedPartitionId): AuditOutboxLookupResult
claimNext(): AuditOutboxLookupResult
recoverNextExpired(): AuditOutboxLookupResult
retry(string recordId, ?AuditTypedReference expectedTenant, string expectedPartitionId,
      int expectedVersion, string expectedToken): AuditOutboxRecord
relay(same arguments): AuditOutboxRecord
poison(same arguments): AuditOutboxRecord
```

All mutating operations require `Connection::isAutoCommit()===true` before any
other resource. At transaction nesting level 0 C2 starts and owns one root. At
active level 1 it joins the caller-owned root without commit, rollback,
savepoint, or close. Every other active／level combination is
`storage_unavailable` before SQL. This is the C3 same-root seam; C2 does not
create or activate C3.

Own-root begin／commit／rollback failure and uncertain DBAL state follow the
Accepted B2 quarantine contract: close the Connection exactly once, discard
raw details, and return `storage_unavailable`. Caller-owned append uses the
transaction-aborting CTE fence after its first mutation. State transitions are
single-row statements, so a zero-row CAS has made no mutation and may safely
return `transition_conflict` without aborting unrelated caller work.

`find()` is read-only and performs no transaction control. It requires
auto-commit enabled and accepts either inactive level `0` or an already-active
caller level `1`; every inconsistent state is `storage_unavailable`. It
validates record ID, tenant, and derived partition before SQL; the predicate
contains record ID, partition, and the exact null／present tenant pair. Zero
rows and cross-tenant rows are the same not-found result with zero
hydration／key／decrypt calls.

### 7. Failure precedence and no-leak

There is no synthetic global precedence across dependencies that were not
called. Exact operation order is:

- append: auto-commit／input／Draft delivery／raw-key／partition -> transaction／
  partition lock／persisted boundary -> existing idempotency resolution -> new
  capacity -> Clock／UUID -> record lock／global collision probe -> Finalizer／
  envelope -> protection／initial state -> atomic write;
- find: auto-commit／input／tenant-partition -> select -> not-found or
  hydrate／authenticate;
- claim／recovery: auto-commit -> root -> one Clock -> candidate select ->
  partition lock／head recheck -> hydrate／authenticate -> C1 transition -> CAS;
- retry／relay／poison: auto-commit／caller scalars -> root／partition lock ->
  tenant-aware head read -> hydrate／authenticate -> one Clock -> C1
  transition -> CAS.

Malformed caller identity／tenant／partition／Draft and malformed persisted row
are `integrity_invalid`. Same-key semantic mismatch is
`idempotency_conflict`. Wrong state／owner／version／token／due／expiry or stale
CAS is `transition_conflict`. Safe-integer, timestamp, UUID, or protected-body
capacity exhaustion and generated-ID collision are `resource_unavailable`.
DB／transaction failure is `storage_unavailable`. Write-side protection failure
is `resource_unavailable`; an existing persisted row that cannot be
authenticated is `integrity_invalid`.

All public and private values that can carry row, tenant, key, token,
timestamp, protected body, or DB scalar data are `#[SensitiveParameter]`.
Exception message, previous exception, trace arguments／string, debug,
serialization, JSON, and `var_export()` expose no canary, SQL, schema, table,
record ID, partition, tenant, hash, key ID, nonce, ciphertext, plaintext, token,
or timestamp. Explicit trusted `toArray()` is not a logging projection.

### 8. Architecture and release boundary

The C2 namespace may depend only on Core Audit values, Accepted C1 Delivery,
Accepted B1 Record／Protection, shared validated PostgreSQL schema／bytea,
Internal UUID generation, PSR Clock, Doctrine DBAL, and PHP runtime types.
It may not depend on Application, Container, Console, HTTP, Transport generic
Outbox types, scheduler, worker, relay runtime, or public API types beyond the
already accepted Core input values.

The C1 Architecture guard is updated only to permit exact C1 calls from the C2
Outbox directory. The A2／B1 Architecture guard is updated only to permit the
C2 directory and these exact new call sites:

- `AuditProtectedIngress::fromArray()` from BodyCodec plus the PostgreSQL
  protected-ingress hydrator;
- `AuditProtectedBodyCodec::protectOutbox()` and `unprotectOutbox()` from the
  C2 Store exactly once each;
- `AuditRecordFinalizer::finalize()` from the C2 Store at the explicit new and
  idempotent-reconstruction sites;
- `AuditOutboxTransition::__construct()` once from the C2 Store constructor,
  and its five operations only from the corresponding Store operation;
- `AuditOutboxStateSnapshot::pending()` once on a new append, and
  `fromArray()` only inside the full-row hydrator with the Store configuration.

The C2 Architecture test owns exact ten-type inventory, exact modifiers and
listed public／protected API, generic sensitive-parameter coverage for every
data-bearing public or private parameter, exact dependency and named
construction graph, no dynamic call, no outside caller／reference, no
PublicApi marker, no generic Outbox reuse, no runtime registration, and exact
immutable-column SQL exclusions. Private helper names／decomposition and
constructor-promoted property layout are deliberately not an exact inventory.
The worker may not use that freedom to add a dependency, public/protected API,
second configuration source, indirect call, or unguarded sensitive parameter.
Accepted B2 Storage Production remains byte-identical.

`AuditStoreArchitectureTest` is superseded only at its direct protected-body
callsite map: `protectRecord()` and `unprotectRecord()` remain exactly one
callsite each from the Accepted B2 Store, while `protectOutbox()` and
`unprotectOutbox()` become exactly one callsite each from the C2
`PostgreSqlAuditOutboxStore`. Its indirect／dynamic-call rejection and every
other B2 invariant remain unchanged. Missing, extra, renamed, indirect, or
outside-C2 Outbox protection callsites must fail a non-vacuous mutation
fixture.

Current Framework migration count becomes 13. Community Board current
clean-install count becomes 18 (`13 Framework + 5 Application`). The package
required inventory is this exact ordered manifest of 54 paths:

```text
composer.json
LICENSE
README.md
CHANGELOG.md
UPGRADE.md
src/Core/Exception/ConfigurationFailure.php
src/Internal/Audit/Storage/AuditStoreFailure.php
src/Internal/Audit/Storage/AuditStoreException.php
src/Internal/Audit/Storage/AuditAppendResult.php
src/Internal/Audit/Storage/AuditLookupResult.php
src/Internal/Audit/Storage/AuditSegmentCloseResult.php
src/Internal/Audit/Storage/PostgreSqlAuditSchema.php
src/Internal/Audit/Storage/PostgreSqlAuditStoredRecordHydrator.php
src/Internal/Audit/Storage/PostgreSqlAuditRecordStore.php
src/Internal/Audit/Delivery/AuditOutboxState.php
src/Internal/Audit/Delivery/AuditOutboxFailure.php
src/Internal/Audit/Delivery/AuditOutboxException.php
src/Internal/Audit/Delivery/AuditOutboxConfiguration.php
src/Internal/Audit/Delivery/AuditOutboxStateSnapshot.php
src/Internal/Audit/Delivery/AuditOutboxTransition.php
src/Internal/Audit/Outbox/AuditOutboxStoreFailure.php
src/Internal/Audit/Outbox/AuditOutboxStoreException.php
src/Internal/Audit/Outbox/AuditOutboxRecord.php
src/Internal/Audit/Outbox/AuditOutboxAppendResult.php
src/Internal/Audit/Outbox/AuditOutboxLookupResult.php
src/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasher.php
src/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydrator.php
src/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydrator.php
src/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchema.php
src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php
migrations/postgresql/Version20260712000000.php
migrations/postgresql/Version20260712010000.php
migrations/postgresql/Version20260724000000.php
migrations/postgresql/Version20260724010000.php
migrations/postgresql/Version20260724100000.php
migrations/postgresql/Version20260724110000.php
migrations/postgresql/Version20260728133000.php
migrations/postgresql/Version20260803000000.php
migrations/postgresql/Version20260808000000.php
migrations/postgresql/Version20260808010000.php
migrations/postgresql/Version20260808100000.php
migrations/postgresql/Version20260829000000.php
migrations/postgresql/Version20260901000000.php
resources/stubs/operation.php.stub
resources/stubs/operation-value.php.stub
resources/stubs/operation-outcome.php.stub
resources/stubs/migration.php.stub
resources/stubs/auth-config.php.stub
resources/stubs/auth-service-provider.php.stub
resources/stubs/auth-register.php.stub
resources/stubs/auth-login.php.stub
resources/stubs/auth-logout.php.stub
resources/stubs/auth-user-migration.php.stub
resources/stubs/auth-session-migration.php.stub
```

This preserves the relative order of every prior exact37 path, inserts C1 and
C2 Production immediately before the migration block, and appends the C2
migration in chronological order. Both package scripts must match this one
manifest byte-for-byte. Historical Stable `1.2` migration and package fixtures
remain unchanged.

The current `DatabaseMigrationCommandTest` is not a historical baseline: its
framework-only `pending／migrations／applied` assertions move from 12 to 13 and
its Framework-plus-one-Application assertions move from 13 to 14. It is part
of C2 Files Allowed and both focused and complete test gates.

The Release Authority tuple is:

```text
stableVersion=1.2.0
candidateVersion=1.3.0-unreleased
capabilityId=audit.postgresql_outbox_persistence.v1
publicSourceInventory=[examples/community-board/README.md migration-count-only]
publicRouteInventory=[]
```

Only the Community Board current migration-count sentence changes. Guide and
Website source do not change. Generated HTML／Raw／Search／LLM artifacts,
browser behavior, runtime activation, release, deploy, and push are not C2
evidence.

## Consequences

C2 supplies one transaction-participating persistence component that C3 can
call inside the business root and C4 can call from its later relay loop. It
does not expose a partially wired runtime.

Idempotent retries reuse the authenticated persisted row. New appends bind
ordinal, record identity, timestamp, idempotency hash, and ciphertext in one
commit. FIFO and lease recovery are persisted without duplicating C1 state
rules.

The two-table schema deliberately remains separate from the canonical Audit
chain tables and the generic operation Outbox. Canonical append still occurs
only through the Accepted B2 Store during C4 relay.

## Remaining Issues

- None within P23-003C2.
- P23-003C4 relay／recovery runtime.
- Parent P23-003C Acceptance after all four children.

## Suggested Next Action

Proceed from the committed D153／P23-003C4 clean freeze to fresh independent
preflight. Preserve every Accepted C2 Production byte.
