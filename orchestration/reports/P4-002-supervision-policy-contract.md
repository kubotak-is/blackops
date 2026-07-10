# P4-002: Supervision Policy Contract

Status: Completed

## Summary

P4-002を完了した。

承認されたRetry既定値に基づき、Supervision Policy Contract、Supervision Decision、Retry Scheduling Dataを実装した。Deferred Worker RuntimeはHandler例外を`attempt.failed`として記録した後、Supervision Policyの判断に基づいて`attempt.retry_scheduled`または`operation.failed`へ遷移する。

Dead Letter Transportはまだ未実装のため、Retry不能または最大Attempt到達時は承認方針どおり`operation.failed`へ遷移させる。

## Changed Files

- `TODO.md`
- `spec/03-execution.md`
- `docs/internals/deferred-transport-contract.md`
- `docs/internals/supervision-policy.md`
- `src/Core/Supervision/SupervisionPolicy.php`
- `src/Core/Supervision/SupervisionAction.php`
- `src/Core/Supervision/SupervisionDecision.php`
- `src/Core/Supervision/RetryableException.php`
- `src/Core/Supervision/ExponentialBackoffSupervisionPolicy.php`
- `src/Internal/Execution/DeferredFailureSupervisor.php`
- `src/Internal/Execution/DeferredWorkerRuntime.php`
- `src/Internal/Execution/DeferredWorkerRuntimeServices.php`
- `src/Internal/Journal/JournalRecordBuilder.php`
- `src/Internal/Journal/JournalRecordFactory.php`
- `src/Journal/Data/AttemptRetryScheduledData.php`
- `src/Journal/Data/OperationFailedData.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationLifecycleStore.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationLifecycleSql.php`
- `src/Transport/PostgreSql/PostgreSqlJournalDataCodec.php`
- `src/Transport/PostgreSql/PostgreSqlOperationFailedReservation.php`
- `src/Transport/PostgreSql/PostgreSqlRetryScheduledReservation.php`
- `src/Transport/PostgreSql/PostgreSqlTerminalTransition.php`
- `tests/Core/Supervision/SupervisionPolicyTest.php`
- `tests/Internal/Execution/DeferredWorkerRuntimeTest.php`
- `tests/Internal/Journal/JournalRecordFactoryTest.php`
- `tests/Journal/JournalContractTest.php`
- `tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php`
- `orchestration/tasks/P4-002-supervision-policy-contract.md`
- `orchestration/reports/P4-002-supervision-policy-contract.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- Deferred既定Policyは最大3 Attempt、初期Delay 1秒、倍率2.0、最大Delay 60秒、Jitter ±20% とする。
- Attempt Timeoutは後続のConfig仕様で定義し、このTaskでは未実装とする。
- Retryable判定は既定Policyでは`RetryableException` marker interfaceを判断材料にする。
- Supervision DecisionはRetry時にDelay Millisecondsを返し、`scheduledAt`はRuntimeのClockから計算する。
- Dead Letter Transport未実装の間は、Retry不能または上限到達時に`operation.failed`へ遷移させる。
- Runtimeの失敗監督、Journal Record構築、PostgreSQL Lifecycle SQLを小さな内部クラスへ分割し、既存クラスの責務肥大化を避けた。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'SupervisionPolicyTest|DeferredWorkerRuntimeTest|JournalRecordFactoryTest|PostgreSqlCanonicalJournalStoreTest|JournalContractTest'
Result: OK (26 tests, 159 assertions). Runtime PHP 8.5.7.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (362 tests, 1002 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 745 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Supervision Policy ContractがPublic APIとして実装されている
- [x] Supervision DecisionがRetry、Fail、Dead Letterを型安全に表現できる
- [x] Retry Scheduling DataがJournal Dataとして実装されている
- [x] `attempt.retry_scheduled` がCodecとFactoryで扱える
- [x] PostgreSQL Lifecycle StoreがSupervisingからRetry Scheduledへ原子的に遷移できる
- [x] Deferred Worker RuntimeがSupervision Decisionに基づきRetry予定を記録できる
- [x] 未確定の既定Backoff値、最大Attempt回数、Attempt Timeoutが仕様判断なしに実装されていない
- [x] 必須Commandがすべて成功している

## Remaining Issues

- Dead Letter Transportは未実装。
- Attempt Timeout、Lease Expired Recovery、Heartbeatは後続Taskで扱う。
- Operation固有Policy解決とManifest統合は後続Taskで扱う。

## Suggested Next Action

P4-003 Task Packetを作成し、Dead Letter TransportまたはLease Expired Recoveryのどちらを先に実装するか決めて進める。
