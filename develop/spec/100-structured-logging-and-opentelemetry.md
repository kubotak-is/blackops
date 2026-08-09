# Structured Logging and OpenTelemetry Observability

## Purpose

Phase 20のStructured Log Schema、OpenTelemetry Trace／Metric Adapter、Liveness／Readinessを一つの安全なObservability Contractとして提供する。

本仕様は[D136 Structured Logging and OpenTelemetry](../decisions/136-structured-logging-and-opentelemetry.md)を正本とし、既存のPSR-3 Logger、Observed Journal、ExecutionContext、HTTP、Inline／Deferred Runtime、Worker、Outbox Relay、Application Scheduler、Maintenance Schedulerへ適用する。

## Scope

- BlackOps Structured Record Schema Version 1
- Application／Framework／Journal／Audit JSONL
- Safe Operation／Attempt／Actor／Tenant／Failure Projection
- OpenTelemetry API Provider Composition
- W3C Trace ContextとProcess越し伝播
- Framework-owned Span LifecycleとSafe Attributes
- 低CardinalityのOperation／Worker／Outbox／Scheduler／Failure Metrics
- Liveness／Readiness Queryと明示Composition用Adapter
- Best-effort Telemetry Failure／Flush Lifecycle
- Local Docker Collector Consumer Evidence

次は対象外とする。

- Framework-owned OpenTelemetry SDK、OTLP Exporter、Collector、Vendor Backend
- CloudWatch専用Adapter、Remote Log Sink、Dashboard、Alert、SLO
- Exporter Endpoint／Header／CredentialのBlackOps Config管理
- Baggage、任意Application Contextの完全な機密情報検出
- Public Probe Routeの自動登録、Built-in Probe HTTP Server
- Exactly-once Telemetry Delivery

## Dependency and Ownership

Framework PackageのProduction Dependencyは`open-telemetry/api`だけとする。Applicationは次を明示Compositionする。

- `TracerProviderInterface`
- `MeterProviderInterface`
- OpenTelemetry SDK
- Span Processor／Metric Reader
- Sampling／Batch／Retry／Timeout
- OTLPまたはVendor Exporter
- Resource、Service Name、Deployment属性
- Endpoint、Header、Credential、Collector

Provider未登録時はOpenTelemetry APIのNo-op実装を使い、Operation、Journal、Outcome、JSONL、HTTP Responseを変えない。Framework Config、Compiled Artifact、ManifestへSDK Instance、Resolved Resource、Endpoint Header、Credentialを保存しない。

OpenTelemetry SDK／OTLP Exporter／HTTP ClientはConsumer／Integration TestのDevelopment Dependencyにできるが、Framework ArchiveのProduction Dependencyへ追加しない。

## Structured Record Schema Version 1

### Common Envelope

すべてのStructured Recordは一行のUTF-8 JSON Objectで、末尾に一つのLFを持つ。

共通Top-level Fieldは次である。

| Field | Type | Contract |
| --- | --- | --- |
| `schemaVersion` | integer | 常に`1` |
| `kind` | string | `application`、`framework`、`journal`、`audit` |
| `occurredAt` | string | UTC、Microseconds付きRFC 3339、末尾`Z` |
| `operation` | object | Operation Scopeがある場合だけ |
| `attempt` | object | Attempt Scopeがある場合。Journalは既存Contractどおり`null`を許可する |
| `telemetry` | object | ValidなActive Span Correlationがある場合だけ |

Application／Framework Recordは次も持つ。

| Field | Type | Contract |
| --- | --- | --- |
| `level` | string | PSR-3 Levelのlowercase |
| `message` | string | CallerまたはFrameworkが指定したMessage |
| `channel` | string | 構成済みSafe Channel |
| `context` | object | `SensitiveProjectionFilter`適用後のUser／Framework Context |

Journal Recordは既存Version 1の`recordId`、`event`、`sequence`、`data`を維持する。Audit Recordは安定した`event`とSafe `data`を持つ。Monologの`datetime`、`level_name`、integer `level`、`extra`、Nested `context.schemaVersion`は公開Wire Fieldにしない。

### Operation Projection

`operation`は次を持つ。

| Field | Type | Contract |
| --- | --- | --- |
| `id` | UUIDv7 string | Operation ID |
| `type` | string | Compiled Operation Type ID |
| `schemaVersion` | integer | Metadataが利用可能なRecordだけに追加できる |
| `strategy` | string | Stable Strategy ID |
| `correlationId` | UUIDv7 string | Operation Correlation ID |
| `causationId` | UUIDv7 string or null | Causationがない場合は`null` |
| `actors` | object or null | origin／authorization／executionのSafe Projection |
| `tenant` | object or null | TenantのSafe Projection |
| `schedule` | object or null | `{name, scheduledAt}`。Schedule Scopeだけ |

