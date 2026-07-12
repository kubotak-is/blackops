# P7-004 Public Console Kernel Composition Report

## Summary

Accepted Application Configuration SnapshotからFramework標準CommandとApplication独自Commandを登録するPublic `ConsoleKernel` を実装した。Installed Applicationは `Application::console()->run()` だけでBuild、Operation List、Migration、Worker、Retention、Schedulerを明示実行できる。

9つのFramework Commandは名前、Description、Option Definitionだけを常時登録し、実CommandとRuntime Dependencyは対象Commandのexecute時まで生成しない。Kernel構成、`list`、`help` はDatabase、Artifact、PCNTL、Retention Configを要求しない。

## Public API and Lazy Command Evidence

`Application::console(): ConsoleKernel` と、Public final `ConsoleKernel::run(?InputInterface, ?OutputInterface): int` を追加した。両constructorはprivateで、同じApplicationから同じKernel Instanceを返す。

Public SignatureへInternal型、Symfony Application、Container、Connection、Raw Configを露出しない。Symfony ApplicationはInternalに閉じ、auto-exitと内部Exception Catchを無効化してPublic Boundaryが安全なBootstrap Errorへ変換する。

`LazyFrameworkCommand` はFactoryをexecute時だけ呼ぶ。ConfigなしApplicationで `list` とWorker `help` を実行し、9 Commandすべてを表示できることを確認した。Application Commandの実行とFramework Command名競合拒否もTestした。

## Application-aware Build Evidence

`blackops:build:compile` はSnapshotのOperation／Service Providerと `app.build` のBuild ID、Artifact Path、Container Class／Namespaceだけを使用する。Provider Config PathやArtifact CLI引数は要求せず、Source DiscoveryへFallbackしない。

Integration TestでMVP ProviderをSnapshotへ登録し、引数なしBuildからOperation Manifest、HTTP Manifest、Compiled Containerの3 Artifactを生成した。同じBuild前にOperation Listが `welcome.show` と `report.generate` を表示した。Build後もDatabase Schemaが存在しないことを確認した。

## Migration and Worker Composition Evidence

Migration Status／Migrateは対象Command実行時だけDatabase ConfigとRunnerを構成する。Integration TestではStatusがPending 2をRead-onlyで表示し、Schemaを作成しなかった。Migrateの明示実行後だけSchemaが作成された。

WorkerはCompile済みArtifact、Execution Config、Database Configを実行時に検証する。Main ConnectionをReceiver、Settlement、Lifecycle、Journal、Outcome、Recoveryへ共有し、Heartbeatは同じParameterから別Connectionを作る。同一 `PcntlSignalHeartbeat` InstanceをDeferred Runtime GuardとWorker Loop Signalへ渡していることをReflection Testで確認した。

HTTPで受け付けたDeferred ReportをWorker Commandの1 iterationで処理し、Handler Failure Supervision後に `retry_scheduled` へ遷移したことを確認した。WorkerはCompile、Migration、DDLを実行しない。

## Retention and Scheduler Evidence

`config/retention.php` の4保持期間、Policy Ref、Actorを検証し、一つの遅延 `ApplicationRetentionRuntime` をPlan、Purge、Schedulerで共有する。単一ConnectionからPlanner、Audit、Tombstone、Outcome／Dead Letter／Journal Delete Serviceを構成する。

Integration TestではConfig DefaultだけでPlan、Purge `--dry-run`、Scheduler Runを実行した。Purgeは `--confirm` または `--dry-run` の明示が必要で、Kernel構成やCommand一覧では実行されない。

## Secret and Process Safety Evidence

- Config値やConnection値をError Messageへ埋め込まない。
- Console Bootstrap ExceptionはPrevious chainをPublic Boundaryへ渡さず、Symfony／PHP表示によるCredential展開を防ぐ。
- Invalid Database SchemaとCredentialを含むConfigで、MessageとPreviousにCredentialがないことをTestした。
- Kernel／list／help／BuildはMigration、Purge、Worker、PCNTLを起動しない。
- Migration、Worker、Purge、Schedulerは対象Commandの明示実行だけで開始する。

