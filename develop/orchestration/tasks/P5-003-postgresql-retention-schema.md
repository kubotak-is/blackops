# P5-003: PostgreSQL Retention Schema

Status: Completed

## Goal

PostgreSQL Transport SchemaへRetentionの基礎Schemaを追加し、Terminal OperationのPayload Tombstone化とRetention Hold保存に備える。

## In Scope

- Operations TableのPayload Tombstone用Column / Constraint
- `retention_holds` Table
- Operationsへの`ON DELETE RESTRICT` Foreign Key
- Migration shape test
- 内部Documentation更新

## Out of Scope

- Retention Hold Store
- Tombstone実行Service
- Purge Plan / Purge Service
- Purge Audit Table
- Retention CLI
- Framework Maintenance Scheduler Worker

## Relevant Specifications

- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/39-retention-runtime.md`
- `develop/decisions/044-data-retention-and-deletion.md`
- `develop/decisions/045-retention-mvp-scope.md`

## Files Allowed to Change

- `src/Transport/PostgreSql/**`
- `tests/Transport/PostgreSql/**`
- `docs/internal/**`
- `develop/orchestration/tasks/P5-003-postgresql-retention-schema.md`
- `develop/orchestration/reports/P5-003-postgresql-retention-schema.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- Cascade Deleteは使用しない
- 未完了OperationのPayload Tombstone化をSchema上も許可しない
- Purge AuditのRecord形はこのTaskで固定しない

## Acceptance Criteria

- [x] Operations Tableが`payload_purged_at`を持つ
- [x] Encoded Payload / ContextをTerminal TombstoneとしてNULL化できる
- [x] `retention_holds` Tableが設定・解除履歴Fieldを持つ
- [x] `retention_holds.operation_id` がOperationsへ`ON DELETE RESTRICT`で参照する
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

`develop/orchestration/reports/P5-003-postgresql-retention-schema.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
