# D030: Lifecycle Event Data

Status: Decided

## Context

Journal Recordは共通EnvelopeとEventごとの型付き `JournalData` で構成することを決定した。

次に、Operationの復元性と監査価値を保ちながら、OperationValue、Outcome、例外等をどこまでJournal Dataへ含めるかを決める。

ここでいうCanonical DataはFramework内部の論理Recordである。Observerへ渡す前にはSensitive Filterを適用し、Durable Storeへ完全Dataを保存する場合は必要な暗号化を適用する。

## Question 1: OperationReceived Data

### Options

- A: Operationを再現できるCanonical Payloadを含め、出力先ごとに安全なProjectionへ変換する
- B: JournalにはRedacted Payloadだけを含め、完全PayloadはExecution Transportだけに保存する
- C: JournalにはPayloadを一切含めない

### Recommendation

Aを推奨する。

BlackOpsの「JournalからOperationを追跡・再現できる」という中核思想を保てる。完全Payloadを無条件にLogへ出す意味ではなく、Observerには除外・マスク済みProjectionだけを渡す。

```text
OperationReceivedData
  operationPayload
  idempotencyKeyHash?
```

[ANSWER]

A

[/ANSWER]

## Question 2: OperationCompleted Data

### Options

- A: 型とSchema Versionを含むCanonical Outcomeを記録する
- B: Outcome Typeだけを記録し、値はOutcome Storeだけへ保存する
- C: 完了した事実だけを記録する

### Recommendation

Aを推奨する。

結果を含めてOperationの全体像を追跡でき、Deferred Outcome取得や監査にも再利用できる。Observer向けProjectionとRetentionはPayload同様に分離する。

```text
OperationCompletedData
  outcomeType
  outcomeSchemaVersion
  outcomePayload
```

[ANSWER]

A

[/ANSWER]

## Question 3: Failure Data

AttemptFailedとOperationFailedには、次の安全な構造化Errorを記録する案とする。

```text
error.type
error.code?
error.message
error.retryable
error.fingerprint
error.details
```

Stack Trace、File Path、引数値は機密情報を含み得るため、Canonical Journalの必須Fieldにはしない。開発環境または保護された診断Sinkへ別のApplication Logとして出力できる。

### Options

- A: この安全な構造化Errorを採用する
- B: Stack TraceもCanonical Journalへ必須で含める
- C: Error Codeだけを記録する

### Recommendation

Aを推奨する。監査・集計に必要な情報を保ちつつ、Journalの長期保存による漏えいリスクを抑えられる。

[ANSWER]

A

[/ANSWER]

## Question 4: Dataを持たないEvent

`operation.accepted`、`attempt.started`、`attempt.succeeded` の追加情報がない場合をどう表すか。

### Options

- A: 共通の `EmptyJournalData` を使用し、Wire上は `{}` とする
- B: Eventごとに空のData Classを作る
- C: `data` を `null` または省略する

### Recommendation

Aを推奨する。共通Envelopeでは `data` を常にObjectとして維持しつつ、意味のないClass増加を避けられる。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

`OperationReceivedData` はOperationを再現できるCanonical Payloadを保持する。Observer等の出力先へはSensitive Filterを通した安全なProjectionを渡す。

`OperationCompletedData` はOutcome Type、Outcome Schema Version、Canonical Outcome Payloadを保持する。Payloadと同様に、Observer向けProjectionおよびRetention Policyを分離する。

`AttemptFailedData` と `OperationFailedData` は、次の安全な構造化Errorを保持する。

```text
error.type
error.code?
error.message
error.retryable
error.fingerprint
error.details
```

Stack Trace、File Path、引数値はCanonical Journalの必須Fieldにせず、開発環境または保護された診断Sink向けApplication Logとして扱う。

追加Dataを持たないEventは共通の `EmptyJournalData` を使用し、Wire上は空Object `{}` とする。`data` Fieldは省略または `null` にしない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Operationの入力から結果までをCanonical Journalで追跡・再現できる。
- Deferred Outcome取得や監査でCanonical Outcomeを再利用できる。
- Canonical Payloadを無条件に外部Logへ出力してはならず、Observer前のSensitive Projectionが必須となる。
- Failureを型、Code、再試行可能性、Fingerprintで集計できる。
- Stack Trace等の診断情報と長期保存されるLifecycle Journalを分離できる。
- 共通Envelopeの `data` は常にJSON Objectとなり、Consumer側の分岐を減らせる。

[/CONSEQUENCES]
