# Operationを実装する

BlackOpsの標準Authoringは、Applicationが実行したい一つの意図である[Operation](glossary.md#operation)自身がNative Typed `handle()`を持つTyped Self-handled形式です。Value、Outcome、Optional ContextをPHP Signatureで宣言し、BuildがMetadataとHandler登録を生成します。

## 標準形

```php
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Operation;

#[OperationType('order.place')]
final readonly class PlaceOrder implements Operation
{
    public function __construct(
        private OrderRepository $orders,
    ) {}

    public function handle(PlaceOrderValue $value): OrderPlaced
    {
        $order = $this->orders->place($value->customerId, $value->items);

        return new OrderPlaced($order->id());
    }
}
```

第一引数は具象Classで`OperationValue`を実装し、Return Classは具象Classで`Outcome`を実装します。ValueとOutcomeはSignatureから推論されるため、標準形では`#[Accepts]`、`#[Returns]`、`OperationHandler`、Generic DocBlockを追加しません。

OperationはContainerへAutowireされます。Repository Interface等のConstructor DependencyはApplicationのService ProviderからBindingします。OperationをOperation Providerへ手動列挙する必要はありません。

## Contextが必要なOperation

Operation IDやDeferred Attemptが必要な場合だけ、第二引数へ`ExecutionContext`を指定します。

```php
use BlackOps\Core\ExecutionContext;

public function handle(GenerateReportValue $value, ExecutionContext $context): ReportGenerated
{
    $attempt = $context->attempt();

    return new ReportGenerated($value->reportName, $context->operationId()->toString());
}
```

InlineではAttemptが`null`、Deferred Workerでは現在のAttemptが入ります。Contextの詳細は[Execution Context](execution-context.md)を参照してください。

## 値のない成功

処理結果のValueが不要な場合は`void`を返します。

```php
public function handle(RebuildIndexValue $value): void
{
    $this->indexes->rebuild($value->name);
}
```

Frameworkは成功時に`EmptyOutcome`へ正規化します。

## 予期された業務拒否

ValidationやBusiness Ruleによる予期された拒否だけを`OperationRejectedException`で通知します。

```php
use BlackOps\Core\Exception\OperationRejectedException;

if (!$this->inventory->isAvailable($value->items)) {
    throw OperationRejectedException::conflict('inventory_unavailable');
}
```

利用できるCategory Factoryは`validation`、`unauthorized`、`forbidden`、`notFound`、`conflict`、`businessRule`です。Codeは安定した識別子とし、Credentialや自由文Payloadを含めません。

その他のThrowableはRejectedへ変換されません。一時障害はRetryable Exception、BugやInfrastructure FailureはFailure Policyへ渡されます。

## Separate Handler

Decoratorや複数実装の切替等で責務を分ける場合は、`#[HandledBy]`と`OperationHandler`を使うCompatibility形を選べます。新しい単純なUse CaseではTyped Self-handledを優先してください。

Sourceを追加したら`php blackops blackops:build:compile`でSignatureとMetadataを検証します。Generatorを利用する場合は[Operation Generator](project-generators.md)を参照してください。
