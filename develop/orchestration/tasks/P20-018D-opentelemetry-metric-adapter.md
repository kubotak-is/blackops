# P20-018D: OpenTelemetry Metric Adapter

Status: Accepted

## Goal

Application-owned OpenTelemetry Meter Providerへ低CardinalityのOperation／Worker／Outbox／Scheduler／Observer／Protection Metricを接続し、安定したName、Type、Unit、有限属性を固定する。

## Source of Truth

- `AGENTS.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/spec/32-worker-crash-recovery.md`
- `develop/spec/39-retention-runtime.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/98-scheduled-application-operation.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/decisions/136-structured-logging-and-opentelemetry.md`

## Dependencies

- P20-018C Accepted

## In Scope

- OpenTelemetry Metric Adapter／No-op Boundary
- Application Meter Provider registration／runtime composition
- Specification 100のStable Instrument Matrix
- Operation Duration／Active／Result
- Worker Claim／Heartbeat Failure
- Outbox Relay Duration／Record Result
- Application／Maintenance Scheduler Duration／Occurrence Result
- Observer／Storage Protection Failure
- Optional Bounded Queue Depth／Oldest Age Snapshot
- Finite Attribute Enum／Cardinality Guard
- Long-running UpDownCounter Balance／Failure Isolation
- Unit、Integration、Runtime Evidence

## Out of Scope

- Dashboard／Alert／SLO／Prometheus Exporter
- SDK／OTLP Exporter／Collector Production Composition
- Operation／Tenant／Actor／Trace単位Metric
- Health／Readiness Public API、Guide／Website

## Files Allowed to Change

- `src/Telemetry/**`
- `src/Internal/Telemetry/**`
- `src/Internal/Execution/**`
- `src/Internal/Outbox/**`
- `src/Internal/Scheduling/**`
- `src/Internal/Scheduler/**`
- `src/Internal/Journal/**`
- `src/Internal/Projection/**`
- `src/Internal/Replay/**`
- `src/Internal/StorageProtection/**`
- `src/Transport/PostgreSql/**` only for bounded operational snapshot queries
- `src/Internal/Application/**`
- `src/Application/ApplicationBuilder.php`
- Corresponding files under `tests/**`
- Metric-focused Consumer fixtures under `tests/Consumer/**`
- `deptrac.yaml`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-018D-opentelemetry-metric-adapter.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- Meter Provider未登録はNo-opでRuntime結果を変えない
- Instrument Name、Type、UCUM UnitはSpecification 100へ厳密一致する
- Attributeは列挙済み有限EnumとCompiled Operation Typeだけを使う
- Operation／Attempt／Correlation／Trace／Tenant／Actor／Key／Occurrence／Record IDをLabelへ入れない
- Tenant／Actor Type、Schedule Name、Raw URL／Input、自由文ReasonをLabelへ入れない
- Success／FailureでMetric名を分けず有限Result属性を使う
- Active UpDownCounterは全Exit Pathで増減がBalanceする
- Queue SnapshotはBounded Queryで、Payload Decode／Tenant Labelを使わない
- Telemetry API Failureを同じMetric Adapterへ再帰記録しない
- Metric FailureはPrimary Operation／Readinessを変更しない
- WorkerはReview前にCommitしない

## Acceptance Criteria

- [x] 全Stable InstrumentのName／Type／UnitがExact Testで固定される
- [x] Result／Runtime／Scheduler／Failure Enumが有限である
- [x] Operation／Worker／Outbox／Scheduler Journeyが正しいData Pointを出す
- [x] Observer／Protection FailureがSafe Code／Purposeだけを出す
- [x] High-cardinality禁止Fieldが全Attributeから排除される
- [x] Active CounterがSuccess／Reject／Retry／Failure／Interruptionで0へ戻る
- [x] Optional Queue Snapshotを追加する場合はDecodeなし／Bounded／Identityなしである（未追加はN/A）
- [x] No-op／throwing ProviderでPrimary Lifecycleを維持する
- [x] Focused／Full SuiteとConsumerが成功する
- [x] Report／STATE／TODOを同期し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app vendor/bin/phpunit tests/Internal/Telemetry tests/Internal/Execution tests/Internal/Outbox tests/Internal/Scheduling tests/Internal/Scheduler tests/Internal/Projection tests/Internal/StorageProtection
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

`develop/orchestration/reports/P20-018D-opentelemetry-metric-adapter.md`へSummary、Changed Files、Instrument／Attribute Matrix、Cardinality／Balance／Failure Evidence、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
