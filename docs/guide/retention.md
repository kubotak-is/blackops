# Retention

BlackOpsはTransport Payload、Canonical [Journal](glossary.md#journal)、[Outcome](glossary.md#outcome)、[Dead Letter](glossary.md#dead-letter)、Idempotency Recordの5つを独立した保持対象として管理します。Project Rootの公開Retention Commandは4つの期間Optionだけを受け付けます。Idempotency Recordの第5期間は`config/retention.php`の`idempotency_record_days`で管理し、省略した場合は4つの基本期間の最長値を使います。`config/retention.php`の期間、`policy_ref`、`actor`はPlan／PurgeとSchedulerで共有するAccepted Policyです。

## 1. Planを確認する

Planは候補を読むだけで、DatabaseやJournalを変更しません。Application-owned `config/retention.php`で期間とPolicyを管理し、Project RootのHostでは次を実行します。

```bash
php blackops retention:plan \
  --transport-payload-days=7 \
  --journal-days=30 \
  --outcome-days=14 \
  --dead-letter-days=90
```

Containerから実行する場合は、同じ引数を`app`へ渡します。

```bash
docker compose run --rm app php blackops retention:plan \
  --transport-payload-days=7 \
  --journal-days=30 \
  --outcome-days=14 \
  --dead-letter-days=90
```

成功時の終了Codeは0です。次の形式を返します。`N`は候補件数で、候補がある場合は対象ごとのIDと期限も続きます。

```text
Retention plan
Total: N
transport_payload: N
journal: N
outcome: N
dead_letter: N
idempotency_record: N
```

## 2. PurgeをDry-runする

変更前にPurgeの対象を再確認します。`--dry-run`は副作用がなく、Planと同じ5対象の件数を返します。

```bash
php blackops retention:purge \
  --dry-run \
  --transport-payload-days=7 \
  --journal-days=30 \
  --outcome-days=14 \
  --dead-letter-days=90
```

```bash
docker compose run --rm app php blackops retention:purge \
  --dry-run \
  --transport-payload-days=7 \
  --journal-days=30 \
  --outcome-days=14 \
  --dead-letter-days=90
```

成功時の終了Codeは0です。出力は次のとおりで、`--dry-run`ではPurge Auditも保存しません。

```text
Retention purge dry run
Total: N
transport_payload: N
journal: N
outcome: N
dead_letter: N
idempotency_record: N
```

## 3. 承認済みのPurgeを実行する

対象とPolicyを確認した後だけ`--confirm`を使います。Policy ReferenceとActorはPurge Auditへ記録されます。

```bash
php blackops retention:purge \
  --confirm \
  --transport-payload-days=7 \
  --journal-days=30 \
  --outcome-days=14 \
  --dead-letter-days=90 \
  --policy-ref=production-retention-v1 \
  --actor=system:retention
```

Containerでは同じ確認済みCommandを次のように実行します。

```bash
docker compose run --rm app php blackops retention:purge \
  --confirm \
  --transport-payload-days=7 \
  --journal-days=30 \
  --outcome-days=14 \
  --dead-letter-days=90 \
  --policy-ref=production-retention-v1 \
  --actor=system:retention
```

変更が適用された場合は次の形式を返します。`total_affected`は実際に変更した件数です。

```text
Retention purge applied
planned: N
transport_payload_purged: N
dead_letters_deleted: N
idempotency_records_deleted: N
total_affected: N
```

`--confirm`だけがPayload、Journal、Outcome、Dead Letter、Idempotency Recordを変更し、Purge Auditを同じDatabase Transactionで保存します。`list`、`help`、Kernel構成の読み込みはPurgeを開始しません。Active Retention HoldのOperationはPlanとPurgeの対象外です。

## Journal Retention

PlannerはOperation IDごとの最新Record時刻からJournalの期限を計算します。期限切れになると、そのOperation IDに属するJournal Recordをまとめて削除します。Inline OperationとDeferred Operationを同じ規則で扱い、Operations行は削除しません。

Plan後にJournalが追加された場合やActive Holdを設定した場合、PurgeはそのOperationを安全側にSkipします。次回のPlanで最新状態から再評価してください。

## Retention Hold

[Retention](glossary.md#retention)はOperation IDを指定して保持期間による削除を止めます。Inline OperationにもOperations行を追加せずHoldを設定できます。HoldがActiveな間、PlannerとPurgeはPayload、Journal、Outcome、Dead Letter、Idempotency Recordを対象外にし、明示解除後に再び候補へ含めます。

## Purge Audit

Purgeは実際に変更または削除した件数を、対象とは独立したPurge Auditへ保存します。AuditはOperation ID、対象、件数、Policy、実行時刻、Actorだけを持ち、Journal Payload、Outcome、Error本文を含みません。PurgeはJournal削除とAudit保存を同じDatabase Transactionで行い、Audit保存に失敗すると削除もRollbackします。

ApplicationはDatabase Audit StoreをPrimaryとし、System Logを付加するfail-closed Audit PortをPurge Serviceへ渡します。Database AuditまたはSystem Logのどちらかが失敗すると、PurgeはDatabase変更をRollbackします。SchedulerやCLIは障害を成功扱いせず、Log Backendの復旧後にPlanから再実行してください。

System Log書き込み後にDatabase Commitが失敗すると、Logだけが残る可能性があります。Audit IDで過剰Logを識別し、データ削除が成功したと推測しないでください。

## 次にProcessを運用する

RetentionをScheduler、Worker、Shutdownと組み合わせる場合は、[Deployment](deployment.md)のProcess一覧へ進みます。
