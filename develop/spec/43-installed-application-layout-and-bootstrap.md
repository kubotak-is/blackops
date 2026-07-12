# Installed Application Layout and Bootstrap

## Official Skeleton Layout

Installed Applicationの公式SkeletonはFeature-first構造とする。

```text
app/
  Feature/
    Report/
      GenerateReport/
        GenerateReport.php
        GenerateReportValue.php
        ReportGenerated.php
    Welcome/
      ShowWelcome/
        ShowWelcome.php
        WelcomeValue.php
        WelcomeShown.php
  Infrastructure/
bin/
  blackops
bootstrap/
  app.php
config/
  app.php
  database.php
  execution.php
  journal.php
  operations.php
  retention.php
migrations/
public/
  index.php
tests/
var/
  build/
  log/
.env.example
.gitignore
Caddyfile
compose.yaml
composer.json
Dockerfile
Dockerfile.frankenphp
README.md
```

`app/Feature/<Feature>/<Action>/` は、Self-handled Operation、Value、Outcome、Responder等、同じ変更理由を持つFileをまとめる。別Handlerが必要な場合も同じAction Directoryへ置く。

Skeletonへ `Internal` Directoryを設けない。HTTPから直接開始しないOperationも、それが属するFeatureへ置く。HTTP、Deferred等の実行経路はDirectoryではなくOperation MetadataとApplication Configurationで決定する。

`app/Infrastructure/` はApplicationが必要とするPersistence、External Service、Clock等の技術実装を置く任意のDirectoryである。Frameworkは `Infrastructure/BlackOps` その他のFramework名を含むDirectoryを要求しない。

このLayoutは公式推奨であり、Frameworkの実行要件ではない。ApplicationはBuild-time Discovery Root、Operation Provider、Configurationにより別のDirectory Layoutを使用できる。

## Starter Features

Skeletonは次の削除可能なStarter Featureを含む。

- `Welcome/ShowWelcome`: Inlineの `GET /welcome`
- `Report/GenerateReport`: Deferredの `POST /reports`

Starter FeatureはSensitive InputのMaskと、Retry後に成功するDeferred Workerの例を示す。各Featureは自身のDirectoryを削除し、Operation登録から除外することで他のApplication Bootstrapへ影響せず削除できなければならない。

## Public Application Bootstrap

`bootstrap/app.php` は `BlackOps\Application\Application` のPublic Builderを利用して共有Application Objectを返す。

```php
use BlackOps\Application\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withConfiguration()
    ->withOperations()
    ->withServices()
    ->create();
```

上記はPublic APIの責務とFluent Shapeを規定する。Optional引数とConfig Objectの詳細はPublic API実装Taskで確定する。

Application Objectは次のProcess Boundaryからのみ利用するComposition Rootである。

- `public/index.php`: PSR-7 RequestをPSR-15 HTTP Handlerへ渡す
- `bin/blackops`: FrameworkとApplicationのConsole Commandを起動する
- Deferred Worker／Scheduler: Console CommandからApplication Runtimeを起動する

業務CodeへApplication Objectを注入してService Locatorとして利用してはならない。業務DependencyはService ProviderからContainerへ登録し、HandlerへConstructor Injectionする。

Application BootstrapとSkeleton Codeは `BlackOps\Internal` を参照してはならない。Application Compositionに必要なFramework型は `#[PublicApi]` を持ち、SignatureへInternal型を露出してはならない。

## Configuration

Skeletonは責務別のPHP Config Fileを持つ。

- `config/app.php`: Application名、Environment、Debug等
- `config/database.php`: PostgreSQL ConnectionとFramework Schema
- `config/operations.php`: Build-time Discovery RootとOptional Operation Provider
- `config/execution.php`: Inline／Deferred、Worker、Supervision設定
- `config/journal.php`: Canonical Journal、Observer設定
- `config/retention.php`: 保持期間、Policy Ref、Maintenance Actor設定

SecretをConfig Fileへ直書きせず、Process Environmentから取得する。

## Environment Loading

Dotenvの読み込みはSkeletonが所有し、Framework PackageはDotenv実装へ依存しない。

- `.env.example` は必要なEnvironment Variableと安全なLocal Defaultを記載する
- `.env` はVersion管理しない
- 実Process Environmentを `.env` より優先する
- Productionは `.env` Fileを必須としない
- 解決済みEnvironment値をApplication Configurationへ渡す

Skeletonが採用するDotenv PackageとBootstrap CodeはSkeletonのComposer Dependencyとして管理する。

## Local Runtime

Skeletonは次を含むDocker Compose Quickstartを提供する。

- PHP 8.5 CLIとComposerを持つApplication Service
- FrankenPHP HTTP Service
- PostgreSQL ServiceとHealth Check
- Build、Migration、Worker、Retentionを実行するCLI導線

Defaultの `docker compose up` はHTTPとPostgreSQLだけを継続起動する。WorkerとMaintenance Schedulerは明示CommandまたはProfileで起動し、Install直後にBackground処理やPurge処理を自動開始しない。

Database Migrationは明示Commandで実行し、HTTP／Worker起動時には実行しない。

## Generated State

- Compile済みManifestとContainerは `var/build/` に出力する
- Application LogとLocal Journal Observer出力は `var/log/` に出力する
- `var/build/` と `var/log/` の生成物はVersion管理しない
- Production RuntimeはCompile済みArtifactが不足または不整合の場合に失敗し、Source DiscoveryへFallbackしない

## Traceability

- Decision: [D064 Installed Application Layout and Bootstrap](../decisions/064-installed-application-layout-and-bootstrap.md)
- Boundary: [Installed Application Boundary](42-installed-application-boundary.md)
- Audit: [P7-001 Installed Application Composition Audit](../orchestration/reports/P7-001-installed-application-composition-audit.md)
