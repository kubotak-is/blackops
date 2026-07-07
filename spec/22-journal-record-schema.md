# Journal Record Schema

## 共通Envelope

Lifecycle Journal Recordは次のNested Envelopeを共通論理Schemaとする。

```json
{
  "recordId": "019...",
  "schemaVersion": 1,
  "kind": "journal",
  "event": "operation.received",
  "occurredAt": "2026-07-02T12:34:56.123456Z",
  "sequence": 1,
  "operation": {
    "id": "019...",
    "type": "report.generate",
    "schemaVersion": 1,
    "strategy": "deferred",
    "correlationId": "019...",
    "causationId": null
  },
  "attempt": null,
  "actors": {},
  "trace": {},
  "data": {}
}
```

物理出力形式はAdapterごとに異なってよいが、論理Fieldと意味を維持しなければならない。

共通Envelopeの `schemaVersion` と、`operation.schemaVersion` は別にVersion管理する。

## Event Name

Wire Nameは小文字のDot-separated形式とする。

```text
operation.received
operation.accepted
attempt.started
attempt.succeeded
attempt.failed
operation.completed
operation.rejected
operation.failed
operation.dead_lettered
```

PHPのEnum Caseには対応するPascalCase名を使用する。

## Sequence

すべてのJournal Recordは、同一Operation内で1から始まり、重複せず単調増加する `sequence` を必須で持つ。

- Timestampは観測時刻であり、順序の正本ではない
- UUIDv7の辞書順をLifecycle順序の正本にしない
- Inline Runtimeは実行中の次Sequenceを管理する
- Deferred処理は永続化されたOperation Stateで次Sequenceを管理する
- Consumerは重複Sequenceと欠番を検知できる

## Event Data

Event固有Fieldは `data` Objectへ格納する。

`data` は任意配列ではなく、Eventごとの型付きData Objectから生成する。Receivedの再現用Payload、FailedのError、RejectedのReason等は、それぞれ固有Schemaを持つ。
