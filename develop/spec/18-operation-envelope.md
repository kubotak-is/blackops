# Operation Envelope

## 型と責務

`OperationEnvelope<TValue>` はFrameworkが生成する `final readonly class` とする。

Envelopeは拡張点ではなく、Operation Definition、OperationValue、ExecutionContext、Execution Strategyの正しい組み合わせを保証する不変Value Objectである。

アプリケーション固有MetadataはEnvelopeの継承ではなくExecutionContext Extensionで扱う。

## Public API

保持する値はPrivate readonly Propertyとし、Getter Methodで公開する。

```php
$operation->definition();
$operation->value();
$operation->context();
$operation->strategy();
```

`OperationEnvelope` はPHPDoc GenericによってOperationValueの具体型を表す。

```php
/**
 * @template-covariant TValue of OperationValue
 */
#[PublicApi]
final readonly class OperationEnvelope
{
    /**
     * @param TValue $value
     */
    public function __construct(
        Operation $definition,
        OperationValue $value,
        ExecutionContext $context,
        ExecutionStrategy $strategy,
    );

    public function definition(): Operation;

    /** @return TValue */
    public function value(): OperationValue;

    public function context(): ExecutionContext;
    public function strategy(): ExecutionStrategy;
}
```

PHPにはpackage-privateまたはfriend classがないためConstructorをPublic APIとする。公開 `with...()` Methodは提供しない。DefinitionとValueのAttribute上の対応検証はRegistry構築時に行う。

## 識別情報

Operation ID、受付時刻、Correlation ID等はExecutionContextだけに保持し、ExecutionContextを正本とする。

MVPでは頻出する値に限り、ExecutionContextへ委譲するConvenience Methodを提供する。

```php
$operation->id();         // context()->operationId()
$operation->receivedAt(); // context()->receivedAt()
```

Envelope自身は識別情報の状態を重複して保持しない。
