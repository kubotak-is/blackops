# Typed Self-handled Operation Invocation

## Authoring Contract

標準Self-handled Operationは `Operation` だけを実装し、Public `handle()` Methodを持つ。

```php
#[OperationType('welcome.show')]
final readonly class ShowWelcome implements Operation
{
    public function handle(WelcomeValue $value): WelcomeShown
    {
        return new WelcomeShown('Welcome to BlackOps');
    }
}
```

Execution Contextが必要な場合だけ第二引数を指定する。

```php
public function handle(
    GenerateReportValue $value,
    ExecutionContext $context,
): ReportGenerated {
    $operationId = $context->operationId();
    $attempt = $context->attempt();

    return new ReportGenerated($operationId, $attempt);
}
```

Execution ContextはInline／Deferredの両方で渡される。InlineではAttemptがnullであり、Deferred Handler実行時はAttemptを持つ。

## Signature Validation

Build CompilerはTyped Self-handled Operationの `handle()` を次の順で検証する。

- PublicかつNon-static
- Parameter数は1または2
- 第一引数はNamed Class Type、Required、Non-reference、Non-variadic
- 第一引数Classは `OperationValue` を実装
- 第一引数ClassをAccepted Valueとして推論する
- 第二引数がある場合はNamed `ExecutionContext`、Required、Non-reference、Non-variadic
- Native Return Typeは具象 `Outcome` Classまたは `void`

Untyped、Builtin、Union、Intersection、Nullable、Default Value、余分なParameterは拒否する。ErrorはOperation／Handler Classと不正なSignature責務を示し、CredentialやPayloadを含めない。

## Invocation Modes

Compiled MetadataのHandlerは次のいずれかである。

### Typed Self-handled

- DefinitionとHandler Classが同じ
- Definitionは `Operation`
- Handlerは上記Typed Signatureを持つ
- Containerから同じInstanceをDefinition／Handlerとして解決する
- Framework InvokerがEnvelope Valueと必要な場合のContextを渡す

### Legacy Self-handled

- DefinitionとHandler Classが同じ
- Classは `Operation` と `OperationHandler` を実装
- Existing `handle(OperationEnvelope)` を呼ぶ

### Separate Handler

- Definitionは `#[HandledBy]` を持つ
- Handlerは既存 `OperationHandler` を実装
- Existing `handle(OperationEnvelope)` を呼ぶ

Typed Self-handledと `#[HandledBy]` の同時指定はAmbiguousとして拒否する。Typed Separate Handlerはこの仕様の対象外とする。

## Runtime Boundary

Runtime Handler ResolverはCompiled ContainerからHandler Objectを解決する。InvokerはMetadataとObjectを受け、次を行う。

1. Handler ClassとCompiled Metadataの一致確認
2. Envelope ValueがMetadata Value Classと一致することを確認
3. Legacy `OperationHandler` はEnvelopeで呼ぶ
4. Typed Self-handledはValue、またはValue＋Contextで呼ぶ
5. Native Outcome／VoidをCompleted Resultへ正規化する
6. `OperationRejectedException` をRejected Resultへ正規化する
7. その他Throwableは変換せず再throwする

Source Discoveryは行わない。Signature ReflectionはBuildとManifest Validationで正本化し、RuntimeはCompiled Handler Classの安全なInvocationに限定する。

## Manifest and Container

Operation Manifest FormatのHandler Fieldは維持する。Handler FieldのPHPDocはLegacy Interfaceだけに限定せず、検証済みHandler Classを表す。

Manifest Decodeは次を検証する。

- Separate Handlerは `OperationHandler`
- DefinitionとHandlerが同じ場合、Legacy Self-handledまたはTyped Self-handled Signature
- Typed SignatureのValue／OutcomeがManifest Metadataと一致
- Handler Classが存在し、Instantiable ServiceとしてContainerへ登録可能

ContainerはTyped Self-handled Classも既存Handlerと同様にAutowireし、Application Service Providerの明示Bindingを尊重する。

## Static Analysis

Magoの通常Sourceへ `examples/quickstart/app` を含める。Quickstartの標準OperationはNative Value Typeにより解析でき、`@implements OperationHandler<...>` またはValue Narrowing Guardを必要としない。

## Compatibility

- Public `OperationHandler` Interfaceは削除しない
- Legacy Self-handledとSeparate Handlerを維持する
- Manifest Field名とOperation Type／Value／Outcome／Strategyは変更しない
- Existing Typed `OperationResult` ReturnとMetadata AttributeはCompatibility Modeとして維持する
- Runtime Source Discoveryを追加しない
- QuickstartはTyped Self-handledへ移行する

## Traceability

- Decisions: [D074 Typed Self-handled Operation Signature](../decisions/074-typed-self-handled-operation-signature.md)、[D075 Native Outcome and Rejection Exception](../decisions/075-native-outcome-and-rejection-exception.md)
- Core API: [Core API](17-core-api.md)
- Authoring: [Operation Authoring and Build Discovery](50-operation-authoring-and-build-discovery.md)
