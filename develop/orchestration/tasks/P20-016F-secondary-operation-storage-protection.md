# P20-016F: Secondary Operation Storage Protection

Status: Accepted

## Goal

Transactional Outbox Payload／Context、Dead Letter Reason、Idempotency Response／Resultを必須BOPD Envelopeへ切り替え、旧Plaintext Rowを自動変換せず安全に拒否する。

## Source of Truth

- `AGENTS.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/decisions/135-tenant-isolation-and-protected-operation-data.md`
- `develop/decisions/109-phase-18-idempotency-and-outbox.md`

## Dependencies

- P20-016B Accepted
- P20-016C Accepted
- P20-016D Accepted
- P20-016E Accepted and committed as `190d42a`

## In Scope

- Transactional Outbox Child Payload／ExecutionContext Envelope
- Deferred Dead Letter Reason Type／Message Envelope
- Idempotency Safe HTTP Response Snapshot Envelope
- Idempotency Typed Result／Rejection／Internal Failure Projection Envelope
- Operation Type／Application Schema／Tenantを含む実Row AAD Context
- Application／HTTP／Worker／Outbox Relay Adapter配線
- Outbox／Dead Letter／Idempotency旧Plaintext Guard Migration
- Empty旧Table／Fresh Database Upgrade
- Relay／Retry／Dead Letter再開、Idempotency Duplicate／Recovery、Retention／Status／Diagnostics Regression
- Wrong Tenant／Purpose／Row／Field、Unknown Key、Tag改ざんIntegration

## Out of Scope

- Canonical Journal、Deferred Operation、Outcomeの再設計
- Outbox Dead Letter Retry AuditのActor／Reason Code保護
- Failure Fingerprint、Scope Hash、Key Hash、Fingerprint Hashの暗号化
- Storage Key Rotation CLI／Audit／Checkpoint
- Offline Plaintext Converter
- Public Guide／Website／Community Board移行

## Files Allowed to Change

