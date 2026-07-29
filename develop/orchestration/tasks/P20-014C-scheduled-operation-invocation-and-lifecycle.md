# P20-014C: Scheduled Operation Invocation and Lifecycle

Status: Accepted

## Goal

P20-014Bが`claimed`として固定したScheduled Occurrence／Operation IDを、通常のInline／Deferred Operation Lifecycleへ接続する。

Scheduled RootのValue構築、Actor／Authorization、Execution Context、Deferred Transport、Journal Context、Occurrence accepted／terminal transitionを完成させる。CLI、Application Container Composition、Supervisor、Consumer Journey、Guideは接続しない。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/134-scheduled-application-operation.md`
- `develop/spec/98-scheduled-application-operation.md`
- `develop/spec/02-lifecycle-and-journal.md`
- `develop/spec/03-execution.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/18-operation-envelope.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/21-clock-and-time.md`
- `develop/spec/22-journal-record-schema.md`
- `develop/spec/23-journal-record-api.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/31-deferred-claim-and-attempt.md`
- `develop/spec/32-worker-crash-recovery.md`
- `develop/spec/33-execution-transport-contract.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- Accepted P20-014A／P20-014B Source、Task、Report
- Current Inline Dispatcher、Deferred Acceptance／Worker、Execution Context Codec、Journal Codec Source and Tests

## In Scope

- Public Application-owned `ScheduledActorProvider`
- Public `Scheduling` NamespaceのDeptrac Layer／Ruleset
- Framework-fixed Scheduled Runtime execution Actor
- Constructor-required-input-free Scheduled OperationValue construction
- Fixed Occurrence Operation IDを使うScheduled Root `ExecutionContext`
- Schedule name／scheduled-at／timezoneを持つ`ScheduleContext`のRoot接続
- Existing Inline Dispatcher LifecycleへのScheduled Envelope接続
- Existing Deferred Acceptance／Transport／Worker LifecycleへのScheduled Envelope接続
- Deferred Context Codecでのversioned Schedule Context round-tripとWorker Retry維持
- Canonical／Observed Journalのsafe Schedule Context projection
- Occurrence `claimed`から`accepted`／`completed`／`rejected`／`failed`／`dead_lettered`へのguarded transition
- Value Construction、Actor Resolution、Authorization、Acceptance／Execution Failureのsafe category
- Claimed Acceptance Recoveryで同じOccurrence／Operation IDを再利用するEvidence
- Report／STATE／TODO synchronization

## Out of Scope

- `operation:schedule:run` Command
- Application Container／Configuration／Build Artifact Composition
- Runtimeによる全Schedule列挙、Batch Summary、Exit Code
- Cron／systemd／Kubernetes／Daemon integration
- New Database Table／Migration／Column
- Retention purge
- HTTP／Console／Frontend Entry変更
- Existing Maintenance Scheduler変更
- Guide、Website、Example、Consumer Journey
- New Composer Dependency
- Commit、Push、PR、External Deploy

## Public Actor Contract

`BlackOps\Scheduling\ScheduledActorProvider`をPublic APIとして追加する。

```php
interface ScheduledActorProvider
{
    public function actor(ScheduleContext $context): ?ActorRef;
}
```

- `ConsoleActorProvider`は共用しない。
- Scheduled execution ActorはFramework固定の`ActorRef`とし、Application Providerから取得しない。
- Providerが返すActorをorigin／authorizationへ設定する。
- Authorization Metadataを持つScheduled OperationではProvider登録を必須にする。
- Providerなし、Invalid Return、Resolution Failureを匿名ActorへFallbackしない。
- Providerが明示的に`null`を返したAuthorized Operationは、通常Authorization境界でauthentication requiredとして拒否する。
- Actor ID、Credential、Secret、Provider ErrorをOccurrence Category、Transport、JournalのSchedule Contextへ保存しない。

## Scheduled Root Context Contract

- Occurrenceの固定Operation IDを`ExecutionContext::operationId()`へ使う。新しいOperation IDを発行しない。
- Root Correlation IDは同じOperation IDから作る。Causation ID、Idempotency Keyは持たない。
- `receivedAt`はOccurrenceの最初の`evaluatedAt`を使い、Acceptance Recoveryで変更しない。
- `ScheduleContext`はSchedule名、Occurrence `scheduledAt`、Metadata timezoneだけを持つ。
- Schedule ContextはInline Attempt、Deferred Payload、Worker Attempt／Retryで同一値を維持する。
- HTTP、Console、Application child dispatchのContextは引き続きSchedule `null`とする。
- 公開`with...()` Methodは追加しない。Internal Factoryで新Contextを組み立てる。

## Invocation Contract

