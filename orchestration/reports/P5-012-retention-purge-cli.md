# P5-012: Retention Purge CLI

Status: Completed

## Summary

P5-012は完了。Retention PurgeのDry RunとConfirm実行を行うSymfony Console Commandを追加した。

`blackops:retention:purge` は `--dry-run` でPlan表示のみ、`--confirm` で注入済み `RetentionPurgeService` を実行する。どちらも指定しない場合、または同時指定した場合は拒否する。

## Changed Files

- `src/Core/Retention/RetentionPurgeService.php`
- `src/Transport/PostgreSql/PostgreSqlRetentionPurgeService.php`
- `src/Internal/Console/RetentionPurgeCommand.php`
- `tests/Internal/Console/RetentionPurgeCommandTest.php`
- `docs/internals/bootstrap.md`
- `docs/internals/retention-plan.md`
- `orchestration/tasks/P5-012-retention-purge-cli.md`
- `orchestration/reports/P5-012-retention-purge-cli.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- Command名は `blackops:retention:purge` とした。
- `--dry-run` はPlan表示だけを行い、Purge Serviceを呼ばない。
- `--confirm` はPurge Serviceを実行する。
- `--dry-run` と `--confirm` は同時指定不可とした。
- どちらのModeも指定しない場合は拒否する。
- Policy期間は4対象すべて明示Optionで受け取る。
- Confirm実行では `--policy-ref` と `--actor` を必須にした。
- CommandはDB接続を生成しない。Composition Rootが `RetentionPlanner` と `RetentionPurgeService` を注入する。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'RetentionPurgeCommandTest|RetentionPurgeResultTest|PostgreSqlRetentionPurgeServiceTest'
Result: OK (8 tests, 28 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (438 tests, 1341 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1021 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] `blackops:retention:purge` Commandが定義される
- [x] `--dry-run` はPlan表示だけを行う
- [x] `--confirm` はPurge Serviceを実行する
- [x] 4対象すべてのRetention期間を明示Optionで受け取る
- [x] Confirm実行はPolicy ReferenceとActorを明示的に要求する
- [x] 必須Commandがすべて成功している

## Remaining Issues

- DB接続生成とCommand登録はApplicationのComposition Rootで扱う。
- Policy Config File Loaderは未実装。後続Taskで扱う。
- System Log配送は未接続。後続Taskで扱う。
- Framework Maintenance Scheduler Workerは未実装。後続Taskで扱う。

## Suggested Next Action

P5-013としてFramework Maintenance Scheduler WorkerのContractを実装する。
