# Scheduled Operation

Scheduled Application Operationは、Repository `main`で提供するExperimental Capabilityです。Stable `1.1.0`には含まれません。[Releases](mvp-status.md)でStableと`main`の差を確認してから、専用のProject Rootで試してください。

:::warning[Experimental capability]
この機能は一回実行の`operation:schedule:run`を外部Supervisorから呼び出す前提です。Frameworkは常駐Daemon、Cron／systemd／KubernetesのManifest生成、外部副作用のExactly Onceを提供しません。
:::

## 何を作るか

一つのOperationへ一つの`#[ScheduledBy]`を付け、Schedule名、5 Field POSIX Cron、IANA Timezoneを宣言します。`#[ScheduledBy]`は入口Metadataであり、Execution Strategyを変更しません。

- InlineはAttributeを追加せず、通常のInline Dispatcherで同じProcess内に完了します。
- Deferredは`#[Deferred]`を追加し、受付後に通常のTransportとWorkerで完了します。
- Schedule専用のValue Factoryはありません。OperationValueは必須Constructor引数なしで構築でき、定刻は`ExecutionContext::schedule()`から読みます。
- 手動の`#[ConsoleCommand]`実行はScheduled Occurrenceを使わず、新しいRoot Operationとして扱います。

## Canonical Authoring

次の例はRepository `main`のCanonical Authoringです。`#[Deferred]`を外すとInlineになります。

```php
<?php

declare(strict_types=1);

namespace App\Feature\Report\GenerateDailyReport;

use BlackOps\Core\Attribute\Deferred;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\ScheduledBy;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;

#[OperationType('report.generate_daily')]
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
    ): DailyReportGenerated {
        $schedule = $context->schedule();
        // Application Serviceへ$schedule?->scheduledAt()を渡す。
        return new DailyReportGenerated();
    }
}

final readonly class GenerateDailyReportValue implements OperationValue {}

final readonly class DailyReportGenerated implements Outcome {}
```

Schedule名は`^[a-z0-9]+(?:\.[a-z0-9]+)*$`でApplication内に一意でなければなりません。Cronは5 Fieldだけを使います。

| Field | 範囲 | 例 |
| --- | --- | --- |
| Minute | `0-59` | `0` |
| Hour | `0-23` | `0` |
| Day of month | `1-31` | `*` |
| Month | `1-12` | `*` |
| Day of week | `0-7`（`0`と`7`は日曜） | `*` |

`*`、数値、Comma List、Inclusive Range、Stepを組み合わせられます。秒Field、`@daily`などのNickname、Month／Weekday Name、`L`／`W`／`#`は使えません。Day of monthとDay of weekを両方制限した場合はPOSIXのORです。Timezoneを省略するとUTCになります。HostのTimezone設定には依存しません。

必須Constructor引数のあるValue、Ephemeral Outcome、重複Schedule名、不正Cron／Timezoneは`build:compile`で拒否されます。任意Payload、Credential、Actor ID、定刻をAttributeやValueへ保存しません。

## InlineとDeferred

`ScheduledBy`とStrategyは独立しています。

| Authoring | `operation:schedule:run`の動作 | 完了確認 |
| --- | --- | --- |
| `#[ScheduledBy]`だけ | Value構築、Validation、Authorization、Journal、Inline Handlerを同じProcessで実行 | Occurrenceが`completed`、JournalがTerminal Event |
| `#[ScheduledBy]`＋`#[Deferred]` | ValueをEncodeし、通常のDeferred Acceptance／Transportへ受理 | Occurrenceが`accepted`、`worker:run`後に`completed` |

どちらも通常Lifecycleを迂回しません。Deferredはat-least-onceであり、外部副作用のExactly Onceは保証されないため、Application Handlerを冪等に設計します。

## Schedule Context

`ExecutionContext::schedule()`はScheduled Rootでだけ非`null`です。`ScheduleContext`は次の読み取り専用値を持ち、`scheduledAt()`はUTCへ正規化されます。

```php
$schedule = $context->schedule();

if ($schedule !== null) {
    $name = $schedule->name();
    $scheduledAtUtc = $schedule->scheduledAt();
    $timezone = $schedule->timezone();
}
```

HTTP、通常のConsoleCommand、Application child dispatchでは`null`です。実際の評価時刻、受理時刻、Raw Cron Parser State、CredentialはContextに入りません。Deferred TransportとWorker Retryでは同じSchedule Contextが維持されます。

## Authorized ScheduleのProvider

`#[Authorize]`をScheduled Operationへ付ける場合だけ、Application-owned `ScheduledActorProvider`をService Providerへ登録します。Console用のProviderは共用しません。ProviderはSchedule ContextからActorを解決し、CredentialやSecretを返しません。

