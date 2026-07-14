# P10-007: Project CLI Command Names Report

Status: Accepted

## Summary

Project Root `blackops`が公開する9個のFramework CommandをPrefixなしCanonical名へ変更した。各Command ClassはCanonical `NAME`と旧`blackops:*`用`LEGACY_NAME`を持ち、Public Application Console KernelのLazy Descriptorが旧名を同じCommandのAliasとして登録する。

Application独自CommandはCanonical名、Legacy Aliasのどちらも名前またはApplication側Aliasとして予約できない。`make:operation`と`make:migration`は変更していない。

README、全Public Guide、Internal Runtime Documentation、Quickstart、Compose、Setup、Consumer TestをCanonical名へ同期した。Stable `1.0.0`のRoot README例とInstalled Applicationへ登録しない内部低レベルCommandは、実際の互換境界として旧名を維持した。

## Changed Files

### Production Code

- `src/Internal/Application/ApplicationConsoleKernel.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/Console/ApplicationOperationListCommand.php`
- `src/Internal/Console/DatabaseMigrationMigrateCommand.php`
- `src/Internal/Console/DatabaseMigrationStatusCommand.php`
- `src/Internal/Console/LazyFrameworkCommand.php`
- `src/Internal/Console/RetentionPlanCommand.php`
- `src/Internal/Console/RetentionPurgeCommand.php`
- `src/Internal/Console/SchedulerDaemonCommand.php`
- `src/Internal/Console/SchedulerRunCommand.php`
- `src/Internal/Console/WorkerRunCommand.php`

### Tests and Consumer Fixtures

- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/MvpSampleEndToEndTest.php`
- `tests/Consumer/framework-update-generators.sh`
- `tests/Consumer/frankenphp-worker-mode.sh`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/quickstart-setup.sh`
- `tests/Consumer/skeleton-create-project.sh`

### Quickstart and Documentation

- `examples/quickstart/README.md`
- `examples/quickstart/bin/setup`
- `examples/quickstart/compose.yaml`
- `README.md`
- `docs/guide/database-migrations.md`
- `docs/guide/execution.md`
- `docs/guide/first-operation.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/operations.md`
- `docs/guide/project-cli.md`
- `docs/guide/project-generators.md`
- `docs/guide/retention.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/guide/troubleshooting.md`
- `docs/guide/validation.md`
- `docs/internal/bootstrap.md`
- `docs/internal/maintenance-scheduler.md`
- `docs/internal/postgresql-journal-store.md`
- `docs/internal/retention-plan.md`
- `docs/internal/worker-runtime.md`

### Orchestration

- `develop/TODO.md`
- `develop/orchestration/tasks/P10-007-project-cli-command-names.md`
- `develop/orchestration/reports/P10-007-project-cli-command-names.md`
- `develop/STATE.md`

D092、Spec 48／49／50／51／55、Spec README、Task PacketのOrchestrator作成済み差分は保持し、Worker Scopeから変更していない。

## Decisions and Assumptions

- Canonical名はD092の9個へ限定し、Generatorの`make:*`と内部低レベルCompiler Commandは変更しない。
- Legacy名は別Commandを複製せず、Canonical Lazy DescriptorのSymfony Console Aliasとして登録する。実処理とRuntime Dependencyの遅延構成を共有する。
- Framework予約名の検査はApplication Command自身のNameだけでなく、Applicationが宣言するAliasesにも適用する。
- Stable `1.0.0`のRoot README Quickstartは`bin/blackops blackops:*`が実体なので書き換えず、`main` Channelの例だけCanonical名へ変更する。
- Framework Update ConsumerはLegacy 1.0.0をCommitted HEAD、Current 1.1.0をWorking Treeから構成する。これによりReview前Commit禁止を守ったままCommand RenameをUpdate後のPackageとして検証する。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit \
  tests/Internal/Application/ApplicationConsoleKernelTest.php \
  tests/Integration/ApplicationConsoleKernelTest.php \
  tests/Integration/ApplicationHttpRuntimeTest.php \
  tests/Integration/MvpSampleEndToEndTest.php
