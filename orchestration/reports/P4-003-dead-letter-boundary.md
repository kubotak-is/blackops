# P4-003: Dead Letter Boundary

Status: Completed

## Summary

P4-003を完了した。

採用済みのDead Letter Schemaに基づき、PostgreSQL Dead Letters Table、`OperationDeadLetteredData`、Dead Letter予約処理、Runtime連携を実装した。Supervision PolicyがDead Letterを返した場合、Worker Runtimeは`attempt.failed`の後に`operation.dead_lettered`だけをTerminal Eventとして記録し、`operation.failed`は併記しない。

## Changed Files

- `TODO.md`
- `spec/03-execution.md`
- `spec/37-postgresql-table-layout.md`
- `docs/internals/deferred-transport-contract.md`
- `docs/internals/supervision-policy.md`
- `src/Internal/Execution/DeferredFailureSupervisor.php`
- `src/Internal/Journal/JournalRecordFactory.php`
- `src/Internal/Journal/JournalTerminalRecordFactory.php`
- `src/Journal/Data/OperationDeadLetteredData.php`
- `src/Transport/PostgreSql/PostgreSqlDeadLetteredReservation.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationLifecycleStore.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php`
- `src/Transport/PostgreSql/PostgreSqlFailureJournalDataCodec.php`
- `src/Transport/PostgreSql/PostgreSqlJournalDataCodec.php`
- `tests/Internal/Execution/DeferredWorkerRuntimeTest.php`
- `tests/Journal/JournalContractTest.php`
- `tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php`
- `tests/Transport/PostgreSql/PostgreSqlDeferredOperationSenderTest.php`
- `orchestration/tasks/P4-003-dead-letter-boundary.md`
- `orchestration/reports/P4-003-dead-letter-boundary.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- Dead Letters Tableは`operation_id`をPrimary Keyとし、最終Attempt、理由、移動時刻、作成時刻を保持する。
- `operation.dead_lettered` Journal Dataは`OperationDeadLetteredData`として実装し、Dead Letters Tableと同じ安全な理由情報、最終Attempt ID、最終Attempt番号、移動時刻を保持する。
- Dead Lettered Operationへ`operation.failed`は併記しない。
- Dead Letter時もOperations行は移動せず、`dead_lettered` Terminal Stateとして残す。
- Failure系Journal Data Codecは専用Codecへ分離し、PostgreSQL Journal Data Codecの責務肥大化を避けた。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'DeferredWorkerRuntimeTest|PostgreSqlCanonicalJournalStoreTest|PostgreSqlDeferredOperationSenderTest|JournalContractTest'
Result: OK (21 tests, 193 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (365 tests, 1041 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 771 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Dead Letters Tableの具体Schemaが確定している
- [x] Dead Letter Journal Dataの形が確定している
- [x] Supervision DecisionがDead Letterを返すと`operation.dead_lettered`だけがTerminal Eventとして記録される
- [x] Dead Letters Tableに調査用Recordが一対一で保存される
- [x] 必須Commandがすべて成功している

## Remaining Issues

- Manual Replayは未実装。
- Lease Expired Recovery、Heartbeat、Claim Settlementは未実装。
- Retention Purgeは未実装。

## Suggested Next Action

P4-004 Task Packetを作成し、Lease Expired RecoveryまたはHeartbeat / Settlementの実装へ進む。
