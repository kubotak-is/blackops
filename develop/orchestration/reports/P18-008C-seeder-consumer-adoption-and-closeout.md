# P18-008C Seeder Consumer Adoption and Follow-up Closeout Report

## Summary

Quickstart／SkeletonとCommunity BoardをFramework Database Seederへ移行した。QuickstartはInstall直後から空の標準Root Seederを持ち、Community Boardは標準Rootから既存のApplication Seederを`SeederRunner`で明示実行する。

Community Board専用のSymfony Console Command、`app:seed`、Seederの手動Service登録を削除し、ApplicationのComposer Direct Dependencyから`symfony/console`を削除した。Existing Volume、Clean Install、HTTP、Deferred、Browserを含む全Consumer、Frontend、Website、Package Exportを回帰した。

## Changed Files

### Quickstart／Skeleton

- `examples/quickstart/app/Infrastructure/Seed/DatabaseSeeder.php`
- `examples/quickstart/README.md`
- `examples/quickstart/app/ApplicationServiceProvider.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- Quickstart／Skeleton／Framework Update関連`tests/Consumer/*.sh`

### Community Board

- `examples/community-board/app/Infrastructure/Seed/DatabaseSeeder.php`
- `examples/community-board/app/Infrastructure/Seed/CommunityBoardSeeder.php`
- `examples/community-board/tests/Seed/DatabaseSeederTest.php`
- `examples/community-board/app/ApplicationServiceProvider.php`
- `examples/community-board/composer.json`／`composer.lock`
- `examples/community-board/app/Console/CommunityBoardSeedCommand.php`を削除
- `examples/community-board/tests/Console/CommunityBoardSeedCommandTest.php`を削除
- Community Board README／Consumer Journey

### Documentation／Management

- `docs/guide/database-seeding.md`と関連Guide／Core API Reference
- `docs/internal/database-seeding.md`と関連Internal Docs
- Website Content Map／Navigation／Reader Experience／Artifact Check
- `README.md`、`CHANGELOG.md`、`develop/DOCS.md`
- Seeder関連Specification、`develop/TODO.md`、`develop/STATE.md`

### Test Isolation Scope Extension

- `tests/Internal/Application/ApplicationSeederDiscoveryTest.php`

## Decisions and Assumptions

- 標準Rootは`App\Infrastructure\Seed\DatabaseSeeder`とし、Skeletonは空実装を配布する。
- Community BoardのRootは`SeederRunner`だけへ依存し、子Seederの順序をApplication Sourceで明示する。
- 既存`CommunityBoardSeeder`は`Seeder`を実装するが、Transaction、再実行、Conflict判定、Domain Service再利用はApplication所有のまま維持する。
- SeederはOperationではないため、HTTP／Deferred／Journal Lifecycleへ暗黙統合しない。
- MagoのExamples-wide Gateを満たすため、対象Example内の既存Lint違反へ最小のNative Sensitive Parameter、桁区切り、複雑度抑制、readonly整理を適用した。業務挙動は変更していない。
- Full PHPUnitでは、標準Root追加後に同一FQCNを一時Sourceへ定義する既存Testが先行TestのClass Loadと衝突した。Orchestrator承認のScope Extensionにより、該当MethodだけをGlobal State非継承の別Processへ隔離した。Production Codeは変更していない。

## Final Quickstart／Skeleton／Community Board Seeder Architecture

- Quickstart／Skeleton: 空の標準Root SeederをPackageへ含める。Migration後にBuildし、`database:seed`を実行できる。
- Community Board: 標準RootがCompiled Containerから`SeederRunner`を受け取り、既存Application SeederをClass指定で実行する。
- Child Seeder: 既存Transaction内でDomain ServiceとRepositoryを利用し、決定的かつ再実行可能なContractを維持する。
- Framework: Build-time Discovery、Compiled Locator、DI、Cycle Guard、安全なConsole Boundaryを所有する。
- Application: 実行順、Transaction、Fixture Policy、Conflict、Environment／権限を所有する。

## Existing Volume／Clean Install／Seed Repeatability Evidence

- Existing Volume JourneyでMigration、Build、`database:seed`、Command list／help、Application Compositionを完走した。
- Clean InstallではMigration前のSeedを安全な固定Failureへ閉じ、Migrationと再Build後にSeedを2回完走した。
- 2回目実行後もApplication Fixtureの件数と関係は一定であり、Operation／Journal／Outcomeの不要な永続化は発生しなかった。
- Community BoardのUnit／Integration Suiteは49 tests／548 assertionsで成功し、既存Transaction、Conflict、Domain Service再利用を回帰した。

## Removed Symfony Console Source／Dependency Evidence

- `examples/community-board/app`と`tests`に`Symfony\Component\Console` Importは0件である。
- `CommunityBoardSeedCommand`と専用Console Testを削除した。
- Application Service Providerから`CommunityBoardSeeder`の明示登録を削除した。
- Community Board `composer.json`から`symfony/console`を削除し、Composer strict validationに成功した。
- FrameworkがConsoleを提供するため、Package自体はFrameworkのTransitive DependencyとしてLockへ残る。ApplicationのDirect Dependencyではない。

## Remaining Composer Dependency Audit and Next Decision Candidates

| Direct Dependency | Current Application Import／Ownership | Result |
| --- | --- | --- |
| `doctrine/dbal` | Infrastructure Repository、Seeder、Migration／Integration Testが直接利用 | 維持 |
| `doctrine/migrations` | Application Migration Classが直接継承 | 維持 |
| `vlucas/phpdotenv` | Application Bootstrapが直接利用 | 維持 |
| `nyholm/psr7`／`nyholm/psr7-server` | HTTP／Worker EntrypointがRequest Factoryを直接利用 | 維持 |
| `laminas/laminas-httphandlerrunner` | HTTP／Worker EntrypointがEmitterを直接利用 | 維持 |
| `symfony/uid` | Application Identifier Adapterが直接利用 | 維持 |
| `symfony/console` | Application Source直接Importなし | Direct Dependency削除 |

次Decision候補は、HTTP EntrypointのPSR-7 Factory／EmitterをFramework Adapterへ寄せる境界、Framework既定Identifier Provider、Dotenv Bootstrap Ownershipである。DBAL／MigrationsはApplication SourceがPublic Vendor APIを直接使う現在の設計を先に見直さない限り削除しない。

## Commands and Results

### Community Board／Frontend

- `bash tests/Consumer/community-board-foundation.sh`: PASS
- `bash tests/Consumer/community-board-clean-install.sh`: PASS
- `bash tests/Consumer/community-board-identity.sh`: PASS
- `bash tests/Consumer/community-board-post-comment.sh`: PASS
- `bash tests/Consumer/community-board-product-journey.sh`: PASS
- `bash tests/Consumer/community-board-digest.sh`: PASS
- `bash tests/Consumer/community-board-browser.sh`: PASS、Playwright 1 test
- Community Board PHPUnit: PASS、49 tests／548 assertions
- Frontend Vitest: PASS、7 files／43 tests
- Svelte Check／Build: PASS、0 diagnostics

### Quickstart／Skeleton／Framework Package

- `bash tests/Consumer/quickstart-setup.sh`: PASS
- `bash tests/Consumer/quickstart-e2e.sh`: PASS、Migration／Build／Seedを含む
- `bash tests/Consumer/skeleton-publication.sh --dry-run`: PASS
- `bash tests/Consumer/skeleton-create-project.sh`: PASS
- `bash tests/Consumer/skeleton-publication-workflow.sh`: PASS
- `bash tests/Consumer/framework-update-generators.sh`: PASS
- `bash tests/Consumer/framework-package-export.sh`: PASS
- `bash tests/Consumer/auth-generator-fresh.sh`: PASS
- `bash tests/Consumer/frankenphp-worker-mode.sh`: PASS

### Website

- `mise exec -- pnpm --dir docs/website test`: PASS、42 tests
- `mise exec -- pnpm --dir docs/website run check`: PASS、Astro 0 diagnostics
- `mise exec -- pnpm --dir docs/website run build`: PASS、31 public pages

### Full Quality Gate

- Seeder Discovery Focused PHPUnit: PASS、2 tests／6 assertions
- `docker compose run --rm app vendor/bin/phpunit`: PASS、1,706 tests／6,830 assertions
- `docker compose run --rm app mago format --check src tests examples/quickstart/app examples/community-board/app examples/community-board/tests`: PASS
- `docker compose run --rm app mago lint`: PASS、No issues
- `docker compose run --rm app mago lint examples/quickstart/app examples/community-board/app`: PASS、既存`no-else-clause` Help 1件のみ。今回触れたSeederのWarningは0件
- `docker compose run --rm app mago analyze`: PASS、No issues
- `docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress`: PASS、0 violations／2,839 allowed
- Root／Quickstart／Community Board `composer validate --strict`: PASS
- Management ID Guard: PASS
- Consumer Script `bash -n`: PASS
- `git diff --check`: PASS

初回Website Buildは新しいSeeding RouteをPagefind Route Inventoryへ追加していなかったため失敗し、Inventory同期後に再実行して成功した。初回Full PHPUnitは前述のProcess-local FQCN衝突で失敗し、承認済みTest隔離後に再実行して成功した。Consumerの旧Zero-diff GuardはTask開始前差分との同一性Guardへ更新し、意図したSource差分を許容しつつJourney中の書き換えを拒否する。

### Orchestrator Review Correction

- `CommunityBoardSeeder::seed()`の引数転送Closureを同値のfirst-class callableへ変更し、Magoの`prefer-first-class-callable` Warningを解消した。
- Community Board Root Seeder Focused PHPUnit: PASS、1 test／2 assertions
- 変更Fileの`php -l`: PASS
- Seed Directory全体のFocused PHPUnitはRoot PostgreSQLにCommunity Board SchemaがないためIntegration Test 1件を実行できなかった。Root Seeder Unitは成功し、Schemaを構築するCommunity Board全Consumerと49 tests／548 assertionsのApplication Suiteは変更前のFull Gateで成功済みである。

## Acceptance Criteria

- [x] Quickstart／Skeletonが標準DatabaseSeederを持ち、Install直後のSeed Journeyが成功した
- [x] Community Boardが`database:seed`から既存Fixtureを投入できる
- [x] Transaction／Deterministic／Idempotent／Domain Service再利用を維持した
- [x] Seeder用Symfony Command、`app:seed`、明示Seeder Service登録を削除した
- [x] Community Board SourceからSymfony Console直接ImportをなくしDirect Dependencyを削除した
- [x] Existing Volume／Clean Install／HTTP／Deferred／Browser Journeyが成功した
- [x] Quickstart／Skeleton／Framework Update／Package Export／Websiteが成功した
- [x] 残Composer Dependencyの所有境界と次Decision候補を記録した
- [x] Full PHP／Frontend／Consumer／Website Quality Gateが成功した
- [x] External Publication／Deployなし、Worker Commitなし

## Remaining Issues

- Active Implementation Blockerはない。
- HTTP Runtime、Identifier、Dotenv、DBAL／MigrationsのApplication Direct Dependency境界は、このTaskでは削除せず上記Decision候補へ送る。
- Documentation WebsiteとCommunity BoardのExternal Publication／Deployは行っていない。
- User所有の既存Root PostgreSQL Runtimeは停止せず、healthyのまま維持した。

## Suggested Next Action

OrchestratorがRoot Seeder Composition、Application-owned Transaction、削除したSymfony Console境界、Test Process隔離、Consumer／Website／Package GateをReviewする。独立確認後にP18-008Cを意味のある単位でCommitし、Phase 19 Reliability and Deliveryの次Taskを策定する。

## Orchestrator Review

Status: Accepted

- Quickstart／Skeletonの空RootとCommunity Boardの`DatabaseSeeder -> SeederRunner -> CommunityBoardSeeder`を確認した。子Seederは既存`Connection::transactional()`、固定Dataset、Conflict判定、Board Domain Serviceを維持し、Operation／Journalへ暗黙統合しない。
- `CommunityBoardSeedCommand`、`app:seed`、手動Service登録、Applicationの`symfony/console` Direct Requirementを削除した。Lockに残るSymfony ConsoleはFrameworkのTransitive Dependencyであり、Application所有と混同していない。
- Examples-wide Mago対応はNative Sensitive Parameter、桁区切り、readonly整理、局所的な既存Complexity抑制に限定した。Review CorrectionでSeederの引数転送をfirst-class callableへ変更し、今回触れたSeeder Warningを解消した。
- Consumer GuardはTask開始前のbinary diffとstatusを実行後に比較するため、意図した未Commit差分を許容してもConsumerによる追加書換えを検出する。標準Root FQCNの既存Test衝突は該当MethodだけをGlobal State非継承の別Processへ隔離した。
- Orchestrator Focusedは17 tests／308 assertions。Full PHPUnitは1,706 tests／6,830 assertions、Community Board Clean InstallはMigration前Safe Failure、Seed 2回、3 User／3 Post／4 Comment、Frontend 7 files／43 tests、Loginまで成功した。
- Orchestrator Websiteは42 tests、Astro diagnostics 0、31-page Site Checkに成功した。Mago Format／Lint／Analyze、Deptrac 0 violations／2,839 allowed、Root／Quickstart／Community Board Composer Strict、Management ID／Production Scope／diff Guardも成功した。
- Framework Production、External Publication／Deploy、Worker Commitはなく、生成／依存／Runtime Artifactはcleanup済みである。P18-008CとPhase 18 Follow-upは完了した。
