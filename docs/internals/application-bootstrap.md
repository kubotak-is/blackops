# Application Bootstrap Internals

Application Bootstrapは、Installed Applicationの入力をInternal Runtime Compositionへ渡せる検証済みSnapshotへ変換する境界である。Public入口は `Application::configure()`、`ApplicationBuilder`、`ApplicationBootstrapException` に限定する。

## Responsibilities

- `ApplicationBasePath`: Application Rootの存在確認と正規化
- `ApplicationEnvironment`: EnvironmentのKey／Value型検証
- `ApplicationConfigurationLoader`: 認識済みConfig Fileの隔離された一回読込
- `ApplicationConfigurationRegistrations`: Config内の登録Sectionの抽出
- `ApplicationProviderValidator`: Provider Contract、生成可能性、Identity重複の検証
- `ApplicationCommandValidator`: Command型、生成可能性、IdentityとCommand Name競合の検証
- `ApplicationConfigurationSnapshot`: Base Path、Environment、Config、登録を保持するImmutableなInternal Snapshot
- `ApplicationBuildConfiguration`／`ApplicationDatabaseConfiguration`: HTTP Runtime用Configの安全な型検証
- `ApplicationHttpRuntime`: Handlerの遅延構成とApplication Instance単位のCache
- `ApplicationHttpRuntimeComposer`: Artifact、Connection、Inline／Deferred境界の内部Composition
- `ApplicationConsoleKernel`: Symfony Applicationと9つのLazy Command Descriptorの内部Composition
- `ApplicationConsoleCommandFactory`: Build、List、Migration、Workerの責務別遅延Factory
- `ApplicationRetentionCommandFactory`: Retention／Schedulerで共有するPolicyとRuntimeの遅延Factory

## Composition Order

BuilderはEnvironmentとConfigを各 `with...()` 呼出時にCaptureする。`create()` ではConfig由来のOperation Provider、Service Provider、Commandを先に取り出し、明示登録を後へ連結して検証する。同一Class Identityは先行登録を保持し、Command Nameが別Classと競合すると失敗する。

Configの責務別MapはSnapshotへそのまま保持するが、Public APIからEnvironmentやConfig全体をDumpする経路は持たせない。Bootstrap Exceptionは入力値を埋め込まず、問題のあるEnvironment名、Config File、登録種別だけを示す。

## Process Boundary

SnapshotはHTTP Runtime Compositionが利用し、将来のConsole Compositionも同じInstanceを再利用する。`http()` の初回呼出だけがArtifactとConnectionを構成し、以後は同じPSR-15 Handlerを返す。`Application` はContainer Locator、Config Getter、Dotenv Loaderを持たない。

HTTP ComposerはOperation／HTTP ManifestとContainerをFail-fastでLoadし、単一DBAL ConnectionをCanonical Journal、Deferred Sender、Acceptance Transactionへ共有する。Inline／Deferredは同じCompiled RegistryとHTTP Manifestを使う。ComposerはMigration、DDL、Build、Source Discoveryを呼び出さない。

`Application` と `ApplicationBuilder` のconstructorはprivateである。生成BridgeはPublic型内部のprivate実装に閉じ、利用者がSnapshot Factoryを注入または差し替えるPublic Extension Pointは設けない。

Console KernelもApplication Instance単位でCacheする。Command名、Description、Option Definitionは常時登録するが、実Command Factoryはexecute時だけ呼ぶ。これにより`list`／`help`はDBAL Connection、Artifact、PCNTLを生成しない。
