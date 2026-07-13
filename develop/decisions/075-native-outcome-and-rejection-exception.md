# D075: Native Outcome and Rejection Exception

Status: Decided

## Context

D074でTyped Self-handled OperationはValueとOptional `ExecutionContext` をNative Parameterとして受け取れるようになった。一方、成功時も利用者は `OperationResult::completed($outcome)` を返し、同じValue／Outcome Classを `#[Accepts]`／`#[Returns]` に重複記述している。

`handle()` の第一引数からAccepted Valueは一意に取得できる。成功時のNative Return Typeを具象 `Outcome` にすればOutcomeも一意に取得でき、IDE、Mago、Build Compilerが同じ型を正本として扱える。

Rejectedを含むすべての結果をNative Return Typeだけで表すにはUnionまたはGeneric Resultが必要だが、PHPにはNative Genericがない。すべての例外をRejectedへ変換すると、一時障害、Bug、Infrastructure障害が業務拒否として隠れ、Deferred Retry／Failed／Dead Letterの意味を壊す。

利用者は、成功時は具象Outcomeを返し、予期された業務拒否だけをFramework提供の明示的な例外で通知する方針を選択した。

## Decision

[DECISION]

1. Typed Self-handled Operationの標準Signatureは、具象 `OperationValue` を第一引数、Optional `ExecutionContext` を第二引数、具象 `Outcome` または `void` をNative Return Typeに持つ。

```php
public function handle(WelcomeValue $value): WelcomeShown;

public function handle(
    GenerateReportValue $value,
    ExecutionContext $context,
): ReportGenerated;
```

2. Build Compilerは第一引数からAccepted Value、Return Typeから成功Outcomeを推論し、Manifestへ保存する。`void` は `EmptyOutcome` として保存する。
3. 標準Typed Self-handled Operationは `#[Accepts]`、`#[Returns]`、`OperationResult::completed()` を記述しない。
4. Typed Self-handled Operationに `#[Accepts]`／`#[Returns]` が残っている場合は移行互換として受け入れるが、Native Signatureとの完全一致を必須とする。
5. Legacy Self-handled／Separate `OperationHandler` は既存 `OperationResult` とMetadata Attribute Contractを維持する。
6. FrameworkはPublicな `OperationRejectedException` を提供する。この例外は `RejectionReason` を保持し、Validation、Unauthorized、Forbidden、Not Found、Conflict、Business Ruleの安定Code Factoryを公開する。
7. Handlerが `OperationRejectedException` をthrowした場合、Framework Invocation Boundaryは `OperationResult::rejected()` へ変換し、既存Inline／Deferred Rejected Lifecycleへ接続する。
8. `OperationRejectedException` 以外の例外はRejectedへ変換しない。Retryable Exception、通常例外、Worker Interruptは既存Supervision／Failure Contractへ伝播する。
9. 具象Outcome ReturnはRuntimeでCompiled Outcome Classとの完全一致を検証し、`void` Returnは `EmptyOutcome` へ正規化する。
10. Union、Intersection、Nullable、Builtin Return、`OperationResult` 以外の非Outcome Class、Native Return Type欠落は標準Typed Signatureとして拒否する。
11. Existing Manifest Field名とSchema Versionは維持する。新しいInvocation ModeはOptional Fieldで表し、旧Artifactは既存Signature／Fieldから安全に復元・検証する。
12. `OperationResult`、`OperationHandler`、`Accepts`、`Returns` はPublic Legacy Compatibility APIとして直ちに削除しない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 標準OperationではValue／Outcome Classの重複AttributeとCompleted Wrapperが不要になる。
- IDEとMagoが成功OutcomeをNative Return Typeとして認識できる。
- `handle(): void` は値のない成功として既存 `EmptyOutcome` Lifecycleへ接続できる。
- 予期された拒否はFramework提供Exceptionを明示的にthrowし、システム障害は従来どおりSupervisionへ送られる。
- ExceptionをControl Flowに使う範囲は、Terminalな業務拒否を通知する単一のFramework Exceptionに限定される。
- Runtime内部の `OperationResult` はCompleted／Rejected Lifecycleの共通表現として維持される。
- D035の「すべてのHandlerがOperationResultを返す」、D052の標準Authoring Result、D074のTyped Self-handled Return／必須Accepts、D071の必須Metadata Attributeを、Typed Self-handled標準形に限って置き換える。
- Legacy／Separate Handlerの移行経路と既存Manifest互換を維持するため、CompilerとInvokerは複数Modeを明示的に扱う。

[/CONSEQUENCES]

## Supersedes

- D035 Handler Result Contractの標準Typed Self-handled Return部分
- D052 Handler Result Public APIの標準Typed Self-handled Authoring部分
- D071 Operation Authoring and Discoveryの必須 `#[Accepts]`／`#[Returns]` 部分
- D074 Typed Self-handled Operation SignatureのReturn Typeと必須 `#[Accepts]` 部分

Legacy Self-handled／Separate `OperationHandler` の互換Contractは維持する。

## References

- [D035 Handler Result Contract](035-handler-result-contract.md)
- [D052 Handler Result Public API](052-handler-result-public-api.md)
- [D071 Operation Authoring and Discovery](071-operation-authoring-and-discovery.md)
- [D074 Typed Self-handled Operation Signature](074-typed-self-handled-operation-signature.md)
- [Native Outcome Invocation](../spec/54-native-outcome-and-rejection-exception.md)
