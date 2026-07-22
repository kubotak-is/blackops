# Installed Application Layout and Bootstrap

## Official Skeleton Layout

Installed Applicationの公式SkeletonはFeature-first構造とする。

```text
app/
  Infrastructure/
    Seed/
      DatabaseSeeder.php
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

`app/Infrastructure/` はApplicationが必要とするPersistence、External Service、Clock、Database Seeder等の技術実装を置くDirectoryである。Frameworkは `Infrastructure/BlackOps` その他のFramework名を含むDirectoryを要求しない。

SkeletonはFramework Database Seederの標準Conventionとして`app/Infrastructure/Seed/DatabaseSeeder.php`を配布する。Root Seederは空でもよく、子Seederが必要なApplicationだけが`SeederRunner`による明示順を追加する。Application固有Migrationの `migrations/` は任意Directoryであり、Migrationを持たないSkeletonへ空Directoryを配布しない。Framework-owned MigrationはFramework Package内部からPublic Database Migration Commandが実行する。

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
- `blackops`: FrameworkとApplicationのConsole Commandを起動する
- Deferred Worker／Scheduler: Console CommandからApplication Runtimeを起動する

業務CodeへApplication Objectを注入してService Locatorとして利用してはならない。業務DependencyはService ProviderからContainerへ登録し、HandlerへConstructor Injectionする。

Application BootstrapとSkeleton Codeは `BlackOps\Internal` を参照してはならない。Application Compositionに必要なFramework型は `#[PublicApi]` を持ち、SignatureへInternal型を露出してはならない。

## Configuration

Skeletonは責務別のPHP Config Fileを持つ。

- `config/app.php`: Application名、Environment、Debug等
- `config/database.php`: Default／Named PostgreSQL ConnectionとFramework Store Connection／Schema
- `config/operations.php`: Build-time Discovery RootとOptional Operation Provider
- `config/execution.php`: Inline／Deferred、Worker、Supervision設定
- `config/journal.php`: Canonical Journal、Observer設定
- `config/retention.php`: 保持期間、Policy Ref、Maintenance Actor設定

SecretをConfig Fileへ直書きせず、Process Environmentから取得する。

## Environment Loading

Default SkeletonはFramework-owned Environment File Capabilityを明示的に有効化し、Application BuilderがProcess EnvironmentとOptional `.env`をBootstrap時に一度だけSnapshotする。

- `.env.example` は必要なEnvironment Variableと安全なLocal Defaultを記載する
- `.env` はVersion管理しない
- 実Process Environmentを `.env` より優先する
- Productionは `.env` Fileを必須としない
- Request／Operation／Worker IterationごとにEnvironmentを再読込しない
- Environment値をCompiled Artifact、Generated Source、Logへ保存しない

Dotenv実装はFramework Internalとし、SkeletonはVendor Classを直接Importしない。Vault、Cloud Secret Manager、独自Dotenv等を使うApplicationは外部Loaderで解決済みEnvironmentを`withEnvironment(array)`へ渡し、利用PackageをApplication Direct Dependencyとして管理する。詳細は[Application Runtime and Bootstrap](78-application-runtime-and-bootstrap.md)を正本とする。

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
- Runtime Boundary: [D114 Application Runtime and Bootstrap Dependency Boundary](../decisions/114-application-runtime-and-bootstrap-dependency-boundary.md)
- Boundary: [Installed Application Boundary](42-installed-application-boundary.md)
- Audit: [P7-001 Installed Application Composition Audit](../orchestration/reports/P7-001-installed-application-composition-audit.md)
- Empty Directory Policy: [D072 Skeleton Empty Directory Policy](../decisions/072-skeleton-empty-directory-policy.md)
