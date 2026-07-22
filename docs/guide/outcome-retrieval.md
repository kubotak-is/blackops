# Outcomeを取得する

正常完了したDeferred Operationは、Operation IDごとに型付き[Outcome](glossary.md#outcome)を保存します。Browserや外部ConsumerはPublic Status Resource、Generated Clientは`.status()`／`.wait()`を主経路にします。PHP AdapterからOutcomeだけを読む場合はPublic `OutcomeReader` Contractを使います。Persistence Payloadを独自にDecodeしたり、PostgreSQLのSchema Versionへ直接依存したりしないでください。

```ts
const current = await GenerateReport.status(operationId, options);

if (current.ok && current.kind === 'completed') {
  current.data.outcome.reportName;
  current.data.outcome.location;
}
```

Status Resultは`accepted`／`running`／`retry_scheduled`をPending、`completed`／`rejected`／`failed`／`dead_lettered`をTerminalとして区別します。認可済みでRetention期限切れを証明できる場合は410 `expired`、UnknownとDenyは同じ404 `unavailable`です。

## PHP AdapterからOutcomeだけを読む

```php
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Outcome\OutcomeReader;

function reportResult(OutcomeReader $outcomes, string $operationId): ?ReportGenerated
{
    $record = $outcomes->find(OperationId::fromString($operationId));

    if ($record === null) {
        return null;
    }

    $outcome = $record->outcome();

    return $outcome instanceof ReportGenerated ? $outcome : null;
}
```

`OutcomeRecord`はOperation ID、復元済み`Outcome`、UTCへ正規化した完了時刻を持ちます。対象Recordがなければ`find()`は`null`を返します。

## `null`の意味を区別する

`null`だけでは次を区別できません。

- Operation IDが未知である
- Operationがまだ完了していない
- OperationがRejected／Failed／Dead Letterになった
- Outcomeの独立した保持期限を過ぎた

Public Status Query／HTTP Resourceはこれらを区別します。`OutcomeReader::find()`はOutcomeだけが必要なPHP Adapter向けなので、`null`をStatus判定へ流用しません。Frameworkの非PublicなTableやPayload形式を利用者向けContractにしないでください。判定例は[Troubleshooting](troubleshooting.md#outcome-status)を確認してください。

## 保存するOutcome

CompletedだけがOutcome Recordを作ります。Rejected、Failed、Retry Scheduled、Dead Letter、Claim Lost、Grace Timeoutは成功Outcomeを作りません。値のない成功を表す`EmptyOutcome`も型付きOutcomeとして保存します。

`EphemeralOutcome`は例外です。HTTPへ一度だけ返すCredential ResponseなのでOutcome Rowを作らず、認可済みStatus Queryにも`operation_unavailable`を返します。Journal上の`EmptyOutcome`をDeclared Ephemeral Classへ復元しないでください。

PostgreSQL Storeは最初の完了結果を上書きせず、重複Saveを拒否します。未対応Schema Version、破損Payload、保存型の不一致、`Outcome`を実装しない値は`OutcomeStoreException`になります。

## Retention

Outcomeの[Retention](glossary.md#retention)はTransport Payload、Journal、Dead Letterから独立しています。`RetentionPolicy::outcomeRetention()`は`OutcomeRecord::completedAt()`を基準に期限を判定します。ActiveなOperation Holdがある場合、PlannerとPurgeはOutcomeを対象外にします。

Purgeが成功すると、同じDatabase TransactionでPayloadを含まない監査Recordを保存し、`RetentionPurgeResult::outcomesDeleted()`へ削除件数を加算します。保持期間とHoldの運用は[Data Retention](retention.md)を確認してください。
