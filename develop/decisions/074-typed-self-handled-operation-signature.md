# D074: Typed Self-handled Operation Signature

Status: Decided

## Context

D071でSelf-handled Operationを標準形にしたが、現行 `OperationHandler` Contractは `handle(OperationEnvelope $operation)` を要求する。利用者は `OperationEnvelope::value()` を取り出し、具体型へNarrowingする `instanceof` Guardと `@implements OperationHandler<TValue, TOutcome>` DocBlockをOperationごとに記述している。

Magoの通常設定はFramework `src/` だけを解析しており、Quickstart Application Codeを対象にしていなかった。Quickstartを直接解析すると現在のDocBlockはTemplate指定として認識されず、Attribute `#[Accepts]` から `OperationEnvelope::value()` の具体型も推論されない。

利用者はValueをNative Typeで受け、Operation ID等が必要な場合だけ `ExecutionContext` を受け取れる形を選択した。Inline／Deferredの両方にExecution Contextは存在し、Deferred固有のAttemptだけがNullable境界となる。

## Decision

[DECISION]

1. Self-handled Operationの標準Signatureを次のどちらかとする。

```php
public function handle(WelcomeValue $value): OperationResult;

public function handle(WelcomeValue $value, ExecutionContext $context): OperationResult;
```

2. 第一引数は必須のNamed Class Typeで、`OperationValue` を実装し、`#[Accepts]` のClassと完全一致しなければならない。
3. 第二引数は省略可能で、指定する場合は必須の `ExecutionContext` 型とする。
4. Inline／Deferredとも第二引数のContextを受け取れる。Inlineでは `attempt()` はnull、Deferred Handler実行時はAttempt Contextを持つ。
5. 戻り値は `OperationResult` のNative Typeを必須とする。
6. Union／Intersection／Builtin／Untyped Value、参照渡し、Variadic、Static Handler、余分な引数を拒否する。
7. Build-time Metadata CompilerはSignatureを検証し、不正ClassとParameter責務を示して失敗する。
8. Manifest LoadもCompiled MetadataとHandler Signatureの整合性を検証し、古い／改変Artifactを拒否する。
9. Runtime InvokerはEnvelope内Value型をHandler呼出前に一度だけ検証し、Valueと必要な場合のContextを渡す。
10. 利用者のSelf-handled Operationは `OperationHandler` を実装せず、Generic DocBlockとValue `instanceof` Guardを書かない。
11. 既存 `OperationHandler<...>` と `handle(OperationEnvelope)` はSeparate Handlerおよび後方互換Contractとして維持する。
12. 既存のLegacy Self-handled Operationも後方互換のため受け入れる。
13. `#[HandledBy]` で指定するSeparate Handlerは現段階では引き続き `OperationHandler` を実装する。
14. Magoの通常AnalysisへQuickstart Application Codeを含め、標準Authoring Shapeを継続検証する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- MagoとIDEはValueをNative Typeとして認識できる。
- Self-handled OperationからGeneric DocBlock、Envelope Value取得、`instanceof`、型不一致例外を削除できる。
- Context不要なOperationはValueだけ、Operation ID／Attempt等が必要なOperationは第二引数だけを追加できる。
- 型不一致GuardをApplicationごとに複製せず、Build／Manifest／Runtime Framework Boundaryへ集約できる。
- Existing Separate HandlerとLegacy Applicationを壊さず段階移行できる。
- PHP Interfaceは実装ごとに異なる具象Value Parameterを要求できないため、Typed Self-handled SignatureはBuild CompilerがReflectionで検証するConventionとなる。
- `#[Accepts]` と第一引数型は当面両方を記述し、Compilerが一致を保証する。
- Handler ResolverとRuntime InvocationはLegacy InterfaceとTyped Self-handledの両経路を安全に扱う必要がある。

[/CONSEQUENCES]

## References

- [D071 Operation Authoring and Discovery](071-operation-authoring-and-discovery.md)
- [Core API](../spec/17-core-api.md)
- [Operation Authoring and Build Discovery](../spec/50-operation-authoring-and-build-discovery.md)
- [Typed Self-handled Invocation](../spec/53-typed-self-handled-operation-invocation.md)
