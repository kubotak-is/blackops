# P18-009C: Public UUIDv7 Generator and Consumer Adoption

Status: Accepted

## Goal

Public `BlackOps\Identifier\Uuidv7Generator`とFramework Default Service Bindingを追加し、Application InfrastructureがUUIDv7 AlgorithmだけをConstructor Injectionできるようにする。Auth GeneratorとCommunity Board Identifier AdapterからSymfony UID直接Importを削除し、Domain固有Identifier Contractを維持する。

## In Scope

- Public `Uuidv7Generator::generate(): string`
- Canonical lowercase RFC 4122 UUIDv7 Default Service
- Application Compiled Container Default Bindingと明示Override
- Invalid Default／Override ResultのSafe Failure
- Internal Framework Identifier Factory／Clock／型付きID互換
- `make:auth` Identifier StubのPublic Generator Injection
- Community Board Identity／Board Identifier Infrastructure Adapter移行
- Domain層のBlackOps／Symfony／Doctrine非依存維持
- Generator Fresh／Force／Framework Update回帰
- Existing Volume／Clean Install／Identity／Board Consumer回帰
- Public API、Architecture、Unit／Integration／Consumer Test
- Specification、Report、STATE同期

## Out of Scope

- Environment／SAPI Runtime Contract変更
- Generic Identifier Framework、Entity ID、Repository、Persistence、Prefix
- Symfony UID Composer Dependency削除
- Skeleton／Package Export／Website／Documentation Closeout
- DBAL／Migrations Wrapper、Phase 19 Idempotency／Outbox
- External Publication／Deploy

## Relevant Specifications

- `develop/decisions/026-identifier-value-objects.md`
- `develop/decisions/106-community-board-domain-layering.md`
- `develop/decisions/111-session-auth-package-contract.md`
- `develop/decisions/114-application-runtime-and-bootstrap-dependency-boundary.md`
- `develop/spec/17-core-api.md`
- `develop/spec/20-identifier-value-objects.md`
- `develop/spec/71-full-stack-reference-application.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/78-application-runtime-and-bootstrap.md`
- `develop/spec/79-phase-18-runtime-follow-up-delivery-plan.md`
- `develop/orchestration/reports/P18-009B-framework-owned-sapi-runtime.md`

## Files Allowed to Change

- `src/Identifier/Uuidv7Generator.php`
- Default Service／Container Binding／Validationに必要な`src/Internal/Identifier/**`、`src/Internal/Application/**`、`src/Internal/DependencyInjection/**`の最小差分
- `resources/stubs/auth-random-identity-identifier.php.stub`
- Auth Generator Fixture／Expected Tree／Testの最小差分
- `examples/community-board/app/Infrastructure/**`のIdentifier Adapterと対応Test
- 対応する`tests/**`、Public API Inventory、Architecture設定
- `develop/spec/17-core-api.md`、`develop/spec/20-identifier-value-objects.md`、`develop/spec/71-full-stack-reference-application.md`、`develop/spec/74-application-ergonomics.md`、`develop/spec/78-application-runtime-and-bootstrap.md`、`develop/spec/79-phase-18-runtime-follow-up-delivery-plan.md`
- `develop/TODO.md`、`develop/STATE.md`、`develop/orchestration/reports/P18-009C-public-uuidv7-generator-and-consumer-adoption.md`

Environment／SAPI Production Code、Composer Dependency削除、Skeleton Distribution、`docs/guide/**`、`docs/website/**`は変更禁止とする。許可外変更が必要な場合は実装を広げずReportへ記録する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- WorkerはCommitしない
- Domain層へBlackOps、Symfony、Doctrine依存を追加しない
- Public SignatureへSymfony UID型、Clock、Entity／Repository型を露出しない
- Internal Framework Identifierの時刻注入／決定的Test Contractを破壊しない
- UUID値やIdentity DetailをSafe Failureへ出さない

## Acceptance Criteria

- [ ] Public APIが`Uuidv7Generator` Interfaceだけを追加する
- [ ] Default ServiceがCanonical lowercase UUIDv7を生成し、Containerから注入できる
- [ ] Application明示Overrideと決定的Test Generatorが動く
- [ ] Invalid Generator ResultがApplication Dataへ渡らずSafe Failureになる
- [ ] Internal Operation／Attempt／Journal等のIdentifier Factoryが回帰しない
- [ ] Auth GeneratorとCommunity Board SourceがSymfony UIDを直接Importしない
- [ ] Community Board DomainがVendor／BlackOps非依存を維持する
- [ ] Existing Volume／Clean Install／Auth／Board Journeyが成功する
- [ ] Full PHPUnit、Mago、Deptrac、Composer Strict、Public API／Management ID／diff Guardが成功する
- [ ] Dependency削除／Distribution／外部Publication差分なし、Worker Commitなし

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests examples/community-board/app examples/community-board/tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict --working-dir=examples/community-board
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples/community-board/app examples/community-board/tests --glob '*.php'
git diff --check
```

Auth Generator Fresh／Force、Community Board Identity／Board／Clean Consumer CommandはRepository内Scriptと直前Reportから列挙し、実行結果をReportへ記録する。

## Expected Report

`develop/orchestration/reports/P18-009C-public-uuidv7-generator-and-consumer-adoption.md`へAGENTS.mdの必須Sectionに加え、次を記録する。

- Final Public API、Default Binding、Override Boundary
- UUIDv7 Canonical／Invalid／Uniqueness／Deterministic Test Evidence
- Auth Generator Fresh／Force／Framework Update Evidence
- Community Board Domain／Infrastructure Dependency Evidence
- Existing／Clean Identity and Board Consumer Results
- Commandsと実結果、未実行理由、Remaining Issue
