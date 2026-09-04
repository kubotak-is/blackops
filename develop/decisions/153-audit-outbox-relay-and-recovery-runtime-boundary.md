# D153: Audit Outbox Relay and Recovery Runtime Boundary

Status: Accepted — P23-003C4 and parent P23-003C Accepted; BlackOps 1.3 unreleased

Updated At: 2026-09-04T13:57:41+09:00

## Current Handoff

P23-003C1 through P23-003C4 and parent P23-003C are Accepted.
The final C candidate is committed at
`d5ab3476181058c5fb87b7c2c9476b6db76fef2e`; its clean same-SHA
Git／Composer package export is Green. No push was performed.

P23-003C4 is the Accepted final serial child of parent P23-003C.
It owns the bounded internal relay and recovery runtime that consumes the same
C3 PostgreSQL Connection, Audit Outbox Store, canonical Record Store, Audit
protection codec, validator, and Outbox configuration graph.
It does not register a command, daemon, scheduler, worker, Container service,
HTTP route, or Application caller.

Stable `1.2.0` remains current. BlackOps `1.3`, Audit Trail delivery, and the
C4 runtime remain unreleased. D154／P23-003D1 is the next bounded authority.

## Context

Accepted C2 deliberately separates each Outbox transition into its own Store
transaction.
`claimNext()` and `recoverNextExpired()` authenticate the protected ingress
before committing a fenced state transition, but return only the protected
`AuditOutboxRecord`.
Accepted B2 canonical durable append receives a
`CanonicalAuditLogicalRecord` and is idempotent for the same `recordId` and
`contentHash`.

C4 must bridge those seams without widening either Accepted Store API.
It must also preserve the crash window that makes at-least-once delivery
recoverable:

```text
claim commit -> decode and validate -> canonical append commit -> relay ack commit
```

Joining these steps to a caller-owned level-1 root would erase that boundary.
Reusing the Stable generic transactional Outbox runtime would mix unrelated
payload, sender, heartbeat, telemetry, command, and scheduler contracts.
Both are forbidden.

Three ambiguity roots are closed here.

1. The relay bridge and its exact Connection／Store identity are fixed before
   Product work.
2. Canonical Store failures, settlement failures, and final-attempt crash
   states have one finite mapping.
3. `poisoned` acknowledges the current ordinal and releases the next ordinal;
   it does not install a persistent partition halt.

## Decision

### 1. Exact namespace and Production types

C4 adds exactly seven Internal Production types under
`BlackOps\Internal\Audit\Relay`.
None carries `#[PublicApi]`.

```text
AuditRelayOutcome
AuditRelayFailure
AuditRelayException
AuditRelayPersistence
PostgreSqlAuditRelayPersistence
AuditIngressCanonicalizer
AuditOutboxRelayRuntime
```

`AuditRelayOutcome` and `AuditRelayFailure` are string enums.
`AuditRelayPersistence` is an interface.
`AuditRelayException` is a final class extending `RuntimeException`.
`PostgreSqlAuditRelayPersistence`, `AuditIngressCanonicalizer`, and
`AuditOutboxRelayRuntime` are final readonly classes.

Every unlisted public or protected callable is forbidden.
Private helper decomposition is implementation-owned, but every private
parameter that can carry a row, logical record, tenant, partition, identifier,
hash, fencing token, exception, or Store result is marked
`#[SensitiveParameter]` and is covered by the exact Architecture inventory.

### 2. Exact outcome and failure values

`AuditRelayOutcome` has exactly eight cases.

```text
idle
recovered_pending
recovery_poisoned
relayed_inserted
relayed_existing
retried
poisoned
stale
```

The outcome carries no record identifier, tenant, partition, hash, attempt,
token, time, reason, or throwable.

`AuditRelayFailure` has exactly three cases.

```text
transaction_invalid
resource_unavailable
storage_unavailable
```

`AuditRelayException` has this exact public surface.

