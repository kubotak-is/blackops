# D051: Operation Envelope and Strategy API

Status: Decided

## Context

D003とD024により、OperationEnvelopeはOperation Definition、OperationValue、ExecutionContext、Execution Strategyを保持する不変Value Objectと決まっている。一方、Execution StrategyのPHP型、Inline Strategyの型、EnvelopeのConstructorとGetter Signatureは未確定である。

PHPにはpackage-privateまたはfriend classがないため、ExecutionContextと同様に、利用者から呼出可能な生成APIを非公開Contractとして扱う設計は採用しない。

## Decision

[DECISION]

Execution StrategyはMethodを持たないPHP Public API Marker Interfaceとする。

```php
namespace BlackOps\Core\Execution;

#[PublicApi]
interface ExecutionStrategy
{
}
```

Phase 1では既定Strategyとして次を提供する。

```php
#[PublicApi]
final readonly class Inline implements ExecutionStrategy
{
}
```

Deferred Strategy、`ExecuteWith` Attribute、Strategy解決は後続Taskで実装する。

OperationEnvelopeは次のPHP Public APIとする。

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
    public function id(): OperationId;
    public function receivedAt(): \DateTimeImmutable;
}
```

ConstructorはPublic APIとし、保持した値を変更する `with...()` Methodは設けない。`id()` と `receivedAt()` は状態を重複保持せずExecutionContextへ委譲する。

Operation DefinitionとOperationValueのAttribute上の対応検証は、AttributeとRegistryを実装する後続Taskで行う。Envelope自身はPHP型としてのOperation、OperationValue、ExecutionContext、ExecutionStrategyだけを保証する。

[/DECISION]

## Consequences

- Inline Vertical Sliceが具体的なStrategy型をEnvelopeへ保持できる。
- 将来の論理StrategyをExecutionStrategy実装として追加できる。
- EnvelopeのValue具体型をPHPDoc GenericでStatic Analysisへ伝えられる。
- 識別情報の正本はExecutionContextだけに維持される。
