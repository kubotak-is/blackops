# D044: Data Retention and Deletion

Status: Decided

## Context

Operations、Canonical Journal、Outcomes、Dead Lettersを別Tableへ分けた。これらは機密性、復旧、監査、Deferred Outcome取得で必要期間が異なる。

単純なCascade Deleteは監査記録を意図せず消す可能性がある。一方、Operation PayloadをJournalと同じ期間保持すると、個人情報や機密情報を必要以上に残し得る。

## Question 1: Retention単位

### Options

- A: Transport Payload、Canonical Journal、Outcome、Dead Letterを別Policyで管理する
- B: Operation単位で全Tableを同時削除する
- C: Frameworkは削除機能を持たない

### Recommendation

Aを推奨する。

```text
transportPayloadRetention
journalRetention
outcomeRetention
deadLetterRetention
```

Operation Typeや監査区分で上書き可能にし、具体的な既定日数はConfig Decisionで定める。

[ANSWER]

A

[/ANSWER]

## Question 2: Operations行とPayload消去

Transport Payloadの保持期限が切れた後もJournalを残す場合をどうするか。

### Options

- A: Operations行のStateと識別情報は残し、Encoded Payload／Contextだけを消去してTombstone化する
- B: Operations行全体を削除する
- C: PayloadをJournalと同じ期間必ず保持する

### Recommendation

Aを推奨する。

```text
encoded_payload    nullable
encoded_context    nullable
payload_purged_at  timestamptz nullable
```

Terminal OperationだけをTombstone化し、未完了Operationの実行Payloadは削除しない。

[ANSWER]

A

[/ANSWER]

## Question 3: 外部キーと削除

### Options

- A: 外部キーは `ON DELETE RESTRICT` とし、Retention Serviceが明示的な順序で削除する
- B: すべて `ON DELETE CASCADE` とする
- C: 外部キーを一切使わない

### Recommendation

Aを推奨する。

誤ってOperations行を削除してJournalやOutcomeまで消すことを防ぐ。保持期限の異なる子RecordをRetention ServiceがPolicyに従って明示削除する。

[ANSWER]

A

[/ANSWER]

## Question 4: Legal Hold

### Options

- A: Operation単位のLegal Holdを設け、Retention削除を停止できるようにする
- B: Legal HoldはFramework外でのみ管理する
- C: Legal Holdを考慮しない

### Recommendation

Aを推奨する。

MVPでは管理UIを作らず、SchemaとPortだけを用意する。監査調査中のOperationを期限による自動削除から保護できる。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

Transport Payload、Canonical Journal、Outcome、Dead Letterは別々のRetention Policyで管理する。Operation Typeまたは監査区分で上書き可能とする。

Terminal OperationのTransport Payload保持期限が切れた場合、Operations行のStateと識別情報は残し、Encoded PayloadとContextだけを消去してTombstone化する。未完了Operationの実行Payloadは削除しない。

子TableからOperationsへの外部キーは `ON DELETE RESTRICT` とし、Retention ServiceがPolicyに従って明示的な順序で削除する。Cascade Deleteは使用しない。

Operation単位のLegal Holdを設け、Hold中はすべてのRetention削除を停止する。MVPでは管理UIを作らず、SchemaとPortを用意する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 復旧、監査、Outcome取得、Dead Letter調査の必要期間を独立して設定できる。
- Operation PayloadをJournalと同じ期間保持する必要がなく、機密情報の保持を短縮できる。
- Terminal Stateと追跡用識別情報を残したまま実行Payloadを消去できる。
- 誤操作によるCascadeでJournalやOutcomeが消えることを防げる。
- Retention Serviceは依存関係を理解した安全な削除順序を実装する必要がある。
- Legal Hold中のOperationを期限削除から保護できる。
- Tombstone化後のOperationはReplayできず、追跡と監査だけが可能になる。

[/CONSEQUENCES]