```text
__construct(AuditRelayFailure $failure)
failure(): AuditRelayFailure
code(): string
__debugInfo(): array
__serialize(): array
__unserialize(array $data): void
```

Its message is exactly `Audit relay operation failed.`.
Its previous exception is always null.
Debug, serialization, JSON, `var_export()`, and trace surfaces expose no row,
logical record, idempotency key, tenant, partition, identifier, hash, fencing
token, key reference, key material, Store result, or caught throwable.

### 3. Exact persistence seam

`AuditRelayPersistence` is the sole C4 Store seam.
Its exact public surface is:

```text
recoverNextExpired(): AuditOutboxLookupResult
claimNext(): AuditOutboxLookupResult
appendCanonical(CanonicalAuditLogicalRecord $logical): AuditAppendResult
retry(
  string $recordId,
  ?AuditTypedReference $expectedTenant,
  string $expectedPartitionId,
  int $expectedVersion,
  string $expectedToken
): AuditOutboxRecord
relay(same five parameters): AuditOutboxRecord
poison(same five parameters): AuditOutboxRecord
```

The logical record and every scalar／tenant parameter are
`#[SensitiveParameter]`.

`PostgreSqlAuditRelayPersistence` has one public constructor:

```text
__construct(
  PostgreSqlAuditOutboxStore $outbox,
  PostgreSqlAuditRecordStore $records
)
```

It implements the interface and delegates each method exactly once to the
corresponding Accepted Store method.
`appendCanonical()` delegates to `PostgreSqlAuditRecordStore::appendDurable()`.
It performs no retry, translation, validation, transaction control, Clock or
UUID access, and returns the exact Store result object.
It rethrows the exact Store exception object unchanged.

Production constructs this adapter exactly once, only in
`ApplicationAuditRuntimeComposer`, from the same two Store instances exposed
by the composed `ApplicationAuditRuntime`.
No other Production caller or implementation is allowed.

### 4. Exact protected-ingress canonicalizer

`AuditIngressCanonicalizer` has this exact public surface:

```text
__construct(
  AuditProtectedBodyCodec $protection,
  AuditRecordValidator $validator
)
canonicalize(AuditOutboxRecord $record): CanonicalAuditLogicalRecord
```

The record parameter is `#[SensitiveParameter]`.
One call performs this exact order:

1. capture the Outbox record and protected-ingress bindings once;
2. call `AuditProtectedBodyCodec::unprotectOutbox()` exactly once;
3. capture the returned `AuditIngressEnvelope` once;
4. require the exact envelope kind, durable policy, `recordId`, tenant,
   partition, ingress ordinal, and `contentHash` bindings to match the claimed
   row;
5. construct one `AuditLogicalRecordCandidate` from only the envelope's
   `logicalRecord` member;
6. call `AuditRecordValidator::validateLogical()` exactly once;
7. construct and return one `CanonicalAuditLogicalRecord`.

No raw idempotency key is returned or retained.
The Accepted codec remains the sole decryption and AAD authority.
The Accepted validator remains the sole Catalog and logical-record semantic
authority.
The canonicalizer does not calculate or replace a content hash; the canonical
Record Store recomputes it and the runtime compares the committed result with
the claimed Outbox binding.

`AuditProtectionException` is distinguishable from
`AuditValidationException` at the runtime boundary.
Every other throwable from this seam is unexpected and contracts to C4
`storage_unavailable` without a settlement attempt.

### 5. Exact bounded relay runtime

`AuditOutboxRelayRuntime` has this exact public surface:

```text
__construct(
  Doctrine\DBAL\Connection $connection,
  AuditRelayPersistence $persistence,
  AuditIngressCanonicalizer $canonicalizer,
  AuditOutboxConfiguration $configuration
)
recoverOne(): AuditRelayOutcome
relayOne(): AuditRelayOutcome
```

