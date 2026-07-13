# P7-005A: Operation Authoring Conventions

Status: Accepted

## Goal

Self-handled Operation、Build-time Discovery、Handler自動DI登録を追加し、Quickstart利用者がOperationごとのSeparate Handler、Operation Provider、Handler登録用Service Providerを記述しなくても、FeatureへOperationを追加するだけでBuild／HTTP／Worker実行できるようにする。

## In Scope

- Self-handled Operation Metadata Compile
- Optional `#[HandledBy]` とSeparate Handler互換
- Ambiguous／Missing Handler Validation
- Definition Instanceを作らないHTTP Route／Manifest Compile
- `config/operations.php` のDiscovery Root Validation
- Application-aware Build／Operation ListのBuild-time Discovery
- Provider＋Discovery Definition Merge／Deduplication
- Compiled HandlerのRuntime Container自動Autowire登録
- Application Service Provider Bindingとの安全な統合
- Required Constructor Dependencyを持つSelf-handled Operation
- Quickstart Welcome／ReportのSelf-handled移行
- Quickstart Operation／Service Provider削除
- Integration／Architecture／Guide／Internals更新

## Out of Scope

- Runtime HTTP／Worker Source Discovery
- `OperationProvider`／`ServiceProvider` Public Contract削除
- Generator Command
- Docker／Compose／Consumer Install E2E
- Journal Observer Composition
- Handler Decorator自動生成
- Repository実装をQuickstartへ追加

## Relevant Specifications and Decisions

- `develop/decisions/023-core-api-shape.md`
- `develop/decisions/064-installed-application-layout-and-bootstrap.md`
- `develop/decisions/071-operation-authoring-and-discovery.md`
- `develop/spec/17-core-api.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`

## Files Allowed to Change