Result: OK (16 tests, 160 assertions)。Canonical／Legacy 9個と競合予約をTargeted確認。

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: RootとQuickstartのComposer Metadataがstrict validationに成功。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
Result: Format済み、Lint No issues found。

docker compose run --rm app mago analyze
Result: 初回は競合名のarray_intersect結果がmixedと推論され1 Error。予約名の逐次型付き検査へ修正し、最終RunはNo issues found。

docker compose run --rm app vendor/bin/phpunit
Result: OK (871 tests, 2853 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1712 / Warnings 0 / Errors 0。

bash tests/Consumer/quickstart-e2e.sh
Result: Quickstart consumer E2E passed。Canonical List／Build／Migration／Worker／Retentionを完走。

bash tests/Consumer/frankenphp-worker-mode.sh
Result: FrankenPHP worker mode consumer E2E passed。Bootstrap、Flush、Rejected隔離、DB Disconnect／Reconnect、Multi-request、Memory／Restart、Classic Fallback成功。

bash tests/Consumer/quickstart-setup.sh
Result: Quickstart setup tests passed。Next StepsがCanonical Commandを表示。

bash tests/Consumer/skeleton-create-project.sh
Result: Skeleton create-project smoke passed。Canonical Buildが成功。

bash tests/Consumer/framework-update-generators.sh
Result: 初回はCurrent 1.1.0 FixtureがCommitted HEADの旧Console実装を保持しCanonical BuildでExit 255。Legacy=HEAD／Current=Working TreeへFixture境界を修正後、Framework update generator smoke passed。

bash tests/Consumer/skeleton-publication.sh --dry-run
Result: Skeleton publication dry run passed。version=1.0.1、split=working-tree。

mise exec -- pnpm --dir docs/website run test
Result: 35 tests / 35 passed / 0 failed。

mise exec -- pnpm --dir docs/website run check
Result: Content／Mermaid／Astro Check成功。16 files / 0 errors / 0 warnings / 0 hints。

mise exec -- pnpm --dir docs/website run build
Result: 28 Public Pages plus 404、Pagefind 29 HTML、Artifact／Site／Search Guard成功。既知のMermaid Chunk Warningのみ。

for file in <changed Consumer shell scripts>; do bash -n "$file"; done
docker compose run --rm app php -l examples/quickstart/bin/setup
! rg -n 'php blackops blackops:' README.md docs/guide examples tests/Consumer
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
Result: 初回の一括Syntax確認はPHP SetupをBashとして扱ったため失敗。ShellとPHPをInterpreter別に再実行し、Syntax、Active Command表記、PHP管理ID、Diff Checkがすべて成功。
```

## Acceptance Criteria

- [x] `php blackops list`がPrefixなしCanonical名をFramework Commandとして表示する
- [x] 9個のCanonical Commandが従来と同じ処理を実行する
- [x] 9個の旧`blackops:*`名が互換Aliasとして実行できる
- [x] Canonical名とAliasへApplication Commandが競合できない
- [x] `make:operation`と`make:migration`が不変である
- [x] README、全Public Guide、Quickstart、Compose、Setup、Consumer TestがCanonical名を使用する
- [x] Active利用者向けSourceに`php blackops blackops:`が残らない
- [x] Website Unit／Check／Buildが成功する
- [x] Composer／Mago／PHPUnit／Deptracが成功する
- [x] Quickstart／Worker Mode／Skeleton／Framework Update Consumer Testが成功する

## Remaining Issues

P10-007のRepository内Blockerはない。Cloudflare External Configuration待ちはP10-006から継続する独立Blockerであり、本TaskのCommand Renameには影響しない。

## Suggested Next Action

OrchestratorがProduction Code、Legacy Alias、予約名競合、Framework Update Fixture、利用者向け表記を独立再検証し、P10-007をAcceptedとした。Task単位でCommitし、GitHub Actions結果を確認する。
