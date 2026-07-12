# MVP Lifecycle Events

## 標準Event

```text
operation.received
operation.accepted
attempt.started
attempt.succeeded
attempt.failed
attempt.retry_scheduled
operation.completed
operation.rejected
operation.failed
operation.dead_lettered
```

## Retry Scheduled

`attempt.retry_scheduled` はSupervision PolicyがRetryを決定した事実を表す。

```text
AttemptRetryScheduledData
  failedAttemptId
  nextAttemptNumber
  scheduledAt
  delayMilliseconds
```

## Accepted

`operation.accepted` はDeferred OperationがExecution TransportへDurableに永続化され、Frameworkが後続実行の責任を引き受けた場合だけ記録する。

Inline Operationは `operation.received` から直接 `attempt.started` へ進む。

## Attempt SucceededとOperation Completed

`attempt.succeeded` はHandlerが成功結果を返した事実を表す。

`operation.completed` はOutcome保存等の必要な最終処理を終え、OperationがTerminalになった事実を表す。

## Terminal Event

一つのOperationが持つTerminal Eventは一つとする。

- Dead Letterへ隔離できたDeferred Operation：`operation.dead_lettered`
- 隔離せず最終失敗したOperation：`operation.failed`

Dead LetteredとなるOperationへOperation Failedを併記しない。
