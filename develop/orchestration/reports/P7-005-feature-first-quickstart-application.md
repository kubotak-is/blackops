# P7-005 Feature-first Quickstart Application Report

## Summary

Framework内の単一File MVP Fixtureを削除し、Install直後と同じFeature-first Source Tree、独立Composer Metadata、Public Bootstrap、HTTP／Console Entrypoint、Config、Environment Example、Generated State Boundaryを持つ `examples/quickstart/` へ移行した。

Quickstartは `blackops/skeleton` Distribution Repositoryへ将来SplitするSource of Truthである。P7-005ではDocker／Compose、Remote Consumer Install、Post-create Scriptを実装していない。

## Installed Tree and Composer Boundary

SpecのTreeどおり、`App\Feature\Welcome\ShowWelcome` と `App\Feature\Report\GenerateReport`、Application Provider、Bootstrap、Entrypoint、6 Config、Environment Example、README、Placeholder Directoryを配置した。`bin/blackops` はMode 755である。

`composer.json` は `blackops/skeleton`／`type: project`、PHP 8.5、`App\ => app/`、`App\Tests\ => tests/` を定義し、Framework、Dotenv、Nyholm PSR-7／Server、Laminas Emitterを直接Requireする。Root Framework Composerへ `App\` Dev Autoloadを追加していない。

Composer Lock、Path Repository、Vendor、Absolute Local Path、Docker／Compose、Generated Artifact／Logは含めない。Framework Constraintは初期Release系列として `^1.0` を置き、Release Versionとの機械的同期はPhase 8 Scopeとした。

## Feature Separation and Public API Evidence

WelcomeとReportはDefinition、Value、Handler、Outcome、Feature固有ExceptionをAction Directoryの個別Fileへ分離した。両Feature間の直接Class参照をArchitecture Testで禁止する。

WelcomeはHeader Sensitive MaskとInline 200 Outcome、ReportはAPI Token Sensitive Mask、Deferred 202、初回Retryable Failure、次Attempt Successを維持する。ProviderはPublic `OperationProvider`／`ServiceProvider` Contractだけを利用する。

Quickstart内の全PHP Fileに `BlackOps\Internal` ImportがないことをArchitecture TestとRequired `rg` で確認した。

## Bootstrap／HTTP／Console Entrypoint Evidence

`bootstrap/app.php` は既存Process Environmentを優先してSkeleton-owned Dotenvをsafe loadし、解決済み文字列Environment、Config、ProviderをPublic Application Builderへ渡す。`.env` 不在はErrorにしない。

`public/index.php` はQuickstart Autoloader、Bootstrap、Nyholm Request Creator、`Application::http()`、Laminas SAPI Emitだけを所有する。Laminas参照はこのFileだけである。

`bin/blackops` はAutoloaderとBootstrapを読み、`$application->console()->run()` のExit Codeを返すだけである。EntrypointはBuild、Migration、Worker、Retentionを暗黙実行しない。

## Config／Environment／Generated State Evidence

- `app.php`: Build ID、`var/build` の3 Artifact、Container Class／Namespace
- `database.php`: Environment由来DBAL ParameterとSchema
- `execution.php`: Worker ID、Lease、Heartbeat、Grace、継続Flag
- `operations.php`: Application Operation Provider
- `journal.php`: Public／Internal Backendを参照しない空Placeholder
- `retention.php`: 4保持期間、Policy Ref、Actor

`.env.example` はLocal PostgreSQL、Worker、Retentionの安全なDefaultだけを持つ。`.gitignore` は `.env`、Vendor、Generated Artifact／Logを除外し、`composer.lock` は除外しない。`var/build` と `var/log` は `.gitignore` だけを保持する。

## Migrated Integration Evidence

MVP E2E、Application HTTP Runtime、Application Console Kernelの3 Integration TestをQuickstart Sourceへ移行した。TestはQuickstartのPHP Sourceを明示requireし、一時Provider ConfigまたはPublic BuilderへProvider Class Nameを渡す。Root Composerの偶発的なApplication Autoloadに依存しない。

Focused Testで次を維持した。

- 3 Artifact BuildとProduction Load
- Inline Welcome 200とLifecycle Journal
- Deferred Report 202、Retry Schedule、再起動Worker成功、Typed Outcome
- Public HTTP RuntimeとConsole KernelのBuild／Migration／Worker／Retention導線
- Installed Tree、Composer Boundary、Internal Import不在、Feature独立、Laminas境界

## Changed Files

- `examples/mvp/**`（削除）
- `examples/quickstart/**`
- `tests/Integration/MvpSampleEndToEndTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `docs/guide/mvp-sample.md`
- `docs/guide/application-bootstrap.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/internals/bootstrap.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P7-005-feature-first-quickstart-application.md`
- `develop/orchestration/reports/P7-005-feature-first-quickstart-application.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Quickstart Source内へFramework Root専用Path Repositoryを保存せず、Local Consumer Install時の一時Repository注入はP7-006で行う。
- Post-create Script、Framework ConstraintのRelease同期、Split WorkflowはPhase 8で実装する。
- DotenvはApplication所有とし、Frameworkへ依存を追加しない。
- ConfigのOperation ProviderとBootstrapの明示ProviderはIdentity重複排除により一度だけ登録される。
- Docker／Composeを先行配置せず、READMEはHost PHP／Composer／PostgreSQLの手動手順を説明する。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid.

docker compose run --rm app mago format --check src tests examples/quickstart/app examples/quickstart/bootstrap examples/quickstart/config examples/quickstart/public
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit tests/Integration/MvpSampleEndToEndTest.php tests/Integration/ApplicationHttpRuntimeTest.php tests/Integration/ApplicationConsoleKernelTest.php tests/Architecture/QuickstartApplicationArchitectureTest.php
Result: OK (8 tests, 149 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/phpunit
Result: OK (626 tests, 2120 assertions). Runtime PHP 8.5.7.

! rg -n 'BlackOps\\Internal' examples/quickstart --glob '*.php'
Result: No matches (negated command exited 0).

! find examples/quickstart -name composer.lock -o -path '*/vendor/*' -o -type f -path '*/var/build/*' ! -name .gitignore -o -type f -path '*/var/log/*' ! -name .gitignore | grep .
Result: Task Packet記載のunparenthesized expressionをそのまま実行。No output、negated check exited 0.

! rg -n '"type"[[:space:]]*:[[:space:]]*"path"|/home/|/mnt/' examples/quickstart/composer.json
Result: No matches (negated command exited 0).

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples/quickstart --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Feature-first Installed Treeと独立Composer Metadataを配置した
- [x] Lock、Path Repository、Vendor、Generated StateをSourceから除外した
- [x] Application／Process BoundaryからInternal Importを排除した
- [x] Laminas参照をHTTP Emitterだけへ限定した
- [x] Public Bootstrap、HTTP、Console Entrypointを実装した
- [x] Welcome／ReportをFeature Action Directoryへ分離した
- [x] Sensitive Mask、Deferred Retry、Typed Outcomeを維持した
- [x] Existing Integration TestをQuickstart Sourceへ移行した
- [x] Root Dev Autoload非依存をArchitecture Testで検証した
- [x] READMEへ手動Setupと明示Process Commandを記載した
- [x] Required Commandsがすべて成功した
- [x] Docs、TODO、Report、Checkpointを更新した

## Remaining Issues

Blockerはない。Docker／Compose Local Runtime、独立Composer Install、HTTP Process Consumer E2EはP7-006、Post-create／Split／Release同期はPhase 8 Scopeである。

## Suggested Next Action

Orchestrator CodexがInstalled Tree、Composer Metadata、Public Entrypoint、Environment Precedence、Feature独立、Integration移行をReviewし、受入後にLocal Runtime and Consumer E2EをP7-006として実装する。

## Orchestrator Review

2026-07-13に差分とTask ScopeをReviewし、Feature-first Installed Tree、独立Composer Metadata、Public Bootstrap／Entrypoint、Environment優先順位、Welcome／Report分離、旧MVP Integration移行、Generated State除外を確認した。

OrchestratorがFocused Test 8件149 Assertions、Full Test 626件2120 Assertions、Root／Quickstart Composer Validation、Mago Format／Lint／Analyze、Internal Import、Path Repository、Lock／Vendor／Generated State、管理ID、`git diff --check` を再実行し、すべて成功した。P7-005をAcceptedとする。
