# P5-003: PostgreSQL Retention Schema

Status: Completed

## Summary

PostgreSQL Retention Schemaを実装した。

Operations TableへTerminal Payload Tombstone用の`payload_purged_at`を追加し、`encoded_payload` / `encoded_context`をnullable化した。Schema Constraintで未完了OperationのTombstone化を拒否する。`retention_holds` Tableを追加し、OperationsへのForeign Keyは`ON DELETE RESTRICT`にした。

## Changed Files

- `orchestration/tasks/P5-003-postgresql-retention-schema.md`
- `orchestration/reports/P5-003-postgresql-retention-schema.md`
- `orchestration/STATE.md`
- `docs/internals/retention-hold.md`
- `docs/internals/retention-policy.md`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php`
- `tests/Transport/PostgreSql/PostgreSqlDeferredOperationSenderTest.php`

## Decisions and Assumptions

- P5-003はPayload Tombstone列とRetention Hold Tableに限定する。
- Purge Audit TableはRecord IDと詳細Schema判断を後続Taskへ分離する。
- Cascade Deleteは使用しない。
- Terminal State以外のPayload Tombstone化はSchema Constraintで拒否する。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter PostgreSqlDeferredOperationSenderTest
Result: OK (9 tests, 62 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (399 tests, 1177 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 854 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Operations Tableが`payload_purged_at`を持つ
- [x] Encoded Payload / ContextをTerminal TombstoneとしてNULL化できる
- [x] `retention_holds` Tableが設定・解除履歴Fieldを持つ
- [x] `retention_holds.operation_id` がOperationsへ`ON DELETE RESTRICT`で参照する
- [x] 必須Commandがすべて成功している

## Remaining Issues

- Retention Hold Storeは未実装。
- Purge Audit Tableは未実装。
- Tombstone / Purge Plan / Purge Serviceは未実装。
- Retention CLIとFramework Maintenance Scheduler Workerは未実装。

## Suggested Next Action

P5-004へ進み、Retention Hold StoreまたはPurge Audit Tableを実装する。
