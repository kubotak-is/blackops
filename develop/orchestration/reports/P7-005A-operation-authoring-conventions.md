# P7-005A Operation Authoring Conventions Report

## Summary

Self-handled Operation、Build-time Application Discovery、Compiled Handlerの自動DI登録を実装した。QuickstartはOperation Definition自身がHandlerとなり、Feature Source追加だけでApplication Build／Listへ含まれる。Production HTTP／Worker RuntimeはSource Discoveryを呼ばず、Compile済みArtifactとContainerだけを使用する。

## Self-handled and Compatibility Evidence

- `Operation` と `OperationHandler` を実装し `HandledBy` がないDefinitionはDefinition Class自身をHandler MetadataへCompileする。
- Self-handledと `HandledBy` の併用、Handlerなし、複数Attribute、Invalid Handler Contractを拒否する。
- Separate Handlerは既存どおり `HandledBy` を使用できる。
- HTTP Route／Manifest Compileはclass-string Reflectionを使用し、Required Constructor DefinitionをInstance化しない。
- Required Constructorを持つSelf-handled OperationはHTTP、Deferred Worker、Lease RecoveryでContainer-resolved Handlerと同じInstanceをDefinitionとして使う。
- Separate Handler Definitionの既存no-argument生成Contractは維持する。
- Legacy standalone HTTP Manifest／Unified BuildもDefinition class-stringをRoute Compilerへ渡し、required-constructor Self-handled DefinitionをBuild時に生成しない。

## Build-time Discovery Evidence

- `ApplicationOperationDiscovery` は `operations.discovery` が未指定なら空Discoveryを返し、Provider-only構成を維持する。
- Discovery Keyを明示した場合は非空の絶対・存在・可読Directoryだけを許可し、重複Rootを正規化する。
- Application Build／Operation ListだけがDiscoveryを呼ぶ。
- Provider Definitionを先に、Class Name順のDiscovered Definitionを後にMergeし、同一Definitionを一度だけCompileする。同じType IDの異なるDefinitionはRegistryで拒否する。

## Automatic Handler Registration and DI Evidence

- Application Service Provider適用後、Compiled Registryの全Handlerをpublic autowired serviceとして自動登録する。
- 既存Definition、Alias、`ServiceRegistry::set()` Instanceを検出し、Applicationの明示Bindingを上書きしない。
- 同じHandlerの再登録を行わない。
- Service Providerが登録したRepository Interface実装をRequired ConstructorのSelf-handled Operationへ注入できるTestを追加した。

## Quickstart Simplification Evidence

削除したBoilerplate:

- `examples/quickstart/app/ApplicationOperationProvider.php`
- `examples/quickstart/app/ApplicationServiceProvider.php`
- `examples/quickstart/app/Feature/Welcome/ShowWelcome/ShowWelcomeHandler.php`
- `examples/quickstart/app/Feature/Report/GenerateReport/GenerateReportHandler.php`

`ShowWelcome` と `GenerateReport` へ `handle()` を統合し、Bootstrapの `withOperations()`／`withServices()` を削除した。`config/operations.php` はFeature Discovery Rootと空Provider Listを返す。READMEはFeature Directoryだけで追加・削除できる手順とApplication固有DependencyのService Provider責務へ更新した。

## Runtime No-discovery Evidence

Discoveryの接続先は `ApplicationBuildCompileCommand` と `ApplicationOperationListCommand` だけである。`ProductionRuntimeComposer`、`DeferredWorkerRuntime`、`DeferredLeaseExpiredRecovery` はOperation Manifest、HTTP Manifest、Compiled ContainerからDefinition／Handlerを解決し、Source ScanやFallbackを行わない。Missing Artifact fail-fast Integrationも維持した。

## Changed Files

