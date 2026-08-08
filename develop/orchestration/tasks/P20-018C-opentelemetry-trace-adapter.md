# P20-018C: OpenTelemetry Trace Adapter

Status: Ready

## Goal

Application-owned OpenTelemetry Tracer ProviderへFramework Lifecycleを接続し、Deferred Acceptance／Outbox Producer、Inline Internal、Worker Consumer、Schedule／Maintenance／Relay Runtime Spanを安全かつ決定的に生成する。

## Source of Truth

- `AGENTS.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/31-inline-dispatcher.md`
- `develop/spec/32-worker-crash-recovery.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/98-scheduled-application-operation.md`
- `develop/decisions/136-structured-logging-and-opentelemetry.md`

## Dependencies

- P20-018B Accepted

## In Scope

- OpenTelemetry Trace Adapter／No-op Boundary
- Application Tracer Provider registration／runtime composition
- Instrumentation Scope `blackops.framework`
- Span MatrixとParent／Link Rules
- Safe Trace Attributes／Lifecycle Events／Status
- HTTP、Inline、Deferred Acceptance、Worker Attempt／Retry
- Transactional Outbox Producer、Relay、Application Schedule、Maintenance、Observer Replay
- Structured JSONL／Observed Journal Active Correlation接続
- `finally` end／detach、nested／failure／long-running isolation
- Unit、Integration、Runtime Consumer Evidence

## Out of Scope

- Metric Instruments
- SDK／OTLP Exporter／Collector Production Composition
- HTTP Server／DB Client Auto-instrumentation
- Raw `recordException()`、Baggage
- Health／Readiness、Guide／Website

## Files Allowed to Change

- `src/Telemetry/**`
- `src/Internal/Telemetry/**`
- `src/Internal/Execution/**`
- `src/Internal/Http/**`
- `src/Http/**` only where operation span boundary requires it
- `src/Internal/Outbox/**`
- `src/Internal/Scheduling/**`
- `src/Internal/Scheduler/**`
- `src/Internal/Replay/**`
- `src/Internal/Journal/**`
- `src/Internal/Projection/**`
- `src/Internal/Logging/**`
- `src/Internal/Application/**`
- `src/Application/ApplicationBuilder.php`
- Corresponding files under `tests/**`
- Trace-focused Consumer fixtures under `tests/Consumer/**`
- `deptrac.yaml`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-018C-opentelemetry-trace-adapter.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- Tracer Provider未登録はNo-opで、Runtime結果／JSONL Shapeを変えない
- SDK／ExporterをProduction Dependency／BlackOps Configへ追加しない
- Span Name／Kind／AttributeはSpecification 100のMatrixへ厳密一致する
- Deferred待機／Retry待機中にSpanを開いたままにしない
- Attemptごとに異なるConsumer Span IDを使う
- HTTP Server／DB Client Spanを重複生成しない
- Raw Tenant／Actor／Key／Payload／Outcome／Throwable Message／Stackを記録しない
- `recordException()`をFramework既定で呼ばない
- Scope detachとSpan endは全Success／Reject／Retry／Failure／Interruptionで`finally`実行する
- Telemetry FailureはPrimary Throwable／Journal／Outcome／HTTP Responseを置換しない
- WorkerはReview前にCommitしない

## Acceptance Criteria

- [ ] No-op ProviderでExisting Runtime結果が完全に維持される
- [ ] Span Name／Kind／Parent Matrixが固定される
- [ ] InlineとDeferred Acceptance／Workerが別Process Spanを持つ
- [ ] Retryが別Span IDかつ同じPersisted Parentへ収束する
- [ ] Outbox／Relay／Schedule／Maintenance／Replay Spanが境界どおり終了する
- [ ] Structured JSONLとObserved JournalがActive Trace／Span相関を持つ
- [ ] Safe Attribute allowlist以外を出さない
- [ ] Telemetry API／Provider FailureがPrimary Lifecycleを変更しない
- [ ] Nested／Exception／Signal Interruption後にActive Scopeが残らない
- [ ] Focused／Full SuiteとConsumerが成功する
- [ ] Report／STATE／TODOを同期し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app vendor/bin/phpunit tests/Internal/Telemetry tests/Internal/Execution tests/Internal/Http tests/Internal/Outbox tests/Internal/Scheduling tests/Internal/Scheduler tests/Internal/Replay tests/Internal/Logging
bash tests/Consumer/quickstart-e2e.sh
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Completion Report

`develop/orchestration/reports/P20-018C-opentelemetry-trace-adapter.md`へSummary、Changed Files、Span／Parent Matrix、Safe Attribute／Failure Isolation Evidence、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