The runtime has no Clock, UUID source, nonce source, sleep, batch size,
heartbeat, logger, telemetry, signal, loop, daemon, scheduler, command, or
Container dependency.
One method call handles at most one recovered lease or one claimed record.
A future activation owner may call `recoverOne()` and `relayOne()` in a bounded
cycle, but C4 creates no such caller.

The injected Connection is the exact Framework Connection used by both
Accepted Stores.
The injected configuration is the exact C3 instance used by the Outbox Store.
Those identities are fixed by the sole composer construction graph and direct
composition tests; the runtime does not resolve either dependency again.

At entry, and immediately after every successful persistence method, the
Connection must have auto-commit enabled, no active transaction, and nesting
level `0`.
Before every later persistence method the same condition is checked again.
Failure is `transaction_invalid`, and no later persistence call occurs.

The runtime itself performs zero begin, commit, rollback, savepoint, SQL,
Clock, UUID, nonce, protection-key, or configuration-resolution calls.
Caller-owned active level `1`, inactive nonzero levels, level `2+`, and
auto-commit disabled are rejected before the first persistence call.

### 6. Recovery order

`recoverOne()` performs exactly:

```text
entry state -> recoverNextExpired own-root call -> success post-state -> result
```

- not found returns `idle`;
- a recovered `pending` record returns `recovered_pending`;
- a recovered `poisoned` record returns `recovery_poisoned`;
- `transition_conflict` returns `stale`;
- Outbox `resource_unavailable` throws
  `AuditRelayException(resource_unavailable)`;
- every other Outbox failure or unexpected throwable throws
  `AuditRelayException(storage_unavailable)`.

No claim, decode, canonical append, retry, relay, or second poison follows a
recovery call.
Accepted C2 captures the sole recovery Clock and applies the exact
expiry-minus-one-microsecond／equality／plus-one-microsecond rule.

### 7. Relay order and committed-result binding

`relayOne()` performs exactly:

```text
entry state
-> claimNext own-root call and commit
-> success post-state
-> canonicalize protected ingress
-> pre-append state
-> appendCanonical own-root call and commit
-> success post-state
-> validate committed recordId/partition/tenant/contentHash
-> pre-ack state
-> relay own-root call and commit
-> success post-state
-> relayed_inserted | relayed_existing
```

Claim not found returns `idle`.
A claim `transition_conflict` returns `stale`.
Other claim failure follows the recovery mapping above.

The claimed record must be `leased`, have attempt `1..maxAttempts`, a positive
safe version, and non-null fencing token and expiry.
The runtime captures record ID, tenant, partition, version, token, attempt,
and content hash from that one claimed record before later calls.

After canonical append, the returned stored record must have the same
`recordId`, derived partition, tenant binding, and `contentHash`.
Mismatch is a non-retryable integrity failure and is settled through
`poison()`.
`AuditAppendResult::inserted() === true` produces `relayed_inserted` after ack;
false produces `relayed_existing` after ack.

An append success is never followed by retry or poison.
If relay ack is stale or unavailable, the canonical row remains committed and
the lease is left for the Accepted recovery path.

### 8. Sender and settlement failure matrix

The canonical append mapping is exact.

| Cause | Classification | attempt < max | attempt = max |
|---|---|---|---|
| `AuditStoreFailure::ResourceUnavailable` | retryable | `retry()` | `poison()` |
| `AuditStoreFailure::StorageUnavailable` | retryable | `retry()` | `poison()` |
| `AuditProtectionException` before append | retryable | `retry()` | `poison()` |
| `AuditStoreFailure::IdempotencyConflict` | non-retryable | `poison()` | `poison()` |
| `AuditStoreFailure::SegmentConflict` | non-retryable | `poison()` | `poison()` |
| `AuditStoreFailure::IntegrityInvalid` | non-retryable | `poison()` | `poison()` |
| `AuditValidationException` before append | non-retryable | `poison()` | `poison()` |
| committed-result binding mismatch | non-retryable | `poison()` | `poison()` |
| any other throwable | unknown | no settlement; throw `storage_unavailable` | same |

