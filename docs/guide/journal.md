# Journal

Journalは、OperationのLifecycleで起きた事実を順序付きで追跡するための記録です。このPageでは、保存用のCanonical JournalとObserverへ渡すObserved Journalを区別し、現在のLifecycle Event、JSONL出力、Replay、運用上の保護境界を確認します。

JournalはApplication Log、Outcome、Execution Transport Payloadの代わりではありません。Application Logは診断メッセージ、Outcomeは正常完了時の型付き結果、Transport PayloadはWorkerへ配送する入力を扱い、JournalはLifecycleの事実を扱います。

## CanonicalとObservedを分ける

Canonical JournalはFrameworkがTyped RecordとしてPostgreSQLへ保護して保存する正本です。Operationの復元や監査に必要なデータを保持しますが、公開Observerへそのまま渡す契約ではありません。Canonical PostgreSQLの内部RowやPayloadは、このPageのJSON例が表すPublic Serializationではありません。

Observerへ渡すのは、FrameworkのSensitive Filterを通過したObserved Projectionです。`#[Sensitive]` の既定はOmitで、必要に応じてMask（例ではActor IDを `[masked]` として表示）または鍵付きHMAC Hashを選べます。Observer AdapterはCanonical Payloadへアクセスせず、Observed Projectionへ追加のFilterだけを適用します。

```text
Canonical Journal (保護された正本)
  -> Sensitive Filter
  -> Observed Journal Projection
  -> JSONLなどのObserver
```

Sensitive値がObservedから消えることは、保存時暗号化の代わりにはなりません。Canonical Storeには保存時暗号化、Access Control、Retention、Key Rotationを別々の運用Policyとして設定してください。

## Lifecycle Event

現在の標準Eventは次の10件です。Event名は小文字のdot-separated Wire Nameで、`sequence` はOperationごとに1から始まる単調増加値です。

| Event | 記録する事実 |
| --- | --- |
| `operation.received` | Operationを受付した |
| `operation.accepted` | Deferred実行をDurableに引き受けた |
| `attempt.started` | Attemptを開始した |
| `attempt.succeeded` | Handlerが成功結果を返した |
| `attempt.failed` | Attemptが失敗した |
| `attempt.retry_scheduled` | Supervision Policyが次のRetryを予定した |
| `operation.completed` | Operationの最終処理を完了した |
| `operation.rejected` | Validation、Authorization、Business Ruleなどで拒否した |
| `operation.failed` | Retryせず最終失敗にした |
| `operation.dead_lettered` | Deferred Operationを隔離した |

`operation.accepted` はDeferredだけのEventです。Inlineは `operation.received` から直接 `attempt.started` へ進みます。`attempt.succeeded` はHandlerの事実、`operation.completed` はOperationの最終処理まで終わった事実です。DeferredではTyped OutcomeをOutcome Storeへ保存しますが、InlineではHTTP Responseだけへ返し、Outcome Store Rowを作成しません。Journal Eventの `data` とOutcome Store Rowは別の契約です。Terminal EventはCompleted、Rejected、Failed、Dead Letteredのいずれか一つです。

## Observed JSONL

JSONL Observerは1行に1つのObserved Recordを、`JsonlJournalRecordEncoder` の形式で出力します。これはCanonical PostgreSQL Journalの公開シリアライズではありません。日時はUTCのRFC3339マイクロ秒（`Y-m-d\\TH:i:s.u\\Z`）です。

```jsonl
{"schemaVersion":1,"kind":"journal","recordId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","event":"operation.received","occurredAt":"2026-07-02T12:34:56.123456Z","sequence":1,"operation":{"id":"019f32ab-2be0-7b38-a0a7-1ab2f9687701","type":"report.generate","schemaVersion":1,"strategy":"deferred","correlationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687701","causationId":null,"actors":{"origin":{"id":"[masked]","type":"user"},"authorization":null,"execution":{"id":"[masked]","type":"http"}}},"attempt":null,"data":{"value":{"reportName":"weekly"}}}
{"schemaVersion":1,"kind":"journal","recordId":"019f32ab-2be0-7b38-a0a7-1ab2f9687699","event":"operation.accepted","occurredAt":"2026-07-02T12:34:56.223456Z","sequence":2,"operation":{"id":"019f32ab-2be0-7b38-a0a7-1ab2f9687701","type":"report.generate","schemaVersion":1,"strategy":"deferred","correlationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687701","causationId":null,"actors":{"origin":{"id":"[masked]","type":"user"},"authorization":null,"execution":{"id":"[masked]","type":"http"}}},"attempt":null,"data":{}}
{"schemaVersion":1,"kind":"journal","recordId":"019f32ab-2be0-7b38-a0a7-1ab2f9687700","event":"attempt.started","occurredAt":"2026-07-02T12:34:57.123456Z","sequence":3,"operation":{"id":"019f32ab-2be0-7b38-a0a7-1ab2f9687701","type":"report.generate","schemaVersion":1,"strategy":"deferred","correlationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687701","causationId":null,"actors":{"origin":{"id":"[masked]","type":"user"},"authorization":null,"execution":{"id":"[masked]","type":"worker"}}},"attempt":{"id":"019f32ab-2be0-7b38-a0a7-1ab2f9687702","number":1,"startedAt":"2026-07-02T12:34:57.123456Z"},"data":{}}
```

