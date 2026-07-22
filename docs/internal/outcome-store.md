# Typed Outcome Store

The public outcome boundary consists of `OutcomeRecord`, `OutcomeReader`, `OutcomeWriter`, `OutcomeStore`, and `OutcomeStoreException`. It exposes only Core identifiers, the `Outcome` marker, immutable UTC time, and PHP exceptions. Database connections, encoded payloads, schema versions, Doctrine types, and PostgreSQL names remain adapter details.

## PostgreSQL representation

`PostgreSqlOutcomeStore` persists one row per operation:

```text
operation_id primary key, foreign key to operations on delete restrict
outcome_type
schema_version
encoded_payload
completed_at
```

The adapter currently writes schema version 1. Before hydration it decodes the JSON envelope, verifies that the payload class matches the row's `outcome_type`, and verifies that the class exists and implements `Outcome`. Only then does it construct the outcome. This prevents a corrupt row from invoking an unrelated constructor. Duplicate operation IDs, foreign-key failures, unsupported schemas, malformed payloads, type mismatches, and non-Outcome classes become `OutcomeStoreException`.

## Worker transaction

The deferred completion boundary uses one DBAL connection and transaction:

```text
lock and fence running operation
mark completed and advance sequence
append attempt.succeeded
append operation.completed with canonical outcome
insert typed outcome row
commit
```

`DeferredWorkerRuntimeStorage` therefore requires an `OutcomeWriter` backed by the same connection as lifecycle and canonical journal storage. An outcome insert or encoding failure aborts the transaction. The operation remains running with only the previously committed attempt-start record; no completed state, completed journal, or outcome row survives.

Rejected and supervised failure paths never call the outcome writer. Existing canonical journal outcome data remains unchanged and is independent from outcome-table retention.

`EphemeralOutcome` is never a store input in a valid runtime graph. Inline completion records an `EmptyOutcome` while returning the actual object only to the direct caller. The PostgreSQL codec also rejects an actual `EphemeralOutcome` defensively, so a manually composed or corrupted path cannot create an outcome row.

## Retention transaction

`PostgreSqlRetentionPlanner` selects outcome rows by `completed_at + outcome retention <= now` and excludes any operation with an active hold. `PostgreSqlOutcomeRetentionDeleteService` checks the hold again in its delete statement to close the plan-to-execution race.

Each deleted row and its payload-free `RetentionPurgeAuditRecord` are committed on the same connection transaction. Audit failure rolls the deletion back. The purge facade reports the count through `RetentionPurgeResult::outcomesDeleted()`.