- Registry／Discovery: Metadata compiler、Definition collector/factory接続、absolute root validation、Application discovery境界
- Application／Standalone Build／List／DI: Discovery merge、class-string HTTP compile、Handler自動登録、明示Binding保護
- Runtime: HTTP、Deferred Worker、Lease Recoveryのcontainer-resolved Self-handled Definition
- Quickstart: 2 Self-handled Operation、Provider／Handler Boilerplate削除、Bootstrap／Config／README
- Tests: Registry、Discovery、Application、Console、DI、HTTP、Runtime、Integration、Architecture
- Docs／管理: Guide、Internals、TODO、Task Packet、Report、STATE

## Decisions and Assumptions

- `operations.discovery` 未指定はOperation Provider互換のため空Discoveryとして扱う。明示した空Listは設定誤りとして拒否する。
- Registry層からExecution層へ依存させず、Runtime handler resolverはcallable ContractとしてDefinition Factoryへ渡す。
- Handler Classの同一性比較はOperation／OperationHandlerの交差型を表すClass Name文字列として行う。
- Legacy standalone build/discovery commandsは互換維持し、Application-aware commandsだけを新Configへ接続した。
- Legacy standalone HTTP ManifestはProvider／Discovery両経路、Unified BuildはProvider経路でrequired-constructor Self-handled OperationをCompileできる。Unified BuildもCompiled HandlerをContainerへ自動登録する。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid.

docker compose run --rm app mago format --check src tests examples/quickstart/app examples/quickstart/bootstrap examples/quickstart/config
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit tests/Internal/Registry tests/Internal/Discovery tests/Internal/Application tests/Internal/Console tests/Internal/DependencyInjection tests/Http tests/Integration/MvpSampleEndToEndTest.php tests/Integration/ApplicationHttpRuntimeTest.php tests/Integration/ApplicationConsoleKernelTest.php tests/Architecture/QuickstartApplicationArchitectureTest.php
Result: OK (184 tests, 565 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/phpunit tests/Internal/Console/CompileHttpManifestCommandTest.php tests/Internal/Console/CompileBuildArtifactsCommandTest.php
Result: OK (17 tests, 51 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/phpunit tests/Internal/Execution/DeferredWorkerRuntimeTest.php tests/Internal/Runtime/ProductionRuntimeComposerTest.php
Result: OK (15 tests, 153 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/phpunit
Result: OK (644 tests, 2160 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 348 files / Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1484 / Warnings 0 / Errors 0.

! rg -n 'Application(Operation|Service)Provider|ShowWelcomeHandler|GenerateReportHandler|#\[HandledBy' examples/quickstart --glob '*.php'
Result: No matches (negated command exited 0).

! rg -n 'BlackOps\\Internal' examples/quickstart --glob '*.php'
Result: No matches (negated command exited 0).

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples/quickstart --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

## Acceptance Criteria

Task Packetの全Acceptance Criteriaを満たした。Self-handled／Separate Handler互換、Required ConstructorのApplication／standalone Provider／standalone Discovery Compile、HTTP／Worker／Lease Recovery、Provider-only、Provider＋Discovery Merge、明示Service Binding、Quickstart Retry／OutcomeをTestで確認した。

## Remaining Issues

- なし。
- Docker／Compose、Remote Consumer Install、Post-create Script、Journal Observer CompositionはTask範囲外である。

## Suggested Next Action

Orchestrator CodexがMetadata validation、Build-time only Discovery、明示Binding優先、Runtime container-resolved同一Instance、QuickstartのProvider不要構成をReviewする。受入後にP7-006へ進む。

## Orchestrator Review

2026-07-13に差分とTask ScopeをReviewし、Self-handled／Separate Handler判定、Build-time Discovery、Provider-only互換、Handler自動DI登録、明示Service Binding優先、HTTP／Worker／Lease RecoveryのContainer-resolved Definition、standalone Build互換、Runtime No-discoveryを確認した。

OrchestratorがRequired Focused Test 184件565 Assertions、Runtime Focused Test 15件153 Assertions、Full Test 644件2160 Assertions、Root／Quickstart Composer Validation、Mago Format／Lint／Analyze、Deptrac、Boilerplate／Internal／管理ID Guard、`git diff --check` を再実行し、すべて成功した。P7-005AをAcceptedとする。
