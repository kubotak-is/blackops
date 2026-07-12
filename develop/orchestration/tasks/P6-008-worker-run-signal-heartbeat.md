# P6-008: Worker Run with Signal Heartbeat

Status: Accepted

## Goal

PostgreSQL Deferred Operationを継続処理する最小Worker Loopと`blackops:worker:run` Commandを実装し、PCNTL SignalによるHandler実行中Heartbeat、Crash Recovery、Fencing、Graceful ShutdownをMVP仕様どおり接続する。

## In Scope

- 一件単位のExpired Attempt Recovery、Claim、Deferred Worker Runtime実行、Terminal Acknowledge
- Claimがない場合の設定可能なIdle Sleep
- Test用の有限IterationとProduction用の継続Loop
- PCNTL asynchronous `SIGALRM`による定期Heartbeat
- Heartbeat専用`ClaimHeartbeat`依存と専用DB Connection構成境界
- Heartbeat失敗時のClaim Lost中断と完了更新禁止
- SIGTERM／SIGINTで新規Claim停止
- Grace Period内の実行完了と、超過時の非Release／自然Lease失効
- Signal／Alarm設定のOperation境界Cleanupと以前のHandler復元
- `blackops:worker:run` Symfony Console Command
- Reference Docker RuntimeのPCNTL有効化
- Worker Loop、Signal、Heartbeat、例外継続、ShutdownのUnit／Integration Test
- Worker利用方法と専用Connection要件のDocumentation

## Out of Scope

- Outcome Store追加
- Doctrine Migration Command
- Child Process／IPC Worker
- 複数Claimの同一Process並列実行
- Framework外部のProcess Supervisor設定
- Public Core／Operation API変更
- FrankenPHP HTTP Front Controller

## Relevant Specifications and Decisions

- `develop/spec/12-mvp-scope.md`
- `develop/spec/31-deferred-claim-and-attempt.md`
- `develop/spec/32-worker-crash-recovery.md`
- `develop/spec/33-execution-transport-contract.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/decisions/038-worker-crash-recovery.md`
- `develop/decisions/039-execution-transport-contract.md`
- `develop/decisions/059-worker-heartbeat-runtime.md`

## Files Allowed to Change

- `src/Internal/Execution/**`
- `src/Internal/Console/**`
- `tests/Internal/Execution/**`
- `tests/Internal/Console/**`
- `Dockerfile`
- `docs/internals/worker-runtime.md`
- `docs/internals/README.md`
- `docs/internals/bootstrap.md`
- `docs/guide/runtime-bootstrap.md`
- `develop/TODO.md`
- `develop/decisions/059-worker-heartbeat-runtime.md`
- `develop/orchestration/tasks/P6-008-worker-run-signal-heartbeat.md`
- `develop/orchestration/reports/P6-008-worker-run-signal-heartbeat.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Constraints

- Production Code／TestのCommentへSpec、Decision、Task、TODOの管理番号を書かない
- Signal Heartbeatは通常Claim／Lifecycle処理と別のDBAL Connectionを使うCompositionを前提とする
- Heartbeat間隔はLease期間より短い正の秒数として検証する
- SIGALRM Handlerは次回Alarmを再設定し、Operation終了時にAlarmとSignal状態を必ずCleanupする
- Heartbeat失敗とGrace Period超過をHandler例外Supervisionへ渡さない
- Claim Lost後はAcknowledge、Release、Lifecycle完了更新を行わない
- Shutdown時にClaimを早期Releaseしない
- PCNTL不足はWorker構成／実行時に明示的な例外でFail Fastし、HTTP／Build経路へ影響させない
- Worker Loopは一度に一Claimだけ実行する
- TestでSignalをProcessへ実送信する場合も、Test RunnerのSignal状態を終了時に復元する

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

## Required Commands

```bash
docker compose build app
docker compose run --rm app php -r 'exit(extension_loaded("pcntl") ? 0 : 1);'
docker compose run --rm app vendor/bin/phpunit --filter 'WorkerRun|SignalHeartbeat|DeferredWorkerLoop'
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P6-008-worker-run-signal-heartbeat.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
