# D134: Scheduled Application Operation

Status: Decided

## Context

D115とSpecification 82は、`#[ScheduledBy(...)]`をHTTP `#[Route]`、`#[ConsoleCommand]`と並ぶApplication Operationの入口として扱い、Execution Strategyとは独立させる方針を確定した。

```php
#[ScheduledBy(...)]
#[Deferred]
final readonly class GenerateDailyReport implements Operation
{
}
```

一方、次は未確定である。

- Scheduleの式、Timezone、DST
- Process停止中に発生したMisfire
- 前回実行がTerminalでない場合のOverlap
- Deploy、再起動、複数Processでも重複しないSchedule／Occurrence Identity
- Scheduled Root OperationのIdempotency、Actor、Journal
- HTTPやConsoleと異なり入力がない入口から、型付きOperationValueをどう生成するか
- 既存`maintenance scheduler`と区別できるBlackOps CLI／Runtime

Cron Attributeだけを追加しても、Value、Actor、永続状態、重複制御が未定義なら安全に実行できない。Public API、Manifest、ExecutionContext、PostgreSQL Migration、CLI、Journalを変更する前に一つのContractへ絞る。

## Inherited Decisions

次はD115／Specification 82を維持し、本Decisionで再選択しない。

- `#[ScheduledBy]`はOperation入口であり、`#[Deferred]`を暗黙に追加しない。
- AttributeなしのExecution StrategyはInline、`#[Deferred]`付きはDeferredとする。
- `#[ConsoleCommand]`は独立した手動入口であり、必要なOperationだけが併用する。
- `availableAt`は一回のDeferred child dispatchを遅らせる値であり、定期Scheduleではない。
- Application Operation Schedulerを、Retention／Outbox Relay用の`MaintenanceScheduler`や`blackops scheduler:*`として再解釈しない。
- Scheduled Operationも通常のValidation、Authorization、Lifecycle Journal、Retry、Outcome、Retentionを迂回しない。

## Decision Drivers

- `#[ScheduledBy]`を付けるたびにApplication固有Factory実装を要求しない
- Calendar上の定刻と実際の受理時刻を分離し、Process停止やDST後も結果を説明できる
- 複数Process、再起動、同じManifestの再評価でも同じOccurrenceを二重受理しない
- Scheduled Root、Manual Console実行、Retry、Replayを異なるIdentityとして追跡できる
- CredentialやSecretをAttribute、OperationValue、Transport、Journalへ保存しない
- 既存のHTTP Actor-scoped IdempotencyをSchedule専用の重複排除へ無理に流用しない
- Productionでは一回実行CLIを外部Supervisor／Timerから安全に呼べる

## Question 1: AttributeとSchedule Identity

### Options

- A: 一つのOperationに一つの`#[ScheduledBy(name, cron, timezone)]`を許可する。`name`はApplication内で一意かつRenameに耐える明示Identity、`cron`は5 Field、`timezone`省略時はUTCとする
- B: Operation Type IDをSchedule Identityとして使い、`name`を要求しない。同じOperationへの複数Attributeを許可する
- C: Attributeは使わず、Application ConfigurationだけでOperation Class、Cron、Timezoneを登録する

### Recommendation

Aを推奨する。

Operation Class／Type IDだけでは、Renameや将来の複数ScheduleでOccurrence Identityが変わる。明示`name`ならDatabase上の一意制約、運用表示、Journalを同じ語で追跡できる。初期Scopeを一Operation一Scheduleに限定するとManifest、Value、Overlapを小さく保てる。複数時刻が必要なら別のScheduled Operationへ分け、同じApplication Serviceを呼び出す。

推奨するAuthoringは次である。

```php
use BlackOps\Core\Attribute\Deferred;
use BlackOps\Core\Attribute\ScheduledBy;
use BlackOps\Core\Operation;

#[ScheduledBy(
    name: 'reports.daily',
    cron: '0 0 * * *',
    timezone: 'Asia/Tokyo',
)]
#[Deferred]
final readonly class GenerateDailyReport implements Operation
{
}
```

[ANSWER]

A

[/ANSWER]

## Question 2: OperationValueとSchedule Context

### Options

- A: `#[ScheduledBy]`を付けるOperationのOperationValueは、必須Constructor引数を持たない型に限定する。Frameworkが既定値からValueを構築し、Schedule名とCalendar上の定刻は`ExecutionContext::schedule(): ?ScheduleContext`で提供する
- B: 全Scheduled OperationへApplication-owned `ScheduledOperationValueFactory`の実装と登録を必須にする
- C: `#[ScheduledBy]`へOperationValueの固定配列を記述し、FrameworkがAttribute値からBindingする

