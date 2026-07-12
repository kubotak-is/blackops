# P3-010: Deferred Worker Runtime Report

Status: Accepted

## Summary

Claim済みDeferred OperationをDecodeし、Attemptを開始してHandlerを実行し、成功または業務RejectをOperation StateとCanonical Journalへ反映するInternal Worker Runtimeを追加した。

Attempt開始BoundaryとResult反映BoundaryはそれぞれDBAL Transactionで囲み、State、Sequence、Canonical Journalを同一TransactionでCommitする。Handler実行中はDatabase Transactionを保持しない。

## Changed Files

- `src/Internal/Execution/DeferredWorkerRuntime.php`
- `src/Internal/Execution/DeferredWorkerRuntimeServices.php`
- `src/Internal/Execution/DeferredWorkerRuntimeStorage.php`
- `src/Transport/PostgreSql/PostgreSqlAttemptStartedReservation.php`
- `src/Transport/PostgreSql/PostgreSqlCompletionReservation.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationLifecycleStore.php`
- `src/Transport/PostgreSql/PostgreSqlRejectionReservation.php`
- `tests/Internal/Execution/DeferredWorkerRuntimeTest.php`
- `docs/internals/deferred-transport-contract.md`
- `develop/orchestration/tasks/P3-010-deferred-worker-runtime.md`
- `develop/orchestration/reports/P3-010-deferred-worker-runtime.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Operation Definitionは業務入力を持たない定義Classとして扱い、MetadataのDefinition Classを引数なしで復元する。
- Claim TokenはPostgreSQL Adapter内部形式としてOperation IDとFencing Tokenを検証する。
- Handler成功時は`attempt.succeeded`と`operation.completed`を連続Sequenceで保存する。
- Handler業務Reject時は`operation.rejected`を保存し、Operation Stateを`rejected`へ更新する。
- Handler例外、Retry、Dead Letter、Heartbeat、Settlement、Lease Expired Recoveryは後続Phaseへ残した。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter DeferredWorkerRuntimeTest
Result: OK (2 tests, 22 assertions). Runtime PHP 8.5.7.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (347 tests, 902 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 673 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Deferred Worker Runtimeが追加される
- [x] Claim済みMessageをOperationValue / ExecutionContextへDecodeできる
- [x] Attempt開始時に`attempt.started` Journalが保存される
- [x] Handler成功時に`attempt.succeeded` と `operation.completed` Journalが保存される
- [x] Handler業務Reject時に`operation.rejected` Journalが保存される
- [x] 成功時にOperation Stateが`completed`へ更新される
- [x] 業務Reject時にOperation Stateが`rejected`へ更新される
- [x] Sequenceが受付後の次Sequenceから継続する
- [x] Handler実行中にDatabase Transactionを保持しない
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- Handler例外のRetry / Failure / Dead Letterは未実装。
- Heartbeatは未実装。
- Claim Settlement acknowledge / releaseは未実装。
- Worker Loop / CLI Commandは未実装。
- Lease Expired Recoveryは未実装。
- Outcome取得用Outcomes Tableは未実装。

## Suggested Next Action

Phase 3 CloseoutでDeferred Vertical Sliceの達成範囲を確認し、Retry / Heartbeat / Crash Recovery / Dead LetterをPhase 4へ引き継ぐ。
