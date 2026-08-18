# BlackOps - The PHP Framework

BlackOpsは、PHP 8.5向けのHeadless Operation Frameworkです。HTTPとWorkerの処理を一つのOperationとして扱い、受付・再試行・完了までを同じIDで追跡できるPHP Frameworkです。この処理単位をOperation、その識別子をOperation IDと呼びます。Operationは型付きInputからOutcomeまでを一つの契約にまとめます。

## Start Here

最初は[Install](installation.md)でProjectを作り、[Quickstart and Skeleton](mvp-sample.md)でHTTPの同期処理とWorkerの非同期処理を動かします。ここで、InlineはRequest内で完了する実行、Deferred Workerは受付後に同じOperation IDで続ける実行、JournalはそのLifecycle事実を順序付きで記録する仕組みだと確認できます。[First Operation](first-operation.md)では、自分のOperationを生成してHTTP 202、Status、Worker、Typed Outcomeまで確認します。

### [What's BlackOps](why-blackops.md)

BlackOpsはHTTP、Console、Workerなどの入口から受け取った仕事をOperationへそろえ、同じOperation IDで受付・再試行・完了を確認できるHeadless Operation Frameworkです。固有語と実行境界は次のQuickstartと[What's BlackOps](why-blackops.md)で順に説明します。

### 最短で試す

[Quickstart and Skeleton](mvp-sample.md)は空のディレクトリからComposerによるインストール、Inlineリクエスト、Deferred受付、Worker実行までを一つのページで案内します。初めて使う場合は[Install](installation.md)で前提を確認してから進んでください。

### 目的から例を選ぶ

- [Quickstart and Skeleton](mvp-sample.md)は、Typed Operation、HTTP 202、Worker、Journal、Status／Outcome、Generated Operation ObjectというFramework Contractを最短距離で確認します。
- [BlackOps Board Reference Application](community-board.md)は、Application-owned Authentication、Domain／Infrastructure、SvelteKit Same-origin BFF、Inline Post／Comment、Deferred Digest、Accessible Browser UIまでを一続きで確認します。

まずFrameworkの形を覚える場合はQuickstart、実Applicationの責任分界をBrowserから追う場合はBlackOps Boardを選んでください。

### 読み進め方

1. [Install](installation.md): Stable `1.2.0`の前提を確認してProjectを作る
2. [Quickstart and Skeleton](mvp-sample.md): InstallからInline／Deferred／Workerまで動かす
3. [First Operation](first-operation.md): Generatorから自分のOperationを実装する
4. [Directory](directory-structure.md): Applicationが所有するディレクトリ構成をつかむ
5. [Local Runtime](runtime-bootstrap.md): 既定のWorker ModeとClassic Fallbackを運用する

初期DataやDemo Fixtureが必要なApplicationでは、[Seeder](database-seeding.md)でRoot Seeder、子Seeder、Migration／Build／Seedの順序を確認してください。

設計から理解する場合は[What's BlackOps](why-blackops.md)、[Core Concepts](core-concepts.md)、[Lifecycle](operation-lifecycle.md)、[Journal](journal.md)の順に進みます。必要なページだけを探す場合は、左側のメニューまたは検索から[Authoring](operations.md)、[Inline and Deferred](execution.md)、[Testing](testing.md)、[Deployment](deployment.md)、[Troubleshooting](troubleshooting.md)へ進めます。

Tenant付きOperation、Protected Storage、Breaking Upgrade、Key Rotationを導入する場合は、公開済みExperimental Stable `1.2.0`の[Tenant and Storage Protection](tenant-protection.md)をStep順に完走してください。Framework PackageのCapabilityとApplication-owned責務は[Releases](mvp-status.md)で確認してください。

ApplicationのAuthentication実装を薄くする場合は、Framework同梱のOpt-in Session Coreを[Session AuthenticationをOpt-in登録する](application-bootstrap.md#session-authenticationをopt-in登録する)で登録し、[HTTP Authenticationの境界](security.md#http-authenticationの境界)でToken LifecycleとApplication責務の分界を確認できます。

Install直後のApplicationへUser／Password／Register／Login／Logoutを追加する場合は、[Session Authentication Starter](security.md#session-authentication-starter)の`make:auth`手順を使います。

## Build

[Authoring](operations.md)、[Generators](project-generators.md)、[Value and Validation](validation.md)、[Inline and Deferred](execution.md)、[Authentication](authentication.md)、[Authorization](authorization.md)、[Frontend](frontend.md)からApplicationを組み立てます。

### [Authoring](operations.md)

`#[Route]`で同期API、`#[Deferred]`で非同期化します。HTTPリクエストもコンソールコマンドもJobも、すべてはOperationで統一されます。

### [Frontend](frontend.md)

BlackOpsはフロントエンドを持ちません。代わりに、JavaScript向けに接続クライアントのコードを自動生成します。フロントエンドはNext.jsでもNuxtでもSvelteKitでもお好きなFrameworkと組み合わせられます。

## Async and Lifecycle

[Lifecycle](operation-lifecycle.md)、[Execution Context](execution-context.md)、[Outcome](outcome-retrieval.md)、[Outbox](outbox.md)、[Journal](journal.md)で受理後の実行と結果を追跡します。

### [Lifecycle](operation-lifecycle.md)

受理・試行・リトライ・拒否・完了をFrameworkが自動でJournalへ記録します。「なぜ失敗したか」をFrameworkが記録します。

### [Journal](journal.md)

Canonical JournalとObserverへ渡すObserved Projectionを区別し、JSONLの構造、Replay、Retention、Securityの境界を確認します。

## Data and Security

[Transaction](database-and-transactions.md)、[Migration](database-migrations.md)、[Seeder](database-seeding.md)、[Retention](retention.md)、[Security](security.md)、[Tenant and Storage Protection](tenant-protection.md)でDataと機密性の境界を確認します。

## Operate

[Configuration](configuration.md)、[Deployment](deployment.md)、[Observability](observability.md)、[Testing](testing.md)、[Troubleshooting](troubleshooting.md)から運用時の確認順を選びます。

## Reference

最初にCommandを調べる場合は[BlackOps CLI](project-cli.md)を開いてください。正確な契約は[BlackOps CLI](project-cli.md)、[Application Bootstrap](application-bootstrap.md)、[Core API](core-api.md)、[Attributes](attributes.md)、[Observer Replay](observer-replay.md)、[Glossary](glossary.md)で調べられます。

## Releases

提供範囲と移行情報は[Releases](mvp-status.md)に集約しています。

### ドキュメントの公開範囲

このWebsiteはRepository `main`の最新Sourceから生成します。公開済みExperimental Stableは`1.2.0`です。Frontend Operation ObjectはFramework／Skeletonの公開Surface、BlackOps BoardはRepository Exampleとして提供します。BlackOpsはExperimentalであり、1.x Minor間のBackward CompatibilityとProduction Readinessを保証しません。Production Readyは2.xから予定します。各ページ上部のVersion Noticeと[Releases](mvp-status.md)を確認してください。
