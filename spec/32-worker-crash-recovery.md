# Worker Crash Recovery

## Heartbeat

WorkerはHandler実行中、定期的にHeartbeatを送りLeaseを延長する。

Heartbeat間隔はLease期間より十分短くする。Lease期間とHeartbeat間隔の具体値はConfigで定める。

## Crash Recovery

Attempt開始後にWorkerがCrashし、Running StateのLeaseが失効した場合、次にClaimしたWorkerが前Attemptを閉じる。

```text
attempt.failed
  error.type: lease_expired
  error.retryable: Supervision Policyが判定
```

その後、Supervision PolicyがAttempt回数、Deadline等に基づきRetry、Fail、Dead Letterを判断する。

## Stale Worker

Heartbeat更新に失敗したWorkerはClaimを失ったものとみなす。

- Framework Stateを更新しない
- Outcomeを保存しない
- Lifecycle Journalの完了Eventを発行しない
- 可能ならHandlerを協調的に中断する
- System LogとMetricへStale Workerを記録する

PHPで実行中Handlerを強制停止できない場合、外部副作用は残り得る。外部副作用には冪等性が必要である。

## Graceful Shutdown

SIGTERM等を受けたWorkerは新規Claimを停止する。

実行中処理はGrace Period内で完了させる。超過時はLeaseを早期解放せずProcessを終了し、Leaseの自然失効後にRecoveryへ委ねる。
