# PostgreSQL Transaction Boundaries

## Canonical Journal Store

PostgreSQL AdapterはExecution Transportに加え、Canonical Journal Storeを実装する。

Portは分離したまま、同じConnectionとTransactionへ参加できるようにする。

## Deferred受付

次を同一TransactionでCommitする。

- Operation StateのInsert
- `operation.received` Canonical Journal
- `operation.accepted` Canonical Journal
- 初期Sequence

Commit成功後に `DeferredAcknowledgement` を返す。

## Worker

Handler実行中にDatabase Transactionを保持しない。

```text
Tx 1: Claim + Fencing更新
Tx 2: Attempt Started + State + Sequence + Canonical Journal
      Commit
Handler実行
Tx 3: Fencing検証 + Result State + Sequence + Canonical Journal + Outcome
      Commit
```

各Lifecycle境界内ではState、Sequence、Canonical Journal、Outcomeを原子的に更新する。

業務Databaseが別Connectionの場合、その更新との原子性はTransactional Outbox等を使わない限り保証しない。

## Observer配送

Database Commit後、安全なProjectionをObserverへBestEffortで配送する。

外部ObserverをDatabase TransactionのCommit条件にしない。配送失敗またはCommit後のCrashでProjectionが欠落した場合もCanonical Journalを正本として保持する。

MVPではTransactional Outbox Relayを実装しない。手動または後続のReplay ToolでCanonical JournalからProjectionを再送可能にする。
