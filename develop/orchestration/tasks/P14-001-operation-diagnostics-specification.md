# P14-001: Operation Diagnostics Specification

Status: Ready

## Goal

Decision 097で確定したPhase 14のOperation Diagnosticsを、実装者が追加判断なしでTask分割できる仕様とDelivery Planへ落とし込む。

Failure Lifecycle、Operation ID相関、Safe Projection、内部Diagnostics Aggregate、CLI、Local Viewer、PSR-3接続の境界を正本化する。

## In Scope

- Operation成立前／後のErrorとOperation IDの境界
- Inline ThrowableのTerminal LifecycleとHTTP 500相関Contract
- Canonical Journal、Deferred State、Outcome、Dead Letter、Purge Auditを集約する内部Query Model
- Safe Diagnostics ProjectionとRestricted Canonical Dataの責任分界
- Missing／Fully Purged／Unauthorized／Partially PurgedのAvailability Contract
- `operation:inspect`のHuman／JSON／Exit Code Contract
- Local ViewerのEnable Gate、Loopback、Token、Read-only境界
- PSR-3 Runtime CompositionとOperation Scope ID相関
- P14-002からP14-007までのDelivery PlanとAcceptance Gate

## Out of Scope

- Production Code、Test、Migration、Application Configurationの変更
- Public PHP Diagnostics API、Public HTTP Status／Outcome API
- Application Authentication／AuthorizationとTenant分離
- Canonical Raw Dataまたは例外Messageの表示Option
- OpenTelemetry、Remote Collector、Metric、Dashboard
- Documentation Website公開

## Relevant Specifications and Decisions

- `develop/spec/02-lifecycle-and-journal.md`
- `develop/spec/03-execution.md`
- `develop/spec/05-http.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/22-journal-record-schema.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/26-journal-ports.md`
- `develop/spec/31-deferred-claim-and-attempt.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/39-retention-runtime.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/decisions/097-phase-14-operation-diagnostics.md`

## Files Allowed to Change

- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/spec/README.md`
- `develop/decisions/098-deferred-acceptance-failure-lifecycle.md`
- `develop/TODO.md`
- `develop/orchestration/reports/P14-001-operation-diagnostics-specification.md`
- `develop/STATE.md`

Source、Test、Guide、既存Specification、Decisionは変更しない。仕様の矛盾は実装前にReportへ記録する。

## Acceptance Criteria

- [ ] Operation成立前／後のFailureとOperation ID境界が明記されている
- [ ] Inline ThrowableのJournal、HTTP 500、PSR-3相関要件が固定されている
- [ ] 内部Diagnostics AggregateのField、State Authority、Availability、Integrity Failureが定義されている
- [ ] CLIのInput、Human出力、Version付きJSON、stdout／stderr、Exit Codeが定義されている
- [ ] Local Viewerの既定無効、明示起動、Loopback、Random Token、Read-only境界が定義されている
- [ ] Sensitive、Actor、Credential、Error Message、Canonical Raw Dataの禁止境界が定義されている
- [ ] P14-002からP14-007の各Taskが単一責務とAcceptance Gateを持つ
- [ ] `develop/spec/README.md`と`develop/TODO.md`が同期されている
- [ ] Production Codeを変更しない
- [ ] Report／STATEを更新し、WorkerはCommitしない

## Required Commands

```bash
rg -n "OperationDiagnostics|operation:inspect|operation:viewer|operation.unavailable|diagnostics.viewer|ExecutionScopedLogger" develop/spec/65-operation-diagnostics.md develop/spec/66-phase-14-delivery-plan.md develop/TODO.md
rg -n "65-operation-diagnostics|66-phase-14-delivery-plan" develop/spec/README.md
git diff --check
```

## Expected Report

`develop/orchestration/reports/P14-001-operation-diagnostics-specification.md`へSummary、Changed Files、Decisions and Assumptions、Specification Coverage、Task Breakdown、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
