# P18-009D: Runtime Distribution, Dependency Audit, and Closeout

Status: Ready

## Goal

Environment Bootstrap、Framework-owned SAPI Runtime、Public UUIDv7 GeneratorをSkeleton／Distribution／Documentationへ同期する。Quickstart／Community Boardから未使用のDotenv、Nyholm、Laminas、Symfony UID Direct Dependencyを削除し、DBAL／Migrations維持を実Importで確認する。Full Consumer Gate後にRuntime Follow-upを閉じ、Phase 19へ進む。

## In Scope

- Official Skeleton Bootstrap、Classic／Worker Entrypoint
- Create-project、Skeleton Publication Dry-run、Framework Update、Package Export
- Quickstart／Community BoardのVendor Runtime Direct Import不在確認
- Dotenv、Nyholm PSR-7／Server、Laminas Handler Runner、Symfony UID Direct Dependency削除
- DBAL／Migrations Direct ImportとDirect Dependency対応確認
- Composer Lock／Install／Strict Validation
- Quickstart／Community Board Existing／Clean／HTTP／Worker／Auth／Board／Deferred／Browser回帰
- README、CHANGELOG、UPGRADE、Guide、Internal Docs、Website、Security／Troubleshooting同期
- TODO、Delivery Plan、STATE、Report Closeout
- Full PHP／Frontend／Consumer／Website Quality Gate

## Out of Scope

- Environment／SAPI／UUID Public Contractの再設計
- DBAL Query Builder、Repository Base Class、ORM、Migration DSL
- Phase 19 Idempotency／Outbox Production Code
- Documentation Website／Community Boardの外部Publication／Deploy
- GitHub Release、Packagist Publication、Cloudflare変更

## Relevant Specifications

- `develop/decisions/110-application-ergonomics.md`
- `develop/decisions/114-application-runtime-and-bootstrap-dependency-boundary.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/51-local-runtime-and-consumer-e2e.md`
- `develop/spec/71-full-stack-reference-application.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/78-application-runtime-and-bootstrap.md`
- `develop/spec/79-phase-18-runtime-follow-up-delivery-plan.md`
- `develop/orchestration/reports/P18-009A-environment-file-bootstrap.md`
- `develop/orchestration/reports/P18-009B-framework-owned-sapi-runtime.md`
- `develop/orchestration/reports/P18-009C-public-uuidv7-generator-and-consumer-adoption.md`

## Files Allowed to Change

- `examples/quickstart/**`
- `examples/community-board/**`
- Skeleton／Create-project／Publication／Framework Update／Package Exportに必要な`resources/**`、`scripts/**`、`.github/**`、`tests/Consumer/**`、`tests/Architecture/**`の最小差分
- Application／Distribution Composer MetadataとLock File
- `README.md`、`CHANGELOG.md`、`UPGRADE.md`
- `docs/guide/**`、`docs/internal/**`、`docs/website/**`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`、`develop/spec/44-public-application-bootstrap-api.md`、`develop/spec/46-composer-skeleton-publication.md`、`develop/spec/49-feature-first-quickstart-application.md`、`develop/spec/51-local-runtime-and-consumer-e2e.md`、`develop/spec/71-full-stack-reference-application.md`、`develop/spec/74-application-ergonomics.md`、`develop/spec/78-application-runtime-and-bootstrap.md`、`develop/spec/79-phase-18-runtime-follow-up-delivery-plan.md`
- `develop/TODO.md`、`develop/DOCS.md`、`develop/STATE.md`、`develop/orchestration/reports/P18-009D-runtime-distribution-dependency-closeout.md`

Framework Production `src/**`とPublic Contractは変更禁止とする。不足が見つかった場合は実装を広げずReportのBlockerとして返す。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- WorkerはCommitしない
- Direct DependencyはSource／Toolingの実ImportとConsumer Install成功を確認してから削除する
- DBAL／MigrationsをDependency削減だけを理由に削除またはWrapper化しない
- Secret、Environment値、Credential、Request BodyをDocumentation Example／Log／Reportへ残さない
- User所有の起動中Runtimeを停止する必要があれば状態をReportへ記録する
- External Publication／Deployを行わない

## Acceptance Criteria

- [ ] Skeleton Bootstrap／Classic／Worker EntrypointがVendor Runtimeを直接Importしない
- [ ] Create-project／Publication Dry-run／Framework Update／Package Exportが成功する
- [ ] Quickstart／Community Boardから未使用のDotenv／Nyholm／Laminas／Symfony UID Direct Dependencyを削除できる
- [ ] DBAL／Migrations Direct DependencyがApplication実Importと一致する
- [ ] Quickstart／Community Board Existing／Clean／Classic／Worker／Auth／Board／Deferred／Browserが成功する
- [ ] Guide／Internal Docs／WebsiteがFinal Public Contractと一致する
- [ ] Full PHP／Frontend／Consumer／Website Quality Gateが成功する
- [ ] TODO／Delivery Plan／STATEがRuntime Follow-up完了とPhase 19 Next Actionを示す
- [ ] External Publication／Deployなし、Framework Production差分なし、Worker Commitなし

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests examples/quickstart/app examples/community-board/app examples/community-board/tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict --working-dir=examples/quickstart
docker compose run --rm app composer validate --strict --working-dir=examples/community-board
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples/quickstart examples/community-board --glob '*.php'
git diff --check
```

Consumer／Frontend／Browser／Website／Skeleton／Export CommandはRepository内Scriptと直前Reportから列挙し、実行結果をReportへ残す。

## Expected Report

`develop/orchestration/reports/P18-009D-runtime-distribution-dependency-closeout.md`へAGENTS.mdの必須Sectionに加え、次を記録する。

- Final Skeleton／Quickstart／Community Board Bootstrap and Runtime Shape
- Removed Direct Import／Dependency MatrixとDBAL／Migrations維持Evidence
- Existing／Clean／Framework Update／Publication／Export Evidence
- Full Consumer／Frontend／Browser／Website Gate
- Phase 19 HandoffとRemaining Issue
- Commandsと実結果、未実行理由
