# P9-003: Application Migration Generator and Runtime Report

Status: Accepted

## Summary

Installed ApplicationのProject所有`bin/blackops`から実行する`make:migration <Description>`を実装した。Framework所有StubからUTCの`VersionYYYYMMDDHHMMSS`、`App\Migrations` Namespace、Doctrine標準Constructor、Description、空の`up()`／`down()`を持つMigrationを生成する。

Application Rootの`migrations/`は最初の生成時だけ作成する。Description不正、同一秒Version衝突、Stub読込、Write、Publish失敗では既存状態を変更せず、今回作成したTemporary Fileと空DirectoryだけをRollbackする。GeneratorはDatabase、Migration Runner、Build、Composer、Networkを構成しない。

既存の`blackops:database:status`／`blackops:database:migrate`へoptional Application Migration directoryを統合した。Framework MigrationとApplication Migrationは同じConnection、Framework Schema内Metadata Table、transactional／all-or-nothing設定を共有する。ComparatorはFramework Namespaceを常に先にし、各Namespace内をVersion Class順に実行する。Framework MigrationだけがSchema名をConstructor注入し、Application MigrationはDoctrine標準のConnection／Logger Constructorで生成する。

Application Migration Finderは`Version*.php`をDatabase Command実行時だけ厳格に読み込む。Parse Error、Namespace不一致、File名とClass名の不一致、非`AbstractMigration`、未知Namespaceを拒否する。`migrations`がFile、symlink、Application外解決となる場合もFramework-only状態として黙って無視せず拒否する。

## Changed Files

- `resources/stubs/migration.php.stub`
- `src/Internal/Console/MakeMigrationCommand.php`
- `src/Internal/Generator/MigrationGenerator.php`
- `src/Internal/Generator/MigrationGeneratorInput.php`
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
- `docs/internal/README.md`
- `docs/internal/project-generators.md`
- `docs/internal/database-migrations.md`
- `docs/internal/application-bootstrap.md`
- `examples/quickstart/README.md`
- `develop/spec/55-project-generators-and-application-migrations.md`
- `develop/spec/56-phase-9-delivery-plan.md`
- `develop/orchestration/tasks/P9-003-application-migration-generator.md`
- `develop/orchestration/tasks/P9-004-framework-update-generator-smoke.md`
- `develop/orchestration/reports/P9-003-application-migration-generator.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Migration DescriptionはASCII PascalCase PHP Identifierとし、Keyword／Reserved Type Nameも拒否する。
- Version ClockはPSR Clockとして注入可能にし、生成時は必ずUTCへ変換する。
- StubはFramework Rootの`resources/stubs/`だけに置き、生成Classはconstructorを宣言しない。
- Application Migration directoryの不在だけをFramework-only状態として扱う。既存file、broken／outside symlink、非Directoryは設定誤りとして拒否する。
- Application Migration File自体のsymlinkも任意PHP読込を避けるため拒否する。
- Doctrine既定Alphabetical ComparatorはFQCN全体を比較して`App\Migrations`をFrameworkより先にするため使用しない。Framework Namespaceを先にし、各Namespace内をFQCN順にするComparatorをDependencyFactoryへ登録する。
- Framework Migrationは3引数でSchemaを注入し、Application MigrationはDoctrine標準2引数で生成する。その他のNamespaceはFactoryでも拒否する。
- `list`／`help`はCommand Descriptorだけを使い、Migration Directory／Stub／DBを解決しない。Database Command以外のHTTP、Worker、Scheduler、Build CompositionにはMigration Runnerを追加しない。

## Commands and Results

```text
docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit tests/Internal/Generator tests/Internal/Console tests/Internal/Application/ApplicationConsoleKernelTest.php tests/Internal/Migration
Result: OK (106 tests, 354 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/phpunit
Result: Worker／Orchestrator final rerunともにOK (771 tests, 2544 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Worker／Orchestrator final rerunともに368 files / Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1578 / Warnings 0 / Errors 0.

bash tests/Consumer/quickstart-e2e.sh
Result: Worker／Orchestrator final rerunともにQuickstart consumer E2E passed. Final success line and Exit 0を確認した。

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches; negated command exited 0.

git diff --check
Result: No output.
```

Development中の最初のFocused Runでは、Test内Source Stringのescape誤り、dry-run後に同じDoctrine Migration Instanceをapplyへ再利用した`FrozenMigration`、標準Constructor TestのConnection Parameter不足、空TableのRow Count Assertion誤りを検出した。Sourceを修正し、実CLIと同じくdry-run／applyごとに新しいRunnerを構成し、Test ConnectionとTable存在Assertionを正した後、Focused／Full Suiteは成功した。

## Acceptance Criteria

- [x] UTC Version、`App\Migrations`、Description、空のup／downを持つFileを生成する
- [x] Description不正／Version衝突で既存状態を変更しない
- [x] `migrations/`を必要時だけ作成し、失敗時の不要な空Directoryを残さない
- [x] Directory不在ProjectでFramework MigrationのStatus／Dry-run／Migrateが従来どおり動く
- [x] FrameworkとApplication Migrationを同じMetadata TableでStatus／Dry-run／Migrateできる
- [x] Frameworkを先に、各Namespace内をVersion Class順に実行する
- [x] Application MigrationがDoctrine標準Constructorで生成される
- [x] Parse Error／Namespace不一致／未知ClassをDatabase Command実行時に拒否する
- [x] Existing file／Directory symlink／Migration file symlinkから任意PHPを読み込まない
- [x] HTTP／Worker／Build／Console list／helpに暗黙Migration Side Effectがない
- [x] Guide／Internals／Quickstart READMEが実行境界と一致する

## Remaining Issues

P9-003 Scope内の既知Code Blockerはない。Orchestrator ReviewでFramework-first Comparator、strict Finder、symlink／file境界を確認し、Framework先行・各Namespace内Version順のInvariantを`develop/spec/55-project-generators-and-application-migrations.md`へ追記した。

## Suggested Next Action

P9-003 Accepted ChangeをCommit／Pushし、P9-004 Framework Update Generator Smoke and Closeoutへ進む。
