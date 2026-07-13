# Installed Application Boundary

## Purpose

Installed Application ExampleはFramework Repository内のTest Fixtureではなく、通常のComposer Dependencyとして `blackops/framework` を利用する独立Consumerである。

`examples/quickstart/` を利用者向けExampleとComposer Project Package `blackops/skeleton` の共通Source of Truthとする。

## Package Boundary

Installed Applicationは自身の `composer.json`、PSR-4 Application Namespace、Runtime Entry Point、Configuration、Build Outputを所有する。

Application Code、Configuration、Bootstrap、Process Entrypointは `BlackOps\Internal` Namespaceを参照してはならない。FrameworkがApplication Compositionに必要な機能は、`#[PublicApi]` を持つ型を通して提供する。

Repository内のConsumer Testは、Root PackageのDev Autoloadによる偶発的なClass参照を成功条件にしてはならない。独立したComposer Installと同等のDependency Boundaryを検証する。

## Shared Application Configuration

HTTP、Console、Worker、Build、Migration、Retention、Schedulerは、同じApplication Configurationから構成する。

Application Configurationは少なくとも次をFrameworkへ提供できなければならない。

- Operation ProviderとService Provider
- EnvironmentとApplication Mode
- PostgreSQL Connection情報とFramework Schema
- Build ArtifactのInput／Output Path
- Clock、PSR-17 Factory、Journal Observer等のRuntime Dependency
- Application独自のConsole Command

具体的なConfig File、Builder、Facade、Kernelの形は後続仕様で定める。

## Process Boundaries

Installed Applicationは次のProcess Boundaryを持つ。

- HTTP RequestをPSR-15 Handlerへ渡すWeb Entrypoint
- Project所有の薄い `blackops` Console Entrypoint
- Console Kernelから起動するDeferred Worker
- Console Kernelから明示的に実行するBuild、Migration、Retention、Scheduler

Database MigrationはHTTPまたはWorker起動時に暗黙実行しない。Production RuntimeはCompile済みArtifactを読み込み、不足または不整合時にSource DiscoveryへFallbackしない。

## Security and Generated State

CredentialとSecretをSkeletonへ保存しない。Version管理対象にはEnvironment Variable名と安全なLocal Defaultを示す `.env.example` だけを含める。

Compile済みContainer、Manifest、Lock、Runtime Log等の生成物はApplication Sourceと分離し、Version管理対象に含めない。

## Verification

Consumer E2Eは最低限次を検証する。

1. 独立Application DependencyとしてFrameworkを読み込める
2. Application CodeとBootstrapに `BlackOps\Internal` 参照がない
3. Build Artifactを生成しProduction Runtimeで読み込める
4. Inline HTTP Operationが完了する
5. Deferred HTTP OperationをWorkerが実行できる
6. Versioned Migrationを明示Commandで適用できる
7. RetentionのPlanまたは安全なDry RunをApplication CLIから実行できる

## Traceability

- Decision: [D063 Developer Experience Roadmap](../decisions/063-developer-experience-roadmap.md)
- Audit: [P7-001 Installed Application Composition Audit](../orchestration/reports/P7-001-installed-application-composition-audit.md)
