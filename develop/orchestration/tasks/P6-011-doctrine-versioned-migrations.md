# P6-011: Doctrine Versioned Migrations

Status: Completed

## Goal

Framework-owned PostgreSQL SchemaをDoctrine MigrationsのVersioned Baselineとして明示適用できるようにし、status、dry-run、idempotent applyをSymfony Consoleから提供する。

## In Scope

- 現在のOperations、Journal、Outcomes、Dead Letters、Retention Tablesを作るBaseline Migration
- Configurable PostgreSQL SchemaをMigrationへ注入するDoctrine Migration Factory
- Doctrine `DependencyFactory`のFramework内部Composition
- `blackops:database:migrate` Command
- `blackops:database:status` Command
- `--dry-run`、Non-interactive apply、No-pending時の安全な再実行
- `schema_migrations`をDoctrine Metadata Storage形式へ統一
- 新規DatabaseへのBaseline Apply Integration Test
- 既存Programmatic Test SchemaとのMetadata互換性
- Dry RunでFramework Tableを変更しないことのTest
- Apply後の全主要Table／Constraint／Index／Version Row検証
- Irreversible Baseline Down境界
- Deployment時のMigration手順とProgrammatic `migrate()` Test-only境界のDocumentation

## Out of Scope

- ORM／DoctrineBundle／Symfony full-stack
- Application-owned Migration
- Automatic Migration on HTTP／Worker startup
- Destructive Down Migration
- MySQL／SQLite Migration
- Credential／Connection生成のPublic API
- Future Schema Versionの追加
- Canonical Journal Retention削除

## Relevant Specifications and Decisions

- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/37-postgresql-table-layout.md`
- `develop/decisions/043-postgresql-table-layout.md`
- `develop/decisions/057-database-access-and-migration-library.md`

## Files Allowed to Change

- `src/Internal/Migration/**`
- `src/Internal/Console/DatabaseMigration*.php`
- `tests/Internal/Migration/**`
- `tests/Internal/Console/DatabaseMigration*Test.php`
- `src/Transport/PostgreSql/PostgreSqlJournalSchema.php`
- `tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php`
- `migrations/postgresql/**`
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

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Constraints

- Production Code／TestのCommentへSpec、Decision、Task、TODOの管理番号を書かない
- MigrationはDoctrine ORM、Bundle、Symfony Kernelへ依存しない
- Schema名は既存PostgreSQL Identifier検証と同等に安全に扱う
- Doctrine Metadata TableはConfigurable Schema内の`schema_migrations`を使う
- Metadata Table列をDoctrine Migrations 3.9の期待形状へ揃える
- BaselineはTransactional／All-or-nothingでApplyする
- DownはData Lossを避けるため明示的にIrreversibleとする
- Dry RunではOperations／Journal／Outcomes等のFramework Data Tableを作成・変更しない
- HTTP／Worker Runtime startupからMigrationを暗黙実行しない
- Existing Adapter `migrate()`はIntegration Test helperとして維持する
- No-pending Migrationの再実行を成功扱いにする

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

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit --filter 'DatabaseMigration|PostgreSqlCanonicalJournalStore'
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

`develop/orchestration/reports/P6-011-doctrine-versioned-migrations.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
