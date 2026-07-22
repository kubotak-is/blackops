# Operation Authoring and Build Discovery

## Purpose

Application Authorは、単純なOperationを自己完結したUse Case Classとして記述し、Feature SourceへFileを追加するだけでBuild Artifactへ含められる。FrameworkはBuild時にOperationを探索し、HandlerをDI Containerへ自動登録する。Production Runtimeは従来どおりArtifactだけを使用する。

## Self-handled Operation

標準形はOperation Definition自身がNative Typed `handle()` を所有する。

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

Self-handled Operationは `OperationHandler`、`#[HandledBy]`、`#[Accepts]`、`#[Returns]` を指定しない。CompilerはNative SignatureからValue／Outcomeを推論し、Handler MetadataへDefinition Class自身を設定する。Contextが必要な場合は第二引数へ `ExecutionContext` を指定する。予期された拒否は `OperationRejectedException`、値のない成功は `void` を使用する。

Self-handled OperationはContainer ServiceとしてAutowireされる。ConstructorへRepository Interface等を要求する場合、ApplicationはService ProviderでInterface BindingまたはFactoryを登録する。

## Separate Handler Compatibility

責務分離、Decorator、複数実装切替等が必要な場合は既存形を使用できる。

```php
#[HandledBy(GenerateReportHandler::class)]
final readonly class GenerateReport implements Operation {}
```

Validation Ruleは次とする。

- ValidなTyped `handle()` を持ち `#[HandledBy]` がない: Definition自身をHandlerとする
- `OperationHandler` を実装し `#[HandledBy]` がない: Legacy Self-handledとしてDefinition自身をHandlerとする
- `OperationHandler` を実装せず `#[HandledBy]` が一つ: 指定ClassをHandlerとする
- `OperationHandler` を実装し `#[HandledBy]` もある: Ambiguousとして拒否する
- どちらもない: Missing Handlerとして拒否する
- `#[HandledBy]` が複数: 拒否する
- External Handlerは `OperationHandler` を実装しなければならない

Operation ManifestとHTTP ManifestのHandler Fieldは維持する。Self-handledの場合はDefinitionとHandlerが同じClass Nameになる。

## Definition Reflection

Operation DefinitionはMetadata／Route CompileのためにInstance化しない。Operation Type、Accepted Value、Handler、Outcome、Execution Strategy、HTTP RouteはDefinition ClassのReflectionから読む。

Declared Outcomeが`EphemeralOutcome`を実装する場合、CompilerはRouteと明示`#[ExecuteWith(Inline::class)]`を必須にし、Deferred、Routeなし、`#[ConsoleCommand]`を拒否する。Operation／HTTP／Frontend Manifestへ同じEphemeral Flagを出力し、Runtime Attribute ReflectionへFallbackしない。Ephemeral OperationのSensitive Value／OutcomeはBuild SampleやArtifactへ含めない。

このためSelf-handled OperationはRequired Constructor Dependencyを持てる。Instance生成はRuntime ContainerがHandlerを解決する時だけ行う。

## Application Discovery Configuration

Installed Applicationの `config/operations.php` はBuild-time Discovery RootとOptional Providerを定義する。

```php
return [
    'discovery' => [
        dirname(__DIR__) . '/app/Feature',
    ],
    'providers' => [],
];
```

Discovery Rootは存在する絶対Directory Pathでなければならない。重複Pathは一度だけ扱う。

Application-aware `build:compile` と `operation:list` は次を行う。

1. Accepted SnapshotのOperation Providerを解決する
2. `operations.discovery` RootをToken／Reflectionで探索する
3. Provider DefinitionとDiscovered DefinitionをMergeする
4. Operation MetadataをCompileする
5. BuildではManifest／Containerを生成し、ListではMetadataを表示する

DiscoveryはCommand実行時だけ行い、HTTP／Worker Runtime Compositionから呼ばない。

