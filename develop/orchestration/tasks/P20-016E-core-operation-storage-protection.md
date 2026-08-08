# P20-016E: Core Operation Storage Protection

Status: Accepted

## Goal

Canonical Journal、Deferred Operation Payload／Context、Outcomeを必須Encrypted Envelopeへ切り替え、旧Plaintext Rowを自動変換せず安全に拒否する。

## Source of Truth

- `AGENTS.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/decisions/135-tenant-isolation-and-protected-operation-data.md`

## Dependencies

- P20-016B Accepted
- P20-016C Accepted
- P20-016D Accepted

## In Scope

- Canonical Journal Record Envelope
- Deferred OperationValue Payload／ExecutionContext Envelope
- Outcome Payload Envelope
- Application／Worker／Status／Replay／Retention Adapter配線
- Storage Purpose／Record／Operation／Tenant AAD Context
- Provider必須Bootstrap境界
- Journal／Operations／Outcomes旧Plaintext Guard Migration
- Empty旧Table／Fresh Database Upgrade
- Tamper、Wrong Tenant／Purpose／Row／Field、Unknown Key Integration
- Tombstone、Retention、Replay、Diagnostics Regression

## Out of Scope

- Outbox／Dead Letter／Idempotency Protection
- Rotation CLI
- Offline Plaintext Converter
- Public Guide／Website

## Files Allowed to Change

- `src/Journal/**`
- `src/Outcome/**`
- `src/Transport/PostgreSql/PostgreSqlCanonicalJournalStore.php`
- `src/Transport/PostgreSql/PostgreSqlJournal*.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperation*.php`
- `src/Transport/PostgreSql/PostgreSqlOutcome*.php`
- `src/Transport/PostgreSql/PostgreSqlStatus*.php`
- `src/Transport/PostgreSql/PostgreSqlObserverReplay*.php`
- `src/Transport/PostgreSql/PostgreSql*Retention*.php`
- `src/Internal/Execution/**`
- `src/Internal/Journal/**`
- `src/Internal/Status/**`
- `src/Internal/Replay/**`
- `src/Internal/Retention/**`
- `src/Internal/Diagnostics/**`
- `src/Internal/OperationData/PostgreSqlTenantScopedCanonicalJournalReader.php`
- `src/Internal/OperationData/PostgreSqlTenantScopedOutcomeReader.php`
- `src/Internal/Application/**`
- `src/Application/ApplicationBuilder.php`
- `src/Internal/Migration/**`
- `migrations/postgresql/**`
- Corresponding files under `tests/Journal/**`
- Corresponding files under `tests/Outcome/**`
- Corresponding files under `tests/Transport/PostgreSql/**`
- Corresponding files under `tests/Internal/Execution/**`
- Corresponding files under `tests/Internal/Journal/**`
- Corresponding files under `tests/Internal/Status/**`
- Corresponding files under `tests/Internal/Replay/**`
- Corresponding files under `tests/Internal/Retention/**`
- Corresponding files under `tests/Internal/Diagnostics/**`
- Corresponding files under `tests/Internal/Application/**`
- `tests/Internal/Application/OperationConsoleIntegrationTest.php`
- `tests/Internal/Application/ApplicationSeederConsoleIntegrationTest.php`
- `tests/Internal/Migration/DatabaseMigrationRunnerTest.php`
- `tests/Internal/Console/DatabaseMigrationCommandTest.php`
- `tests/Internal/Console/JournalObserverReplayCommandTest.php`
- `tests/Internal/Outbox/OutboxRelayRuntimeTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/MvpSampleEndToEndTest.php`
- `tests/Http/DeferredOperationRequestHandlerTest.php`
- `tests/Http/OperationRequestHandlerTest.php`
- `examples/quickstart/app/ApplicationServiceProvider.php`
- `examples/quickstart/app/Security/SampleStorageKeyProvider.php`
- `examples/quickstart/.env.example`
- Migration／worker／status／replay Consumer scripts
- `tests/Consumer/framework-package-export.sh`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-016E-core-operation-storage-protection.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- Protected AdapterはEnvelope以外をWrite／Readしない
- Storage Key Provider未登録をBootstrap Errorにし、固定Default Keyを生成しない
- AADはStorage Purpose、Record Identity、Operation ID／Type／Schema、Tenantを実Rowから構成する
- Clear Tenant／Origin Actorと復号後Contextの不一致をIntegrity Failureにする
- Status／Application QueryはAuthorization Allow後だけ復号する
- Retention Plan／PurgeはCiphertextをDecodeしない
- ReplayはBounded選択後、RowごとのTenant／Purposeで復号してSafe Projectionする
- Envelope FailureをPlaintext／別CodecへFallbackしない
- Migrationは対象旧Tableが非空なら変更前にSafe Errorで停止する
- Existing Rowを自動変換／Backfill／削除しない
- Empty旧Table／Fresh Databaseだけを新Contractへ進める
- Migration FailureはTransactionでRollbackし、部分変換を残さない
- Provider／Key／Ciphertext／Tenant Raw IDをLog／Error／Artifactへ出さない
- WorkerはReview前にCommitしない

## Acceptance Criteria

- [x] Journal／Deferred Payload／Context／OutcomeのDB bytesが`BOPD` Envelopeである
- [x] Raw OperationValue／Context／Outcome JSON断片がDBへ残らない
- [x] Inline／Deferred／Retry／Dead Letter前Lifecycleが通常どおり完走する
- [x] Status／Authorized Journal／Outcome QueryがAllow後だけ復号する
- [x] Wrong Tenant／Purpose／Row／Field、Unknown Key、Tag改ざんをSafe Failureにする
- [x] Tenant／Origin Subject不一致をDecode後のIntegrity Failureにする
- [x] Retention Tombstone／PurgeがCiphertext非Decodeで動作する
- [x] Observer ReplayがSafe Projectionだけを再配送する
- [x] ProviderなしBootstrapを拒否し、Key MaterialをArtifactへ保存しない
- [x] Non-empty旧Plaintext Schema UpgradeがData不変で停止する
- [x] Empty旧Schema／Fresh Migration／Package Exportが成功する
- [x] Full Suite／Consumer／Architecture Guardが成功する
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

`develop/orchestration/reports/P20-016E-core-operation-storage-protection.md`へ必須項目とDB Wire／Migration／Tamper Evidenceを記録する。
