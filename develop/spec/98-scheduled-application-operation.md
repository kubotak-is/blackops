# Scheduled Application Operation

## Scope

`#[ScheduledBy]`をHTTP `#[Route]`、`#[ConsoleCommand]`と並ぶApplication Operationの入口として提供する。

Scheduled Application Operationは、Retention／Outbox Relay等を実行するFramework Maintenance Schedulerとは別Capabilityである。既存`blackops scheduler:run`／`scheduler:daemon`の意味を変更しない。

## Authoring

Canonical Authoringは次とする。

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
    public function handle(
        GenerateDailyReportValue $value,
        ExecutionContext $context,
    ): ReportGenerated {
        // ...
    }
}
```

`ScheduledBy`はClass Target、非Repeatableとし、次の値を持つ。

| Field | Contract |
| --- | --- |
| `name` | Application内で一意なSchedule Identity。`^[a-z0-9]+(?:\.[a-z0-9]+)*$` |
| `cron` | 5 Field POSIX Cron |
| `timezone` | IANA Timezone。省略時`UTC` |

一つのOperationへ一つのScheduleだけを許可する。同じOperationを複数Scheduleで起動したい場合は、Scheduleごとに小さいOperationを定義し、同じApplication Serviceを呼び出す。

`ScheduledBy`はExecution Strategyを変更しない。AttributeなしはInline、`#[Deferred]`付きはDeferredである。`#[ConsoleCommand]`を併用した手動実行はScheduled Occurrenceを使わない。

Ephemeral OutcomeはScheduled Entryを持てない。

## Cron Contract

Cronは空白区切りの5 Fieldとする。

| Field | Range |
| --- | --- |
| Minute | `0-59` |
| Hour | `0-23` |
| Day of Month | `1-31` |
| Month | `1-12` |
| Day of Week | `0-7`。`0`と`7`はSunday |

各Fieldは`*`、数値、Comma List、Inclusive Range、Stepを組み合わせられる。Stepは正の整数とし、Rangeは逆転できない。Month／Weekday Name、秒Field、`@daily`等のNickname、`L`／`W`／`#`等の拡張記法は受理しない。

Day of MonthとDay of Weekの両方が`*`以外ならORで一致させる。

CompilerはCronのField数、文字、範囲、Range、Stepを検証し、不正なOperationをBuild Errorにする。EvaluatorとCompilerは同じParser Contractを使う。

## Timezone and DST

- TimezoneはHost Defaultを使わず、AttributeのIANA Timezoneを使う。
- Attribute省略時はUTCとする。
- Calendar評価は指定Timezone、永続化と比較はUTC Instantを正本とする。
- DST開始で存在しないLocal Date／TimeはOccurrenceを作らない。
- DST終了で同じLocal Date／Timeが二回現れる場合は、最初のUTC InstantだけをOccurrence候補にする。
- Schedule Contextの`scheduledAt`はUTCに正規化する。

## OperationValue

Scheduled OperationのOperationValueは、必須Constructor引数を持ってはならない。ClassはInstantiableで、Frameworkが引数なしで構築できなければならない。既定値付き引数は許可する。

FrameworkはSchedule評価時にValueを構築し、通常のValue Validationを行う。任意Payload、Credential、Actor、Schedule時刻をAttributeやValueへ注入しない。

必須入力を持つ既存HTTP／Console Operationを定期実行したい場合は、必須引数なしValueを持つScheduled Operationから同じApplication Serviceを呼ぶ。Scheduled Value Factoryは初期Scope外である。

## Schedule Context

`BlackOps\Core\ScheduleContext`を不変Public Value Objectとして提供する。

```php
final readonly class ScheduleContext
{
    public function name(): string;

    public function scheduledAt(): DateTimeImmutable;

    public function timezone(): string;
}
```

`ExecutionContext`へ次を追加する。

```php
public function schedule(): ?ScheduleContext;
```

HTTP、Console、Application child dispatchでは`null`である。Scheduled Rootでは非nullで、Transport、Worker Retry、Journalへ同じ値を伝播する。公開`with...()` Methodは追加しない。

Schedule ContextはSchedule名、Calendar上の定刻、Timezoneだけを持つ。実際の評価時刻、受理時刻、Actor、Credential、Raw Cron Parser Stateを含めない。

## Compiled Metadata and Manifest

