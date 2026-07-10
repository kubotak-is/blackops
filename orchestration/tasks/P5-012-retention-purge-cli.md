# P5-012: Retention Purge CLI

Status: Completed

## Goal

Retention PurgeのDry RunとConfirm実行を行うSymfony Console Commandを追加する。

## In Scope

- `blackops:retention:purge` Command
- `--dry-run` と `--confirm`
- 明示Policy Option
- Policy Reference / Actor Option
- Unit Test
- 内部Documentation更新

## Out of Scope

- DB接続生成
- Policy Config File Loader
- Framework Maintenance Scheduler Worker
- System Log配送

## Relevant Specifications

- `spec/39-retention-runtime.md`
- `decisions/045-retention-mvp-scope.md`
- `orchestration/tasks/P5-010-retention-purge-service-facade.md`
- `orchestration/tasks/P5-011-retention-plan-cli.md`

## Files Allowed to Change

- `src/Core/Retention/**`
- `src/Transport/PostgreSql/**`
- `src/Internal/Console/**`
- `tests/Core/Retention/**`
- `tests/Transport/PostgreSql/**`
- `tests/Internal/Console/**`
- `docs/internals/**`
- `orchestration/tasks/P5-012-retention-purge-cli.md`
- `orchestration/reports/P5-012-retention-purge-cli.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommandはDB接続を生成しない
- Commandは注入済み `RetentionPlanner` / `RetentionPurgeService` だけを使う
- Policy期間は暗黙の既定値を持たず、4対象すべて明示Optionで受け取る
- `--dry-run` と `--confirm` は同時指定不可
- `--confirm` なしでPurgeを実行しない

## Acceptance Criteria

- [x] `blackops:retention:purge` Commandが定義される
- [x] `--dry-run` はPlan表示だけを行う
- [x] `--confirm` はPurge Serviceを実行する
- [x] 4対象すべてのRetention期間を明示Optionで受け取る
- [x] Confirm実行はPolicy ReferenceとActorを明示的に要求する
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

`orchestration/reports/P5-012-retention-purge-cli.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
