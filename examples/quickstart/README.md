# BlackOps Quickstart

Feature-firstのBlackOps Application Skeletonである。Inline `GET /welcome`、調査用Inline Failure `POST /failures`、Database Transaction付き`POST /orders`、Deferred `POST /reports`、PostgreSQL 18、FrankenPHP 1、PHP 8.5 CLIを含む。

## Distribution Status

このDirectoryはFramework Repository `main`のPreview Quickstartであると同時に、Packagist Package `blackops/skeleton`のSource of Truthである。Release WorkflowがこのDirectoryだけを`kubotak-is/blackops-skeleton`へSplitし、Frameworkと同じVersionで公開する。公開済みStable `1.1.0`にはHeader AuthenticationとPhase 13のDatabase／Transaction Journeyが未収録で、`POST /orders`も含まれない。

Local検証ではCommitted QuickstartだけをPackage Rootへ抽出し、SkeletonとFrameworkを`symlink=false`の別々のLocal Repositoryとして通常／`--no-scripts` Create-projectする。Remote検証は空のComposer HomeからPackagist Packageだけを取得する。

## Setup

```bash
php bin/setup
docker compose build app http
docker compose run --rm app composer install
docker compose run --rm app php blackops operation:list
docker compose run --rm app php blackops build:compile
docker compose run --rm app php blackops database:status
docker compose run --rm app php blackops database:migrate
docker compose up -d
```

Composer `create-project`は`post-create-project-cmd`から同じ`bin/setup`を実行する。`--no-scripts`で作成した場合、またはSetupを明示的に再実行する場合はProject Root内外のどのWorking Directoryからでも`php /path/to/my-app/bin/setup`を実行できる。Setupは`.env`がない場合だけ`.env.example`をCopyし、`var/build/`と`var/log/`を準備する。既存`.env`は変更しない。

```bash
composer create-project blackops/skeleton my-app 1.1.0
```

```bash
composer create-project --no-scripts blackops/skeleton my-app 1.1.0
php my-app/bin/setup
```

