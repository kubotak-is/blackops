# P20-013: Scheduled Application Operation Contract

Status: Accepted

## Goal

`#[ScheduledBy]`をApplication Operationの独立入口として実装する前に、Value、Cron／Timezone／DST、Misfire、Overlap、Identity、Idempotency、Actor、Journal、BlackOps CLIのContractを確定する。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/115-deferred-authoring-and-operation-dispatch.md`
- `develop/spec/82-operation-dispatch-and-deferred-authoring.md`
- `develop/decisions/027-clock-and-time.md`
- `develop/decisions/037-deferred-claim-and-attempt.md`
- `develop/decisions/038-worker-crash-recovery.md`
- `develop/decisions/109-phase-18-idempotency-and-outbox.md`
- `develop/decisions/126-implicit-inline-ephemeral-outcome.md`
- Current Operation Metadata／Manifest／Console／Deferred Acceptance／Maintenance Scheduler Source and Tests

## In Scope

- Current RuntimeのRead-only Design Audit
- `#[ScheduledBy]`のPublic Authoring Shape
- Scheduled OperationValueの生成境界
- Cron Grammar、Timezone、DST
- Misfire／Overlap Policy
- Schedule／Occurrence Identity、Persistence、Concurrency、Crash Recovery
- Scheduled RootのOperation ID、Idempotency、Retry／Replay関係
- Schedule Actor／Authorization
- ExecutionContext／Transport／Journalへ伝播するSchedule Context
- Application Operation用BlackOps CLIとMaintenance SchedulerのProcess分離
- D134のQuestion、Recommendation、回答反映
- Decision後のSpecification／実装Task分割
- TODO／STATE／Report同期

## Out of Scope

- Production Code、Test、Migration、Dependency、Public APIの実装
- `#[ScheduledBy]`、Schedule Runtime、CLI、Daemonの実装
- Existing `scheduler:run`／`scheduler:daemon`の変更
- Existing HTTP／Console／Deferred／Idempotency Contractの変更
- Phase 21 Transaction Interception
- Journal／Outcome Tenant Isolation、Encryption、OpenTelemetry Adapter
- Public Guide／Website変更
- Commit、Push、PR、External Deploy

## Files Allowed to Change

- `develop/decisions/134-scheduled-application-operation.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-013-scheduled-application-operation-contract.md`

Decision回答後に新しい確定Specificationを作る。Production Codeまたは上記以外の変更が必要なら実装を広げず、Reportへ記録する。

## Audit Questions

1. Existing Metadata／Manifestは入口Metadataをどこまで保持し、Schedule用Schemaをどこへ追加するか。
2. Scheduled Rootが型付きValueを安全に構築する最小Contractは何か。
3. CronのCalendar SlotをUTC、IANA Timezone、DSTでどう一意化するか。
4. 初回Deploy、Process停止、複数の未処理SlotをMisfire Policyでどう扱うか。
5. Running／Retry Scheduled／Dead Letter中の前回OccurrenceをOverlap判定でどう扱うか。
6. 複数Process、Transaction Rollback、Acceptance Crash後も一つのOccurrence／Operation IDへ収束できるか。
7. HTTP Actor-scoped IdempotencyとSchedule専用Occurrence Identityをどう分離するか。
8. Scheduled Service Principal、Authorization再評価、Credential非保存をどう保証するか。
9. Inline／Deferred双方でValidation、Journal、Outcome、Retryを既存Runtimeへ接続できるか。
10. Maintenance SchedulerとApplication ScheduleをCLI、Composition、Deployment Processでどう区別するか。

## Acceptance Criteria

- [x] D115／Specification 82のEntry／Execution分離を維持する
- [x] `#[ScheduledBy]`のIdentity、Cron、Timezone、Value Contract候補を示す
- [x] DST、Misfire、OverlapをFixture化できる時刻規則へ絞る
- [x] Schedule／Occurrence／Operation ID／Retry／ReplayのIdentity Matrixを示す
- [x] PostgreSQL Concurrency／Crash RecoveryのInvariantを示す
- [x] Actor／Authorization／Credential境界を示す
- [x] Schedule ContextのExecutionContext／Transport／Journal境界を示す
- [x] Maintenance Schedulerと異なるBlackOps CLI／Process Contractを示す
- [x] User回答をD134へ反映し、D134をDecidedにする
- [x] Decision後のSpecificationとProduction Task Packetを分割する
- [x] Production Codeを変更しない
- [x] Report／STATE／TODOを同期し、Commitしない

## Required Commands

```bash
rg -n "ScheduledBy|scheduler:run|scheduler:daemon|MaintenanceScheduler|OperationMetadata|ExecutionContext|Idempotency" src tests develop/spec develop/decisions
git diff --check
```

Production Testは変更しないため、このDecision Taskでは既存Suiteの再実行を必須にしない。確定SpecificationとProduction Task Packetで必要なTest Matrixを定義する。

## Completion Report

`develop/orchestration/reports/P20-013-scheduled-application-operation-contract.md`へSummary、Evidence Inventory、Recommended Contract、Open Decision Questions、Identity Matrix、Security／Time／Concurrency Boundaries、Commands and Results、Remaining Issues、Suggested Next Actionを記録する。
