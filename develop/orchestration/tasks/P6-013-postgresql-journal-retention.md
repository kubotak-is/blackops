# P6-013: PostgreSQL Journal Retention

Status: Completed

## Goal

Canonical JournalのRetention Planと安全な物理削除をInline／Deferredの両方に実装し、実際の削除件数とPayloadなしPurge Auditを同一Transactionで保存する。

## In Scope

- `PostgreSqlRetentionPlanner` のJournal候補生成
- Operation IDごとの最新Journal `occurred_at` をbasisとする期限判定
- Inline／Deferred JournalのOperation ID単位削除
- Plan後のActive Hold／新規Journal Recordを実行時に再確認してSkipする競合安全性
- Journal削除と `RetentionPurgeTarget::Journal` Auditの同一Transaction
- `RetentionPurgeResult` のJournal削除件数とTotalへの反映
- `PostgreSqlRetentionPurgeService` へのJournal Delete接続
- Retention HoldとPurge AuditからOperations外部キーを外すVersioned Migration
- Programmatic SchemaとVersioned Migrationの物理形状整合
- Retention Guide／Internals／TODO／Task Report／STATE更新

## Out of Scope

- Operations行の削除
- Transport Payload／Outcome／Dead Letter Retentionの設計変更
- Purge AuditのSystem Log配送
- Per-operation-type Retention Override
- Retention管理UI
- Phase 6全体Closeout

## Relevant Specifications and Decisions

- `develop/spec/26-journal-ports.md`
- `develop/spec/37-postgresql-table-layout.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/decisions/044-data-retention-and-deletion.md`
- `develop/decisions/045-retention-mvp-scope.md`
- `develop/decisions/061-retention-operation-reference.md`

## Files Allowed to Change

- `src/Core/Retention/RetentionPurgeResult.php`
- `src/Internal/Migration/ConfigurablePostgreSqlMigrationFactory.php`
- `src/Transport/PostgreSql/PostgreSqlRetentionPlanner.php`
- `src/Transport/PostgreSql/PostgreSqlJournalRetentionDeleteService.php`
- `src/Transport/PostgreSql/PostgreSqlRetentionPurgeService.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php`
- `migrations/postgresql/**`
- `tests/Core/Retention/RetentionPurgeResultTest.php`
- `tests/Transport/PostgreSql/PostgreSqlRetentionPlannerTest.php`
- `tests/Transport/PostgreSql/PostgreSqlJournalRetentionDeleteServiceTest.php`
- `tests/Transport/PostgreSql/PostgreSqlRetentionPurgeServiceTest.php`
- `tests/Transport/PostgreSql/PostgreSqlRetentionPurgeAuditStoreTest.php`
- `tests/Transport/PostgreSql/PostgreSqlRetentionHoldStoreTest.php`
- `tests/Transport/PostgreSql/PostgreSqlDeferredOperationSenderTest.php`
- `tests/Internal/Migration/DatabaseMigrationRunnerTest.php`
- `tests/Internal/Migration/ConfigurablePostgreSqlMigrationFactoryTest.php`
- `tests/Internal/Console/DatabaseMigrationCommandTest.php`
- `docs/guide/retention.md`
- `docs/guide/README.md`
- `docs/internals/retention-plan.md`
- `docs/internals/retention-purge-audit.md`
- `docs/internals/database-migrations.md`
- `develop/TODO.md`
- `develop/spec/README.md`
- `develop/spec/37-postgresql-table-layout.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/decisions/061-retention-operation-reference.md`
- `develop/orchestration/tasks/P6-013-postgresql-journal-retention.md`
- `develop/orchestration/reports/P6-013-postgresql-journal-retention.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Constraints

- Production Code／TestのCommentへSpec、Decision、Task、TODOの管理番号を書かない
- Journal PlanはOperation IDごとに一件とし、最新 `occurred_at`をbasisにする
- Journal Deleteは対象Operation IDのJournal Recordを一括削除する
- Plan生成後の新規Journal Recordがある場合は実削除しない
- Active HoldはPlan生成時と実削除時の両方で対象を保護する
- Inline OperationはOperations行を新規作成せず、JournalとAuditのOperation IDで追跡する
- Inline OperationへのHoldもOperations行を作成せず、型付きOperation IDで保存する
- Journal削除とAudit保存は同一DBAL TransactionでCommit／Rollbackする
- AuditはJournal Payload／Outcome／Error本文を含めない
- 既存Versioned Migrationは書き換えず、追加Versionで外部キーを削除する
- custom Migration FactoryはPostgreSQL Migration namespace外または`AbstractMigration`非継承Classを拒否し、一件のVersion名に固定しない
- `RetentionPurgeResult` の既存constructor呼び出しと互換性を維持する

## Acceptance Criteria

- [x] Journal Retention PlanがOperation IDごとに最新Journal時刻をbasisとする
- [x] 期限前のJournalとActive Hold対象がPlanから除外される
- [x] Inline／Deferredの期限切れJournalをOperation ID単位で削除できる
- [x] 削除件数付きJournal Purge Auditが同一Transactionで保存される
- [x] Audit失敗時にJournal削除がRollbackされる
- [x] Plan後のHold設定または新規Journal追加で実削除がSkipされる
- [x] Purge AuditはOperations行のないInline Operation IDも保存できる
- [x] Retention HoldはOperations行のないInline Operation IDも保存・解除できる
- [x] 追加MigrationがHold／Auditの外部キーを削除し、再実行はNo-pendingで成功する
- [x] custom Migration Factoryが複数のFramework Versionを生成でき、namespace外のClassを拒否する
- [x] Programmatic SchemaとVersioned Migrationが同じHold／Audit外部キー境界になる
- [x] Purge Facade／Result／CLI totalにJournal実削除件数が反映される
- [x] Retention DocumentationがInline／Deferred／Hold／Audit／競合境界を説明する
- [x] 必須Commandがすべて成功する

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit --filter 'JournalRetention|RetentionPlanner|RetentionPurgeService|RetentionPurgeResult|DatabaseMigration'
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P6-013-postgresql-journal-retention.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
