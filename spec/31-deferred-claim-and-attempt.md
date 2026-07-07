# Deferred Claim and Attempt

## AttemptContext

`AttemptContext` は次を必須で保持する。

```text
AttemptContext
  attemptId
  number
  startedAt
```

Attempt番号は1から開始する。Retry PolicyはContextから現在のAttempt回数を参照できる。

## Lease Metadata

Lease Owner、Lease期限、Fencing Token等はExecution Transport内部のClaim Metadataとする。

Infrastructure固有の排他情報であるため、公開ExecutionContextおよび業務Handlerへ露出させない。Journalへは必要な安全な実行情報だけをProjectionできる。

## Attempt開始境界

WorkerはClaim成功後、Handler呼び出し直前にAttempt IDを発行する。

Attempt Stateと `attempt.started` の記録成功をHandler呼び出しの前提とする。Claim後、Attempt開始前にCrashした処理はAttemptとして数えない。

## Fencing

Claimごとに単調増加するFencing Tokenを発行する。

Framework State、Outcome、Journalの完了更新時にToken一致を検証する。Tokenが一致しない古いClaimからの書き込みはStale Claimとして拒否し、最新WorkerのStateを上書きしない。

FencingはHandlerが既に行った外部副作用を取り消さない。外部副作用にはOperation IDやIdempotency Keyを用いた冪等性設計が必要である。
