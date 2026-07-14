# BlackOps Quickstart

Feature-firstのBlackOps Application Skeletonである。Inline `GET /welcome` とDeferred `POST /reports`、PostgreSQL 18、FrankenPHP 1、PHP 8.5 CLIを含む。

## Distribution Status

このDirectoryはFramework Repository内のQuickstartであると同時に、Packagist Package `blackops/skeleton`のSource of Truthである。Release WorkflowがこのDirectoryだけを`kubotak-is/blackops-skeleton`へSplitし、Frameworkと同じVersionで公開する。Stable `1.0.0`のRemote Installを検証済みである。

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
composer create-project blackops/skeleton my-app
```

```bash
composer create-project --no-scripts blackops/skeleton my-app
php my-app/bin/setup
```

Framework Repository内のQuickstartを直接使う場合は、最初に`php bin/setup`を実行する。

Install、Build、MigrationはImage startupに含まれない。Default `docker compose up` はHealthyなPostgreSQLとWorker Mode HTTPだけを起動し、Deferred Worker、Scheduler、Migration、Retention Purgeは起動しない。HTTP Portは `.env` の `HTTP_PORT` で変更でき、既定は8080である。

Setupは次手順を表示するだけで、Composer Install、Network Access、Docker、Database、Migration、Artifact Build、Worker、Scheduler、Retentionを実行しない。

## HTTP

```bash
curl -H 'X-Sample-Token: local-example' http://127.0.0.1:8080/welcome

curl -X POST -H 'Content-Type: application/json' \
  -d '{"reportName":"weekly","apiToken":"local-example"}' \
  http://127.0.0.1:8080/reports
```

Inline JournalのSensitive値は `var/log/journal.jsonl` へMask済みで追記される。既定Deliveryは `best_effort` である。

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

Welcomeは `app/Feature/Welcome/`、Reportは `app/Feature/Report/` を削除するだけでよい。Provider一覧、もう一方のFeature、Bootstrap、Configは変更不要である。`config/operations.php` のDiscovery Rootへ追加したOperationは次のBuildで検出される。

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
