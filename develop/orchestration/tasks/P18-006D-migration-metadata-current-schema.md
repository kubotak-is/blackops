# P18-006D: Migration Metadata Current Schema

Status: Ready

## Goal

PostgreSQLの接続Role名とFramework Migration Schema名が同じで、そのSchemaが`current_schema()`になる環境でも、既存のDoctrine Metadata Tableを正しく認識する。`database:migrate`の再実行で`schema_migrations`を重複作成せず、既存VolumeへApplication Forward Migrationを適用できる状態へ戻す。

P18-007で発見したFramework既存不具合を独立修正し、Community Boardの未コミット移行差分は変更しない。

## Context

Community Boardの既存VolumeはDatabase User=`blackops`、Framework Schema=`blackops`である。PostgreSQLの既定`search_path`に`"$user"`が含まれるため、Schema作成後は`current_schema()`も`blackops`になる。

Doctrine DBALのSchema ManagerはCurrent Schema内のTable名を非修飾名として列挙する一方、Migration Metadataは`blackops.schema_migrations`という修飾名で設定されている。この状態で`TableMetadataStorage::ensureInitialized()`を呼ぶと既存Tableを未作成と誤認し、`42P07 Duplicate table`になる。Frameworkの事前存在確認自体は成功しているため、Legacy Upgrade後のDoctrine初期化境界に不整合がある。

## Relevant Specifications and Decisions

- `develop/spec/42-installed-application-boundary.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/55-project-generators-and-application-migrations.md`
- `develop/spec/56-phase-9-delivery-plan.md`
- `develop/decisions/057-database-access-and-migration-library.md`
- `develop/decisions/080-project-generator-command-contract.md`

## In Scope

- Current SchemaとMigration Schemaが同じ場合のDoctrine Metadata初期化修正
- 既存Metadata Table、Fresh Schema、Legacy Metadata Upgradeの回帰
- 同一Connectionでの`status`／`migrate`／再`migrate`の回帰
- `search_path`またはConnection Session Stateを変更する場合の復元／Transaction境界の保証
- Community Board既存VolumeでForward Migrationが開始できることの確認
- Focused Unit／PostgreSQL Integration Test、Full Migration Test
- Task ReportとSTATE更新

## Out of Scope

- Community BoardのIdentity／Frontend／Command／Migration実装変更
- Public Migration API、Command名、Configuration形式の変更
- Doctrine DBAL／MigrationsのForkまたはVendor Patch
- Metadata Table名、Framework Migration Schemaの変更
- Application Migration Historyの書換え
- Database作成、Role作成、権限管理の自動化
- External Publication／Deploy

## Required Behavior

- Migration SchemaがCurrent Schemaでも既存`<schema>.schema_migrations`を一度だけ利用する。
- 既存Metadata Row、`executed_at`、`execution_time`を失わない。
- `database:migrate`を二回以上実行しても二回目以降は0 Migrationで成功する。
- Fresh DatabaseではMetadata TableとFramework Migrationを従来どおり作成する。
- Migration SchemaがCurrent Schemaではない既存経路を回帰させない。
- Legacy `applied_at` Metadata Upgradeを回帰させない。
- `status`と`dry-run`はSchema／Tableを暗黙作成しない。
- 修正のために変更したConnection Session Stateは呼出し後へ漏らさない。Transaction Rollback時も同様とする。
- Schema／Table Identifierは既存の安全なValidation／Quotingを維持し、動的SQLへ未検証値を挿入しない。
- Framework Version RowをBaseline扱いで捏造せず、Doctrine Metadata Storageの既存Contractを維持する。

## Allowed Files

- `src/Internal/Migration/DoctrineMigrationMetadataBootstrapper.php`
- 原因解消に不可欠な場合だけ`src/Internal/Migration/DoctrineMigrationDependencyFactory.php`
- 原因解消に不可欠な場合だけ`src/Internal/Migration/PostgreSqlMigrationSchema.php`
- `tests/Internal/Migration/DatabaseMigrationRunnerTest.php`
- Migration専用Test Fixtureの最小追加
- `develop/STATE.md`
- `develop/orchestration/reports/P18-006D-migration-metadata-current-schema.md`

Community Boardの未コミット差分、`examples/community-board/**`、その他Framework Production Codeは変更禁止とする。Community Board既存Volumeの確認はRead-onlyなTask対象外差分を利用してよいが、このTaskの変更として含めない。

## Required Verification

1. Target SchemaをConnectionのCurrent SchemaにしたPostgreSQL Integration Test
2. Fresh Migrate、Second Migrate 0件、Status Pending 0件
3. Existing Metadata TableとVersion Row保持
4. Legacy Metadata Upgrade
5. Non-current Target Schema Regression
6. Status／Dry-run Side Effect不在
7. Connection Session State非漏出
8. `docker compose run --rm app vendor/bin/phpunit tests/Internal/Migration/DatabaseMigrationRunnerTest.php`
9. `docker compose run --rm app mago format --check src tests`
10. `docker compose run --rm app mago lint src tests`
11. `docker compose run --rm app mago analyze src tests`
12. `docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress`
13. AGENTS.mdのManagement ID Guardと`git diff --check`

## Acceptance Criteria

- [ ] Current SchemaとMigration Schemaが同じ既存DatabaseでMigration再実行が成功する
- [ ] `schema_migrations`のDuplicate Table ErrorがRegression Testで再現・防止される
- [ ] Fresh／Legacy／Non-current Schemaの既存Migration Contractが回帰しない
- [ ] Metadata RowとApplication Migration Historyを失わない
- [ ] Public API／Configuration／Command Contractを変更しない
- [ ] Community Boardの未コミット差分を変更しない
- [ ] ReportとSTATEがReview Readyへ更新される
- [ ] Worker Commitなし

## Completion Report

`develop/orchestration/reports/P18-006D-migration-metadata-current-schema.md`へAGENTS.mdの必須Sectionに加え、次を記録する。

- Root CauseとDoctrine DBAL Current Schema Name Normalization
- Chosen FixとConnection Session State境界
- Fresh／Existing／Legacy／Repeated Migration Evidence
- P18-007を再開可能か
