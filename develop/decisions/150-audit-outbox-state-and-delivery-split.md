# D150: Audit Outbox State and Delivery Split

Status: Accepted — P23-003C1 through C4 and parent P23-003C Accepted; BlackOps 1.3 unreleased

Updated At: 2026-09-04T13:57:41+09:00

## Current Handoff

P23-003B1／B2 and parent P23-003B are Accepted.
P23-003C1 through P23-003C4 and parent P23-003C are Accepted.
The final C candidate is committed at
`d5ab3476181058c5fb87b7c2c9476b6db76fef2e`; its clean same-SHA
Git／Composer package export is Green. No push was performed.

P23-003C1 is Accepted at committed candidate
`bc12a67210d9cb132cf3246f96b00d79fb519656`, with clean same-SHA package
export Green.
P23-003C2 is Accepted at committed candidate
`a92bef70fdb843a4dd2b8b34243fc892b43939cc`, with Complete Final Gate,
fresh General／DB／Documentation review, and clean same-SHA package export
Green. No push was performed.

P23-003C3 is Accepted at committed candidate
`b7b352a8e4c86a45822351c108772367515654bd`, with Complete Final Gate,
fresh General／Transaction／Documentation review, and clean same-SHA package
export Green.
It owns Catalog-fixed delivery selection, same-Framework-Connection mutation
orchestration, protected Operation-read release gating, and Application-facing
opaque／Audit-protection key composition. Its composer remains uncalled.

P23-003C4 is the Accepted final serial child. D153 is its most-specific
authority. It owns bounded `recoverOne()`／`relayOne()` seams, three
Store-owned relay transactions, protected-ingress canonicalization,
sender／settlement failure precedence, crash recovery, and poison FIFO
progress over the same C3 graph. It activates no current runtime.

D154 and P23-003D1 are the next authority／Task boundary.
Stable `1.2.0` remains current; BlackOps `1.3` and all Audit capabilities
remain unreleased.

## Context

The current P23-003C contract combines four different failure and ownership boundaries:

1. a pure four-state lifecycle with attempt, lease, version, and backoff rules;
2. a PostgreSQL row, per-partition ordinal, FIFO claim, and expected-version CAS;
3. required／durable policy selection, same-root transaction orchestration, and Application key-provider composition;
4. at-least-once relay, append-before-ack recovery, retry, and poison handling.

Implementing those boundaries in one worker packet would mix pure validation with database concurrency, transaction ownership, dependency injection, and runtime behavior.
It would also make a failure in one lane invalidate evidence from every other lane.
The serial split keeps each packet independently reviewable while retaining one parent P23-003C Acceptance boundary.

## Decision

### 1. Serial ownership

The P23-003C children are ordered as follows.

- P23-003C1: pure Audit Outbox state foundation.
- P23-003C2: PostgreSQL Audit Outbox row, migration, hydrator, idempotency, ordinal, FIFO, and fenced CAS persistence.
- P23-003C3: required／durable delivery selection, same-root transaction orchestration, protected-read response gating, and Application-facing opaque／protection key composition.
- P23-003C4: at-least-once relay and recovery runtime using the same `recordId` and `contentHash`.

Each child requires the previous child to be Accepted.
Parent P23-003C is not Accepted until C1 through C4 are all Accepted.
No child may activate a partial runtime that bypasses a later child.

### 2. C1 exact internal types

C1 adds exactly six Internal Production types under `BlackOps\Internal\Audit\Delivery`.
None carries `#[PublicApi]`.