Symfony Application CommandのDiscovery Rootは`app.command_discovery`であり、Operationの`operations.discovery`とは独立する。両者は`build:compile`でだけ走査される。Operation `#[ConsoleCommand]`はCompiled Operation Metadataと同じDefinition Reflectionから抽出するが、Operation ManifestへConsole Fieldを追加せず、Command Manifest Schema 2の`operation_commands`としてConsole Compositionが所有する。RuntimeはAttribute ReflectionへFallbackしない。

## Provider Extension

`OperationProvider` はOptional Extension Pointとして維持する。

- Composer PackageがOperation Definitionを公開する
- Application外DirectoryまたはGenerated Classを登録する
- Source Discoveryを使用しない明示Moduleを構成する
- TestでDefinition Setを明示する

ProviderとDiscoveryが同じDefinitionを返す場合は一度だけCompileする。異なるDefinitionが同じOperation Type IDを持つ場合は既存Conflict Errorを維持する。

## Automatic Handler Registration

Application BuildはCompiled Registryの全Handler MetadataをRuntime Containerへ自動登録する。

- Handler ClassをAutowireしPublic ServiceとしてHandler Resolverから取得可能にする
- 同じHandler Classは一度だけ登録する
- Self-handled OperationはDefinition Classを登録する
- External Handlerも同じ規則で登録する
- Application Service Providerが同じService IDを明示定義している場合はApplication定義を尊重し、無言で上書きしない
- Repository Interface等のHandler DependencyはService ProviderがBindingする

Quickstartの標準Handlerは追加Service ProviderなしでCompile／実行できる。

## Seeder Discovery Independence

Database Seeder DiscoveryはOperation Discoveryと同じBuild Lifecycleを使うが、Operation Registry、Operation Manifest、Handler MetadataへSeederを混在させない。

- Seederは`config/database.php`の`seeding.discovery`または標準Seed Directoryだけを探索する
- Instantiableな`Seeder`実装だけを決定的に収集し、Constructorを実行しない
- 検出結果はCompiled ContainerのPrivate ServiceとSeeder専用Locatorへ固定する
- 実行順はDiscovery順ではなく、Root Seederが`SeederRunner`へ渡すClass順で決まる
- RuntimeはSource Scan、Attribute Reflection、動的ConstructionへFallbackしない

## Quickstart Shape

Quickstartから次を削除する。

```text
app/ApplicationOperationProvider.php
app/ApplicationServiceProvider.php
```

WelcomeとReportのSeparate Handler Fileを削除し、各Operation DefinitionへNative Typed `handle()` を置く。BootstrapはProviderを明示登録せず、Configurationだけを読み込む。

```php
return Application::configure($basePath)
    ->withEnvironment($environment)
    ->withConfiguration()
    ->create();
```

Service Providerを追加する方法はREADMEへRepository Binding例として説明するが、QuickstartのSample Handler登録には使用しない。

## Verification

- Self-handled Operation MetadataのDefinitionとHandlerが同じClassになる
- Required Constructorを持つSelf-handled OperationをMetadata／Route Compileできる
- Separate Handler Contractが回帰しない
- Ambiguous／Missing／Invalid Handlerを拒否する
- Application Build／ListがDiscovery RootだけでQuickstart Operationを見つける
- Runtime ContainerがDiscovered Handlerを自動解決する
- Repository Interface BindingをService Providerから注入できる
- HTTP／Worker RuntimeでSource Discoveryを呼ばない
- Quickstart追加時にProvider一覧編集が不要になる
- Quickstart OperationにGeneric DocBlock、Envelope Value Narrowing Guardがない
- Quickstart Application Codeが通常のMago Analysisで成功する

## Traceability

- Decision: [Operation Authoring and Discovery](../decisions/071-operation-authoring-and-discovery.md)
- Core API: [Core API](17-core-api.md)
- Quickstart: [Feature-first Quickstart Application](49-feature-first-quickstart-application.md)
