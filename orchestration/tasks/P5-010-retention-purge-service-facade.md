# P5-010: Retention Purge Service Facade

Status: Completed

## Goal

Retention CLIとScheduler Workerが呼び出すためのPurge Service Facadeを追加する。

## In Scope

- Purge Result Contract
- PostgreSQL Purge Service Facade
- Planner / Transport Payload Tombstone / Dead Letter Deleteの接続
- Integration Test
- 内部Documentation更新

## Out of Scope

- Canonical Journal削除
- Outcome削除
- System Log配送
- Retention CLI
- Framework Maintenance Scheduler Worker

## Relevant Specifications

- `spec/39-retention-runtime.md`
- `decisions/045-retention-mvp-scope.md`
- `orchestration/tasks/P5-007-retention-plan-contract-and-postgresql-planner.md`
- `orchestration/tasks/P5-008-postgresql-transport-payload-tombstone.md`
- `orchestration/tasks/P5-009-postgresql-dead-letter-retention-delete.md`

## Files Allowed to Change

- `src/Core/Retention/**`
- `src/Transport/PostgreSql/**`
- `tests/Core/Retention/**`
- `tests/Transport/PostgreSql/**`
- `docs/internals/**`
- `orchestration/tasks/P5-010-retention-purge-service-facade.md`
- `orchestration/reports/P5-010-retention-purge-service-facade.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- FacadeはPlan生成と既存Purge実行Serviceの接続だけを行う
- PayloadやContextをResultへ含めない
- System Log配送は後続Taskへ分離する

## Acceptance Criteria

- [x] Purge ResultがPlanと実行件数を表現できる
- [x] PostgreSQL Purge ServiceがPlannerを呼ぶ
- [x] PostgreSQL Purge ServiceがTransport Payload Tombstoneを実行する
- [x] PostgreSQL Purge ServiceがDead Letter Deleteを実行する
- [x] ResultへPayloadやContextが含まれない
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

`orchestration/reports/P5-010-retention-purge-service-facade.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
