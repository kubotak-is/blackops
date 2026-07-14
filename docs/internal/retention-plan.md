# Retention Plan

Retention Planは、Retention PurgeのDry Run、手動Purge、Scheduler Workerが共有する削除候補一覧である。

Plan生成は副作用を持たない。Payload、Context、Journal本文、Dead Letter本文など、削除対象Dataの中身はPlanへ含めない。

## Plan Item

`RetentionPlanItem` は次を保持する。

```text
operation_id
target
basis_at
eligible_at
```

`basis_at` はRetention期間を計算する基準時刻である。Transport PayloadではTerminal Stateへ到達した時刻、JournalではOperation IDごとの最新 `occurred_at`、Outcomeでは完了時刻、Dead LetterではDead Letterへ移動した時刻を使う。

`eligible_at` は `basis_at + retention period` で計算される。Plannerは `eligible_at <= now` の候補だけを返す。

## Plan

`RetentionPlan` は `RetentionPlanItem` の不変一覧である。

Dry RunはこのPlanを表示するだけで、DBを変更しない。実削除Serviceは同じPlanを入力として受け取り、Targetごとの安全な順序でTombstone化または削除を行う。

## Planner Port

`RetentionPlanner` はPolicyと現在時刻からPlanを生成する。

```text
plan(policy, now)
```

Policyは4対象すべての期間を明示的に持つ。Policy未設定時の警告やPurge停止はConfig/Manifest/CLI側で扱い、Plannerへは確定済みPolicyだけを渡す。

## PostgreSQL Planner

`PostgreSqlRetentionPlanner` は現在のPostgreSQL物理Schemaから次の候補を返す。

- Transport Payload Tombstone候補
- Canonical Journal削除候補
- Outcome削除候補
- Dead Letter削除候補

Transport Payload候補はTerminal Operationで、PayloadとContextがまだ残っており、`payload_purged_at` が未設定の行である。

Dead Letter候補は `dead_letters.moved_at` が期限を過ぎた行である。

Outcome候補は `outcomes.completed_at` が期限を過ぎた行である。

Journal候補はOperation IDごとにGroup化し、最新 `occurred_at` が期限を過ぎたものを一件だけ返す。InlineとDeferredを区別せず、Operations行の存在を候補条件にしない。

Active Holdが存在するOperationは、Targetに関係なくPlanから除外する。

## Journal Delete

`PostgreSqlJournalRetentionDeleteService` はPlan内の `journal` 候補だけを処理する。Operation IDに属するJournal Recordを一括削除し、実際に削除したRecord数を返す。

Delete文は実行時に最新 `occurred_at` がPlanの `basis_at` と一致することとActive Holdがないことを再確認する。Plan後に新規RecordまたはHoldが追加されたOperationは削除せず、Auditも記録しない。

Journal削除と件数付きPurge Auditは同じDBAL Transactionに含まれる。Audit保存が失敗した場合はJournal削除もRollbackする。

## Transport Payload Tombstone

`PostgreSqlTransportPayloadTombstoneService` はPlan内の `transport_payload` 候補だけを処理する。

Serviceは実行時にも次を再確認する。

- OperationがTerminal Stateである
- Encoded PayloadとEncoded Contextがまだ残っている
- `payload_purged_at` が未設定である
- Active Holdが存在しない

条件を満たしたOperationだけ、Encoded PayloadとEncoded ContextをNULL化し、`payload_purged_at` を記録する。Operations行自体は削除しない。

成功したTombstoneごとにPayloadなしのPurge Auditを記録する。Plan生成後にHoldが設定された場合や、既にTombstone済みになった場合は、実行時の再確認で安全側にスキップする。

## Dead Letter Delete

`PostgreSqlDeadLetterRetentionDeleteService` はPlan内の `dead_letter` 候補だけを処理する。

Serviceは実行時にもActive Holdが存在しないことを再確認する。条件を満たしたDead Letter Recordだけを削除し、Operations行は削除しない。

成功した削除ごとにPayloadなしのPurge Auditを記録する。Plan生成後にHoldが設定された場合や、既に削除済みになった場合は、実行時の再確認で安全側にスキップする。

## Outcome Delete

`PostgreSqlOutcomeRetentionDeleteService` はPlan内の `outcome` 候補だけを処理する。実行時にもActive Holdが存在しないことをDelete条件で再確認する。

Outcome Row削除とPayloadなしのPurge Auditは同じTransactionでCommitする。Audit保存に失敗した場合はOutcome削除もRollbackする。

## Purge Service Facade

`PostgreSqlRetentionPurgeService` はCLIとScheduler Workerが呼び出すための薄いFacadeである。

Facadeは次の順で処理する。

```text
plan
transport payload tombstone
dead letter delete
outcome delete
journal delete
```

ResultはPlanと対象別の実行件数だけを返す。Payload、Context、Dead Letter本文、Journal本文は返さない。

ResultはJournalの実削除Record数も対象別件数とTotalへ反映する。System Log配送は別の責務として扱う。

## Plan CLI

`retention:plan` はPlanを表示するだけのSymfony Console Commandである。

```text
retention:plan
  --transport-payload-days=7
  --journal-days=30
  --outcome-days=14
  --dead-letter-days=90
```

CommandはDB接続を生成しない。ApplicationのComposition Rootが `RetentionPlanner` を組み立て、Commandへ注入する。

4対象すべてのRetention期間を明示Optionで受け取る。暗黙の既定値は持たない。

Purge実行CommandとPolicy Config File Loaderは後続Taskで扱う。

## Purge CLI

`retention:purge` はDry RunまたはConfirm実行を行うSymfony Console Commandである。

Dry Run:

```text
retention:purge
  --dry-run
  --transport-payload-days=7
  --journal-days=30
  --outcome-days=14
  --dead-letter-days=90
```

Confirm:

```text
retention:purge
  --confirm
  --transport-payload-days=7
  --journal-days=30
  --outcome-days=14
  --dead-letter-days=90
  --policy-ref=production-retention-v1
  --actor=system:retention
```

`--dry-run` と `--confirm` は同時指定できない。どちらも指定しない場合も拒否する。

CommandはDB接続を生成しない。ApplicationのComposition Rootが `RetentionPlanner` と `RetentionPurgeService` を組み立て、Commandへ注入する。

Confirm実行ではPolicy ReferenceとActor Referenceを明示的に要求する。Purge Auditは実行Service側で記録する。
