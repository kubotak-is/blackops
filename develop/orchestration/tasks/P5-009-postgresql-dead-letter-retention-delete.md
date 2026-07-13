# P5-009: PostgreSQL Dead Letter Retention Delete

Status: Completed

## Goal

Retention Planに基づき、PostgreSQL Dead Letter Recordを安全に削除できるようにする。

## In Scope

- PostgreSQL Dead Letter Retention Delete Service
- Purge Audit書き込み連携
- 実行時のActive Hold再確認
- Integration Test
- 内部Documentation更新

## Out of Scope

- Transport Payload Tombstone
- Canonical Journal削除
- Outcome削除
- Operations行削除
- System Log配送
- Retention CLI
- Framework Maintenance Scheduler Worker

## Relevant Specifications

- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/39-retention-runtime.md`
- `develop/decisions/044-data-retention-and-deletion.md`
- `develop/decisions/045-retention-mvp-scope.md`
- `develop/orchestration/tasks/P5-007-retention-plan-contract-and-postgresql-planner.md`

## Files Allowed to Change

- `src/Transport/PostgreSql/**`
- `tests/Transport/PostgreSql/**`
- `docs/internal/**`
- `develop/orchestration/tasks/P5-009-postgresql-dead-letter-retention-delete.md`
- `develop/orchestration/reports/P5-009-postgresql-dead-letter-retention-delete.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- Dead Letter削除ではOperations行を削除しない
- Active Hold中のOperationは実行時にも除外する
- 成功した削除だけPurge Auditへ記録する
- 削除対象自身のJournalへPurge Eventを追加しない

## Acceptance Criteria

- [x] Plan内のDead Letter候補を削除できる
- [x] Plan内の非Dead Letter候補は無視される
- [x] Active Holdなしを実行時にも再確認する
- [x] 成功した削除についてPurge Auditが記録される
- [x] Operations行は削除されない
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

`develop/orchestration/reports/P5-009-postgresql-dead-letter-retention-delete.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
