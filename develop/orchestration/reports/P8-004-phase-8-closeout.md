# P8-004 Phase 8 Closeout Report

## Summary

Phase 8 Composer Project BootstrapをCloseした。Framework／Skeleton Stable `1.0.0`のGitHub／Packagist Metadata、通常／`--no-scripts` Remote Create-project、Post-create／Manual Setup、Installed Lock／Autoload／CLI、暗黙Side Effect不在を公開Packageだけから検証した。

Full Quality Suite、Quickstart Consumer E2E、Local Create-project、Publication Dry Runも再実行し、Phase Acceptance 10項目をすべてEvidence付きでSatisfiedとした。Production CodeとTestは変更していない。

## Packagist and Remote Package Evidence

| Package | Version | Source evidence |
| --- | --- | --- |
| `blackops/framework` | `1.0.0` | Framework Commit `279716f904f17be9341f3fdaae30156ab17d8a62` |
| `blackops/skeleton` | `1.0.0` | Distribution Split Commit `da573f3190e5e855a9c09e275980c6ddc5cce028` |

Skeleton Metadataは`type: project`、PHP `>=8.5`、Framework `^1.0`、`App\\` PSR-4を公開する。Distribution `main`と`1.0.0`は同じSplit Commitを指す。

## Remote Normal and No-scripts Create-project Evidence

Repository外の一時Directoryと空Composer HomeをPHP 8.5 Containerへmountし、Local Path Repository、Local Framework Mount、既存Composer Cacheを使わずPackagistから次を実行した。

```text
composer create-project blackops/skeleton /smoke/normal 1.0.0 --no-interaction --prefer-dist
composer create-project blackops/skeleton /smoke/no-scripts 1.0.0 --no-interaction --prefer-dist --no-scripts
composer create-project blackops/skeleton /smoke/my-app --no-interaction --prefer-dist
```

明示Versionの両InstallはSkeleton `1.0.0`と38 Runtime Packageを取得し、Lockへ`blackops/framework:1.0.0`を記録した。Version省略の公式Commandも最新Stable `1.0.0`を選択した。通常Installは`post-create-project-cmd`から`.env`をbyte-for-byte Copyし、`var/build`／`var/log`を保持した。Build Artifact、Journal、Database、Migrationは生成されなかった。

`--no-scripts` Install直後は`.env`がなく、Manual `php bin/setup`で同じ状態を作成した。Setup再実行前後の`.env` Hashは一致した。両ProjectのComposer Metadataに`repositories`／`version`はなく、Consumer AutoloadからFramework Application、Welcome、Report ClassをLoadできた。Project CLIのCommand Listに`blackops:build:compile`が存在した。

Docker Container／Image／Network／VolumeはSmoke前後で一致し、一時Directoryを削除した。最初の検証Harnessは2つの独立Composer Autoloaderを同じPHP Processへ読み込み、Composer生成Init Class名の衝突で終了した。Install結果に問題はなく、Projectごとに独立PHP Processへ修正した再実行が全項目に成功した。

## Phase 8 Acceptance Evidence

| # | Acceptance criterion | Status | Evidence |
| --- | --- | --- | --- |
| 1 | Post-createが安全かつ再実行可能 | Satisfied | 通常Remote Installと既存Setup Testで`.env` Copy、Directory準備、再実行非上書きを確認。 |
| 2 | `--no-scripts`とManual Setupが成立 | Satisfied | Remote `--no-scripts` Install後にManual Setupを2回実行し、通常と同じ状態とHash不変を確認。 |
| 3 | Split Rootが正しいSkeleton Package | Satisfied | Distribution／Packagist Metadata、Root Allowlist、必須File、Executable BitをP8-003で確認。 |
| 4 | Framework／Skeleton Version Policyを機械検証 | Satisfied | 両Package `1.0.0`、Skeleton Constraint `^1.0`、Publication Guard、Remote Lock `1.0.0`が一致。 |
| 5 | Lock／Vendor／Path Repository／Generated Stateが配布へ不在 | Satisfied | Publication Dry Run、Remote Composer Metadata、Source Guardが成功。生成ApplicationだけがLock／Vendorを所有。 |
| 6 | 通常Create-projectが成功 | Satisfied | 空Composer HomeからPackagist Stable `1.0.0`を通常Installし、Post-create、Lock、Autoload、CLIを確認。 |
| 7 | `--no-scripts` Install後Smokeが成功 | Satisfied | Remote Install、Manual Setup、再実行、Autoload、CLI、Cleanupが成功。 |
| 8 | 同一Release Tag Publication Boundaryが成立 | Satisfied | Framework `1.0.0`とSkeleton `1.0.0`をGitHub／Packagistへ公開。Recovery Workflow全Gate成功。 |
| 9 | Remote Packagist Packageから公式Commandが成功 | Satisfied | Local Repository／CacheなしでVersion省略の`composer create-project blackops/skeleton my-app`がStable `1.0.0`を選択して成功。 |
| 10 | Full QualityとConsumer／Install Smokeが成功 | Satisfied | Mago、721 PHPUnit、Deptrac、Consumer E2E、Local／Remote Create-project、Publication Dry Runが成功。 |

