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

## Composition Order

BuilderはEnvironmentとConfigを各 `with...()` 呼出時にCaptureする。`create()` ではConfig由来のOperation Provider、Service Provider、Commandを先に取り出し、明示登録を後へ連結して検証する。同一Class Identityは先行登録を保持し、Command Nameが別Classと競合すると失敗する。

Configの責務別MapはSnapshotへそのまま保持するが、Public APIからEnvironmentやConfig全体をDumpする経路は持たせない。Bootstrap Exceptionは入力値を埋め込まず、問題のあるEnvironment名、Config File、登録種別だけを示す。

## Process Boundary

Snapshotは将来のHTTP／Console Runtime Compositionが再利用するためのInternal型であり、現段階ではRuntimeを起動しない。`Application` はContainer Locator、HTTP／Console Placeholder、Dotenv Loaderを持たない。次のComposition実装はSnapshotを消費し、Application Directory規約やInternal型をInstalled Applicationへ漏らさないこと。

`Application` と `ApplicationBuilder` のconstructorはprivateである。生成BridgeはPublic型内部のprivate実装に閉じ、利用者がSnapshot Factoryを注入または差し替えるPublic Extension Pointは設けない。
