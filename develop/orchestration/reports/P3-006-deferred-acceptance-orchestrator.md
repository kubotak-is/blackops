# P3-006: Deferred Acceptance Orchestrator Report

Status: Accepted

## Summary

Deferred受付時に、Operation State保存、`operation.received` Journal、`operation.accepted` Journal、Operation Stateの次Sequence更新を同一DBAL TransactionでCommitするInternal Orchestratorを追加した。

受付成功時はOperation Stateが`accepted`、Journal Sequenceが1と2、次Sequenceが3になり、`DeferredAcknowledgement`を返す。Handler実行、Observer配送、HTTP 202 Response変換、Worker Claimは後続Taskへ残した。

## Changed Files

- `src/Internal/Execution/DeferredAcceptanceOrchestrator.php`
- `src/Internal/Journal/JournalRecordFactory.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSender.php`
- `tests/Internal/Journal/JournalRecordFactoryTest.php`
- `tests/Transport/PostgreSql/PostgreSqlDeferredAcceptanceOrchestratorTest.php`
- `docs/internals/deferred-transport-contract.md`
- `develop/orchestration/tasks/P3-006-deferred-acceptance-orchestrator.md`
- `develop/orchestration/reports/P3-006-deferred-acceptance-orchestrator.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Deferred受付OrchestratorはInternal層に置き、PostgreSQL DBAL AdapterとCanonical Journal Writerを同一DBAL Connection / Transactionで組み合わせる。
- Deferred受付時のLifecycleは`initial -> received -> accepted`として検証する。
- Deferred受付時のJournal Sequenceは`operation.received = 1`、`operation.accepted = 2`とし、Operation Stateの`next_sequence`を3へ進める。
- `operation.accepted`は受付完了を表す空DataのJournal Recordとして保存する。
- Operation EnvelopeのExecution StrategyがDeferredでない場合、Orchestratorは受付しない。
- Handler実行、Observer配送、HTTP Response変換、Dispatcher / Worker RuntimeはこのTaskの範囲外とした。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'PostgreSqlDeferredAcceptanceOrchestratorTest|JournalRecordFactoryTest'
Result: OK (4 tests, 23 assertions). Runtime PHP 8.5.7.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (330 tests, 807 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 513 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Deferred Acceptance Orchestratorが追加される
- [x] Deferred受付成功時にOperation Stateが保存される
- [x] Deferred受付成功時に`operation.received` と `operation.accepted` Journalが保存される
- [x] Journal Sequenceが1, 2として保存される
- [x] Operation Stateの次Sequenceが3へ進む
- [x] 受付成功時に`DeferredAcknowledgement`が返る
- [x] Duplicate Operation ID等の失敗時にTransactionがRollbackされる
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- HTTP 202 Response変換は未実装。
- Deferred Dispatcher統合は未実装。
- Operation Codec実装は未実装。
- Claim、Heartbeat、Acknowledge、Release、Worker Runtimeは未実装。

## Suggested Next Action

HTTP入口からDeferred Operationを受け付け、Orchestratorへ渡して`DeferredAcknowledgement`をHTTP 202 Responseへ変換するTaskへ進む。
