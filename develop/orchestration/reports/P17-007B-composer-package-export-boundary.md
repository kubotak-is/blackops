# P17-007B Composer Package Export Boundary Report

## Summary

Root `.gitattributes`の`export-ignore`をSource Archiveの正本として追加し、Composer `archive.exclude`を同じRoot相対Path集合へ同期した。Git ArchiveとComposer Archiveの両方を一時Directoryで生成するConsumer Testを追加し、Required Runtime Asset、Excluded Development Asset、Root allowlist、Composer Metadata、Production AutoloadをCIで検証する。

## Before／After Archive Inventory

実装前の`git archive HEAD`は1,764 filesで、`.codex/`、`.github/`、`develop/`、`docs/`、`examples/`、`tests/`、Container／Local Tooling、`composer.lock`、`runtime/`を含んでいた。

`git archive --worktree-attributes HEAD`による実装後Archiveは575 filesである。Root inventoryは次に限定した。

```text
CHANGELOG.md
LICENSE
README.md
UPGRADE.md
composer.json
migrations/
resources/
src/
```

Composer Archiveも同じRoot inventoryを満たす。初回TestでComposer ArchiveがGit追跡外の`.agents/`、`.deptrac.cache`、`.env`、`.phpunit.cache`、`vendor/`を含むことを検出したため、これらも両方の除外集合へ追加した。

## Export Boundary and Required Runtime Assets

- `src/Internal/Migration/DoctrineMigrationDependencyFactory.php`はPackage Rootの`migrations/postgresql/`を参照するため、Framework Migration 2 filesを配布する。
- `src/Internal/Application/ApplicationConsoleCommandFactory.php`はPackage Rootの`resources/stubs/`を参照するため、Operation 3 stubsとMigration 1 stubを配布する。
- `runtime/`はRepository health-check applicationとFrankenPHP開発Runtimeであり、Framework library Sourceから参照されていないため除外する。
- `.gitattributes`自身は配布Contractの実行後には不要なためArchiveから除外する。
- Composer ArchiveはGit追跡外Fileも対象にできるため、Repository-local cache、`.env`、`vendor/`も明示除外する。

## Changed Files

- New `.gitattributes`
- `composer.json`
- New `tests/Consumer/framework-package-export.sh`
- `.github/workflows/ci.yml`
- `docs/internal/installed-application-status.md`
- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P17-007B-composer-package-export-boundary.md`

Framework Production Code、Quickstart、Community Boardは変更していない。ユーザー所有の未Commit差分`develop/decisions/108-ray-aop-upstream-and-phase-order.md`は変更、stage、revertしていない。

## Decisions and Assumptions

- `.gitattributes`とComposer `archive.exclude`は同じ除外集合を持ち、Shell Testがsorted path集合を比較する。
- Git側は`git archive --worktree-attributes HEAD`を使用し、Committed TreeをSourceにしながら未Commitの`.gitattributes`を検証する。
- 狭すぎる`src/`単独Allowlistにはせず、Package RootでRuntime参照されるMigration／Generator StubをRequired Pathとして固定する。
- Testは一時Directoryと一時Composer Homeだけへ書き込み、Main Working Tree、Repository Index、共有Composer Cacheを変更しない。各Container Commandは120秒で有限終了する。
- Full PHPUnit／DeptracはProduction PHP SourceとDependency集合に差分がないため省略した。Shell Contract、Composer、Mago、Scope GuardをTask指定どおり実行した。

## Commands and Results

- `bash tests/Consumer/framework-package-export.sh`: PASS。Git／Composer両Archive、除外集合同期、Required／Excluded、strict metadata、production autoload、cleanupを検証。
- `docker compose run --rm app composer validate --strict`: PASS。`./composer.json is valid`。
- `docker compose run --rm app composer install --no-interaction --prefer-dist --no-progress`: PASS。Locked dependency変更なし、autoload生成成功。
- `docker compose run --rm app mago format --check src tests`: PASS。全File formatted。
- Management ID Guard: PASS。`src tests`のPHP Comment／DocBlockに管理IDなし。
- `git diff --check`: PASS。
- `git diff --exit-code -- src examples/quickstart examples/community-board`: PASS。
- Archive inventory count: before 1,764 files、after 575 files。

Sandbox内の初回Shell TestはDocker Socket権限で実行不能だったため、承認済みDocker実行へ切り替えた。実装初稿のTestはComposer ArchiveへGit追跡外開発資産が入ることを正しく検出し、除外集合を補完した後にPASSした。

## Acceptance Criteria

- [x] Root `.gitattributes`へGroup Comment付き`export-ignore`を追加した
- [x] Required File／DirectoryをGit／Composer両Archiveへ保持した
- [x] Repository開発資産、Local Tooling、`composer.lock`、`runtime/`を両Archiveから除外した
- [x] Framework Migration 2 filesとOperation／Migration Generator Stub 4 filesを固定した
- [x] Export済みPackageのComposer strict validationとProduction Autoload生成が成功した
- [x] `.gitattributes`とComposer `archive.exclude`の同一集合を機械検証した
- [x] GitHub Actions Quality JobへPackage Export Gateを追加した
- [x] Quickstart、Community Board、Framework Production Codeを変更していない
- [x] Report、STATE、TODOを実装と同期した
- [x] WorkerはCommitしていない

## Remaining Issues

Package Export Boundaryに残課題はない。Git Tag、GitHub Release、Packagist、Skeleton DistributionはTask Scope外のため変更していない。D108のAOP方針とPhase順序は本Taskで確定していない。

## Suggested Next Action

Orchestrator Codexが差分とPackage Export ContractをReviewし、AcceptedならCommitする。その後、D108 Question 1の回答に基づいてPhase順序を確定する。

## Orchestrator Review

2026-07-21T23:50:50+09:00にAcceptedとした。`.gitattributes`とComposer除外集合、Package Root allowlist、Runtimeから参照されるMigration／Generator Stub、CI接続、ScopeをReviewした。Orchestratorが`bash tests/Consumer/framework-package-export.sh`を独立再実行し、Git／Composer両Archiveの生成、strict validation、Production Autoload、Cleanupが再成功した。
