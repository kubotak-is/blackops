# P7-005: Feature-first Quickstart Application

Status: Accepted

## Goal

`examples/mvp/` のFramework内Fixtureを、Install直後と同じFeature-first Tree、独立Composer Metadata、Public Bootstrap、HTTP／Console Entrypoint、Config、Environment Example、Generated State Boundaryを持つ `examples/quickstart/` へ移行し、Phase 8の `blackops/skeleton` Source of Truthを作る。

## In Scope

- `examples/quickstart/` の独立Composer Project Source Tree
- `App\` PSR-4 Namespaceと `blackops/skeleton` Package Metadata
- Welcome Inline Feature
- Report Deferred／Retry Feature
- Application Operation／Service Provider
- Skeleton-owned Dotenv Bootstrap
- Public `Application` Builderだけを使う `bootstrap/app.php`
- Nyholm Server Request CreatorとLaminas SAPI Emitterだけを使う `public/index.php`
- Public Console Kernelだけを使う薄い `bin/blackops`
- Build、Database、Execution、Journal、Operation、Retention Config
- `.env.example`、`.gitignore`、`var/build`／`var/log` Boundary
- Local PHP／Composer／PostgreSQL手順のREADME
- Existing MVP Integration Test SourceのQuickstart移行
- `examples/mvp/` の重複Source削除
- Installed Tree／Internal Import／Feature Independence Test

## Out of Scope

- Dockerfile、FrankenPHP Dockerfile、Compose
- 独立Composer InstallとPath Repository注入
- HTTP Processを起動するConsumer E2E
- Post-create Composer Script
- Skeleton Split／Tag／Packagist Workflow
- Generator Command
- Remote Dependency Downloadを前提にするTest
- Journal Observer／Remote Logging Backend

## Relevant Specifications and Decisions

- `develop/decisions/064-installed-application-layout-and-bootstrap.md`
- `develop/decisions/065-composer-skeleton-publication.md`
- `develop/decisions/069-skeleton-http-entrypoint-adapters.md`
- `develop/spec/42-installed-application-boundary.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/49-feature-first-quickstart-application.md`

## Files Allowed to Change

- `examples/mvp/**` (delete)
- `examples/quickstart/**`
- `tests/Integration/MvpSampleEndToEndTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `docs/guide/mvp-sample.md`
- `docs/guide/application-bootstrap.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/internal/bootstrap.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P7-005-feature-first-quickstart-application.md`
- `develop/orchestration/reports/P7-005-feature-first-quickstart-application.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Required Installed Tree

Spec 49のProject Treeを実Fileとして配置する。P7-006 ScopeのDocker／Compose以外は、Install直後に存在するSourceとPlaceholder Directoryを含める。

- `composer.lock` を置かない
- `composer.json` へMain RepositoryのPath Repositoryを置かない
- `vendor/`、`.env`、Generated Artifact／Logを置かない
- `var/build/.gitignore` と `var/log/.gitignore` でDirectoryだけを維持する
- `bin/blackops` にExecutable Bitを付ける

## Composer Contract

```text
name: blackops/skeleton
type: project
php: >=8.5
autoload: App\ => app/
autoload-dev: App\Tests\ => tests/
```

`blackops/framework` は同一Release系列を許容するConstraintとし、P7-006のLocal Consumer InstallではSourceを書き換えず一時Repository設定でRoot Frameworkを解決する。

Skeletonが直接Classを参照する `vlucas/phpdotenv`、`nyholm/psr7`、`nyholm/psr7-server`、`laminas/laminas-httphandlerrunner` を直接Requireする。

## Application Source Contract

- `App\Feature\Welcome\ShowWelcome` と `App\Feature\Report\GenerateReport` をFeatureごとのAction Directoryへ分離する
- Definition、Value、Handler、Outcome、Feature固有Exceptionを個別Fileにする
- WelcomeはGET `/welcome`、Header Sensitive Mask、Inline 200 Outcome
- ReportはPOST `/reports`、API Token Sensitive Mask、Deferred 202、初回Retryable Failure、次Attempt Success
- ProviderはPublic `OperationProvider`／`ServiceProvider` Contractだけを使う
- Feature間の直接Class参照を禁止する
- `BlackOps\Internal` Importを禁止する

## Bootstrap and Entrypoint Contract

- `bootstrap/app.php` は`.env`をProcess Environment優先で安全に読み、Public Application Builderへ解決済みEnvironment、Config、Providerを渡す
- `.env` 不在をProduction Errorにしない
- `public/index.php` のLaminas参照はEmitterだけに限定する
- `public/index.php` はRequest生成、`Application::http()`、Response Emitだけを行う
- `bin/blackops` はApplication読込と `console()->run()` のExit Code返却だけを行う
- EntrypointでBuild、Migration、Worker、Retentionを暗黙実行しない

## Config and Generated State Contract

- `app.php`: Build IDと `var/build` の3 Artifact、Container Class／Namespace
- `database.php`: Environment由来DBAL ParameterとSchema
- `execution.php`: Worker ID／Lease／Heartbeat／Grace／継続Flag
- `operations.php`: Provider登録の正本またはBootstrapとの重複安全性
- `journal.php`: 未対応Internal Backendを参照しない責務Placeholder
- `retention.php`: 4保持日数、Policy Ref、Actor
- `.env.example`: Local用の安全なDefaultのみ
- `.gitignore`: `.env`、`vendor/`、Generated Artifact／Logを除外し、`composer.lock` は除外しない

## Test Migration Contract

- Existing 3 Integration Testが `examples/mvp` Namespace／Pathへ依存しない
- Quickstart Feature／Providerを明示requireして同じRuntime Evidenceを維持する
- Root Composer Dev Autoloadへ `App\` を追加しない
- Architecture TestがTree、Composer Metadata、Internal Import不在、Laminas境界、Feature間依存、Lock／Path Repository不在、Executable Entrypointを検証する
- Remote Composer InstallはP7-006へ残す

## Constraints

- Production CodeとTestのComment／DocBlockへDecision、Spec、Task、TODOの管理番号を書かない
- Skeletonへ `Internal` Directoryまたは `Infrastructure/BlackOps` Directoryを作らない
- Bootstrap／Config／Entrypointから `BlackOps\Internal` を参照しない
- Root Composer AutoloadでQuickstartをFramework Packageの一部にしない
- Composer MetadataへLocal Absolute Path、Credential、Tokenを含めない
- `.env.example` に実Secretを入れない
- HTTP／Console起動でMigrationまたはBuildを実行しない
- P7-006のDocker／Composeを先行実装しない

## Acceptance Criteria

- [x] Spec 49のP7-005 Treeが実Fileとして存在する
- [x] `examples/quickstart/composer.json` が独立Projectとしてstrict validation可能
- [x] Composer Lock、Path Repository、Vendor、Generated StateがSourceにない
- [x] Application CodeとProcess BoundaryにInternal Importがない
- [x] Laminas型がHTTP Emitter境界だけにある
- [x] Thin Bootstrap、HTTP Entrypoint、Console EntrypointがPublic APIだけを使う
- [x] Welcome／Report FeatureがAction Directory単位で分離される
- [x] Sensitive MaskとDeferred Retry Sampleを維持する
- [x] Existing Integration TestがQuickstart Sourceへ移行し成功する
- [x] Root Dev Autoloadへ依存しないことをArchitecture Testで検証する
- [x] READMEが手動Setupと明示Build／Migration／Worker／Retention導線を説明する
- [x] Focused／Full Test、Mago、Composer Validation、管理ID、Diff Checkが成功する
- [x] Report、Checkpoint、TODOが更新される

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples/quickstart/app examples/quickstart/bootstrap examples/quickstart/config examples/quickstart/public
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit tests/Integration/MvpSampleEndToEndTest.php tests/Integration/ApplicationHttpRuntimeTest.php tests/Integration/ApplicationConsoleKernelTest.php tests/Architecture/QuickstartApplicationArchitectureTest.php
docker compose run --rm app vendor/bin/phpunit
! rg -n 'BlackOps\\Internal' examples/quickstart --glob '*.php'
! find examples/quickstart -name composer.lock -o -path '*/vendor/*' -o -type f -path '*/var/build/*' ! -name .gitignore -o -type f -path '*/var/log/*' ! -name .gitignore | grep .
! rg -n '"type"[[:space:]]*:[[:space:]]*"path"|/home/|/mnt/' examples/quickstart/composer.json
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples/quickstart --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P7-005-feature-first-quickstart-application.md` に次を記録する。

- Summary
- Installed Tree and Composer Boundary
- Feature Separation and Public API Evidence
- Bootstrap／HTTP／Console Entrypoint Evidence
- Config／Environment／Generated State Evidence
- Migrated Integration Evidence
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
