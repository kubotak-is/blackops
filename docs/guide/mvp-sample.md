# Quickstart

Install直後のSkeletonにはInline `GET /welcome`とDeferred `POST /reports`が含まれます。同じFeature-first SourceからHTTP受付、Lifecycle Journal、Worker Retry、Typed Outcomeを確認できます。

## 準備

[インストール](installation.md)後のProject Rootで実行します。

```bash
docker compose build app http
docker compose run --rm app composer install
docker compose run --rm app php bin/blackops blackops:build:compile
docker compose run --rm app php bin/blackops blackops:database:migrate
docker compose up -d
```

## Inline Welcome

```bash
curl -H 'X-Sample-Token: local-example-token' \
  http://127.0.0.1:8080/welcome
```

```json
{"message":"Welcome to BlackOps"}
```

Requestは`WelcomeValue`へBindされ、`ShowWelcome::handle(WelcomeValue): WelcomeShown`が同期実行されます。同じOperation IDに受付、Attempt開始、成功、完了のLifecycle Journalが記録されます。

HeaderのSample TokenはCanonical Journalで再現用Valueとして扱われます。Observed JSONLでは`#[Sensitive]`に従いMaskされ、平文のまま`var/log/journal.jsonl`へ出力されません。

## Deferred Report

```bash
curl -X POST -H 'Content-Type: application/json' \
  -d '{"reportName":"weekly","apiToken":"local-example-token"}' \
  http://127.0.0.1:8080/reports
```

受付成功はHTTP 202とOperation IDを返します。

```json
{
  "status": "accepted",
  "operationId": "019...",
  "acceptedAt": "2026-07-12T00:00:00.123456Z"
}
```

HTTP ProcessはHandlerを実行しません。Workerを明示的に起動します。

```bash
docker compose run --rm app php bin/blackops blackops:worker:run --iterations=1
docker compose run --rm app php bin/blackops blackops:worker:run --iterations=1
```

Sampleは最初のAttemptでRetryable Exceptionをthrowし、次のAttemptで`ReportGenerated`を保存します。Operation IDからOutcomeを読む方法は[Outcome Retrieval](outcome-retrieval.md)を参照してください。

常駐WorkerはCompose Profileから起動できます。

```bash
docker compose --profile worker up worker
```

## Starter Featureを外す

Welcomeは`app/Feature/Welcome/`、Reportは`app/Feature/Report/`をDirectoryごと削除できます。`config/operations.php`のDiscovery RootがOperationを検出するため、Provider一覧やBootstrapの編集は不要です。

次にApplication固有のUse Caseを書く場合は[Operation Authoring](operations.md)へ進んでください。
