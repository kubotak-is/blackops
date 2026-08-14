# P20-015: Tenant Isolation and Protected Operation Data Contract

Status: Accepted

## Goal

Phase 20のJournal／Outcome参照制御、Tenant分離、暗号化CapabilityをProduction Code変更前に確定する。

Tenant Identity／伝播、PostgreSQL Clear Subject、Status／Journal／Outcome Read Policy、Protected Storage Scope、Authenticated Encryption、旧Plaintext Contractの拒否境界、Key Rotationを一つのDecisionへ整理し、実装可能なSliceへ分割する。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/009-execution-context.md`
- `develop/decisions/031-sensitive-projection.md`
- `develop/decisions/041-postgresql-transport-schema.md`
- `develop/decisions/044-data-retention-and-deletion.md`
- `develop/decisions/097-phase-14-operation-diagnostics.md`
- `develop/decisions/102-phase-16-deferred-status-and-outcome-api.md`
- `develop/decisions/109-phase-18-idempotency-and-outbox.md`
- `develop/decisions/134-scheduled-application-operation.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/98-scheduled-application-operation.md`
- Current ExecutionContext／Entry Adapter／Status Query／Journal／Outcome／Transport／Outbox／Idempotency／Retention／Replay Source and Tests

## In Scope

- Current Runtime／Persistence／Public PortのRead-only Design Audit
- TenantRefとExecutionContext Optional Tenant候補
- HTTP／Console／Scheduled／DispatcherのTenant Source
- Child／Deferred／Retry／Recovery／Replay Tenant Propagation
- PostgreSQL Tenant Metadata、Query、Constraint、Integrity
- Status Subjectの認可前Clear Projection
- Canonical Journal／OutcomeのApplication Read Authorization
- Infrastructure Raw PortとEnd-user Query Portの分離
- Journal／Transport／Outcome／Outbox／Dead Letter Reason／Idempotency Response／ResultのProtection Scope
- Authenticated Encryption Envelope、AAD、Algorithm、Key Provider
- Required Protection、Breaking Upgrade、Corrupt Envelope境界
- Key Rotation、Plan／Rotate CLI、Audit、Checkpoint／Resume
- D135 Question、Recommendation、User回答
- Decision後のSpecification／Production Task分割
- TODO／STATE／Report／Decision Index同期

## Out of Scope

- Production Code、Test、Migration、Dependency、Public API実装
- Tenant Directory、Membership、Role、Permission
- Database／Schema per TenantまたはRow Level Securityの強制
- KMS／Secret Manager Vendor Adapter
- Admin UI、Public Canonical Raw HTTP Endpoint
- OpenTelemetry Adapter／Remote Exporter
- Commit、Push、PR、External Deploy

## Files Allowed to Change

- `develop/decisions/135-tenant-isolation-and-protected-operation-data.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-015-tenant-isolation-and-protected-operation-data-contract.md`
- `develop/orchestration/tasks/P20-016A-tenant-context-and-propagation.md`
- `develop/orchestration/tasks/P20-016B-storage-protection-core.md`
- `develop/orchestration/tasks/P20-016C-postgresql-tenant-isolation.md`
- `develop/orchestration/tasks/P20-016D-operation-data-read-authorization.md`
- `develop/orchestration/tasks/P20-016E-core-operation-storage-protection.md`
- `develop/orchestration/tasks/P20-016F-reliability-storage-protection.md`
- `develop/orchestration/tasks/P20-016G-storage-key-rotation.md`
- `develop/orchestration/tasks/P20-016H-tenant-protection-documentation.md`

User回答後に確定Specificationを追加する。Production Codeまたは上記以外の変更が必要なら実装を広げず、Reportへ記録する。

## Audit Questions

1. TenantをActor／Valueと分離したOptional Contextとして固定するか。
2. HTTP／Console／Schedule／Direct DispatchでTenantを誰が確定し、Child／Retry／Replayへどう伝播するか。
3. 認可前にEncrypted BlobをDecodeせずTenant不一致を拒否できるか。
4. Existing Status AuthorizerとRaw Journal／Outcome Readerをどう分離するか。
5. どのFramework-owned Blobを保護し、どのMetadataをQuery用Clear Columnに残すか。
6. Algorithm、Nonce、AAD、Envelope、Key Materialの責任をFramework／Applicationでどう分けるか。
7. Experimental v1の旧Plaintext Contractを暗黙互換せず、既存Dataを無断変更せずにFail-closedで拒否できるか。
8. Per-tenant／Purpose Key、Rotation、Audit、Checkpoint、Crash Recoveryをどう扱うか。

## Acceptance Criteria

- [x] Current Tenant未実装境界とStatus既定DenyをSourceで確認する
- [x] Raw Journal／Outcome PortとEnd-user Queryの差を示す
- [x] SQL JSON ProjectionとEncrypted BlobのConflictを示す
- [x] Tenant Identity、Entry Source、Propagation候補をD135へ示す
- [x] PostgreSQL Clear SubjectとTenant Integrity候補を示す
- [x] Journal／Outcome Read Authorization候補を示す
- [x] Protected Storage Scope、AEAD、AAD、Key Provider候補を示す
- [x] Required Protection、Breaking Upgrade Guard、Rotation候補を示す
- [x] User回答をD135へ反映し、D135をDecidedにする
- [x] 確定SpecificationとProduction Task Packetへ分割する
- [x] Production Codeを変更しない
- [x] STATE／TODO／Decision Index／Reportを同期する

## Required Commands

```bash
rg -n "Tenant|OperationStatusAuthorizer|CanonicalJournalReader|OutcomeReader|encoded_|convert_from|Idempotency|Outbox" src tests migrations/postgresql develop/spec develop/decisions
git diff --check
```

Production Code／Testは変更しないため、Contract確定段階ではExisting Suiteを再実行しない。次の確定SpecificationでMigration、Crypto Known-answer、Tamper、Tenant Isolation、Crash／RotationのTest Matrixを定義する。

## Completion Report

`develop/orchestration/reports/P20-015-tenant-isolation-and-protected-operation-data-contract.md`へSummary、Evidence Inventory、Decision Questions、Recommended Contract、Security／Compatibility Boundaries、Commands and Results、Remaining Issues、Suggested Next Actionを記録する。