```text
AuditOutboxState: string enum
  pending
  leased
  relayed
  poisoned

AuditOutboxFailure: string enum
  configuration_invalid
  integrity_invalid
  transition_conflict
  resource_unavailable

AuditOutboxException extends RuntimeException
  __construct(AuditOutboxFailure)
  failure(): AuditOutboxFailure
  code(): string
  message: "Audit outbox state transition failed."
  previous: null

AuditOutboxConfiguration
  __construct(
    int leaseDurationSeconds = 300,
    int maxAttempts = 5,
    int backoffBaseSeconds = 1,
    int backoffCapSeconds = 3600
  )
  readonly public values with exact bounds

AuditOutboxStateSnapshot
  pending(string availableAt, AuditOutboxConfiguration): self
  fromArray(#[SensitiveParameter] array, AuditOutboxConfiguration): self
  state(): AuditOutboxState
  attempt(): int
  availableAt(): ?string
  leaseFencingToken(): ?string
  leaseExpiresAt(): ?string
  version(): int
  toArray(): exact ordered six-field array
  __debugInfo(): []
  __serialize(): []
  __unserialize(array): always integrity_invalid

AuditOutboxTransition
  __construct(AuditOutboxConfiguration)
  claim(snapshot, expectedVersion, newToken, trustedNow): snapshot
  retry(snapshot, expectedVersion, expectedToken, trustedNow): snapshot
  recover(snapshot, expectedVersion, expectedToken, trustedNow): snapshot
  relay(snapshot, expectedVersion, expectedToken, trustedNow): snapshot
  poison(snapshot, expectedVersion, expectedToken, trustedNow): snapshot
```

The exact ordered state snapshot is:

```text
{state,attempt,availableAt,leaseFencingToken,leaseExpiresAt,version}
```

The raw／`toArray()` representation uses the string enum value for `state`, native integers for `attempt` and `version`, and exact string-or-null members for all three scheduling／lease fields.
`state()` alone returns the enum instance.

It is not a second persisted row schema.
P23-003C2 composes this snapshot with the immutable `AuditProtectedIngress` fields to form the exact 15-field `AuditOutboxRecord v1` row.

### 3. Configuration and scalar grammar

Configuration bounds are closed:

- `leaseDurationSeconds`: `1..86400`, default `300`;
- `maxAttempts`: `1..32`, default `5`;
- `backoffBaseSeconds`: `1..3600`, default `1`;
- `backoffCapSeconds`: `1..86400`, default `3600`.

Any invalid configuration throws `configuration_invalid` before a snapshot or transition is evaluated.

Every timestamp is an exact UTC value in `YYYY-MM-DDTHH:MM:SS.ffffffZ` form and must round-trip through the existing `AuditRecordTime` validator.
Every version is an integer in `1..9007199254740991`.
Every attempt is an integer bounded by the state matrix and the injected `maxAttempts`.
Every non-null lease token is a canonical lowercase UUIDv7.
The token is an opaque non-credential fencing value; its UUID form supplies a closed wire and does not grant authorization by itself.
P23-003C2 later generates it with the existing injected UUIDv7 source after capturing the one trusted transition clock.

### 4. Exact state matrix

The snapshot matrix is closed.

| State | Attempt | availableAt | leaseFencingToken | leaseExpiresAt | Version |
| --- | --- | --- | --- | --- | --- |
| `pending` | `0..maxAttempts-1` | non-null | null | null | positive safe integer |
| `leased` | `1..maxAttempts` | null | non-null | non-null | positive safe integer |
| `relayed` | `1..maxAttempts` | null | null | null | positive safe integer |
| `poisoned` | `1..maxAttempts` | null | null | null | positive safe integer |

`pending()` creates exactly `pending,attempt=0,version=1` with the supplied canonical `availableAt` and null lease fields.
`fromArray()` checks `count===6` before list detection or key extraction, accepts only the exact six keys in the exact order, and rejects missing／extra／wide input before copying any member.
After that fixed-width guard it validates scalar types and the whole matrix before retaining a detached snapshot.
Malformed persisted or decoded state is always `integrity_invalid`.

### 5. Exact transitions

Every transition is pure and returns a new snapshot.
The input snapshot never mutates.
Every successful operation increments version by exactly one.
Version exhaustion is `resource_unavailable` and produces no result.

`claim` requires a due `pending` snapshot, `attempt < maxAttempts`, the expected current version, and one valid new token.
It increments attempt and version once, sets state to `leased`, clears `availableAt`, stores the new token, and computes `leaseExpiresAt=trustedNow+leaseDurationSeconds`.

`retry` requires `leased`, `attempt < maxAttempts`, the expected version, the matching stored token, and an active lease.
It preserves attempt, increments version once, returns to `pending`, clears both lease fields, and computes `availableAt=trustedNow+d(attempt)`.

`recover` requires `leased`, the expected version, the matching stored token, and an expired lease.
When `attempt < maxAttempts`, it performs the same pending/backoff result as retry.
When `attempt=maxAttempts`, it increments version once and returns `poisoned` with all scheduling and lease fields null.

