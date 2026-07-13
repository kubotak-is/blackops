# In-Memory Execution Transport

`InMemoryExecutionTransport` is a database-free `ExecutionTransport` adapter for unit tests. It stores messages and claim state in one PHP object and loses all data when that object or process ends.

It is not durable, process-safe, thread-safe, or a production queue. It does not write Canonical Journal records, Outcome records, Attempt state, or lifecycle state. Use the PostgreSQL transport and lifecycle stores when those guarantees matter.

## Construction

Tests inject a PSR Clock and an explicit positive lease duration in seconds:

```php
use BlackOps\Transport\InMemory\InMemoryExecutionTransport;

$transport = new InMemoryExecutionTransport(
    clock: $testClock,
    leaseSeconds: 30,
);
```

The injected clock supplies `acceptedAt` for enqueue and the operation time for heartbeat, acknowledge, and release. `ClaimRequest::claimedAt()` remains the eligibility and lease-start time for each claim, allowing tests to advance claim time explicitly.

## Queue Semantics

- Enqueue rejects an Operation ID already present in the adapter, including a settled entry.
- A message is available when `availableAt <= claimedAt`.
- An active lease is expired when `leaseExpiresAt <= claimedAt`.
- One eligible message is claimed at a time, ordered by `availableAt` instant and then Operation ID.
- Every claim of an Operation ID increments its fencing sequence and returns an opaque token containing only the Operation ID and sequence.
- Acknowledge settles the in-memory queue entry. It cannot be claimed again.
- Release invalidates the current claim, replaces the message `availableAt`, and makes the entry claimable at or after that instant.

`DateTimeImmutable` values are compared as instants. Equivalent times with different UTC offsets therefore have the same eligibility boundary.

## Lease and Fencing Boundaries

Heartbeat, acknowledge, and release require the current token and an unexpired lease. The current lease is valid only while the injected clock returns a time strictly before `leaseExpiresAt`. At the exact expiry instant these operations fail with `DeferredTransportException`, while a new `ClaimRequest` may reclaim the message with the next fencing token.

Validation completes before any mutation. An unknown token, a stale fencing token, an expired token, a released token, or a settled token cannot extend a lease, settle an entry, change its availability, or overwrite the current claim.

## PostgreSQL Differences

The in-memory adapter intentionally models only the public transport ports needed by isolated tests:

- PostgreSQL persists encoded messages and supports process boundaries; in-memory state is local to one object.
- PostgreSQL coordinates concurrent workers with row locks and transactions; the in-memory adapter provides no concurrency guarantee.
- PostgreSQL lifecycle stores own Attempt start, Journal, Outcome, terminal transition, and expired-running-Attempt recovery. The in-memory adapter has no Attempt state and directly reclaims an expired queue lease.
- PostgreSQL acknowledge verifies that lifecycle processing has already made the operation terminal. In-memory acknowledge settles only its private queue record because there is no separate lifecycle store.
- PostgreSQL release rejects a claim after Attempt start. The in-memory adapter cannot represent Attempt start, so it accepts release for any current unexpired claim.

These differences make the adapter useful for deterministic unit tests, not a substitute for PostgreSQL integration tests.
