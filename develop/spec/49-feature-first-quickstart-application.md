# Feature-first Quickstart Application

## Purpose

`examples/quickstart/` は、Framework Repository内のTest Fixtureではなく、Install直後と同じSource Treeを持つ独立Composer Projectである。同時にPhase 8で `blackops/skeleton` Distribution RepositoryへSplitするSource of Truthとなる。

P7-005はApplication Source、Bootstrap、HTTP／Console Entrypoint、Config、Environment Example、Generated Directory Boundary、README、Testを配置する。Docker／FrankenPHP ImageとConsumer Install E2EはP7-006で追加する。

## Project Tree

```text
examples/quickstart/
  app/
    Feature/
      Diagnostics/
        TriggerFailure/
          TriggerFailure.php
          TriggerFailureValue.php
          FailureTriggered.php
      Report/
        GenerateReport/
          GenerateReport.php
          GenerateReportValue.php
          ReportGenerated.php
          ReportGenerationTemporarilyUnavailable.php
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
    diagnostics.php
    execution.php
    journal.php
    logging.php
    operations.php
    retention.php
  public/
    index.php
  tests/
  var/
    build/.gitignore
    log/.gitignore
  .env.example
  .gitignore
  composer.json
  README.md
```

`examples/mvp/` のFeatureとTest用途はQuickstartへ移し、重複するMVP Source Treeは削除する。Repository Integration TestはQuickstartのBuild-time DiscoveryとFeatureを使用する。

Quickstart Localの`config/diagnostics.php`だけがDevelopment Viewerを`enabled: true`にする。Framework既定は無効のままとし、このLocal設定をProduction Enableの根拠にしない。

## Composer Boundary

QuickstartのComposer Package Nameは `blackops/skeleton`、Application Namespaceは `App\` とする。`composer.lock` は含めない。

Applicationが直接利用するRuntime／Database Dependencyだけを宣言する。

```text
php                                  >=8.5
blackops/framework                   same release major/minor
```

Environment File、PSR-7 Request／Response、SAPI Emit、UUIDv7はFramework-owned Runtime Capabilityである。ApplicationがDBAL／Migration APIを実Importする場合は、そのDirect Dependencyを宣言する。Framework Root PackageのRuntime実装DependencyをSkeletonへ重複宣言しない。

Main Repositoryだけで成立するPath Repositoryを `composer.json` へ保存しない。Local Consumer検証はCommandまたは一時Copy側でFramework Path Repository／Versionを注入し、配布Sourceを変更しない。

P7-005ではSkeleton SourceのComposer MetadataとAutoload Boundaryを成立させる。Release Version Constraintの機械的同期、Post-create Script、Split Workflow、Packagist公開はPhase 8で完成させる。

## Feature Boundary

### Welcome

`GET /welcome` はInline Operationで、`X-Sample-Token` HeaderをSensitive Mask対象として受け取り、次を返す。

```json
{"message":"Welcome to BlackOps"}
```

### Report

`POST /reports` はDeferred Operationで、`reportName` とSensitive Mask対象の `apiToken` を受け取る。初回AttemptはRetryable Exceptionを投げ、次回AttemptでReport Outcomeを返す。

### Diagnostics Failure

`POST /failures`は認証／認可付きInline Operationで、非Secret `reference`と`SensitiveMode::Mask`の`sensitiveNote`を受け取る。OperationはPSR-3 LoggerをConstructor Injectionし、ReferenceだけをApplication Logへ記録した後に固定Messageの`RuntimeException`を投げる。HTTPはSafe 500とOperation IDだけを返し、`operation:inspect`とLocal Viewerへ渡せる。

Feature内のSelf-handled Operation、Value、Outcome、Feature固有Exceptionは同じAction Directoryへ置く。片方のFeature Directoryを削除しても、Provider一覧やもう片方のBootstrap／Configを変更せず利用できる。

Application Code、Test、Bootstrap、Config、Entrypointは `BlackOps\Internal` をImportしない。

## Application Bootstrap

`bootstrap/app.php` はPublic BuilderでFramework-owned Environment File Capabilityを有効化し、Applicationを返す。

```php
return Application::configure(dirname(__DIR__))
    ->withEnvironmentFile()
    ->withConfiguration()
    ->create();
