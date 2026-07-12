# P5-006: PostgreSQL Purge Audit Store

Status: Completed

## Goal

Retention Purge Audit ContractをPostgreSQLへ保存できるようにする。

## In Scope

- `retention_purge_audits` Table
- PostgreSQL Purge Audit Store
- Store integration test
- 内部Documentation更新

## Out of Scope

- Tombstone実行Service
- Purge Plan / Purge Service
- System Log配送
- Retention CLI
- Framework Maintenance Scheduler Worker

## Relevant Specifications

- `develop/spec/39-retention-runtime.md`
- `develop/decisions/045-retention-mvp-scope.md`
- `develop/orchestration/tasks/P5-005-purge-audit-contract.md`

## Files Allowed to Change

- `src/Transport/PostgreSql/**`
- `tests/Transport/PostgreSql/**`
- `docs/internals/**`
- `develop/orchestration/tasks/P5-006-postgresql-purge-audit-store.md`
- `develop/orchestration/reports/P5-006-postgresql-purge-audit-store.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- Purge Audit TableへPayloadを保存しない
- Cascade Deleteは使用しない
- 削除対象自身のJournalへPurge Eventを追加しない
- System Log配送は後続Taskへ分離する

## Acceptance Criteria

- [x] `retention_purge_audits` TableがPayloadを含まない
- [x] Audit RecordのID、Operation ID、Target、件数、Policy、実行時刻、実行Actorを保存できる
- [x] Operation IDはOperationsへ`ON DELETE RESTRICT`で参照する
- [x] PostgreSQL Storeが `RetentionPurgeAuditPort` を実装する
- [x] 必須Commandがすべて成功している

## Required Commands

```bash
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

`develop/orchestration/reports/P5-006-postgresql-purge-audit-store.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
