# Idempotency

Idempotency is an Internal lifecycle boundary shared by HTTP mutations and PHP
dispatch. The public surface accepts an opaque `IdempotencyKey`; scope,
fingerprint, storage records, and recovery remain Framework-owned.

## Entry ordering

Mutation requests and keyed PHP dispatch perform binding, validation,
authentication, and authorization before computing the scope and fingerprint or
creating a record. A key is rejected without a claim for GET/HEAD, ephemeral
outcomes, anonymous actors, malformed values, and duplicate header fields.
Authorization is evaluated for every keyed request, including a duplicate.

The scope combines the operation type, authorization actor, and opaque key hash.
The fingerprint is a versioned digest of the operation type and canonical
operation value. Raw keys, credentials, canonical values, and arbitrary headers
never enter an idempotency record.

## Store lifecycle

`IdempotencyStore` has four responsibilities:

```text
claim(scope, key hash, fingerprint, operation, strategy, created, expires)
terminalize(operation, terminal record, expected state)
find(scope)
attach/read response snapshot
```

The PostgreSQL store uses a unique `(scope_version, scope_hash)` boundary and
an insert with conflict handling. Exactly one concurrent caller receives a
`Claimed` processing record; another caller receives the existing record and
then compares its fingerprint to select the same-fingerprint, conflict, or
in-progress path. Terminalize requires the original operation ID, fingerprint,
and processing state. A second terminalize or a mismatched guard is a no-op.

The `idempotency_records` table keeps processing records after a process crash.
Terminal rows store either a typed result projection, a safe HTTP snapshot, or a
deferred acceptance timestamp. Schema checks reject partial projections,
unknown state combinations, and invalid result fields. The operation ID has a
unique constraint; the expiry index is the only additional retention index.

## Replay projections

`OperationResult` retains the original operation ID and has a replay marker.
`DeferredAcknowledgement` retains the original operation ID, acceptance time,
and replay marker. Neither public value exposes HTTP headers.

`IdempotencyResponseSnapshot` is versioned and only permits framework-generated
`Content-Type`, `Location`, and `Retry-After` headers. Replay overwrites the
response with `Idempotency-Replayed: true` and
`Cache-Control: private, no-store`. Credentials, cookies, arbitrary application
headers, and throwable details are not persisted.

Missing typed result or snapshot data is an `idempotency_expired` response, not
a new execution. Storage, decode, and attachment failures cross the safe
internal-failure boundary; SQL, table names, raw keys, and exception details do
not reach the caller.

## Crash recovery

`IdempotencyRecovery` may reconstruct a processing record only from a complete,
validated canonical journal and, for deferred acceptance, a matching durable
operation row. Inline recovery requires exactly one terminal journal event and
returns the typed completed or rejected result. Deferred recovery requires one
acceptance event plus a valid operation row and returns the original 202
acknowledgement.

Insufficient but valid evidence (for example, a missing journal tail) leaves the
record processing so a later recovery can retry the inspection. Corrupt or
contradictory evidence is terminalized as an internal failure. Neither case is
guessed or used to execute the handler again; a terminal internal failure is
returned through the safe replay-failure boundary.

## Retention

Idempotency records are an independent retention target. The configured period
is optional and defaults to the longest existing operation, journal, outcome,
or dead-letter period. A terminal record remains non-reclaimable while retained,
even after its response expiry; only confirmed purge permits a new claim for the
same scope and key. Active legal holds are checked both while planning and at
delete time. Successful deletion writes a payload-free audit containing only
the operation, target, count, policy, timestamp, and actor.

## Ownership

HTTP and PHP adapters own ordering and response projection. The Internal store,
fingerprint codec, PostgreSQL schema, and recovery service own persistence and
integrity. Applications may provide authorization and runtime configuration but
must not depend on Internal scope, fingerprint, snapshot, or SQL types.