Actorは`{"id":"[masked]","type":"..."}`または`null`、Tenantは`{"id":"[masked]","type":"..."}`または`null`とする。Raw Actor／Tenant IDとHashを出さない。

Application／Frameworkの`attempt`はAttempt Scopeがある場合だけ出力し、`id`、`number`、`startedAt`を持つ。Attempt開始前は省略する。既存`operation.attemptId`は廃止し、Top-level `attempt`へ正規化する。現在のMonolog Nested ShapeはExperimental `main`の不整合であり、Dual-write／Legacy Formatterを提供しない。

### Telemetry Projection

`telemetry`は次だけを持つ。

```json
{
  "traceId": "0123456789abcdef0123456789abcdef",
  "spanId": "0123456789abcdef",
  "sampled": true
}
```

- `traceId`は32文字、`spanId`は16文字のlowercase Hexとする。
- Invalid／Zero ContextではBlock自体を省略する。
- `traceparent`、`tracestate`、Baggage、Exporter／Vendor属性をJSONLへ出さない。
- Operation ID／Correlation IDをTrace／Span IDへ変換しない。

### Failure Projection

Framework-owned Failureは安定したClassification／Code、Optional Operation Type、Storage Purpose等のSafe Enumだけを出す。Throwable Message、Stack、SQL、DSN、Payload、Outcome、Ciphertext、Nonce、Tag、Key ID、Provider Detailを自動出力しない。

Rotation AuditのSafe Fingerprint／Scope Hashは既存Audit Storeへ留め、Default JSONL／Metricへ複製しない。Application Message自体へSecretを入れない責任はApplicationに残る。

### Formatter and Delivery

MonologはStream、Channel、Minimum Level、Backend Writeを担当し、BlackOps Canonical Formatterが公開JSON Shapeを担当する。Application／Framework Recordは必ず`ExecutionScopedLogger`と共通Safe Projectionを通す。

Observed JournalはCanonical Ciphertextを公開せず、Sensitive ProjectionとTelemetry Correlation後に同じVersion 1 EnvelopeへEncodeする。Observer ReplayはOriginal RecordのTelemetry Correlationを維持し、Replay処理のSpan IDへ上書きしない。

JSONL Backend FailureはBest-effortでPrimary Operationを変更しない。Required／Durable Journal Policyは既存Journal Contractを維持し、Trace Samplingと独立する。

## Telemetry Context

### Public Shape

Public immutable `TelemetryContext`をOpenTelemetry API ContextのSerializable Boundaryとして提供する。

```text
TelemetryContext
  traceparent(): string
  tracestate(): ?string
```

- `traceparent`はW3C Trace ContextとしてValidでなければならない。
- Serializable boundaryは現在W3C version `00`を受け付ける。`ff`および将来VersionはRaw carrierを保存せず、未対応としてParentなしに扱う。
- `tracestate`はW3C GrammarとLength Limitを満たす場合だけ保持する。
- BaggageはProperty、Constructor、Codec、Logへ追加しない。
- ContextはCredential、Tenant、Actor、OperationValueを持たない。

`ExecutionContext::telemetry(): ?TelemetryContext`を末尾Optional Extensionとして追加する。Public Root Dispatchは末尾Optional Telemetry Contextを受けられる。Child DispatchはOverrideを追加せず、現在のActive Spanから新しいProducer Contextを作る。

Canonical／Observed JournalとStructured JSONLの`telemetry`は、Valid Contextから投影した`traceId`、`spanId`、`sampled`だけをTop-level correlationとして保持する。Raw `traceparent`／`tracestate`とBaggageはCanonical／Observed Journalの相関ShapeとStructured JSONLへ出さない。一方、Deferred／OutboxのExecutionContextでは既存BOPD暗号化境界内に保持する。

### Entry and Propagation

| Boundary | Contract |
| --- | --- |
| HTTP | W3C `traceparent`／`tracestate`を抽出し、Valid Remote Parentとして利用する |
| Console／Scheduled Root | Incoming Carrierがないため新しいTraceを開始する |
| Direct Root Dispatch | Explicit Optional Telemetry ContextがあればParentにする |
| Inline Child | Current SpanをParentにして新しいInternal／Producer Spanを作る |
| Deferred Acceptance | Producer Span ContextをEncrypted Deferred Contextへ保存する |
| Transactional Outbox | Current SpanからProducer ContextをEncrypted Outbox Contextへ保存する |
| Worker Attempt／Retry | Persisted ContextをRemote Parentにし、Attemptごとに新しいConsumer Spanを作る |
| Observer Replay | Original Record Correlationを保持し、Replay Runtime Spanとは分離する |
| Future Operation Replay | 新しいRoot Traceを開始し、元ContextへLinkする |

