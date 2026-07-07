# D037: Deferred Claim and Attempt

Status: Decided

## Context

Deferred OperationはWorkerがExecution TransportからClaimして実行する。Attempt ID、Attempt番号、Lease、Process Crash時の再Claimを曖昧にすると、同時実行や古いWorkerによる完了書き込みが起こる。

ここではAttemptの識別、Lease Metadataの置き場所、Attempt開始境界、Lease失効後のFencingを決める。

## Question 1: Attempt番号

`AttemptContext` に何を持たせるか。

### Options

- A: Attempt ID、1始まりのAttempt番号、開始時刻を必須にする
- B: Attempt IDと開始時刻だけを持ち、回数はJournalから数える
- C: Attempt番号だけを持ち、Attempt IDを廃止する

### Recommendation

Aを推奨する。

```text
AttemptContext
  attemptId
  number
  startedAt
```

Retry Policyが現在回数を即座に参照でき、Journal欠番やObserver配送失敗があっても回数を復元できる。

[ANSWER]

A

[/ANSWER]

## Question 2: Lease Metadata

### Options

- A: LeaseはTransport内部のClaim Metadataとし、公開ExecutionContextへ含めない
- B: Lease Owner、Token、期限をExecutionContextへ含める
- C: Leaseを設けず、取得したWorkerを信頼する

### Recommendation

Aを推奨する。

LeaseはSQLite、SQS等で方式が異なるInfrastructure上の排他情報であり、業務Handlerへ公開する必要がない。Journalへは必要な安全な実行情報だけをProjectionする。

[ANSWER]

A

[/ANSWER]

## Question 3: Attempt開始境界

### Options

- A: Claim成功後、Handler呼び出し直前にAttempt IDを発行して `attempt.started` を記録する
- B: Queue投入時に最初のAttempt IDを発行する
- C: Handlerが正常終了した後にAttemptを記録する

### Recommendation

Aを推奨する。

ClaimしただけでHandlerを開始できなかったCrashを、実行Attemptとして数えずに済む。`attempt.started` の記録成功をHandler呼び出しの前提とする。

[ANSWER]

A

[/ANSWER]

## Question 4: Lease失効とFencing

古いWorkerが停止せず、Lease失効後に別Workerが再Claimする場合をどう扱うか。

### Options

- A: Claimごとに単調増加Fencing Tokenを発行し、State更新時に一致を検証する
- B: Lease期限だけを確認し、古いWorkerからの書き込みも受け入れる
- C: 同時実行は起きない前提とする

### Recommendation

Aを推奨する。

古いWorkerがHandlerの外部副作用を完全に止めることはできないが、Framework State、Outcome、Journalの完了更新は拒否できる。Token不一致はStale Claimとして記録・監視し、最新WorkerのStateを上書きしない。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

`AttemptContext` はAttempt ID、1始まりのAttempt番号、開始時刻を必須で保持する。

Lease Owner、Lease期限、Fencing Token等はExecution Transport内部のClaim Metadataとし、公開ExecutionContextへ含めない。

WorkerはClaim成功後、Handler呼び出し直前にAttempt IDを発行する。Attempt Stateと `attempt.started` の記録成功をHandler呼び出しの前提とする。Claim後、Attempt開始前にCrashした処理はAttemptとして数えない。

Claimごとに単調増加するFencing Tokenを発行する。Framework State、Outcome、Journalの完了更新時にToken一致を検証し、古いClaimからの書き込みをStale Claimとして拒否する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Retry PolicyがJournalの読み直しなしに現在のAttempt回数を参照できる。
- Infrastructure固有のLease情報を業務Handlerから隠蔽できる。
- 実際にHandlerを開始しなかったClaimをAttempt回数へ含めずに済む。
- `attempt.started` より前にHandlerが実行されることを防げる。
- Lease失効後の古いWorkerが最新State、Outcome、Journal完了を上書きできない。
- FencingではHandlerが既に行った外部副作用を取り消せないため、業務側の冪等性設計は引き続き必要になる。
- Deferred Operation StateへAttempt番号、Claim情報、単調増加Fencing Tokenを保持する必要がある。

[/CONSEQUENCES]