- `src/Internal/Registry/**`
- `src/Internal/Discovery/**`
- `src/Internal/Application/**`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/Console/ApplicationOperationListCommand.php`
- `src/Internal/Console/CompileBuildArtifactsCommand.php`
- `src/Internal/Console/CompileHttpManifestCommand.php`
- `src/Internal/DependencyInjection/**`
- `src/Internal/Execution/DeferredWorkerRuntime.php`
- `src/Internal/Execution/DeferredLeaseExpiredRecovery.php`
- `src/Internal/Runtime/ProductionRuntimeComposer.php`
- `src/Http/Routing/HttpRouteCompiler.php`
- `src/Http/Routing/HttpOperationManifest.php`
- `tests/Internal/Registry/**`
- `tests/Internal/Discovery/**`
- `tests/Internal/Application/**`
- `tests/Internal/Console/**`
- `tests/Internal/DependencyInjection/**`
- `tests/Internal/Execution/DeferredWorkerRuntimeTest.php`
- `tests/Internal/Execution/DeferredLeaseExpiredRecoveryTest.php`
- `tests/Internal/Runtime/ProductionRuntimeComposerTest.php`
- `tests/Http/**`
- `tests/Integration/MvpSampleEndToEndTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `examples/quickstart/app/**`
- `examples/quickstart/bootstrap/app.php`
- `examples/quickstart/config/operations.php`
- `examples/quickstart/README.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/application-bootstrap.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/internal/operation-registry.md`
- `docs/internal/runtime-container.md`
- `docs/internal/bootstrap.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P7-005A-operation-authoring-conventions.md`
- `develop/orchestration/reports/P7-005A-operation-authoring-conventions.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Self-handled Metadata Contract

- Definitionが `Operation` と `OperationHandler` を実装し、`#[HandledBy]` がない場合、Handler MetadataはDefinition Class自身
- Definitionが `OperationHandler` を実装せず、`#[HandledBy]` が一つの場合は指定Handler
- Self-handledかつ `#[HandledBy]` ありはAmbiguous Error
- Handler実装もAttributeもない場合はMissing Handler Error
- 複数 `#[HandledBy]` とInvalid Handler Contractは既存どおり拒否
- Manifest Handler FieldとBuild ID Formatは変更しない

## Definition Reflection Contract

- HTTP Route／Manifest CompileでOperation DefinitionをInstance化しない
- Route AttributeはDefinition Class Reflectionから読む
- Self-handled OperationはRequired Constructor Dependencyを持てる
- HTTP RuntimeとDeferred Worker／Lease RecoveryはSelf-handled DefinitionをCompiled ContainerのHandler Serviceから取得する
- Self-handled時は同じContainer-resolved InstanceをDefinitionとHandlerの責務に使う
- Separate Handler方式のDefinitionは既存どおり引数なしで生成できるContractを維持する
- Existing no-argument Separate Definitionの挙動を回帰させない

## Discovery Contract

`config/operations.php`:

```php
return [
    'discovery' => [dirname(__DIR__) . '/app/Feature'],
    'providers' => [],
];
```

- Discoveryは存在する絶対Directoryだけを受ける
- Duplicate Rootを一度だけ扱う
- Build／Operation List Command実行時だけSourceを探索する
- Provider Definitionを先に、Discovered Definitionを決定的なClass Name順でMergeする
- Same Definitionは一度、Same Type ID ConflictはError
- HTTP／Worker Runtime Artifact LoadからDiscoveryを呼ばない

## Automatic Handler Registration Contract

- Build Registryの全Handler ClassをContainer Builderへ自動Autowire登録する
- Self-handledとSeparate Handlerを同じ規則で扱う
- Same Handlerは一度だけ登録する
- Application Service Providerの明示Bindingを尊重する
- Handler DependencyのInterface BindingはService Providerが所有する
- Handler登録だけのService Providerを要求しない

## Quickstart Migration Contract

削除:

```text
app/ApplicationOperationProvider.php
app/ApplicationServiceProvider.php
app/Feature/Welcome/ShowWelcome/ShowWelcomeHandler.php
app/Feature/Report/GenerateReport/GenerateReportHandler.php
```

- `ShowWelcome` と `GenerateReport` が `OperationHandler` を実装し `handle()` を所有する
- `#[HandledBy]` を削除する
- Bootstrapから `withOperations()`／`withServices()` を削除する
- `operations.php` はFeature Discovery Rootと空Provider Listを返す
- Feature削除時にProvider一覧を編集しないREADMEへ更新する
- Root Dev Autoloadへ `App\` を追加しない

## Constraints

- Production CodeとTestのComment／DocBlockへDecision、Spec、Task、TODOの管理番号を書かない
- Runtime Source DiscoveryまたはFallbackを追加しない
- Existing Separate Handler Contractを削除・非推奨化しない
- QuickstartへProvider Boilerplateを別名で再導入しない
- HandlerのRequired DependencyをMetadata Compile時にInstance化しない
- Self-handled DefinitionをHTTP／Worker RuntimeでReflectionによる引数なし生成へ戻さない
- Explicit Application Service Bindingを自動登録で無言上書きしない
- Public API SignatureへInternal Discovery／Container型を露出しない

## Acceptance Criteria

- [x] Self-handled OperationをHandler MetadataへCompileできる
- [x] Required Constructorを持つSelf-handled OperationをRoute／Manifest Compileできる
- [x] Required Constructorを持つSelf-handled OperationをHTTP／Worker／Lease RecoveryでContainer解決できる
- [x] Separate Handler方式が回帰しない
- [x] Ambiguous／Missing／Invalid Handlerを拒否する
- [x] Build／ListがDiscovery RootだけでQuickstart Operationを検出する
- [x] Provider＋Discovery Mergeと重複排除が成立する
- [x] Buildが全HandlerをContainerへ自動登録する
- [x] Service ProviderのRepository BindingをSelf-handled Operationへ注入できる
- [x] RuntimeがSource Discoveryを行わない
- [x] Quickstartから4 Boilerplate FileとProvider Fluent Callがなくなる
- [x] Quickstart HTTP／Worker／Retry／Outcome Integrationが成功する
- [x] Focused／Full Test、Mago、Deptrac、Composer Validation、境界Guardが成功する
- [x] Docs、Report、Checkpointが更新される

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples/quickstart/app examples/quickstart/bootstrap examples/quickstart/config
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit tests/Internal/Registry tests/Internal/Discovery tests/Internal/Application tests/Internal/Console tests/Internal/DependencyInjection tests/Http tests/Integration/MvpSampleEndToEndTest.php tests/Integration/ApplicationHttpRuntimeTest.php tests/Integration/ApplicationConsoleKernelTest.php tests/Architecture/QuickstartApplicationArchitectureTest.php
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Application(Operation|Service)Provider|ShowWelcomeHandler|GenerateReportHandler|#\[HandledBy' examples/quickstart --glob '*.php'
! rg -n 'BlackOps\\Internal' examples/quickstart --glob '*.php'
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples/quickstart --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P7-005A-operation-authoring-conventions.md` に次を記録する。

- Summary
- Self-handled and Compatibility Evidence
- Build-time Discovery Evidence
- Automatic Handler Registration／DI Evidence
- Quickstart Simplification Evidence
- Runtime No-discovery Evidence
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
