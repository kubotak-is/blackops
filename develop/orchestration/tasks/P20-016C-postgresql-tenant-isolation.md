# P20-016C: PostgreSQL Tenant Isolation

Status: Accepted

## Goal

Operation-owned PostgreSQL RowへRestricted Clear Tenant／Origin Actor Subjectを追加し、Tenant不一致をProtected Blob Decode前に拒否できるQuery、Constraint、Index、Integrity Contractを実装する。

## Source of Truth

- `AGENTS.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/98-scheduled-application-operation.md`
- `develop/decisions/135-tenant-isolation-and-protected-operation-data.md`

## Dependencies

- P20-016A Accepted
- P20-016B Accepted

## In Scope

- Operations、Journal、Outcome、Idempotency、Outbox、Dead Letter、Schedule Occurrence、Retention EvidenceのTenant Column
- Status認可前Origin ActorRef Clear Subject
- Both-null／Both-present Check Constraint
- Tenant＋Record Identity Predicate／Index
- Adapter Write／Read／Claim／Retention／ReplayのTenant Metadata
- Idempotency ScopeへのTenant追加
- Same Operation Tenant Integrity
- Schema Helper、Doctrine Migration、Migration Inventory／Package Export
- Multi-tenant PostgreSQL Integration／Concurrency Test

## Out of Scope

- Protected Blob Encryption配線
- Application向けJournal／Outcome Read Authorizer
- Rotation CLI
- Database RLS、Schema／Database per Tenant
- Public Guide／Website

## Files Allowed to Change

- `src/Transport/PostgreSql/**`
- `src/Core/Execution/DeferredOperationMessage.php`
- `src/Core/Retention/RetentionPlanItem.php`
- `src/Core/Retention/RetentionPurgeAuditRecord.php`
- `src/Internal/Http/DeferredHttpOperationAcceptor.php`
- `src/Transport/InMemory/InMemoryOperationRecord.php`
- `src/Internal/Idempotency/**`
- `src/Internal/Execution/InlineDispatcher.php`
- `src/Internal/Execution/DeferredAcceptanceOrchestrator.php`
- `src/Internal/Outbox/**`
- `src/Internal/Scheduling/**`
- `src/Internal/Retention/**`
- `src/Internal/Replay/**`
- `src/Internal/Status/**`
- `src/Internal/Migration/**`
- `migrations/postgresql/**`
- Corresponding files under `tests/Transport/PostgreSql/**`
- `tests/Core/Execution/DeferredOperationMessageTest.php`
- Corresponding files under `tests/Core/Retention/**`
- Corresponding files under `tests/Internal/Http/**`
- Corresponding files under `tests/Transport/InMemory/**`
- Corresponding files under `tests/Internal/Idempotency/**`
- Corresponding files under `tests/Internal/Execution/**`
- Corresponding files under `tests/Internal/Outbox/**`
- Corresponding files under `tests/Internal/Scheduling/**`
- Corresponding files under `tests/Internal/Retention/**`
- Corresponding files under `tests/Internal/Replay/**`
- Corresponding files under `tests/Internal/Status/**`
- Corresponding files under `tests/Internal/Migration/**`
- Migration／package export Consumer scripts
- `tests/Integration/MvpSampleEndToEndTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Http/DeferredOperationRequestHandlerTest.php`
- `tests/Http/OperationRequestHandlerTest.php`
- `tests/Internal/Console/DatabaseMigrationCommandTest.php`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/community-board-foundation.sh`
- `tests/Consumer/community-board-digest.sh`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-016C-postgresql-tenant-isolation.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- `tenant_type`／`tenant_id`は両方nullまたは両方non-nullだけを許可する
- Origin ActorRefもType／IDの両方null／presentを同じConstraintで守る
- TenantありApplication QueryはTenantとOperation／Record Identityを同じSQL Predicateへ含める
- Tenant不一致RowのEncoded BlobをSELECT／Decodeしない
- 同じOperation IDのTenant不一致をIntegrity Failureにする
- TenantなしはGlobal／Single-tenant Operationであり、Legacy推測をしない
- Status SubjectはEncoded Journal JSON Projectionを廃止し、Clear Tenant／Origin Actorだけを読む
- Cross-tenant Worker／Retention／ReplayはBounded Infrastructure Queryとして明示する
- Idempotency Scope HashへTenant presence／type／idをCanonical Boundary付きで含める
- Tenant Raw IDをLog／Observed Journal／Default CLIへ出さない
- MigrationはExisting Dataを自動Backfill／推測／削除しない
- New Dependencyを追加しない
- WorkerはReview前にCommitしない

## Acceptance Criteria

- [x] Schema HelperとMigrationが全対象TableのTenant／Origin Subject Contractを一致して作る
- [x] Fresh Migration、Current Schema、Dry-run、Package Exportが同期する
- [x] Both-null／partial Tenant／partial Actor Constraint MatrixをDatabaseで検証する
- [x] Tenant AからTenant BのOperation／Journal／Outcome／Idempotency／Outbox／Dead Letterを取得できない
- [x] Tenant不一致時にEncoded Blob Decoderが呼ばれない
- [x] Same Operation Tenant不一致がSafe Integrity Failureになる
- [x] Status Subjectが`convert_from(encoded_...)::jsonb`へ依存しない
- [x] Worker／Retention／ReplayがRowごとのTenantを混同しない
- [x] Idempotency KeyがTenantごとに独立Scopeになる
- [x] Scheduled実行候補だけTenantを保持し、Skip Contractを維持する
- [x] Existing Global／Single-tenant JourneyとFull Suiteを維持する
- [x] Report／STATE／TODOを同期し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/framework-package-export.sh
! rg -n 'convert_from\\([^)]*encoded_(record|payload|context)' src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Completion Report

`develop/orchestration/reports/P20-016C-postgresql-tenant-isolation.md`へ必須項目とCross-tenant／Migration Evidenceを記録する。
