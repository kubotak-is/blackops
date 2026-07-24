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

通常のDeferred OperationはHandler実行中にFramework Store Transactionを保持しない。

```text
Tx 1: Claim + Fencing更新
Tx 2: Attempt Started + State + Sequence + Canonical Journal
      Commit
Handler実行
Tx 3: Fencing検証 + Result State + Sequence + Canonical Journal + Outcome
      Commit
```

各Lifecycle境界内ではState、Sequence、Canonical Journal、Outcomeを原子的に更新する。

`#[Transactional]`付きOperationは明示例外とする。Attempt Startedは先にCommitしたまま、Authorization後にApplication Transactionを開始する。Application ConnectionとFramework Storeが同一Connection Instanceなら、Handlerの業務更新、Fencing検証、Result State、Sequence、Canonical Journal、Outcomeを一つの成功Transactionへ含める。Rejected／ThrowableではApplication TransactionをRollbackしてから、既存の短いFramework TransactionでTerminalまたはSupervision状態を記録する。

一般Serviceの`#[Transactional]`はMethod Return時にCommitする。Operation自体がTransactionalでない場合、そのCommitと後続のWorker Result Transactionは原子的ではない。

業務Databaseが別Connectionの場合、その更新との原子性はTransactional Outbox等を使わない限り保証しない。

## Observer配送

Database Commit後、安全なProjectionをObserverへBestEffortで配送する。

外部ObserverをDatabase TransactionのCommit条件にしない。配送失敗またはCommit後のCrashでProjectionが欠落した場合もCanonical Journalを正本として保持する。

初期MVPではTransactional Outbox Relayを実装しなかったが、Phase 19でPersistence、有限Relay、Retry／Fencing、Dead Letter再開を追加した。Canonical Journalを変更しないObserver Replayは別Commandとして、現在のSensitive Projectionをat-least-onceで再送する。
