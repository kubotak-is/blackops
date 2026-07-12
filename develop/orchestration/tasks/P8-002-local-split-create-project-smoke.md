# P8-002: Local Split and Create-project Smoke

Status: Ready

## Goal

Committed `examples/quickstart/` Treeだけを一時Skeleton Package Rootへ抽出し、Local Composer Repositoryから通常／`--no-scripts`の `composer create-project blackops/skeleton` を実行して、Phase 8のPackage Source、Version、Post-create、Manual Setup境界を外部公開前に検証する。

## In Scope

- GitのCommitted Quickstart Treeから一時Package Rootを生成
- Split Package RootのComposer Metadata／Executable／Source Tree検証
- Skeleton `1.0.0` とFramework `1.0.0` のLocal Version注入
- Skeleton `^1.0` Framework Constraint整合性検証
- Local Path Repository `symlink=false` の通常create-project
- Post-create `.env`／Directory／Lock／Vendor／Autoload Smoke
- `--no-scripts` create-projectとManual `bin/setup` Smoke
- Split／Source CleanlinessとCleanup
- Guide／Internals／Report／Checkpoint

## Out of Scope

- Git Historyを持つDistribution RepositoryへのPush
- Remote Git Tag作成
- Packagist公開／更新
- Framework／Skeleton Release Version自動書換
- GitHub Actions Publication Workflow
- Production Runtime機能変更

## Relevant Specifications and Decisions

- `develop/decisions/065-composer-skeleton-publication.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/52-phase-8-delivery-plan.md`

## Files Allowed to Change

- `tests/Consumer/skeleton-create-project.sh`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `docs/guide/installed-application-status.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/internals/mvp-e2e.md`
- `examples/quickstart/README.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P8-002-local-split-create-project-smoke.md`
- `develop/orchestration/reports/P8-002-local-split-create-project-smoke.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Package Extraction Contract

- `git archive HEAD:examples/quickstart` でCommitted Treeだけを一時Package Rootへ抽出する
- Working Treeの未Commit File、Root Vendor、Root Dev AutoloadをPackageへ含めない
- Package Root直下に `composer.json`、`bin/setup`、`bin/blackops`、Application Treeが存在する
- Executable bitを保持する
- Package Sourceに `.env`、`composer.lock`、`vendor/`、Path Repository、Build Artifact、JSONL Logを含めない
- Test終了時は通常／no-scripts ProjectとPackage Rootを含む一時Directoryを削除する

## Version and Repository Contract

- Local Smoke VersionはFramework／Skeletonとも `1.0.0`
- Skeletonの `blackops/framework` Constraintが `^1.0` であり、Local Framework Versionを許容することを検証する
- SkeletonとFrameworkを別々のComposer Path Repositoryとして渡す
- 両Repositoryは `symlink=false` とし、生成ProjectのSkeleton SourceとFramework DependencyをCopy Installする
- Checked-in Composer MetadataへLocal RepositoryまたはVersion fieldを追加しない

## Create-project Scenario

### Normal

1. `composer create-project blackops/skeleton <target> 1.0.0` をLocal Repository指定で実行
2. TargetがSkeleton Package RootへのSymlinkでないことを確認
3. `composer.lock` と `vendor/autoload.php` が存在することを確認
4. Framework PackageがSymlinkでなくCopy Installされたことを確認
5. Post-createにより `.env` がExampleと同一で生成されることを確認
6. `var/build`／`var/log` とExecutable Entrypointを確認
7. Composer AutoloadでPublic `Application` とQuickstart Operation Classをloadできることを確認
8. Install中にBuild Artifact、Migration、Docker Resourceを生成していないことを確認

### No Scripts

1. 同じLocal Repositoryから `--no-scripts` create-projectを実行
2. `.env` が存在しないことを確認
3. Source、Lock、Vendor、Autoloadが成立することを確認
4. `php bin/setup` を手動実行
5. `.env` とLocal Directoryが通常Installと同じ状態になることを確認
6. Setup再実行で既存 `.env` が変わらないことを確認

## Acceptance Criteria

- [ ] Committed QuickstartだけからPackage Rootを抽出する
- [ ] Split RootのComposer MetadataとExecutableが正しい
- [ ] Lock、Vendor、Path Repository、Generated StateをPackage Sourceへ含めない
- [ ] Skeleton／Framework `1.0.0` と `^1.0` Constraintが整合する
- [ ] 通常Local create-projectが成功する
- [ ] Post-create Setup、Lock、Vendor、Autoload Smokeが成功する
- [ ] `--no-scripts` create-projectとManual Setupが成功する
- [ ] 両InstallがsymlinkではなくCopyで成立する
- [ ] Install中にBuild／Migration／Docker Side Effectがない
- [ ] Source Tree非変更と一時Resource Cleanupが成立する
- [ ] Composer、Architecture／Full Test、Mago、Deptrac、Guardが成功する
- [ ] Docs、Report、Checkpointが更新される

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit tests/Architecture/QuickstartApplicationArchitectureTest.php
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/skeleton-create-project.sh
! rg -n '"type"[[:space:]]*:[[:space:]]*"path"|"url"[[:space:]]*:[[:space:]]*"/framework"|"repositories"[[:space:]]*:|"version"[[:space:]]*:' examples/quickstart/composer.json
! test -e examples/quickstart/composer.lock
! test -d examples/quickstart/vendor
! rg -n 'BlackOps\\Internal' examples/quickstart --glob '*.php'
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P8-002-local-split-create-project-smoke.md` に次を記録する。

- Summary
- Package Extraction／Cleanliness Evidence
- Version／Repository Evidence
- Normal Create-project Evidence
- No-scripts／Manual Setup Evidence
- Side Effect／Cleanup Evidence
- Changed Files
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
