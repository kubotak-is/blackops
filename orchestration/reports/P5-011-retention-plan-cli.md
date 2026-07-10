# P5-011: Retention Plan CLI

Status: Completed

## Summary

P5-011は完了。Retention Planを副作用なしで表示するSymfony Console Commandを追加した。

`blackops:retention:plan` は注入済み `RetentionPlanner` を呼び出し、Plan件数と候補一覧を表示する。Policy期間は4対象すべて明示Optionで受け取り、暗黙の既定値は持たない。

## Changed Files

- `src/Internal/Console/RetentionPlanCommand.php`
- `tests/Internal/Console/RetentionPlanCommandTest.php`
- `docs/internals/bootstrap.md`
- `docs/internals/retention-plan.md`
- `orchestration/tasks/P5-011-retention-plan-cli.md`
- `orchestration/reports/P5-011-retention-plan-cli.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- Command名は `blackops:retention:plan` とした。
- CommandはDB接続を生成しない。Composition Rootが `RetentionPlanner` を組み立てて注入する。
- Policy期間は `--transport-payload-days` / `--journal-days` / `--outcome-days` / `--dead-letter-days` で明示的に受け取る。
- 4対象のいずれかが未指定、または正の整数でない場合は失敗する。
- CommandはPlan表示だけを行い、PurgeやDB更新は実行しない。
- Policy Config File LoaderとPurge実行Commandは後続Taskで扱う。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter RetentionPlanCommandTest
Result: OK (2 tests, 9 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (434 tests, 1327 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 982 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] `blackops:retention:plan` Commandが定義される
- [x] 4対象すべてのRetention期間を明示Optionで受け取る
- [x] Commandが `RetentionPlanner` を呼び、Planを表示する
- [x] Plan CommandはDB更新やPurgeを実行しない
- [x] 必須Commandがすべて成功している

## Remaining Issues

- Purge実行Commandは未実装。後続Taskで扱う。
- DB接続生成とCommand登録はApplicationのComposition Rootで扱う。
- Policy Config File Loaderは未実装。後続Taskで扱う。
- Framework Maintenance Scheduler Workerは未実装。後続Taskで扱う。

## Suggested Next Action

P5-012としてRetention Purge Confirm CLIを実装する。
