# D015: Log DeliveryとRetention

Status: Decided

## Context

D014により、FW LoggerはOperation Contextを自動付与し、標準Lifecycle Journal Logを自動生成することが決まった。

次に、Journal Observerが停止している場合の挙動、監査用途の耐久性、Sampling、Buffering、保持責務を決める。

## Question 1: Application Logの出力失敗

通常のApplication Log出力に失敗した場合、Operationをどうするか。

### Options

- A: Operationを失敗させる
- B: Operationは継続し、Fallback Errorだけを可能な範囲で記録する
- C: Log Levelによって挙動を変える

### Recommendation

Bを推奨する。

通常Log出力先の障害によってアプリケーション全体を停止させない。Observer Failure Metric、標準エラー、予備Sinkなどへ通知する。

[ANSWER]

B

[/ANSWER]

## Question 2: Lifecycle Journalの保証レベル

Lifecycle Journal Recordの出力保証をどう設定するか。

### Options

- A: すべてBest Effortとする
- B: すべて永続化成功をOperation実行の前提とする
- C: Delivery Policyを選択可能にする

### Recommendation

Cを推奨する。

候補Policy：

| Policy | 意味 |
| --- | --- |
| BestEffort | Observer失敗でもOperationを継続 |
| Required | Journal出力成功を処理継続の条件にする |
| Durable | 信頼できるLocal Store／Outboxへの記録成功を条件にし、外部Sinkへ後送する |

既定はBestEffortとし、監査対象Operationでは `Required` または `Durable` を指定できるようにする。

[ANSWER]

C

[/ANSWER]

## Question 3: Delivery Policyの設定場所

OperationごとのJournal Delivery Policyをどう設定するか。

### Options

- A: Global Configだけで設定する
- B: Global既定値をConfigに置き、Operation Attributeで上書きする
- C: Handlerが実行時に決める

### Recommendation

Bを推奨する。

```php
#[JournalDelivery(Durable::class)]
final class CapturePayment implements Operation
{
}
```

Manifest Compilerが、Durable指定なのに対応Storeがない構成をBuild時に拒否する。

[ANSWER]

B

[/ANSWER]

## Question 4: 複数Observer

CloudWatch、OTel、Audit Storeなど複数Observerがある場合、失敗をどう扱うか。

### Options

- A: 一つ失敗したら後続Observerを呼ばない
- B: Observerを独立して実行し、個別の成功／失敗を集約する
- C: Observerは一つだけ許可する

### Recommendation

Bを推奨する。

一つの外部Sink障害が他の記録経路を妨げない。Delivery Policyは、どのObserverまたはStoreをRequired対象とするか明示できるようにする。

[ANSWER]

B

[/ANSWER]

## Question 5: Sampling

大量トラフィック時にLog Samplingをどう適用するか。

### Options

- A: Application LogとJournal Logの両方をSampling可能にする
- B: Application LogはSampling可能、標準Lifecycle Journal LogはSamplingしない
- C: Samplingを提供しない

### Recommendation

Bを推奨する。

Lifecycleの一部だけが欠落するとOperationの状態を誤認するため、標準Journal EventはSamplingしない。Application Debug／Info LogはConfigとAdapterでSampling可能にする。

[ANSWER]

B

[/ANSWER]

## Question 6: Lifecycle EventのLog Level

標準Lifecycle EventをPSR-3 Levelへどう対応付けるか。

### Options

- A: すべてInfoにする
- B: Event種別に応じた既定LevelをFWが持つ
- C: Levelを持たずJournal専用Sinkだけへ送る

### Recommendation

Bを推奨する。

初期候補：

| Event | Level |
| --- | --- |
| Received / Accepted / Started / Succeeded / Completed | Info |
| Rejected | Notice |
| AttemptFailed（Retry予定） | Warning |
| OperationFailed / DeadLettered | Error |

Adapter側でLevel Mappingを上書き可能にする。

[ANSWER]

B

[/ANSWER]

## Question 7: BufferingとFlush

