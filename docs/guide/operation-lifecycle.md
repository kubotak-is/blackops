# Lifecycle

BlackOpsはInlineとDeferredを同じLifecycle Modelで記録します。ApplicationはOperation IDを相関Keyとして受付からTerminal Stateまで追跡できます。Outcome RecordはDeferred完了時だけ保存し、Inlineでは作成しません。

## 共通Lifecycle

正常完了するOperationはReceived、Running、Finalizing、Completedの順に進みます。DeferredだけはDurable受付後にAcceptedを経由します。

### 正常完了

<div class="archify-figure">

![operation.receivedでReceivedとなる。Inlineはattempt.startedでRunningへ進み、Deferredはoperation.acceptedによるAcceptedを経てRunningへ進む。attempt.succeededでFinalizing、operation.completedでTerminalのCompletedとなる。](assets/diagrams/lifecycle-success.png)

</div>

### 業務拒否

<div class="archify-figure">

![受付時のReceivedと実行中のRunningのどちらからも、operation.rejectedによってTerminalのRejectedへ進む。二つの経路を並べて示しており、通常のExceptionを業務拒否として扱う図ではない。](assets/diagrams/lifecycle-rejection.png)

</div>

### 失敗とRetry

<div class="archify-figure">

![Runningでattempt.failedが起きるとSupervisingへ進む。attempt.retry_scheduledはRetry Scheduledを経てattempt.startedでRunningへ戻る。operation.failedはFailedへ、Deferredのoperation.dead_letteredはDead Letterへ進む。Finalizingからoperation.failedでFailedとなる経路もある。FailedとDead LetterはTerminalで、Retryは同じOperation IDと新しいAttempt IDを使う。](assets/diagrams/lifecycle-failure.png)

</div>

失敗の図で再掲するSupervisingやFailedは同じ論理状態です。
独立した経路同士を順番に実行する意味ではありません。
FinalizingとSupervisingはLifecycleの処理段階であり、公開Status APIの値ではありません。


| 経路 | 状態遷移 |
| --- | --- |
| Inline成功 | Received → Running → Finalizing → Completed |
| Deferred成功 | Received → Accepted → Running → Finalizing → Completed |
| 業務拒否 | ReceivedまたはRunning → Rejected |
| Retry | Running → Supervising → Retry Scheduled → Running |
| 最終失敗 | Running → Supervising → Failed |
| Deferred隔離 | Running → Supervising → Dead Letter |

InlineはAcceptedを通らず、受付元のProcessで[Attempt](glossary.md#attempt)を開始します。DeferredだけがDurable受付後にAcceptedとなり、別ProcessのWorkerが[Claim](glossary.md#claim)してAttemptを開始します。Completed、Rejected、Failed、Dead LetterはTerminalであり、新しいLifecycle EventやHandler実行へ進みません。

## Rejected

`OperationRejectedException`は予期された業務拒否です。FrameworkはRejected ResultとTerminal Lifecycleへ変換します。Validation、Authorization、Not Found、Conflict、Business Ruleを安定したCategory／Codeで表現します。

## RetryとFailure

Retryable ExceptionはSupervision Policyに従い`attempt.failed`と`attempt.retry_scheduled`を記録します。次のWorker Attemptが同じOperationを再Claimします。Retry上限を超えた処理はFailed／[Dead Letter](glossary.md#dead-letter)へ進みます。

通常のException、Worker Interrupt、Claim Lossは業務拒否として扱いません。[Lease](glossary.md#lease)、[Heartbeat](glossary.md#heartbeat)、[Fencing Token](glossary.md#fencing-token)により、古いWorkerが成功を確定しないようにします。

## Outcome

DeferredのCompletedだけがTyped Outcomeを保存します。InlineはOutcome Recordを作成せず、HTTPではResponseへ、Operation用CLIでは`--json`指定時の完了JSONへOutcomeを返します。Rejected、Failed、Retry Scheduled、Dead Letter、Claim LostはOutcome Recordを作成しません。詳細は[Outcome](outcome-retrieval.md)を参照してください。

JournalとOutcomeは別々の保持期間を設定できます。Operation単位のHoldと安全なPurgeについては[Retention](retention.md)を参照してください。

Lifecycle EventのObserved JSONL、Canonical／Observedの境界、Observer Replayは[Journal](journal.md)で確認できます。

仕組みを理解したら[Install](installation.md)からApplicationを作成します。

## 次にContextの伝播を読む

状態遷移に付随するID、Actor、Tenant、Attemptは、[Execution Context](execution-context.md)で確認します。