```php
<?php

declare(strict_types=1);

namespace App\Security;

use BlackOps\Core\ActorRef;
use BlackOps\Core\ScheduleContext;
use BlackOps\Scheduling\ScheduledActorProvider;

final readonly class ReportScheduleActorProvider implements ScheduledActorProvider
{
    public function actor(ScheduleContext $context): ?ActorRef
    {
        return new ActorRef('report-service', 'service');
    }
}
```

Service ProviderでBindingします。

```php
<?php

declare(strict_types=1);

namespace App;

use App\Security\ReportScheduleActorProvider;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Scheduling\ScheduledActorProvider;

final readonly class ApplicationServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(ScheduledActorProvider::class, ReportScheduleActorProvider::class);
    }
}
```

`ApplicationServiceProvider`を`config/app.php`の`services`へ登録してからBuildします。既存のService登録があれば同じ配列へ追加し、登録だけでなくProvider実装と同じApplication Rootへ配置してください。

```php
// config/app.php
return [
    'services' => [
        App\ApplicationServiceProvider::class,
    ],
];
```

Authorized ScheduleがあるのにProviderがない、または登録Typeが不正ならBuild／Bootstrapが安全なConfiguration Errorで停止します。Providerが明示的に`null`を返す場合は匿名Fallbackせず、通常Authorization境界で拒否されます。AuthorizationなしのScheduleだけならProvider登録は不要です。

## Migration、Build、初回実行

Project Rootで次の順序を守ります。MigrationとBuildは暗黙に実行されません。

```bash
php blackops database:migrate
php blackops build:compile
php blackops operation:schedule:run --json
```

`build:compile`はOperation Manifest、HTTP／Frontend Artifact、Compiled Containerを同じApplication Build IDで生成します。RuntimeはSource DiscoveryへFallbackしません。Databaseは`blackops` Schema（またはApplication設定のFramework Schema）へSchedule State／Occurrenceを作成します。

初回評価は過去をBackfillせず、現在のCalendar Minuteだけを候補にします。次回以降はCursorより後を評価し、複数Slotが溜まっている場合は最新一件だけを実行候補にします。

Daily例の`0 0 * * *`はAsia/Tokyoの毎日0:00だけを対象にするため、任意時刻に初回実行すると`accepted: 0`（No Schedule）になるのが正常です。上のJSON／Human例の`accepted: 2`はCountのShapeを示すサンプルで、固定された期待値ではありません。初回の受理を確実に検証する場合は、検証用OperationだけCronを`* * * * *`へ変更し、`build:compile`後に同じCalendar Minute内でone-shot Commandを実行します。確認後は実運用Cronへ戻し、再度`build:compile`してArtifactを更新してください。

## CLIの結果とExit Code

`operation:schedule:run`は一回評価して終了します。Cron、systemd timer、Kubernetes CronJobなどの外部Supervisorが頻度、Timeout、Restart、Alertを所有します。

成功時のJSONは固定Shapeです。

```json
{"schemaVersion":1,"status":"ok","evaluated":2,"accepted":2,"skipped_misfire":0,"skipped_overlap":0,"failed":0}
```

Human出力は次のCountを同じ順で表示します。

```text
Scheduled operation run completed.
evaluated: 2
accepted: 2
skipped_misfire: 0
skipped_overlap: 0
failed: 0
```

| Exit | 条件 | 出力 |
| --- | --- | --- |
| `0` | No Schedule、または`failed: 0` | Human CountまたはJSON `status: "ok"` |
| `1` | Occurrence単位のEvaluation、Validation、Authorization、Invocation、Acceptance Failureを集計 | Human CountまたはJSON `status: "failed"`。Runner／Bootstrap境界のTop-level Runtime ErrorはJSON `code: "runtime_error"`（Humanは安全な失敗表示） |
| `2` | Unknown Option、Manifest／Build ID／Providerなど安全な入力・設定Error | `configuration_error`。Raw Exception、SQL、Value、Credentialは出さない |

Unknown Optionでも`--json`が指定されていれば、入力Bind前にJSON Error Shapeへ縮約されます。

Occurrence単位のFailureはCountsへ集計されて`status: "failed"`になります。Runner／BootstrapでCommand自体を構成できないTop-level Runtime Errorは、`--json`なら`{"schemaVersion":1,"status":"failed","code":"runtime_error"}`として返り、Occurrence Countとは別に扱います。

## Misfire、Overlap、Crash Recovery

Schedule StateのCursorとPostgreSQL Occurrenceが正本です。`(schedule_name, scheduled_at UTC)`は一意で、Claim時に発行したOperation IDを再評価、Crash Recovery、Acceptance Retry、Worker Retryで維持します。

