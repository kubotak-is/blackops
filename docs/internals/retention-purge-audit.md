# Retention Purge Audit

Retention Purge Auditは、Retention削除の実行結果をPayloadなしで保存するためのPublic Contractである。

Purge Auditは削除対象のJournalへ追記しない。Transport Payload、Journal、Outcome、Dead Letterなど、削除やTombstone化の対象そのものが失われる場合でも、別の監査Recordとして残せるようにする。

## Identifier

`RetentionPurgeAuditId` はPurge Audit専用のUUIDv7 Identifierである。

他のFramework IDと同じく、小文字RFC 4122形式へ正規化し、UUID Version 7だけを受け入れる。公開APIはSymfony UIDなどの具体Library型に依存しない。

## Target

`RetentionPurgeTarget` はPurge Auditが記録する削除対象種別を表す。

```text
transport_payload
journal
outcome
dead_letter
```

この種別はRetention Policyの対象と対応するが、監査Recordでは実際にPurge処理が影響を与えた対象を明示するために独立した型として扱う。

## Policy Reference

`RetentionPolicyRef` はPurge実行時に適用されたPolicyを表す非空文字列Referenceである。

このReferenceはCredentialやPayloadを含めない。Policy名、Policy Version、Manifest由来の安定IDなど、後から運用者が追跡できる値を入れる。

## Audit Record

`RetentionPurgeAuditRecord` は次を保持する。

```text
audit_id
operation_id
target
affected_count
policy
purged_at
purged_by
```

`affected_count` は1以上である。0件のPurge試行、Dry Run、Plan生成結果はこのRecordでは表現しない。

`purged_at` は読み出し時にUTCへ正規化される。Storage実装はTimezone付き時刻として保存し、比較や検索ではUTC基準で扱う。

## Port

`RetentionPurgeAuditPort` はPurge Auditの保存実装を抽象化する。

```text
record
```

PostgreSQL Store、Purge Service、CLI、Scheduler Workerとの接続は後続Taskで扱う。System Logへも同じ事実を出力する場合は、Purge Service側でAudit StoreとLoggerへ別配送する。

## PostgreSQL Schema

`retention_purge_audits` TableはPurge結果をPayloadなしで保存する。

```text
audit_id
operation_id
target
affected_count
policy
purged_at
purged_by
created_at
```

`operation_id` はOperations Tableへ `ON DELETE RESTRICT` で参照する。削除対象Operationが消える場合でもAudit Recordの意味が壊れないよう、Cascade Deleteは使わない。

`PostgreSqlRetentionPurgeAuditStore` は `RetentionPurgeAuditPort` を実装し、Recordの各Fieldをそのまま保存する。StoreはPayloadやContextを受け取らず、System Logへの配送も担当しない。
