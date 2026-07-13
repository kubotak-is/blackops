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

## Identifierの関係

```mermaid
flowchart TB
    accTitle: Operationと追跡Identifierの関係
    accDescr: Root OperationはOperation IDと同じUUID値で別型のCorrelation IDを開始する。RetryごとのAttemptは固有のAttempt IDを持つ。子OperationはCorrelation IDを引き継ぎ、親Operation IDと同じ値をCausation IDとして持つ。
    Root["Root Operation<br/>Operation ID: O1<br/>Correlation ID: O1と同じ値"]
    Attempt1["Attempt 1<br/>Attempt ID: A1"]
    Attempt2["Attempt 2<br/>Attempt ID: A2"]
    Child["Child Operation<br/>Operation ID: O2<br/>Correlation ID: O1と同じ値<br/>Causation ID: O1と同じ値"]
    ChildAttempt["Attempt 1<br/>Attempt ID: A3"]
    Root -->|一回目の実行| Attempt1
    Root -->|Retry| Attempt2
    Root -->|新しい意図を発生| Child
    Child -->|子の実行| ChildAttempt
```

### 図のテキスト代替

| Identifier | 関係 |
| --- | --- |
| Operation ID | Operationごとに一つ発行し、Retryしても変わりません。 |
| Attempt ID | Handlerを実行するAttemptごとに新しく発行します。 |
| Correlation ID | Rootと子Operationを同じTraceへまとめ、子へ引き継ぎます。RootではOperation IDと同じUUID値を別の型で保持します。 |
| Causation ID | 子Operationを発生させた親Operation IDと同じUUID値を別の型で保持します。Rootでは`null`です。 |

Identifierは同じUUID値を共有する場合でも別のPHP型です。Operation IDをCorrelation IDやCausation IDの引数へそのまま渡すことはできません。RetryではOperation IDとCorrelation IDを維持し、Attempt IDだけが変わります。

## InlineとDeferred

Inline ContextにもOperation IDがありますが、Deferred Claimではないため`attempt()`は`null`です。Deferred Workerでは現在のAttempt Numberや開始情報を`AttemptContext`から読めます。

OperationがContextを使わない場合は第二引数を省略してください。第一引数は常に具象`OperationValue`であり、Contextだけを受け取るSignatureはBuildで拒否されます。
