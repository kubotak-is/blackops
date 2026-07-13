# Local Runtimeを起動する

InstallしたApplicationは、Docker ComposeでPHP 8.5 CLI、FrankenPHP 1、PostgreSQL 18を起動できます。依存Install、Artifact Build、MigrationはImage起動へ含まれないため、順番に明示実行します。OperationのRuntime検索情報を持つ[Manifest](glossary.md#manifest)はBuildで生成します。

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
docker compose run --rm app php blackops blackops:operation:list
docker compose run --rm app php blackops blackops:build:compile
```

BuildはTyped Self-handled Signatureを検証し、OperationをHandlerとしてContainerへ自動登録します。HTTP／Worker RuntimeはCompile済みArtifactだけを読み、Request処理中にSource Discoveryや再Compileを行いません。

## Database Migration

Statusを確認してからFramework Migrationを適用します。

```bash
docker compose run --rm app php blackops blackops:database:status
docker compose run --rm app php blackops blackops:database:migrate --dry-run
docker compose run --rm app php blackops blackops:database:migrate
```

HTTPやWorkerの起動時にMigrationは実行されません。Application Migrationがある場合も同じCommandへ統合されます。

## HTTPを起動する

```bash
docker compose up -d
```

既定Portは`8080`です。`.env`の`HTTP_PORT`で変更できます。Welcome Operationを実行します。

```bash
read -rsp 'Sample token: ' SAMPLE_TOKEN && printf '\n'
curl -H "X-Sample-Token: ${SAMPLE_TOKEN}" http://127.0.0.1:8080/welcome
unset SAMPLE_TOKEN
```

```json
{"message":"Welcome to BlackOps"}
```

FrankenPHPはLocalではplain HTTPのClassic Modeで動作します。TLS、Domain、Process SupervisionはDeployment環境が所有します。

### Worker Modeを明示的に試す

Worker ModeはApplication、Environment、Configuration、Compile済みRuntimeをProcess起動時に一度だけ構成するOpt-inです。DefaultのClassic Modeを停止し、専用Profileを起動します。

```bash
docker compose stop http
docker compose --profile worker-mode up -d http-worker
curl -H 'X-Sample-Token: local-example' http://127.0.0.1:8081/welcome
```

Worker ModeのPortは既定8081で、`WORKER_HTTP_PORT`から変更できます。1 WorkerあたりのRequest上限は`FRANKENPHP_MAX_REQUESTS`で指定し、既定1000に達するとFrankenPHPがWorker Threadを再起動します。

Frameworkは各Request前にDatabase Connectionを確認し、Stale Connectionをcloseして一度だけ再接続します。再接続できないRequest、Throwableが発生したRequest、未完了Transactionを残したRequestは500となり、次RequestへConnectionを持ち越しません。Operation ScopeはRequest終了時に空であることを確認し、JSONL Journalは毎Request flushします。

Worker ModeではApplication ServiceもRequest間で再利用されます。Request Body、Actor、Tenant、PSR-7 Request等をService propertyやstaticへ保存せず、Operation ValueまたはExecution Contextから受け取ります。FrankenPHPが自動resetしない`$_ENV`はEntrypointがProcess開始時の値へ復元します。

Classic Modeへ戻す場合はWorker serviceを停止し、Default HTTPを起動します。同じ`config/`、Build Artifact、Database Configurationを使うため、Application Codeの別実装は不要です。

```bash
docker compose --profile worker-mode stop http-worker
docker compose up -d http
```

## WorkerとMaintenance

Deferred OperationはHTTP Processとは別のWorkerで実行します。

```bash
docker compose run --rm app php blackops blackops:worker:run --iterations=1
docker compose --profile worker up worker
```

SchedulerとRetentionも明示Profile／Commandだけで開始します。

```bash
docker compose run --rm app php blackops blackops:retention:plan
docker compose run --rm app php blackops blackops:retention:purge --dry-run
docker compose --profile maintenance up scheduler
```

変更を伴うPurgeは`--confirm`を明示するまで実行されません。作業後はLocal Runtimeを停止できます。

```bash
docker compose down
```

InlineとDeferredを続けて試す場合は[Quickstart](mvp-sample.md)へ進んでください。Runtime構成の詳細は[Application Bootstrap](application-bootstrap.md)と[Execution](execution.md)を参照してください。
