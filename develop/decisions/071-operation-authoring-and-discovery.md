# D071: Operation Authoring and Discovery

Status: Decided

## Context

P7-005でFeature-first Quickstartを実File化した結果、単純なOperationでもDefinition、Handler、Operation Provider、Service Providerを毎回記述する構造が利用者にとって冗長であることが明確になった。

既存分離には、Operation Metadataと実行実装の分離、HandlerのDI解決、Handler差替え、Deferred Worker再構成という意味がある。一方、これらはすべてのOperationへ別Handler Classと手動Provider登録を強制しなければ維持できない性質ではない。

Production RuntimeがSourceを探索しない原則は維持しながら、Application AuthoringとBuild-time Discoveryを簡潔にする。

## Decision

[DECISION]

1. Self-handled OperationをApplication Authoringの標準形として追加する。
2. Operation Definitionが `OperationHandler` も実装する場合、`#[HandledBy]` を省略し、Definition自身をHandler MetadataとしてCompileする。
3. Self-handled Operationと `#[HandledBy]` の同時指定は曖昧なため拒否する。
4. OperationとHandlerを分離する既存Contractは維持する。
5. 分離型Operationは引き続き `#[HandledBy(Handler::class)]` を必須とする。
6. Self-handled OperationはDI ContainerからHandler Serviceとして解決し、Constructor Injectionを利用できる。
7. HTTP Route／Manifest CompileはOperation Definition Instanceを要求せず、Definition ClassのReflection MetadataからCompileする。
8. Operation Source DiscoveryはApplication Build時だけ実行する。
9. Installed Applicationは `config/operations.php` のDiscovery RootへOperationを追加するだけでよく、OperationごとのProvider一覧を更新しない。
10. Production HTTP／Worker RuntimeはCompile済みArtifactだけを読み、Source DiscoveryへFallbackしない。
11. `OperationProvider` はPackage、Module、Generated Definition、Application外Source等を明示追加する拡張点として維持する。
12. Provider定義とBuild-time Discovery定義はIdentity単位でMergeし、同じDefinitionを二重登録しない。
13. Build CompilerはCompiled Operation Metadataに含まれるHandler ClassをDI Containerへ自動Autowire登録する。
14. Applicationの `ServiceProvider` はRepository Interface Binding、External Client、Factory、Shared Service等のApplication固有Dependencyへ限定する。
15. Operation Handlerを登録するためだけのService Provider記述をQuickstartから削除する。
16. QuickstartはSelf-handled OperationとBuild-time Discoveryを使用し、Application Operation Provider／Service Providerを持たない最小例へ更新する。

[/DECISION]

## Consequences

[CONSEQUENCES]

### Benefits

- 単純なOperationはDefinitionとHandlerを一つのClassにまとめられる。
- Feature追加時にOperation ProviderとService Providerの一覧を毎回編集する必要がない。
- Handlerは引き続きDI Containerから解決され、Repository等をConstructor Injectionできる。
- 高度な分離、Decorator、Handler差替えには既存 `#[HandledBy]` Contractを利用できる。
- RuntimeのArtifact-only／No Discovery保証を維持できる。

### Constraints

- Metadata CompilerはSelf-handledとExternal Handlerの2形態を明確に検証する必要がある。
- Route CompilerをDefinition Instance依存からClass Reflectionへ変更する必要がある。
- Build Artifact ContainerはOperation MetadataからHandlerを自動登録し、Application Service Bindingと競合しない必要がある。
- Build-time Discovery Rootは絶対PathとしてConfig検証し、Runtimeでは読まない。

### Compatibility

- 既存の `Operation` + `#[HandledBy]` + Separate Handlerは引き続き有効である。
- `OperationProvider` と `ServiceProvider` のPublic Contractは削除しない。
- Manifest FormatのHandler Fieldは維持し、Self-handledの場合だけDefinition Classと同じ値になる。

[/CONSEQUENCES]

## References

- [Core API Shape](023-core-api-shape.md)
- [Installed Application Layout and Bootstrap](064-installed-application-layout-and-bootstrap.md)
- [Feature-first Quickstart Application](../spec/49-feature-first-quickstart-application.md)