Invalid Remote ContextはRaw HeaderをLog／Metricへ出さずRemote Parentなしとして扱う。Current Sourceに存在しないTerminal Operation Replay APIは本Phaseで追加しない。

Deferred／Outbox ContextはBOPD Encrypted Field内へ保存し、Clear Columnへ`traceparent`／`tracestate`を追加しない。Tenant／Operation AAD Contractを維持する。

## Trace Adapter

### Instrumentation Scope

Instrumentation Scope Nameは`blackops.framework`、VersionはFramework Package Version
`1.1.0`を使う。ResourceとService NameはApplication Providerの値を尊重する。

### Span Matrix

| Span Name | Kind | Boundary |
| --- | --- | --- |
| `blackops.operation.accept` | Producer | Deferred Acceptance、Transactional Child／Outbox送信 |
| `blackops.operation.execute` | Internal | Inline Operation execution |
| `blackops.operation.execute` | Consumer | Deferred Worker Attempt |
| `blackops.outbox.relay` | Internal | Bounded Outbox Relay run |
| `blackops.operation.schedule.evaluate` | Internal | Application Schedule evaluation／acceptance run |
| `blackops.maintenance.run` | Internal | Maintenance Scheduler run |
| `blackops.observer.replay` | Internal | Bounded Observer Replay run |

HTTP Server SpanとDB Client SpanはApplicationのAuto／Manual Instrumentationへ委ね、Frameworkが重複生成しない。Point-in-time Journal EventごとにSpanを作らず、該当Operation／Runtime SpanへSafe Eventを追加できる。

Deferred待機中、Retry待機中、Outbox待機中にSpanを開いたままにしない。各SpanはProcess境界の`finally`で終了し、Active Scopeをdetachする。Worker Retryは同じPersisted Parentを持つ別Consumer Spanとし、Attempt ID／Numberで区別する。

### Trace Attributes

許可するFramework Attributeは次に限定する。

- `blackops.operation.id`
- `blackops.operation.type`
- `blackops.operation.strategy`
- `blackops.attempt.id`
- `blackops.attempt.number`
- `blackops.correlation.id`
- `blackops.causation.id`
- `blackops.schedule.name`
- `blackops.runtime.kind`
- `blackops.result`
- `blackops.actor.origin.type`／`id=[masked]`
- `blackops.actor.authorization.type`／`id=[masked]`
- `blackops.actor.execution.type`／`id=[masked]`
- `blackops.tenant.type`／`id=[masked]`
- `error.type`のSafe Class／Code
- `blackops.storage.purpose`のSafe Enum

`blackops.result`は`completed`、`rejected`、`failed`、`retry_scheduled`、
`dead_lettered`、`interrupted`の有限値だけを許可する。Runtime Failure Codeも
Frameworkが定義した有限のASCII Codeだけを使い、自由文やThrowable Messageを記録しない。

`blackops.runtime.kind`は`operation`、`worker`、`scheduler`、`maintenance`、
`outbox_relay`、`observer_replay`の有限値だけを許可する。

OperationValue、Outcome、Reason Message、Throwable Message／Stack、Raw Actor／Tenant／Key、SQL／Provider DetailをAttribute／Eventへ追加しない。Frameworkは`recordException()`でRaw Throwable Detailを自動記録せず、Span StatusとSafe `error.type`だけを設定する。

## Metric Adapter

Instrumentation Scope NameはTraceと同じ`blackops.framework`を使う。初期のStable Instrumentは次である。

| Instrument | Type | Unit | Allowed Attributes |
| --- | --- | --- | --- |
| `blackops.operation.duration` | Histogram | `s` | operation type、strategy、runtime kind、result |
| `blackops.operation.active` | UpDownCounter | `{operation}` | operation type、strategy、runtime kind |
| `blackops.worker.claims` | Counter | `{claim}` | result |
| `blackops.worker.heartbeat.failures` | Counter | `{failure}` | failure code |
| `blackops.outbox.relay.duration` | Histogram | `s` | result |
| `blackops.outbox.relay.records` | Counter | `{record}` | result |
| `blackops.scheduler.run.duration` | Histogram | `s` | scheduler kind、result |
| `blackops.scheduler.occurrences` | Counter | `{occurrence}` | scheduler kind、result |
| `blackops.observer.failures` | Counter | `{failure}` | observer kind、failure code |
| `blackops.storage.protection.failures` | Counter | `{failure}` | storage purpose、failure code |

