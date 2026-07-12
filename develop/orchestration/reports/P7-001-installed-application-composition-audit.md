# P7-001 Installed Application Composition Audit Report

## Summary

現在の `examples/mvp/` はOperation Definition、Value、Handler、Outcome、Providerの最小例であり、Framework Repository内のIntegration Test Fixtureとしては有効である。一方、独立したComposer Project、実Process Entrypoint、Environment／Config、Build Artifact、Database Migration、HTTP／Worker／Maintenance Bootstrapを持たず、Install直後ApplicationのExampleにはなっていない。

Operation実装は公開APIだけで記述できているが、E2E Bootstrapは24種類の `BlackOps\Internal` 型へ直接依存する。Phase 7ではInternal実装をSkeletonへ移すのではなく、ApplicationがHTTP、Worker、Build、Migration、Retentionを構成できるPublic Composition APIを追加する必要がある。

## Current Sample Evidence

### Existing Files

`examples/mvp/` は4 Fileだけを持つ。

```text
examples/mvp/README.md
examples/mvp/operation-providers.php
examples/mvp/service-providers.php
examples/mvp/src/MvpSample.php
```

次のInstalled Application要素は存在しない。

- 独立した `composer.json`／`composer.lock`
- `App\` 等のApplication NamespaceとPSR-4 Root
- `public/index.php` HTTP Entrypoint
- Project所有の `bin/blackops`
- `bootstrap/`、`config/`、`var/`、`migrations/`
- `.env.example` とApplication Environment Contract
- Application単独のCompose／FrankenPHP Runtime
- Install後のBuild、Migration、HTTP、Worker、Retention Smoke Command

### Public Domain API

`examples/mvp/src/MvpSample.php` のOperation、Value、Handler、Outcome、Operation Provider、Service Providerは `BlackOps\Core` と `BlackOps\Http` の公開Contractだけを利用している。この部分はInstalled Applicationへ移行可能である。

### Internal Bootstrap Dependency

`tests/Integration/MvpSampleEndToEndTest.php` は、Build、Artifact Load、Runtime Composition、Deferred Acceptance、Worker、Migration、Projectionを組み立てるため24種類の `BlackOps\Internal` 型をImportする。

主なGapは次のとおり。

| Consumer Capability | Current Internal Boundary |
| --- | --- |
| Build Artifacts | `CompileBuildArtifactsCommand` |
| Artifact Load／HTTP Runtime | `ProductionRuntimeArtifactLoader`、`ProductionRuntimeComposer` |
| Deferred HTTP Acceptance | `DeferredHttpOperationAcceptor`、`DeferredAcceptanceOrchestrator` |
| Worker Runtime | `DeferredWorkerRuntime`、Storage／Services／Guard |
| Identifier／Context／Journal Factory | Internal Factory群 |
| Migration | `DatabaseMigrationRunner` |
| Observed Journal Projection | Internal Pipeline／Projector／Filter群 |

Framework Console Commandの大半も `BlackOps\Internal\Console` にあり、Applicationは現状それらを登録するためInternal Classを直接生成する必要がある。

## Public Composition Gaps

Phase 7以降で、少なくとも次の公開境界が必要になる。

1. Application Configurationを一度読み込み、HTTP／Console／Workerへ共有するBootstrap境界
2. Operation ProviderとService Providerの登録境界
3. PostgreSQL Connection、Schema、Clock、PSR-17、Journal Observer等のApplication Dependency設定
4. Build Artifact PathとProduction Artifact Loadの公開境界
5. Inline／Deferredを同じRoute Registryから扱うPSR-15 HTTP Runtime境界
6. Worker LoopをApplication設定から構成する境界
7. Migration／Build／Worker／Retention／Scheduler Commandをまとめて登録するConsole Kernel境界
8. Application独自Commandを追加できるExtension境界

具体的なFacade、Builder、Kernel、Config ObjectのClass名とMethod Signatureは後続Decisionで確定する。

## Confirmed Requirements

- `examples/quickstart/` は独立ConsumerとしてInstall可能でなければならない。
- `examples/quickstart/` のApplication Codeは `BlackOps\Internal` を参照してはならない。
- Exampleと `blackops/skeleton` は同じSourceを使う。
- Project作成の公式経路は `composer create-project blackops/skeleton my-app` とする。
- Projectは薄い `bin/blackops` Entrypointを所有し、Command実装とGenerator StubはFramework Packageが所有する。
- HTTP、Worker、Build、Migration、RetentionのProcessは同じApplication Configurationから構成される。
- Database MigrationはHTTP／Worker起動時に暗黙実行せず、明示的なDeployment Commandとする。
- Production RuntimeはCompile済みArtifactを読み込み、Source DiscoveryへFallbackしない。
- SecretはRepositoryへ保存せず、Environment参照として扱う。

## Decisions Still Required

- 推奨Application Directory LayoutとStarter Featureの配置
- Root NamespaceとCreate Project後のProject Identity初期化範囲
- `bootstrap/app.php` が返すPublic Bootstrap Objectの形
- PHP Config FileとEnvironment Variableの責務分担
- Skeletonに含めるLocal Runtime範囲
- 初期Operationを実行可能なWelcome／Report Sampleとして残すか、最小Placeholderにするか

## Changed Files

- `develop/TODO.md`
- `develop/spec/README.md`
- `develop/spec/42-installed-application-boundary.md`
- `develop/orchestration/tasks/P7-001-installed-application-composition-audit.md`
- `develop/orchestration/reports/P7-001-installed-application-composition-audit.md`
- `develop/STATE.md`

Production CodeとTestは変更していない。

## Commands and Results

```text
find examples/mvp -type f | sort
Result: README、operation-providers、service-providers、MvpSampleの4 files。

rg -o 'BlackOps\\Internal\\[A-Za-z0-9_\\]+' tests/Integration/MvpSampleEndToEndTest.php | sort -u
Result: 24 unique Internal types。

rg -n '#\[PublicApi\]' src
Result: Core、HTTP Attribute、Journal、Logging、Outcome等の公開型を確認。Application Bootstrap／Console Kernelの公開Facadeは存在しない。

git diff --check
Result: No output。
```

## Acceptance Criteria

- [x] 現在のSampleがInstalled Applicationとして不足する要素が列挙される
- [x] Consumer E2Eが直接利用するInternal API境界が特定される
- [x] Phase 7で追加すべきPublic Composition領域が分類される
- [x] D063から確定済みのInstalled Application要件が仕様化される
- [x] Product／Public API判断が必要な事項が後続Decision候補として分離される
- [x] Production CodeとTestを変更していない

## Remaining Issues

Application Layout、Bootstrap API、Environment Contract、Starter Featureは未決定であり、Production実装Taskを作る前にDecisionが必要である。

## Suggested Next Action

D064でInstalled Application LayoutとPublic Bootstrap境界を決定し、確定後にPhase 7のProduction実装Taskを分割する。
