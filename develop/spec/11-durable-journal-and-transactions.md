# Durable Journal and Transactions

## Durableの保証範囲

Durable PolicyはLocal Storeへの耐久記録を基本保証とする。

業務DBとJournal／Outbox Storeが同じTransactionへ参加できる場合だけ、原子的なCommitを追加保証する。異なるDatabaseや外部APIを含む処理について、FWは一律の原子性を保証しない。

## Transaction Middleware

Operation単位で任意適用できるTransaction Operation Middlewareを標準方式とする。

```php
#[Transactional(connection: 'default')]
#[JournalDelivery(Durable::class)]
final class CreateOrder implements HttpOperation
{
}
```

MiddlewareはHandlerをbegin／commit／rollbackで包み、Journal Outboxを同じTransactionへ参加可能にする。

複数DB、短いTransaction範囲など標準Middlewareで表現できない場合、Handler内の手動Transactionも許可する。

## Transactional Outbox

Transactional OutboxのPortと拡張余地を初期設計に含める。具体的なPersistence AdapterとRelay実装は初期Vertical Slice後に追加する。

```text
DB Transaction Begin
  -> 業務データを更新
  -> OutboxへJournal／子Operation Recordを保存
DB Transaction Commit

Outbox Relay
  -> 未送信Recordを取得
  -> 外部Observer／Transportへ配送
  -> 送信済みに更新
```

## Deferred Transport

Deferred配送は別Adapterとして二経路を表現できるようにする。

- Direct Transport：業務DB更新を伴わず、外部TransportへのPublish成功を受付条件とする
- Outbox Transport：業務DB更新と子Operation発行を同じTransactionで結び付ける

Operationは論理的なDeferred Strategyだけを指定し、Direct／OutboxのTransport選択はConfigへ委ねる。

## Journal Record Identity

各Journal Recordは次を持つ。

- Record ID：UUIDv7
- Operation ID
- Attempt ID：Optional
- Operation内Sequence
- Event

Record IDはObserverの冪等取り込みに使用する。Sequenceは同一Operation内の順序と欠落候補の検知に使用する。

## Relay Delivery

Outbox Relayの配送保証はat-least-onceとする。

送信成功後、送信済み更新前にProcessが停止すると重複配送される。Record IDによる冪等取り込みを前提とする。