Framework Repository内のQuickstartへ直接`composer install`するだけでは、`blackops/framework:^1.1`がPackagistのStableへ解決され、`main` PreviewのSourceと一致しない。認証付き`/welcome`／`/reports`とPhase 13の`/orders`を試す場合は、[利用者向けQuickstartのRepository main Preview手順](../../docs/guide/mvp-sample.md#repository-main-preview)でFramework SourceをLocal Path Repositoryとして組み合わせる。準備後のPreview Directoryで`php bin/setup`を実行する。

Install、Build、MigrationはImage startupに含まれない。Default `docker compose up` はHealthyなPostgreSQLとWorker Mode HTTPだけを起動し、Deferred Worker、Scheduler、Migration、Retention Purgeは起動しない。HTTP Portは `.env` の `HTTP_PORT` で変更でき、既定は8080である。

Setupは次手順を表示するだけで、Composer Install、Network Access、Docker、Database、Migration、Artifact Build、Worker、Scheduler、Retentionを実行しない。

## HTTP

```bash
curl -H 'X-Sample-Token: local-example' http://127.0.0.1:8080/welcome

curl -X POST -H 'Content-Type: application/json' \
  -H 'X-Sample-Token: local-example' \
  -d '{"reference":"order-001"}' \
  http://127.0.0.1:8080/orders

curl -X POST -H 'Content-Type: application/json' \
  -H 'X-Sample-Token: local-example' \
  -d '{"reportName":"weekly","recipientEmail":"reports@example.com"}' \
  http://127.0.0.1:8080/reports

curl -X POST -H 'Content-Type: application/json' \
  -H 'X-Sample-Token: local-example' \
  -d '{"reference":"incident-demo-001","sensitiveNote":"private diagnostic note"}' \
  http://127.0.0.1:8080/failures
```

Failure Responseの`operationId`はHuman／JSONのどちらでも調べられる。

```bash
docker compose run --rm app php blackops operation:inspect <operation-id>
docker compose run --rm app php blackops operation:inspect <operation-id> --json
```

`config/logging.php`はApplication／Framework相関Logを`var/log/application.jsonl`へJSONLで出力する。Docker-only QuickstartではPostgreSQLをHostへPublishせず、ViewerもCLI ContainerのLoopback限定なので、Hostから`php blackops operation:viewer`を実行したりHost Browserで開いたりできない。Docker利用時は上記Human／JSON Inspectを使う。ViewerはConsumer E2Eと同様にViewer／HTTP Clientを同じCLI ContainerとLocal Network Namespaceへ置く場合だけ検証でき、Host Browser公開の手順ではない。

BrowserでViewerを利用するには、Application／PHP CLI／PostgreSQL／Browserが同じLocal Network Namespaceから到達可能なNative Runtimeを準備し、Project Rootで`php blackops operation:viewer`を明示実行する。Non-loopback Bindへ緩めずLoopbackを維持し、起動ごとに変わるBootstrap Tokenを保存／共有しない。

OrderはDefault DBAL `Connection`をConstructor InjectionしたRepositoryを使う。`CreateOrder::handle()`の`#[Transactional]`が最外Transactionを所有し、Container管理の`CreateOrderCommand`は`#[Transactional]`で同じConnectionへNested Required参加する。Order Rowと成功Terminal JournalがCommitした後、`#[AfterCommit]`の`RecordOrderCommit::record()`がCommit確認Rowを追加する。

```json
{"reference":"order-001","status":"created"}
```

After Commitは同期Best-effortであり、Process Crashを越える再送やDeliveryを保証しない。Email、Webhook、Message Publish等を確実に配送する場合はTransactional Outboxが必要で、現行FrameworkはOutbox PersistenceとRelayをまだ提供していない。

`X-Sample-Token`はAuthentication Middlewareだけが読み、Operation Value、Transport、Journalへ保存しない。`SAMPLE_API_TOKEN`が未設定または空なら、Authenticatorは既知値へFallbackせず構成を失敗させる。

Reportの`recipientEmail`は業務上のSensitive値の例である。HTTP内で完了するValidation RejectionのObserved Projectionでは`var/log/journal.jsonl`へ`[masked]`として記録する。Valid Deferred ReportのWorker EventはJSONLへ転送せず、Raw ValueとActor IDを含むCanonical PostgreSQL Journalが正本となる。既定Observer Deliveryは`best_effort`である。

このHeader Token認証はLocal Development専用のExampleである。ProductionではApplicationがSession、Bearer Token、External IdP等の認証方式とSecret管理へ置き換える。

Headerを省略するとAuthentication MiddlewareはAnonymousとして通過させ、`#[Authorize]`を持つOperationがOperation ID付き401でRejectする。不正なHeader値はOperation受付前の401となり、Operation IDとJournalを作らない。

```bash
curl -i http://127.0.0.1:8080/welcome
curl -i -H 'X-Sample-Token: invalid' http://127.0.0.1:8080/welcome
```

### FrankenPHP Worker Mode

Default HTTPはWorker Modeであり、Process単位でApplication、Environment、Configuration、Compile済みRuntimeを一度だけ構成する。

```bash
docker compose up -d http
curl -H 'X-Sample-Token: local-example' http://127.0.0.1:8080/welcome
```

`FRANKENPHP_MAX_REQUESTS`はWorker Threadを安全に再起動するRequest上限で、既定は1000である。Frameworkは各Request前にDatabase Connectionをhealth-checkし、Stale Connectionをcloseして一度再接続する。再接続できない場合は500として失敗し、成功Responseとして扱わない。Request終了時はOperation Scopeを検査し、JSONL Observerをflushする。Throwableまたは未完了TransactionのConnectionは次Requestへ持ち越さずcloseする。

FrankenPHPはWorker callback終了後にRequest Superglobalをcleanupするが、`$_ENV`はRequest間でresetしない。EntrypointはProcess開始時の`$_ENV`へ毎Request復元する。Application ServiceはRequest Body、Actor、Tenant、PSR-7 Request等のRequest固有Stateをpropertyやstaticへ保持せず、Operation ValueまたはExecution Contextから受け取る。

Classic Modeは明示Fallbackとして`classic-mode` Profileから起動できる。既定Portは8081で、`CLASSIC_HTTP_PORT`から変更する。

```bash
docker compose --profile classic-mode up -d http-classic
curl -H 'X-Sample-Token: local-example' http://127.0.0.1:8081/welcome
```

## Worker and Maintenance

```bash
docker compose run --rm app php blackops worker:run --iterations=1
docker compose --profile worker up worker
docker compose run --rm app php blackops retention:plan
docker compose run --rm app php blackops retention:purge --dry-run
docker compose --profile maintenance up scheduler
```

Sample Report Operationは最初のAttemptでRetryを要求し、次のAttemptで成功する。変更を適用するPurgeは `--confirm` を明示した場合だけ実行する。

## Removing Starter Features

Welcomeは `app/Feature/Welcome/`、Reportは `app/Feature/Report/`、Local Failure Exampleは`app/Feature/Diagnostics/`を削除するだけでよい。Orderを削除する場合は`app/Feature/Order/`と`migrations/Version20260718000000.php`を削除し、`ApplicationServiceProvider`からOrder用の3 Bindingを外す。`config/operations.php` のDiscovery Rootへ追加したOperationは次のBuildで検出される。

Operation自身に `handle(OperationValue): Outcome`、またはAttempt等の実行情報が必要なら `handle(OperationValue, ExecutionContext): Outcome` を定義するTyped Self-handledが標準形である。ValueとOutcomeはSignatureから推論され、`#[Accepts]`、`#[Returns]`、`OperationResult::completed()`は不要である。予期された業務拒否はFrameworkの `OperationRejectedException`、一時障害は通常のRetryable Exceptionをthrowする。FrameworkはOperationをContainerへAutowireする。Repository InterfaceやExternal Client等のConstructor Dependencyが必要な場合だけ、`ServiceProvider` を作り `config/app.php` の `services` へ登録する。

## Creating an Operation

Framework所有のGeneratorから、Build可能なTyped Self-handled Operation、Value、Outcomeを作成できる。

```bash
php blackops make:operation Billing/CreateInvoice --type=billing.invoice.create
php blackops build:compile
```

Generatorは`app/Feature/Billing/CreateInvoice/`へ3 Fileを作成する。既存Fileは上書きせず、Route、Deferred設定、Build、Database操作は行わない。生成後のSourceはApplication所有であり、Framework Updateによって書き換えられない。CommandとStubはFramework Packageが所有するため、`composer update blackops/framework`後の新規生成には更新済み実装が使われ、Projectの`blackops`を置き換える必要はない。

## Creating a Migration

Application固有のMigrationはFramework所有Generatorで作成する。

```bash
php blackops make:migration CreateOrdersTable
```

最初の実行時に`migrations/`が作られ、UTC Versionの`App\Migrations` Classが生成される。`up()`／`down()`へApplicationのSQLを記述した後、Framework Migrationと同じ明示Commandで確認・適用する。

```bash
php blackops database:status
php blackops database:migrate --dry-run
php blackops database:migrate
```

Generator自身はDatabase接続、Migration適用、Buildを行わない。Application Migrationがない場合、空の`migrations/`は不要である。