`relay` requires the expected version and matching active lease.
It increments version once and returns terminal `relayed` with all scheduling and lease fields null.

`poison` requires the expected version and matching active lease.
It increments version once and returns terminal `poisoned` with all scheduling and lease fields null.
It is the non-retryable active-owner path and is valid at any leased attempt `1..maxAttempts`.

`trustedNow < leaseExpiresAt` is active.
`trustedNow >= leaseExpiresAt`, including equality, is expired.
Pending is due exactly when `trustedNow >= availableAt`.
Terminal states accept no transition.
A wrong operation state, not-yet-due claim, invalid ownership input, expected-version mismatch, token mismatch, active／expired boundary mismatch, retry at max, or second terminal settlement is `transition_conflict` and returns no snapshot.

### 6. Backoff and time arithmetic

For failed claim attempt `n`, `d(1)=min(backoffBaseSeconds,backoffCapSeconds)`.
For each later attempt, if the current delay is greater than `floor(backoffCapSeconds/2)`, the next delay is the cap; otherwise it is twice the current delay.
The comparison occurs before multiplication.
The loop is bounded by `maxAttempts<=32` and never uses floating point or jitter.

Adding lease or backoff seconds must produce another exact four-digit-year UTC timestamp with six fractional digits.
An unrepresentable result, timestamp overflow, or version overflow is `resource_unavailable`.
Invalid timestamp grammar is `transition_conflict` for operation input and `integrity_invalid` for a restored snapshot.

### 7. Validation precedence and no-leak behavior

The operation-level validation order is:

1. caller scalar grammar and safe-integer bounds in signature order;
2. operation state eligibility;
3. expected version and, when present, matching stored token;
4. due／active／expired and attempt boundary;
5. version capacity, followed by timestamp-addition capacity;
6. trusted result reconstruction.

All failures are value-free.
No exception message, previous exception, trace argument, debug output, serialized output, implicit object JSON output, or `var_export()` output may expose a lease token or timestamp canary.
The explicit trusted `toArray()` result contains the state fields by design and is not a logging projection.
All array and token arguments that can contain such values are marked `#[SensitiveParameter]`.

### 8. C1 architecture boundary

C1 depends only on PHP runtime types and the existing Core Audit timestamp validator.
It does not depend on Doctrine DBAL, PSR Clock, UUID generators, B1 protection codecs, B2 storage, Application, Container, Transport, CLI, HTTP, or existing generic Outbox runtime types.

No Production file outside `src/Internal/Audit/Delivery` may reference a C1 type in this packet.
The existing generic operation Outbox is not reused because its states, retry semantics, AAD, and terminal handling differ from `AuditOutboxRecord v1`.

The Accepted A2 Architecture guard may change only to recognize this direct
Core timestamp-validator reuse. It must continue rejecting every other A2
type from Delivery and must not authorize Application, Container, Transport,
CLI, HTTP, storage, or runtime registration.

### 9. Release boundary

The Release Authority tuple is:

```text
stableVersion=1.2.0
candidateVersion=1.3.0-unreleased
capabilityId=audit.outbox_state.v1
publicSourceInventory=[]
publicRouteInventory=[]
```

C1 adds an unregistered Internal foundation only.
It does not change Stable migration history, current commands, public Guide／Website source, generated HTML／Raw／Search／LLM artifacts, or runtime behavior.

## Consequences

C1 can be implemented and reviewed without Docker PostgreSQL concurrency or Application wiring.
Its tests can exhaust every state, attempt, due, lease, version, and backoff boundary deterministically.
C2 can later apply the same pure transition result through one expected-version PostgreSQL CAS without reimplementing lifecycle rules.

The split does not weaken the parent contract.
Required same-transaction append, durable ingress persistence, and Application
key composition are Accepted through C3. Relay remains blocked until C4 is
Accepted, and current runtime activation remains separately blocked.

## Remaining Issues

None within P23-003C1 through C4 or parent P23-003C.
P23-003D through G, P23-004 through 006, release, and runtime activation remain
downstream.

## Suggested Next Action

Proceed to the bounded D154／P23-003D1 Framework operational Catalog packet
without reopening Accepted C behavior.
