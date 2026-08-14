# ConsoleCommand

Console CommandはTerminalからOperationを起動するApplication入口です。`#[ConsoleCommand]`はStable `1.1.0`にはないRepository `main`のExperimental Adapterです。[Stableとmain](mvp-status.md#stableとmain)を確認し、Applicationが選んだConsole Actor、Secret配布、Process Supervisorを準備してください。

## 実行手順

### 1. AttributeとValueを定義する

OperationへCanonicalなCommand名（空白や`|`を含まない`segment:command`）と説明を付けます。public constructor-promotedな`string`、`int`、`float`、`bool`のValue PropertyがLong Optionになります。DefaultがあるOptionだけ省略できます。

```php
use BlackOps\Core\Attribute\ConsoleCommand;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;

#[ConsoleCommand('report:export', 'Export a report.')]
#[OperationType('report.export')]
final readonly class ExportReport implements Operation
{
    public function handle(ExportReportValue $value): ReportExported
    {
        return new ReportExported($value->reportName);
    }
}

final readonly class ExportReportValue implements OperationValue
{
    public function __construct(public string $reportName) {}
}

final readonly class ReportExported implements Outcome
{
    public function __construct(public string $reportName) {}
}
```

`#[Sensitive]`を含むValue、Outcome、配列／Object／Enum入力、位置引数はConsole Contractで受け付けません。Raw Credential、Canonical Payload、Throwableをstdoutへ追加しないでください。

### 2. BuildとHelpを確認する

Project RootでManifestとContainerを更新し、公開Optionを実装から確認します。

```bash
php blackops build:compile
php blackops help report:export
```

`help`はCommand ManifestのMetadataを表示します。Handler、Database、Actor Providerを実行しないため、実行前の安全な探索に使えます。Build失敗時は[Build Artifact不在／Build ID不一致](troubleshooting.md#build-artifact不在build-id不一致)を参照します。

### 3. Human／JSONで実行する

```bash
php blackops report:export --report-name=weekly
php blackops report:export --report-name=weekly --json
```

Human成功は次のように表示されます。

```text
Completed.
```

`--json`は一行のVersioned JSONをstdoutへ出します。

```json
{"schemaVersion":1,"status":"completed","outcome":{"reportName":"weekly"}}
```

Deferred OperationならHumanは`Accepted operation <operation-id>.`、JSONは`status: "accepted"`と`operationId`を返します。受付後は[Inline and Deferred](execution.md)のStatus／Outcome手順でWorker完了を確認します。

## Exit／Failure Contract

| 結果 | Human／JSONの安全な分類 | Exit |
| --- | --- | ---: |
| Inline Completed／Deferred Accepted | `completed`／`accepted` | `0` |
| Binding／Value Validation | `rejected`、`category: validation`、Violation | `2` |
| 業務Rejected／Internal Error | `rejected`または`internal_error` | `1` |

ValidationはOperation IDを伴う場合があります。Rejectedの`code`とViolationの`field`／`rule`／`code`だけを調査キーにし、Exception Message、SQL、Credentialを公開Outputへ出しません。Unknown Optionや型不一致もBinding／Validationとして扱い、Exit `2`を返します。

## AuthorizationとActor

Console Runtimeは、Applicationが`ConsoleActorProvider`をBindingしていればそのActorをCurrent／Origin Actorへ設定し、未Bindingなら`null` Actorと`console-runtime`の入口識別子を使います。Operationの`#[Authorize]` PolicyはこのContextを評価し、Denyは業務Rejected（Exit `1`）として返ります。OS User、Scheduler、Secret配布、Role／Permission検索はApplication／運用責務です。

ConsoleからEphemeral Outcome Operationを実行するContractはありません。Commandを追加したら`build:compile`、`help`、Human／JSON、Validation、Authorization、DeferredならWorker／Statusまでを同じTaskで確認してください。詳細なCommand一覧は[Operation Command](project-cli.md#operation-command)、失敗時は[Troubleshooting](troubleshooting.md)を参照します。
