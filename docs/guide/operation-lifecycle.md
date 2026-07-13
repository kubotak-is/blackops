# Operation Lifecycle

BlackOpsはInlineとDeferredを同じLifecycle Modelで記録します。ApplicationはOperation IDを相関Keyとして受付からTerminal Stateまで追跡できます。

## 共通Lifecycle

正常完了するOperationは次の順に進みます。

```text
operation.received
attempt.started
attempt.succeeded
operation.completed
```

InlineではHTTP Request内でAttemptを実行します。DeferredではHTTPが受付までをDurableに保存し、別ProcessのWorkerがClaimしてAttemptを開始します。

## Rejected

`OperationRejectedException`は予期された業務拒否です。FrameworkはRejected ResultとTerminal Lifecycleへ変換します。Validation、Authorization、Not Found、Conflict、Business Ruleを安定したCategory／Codeで表現します。

## RetryとFailure

Retryable ExceptionはSupervision Policyに従い`attempt.failed`と`attempt.retry_scheduled`を記録します。次のWorker Attemptが同じOperationを再Claimします。Retry上限を超えた処理はFailed／Dead Letterへ進みます。

通常のException、Worker Interrupt、Claim Lossは業務拒否として扱いません。Lease、Heartbeat、Fencingにより、古いWorkerが成功を確定しないようにします。

## Outcome

CompletedだけがTyped Outcomeを保存します。Rejected、Failed、Retry Scheduled、Dead Letter、Claim LostはOutcome Recordを作成しません。詳細は[Outcome Retrieval](outcome-retrieval.md)を参照してください。

JournalとOutcomeは別々の保持期間を設定できます。Operation単位のHoldと安全なPurgeについては[Data Retention](retention.md)を参照してください。
