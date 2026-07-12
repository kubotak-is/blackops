# P7-003: Public HTTP Runtime Composition

Status: Ready

## Goal

Accepted Application Configuration SnapshotからCompile済みArtifact、PostgreSQL Canonical Journal、Inline Dispatcher、Deferred AcceptorをFramework内部で構成し、Installed Applicationが `BlackOps\Internal` を参照せず `Application::http()` からPSR-15 Handlerを取得できるようにする。

## In Scope

- Public `Application::http(): RequestHandlerInterface`
- 同一Application InstanceでのHTTP Handler遅延構成とInstance再利用
- `config/app.php` のBuild Artifact Configuration検証
- `config/database.php` のDBAL Connection Parameter／Schema検証
- Production Runtime Artifact LoadとBuild ID整合性
- PostgreSQL Canonical Journal Store
- Inline DispatcherとJSON Responder
- PostgreSQL Deferred Sender／Acceptance Orchestrator／HTTP Acceptor
- Framework所有のClock、Identifier、Codec、PSR-17 Factory Composition
- Bootstrap Errorの責務表示とSecret非露出
- Inline 200／Deferred 202とTransactional AcceptanceのIntegration Test
- Public API／Architecture Test、Guide／Internals Documentation

## Out of Scope

- `Application::console()` とConsole Kernel
- Build Artifact Compile CommandのPublic Composition
- Database Migrationの実行または自動化
- Worker／Scheduler／Retention Runtime
- Journal Observer／JSONL BackendのApplication Config
- Dotenv Loading
- `examples/quickstart/`
- FrankenPHP Front ControllerのSkeleton配置
- Generator Command
- Source Discovery／Reflection ScanへのProduction Fallback

## Relevant Specifications and Decisions

- `develop/decisions/064-installed-application-layout-and-bootstrap.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/42-installed-application-boundary.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/47-public-http-runtime-configuration.md`

## Files Allowed to Change

- `src/Application/Application.php`
- `src/Application/ApplicationBootstrapException.php`
- `src/Internal/Application/**`
- `src/Internal/Runtime/ProductionRuntimeComposer.php`
- `src/Internal/Runtime/ProductionRuntimeComposition.php`
- `src/Internal/Runtime/ProductionRuntimeDependencies.php`
- `tests/Application/**`
- `tests/Internal/Application/**`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Architecture/PublicApiArchitectureTest.php`
- `deptrac.yaml`
- `docs/guide/application-bootstrap.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/internals/application-bootstrap.md`
- `docs/internals/runtime-container.md`
- `develop/orchestration/tasks/P7-003-public-http-runtime-composition.md`
- `develop/orchestration/reports/P7-003-public-http-runtime-composition.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Required Public Contract

`Application` に次だけを追加する。

```php
public function http(): Psr\Http\Server\RequestHandlerInterface;
```

- `http()` は初回呼出時にRuntimeを構成する
- 同じApplication Instanceでは同じHandler Instanceを返す
- Public SignatureへInternal型、DBAL Connection、Container、Raw Configを露出しない
- `console()`、Container Getter、Config Getterを追加しない

## Build Configuration Contract

`config/app.php` の `build` Sectionから次を読む。

```text
operation_manifest  non-empty absolute path
http_manifest       non-empty absolute path
container           non-empty absolute path
container_class     non-empty class identifier
container_namespace string; empty allowed
```

- Missing Key、無効型、空の必須値、相対PathをBootstrap Errorとする
- Artifactの不存在、不正Format、Version／Build ID不一致をFail-fastする
- Source Discovery、Container Compile、Development DefaultへFallbackしない

## Database Configuration Contract

`config/database.php` から次を読む。

```text
connection  Doctrine DBAL connection parameter array
schema      non-empty valid PostgreSQL schema identifier
```

- Environment Variable名をFrameworkへHard Codeしない
- Connection ParameterはApplication Configが解決済み値を渡す
- Password、DSN Credential、Token、Connection ValueをError Messageへ含めない
- 一つのDBAL ConnectionをSender、Canonical Journal、Deferred Acceptance Transactionで共有する

## Runtime Composition Requirements

- Compile済みOperation／HTTP ManifestとContainerを一度だけLoadする
- Inline／Deferred Routeが同じRegistryとBuild IDを使う
- InlineはCompiled ContainerからHandlerを解決する
- DeferredはReflection JSON CodecでMessageを生成する
- Deferred AcceptanceはOperation State、Received、Accepted、Next Sequenceを既存Transaction境界で保存する
- Nyholm PSR-17をFramework Defaultとして内部構成する
- System ClockとUUIDv7 Factoryを内部構成する
- HTTP Runtime構成または起動でMigration、DDL、Artifact Compileを実行しない

## Constraints

- Production CodeとTestのComment／DocBlockへDecision、Spec、Task、TODOの管理番号を書かない
- Existing Internal Runtimeを無検討にPublic化、Rename、Moveしない
- `ApplicationConfigurationSnapshot` のRaw Environment／ConfigをPublic APIへ公開しない
- Credential値をException Message、Test failure message、Logへ含めない
- Deferred RouteをInline Dispatcherへ誤ってFallbackさせない
- Missing／Invalid Artifact時にDevelopment DiscoveryへFallbackしない
- Runtime未構成を成功扱いするDummy Handlerを追加しない

## Acceptance Criteria

- [ ] `Application::http()` がPSR-15 Handlerを返す
- [ ] 同一Applicationで複数回呼び出すと同じHandler Instanceを返す
- [ ] Build ConfigのMissing／型／空値／相対Pathを安全に拒否する
- [ ] Database ConfigのMissing／型／SchemaをCredential非露出で拒否する
- [ ] Missing／Invalid／Build ID不一致ArtifactをFallbackせず拒否する
- [ ] Compile済みContainerのHandlerでInline Routeが200 Responseを返す
- [ ] Deferred Routeが202とOperation IDを返す
- [ ] Deferred AcceptanceがState、Received、Accepted、Next Sequenceを一Transactionで保存する
- [ ] Inline／Deferredが同じCompile済みRegistryとHTTP Manifestを使う
- [ ] HTTP構成時にMigration、DDL、Build、Workerを実行しない
- [ ] Public SignatureへInternal型、Container、Connection、Raw Configを露出しない
- [ ] Focused／Full Test、Mago、Deptrac、Composer Validationが成功する
- [ ] Guide、Internals、Report、Checkpointが更新される

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit tests/Application tests/Internal/Application tests/Integration/ApplicationHttpRuntimeTest.php tests/Architecture/PublicApiArchitectureTest.php
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P7-003-public-http-runtime-composition.md` に次を記録する。

- Summary
- Public API and Lazy Composition
- Build and Database Configuration
- Inline／Deferred Composition Evidence
- Transaction and Process Safety Evidence
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
