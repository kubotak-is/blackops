# Logging and Traceability

## FW Logger

FW LoggerはPSR-3 `LoggerInterface` を実装する。

現在のExecution ScopeからOperation Contextを自動付与し、実際の出力をMonolog等のPSR-3 Loggerへ委譲できるDecoratorとして設計する。

HandlerやServiceはLoggerをConstructor Injectionして利用する。各Log呼び出しでOperation IDを手動指定する必要はない。

## 自動Context

Operation内のApplication Logへ、存在する次の情報を自動付与する。

- Operation ID
- Operation Type ID
- Attempt ID
- Correlation ID
- Causation ID
- Execution Strategy
- origin／execution／authorization Actor Type
- マスク済みorigin／execution／authorization Actor ID

Actor IDはRaw値を出力せず、固定MaskまたはOmitする。Actor属性とOperationValue本体は、漏えいとログ肥大化を避けるため自動付与しない。

## Record Kind

Application LogとLifecycle Journal Logは共通の構造化Envelopeを使い、Record Kindで区別する。

```json
{
  "schemaVersion": 1,
  "kind": "application",
  "level": "info",
  "message": "Order persisted",
  "operation": {
    "id": "019...",
    "type": "order.create"
  },
  "context": {
    "orderId": "order-123"
  }
}
```

```json
{
  "schemaVersion": 1,
  "kind": "journal",
  "event": "operation.completed",
  "operation": {
    "id": "019...",
    "type": "order.create"
  }
}
```

## Lifecycle Journal Log

FWは次のLifecycle Eventを自動生成する。

- OperationReceived
- OperationAccepted
- AttemptStarted
- AttemptSucceeded
- AttemptFailed
- AttemptRetryScheduled
- OperationCompleted
- OperationRejected
- OperationFailed
- OperationDeadLettered

ユーザーコードが同じLifecycle Logを手動出力する必要はない。

## Execution Scope

FW RuntimeはOperation実行境界でExecution Scopeを開始し、`finally` で必ず終了する。

ScopeはStackとして管理し、ネスト実行後に親Contextを復元する。PHP-FPM Request間、長期WorkerのOperation間、Fiber並行実行間でContextを混線させない。

LoggerはScope Providerから現在のContextを取得する。Operation外のLogは、Operation IDを持たないSystem／Application Logとして記録する。

Framework Error Logは固定MessageとSafe Failure Classificationを使い、Operation ID、Attempt ID、Correlation ID、Causation IDをApplication LogおよびCanonical Journalと相関させる。Exception Typeは記録できるが、Exception Message、Stack Trace、Raw Value、Credentialを含めない。Failure Journalの記録成否は安全な真偽値として記録し、二次障害がある場合は`failure_recording_failed`とException Typeだけを別Fieldへ記録する。

## Field保護

FW予約FieldはユーザーContextから上書きできない。

ユーザーContextは予約Fieldとは別の `context` namespaceへ格納する。開発環境では予約Fieldの指定を例外または警告として検出する。

## Sensitive Filtering

Logger ContextとJournal Recordは、Adapterへ渡す前にFW共通PipelineでSensitive Filterを必ず通す。

`#[Sensitive]` Metadata、予約Key、登録済みRedactorを利用する。各Adapterは出力先固有の追加Filterを適用できる。

## OpenTelemetry

OTel Trace ID／Span IDはOperation ID／Correlation IDと別に保持する。

ExecutionContextへOTel Contextを伝播し、構造化Logへ双方のIDを記録して関連付ける。UUIDv7のOperation IDをOTel Trace IDとして流用しない。

## Delivery Policy

Lifecycle Journal Recordは次のDelivery Policyを選択できる。

| Policy | 保証 |
| --- | --- |
| BestEffort | Observer失敗でもOperationを継続 |
| Required | 指定出力先への記録成功を処理継続の条件とする |
| Durable | Local StoreまたはOutboxへの記録成功を条件とし、外部Sinkへ後送する |

Global既定値はConfigで設定し、Operation Definitionの `#[JournalDelivery(...)]` で上書きできる。既定はBestEffortとする。

Manifest Compilerは、Required／Durableに必要なObserverまたはStoreがない構成を拒否する。

通常のApplication Log出力失敗ではOperationを継続し、Observer Failure Metric、標準エラー、予備Sinkなどへ可能な範囲で通知する。

Framework Error LogのBackend FailureもPrimary Throwable、Terminal Lifecycle、HTTP Responseを変更しない。

## Multiple Observers

複数Observerは独立して実行し、個別の成功と失敗を集約する。

Delivery Policyは、Required対象となるObserverまたはStoreを明示できるようにする。

## SamplingとLevel

Application Debug／Info LogはSampling可能とする。標準Lifecycle Journal LogはSamplingしない。

既定Level：

| Event | Level |
| --- | --- |
| Received / Accepted / Started / Succeeded / Completed | Info |
| Rejected | Notice |
| AttemptFailed（Retry予定） | Warning |
| RetryScheduled | Info |
| OperationFailed / DeadLettered | Error |

AdapterはLevel Mappingを上書きできる。

## BufferingとFlush

FWはOperation終了、Worker Loop終了、Process ShutdownでFlush Hookを呼ぶ。

AdapterはBuffer方式を選択できるが、Durable PolicyではMemory Bufferだけを記録成功とみなさない。

## Retention

FWはRecord Kind、監査区分、Operation Type等に基づくRetention Metadataと推奨値を表現する。

実際の保持と削除は各SinkおよびInfrastructure Adapterへ委ねる。
