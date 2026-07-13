# P5-007: Retention Plan Contract and PostgreSQL Planner

Status: Completed

## Goal

Retention PurgeのDry Runと実行Serviceが共有するPlan Contractを追加し、PostgreSQLから安全に削除候補を抽出できるようにする。

## In Scope

- Retention Plan Item
- Retention Plan
- Retention Planner Port
- PostgreSQL Planner
- Hold中Operationの除外
- Unit Test / PostgreSQL integration test
- 内部Documentation更新

## Out of Scope

- Tombstone実行Service
- 実削除Service
- Purge Audit書き込み連携
- System Log配送
- Retention CLI
- Framework Maintenance Scheduler Worker

## Relevant Specifications

- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/39-retention-runtime.md`
- `develop/decisions/044-data-retention-and-deletion.md`
- `develop/decisions/045-retention-mvp-scope.md`

## Files Allowed to Change

- `src/Core/Retention/**`
- `src/Transport/PostgreSql/**`
- `tests/Core/Retention/**`
- `tests/Transport/PostgreSql/**`
- `docs/internal/**`
- `develop/orchestration/tasks/P5-007-retention-plan-contract-and-postgresql-planner.md`
- `develop/orchestration/reports/P5-007-retention-plan-contract-and-postgresql-planner.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- PlanはPayloadを含めない
- Plannerは削除やTombstone化を行わない
- Hold中のOperationは候補から除外する
- CLIとSchedulerは後続Taskへ分離する

## Acceptance Criteria

- [x] Retention Plan ContractがPublic APIとして定義される
- [x] Plan ItemがOperation ID、Target、基準時刻、期限時刻を表現できる
- [x] PostgreSQL PlannerがTransport Payload Tombstone候補を抽出できる
- [x] PostgreSQL PlannerがDead Letter候補を抽出できる
- [x] Hold中Operationが候補から除外される
- [x] PlannerはDry Run用途として副作用を持たない
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

`develop/orchestration/reports/P5-007-retention-plan-contract-and-postgresql-planner.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
