# D016: Durable JournalとTransaction

Status: Decided

## Context

D015で、Lifecycle JournalのDelivery PolicyとしてBestEffort、Required、Durableを定義した。

ただし、Journal Storeへの記録と業務DBの更新が別Transactionの場合、次の不整合が起こり得る。

```text
業務DB更新: 成功
OperationCompleted記録: 失敗
```

または：

```text
OperationAccepted記録: 成功
Execution Transportへの配送: 失敗
```

この設計対話では、Durableの保証範囲、Transactional Outbox、Journal Recordの重複配送を決める。

## Question 1: Durableの保証範囲

Durable Policyが保証する内容をどこまでにするか。

### Options

- A: Journal RecordがLocal Storeへ保存されることだけを保証する
- B: Journal Recordと業務DB更新の原子的なCommitまで保証する
- C: Aを基本保証とし、同一Transactionへ参加できる場合だけTransactional Guaranteeを追加する

### Recommendation

Cを推奨する。

異なるDatabaseや外部APIを含む処理に、FWが一律の原子性を保証することはできない。DurableはLocal Storeへの耐久記録を基本とし、対応Persistence AdapterではTransactional Outboxへ参加できるようにする。

[ANSWER]

C

[/ANSWER]

## Question 2: Transactional Outbox

業務DBとJournal Storeが同じTransactionへ参加できる場合、Outboxを標準機能として提供するか。

### Options

- A: 提供する
- B: アプリケーションへ完全に任せる
- C: 初期バージョンでは扱わない

### Recommendation

Aを推奨する。

```text
DB Transaction Begin
  -> Handlerが業務データを更新
  -> Journal RecordをOutboxへ追加
DB Transaction Commit
  -> Outbox Relayが外部Observerへ配送
```

Persistence AdapterがTransactionへ参加するためのPortを提供し、対応していないDBや外部APIでは保証を明示的に下げる。

[ANSWER]

C、Outboxよくわかってない

[/ANSWER]

## Question 3: Transaction境界の所有者

Handler実行時のTransactionを誰が管理するか。

### Options

- A: 各Handlerがbegin／commit／rollbackする
- B: Transaction Operation MiddlewareがHandlerとJournal Outboxを包む
- C: FWがすべてのOperationを常にTransactionで包む

### Recommendation

Bを推奨する。

```php
#[Transactional]
#[JournalDelivery(Durable::class)]
final class CapturePayment implements Operation
{
}
```

Operation Middlewareとして実装すれば、DomainやHandlerをTransaction APIへ結合せず、必要なOperationだけ適用できる。

[ANSWER]

うーーん、トランザクションはユーザーが任意に設定できたほうがいいかな。AOPとか、あるいはLaravelみたいなDBManagerでもいいかも。
悩ましい、
Bの利点は？

[/ANSWER]

## Question 4: Execution Transportとの二重書き込み

Deferred OperationのJournal記録とExecution Transportへの配送をどう整合させるか。

### Options

- A: Journal StoreとTransportへ順番に書き、片方の失敗はRetryする
- B: Local OutboxへOperation配送Recordを保存し、RelayがExecution Transportへ送る
- C: Execution Transportだけを信頼し、Journal記録を省略する

### Recommendation

Bを推奨する。

受付処理ではLocal Outboxへの保存成功をAcknowledgement条件とする。RelayがSQS、Kafka等へ配送し、成功後にOutbox Recordを完了扱いにする。

Transport自体が同等のDurabilityを提供し、直接Publishを選ぶAdapterも許可できるが、その保証Capabilityを明示させる。

[ANSWER]

？

[/ANSWER]

## Question 5: Journal Record ID

同じRecordがRelayやObserverへ複数回配送された場合、どう重複排除するか。

### Options

- A: Operation IDとEvent名の組み合わせを一意Keyにする
- B: 各Journal Recordへ独立したRecord IDを発行する
- C: 重複排除を行わない

### Recommendation

Bを推奨する。

```text
recordId: UUIDv7
operationId: UUIDv7
attemptId: UUIDv7 | null
sequence: Operation内の単調増加番号
event: OperationCompleted
```

Retryによって同じEvent名が複数回発生し得るため、Record IDを独立させる。ObserverはRecord IDで冪等に取り込める。

[ANSWER]

B

[/ANSWER]

## Question 6: Operation内Sequence

Journal RecordへOperation内のSequence番号を持たせるか。

### Options