### Recommendation

Aを推奨する。

Schedule IdentityとCalendar上の定刻は業務入力ではなく実行Metadataであり、`ExecutionContext`へ置く方がHTTP／ConsoleのValue Contractを汚さない。定期OperationはSchedule ContextとDIされたApplication Serviceから必要な期間や対象を決定できる。Factoryを毎回要求せず、Attributeへ任意PayloadやCredentialを入れない。

`ScheduleContext`は少なくとも安定したSchedule名、Calendar上の定刻を表すUTC Instant、設定Timezoneを読み取り専用で公開する。HTTP／Console／child dispatchでは`null`である。必須入力を持つ既存Operationを定期実行したい場合は、必須引数なしの小さいScheduled OperationからApplication Serviceを呼ぶ。任意Payload Factoryは初期Scopeへ含めない。

[ANSWER]

A

[/ANSWER]

## Question 3: Cron、Timezone、DST

### Options

- A: 5 Field POSIX Cronを採用し、Day of MonthとDay of Weekの両方を制限した場合はORとする。秒、Nickname、Application独自DSLは初期Scope外とする。TimezoneはIANA名、既定UTCとする。存在しないLocal TimeはOccurrenceを作らず、DSTで重複するLocal Timeは最初のUTC Instantだけを採用する
- B: 秒を含む6 Field Cron、Nickname、Interval DSLまで最初から受理する。DST重複時は二回実行する
- C: Cronを使わず、固定Intervalだけを受理する

### Recommendation

Aを推奨する。

5 Field Cronは外部Timerと合わせやすく、秒単位実行による負荷と重複制御を避けられる。Timezone省略時をUTCに固定すればHost設定に依存しない。Calendar Scheduleとして同じLocal Date／Timeを二回実行しない方が日次処理の予想に合う。Cron Parser Packageは実装Task前にSupply Chain Reviewを行い、GrammarとDST FixtureをBlackOps側のContract Testで固定する。

[ANSWER]

A

[/ANSWER]

## Question 4: Misfire

### Options

- A: 永続Cursor以降に複数の未処理Occurrenceがある場合、最新の一件だけを受理する`FireOnce`を初期Contractとする。初回Deployは過去をBackfillせず、古いOccurrenceは`skipped_misfire`として残す
- B: Process停止中のOccurrenceをすべてSkipし、再開後の次回定刻まで何も実行しない
- C: 未処理OccurrenceをすべてCatch-upし、古い順に受理する

### Recommendation

Aを推奨する。

停止期間の業務を完全に失わず、長期停止後の大量Dispatchも避けられる。初回Deployで過去Scheduleを遡らず、再開時は最新のCalendar Slotだけを一回受理する。全件Catch-upはLoad、外部副作用、Retentionを増やすため、Bounded Catch-up PolicyとRate Limitを設計する後続Capabilityへ分ける。

[ANSWER]

A

[/ANSWER]

## Question 5: Overlap

### Options

- A: 同じScheduleの直前Occurrenceが非Terminalなら新しいOccurrenceを受理せず、`skipped_overlap`として永続化する。初期ContractはOverlap禁止に固定する
- B: 前回Stateに関係なく毎回受理し、Operation側へ競合制御を委ねる
- C: 新しいOccurrenceを一件だけ待機させ、前回Terminal後に必ず実行する

### Recommendation

Aを推奨する。

既存WorkerはSchedule単位の直列Queueを保証しないため、Cを表面的に提供すると実際にはOverlapし得る。Aなら同じScheduleの長時間実行、Retry、Dead Letter中に重複副作用を増やさず、Skip理由も運用者が確認できる。並列許可は明示PolicyとOperation側Idempotencyを設計してから追加する。

[ANSWER]

A

[/ANSWER]

## Question 6: Occurrence IdentityとIdempotency

### Options

- A: PostgreSQLへSchedule State／Occurrenceを保存し、`(schedule name, nominal scheduled_at UTC)`を一意にする。Occurrence作成時にOperation IDを一度だけ発行し、再評価、Process Crash、受理Retryでも同じOccurrence／Operation IDを使う
- B: Cron評価ごとに新しいOperation IDを発行し、既存HTTP Idempotency Storeへ内部Keyを渡して重複をまとめる
- C: Databaseへ状態を持たず、単一ProcessのMemory Lockだけで重複を防ぐ

