# P20-018B: Telemetry Context Propagation

Status: Ready

## Goal

OpenTelemetry APIとW3C Trace ContextのSerializable Boundaryを追加し、HTTP／Direct RootからChild、Deferred Context、Transactional Outbox、Worker RetryまでTrace Contextを安全に伝播する。

## Source of Truth

- `AGENTS.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/33-execution-transport-contract.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/decisions/136-structured-logging-and-opentelemetry.md`

## Dependencies

- P20-018A Accepted

## In Scope

- `open-telemetry/api` Production DependencyのSupply Chain Review／追加
- Public immutable `TelemetryContext`
- `ExecutionContext::telemetry(): ?TelemetryContext`
- HTTP W3C `traceparent`／`tracestate` Extract
- Public Root Dispatchの末尾Optional Telemetry Context
- Child／Deferred／Outbox／Worker Retry Propagation
- Execution Context CodecのBackward-compatible Optional Field
- Canonical／Observed JournalのOptional Telemetry Correlation Shape
- Invalid／Zero／Malformed／Oversized ContextのSafe Handling
- Unit、Integration、Protected Transport／Outbox Evidence

## Out of Scope

- Span開始／終了、Tracer Provider Composition
- Metrics
- SDK／Exporter／Collector
- Terminal Operation Replay APIの新設
- Health／Readiness、Guide／Website

## Files Allowed to Change

- `composer.json`
- `composer.lock`
- `src/Core/ExecutionContext.php`
- `src/Core/Execution/**`
- `src/Execution/**`
- `src/Telemetry/**`
- `src/Internal/Telemetry/**`
- `src/Internal/ExecutionContext/**`
- `src/Internal/Codec/**`
- `src/Internal/Http/**`
- `src/Http/**` only for W3C entry extraction／explicit context plumbing
- `src/Internal/Execution/**`
- `src/Internal/Outbox/**`
- `src/Outbox/**`
- `src/Journal/**`
- `src/Internal/Journal/**`
- `src/Internal/Projection/**`
- `src/Transport/**` only where encoded context propagation requires it
- `src/Internal/Application/**` only for shared context service composition
- Corresponding files under `tests/**`
- Required Consumer propagation fixtures under `tests/Consumer/**`
- `deptrac.yaml`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/33-execution-transport-contract.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-018B-telemetry-context-propagation.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- Production Dependencyは`open-telemetry/api`だけ。SDK／Exporterを`require`へ入れない
- Package導入前にRepositoryのComposer Package Assessment手順を実施する
- `TelemetryContext`はValid `traceparent`とOptional Bounded `tracestate`だけを持つ
- Baggage、Credential、Actor、Tenant、Valueを追加しない
- Invalid Remote HeaderはRaw値をLog／Exceptionへ出さずParentなしとして扱う
- ChildはTelemetry Overrideを公開せずCurrent Contextを継承する
- Deferred／OutboxはBOPD Encrypted Contextへ保存し、Clear Column／AADを弱めない
- Retryは受理済みPersisted Contextを再解決／置換しない
- Observer ReplayはOriginal Record Correlationを上書きしない
- Operation／Correlation IDからTrace／Span IDを生成しない
- WorkerはReview前にCommitしない

## Acceptance Criteria

- [ ] Composer Assessment後にAPI-only Production Dependencyが追加される
- [ ] TelemetryContextのValid／Invalid／zero／length Matrixが固定される
- [ ] ExecutionContextあり／なしがround-tripする
- [ ] HTTP Valid Parentを取り込み、Invalid CarrierはSafeにRoot扱いする
- [ ] Direct Rootが末尾Optional Contextを受ける
- [ ] Child／Deferred／Outbox／Worker Retryで同じTrace Contextが維持される
- [ ] Trace ContextがProtected Blob外のClear Columnへ出ない
- [ ] Observed JournalがValid CorrelationだけをSafe Shapeで保持できる
- [ ] Baggage／Raw Carrier／Tenant／Actor／CredentialがPersistence／Errorへ出ない
- [ ] Existing Tenant／Storage Protection／Quickstart Journeyを維持する
- [ ] Full Suite／Architecture／Package Exportが成功する
- [ ] Report／STATE／TODOを同期し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app vendor/bin/phpunit tests/Core tests/Internal/ExecutionContext tests/Internal/Codec tests/Internal/Http tests/Internal/Outbox tests/Transport
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

`develop/orchestration/reports/P20-018B-telemetry-context-propagation.md`へPackage Assessment、Summary、Changed Files、Propagation Matrix、Protection／Redaction Evidence、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
