# Lifecycle and Journal

## Operationの成立

入力アダプタが入力を最低限解析し、対象Operation Definitionを特定できた時点でOperation IDを発行する。

業務バリデーションの失敗もOperationとしてJournalへ記録する。プロトコルとして解析不能な入力やRoute不一致は入力アダプタの責務とする。

Route特定後に必須Field欠落または型不一致でBindingへ失敗した場合は、Sequence 1の`OperationRejected`を直接記録する。具象OperationValueが存在しないため`OperationReceived`を記録せず、Raw Inputや偽Valueを補わない。

`OperationReceived`はOperationValueのBindingが成功し、再現可能なOperation Envelopeを構成できた境界を表す。Binding後のValue Validation Failureは`OperationReceived`から`OperationRejected`へ遷移する。

## 基本ライフサイクル

初期設計では次のJournal Entryを扱う。

- `OperationReceived`（Wire Name: `operation.received`）
- `OperationAccepted`（Wire Name: `operation.accepted`）
- `AttemptStarted`（Wire Name: `attempt.started`）
- `AttemptSucceeded`（Wire Name: `attempt.succeeded`）
- `AttemptFailed`（Wire Name: `attempt.failed`）
- `AttemptRetryScheduled`（Wire Name: `attempt.retry_scheduled`）
- `OperationCompleted`（Wire Name: `operation.completed`）
- `OperationRejected`（Wire Name: `operation.rejected`）
- `OperationFailed`（Wire Name: `operation.failed`）
- `OperationDeadLettered`（Wire Name: `operation.dead_lettered`）

`OperationAccepted` はDeferred OperationがExecution TransportへDurableに永続化された場合だけ記録する。Inline OperationはReceivedから直接Attempt Startedへ進む。

`AttemptSucceeded` はHandler成功、`OperationCompleted` はOutcome保存等の最終処理を含むTerminal化を表す。

Dead Letterへ隔離したOperationには `OperationFailed` を併記せず、Terminal Eventを `OperationDeadLettered` 一つとする。

業務上の予期された拒否とシステム障害を区別する。

- `OperationRejected`：Validation、認可、競合などの予期された拒否
- `AttemptFailed`：一回の実行試行における障害
- `OperationFailed`：Retry不能または上限到達によるOperation全体の失敗

拒否と失敗はHTTPに依存しないReasonを持ち、Web Adapterが具体的な4xxまたは5xxへ変換する。

Lifecycle State MachineはBinding Failureに限りInitial Stateから`OperationRejected`へのTerminal Transitionを許可する。通常のInline／Deferred OperationとValue Validation Failureは`OperationReceived`から開始する。

最終状態へ到達したOperationを同じOperation IDで再実行しない。手動Replayは新しいOperationとして作り、元Operationとの因果関係を記録する。

## Journal

Journalは、Operationのライフサイクルで発生した事実をJournal Recordとして表す論理的な追記型ログである。Operationそのものとは区別する。

Journalの出力・保持方針はExecution Strategyと独立させる。

## 再現可能なJournal

`OperationReceived` のJournal Recordには、元のOperation Envelopeを再現できる情報を含める。Binding Failureは`OperationReceived`を作らず、`OperationRejected`へ安全なViolationだけを記録する。

候補となる正規情報：

- Operation Type ID
- Schema Version
- Operation Value
- Operation ID
- 受付時刻
- ExecutionContext
- Idempotency Key
- Execution Strategy

正規形式はJournal ObserverとExecution Transportで共有するが、物理的に同一のJSONを全出力先へ送ることは要求しない。

## Type IDとSchema Version

Operationの型はPHPの完全修飾クラス名ではなく、明示された安定したType IDで識別する。

```php
#[OperationType('order.create')]
final class CreateOrder implements Operation
{
}
```

Journal RecordはSchema Versionを持つ。古い形式はUpcaster Chainによって現在形式へ変換してからOperationを復元する。

```text
order.create v1
  -> CreateOrderV1ToV2 Upcaster
  -> order.create v2
  -> Current OperationValue
```

Upcasterはデータ形式だけを扱う純粋な変換とし、業務処理や外部I/Oを行わない。

## センシティブ値

センシティブ値は `#[Sensitive]` Attributeを基本として宣言する。

- Journal Observerには、除外またはマスクした観測用Projectionを渡す
- Execution Transportには、Operationを再現可能な完全データを暗号化して渡せるようにする
- 安全な配送Capabilityを持たないTransportへセンシティブなOperationを割り当てた構成は拒否する
- 構成不備は可能な限り起動時またはCIで検出し、実行時にも検証する

HMACは改ざん検知には使えるが暗号化ではない。機密性には認証付き暗号を利用する。具体方式と鍵管理はInfrastructure設計で決める。

## Observerと監査

Journal Observerはログ、OpenTelemetry、CloudWatch、監査基盤などへの出力を担う。Execution Transportとはインターフェースを分離する。

長期保存と監査はExecution Transportの責務とせず、Journal Observerの出力先へ委ねる。
