# D003: Execution Strategyと再現可能なJournal

Status: Decided

## Context

D001ではOperation EnvelopeにDispatch Modeを持たせる方向としていた。しかしD002の議論により、`Immediate/Durable` という固定的な種別ではなく、Operationの配送と実行をStrategyとして差し替える案が生まれた。

また、遅延実行ではOperationをDBへ一時保存するだけでなく、SQSやKafkaへJSONとして送出する可能性がある。この送出データをJournalとし、JournalからOperationを再現可能にする構想を検討する。

## Question 1: Dispatch ModeとExecution Strategy

Operationの実行方法をどのように表すか。

### Options

- A: `Immediate/Durable` のenumをOperation Envelopeへ保持する
- B: OperationごとにExecution Strategyを選択し、Strategyが配送と実行方法を決める
- C: FWには実行方法の概念を設けず、Handlerがすべて決める

### Recommendation

Bを推奨する。

初期実装ではInline StrategyとDeferred Strategyを提供し、将来はBatch、Coalesce、Scheduledなどを追加できる。

```php
#[ExecuteWith(Deferred::class)]
final readonly class AddFavorite implements Operation
{
}
```

Attribute未指定時はInline Strategyを使用する。

[ANSWER]

B

[/ANSWER]

## Question 2: StrategyとInfrastructureの分離

SQS、Kafka、RDBなどの具体的な配送先をOperationのAttributeへ直接書くか。

### Options

- A: `#[ExecuteWith(SqsStrategy::class)]` のように具体的な技術をOperationへ指定する
- B: `#[ExecuteWith(Deferred::class)]` のように論理Strategyだけを指定し、実際のTransportはInfrastructure設定で割り当てる
- C: StrategyもTransportもすべて外部設定とし、OperationにはAttributeを付けない

### Recommendation

Bを推奨する。

Operationは「後で実行してよい」という意味だけを宣言し、SQS、Kafka、RDBなどの選択は環境やInfrastructure層へ委ねる。

```text
Deferred Strategy
    -> production: SQS Transport
    -> local: Database Transport
    -> test: InMemory Transport
```

[ANSWER]

B
Configで設定でいいですね。

[/ANSWER]

## Question 3: JournalからのOperation再現

Journalへ、Operationを再生成できるだけの情報を含めるか。

再現に必要な候補：

- Operationの型識別子
- スキーマバージョン
- Operationの入力値
- Operation ID
- 作成日時
- Context
- Idempotency Key
- Execution Strategy

### Options

- A: `OperationReceived` Journal Entryから元のOperation Envelopeを完全に再現できるようにする
- B: Journalは観測専用とし、再現用のメッセージ形式を別に設ける
- C: Operationごとに再現可能か選択できるようにする

### Recommendation

Aを推奨する。

Journalを共通の正規表現として利用でき、DB、SQS、Kafka、ログへ同じOperation IDとスキーマを持つ記録を送れる。ただし、出力先ごとに機密値の除外や暗号化が必要になるため、物理的に同一のJSONをすべての出力先へ送るとは限らない。

[ANSWER]

A
確かに、ログやOtel等に送出されるものはなんらかフィルタを通してセキュアなデータを送出される仕組みが必要ですね。別途検討で良さそう。
ジャストアイデアだがOperationValueの定義にセンシティブプロパティを設定したOperationのStorategyでは遅延処理を使えないなどの制約があってもいいかもですね

[/ANSWER]

## Question 4: Journal AdapterとExecution Transport

Journalの出力と、遅延Operationの配送を同じインターフェースで扱うか。

### Options

- A: 一つのJournal Adapterへ統一する。Adapterが観測または実行配送を担当する
- B: 正規化されたJournal形式は共有するが、Journal ObserverとExecution Transportのインターフェースは分離する
- C: Journal形式もインターフェースも完全に分離する

### Recommendation

Bを推奨する。

CloudWatchへの出力失敗とSQSへの配送失敗では、必要な保証が異なる。データの概念モデルを共有しつつ、次の責務を分ける。

```text
Journal Record
  ├─ Journal Observer     ログ、OTel、CloudWatch
  └─ Execution Transport  SQS、Kafka、RDB、InMemory
```

[ANSWER]

B

[/ANSWER]

## Question 5: 配送完了後の保持

Execution Transport上のOperationは、正常終了後にどう扱うか。

### Options

- A: 削除またはAcknowledgementし、実行キューから除去する。履歴はJournal Observer側へ任せる
- B: 実行履歴としてExecution Transportにも永久保存する
- C: Strategyごとに保持方針を必須設定する

### Recommendation

Aを推奨する。

Execution Transportは未完了Operationの安全な配送に集中し、長期保存や監査はJournal Observerの出力先へ委ねる。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

1. `Immediate/Durable` の固定的なDispatch Modeは採用せず、Operationの配送と実行方法をExecution Strategyとして表現する。
2. Attribute未指定時は、受け付けたプロセス内で実行するInline Strategyを使用する。
3. OperationはPHP AttributeによってInline以外の論理Strategyを指定できる。
4. OperationはSQS、Kafka、RDBなどの具体的なInfrastructureを指定しない。
5. 論理Strategyと具体的なExecution Transportの対応はConfigで定義する。
6. `OperationReceived` のJournal Recordには、元のOperation Envelopeを再現できる情報を含める。
7. 再現可能な正規Journal形式を、Journal ObserverとExecution Transportで共有する。
8. Journal ObserverとExecution Transportは、異なる信頼性要件を持つため別のインターフェースとする。
9. Journal Observerへ渡すデータには、出力先に応じたフィルタ、マスク、除外などの変換を適用できるようにする。詳細は後続設計で決める。
10. Execution Transportは未完了Operationの安全な配送を担い、正常終了後は削除またはAcknowledgementによって実行キューから除去する。
11. 長期保存と監査はExecution Transportの責務とせず、Journal Observerの出力先へ委ねる。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 新しい実行方法はExecution Strategyとして追加でき、Operation Envelopeのenum変更を必要としない。
- 同じOperationを、環境ごとにSQS、Kafka、RDB、InMemoryなど異なるTransportへ割り当てられる。
- Journal RecordがOperation配送の正規形式になるため、型識別子とスキーマバージョンの安定した設計が必要になる。
- WorkerはJournal RecordをデシリアライズしてOperation Envelopeを再構築する。
- 観測出力と実行配送は形式を共有するが、失敗時の扱いと配送保証を独立して設計できる。
- センシティブ値を含むOperationについて、観測用のフィルタと実行配送用の暗号化・許可規則を別途決める必要がある。
- Deferred、Batch、Coalesce、Scheduledなど、初期提供するStrategyと各Strategyの保証は後続設計で決める必要がある。

[/CONSEQUENCES]