```

`.env` がないProduction Environmentでも起動可能とする。既存Process Environmentは `.env` より優先する。Quickstart SourceはDotenv Classを参照せず、Framework-owned Environment File Capabilityを明示的に有効化する。

## HTTP Entrypoint

`public/index.php` は次だけを行う。

1. Composer Autoloaderと `bootstrap/app.php` を読む
2. `SapiRuntime::run($application)`を呼ぶ

Request生成、Response Emit、Safe 500はFramework-owned `SapiRuntime`が担当する。HTTP EntrypointはPSR-7実装、Laminas、FrankenPHP Loop、Internal Runtime、Container、DBAL Connection、Artifact Loaderを参照せず、BuildやMigrationを実行しない。Worker Entrypointは`SapiRuntime::runWorker($application)`を呼ぶ。

FrankenPHP Worker Mode用EntrypointはApplicationをProcess起動時に一度構成し、Request LoopへPublic HTTP Handlerを渡す。Worker ModeはInstall直後のDefault HTTPとし、Request終了時のScope Cleanup、Journal Flush、Connection Recovery、例外隔離、Memory上限、`max_requests` RestartをConsumer E2Eで継続検証する。Classic Front Controllerは明示ProfileのFallbackとして維持する。

## Console Entrypoint

Project Rootの`blackops`はExecutable PHP Scriptとし、Composer Autoloaderと`bootstrap/app.php`を読み、次だけを行う。

```php
exit($application->console()->run());
```

Framework Command実装やRuntime DependencyをProject側で生成しない。

## Configuration

### app.php

`app.build` にApplication Build ID、Operation Manifest、HTTP Manifest、Containerの絶対Path、Container Class／Namespaceを定義する。出力先は `var/build/` とする。

### database.php

FrameworkのEnvironment Snapshotから解決したDBAL ParameterとFramework Schemaを返す。CredentialをRepositoryへ直書きしない。

### execution.php

Worker ID、Lease、Heartbeat、Grace、Handler Failure継続を定義する。Environment Variable名と型変換はこのConfigが所有する。

### operations.php

Build-time Discovery RootとOptional Operation Providerを定義する。通常のFeature追加ではProvider一覧を編集しない。

### journal.php

将来のJournal Observer設定用の責務別Fileとして存在し、P7-005で未対応のInternal Backend Classを参照しない。

### logging.php

Built-in JSONL BackendをApplication-ownedの絶対Path `var/log/application.jsonl`、Channel `blackops`、Minimum Level `info`で構成する。DirectoryはSetupが準備し、Frameworkは暗黙作成しない。

### retention.php

4対象の保持日数、Policy Ref、Actorを定義し、Retention CommandとSchedulerが同じPolicyを使う。

Config FileはApplication NamespaceとFramework Public型以外のFramework実装型をImportしない。

## Environment and Generated State

`.env.example` はLocal PostgreSQL、Framework Schema、Worker ID、Worker Timing、Retention Policyに必要なVariableと安全なLocal Defaultを示す。実SecretやProduction Credentialを含めない。

`.gitignore` は `.env`、`vendor/`、`var/build/*`、`var/log/*` を除外する。Skeleton Source自体には `composer.lock` を含めないが、生成Applicationが自身のLock FileをVersion管理できるようIgnoreしない。Directory維持用 `.gitignore` は配布する。

HTTP、Console、BootstrapはGenerated Directoryを作成しない。Install時のDirectory準備と `.env.example` Copyの自動化はPhase 8 Post-create Scriptで追加する。P7-005 READMEは手動準備手順を記載する。

## README Contract

READMEは少なくとも次を説明する。

- Required PHP／Composer／PostgreSQL
- `.env.example` CopyとGenerated Directory準備
- Dependency Install
- `build:compile`
- `database:status`／`migrate`
- `database:seed`
- HTTP起動
- Welcome Request
- Deferred Report Request
- Worker Run
- Retention Plan／Dry Run
- Worker、Migration、Build、Seed、Purgeが暗黙実行されないこと
- Starter FeatureをDirectory単位で削除する方法
- Failure ResponseのOperation IDをHuman／JSON InspectとLocal Viewerへ渡す方法

P7-005ではDocker Compose Commandを完成手順として記載しない。P7-006でLocal Runtimeが追加された時点でREADMEをDocker Quickstartへ更新する。

## Verification

- Quickstart TreeがInstalled Layoutと一致する
- `composer.json` がstrict validation可能で、Path RepositoryとLock Fileを含まない
- `App\` SourceをRoot Dev Autoloadへ追加せず独立PSR-4 Boundaryを持つ
- Bootstrap、Entrypoint、Application Code、Configに `BlackOps\Internal` Importがない
- Public BootstrapがHTTPとConsoleへ同じApplicationを提供する
- Application-aware BuildがQuickstart Discovery Rootから3 Artifactを生成できる
- Inline WelcomeとDeferred Reportの既存Integration CoverageをQuickstart Sourceへ移行する
- Inline FailureがReceived、Attempt Started、Attempt Failed、Operation Failedへ到達し、Safe SurfaceがMaskを維持する
- Feature Directory間に直接依存がない
- Generated StateとSecretがVersion管理対象に入らない

## Traceability

- Layout Decision: [Installed Application Layout and Bootstrap](../decisions/064-installed-application-layout-and-bootstrap.md)
- Entrypoint Decision: [Project Root BlackOps Entrypoint](../decisions/083-project-root-blackops-entrypoint.md)
- HTTP Adapter Decision: [Skeleton HTTP Entrypoint Adapters](../decisions/069-skeleton-http-entrypoint-adapters.md)
- Installed Layout: [Installed Application Layout and Bootstrap](43-installed-application-layout-and-bootstrap.md)
- Skeleton Publication: [Composer Skeleton Publication](46-composer-skeleton-publication.md)