- Scheduled Runtimeへ渡されたCompiled Metadata、Definition、OccurrenceのType／Schedule／Operation ID整合を検証する。
- ValueはCompiled MetadataのValue Classを引数なしで構築し、通常Validationを迂回しない。
- Inlineは既存Inline DispatcherのAuthorization、Journal、Transaction、Outcome／Rejection処理を再利用する。
- Deferredは既存Operation CodecとDeferred Acceptance Orchestratorを使い、通常の`received`／`accepted` JournalとPostgreSQL Transportを再利用する。
- `#[ScheduledBy]`だけのOperationはInline、`#[ScheduledBy]`＋`#[Deferred]`はDeferredとし、Execution StrategyをSchedule入口から上書きしない。
- Deferred Acceptance Recoveryは`claimed` Occurrenceの固定Operation IDと同じEncoded Message Integrityへ収束する。
- Scheduled入口をHTTP Idempotency Storeへ流用しない。

## Occurrence Lifecycle Contract

| Trigger | From | To | Category／Timestamp |
| --- | --- | --- | --- |
| Deferred acceptance成功 | `claimed` | `accepted` | `accepted_at`をAcknowledgement UTC Instantで設定 |
| Inline completion | `claimed` | `completed` | Categoryなし |
| Validation／Authorization rejection | `claimed` | `rejected` | Stable safe category |
| Value／Actor／Acceptance／Inline execution failure | `claimed` | `failed` | Stable safe category |
| Deferred worker completion | `accepted` | `completed` | Categoryなし |
| Deferred worker rejection | `accepted` | `rejected` | Stable safe category |
| Deferred terminal failure | `accepted` | `failed` | Stable safe category |
| Deferred dead letter | `accepted` | `dead_lettered` | Stable safe category |
| Deferred retry scheduled | `accepted` | `accepted` | Operation ID／Schedule Contextを維持 |

- TransitionはOperation IDとExpected Stateでguardし、Zero／Multiple Row Updateを成功扱いしない。
- Terminalから別Stateへ戻さない。
- Failure Categoryは有限のstable valueとし、Exception Class／Message、Raw SQL、Raw Database Errorを保存しない。
- UTC Timestampはmicrosecondsを保持する。
- Deferred acceptanceのoperations／journal／occurrence更新は同じDB Transactionへ参加する。
- Deferred Workerのoperations／journal／occurrence terminal更新も同じDB Transactionへ参加する。
- Inline Handler TransactionとOccurrence terminalizationのfailure orderingをTestで固定し、Handler成功をOccurrence failureで成功扱いしない。

## Journal／Transport Contract

- Execution Context CodecはOptional `schedule` Objectをversioned payloadへ追加する。
- Schedule Objectは`name`、Canonical UTC microseconds `scheduled_at`、IANA `timezone`だけを許可する。
- Missing ScheduleはLegacy／HTTP／Console／child contextとして`null`へdecodeする。
- Unknown／Missing field、Invalid name、Invalid timestamp、Invalid timezoneをSafe Codec Errorで拒否する。
- Canonical Journal Operation ContextへOptional Schedule Contextを追加し、PostgreSQL Journal Codecでround-tripする。
- Existing Journal payload without Scheduleは後方互換でdecodeする。
- Observed JournalもSchedule Contextを保持する。Actorは既存どおりmaskし、Schedule ContextへActor／Credentialを追加しない。
- Journal／Deferred Payloadの時刻はUTC RFC 3339 microsecondsを維持する。

## Files Allowed to Change