## JSONL Parameters

### Top-level Record

| Parameter | Type | 説明 |
| --- | --- | --- |
| `schemaVersion` | `integer` | Observed RecordのEnvelope Schema Version。現在は`1`。 |
| `kind` | `string` | 固定値`journal`。 |
| `recordId` | `string` | Recordを識別するUUIDv7 String。Operation IDとは別の値。 |
| `event` | `string` | 10個のLifecycle Eventのdot-separated Wire Name。 |
| `occurredAt` | `string` | Event発生時刻。UTC RFC 3339 Microseconds（`Y-m-d\\TH:i:s.u\\Z`）。 |
| `sequence` | `integer` | Operation内で1から始まる単調増加値。 |
| `operation` | `object` | Operationの識別、Schema、Strategy、相関、Actor Projection。 |
| `attempt` | `object \| null` | AttemptがないEventは`null`。ある場合はAttempt Object。 |
| `data` | `object` | Event固有のSensitive Projection済みObject。 |

### `operation`

| Parameter | Type | 説明 |
| --- | --- | --- |
| `operation.id` | `string` | Operationを識別するUUIDv7 String。全Recordで同じ値。 |
| `operation.type` | `string` | 安定したOperation Type ID（例：`report.generate`）。 |
| `operation.schemaVersion` | `integer` | Operation PayloadのSchema Version。Envelope Versionとは別。 |
| `operation.strategy` | `string` | 実行経路（`inline`または`deferred`など）。 |
| `operation.correlationId` | `string` | RootではOperation IDと同じUUID値。子Operationは親のCorrelationを引き継ぐ。 |
| `operation.causationId` | `string \| null` | 子Operationを発生させた親Operation IDと同じUUID値。Rootでは`null`。 |
| `operation.actors` | `object \| null` | ActorContextがない場合は全体が`null`。詳細は次のTable。 |

### `operation.actors`／Actor

| Parameter | Type | 説明 |
| --- | --- | --- |
| `operation.actors` | `object \| null` | ActorContext全体。存在する場合は`origin`、`authorization`、`execution`を必ず持つ。 |
| `operation.actors.origin` | `object \| null` | 元のRequest／Actor。ObservedではIDを`[masked]`へ置換し、Actorがなければ`null`。 |
| `operation.actors.authorization` | `object \| null` | 認可判断に使ったActor。ObservedではIDを`[masked]`へ置換し、Actorがなければ`null`。 |
| `operation.actors.execution` | `object` | 実行ProcessのActor。ObservedではIDを`[masked]`へ置換する。ActorContextがある場合は必須。 |
| `operation.actors.*.id` | `string` | Actor ID。Observed Projectionでは`[masked]`。 |
| `operation.actors.*.type` | `string` | Actorの種別（例：`user`、`http`、`worker`）。 |

### `attempt`

| Parameter | Type | 説明 |
| --- | --- | --- |
| `attempt` | `object \| null` | Attemptを開始していないEventは`null`。 |
| `attempt.id` | `string` | Attemptを識別するUUIDv7 String。 |
| `attempt.number` | `integer` | Operation内のAttempt番号。1以上。 |
| `attempt.startedAt` | `string` | Attempt開始時刻。UTC RFC 3339 Microseconds。 |

### Event固有`data`

