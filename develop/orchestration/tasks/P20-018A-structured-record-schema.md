# P20-018A: Structured Record Schema

Status: Accepted

## Goal

Application／Framework／Journal／Audit JSONLをBlackOps Structured Record `schemaVersion: 1`の共通Top-level Envelopeへ正規化し、Operation／Attempt／Actor／Tenant／FailureのSafe Projectionを固定する。

## Source of Truth

- `AGENTS.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/94-journal-documentation.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/decisions/136-structured-logging-and-opentelemetry.md`

## Dependencies

- P20-017 Accepted

## In Scope

- Canonical Structured Record／Formatter／Encoder
- Application／Framework RecordのTop-level JSONL
- Observed Journal Version 1の共通Field整合
- Retention等のFramework-owned Audit JSONL整合
- Top-level Operation／Attempt／Tenant Safe Projection
- UTC Microseconds、LF、Empty Object／List Wire Shape
- Monolog Nested Context Shapeの廃止
- Installed Application／Quickstart JSONL Fixture更新
- Unit、Integration、Consumer Wire／Redaction Evidence

## Out of Scope

- OpenTelemetry Dependency／Trace Context／Span／Metric
- Health／Readiness
- Remote Sink／Exporter／Collector
- Public Guide／Website

## Files Allowed to Change

- `src/Internal/Logging/**`
- `src/Logging/**`
- `src/Internal/Projection/**`
- `src/Journal/**`
- `src/Core/ActorContext.php`
- `src/Core/TenantRef.php`
- `src/Core/ExecutionContext.php`
- `src/Core/Retention/**`
- `src/Internal/Retention/**`
- `src/Internal/Application/**` only where logging／observer／audit composition directly requires it
- Corresponding files under `tests/**`
- Logging／JSONL fixtures in `examples/quickstart/**`, `resources/skeleton/**`, `tests/Consumer/**`
- `docs/internal/**` only when an existing implementation contract would otherwise be false before P20-018F
- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/94-journal-documentation.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-018A-structured-record-schema.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- `schemaVersion`、`kind`、`occurredAt`をTop-levelへ出す
- Monolog固有`datetime`／`level_name`／integer `level`／`extra`を公開Wireへ出さない
- Application／Frameworkはlowercase `level`、`message`、`channel`、Filtered `context`を持つ
- `operation.attemptId`をTop-level `attempt`へ正規化し、Legacy Dual-writeしない
- Actor／Tenant IDは固定`[masked]`。Hash／Raw IDを出さない
- Protection FailureはSafe Code／Purposeだけにする
- Telemetry Blockは後続Taskまで省略し、Operation IDから生成しない
- Observed Journalの既存Lifecycle Field、Nullable Attempt、Empty Shapeを壊さない
- Application Log FailureはBest-effort、Required Audit／Journal Policyは既存保証を維持する
- New Dependencyを追加しない
- WorkerはReview前にCommitしない

## Acceptance Criteria

- [x] Application／Framework／Journal／Auditが共通Top-level Version 1を持つ
- [x] Monolog Nested ContextのSchema不整合が解消される
- [x] UTC Microseconds、LF、Empty Object／ListがExact Wire Testで固定される
- [x] Operation／Attempt FieldがSpecification 100へ一致する
- [x] Actor／Tenant Raw ID／Hashが全Recordから排除される
- [x] Payload／Outcome／Key／Provider／Throwable Detail Sentinelが出ない
- [x] Operation外System LogはOperation／Attemptを持たない
- [x] Existing Quickstart Failure Journeyが同じOperation IDでHTTP／Journal／Log相関できる
- [x] Focused／Full SuiteとConsumerが成功する
- [x] Report／STATE／TODOを同期し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app vendor/bin/phpunit tests/Internal/Logging tests/Logging tests/Internal/Projection tests/Internal/Retention
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

`develop/orchestration/reports/P20-018A-structured-record-schema.md`へSummary、Changed Files、Decisions and Assumptions、Exact Wire／Redaction Evidence、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
