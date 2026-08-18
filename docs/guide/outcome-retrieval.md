# Outcome

正常完了したDeferred Operationは、Operation IDごとに型付き[Outcome](glossary.md#outcome)を保存します。Browserや外部ConsumerはPublic Status Resource、Generated Clientは`.status()`／`.wait()`を主経路にします。PHP Adapterから読む場合も、Default-deny `OperationOutcomeQuery`へCurrent Actor、Current Tenant、`OperationDataPurpose`を渡します。Raw Reader、Persistence Payload、PostgreSQLのSchema Versionへ直接依存しないでください。

```ts
const current = await GenerateReport.status(operationId, options);

if (current.ok && current.kind === 'completed') {
  current.data.outcome.reportName;
  current.data.outcome.operationId;
}
```

Status Resultは`accepted`／`running`／`retry_scheduled`をPending、`completed`／`rejected`／`failed`／`dead_lettered`をTerminalとして区別します。認可済みでRetention期限切れを証明できる場合は410 `expired`、UnknownとDenyは同じ404 `operation_unavailable`です。

## 認可済みPHP QueryからOutcomeを読む

```php
use BlackOps\Core\ActorRef;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\OperationData\OperationDataPurpose;
use BlackOps\OperationData\OperationOutcomeFound;
use BlackOps\OperationData\OperationOutcomeQuery;

function reportResult(
    OperationOutcomeQuery $outcomes,
    string $operationId,
    ?ActorRef $currentActor,
    ?TenantRef $currentTenant,
): ?ReportGenerated
{
    $result = $outcomes->find(
        OperationId::fromString($operationId),
        $currentActor,
        $currentTenant,
        OperationDataPurpose::fromString('report.read'),
    );

    if (!$result instanceof OperationOutcomeFound) {
        return null;
    }

    $outcome = $result->record()->outcome();

    return $outcome instanceof ReportGenerated ? $outcome : null;
}
```

`OperationOutcomeFound`は`OutcomeRecord`を返し、`OperationOutcomeUnavailable`はUnknown、Tenant不一致、Deny、Retention削除を安全に表します。Allow前にProtected BlobをDecodeしません。

## `null`の意味を区別する

`null`だけでは次を区別できません。

- Operation IDが未知である
- Operationがまだ完了していない
- OperationがRejected／Failed／Dead Letterになった
- Outcomeの独立した保持期限を過ぎた

Public Status Query／HTTP Resourceはこれらを区別します。`OperationOutcomeQuery::find()`のUnavailableをStatus判定へ流用しません。FrameworkのInfrastructure SPI、Table、Payload形式を利用者向けContractにしないでください。判定例は[Outcome Status](troubleshooting.md#outcome-status)を確認してください。

## 保存するOutcome

DeferredのCompletedだけがOutcome Recordを作ります。Inline completedはHTTP ResponseだけへOutcomeを返し、Outcome Recordを作りません。Rejected、Failed、Retry Scheduled、Dead Letter、Claim Lost、Grace Timeoutは成功Outcomeを作りません。値のないDeferred成功を表す`EmptyOutcome`も型付きOutcomeとして保存します。

`EphemeralOutcome`は例外です。HTTPへ一度だけ返すCredential ResponseなのでOutcome Rowを作らず、認可済みStatus Queryにも`operation_unavailable`を返します。Journal上の`EmptyOutcome`をDeclared Ephemeral Classへ復元しないでください。

PostgreSQL Storeは最初の完了結果を上書きせず、重複Saveを拒否します。未対応Schema Version、破損Payload、保存型の不一致、`Outcome`を実装しない値は`OutcomeStoreException`になります。

## Retention

Outcomeの[Retention](glossary.md#retention)はTransport Payload、Journal、Dead Letterから独立しています。`RetentionPolicy::outcomeRetention()`は`OutcomeRecord::completedAt()`を基準に期限を判定します。ActiveなOperation Holdがある場合、PlannerとPurgeはOutcomeを対象外にします。

Purgeが成功すると、同じDatabase TransactionでPayloadを含まない監査Recordを保存し、`RetentionPurgeResult::outcomesDeleted()`へ削除件数を加算します。保持期間とHoldの運用は[Retention](retention.md)を確認してください。

## 次にLifecycleの結果を整理する

Status、Outcome、Expiredの差をLifecycle全体で理解する場合は、[Lifecycle](operation-lifecycle.md)へ戻ります。
