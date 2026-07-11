# D060: Typed Outcome Store Contract

Status: Decided

## Context

MVPはDeferred Operation完了後にOutcomeをOperation IDで取得し、Canonical Journalとは独立したRetentionを適用する。PostgreSQL Table Layoutは一対一の`outcomes` Tableを確定しているが、PHP Outcome Storeが復元済みOutcomeを返すか、Codec済みPayloadを返すかは未確定だった。

Codec済みRecordを公開すると、ApplicationがSchema Version、Class Hydration、Payload Encodingを理解する必要があり、PostgreSQLやMVP Codecの都合が利用側へ漏れる。一方、復元済みOutcomeを返せば既存の`Outcome` Marker、`#[Returns]` Metadata、Canonical JournalのTyped Record方針と揃えられる。

## Decision

[DECISION]

Public Outcome Storeは復元済み`Outcome`を含むTyped `OutcomeRecord`を扱う。

```text
OutcomeRecord
  operationId
  outcome
  completedAt
```

Portは読書きを分離する。

```text
OutcomeReader  find(OperationId): ?OutcomeRecord
OutcomeWriter  save(OutcomeRecord): void
OutcomeStore   Reader + Writer
```

Outcome Type、Schema Version、Encoded PayloadはPersistence Adapter内部のRecordとCodecへ閉じる。Public API利用者はPayloadをDecodeしない。

Outcome RecordはOperation IDごとに一件のImmutableな完了結果とする。同じOperation IDへの二重保存、未知Schema、Outcomeでない復元型、不正Payloadは専用Outcome Store Exceptionで拒否する。

Worker成功完了時はOperation State、Canonical Journal、Outcomeを同じDatabase TransactionでCommitする。Outcome保存に失敗した場合、完了StateとJournalもRollbackする。

[/DECISION]

## Consequences

[CONSEQUENCES]

- ApplicationはOperation IDからDomain Outcomeを直接取得できる。
- Persistence Schema VersionとEncodingをAdapter内部で進化させられる。
- Public RecordとReader／Writer／Storeが新しい安定Contractになる。
- PostgreSQL AdapterはOutcome Classの検証、Encode／Hydrate、重複排除を担う。
- Deferred Worker StorageへOutcome Writerを必須注入し、完了Transactionへ参加させる必要がある。
- Outcome RetentionはTyped Readerとは独立してEncoded Rowを削除できる。

[/CONSEQUENCES]
