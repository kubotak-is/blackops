# Installed Application Status

Status: Phase 7 Complete

`examples/quickstart/` は、Feature-firstのInstalled Application Exampleと将来の `blackops/skeleton` Package Source Boundaryとして成立している。Phase 7 CompleteはPackagist公開、Production Ready、Stable Releaseを意味しない。

## Phase 7 Acceptance Evidence

| Acceptance criterion | Status | Evidence |
| --- | --- | --- |
| Quickstartが独立Composer Projectとして成立する | Satisfied | `examples/quickstart/composer.json` は `blackops/skeleton`、`type: project`、独自のPSR-4 Autoloadと直接Dependencyを持つ。Rootへ `App\\` Autoloadを追加せず、Root／Quickstart両方のComposer Validationが成功した。 |
| Application CodeとBootstrapにInternal Importがない | Satisfied | Quickstart Architecture Testと全Quickstart PHPを対象にしたInternal Import Guardが成功した。Public Application Builder、HTTP Handler、Console KernelだけでProcessを構成する。 |
| Welcome／ReportをDirectory単位で削除できる | Satisfied | `app/Feature/Welcome/` と `app/Feature/Report/` は独立し、Build-time DiscoveryによりProvider一覧やBootstrapの編集なしで追加・削除できる。 |
| HTTPとConsoleを同じConfiguration Snapshotで構成できる | Satisfied | `bootstrap/app.php` が一つのApplicationを作り、`public/index.php` と `bin/blackops` がPublic `http()`／`console()` Compositionを利用する。P7-002からP7-004のAccepted TestがSnapshot、HTTP、Console境界を検証した。 |
| Project所有CLIがFramework Commandを起動する | Satisfied | `examples/quickstart/bin/blackops` はApplicationのPublic Console Kernelを起動し、Build、Operation List、Migration、Worker、Retention、Scheduler Commandを提供する。 |
| BuildとMigrationが明示Commandである | Satisfied | Compose startupはInstall、Build、Migrationを実行しない。Consumer E2EはBuild時とRead-only Status後のSchema不在、明示Migrate後のSchema作成を検証する。 |
| Local RuntimeでInline／Deferred／Worker／Retry／Outcome／Retentionを検証できる | Satisfied | Quickstart所有のPHP 8.5、FrankenPHP 1、PostgreSQL 18 RuntimeでWelcome 200、Report 202、Worker Retry後Completed、Encoded Outcome、Sensitive Projection、Retention Plan／Dry Runを検証した。 |
| Root Dev AutoloadなしConsumer E2Eが成功する | Satisfied | `tests/Consumer/quickstart-e2e.sh` は一時ConsumerへFrameworkを`symlink=false`でmirror installし、通常RuntimeからFramework Root mountを外してConsumer `vendor/autoload.php`だけでScenarioを完走する。 |
| Full Quality SuiteとArchitecture Guardが成功する | Satisfied | P7-007でComposer／Compose、Mago Format／Lint／Analyze、Full PHPUnit、Deptrac、Consumer E2E、Internal／Source Cleanliness／管理ID Guard、Diff Checkを再実行した。 |

詳細なCommand結果は [Phase 7 Closeout Report](../../develop/orchestration/reports/P7-007-phase-7-closeout.md) に記録する。

## Installed Tree

```text
examples/quickstart/
  app/Feature/Welcome/ShowWelcome/
  app/Feature/Report/GenerateReport/
  bin/blackops
  bootstrap/app.php
  config/{app,database,execution,journal,operations,retention}.php
  public/index.php
  tests/
  var/{build,log}/
  .env.example
  .gitignore
  Caddyfile
  compose.yaml
  composer.json
  Dockerfile
  Dockerfile.frankenphp
  README.md
```

このTreeは [Installed Application Layout](../../develop/spec/43-installed-application-layout-and-bootstrap.md) と、その具体化である [Feature-first Quickstart Application](../../develop/spec/49-feature-first-quickstart-application.md) に一致する。`app/Infrastructure/` と `migrations/` は必要になったApplicationが追加する任意の配置先であり、空Directoryとしては配布しない。`tests/` とGenerated Directoryは `.gitignore` で保持し、Generated Artifact、Log、`.env`、`vendor/`、`composer.lock` はSourceへ含めない。

## Authoring and Process Boundary

Operation自身がHandlerを兼ねるSelf-handled形式を標準とする。Constructor Dependencyなどで責務を分ける場合はOptional `#[HandledBy]` とSeparate Handlerを利用できる。Operation DiscoveryとDI Container生成はBuild時だけに行われ、Production HTTP／Worker RuntimeはCompile済みArtifactへFail-fastする。

Default Compose ServiceはPostgreSQLとHTTPだけである。Composer Install、Artifact Build、Migration、Worker、Scheduler、Retention Purgeは明示CommandまたはProfileで実行する。変更を伴うPurgeは追加の`--confirm`を要求する。

## Phase 8 Handoff

Phase 8はこのDirectoryを `blackops/skeleton` の配布Sourceとして扱い、次を実施する。

- Skeleton Distribution RepositoryまたはPackage生成境界を確定する
- Framework Release VersionとSkeleton Constraintを同期する
- PackagistへPackageを公開する
- `composer create-project blackops/skeleton my-app` を提供する
- 公開PackageからのInstall後Smoke Testを整備する

Phase 7のConsumer E2Eは一時Path RepositoryによるLocal copy installである。Remote Packageの存在や公開後Installを保証しない。
