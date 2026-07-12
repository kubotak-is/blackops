# P5-010: Retention Purge Service Facade

Status: Completed

## Summary

P5-010は完了。Retention CLIとScheduler Workerが呼び出すためのPostgreSQL Purge Service Facadeを追加した。

FacadeはPlanner、Transport Payload Tombstone Service、Dead Letter Retention Delete Serviceを順に呼び出し、Planと実行件数だけを持つPayload-freeなResultを返す。

## Changed Files

- `src/Core/Retention/RetentionPurgeResult.php`
- `src/Transport/PostgreSql/PostgreSqlRetentionPurgeService.php`
- `tests/Core/Retention/RetentionPurgeResultTest.php`
- `tests/Transport/PostgreSql/PostgreSqlRetentionPurgeServiceTest.php`
- `docs/internals/retention-plan.md`
- `docs/internals/retention-policy.md`
- `develop/orchestration/tasks/P5-010-retention-purge-service-facade.md`
- `develop/orchestration/reports/P5-010-retention-purge-service-facade.md`
- `develop/STATE.md`

## Decisions and Assumptions

- `RetentionPurgeResult` はPlan、Transport Payload Tombstone件数、Dead Letter削除件数を保持する。
- ResultはPayload、Context、Dead Letter本文、Journal本文を含めない。
- PostgreSQL Purge Service Facadeは既存Serviceを接続する薄い層とし、新しい削除SQLは持たない。
- FacadeはPlan生成後、Transport Payload Tombstone、Dead Letter Deleteの順に実行する。
- Canonical Journal、Outcome、System Log配送は後続Taskで接続する。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'RetentionPurgeResultTest|PostgreSqlRetentionPurgeServiceTest'
Result: OK (4 tests, 14 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (432 tests, 1318 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 959 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Purge ResultがPlanと実行件数を表現できる
- [x] PostgreSQL Purge ServiceがPlannerを呼ぶ
- [x] PostgreSQL Purge ServiceがTransport Payload Tombstoneを実行する
- [x] PostgreSQL Purge ServiceがDead Letter Deleteを実行する
- [x] ResultへPayloadやContextが含まれない
- [x] 必須Commandがすべて成功している

## Remaining Issues

- Canonical Journal削除、Outcome削除は未実装。後続Taskで扱う。
- System Log配送は未接続。後続Taskで扱う。
- Retention CLIとFramework Maintenance Scheduler Workerは未実装。後続Taskで扱う。

## Suggested Next Action

P5-011としてRetention CLIのPlan / Dry Run Commandを実装する。
