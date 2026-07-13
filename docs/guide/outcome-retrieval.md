# Outcomeを取得する

正常完了したDeferred Operationは、Operation IDごとに型付き[Outcome](glossary.md#outcome)を保存します。ApplicationはPublic `OutcomeReader` ContractからOutcomeを読みます。Persistence Payloadを独自にDecodeしたり、PostgreSQLのSchema Versionへ直接依存したりしないでください。

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

現行RuntimeはOutcome／Status用HTTP endpointやCLI Commandを提供しません。区別が必要なApplicationは、Operation Status Viewを実装し、`OutcomeReader`の結果と組み合わせてControllerやCLI Commandから返します。Frameworkの非PublicなTableやPayload形式を利用者向けContractにしないでください。判定例は[Troubleshooting](troubleshooting.md#outcome-status)を確認してください。

## 保存するOutcome

CompletedだけがOutcome Recordを作ります。Rejected、Failed、Retry Scheduled、Dead Letter、Claim Lost、Grace Timeoutは成功Outcomeを作りません。値のない成功を表す`EmptyOutcome`も型付きOutcomeとして保存します。

PostgreSQL Storeは最初の完了結果を上書きせず、重複Saveを拒否します。未対応Schema Version、破損Payload、保存型の不一致、`Outcome`を実装しない値は`OutcomeStoreException`になります。

## Retention

Outcomeの[Retention](glossary.md#retention)はTransport Payload、Journal、Dead Letterから独立しています。`RetentionPolicy::outcomeRetention()`は`OutcomeRecord::completedAt()`を基準に期限を判定します。ActiveなOperation Holdがある場合、PlannerとPurgeはOutcomeを対象外にします。

Purgeが成功すると、同じDatabase TransactionでPayloadを含まない監査Recordを保存し、`RetentionPurgeResult::outcomesDeleted()`へ削除件数を加算します。保持期間とHoldの運用は[Data Retention](retention.md)を確認してください。
