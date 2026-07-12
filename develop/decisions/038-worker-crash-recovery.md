# D038: Worker Crash Recovery

Status: Decided

## Context

Deferred ClaimはLeaseとFencing Tokenで保護する。次に、長時間HandlerのLease更新、Attempt開始後のWorker Crash、Lease更新失敗、Graceful Shutdown時の動作を決める。

Worker Crashは通常の例外と違い、停止したProcess自身が `attempt.failed` を記録できない。次にClaimしたWorkerが安全に復旧処理を行う必要がある。

## Question 1: Lease更新

### Options

- A: WorkerがHandler実行中に定期HeartbeatでLeaseを延長する
- B: Claim時に十分長い固定Leaseを一度だけ設定する
- C: Lease期限を設けない

### Recommendation

Aを推奨する。

Lease期間より長いHandlerを実行でき、Crash時はHeartbeat停止によって最終的に再Claimできる。Heartbeat間隔はLease期間より十分短くし、具体値はConfigで定める。

[ANSWER]

A

[/ANSWER]

## Question 2: Attempt開始後のCrash

Leaseが切れたOperationのStateがRunningだった場合、次のWorkerはどう復旧するか。

### Options

- A: 前AttemptをLease Expiredによる `attempt.failed` として閉じ、Supervision Policyへ渡す
- B: 前Attemptを無視して、新しい `attempt.started` を直接記録する
- C: Operationを即座にDead Letteredにする

### Recommendation

Aを推奨する。

既存のState Machineを崩さず、CrashもAttempt失敗として追跡できる。構造化Errorは `lease_expired` を示し、Retry Policyが回数と期限を基にRetry／Fail／Dead Letterを判断する。

[ANSWER]

A

[/ANSWER]

## Question 3: Heartbeat失敗後の古いWorker

### Options

- A: Claimを失ったものとして完了更新を禁止し、可能なら処理を協調的に中断する
- B: Handlerを最後まで実行し、Fencing Tokenを無視して結果を保存する
- C: Lease更新が失敗しても期限までは通常処理を続ける

### Recommendation

Aを推奨する。

PHPで実行中Handlerを強制停止できない場合でも、戻り後のOutcome保存とLifecycle更新はFencingで拒否する。Stale WorkerをSystem LogとMetricへ記録する。

[ANSWER]

A

[/ANSWER]

## Question 4: Graceful Shutdown

SIGTERM等を受けたWorkerの動作を決める。

### Options

- A: 新規Claimを停止し、実行中処理はGrace Period内で完了させ、超過時はLeaseを自然失効させる
- B: 実行中でも直ちにLeaseを解放してProcessを終了する
- C: Signalを無視して実行を続ける

### Recommendation

Aを推奨する。

実行中にLeaseを早期解放すると、古いHandlerが動いている間に別Workerが同じOperationを開始し得る。Grace Period超過時は完了書き込みを行わずProcessを終了し、Lease Expiry後のRecoveryへ委ねる。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

WorkerはHandler実行中、Lease期間より十分短い間隔でHeartbeatを送り、Leaseを延長する。Lease期間とHeartbeat間隔はConfigで定める。

Attempt開始後にWorkerがCrashし、Running StateのLeaseが失効した場合、次にClaimしたWorkerは前Attemptを `lease_expired` による `attempt.failed` として閉じ、Supervision Policyへ渡す。

Heartbeat更新に失敗したWorkerはClaimを失ったものとみなし、Framework State、Outcome、Lifecycle Journalの完了更新を禁止する。可能な場合はHandlerを協調的に中断し、Stale WorkerをSystem LogとMetricへ記録する。

Graceful Shutdownでは新規Claimを停止し、実行中処理をGrace Period内で完了させる。超過時はLeaseを早期解放せずProcessを終了し、Leaseの自然失効後にRecoveryへ委ねる。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Lease期間を超えるHandlerもHeartbeatによって実行できる。
- Process Crashを追跡可能なAttempt Failureとして扱える。
- CrashしたAttemptもRetry上限とDeadlineの計算対象になる。
- Claimを失った古いWorkerによるFramework Stateの上書きを防止できる。
- PHPでHandlerを強制停止できない場合、外部副作用は残り得るため冪等性が必要である。
- Shutdown時の早期Lease解放による同時実行を避けられる。
- Worker RuntimeにHeartbeat、Signal処理、Grace Period、Stale Claim Metricが必要になる。

[/CONSEQUENCES]
