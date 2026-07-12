# P6-011 Completion Report

## Summary

Doctrine Migrations 3.9を使う一件のPostgreSQL Versioned Baselineと、明示的なapply／dry-run／status入口を実装した。

Baselineはconfigurable SchemaへOperations、Journal、Outcomes、Dead Letters、Retention Holds、Retention Purge Auditsと現在のConstraint／Indexを作成する。Doctrine `DependencyFactory`は既存DBAL Connectionから構成し、custom Migration Factoryが検証済みSchema名をBaselineへ注入する。

Metadata bootstrapは、Schemaが存在しない状態と既存の `version/applied_at` 管理表を明示的に扱う。apply時だけSchemaを作成し、legacy timestampをUTCのDoctrine形式へ移送してから `ensureInitialized()` を実行する。statusとdry-runはread-onlyであり、fresh DatabaseへSchemaやTableを残さない。

旧SQL migration `001_create_canonical_journal.sql` は新しいVersioned Baselineと矛盾するため削除した。

## Changed Files

- `migrations/postgresql/Version20260712000000.php`
- `migrations/postgresql/001_create_canonical_journal.sql`（削除）
- `src/Internal/Migration/PostgreSqlMigrationSchema.php`
- `src/Internal/Migration/ConfigurablePostgreSqlMigrationFactory.php`
- `src/Internal/Migration/DoctrineMigrationDependencyFactory.php`
- `src/Internal/Migration/DoctrineMigrationMetadataBootstrapper.php`
- `src/Internal/Migration/DatabaseMigrationRunner.php`
- `src/Internal/Migration/DatabaseMigrationStatus.php`
- `src/Internal/Migration/DatabaseMigrationResult.php`
- `src/Internal/Console/DatabaseMigrationMigrateCommand.php`
- `src/Internal/Console/DatabaseMigrationStatusCommand.php`
- `src/Transport/PostgreSql/PostgreSqlJournalSchema.php`
- `tests/Internal/Migration/DatabaseMigrationRunnerTest.php`
- `tests/Internal/Console/DatabaseMigrationCommandTest.php`
- `tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php`
- `docs/guide/database-migrations.md`
- `docs/guide/README.md`
- `docs/internals/database-migrations.md`
- `docs/internals/README.md`
- `docs/internals/bootstrap.md`
- `docs/internals/postgresql-journal-store.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P6-011-doctrine-versioned-migrations.md`
- `develop/orchestration/reports/P6-011-doctrine-versioned-migrations.md`
- `develop/STATE.md`

## Decisions and Assumptions

- `schema_migrations` はDoctrine Table Metadata Storageの期待形状である `version varchar(191) primary key`、`executed_at timestamp(0) without time zone nullable`、`execution_time integer nullable`へ統一した。
- apply時だけSchema／Metadata bootstrapを実行する。statusとdry-runはDatabaseを変更しない。
- legacy `applied_at timestamptz` は単純renameせず、UTCへ変換した `executed_at timestamp without time zone` へ値を移送する。
- Metadata bootstrapとshape整合は一つの短いTransaction、Framework Data Table BaselineはDoctrineのtransactional／all-or-nothing Migrationとして別Transactionで適用する。
- 現在のprogrammatic Adapter helperが作成した空SchemaをadoptできるようBaseline DDLは `IF NOT EXISTS` を使う。この互換性は現在のhelper生成Schemaに限定し、任意のSchema drift修復は扱わない。
- Existing Adapter `migrate()` はIntegration Test helperとして維持する。Production DeploymentはVersioned Commandを使用する。
- Baseline downはFramework Dataを破壊するため常にIrreversibleとした。
- HTTP／Worker／FrankenPHP startupファイルは変更しておらず、暗黙Migration経路は追加していない。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'DatabaseMigration|PostgreSqlCanonicalJournalStore'
Result: OK (20 tests, 90 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (572 tests, 1807 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 316 files / Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1285 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Doctrine Migrationsが一件のVersioned Baselineを認識する
- [x] Configurable SchemaへDoctrine Metadataを保存する
- [x] Baselineが現在の全Framework Tableを作成する
- [x] `blackops:database:migrate`がNon-interactive Applyできる
- [x] `--dry-run`がSQL Planを生成しFramework Data Tableを変更しない
- [x] Apply後の再実行が安全に成功する
- [x] `blackops:database:status`がApplied／Pending状態を表示する
- [x] Baseline DownがIrreversibleとして拒否される
- [x] Programmatic Test SchemaとDoctrine Metadata形状が互換になる
- [x] HTTP／Worker startupにMigration副作用が追加されない
- [x] Deployment Guideと内部CompositionがDocumentationへ記録される
- [x] 必須Commandがすべて成功する

## Remaining Issues

- なし。

## Suggested Next Action

Orchestrator Codexが差分、Report、必須Command結果をReviewし、受入可能ならTask単位でCommitする。
