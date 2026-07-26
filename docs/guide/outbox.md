# Outbox

Transactional Outboxは、業務MutationとDeferred child Operationの発行を同じDatabase Transactionへ記録するための境界です。`Operations::dispatch()`はactive OperationのContextとValueを使い、Commit前にchildを実行しません。実装例は[Transactional Outboxへの登録](execution.md#transactional-outboxへの登録)を参照してください。

RelayはProject RootのContainer CLIから明示的に起動します。

```bash
docker compose run --rm app php blackops outbox:relay:run --until-empty
docker compose run --rm app php blackops outbox:relay:daemon
```

`outbox:relay:run`はPending Rowを一度配送し、`--until-empty`で空になるまで有限に繰り返します。常駐運用は`outbox:relay:daemon`をProcess Supervisorから起動してください。

## 受付の流れ

1. Root OperationがNamed ConnectionのTransactionを開始する
2. `Operations::dispatch(OperationClass::class, $value)`がchildのDefinitionとDispatch Metadataを登録する
3. 業務変更とOutbox Rowを同じTransactionでCommitする
4. Relayが未配送Rowをclaimし、child OperationをDeferred Workerへ渡す

Commitに失敗した場合は業務変更もOutbox Rowも残りません。Commit後のRelay停止は、Rowを再開可能な状態で残します。DatabaseのTransaction境界は[Transaction](database-and-transactions.md)で確認できます。

## Delivery保証

Relayはat-least-onceです。Lease、Fencing、Retry、Dead Letterを使って同じchild Operation Identityを再配送するため、Applicationの外部副作用は重複耐性を設計してください。外部Message Broker、Exactly Once、Scheduled OperationはこのRuntimeの提供契約ではありません。

OutboxはCanonical Journalを置き換えません。JournalはOperation Lifecycleの事実、OutboxはDeferred配送の再開境界を記録します。Journalの読み方は[Lifecycle](operation-lifecycle.md)を参照してください。