- 古いSlotは`skipped_misfire`として残り、Operation IDはありません。
- 直前Occurrenceが非Terminal（Claim中、Accepted、Retry中など）なら新しいSlotは`skipped_overlap`となり、Operation IDはありません。
- Completed、Rejected、Failed、Dead LetteredはTerminalです。
- Claim後、Invocation前にProcessが停止しても、次の一回実行が同じOccurrence／Operation IDを先に再開します。
- 複数Processが同時に起動しても、PostgreSQL Transaction、Unique Constraint、Schedule Advisory Lockで一つのSlotへ収束します。

FrameworkはHandlerの外部副作用をExactly Onceにしません。同じOperation IDで再試行できるよう、外部APIやDB Mutationの重複耐性をApplicationで設計します。

## OccurrenceとJournalを安全に確認する

Occurrenceの診断では、Canonical Value、Outcome、Credentialを取得しません。Read-onlyの運用Queryでは、Schedule名、UTC Slot、評価時刻、State、Safe Category、Operation ID、Accepted時刻だけを読みます。

QueryはProject RootからFramework SchemaへRead-only接続して実行します。SkeletonのDocker環境では`docker compose exec -T postgres psql -U blackops -d blackops`を起動し、別環境ではApplicationのConnection／Schemaへ接続するRead-only PostgreSQL Clientを使います。`blackops` Schemaは設定に合わせて置き換え、CredentialをCommandへ直書きしません。

```sql
SELECT schedule_name,
       scheduled_at,
       evaluated_at,
       state,
       category,
       operation_id::text,
       accepted_at
FROM blackops.schedule_occurrences
WHERE schedule_name = 'reports.daily'
ORDER BY scheduled_at DESC
LIMIT 20;
```

Skip StateのOperation IDは`NULL`です。実行候補だけが固定Operation IDを持ちます。Operation IDを得たら、[BlackOps CLI](project-cli.md)の`operation:inspect`でSafe Statusを確認し、Canonical JournalのEvent列と同じIDで相関します。

Schedule Contextを持つJournalの安全なOperation Projectionは次の形です。

```json
{
  "operation": {
    "id": "019f0000-0000-7000-8000-000000000001",
    "type": "report.generate_daily",
    "strategy": "deferred",
    "schedule": {
      "name": "reports.daily",
      "scheduled_at": "2026-07-22T15:00:00.000000Z",
      "timezone": "Asia/Tokyo"
    }
  },
  "event": "operation.accepted"
}
```

JournalのSchedule ProjectionはSchedule名、Calendar上の定刻、Timezoneだけを持ちます。Actor ID、Credential、Raw Value、Cron Parser Stateは保存・表示しません。Deferredの代表的なEvent列は`operation.received` → `operation.accepted` → `attempt.started` → Terminal Eventです。Inlineは`operation.received` → `attempt.started` → Terminal Eventです。

## DSTと外部Supervisor

Calendar評価はAttributeのIANA Timezoneで行い、永続化と比較はUTC Instantです。DST開始で存在しないLocal TimeはOccurrenceを作らず、DST終了で二度現れるLocal Timeは最初のUTC Instantだけを候補にします。

外部Supervisorはone-shot Commandを起動します。例としてsystemd timerやKubernetes CronJobをApplication側で定義できますが、FrameworkがManifestを生成したり、常駐Processを起動したりはしません。

```text
External Supervisor
    -> php blackops operation:schedule:run --json
        -> Inline completion or Deferred acceptance
            -> php blackops worker:run (Deferred only)
```

`php blackops scheduler:run`／`scheduler:daemon`はRetention等のFramework Maintenance専用です。Application Scheduleを起動せず、`operation:schedule:run`とは別Processとして監督します。

## Failure対応

1. `2`なら`build:compile`、Application Build ID、Manifest、Provider登録、Optionを確認します。Schedule Stateは設定Errorで変更されません。
2. `1`ならJSON Countの`failed`とSafe Categoryを記録し、対象OccurrenceをRead-onlyで確認します。
3. `claimed`が残っていれば次のone-shot実行でRecoveryを先に行います。同じOperation IDから別IDへ置き換えません。
4. Deferredが`accepted`のままなら、同じDatabase／Schema／Build ArtifactのWorkerを有限Loopで実行します。
5. JournalとOutcomeを確認するときもRaw Payload、Credential、Provider Errorを公開Logへコピーしません。

既存のHTTP／Deferred／Journalの一般的な境界は[Inline and Deferred](execution.md)、[Execution Context](execution-context.md)、[Journal](journal.md)、[Deployment](deployment.md)、[Troubleshooting](troubleshooting.md)を参照してください。