## Documentation and Phase 9 Handoff

Guide、Installed Application Status、MVP Status、Quickstart README、Internalsを公開済みStable Packageへ同期した。MVP Complete、Stable Package公開、Production Readyは別の状態として維持する。

Phase 9は既存のProject所有`bin/blackops`とFramework Console Kernelを基盤に、`make:operation`／`make:migration`とFramework Update時のCommand／Stub追従を実装する。Skeleton Sourceは引き続きMain Repositoryの`examples/quickstart/`だけを正本とし、Distributionへ直接機能Commitしない。

## Changed Files

- `develop/TODO.md`
- `develop/DOCS.md`
- `develop/spec/52-phase-8-delivery-plan.md`
- `docs/guide/README.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/internal/installed-application-status.md`
- `docs/guide/mvp-status.md`
- `docs/guide/mvp-sample.md`
- `docs/internal/mvp-e2e.md`
- `docs/internal/skeleton-publication.md`
- `examples/quickstart/README.md`
- `develop/orchestration/tasks/P8-004-phase-8-closeout.md`
- `develop/orchestration/reports/P8-004-phase-8-closeout.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Remote SmokeはCloseout Evidenceであり、新しいCommitted Test Scriptを追加しない。
- Stable `1.0.0`を明示して最新BranchやLocal SourceへFallbackしない。
- 通常／`--no-scripts` Projectは独立Autoloaderを持つため、別PHP Processで検証する。
- Release Notes、追加Release、Generator、Documentation WebsiteはPhase 8 Scopeへ含めない。

## Commands and Results

```text
Packagist Composer Metadata API
Result: Framework／Skeleton Stable 1.0.0が公開Commitを参照。

Remote normal／--no-scripts composer create-project
Result: Passed. Skeleton 1.0.0、Framework Lock 1.0.0、38 packages、Setup／Autoload／CLI／Side-effect／Cleanup checks passed.

Versionless official composer create-project command
Result: Passed. Latest stable Skeleton 1.0.0 and Framework 1.0.0 were selected.

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Both Composer files are valid.

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: All commands completed with no issues.

docker compose run --rm app vendor/bin/phpunit
Result: OK (721 tests, 2374 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 361 files / Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1546 / Warnings 0 / Errors 0.

bash tests/Consumer/quickstart-e2e.sh
Result: Quickstart consumer E2E passed.

bash tests/Consumer/skeleton-create-project.sh
Result: Skeleton create-project smoke passed.

bash tests/Consumer/skeleton-publication.sh 1.0.0 refs/tags/1.0.0
Result: Passed. Source 279716f904f17be9341f3fdaae30156ab17d8a62, split da573f3190e5e855a9c09e275980c6ddc5cce028.

Internal import、Path Repository、Lock／Vendor、management ID、stale public documentation guards
Result: No forbidden matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

Task Packetの10項目をすべて満たした。Packagist Metadata、Remote通常／`--no-scripts` Install、Lock、Metadata、Autoload、Side Effect、Documentation、Full Quality、Phase管理をEvidence付きで受け入れた。

## Remaining Post-Phase-8 Work

Phase 8 Blockerはない。Project Generator Command、Framework Update追従、Documentation Website、MVP後のOperational／Adapter項目は後続Phaseで扱う。Stable Package公開はProduction Certificationを意味しない。

## Suggested Next Action

Phase 9 Project BlackOps CLIのTask Packetを作成し、`make:operation`と`make:migration`の仕様対話から開始する。