- A: 持たせ、同一Operation内の順序と欠落検知に使う
- B: Timestampだけで順序を決める
- C: Adapterごとに任せる

### Recommendation

Aを推奨する。

分散環境ではTimestampが同一になったり前後したりする可能性がある。Sequenceによって同一Operation内の順序を安定させ、欠落候補も検知できる。

複数Workerが同じOperationを同時実行しないLease／Concurrency Controlは別途必要になる。

[ANSWER]

A

[/ANSWER]

## Question 7: Outbox Relayの配送保証

Outbox RelayからObserver／Transportへの配送保証をどうするか。

### Options

- A: at-most-once
- B: at-least-once
- C: exactly-once

### Recommendation

Bを推奨する。

送信成功後、Outbox完了更新前にProcessが停止すると再配送が起きる。Record IDによる冪等取り込みを前提に、損失を避けるat-least-onceを採用する。

[ANSWER]

B

[/ANSWER]

## Follow-up 1: Transactional Outboxの具体例

### Outboxがない場合

注文保存後に通知OperationをSQSへ送る処理を考える。

```text
1. ordersへINSERT              成功
2. DB COMMIT                  成功
3. SQSへSendNotification送信   失敗
```

注文は作られたが通知Operationは失われる。逆順にしても問題は解決しない。

```text
1. SQSへSendNotification送信   成功
2. ordersへINSERT              失敗
```

今度は存在しない注文の通知が実行される。

DBとSQSは同じTransactionへ参加できないため、単純な順序変更では原子的にできない。

### Outboxがある場合

Queueへ送るOperationを、まず業務DB内のOutbox Tableへ保存する。

```text
DB Transaction Begin
  1. ordersへINSERT
  2. outboxへSendNotification RecordをINSERT
DB Transaction Commit
```

同じDB Transactionなので、両方成功するか両方失敗する。

別のOutbox Relayが未送信Recordを配送する。

```text
outbox未送信Recordを取得
  -> SQSへ送信
  -> 成功したらoutboxを送信済みに更新
```

Relay停止中もRecordはDBに残るため、再起動後に配送できる。送信成功後、送信済み更新前に停止すると重複配送されるため、at-least-onceとRecord IDによる冪等処理が必要になる。

### Outboxが不要な場合

業務DB更新を伴わず、HTTP受付から直接SQSへOperationを登録するだけなら、SQSのSend成功をAcknowledgement条件にできる。この場合はLocal Outboxを必須にしなくてもよい。

### Question

Transactional Outboxを初期バージョンでどこまで扱うか。

### Options

- A: 標準機能として初期バージョンから提供する
- B: Portと拡張余地だけ設計し、実装は初期バージョン後にする
- C: FWの対象外とする

### Recommendation

Bを推奨する。

二重書き込み問題を仕様上無視せず、将来の互換性を確保する。一方、最初のVertical Sliceは業務DBと結合しないExecution Transportで成立させ、Outbox実装はPersistence Adapterと共に追加する。

[ANSWER]

なるほど、UnitOfWork的なやつですねー。これがあると結構FWのアピールポイントになりそう。B

[/ANSWER]

## Follow-up 2: Transaction Middlewareの利点

Transaction Middlewareは、すべてのOperationを強制的にTransaction化するものではない。AttributeまたはConfigで選択されたOperationだけに適用するAOP相当の仕組みである。

```php
#[Transactional(connection: 'default')]
final class CreateOrder implements HttpOperation
{
}
```

実行フロー：

```text
Transaction Middleware
  -> begin
  -> 次のMiddleware
  -> Handler
  -> Completedならcommit
  -> RejectedまたはExceptionならrollback
```

### HandlerがTransactionを管理する場合

```php
public function handle(OperationEnvelope $operation): OperationResult
{
    return $this->db->transaction(function () {
        // ...
    });
}
```

利点：

- Operation内の一部分だけをTransaction化できる
- 特殊なTransaction制御を自由に書ける

欠点：

- HandlerがPersistence APIへ結合する
- begin／commit／rollbackの書き忘れや規則差が生まれる
- FWがJournal Outboxを同じTransactionへ自動参加させにくい

### MiddlewareがTransactionを管理する場合

利点：

- HandlerはTransaction APIを知らなくてよい
- 全体のrollback規則が統一される
- Journal Outboxを同じTransactionへ参加させられる
- TestでTransaction Middlewareを差し替えられる

欠点：

- Handler全体がTransactionに包まれ、外部API呼び出し中もLockを保持する可能性がある
- 複数DBや特殊な境界には標準Middlewareだけでは対応できない

