# P20-017 Structured Logging and OpenTelemetry Contract Report

## Summary

Phase 20の残件をCurrent Sourceへ照合し、Userが全8問でRecommendation Aを承認したため、D136をDecidedとした。確定Specification 100とProduction Task P20-018A〜Fへ分割し、P20-017をAcceptedとした。Production Code、Test、Dependencyは変更していない。

Current `ExecutionScopedLogger`はBlackOps EnvelopeをPSR-3 Contextへ入れる一方、Monolog `JsonFormatter`が別のTop-level Recordを生成する。Observed Journalは独立したTop-level Version 1であり、D014の共通EnvelopeはWire上未成立である。またExecutionContext／Deferred ContextはTrace Contextを持たず、OTel Dependency／Provider Composition、Metric Schema、Health／Readiness Surfaceも存在しない。

## Changed Files

- `develop/decisions/136-structured-logging-and-opentelemetry.md`
- `develop/orchestration/tasks/P20-017-structured-logging-and-opentelemetry-contract.md`
- `develop/orchestration/reports/P20-017-structured-logging-and-opentelemetry-contract.md`
- `develop/spec/README.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P20-018A-structured-record-schema.md`
- `develop/orchestration/tasks/P20-018B-telemetry-context-propagation.md`
- `develop/orchestration/tasks/P20-018C-opentelemetry-trace-adapter.md`
- `develop/orchestration/tasks/P20-018D-opentelemetry-metric-adapter.md`
- `develop/orchestration/tasks/P20-018E-operational-health-and-local-collector.md`
- `develop/orchestration/tasks/P20-018F-observability-documentation.md`

## Evidence Inventory

| Area | Current Evidence | Design Impact |
| --- | --- | --- |
| Application Log | `ExecutionScopedLogger`、`MonologJsonlLoggerFactory` | BlackOps fields are nested under Monolog context; intended common top-level schema is not emitted |
| Observed Journal | `JsonlJournalRecordEncoder`、`ObservedJournalRecordProjector` | Independent top-level version 1; Actor masked and Tenant omitted |
| Execution Context | `ExecutionContext`、`ExecutionScopeProvider`、Context Codec | Tenant exists, Telemetry Context does not |
| Runtime Boundaries | Inline、Deferred Acceptance、Worker Attempt、Outbox Relay、Application／Maintenance Scheduler | Stable spans and metrics are not emitted |
| Dependency／Composition | `composer.json`、Application Runtime Composers | No OpenTelemetry API／SDK／Exporter or provider binding |
| Operations | Worker heartbeat、Connection health check、Schedule／Relay result types | Runtime evidence exists but no common Metric／Probe contract |
| Security | D135／Specification 99 | Raw Tenant／Key／Payload／Provider detail forbidden; safe failure codes available |

## Decision Questions

D136は次の8点をUser Decisionへ出した。

1. Structured Log SchemaとVersion 1の正規化
2. SignalごとのSafe Identity／Failure Projection
3. OpenTelemetry APIとApplication-owned SDK／Exporter
4. W3C Trace ContextとProcess越しPropagation
5. Producer／Internal／Consumer Span Lifecycle
6. 低Cardinality Metric Schema
7. Explicit Health／Readiness Surface
8. Best-effort Failure、Sampling、Flush、Shutdown

各QuestionのRecommendationはAであり、User回答もすべてAである。

## Decision Answers

| Question | Answer | Status |
| --- | --- | --- |
| 1. Structured Log Schema and Version | A | Confirmed |
| 2. Safe Identity and Failure Projection | A | Confirmed |
| 3. OpenTelemetry Dependency and Ownership | A | Confirmed |
| 4. Trace Context and Propagation | A | Confirmed |
| 5. Span Lifecycle and Attributes | A | Confirmed |
| 6. Metric Schema and Cardinality | A | Confirmed |
| 7. Health and Readiness Surface | A | Confirmed |
| 8. Failure, Sampling, Flush, and Shutdown | A | Confirmed |

## Recommended Contract

