# P3-003: Database Access and Migration Library

Status: Accepted

## Goal

Deferred受付Orchestrator実装前に、Framework-owned PostgreSQL AdapterのDatabase接続とMigration管理に使うLibraryを確定し、依存関係とArchitecture Guardを更新する。

## In Scope

- Database Access / Migration LibraryのDecisionを追加する
- Doctrine DBALをRuntime依存へ追加する
- Doctrine MigrationsをRuntime依存へ追加する
- Symfony Stopwatchを7.4 LTS系列へ固定する
- Runtime Dependencies Documentationを更新する
- magoのvendor type resolution設定を更新する
- deptracのLibrary layerとTransport依存規則を更新する
- Task Report、STATEを更新する

## Out of Scope

- 既存PostgreSQL AdapterのDBAL移行
- Migration Command実装
- Doctrine ORM導入
- Symfony Bundle統合
- Deferred受付Orchestrator実装

## Relevant Specifications

- `develop/spec/09-runtime-and-di.md`
- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/34-mvp-database-transport.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/40-mvp-delivery-plan.md`
- `develop/decisions/040-mvp-database-transport.md`
- `develop/decisions/041-postgresql-transport-schema.md`
- `develop/decisions/042-postgresql-transaction-boundaries.md`
- `develop/decisions/057-database-access-and-migration-library.md`

## Files Allowed to Change

- `composer.json`
- `composer.lock`
- `mago.toml`
- `deptrac.yaml`
- `develop/spec/README.md`
- `docs/internal/runtime-dependencies.md`
- `develop/decisions/057-database-access-and-migration-library.md`
- `develop/orchestration/tasks/P3-003-database-access-and-migration-library.md`
- `develop/orchestration/reports/P3-003-database-access-and-migration-library.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Doctrine ORMを追加しない
- Symfony full-stack、DoctrineBundle、DoctrineMigrationsBundleを必須依存にしない
- Symfony ComponentのRoot Constraintは7.4 LTS系列に揃える

## Acceptance Criteria

- [x] Database Access / Migration LibraryのDecisionが追加される
- [x] Doctrine DBALがRuntime依存へ追加される
- [x] Doctrine MigrationsがRuntime依存へ追加される
- [x] Symfony Stopwatchが7.4 LTS系列へ固定される
- [x] Runtime Dependencies Documentationが更新される
- [x] magoのvendor type resolution設定が更新される
- [x] deptracのLibrary layerとTransport依存規則が更新される
- [x] Formatterを含む必須品質Commandが成功する

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer audit
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
```

## Expected Report

`develop/orchestration/reports/P3-003-database-access-and-migration-library.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
