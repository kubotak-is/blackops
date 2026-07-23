# Data Retention and Deletion

## Retention Policy

次を別々のPolicyで管理する。

```text
transportPayloadRetention
journalRetention
outcomeRetention
deadLetterRetention
idempotencyRecordRetention
```

Operation Typeまたは監査区分で上書き可能とする。具体的な既定期間はConfig仕様で定める。

## Tombstone

Terminal OperationのTransport Payload保持期限が切れた場合、Operations行のStateと識別情報は残し、Encoded PayloadとContextだけを消去する。

```text
encoded_payload    nullable
encoded_context    nullable
payload_purged_at  timestamptz nullable
```

未完了Operationの実行Payloadは削除しない。

Tombstone化後のOperationはReplayできず、追跡と監査だけが可能である。

## 外部キー

OutcomeなどDeferred Operationに所有される子TableからOperationsへの外部キーは `ON DELETE RESTRICT` とする。Cascade Deleteは使用しない。Retention Serviceが各Policyと依存関係に従って、明示的な順序で削除する。

`retention_holds.operation_id` と `retention_purge_audits.operation_id` は型付き識別値として保持し、Operationsへの外部キーを持たない。Operations行を作らないInline OperationもHoldとPurge Auditの対象にできる。Retention Service自体はInline／DeferredのどちらのOperations行も削除しない。

## Legal Hold

Operation単位のLegal Holdを設ける。

Hold中はTransport Payload、Canonical Journal、Outcome、Dead Letter、Idempotency Recordを含むすべてのRetention削除を停止する。

MVPでは管理UIを作らず、SchemaとPortを用意する。
