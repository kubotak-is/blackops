# MVP Scope

## Goal

MVPはHttp Inline OperationとDeferred Operationを両方実行し、同じOperation ModelとJournalで追跡できることを証明する。

```text
HTTP Request
  -> Operation
  -> Journal
  ├─ Inline -> Handler -> Outcome -> HTTP Response
  └─ Deferred -> Local Transport -> HTTP 202
                         -> Worker -> Handler -> Outcome
```

## Sample Operations

### Inline

```text
GET /welcome
  -> ShowWelcome
  -> WelcomeShown
  -> HTTP 200
```

### Deferred

```text
POST /reports
  -> GenerateReport
  -> HTTP 202 + Operation ID
  -> Worker
  -> ReportGenerated
```

## Included

- PHP 8.5
- Http Inline／Deferred Operation
- Route、Binding、Handler、Outcome、Responder
- Processを越えて利用できるLocal Execution Transport
- Worker
- 最低一回のRetry
- PSR-3 FW Logger
- Execution Scope
- JSON Lines構造化Log
- Lifecycle Journal自動記録
- 最小Sensitive Filter
- 開発用Dynamic Discovery
- Operation Manifest Compile
- Symfony DI Container Compile
- PostgreSQL Canonical Journal Store
- Retention Policy、Dry Run、Purge CLI
- Framework Maintenance Scheduler Worker
- Retention HoldとPurge Audit
- Unit Test／Integration Test

## CLI

```text
operation:list
operation:compile
container:compile
worker:run
retention:plan
retention:purge
scheduler:run
```

## Excluded

- Transactional Outbox実装
- SQS／Kafka
- OpenTelemetry／CloudWatch
- 認証／認可
- Sensitive Payload暗号化
- Coalesce／Scheduled Operation Strategy
- Dead Letter管理UI
- Message Adapter
- ORM
- 雛形生成CLI

各Portと拡張点は後続実装で壊さないようにするが、外部InfrastructureはMVP後に追加する。

## Definition of Done

- SampleのInline／Deferred Operationが動く
- 全Lifecycle JournalをOperation IDで追跡できる
- HTTP 200／202とOperation IDが返る
- Worker再起動後も未処理Deferred Operationを実行できる
- Handler例外をAttemptFailedとして記録できる
- 最低一回のRetryを実行できる
- Sensitive Filterの最小実装がある
- Manifest／Container Compileが成功する
- Unit TestとIntegration Testが通る
