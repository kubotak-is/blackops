# P8-001A: Signal Heartbeat Test Stability

Status: Ready

## Goal

Full PHPUnitで間欠的にheartbeat count 0となる既存Signal heartbeat Testを、Production Contractを弱めずOS timer schedulingに依存しない決定的な検証へ変更し、Phase 8の品質Gateを安定化する。

## In Scope

- `SignalHeartbeatTest` のFlake原因確認
- Alarm arming、SIGALRM handler、同期Handler中heartbeat、Signal State restoreの決定的検証
- 実時間Deadline／CPU scheduling依存の除去
- Focused反復とFull Suite複数回による安定性確認
- Report／Checkpoint

## Out of Scope

- Production Signal Runtime Contract変更
- Heartbeat Interval／Lease／Grace仕様変更
- Worker Runtime機能追加
- P8-001 Setup変更
- PHPUnit全体のProcess Isolation変更

## Relevant Specifications and Reports

- `develop/spec/32-worker-crash-recovery.md`
- `develop/spec/45-phase-7-delivery-plan.md`
- `develop/orchestration/reports/P8-001-post-create-initialization.md`

## Files Allowed to Change

- `tests/Internal/Execution/SignalHeartbeatTest.php`
- `develop/orchestration/tasks/P8-001A-signal-heartbeat-test-stability.md`
- `develop/orchestration/reports/P8-001A-signal-heartbeat-test-stability.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Verification Contract

- TestはSignal RuntimeがClaim実行時にAlarmをarmしたことを確認する
- Test自身がProcessへSIGALRMを送信し、同期Operation中にHeartbeat Handlerが実行されることを確認する
- Test終了後にAsync Signal ModeとSIGALRM／SIGTERM／SIGINT Handlerが復元されることを確認する
- `microtime()` Deadlineや1秒以上の待機でSignal配送を待たない
- POSIX Signal送信が利用できないEnvironmentでは理由付きSkipとする
- Production `PcntlSignalHeartbeat` は変更しない

## Acceptance Criteria

- [ ] Flaky Testが実時間Deadlineへ依存しない
- [ ] Alarmがarmされたことを検証する
- [ ] 同期Operation中のSIGALRMがHeartbeatを実行する
- [ ] Signal State restoreを維持する
- [ ] Focused Signal Suiteを20回連続実行して成功する
- [ ] Full PHPUnitを2回連続実行して成功する
- [ ] Mago、Deptrac、管理ID Guard、Diff Checkが成功する
- [ ] ReportとSTATEが更新される

## Required Commands

```bash
for run in $(seq 1 20); do docker compose run --rm app vendor/bin/phpunit tests/Internal/Execution/SignalHeartbeatTest.php; done
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P8-001A-signal-heartbeat-test-stability.md` に次を記録する。

- Summary
- Flake Cause／Test Design Evidence
- Alarm／Signal／Restore Evidence
- Repetition Results
- Changed Files
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
