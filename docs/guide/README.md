# BlackOps - The PHP Framework

BlackOpsは、PHP 8.5向けのHeadless Operation Frameworkです。同期HTTP実行とPostgreSQLを使ったDeferred実行を同じOperation Modelで扱い、Lifecycle Journal、Retry、Outcome、Retention、BlackOps CLIを提供します。

### [Authoring](operations.md)

`#[Route]`で同期API、`#[Deferred]`で非同期化します。HTTPリクエストもコンソールコマンドもJobも、すべてはOperationで統一されます。

### [Lifecycle](operation-lifecycle.md)

受理・試行・リトライ・拒否・完了をFrameworkが自動でJournalへ記録します。「なぜ失敗したか」をFrameworkが記録します。

### [Journal](journal.md)

Canonical JournalとObserverへ渡すObserved Projectionを区別し、JSONLの構造、Replay、Retention、Securityの境界を確認します。

### [Frontend](frontend.md)

BlackOpsはフロントエンドを持ちません。代わりに、JavaScript向けに接続クライアントのコードを自動生成します。フロントエンドはNext.jsでもNuxtでもSvelteKitでもお好きなFrameworkと組み合わせられます。

## 最短で試す

[Quickstart and Skeleton](mvp-sample.md)は空のディレクトリからComposerによるインストール、Inlineリクエスト、Deferred受付、Worker実行までを一つのページで案内します。初めて使う場合は[Install](installation.md)で前提を確認してから進んでください。

## 目的から例を選ぶ

- [Quickstart and Skeleton](mvp-sample.md)は、Typed Operation、HTTP 202、Worker、Journal、Status／Outcome、Generated Operation ObjectというFramework Contractを最短距離で確認します。
- [BlackOps Board Reference Application](community-board.md)は、Application-owned Authentication、Domain／Infrastructure、SvelteKit Same-origin BFF、Inline Post／Comment、Deferred Digest、Accessible Browser UIまでを一続きで確認します。

まずFrameworkの形を覚える場合はQuickstart、実Applicationの責任分界をBrowserから追う場合はBlackOps Boardを選んでください。

## 読み進め方

1. [Install](installation.md): Stableと`main`の前提を確認してProjectを作る
2. [Quickstart and Skeleton](mvp-sample.md): InstallからInline／Deferred／Workerまで動かす
3. [First Operation](first-operation.md): Generatorから自分のOperationを実装する
4. [Directory](directory-structure.md): Applicationが所有するディレクトリ構成をつかむ
5. [Local Runtime](runtime-bootstrap.md): 既定のWorker ModeとClassic Fallbackを運用する

初期DataやDemo Fixtureが必要なApplicationでは、[Seeder](database-seeding.md)でRoot Seeder、子Seeder、Migration／Build／Seedの順序を確認してください。

設計から理解する場合は[What's BlackOps](why-blackops.md)、[Core Concepts](core-concepts.md)、[Lifecycle](operation-lifecycle.md)、[Journal](journal.md)の順に進みます。必要なページだけを探す場合は、左側のメニューまたは検索から[Authoring](operations.md)、[Inline and Deferred](execution.md)、[Testing](testing.md)、[Deployment](deployment.md)、[Troubleshooting](troubleshooting.md)へ進めます。

Tenant付きOperation、Protected Storage、Breaking Upgrade、Key Rotationを導入する場合は、Repository `main`専用の[Tenant and Storage Protection](tenant-protection.md)をStep順に完走してください。Stable `1.1.0`にはこのTenant／Storage Protection契約がないため、Stable利用者は[Stableとmain](mvp-status.md#stableとmain)へ戻ってください。

ApplicationのAuthentication実装を薄くする場合は、Framework同梱のOpt-in Session Coreを[Session AuthenticationをOpt-in登録する](application-bootstrap.md#session-authenticationをopt-in登録する)で登録し、[HTTP Authenticationの境界](security.md#http-authenticationの境界)でToken LifecycleとApplication責務の分界を確認できます。

Install直後のApplicationへUser／Password／Register／Login／Logoutを追加する場合は、[Session Authentication Starter](security.md#session-authentication-starter)の`make:auth`手順を使います。

## ドキュメントの公開範囲

このWebsiteはRepository `main`の最新ドキュメントです。Stableは`1.1.0`です。`main`にはPHP Operationから生成するFrontend Operation ObjectとLocal Full-stack Reference Applicationもありますが、Stable `1.1.0`には含まれません。BlackOpsはExperimentalであり、1.x Minor間のBackward CompatibilityとProduction Readinessを保証しません。Production Readyは2.xから予定します。各ページ上部のVersion Noticeと[Releases](mvp-status.md)を確認してください。
