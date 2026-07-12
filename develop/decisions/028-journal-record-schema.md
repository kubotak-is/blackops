# D028: Journal Record Schema

Status: Decided

## Context

JournalはBlackOpsの中核であり、Lifecycleの追跡、障害解析、監査、Operationの復元に利用する。

時刻は観測用であり、TimestampやUUIDv7だけでは厳密な順序を保証しないと決定した。ここでは全Lifecycle Eventに共通するJournal Recordの論理Schema、Event名、順序情報、Event固有Dataの配置を決める。

物理出力はJSON Lines、OTel、CloudWatch等で異なってよいが、論理Schemaは共有する。

## Question 1: 共通Envelope

次の構造を基本とするか。

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

### Options

- A: このNested Envelopeを採用する
- B: 全FieldをFlatに配置する
- C: 最小共通Fieldだけを定め、残りはAdapterごとに任せる

### Recommendation

Aを推奨する。Operation、Attempt、Actor、Traceの名前空間が分かれ、Application Logの共通Envelopeとも揃えやすい。

[ANSWER]

A

[/ANSWER]

## Question 2: EventのWire Name

### Options

- A: `operation.received` のような小文字のDot-separated Name
- B: `OperationReceived` のようなPascalCase Name
- C: `operation_received` のようなsnake_case Name

### Recommendation

Aを推奨する。

PHPではEnum Caseを `OperationReceived` とし、外部Schemaの値だけを `operation.received` にできる。Operation系、Attempt系を名前空間として扱いやすい。

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

[ANSWER]

A

[/ANSWER]

## Question 3: Lifecycle順序

### Options

- A: Operationごとの単調増加 `sequence` を全Journal Recordへ必須とする
- B: `previousRecordId` によるChainを作る
- C: 順序Fieldは持たず、状態遷移規則とTimestampから復元する

### Recommendation

Aを推奨する。

`sequence` は1から開始し、同じOperation ID内で重複せず単調増加する。InlineではRuntime、Deferredでは永続化したOperation Stateが次値を管理する。Timestampが同じでも順序を確定でき、欠番も検知できる。

[ANSWER]

A

[/ANSWER]

## Question 4: Event固有Data

### Options

- A: Event固有Fieldを `data` Objectへ格納する
- B: 共通EnvelopeのTop Levelへ展開する
- C: Eventごとに完全に別のRecord ClassとSchemaを持つ

### Recommendation

Aを推奨する。

共通Envelopeを安定させながら、Receivedの再現用Payload、FailedのError、RejectedのReason等をEventごとにSchema化できる。`data` は任意配列ではなく、Eventごとの型付きData Objectから生成する。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

Lifecycle Journal Recordは、Operation、Attempt、Actor、Trace、Event Dataを名前空間化したNested Envelopeを共通論理Schemaとする。

Lifecycle EventのWire Nameは小文字のDot-separated形式とする。PHPのEnum CaseはPascalCaseを使用できる。

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

すべてのJournal Recordは、同一Operation内で1から始まり、重複せず単調増加する `sequence` を必須で持つ。TimestampおよびUUIDv7はLifecycle順序の正本にしない。

Event固有FieldはTop Levelへ展開せず、`data` Objectへ格納する。`data` は任意配列ではなくEventごとの型付きData Objectから生成する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- JSON Lines、OTel、CloudWatch等のAdapterが共通の論理Schemaを共有できる。
- Operation、Attempt、Actor、TraceのField衝突を避けられる。
- Dot-separated Event名によってOperation系とAttempt系を機械的に分類できる。
- `sequence` によって同一TimestampでもOperation内の順序を確定し、重複や欠番を検知できる。
- Inline RuntimeとDeferred永続Stateは、安全に次のSequenceを割り当てる責務を持つ。
- Event固有Dataを型検査・Versioning・Sensitive Filteringの対象にできる。
- 共通EnvelopeのSchema VersionとOperation PayloadのSchema Versionは別のFieldとして管理する。

[/CONSEQUENCES]