A successful retry returns `retried`.
A successful poison returns `poisoned`.

Settlement failure precedence is exact.

- `transition_conflict` returns `stale` and no second settlement is attempted;
- `resource_unavailable` throws C4 `resource_unavailable`;
- every other Outbox failure or unexpected throwable throws C4
  `storage_unavailable`;
- a settlement failure supersedes the original sender failure because the
  persisted lease state could not be confirmed;
- every C4 exception has fixed message, null previous, and contains no original
  throwable.

The same settlement mapping applies to relay ack after a successful append,
except that retry／poison fallback is forbidden.

### 9. Crash, final attempt, and FIFO meaning

The accepted guarantee is at-least-once attempt with idempotent canonical
append, not distributed exactly-once settlement.

- crash after claim and before append leaves a lease for recovery;
- crash after canonical append and before ack leaves a committed canonical row
  and a lease;
- a later retry uses the same `recordId` and logical bytes, so canonical append
  returns the existing same-sequence／same-hash row before ack;
- ack commit followed by response loss may surface `storage_unavailable` even
  though the row is already `relayed`;
- when an expired final-attempt lease is recovered, Accepted C2 changes it to
  `poisoned` without querying the canonical Store.

Therefore a final-attempt `poisoned` Outbox row may or may not already have its
canonical record.
`relayed` means the relay acknowledgement was observed and committed.
`poisoned` means delivery completion was not established within the bounded
attempt lifecycle; it is not proof that the canonical row is absent.
No new reconciliation API is added in C4.

Both `relayed` and `poisoned` acknowledge their ordinal and release the next
ordinal in the same partition.
Poison returns an explicit operational outcome for monitoring but does not add
a persistent partition halt.
Changing that rule requires a future state-machine and schema Decision.

### 10. Application composition and nonactivation

`ApplicationAuditRuntime` adds exactly one private
`AuditOutboxRelayRuntime` member, one final argument to
`fromComposition()`, and one public getter:

```text
relay(): AuditOutboxRelayRuntime
```

Every prior member, callable, identity, and return type remains unchanged.

`ApplicationAuditRuntimeComposer::compose()` constructs exactly one
`AuditIngressCanonicalizer`, one `PostgreSqlAuditRelayPersistence`, and one
`AuditOutboxRelayRuntime` after constructing the Accepted Stores.
It passes the same Framework Connection, protection codec, validator,
configuration, canonical Store, and Outbox Store instances already in the C3
graph.
Provider resolution counts and all prior C3 identities remain unchanged.

C4 has exact zero Production callers of
`ApplicationAuditRuntimeComposer::compose()`, `recoverOne()`, and
`relayOne()` outside the composer construction and runtime getter graph.
No current Application／Container／Console／HTTP／worker／scheduler／maintenance
task／route registers or invokes C4.

The Stable generic `BlackOps\Internal\Outbox\OutboxRelayRuntime`, its
`outbox:relay:*` commands, scheduler task, configuration, sender, telemetry,
and public Documentation remain byte-identical and must remain positively
present.
They are not an Audit relay adapter or C4 activation seam.

### 11. Failure precedence

The first applicable failure at each C4 entry is:

1. Connection auto-commit／active／nesting state;
2. claim or recovery Store transition／resource／other failure;
3. Connection post-state after a successful claim or recovery return;
4. returned-record state／binding integrity;
5. decode／protection or logical validation;
6. Connection pre-state before canonical append;
7. canonical append failure;
8. Connection post-state after a successful canonical append return;
9. committed-result inspection and binding;
10. Connection pre-state before retry／poison／relay settlement;
11. retry／poison／relay settlement failure;
12. Connection post-state after a successful settlement return.

Connection post-state is checked immediately after a successful Store return
and before interpreting or exposing that result.
A Store exception is mapped without a catch-path post-state recheck, so an
already-selected Store failure is not masked.
A successful claim whose returned record cannot yield trusted fence
coordinates becomes `storage_unavailable` with no settlement attempt.
Any failure while inspecting the committed canonical result is a
non-retryable committed-result binding failure and is settled through poison.