`result`、`runtime kind`、`scheduler kind`、`failure code`はSpecificationとTestで列挙する有限Enumでなければならない。Operation TypeはCompiled Manifestに存在する有限集合だけを使う。

次をMetric Attributeへ入れない。

- Operation／Attempt／Correlation／Causation ID
- Trace／Span ID
- Tenant／Actor Type／ID
- Key ID
- Schedule Name／Occurrence ID
- Record／Outbox／Dead Letter ID
- URL Raw Path、User Input、自由文Reason
- Throwable Classを除くMessage／Stack

PostgreSQLのBounded Operational Snapshotを追加する場合、次をObservable Gaugeとして提供できる。

- `blackops.worker.queue.depth` unit `{operation}`
- `blackops.worker.queue.oldest.age` unit `s`
- `blackops.outbox.queue.depth` unit `{record}`
- `blackops.outbox.queue.oldest.age` unit `s`

Snapshot QueryはTenant／Row IdentityをLabelへ出さず、Readiness Sourceと同じConnection／Migration Compatibility境界を使う。Telemetry API FailureをTelemetry Failure Metricで再帰記録しない。

## Liveness and Readiness

### Public Query

Public `OperationalHealthQuery`は`Liveness`／`Readiness`を指定してSafe `OperationalHealthReport`を返す。

```text
OperationalHealthReport
  schemaVersion: 1
  kind: liveness|readiness
  status: pass|fail
  checkedAt: UTC microseconds
  checks: list<OperationalHealthCheck>

OperationalHealthCheck
  code: stable ASCII code
  status: pass|fail
```

Message、Throwable、Duration、DSN、Host、Port、Schema、Table、SQL、Provider Class、Key ID、CredentialをResultへ含めない。

LivenessはProcessがQueryへ応答できることだけを表し、Database、Storage Key Provider、OTel Exporterを確認しない。Readinessは構成されたRuntimeに応じて次のBounded Checkを行う。

- Compiled ArtifactとApplication Build IDの整合
- Runtime ConfigurationのValidation済み状態
- Framework Database Connection
- Migration Compatibility／Legacy Protected Schema Guard
- Required Storage Key Providerの構成可能性。Key Material／Key IDは結果へ出さない
- Worker／Outbox／Scheduleに必要なRuntime Service Composition

Collector／Exporter接続失敗、Sampling、Dashboard、Remote BackendはReadiness Checkに含めない。

### Explicit Adapters

- PSR-15 HandlerはApplicationが明示Routeへ登録する。Frameworkは`/health`、`/ready`等を自動公開しない。
- PassはHTTP 200、Failは503、`Content-Type: application/json`、`Cache-Control: no-store`とする。
- CLI Formatter／Command Adapterは同じReportをHuman／one-line JSONへ変換できるが、Application Consoleへ暗黙登録しない。
- Worker／SchedulerへProbe HTTP Serverを内蔵しない。SupervisorはExplicit CLI／Application Adapterを使う。

## Failure, Flush, and Long-running Runtime

- Span／Metric API、Lifecycle Hook、Exporter／Flush FailureはPrimary Operationを変更しない。
- Telemetry内部FailureはRaw Messageなしの有限Codeへ縮約し、可能なら既存Safe LoggerへBest-effort記録する。
- FrameworkはProviderを強制Shutdownしない。Application SDK BootstrapがShutdownを所有する。
- FrameworkはOperation終了、Worker／Relay／Scheduler Loop終了、Process ShutdownでLifecycle HookへFlush機会を通知する。
- HookはBatch FlushまたはNo-opを選べる。Frameworkは毎Operationの同期Network Exportを要求しない。
- Scope、Span、Active Metric Stateは`finally`で閉じ、FrankenPHP／Workerの次Request／Iterationへ漏らさない。
- Collector停止中もOperation、Terminal Journal、Outcome、HTTP Response、Readinessは既存結果を維持する。

## Local Docker Collector Consumer

Local E2EはOfficial Collector Docker ImageをFrameworkのProduction Compose Defaultへ追加せず、Consumer専用Compose Profile／Fixtureで起動する。

初期Contractは次である。

```yaml
receivers:
  otlp:
    protocols:
      grpc:
        endpoint: 0.0.0.0:4317
      http:
        endpoint: 0.0.0.0:4318

exporters:
  debug:
    verbosity: detailed

service:
  pipelines:
    traces:
      receivers: [otlp]
      exporters: [debug]
    metrics:
      receivers: [otlp]
      exporters: [debug]
```

