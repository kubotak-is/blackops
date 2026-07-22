# P18-008C: Seeder Consumer Adoption and Follow-up Closeout

Status: Planned

## Goal

Quickstart／SkeletonとCommunity BoardをFramework Database Seederへ移行する。Community BoardからSeeder用Symfony Commandと不要なDirect Dependencyを削除し、Existing Volume／Clean Install／Framework Update／Websiteを回帰してPhase 18 Follow-upを閉じる。

## In Scope

- Quickstart／Skeletonの標準`app/Infrastructure/Seed/DatabaseSeeder.php`
- Install直後の`database:migrate`／`build:compile`／`database:seed` Journey
- Community Board Root Seederと`SeederRunner` Composition
- Existing `CommunityBoardSeeder`のApplication-owned Transaction／Deterministic／Idempotent Contract維持
- `CommunityBoardSeedCommand`、`app:seed`、明示Seeder Service登録の削除
- Symfony Console直接Import不在確認と`composer.json` Direct Dependency削除
- Seed Unit／Console／Existing Volume／Clean Install／HTTP／Deferred／Browser回帰
- README、Guide、Internal Docs、Website、TODO、STATE、Report同期
- Package Export、Skeleton Publication Dry-run、Framework Update、Full Quality Gate
- 残Composer Dependency Auditと別Decision候補のReport

## Out of Scope

- Seeder Public API／Build／Command／Generator Contract変更
- Community Boardの新機能またはUI再設計
- Seeder以外のDotenv、PSR-7 Runtime、UUID、DBAL／Migrations Dependency削除
- DBAL Wrapper、ORM、Factory／Faker、Seed History
- Phase 19 Idempotency／Outbox
- Documentation Website／Community Boardの外部Publication／Deploy

## Relevant Specifications

- `develop/decisions/103-full-stack-reference-application.md`
- `develop/decisions/106-community-board-domain-layering.md`
- `develop/decisions/110-application-ergonomics.md`
- `develop/decisions/113-database-seeder-contract.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/51-local-runtime-and-consumer-e2e.md`
- `develop/spec/71-full-stack-reference-application.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/76-database-seeding.md`
- `develop/spec/77-phase-18-follow-up-delivery-plan.md`
- `develop/orchestration/reports/P18-008A-seeder-core-and-build-discovery.md`
- `develop/orchestration/reports/P18-008B-seeder-console-and-generator.md`

## Files Allowed to Change

- `examples/quickstart/**`
- `examples/community-board/**`
- Quickstart／Skeleton／Community Board／Framework Update／Package Exportに必要な`tests/Consumer/**`、`tests/Architecture/**`の最小差分
- `docs/guide/**`、`docs/internal/**`、`docs/website/**`
- `README.md`、`CHANGELOG.md`、`UPGRADE.md`のFollow-up同期に必要な最小差分
- `develop/spec/43-installed-application-layout-and-bootstrap.md`、`develop/spec/48-public-console-kernel-composition.md`、`develop/spec/49-feature-first-quickstart-application.md`、`develop/spec/51-local-runtime-and-consumer-e2e.md`、`develop/spec/55-project-generators-and-application-migrations.md`、`develop/spec/71-full-stack-reference-application.md`、`develop/spec/74-application-ergonomics.md`、`develop/spec/75-phase-18-delivery-plan.md`、`develop/spec/76-database-seeding.md`、`develop/spec/77-phase-18-follow-up-delivery-plan.md`
- `develop/TODO.md`、`develop/DOCS.md`、`develop/STATE.md`、`develop/orchestration/reports/P18-008C-seeder-consumer-adoption-and-closeout.md`

Framework Production `src/**`、`resources/stubs/**`は変更禁止とする。Public Contract不足が見つかった場合は実装を広げずReportのBlockerとして返す。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- WorkerはCommitしない
- Community Board DomainへBlackOps、Doctrine、Symfony依存を追加しない
- Seed Data、Password、Token、SQL DetailをCommand Output、Log、Reportへ残さない
- Direct DependencyはApplication Source／Toolingの実Importを確認してから削除する
- User所有の起動中Runtimeを停止する必要があれば状態をReportへ記録する

## Acceptance Criteria

- [ ] Quickstart／Skeletonが標準DatabaseSeederを持ち、Install直後のSeed Journeyが成功する
- [ ] Community Boardが`database:seed`から既存Fixtureを投入できる
- [ ] Community BoardのTransaction／Deterministic／Idempotent／Domain Service再利用が維持される
- [ ] Seeder用Symfony Command、`app:seed`、明示Seeder Service登録がなくなる
- [ ] Community Board SourceがSymfony Consoleを直接Importせず、Direct Dependencyを削除できる
- [ ] Existing Volume／Clean Install／HTTP／Deferred／Browser Journeyが成功する
- [ ] Quickstart／Skeleton／Framework Update／Package Export／Websiteが成功する
- [ ] 残Composer Dependencyの所有境界と次Decision候補がReportされる
- [ ] Full PHP／Frontend／Consumer／Website Quality Gateが成功する
- [ ] External Publication／Deployなし、Worker Commitなし

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests examples/quickstart/app examples/community-board/app examples/community-board/tests
docker compose run --rm app mago lint
docker compose run --rm app mago lint examples/quickstart/app examples/community-board/app
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict --working-dir=examples/quickstart
docker compose run --rm app composer validate --strict --working-dir=examples/community-board
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples/quickstart/app examples/community-board/app examples/community-board/tests --glob '*.php'
git diff --check
```

Task Packet記載以外のConsumer／Frontend／Website CommandはRepository内Scriptと直前Reportから列挙し、実行結果をReportへ残す。

## Expected Report

`develop/orchestration/reports/P18-008C-seeder-consumer-adoption-and-closeout.md`へAGENTS.mdの必須Sectionに加え、次を記録する。

- Final Quickstart／Skeleton／Community Board Seeder Architecture
- Existing Volume／Clean Install／Seed Repeatability Evidence
- Removed Symfony Console Source／Dependency Evidence
- Full Consumer／Frontend／Website／Package Export Gate
- Remaining Composer Dependency Auditと次Decision候補
- Commandsと実結果、未実行理由、Remaining Issue
