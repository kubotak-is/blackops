# Application Bootstrap Internals

Application Bootstrapは、Installed Applicationの入力をInternal Runtime Compositionへ渡せる検証済みSnapshotへ変換する境界である。Public入口は `Application::configure()`、`ApplicationBuilder`、`Environment`、`ApplicationBootstrapException` に限定する。

## Responsibilities

- `ApplicationBasePath`: Application Rootの存在確認と正規化
- Public `Environment`: EnvironmentのCopy、Key／Value型検証、型付きAccessor、安全なFailure
- `ApplicationConfigurationLoader`: 認識済みConfig Fileの遅延読込とEnvironment Closureの一回評価
- `ApplicationConfigurationRegistrations`: Config内の登録Sectionの抽出
- `ApplicationMiddlewareValidator`: Global PSR-15 Middleware ClassのContract、生成可能性、重複を検証
- `ApplicationProviderValidator`: Provider Contract、生成可能性、Identity重複の検証
- `ApplicationCommandValidator`: Command型、生成可能性、IdentityとCommand Name競合の検証
- `ApplicationConfigurationSnapshot`: Base Path、評価済みConfig、登録を保持するImmutableなInternal Snapshot
- `ApplicationBuildConfiguration`／`ApplicationDatabaseConfiguration`: HTTP Runtime用Configの安全な型検証
- `ApplicationHttpRuntime`: Handlerの遅延構成とApplication Instance単位のCache
- `ApplicationHttpRuntimeComposer`: Artifact、Connection、Inline／Deferred境界の内部Composition
- `ApplicationWorkerComposer`: Compile済みArtifact、Worker Actor、Authorization、Main／Heartbeat Connection、Worker Loopの内部Composition
- `ApplicationConsoleKernel`: Symfony Applicationと11個のLazy Command Descriptorの内部Composition
- `ApplicationConsoleCommandFactory`: Generator、Build、List、Migration、Workerの責務別遅延Factory
- `ApplicationRetentionCommandFactory`: Retention／Schedulerで共有するPolicyとRuntimeの遅延Factory

## Composition Order

BuilderはEnvironment入力とConfig Directoryを各 `with...()` 呼出時にCaptureする。Directoryの存在は即時検証するが、Config Fileの`require`とClosure評価は`create()`まで遅延する。`create()`は最終Environmentから一つのReadonly Instanceを作り、全Closureへ同じInstanceを一度だけ渡す。その後、Config由来のOperation Provider、Service Provider、Commandを先に取り出し、明示登録を後へ連結して検証する。同一Class Identityは先行登録を保持し、Command Nameが別Classと競合すると失敗する。

Configの責務別MapはSnapshotへ保持するが、Environment Instance／Raw Environment ArrayはSnapshot、Compiled Container、Manifestへ渡さない。Public APIからEnvironmentやConfig全体をDumpする経路も持たせない。Bootstrap Exceptionは入力値やClosure Throwable Detailを埋め込まず、問題のあるEnvironment名、Config File、登録種別だけを示す。

Installed Quickstartは`config/app.php`の`services`へApplication所有の`ApplicationServiceProvider`を登録し、`HttpAuthenticator`を`SampleTokenAuthenticator`へBindingする。`config/middleware.php`はFrameworkの`AuthenticationMiddleware`をGlobal Pipelineへ登録する。Expected Sample TokenはAuthenticator構築時にEnvironmentから一度だけSnapshotし、RequestごとにEnvironmentを読み直さない。未設定または空白だけの値は構成時にFail-closedとし、Default CredentialへFallbackしない。Framework Containerへ渡すのはAuthenticator Serviceであり、Credential値そのものではない。

## Process Boundary

SnapshotはHTTP Runtime Compositionが利用し、将来のConsole Compositionも同じInstanceを再利用する。`http()` の初回呼出だけがArtifactとConnectionを構成し、以後は同じPSR-15 Handlerを返す。`Application` はContainer Locator、Config Getter、Dotenv Loaderを持たない。

HTTP ComposerはOperation／HTTP ManifestとContainerをFail-fastでLoadし、単一DBAL ConnectionをCanonical Journal、Deferred Sender、Acceptance Transactionへ共有する。Inline／Deferredは同じCompiled RegistryとHTTP Manifestを使う。Global MiddlewareはConfig登録順にCompiled Containerから解決し、最初の登録を最外層としてHTTP Operation Handlerを包む。ComposerはMigration、DDL、Build、Source Discoveryを呼び出さない。

Worker ComposerもOperation ManifestとCompiled ContainerをFail-fastでLoadする。Handler ResolverとAuthorization Policy Resolverは同じCompiled Containerを共有し、Policy Constructor DependencyをRuntime ReflectionやSource DiscoveryへFallbackせず解決する。`execution.worker.id`はPostgreSQL Lease Ownerと`system` Typeのexecution Actorへ共通利用する。Main ConnectionとHeartbeat Connectionは同じDatabase設定から生成するが、Instanceは分離する。

Worker AttemptではTransport Contextのorigin／authorizationを維持し、executionだけをConfigured Worker Actorへ置き換える。Policy付きOperationはAttempt開始後、Handler前にCompiled Policyを再評価する。Security DenialはTerminal Rejected、Policy Backend例外は既存Supervision境界へ分類する。

`Application` と `ApplicationBuilder` のconstructorはprivateである。生成BridgeはPublic型内部のprivate実装に閉じ、利用者がSnapshot Factoryを注入または差し替えるPublic Extension Pointは設けない。

Console KernelもApplication Instance単位でCacheする。Command名、Description、Option Definitionは常時登録するが、実Command Factoryはexecute時だけ呼ぶ。これにより`list`／`help`はDBAL Connection、Artifact、PCNTLを生成しない。

`make:operation`と`make:migration`も同じLazy Descriptorとして登録する。Kernel構成時はOperation Source、Migration Directory、Framework Stubを読まず、Command実行時だけApplication Base PathとFramework Package内StubをGeneratorへ渡す。Application CommandはGenerator名を上書きできない。

Database Command実行時だけApplication Console FactoryがDBAL Connectionと`<basePath>/migrations` ConventionをMigration Runnerへ渡す。Generator、Build、Worker、HTTP、Scheduler、`list`、`help`はApplication MigrationをScanまたは適用しない。
