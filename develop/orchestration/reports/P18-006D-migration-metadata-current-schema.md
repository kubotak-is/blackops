# P18-006D: Migration Metadata Current Schema Report

## Summary

PostgreSQLの`current_schema()`とFramework Migration Schemaが同じ場合だけ、Doctrineへ渡すMetadata Table名を`schema_migrations`へ正規化した。Current SchemaではDBALがTable名を非修飾名として列挙するため、設定側も同じ論理名へ合わせる。Current Schemaでない場合は従来どおり`<schema>.schema_migrations`を渡す。

物理Table、Metadata Row、Public API、Configuration、Command、Connection Session Stateは変更しない。Doctrine `TableMetadataStorage::ensureInitialized()`はSkipせず、Fresh／Existing／Legacyのすべてで従来の初期化契約を完走する。

Community Boardの保持中P18-007差分はP18-006D開始後に変更、整形、Stage、削除していない。発見元の既存VolumeではApplication Forward Migration 1件を適用でき、二回目は0件、StatusはPending 0件になった。

## Changed Files

- `src/Internal/Migration/DoctrineMigrationDependencyFactory.php`
  - Current Schema名とMigration Schema名が同じ場合だけDoctrine Metadata Table名を非修飾化
- `tests/Internal/Migration/DatabaseMigrationRunnerTest.php`
  - Current SchemaでのFresh／Existing／Second Migrate／Status／Dry-run／Legacy／Metadata保持／Search Path不変を実PostgreSQLで固定
- `develop/orchestration/reports/P18-006D-migration-metadata-current-schema.md`
- `develop/STATE.md`

Framework Production修正は`src/Internal/Migration/DoctrineMigrationDependencyFactory.php`の1 Fileだけである。

## Root Cause and Doctrine Normalization

PostgreSQLの既定`search_path`は`"$user", public`を含む。接続Role=`blackops`かつMigration Schema=`blackops`ではSchema作成後の`current_schema()`も`blackops`になる。

Doctrine DBAL Schema ManagerはCurrent Schema内のTableを`schema_migrations`という非修飾名へ正規化する。一方、従来の`TableMetadataStorageConfiguration`は常に`blackops.schema_migrations`を設定していた。`tablesExist()`の比較が一致せず、既存Tableを未初期化と誤認して`CREATE TABLE`し、PostgreSQLが`42P07 Duplicate table`を返した。

修正はDependency Factory作成時に`SELECT current_schema()`を読み、Target Schemaと一致する場合だけDoctrineの論理Table名を`schema_migrations`へ合わせる。異なる場合は従来の安全に検証された修飾名を維持する。Table名は固定Literalまたは`PostgreSqlMigrationSchema::doctrineTable()`だけから作り、未検証IdentifierをSQLへ挿入しない。

## Decisions and Assumptions

- 既存Metadata Table時に`ensureInitialized()`をSkipする案は採用しなかった。Doctrine内部の`isInitialized`／`schemaUpToDate`契約が満たされず、Migration完了記録時に`MetadataStorageError`になることをFocused Testで確認したためである。
- `search_path`を一時変更する案も採用しなかった。Metadata Storageが保持するSchema ManagerのCurrent Schema Cacheと、`status()`がBootstrapperを通らない経路の両方を安全に扱う必要があるためである。
- 論理名の正規化だけなのでConnection Session Stateを変更せず、Commit／Rollback復元処理も不要である。Regression Testは呼出し前後の`SHOW search_path`一致を確認する。
- Status／Dry-runは`SELECT current_schema()`以外の新しい副作用を持たず、Schema／Tableを作らない。

## Commands and Results

### Focused PostgreSQL Regression

```text
docker compose run --rm app vendor/bin/phpunit tests/Internal/Migration/DatabaseMigrationRunnerTest.php
OK (19 tests, 78 assertions)
```

次を同じTest Classで確認した。

