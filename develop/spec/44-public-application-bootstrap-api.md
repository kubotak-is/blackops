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
withEnvironmentFile(?string $path = null): self
withConfiguration(?string $directory = null): self
withOperations(iterable $providers = []): self
withServices(iterable $providers = []): self
withCommands(iterable $commands = []): self
create(): Application
```

### Environment

`withEnvironment()` は解決済みのEnvironment値をApplication Configurationへ取り込む。引数を省略した場合は現在のProcess Environmentだけを読む。

`withEnvironmentFile()`は引数省略時に`<basePath>/.env`をOptional Fileとして扱い、Process Environmentを優先して不足Keyだけを読込む。Dotenv実装はFramework Internalとし、Environment Fileが存在しない場合はLocal／ProductionともFailureにしない。既存FileのUnreadable／Parse Failureは値やRaw Throwableを含まないBootstrap Errorとする。

Parse／Shape検証に成功したResolved SnapshotはBootstrap時に一度だけ`$_ENV`へ同期する。Process Environmentを優先し、`putenv()`は変更しない。Parse／Shape Failureでは`$_ENV`を部分更新せず、既存のQuickstart／Application ConsumerがBootstrap後に`$_ENV`を読む互換境界を維持する。

同じBuilderでEnvironment Sourceを複数回明示した場合は最後の`withEnvironment()`または`withEnvironmentFile()`が置き換える。Environment Sourceと`withConfiguration()`の呼出順はConfiguration評価結果を変えない。詳細は[Application Runtime and Bootstrap](78-application-runtime-and-bootstrap.md)を正本とする。

Environment値はPublic Readonly `Environment`へCopyして検証する。Config Closureは`string()`、`optionalString()`、`int()`、`positiveInt()`、`bool()`で型付き値を取得する。Secret値をError Message、Log、Debug Dumpへ出力してはならない。Environment全体を返すGetter、Global Helper、Runtime Mutationは提供しない。

### Configuration Directory

`withConfiguration()` は責務別PHP Config Fileを読み込む。引数を省略した場合は `<basePath>/config` を使用する。

Config Fileは読み込み時に副作用を起こさず、Configuration Dataの配列、またはPublic `Environment`を一つ受け取り配列を返すClosureを返す。Directoryは`withConfiguration()`で検証するが、File読込とClosure評価は`create()`まで遅延する。全Closureへ同じ最終Environment Instanceを一度だけ渡すため、`withEnvironment()`と`withConfiguration()`の呼出順に依存しない。Closure以外のCallable、Signature不正、配列以外の戻り値、未知の必須Key、無効な型、空の必須値はBootstrap Errorとする。

少なくとも次のFileを認識する。

- `app.php`
- `database.php`
- `operations.php`
- `execution.php`
- `journal.php`
- `logging.php`

`database.php`はConnection／Framework Schemaに加えてOptionalな`seeding.root`と`seeding.discovery`を扱う。省略時は`App\Infrastructure\Seed\DatabaseSeeder`と`<basePath>/app/Infrastructure/Seed`を標準Conventionとする。標準DirectoryまたはRootが存在しないApplicationはSeeding未構成として既存Processを維持し、明示値の不正はBuild Failureにする。

Optional Fileが存在しない場合のDefaultはFrameworkが所有する。Productionに必要な値が不足する場合は、安全でない推測やDevelopment DefaultへのFallbackを行わず起動を失敗させる。

### Providers and Commands

`withOperations()` はConfig由来のOperation Providerへ明示Providerを追加する。各要素は `OperationProvider` InstanceまたはそのClass Nameでなければならない。

`withServices()` はConfig由来のService Providerへ明示Providerを追加する。各要素は `ServiceProvider` InstanceまたはそのClass Nameでなければならない。

`withCommands()` はApplication独自のSymfony Console Commandを明示追加する。Configured Sourceの`#[AsCommand]`付きCommandはBuild時に自動Discoveryし、Compiled ContainerからConstructor Injectionする。明示登録はPackage Command、Instance、同一ClassのOverride／追加に使用し、Framework標準Commandの実装はFramework Packageが所有する。

SeederはApplication Commandとは別のDatabase Capabilityである。Application-aware BuildはSeederをBuild時にだけDiscoveryし、Private Service／Compiled Locator／Root Runtimeへ固定する。HTTP、Worker、通常Console CompositionはSeeder Sourceを探索しない。

重複するProvider／Commandは、同一Identityを二重登録せず、競合するCommand NameはBootstrap Errorとする。