`OperationMetadata`はOptional `OperationScheduleMetadata`を持つ。

```php
final readonly class OperationScheduleMetadata
{
    public function __construct(
        public string $name,
        public string $cron,
        public string $timezone,
    );
}
```

`OperationRegistry`はSchedule名の一意性をBuild時に検証し、Schedule名からMetadataを取得できる。ScheduleがないOperationのMetadata Shapeと既存検索は維持する。

Operation ManifestはOptional `schedule` Objectを保存する。

```text
schedule:
  name: reports.daily
  cron: 0 0 * * *
  timezone: Asia/Tokyo
```

Manifest Schema Versionを`3`へ更新する。旧Schemaを暗黙変換せず、Application Build Artifactの再生成を要求する。DecoderはSchedule Shape、Cron、Timezoneを再検証し、Malformed Artifactを拒否する。

HTTP Manifest、Console Manifest、Frontend ContractはScheduleを入口として公開しない。既存Route／Console／Frontend Shapeを変更しない。

## Persistent Schedule State

PostgreSQLをSchedule StateとOccurrenceの正本とする。

最低限、次を永続化する。

### Schedule State

- Schedule name
- Operation Type ID
- 最後に評価を完了したUTC Cursor
- Created／Updated timestamp

### Occurrence

- Schedule name
- Calendar上の`scheduled_at` UTC
- Evaluation timestamp
- State
- Skip／Failure Category
- Operation ID。実行候補だけが持ち、Skipには持たない
- Accepted timestamp

`(schedule name, scheduled_at UTC)`を一意にする。複数Evaluator、Process再起動、同じManifestの再評価は同じOccurrenceへ収束する。

Schedule名を別Operation Typeへ付け替えたManifestは、未完了Occurrenceの誤実行を防ぐためRuntime開始前に拒否する。

## Evaluation Cursor and Misfire

一回の評価では`now`をUTC Minute境界へ切り下げる。

- Schedule Stateがない初回評価は、現在のCalendar Minuteだけを候補にし、それ以前をBackfillしない。
- 次回以降は直前Cursorより後、現在Minute以下のCalendar Slotを列挙する。
- 未処理Slotが複数ある場合、最新一件だけを実行候補にする。
- 古いSlotは`skipped_misfire`として永続化する。
- Occurrence作成とCursor前進を同じPostgreSQL Transactionで行う。
- Transaction失敗時はCursorだけを前進させない。

全件Catch-up、任意Misfire Policy、Rate Limitは初期Scope外である。

## Overlap

同じScheduleの直前実行Occurrenceが非Terminalなら、新しい実行候補を受理しない。`skipped_overlap`として永続化する。

Running、Retry Scheduled、Claim中、Acceptance Recovery中は非Terminalとして扱う。Completed、Rejected、Failed、Dead LetteredはTerminalとして扱う。

初期ContractはOverlap禁止に固定し、並列許可やQueue-one Optionを公開しない。

## Occurrence and Operation Identity

| Action | Occurrence | Operation ID |
| --- | --- | --- |
| 初回の実行候補Claim | 新規 | 一度だけ発行して固定 |
| 同じSlotの再評価 | 同一 | 同一 |
| Acceptance Crash Recovery | 同一 | 同一 |
| Worker Retry | 同一 | 同一 |
| Misfire／Overlap Skip | 記録する | 発行しない |
| Console手動実行 | 使用しない | 新規Root |
| Operation Replay | 元Occurrenceを再利用しない | 新規、元OperationをCausationにする |

Schedule専用Occurrence Identityを、`Operation Type ID + authenticated Actor + Idempotency Key`でScopeされるHTTP Idempotency Storeへ流用しない。

FrameworkはHandlerの外部副作用へExactly Onceを保証しない。Deferred実行はat-least-onceであり、Handlerは通常どおり冪等に設計する。

## Actor and Authorization

Scheduled Runtimeは次のActor Contextを作る。

| Actor | Source |
| --- | --- |
| execution | Framework固定のScheduled Runtime system Actor |
| origin | Application-owned `ScheduledActorProvider` |
| authorization | Application-owned `ScheduledActorProvider` |

`ScheduledActorProvider`はSchedule Contextから`?ActorRef`を解決する。CredentialやSecretを返さず、Attribute、Value、Transport、JournalへCredentialを保存しない。