| Event | Parameter | Type | 説明 |
| --- | --- | --- | --- |
| `operation.received`（通常） | `data.value` | `object` | 受付時のOperationValueをSensitive ProjectionしたObject。 |
| `operation.received`（Ephemeral） | `data` | `object` | Ephemeral OutcomeではJournalRecordFactoryがEmptyJournalDataを使うため`{}`。 |
| `operation.accepted` | `data` | `object` | DeferredをDurableに受理した事実。EmptyJournalDataのため`{}`。 |
| `attempt.started` | `data` | `object` | Attempt開始の事実。EmptyJournalDataのため`{}`。 |
| `attempt.succeeded` | `data` | `object` | Handlerが成功結果を返した事実。EmptyJournalDataのため`{}`。 |
| `attempt.failed` | `data.errorType` | `string` | Attempt Failureの型名。 |
| `attempt.failed` | `data.errorMessage` | `string` | Exception Message。SecretをMessageへ含めない。 |
| `attempt.failed` | `data.retryable` | `boolean` | Supervision PolicyがRetry可能と判定したか。 |
| `attempt.retry_scheduled` | `data.failedAttemptId` | `string` | Retry対象AttemptのUUIDv7 String。 |
| `attempt.retry_scheduled` | `data.nextAttemptNumber` | `integer` | 次に開始するAttempt番号。 |
| `attempt.retry_scheduled` | `data.scheduledAt` | `string` | Retry予定時刻。UTC RFC 3339 Microseconds。 |
| `attempt.retry_scheduled` | `data.delayMilliseconds` | `integer` | RetryまでのDelay（ミリ秒）。0以上。 |
| `operation.completed` | `data.outcome` | `object` | Completed時のOutcomeをSensitive ProjectionしたObject。Outcome Store Rowそのものではない。 |
| `operation.rejected` | `data.reason.category` | `string` | Rejection Category。 |
| `operation.rejected` | `data.reason.code` | `string` | 公開可能なRejection Code。 |
| `operation.rejected` | `data.reason.violations` | `array<object>` | Violation Objectの配列。Raw Rejection Valueは含めない。 |
| `operation.rejected` | `data.reason.violations[].field` | `string` | 拒否対象Field名。 |
| `operation.rejected` | `data.reason.violations[].rule` | `string` | 違反したValidation Rule。 |
| `operation.rejected` | `data.reason.violations[].code` | `string` | 公開可能なViolation Code。 |
| `operation.failed` | `data.errorType` | `string` | Operation Failureの型名。 |
| `operation.failed` | `data.errorMessage` | `string` | Exception Message。SecretをMessageへ含めない。 |
| `operation.failed` | `data.retryable` | `boolean` | FailureがRetry可能か。 |
| `operation.dead_lettered` | `data.finalAttemptId` | `string \| null` | 隔離された最終AttemptのUUIDv7 String。未指定なら`null`。 |
| `operation.dead_lettered` | `data.finalAttemptNumber` | `integer \| null` | 隔離された最終Attempt番号。未指定なら`null`。 |
| `operation.dead_lettered` | `data.reasonType` | `string` | Dead Letter理由の型名。 |
| `operation.dead_lettered` | `data.reasonMessage` | `string` | Reason Message。SecretをMessageへ含めない。 |
| `operation.dead_lettered` | `data.movedAt` | `string` | Dead Letterへ移動した時刻。UTC RFC 3339 Microseconds。 |

`data` はEvent固有のProjectionです。共通EnvelopeへEvent固有Fieldを追加せず、EncoderはScalar、配列、日時、Framework IdentifierをJSONへ正規化し、任意のApplication Objectの`__toString()`は呼び出しません。対応しないObjectは`null`にします。EmptyJournalDataを使うEvent／Variantも省略していません。

## JSONLの設定

JSONLの出力先は、Application Configurationで絶対Pathを指定します。相対Pathや暗黙のCurrent Directoryに依存しないでください。編集するApplication-owned Fileは `config/journal.php` です。既定Configの書式は[Observed Journal](configuration.md#observed-journal)を参照してください。

```php
return [
    'jsonl' => [
        'enabled' => true,
        'path' => dirname(__DIR__) . '/var/log/journal.jsonl',
        'delivery' => 'best_effort',
    ],
];
```

- 親Directoryは事前に存在し、実行Processから書込み可能にする
- 出力失敗時の `best_effort` はObserver FailureをOperationの失敗にせず処理を継続する。監査要件のある `required` は書込み失敗をOperationのエラーとして扱う
- File PermissionとDirectory Permissionを最小権限にし、Operator以外の読み取りを許可しない
- Rotation、圧縮、Backup、Retention、Purgeを運用Schedulerで管理し、保持期間とLegal HoldをCanonical／Observedそれぞれに定める
- Canonical Storeの保存時暗号化、鍵の生成・保管・Rotation・失効はFrameworkのJournal設定APIではなく、Application／Infrastructure／運用の責務としてApplication Secret Storeまたは組織のKMSで管理する。鍵とJSONLを同じDirectoryへ置かない

JSONLはObserver Projectionです。Canonical StoreのRetention、Access Control、暗号化、鍵管理を省略する設定ではありません。

## Replayの境界

Observer Replayは、Canonical JournalのRecordを現在のObserved Projectionへ変換してObserverへ再配送する運用操作です。Record ID、Operation ID、Sequence、Occurred Atを保ち、at-least-onceで届くためTargetはRecord IDを冪等性Keyとして扱います。詳細なSelector、Dry-run、Checkpoint、Auditは[Observer Replay](observer-replay.md)を参照してください。

Observer Replayは、完了済みOperationをもう一度Handlerへ実行するOperation Replayとは別物です。Operation Replayは新しいOperation IDを発行し、元OperationへのCausationを記録するApplicationの再実行手順です。Observer ReplayからHandlerを呼び出したり、Canonical Rowを更新したりしません。

## OpenTelemetryとの関係

Repository mainにはOpenTelemetryのAdapter、Exporter、Configurationは実装されていません。したがって、SpanやMetricの出力を現行Public Contractとして構成しないでください。

将来の候補方向として、Operation／AttemptをSpan、Lifecycle EventをSpan Event、Retry・Rejected・Failure・Dead LetterをMetric、Correlation／CausationをTrace Contextへ写像できます。これは設計候補であり、Field名・Sampling・Error処理を含むPublic Contractではありません。実装される場合も、Adapterが受け取るのはCanonicalではなくObserved Projectionです。

次は[Lifecycle](operation-lifecycle.md)で状態遷移を確認し、保存期間は[Retention](retention.md)、再配送は[Observer Replay](observer-replay.md)へ進んでください。
