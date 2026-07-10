# P4-005: Lease Expired Recovery

Status: Completed

## Summary

Lease期限切れのRunning Operationを検出し、保存済みCurrent Attempt情報から前Attemptを復元して`lease_expired`の`attempt.failed`として閉じるRecoveryを実装した。

Recovery後は既存のSupervision Policyへ接続し、Policy判断に基づいてRetry / Fail / Dead Letterへ遷移する。既定の`lease_expired`はRetryableな内部例外として扱われるため、既定PolicyではRetryへ進む。

## Changed Files

- `docs/internals/deferred-transport-contract.md`
- `orchestration/STATE.md`
- `orchestration/tasks/P4-005-lease-expired-recovery.md`
- `orchestration/reports/P4-005-lease-expired-recovery.md`
- `src/Internal/Execution/DeferredLeaseExpiredRecovery.php`
- `src/Internal/Execution/DeferredWorkerRuntime.php`
- `src/Internal/Execution/LeaseExpiredException.php`
- `src/Transport/PostgreSql/PostgreSqlDeadLetterStore.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationLifecycleSql.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationLifecycleStore.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php`
- `src/Transport/PostgreSql/PostgreSqlLeaseExpiredRecoveryStore.php`
- `src/Transport/PostgreSql/PostgreSqlLeaseExpiredReservation.php`
- `tests/Internal/Execution/DeferredWorkerRuntimeTest.php`
- `tests/Transport/PostgreSql/PostgreSqlDeferredOperationSenderTest.php`

## Decisions and Assumptions

- 失効Running Attemptの復元方式は、Operations Tableへ`current_attempt_id`と`current_attempt_started_at`を保存する方式で確定した。
- RuntimeはAttempt Context生成後、Handler呼び出し前にCurrent Attempt情報をOperations Rowへ記録する。
- Lease Expired RecoveryはCurrent Attempt情報があるRunning Operationのみを対象にする。
- Recovery予約時はStateを`supervising`へ進め、Lease情報とCurrent Attempt情報を解除する。
- `lease_expired`はFramework内部のRetryableな例外として扱い、Supervision Policyへ通常Failureと同じ形で渡す。
- Dead Letter挿入処理とLease Expired予約処理は、Lifecycle Storeの責務過多を避けるため専用Storeへ分離した。
- Attempt開始前にCrashしたClaimはAttemptとして数えない。自動復旧は後続Taskへ残す。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'DeferredWorkerRuntimeTest|PostgreSqlDeferredOperationSenderTest'
Result: OK (11 tests, 142 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (369 tests, 1076 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 841 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] 失効Running AttemptのAttempt ID復元方式が確定している
- [x] Lease期限切れRunning Operationが`attempt.failed`として閉じられる
- [x] `lease_expired`が安全な構造化Errorとして保存される
- [x] Supervision Policyの判断に基づきRetry / Fail / Dead Letterへ遷移する
- [x] 必須Commandがすべて成功している

## Remaining Issues

Attempt開始前Crashの自動復旧は未実装。Current Attempt情報がないRunning Operationは、今回のLease Expired Recovery対象外とし、後続TaskでClaim再投入または失敗遷移の扱いを決める。

## Suggested Next Action

P4-006へ進み、Phase 4の次Taskを開始する。
