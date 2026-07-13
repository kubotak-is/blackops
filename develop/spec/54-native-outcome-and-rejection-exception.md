# Native Outcome and Rejection Exception

## Standard Authoring Contract

Typed Self-handled Operationは、Native ParameterとNative Return TypeだけでValue／Outcome Contractを宣言する。

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

Contextが必要な場合だけ第二引数へ `ExecutionContext` を指定する。値を返さない成功は `void` を指定する。

```php
public function handle(RebuildIndexValue $value, ExecutionContext $context): void
{
    $this->indexes->rebuild($value->name);
}
```

Compilerは第一引数をAccepted Value、具象Return ClassをOutcomeとしてManifestへ保存する。`void` は `EmptyOutcome` とする。

## Rejected Signal

予期された業務上の拒否はPublic `OperationRejectedException` をthrowする。

```php
if (!$this->inventory->isAvailable($value->items)) {
    throw OperationRejectedException::conflict('inventory_unavailable');
}
```

Factoryは `RejectionReason` と同じCategoryを提供する。

- `validation(string $code)`
- `unauthorized(string $code)`
- `forbidden(string $code)`
- `notFound(string $code)`
- `conflict(string $code)`
- `businessRule(string $code)`

Exceptionは安定したCategory／Codeだけを保持し、Payload、Credential、自由文Detailsを保持しない。Invocation BoundaryはこのExceptionだけを `OperationResult::rejected()` へ変換する。

## Failure Boundary

`OperationRejectedException` 以外のThrowableはRejectedへ変換しない。

- Retryable Exception: Supervision PolicyによるRetry／Failed／Dead Letter
- 通常例外: 既存Failure Policy
- Worker Interrupt／Claim Loss: 既存Worker Recovery Contract

これにより業務拒否、Temporary Failure、Bug、Infrastructure Failureを混同しない。

## Signature Validation

Build CompilerはTyped Self-handled Signatureを次のとおり検証する。

- Public、Non-static、Instantiable
- ParameterはValueだけ、またはValue＋`ExecutionContext`
- 第一引数はRequired Named Classで `OperationValue` を実装
- 第二引数は指定する場合Required `ExecutionContext`
- ReturnはRequired Named Type
- Return Classは具象 `Outcome`、またはBuiltin `void`
- Union、Intersection、Nullable、その他Builtin、Reference、Variadic、Optional Parameterを拒否

`#[Accepts]`／`#[Returns]` はTyped Self-handledではOptionalな移行互換Metadataとする。指定時は推論Classとの完全一致を必須とする。Legacy Self-handled／Separate Handlerでは既存どおり必須とする。

## Runtime Normalization

共通InvokerはCompiled Invocation Modeだけを使用する。

1. Handler／Value／Context Modeを検証する
2. HandlerをValue、またはValue＋Contextで呼ぶ
3. 具象Outcome Modeでは戻り値ClassがMetadata Outcomeと完全一致することを検証する
4. Void Modeでは戻り値nullを `EmptyOutcome` へ変換する
5. `OperationRejectedException` はRejected Resultへ変換する
6. その他Throwableは変換せず再throwする

RuntimeはSource DiscoveryやSignature推論を行わない。

## Compatibility

- Legacy Self-handled／Separate `OperationHandler` は `OperationResult` を返す
- Existing `Accepts`／`Returns` Attributeは削除しない
- Existing Typed `handle(...): OperationResult` はAttribute付きCompatibility Modeとして受け入れる
- ManifestのExisting Field名とSchema Versionを維持する
- Optional Invocation Fieldがない旧ManifestはClass Signatureと既存Metadataから復元・検証する
- 新規QuickstartとGuideはNative Outcome／Void標準形だけを使用する

## Traceability

- Decision: [D075 Native Outcome and Rejection Exception](../decisions/075-native-outcome-and-rejection-exception.md)
- Core API: [Core API](17-core-api.md)
- Handler Result: [Handler Result Contract](29-handler-result-contract.md)
- Typed Invocation: [Typed Self-handled Invocation](53-typed-self-handled-operation-invocation.md)
