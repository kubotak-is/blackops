# P20-013: Scheduled Application Operation Contract Report

## Summary

D115で将来Taskへ分離されたScheduled Application OperationをCurrent Sourceへ照合し、D134を確定した。Production Codeは変更していない。

DecisionはCron Attributeだけでなく、入力のない入口からのOperationValue構築、Schedule Context、Actor、Occurrence Persistence、Journal、CLIを同時に扱う。UserはQuestion 1から8のRecommendation A一式を承認した。

## Changed Files

- `develop/decisions/134-scheduled-application-operation.md`
- `develop/orchestration/tasks/P20-013-scheduled-application-operation-contract.md`
- `develop/orchestration/reports/P20-013-scheduled-application-operation-contract.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`

## Evidence Inventory

| Area | Current Evidence | Design Impact |
| --- | --- | --- |
| Entry／Execution | D115、Specification 82 | `ScheduledBy`と`Deferred`を独立させる |
| Operation Metadata | `OperationMetadata`、`OperationMetadataCompiler` | Schedule Metadata／Manifest Schemaは未実装 |
| Console Value | `OperationConsoleMetadataCompiler`、`OperationConsoleValueBinder` | Consoleは入力OptionからValueを構築するが、Scheduleには入力元がない |
| Deferred Acceptance | `DeferredHttpOperationAcceptor`、`DeferredAcceptanceOrchestrator` | 通常のValidation／Authorization／Journal／Transportを再利用できる |
| Idempotency | D109、`IdempotencyScopeHasher` | Existing ScopeはOperation Type＋Authenticated Actor＋Keyで、Schedule Occurrenceとは一致しない |
| Execution Context | `ExecutionContext` | Optional Schedule Contextは未実装だが、Context Extensionは後方互換Getterで追加する方針 |
| Maintenance | `MaintenanceScheduler`、`scheduler:run`、`scheduler:daemon` | Application Operation SchedulerとProcess／Commandを分離する |
| Actor | `ConsoleActorProvider`、`ActorContext` | Console OperatorとScheduled Service Principalを共用しない |

## Recommended Contract

- 一Operation一Schedule、明示Schedule名、5 Field Cron、IANA Timezone、既定UTC
- Scheduled OperationValueは必須Constructor引数なし
- Nominal時刻とSchedule名はOptional `ScheduleContext`としてExecutionContextへ伝播
- 初回DeployはBackfillなし、停止後は最新一件だけを`FireOnce`
- 前回非Terminal中は新Occurrenceを`skipped_overlap`
- PostgreSQLの`(schedule name, nominal UTC)`一意制約と固定Operation ID
- HTTP Actor-scoped IdempotencyをSchedule重複排除へ流用しない
- `ScheduledActorProvider`と固定Framework execution Actorを分離
- Application Operation用一回実行CLIは`blackops operation:schedule:run`
- Existing Maintenance Scheduler、Console手動実行、Worker Retry、Operation ReplayとIdentityを分離

## Identity Matrix

| Action | Schedule Occurrence | Operation ID | Causation |
| --- | --- | --- | --- |
| 定刻評価の初回受理 | 新規 | 新規発行してOccurrenceへ固定 | Rootのためなし |
| 同じSlotの再評価／Crash Recovery | 同一 | 同一 | 変更なし |
| Worker Retry | 同一 | 同一 | 変更なし |
| Misfire Skip／Overlap Skip | 記録する | 発行しない | なし |
| `#[ConsoleCommand]`手動実行 | 使用しない | 新規 | Rootのためなし |
| 明示Operation Replay | 元Occurrenceを再利用しない | 新規 | 元Operation |

## Security／Time／Concurrency Boundaries

- Attribute、Value、Transport、JournalへCredential／Secretを保存しない。
- Authorized Scheduled OperationはApplication-owned Actor Providerを必須にし、匿名Fallbackしない。
- Calendar評価は設定Timezone、保存／比較はUTCを正本とする。
- DSTで存在しないLocal Timeは実行せず、重複Local Timeは一回だけとする案をUser判断へ出した。
- 複数EvaluatorはPostgreSQL Transaction、Claim、一意制約で同じOccurrenceへ収束する。
- Schedule重複排除はOperation Handlerの外部副作用にExactly Onceを保証しない。

## Decision Answers

D134のQuestion 1から8はすべてAで確定した。

## Commands and Results

```text
PASS rg -n "ScheduledBy|scheduler:run|scheduler:daemon|MaintenanceScheduler|OperationMetadata|ExecutionContext|Idempotency" ...
PASS Current Source／Decision／Specification audit
PASS git diff --check
NOT RUN Production tests: Production Code／Testを変更していないDecision Taskのため
```

## Acceptance Criteria

- [x] Existing Entry／Execution分離とCurrent Runtimeを監査した
- [x] Value、Time、Identity、Actor、Journal、CLIをD134のQuestionへ整理した
- [x] RecommendationとIdentity Matrixを示した
- [x] User回答をD134へ反映し、Decidedにする
- [x] 確定SpecificationとProduction Task Packetを作成する
- [x] Production Codeを変更していない
- [x] STATE／TODO／Spec Index同期と`git diff --check`

## Remaining Issues

- Cron Parser PackageのSupply Chain Review
- Production Task Packetごとの実装とAcceptance

## Suggested Next Action

Specification 98を正本に、Public Attribute／Schedule Context／Manifest Contractの最初のProduction Taskへ進む。
