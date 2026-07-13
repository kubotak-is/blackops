# MVP Sample

`examples/quickstart` は、BlackOps MVPのInline／Deferred Vertical SliceをFeature-firstの独立Composer Applicationとして示す。同じSource Treeを公開済み `blackops/skeleton` Packageの配布元として使用する。

Quickstart所有のDocker ComposeはPostgreSQL 18とFrankenPHP HTTPだけをDefault起動する。依存Install、Build、Migration、Worker、RetentionはREADMEの明示Commandで実行する。Inline Observed JournalはSensitive Projection後に `var/log/journal.jsonl` へ追記される。

## Sample Operations

### Inline Welcome

```text
GET /welcome
X-Sample-Token: local-example-token

200 OK
{"message":"Welcome to BlackOps"}
```

Requestは `ShowWelcome` と `WelcomeValue` へbindされ、Self-handledな `ShowWelcome::handle(WelcomeValue)` がtyped `WelcomeShown`を返す。同じOperation IDへ次のCanonical Journalが永続化される。

```text
operation.received
attempt.started
attempt.succeeded
operation.completed
```

### Deferred Report

```text
POST /reports
Content-Type: application/json

{"reportName":"weekly","apiToken":"local-report-token"}
```

受付成功はHTTP 202とOperation IDを返す。

```json
{
  "status": "accepted",
  "operationId": "019...",
  "acceptedAt": "2026-07-12T00:00:00.123456Z"
}
```

HTTP ProcessはHandlerを実行しない。別のWorker compositionがPostgreSQLからOperationをclaimする。Sampleの `GenerateReport::handle(GenerateReportValue, ExecutionContext)` はContextからAttemptを取得し、Attempt 1でretryable exceptionを投げ、`attempt.failed` と `attempt.retry_scheduled` を記録する。再起動相当の新しいWorker／DBAL Connection／DI ContainerがAttempt 2をclaimし、typed `ReportGenerated`をOutcomes Tableへ保存する。

OutcomeはPHPの `OutcomeReader`からOperation IDで取得する。MVP SampleはDeferred Outcome取得用HTTP Endpointを追加しない。

## Build Artifacts

Application buildではPublic Console KernelがQuickstartのProviderとConfigからOperation Manifest、HTTP Manifest、DI Containerをcompileする。

```bash
cd examples/quickstart
php bin/blackops blackops:build:compile
```

BuildはTyped Self-handledのValue型、Optional Context型、Return型を検証してManifestへInvocation Modeを保存する。Production Runtimeはこの3 Artifactを同じBuild IDでloadし、Manifestと現在のSignatureの不整合を拒否する。Request時やWorker起動時にOperation Discovery、Container Compile、Database Migrationは実行しない。

Database SchemaはDeployment時に先に適用する。

```bash
php bin/blackops blackops:database:migrate --no-interaction
```

## Reproduce the E2E

Repository RootのDocker Composeだけで完全なE2Eを実行できる。

```bash
docker compose run --rm app vendor/bin/phpunit --filter MvpSample
```

Testは独立したPostgreSQL SchemaをVersioned Migrationで準備し、次を実際に検証する。

- Compile済みManifest／ContainerのProduction load
- Inline HTTP 200と4件のLifecycle Journal
- Deferred HTTP 202とDurable受付Journal
- HTTPとは別ConnectionのWorkerによる初回失敗とRetry Schedule
- 別Connection／別Containerの再起動Workerによる後続成功
- Operation IDによるtyped Outcome取得
- Canonical Journalと安全なObserved Projectionの分離
- Root Composerへ `App\` Autoloadを追加せず、Quickstart Sourceを明示的に読み込む境界

## Sensitive Data Boundary

Canonical JournalはOperationの再現性を保つ正本である。SampleのtokenはCanonical Received Recordには保持される。一方、`#[Sensitive]`を付けた値はObserved Projectionでmaskされ、JSONLへ平文出力されない。

これは暗号化やCanonical Retentionの代替ではない。Canonical StoreへのAccess Control、暗号化、Retentionは別の運用境界で扱う。
