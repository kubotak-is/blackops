# P5-011: Retention Plan CLI

Status: Completed

## Goal

Retention Planを副作用なしで表示するSymfony Console Commandを追加する。

## In Scope

- `blackops:retention:plan` Command
- 明示Policy Option
- Plan件数と候補一覧のText出力
- Unit Test
- 内部Documentation更新

## Out of Scope

- Purge実行Command
- DB接続生成
- Policy Config File Loader
- Framework Maintenance Scheduler Worker

## Relevant Specifications

- `spec/39-retention-runtime.md`
- `decisions/045-retention-mvp-scope.md`
- `orchestration/tasks/P5-007-retention-plan-contract-and-postgresql-planner.md`

## Files Allowed to Change

- `src/Internal/Console/**`
- `tests/Internal/Console/**`
- `docs/internals/**`
- `orchestration/tasks/P5-011-retention-plan-cli.md`
- `orchestration/reports/P5-011-retention-plan-cli.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommandはDB接続を生成しない
- Commandは注入済み `RetentionPlanner` だけを使う
- Policy期間は暗黙の既定値を持たず、4対象すべて明示Optionで受け取る
- Plan CommandはPurgeを実行しない

## Acceptance Criteria

- [x] `blackops:retention:plan` Commandが定義される
- [x] 4対象すべてのRetention期間を明示Optionで受け取る
- [x] Commandが `RetentionPlanner` を呼び、Planを表示する
- [x] Plan CommandはDB更新やPurgeを実行しない
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

`orchestration/reports/P5-011-retention-plan-cli.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