`#[Authorize]`付きScheduled OperationはProvider登録をBuild／Bootstrapで必須にする。Providerなし、Invalid Type、Resolution Failureを匿名ActorへFallbackしない。Authorizationは通常Operationと同じ境界で毎回評価する。

Console用`ConsoleActorProvider`は共用しない。

## Invocation and Lifecycle

Inline Scheduled Operationは通常Inline Dispatcher、Deferred Scheduled Operationは通常Deferred Acceptance／Transport／Workerを使う。

どちらも次を迂回しない。

- OperationValue構築とValidation
- Authorization
- Operation ID／Correlation ID
- Lifecycle Journal
- Transaction
- Retry／Dead Letter
- Outcome／Retention

Schedule ContextはDeferred PayloadへVersion付きで保存し、Worker Retryで維持する。Journalの安全なContext ProjectionへSchedule名、Calendar上の定刻、Timezoneを追加する。

Occurrence Claim後のValue構築、Validation、Authorization、Acceptance Failureは安全なCategoryでOccurrenceへ記録する。同じOccurrenceを別Operation IDで暗黙再発行しない。

## BlackOps CLI

Application Operation用に一回実行Commandを提供する。

```bash
php blackops operation:schedule:run
```

Commandは現在時刻までのScheduleを一回評価し、OccurrenceをClaimし、Inline実行またはDeferred受理を行って終了する。Cron、systemd timer、Kubernetes CronJob等の外部Supervisorが実行頻度、Timeout、Process Retry、Alertを所有する。

Human出力と`--json`は最低限、evaluated、accepted、skipped misfire、skipped overlap、failedの件数を返す。成功はExit Code `0`、安全な入力／設定Errorは`2`、Runtime Failureは`1`とする。Credential、Raw Value、Raw Errorを出力しない。

Application Schedule Daemonは初期Scope外である。

## Security and Observability

- Schedule Attribute／ManifestへCredential、Secret、Actor ID、任意Payloadを保存しない。
- Cron／Timezone／Schedule名のInvalid ValueはBuild時にSafe Errorへ縮約する。
- LogはSchedule名、Operation Type ID、Operation ID、Safe Categoryを相関できる。
- Journalは通常Lifecycleを正本とし、Schedule Contextを安全な追加Metadataとして扱う。
- Schedule State／Occurrenceの運用QueryはCanonical OperationValue、Outcome、Credentialを返さない。
- Schedule State／OccurrenceのRetentionはProduction Runtime実装前に既存Operation／Journal Retentionとの順序を固定する。

## Delivery Plan

1. P20-014A: Public `ScheduledBy`、Schedule Context、Metadata／Manifest Compiler
2. P20-014B: PostgreSQL Schedule State／Occurrence、Cron Evaluator、Misfire／Overlap
3. P20-014C: Inline／Deferred Invocation、Actor／Authorization、Transport／Journal
4. P20-014D: `operation:schedule:run`、Application Composition、Crash／Concurrency Consumer Evidence
5. P20-014E: Guide、Reference、Deployment、Troubleshooting、Documentation Review

Cron Evaluatorへ外部Packageを導入する場合は、Install前にSupply Chain Reviewを行い、Grammar／DST BehaviorをBlackOps Contract Testで固定する。

## Acceptance

- `ScheduledBy`とExecution Strategyが独立している
- Schedule名、Cron、Timezone、Value ShapeをBuild時に検証する
- DST、Misfire、Overlapを決定的なClock Fixtureで検証する
- 複数ProcessとCrash Recoveryが同じOccurrence／Operation IDへ収束する
- Inline／Deferred双方が通常Lifecycleを通る
- Authorized Scheduleは明示Actor Providerを要求する
- Existing Maintenance Scheduler、Console、HTTP、Frontend Contractを変更しない
- CLI、Consumer、GuideがApplication ScheduleとMaintenance Schedulerを区別する

## Traceability

- [D134 Scheduled Application Operation](../decisions/134-scheduled-application-operation.md)
- [Operation Dispatch and Deferred Authoring](82-operation-dispatch-and-deferred-authoring.md)
- [Clock and Time](21-clock-and-time.md)
- [Deferred Claim and Attempt](31-deferred-claim-and-attempt.md)
- [Worker Crash Recovery](32-worker-crash-recovery.md)
- [Reliability and Delivery](80-reliability-and-delivery.md)
