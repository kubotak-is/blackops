# P18-009A: Environment File Bootstrap

Status: Accepted

## Goal

Public `ApplicationBuilder::withEnvironmentFile()`を追加し、Process Environmentを優先したOptional `.env`のstring-only SnapshotをBootstrap時に一度だけ構成する。既存`withEnvironment(array)`／Process-only／External Loader互換を維持し、Quickstart BootstrapからDotenv Vendor配線を除く。

## In Scope

- Public `ApplicationBuilder::withEnvironmentFile(?string $path = null): self`
- Default `<basePath>/.env`と明示Path
- Process Environment優先、Optional Missing、string-only Snapshot
- Existing／Unreadable／Invalid FileのSafe Bootstrap Failure
- Environment Source置換順とConfiguration評価順の分離
- `create()`単位の一回読込とApplication Instance内再読込不在
- Secret／Raw Value／Parser ThrowableのError、Log、Artifact不在
- 既存`withEnvironment(array)`、引数省略Process-only、External Loader互換
- Quickstart `bootstrap/app.php`のFramework Capability移行とConsumer回帰
- Public API Inventory、Architecture、Unit／Integration／Consumer Test
- Specification、Report、STATE同期

## Out of Scope

- Classic／FrankenPHP Entrypoint、Request Factory、Response Emit、Worker Loop
- Public UUIDv7 Generator、Auth Generator、Community Board Source
- Quickstart／Community Board／SkeletonのComposer Dependency削除
- DBAL／Migrations、Phase 19 Idempotency／Outbox
- Documentation Website／Community Boardの外部Publication／Deploy

## Relevant Specifications

- `develop/decisions/110-application-ergonomics.md`
- `develop/decisions/114-application-runtime-and-bootstrap-dependency-boundary.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/78-application-runtime-and-bootstrap.md`
- `develop/spec/79-phase-18-runtime-follow-up-delivery-plan.md`

## Files Allowed to Change

- `src/Application/ApplicationBuilder.php`
- Environment Fileの解決／読込／Safe Failureに必要な`src/Internal/Application/**`の最小差分
- `composer.json`、`composer.lock`
- 対応する`tests/Application/**`、`tests/Internal/Application/**`、`tests/Architecture/**`、`tests/Consumer/**`とPermanent Fixture
- `examples/quickstart/bootstrap/app.php`とEnvironment Bootstrap回帰に必要なQuickstart Test／Scriptの最小差分
- Public API Inventory、Architecture設定
- `develop/spec/43-installed-application-layout-and-bootstrap.md`、`develop/spec/44-public-application-bootstrap-api.md`、`develop/spec/74-application-ergonomics.md`、`develop/spec/78-application-runtime-and-bootstrap.md`、`develop/spec/79-phase-18-runtime-follow-up-delivery-plan.md`
- `develop/TODO.md`、`develop/STATE.md`、`develop/orchestration/reports/P18-009A-environment-file-bootstrap.md`

HTTP Runtime Production Code、`src/Identifier/**`、`resources/stubs/**`、`examples/community-board/**`、Quickstart Composer Dependency、`docs/guide/**`、`docs/website/**`は変更禁止とする。許可外変更が必要な場合は実装を広げずReportへ記録する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- WorkerはCommitしない
- Builder Methodを呼ばないApplicationの`.env`を暗黙に探索しない
- Process EnvironmentをEnvironment Fileより優先する
- Missing `.env`だけではLocal／Production Bootstrapを失敗させない
- Environment値をConfiguration Snapshot、Compiled Container、Manifest、Generated Source、Logへ保存しない
- Request／Operation／Worker IterationごとにEnvironmentを再読込しない

## Acceptance Criteria

- [x] Public APIへ`withEnvironmentFile(?string $path = null): self`だけを追加する
- [x] Default／Explicit File、Missing、Process Override、Invalid／Unreadable Matrixが仕様どおり動く
- [x] Environment Snapshotがstring-key／string-valueだけを保持する
- [x] `withEnvironment(array)`、Process-only、External Loaderが回帰しない
- [x] Environment SourceのLast Explicit SelectionとConfiguration Call Orderが決定的である
- [x] Secret／Raw Value／Parser DetailがException、Log、Artifactへ出ない
- [x] Quickstart BootstrapがDotenv Vendor Classを直接Importしない
- [x] Quickstart Environment／Configuration／HTTP／Console Consumerが成功する
- [x] Full PHPUnit、Mago、Deptrac、Composer Strict、Public API／Management ID／diff Guardが成功する
- [x] HTTP Runtime／UUID／Community Board／Dependency削除／外部Publication差分なし、Worker Commitなし

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict --working-dir=examples/quickstart
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples/quickstart/bootstrap --glob '*.php'
git diff --check
```

Quickstart Environment／Configuration Consumer CommandはRepository内Scriptと直前Reportから特定し、実行結果をReportへ記録する。

## Expected Report

`develop/orchestration/reports/P18-009A-environment-file-bootstrap.md`へAGENTS.mdの必須Sectionに加え、次を記録する。

- Final Public APIとEnvironment Source Precedence
- Missing／Present／Invalid／Unreadable／Process Override Matrix
- Single SnapshotとSecret-safe Failure Evidence
- Existing `withEnvironment`／Quickstart Consumer Compatibility
- Commandsと実結果、未実行理由、Remaining Issue