## Changed Files

- `src/Application/Application.php`
- `src/Application/ConsoleKernel.php`
- `src/Internal/Application/**`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/Console/ApplicationOperationListCommand.php`
- `src/Internal/Console/LazyFrameworkCommand.php`
- `tests/Application/ApplicationTest.php`
- `tests/Internal/Application/**`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `docs/guide/application-bootstrap.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/guide/database-migrations.md`
- `docs/guide/retention.md`
- `docs/internals/application-bootstrap.md`
- `docs/internals/bootstrap.md`
- `docs/internals/worker-runtime.md`
- `docs/internals/maintenance-scheduler.md`
- `develop/orchestration/tasks/P7-004-public-console-kernel-composition.md`
- `develop/orchestration/reports/P7-004-public-console-kernel-composition.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Symfonyの標準LazyCommandはHelp取得時にFactoryを呼ぶため使用せず、Definitionを事前登録するFramework専用Proxyを実装した。
- Low-level Compile Commandは維持するがPublic Kernelへ登録せず、Snapshot-aware Commandを同じ標準Command名で登録する。
- Retention CLI Optionの未指定値はAccepted Config Defaultへ設定し、手動CommandとSchedulerのPolicy Driftを防ぐ。
- Worker DefaultはLease 60秒、Heartbeat 10秒、Grace 20秒、Handler Failure後継続trueとする。
- Scheduler Multi-start LockはScope外であり、外部Orchestratorの責務を維持する。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit tests/Application tests/Internal/Application tests/Internal/Console tests/Integration/ApplicationConsoleKernelTest.php tests/Architecture/PublicApiArchitectureTest.php
Result: OK (77 tests, 273 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/phpunit
Result: OK (622 tests, 2044 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 347 files / Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1470 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Public ConsoleKernelとApplication単位のInstance Cacheを実装した
- [x] 9 Framework CommandをRuntime Dependencyなしでlist／help表示する
- [x] Application Command実行とFramework名競合拒否を実装した
- [x] Snapshot-aware Build／Operation Listを実装した
- [x] Status read-only／Migrate explicitのMigration境界を実装した
- [x] WorkerのMain／Heartbeat別Connectionと同一Signal Instanceを構成した
- [x] WorkerがCompile済みArtifactからDeferred Operationを処理した
- [x] Retention Plan／Purge／Schedulerが同じConfig Policyを使う
- [x] 暗黙Migration、Build、Purge、Worker、Source Discoveryを追加していない
- [x] Credential-safe Bootstrap Errorを実装した
- [x] Public API、Focused／Full Test、Mago、Deptracが成功した
- [x] Guide、Internals、Report、Checkpointを更新した

## Remaining Issues

Blockerはない。Feature-first Quickstart、実 `bin/blackops`、Generator、Scheduler Multi-start LockはScope外である。

## Suggested Next Action

Orchestrator CodexがLazy Factory、Build Provider、Migration Read-only、Worker Connection／Signal、Retention Policy共有、Secret SafetyをReviewし、受入後にFeature-first Quickstart ApplicationをTask化する。

## Orchestrator Review

2026-07-12に差分とTask ScopeをReviewし、Public ConsoleKernelが `run()` だけを公開すること、9 CommandのFactoryがexecute時まで遅延すること、Application-aware Build、Migrationの明示境界、WorkerのConnection／Signal構成、Retention Policy共有、Credential-safe Errorを確認した。

OrchestratorがFocused Test 77件273 Assertions、Full Test 622件2044 Assertions、Composer Validation、Mago Format／Lint／Analyze、Deptrac、管理ID Check、`git diff --check` を再実行し、すべて成功した。P7-004をAcceptedとする。
