# Execution

## Execution Strategy

Operationの配送と実行方法は固定enumではなくExecution Strategyとして表現する。

Attribute未指定時は、受け付けたプロセス内で実行するInline Strategyを使用する。Operationは `#[ExecuteWith(...)]` で別の論理Strategyを指定できる。

```php
#[ExecuteWith(Deferred::class)]
final class AddFavorite implements Operation
{
}
```

初期候補はInlineとDeferredとする。Batch、Coalesce、Scheduledは将来拡張として扱う。

Execution StrategyはMethodを持たない `BlackOps\Core\Execution\ExecutionStrategy` Marker Interfaceとする。既定のInline実行は `BlackOps\Core\Execution\Inline` で表し、いずれも `#[PublicApi]` を付ける。

Deferred Strategy、`ExecuteWith` Attribute、Strategy解決は後続Taskで実装する。

## Execution Transport

OperationはSQS、Kafka、RDBなどの具体的Infrastructureを指定しない。論理StrategyとExecution Transportの対応はConfigで定義する。

```text
Deferred
  -> production: SQS Transport
  -> local: Database Transport
  -> test: InMemory Transport
```

Execution Transportは未完了Operationの安全な配送を担う。正常終了後は削除またはAcknowledgementによって実行キューから除去する。

Journal ObserverとExecution Transportは正規Journal形式を共有するが、信頼性要件が異なるため別インターフェースとする。

## Deferredの受付

Deferred Operationの受付応答だけを生成する同期Handlerは設けない。FWはExecution Transportへの配送成功後にAcknowledgementを生成する。

受付時のAuthorization Policyまたは受付処理がOperation成立後かつAttempt開始前に予期せず失敗した場合、受付TransactionをRollbackし、別Transactionで`operation.received -> operation.failed`を記録する。Transport Row、Attempt、Outcome、Dead Letterは作らず、最初のThrowableをPrimary Failureとして維持する。

同期処理と非同期後続処理が独立した業務責務を持つ場合、同期Operationから別のDeferred Operationを発行する。

## Supervision Policy

Execution Strategyごとの既定Supervision PolicyをConfigで設定する。Operationは `#[SupervisedBy(...)]` によって上書きできる。

Policyは例外とAttempt Contextを受け取り、Retry、Fail、Dead Letterのいずれかを返す。

```php
interface SupervisionPolicy
{
    public function decide(
        Throwable $error,
        AttemptContext $attempt,
    ): SupervisionDecision;
}
```

### Inline

既定では自動Retryしない。例外をFailure Responseへ変換する。必要なOperationだけ専用PolicyでRetryを許可できる。

Attempt開始後のAuthorization、Handler Resolution、Transaction、Handler Invocationで予期しないThrowableが発生した場合は、同じOperation IDとAttempt IDで`attempt.failed -> operation.failed`へTerminal化する。Transactional OperationはApplication TransactionをRollbackしてからFailure Journalを別Transactionで記録し、RollbackまたはJournal記録の二次障害でPrimary Throwableを置き換えない。

### Deferred

Retry可能な例外を、上限回数付き指数BackoffとJitterでRetryする。

既定値は最大3 Attempt、初期Delay 1秒、倍率2.0、最大Delay 60秒、Jitter ±20% とする。Attempt Timeoutは後続のConfig仕様で定義する。

Retry不能または上限到達したOperationはDead Letter Transportへ移し、`OperationDeadLettered` を記録する。手動Replayは新しいOperation IDで行う。

Dead Letterへ隔離せず最終失敗させる場合は、`OperationFailed` を記録する。

## 冪等性

FWはOperation IDを使ったInbox/Deduplication機構を提供する。

FWはExactly Onceを保証しない。Handlerには冪等な設計を推奨し、外部副作用にはIdempotency Keyの利用を求める。

## Coalesce

複数のDeferred Operationを一定期間で集約し、業務DBへの反映をまとめる機能を将来検討する。集約後も個々のOperationをJournal上で追跡可能にする。
