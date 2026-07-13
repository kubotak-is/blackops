# Local Runtime

InstallしたApplicationは、Docker ComposeでPHP 8.5 CLI、FrankenPHP 1、PostgreSQL 18を起動できます。依存Install、Artifact Build、MigrationはImage起動へ含まれないため、順番に明示実行します。

## ImageとDependency

Project Rootで実行します。

```bash
docker compose build app http
docker compose run --rm app composer install
```

Defaultの`docker compose up`はPostgreSQLとHTTPだけを継続起動します。Worker、Scheduler、Migration、Retention Purgeは自動開始しません。

## OperationとArtifact

Discovery対象のOperationを確認し、Operation Manifest、HTTP Manifest、DI Containerを`var/build/`へCompileします。

```bash
docker compose run --rm app php bin/blackops blackops:operation:list
docker compose run --rm app php bin/blackops blackops:build:compile
```

BuildはTyped Self-handled Signatureを検証し、OperationをHandlerとしてContainerへ自動登録します。HTTP／Worker RuntimeはCompile済みArtifactだけを読み、Request処理中にSource Discoveryや再Compileを行いません。

## Database Migration

Statusを確認してからFramework Migrationを適用します。

```bash
docker compose run --rm app php bin/blackops blackops:database:status
docker compose run --rm app php bin/blackops blackops:database:migrate --dry-run
docker compose run --rm app php bin/blackops blackops:database:migrate
```

HTTPやWorkerの起動時にMigrationは実行されません。Application Migrationがある場合も同じCommandへ統合されます。

## HTTPを起動する

```bash
docker compose up -d
```

既定Portは`8080`です。`.env`の`HTTP_PORT`で変更できます。Welcome Operationを実行します。

```bash
curl -H 'X-Sample-Token: local-example' http://127.0.0.1:8080/welcome
```

```json
{"message":"Welcome to BlackOps"}
```

FrankenPHPはLocalではplain HTTPのclassic modeで動作します。TLS、Domain、Process SupervisionはDeployment環境が所有します。

## WorkerとMaintenance

Deferred OperationはHTTP Processとは別のWorkerで実行します。

```bash
docker compose run --rm app php bin/blackops blackops:worker:run --iterations=1
docker compose --profile worker up worker
```

SchedulerとRetentionも明示Profile／Commandだけで開始します。

```bash
docker compose run --rm app php bin/blackops blackops:retention:plan
docker compose run --rm app php bin/blackops blackops:retention:purge --dry-run
docker compose --profile maintenance up scheduler
```

変更を伴うPurgeは`--confirm`を明示するまで実行されません。作業後はLocal Runtimeを停止できます。

```bash
docker compose down
```

InlineとDeferredを続けて試す場合は[Quickstart](mvp-sample.md)へ進んでください。Runtime構成の詳細は[Application Bootstrap](application-bootstrap.md)と[Execution](execution.md)を参照してください。