- Target Schemaを`search_path`先頭へ置き、Fresh Migrate後に実際の`current_schema()`となること
- 新しいRunnerの`status()`がApplied 2／Pending 0を返すこと
- Second Migrateが0件で成功すること
- Metadata RowのVersion／Executed At／Execution Timeが不変であること
- Current SchemaのLegacy `applied_at`を`executed_at`へ移し、Timestampを保持すること
- Non-current Schemaの既存Fresh／Repeated／Legacy Testが成功すること
- Status／Dry-runがSchemaを作成しないこと
- `SHOW search_path`が呼出し前後で不変であること

### Community Board Existing Volume

```text
docker compose -f examples/community-board/compose.yaml run --rm app php blackops database:migrate
Database migrations applied
migrations: 1

docker compose -f examples/community-board/compose.yaml run --rm app php blackops database:migrate
Database migrations applied
migrations: 0

docker compose -f examples/community-board/compose.yaml run --rm app php blackops database:status
applied: 6
pending: 0
```

P18-007のApplication Forward Migration `App\\Migrations\\Version20260722000100`まで既存履歴へ追加され、二回目以降にMetadata Tableを再作成しない。

### Full Test and Architecture

```text
docker compose run --rm app vendor/bin/phpunit
OK (1662 tests, 6673 assertions)

docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
Violations 0 / Allowed 2793
```

### Formatting and Static Quality

```text
docker compose run --rm app mago format --check src tests
成功

docker compose run --rm app mago lint src/Internal/Migration/DoctrineMigrationDependencyFactory.php
No issues found

docker compose run --rm app mago analyze src/Internal/Migration/DoctrineMigrationDependencyFactory.php
No issues found
```

指定された全体Commandは、P18-006D外の既存Baseline違反により失敗した。

```text
docker compose run --rm app mago lint src tests
133 errors / 1252 warnings / 40 help

docker compose run --rm app mago analyze src tests
329 errors / 2 warnings / 1 note / 582 help
```

代表例は既存`tests/Internal/Status/OperationStatusJournalValidatorTest.php`のComplexity、既存Transaction TestのMethod数、複数の既存Testで`PHPUnit\\Framework\\TestCase`をunknownとする解析である。許可範囲外のため修正していない。変更Production File単体のLint／AnalyzeとFull PHPUnitは成功している。

### Guards

```text
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
成功

git diff --check
成功
```

## Acceptance Criteria

- [x] Current SchemaとMigration Schemaが同じ既存DatabaseでMigration再実行が成功する
- [x] Duplicate Table Errorを実PostgreSQL Regressionで防止する
- [x] Fresh／Legacy／Non-current SchemaのMigration Contractが回帰しない
- [x] Metadata RowとApplication Migration Historyを失わない
- [x] Public API／Configuration／Command Contractを変更しない
- [x] Community Boardの未コミットSource差分をP18-006D中に変更しない
- [x] ReportとSTATEをReview Readyへ更新する
- [x] Worker Commitなし

## Remaining Issues

- Repository全体のMago Lint／AnalyzeにはP18-006D以前からのBaseline違反が残る。P18-006D許可範囲では解消しない。
- Community Boardの既存DatabaseへP18-007 Forward Migrationを適用済みである。Source差分は保持されており、P18-007のAuthentication／Frontend／Command検証はOrchestrator受入後に再開する。

## Suggested Next Action

OrchestratorがFramework Production 1 FileとCurrent／Non-current Schema Regressionを独立Reviewする。P18-006Dを独立Commitした後、保持中のP18-007へ戻り、Community BoardのSession／Frontend／Command Consumerを完走する。

## Orchestrator Review

Accepted。

- Focused PostgreSQL Regression: 19 tests／78 assertions
- Production Mago Format／Lint／Analyze: 成功
- Deptrac: 0 violations／2793 allowed
- Management ID Guard／`git diff --check`: 成功
- Community Board Existing Volume: applied 6／pending 0、P18-007 Forward Migration適用済み
- Public API／Session State変更なし、Community Board SourceはこのCommitへ含めない
