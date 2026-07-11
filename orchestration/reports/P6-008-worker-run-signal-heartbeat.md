# P6-008: Worker Run with Signal Heartbeat Report

Status: Completed

## Summary

- Expired Attempt Recovery、単一Claim、Deferred Runtime実行、Terminal Acknowledge、Idle Sleepを接続する`DeferredWorkerLoop`を追加した。
- 有限Iterationと継続Loopを同じ実装で提供し、`blackops:worker:run` Symfony Console Commandから起動可能にした。
- PCNTL asynchronous signalを使う`PcntlSignalHeartbeat`を追加し、Handler実行中の定期Heartbeat、SIGTERM／SIGINT停止要求、Grace Period超過中断を実装した。
- Signal GuardをAttempt開始Transaction後から結果確定Transaction前のHandler境界だけへ適用した。
- Heartbeat失敗とGrace Period超過を通常Handler Supervisionから除外し、Acknowledge、Release、Lifecycle完了更新を行わずLease Expired Recoveryへ委ねるようにした。
- Supervision記録済みHandler例外だけを`SupervisedHandlerFailureException`で識別し、Loop継続対象をその型へ限定した。Metadata、Transaction、Recovery、Claim、Completion、Settlementの基盤例外はWorker Failureとして伝播する。
- Heartbeat戻り値の更新済みClaimを次回Heartbeatへ引き継ぎ、Signal／Alarm／Async設定をLoop終了時に復元するようにした。
- Reference Docker ImageでPCNTLを有効化し、専用DBAL Connectionを使うComposition例と運用契約をDocumentationへ追加した。

## Changed Files

- `Dockerfile`
- `TODO.md`
- `decisions/059-worker-heartbeat-runtime.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/internals/README.md`
- `docs/internals/bootstrap.md`
- `docs/internals/worker-runtime.md`
- `src/Internal/Console/WorkerRunCommand.php`
- `src/Internal/Execution/ClaimExecutionGuard.php`
- `src/Internal/Execution/DeferredClaimRuntime.php`
- `src/Internal/Execution/DeferredLeaseExpiredRecovery.php`
- `src/Internal/Execution/DeferredWorkerLoop.php`
- `src/Internal/Execution/DeferredWorkerRuntime.php`
- `src/Internal/Execution/DirectClaimExecutionGuard.php`
- `src/Internal/Execution/ExpiredAttemptRecovery.php`
- `src/Internal/Execution/HandlerInvocationFailedException.php`
- `src/Internal/Execution/NativeWorkerSleeper.php`
- `src/Internal/Execution/PcntlSignalHeartbeat.php`
- `src/Internal/Execution/PcntlSignalSupport.php`
- `src/Internal/Execution/SupervisedHandlerFailureException.php`
- `src/Internal/Execution/WorkerClaimLostException.php`
- `src/Internal/Execution/WorkerExecutionInterruptedException.php`
- `src/Internal/Execution/WorkerGracePeriodExceededException.php`
- `src/Internal/Execution/WorkerLoop.php`
- `src/Internal/Execution/WorkerSignalRuntime.php`
- `src/Internal/Execution/WorkerSleeper.php`
- `tests/Internal/Console/WorkerRunCommandTest.php`
- `tests/Internal/Execution/DeferredWorkerLoopTest.php`
- `tests/Internal/Execution/DeferredWorkerRuntimeTest.php`
- `tests/Internal/Execution/SignalHeartbeatTest.php`
- `orchestration/tasks/P6-008-worker-run-signal-heartbeat.md`
- `orchestration/reports/P6-008-worker-run-signal-heartbeat.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- 同じ`PcntlSignalHeartbeat` InstanceをWorker LoopのSignal RuntimeとDeferred RuntimeのHandler Guardへ注入する。これによりProcess SignalはLoop全体で受け、Heartbeat AlarmはHandler実行中だけ有効になる。
- `ClaimHeartbeat` AdapterはClaim／Lifecycle／Journal／Recovery／Settlementとは別のDBAL `Connection` Instanceで構成する。Signal割込み時に通常DBAL処理へ再入しないための必須Composition境界とした。
- Heartbeat間隔はLease期間より短い正数、Grace Periodも正数として構成時に検証する。
- SIGTERM／SIGINTは新規Claimを停止する。Active HandlerはGrace Period内なら通常完了とAcknowledgeを許し、超過時はClaimをReleaseせずProcessをFailure終了させる。
- Heartbeat失敗またはGrace超過はClaim所有権を信頼できないため、通常のHandler Failure Supervisionを実行しない。Running StateとAttempt開始Journalを残し、Lease自然失効後のRecoveryへ委ねる。
- Handler例外はFailure／Retry／Dead LetterのSupervision書込みが成功した後だけ専用Wrapperへ変換する。Supervision書込み自体が失敗した場合は元のInfrastructure例外を伝播し、Loopで継続しない。
- PCNTL確認はWorker Signal Runtimeの構成時だけ行い、HTTP／Build経路へ拡張要件を波及させない。
- Handler呼出し失敗の正規化を専用境界へ分離した最終差分では、Mago LintのHalstead Warningも解消した。

## Commands and Results

```text
docker compose build app
Result: Image blackops/framework:dev Built.

