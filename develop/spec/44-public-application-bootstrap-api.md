# Public Application Bootstrap API

## Purpose

Frameworkは、ApplicationがInternal Runtime Classを直接組み立てずにHTTP、Console、Worker、Build、Migration、Retention、Schedulerを同じ設定から起動できるPublic Composition APIを提供する。

Public Bootstrap APIは `BlackOps\Application` Namespaceへ置き、すべて `#[PublicApi]` の互換性Contractとする。Public Signatureへ `BlackOps\Internal` 型を露出してはならない。

## Application Builder

Bootstrapの開始点は次とする。

```php
Application::configure(string $basePath): ApplicationBuilder
```

`basePath` はApplication Rootの実在する絶対Pathへ正規化する。空文字、存在しないDirectory、File Pathは拒否する。

`ApplicationBuilder` は次のFluent Configurationを提供する。

```php
withEnvironment(?array $variables = null): self
withConfiguration(?string $directory = null): self
withOperations(iterable $providers = []): self
withServices(iterable $providers = []): self
withCommands(iterable $commands = []): self
create(): Application
```

### Environment

`withEnvironment()` は解決済みのEnvironment値をApplication Configurationへ取り込む。引数を省略した場合は現在のProcess Environmentを読む。

Frameworkは `.env` Fileを探索または解析しない。Skeletonの `bootstrap/app.php` がDotenv Packageを呼び出した後に `withEnvironment()` を実行する。

Environment値はStringを基本とし、Config ValidationがBoolean、Integer、Enum、Duration等の意味へ変換する。Secret値をError Message、Log、Debug Dumpへ出力してはならない。

### Configuration Directory

`withConfiguration()` は責務別PHP Config Fileを読み込む。引数を省略した場合は `<basePath>/config` を使用する。

Config Fileは読み込み時に副作用を起こさず、Configuration Dataを返す。存在するFileが配列以外を返す場合、未知の必須Key、無効な型、空の必須値はBootstrap Errorとする。

少なくとも次のFileを認識する。

- `app.php`
- `database.php`
- `operations.php`
- `execution.php`
- `journal.php`

Optional Fileが存在しない場合のDefaultはFrameworkが所有する。Productionに必要な値が不足する場合は、安全でない推測やDevelopment DefaultへのFallbackを行わず起動を失敗させる。

### Providers and Commands

`withOperations()` はConfig由来のOperation Providerへ明示Providerを追加する。各要素は `OperationProvider` InstanceまたはそのClass Nameでなければならない。

`withServices()` はConfig由来のService Providerへ明示Providerを追加する。各要素は `ServiceProvider` InstanceまたはそのClass Nameでなければならない。

`withCommands()` はApplication独自のSymfony Console Commandを追加する。Framework標準Commandの実装はFramework Packageが所有する。

重複するProvider／Commandは、同一Identityを二重登録せず、競合するCommand NameはBootstrap Errorとする。

## Configuration Precedence

Configurationは次の順で構成する。後の明示指定が前のDefaultを上書きまたは追加する。

1. Frameworkの安全なDefault
2. Process Environment
3. `config/*.php`
4. Builderへ明示したProvider／Command

Environment VariableとConfig Keyの対応は責務別Configが所有する。Frameworkは任意のEnvironment Variableを無条件にConfigへ展開しない。

`create()` はConfigurationを検証し、以後のProcess Compositionで共有するSnapshotを持つ `Application` を返す。作成後のProcess EnvironmentまたはConfig File変更を暗黙に再読込しない。

## Application Object

`Application` はProcess Entrypoint専用のComposition Rootである。

```php
http(): Psr\Http\Server\RequestHandlerInterface
console(): BlackOps\Application\ConsoleKernel
```

HTTP RuntimeとConsole Kernelは必要になった時点で遅延構成し、同じApplication Configuration Snapshotを使う。同じApplication Instanceから同じProcess Boundaryを複数回取得した場合、同じ構成済みInstanceを返す。

`Application` は次を公開しない。

- PSR-11 Containerそのものを取得するService Locator Method
- Internal Runtime／Factory／Storage Object
- CredentialまたはSecretを含む未加工Config Dump
- HTTP起動時の暗黙MigrationまたはBuild

## HTTP Boundary

`http()` はPSR-15 `RequestHandlerInterface` を返す。

- Inline RouteとDeferred Routeを同じCompile済みHTTP Manifestから構成する
- Production Artifactが不足、不正、Build ID不一致の場合は起動を失敗させる
- Production RuntimeでSource DiscoveryへFallbackしない
- Migrationを暗黙実行しない
- RequestごとにApplication Configurationを再読込しない

FrankenPHP Front ControllerはServer Requestの生成とResponse Emitだけを所有し、Framework Runtime Compositionを複製しない。

## Console Boundary

`console()` はFramework所有の `ConsoleKernel` を返す。`ConsoleKernel` はSymfony Consoleを内部で利用し、Projectの `bin/blackops` から実行できる。

Console KernelはApplication Configurationに応じて次のCommandを登録できる。

- Build Artifact Compile／List
- Database Migration Status／Migrate
- Worker Run
- Retention Plan／Purge
- Scheduler Run／Daemon
- Application独自Command

Generator CommandはPhase 9で追加する。Phase 7では既存Runtime／Maintenance Commandの安全なPublic Compositionを対象とする。

Projectの `bin/blackops` はAutoloaderと `bootstrap/app.php` を読み、`$application->console()->run()` の終了Codeを返すだけの薄いEntrypointとする。

## Failure Contract

Configuration、Provider、Artifact、Runtime Dependencyの不備は、責務を示すPublic Bootstrap ExceptionとしてProcess開始前に失敗させる。

Error Messageは問題のConfig KeyまたはPathを示すが、Password、Token、DSN Credential等のSecret値を含めない。

## Verification

- Public API Architecture Guardが全 `BlackOps\Application` Public Signatureを検証する
- BuilderのDefault、Precedence、Validation、Duplicate処理をUnit Testする
- HTTPとConsoleが同じConfiguration Snapshotを使うことをIntegration Testする
- Skeleton Bootstrapに `BlackOps\Internal` ImportがないことをArchitecture Testする
- Framework Update後もProject所有Entrypointを変更せずCommand実装を更新できることをConsumer Testする

## Traceability

- Decision: [D064 Installed Application Layout and Bootstrap](../decisions/064-installed-application-layout-and-bootstrap.md)
- Layout: [Installed Application Layout and Bootstrap](43-installed-application-layout-and-bootstrap.md)
- Public API: [Core API](17-core-api.md)
