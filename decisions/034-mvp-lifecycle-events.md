# D034: MVP Lifecycle Events

Status: Decided

## Context

Lifecycle状態遷移を機械的に検証する前に、MVPで記録するEvent集合を整合させる。

現在のEvent一覧にはAttempt失敗はあるが、Retryがいつ予定されたかを表すEventがない。また、Accepted、AttemptSucceeded、OperationFailed、DeadLetteredの境界を明確にしないと、同じ実装でも異なるJournal列が生成される。

## Question 1: Retry Scheduled Event

### Options

- A: `attempt.retry_scheduled` を標準Lifecycle Eventへ追加する
- B: `attempt.failed` のData内だけに次回予定を含める
- C: Retry予定はJournalへ記録しない

### Recommendation

Aを推奨する。

Supervision Policyの判断結果を独立した事実として記録できる。

```text
AttemptRetryScheduledData
  failedAttemptId
  nextAttemptNumber
  scheduledAt
  delayMilliseconds
```

[ANSWER]

A

[/ANSWER]

## Question 2: OperationAccepted

### Options

- A: Deferred OperationがExecution Transportへ永続化された場合だけ記録する
- B: InlineとDeferredの両方でReceived直後に記録する
- C: Acceptedを廃止する

### Recommendation

Aを推奨する。

`operation.accepted` を「Frameworkが後続実行の責任をDurableに引き受けた」という明確な意味にできる。Inlineは `operation.received` から直接 `attempt.started` へ進む。

[ANSWER]

A

[/ANSWER]

## Question 3: AttemptSucceededとOperationCompleted

### Options

- A: 両方を維持する
- B: AttemptSucceededを廃止し、OperationCompletedだけにする
- C: OperationCompletedを廃止し、AttemptSucceededだけにする

### Recommendation

Aを推奨する。

`attempt.succeeded` はHandlerがOutcomeを返した事実、`operation.completed` はOutcome保存や必要な最終処理まで完了し、OperationがTerminalになった事実とする。両者の間で障害が起きたことも追跡できる。

[ANSWER]

A

[/ANSWER]

## Question 4: FailedとDead Lettered

Deferred OperationをDead Letterへ移した場合に、`operation.failed` も併記するか。

### Options

- A: Dead Letteredを独立したTerminal Eventとし、OperationFailedは併記しない
- B: OperationFailedの後にOperationDeadLetteredも記録する
- C: OperationFailedだけを記録し、Dead Letter移動はDataへ含める

### Recommendation

Aを推奨する。

一つのOperationにTerminal Eventを一つだけ持たせる。Retry不能・上限到達後に隔離できたDeferred Operationは `operation.dead_lettered`、隔離せず最終失敗したOperationは `operation.failed` とする。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

標準Lifecycle Eventへ `attempt.retry_scheduled` を追加する。Supervision PolicyがRetryを決定した事実として、失敗したAttempt ID、次Attempt番号、予定時刻、Delayを記録する。

`operation.accepted` はDeferred OperationがExecution TransportへDurableに永続化され、Frameworkが後続実行の責任を引き受けた場合だけ記録する。Inline OperationはReceivedから直接Attempt Startedへ進む。

`attempt.succeeded` と `operation.completed` は両方維持する。前者はHandlerが成功結果を返した事実、後者はOutcome保存等の最終処理を終えてOperationがTerminalになった事実とする。

`operation.dead_lettered` は独立したTerminal Eventとし、同じOperationへ `operation.failed` を併記しない。Dead Letterへ隔離できたDeferred OperationはDead Lettered、隔離せず最終失敗したOperationはFailedとする。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Retry予定とBackoffをJournalから追跡できる。
- Acceptedが単なる受付ではなくDurableな責任引受を表す。
- Handler成功後からOperation完了前の障害を識別できる。
- 一つのOperationが持つTerminal Eventを一つに限定できる。
- `JournalEvent`、Data型、Factory、CodecへRetry Scheduledを追加する必要がある。
- 状態遷移表はInlineとDeferredでAccepted経路を分ける必要がある。

[/CONSEQUENCES]
