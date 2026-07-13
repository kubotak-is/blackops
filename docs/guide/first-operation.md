# 最初のOperationを完走する

このTutorialでは、Install直後のDeferred Report Featureを使い、Source確認、Build、HTTP受付、[Journal](glossary.md#journal)確認、Worker実行、[Outcome](glossary.md#outcome)取得までを一つの流れで進めます。DeferredはRequestをDurableに受け付けた後、別ProcessのWorkerがOperationを実行する[Execution Strategy](core-concepts.md#execution-strategy)です。

Stable `1.0.0`のQuickstartに含まれる機能だけを使います。`make:operation`やApplication Migration Runtimeは必要ありません。

Composer Install、`.env`、`var/build`、`var/log`の準備がまだなら、先に[Local Runtime](runtime-bootstrap.md)の準備手順を完了してください。

## 1. Operation Sourceを書く

`app/Feature/Report/GenerateReport/`へ次の4 Fileを作成し、それぞれの内容を保存します。Install直後のSkeletonには同じFileがすでに入っているため、その場合は内容が一致することを確認して次へ進んでください。以下のSourceはQuickstartと同じです。

### Operation: `GenerateReport.php`

```php
<?php

declare(strict_types=1);

namespace App\Feature\Report\GenerateReport;

use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;
use LogicException;

#[Route(method: 'POST', path: '/reports')]
#[OperationType('report.generate')]
#[ExecuteWith(Deferred::class)]
final readonly class GenerateReport implements Operation
{
    public function handle(GenerateReportValue $value, ExecutionContext $context): ReportGenerated
    {
        $attempt = $context->attempt();

        if ($attempt === null) {
            throw new LogicException('Report handler requires a deferred attempt.');
        }

        if ($attempt->number() === 1) {
            throw new ReportGenerationTemporarilyUnavailable('Report backend is temporarily unavailable.');
        }

        return new ReportGenerated($value->reportName, '/reports/generated/' . $value->reportName . '.json');
    }
}
```

### OperationValue: `GenerateReportValue.php`

```php
<?php

declare(strict_types=1);

namespace App\Feature\Report\GenerateReport;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;
use BlackOps\Core\OperationValue;
use SensitiveParameter;

final readonly class GenerateReportValue implements OperationValue
{
    public function __construct(
        public string $reportName,
        #[Sensitive(SensitiveMode::Mask)]
        #[SensitiveParameter]
        public string $apiToken,
    ) {}
}
```

`#[Sensitive]`はObserved Journalへ出す値をMaskします。認証、認可、暗号化、Access Control、Retentionの代わりにはなりません。責任分界は[SecurityとSensitive Data](security.md)を確認してください。

### Outcome: `ReportGenerated.php`

```php
<?php

declare(strict_types=1);

namespace App\Feature\Report\GenerateReport;

use BlackOps\Core\Outcome;

final readonly class ReportGenerated implements Outcome
{
    public function __construct(
        public string $reportName,
        public string $location,
    ) {}
}
```

### Retryable Exception: `ReportGenerationTemporarilyUnavailable.php`

```php
<?php

declare(strict_types=1);

namespace App\Feature\Report\GenerateReport;

use BlackOps\Core\Supervision\RetryableException;
use RuntimeException;

final class ReportGenerationTemporarilyUnavailable extends RuntimeException implements RetryableException {}
```

`handle()`は一回目の[Attempt](glossary.md#attempt)でRetryを要求し、二回目で`ReportGenerated`を返します。ValueとOutcomeはNative Signatureから推論されるため、標準形では`#[Accepts]`、`#[Returns]`、`OperationHandler`を追加しません。

## 2. ArtifactをBuildする

Project RootでOperation Manifest、HTTP Manifest、DI ContainerをCompileします。

```bash
docker compose run --rm app php bin/blackops blackops:build:compile
```

Commandは成功すると次を出力します。

```text
Build artifacts written.
```

Buildは`GenerateReportValue`と`ReportGenerated`の型、`ExecutionContext`の位置、Route、Deferred Strategyを検証します。失敗した場合は[Troubleshooting](troubleshooting.md#typed-self-handled-signature-error)を確認してください。

## 3. DatabaseとHTTP Runtimeを起動する

Migrationを明示的に適用してから、PostgreSQLとHTTPを起動します。

```bash
docker compose run --rm app php bin/blackops blackops:database:migrate
```

```text
Database migrations applied
migrations: 2
```

Application Migrationを追加している場合、`migrations`の件数は変わります。

```bash
docker compose up -d postgres http
```

```text
Container my-app-postgres-1 Healthy
Container my-app-http-1 Started
```

Container名はProject Directory名に応じて変わります。

## 4. HTTPからOperationを受け付ける

SecretをShell HistoryやTutorialへ直書きしないよう、環境変数からRequest JSONを作ります。

```bash
read -rsp 'Report API token: ' REPORT_API_TOKEN && printf '\n'
export REPORT_API_TOKEN
REQUEST_BODY=$(php -r 'echo json_encode(["reportName" => "weekly", "apiToken" => getenv("REPORT_API_TOKEN")], JSON_THROW_ON_ERROR);')
printf '%s\n' "$REQUEST_BODY"
```

入力JSONの形は次のとおりです。`apiToken`の表示は説明用Placeholderであり、実際の値は`REPORT_API_TOKEN`から入ります。

```json
{"reportName":"weekly","apiToken":"<REPORT_API_TOKENから入力>"}
```

Response BodyをFileへ保存し、HTTP Statusを表示します。

```bash
HTTP_STATUS=$(curl --silent --show-error --output var/report-response.json --write-out '%{http_code}' \
  -X POST -H 'Content-Type: application/json' \
  --data "$REQUEST_BODY" \
  http://127.0.0.1:8080/reports)
printf 'HTTP %s\n' "$HTTP_STATUS"
php -r 'echo json_encode(json_decode(file_get_contents("var/report-response.json"), true, 512, JSON_THROW_ON_ERROR), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;'
unset REPORT_API_TOKEN REQUEST_BODY
```

```text
HTTP 202
```

```json
{"status":"accepted","operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","acceptedAt":"2026-07-13T11:47:40.123456Z"}
```

`operationId`と`acceptedAt`は実行ごとに変わります。以降はResponseからOperation IDを読み取ります。

```bash
OPERATION_ID=$(php -r '$data = json_decode(file_get_contents("var/report-response.json"), true, 512, JSON_THROW_ON_ERROR); echo $data["operationId"];')
printf '%s\n' "$OPERATION_ID"
```

```text
019f32ab-2be0-7b38-a0a7-1ab2f9687697
```

このUUIDv7も実行ごとに変わります。

## 5. Mask済みJournalを確認する

`operation.received` Recordを同じOperation IDで検索します。

```bash
grep "$OPERATION_ID" var/log/journal.jsonl | grep '"event":"operation.received"' | head -n 1
```

```jsonl
{"schemaVersion":1,"kind":"journal","event":"operation.received","occurredAt":"2026-07-13T11:47:40.123456Z","sequence":1,"operation":{"id":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","type":"report.generate","schemaVersion":1,"strategy":"deferred","correlationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","causationId":null},"attempt":null,"data":{"value":{"reportName":"weekly","apiToken":"[masked]"}}}
```

この一行は実際のJSONL Shapeです。`occurredAt`、Operation ID、Correlation IDは実行ごとに変わります。`apiToken`は`[masked]`であり、入力した値はObserved `journal.jsonl`へ残りません。Canonical Storeの暗号化や閲覧権限は別途Application／運用側で構成します。

## 6. Workerで完了させる

一回目のWorker実行はRetryをScheduleします。

```bash
docker compose run --rm app php bin/blackops blackops:worker:run --iterations=1 --idle-sleep-milliseconds=1
```

```text
Worker stopped. Processed claims: 0
```

QuickstartのRetry Delay後にもう一度実行します。

```bash
sleep 2
docker compose run --rm app php bin/blackops blackops:worker:run --iterations=1 --idle-sleep-milliseconds=1
```

```text
Worker stopped. Processed claims: 1
```

`Processed claims`は正常完了したClaim数です。一回目のRetry Scheduledは成功件数へ含まれません。常駐運用ではProcess ManagerまたはComposeのWorker Profileを使います。

## 7. 同じOperation IDでOutcomeを取得する

現行RuntimeはOutcome用HTTP endpointやCLI Commandを提供しません。ApplicationがController、CLI Command等の入口を実装し、Public `OutcomeReader`へResponseのOperation IDを渡します。Persistence PayloadやPostgreSQL TableをApplication Codeで直接Decodeしないでください。

次はApplication ServiceのInputです。`$operationId`には上で取得した同じ文字列を渡します。

```php
use App\Feature\Report\GenerateReport\ReportGenerated;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Outcome\OutcomeReader;
use RuntimeException;

function reportOutcome(OutcomeReader $outcomes, string $operationId): array
{
    $record = $outcomes->find(OperationId::fromString($operationId));

    if ($record === null) {
        return ['status' => 'pending_or_unavailable', 'operationId' => $operationId];
    }

    $outcome = $record->outcome();

    if (!$outcome instanceof ReportGenerated) {
        throw new RuntimeException('The stored outcome type does not match report.generate.');
    }

    return [
        'status' => 'completed',
        'operationId' => $record->operationId()->toString(),
        'completedAt' => $record->completedAt()->format('Y-m-d\\TH:i:s.u\\Z'),
        'outcome' => [
            'reportName' => $outcome->reportName,
            'location' => $outcome->location,
        ],
    ];
}
```

Applicationの入口がこの配列をJSON化すると、Outputは次の形になります。

```json
{"status":"completed","operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","completedAt":"2026-07-13T11:47:43.456789Z","outcome":{"reportName":"weekly","location":"/reports/generated/weekly.json"}}
```

`operationId`と`completedAt`は実行ごとに変わります。`find()`が`null`を返すだけでは、Pending、未知のID、失敗、保持期限切れを区別できません。区別するApplication Status Viewの設計は[Outcome Retrieval](outcome-retrieval.md)と[Troubleshooting](troubleshooting.md#outcome-status)を確認してください。

作業後はRuntimeを停止します。

```bash
docker compose down
```

```text
Container my-app-http-1 Removed
Container my-app-postgres-1 Removed
```

Container名はProject Directory名に応じて変わります。次は[Operation Authoring](operations.md)で独自Use Caseの書き方を確認してください。