### Recommendation

Aを推奨する。

Scheduleの重複排除はAuthenticated Actorを前提にするHTTP IdempotencyとはScopeもRetentionも異なる。専用Occurrence Rowを正本にすると、複数ProcessとCrash RecoveryをDatabase Transaction／Unique Constraintで扱え、Misfire／OverlapのSkipも監査できる。Handlerの外部副作用は引き続きat-least-onceを前提に冪等に設計し、Exactly Onceは保証しない。

Manual `#[ConsoleCommand]`実行は新しいRoot Operation IDを発行し、Scheduled Occurrence Identityを再利用しない。Worker Retryは同じScheduled Operation ID、明示Operation Replayは既存Contractどおり新しいOperation IDとCausationを使う。

[ANSWER]

A

[/ANSWER]

## Question 7: ActorとAuthorization

### Options

- A: execution Actorは固定のFramework system Actorとし、origin／authorization ActorはApplication-owned `ScheduledActorProvider`がSchedule Contextから解決する。`#[Authorize]`付きScheduled OperationはProvider登録をBuild／Bootstrapで必須にする
- B: `ConsoleActorProvider`をScheduled Runtimeでも共用する
- C: Scheduled Operationへ`#[Authorize]`を許可せず、常にActorなしで実行する

### Recommendation

Aを推奨する。

ConsoleとScheduleは異なるTrust Boundaryであり、Providerを共用すると手動OperatorとApplication Service Principalを混同する。AttributeへActor IDやCredentialを保存せず、Application Compositionで安定したActorRefだけを解決する。Authorizationは通常Operationと同じ時点で毎回評価し、ProviderがないAuthorized Operationを匿名でFallbackしない。

[ANSWER]

A

[/ANSWER]

## Question 8: BlackOps CLIとProcess Boundary

### Options

- A: Application Operation用に`blackops operation:schedule:run`を追加し、一回の評価／受理後に終了する。ProductionはCron、systemd timer、Kubernetes CronJob等から呼ぶ。Daemonは運用Evidence後の後続Capabilityとする
- B: `blackops schedule:run`と`blackops schedule:daemon`を最初から追加する
- C: 既存Maintenance用`blackops scheduler:run`／`scheduler:daemon`へ統合する

### Recommendation

Aを推奨する。

`operation:schedule:*`なら既存`MaintenanceScheduler`と名前でもRuntimeでも分離できる。一回実行CLIは二重起動をPostgreSQL Claimで安全にし、Supervisor固有の再起動、停止、時刻管理をFramework内Daemonへ持ち込まない。Daemonが必要になった場合も同じ一回評価Serviceを再利用できる。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

1. 一つのOperationへ一つの`#[ScheduledBy]`を許可し、Application内で一意な明示`name`、5 Field POSIX `cron`、IANA `timezone`を持たせる。Timezone省略時はUTCとする。
2. Scheduled OperationのOperationValueは必須Constructor引数を持たない。Schedule名、Calendar上の定刻を表すUTC Instant、設定TimezoneはOptional `ScheduleContext`として`ExecutionContext`へ追加する。
3. Day of MonthとDay of Weekの両方を制限したCronはPOSIX OR Semanticsとする。存在しないDST Local TimeはOccurrenceを作らず、重複Local Timeは最初のUTC Instantだけを採用する。
4. 初回Deployは過去をBackfillしない。永続Cursor以降に複数の未処理Occurrenceがある場合は最新一件だけを`FireOnce`で受理し、古いOccurrenceを`skipped_misfire`として記録する。
5. 同じScheduleの直前Occurrenceが非Terminalなら新しいOccurrenceを受理せず、`skipped_overlap`として記録する。初期ContractはOverlap禁止とする。
6. PostgreSQLへSchedule State／Occurrenceを保存し、`(schedule name, nominal scheduled_at UTC)`を一意にする。OccurrenceのOperation IDは一度だけ発行し、再評価、Crash Recovery、Acceptance Retry、Worker Retryで維持する。
7. execution ActorはFrameworkのScheduled Runtime system Actorとする。origin／authorization ActorはApplication-owned `ScheduledActorProvider`が解決し、`#[Authorize]`付きScheduled OperationではProvider登録を必須にする。
8. Application Operation用BlackOps CLIは一回実行の`operation:schedule:run`とする。既存Maintenance用`scheduler:run`／`scheduler:daemon`へ統合せず、Application Schedule Daemonは後続Capabilityへ分ける。
9. `#[ScheduledBy]`はExecution Strategyを暗黙に変更しない。Inlineは既定Dispatcher、`#[Deferred]`は既定Acceptance／Transport／Workerを使う。
10. Manual Console実行はScheduled Occurrenceを使わず新しいRoot Operation IDを発行する。明示Operation Replayは新しいOperation IDと元Operation Causationを使う。

