# P20-016F: Reliability Storage Protection

Status: Ready

## Goal

Transactional Outbox Payload／Context、Dead Letter Reason、Idempotency Response／Resultを必須Encrypted Envelopeへ切り替え、Reliability LifecycleとTenant Isolationを維持する。

## Source of Truth

- `AGENTS.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/decisions/135-tenant-isolation-and-protected-operation-data.md`

## Dependencies

- P20-016E Accepted

## In Scope

- Outbox Payload／Context Envelope
- Dead Letter Reason Type／Message Envelope
- Idempotency Safe HTTP Response Snapshot Envelope
- Idempotency Typed Result／Rejection Projection Envelope
- Relay、Retry、Recovery、Duplicate Replay、Retention Adapter配線
- Reliability Tableの旧Plaintext Guard Migration
- Tenant／Purpose／Identity AAD
- Crash、Lease、Fencing、Duplicate、Retention Integration Evidence
- Community Board／Consumer Reliability Regression

## Out of Scope

- Canonical Journal／Deferred Operation／Outcome Protection
- Rotation CLI
- External Broker、Exactly Once
- Offline Plaintext Converter
- Public Guide／Website

## Files Allowed to Change

- `src/Outbox/**`
- `src/Idempotency/**`
- `src/Internal/Outbox/**`
- `src/Internal/Idempotency/**`
- `src/Internal/Application/ApplicationOutbox*.php`
- `src/Internal/Scheduler/OutboxRelayMaintenanceTask.php`
- `src/Internal/Console/Outbox*.php`
- `src/Transport/PostgreSql/PostgreSqlOutbox*.php`
- `src/Transport/PostgreSql/PostgreSqlDeadLetter*.php`
- `src/Transport/PostgreSql/PostgreSqlIdempotency*.php`
- `src/Transport/PostgreSql/PostgreSql*Retention*.php`
- `src/Internal/Migration/**`
- `migrations/postgresql/**`
- Corresponding files under `tests/Outbox/**`
- Corresponding files under `tests/Idempotency/**`
- Corresponding files under `tests/Internal/Outbox/**`
- Corresponding files under `tests/Internal/Idempotency/**`
- Corresponding files under `tests/Internal/Application/**`
- Corresponding files under `tests/Internal/Scheduler/**`
- Corresponding files under `tests/Internal/Console/**`
- Corresponding files under `tests/Transport/PostgreSql/**`
- Reliability／Community Board Consumer fixtures/scripts
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-016F-reliability-storage-protection.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- Outbox Child Tenantは親Tenantを継承し、Relay／Retryで再解決しない
- Outbox PayloadとContextは別Storage Purposeで暗号化する
- Dead Letter ReasonのType／Messageは一つのVersion付きProjectionとして暗号化する
- Idempotency Response／Resultは別Envelopeとし、Scope／Fingerprint／State等のClear Hash Metadataを維持する
- Duplicate LookupはCurrent Authentication／Authorization／Tenant Scope確認後に行う
- Idempotency ScopeへTenantを含め、別Tenantの同じKeyを衝突させない
- Relay／RecoveryはEnvelope Failureを再試行可能な外部Failureに偽装しない
- CLI／LogへPayload、Response、Result、Reason、Tenant Raw ID、Protection Detailを出さない
- RetentionはCiphertextをDecodeせずPlan／Purgeする
- Migrationは対象旧Tableが非空なら変更前に停止し、自動変換／削除しない
- Existing at-least-once、Fixed Identity、Lease／Fencing、Replay分離を維持する
- WorkerはReview前にCommitしない

## Acceptance Criteria

- [ ] Outbox Payload／Context、Dead Letter Reason、Idempotency Response／Resultが`BOPD` Envelopeである
- [ ] Raw JSON／Response Body／Reason Messageが対象Columnへ残らない
- [ ] Outbox Relay／Retry／Dead Letterが同じTenant／Operation Identityを維持する
- [ ] Idempotency DuplicateがTenant内で収束し、別Tenantでは独立する
- [ ] Wrong Tenant／Purpose／Record、Unknown Key、Tag改ざんをSafe Failureにする
- [ ] Authentication／Authorization／Tenant確認前にDuplicate Resultを復号しない
- [ ] Crash／Lease／Fencing／RecoveryがCiphertext更新で既存保証を失わない
- [ ] Retention／Legal Hold／PurgeがProtected Dataを露出しない
- [ ] Non-empty旧Reliability Schema UpgradeがData不変で停止する
- [ ] Empty旧Schema／Fresh Migration／Package Exportが成功する
- [ ] Community Board Reliability JourneyとFull Suiteが成功する
- [ ] Report／STATE／TODOを同期し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/community-board-digest.sh
bash tests/Consumer/community-board-post-comment.sh
bash tests/Consumer/framework-package-export.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Completion Report

`develop/orchestration/reports/P20-016F-reliability-storage-protection.md`へ必須項目とReliability／DB Wire／Migration Evidenceを記録する。
