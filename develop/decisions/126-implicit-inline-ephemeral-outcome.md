# D126: Implicit Inline Ephemeral Outcome

Status: Decided

## Context

OperationはExecution Strategy Attributeを省略した場合にInlineとしてCompileされる。一方、Declared Outcomeが`EphemeralOutcome`の場合だけは、HTTP Routeに加えて次の明示指定を要求している。

```php
#[ExecuteWith(Inline::class)]
```

この指定は、Credential等のEphemeral OutcomeをDeferred、Console、Routeなしの経路へ誤って公開しないためのGuardとして導入した。しかしOutcome TypeからEphemeral Contractは一意に判定でき、Attributeなしも既にInlineへ正規化される。安全性のために必要なのは「最終StrategyがInlineであること」であり、同じ情報を利用者へ二重に記述させることではない。

Auth Generator Stub、Reference Application、Consumer Fixture、Guideへ`ExecuteWith`のliteralまたはclass-string指定が残り、Canonicalな「AttributeなしはInline、Deferredだけ`#[Deferred]`」というAuthoring Modelを分かりにくくしている。

## Decision

[DECISION]

1. Declared Outcomeが`EphemeralOutcome`で、Execution Strategy Attributeを持たないOperationは、既定のInline Strategyとして受理する。
2. Ephemeral Operationは引き続きexactly one HTTP `#[Route]`を必須とし、`#[ConsoleCommand]`、`#[Deferred]`、Inline以外の`#[ExecuteWith(...)]`を拒否する。
3. Existing `#[ExecuteWith(Inline::class)]`は後方互換として受理するが、Canonical Authoringには使用しない。
4. 新しい`#[Inline]`または`#[Ephemeral]` Attributeは追加しない。`EphemeralOutcome` Markerと既定Inlineで意図を表現する。
5. Auth Generator、Reference Application、Consumer Fixture、Reader-facing GuideからEphemeral Operationの明示Inline指定と不要Importを削除する。
6. Manifest Strategyは従来どおり`BlackOps\Core\Execution\Inline`へ正規化し、Manifest Schema、Journal、Outcome Store、Frontend Contract、HTTP Responseの境界を変更しない。
7. Stable `1.1.0`の配布物は変更せず、本DecisionはRepository `main`の次Release Contractとして扱う。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Credential Operationは`#[Route]`、`#[OperationType]`、Typed `handle()`だけで安全なInline Ephemeral Operationとして記述できる。
- Security GuardはStrategy Attributeの有無ではなく、解決後Strategy、Route、Console Metadataで維持される。
- 既存Applicationの明示Inlineは壊れない。
- `ExecuteWith`はCustom Strategyと後方互換のPublic APIとして残るが、標準的なInline／Deferred Authoringからは不要になる。

[/CONSEQUENCES]

## References

- [D115 Deferred Authoring and Operation Dispatch](115-deferred-authoring-and-operation-dispatch.md)
- [Specification 50 Operation Authoring and Build Discovery](../spec/50-operation-authoring-and-build-discovery.md)
- [Specification 82 Operation Dispatch and Deferred Authoring](../spec/82-operation-dispatch-and-deferred-authoring.md)
- [Specification 93 Implicit Inline Ephemeral Outcome](../spec/93-implicit-inline-ephemeral-outcome.md)
