# P6-013 Completion Report

## Summary

Canonical Journal RetentionをInline／Deferred共通で実装した。PlannerはOperation IDごとの最新Journal時刻をbasisとして候補を作り、Journal DeleteはPlan後の新規RecordとActive Holdを実行時に再確認する。削除Record数とPayloadなしPurge Auditを同じDBAL Transactionで保存し、Audit失敗時は削除をRollbackする。

Retention HoldとPurge AuditのOperation IDをOperations行から独立させる追加Versioned Migrationを導入し、Operations行を持たないInline OperationでもHold設定・解除とPurge Audit保存を可能にした。

## Changed Files

- `src/Core/Retention/RetentionPurgeResult.php`
- `src/Internal/Migration/ConfigurablePostgreSqlMigrationFactory.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php`
- `src/Transport/PostgreSql/PostgreSqlRetentionPlanner.php`
- `src/Transport/PostgreSql/PostgreSqlJournalRetentionDeleteService.php`
- `src/Transport/PostgreSql/PostgreSqlRetentionPurgeService.php`
- `migrations/postgresql/Version20260712010000.php`
- `tests/Core/Retention/RetentionPurgeResultTest.php`
- `tests/Internal/Console/DatabaseMigrationCommandTest.php`
- `tests/Internal/Migration/ConfigurablePostgreSqlMigrationFactoryTest.php`
- `tests/Internal/Migration/DatabaseMigrationRunnerTest.php`
- `tests/Transport/PostgreSql/PostgreSqlDeferredOperationSenderTest.php`
- `tests/Transport/PostgreSql/PostgreSqlJournalRetentionDeleteServiceTest.php`
- `tests/Transport/PostgreSql/PostgreSqlRetentionHoldStoreTest.php`
- `tests/Transport/PostgreSql/PostgreSqlRetentionPlannerTest.php`
- `tests/Transport/PostgreSql/PostgreSqlRetentionPurgeAuditStoreTest.php`
- `tests/Transport/PostgreSql/PostgreSqlRetentionPurgeServiceTest.php`
- `docs/guide/README.md`
- `docs/guide/retention.md`
- `docs/guide/retention.md`
- `docs/internal/database-migrations.md`
- `docs/internal/retention-plan.md`
- `docs/internal/retention-purge-audit.md`
- `develop/spec/37-postgresql-table-layout.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/README.md`
- `develop/decisions/061-retention-operation-reference.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P6-013-postgresql-journal-retention.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P6-013-postgresql-journal-retention.md`

## Decisions and Assumptions

- JournalのbasisはOperation IDごとの `MAX(occurred_at)` とした。
- Delete時は最新時刻がPlan basisと一致し、Active Holdが存在しない場合だけOperation ID単位で全Journal Recordを削除する。
- Journal Delete ServiceはPurge Facadeの必須Dependencyとし、不完全なCompositionを許可しない。
- `RetentionPurgeResult` は既存の4引数constructor呼び出しを維持し、末尾のJournal件数を既定0で追加した。
- Retention HoldとPurge AuditはInline Operationを扱うためOperations外部キーを持たない。OutcomeのOperations外部キーは `ON DELETE RESTRICT` のまま維持する。
- 既存Baselineは変更せず、追加VersionでHold／Audit外部キーを削除する。Inline参照が保存され得るためdownはIrreversibleとした。
- Migration FactoryはFramework PostgreSQL Migration Namespaceの `AbstractMigration` subclassだけを許可し、全Versionへconfigurable Schemaを注入する。
- Purge AuditのSystem Log配送はTask範囲外のまま残した。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'JournalRetention|RetentionPlanner|RetentionPurgeService|RetentionPurgeResult|DatabaseMigration'
Result: OK (24 tests, 107 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (581 tests, 1872 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 317 files / Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1301 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

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
- [x] Programmatic SchemaとVersioned Migrationが同じ外部キー境界になる
- [x] Purge Facade／Result／CLI totalにJournal実削除件数が反映される
- [x] Retention DocumentationがInline／Deferred／Hold／Audit／競合境界を説明する
- [x] 必須Commandがすべて成功する

## Remaining Issues

- Purge AuditのSystem Log配送は未接続であり、Task範囲外として残る。
- Operations行の物理削除はRetention Serviceの対象外である。

## Suggested Next Action

Orchestrator Codexが実装、追加Migration、競合安全性、Documentation、Verification結果をReviewし、受入後にTask単位でCommitする。