LogをBufferしてBatch送信する場合、いつFlushするか。

### Options

- A: Adapterへ完全に任せる
- B: FWがOperation終了、Worker Loop終了、Process ShutdownでFlush Hookを呼ぶ
- C: すべて同期送信する

### Recommendation

Bを推奨する。

AdapterがBuffer方式を選べる一方、FWはLifecycle境界でFlush機会を提供する。Durable PolicyはMemory Bufferだけを記録成功とみなさない。

[ANSWER]

B

[/ANSWER]

## Question 8: Retention

LogやJournalの保持期間をFWが管理するか。

### Options

- A: FWがすべてのSinkの保持期間を管理する
- B: FWはRetention Metadataと推奨値を提供し、実際の削除は各Sink／Infrastructureへ委ねる
- C: Retentionを仕様に含めない

### Recommendation

Bを推奨する。

CloudWatch、S3、Database、OTel Backendでは保持機構が異なる。FWはRecord Kind、監査区分、Operation Type等に基づくPolicyを表現し、実施はAdapterへ委ねる。

[ANSWER]

B

[/ANSWER]

## Decision

[DECISION]

1. 通常のApplication Log出力に失敗してもOperationを継続する。
2. Application Log出力失敗はObserver Failure Metric、標準エラー、予備Sinkなどへ可能な範囲で通知する。
3. Lifecycle Journal RecordはBestEffort、Required、DurableのDelivery Policyを選択可能にする。
4. BestEffortはObserver失敗でもOperationを継続する。
5. Requiredは指定されたJournal出力先への記録成功を処理継続の条件とする。
6. Durableは信頼できるLocal StoreまたはOutboxへの記録成功を処理継続の条件とし、外部Sinkへ後送する。
7. Global既定PolicyはConfigで設定し、Operation Definitionの `#[JournalDelivery(...)]` Attributeで上書きできる。
8. 既定PolicyはBestEffortとする。
9. Manifest Compilerは、Required／Durable Policyに必要なObserverまたはStoreが構成されていない場合、Buildを失敗させる。
10. 複数Observerは独立して実行し、個別の成功と失敗を集約する。
11. Delivery PolicyはRequired対象となるObserverまたはStoreを明示できるようにする。
12. Application Debug／Info LogはSampling可能とする。
13. 標準Lifecycle Journal LogはSamplingしない。
14. Lifecycle Event種別ごとに既定PSR-3 Levelを持ち、AdapterでMappingを上書き可能にする。
15. Received、Accepted、Started、Succeeded、Completedの既定LevelはInfoとする。
16. Rejectedの既定LevelはNoticeとする。
17. Retry予定のAttemptFailedはWarningとする。
18. OperationFailedとOperationDeadLetteredはErrorとする。
19. FWはOperation終了、Worker Loop終了、Process ShutdownでFlush Hookを呼ぶ。
20. Durable PolicyではMemory Bufferだけを記録成功とみなさない。
21. FWはRecord Kind、監査区分、Operation Type等に基づくRetention Metadataと推奨値を表現する。
22. 実際の保持と削除は各SinkおよびInfrastructure Adapterへ委ねる。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 通常の観測基盤障害がアプリケーション全体の停止へ直結しにくくなる。
- 監査対象Operationでは、Journal記録を業務処理の前提条件へ引き上げられる。
- Durable PolicyにはLocal Journal StoreまたはTransactional Outboxが必要になる。
- 複数Sinkの一部が停止しても、他のObserverへ記録を継続できる。
- Lifecycle JournalをSamplingによって部分欠落させず、状態追跡の一貫性を維持できる。
- Journal Delivery Policy、Observer Result、Fallback Sink、Flushable Observer、Retention PolicyのContractを実装する必要がある。
- Durable Journal Storeと業務DBのTransaction境界を別途決める必要がある。
- Process Crash時にMemory Bufferが失われるため、BestEffortとDurableの保証差を利用者へ明示する必要がある。

[/CONSEQUENCES]