### 12. Evidence and mutation resistance

Acceptance requires direct and real PostgreSQL evidence for:

- exact three-transaction claim／append／ack order;
- inserted and existing canonical append results;
- claim-before-append, append-before-ack, final-attempt, and response-loss
  crash states;
- all canonical Store failure cases, protection and validation failures,
  unexpected throwables, attempts below and at max, and every settlement
  precedence branch;
- recovery expiry boundary, stale token/version, relay-versus-recovery,
  same-partition parallel claim, ordinal FIFO, cross-partition progress, and
  poison releasing the next ordinal;
- system and tenant records, content cap and cap+1, historical Outbox key,
  role-separated record／Outbox keys, and ciphertext preservation;
- active level1, auto-commit false, wrong Connection identity, post-Store
  drift, and zero runtime transaction-control calls;
- direct and composed debug／serialization／JSON／`var_export()`／trace no-leak;
- exact class／interface／enum modifiers, signatures, defaults, nullability,
  attributes, implementations, dependencies, constructors, callers, direct
  calls, and zero activation.

Architecture mutation fixtures cover alias, group-use, FQCN, same-namespace,
literal and one-hop／two-hop class strings, heredoc／nowdoc, dynamic,
reflection, parenthesized callables, extra files／types／implementations／
dependencies／callers, forbidden capabilities, and non-executable decoys.
An oracle derived only from the candidate itself is forbidden.

### 13. Package and release boundary

The Release impact is exact:

```text
stableVersion=1.2.0
candidateVersion=1.3.0-unreleased
capabilityId=audit.outbox_relay.v1
publicSourceInventory=[]
publicRouteInventory=[]
publicCliInventory=[]
frameworkMigrationCount=13
communityMigrationCount=18
```

C4 adds seven Internal package paths to the Accepted exact68 required
inventory, producing exact75 in the same ordered list and the single export
validation loop.
Both Git and Composer archives require the same exact75.
The required-inventory oracle retains one executable validation function,
exactly two top-level calls, and repinned mutation rejection independent of
the candidate's own reviewed hash.

No migration, public API, public route, public command, Guide／Website source,
Community Board behavior, generated artifact, release, deploy, or publication
changes in C4.
The existing Stable generic Outbox CLI remains a positive unchanged fixture,
while every Audit-specific CLI／route／registration remains exact zero.

## Rejected Alternatives

### Reuse the Stable generic Outbox runtime

Rejected because its payload, sender, heartbeat, telemetry, command, and
scheduler contracts are unrelated to Audit record canonicalization and would
change Stable behavior.

### Join a caller-owned transaction

Rejected because claim, canonical append, and ack must be independently
committed to preserve the recoverable at-least-once crash boundary.

### Add a C2 claim-and-decode API

Rejected because C2 already authenticates before claim and its exact Store API
is Accepted.
C4 performs one deliberate second open after the claim transaction commits.

### Reconcile final-attempt poison against the canonical Store

Rejected for C4 because it requires a new expired-owner transition and changes
Accepted C1／C2 state authority.
The conservative terminal meaning is fixed instead.

### Persistently halt a partition after poison

Rejected because Accepted C1／C2 define both terminal states as acknowledged
positions that release FIFO progress.

## Consequences

- C4 is directly callable and fully testable but remains unregistered.
- At-least-once delivery uses three Store-owned transactions and exact
  idempotent canonical identity.
- A poison outcome is operationally visible without introducing a new table,
  state, reason field, or partition halt.
- C1／C2／C3 Production remains byte-identical except for the exact C3
  Application runtime／composer extension named here.
- Parent P23-003C is Accepted after C4 gates and reviews, but Audit runtime
  activation remains owned by later producer／registration work.
- Push, release, deploy, publication, and browser behavior remain outside this
  Decision.
