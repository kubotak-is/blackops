# Data Retention

BlackOpsはTransport Payload、Canonical Journal、Outcome、Dead Letterを独立した保持期間で管理する。Productionでは4つの期間を明示し、未設定のPolicyでPurgeを実行しない。

候補確認には副作用のないPlan Commandを使う。

```text
blackops:retention:plan
  --transport-payload-days=7
  --journal-days=30
  --outcome-days=14
  --dead-letter-days=90
```

Purge Commandは `--dry-run` または `--confirm` のどちらかを要求する。Confirm時は監査用のPolicy ReferenceとActorも指定する。

```text
blackops:retention:purge
  --confirm
  --transport-payload-days=7
  --journal-days=30
  --outcome-days=14
  --dead-letter-days=90
  --policy-ref=production-retention-v1
  --actor=system:retention
```

## Journal Retention

Journalの期限はOperation IDごとの最新Record時刻から計算する。期限切れになると、そのOperation IDに属するJournal Recordをまとめて削除する。Inline OperationとDeferred Operationを同じ規則で扱い、Operations行は削除しない。

Plan後にJournalが追加された場合やActive Holdが設定された場合、PurgeはそのOperationを安全側にSkipする。次回のPlanで最新状態から再評価される。

## Retention Hold

HoldはOperation IDを指定して保持期間による削除を止める。Inline OperationにもOperations行を追加せずHoldを設定できる。HoldがActiveな間はPayload、Journal、Outcome、Dead LetterのPlanと実削除から除外され、明示的な解除後に再び候補となる。

## Purge Audit

実際に変更または削除した件数は、対象とは独立したPurge Auditへ保存される。AuditはOperation ID、対象、件数、Policy、実行時刻、Actorだけを持ち、Journal Payload、Outcome、Error本文を含まない。Journal削除とAudit保存は同じDatabase Transactionで行われ、Audit保存に失敗すると削除もRollbackされる。
