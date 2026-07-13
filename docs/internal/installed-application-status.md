# Installed Application Status

Status: Phase 9 Complete in Main; Framework and Skeleton 1.0.0 Published

この文書はRepositoryのPhase Acceptance Evidenceを記録するFramework実装者向け資料であり、公開Documentation WebsiteのContent Sourceには含めない。利用者向けの現行機能と制約は[Guide MVP Status](../guide/mvp-status.md)を参照する。

`examples/quickstart/` はFeature-firstのInstalled Application Exampleと`blackops/skeleton`のSource of Truthである。Framework／Skeleton Stable `1.0.0`をGitHubとPackagistへ公開し、Remote `composer create-project`を検証済みである。Main BranchではPhase 9のProject GeneratorとApplication Migration Runtimeも完成した。Phase 9の実装完了は新しいPackagist Releaseの公開を意味せず、Phase 8 CompleteとStable Package公開もProduction Readyを意味しない。

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
  bin/setup
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

## Phase 8 Publication Evidence

Skeletonには再実行可能な`bin/setup`とComposer `post-create-project-cmd`が実装済みである。Setupは未作成`.env`のCopyとLocal生成Directoryの準備だけを行い、既存`.env`を変更せず、外部ProcessやRuntime Side Effectを起動しない。`--no-scripts`利用時も`php bin/setup`で同じ準備を行える。

Committed Quickstartから`git archive`で抽出したClean Packageを使い、Local Skeleton／Framework Repositoryから通常と`--no-scripts`のCreate-projectが成功している。両PackageはCopy Installで、Lock、Vendor、Autoload、Post-create、Manual Setup、Source Cleanliness、Side Effect不在、Cleanupを検証済みである。

Framework Source RefからQuickstartだけを決定的にSubtree Splitし、同一Version Tagを付けるPublication Workflowが成功している。GitHub Actions WorkflowはFull Quality、Consumer、Create-project、Publication Gateの後だけDeploy Keyを展開し、Remote `main`とTagをfail-closedで更新する。Framework `1.0.0`はFramework Commit `279716f`、Skeleton `1.0.0`はSplit Commit `da573f3`としてGitHub／Packagistへ公開済みである。

空のComposer Homeと一時DirectoryからPackagistだけを使用し、次を検証した。

- 通常の`composer create-project blackops/skeleton my-app 1.0.0`
- `--no-scripts` InstallとManual `bin/setup`
- Skeleton Identity、Framework `1.0.0` Lock、Consumer Autoload、Project CLI
- `.env` Copy、再実行非上書き、Generated State不在
- Install／SetupによるDocker、Database、Migration、Build Side Effect不在

Phase 7 Consumer E2EとLocal Create-projectはSource／Runtime境界、P8-004 Remote Smokeは公開Package可用性を担当し、両方の証拠を維持する。詳細は [Phase 8 Closeout Report](../../develop/orchestration/reports/P8-004-phase-8-closeout.md) を参照する。

## Phase 9 Project CLI Evidence

Project所有の`bin/blackops`から、Framework所有の`make:operation`と`make:migration`を利用できる。Operation GeneratorはTyped Self-handled Operation／Value／Outcomeを安全に生成し、Migration GeneratorはApplication所有のUTC Version Fileを生成する。Application MigrationはFramework Migrationと同じ明示Database Commandで、Framework Namespaceを先に実行する。

Framework Update SmokeはRepository外の一時ConsumerにLocal `1.0.0`旧版相当と`1.1.0` Current Framework Packageを構成し、ComposerがFramework Dependencyだけを更新するScenarioを再現する。Update前後でProject Entrypoint、既存生成Operation、既存生成Migrationのhashが一致する。Update後のVendor Command Source／StubがCurrent Frameworkとbyte一致し、新規生成出力とSourceだけがCurrent Command／Stubへ切り替わることも検証する。

Quickstart Consumer E2EとLocal Create-project SmokeもOperation／Migration生成を含み、生成OperationのBuildとApplication Migrationの適用を検証する。Framework StubはFramework Packageの`resources/stubs/`だけに置き、Skeleton Sourceへ複製しない。
