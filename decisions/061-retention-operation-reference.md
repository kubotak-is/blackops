# D061: Retention Operation Reference

Status: Decided

## Context

Canonical Journal RetentionはInlineとDeferredの両方をOperation ID単位で削除し、Holdで保護し、Purge Auditを独立して残す必要がある。

Deferred Operationは `operations` Tableに行を持つが、Inline OperationはCanonical `journal` にだけOperation IDを持つ。現在の `retention_holds.operation_id` と `retention_purge_audits.operation_id` は `operations.operation_id` への外部キーを持つため、Inline JournalへのHold設定とPurge Audit保存ができない。

## Decision

`retention_holds.operation_id` と `retention_purge_audits.operation_id` はUUIDの識別値として保持するが、`operations` Tableへの外部キーは持たない。

Versioned Migrationで既存の `retention_holds_operation_id_fkey` と `retention_purge_audits_operation_id_fkey` を削除する。Programmatic Integration Test Schemaも同じ物理形状に揃える。

Journal RetentionはOperation IDごとの最新 `occurred_at` を保持期間の基準とし、Active Holdがある場合は候補化と実削除の両方で停止する。

## Consequences

- Inline／DeferredのどちらにもHoldを設定でき、Journal PurgeでOperation ID付きAuditを残せる。
- Purge AuditはOperations行の存在に依存せず、削除対象から独立した証跡となる。
- Database外部キーではなく、型付き `OperationId`、Hold Store、Purge Serviceが参照の完全性を管理する。
- JournalにPlan後の新規Recordが追加された場合、実削除時の再確認で安全側にSkipする。
