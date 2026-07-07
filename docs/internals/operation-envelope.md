# Operation Envelope

`BlackOps\Core\OperationEnvelope` は、Operation Definition、型付けされたOperationValue、ExecutionContext、ExecutionStrategyを一つの不変値としてHandlerへ渡す。

Envelopeは `final readonly class` であり、保持した値を変更するMethodを提供しない。ConstructorはPHP Public APIで、具体的なOperationValue型はPHPDoc Genericで表現する。

```php
/**
 * @template-covariant TValue of OperationValue
 */
final readonly class OperationEnvelope
{
    /** @param TValue $value */
    public function __construct(
        Operation $definition,
        OperationValue $value,
        ExecutionContext $context,
        ExecutionStrategy $strategy,
    );
}
```

Operation IDと受付時刻はEnvelopeへ複製しない。`id()` と `receivedAt()` はExecutionContextへ委譲する。

## Execution Strategy

`ExecutionStrategy` は論理的な配送・実行方法を表すMarker Interfaceである。Phase 1の既定Strategyは、同じProcess内で実行する `Inline` とする。

StrategyはSQSやDatabaseなどの具体的なTransportを表さない。Deferred StrategyとTransportの対応、AttributeによるStrategy選択は後続実装で扱う。

## Invariants

- Definitionは `Operation` を実装する
- Valueは `OperationValue` を実装する
- Strategyは `ExecutionStrategy` を実装する
- 識別情報の正本はExecutionContextだけに置く
- DefinitionとValueの宣言上の対応はRegistry構築時に検証する