Command Discovery Rootは`app.command_discovery`で明示し、欠落または空Listでは探索しない。Discovery結果は同じApplication Build IDのCommand Manifestへ固定し、通常のConsole List、HTTP、WorkerはSourceを探索しない。Missing／Invalid／Stale Command ManifestはDiscovered Commandを未登録として扱い、Framework `build:compile`によるRecoveryを維持する。

`#[ConsoleCommand]`付きOperationはOperation Discoveryの結果からBuild時にCommand Manifestへ固定する。Console Actorが必要なApplicationはPublic `ConsoleActorProvider`をService ProviderでBindingする。ProviderはOperation実行時だけ解決し、Kernel構成、`list`、`help`ではContainerやDatabaseとともに解決しない。

Session AuthenticationはPublic `SessionServiceProvider::bearer()`または`::cookie()` Instanceを`services`／`withServices()`へ明示追加した場合だけCompiled Containerへ登録する。ProviderはApplication-owned `SessionIdentityProvider` Class、`SessionConfiguration`、Cookie選択時のCookie名だけを受け、ApplicationへInternal Store／Clock／Random／Identifier型を露出しない。未登録ApplicationのContainer Service、Build Artifact、HTTP結果、Migrationを変えない。

## Configuration Precedence

Configurationは次の順で構成する。後の明示指定が前のDefaultを上書きまたは追加する。

1. Frameworkの安全なDefault
2. Process Environment
3. `config/*.php`
4. Builderへ明示したProvider／Command

Environment VariableとConfig Keyの対応は責務別Configが所有する。Frameworkは任意のEnvironment Variableを無条件にConfigへ展開しない。

`create()` はEnvironmentとConfigurationを一度評価・検証し、以後のProcess Compositionで共有するConfiguration Snapshotを持つ `Application` を返す。Environment自体をConfiguration Snapshot、Compiled Container、Manifestへ保持しない。作成後のProcess EnvironmentまたはConfig File変更を暗黙に再読込しない。同じBuilderから再度`create()`する場合は、新しいEnvironment InstanceでConfigを一度評価する。

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

Default Front ControllerはPublic `BlackOps\Http\SapiRuntime`へApplication Instanceを渡すだけとし、Server Request生成、Response Emit、Safe 500、Worker Loop、Environment Restore、GCをFrameworkが所有する。

公式HTTP RuntimeはFrankenPHP Worker ModeをDefaultとし、ApplicationとConfiguration SnapshotをProcess起動時に一度だけ構成する。各Request境界でFrameworkはOperation Scopeの終了を検査し、Observer Bufferをflushし、Connection Healthを確認して失敗時または未完了Transaction時のConnectionをcloseする。EntrypointはFrankenPHPが自動resetしない`$_ENV`をProcess開始時のSnapshotへ復元する。FrameworkはApplication固有Service Stateを汎用resetしないため、Application ServiceはRequest Body、Actor、Tenant、PSR-7 Request等のRequest固有Stateをpropertyまたはstaticへ保持せず、Operation ValueまたはExecution Contextから受け取る。複数Request、例外後Request、Connection切断、Memory Growth、`max_requests` RestartはDefault ServiceのConsumer E2Eで継続検証する。Classic Modeは明示Fallbackとして維持する。

Secretを含むConfiguration SnapshotをBuild Artifactへ保存しない。

`Application::http()`はCustom Server Adapter、Test、Application-owned Outer Router向けPSR-15 Escape Hatchとして維持する。Default Classic／Worker EntrypointだけがFramework-owned SAPI Runtimeを使う。

Production Logging Backendも同じConfiguration SnapshotからHTTP／Worker Process Composition時に一度だけ解決する。Request、Attempt、Log RecordごとにConfig FileまたはProcess Environmentを再読込しない。

## Console Boundary

`console()` はFramework所有の `ConsoleKernel` を返す。`ConsoleKernel` はSymfony Consoleを内部で利用し、Projectの `blackops` から実行できる。

Console KernelはApplication Configurationに応じて次のCommandを登録できる。

- Build Artifact Compile／List
- Database Migration Status／Migrate
- Worker Run
- Retention Plan／Purge
- Scheduler Run／Daemon
- Application独自Command
- `#[ConsoleCommand]`付きOperation Command

Generator Commandは`make:operation`と`make:migration`を提供する。Operation CommandはHTTPと同じArtifact、Validation、Authorization、Inline／Deferred Lifecycle、Journal、Transactionを再利用する。

Projectの `blackops` はAutoloaderと `bootstrap/app.php` を読み、`$application->console()->run()` の終了Codeを返すだけの薄いEntrypointとする。

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
- Runtime Boundary: [Application Runtime and Bootstrap](78-application-runtime-and-bootstrap.md)
