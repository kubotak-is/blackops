# P7-003 Public HTTP Runtime Composition Report

## Summary

Accepted Application Configuration SnapshotからCompile済みOperation／HTTP Manifest、Compiled Container、PostgreSQL Canonical Journal、Inline Dispatcher、Deferred AcceptorをFramework内部で構成し、Installed ApplicationがPublic `Application::http()` からPSR-15 Handlerを取得できるようにした。

HTTP Handlerは初回呼出時だけ遅延構成し、同じApplication Instanceでは同じHandler Instanceを再利用する。Runtime構成はArtifact Compile、Source Discovery、Migration、DDL、Worker起動を行わない。

## Public API and Lazy Composition

`Application` に次のPublic Methodだけを追加した。

```php
public function http(): Psr\Http\Server\RequestHandlerInterface;
```

Internal `ApplicationHttpRuntime` がAccepted Snapshotと構成済みHandlerを保持する。Public Signature、constructor、PHPDocへInternal Snapshot、Container、DBAL Connection、Raw Configを露出しない。Applicationのprivate constructorとprivate Snapshot境界も維持した。

## Build and Database Configuration

`ApplicationBuildConfiguration` は `app.build` の次のKeyを検証する。

- `operation_manifest`、`http_manifest`、`container`: 空でない絶対Path
- `container_class`: 空でないClass Identifier
- `container_namespace`: 空文字を許容するNamespace String

`ApplicationDatabaseConfiguration` は `database.connection` を文字列Parameter名の配列、`database.schema` を安全なPostgreSQL Identifierとして検証する。Environment Variable名はFrameworkへHard Codeせず、Connection ValueをError Messageへ埋め込まない。

Production Artifact LoaderがMissing File、不正Envelope／Schema Version、Build ID不一致、Container Class不一致を既存ContractでFail-fastし、Source DiscoveryやCompileへFallbackしない。

## Inline／Deferred Composition Evidence

Application HTTP Composerは一度Loadした同じCompiled Operation Registry、HTTP Manifest、ContainerからInline DispatcherとDeferred Acceptorを構成する。

Integration TestではMVP Sample Artifactを事前Compileし、次を確認した。

- `GET /welcome` がCompiled ContainerのHandlerを解決してHTTP 200とJSON Outcomeを返す
- `POST /reports` がDeferred StrategyとしてHTTP 202、Operation ID、Accepted Atを返す
- 同じApplicationから2回 `http()` を呼び、同じHandler Instanceを返す
- Missing ArtifactをBootstrap Errorとして拒否し、Development Fallbackしない

## Transaction and Process Safety Evidence

一つのDBAL ConnectionをPostgreSQL Canonical Journal Store、Deferred Sender、Deferred Acceptance Orchestratorへ共有する。既存Orchestratorの `Connection::transactional()` 境界により、Operation State Insert、Received／Accepted Journal、Next Sequence更新を一Transactionで保存する。

Integration TestはDeferred受付後に `state=accepted`、`next_sequence=3`、Journal 2件を確認した。また `http()` 構成直後にFramework Schemaが存在しないことを確認し、暗黙Migration／DDLがないことを証明した。ArtifactはTestの明示Compile、SchemaはTestの明示Migrationで事前準備した。

## Changed Files

- `src/Application/Application.php`
- `src/Internal/Application/ApplicationBuildConfiguration.php`
- `src/Internal/Application/ApplicationDatabaseConfiguration.php`
- `src/Internal/Application/ApplicationHttpRuntime.php`
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php`
- `src/Internal/Runtime/ProductionRuntimeComposer.php`
- `src/Internal/Runtime/ProductionRuntimeDependencies.php`
- `tests/Application/ApplicationTest.php`
- `tests/Internal/Application/ApplicationConfigurationTest.php`
- `tests/Internal/Application/ApplicationHttpConfigurationTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `docs/guide/application-bootstrap.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/internals/application-bootstrap.md`
- `docs/internals/runtime-container.md`
- `develop/orchestration/tasks/P7-003-public-http-runtime-composition.md`
- `develop/orchestration/reports/P7-003-public-http-runtime-composition.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Nyholm PSR-17 FactoryとFramework System Clock／UUIDv7 FactoryをApplication内から差替えるPublic Extension Pointは設けず、Framework Defaultとして内部構成する。
- DBAL Connection ParameterのEnvironment解決はApplication Configの責務とし、Frameworkは解決済み配列だけを受け取る。
- HTTP Runtime構成失敗は責務を示す `ApplicationBootstrapException` へ変換する。Config Validationの安全なKey情報は保持し、Connection ValueはMessageへ含めない。
- Request処理開始後のJournal／Transport Failureは既存Runtime Exception Contractを維持する。
- Deferred AcceptorはProduction Runtime ComposerのOptional Dependencyとして渡し、Inlineだけを使う既存Internal Composer利用との互換性を保つ。

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

docker compose run --rm app vendor/bin/phpunit tests/Application tests/Internal/Application tests/Integration/ApplicationHttpRuntimeTest.php tests/Architecture/PublicApiArchitectureTest.php
Result: OK (31 tests, 97 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/phpunit
Result: OK (613 tests, 1984 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 333 files / Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1366 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Public `Application::http()` とInstance単位のLazy Cacheを実装した
- [x] Build／Database Configを安全に検証する
- [x] Missing／Invalid／Build ID不一致ArtifactをFallbackなしで拒否する
- [x] Compile済みContainerのInline Routeが200を返す
- [x] Deferred Routeが202とOperation IDを返す
- [x] State、Received、Accepted、Next Sequenceを一Transactionで保存する
- [x] Inline／Deferredが同じCompiled Registry／HTTP Manifestを使う
- [x] HTTP構成がMigration、DDL、Build、Discovery、Workerを実行しない
- [x] Public SignatureへInternal型、Container、Connection、Raw Configを露出しない
- [x] CredentialをBootstrap Error Messageへ露出しない
- [x] Focused／Full Testと全品質Commandが成功する
- [x] Guide、Internals、Report、Checkpointを更新する

## Remaining Issues

Blockerはない。Public Console Kernel、Build／Migration Command Composition、Worker／Maintenance RuntimeはTask Scope外であり、後続Taskで実装する。

## Suggested Next Action

Orchestrator CodexがPublic API、Config Error、Lazy Cache、Connection共有、Deferred Transaction、Process SafetyをReviewし、受入後にPublic Console Kernel CompositionをTask化する。

## Orchestrator Review

2026-07-12に差分とTask ScopeをReviewし、Public APIが `Application::http()` のみに限定されること、Handlerの遅延Cache、Build／Database ConfigのFail-fast、単一Connectionの共有、Inline 200／Deferred 202とTransaction結果、暗黙Migration／Buildがないことを確認した。

OrchestratorがFocused Test 31件97 Assertions、Full Test 613件1984 Assertions、Composer Validation、Mago Format／Lint／Analyze、Deptrac、管理ID Check、`git diff --check` を再実行し、すべて成功した。P7-003をAcceptedとする。
