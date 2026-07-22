# Lifecycle Event Data

## Canonical DataとProjection

Canonical DataはFramework内部の論理Recordである。

Observerへ渡す前にはSensitive Filterを適用する。Durable Storeへ完全Dataを保存する場合は、StoreのCapabilityに応じて必要な暗号化を適用する。

Canonical Dataを無条件に外部Logへ出力してはならない。

## OperationReceivedData

`OperationReceivedData` はOperationを再現できるCanonical Payloadを保持する。

```text
OperationReceivedData
  operationPayload
  idempotencyKeyHash?
```

Observerへは除外・マスク済みのProjectionを渡す。

## OperationCompletedData

`OperationCompletedData` はCanonical Outcomeを保持する。

```text
OperationCompletedData
  outcomeType
  outcomeSchemaVersion
  outcomePayload
```

Observer向けProjectionとRetention PolicyはCanonical Outcomeの保存から分離する。

## Ephemeral Operation Data

`EphemeralOutcome`を返すHTTP Inline Operationは、Password等のCredential InputとRaw Token等のResponseをCanonical Journalへ保存しない。

- `operation.received`は`EmptyJournalData`で受付事実だけを記録する
- `attempt.started`／`attempt.succeeded`は通常どおり記録する
- `operation.completed`は実際のEphemeral Outcomeではなく`EmptyOutcome`を持つ`OperationCompletedData`を記録する
- Rejected／Failedは既存のSafe Error Dataだけを記録し、入力／出力値を含めない

これによりLifecycle、Operation ID、時刻、Actor相関を維持しつつ、Ephemeral Operationを再現／再取得不能にする。

## Failure Data

`AttemptFailedData` と `OperationFailedData` は安全な構造化Errorを保持する。

```text
error.type
error.code?
error.message
error.retryable
error.fingerprint
error.details
```

Stack Trace、File Path、引数値はCanonical Journalの必須Fieldにしない。必要な場合は、開発環境または保護された診断Sink向けApplication Logとして出力する。

## EmptyJournalData

追加Dataを持たないEventは共通の `EmptyJournalData` を使用する。

Wire上は空Object `{}` とし、`data` Fieldを省略または `null` にしない。
