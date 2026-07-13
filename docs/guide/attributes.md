# Attributes Reference

BlackOpsはOperation、HTTP Binding、Observed Journal ProjectionのMetadataをPHP Attributeで宣言します。このPageは`src/Core/Attribute/`と`src/Http/Attribute/`にある利用者向けPublic Attribute 11件をSourceと照合しています。`PublicApi` marker自身はFrameworkが公開境界を管理するためのMetadataであり、Application Authoringには使いません。

## Operation Attributes

| Attribute | 用途 | 付与対象 | 最小例 | Typed Self-handled標準形 |
| --- | --- | --- | --- | --- |
| `BlackOps\Core\Attribute\OperationType` | 永続的なdot-separated Operation Type IDを宣言する | Operation Class | `#[OperationType('order.place')]` | 必須 |
| `BlackOps\Core\Attribute\ExecuteWith` | Inline以外のExecution Strategyを選ぶ | Operation Class | `#[ExecuteWith(Deferred::class)]` | Deferred時だけ必要。省略時はInline |
| `BlackOps\Core\Attribute\HandledBy` | Separate Handler Classを指定する | Operation Class | `#[HandledBy(PlaceOrderHandler::class)]` | 不要。Separate Handler互換形だけで使う |
| `BlackOps\Core\Attribute\Accepts` | Accepted `OperationValue`を明示する | Operation Class | `#[Accepts(PlaceOrderValue::class)]` | 不要。第一引数から推論する |
| `BlackOps\Core\Attribute\Returns` | `Outcome` Classを明示する | Operation Class | `#[Returns(OrderPlaced::class)]` | 不要。Return Typeから推論する |
| `BlackOps\Core\Attribute\Sensitive` | Observed ProjectionでPropertyをOmit／Mask／Hashする | `OperationValue` Property | `#[Sensitive(SensitiveMode::Mask)]` | Sensitive Propertyだけで使う |

Typed Self-handled標準形では、`handle(ConcreteValue $value): ConcreteOutcome`のNative Signatureを正本にします。`#[Accepts]`／`#[Returns]`を併記した場合は推論型との完全一致が必要です。新しい単純なOperationへは追加しないでください。

`#[HandledBy]`はDecorator、複数実装切替等でOperation DefinitionとHandlerを分けるCompatibility形に限って使います。Typed Self-handled `handle()`と同時に指定するとBuildがAmbiguousとして拒否します。

### Sensitive Mode

`BlackOps\Core\Attribute\SensitiveMode`はAttributeではなく、`#[Sensitive]`のModeを選ぶPublic enumです。

```php
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;

final readonly class PlaceOrderValue implements OperationValue
{
    public function __construct(
        #[Sensitive(SensitiveMode::Mask)]
        public string $apiToken,
    ) {}
}
```

`Omit`はFieldを除外し、`Mask`は`[masked]`へ置換し、`Hash`は一方向Digestへ置換します。どのModeも認証、認可、暗号化、Access Control、Retentionを代替しません。

## HTTP Attributes

| Attribute | 用途 | 付与対象 | 最小例 | Typed Self-handled標準形 |
| --- | --- | --- | --- | --- |
| `BlackOps\Http\Attribute\Route` | HTTP MethodとPathをOperationへ結び付ける | Operation Class | `#[Route(method: 'POST', path: '/orders')]` | HTTP公開時に必要 |
| `BlackOps\Http\Attribute\FromBody` | JSON Body FieldをValue Constructor引数／PropertyへBindする | ParameterまたはProperty | `#[FromBody('customerId')]` | Body Field名とProperty名が異なる場合に指定。`null`で同名 |
| `BlackOps\Http\Attribute\FromHeader` | HTTP HeaderをValueへBindする | ParameterまたはProperty | `#[FromHeader('Idempotency-Key')]` | Header Inputで使う |
| `BlackOps\Http\Attribute\FromPath` | Route Path ParameterをValueへBindする | ParameterまたはProperty | `#[FromPath('orderId')]` | Path Parameterで使う |
| `BlackOps\Http\Attribute\FromQuery` | Query ParameterをValueへBindする | ParameterまたはProperty | `#[FromQuery('page')]` | Query Parameterで使う |

`FromBody`、`FromHeader`、`FromPath`、`FromQuery`の名前を省略するとProperty／Parameter名を使います。一つのValueへ複数の入力元を無秩序に混在させず、Route Contractが読み取れる形にしてください。

## Typed標準形の全体例

```php
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/orders')]
#[OperationType('order.place')]
final readonly class PlaceOrder implements Operation
{
    public function handle(PlaceOrderValue $value): OrderPlaced
    {
        return new OrderPlaced($value->orderId);
    }
}
```

この標準形には`#[Accepts]`、`#[Returns]`、`#[HandledBy]`がありません。BuildがSignatureからValue、Outcome、Handlerを確定します。Authoringの詳細は[Operation Authoring](operations.md)、Security境界は[SecurityとSensitive Data](security.md)を確認してください。