- BlackOps Structured Record `schemaVersion: 1`を共通Top-level Envelopeとして実装へ一致させる
- Application／Framework／Journal／Auditを`kind`で区別し、UTC MicrosecondsとOptional Operation／Telemetry Blockを共有する
- Actor／TenantはType＋`[masked]`、Protection FailureはSafe Code／Purposeだけを出す
- FrameworkはOpenTelemetry APIだけに依存し、ApplicationがSDK／Exporter／Resource／Credentialを所有する
- W3C Trace ContextをProtected ExecutionContextでChild／Deferred／Outbox／Retryへ伝播し、Baggageを扱わない
- Acceptance／Outbox送信、Inline、Worker AttemptをProcess境界どおり別Spanにする
- `blackops.*` Metricは有限LabelとUCUM Unitだけを許可する
- Liveness／ReadinessはPublic Query＋Safe Resultを提供し、HTTP／CLI RouteはApplicationが明示接続する
- Telemetry FailureはPrimary Operationを変えず、Application SDKがSampling／Batch／Retry／Exporter Shutdownを所有する
- Local Docker CollectorはConsumer Verification Infrastructureとし、Framework本体はExporter／Collectorを所有しない

## Local Docker Verification

Official OpenTelemetry CollectorはOTLP gRPC `4317`／HTTP `4318` Receiverと`debug` Exporterを持つ最小Docker ConfigでTrace／MetricをLocal確認できる。現在のLocal DockerにはCollector Imageが未キャッシュで、既存ContainerはOTLP標準Portを公開していない。

P20-018EはTask開始時に公式Releaseを再確認し、`latest`ではない固定Imageを使う。現時点の公式最新Releaseは`0.157.0`である。ConsumerだけがSDK／OTLP Exporter／HTTP Clientを持ち、Framework ArchiveのProduction DependencyはAPI-onlyを維持する。Collector LogでSpan Matrix、Trace Continuity、Metric Name／Unit／Cardinality、Sensitive Sentinel不在、Collector停止時のFailure Isolationを機械検証する。

## Security／Cardinality／Runtime Boundaries

- Raw Tenant／Actor／Key／Credential／Payload／Outcome／Ciphertext／Exception DetailをLog／Trace／Metricへ出さない。
- Operation ID／Trace IDはLog／Trace相関へ許可するが、Metric Labelへ出さない。
- Tenant Type、Schedule Occurrence、自由文ReasonもMetric Labelへ出さない。
- Invalid Remote ContextはRaw Carrierを記録せず、新しいTraceとして扱う。
- Span／Scopeは`finally`で終了し、Worker／Schedulerの次IterationへContextを残さない。
- OTel Collector／Exporter障害はReadinessを落とさず、Primary Operationを失敗させない。
- FrameworkはExporter Credential、Endpoint Header、SDK ResourceをConfig Snapshot／Compiled Artifactへ保存しない。

## Commands and Results

```text
PASS Current Logger／JSONL／Execution Context／Runtime／Dependency audit
PASS D136 Question／Recommendation／User Answer A×8
PASS Official OpenTelemetry PHP／Propagation／Semantic Convention review
PASS Official Collector Docker／v0.157.0 release review
PASS Specification 100／P20-018A〜F Structure／Reference audit
PASS Required rg audit
PASS D136 Answer block count／Status audit
PASS git diff --check
NOT RUN Production tests: Production Code／Test／Dependencyを変更していないDecision Taskのため
```

## Acceptance Criteria

- [x] Current Wire Shape／Trace／Metric／Probe Gapを監査した
- [x] D014／D015／D099／D135の継承境界を示した
- [x] Structured Schema、Propagation、Span、Metric、Health候補をD136へ整理した
- [x] Official OpenTelemetry GuidanceへRecommendationを照合した
- [x] User回答をD136へ反映し、Decidedにした
- [x] 確定Specification 100とP20-018A〜Fを作成した
- [x] Production Code／Test／Dependencyを変更していない
- [x] STATE／TODO／Decision／Specification Index同期と`git diff --check`

## Remaining Issues

- P20-018A〜FのWorker実装とOrchestrator Acceptance
- P20-018EのLocal Docker Collector Image Pull／Consumer Evidence
- P20-018FのRead-only Documentation Review

## Suggested Next Action

Specification 100を正本に、最初のProduction Task P20-018AをGPT-5.6 Luna High workerへ依頼する。
