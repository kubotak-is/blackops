# P18-008B Seeder Console and Generator Report

## Summary

Framework Built-in `database:seed`と`make:seeder`をProject Consoleへ追加した。両Commandは常時`list`／`help`へ現れ、実行時までCompiled Container、Database、Seed Source、Generator Stubを解決しない。

`database:seed`はApplication Build IDを埋め込んだFresh Compiled Containerだけを読み、Framework内部`CompiledSeederRuntime`からRoot Seederを一度実行する。Success／Unconfigured／Artifact／Resolution／Seeder Failureを固定MessageとExit Codeへ閉じ、Debug VerbosityでもApplication Throwable、SQL、Credential、Seed Valueを表示しない。Migration、Build、HTTP、Worker、Transaction、Journalを暗黙実行しない。

`make:seeder`は一つ以上のPascalCase Segmentを標準Seed DirectoryのPath／Namespaceへ変換し、Framework-owned Stubから`final readonly` Seederを生成する。既存File／Directory、Traversal、Symlink Escape、Write／Publish RaceをProjectFileWriterのAtomic／No-overwrite境界で拒否する。

## Changed Files

### Console／Runtime

- `src/Internal/Application/ApplicationConsoleCommandFactory.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`
- `src/Internal/Application/ApplicationSeederRuntimeResolver.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/Console/DatabaseSeedCommand.php`
- `src/Internal/Console/DatabaseSeedRuntimeException.php`
- `src/Internal/Console/FrameworkCommandNames.php`
- `src/Internal/Console/MakeSeederCommand.php`

### Generator／Stub

- `src/Internal/Generator/SeederGenerator.php`
- `src/Internal/Generator/SeederGeneratorInput.php`
- `resources/stubs/seeder.php.stub`

### Tests／Consumer

- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Internal/Application/ApplicationSeederConsoleIntegrationTest.php`
- `tests/Internal/Console/DatabaseSeedCommandTest.php`
- `tests/Internal/Console/MakeSeederCommandTest.php`
- `tests/Internal/Generator/SeederGeneratorTest.php`
- `tests/Consumer/framework-update-generators.sh`

### Specification／Management

- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/55-project-generators-and-application-migrations.md`
- `develop/spec/76-database-seeding.md`
- `develop/spec/77-phase-18-follow-up-delivery-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P18-008B-seeder-console-and-generator.md`

## Decisions and Assumptions

- FreshnessをContainer自体で検証するため、Application-aware BuildがInternal Parameter `blackops.application_build_id`をCompiled Containerへ固定する。Seed実行時にAccepted ConfigurationのBuild IDと一致しなければRootを解決しない。
- Seeder用Manifestは追加していない。Root／LocatorはP18-008AどおりCompiled Containerだけを正本とする。
- Runtime Resolverは通常Application Commandと同じLogging、DatabaseManager／Default Connection、Transaction RuntimeのSynthetic Serviceを注入する。Seederがこれらへ依存しない場合、Database Configは要求しない。
- `database:seed`はCommand Boundaryで全Throwableを固定Failureへ変換し、Previous Exceptionを外へ渡さない。`-vvv`でもSymfonyの例外描画へ到達しない。
- `make:seeder DatabaseSeeder`もDependency／Runnerを推測しない。生成直後は空`run(): void`だけを持ち、Applicationが実行順とDependencyを編集する。
- Quickstart、Skeleton、Community Board、公開Guide／Websiteは後続Task Scopeのため変更していない。

## Command List／Help／Lazy Dependency Evidence

- Runtime Configurationなし、壊れたApplication Migration SourceありのKernelで`list`が`database:seed`／`make:seeder`を表示した。
- `help make:seeder`はPascalCase入力を表示し、`app/`、Build Artifact、Database接続を作成／解決しなかった。
- Missing Build Artifactで`database:seed`だけを実行するとOwned Exit 1を返し、`var/`、Migration、Buildを作成しなかった。
- `make:seeder`は無効なDatabase ConfigurationとMissing Build ArtifactがあってもFile生成だけを完了した。
- Framework Command Name Set追加により、Explicit／Discovered／Operation Commandとの`database:seed`／`make:seeder`競合を既存Collision Validatorが拒否する。

## Success／Failure／Verbosity／Exit Code Matrix

| Case | Output | Exit |
| --- | --- | --- |
| Fresh configured Root | `Database seeding completed.` | 0 |
| Fresh unconfigured Container | `Database seeding is not configured.` | 1 |
| Missing／Stale／Invalid Container Artifact | `Database seeding artifacts are unavailable.` | 1 |
| Compiled Runtime／Locator Resolution Failure | `Database seeding runtime could not be resolved.` | 1 |
| Runner Cycle／Seeder Throwable／Unexpected Runtime Failure | `Database seeding failed.` | 1 |

Actual Application BuildではRoot ConstructorがBuild／List／Help時に0回、`database:seed`実行時に1回だけ呼ばれた。Rootから2 ChildをCompiled Locator順で実行し、同じCommandで固定Successを返した。Build IDを変更したSnapshotではRoot Constructor／Logicを実行せずStale Artifact Failureになった。

Debug Verbosity Regressionでは`RuntimeException`のClass名、Raw Message、SQL／Seed MarkerがOutputへ出ないことを確認した。

## Generator Input／Path／Atomic Safety Matrix

