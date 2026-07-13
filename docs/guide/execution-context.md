# Execution Context

`ExecutionContext`はOperationの追跡情報を保持するRead-onlyなPublic APIです。Operationが必要とする場合だけTyped Self-handled `handle()`の第二引数へ指定します。

```php
use BlackOps\Core\ExecutionContext;

public function handle(ProcessPaymentValue $value, ExecutionContext $context): PaymentProcessed
{
    $operationId = $context->operationId();
    $correlationId = $context->correlationId();
    $attempt = $context->attempt();

    return new PaymentProcessed($operationId->toString());
}
```

## 読み取れる情報

- `operationId()`: 現在のOperation ID
- `receivedAt()`: 受付時刻。UTCへ正規化される
- `correlationId()`: 関連OperationをまとめるCorrelation ID
- `causationId()`: 原因となるOperationがある場合のID
- `attempt()`: Deferred Attempt。Inlineでは`null`
- `deadline()`: Deadlineが構成されている場合のUTC時刻

ContextはFrameworkが生成し、ApplicationはGetterで読み取ります。公開`with...()` Methodはありません。

## InlineとDeferred

Inline ContextにもOperation IDがありますが、Deferred Claimではないため`attempt()`は`null`です。Deferred Workerでは現在のAttempt Numberや開始情報を`AttemptContext`から読めます。

OperationがContextを使わない場合は第二引数を省略してください。第一引数は常に具象`OperationValue`であり、Contextだけを受け取るSignatureはBuildで拒否されます。
