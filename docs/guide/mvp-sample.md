# Quickstart

空DirectoryからBlackOpsをInstallし、Inline HTTPとDeferred Workerを一続きで確認します。このPageは`main` DocumentのProject Root Entrypointを使うため、SkeletonとFrameworkの`dev-main`を明示します。Latest Stable `1.0.0`は同じRuntime機能を持ちますが、CLI EntrypointはProject Rootではなく`bin` Directory内にあり、Generatorと宣言的Validationは含みません。Stableだけを使う場合は[Installation](installation.md)と[Current Status](mvp-status.md)を先に確認してください。

## 1. ComposerでProjectを作る

PHP 8.5、Composer、Docker Composeを利用できる空Directoryで実行します。

```bash
composer create-project blackops/skeleton my-app dev-main
cd my-app
composer require blackops/framework:dev-main
```

`create-project`は`.env`、`var/build/`、`var/log/`を準備します。Composer Scriptを無効にした場合は`php bin/setup`を一度実行してください。FrameworkのInstall後もProject所有の`blackops`は入口のままで、Command実装は`vendor/blackops/framework`から読み込まれます。

## 2. Image、Artifact、Databaseを準備する

```bash
docker compose build app http
docker compose run --rm app composer install
docker compose run --rm app php blackops build:compile
docker compose run --rm app php blackops database:migrate
docker compose up -d
```

BuildはSourceからOperationとHTTP Manifest、DI Containerを生成します。MigrationとBuildはHTTP起動時に暗黙実行されません。`docker compose up -d`はHealthyなPostgreSQLとWorker Mode HTTPだけを起動し、Deferred WorkerやSchedulerを勝手に常駐させません。Classic Modeは`classic-mode` Profileの明示Fallbackです。

## 3. Inline Operationを呼ぶ

```bash
curl -sS -H 'X-Sample-Token: local-example' http://127.0.0.1:8080/welcome
```

```json
{"message":"Welcome to BlackOps"}
```

`GET /welcome`はRequest内で`ShowWelcome::handle()`を実行し、Typed OutcomeをHTTP 200へ変換します。

## 4. Deferred Operationを受け付ける

```bash
curl -sS -X POST -H 'Content-Type: application/json' \
  -d '{"reportName":"weekly","apiToken":"local-example"}' \
  http://127.0.0.1:8080/reports
```

```json
{"status":"accepted","operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","acceptedAt":"2026-07-14T01:23:45.678901Z"}
```

HTTP 202はHandler完了ではなく、ValueとContextをPostgreSQLへDurableに保存した合図です。`operationId`と`acceptedAt`は実行ごとに変わります。

空の`reportName`は宣言的Validationで受付前にHTTP 422となります。Inline／DeferredのどちらもValidation Failureを202にせず、Handlerを実行しません。

```bash
curl -sS -X POST -H 'Content-Type: application/json' \
  -d '{"reportName":"","apiToken":"local-example"}' \
  http://127.0.0.1:8080/reports
```

```json
{"status":"rejected","operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687698","category":"validation","code":"validation.failed","violations":[{"field":"reportName","rule":"not_blank","code":"validation.not_blank"}]}
```

## 5. Workerで完了させる

Sample Reportは一回目のAttemptでRetryを要求し、二回目で成功します。

```bash
docker compose run --rm app php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
sleep 2
docker compose run --rm app php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
```

```text
Worker stopped. Processed claims: 0
Worker stopped. Processed claims: 1
```

`var/log/journal.jsonl`には受理、Attempt、Retry、完了が追記されます。`apiToken`はRaw Valueではなく`[masked]`として観測されます。

```bash
tail -n 6 var/log/journal.jsonl
```

Outcomeの取得にはPublic `OutcomeReader`をApplicationのHTTP／CLI入口から利用します。現行FrameworkはOutcome参照用の既成HTTP endpointを提供しません。詳しい取得例は[チュートリアル](first-operation.md#outcomeを読む)を参照してください。

## 6. 終了する

```bash
docker compose down
```

次は[チュートリアル: Operationを作る](first-operation.md)で、Generatorが作った3 FileへRoute、Value Validation、Deferred Strategyを追加します。