- `src/Internal/Idempotency/**`
- `src/Internal/Outbox/**`
- `src/Internal/Application/**`
- `src/Internal/Diagnostics/OperationDiagnosticsQuery.php`
- `src/Internal/Execution/InlineDispatcher.php`
- `src/Internal/Execution/DeferredAcceptanceOrchestrator.php`
- `src/Transport/PostgreSql/PostgreSqlOutbox*.php`
- `src/Transport/PostgreSql/PostgreSqlDeadLetter*.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationLifecycleStore.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php`
- `src/Transport/PostgreSql/PostgreSqlDiagnosticsReader.php`
- `src/Transport/PostgreSql/PostgreSqlIdempotencySchema.php`
- `migrations/postgresql/**`
- Corresponding files under `tests/Internal/Idempotency/**`
- Corresponding files under `tests/Internal/Outbox/**`
- Corresponding files under `tests/Internal/Application/**`
- Corresponding files under `tests/Transport/PostgreSql/**`
- `tests/Internal/Execution/DeferredWorkerRuntimeTest.php`
- `tests/Internal/Execution/InlineDispatcherTest.php`
- `tests/Internal/Execution/DeferredAcceptanceOrchestratorTest.php`
- `tests/Internal/Migration/DatabaseMigrationRunnerTest.php`
- `tests/Internal/Console/DatabaseMigrationCommandTest.php`
- `tests/Internal/Console/OutboxRelayCommandsTest.php`
- `tests/Internal/Scheduler/OutboxRelayMaintenanceTaskTest.php`
- `tests/Http/OperationRequestHandlerTest.php`
- `tests/Http/DeferredOperationRequestHandlerTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/MvpSampleEndToEndTest.php`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/framework-package-export.sh`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-016F-secondary-operation-storage-protection.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- Protected AdapterはEnvelope以外をWrite／Readせず、Plaintext／別CodecへFallbackしない
- New WriteはApplicationのmandatory `StorageKeyProvider`と既存`BopdEnvelopeCodec`を使い、固定Default Keyを生成しない
- Outbox Payload／Contextは別Purposeで保護し、Outbox `record_id`、Child Operation ID／Type／Schema、Row TenantへBindingする
- Outbox ClaimはBounded Row選択後にRowごとのTenant／AADで復号し、Decoded ContextのOperation／Tenant／Origin SubjectをClear Metadataと照合する
- Dead Letter Reason Type／Messageは一つのVersion付きProjectionとして保護し、Clear ColumnへReason Detailを残さない。Status／Diagnostics／RetentionはReason EnvelopeをDecodeしない
- Idempotency ResponseはStatus／Allowlist Header／Safe Bodyを一つのVersion付きProjectionとして保護する
- Idempotency ResultはCompleted Outcome、Rejection Category／Code、Internal Failure Markerを一つのVersion付きProjectionとして保護する
- Idempotency RowへOperation TypeとApplication Schema VersionをRestricted Clear Metadataとして保持し、Canonical Scope Identity、Operation ID、TenantとともにAADへBindingする
- Processing RowはResponse／Result Envelopeなしを許可する。Terminal Snapshotが存在する場合は対応Envelopeを必須にし、旧個別Plaintext Fieldを残さない
- Protected `bytea` Writeは任意Binaryを安全にBindし、Schemaはnon-null Envelopeへ`BOPD` Prefix Checkを持つ
- Duplicate／Recovery ReadはClear Scope／Fingerprint／Tenant Integrityを確認してから必要なEnvelopeだけを復号する
- Migrationは新しい`Version20260808010000`で、対象旧Outbox／Dead Letter／Idempotency Tableが非空なら変更前にSafe Errorで停止する
- Existing Rowを自動変換／Backfill／削除しない。Migration FailureはTransactionでRollbackし、Row／Encoded Bytes／Schemaを部分変更しない
- Empty旧Table／Fresh Databaseだけを新Contractへ進める
- Retention Plan／Purge、Status Subject／Detail、Dead Letter存在確認はCiphertextをDecodeしない
- Provider／Key／Ciphertext／Nonce／Tag／Tenant Raw ID／Reason／Response BodyをLog、Error、CLI、Artifact、Reportへ出さない
- WorkerはReview前にCommitしない

## Acceptance Criteria

- [x] Outbox Payload／Context、Dead Letter Reason、Idempotency Response／ResultのDB bytesが`BOPD` Envelopeである
- [x] Raw Child OperationValue／Context、Reason Type／Message、Response Header／Body、Outcome／Rejection断片がDBへ残らない
- [x] Outbox Register／Claim／Relay／Retry／Dead Letter再開が同じRecord／Operation Identityで完走する
- [x] Idempotency Inline／Deferred Terminal Replay、Conflict／In-progress／Expired、Recovery Contractが維持される
- [x] Wrong Tenant／Purpose／Row／Field、Unknown Key、Tag改ざんをSafe Failureにする
- [x] Outbox Clear Tenant／Origin SubjectとDecoded Context不一致をIntegrity Failureにする
- [x] Retention／Status／DiagnosticsがDead Letter／Idempotency／Outbox Ciphertextを不要にDecodeしない
- [x] ProviderなしBootstrapを拒否し、Key MaterialをArtifactへ保存しない
- [x] Non-empty旧Plaintext Schema Upgradeが全対象TableでData／Schema不変のまま停止する
- [x] Empty旧Schema／Fresh Migration／Package Inventoryが成功する
- [x] Focused Suite／Full Suite／Consumer／Architecture Guardが成功する
- [x] Report／STATE／TODOを同期し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/framework-package-export.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Completion Report

`develop/orchestration/reports/P20-016F-secondary-operation-storage-protection.md`へ必須項目、DB Wire、Migration Guard、Tamper／Tenant／Non-decode Evidenceを記録する。
