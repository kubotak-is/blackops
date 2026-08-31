# D150: Audit Outbox State and Delivery Split

Status: Proposed — P23-003C1 Accepted; D151／P23-003C2 second corrected-packet commit checkpoint; fresh re-preflight required; BlackOps 1.3 unreleased

Updated At: 2026-09-01T06:39:35+09:00

## Current Handoff

P23-003B1／B2 and parent P23-003B are Accepted.
Reviewed B2 candidate `968afe2b0c4bbff86e0013592d69dc7115cf8b4f` and its clean-candidate package export are Green.
The management-only closeout is committed at `e772242ed2156ee259ae177f69a6b0a9189fef68`, and the worktree was clean before this packet was drafted.

P23-003C is split into serial C1→C2→C3→C4 packets.
C1 owns only the pure Audit Outbox state snapshot, configuration, deterministic backoff, and transition rules.
C1 performs no SQL, transaction control, clock read, identifier generation, key lookup, encryption, canonical append, relay, Application composition, or runtime registration.

Stable `1.2.0` remains current and BlackOps `1.3` remains unreleased.
No public API, public Documentation, CLI, HTTP route, Application Container, Audit Outbox table, or relay runtime becomes active in C1.

The first Sol Max preflight is Historical. Focused execution exposed one
Architecture-scope conflict: this Decision requires direct reuse of
`AuditRecordTime`, while the Accepted A2 source-registration guard rejected
that reference from Delivery. C1 therefore owns one bounded guard correction
that permits only `AuditRecordTime` in `src/Internal/Audit/Delivery`; all other
A2 type references and every runtime registration remain forbidden.

Fresh corrected Sol Max preflight, bounded Luna implementation, Iteration Gate,
and Complete Final Gate are Green. Product／Test exact11 is accepted at
`d0bfa64e35ce8d113909a2aba710c57ed7bb33fd6711c51df207e71d33919fad`.
Fresh Orchestrator Sol Max implementation review returned
`P1=0/P2=0/P3=0`; P23-003C1 is Accepted. Reviewed candidate
`bc12a67210d9cb132cf3246f96b00d79fb519656` is committed, and its clean
exact-SHA Git／Composer package export is Green. No push was performed.

The shared `AuditRecordArchitectureTest.php` belonged to Accepted B2 exact26.
C1 supersedes only that test byte. The other Accepted B2 exact25 remain
byte-identical at Task-order aggregate
`4b605e2a23dd77b1c41f8aed95244a46776aef599d2607c88a7d63ee8a2f0354`;
no B2 Production byte changes.

D151 and the exact P23-003C2 Task Packet were drafted against clean C1 closeout
HEAD `4bebcb09d8e67e58c424d0c9543e594708d02872`. D151 is the
most-specific C2 authority for the two-table schema, callable graph,
transaction participation, authenticated idempotent reconstruction, ordinal,
FIFO selection, and fenced persistence. No C2 Product work or partial runtime
activation has occurred.

The first General／DB／Documentation preflight lanes are Historical Changes
Requested. Their overlapping configuration, DDL, ordering, scope, package,
and callable-authority roots are corrected in D151 and the Task.

The clean-commit re-preflight at
`26527296da112e315c6a332d26282f52625bb7a7` is also Historical: General and DB
each returned `P1=1/P2=0/P3=0`, while Documentation returned Green. D151 and
the Task now bind every retained row tenant pair through the exact aggregate,
make the Store derive the hydrated partition before decrypt, and add the
current migration-command count test to exact27／worker exact29 scope.

This second correction establishes a new nine-file management packet; its
exact SHA is captured after commit. Next Action: capture that SHA and run fresh
Sol／Documentation re-preflight from the clean HEAD. Product work waits for
Green.

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
Required same-transaction append, durable ingress persistence, Application key composition, and relay remain blocked until their owning children are Accepted.

## Remaining Issues

- P23-003C2 PostgreSQL row／FIFO／CAS persistence.
- P23-003C3 delivery transaction／policy／key composition.
- P23-003C4 relay and recovery runtime.
- Parent P23-003C Acceptance after all four children.

## Suggested Next Action

Capture the second corrected-packet commit SHA, then run fresh Sol／
Documentation re-preflight against D151 and the exact P23-003C2 Task Packet.
Product work remains blocked until Green.
