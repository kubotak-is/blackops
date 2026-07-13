# Data Retentionを運用する

BlackOpsはTransport Payload、Canonical [Journal](glossary.md#journal)、[Outcome](glossary.md#outcome)、[Dead Letter](glossary.md#dead-letter)を独立した保持期間で管理します。Productionでは4つの期間を明示し、未設定のPolicyでPurgeを実行しないでください。

Public Console Kernelは`config/retention.php`の4期間、`policy_ref`、`actor`をRetention Plan／PurgeとSchedulerで共有します。Command Optionを省略すると、このAccepted Policyを使います。

候補を確認するときは、副作用のないPlan Commandを使います。

```text
blackops:retention:plan
  --transport-payload-days=7
  --journal-days=30
  --outcome-days=14
  --dead-letter-days=90
```

Purge Commandは`--dry-run`または`--confirm`のどちらかを要求します。Confirm時は監査用のPolicy ReferenceとActorも指定します。

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

Kernel構成、`list`、`help`ではRetention ConnectionやPurge Serviceを構成せず、Purgeも実行しません。`blackops:retention:purge --confirm`または明示的なScheduler Commandだけが変更を開始します。

## Journal Retention

PlannerはOperation IDごとの最新Record時刻からJournalの期限を計算します。期限切れになると、そのOperation IDに属するJournal Recordをまとめて削除します。Inline OperationとDeferred Operationを同じ規則で扱い、Operations行は削除しません。

Plan後にJournalが追加された場合やActive Holdを設定した場合、PurgeはそのOperationを安全側にSkipします。次回のPlanで最新状態から再評価してください。

## Retention Hold

[Retention Hold](glossary.md#retention)はOperation IDを指定して保持期間による削除を止めます。Inline OperationにもOperations行を追加せずHoldを設定できます。HoldがActiveな間、PlannerとPurgeはPayload、Journal、Outcome、Dead Letterを対象外にし、明示解除後に再び候補へ含めます。

## Purge Audit

Purgeは実際に変更または削除した件数を、対象とは独立したPurge Auditへ保存します。AuditはOperation ID、対象、件数、Policy、実行時刻、Actorだけを持ち、Journal Payload、Outcome、Error本文を含みません。PurgeはJournal削除とAudit保存を同じDatabase Transactionで行い、Audit保存に失敗すると削除もRollbackします。

ApplicationはDatabase Audit StoreをPrimaryとし、System Logを付加するfail-closed Audit PortをPurge Serviceへ渡します。Database AuditまたはSystem Logのどちらかが失敗すると、PurgeはDatabase変更をRollbackします。SchedulerやCLIは障害を成功扱いせず、Log Backendの復旧後にPlanから再実行してください。

System Log書き込み後にDatabase Commitが失敗すると、Logだけが残る可能性があります。Audit IDで過剰Logを識別し、データ削除が成功したと推測しないでください。
