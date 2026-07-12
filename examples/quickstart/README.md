# BlackOps Quickstart

Feature-first構造のBlackOps Application Skeletonである。Inline `GET /welcome` とDeferred `POST /reports` を含む。

## Requirements

- PHP 8.5以上
- Composer
- PostgreSQL
- PCNTLを有効にしたPHP CLI（Worker実行時のみ）

## Setup

DependencyをInstallし、Local Environmentと生成Directoryを準備する。

```bash
composer install
cp .env.example .env
mkdir -p var/build var/log
```

PostgreSQLを起動して `.env` の接続情報を調整する。Build、Migration、Worker、RetentionはInstallやHTTP起動では暗黙実行されない。

## Build and Database

```bash
php bin/blackops blackops:operation:list
php bin/blackops blackops:build:compile
php bin/blackops blackops:database:status
php bin/blackops blackops:database:migrate --dry-run
php bin/blackops blackops:database:migrate
```

## HTTP

PHPのBuilt-in Serverを使う場合は次を実行する。

```bash
php -S 127.0.0.1:8080 -t public
```

Inline Welcome:

```bash
curl -H 'X-Sample-Token: local-example' http://127.0.0.1:8080/welcome
```

Deferred Report:

```bash
curl -X POST -H 'Content-Type: application/json' \
  -d '{"reportName":"weekly","apiToken":"local-example"}' \
  http://127.0.0.1:8080/reports
```

Workerを明示的に実行する。

```bash
php bin/blackops blackops:worker:run --idle-sleep-milliseconds=1000
```

Self-handledなSample Report Operationは最初のAttemptでRetryable Failureを返し、次のAttemptで成功する。

## Retention

```bash
php bin/blackops blackops:retention:plan
php bin/blackops blackops:retention:purge --dry-run
```

変更を適用するPurgeは `--confirm` を明示した場合だけ実行する。

## Removing Starter Features

FeatureはAction Directory単位で独立している。Welcomeを削除する場合は `app/Feature/Welcome/` を削除する。Reportも `app/Feature/Report/` を削除するだけでよく、Provider一覧、もう一方のFeature、Bootstrap、Configは変更不要である。`config/operations.php` のDiscovery Rootに追加したOperationは次のBuildで自動検出される。

Operation自身が `OperationHandler` を実装するのが標準形である。Repository InterfaceやExternal Client等のConstructor Dependencyが必要な場合だけ、`ServiceProvider` を作り `config/app.php` の `services` へ登録する。Interface BindingはProviderが所有し、Operation自身はContainerを受け取らない。

Docker／ComposeによるLocal Runtimeは後続Taskで追加する。
