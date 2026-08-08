# P20-017: Structured Logging and OpenTelemetry Contract

Status: Accepted

## Goal

Phase 20のStructured Log Schema、OpenTelemetry Trace／Metric Adapter、Health／Readiness境界をProduction Code変更前に確定する。

Existing PSR-3／Monolog JSONLとObserved JournalのSchema差、OTel Context伝播、SDK／Exporter責任、Span Lifecycle、Metric Cardinality、Probe公開方法、Failure／Flush境界を一つのDecisionへ整理し、実装可能なSliceへ分割する。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/014-logging-and-traceability.md`
- `develop/decisions/015-log-delivery-and-retention.md`
- `develop/decisions/099-production-logging-configuration.md`
- `develop/decisions/135-tenant-isolation-and-protected-operation-data.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/94-journal-documentation.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- Current Logger／JSONL Encoder／Execution Context／Worker／Outbox／Scheduler／Storage Protection Source and Tests
- OpenTelemetry PHP official Documentation and Semantic Conventions

## In Scope

- Current Structured Log／Observed JournalのRead-only Design Audit
- Structured Record VersionとTop-level Envelope
- Operation／Attempt／Correlation／Actor／Tenant／FailureのSafe Projection
- OpenTelemetry API／SDK／Exporter責任分離
- W3C Trace ContextとHTTP／Child／Deferred／Outbox／Retry／Replay Propagation
- Producer／Internal／Consumer Span LifecycleとSafe Attributes
- Operation／Worker／Outbox／Scheduler／Failure Metric Schema
- Metric Label CardinalityとUnit
- Liveness／Readiness Port、Safe Result、Explicit HTTP／CLI Adapter
- Sampling、Batch、Flush、Shutdown、Exporter Failure境界
- D136 Question、Recommendation、User回答
- Decision後のSpecification／Production Task分割
- TODO／STATE／Report／Decision Index同期

## Out of Scope

- Production Code、Test、Migration、Dependency、Public API実装
- OpenTelemetry SDK／OTLP Exporter／Collectorの導入
- Vendor固有Backend、Dashboard、Alert、SLO
- CloudWatch Adapter、Remote Log Sink
- Public Probe Routeの自動登録
- Documentation Website本文変更
- Commit、Push、PR、External Deploy

## Files Allowed to Change

- `develop/decisions/136-structured-logging-and-opentelemetry.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-017-structured-logging-and-opentelemetry-contract.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/orchestration/tasks/P20-018A-structured-record-schema.md`
- `develop/orchestration/tasks/P20-018B-telemetry-context-propagation.md`
- `develop/orchestration/tasks/P20-018C-opentelemetry-trace-adapter.md`
- `develop/orchestration/tasks/P20-018D-opentelemetry-metric-adapter.md`
- `develop/orchestration/tasks/P20-018E-operational-health-and-local-collector.md`
- `develop/orchestration/tasks/P20-018F-observability-documentation.md`

User回答後に確定SpecificationとProduction Task Packetを追加する。Production Codeまたは上記以外の変更が必要なら実装を広げず、Reportへ記録する。

## Audit Questions

1. Existing `schemaVersion: 1`の意図とMonolog Nested Context不整合をどのSchemaへ収束させるか。
2. Operation／Actor／Tenant／Protection FailureをSignalごとにどこまで安全に投影するか。
3. OpenTelemetry API、SDK、Exporter、Resource、CredentialをFramework／Applicationでどう分けるか。
4. W3C Trace ContextをHTTP、Child、Deferred、Outbox、Retry、Replayへどう伝播するか。
5. Acceptance、Inline、Worker Attempt、Outbox、ScheduleをどのSpan境界にするか。
6. Operation／Worker／Outbox／Scheduler Metricを低Cardinalityでどう固定するか。
7. Headless FrameworkでLiveness／Readinessをどう公開するか。
8. Sampling、Batch、Flush、Shutdown、Exporter FailureをPrimary Lifecycleからどう分離するか。

## Acceptance Criteria

- [x] Application LogとObserved JournalのCurrent Wire Shape差をSourceで確認する
- [x] Current ExecutionContext／TransportにTrace Contextがないことを確認する
- [x] Current Dependency／CompositionにOTel API／SDK／Exporterがないことを確認する
- [x] Worker／Outbox／Scheduler／HealthのCurrent Instrumentation Gapを確認する
- [x] D014／D015／D099／D135の継承境界を維持する
- [x] Structured Record、Safe Identity、Trace Propagation候補をD136へ示す
- [x] Span、Metric、Health／Readiness、Failure Lifecycle候補をD136へ示す
- [x] Official OpenTelemetry PHP／Semantic ConventionへRecommendationを照合する
- [x] User回答をD136へ反映し、D136をDecidedにする
- [x] 確定SpecificationとProduction Task Packetへ分割する
- [x] Production Code／Test／Dependencyを変更しない
- [x] STATE／TODO／Decision Index／Reportを同期し、Commitしない

## Required Commands

```bash
rg -n "schemaVersion|ExecutionScopedLogger|JsonlJournalRecordEncoder|OpenTelemetry|traceparent|traceId|spanId|heartbeat|scheduler|readiness|health" src tests composer.json develop/spec develop/decisions
git diff --check
```

Production Code／Testを変更しないため、Existing SuiteはこのDecision Pending時点で再実行しない。User回答後の確定SpecificationでStructured Wire、Propagation、Span、Metric、Probe、Failure、Long-running ProcessのTest Matrixを定義する。

## Completion Report

`develop/orchestration/reports/P20-017-structured-logging-and-opentelemetry-contract.md`へSummary、Evidence Inventory、Decision Questions、Recommended Contract、Security／Cardinality／Runtime Boundaries、Commands and Results、Remaining Issues、Suggested Next Actionを記録する。
