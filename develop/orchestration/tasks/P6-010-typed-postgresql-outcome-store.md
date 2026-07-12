# P6-010: Typed PostgreSQL Outcome Store

Status: Accepted

## Goal

Typed Public Outcome Store ContractとPostgreSQL Adapterを実装し、Deferred Worker成功時にState／Canonical Journal／Outcomeを同一Transactionで保存し、Operation ID取得と独立Outcome Retentionを可能にする。

## In Scope

- Public `OutcomeRecord`、`OutcomeReader`、`OutcomeWriter`、`OutcomeStore`
- Public Outcome Store専用Exception
- UTC正規化されたCompletion時刻とImmutable Record
- PostgreSQL `outcomes` Table、Operation ID一対一、Operations FK、Completed At Index
- Adapter内部Outcome Type／Schema Version／Encoded Payload Codec
- PostgreSQL Outcome StoreのSave、Find、Duplicate／Unknown／Corrupt拒否
- Deferred Worker成功完了TransactionへのOutcome保存接続
- Outcome保存失敗時のState／Journal／Outcome原子Rollback
- Rejected／Failed／Retry／Dead Letter時にOutcomeを保存しない境界
- Retention PlannerのOutcome候補、Active Hold除外
- Outcome Row削除、Purge Audit、Purge Result件数
- Typed Outcome取得とRetention利用方法のDocumentation
- Public API Architecture Guard適合

## Out of Scope

- HTTP Outcome Endpoint
- Outcome Streaming／Pagination
- Outcome Upcaster Chain
- Encryption／Compression
- Doctrine Versioned Migration Command
- Canonical Journal Retention削除
- Dead Letter Replay UI
- Public Operation Codec変更

## Relevant Specifications and Decisions

- `develop/spec/12-mvp-scope.md`
- `develop/spec/29-handler-result-contract.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/37-postgresql-table-layout.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/39-retention-runtime.md`
- `develop/decisions/006-handler-and-outcome.md`
- `develop/decisions/043-postgresql-table-layout.md`
- `develop/decisions/044-data-retention-and-deletion.md`
- `develop/decisions/060-typed-outcome-store-contract.md`

## Files Allowed to Change

- `src/Outcome/**`
- `tests/Outcome/**`
- `src/Transport/PostgreSql/**`
- `tests/Transport/PostgreSql/**`
- `src/Internal/Execution/**`
- `tests/Internal/Execution/**`
- `src/Core/Retention/RetentionPurgeResult.php`
- `tests/Core/Retention/**`
- `deptrac.yaml`
- `mago.toml`
- `docs/guide/outcome-retrieval.md`
- `docs/guide/README.md`
- `docs/internals/outcome-store.md`
- `docs/internals/README.md`
- `docs/internals/worker-runtime.md`
- `docs/internals/retention-plan.md`
- `develop/TODO.md`
- `develop/decisions/060-typed-outcome-store-contract.md`
- `develop/orchestration/tasks/P6-010-typed-postgresql-outcome-store.md`
- `develop/orchestration/reports/P6-010-typed-postgresql-outcome-store.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Constraints

- Production Code／TestのCommentへSpec、Decision、Task、TODOの管理番号を書かない
- Public Outcome ContractへInternal、PostgreSQL、Doctrine、Codec型を露出しない
- Public Recordは復元済み`Outcome`を返し、Encoded Payloadを返さない
- `completedAt`をUTCへ正規化する
- Outcome Type／Schema Version／Payload検証をAdapter内部でFail Fastする
- Outcome保存はWorker Completion Transactionと同じDBAL Connectionへ参加する
- Outcome保存失敗後にCompleted StateまたはCompleted Journalを残さない
- Duplicate Saveで既存Outcomeを上書きしない
- Active Retention Hold中のOutcomeをPlan／Deleteしない
- Outcome削除とPurge Auditを同一Transactionで行う
- Existing Journal内Outcome保存を維持する

## Acceptance Criteria

- [x] Typed Public Outcome Record／Reader／Writer／Storeが追加される
- [x] Public Outcome APIがInternal／Library型を露出しない
- [x] PostgreSQL Schemaが一対一`outcomes` Tableを作成する
- [x] Outcome StoreがTyped Outcomeを保存・取得できる
- [x] Unknown Operationはnullを返す
- [x] Duplicate、Unknown Schema、Corrupt Payload、Non-Outcome型を専用Exceptionで拒否する
- [x] Worker成功時にState／Journal／Outcomeが同一TransactionでCommitされる
- [x] Outcome保存失敗時に完了Transaction全体がRollbackする
- [x] Rejected／Failed／Retry／Dead LetterはOutcome Rowを作らない
- [x] Retention Plannerが期限切れOutcomeを計画しActive Holdを除外する
- [x] Outcome PurgeがRowとAuditを同一Transactionで保存し件数を返す
- [x] Typed Outcome取得とRetentionがDocumentationへ記録される
- [x] 必須Commandがすべて成功する

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit --filter 'OutcomeRecord|OutcomeStore|DeferredWorkerRuntime|RetentionPlanner|RetentionPurge'
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P6-010-typed-postgresql-outcome-store.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
