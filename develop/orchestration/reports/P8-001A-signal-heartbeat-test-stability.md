# P8-001A Signal Heartbeat Test Stability Report

## Summary

Signal heartbeat TestからOS timer配送を待つ実時間Deadlineを除去し、Test自身が同期Operation中にProcessへSIGALRMを送信する決定的な検証へ変更した。Production `PcntlSignalHeartbeat`とRuntime Contractは変更していない。

TestはClaim実行時のAlarm arming、SIGALRMによる同期Heartbeat、Heartbeat後のAlarm re-arm、Async Signal ModeとSIGALRM／SIGTERM／SIGINT Handlerの復元を検証する。Focused Suite 20回とFull Suite 2回が連続成功した。

## Flake Cause and Test Design Evidence

旧Testは1秒Alarmをarmした後、`microtime()`の2.5秒Deadlineまで`usleep()`を反復し、OS timerとPHP async signal配送が先に実行されることを仮定していた。Full Suite負荷下ではDeadline終了時点でheartbeat count 0となる間欠Failureが観測された。

新Testはheartbeat intervalを30秒としてOS timerの自然発火を待たず、同期Operation Callback内で次を行う。

1. `pcntl_alarm(0)`の戻り値が0より大きく30以下であることによりClaim実行時のAlarm armingを確認する
2. `posix_kill(getmypid(), SIGALRM)`でTest Processへ明示Signalを送る
3. `pcntl_signal_dispatch()`でPending Signalを同期配送する
4. Heartbeat callが正確に1回であることを確認する
5. Handlerが再armしたAlarmを同じ正の範囲で確認する

POSIX Signal送信がないEnvironmentでは理由付きSkipとする。Heartbeat failure Testも同じ明示Signal方式へ変更し、1秒timerの自然発火待ちを除去した。

## Alarm, Signal, and Restore Evidence

Initial／re-arm Alarmはいずれも正の30秒以下であるため、`run()`がAlarmをarmし、SIGALRM HandlerがHeartbeat後に再armするContractを別々に検証する。Exact remaining timeはProcess schedulingで減少し得るため成功条件にしない。

Signal送信後、同期Operation Callbackを抜ける前にRecording Heartbeatが1回呼ばれる。Handler failure経路は明示SIGALRMにより`WorkerClaimLostException`へ変換される。

Test前にAsync Signal ModeとSIGALRM／SIGTERM／SIGINT Handlerを保存し、`runLoop()`終了後にすべて同一であることを確認する既存Assertionを維持した。Production Codeは変更していない。

## Repetition Results

- Focused Signal Suite: 20/20 runs passed
- Each Focused Run: 7 tests / 21 assertions
- Full PHPUnit Run 1: 647 tests / 2196 assertions
- Full PHPUnit Run 2: 647 tests / 2196 assertions
- Heartbeat count 0の再発: なし

Orchestrator AcceptanceではFocused Signal Suiteを5回連続、Full PHPUnitを1回再実行し、Focusedは各 `7 tests / 21 assertions`、Fullは `647 tests / 2196 assertions` で全Run成功した。

## Changed Files

- `tests/Internal/Execution/SignalHeartbeatTest.php`
- `develop/orchestration/tasks/P8-001A-signal-heartbeat-test-stability.md`
- `develop/orchestration/reports/P8-001A-signal-heartbeat-test-stability.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Alarm timerそのものは初回arm値とHeartbeat後re-arm値で検証し、自然な時間経過によるSignal配送は成功条件にしない。
- `pcntl_signal_dispatch()`を明示し、Async配送のOpcode timingへTest結果を依存させない。
- SIGTERM Grace Expiry TestはGrace Periodの実時間Contractを検証する別責務であり、今回のheartbeat delivery Flake範囲外として維持した。
- POSIX Signal送信が利用できない環境ではContractを偽装せずSkipする。

## Commands and Results

```text
for run in $(seq 1 20); do docker compose run --rm app vendor/bin/phpunit tests/Internal/Execution/SignalHeartbeatTest.php; done
Result: 20/20 runs passed. Each run OK (7 tests, 21 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/phpunit
Result: Run 1 OK (647 tests, 2196 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/phpunit
Result: Run 2 OK (647 tests, 2196 assertions). Runtime PHP 8.5.7.

docker compose run --rm app mago format --check src tests examples
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/deptrac
Result: 350 files / Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1489 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?...|D...|P...|TODO.md:...' src tests examples --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

Task Packetの8項目をすべて満たした。実時間Deadline依存を除去し、Alarm arming、同期Heartbeat、Signal State復元、Focused 20回、Full 2回、全品質Command、管理文書を完了した。

## Remaining Issues

Blockerはない。Production Signal RuntimeとHeartbeat／Lease／Grace Contractは変更していない。

## Suggested Next Action

P8-002 Local Split and Create-project Smokeへ進む。
