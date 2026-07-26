# Implicit Inline Ephemeral Outcome

## Purpose

`EphemeralOutcome`を返すHTTP Operationへ、既定Strategyと同じ`#[ExecuteWith(Inline::class)]`を重複記述させない。Credential非永続化境界を維持したまま、Canonical Authoringを「AttributeなしはInline、Deferredだけ`#[Deferred]`」へ統一する。

## Canonical Authoring

```php
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Http\Attribute\Route;

final readonly class CredentialIssued implements EphemeralOutcome
{
    public function __construct(
        #[Sensitive]
        public string $token,
    ) {}
}

#[OperationType('identity.credential.issue')]
#[Route('POST', '/credentials')]
final readonly class IssueCredential implements Operation
{
    public function handle(IssueCredentialValue $value): CredentialIssued
    {
        // ...
    }
}
```

`EphemeralOutcome` MarkerはOutcomeのPersistence／Projection Contractを示す。Execution Strategy Attributeを省略したOperationは通常どおりInlineへ正規化されるため、Ephemeral Operationだけ明示Inlineを要求しない。

## Compiler Contract

Declared Outcomeが`EphemeralOutcome`を実装する場合、Compilerは次を検証する。

- exactly one HTTP `#[Route]`を持つ
- 解決後Execution Strategyが`BlackOps\Core\Execution\Inline`である
- `#[ConsoleCommand]`を持たない
- Ephemeral Outcome ShapeとSensitive Property Contractを満たす

次は受理する。

- Execution Strategy Attributeを省略した暗黙Inline
- 互換用`#[ExecuteWith(Inline::class)]`

次は拒否する。

- `#[Deferred]`
- `#[ExecuteWith(Deferred::class)]`
- Inline以外のCustom Strategy
- Routeなし、Route複数
- `#[ConsoleCommand]`との併置

Compiler Errorは「明示Inlineがないこと」ではなく、実際に違反したStrategy、Route、Console境界を示す。RuntimeはSource ReflectionへFallbackせず、Compile済みManifestのInline Strategyだけを使う。

## Compatibility

- `ExecuteWith` Public APIは削除しない。
- Existing `#[ExecuteWith(Inline::class)]`は同じManifestを生成する。
- Existing Deferred／Custom StrategyのCompile規則を変更しない。
- Manifest Schema、Operation Type、Journal Strategy Identity、HTTP Status／Body、Outcome Store、Status API、Generated Frontend Objectを変更しない。
- Stable `1.1.0` Artifactを変更しない。

## Source Synchronization

Canonical Sourceから次を削除する。

- Ephemeral Operationの`#[ExecuteWith(Inline::class)]`またはliteral class-string
- その指定だけに必要だった`ExecuteWith`／`Inline` Import
- 「Ephemeralは明示Inline必須」「暗黙InlineはBuild Error」というGuide記述

対象は少なくともAuth Generator Stub、Community Board Identity、Auth Consumer Fixture、Ephemeral Compiler／Frontend／PostgreSQL Fixture、Authentication／Operation／Attribute／Core API Guideを含む。

## Verification

- 暗黙InlineのTyped／Legacy Ephemeral OperationがCompileできる
- Explicit Inline CompatibilityがCompileできる
- Deferred、Custom Strategy、Routeなし、Consoleを拒否する
- Auth Generatorが`ExecuteWith`なしのStarterを生成し、Fresh Consumerが完走する
- Community BoardのRegister／Login／LogoutがBuild／HTTP Journeyを維持する
- Ephemeral OutcomeがJournal／Observer／Outcome Store／Status／Frontendへ残らない
- Website Source／ArtifactへEphemeralの明示Inline必須説明が残らない

## Traceability

- Decision: [D126 Implicit Inline Ephemeral Outcome](../decisions/126-implicit-inline-ephemeral-outcome.md)
- Authoring: [Specification 50](50-operation-authoring-and-build-discovery.md)
- Execution: [Specification 82](82-operation-dispatch-and-deferred-authoring.md)
