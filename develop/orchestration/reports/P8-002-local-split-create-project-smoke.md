# P8-002 Local Split and Create-project Smoke Report

## Summary

Committed `examples/quickstart/`だけをCleanな一時Skeleton Package Rootへ抽出し、別々のLocal Skeleton／Framework Composer Repositoryから通常と`--no-scripts`のCreate-projectを検証した。両Packageは`symlink=false`でCopy Installされ、Version、Lock、Vendor、Autoload、Post-create／Manual Setup、Side Effect不在、Source Cleanliness、Cleanupが成功した。

Checked-in Quickstart Composer MetadataへLocal RepositoryまたはVersionは追加していない。Remote Distribution Repository、Tag、Packagistは未実装である。

## Package Extraction and Cleanliness Evidence

Testは次のCommandだけでPackage Sourceを抽出する。

```bash
git archive HEAD:examples/quickstart | tar -x -C "$package_root"
```

これによりWorking Treeの未Commit変更、Framework Root Vendor、Root Dev AutoloadをPackageへ含めない。Package RootでComposer Metadata、Application Tree、Bootstrap、Executableな`bin/setup`／`bin/blackops`を確認した。

Package Sourceに`.env`、`composer.lock`、`vendor/`、Path Repository、Version field、Build Artifact、JSONL Logがないことを機械検証した。Split Rootの`composer validate --strict`も成功した。

初回SmokeでCommitted `bin/setup`がmode `100644`であることを検出した。Task許可範囲へmode修正だけを追加し、Orchestratorがmode-only Commit `eaf3d35`を作成した。以後のHEAD Archiveで`100755`とExecutableを確認し、Smoke側のchmodやContract緩和は行っていない。

## Version and Repository Evidence

一時Composer Homeだけへ次の独立Path Repositoryを定義した。

- Skeleton: `/smoke/package`、version `1.0.0`、`symlink=false`
- Framework: `/framework`、version `1.0.0`、`symlink=false`

Skeleton MetadataのNameは`blackops/skeleton`、Typeは`project`、Framework Constraintは`^1.0`である。Create-projectはSkeleton `1.0.0`を解決し、生成LockはFramework `1.0.0`を記録したため、同一Version PolicyとConstraint整合性が成立する。

## Normal Create-project Evidence

通常Create-projectはSkeleton Sourceを一時TargetへMirrorし、38 PackageをInstallしてApplication Lockを生成した。Targetと`vendor/blackops/framework`はSymlinkでなく、Package Sourceとは異なるFile実体である。

Composer `post-create-project-cmd`が`bin/setup`を実行し、`.env`はExampleとbyte一致した。Generated DirectoryとExecutable Entrypointが存在し、Consumer `vendor/autoload.php`だけでPublic `Application`、Welcome、Report OperationをLoadできた。

生成Targetの`composer.json`には`repositories`／`version` Keyがなく、一時Composer HomeのLocal Repository／Version InjectionがApplicationへ保存されない。生成Lockは`blackops/framework` `1.0.0`を記録する。

## No-scripts and Manual Setup Evidence

同じRepositoriesから`--no-scripts` Create-projectを実行し、Setup前に`.env`がない一方、Source、Lock、Vendor、Framework Copy、Autoloadが成立することを確認した。

Generated Directoryを除去して`php bin/setup`を手動実行し、`.env`のbyte一致とDirectory再作成を確認した。再実行前後の`.env` Content Hash、Permission、Timestampは同一である。

No-scripts Targetも`composer.json`へ`repositories`／`version` Keyを持たず、生成Lockは`blackops/framework` `1.0.0`を記録する。Repository／Version Injectionは通常／no-scriptsの両経路で一時Composer Homeだけに閉じる。

## Side Effect and Cleanup Evidence

通常／no-scriptsの両Install後もOperation／HTTP／Container Build Artifact、JSONL Logは存在しない。SetupはMigrationやDatabase接続を行わず、Smoke前後のDocker Container、Image、Network、Volume ID一覧が同一である。

Checked-in QuickstartのGit StatusをSmoke前後で比較し、Source変更がないことを確認した。成功時は一時Package、両生成Project、Composer Homeを明示削除して不存在を確認し、失敗時もTrapが同じ一時Rootを削除する。

## Changed Files

- `tests/Consumer/skeleton-create-project.sh`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `examples/quickstart/bin/setup`（Git executable modeのみ。mode-only CommitはOrchestrator対応済み）
- `examples/quickstart/README.md`
- `docs/guide/installed-application-status.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/internals/mvp-e2e.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P8-002-local-split-create-project-smoke.md`
- `develop/orchestration/reports/P8-002-local-split-create-project-smoke.md`
- `develop/STATE.md`

Production Codeは変更していない。

## Decisions and Assumptions

- Local Package SourceはWorking Tree CopyではなくCommitted Git Archiveを正本とした。
- Repository／Version Injectionは一時Composer Homeだけに置き、生成ProjectやPackage Sourceへ保存しない。
- Composer dependency downloadはInstall自体の責務であり、Post-create SetupのNetwork Side Effectではない。
- Docker Resource不変はSmoke前後のID Set比較で検証した。Test Runner Containerは`docker run --rm`で終了時に除去される。
- Local Smoke成功はDistribution Repository、Remote Tag、Packagist Packageの存在を意味しない。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid.

docker compose run --rm app mago format --check src tests examples
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit tests/Architecture/QuickstartApplicationArchitectureTest.php
Result: OK (6 tests, 94 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/phpunit
Result: OK (647 tests, 2197 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 350 files / Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1489 / Warnings 0 / Errors 0.

bash tests/Consumer/skeleton-create-project.sh
Result: Skeleton create-project smoke passed. Normal/no-scripts, setup, autoload, side-effect, source, cleanup checks succeeded.

! rg -n '"type"..."path"|"url"..."/framework"|"repositories"...|"version"...' examples/quickstart/composer.json
Result: No matches.

! test -e examples/quickstart/composer.lock
! test -d examples/quickstart/vendor
Result: Both negated checks exited 0.

! rg -n 'BlackOps\\Internal' examples/quickstart --glob '*.php'
Result: No matches.

! rg -n 'Spec(ification)?...|D...|P...|TODO.md:...' src tests examples --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

Orchestrator Review後、両生成TargetのMetadata非保存とno-scripts Lock Version Assertionを追加してSmokeを再実行した。Architecture `6 tests / 94 assertions`、全Source／Internal／管理ID Guard、`git diff --check`も再成功した。

## Acceptance Criteria

Task Packetの12項目をすべて満たした。Committed Extraction、Metadata／Executable、Source Cleanliness、Version、通常／no-scripts Create-project、Setup、Copy Install、Side Effect不在、Source／Cleanup、全品質Command、管理文書を完了した。

Orchestrator AcceptanceではArchitecture `6 tests / 94 assertions` とLocal create-project Smokeを再実行した。通常／no-scripts双方でRepository／Version Key不在、Framework `1.0.0` Lock、Copy Install、Setup、Autoload、Side Effect不在、Cleanupが成功した。

## Remaining Issues

Blockerはない。Distribution RepositoryへのSplit Push、Remote Tag、Packagist公開、Remote PackageからのCreate-projectは後続TaskのScopeである。

## Suggested Next Action

P8-003 Distribution PublicationのRepository／Credential境界を確認する。