[/DECISION]

## Cross-cutting Contract

次を実装上の不変条件とする。

- CompilerはSchedule名重複、Cron／Timezone不正、必須Value引数、Ephemeral OutcomeをBuild Errorにする。
- Schedule評価とOccurrence ClaimはPostgreSQL Transactionで行い、複数Processの同時評価を許容する。
- Schedule Cursor、Occurrence、Skip理由、Operation ID、Nominal／Evaluated／Accepted時刻を運用Query可能にする。
- `ScheduleContext`はTransportでWorkerへ伝播し、通常Lifecycle Journalの安全なContext ProjectionへSchedule名とNominal時刻を残す。
- OperationValue、Outcome、Journal、LogへCredential、Secret、Raw Provider Errorを含めない。
- InlineはSchedule Runtime内で通常Dispatcherを使い、Deferredは通常Acceptance／Transport／Workerを使う。どちらもValidation、Authorization、Journalを迂回しない。
- Occurrence作成後にValue構築、Validation、Authorization、Acceptanceが失敗した場合も、Schedule Stateへ安全なFailure Categoryを残し、同じOccurrenceを別Operationとして暗黙再発行しない。
- Public GuideはApplication ScheduleとMaintenance Schedulerを別のProcess／用途として説明する。

## Consequences

[CONSEQUENCES]

- 一般的な定期OperationはValue Factoryを追加せず、必須引数なしValueとSchedule Contextで実装できる。
- 必須入力を持つ既存HTTP／Console Operationへ直接`#[ScheduledBy]`を追加できない。小さいScheduled Operationから同じApplication Serviceを呼ぶ。
- Schedule Context追加によりExecutionContext、Transport Codec、Canonical／Observed Journal ProjectionのVersion更新が必要になる。
- Misfireは完全Backfillせず、OverlapはQueueしない。SkipされたOccurrenceも運用履歴として残る。
- PostgreSQLがSchedule Cursor、Occurrence Claim、重複排除の正本になる。Memory-only Runtimeは提供しない。
- Schedule重複排除とWorker RetryはOperation Handlerの外部副作用にExactly Onceを保証しない。
- Console OperatorとScheduled Service Principalは異なるActor Providerを使用する。
- Production Supervisorは`operation:schedule:run`の実行頻度、Timeout、Retry、Alertを所有する。
- Cron Parser DependencyはProduction導入前にSupply Chain Reviewを行い、BlackOpsのGrammar／DST Contract TestでVendor Behaviorを固定する。

[/CONSEQUENCES]

## Delivery Boundary

Decision後のSpecification／Task Packetは少なくとも次へ分割する。

1. Public Attribute、Schedule Context、Manifest Compiler Contract
2. PostgreSQL Schedule State／Occurrence、Migration、Claim／Misfire／Overlap
3. Inline／Deferred Invocation、Actor／Authorization、Journal
4. BlackOps CLI、Application Composition、Consumer／Crash／Concurrency Evidence
5. Guide、Reference、Deployment、Troubleshooting、Website Regression

Phase 21のFramework-owned Transaction Interception、Journal／Outcome Tenant Isolation、OpenTelemetry Adapterは本Decisionへ含めない。

## Traceability

- [D115 Deferred Authoring and Operation Dispatch](115-deferred-authoring-and-operation-dispatch.md)
- [Operation Dispatch and Deferred Authoring](../spec/82-operation-dispatch-and-deferred-authoring.md)
- [Scheduled Application Operation](../spec/98-scheduled-application-operation.md)
- [D027 Clock and Time](027-clock-and-time.md)
- [D037 Deferred Claim and Attempt](037-deferred-claim-and-attempt.md)
- [D038 Worker Crash Recovery](038-worker-crash-recovery.md)
- [D109 Idempotency and Outbox](109-phase-18-idempotency-and-outbox.md)
- [D126 Implicit Inline Ephemeral Outcome](126-implicit-inline-ephemeral-outcome.md)
