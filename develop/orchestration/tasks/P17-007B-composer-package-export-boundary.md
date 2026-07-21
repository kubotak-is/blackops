# P17-007B: Composer Package Export Boundary

Status: Accepted

## Goal

`blackops/framework`のSource ArchiveとComposer Packageから、Framework利用時に不要なRepository開発資産を除外する。`.gitattributes`の`export-ignore`を正本にし、Composer Archive設定と自動Testを同期して、必要なRuntime／Generator／Migration資産だけを配布する。

## Context

Root RepositoryにはFramework Runtimeに加えて、Orchestration、Decision、Test、Example、CI、Container、Documentation Website等が同居している。現在 `.gitattributes` がなく、`git archive HEAD`にはRepositoryの全追跡Fileが含まれる。

利用者がDependencyとしてInstallするPackageには、少なくとも次が必要である。

- `composer.json`
- `src/`
- `migrations/`
- `resources/stubs/`
- `LICENSE`
- `README.md`
- `CHANGELOG.md`
- `UPGRADE.md`

FrameworkのMigration LoaderとProject Generatorは、Package Rootの`migrations/`と`resources/stubs/`をRuntimeで参照するため除外してはならない。

## Source of Truth

- `composer.json`
- `develop/spec/52-phase-8-delivery-plan.md`
- `develop/spec/61-experimental-release-contract.md`
- `docs/internal/installed-application-status.md`
- `docs/internal/project-generators.md`
- `docs/internal/database-migrations.md`

## In Scope

- 現在の`git archive`／Composer Archive内容の監査
- Root `.gitattributes`の追加
- Repository開発資産への`export-ignore`
- `composer.json`の`archive.exclude`同期が必要かの判断と実装
- Export済みPackageのAllowlist／Denylist Test
- Export済みPackage内でのComposer Metadata、Production Autoload、Migration／Stub存在確認
- GitHub Actions CIへの軽量なPackage Export Gate追加
- Internal Documentation、Report、STATE、TODOの必要最小限同期

## Out of Scope

- Git Tag、GitHub Release、Packagist更新
- 公開済みVersionのArchive差し替え
- Skeleton Distribution Repositoryの変更
- Quickstart／Community Board／Documentation Websiteの内容変更
- Runtime DependencyまたはPublic API変更
- D108のAOP方針確定

## Export Contract

### Required

- `.gitattributes`はArchive自身から除外してよい
- `composer.json`
- `src/**`
- `migrations/**`
- `resources/stubs/**`
- `LICENSE`
- `README.md`
- `CHANGELOG.md`
- `UPGRADE.md`

### Excluded Development Assets

- `.codex/**`
- `.github/**`
- `develop/**`
- `docs/**`
- `examples/**`
- `tests/**`
- Container／Local Tooling files: `.dockerignore`、`.env.example`、`AGENTS.md`、`Dockerfile`、`Dockerfile.frankenphp`、`compose.yaml`、`deptrac.yaml`、`mago.toml`、`mise.toml`、`phpunit.xml`
- Root development lockfile `composer.lock`
- Repository-only `.gitignore`

`runtime/`はRepositoryのhealth-check applicationとFrankenPHP開発Runtimeであり、Framework library Runtimeから参照されていないことを確認できた場合だけ除外する。推測で除外しない。

## Files Allowed to Change

- New `.gitattributes`
- `composer.json`
- New `tests/Consumer/framework-package-export.sh`
- `.github/workflows/ci.yml`
- `docs/internal/installed-application-status.md`
- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P17-007B-composer-package-export-boundary.md`

上記以外が必要なら実装を広げずReportへBlockerとして返す。

## Implementation Constraints

- `.gitattributes`へ除外理由を表すGroup Commentを置く
- Source CheckoutとExported Packageの責務を混同しない
- `src/`だけを残す狭すぎるAllowlistにせず、RuntimeでFile Path参照されるMigration／Stubを固定する
- Archive Testは現在のCommit Treeに加え、未Commitの`.gitattributes`変更を検証できる方法を選ぶ
- TestはTemporary Directoryだけを使用し、Repository、Docker、Composer Cacheを変更しない
- NetworkやPackagist Publicationを成功条件にしない
- `composer archive`の設定を追加する場合、`.gitattributes`と同じ除外集合を機械検証する
- Shell Testは`set -euo pipefail`、明示Cleanup、有限実行、予期しないFileの診断を持つ

## Acceptance Criteria

- [ ] `.gitattributes`がRootにあり、Repository開発資産へ`export-ignore`を設定する
- [ ] Export済みPackageにRequired File／Directoryが全て存在する
- [ ] Export済みPackageにExcluded Development Assetsが存在しない
- [ ] Framework MigrationとOperation／Migration Generator Stubを除外しない
- [ ] Export済みPackageの`composer validate --strict`とProduction Autoload生成が成功する
- [ ] Composer Archive設定を追加した場合、Git Archiveと同じRequired／Excluded Contractを満たす
- [ ] CIがPackage Export Contractを検証する
- [ ] Quickstart／Community Board／Framework Production Codeを変更しない
- [ ] Report／STATE／TODOが実装と一致する
- [ ] WorkerはCommitしない

## Required Commands

```bash
bash tests/Consumer/framework-package-export.sh
docker compose run --rm app composer validate --strict
docker compose run --rm app composer install --no-interaction --prefer-dist --no-progress
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
git diff --exit-code -- src examples/quickstart examples/community-board
```

Full PHPUnit／DeptracはProduction PHP SourceとDependencyに差分がない場合は省略できる。省略理由をReportへ記録する。

## Completion Report

`develop/orchestration/reports/P17-007B-composer-package-export-boundary.md`へ少なくとも次を記載する。

- Summary
- Before／After Archive Inventory
- Export Boundary and Required Runtime Assets
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