### Question

Transaction管理をどう提供するか。

### Options

- A: OptionalなTransaction Middlewareを標準方式とし、特殊な場合はHandler内の手動Transactionも許可する
- B: TransactionはすべてHandlerが任意に管理する
- C: Transaction Middlewareだけを許可し、Handlerからの手動管理を禁止する

### Recommendation

Aを推奨する。

通常は宣言的なAOP方式を使い、短いTransaction範囲や複数DBなど、標準方式で表現できない場合だけユーザー管理へ降りる。

[ANSWER]

A

[/ANSWER]

## Follow-up 3: Deferred配送とOutbox

Deferred Operationの配送経路を二つに分ける。

### Direct Transport

```text
HTTP受付
  -> SQSへ直接Publish
  -> SQS成功後にHTTP 202
```

業務DB更新を伴わない受付に適する。

### Outbox Transport

```text
業務DB Transaction
  -> 業務データ更新
  -> Outboxへ子Operationを保存
Commit
  -> RelayがSQSへ後送
```

業務DB更新と子Operation発行を失敗なく結び付けたい場合に使う。

### Question

Execution Transportで両方の経路を表現できるようにするか。

### Options

- A: Direct TransportとOutbox Transportを別Adapterとして表現する
- B: Direct Transportだけを提供する
- C: Outbox Transportだけを提供する

### Recommendation

Aを推奨する。

Operationは論理的なDeferred Strategyだけを指定し、ConfigがDirectまたはOutboxのTransport Adapterを割り当てる。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

1. Durable PolicyはLocal Storeへの耐久記録を基本保証とする。
2. 業務DBとJournal／Outbox Storeが同じTransactionへ参加できる場合だけ、原子的なCommitを追加保証する。
3. 異なるDatabaseや外部APIを含む処理について、FWは一律の原子性を保証しない。
4. Transactional OutboxのPortと拡張余地を初期設計に含める。
5. Transactional Outboxの具体的なPersistence AdapterとRelay実装は、初期Vertical Slice後に追加する。
6. Transaction管理の標準方式として、Operation単位で任意適用できるTransaction Operation Middlewareを提供する。
7. Operation Definitionは `#[Transactional(...)]` AttributeまたはConfigでTransaction Middlewareを指定できる。
8. Transaction MiddlewareはHandlerをbegin／commit／rollbackで包み、Journal Outboxを同じTransactionへ参加可能にする。
9. 複数DB、短いTransaction範囲など標準Middlewareで表現できない場合、Handler内の手動Transactionも許可する。
10. Deferred配送はDirect TransportとOutbox Transportを別Adapterとして表現できるようにする。
11. 業務DB更新を伴わない受付では、Direct TransportへのPublish成功をAcknowledgement条件にできる。
12. 業務DB更新と子Operation発行を結び付ける場合、Outbox Transportを使用できる。
13. Operationは論理的なDeferred Strategyだけを指定し、Direct／OutboxのTransport選択はConfigへ委ねる。
14. 各Journal Recordへ独立したUUIDv7のRecord IDを発行する。
15. Journal RecordはOperation ID、任意のAttempt ID、Operation内Sequence、Event名を持つ。
16. ObserverはRecord IDによって冪等にRecordを取り込めるようにする。
17. Journal RecordはOperation内の単調増加Sequenceを持ち、順序と欠落候補の検知に使う。
18. Outbox Relayの配送保証はat-least-onceとする。
19. Relayの重複配送はRecord IDによる冪等取り込みを前提とする。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Durableが保証する範囲と、Transactional Guaranteeが成立する条件を区別できる。
- Transaction Middlewareにより、通常のHandlerをTransaction APIから分離できる。
- 特殊なTransaction境界ではユーザーが手動管理へ降りられる。
- Direct TransportとOutbox Transportを用途に応じて選択できる。
- Outbox実装前でも、業務DB更新を伴わないDeferred受付のVertical Sliceを構築できる。
- Record IDとSequenceにより、重複配送、順序、欠落候補を判定できる。
- Transaction Manager Port、Transactional Resource、Outbox Port、Outbox Relay、Direct／Outbox Transport Capabilityを設計する必要がある。
- 同一OperationのSequenceを安全に採番するConcurrency Controlが必要になる。
- Transactional OutboxのPersistence Adapterは初期Vertical Slice後の重要な拡張機能となる。

[/CONSEQUENCES]
