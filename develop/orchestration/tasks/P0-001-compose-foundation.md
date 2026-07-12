# P0-001: Docker Compose Foundation

Status: Accepted

## Goal

Docker ComposeだけでPHP 8.5、Composer、Mago、PHPUnit、Deptrac、PostgreSQLを実行できるPhase 0の開発基盤を作る。

## In Scope

- PHP 8.5 Application Image
- ApplicationとPostgreSQLのCompose Service
- `blackops/framework` の最小Composer Project
- Mago、PHPUnit、Deptracの導入と最小設定
- Source／Test Directoryの最小構成
- Development Setup文書の実行可能なCommandへの更新
- Container、Composer、品質Tool、Database接続のSmoke Test

## Out of Scope

- Operation、Handler、Dispatcher等のProduction Code
- `GET /welcome`
- Canonical Journal Tableの本実装
- CI Workflow
- D047で未確定のFrontend／HTTP Response Contract
- HostへのPHP、Composer、PostgreSQL、品質Toolの導入

## Relevant Specifications

- `develop/spec/00-framework-identity.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/12-mvp-scope.md`
- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/14-package-architecture.md`
- `develop/spec/15-source-layout.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/34-mvp-database-transport.md`
- `develop/spec/40-mvp-delivery-plan.md`
- `develop/decisions/048-implementation-orchestration.md`

## Files Allowed to Change

- `compose.yaml`
- `Dockerfile`
- `.dockerignore`
- `.gitignore`
- `.env.example`
- `composer.json`
- `composer.lock`
- `mago.toml`
- `deptrac.yaml`
- `phpunit.xml`
- `src/`
- `tests/`
- `docker/`
- `docs/internals/development-setup.md`
- `develop/orchestration/reports/P0-001-compose-foundation.md`
- `develop/STATE.md`
- `develop/TODO.md`

許可されていないFileの変更が必要な場合は実装を広げず、Reportへ記載する。

## Constraints

- HostへPHPまたはComposerを要求しない
- PHP Runtimeは8.5以上とする
- PostgreSQLはCompose Serviceとして起動する
- Application ContainerはRepository RootをWorking Directoryとする
- Composer Package名は `blackops/framework` とする
- Production Namespaceは `BlackOps\`、Test Namespaceは `BlackOps\Tests\` とする
- Dependency VersionはTask実行時点でPHP 8.5対応を確認して固定する
- Credentialや実値をCommit対象Fileへ保存しない

## Acceptance Criteria

- [ ] `docker compose config` が成功する
- [ ] Application ImageをBuildできる
- [ ] Compose経由でPHP 8.5以上を確認できる
- [ ] Compose経由でComposerを実行できる
- [ ] PostgreSQL ServiceがHealthyになる
- [ ] Application ContainerからPostgreSQLへ接続できる
- [ ] `composer validate --strict` が成功する
- [ ] Containerが生成するRepository内FileをHost Userが編集できる
- [ ] MagoのLintとStatic Analysisが成功する
- [ ] PHPUnitの最小Testが成功する
- [ ] DeptracのArchitecture検査が成功する
- [ ] HostへのPHP／Composer導入なしで全Commandを再実行できる
- [ ] 実行Commandが内部向けSetup文書へ記載される

## Required Commands

実装に合わせてService内Commandの細部は調整できるが、次の検証をCompose経由で行う。

```bash
docker compose config
docker compose build app
docker compose up -d postgres
docker compose ps
docker compose run --rm app php --version
docker compose run --rm app composer --version
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
docker compose down
```

Database接続Smoke TestのCommandも追加し、Reportへ結果を記録する。

## Expected Report

`develop/orchestration/reports/P0-001-compose-foundation.md` に次を記録する。

- Summary
- Changed Files
- Dependency Versions
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
