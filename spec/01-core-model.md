# Core Model

## Operation

Operationは、要求の受付から最終結果が確定するまで続く論理的な処理単位である。

利用者が定義するOperationクラスは業務入力を保持せず、処理の定義とAttributeを持つOperation Definitionとする。初期設計ではCommandとQueryを型として区別しない。

```php
#[OperationType('order.create')]
#[Accepts(CreateOrderValue::class)]
#[HandledBy(CreateOrderHandler::class)]
#[Returns(OrderCreated::class)]
#[Route(method: 'POST', path: '/orders')]
final class CreateOrder implements Operation
{
}
```

## OperationValue

OperationValueは、Operation Definitionとは別の、型付けされた読み取り専用の業務入力である。

Operation DefinitionとValue型は `#[Accepts(...)]` で関連付ける。

```php
final readonly class CreateOrderValue implements OperationValue
{
    public function __construct(
        #[NotBlank]
        public string $customerId,
        #[Count(min: 1)]
        public array $items,
    ) {
    }
}
```

## Operation Envelope

Operation Envelopeは、一回の論理的なOperationを表す読み取り専用DTOである。

少なくとも次を内包する。

- Operation ID
- Operation受付時刻
- Operation Definition
- OperationValue
- ExecutionContext
- Execution Strategy

Handlerが受け取る引数はOperation Envelope一つだけとする。PHPDoc GenericによってValueの具体型を静的解析可能にする。

```php
/**
 * @param OperationEnvelope<CreateOrderValue> $operation
 */
public function handle(OperationEnvelope $operation): OperationResult
{
    $value = $operation->value();
}
```

## 識別子

### Operation ID

FWがUUIDv7として発行し、一つのOperationの生涯を識別する。呼び出し元による重複防止用のIdempotency Keyとは分離する。

### Attempt ID

Handlerによる一回の実行試行を識別する。Retry時もOperation IDは維持し、新しいAttempt IDを発行する。

### Correlation ID

関連する複数Operationから成る一連の処理を識別する。Root OperationではRootのOperation IDから初期化する。

### Causation ID

子Operationを直接発生させた親Operation IDを表す。

```text
CreateOrder
  operationId: op-1
  correlationId: op-1
  causationId: null

SendNotification
  operationId: op-2
  correlationId: op-1
  causationId: op-1
```

Handlerは別のOperationを発行できる。子には新しいOperation IDを発行し、Correlation IDを維持し、Causation IDへ親Operation IDを設定する。Sagaや補償処理は初期スコープに含めない。

## ExecutionContext

ExecutionContextは、Operationの追跡、連鎖、冪等性、実行制御に使う読み取り専用Metadataである。

コアフィールド：

- Operation ID
- Attempt ID
- Correlation ID
- Causation ID
- Operation受付時刻
- Attempt開始時刻

Optional要素：

- Actor
- Tenant
- Idempotency Key
- Deadline
- 登録済みContext Extension

### Actor

Actor情報は、安定したActor ID、Actor Type、許可された最小限の属性だけを保持する。Password、Session、Bearer TokenなどのCredentialは保持・Journal記録・子Operationへの伝播を禁止する。

### Context Extension

アプリケーション独自Metadataは任意配列ではなく、型付けされたContext Extensionとして登録する。ExtensionはSerializer、伝播Policy、Sensitive Policyを定義できるようにする。

### Deadline

OperationはOptionalな絶対時刻のDeadlineを持てる。期限超過時は新しいAttemptを開始しない。子OperationのDeadlineは親より後の時刻にできない。
