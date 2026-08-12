# P22-003B: Release Architecture and Export Closure

Status: Commit Approved — Post-Commit Export Pending

## Goal

P22-003AでPHP 8.5上に露出したDeptrac 152 violations／59 uncoveredと、`mago-lint-baseline.toml`のFramework archive漏れを、D141の明示Layer／bounded internal facility／Core configuration failure contractとD142のbounded facade／implementation contractで解消する。Architecture waiverやgeneric Public／Transport -> Internal permissionを導入せず、Review／Commit後のSHAをP22-003 replacement Final Fixed Candidateへ返す。

## Source of Truth

- `AGENTS.md`
- `develop/STATE.md`
- `develop/decisions/022-namespace-dependencies.md`
- `develop/decisions/140-release-quality-tooling-baseline.md`
- `develop/decisions/141-release-architecture-and-export-boundary.md`
- `develop/decisions/142-public-facade-and-internal-implementation-cycles.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/17-core-api.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- P22-003／P22-003A Task and Report

## Diagnostic Baseline

- Source Commit: `0eca056` (`build: establish release quality tooling baseline`)
- Deptrac 4.7.1／PHP 8.5: 857 files, 152 violations, 59 uncovered, 4,172 allowed, 0 skipped／warnings／errors
- Violation directions: Transport -> Internal 88, Transport -> StorageProtection 28, Internal -> Telemetry 24, HTTP -> Telemetry 4, Execution -> Telemetry 3, Internal -> Application 2, Transport -> Telemetry 2, Journal -> Telemetry 1
- Uncovered: Identifier／Idempotency／Outbox public contracts 56 dependencies and adopted Dotenv／Nyholm libraries 3 dependencies
- Framework export: tracked root `mago-lint-baseline.toml` is absent from both synchronized archive exclusion lists

## In Scope

- D141に従うDeptrac Layer／Ruleset／Library collector同期
- Generic Internalから分離したInternal Telemetry Runtime／Storage Protection Runtime／Deferred Transport Integrityのnarrow collectorと依存方向
- D142で決定したInternal Application／Auth／HTTP／Idempotency／FrankenPHP SAPI narrow collector、Public facade edge、bounded cycle contract
- Public Core `ConfigurationFailure` marker、`ApplicationBootstrapException` implementation、Internal CLI catch boundaryと回帰Test
- Public API inventory／Guide／Website reader contract同期
- Git／Composer archive exclusionとFramework export／Version guard同期
- Mago strict baseline verification。Source位置変化でgeneratorが要求する場合だけ再生成し、手書きIssue追加は禁止
- P22-003A／P22-003／Specification／TODO／Report／STATE同期

## Out of Scope

- Runtime behavior、Public method signature、Database Schema、Consumer journeyの変更
- Generic `Transport -> Internal`／`Internal -> Application` permission
- `skip_violations`、Deptrac baseline、uncovered ignore、Rule disable／Severity downgrade
- Storage Protection／Telemetry機能の再設計
- Branch Push、Tag、Skeleton publication、Packagist、GitHub Release、Documentation Deploy

## Files Allowed to Change

- `deptrac.yaml`
- `.gitattributes`
- `composer.json`
- `src/Core/Exception/ConfigurationFailure.php`（新規）
- `src/Application/ApplicationBootstrapException.php`
- `src/Internal/Console/ScheduledOperationRunCommand.php`
- `src/Internal/Console/StorageProtectionLazyCommand.php`
- `tests/Application/ApplicationTest.php`
- `tests/Internal/Console/ScheduledOperationRunCommandTest.php`
- `tests/Internal/Console/StorageProtectionLazyCommandTest.php`
- `tests/Architecture/PublicApiArchitectureTest.php`（必要な場合）
- `tests/Consumer/framework-package-export.sh`
- `tests/Consumer/version-baseline.sh`
- `mago-lint-baseline.toml`（generator-required driftだけ）
- `docs/guide/core-api.md`
- `docs/internal/runtime-dependencies.md`
- Documentation WebsiteのSource／Test（Core API reader contractに必要な範囲）
- D022／D141／D142、Specification 16／17／103、Specification index、TODO、STATE
- P22-003／P22-003A／P22-003B Task and Report

## Constraints

- GPT-5.6 Luna High workerがProduction／Test／Config／Documentation implementationを行い、Orchestrator Review前にCommitしない
- Bounded Internal collectorはcatch-all Internal collectorと重複させず、Transportへgeneric Internal accessを与えない
- `Application`／`Auth`／`Http`からcatch-all Internalへのpermissionを削除し、D142で列挙したnarrow implementationだけを許可する
- `ApplicationBootstrapException`はRuntimeException ancestryとsafe message behaviorを維持する
- Public `ConfigurationFailure`はThrowable detailや値を追加しないcategory markerとする
- Git／Composer archive exclusion listは完全一致を維持する
- Source／Test CommentへDecision／Spec／Task管理番号を書かない

## Acceptance Criteria

- [x] Deptrac 4.7.1がPHP 8.5で857 filesを解析し、violations／skipped／uncovered／warnings／errors 0で成功する
- [x] Identifier／Idempotency／Outbox／Dotenv／Nyholm依存が明示Layer／Libraryでcoveredになり、generic Internal permission／skipが存在しない
- [x] Telemetry／Storage Protection／Deferred integrity edgeはD141のbounded layerだけで許可される
- [x] ApplicationBootstrapExceptionのconcrete type／RuntimeException ancestry／safe CLI exit classificationが維持される
- [x] Pre-commit Framework Git／Composer root and exclusion contract passes with synchronized archive roots
- [ ] Post-commit Framework Git／Composer exact regular-file inventory matches, including public `src/Core/Exception/ConfigurationFailure.php`
- [x] Mago format／lint／baseline verify／analyze、focused PHPUnit、Full PHPUnit、Composer strict、Version Guardが成功する
- [x] Guide Core API count／entry、Website test／check／build／site checksが成功する
- [x] Public facade／Internal implementationの追加SCCをD142どおり5つのnon-overlapping collectorと列挙済みbounded edgeへ限定し、generic `Application/Auth/Http -> Internal`を残さない
- [x] Ruleset SCC guardが非自明なSCCを`Core / Idempotency / Telemetry`とD142で受入れた8-layer facade/internal SCCの2集合へ限定し、`InternalSapiRuntime`をSCC外として検証する
- [x] Documentation ReviewerがP1=0／P2=0を返し、Orchestratorがreplacement candidate commitを許可する

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac --no-progress --no-cache
docker compose run --rm app vendor/bin/phpunit tests/Application/ApplicationTest.php tests/Internal/Console/ScheduledOperationRunCommandTest.php tests/Internal/Console/StorageProtectionLazyCommandTest.php tests/Architecture/PublicApiArchitectureTest.php
docker compose run --rm app vendor/bin/phpunit
bash tests/Consumer/framework-package-export.sh
bash -n tests/Consumer/version-baseline.sh
bash tests/Consumer/version-baseline.sh
pnpm --dir docs/website test
pnpm --dir docs/website run check
pnpm --dir docs/website run build
pnpm --dir docs/website run site:check
! rg -n 'skip_violations|ignore_uncovered' deptrac.yaml
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

`version-baseline.sh`はPOSIX shell/awkでrulesetの推移閉包を計算し、非自明なSCC集合がD142の2集合と完全一致することも検証する。

## Expected Report

`develop/orchestration/reports/P22-003B-release-architecture-and-export-closure.md`へSummary、Changed Files、Decisions and Assumptions、Dependency Inventory、Bounded Layer Evidence、Package Export Evidence、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
