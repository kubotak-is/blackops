# P20-018E: Operational Health and Local Collector Evidence

Status: Accepted

## Goal

Liveness／ReadinessのSafe Public Queryと明示Composition用HTTP／CLI Adapterを実装し、Local Docker OpenTelemetry CollectorへTrace／Metricを送るConsumer JourneyでPhase 20 Observabilityを実証する。

## Source of Truth

- `AGENTS.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/51-local-runtime-and-consumer-e2e.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/decisions/136-structured-logging-and-opentelemetry.md`
- Official OpenTelemetry Collector Docker／PHP Exporter Documentation

## Dependencies

- P20-018D Accepted

## In Scope

- Public Liveness／Readiness Query／Report／Check types
- Safe Version 1 JSON／Human Formatter
- Explicit PSR-15 Handler／CLI Adapter
- Compiled Artifact／Runtime Config／Database／Migration／Provider／Runtime Service Checks
- HTTP 200／503、no-store、No Detail Leak
- Framework Package API-only Dependency Audit
- OpenTelemetry SDK／OTLP Exporter／HTTP ClientのTest-only Supply Chain Review
- Pinned Official Collector Consumer Fixture
- OTLP HTTP Trace／Metric Export to Collector `debug` Exporter
- Inline／Deferred Retry／Outbox／Schedule／Maintenance Span Evidence
- Metric Name／Type／Unit／Cardinality Evidence
- JSONL Trace correlation、Redaction、Collector outage isolation
- Deterministic Cleanup

## Out of Scope

- Probe Routeの自動登録
- Worker／Scheduler内蔵HTTP Server
- Production SDK／Exporter／Collector Composition
- Vendor Backend、Dashboard、Alert、Remote Deploy
- Guide／Website本文

## Files Allowed to Change

- `composer.json`
- `composer.lock`
- `src/Observability/**`
- `src/Internal/Observability/**`
- `src/Http/**` only for explicit probe handlers
- `src/Console/**` only for explicit probe adapter types
- `src/Internal/Application/**`
- `src/Application/ApplicationBuilder.php`
- `src/Internal/Migration/**` only for read-only compatibility check composition
- Corresponding files under `tests/**`
- `tests/Consumer/opentelemetry-observability.sh`
- `tests/Consumer/fixtures/opentelemetry/**`
- Consumer-only Compose／Collector／PHP SDK fixture files required by the exact journey
- `compose.yaml` only if an opt-in test profile is strictly required; default services／profiles must remain unchanged
- `deptrac.yaml`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/51-local-runtime-and-consumer-e2e.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-018E-operational-health-and-local-collector.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- LivenessはProcess応答だけを確認し、DB／Provider／Exporterへ接続しない
- ReadinessはSpecification 100のBounded Checkだけを行う
- Collector／Exporter FailureをReadiness Failureにしない
- ResultへDSN、Host、Port、Schema、SQL、Provider Class、Key ID、Credential、Raw Errorを出さない
- Route／CommandはApplicationが明示Compositionし、Framework Defaultへ自動登録しない
- Framework `require`は`open-telemetry/api`だけを維持する
- SDK／OTLP Exporter／HTTP ClientはConsumer／`require-dev`だけに置く
- Collectorは`latest`を使わない。Task開始時に公式最新と確認した`otel/opentelemetry-collector:0.158.0@sha256:5b97e6e3550ec6e48a71dba6f6304d349a293af8df4ee1f51da67be94fce2ecd`を固定する
- OTLP HTTP `4318`を使い、ext-grpcを必須にしない
- Collector ConfigはOTLP Receiver＋`debug` Exporterだけで、Remote Credentialを使わない
- Collector LogへSensitive Sentinel／高Cardinality Labelがないことを否定検証する
- Consumerは成功／失敗でContainer、Network、Volume、一時Artifactを削除する
- WorkerはReview前にCommitしない

## Acceptance Criteria

- [x] Liveness／Readiness ReportのExact Version 1 Shapeが固定される
- [x] LivenessがDependency FailureでFailしない
- [x] ReadinessがDB／Migration／Required Composition FailureをSafe CodeでFailする
- [x] Telemetry Export FailureがReadiness／Primary Journeyを変えない
- [x] Explicit HTTP Adapterが200／503、JSON、no-storeを返し、Routeを自動追加しない
- [x] Explicit CLI AdapterがHuman／one-line JSONを安全に返す
- [x] Framework ArchiveのProduction DependencyがAPI-onlyである
- [x] Pinned CollectorがLocal Dockerで起動しOTLP HTTPを受ける
- [x] Inline／Deferred Retry／Outbox／Schedule／MaintenanceのSpan MatrixがCollector Logで確認できる
- [x] Structured JSONLとCollector Trace／Span IDが相関する
- [x] Stable Metric Name／Type／Unit／有限属性がCollector Logで確認できる
- [x] Sensitive Sentinel／Identity LabelがCollector Logにない
- [x] Collector停止中もPrimary Journey／Readinessが既存結果を維持する
- [x] Consumer Cleanup後に対象Container／Network／Volume／一時Artifactが残らない
- [x] Full Suite／Consumer／Package Exportが成功する
- [x] Report／STATE／TODOを同期し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app vendor/bin/phpunit tests/Observability tests/Internal/Observability tests/Http tests/Internal/Application
bash -n tests/Consumer/opentelemetry-observability.sh
bash tests/Consumer/opentelemetry-observability.sh
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/framework-package-export.sh
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Completion Report

`develop/orchestration/reports/P20-018E-operational-health-and-local-collector.md`へPackage／Image Assessment、Summary、Changed Files、Probe Matrix、Collector Span／Metric／Redaction／Outage／Cleanup Evidence、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