docker compose run --rm app php -r 'exit(extension_loaded("pcntl") ? 0 : 1);'
Result: Exit 0. PCNTL is enabled in the reference app image.

docker compose run --rm app vendor/bin/phpunit --filter 'WorkerRun|SignalHeartbeat|DeferredWorkerLoop'
Result: OK (26 tests, 162 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (531 tests, 1647 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1170 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

Targeted Test初回実行ではTest Fakeのnullsafe write構文と観測用Journal Table名誤りを検出した。通常のnull checkと実Table名`journal`へ修正し、再実行で成功した。

Mago初回実行ではSignal Class Complexity、PCNTL StubのHandler戻り型、Closure戻り値の`mixed`推論、Sleep非負性、Console Optionの`mixed`入力を検出した。Signal Support分離、Handler型正規化、Template PHPDoc、入力検証境界を追加し、Analyze Issue 0、Lint Error 0まで解消した。

安全性Review後、Signal Loop外でClaim Guardを直接呼ぶ誤構成をAlarm設定前の明示例外にした。追加Test後のTargeted SuiteとFull Suiteを同時実行した際は、両Suiteが共有する固定PostgreSQL Test SchemaのDrop／Createが競合した。Testを順次再実行し、上記の最終結果で成功した。

Orchestrator Reviewで、LoopがSupervision未完了のInfrastructure例外まで継続対象にしていた問題を検出した。Supervision記録成功後だけ専用Wrapperを投げるようRuntimeを修正し、Loopはその型だけを継続対象にした。元Handler例外がWrapperの`previous`に保持されること、通常Runtime例外は継続設定が有効でも伝播しSettlementしないことをTestした。

## Acceptance Criteria

- [x] `blackops:worker:run`が登録可能なSymfony Console Commandとして動作する
- [x] LoopがExpired Running Attemptを回収してからEligible Operationを一件Claimする
- [x] Claimがない場合にIdle Sleepし、有限Iterationで終了できる
- [x] Handler実行中に設定間隔でHeartbeatが呼ばれる
- [x] Heartbeat依存をClaim／Lifecycle Connectionと分けて構成できる
- [x] Heartbeat失敗後に完了State、Outcome、Journal、Settlementを更新しない
- [x] 通常成功／Rejected後にTerminal Acknowledgeする
- [x] Handler例外後もSupervision済みStateを保ち、Loop Policyに従って継続できる
- [x] SIGTERM／SIGINT後に新規Claimしない
- [x] Grace Period超過時にClaimをReleaseせず終了する
- [x] Signal／Alarm状態がOperation後に復元される
- [x] PCNTLなしのWorker実行が明示的にFail Fastする
- [x] Reference Docker RuntimeでPCNTLが有効になる
- [x] Worker運用と専用Heartbeat ConnectionがDocumentationへ記録される
- [x] 必須Commandがすべて成功する

## Remaining Issues

- なし。

## Suggested Next Action

- MVP残作業の次Task Packetへ進む。

## Orchestrator Review

- PCNTL AlarmがAttempt開始Transaction後、完了／Rejected Transaction前のHandler区間だけを保護することを確認した。
- Heartbeat戻り値のClaimを引き継ぎ、Claim Lost／Grace超過時にSupervision、Settlement、Releaseを行わないことを確認した。
- SIGTERM／SIGINT、Grace Period、Signal Handler／Alarm／Async設定のCleanupとLoop外Guard誤用のFail FastをTestで確認した。
- Heartbeat用`ClaimHeartbeat`を独立DBAL Connectionで構成でき、同じSignal InstanceをLoopとHandler Guardへ注入するDocumentationを確認した。
- Supervision記録済みHandler失敗だけをLoop継続対象とし、Infrastructure／Transaction／Metadata例外が伝播するようReview修正した。
- Reference Docker ImageでPCNTLが有効であることを確認した。
- Targeted PHPUnitを再実行し、`OK (26 tests, 162 assertions)`を確認した。
- Mago Lintを再実行し、最終差分で`INFO No issues found`を確認した。
- Deptracを再実行し、Violations、Warnings、Errorsが0であることを確認した。
- Review指摘およびBlockerはない。
