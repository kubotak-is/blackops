# P5-004: PostgreSQL Retention Hold Store

Status: Completed

## Summary

PostgreSQL Retention Hold Storeを実装した。

`PostgreSqlRetentionHoldStore` が `RetentionHoldPort` を実装し、`place()` / `release()` / `activeFor()` をPostgreSQL `retention_holds` Tableへ接続した。Transport LayerからInternal IdentifierFactoryへ依存しないよう、Hold ID生成はTransport配下の小さなPortへ分離した。

## Changed Files

- `develop/orchestration/tasks/P5-004-postgresql-retention-hold-store.md`
- `develop/orchestration/reports/P5-004-postgresql-retention-hold-store.md`
- `develop/STATE.md`
- `docs/internal/retention-hold.md`
- `src/Transport/PostgreSql/PostgreSqlRetentionHoldIdGenerator.php`
- `src/Transport/PostgreSql/PostgreSqlRetentionHoldStore.php`
- `src/Transport/PostgreSql/SymfonyRetentionHoldIdGenerator.php`
- `tests/Transport/PostgreSql/PostgreSqlRetentionHoldStoreTest.php`

## Decisions and Assumptions

- Transport LayerからInternal IdentifierFactoryへ依存しない。
- Store専用のHold ID生成PortをTransport配下に置く。
- StoreはRetention Holdの永続化だけを扱い、PurgeやTombstoneは行わない。
- `release()` は未解除Holdだけを更新し、存在しないHoldまたは解除済みHoldを拒否する。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter PostgreSqlRetentionHoldStoreTest
Result: OK (5 tests, 23 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (404 tests, 1200 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 894 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] PostgreSQL Storeが`RetentionHoldPort`を実装する
- [x] `place()` がHoldを保存し、保存済みRecordを返す
- [x] `release()` が同一Hold Recordを解除済みに更新する
- [x] `activeFor()` が未解除Holdだけを返す
- [x] 必須Commandがすべて成功している

## Remaining Issues

- Purge Audit Tableは未実装。
- Tombstone / Purge Plan / Purge Serviceは未実装。
- Retention CLIとFramework Maintenance Scheduler Workerは未実装。

## Suggested Next Action

P5-005へ進み、Purge Audit TableまたはTombstone Serviceを実装する。
