# BlackOps Quickstart

Feature-firstのBlackOps Application Skeletonである。Inline `GET /welcome` とDeferred `POST /reports`、PostgreSQL 18、FrankenPHP 1、PHP 8.5 CLIを含む。

## Distribution Status

このDirectoryは `blackops/skeleton` のPackage Source Boundaryであり、現時点ではFramework Repository内のLocal Quickstartである。Packagist公開、Remote `composer create-project blackops/skeleton my-app`、公開PackageからのInstall Smoke TestはPhase 8で行う。

Release前検証ではCommitted QuickstartだけをLocal Package Rootへ抽出し、SkeletonとFrameworkを`symlink=false`の別々のLocal Repositoryとして通常／`--no-scripts` Create-projectする。Local Smokeは成功しているが、Remote Package公開の証拠ではない。

## Setup

```bash
php bin/setup
docker compose build app http
docker compose run --rm app composer install
docker compose run --rm app php bin/blackops blackops:operation:list
docker compose run --rm app php bin/blackops blackops:build:compile
docker compose run --rm app php bin/blackops blackops:database:status
docker compose run --rm app php bin/blackops blackops:database:migrate
docker compose up -d
```

Composer `create-project`は`post-create-project-cmd`から同じ`bin/setup`を実行する。`--no-scripts`で作成した場合、またはSetupを明示的に再実行する場合はProject Root内外のどのWorking Directoryからでも`php /path/to/my-app/bin/setup`を実行できる。Setupは`.env`がない場合だけ`.env.example`をCopyし、`var/build/`と`var/log/`を準備する。既存`.env`は変更しない。

```bash
composer create-project --no-scripts blackops/skeleton my-app
php my-app/bin/setup
```

Remote `composer create-project`はPackage公開後に利用可能になる。現在のFramework Repository内Quickstartでは最初のSetup手順を直接実行する。

Install、Build、MigrationはImage startupに含まれない。Default `docker compose up` はHealthyなPostgreSQLとHTTPだけを起動し、Worker、Scheduler、Migration、Retention Purgeは起動しない。HTTP Portは `.env` の `HTTP_PORT` で変更でき、既定は8080である。

Setupは次手順を表示するだけで、Composer Install、Network Access、Docker、Database、Migration、Artifact Build、Worker、Scheduler、Retentionを実行しない。

## HTTP

```bash
curl -H 'X-Sample-Token: local-example' http://127.0.0.1:8080/welcome

curl -X POST -H 'Content-Type: application/json' \
  -d '{"reportName":"weekly","apiToken":"local-example"}' \
  http://127.0.0.1:8080/reports
```

Inline JournalのSensitive値は `var/log/journal.jsonl` へMask済みで追記される。既定Deliveryは `best_effort` である。

## Worker and Maintenance

```bash
docker compose run --rm app php bin/blackops blackops:worker:run --iterations=1
docker compose --profile worker up worker
docker compose run --rm app php bin/blackops blackops:retention:plan
docker compose run --rm app php bin/blackops blackops:retention:purge --dry-run
docker compose --profile maintenance up scheduler
```

Sample Report Operationは最初のAttemptでRetryを要求し、次のAttemptで成功する。変更を適用するPurgeは `--confirm` を明示した場合だけ実行する。

## Removing Starter Features

Welcomeは `app/Feature/Welcome/`、Reportは `app/Feature/Report/` を削除するだけでよい。Provider一覧、もう一方のFeature、Bootstrap、Configは変更不要である。`config/operations.php` のDiscovery Rootへ追加したOperationは次のBuildで検出される。

Operation自身に `handle(OperationValue): OperationResult`、またはAttempt等の実行情報が必要なら `handle(OperationValue, ExecutionContext): OperationResult` を定義するTyped Self-handledが標準形である。FrameworkはOperationをContainerへAutowireする。Repository InterfaceやExternal Client等のConstructor Dependencyが必要な場合だけ、`ServiceProvider` を作り `config/app.php` の `services` へ登録する。
