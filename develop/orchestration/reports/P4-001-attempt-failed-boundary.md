# P4-001: Attempt Failed Boundary Report

Status: Accepted

## Summary

Deferred WorkerのHandler例外を捕捉し、`attempt.failed` JournalとOperation State更新を同一Transactionで記録して、Operationを`supervising` Stateへ進めるFailure Boundaryを追加した。

Handler例外は記録後に再throwする。Retry判断はまだ行わず、Supervision Policy導入まで`AttemptFailedData.retryable`は既定で`false`として記録する。

## Changed Files

- `src/Journal/Data/AttemptFailedData.php`
- `src/Internal/Execution/DeferredWorkerRuntime.php`
- `src/Internal/Journal/JournalRecordFactory.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationLifecycleStore.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php`
- `src/Transport/PostgreSql/PostgreSqlFailureReservation.php`
- `src/Transport/PostgreSql/PostgreSqlJournalDataCodec.php`
- `src/Transport/PostgreSql/PostgreSqlJson.php`
- `tests/Internal/Execution/DeferredWorkerRuntimeTest.php`
- `docs/internal/deferred-transport-contract.md`
- `develop/orchestration/tasks/P4-001-attempt-failed-boundary.md`
- `develop/orchestration/reports/P4-001-attempt-failed-boundary.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Failure BoundaryではFencing Tokenを検証し、Stale ClaimからのFailure更新を拒否する。
- Handler例外時はStateを`supervising`へ進め、Lease情報を解除する。
- `attempt.failed` Dataは例外型、例外Message、Retryable判定を保持する。
- Retryable判定はSupervision Policy導入まで既定で`false`とする。
- Retry Scheduling、Operation Failed、Dead Letterは次Task以降へ残した。
- PostgreSQL State CHECK Constraintへ`supervising`を追加し、既存Table向けにConstraint再作成DDLを追加した。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'DeferredWorkerRuntimeTest|JournalRecordFactoryTest'
Result: OK (6 tests, 47 assertions). Runtime PHP 8.5.7.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (349 tests, 918 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 692 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] `AttemptFailedData`が追加される
- [x] Journal Data Codecが`AttemptFailedData`を永続化 / 復元できる
- [x] Handler例外時に`attempt.failed` Journalが保存される
- [x] Handler例外時にOperation Stateが`supervising`へ更新される
- [x] Handler例外時にSequenceが継続する
- [x] Handler例外は記録後に再throwされる
- [x] Fencing Token不一致時はFailure更新が拒否される
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- Supervision Policy Contractは未実装。
- Retry Schedulingは未実装。
- Operation Failed / Dead Letterは未実装。
- Heartbeatは未実装。
- Lease Expired Recoveryは未実装。
- Worker Loop / CLI Commandは未実装。

## Suggested Next Action

Supervision Policy Contract、Supervision Decision、Retry Scheduling Dataを確定して実装する。既定Backoff値や最大Attempt回数が未確定なら、実装前に判断を仰ぐ。