- `src/Scheduling/**`
- `src/Core/ExecutionContext.php`
- `src/Core/ScheduleContext.php`
- `src/Internal/Scheduling/**`
- `src/Internal/ExecutionContext/**`
- `src/Internal/Codec/**`
- `src/Internal/Execution/InlineDispatcher.php`
- `src/Internal/Execution/DeferredAcceptanceOrchestrator.php`
- `src/Internal/Execution/DeferredFailureSupervisor.php`
- `src/Internal/Execution/DeferredLeaseExpiredRecovery.php`
- `src/Internal/Execution/DeferredWorkerRuntime.php`
- `src/Internal/Execution/DeferredWorkerRuntimeServices.php`
- `src/Internal/Execution/DeferredWorkerRuntimeStorage.php`
- `src/Internal/Journal/JournalRecordBuilder.php`
- `src/Internal/Projection/ObservedJournalRecordProjector.php`
- `src/Journal/JournalOperation.php`
- `src/Transport/PostgreSql/PostgreSqlJournalRecordCodec.php`
- `deptrac.yaml`
- `tests/Scheduling/**`
- `tests/Core/ExecutionContextTest.php`
- `tests/Internal/Scheduling/**`
- `tests/Internal/ExecutionContext/**`
- `tests/Internal/Codec/**`
- `tests/Internal/Execution/InlineDispatcherTest.php`
- `tests/Internal/Execution/DeferredLeaseExpiredRecoveryTest.php`
- `tests/Internal/Execution/DeferredWorkerRuntimeTest.php`
- `tests/Internal/Journal/**`
- `tests/Internal/Projection/ObservedJournalRecordProjectorTest.php`
- `tests/Journal/**`
- `tests/Transport/PostgreSql/PostgreSqlDeferredAcceptanceOrchestratorTest.php`
- `tests/Transport/PostgreSql/PostgreSqlJournalRecordCodecTest.php`
- `tests/Transport/PostgreSql/PostgreSqlInlineDispatcherIntegrationTest.php`
- `tests/Architecture/**`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/98-scheduled-application-operation.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-014C-scheduled-operation-invocation-and-lifecycle.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- Existing Public `Dispatcher`／HTTP／Console APIのSignatureを変更しない。
- Existing Inline／Deferred Lifecycleを複製せず、Scheduled Envelopeを受けるInternal seamへ共通化する。
- Reflectionでprivate stateを書き換えない。
- Scheduled Operation Definitionのconstructorless化を要求しない。Definition／Handler resolutionはP20-014D Compositionの責務として注入可能な境界にする。
- Existing OperationValue Validator、Authorization Evaluator、Journal Writer、Operation Codec、Deferred Sender／Workerを迂回しない。
- Schedule ContextをOperationValue、Outcome、RejectionReasonへ混入させない。
- OccurrenceとDeferred operationを別Transactionで部分確定させない。
- Existing Context／Journal CodecのLegacy payloadを維持する。
- ErrorへSchedule Cron全文、Operation／Value Class、Actor ID、Credential、Canonical Value、Raw SQL、Raw Database Errorを露出しない。
- New Dependencyを追加しない。
- `Scheduling` NamespaceはCoreだけへ依存可能とし、InternalからSchedulingへの依存をDeptracへ明示する。
- Existing Phase 20 Working Tree差分を保持する。
- WorkerはCommitしない。

## Acceptance Criteria

- [x] Public ScheduledActorProviderとFramework-fixed execution ActorがConsole Actor Providerから独立している
- [x] Scheduling NamespaceのDeptrac Layer／RulesetとArchitecture Evidenceが追加されている
- [x] Authorized ScheduleのProvider必須、明示null、Resolution Failureを匿名Fallbackなしで処理する
- [x] Valueを引数なし構築し、通常Validation／Authorizationを通す
- [x] Fixed Occurrence Operation ID／Correlation ID／receivedAt／Schedule ContextでScheduled Rootを作る
- [x] Inline Scheduled Operationが通常Journal／Transaction／Outcome／Rejection Lifecycleを通る
- [x] Deferred Scheduled Operationが通常Acceptance／Transport／Worker／Retry／Dead Letter Lifecycleを通る
- [x] Deferred PayloadがSchedule Contextをversion付きでround-tripし、Retryで維持する
- [x] Canonical／Observed JournalがSchedule Contextを安全にround-tripし、Legacy payloadを維持する
- [x] Claimed Acceptance Recoveryが同じOccurrence／Operation IDへ収束する
- [x] Occurrence accepted／completed／rejected／failed／dead_lettered transitionをExpected Stateでguardする
- [x] Deferred acceptance／worker terminalizationとOccurrence更新が同一Transactionでrollbackする
- [x] Stable safe categoryだけをOccurrenceへ保存し、Raw Error／Actor／Valueを保存しない
- [x] HTTP／Console／Application child ContextはSchedule nullのまま
- [x] CLI／Application Composition／Guide／Existing Maintenance Schedulerを変更しない
- [x] Focused／Full Test、Format、Analysis、Architecture Guardを実行する
- [x] Report／STATE／TODOを同期し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Scheduling tests/Core/ExecutionContextTest.php tests/Internal/Scheduling tests/Internal/ExecutionContext tests/Internal/Codec tests/Internal/Execution/InlineDispatcherTest.php tests/Internal/Execution/DeferredWorkerRuntimeTest.php tests/Internal/Projection/ObservedJournalRecordProjectorTest.php tests/Journal tests/Transport/PostgreSql/PostgreSqlDeferredAcceptanceOrchestratorTest.php tests/Transport/PostgreSql/PostgreSqlJournalRecordCodecTest.php
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago analyze src tests
docker compose run --rm app vendor/bin/deptrac analyse --no-progress
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

CommandがRepository既存問題または実行環境で失敗する場合は、未実行理由、Exact Error、変更Sourceに限定した代替CommandをReportへ記録する。

## Completion Report

`develop/orchestration/reports/P20-014C-scheduled-operation-invocation-and-lifecycle.md`へSummary、Changed Files、Decisions and Assumptions、Actor Matrix、Invocation Matrix、Occurrence Transition Matrix、Transport／Journal Shape、Transaction／Recovery Evidence、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
