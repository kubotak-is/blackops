# P18-008A: Seeder Core and Build Discovery

Status: Accepted

## Goal

Public `BlackOps\Database\Seeder`／`SeederRunner` Contract、標準／明示Configuration、Build-time Seeder Discovery、Compiled Container Locatorを実装する。Root SeederとChild SeederがConstructor DIを使え、実行順、Nested Composition、循環、失敗停止をRuntime Scanなしで保証する。

## In Scope

- Public `Seeder::run(): void`／`SeederRunner::run(string ...$seeders): void`
- `app/Infrastructure/Seed`と`App\Infrastructure\Seed\DatabaseSeeder`の標準Convention
- `config/database.php`の`seeding.root`／`seeding.discovery` Override
- Missing DefaultとInvalid Explicit Configurationの分離
- Build-time Seeder Discovery／Validation
- Compiled Container Private Seeder Service／LocatorとRoot解決
- Ordered／Nested／Empty Runner、Unknown Class、Cycle、Child Exception Stop
- Public API Inventory、Architecture／Unit／Integration Test
- Specification、Report、STATE同期

## Out of Scope

- `database:seed`／`make:seeder` Console Command
- Generator Stub
- Quickstart／Skeleton／Community Board Source
- Symfony Application Command Discoveryの変更
- Transaction、Truncate、Retry、Seed History、Operation／Journal統合
- Documentation Website／Community Boardの外部Publication／Deploy

## Relevant Specifications

- `develop/decisions/113-database-seeder-contract.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/17-core-api.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/76-database-seeding.md`
- `develop/spec/77-phase-18-follow-up-delivery-plan.md`

## Files Allowed to Change

- `src/Database/Seeder.php`
- `src/Database/SeederRunner.php`
- Seeder Configuration／Discovery／Compiled Container登録に必要な`src/Internal/Application/**`、`src/Internal/Discovery/**`、`src/Internal/DependencyInjection/**`の最小差分
- Seeder Runner／Locator実装に必要な`src/Internal/Database/**`または`src/Internal/Seeder/**`
- 対応する`tests/**`とPermanent Seeder Fixture
- Public API Inventory、Architecture設定
- `develop/spec/09-runtime-and-di.md`、`develop/spec/16-namespace-dependencies.md`、`develop/spec/17-core-api.md`、`develop/spec/44-public-application-bootstrap-api.md`、`develop/spec/50-operation-authoring-and-build-discovery.md`、`develop/spec/74-application-ergonomics.md`、`develop/spec/76-database-seeding.md`、`develop/spec/77-phase-18-follow-up-delivery-plan.md`
- `develop/TODO.md`、`develop/STATE.md`、`develop/orchestration/reports/P18-008A-seeder-core-and-build-discovery.md`

Console／Generator Production Code、`resources/stubs/**`、`examples/**`、`docs/guide/**`、`docs/website/**`は変更禁止とする。許可外変更が必要な場合は実装を広げずReportへ記録する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- WorkerはCommitしない
- Runtime Source Scan、Runtime Reflection、`new $class()`を追加しない
- Standard Root MissingだけではExisting Application Buildを失敗させない
- Explicit Invalid Configurationと検出済みInvalid SeederはSafe Build Failureにする
- SeederへOperation、Journal、Outcome、暗黙Transactionを追加しない

## Acceptance Criteria

- [x] Public APIが`Seeder`と`SeederRunner`の2 Interfaceだけを追加する
- [x] Standard／Explicit RootとDiscoveryが仕様どおり検証される
- [x] Seeder Constructorを実行せずBuild-time Discoveryできる
- [x] 検出済みSeederがCompiled ContainerからConstructor DIで解決される
- [x] RunnerがArgument順、Nested、Empty、Unknown、Cycle、Exception Stopを保証する
- [x] Missing Standard SeederのExisting ApplicationがBuild／HTTP／Consoleで回帰しない
- [x] Runtime Scan／Reflection Fallback／Dynamic Constructionがない
- [x] Full PHPUnit、Mago、Deptrac、Public API／Management ID／diff Guardが成功する
- [x] Console／Generator／Example／外部Publication差分なし、Worker Commitなし

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P18-008A-seeder-core-and-build-discovery.md`へAGENTS.mdの必須Sectionに加え、次を記録する。

- Final Public APIとNamespace／Architecture Boundary
- Default／Explicit Configuration Matrix
- Discovery／Compiled Container／Locator Evidence
- Ordered／Nested／Unknown／Cycle／Failure Matrix
- Runtime Scan／Reflection不在Evidence
- Commandsと実結果、未実行理由、Remaining Issue
