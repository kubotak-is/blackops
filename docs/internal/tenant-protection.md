# Tenant and Protected Storage Contract

This document is the implementation-facing contract for the tenant and protected-operation-data boundary. The public reader path is `OperationStatusQuery`, `OperationJournalQuery`, and `OperationOutcomeQuery`; raw PostgreSQL ports remain infrastructure SPI.

## Tenant propagation

`TenantRef(type, id)` is an immutable opaque identity. It contains no credential, role, membership, permission, display name, or plan. `ExecutionContext::tenant()` is optional and has no mutator. HTTP authentication may return a verified tenant; `ConsoleTenantProvider`, `ScheduledTenantProvider`, and `Dispatcher::dispatch(..., ?TenantRef $tenant = null)` are independent entry contracts. Child operations, deferred messages, retry, lease recovery, and outbox dispatch inherit the parent tenant without override.

Every operation-owned row carries a nullable tenant type/id pair. Both columns are null for an explicit global operation or both are present for a tenant operation. Queries for a tenant-scoped resource include tenant and operation identity in one predicate. A same-operation tenant mismatch is an integrity failure; no protected payload is decoded to resolve it.

## Read authorization ordering

Application data readers accept an `OperationDataReadAuthorizationRequest` containing resource (`canonical_journal` or `outcome`), purpose, operation identity/type, current/origin actor, and current/origin tenant. `DenyOperationDataReadAuthorizer` is the default. `DefaultOperationJournalQuery` and `DefaultOperationOutcomeQuery` read a restricted clear subject, call the authorizer, and only then invoke the tenant-scoped protected adapter.

```text
clear subject (tenant + operation identity)
  -> OperationDataReadAuthorizer
  -> tenant-scoped row and BOPD bytes
  -> AAD validation and decode
  -> OperationJournalFound / OperationOutcomeFound
```

Unknown, deny, tenant mismatch, and retention-empty results are `Operation*Unavailable`. Storage, protection, decode, and integrity faults use resource-specific stable exceptions. Raw readers are not compiled into the application query binding; workers, status projection, replay, retention, idempotency recovery, and rotation use explicit infrastructure capability bindings.

## Protected storage schema

The nine `StoragePurpose` values are `journal_record`, `deferred_payload`, `deferred_context`, `outcome_payload`, `outbox_payload`, `outbox_context`, `dead_letter_reason`, `idempotency_response`, and `idempotency_result`. Their rows keep only the clear metadata required for lifecycle, claim, retention, or pre-decode authorization: operation identity/type, tenant pair, state, sequence/attempt, timestamps, schema version, and scope hashes. The envelope key ID is in the BOPD header, not a clear column. Recoverable application data is never a plaintext column.

The forward migrations refuse non-empty legacy protected tables before altering them. Empty legacy/current tables are migrated with the expected tenant pairs, identity constraints, indexes, and BOPD envelope columns. A failed guard leaves data, bytes, and constraints unchanged.

## BOPD v1 envelope and AAD

`BopdEnvelopeCodec` writes a binary envelope with magic `BOPD`, version `1`, algorithm `1` (XChaCha20-Poly1305), a bounded key identifier, 24-byte nonce, ciphertext length, ciphertext, and 16-byte authentication tag. The envelope does not carry AAD. `CanonicalAssociatedData` reconstructs version, algorithm, key ID, storage purpose, record identity, operation ID/type, schema version, and explicit tenant presence/type/id from `StorageProtectionContext`.

`StorageKeyProvider::activeKey()` is used only for writes. Reads parse the envelope key ID and call `StorageKeyProvider::key($keyId, $tenant, $purpose)`; the provider result must have the same ID. Unknown key, malformed header, invalid length, purpose/row/field/operation/tenant substitution, and tag failure are one safe protection failure. Plaintext fallback is forbidden. Key material, nonce, tag, ciphertext, SQL, and provider details are absent from exceptions, diagnostics, artifacts, and reports.

## Retention, replay, and secondary storage

Retention plans and purges use clear tenant/state/time/hold metadata and never decode an eligible envelope. Observer replay selects bounded rows, reconstructs the row tenant and purpose, decodes with the same AAD, then applies the current Sensitive Projection. Outbox payload/context, dead-letter reason, and idempotency response/result use separate purposes and row-bound AAD; decoded context is compared with clear operation, tenant, and origin metadata before claim or delivery.

## Rotation contract

`storage:protection:plan` is read-only. `storage:protection:rotate` is plan/dry-run unless `--confirm` is supplied with an explicit checkpoint, non-empty actor, and non-empty reason. Scope is purpose, optional tenant pair, old/new key IDs, batch (1–1000, default 100), and checkpoint. Confirmed rows use current digest, key ID, record identity, and tenant identity as CAS predicates. Checkpoint and audit counters advance in the same row transaction; contention is skipped rather than overwritten.

Output is restricted to purpose, key IDs, checkpoint, selected/rotated/skipped/failed, remaining counts, state, and the measured boundary. Exit `0` means success, `1` storage/protection/runtime failure, and `2` input/confirmation/configuration failure. Crash or SIGKILL leaves an auditable failed fingerprint and can resume from the same scope/checkpoint. Replica, backup, dead-letter, and retention-window old-key checks remain application operations; the Framework never deletes the old key.