| Case | Result |
| --- | --- |
| `DatabaseSeeder` | `app/Infrastructure/Seed/DatabaseSeeder.php`／`App\Infrastructure\Seed` |
| `Board/PostSeeder` | Nested Path／`App\Infrastructure\Seed\Board` |
| Absolute／Dot／Traversal／Empty／Backslash／Control | Write前に拒否 |
| Lowercase／Reserved PHP Name | Write前に拒否 |
| Existing File／Directory Target | 既存内容を保持して拒否 |
| Root外／Symlink Ancestor | Application外へWriteせず拒否 |
| Temporary Write Failure | Partial File／空DirectoryをRollback |
| Publish Race | 競合Fileを保持しTemporary Fileを除去 |
| Missing／Read Race Stub | Framework Absolute Path／Warningを非表示 |

CommandはConfiguration、Database、Migration、Build、Seed、Composer Dump-autoloadを実行しない。

## Framework Update Consumer Evidence

Composer VCS FixtureでFramework `1.0.0`から`1.1.0`へ更新した。Project Root `blackops`、既存Application Source、更新前Operation／Migration、他Dependency LockはByte不変だった。

更新後のVendor Packageへ`seeder.php.stub`、`MakeSeederCommand`、`SeederGenerator`が含まれることを確認し、変更されていないEntrypointから`php blackops make:seeder Upgrade/AfterUpdateSeeder`を実行した。生成Path、Namespace、`Seeder`実装、空`run(): void`は現在のFramework Stubと一致した。

## Commands and Results

- Focused Console／Generator／Build Regression: PASS、86 tests／346 assertions
- `bash tests/Consumer/framework-update-generators.sh`: PASS
- `docker compose run --rm app vendor/bin/phpunit`: PASS、1,706 tests／6,828 assertions
- `docker compose run --rm app mago format --check src tests`: PASS
- `docker compose run --rm app mago lint`: PASS、No issues
- `docker compose run --rm app mago analyze`: PASS、No issues
- `docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress`: PASS、0 violations／2,839 allowed
- `docker compose run --rm app composer validate --strict`: PASS
- Management ID Guard: PASS
- Seeder Runtime Scan／Reflection Guard: PASS
- Forbidden Example／Public Documentation Scope Guard: PASS
- Consumer Script `bash -n`: PASS
- `git diff --check`: PASS
- Worker Commit: 未実行

## Acceptance Criteria

- [x] `database:seed`をFramework Built-inとして常時一覧へ登録した
- [x] Fresh ContainerのRootを一度実行し、固定成功Message／Exit 0を返した
- [x] Unconfigured／Artifact／Resolution／Seeder FailureをSafe Message／Exit 1へ変換した
- [x] Migration／Build／HTTP／Workerを暗黙実行しない
- [x] `make:seeder`がRoot／Nested Classを正しいPath／Namespaceへ生成した
- [x] Invalid Input、Traversal、Symlink、Collision、Write／Publish Failureで既存Fileを変更しない
- [x] Framework Update後もProject Entrypoint不変で新Command／Stubを利用した
- [x] Existing Command Discovery／Operation Console／Migration／Generatorを回帰した
- [x] Full PHPUnit、Mago、Deptrac、Composer、Consumer／Guardが成功した
- [x] Example／公開Documentation／外部Publication差分なし、Worker Commitなし

## Remaining Issues

- Quickstart／Skeletonへの標準Seeder追加、Community Boardの`app:seed`移行とSymfony Console直接Dependency削除はP18-008C Scopeである。
- Public Guide／Website同期、Clean Install／Existing Volume Seed JourneyはP18-008C Scopeである。
- Active Blockerはない。

## Suggested Next Action

OrchestratorがCompiled Build ID Parameter、Safe Failure分類、Lazy Factory、Generator Atomic Safety、Framework Update ConsumerをReviewする。独立Quality Gate後にP18-008BをCommitし、P18-008C Seeder Consumer Adoption and Follow-up Closeoutへ進む。

## Orchestrator Review

Status: Accepted

- `LazyFrameworkCommand`はList／Help用Definitionだけを保持し、実行時はFactoryが返す実CommandへInputを委譲する。`make:seeder`の引数はList／Helpで可視、実行時に二重登録されず、Container／Database／Stubを先行解決しない。
- Compiled Containerへ固定した`blackops.application_build_id`をAccepted ConfigurationとStrict比較し、Missing／MismatchではRoot ConstructorとSeeder Logicへ到達しない。
- `database:seed`は全ThrowableをCommand Boundary内で固定Message／Exit 1へ閉じ、Debug VerbosityでもApplication Throwable、SQL、Seed ValueをSymfony例外描画へ渡さない。
- GeneratorはPascalCase Segmentだけを標準Path／Namespaceへ変換し、既存Target、Traversal、Symlink Ancestor、Write Failure、Publish Race、Stub Read Raceを`ProjectFileWriter`境界で拒否する。
- Orchestrator Focusedは41 tests／143 assertions、Full PHPUnitは1,706 tests／6,828 assertionsで成功した。Mago Format／Lint／Analyze、Deptrac 0 violations／2,839 allowed、Composer Strict、Framework Update Consumer、Management ID／Runtime Scan／Scope／diff Guardも成功した。
- Example／公開Documentation／外部Publication差分とWorker Commitはない。P18-008Cへ進行可能である。