Collector Imageは`latest`ではなく、Task開始時に公式ReleaseとImageを確認した固定Versionを使う。P20-018E開始時に確認したImageは`otel/opentelemetry-collector:0.158.0@sha256:5b97e6e3550ec6e48a71dba6f6304d349a293af8df4ee1f51da67be94fce2ecd`である。PHP ConsumerはOTLP HTTP `http://otel-collector:4318`を使い、gRPC Extensionを必須にしない。

Consumerは少なくとも次を機械検証する。

1. Collector Config起動とOTLP HTTP到達
2. HTTP InlineのServer Parent→Internal Operation Span
3. Deferred Acceptance Producer→Worker ConsumerとRetry別Span
4. Transactional Outbox Producer→Relay Runtime Span
5. Application Schedule／Maintenance Runtime Span
6. Structured JSONLとCollector Trace／Span ID相関
7. Metric名、Type、Unit、有限属性
8. Metric Labelへ個別Identity／自由文がないこと
9. Raw Tenant／Actor／Key／Payload／Outcome／Provider／Exception SentinelがCollector Logにないこと
10. Collector停止中もPrimary JourneyとReadinessが変わらないこと
11. Container、Network、Volume、一時Consumer／LogのCleanup

Collector `debug` Logは一時Artifactとし、Secretを含まない否定検証後に削除する。Remote Backend、Credential、Production Deployは行わない。

## Delivery Plan

1. P20-018A: Structured Record Schema、Canonical Formatter、Application／Framework／Journal／Audit Projection
2. P20-018B: Telemetry Context、W3C Propagation、ExecutionContext／Deferred／Outbox／Retry Persistence
3. P20-018C: OpenTelemetry Trace Adapter、Span Lifecycle、Application Provider Composition
4. P20-018D: Metric Adapter、Operation／Worker／Outbox／Scheduler／Failure Instrumentation
5. P20-018E: Liveness／Readiness、Explicit Adapter、Local Docker Collector Consumer Evidence
6. P20-018F: Public／Internal Guide、Security／Deployment／Troubleshooting、Documentation Review

各Production TaskはGPT-5.6 Luna High workerへ依頼し、WorkerはReview前にCommitしない。Documentation ReviewはRead-only Documentation ReviewerがEvidence付きFindingを返し、OrchestratorがAcceptanceする。

## Acceptance Criteria

- JSONL Application／Framework／Journal／Auditが共通Top-level Version 1へ一致する
- Monolog Nested Context ShapeをDual-writeせずCanonical Formatterへ置き換える
- Raw Actor／Tenant／Key／Payload／Outcome／Provider／Exception Detailが全Signalへ露出しない
- Trace ID／Span IDとOperation／Correlation IDを変換せず関連付ける
- W3C ContextがHTTP、Child、Deferred、Outbox、Worker Retryへ伝播する
- Process／AttemptごとにSpanが開始／終了し、長期RuntimeへScopeが漏れない
- Metric名、Type、Unit、属性Enumが固定され、高Cardinality IdentityをLabelへ出さない
- LivenessとReadinessが分離し、Probe Routeを自動公開しない
- Telemetry Export FailureがPrimary Operation／Readinessを変更しない
- Framework ArchiveのProduction DependencyはOpenTelemetry APIだけである
- Local Docker CollectorでTrace／Metric／Redaction／Failure Isolationを実証する
- Existing Full PHP／Consumer／Website Gateを維持する
- Commit／Push／External DeployはOrchestratorの明示工程まで行わない

## Traceability

- [D136 Structured Logging and OpenTelemetry](../decisions/136-structured-logging-and-opentelemetry.md)
- [Logging and Traceability](10-logging-and-traceability.md)
- [Runtime and Dependency Injection](09-runtime-and-di.md)
- [Operation Diagnostics](65-operation-diagnostics.md)
- [Journal Documentation](94-journal-documentation.md)
- [Tenant Isolation and Protected Operation Data](99-tenant-isolation-and-protected-operation-data.md)
- [OpenTelemetry PHP](https://opentelemetry.io/docs/languages/php/)
- [OpenTelemetry PHP Context Propagation](https://opentelemetry.io/docs/languages/php/propagation/)
- [OpenTelemetry Semantic Conventions](https://opentelemetry.io/docs/specs/semconv/)
- [Install the Collector with Docker](https://opentelemetry.io/docs/collector/install/docker/)
