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

## Credentialを一度だけ返す

LoginやToken Rotationのように、成功値をHTTP Clientへ返す一方でJournalやOutcome Storeへ残したくない場合は`EphemeralOutcome`を使います。Ephemeralは「そのHTTP Response中だけ有効な投影」を意味し、Lifecycleそのものを隠す機能ではありません。

```php
<?php

declare(strict_types=1);

namespace App\Feature\Identity\IssueCredential;

use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Http\Attribute\FromBody;
use BlackOps\Http\Attribute\Route;

final readonly class IssueCredentialValue implements OperationValue
{
    public function __construct(
        #[FromBody]
        public string $email,
        #[FromBody]
        #[Sensitive]
        public string $password,
    ) {}
}

final readonly class CredentialIssued implements EphemeralOutcome
{
    public function __construct(
        #[Sensitive]
        public string $token,
        public string $expiresAt,
    ) {}
}

#[OperationType('identity.credential.issue')]
#[Route('POST', '/credentials')]
#[ExecuteWith(Inline::class)]
final readonly class IssueCredential implements Operation
{
    public function __construct(private CredentialService $credentials) {}

    public function handle(IssueCredentialValue $value): CredentialIssued
    {
        return $this->credentials->issue($value->email, $value->password);
    }
}
```

このOperationにはHTTP Routeと明示的なInline Strategyが必要です。Deferred、Console、Routeなし、暗黙のInlineはBuild Errorになります。Credential名を持つOutcome Propertyには`#[Sensitive]`を付け、CredentialをNested DTOへ隠さずRoot Propertyとして宣言してください。

HTTPは`CredentialIssued`をJSON 200で一度返します。PropertyがないEphemeral Outcomeは`{}`を返します。Canonical JournalにはReceivedを空Data、Completedを`EmptyOutcome`として記録し、Outcome StoreへRowを作りません。Status APIは認可後もUnavailableとなり、Generated Frontend Objectには`.fetch()`、`.toRequest()`、`.url()`だけを生成して`.status()`と`.wait()`を公開しません。

PHPからInline Dispatcherを直接呼ぶ場合は実Outcomeを受け取れますが、FrameworkのJournal、Observer、Status、Console、Deferred経路から復元できません。認証・認可、Cookie、CSRF、暗号化、Browser Storage、Token Rotationは引き続きApplicationが設計します。

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

Sourceを追加したら`php blackops build:compile`でSignatureとMetadataを検証します。Generatorを利用する場合は[Operation Generator](project-generators.md)を参照してください。
