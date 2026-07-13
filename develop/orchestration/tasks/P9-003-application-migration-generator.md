# P9-003: Application Migration Generator and Runtime

Status: Pending P9-002

## Goal

Application Migrationを生成する`make:migration`を提供し、Framework MigrationとApplication Migrationを既存Database Commandの同じ明示Deployment Stepで管理する。

## In Scope

- `make:migration <Description>`とFramework所有Stub
- UTC Doctrine Version Class生成と衝突保護
- Optional `<basePath>/migrations` Convention
- Framework／Application Namespace対応Migration Factory
- Status／Dry-run／MigrateへのApplication Migration統合
- Framework-only Projectの後方互換
- Lazy Command構成とSide Effect不在
- Unit／PostgreSQL Integration／Application Console Test
- Guide／Internals／Quickstart README更新
- Report／STATE更新

## Out of Scope

- Schema Diff／ORM EntityからのMigration自動生成
- Migration自動適用
- Interactive Promptと`--force`
- ApplicationごとのMigration Directory／Namespace設定API
- Metadata Table分離
- Framework Release／Publication

## Relevant Specifications and Decisions

- `develop/decisions/057-database-access-and-migration-library.md`
- `develop/decisions/063-developer-experience-roadmap.md`
- `develop/decisions/077-implementation-worker-model-upgrade.md`
- `develop/decisions/080-project-generator-command-contract.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/55-project-generators-and-application-migrations.md`
- `develop/spec/56-phase-9-delivery-plan.md`

## Files Allowed to Change

- `resources/stubs/migration.php.stub`
- `src/Internal/Console/MakeMigrationCommand.php`
- `src/Internal/Generator/MigrationGenerator.php`
- `src/Internal/Generator/MigrationGeneratorInput.php`
- `src/Internal/Generator/ProjectFileWriter.php`
- `src/Internal/Application/ApplicationConsoleCommandFactory.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`
- `src/Internal/Migration/DoctrineMigrationDependencyFactory.php`
- `src/Internal/Migration/ConfigurablePostgreSqlMigrationFactory.php`
- `src/Internal/Migration/DatabaseMigrationRunner.php`
- `tests/Internal/Console/MakeMigrationCommandTest.php`
- `tests/Internal/Console/DatabaseMigrationCommandTest.php`
- `tests/Internal/Generator/MigrationGeneratorTest.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Internal/Migration/ConfigurablePostgreSqlMigrationFactoryTest.php`
- `tests/Internal/Migration/DatabaseMigrationRunnerTest.php`
- `docs/guide/README.md`
- `docs/guide/project-generators.md`
- `docs/guide/database-migrations.md`
- `docs/internals/README.md`
- `docs/internals/project-generators.md`
- `docs/internals/database-migrations.md`
- `docs/internals/application-bootstrap.md`
- `examples/quickstart/README.md`
- `develop/orchestration/reports/P9-003-application-migration-generator.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は変更を広げず、ReportのBlockerとして返す。

## Constraints

- GPT-5.6 Luna High workerが実装し、Review前にCommitしない
- `make:migration`はDB接続、Migration適用、Build、Composer更新を行わない
- Application Migration Directory不在は正常なFramework-only状態として扱う
- Application MigrationへFramework Schema名をConstructor注入しない
- 未知NamespaceのMigration Classを許可しない
- `list`／`help`でDirectory ScanまたはDB接続を行わない
- PHP Comment／DocBlockへ管理番号を書かない

## Acceptance Criteria

- [ ] UTC Version、`App\Migrations`、Description、空up／downを持つFileを生成する
- [ ] Description不正／Version衝突で既存状態を変更しない
- [ ] `migrations/`を必要時だけ作成し、失敗時の不要な空Directoryを残さない
- [ ] Directory不在ProjectでFramework MigrationのStatus／Dry-run／Migrateが従来どおり動く
- [ ] FrameworkとApplication Migrationを同じMetadata TableでStatus／Dry-run／Migrateできる
- [ ] Application MigrationがDoctrine標準Constructorで生成される
- [ ] Parse Error／Namespace不一致／未知ClassをDatabase Command実行時に拒否する
- [ ] HTTP／Worker／Build／Console list／helpに暗黙Migration Side Effectがない
- [ ] Guide／Internals／Quickstart READMEが実行境界と一致する

## Required Commands

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit tests/Internal/Generator tests/Internal/Console tests/Internal/Application/ApplicationConsoleKernelTest.php tests/Internal/Migration
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-e2e.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P9-003-application-migration-generator.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
