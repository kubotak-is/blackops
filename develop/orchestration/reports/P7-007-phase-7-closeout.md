# P7-007 Phase 7 Closeout Report

## Summary

Phase 7のInstalled Application Example and Skeleton Layoutをcloseoutした。9項目のPhase Acceptance Criteriaを現行Quickstart、Accepted Task Report、Architecture Guard、Full Quality Suite、Root Dev AutoloadなしConsumer E2Eへ対応付けた。

`examples/quickstart/` はPhase 8で `blackops/skeleton` を配布するためのPackage Source Boundaryである。ただしPackagist、Remote `composer create-project`、公開Package Smoke Test、Stable Releaseは未実装である。

## Phase 7 Acceptance Evidence

| # | Acceptance criterion | Status | Evidence |
| --- | --- | --- | --- |
| 1 | Quickstartが独立Composer Projectとして成立する | Satisfied | `examples/quickstart/composer.json` は `blackops/skeleton`／`type: project`／独立PSR-4を定義する。RootとQuickstartのstrict Composer Validationが成功した。P7-005 Accepted。 |
| 2 | Application CodeとBootstrapにInternal Importがない | Satisfied | Quickstart Architecture Testと全Quickstart PHPのInternal Import Guardが成功した。P7-002からP7-005でPublic Bootstrap／HTTP／Console Boundaryを受入済み。 |
| 3 | Welcome／ReportをDirectory単位で削除できる | Satisfied | Feature間に直接依存がなく、Build-time DiscoveryによりProvider／Bootstrap編集なしでFeatureを追加・削除できる。P7-005A Accepted。 |
| 4 | HTTPとConsoleを同じConfiguration Snapshotで構成できる | Satisfied | 一つの `bootstrap/app.php` がApplicationを作り、Public `http()`／`console()`を構成する。Snapshot、HTTP、Console TestはP7-002、P7-003、P7-004でAccepted。 |
| 5 | Project所有CLIがFramework Commandを起動する | Satisfied | `examples/quickstart/bin/blackops` がPublic Console Kernelを起動し、Operation List、Build、Migration、Worker、Retention、Schedulerを利用できる。P7-004／P7-005 Accepted。 |
| 6 | BuildとMigrationが明示Commandである | Satisfied | Compose／ImageにInstall、Build、Migration startupがない。Consumer E2EはBuild／Status後のSchema不在と明示Migrate後の作成を検証した。 |
| 7 | Local RuntimeでInline／Deferred／Worker／Retry／Outcome／Retentionを検証できる | Satisfied | PHP 8.5、FrankenPHP 1、PostgreSQL 18でWelcome 200、Report 202、Retry後Completed、Encoded Outcome、Sensitive JSONL、Retention Plan／Dry Runが成功した。P7-006 Accepted。 |
| 8 | Root Dev Autoloadへ依存しないConsumer E2Eが成功する | Satisfied | Temp ConsumerへFrameworkを `symlink=false` でmirror installし、通常RuntimeはConsumer VendorだけでScenarioを完走した。P7-006 AcceptanceとP7-007再実行で成功した。 |
| 9 | Full PHPUnit、Mago、Deptrac、Public API Guardが成功する | Satisfied | P7-007でMago 3種、647 tests／2187 assertions、Deptrac 350 files／0 violations、Internal／Source／管理ID Guardが成功した。 |

利用者向けの状態とPhase 8境界は `docs/internal/installed-application-status.md` に同期した。

## Installed Tree and Public Boundary Evidence

現行Treeは `develop/spec/43-installed-application-layout-and-bootstrap.md` と `develop/spec/49-feature-first-quickstart-application.md` のProject Treeに一致する。Feature、Bootstrap、Config、HTTP／Console Entrypoint、Tests、Generated Directory Keep File、Local Runtime Fileを持つ。D072により `app/Infrastructure/` と `migrations/` は必要時に追加する任意の配置先と確定し、空Directoryを配布しない。

Quickstart SourceはInternal型をImportせず、Root Composerは `App\` をAutoloadしない。Checked-in Composer MetadataにPath Repository／`/framework`はなく、LockとVendorも存在しない。Generated ArtifactとLogはKeep File以外を含まない。

Self-handled Operationが標準導線であり、Optional `#[HandledBy]` とSeparate Handler互換を維持する。DiscoveryとContainer生成はBuild-timeだけで、HTTP／Worker RuntimeはCompile済みArtifactから構成される。

## Runtime and Consumer Evidence

Quickstart ComposeのDefault Serviceは `postgres` と `http` だけである。`app` は明示CLI run target、WorkerとSchedulerはProfile起動で、Migrationと変更を伴うRetention Purgeも暗黙実行されない。

Consumer E2Eは一時QuickstartへFrameworkをcopy installし、Build、Read-only Database Status、明示Migration、Inline、Sensitive Projection、Deferred受付、Worker Retry／Completion、Outcome、Retention Dry Runを機械検証した。成功後にCompose Resource、Image、一時ConsumerをCleanupした。

## Phase 8 Package Source Handoff

Phase 8は `examples/quickstart/` をPackage Sourceとして次を担当する。

- Skeleton Split／Distribution境界
- Framework Release VersionとのConstraint同期
- Packagist公開
- Remote `composer create-project blackops/skeleton my-app`
- 公開PackageからのInstall後Smoke Test

Phase 7 Consumer E2EはLocal Path Repositoryを一時注入したcopy installであり、Remote Publicationの証拠として扱わない。

## Changed Files

- `develop/TODO.md`
- `develop/DOCS.md`
- `develop/decisions/072-skeleton-empty-directory-policy.md`
- `develop/spec/README.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/45-phase-7-delivery-plan.md`
- `docs/guide/README.md`
- `docs/internal/installed-application-status.md`
- `docs/internal/README.md`
- `docs/internal/mvp-e2e.md`
- `examples/quickstart/README.md`
- `develop/orchestration/tasks/P7-007-phase-7-closeout.md`
- `develop/orchestration/reports/P7-007-phase-7-closeout.md`
- `develop/STATE.md`

Production CodeとTestは変更していない。

## Decisions and Assumptions

- D072でInfrastructure／Application Migration Directoryを必要時に追加する任意境界と確定し、Spec 43と具体Tree仕様のSpec 49を同期した。
- Phase 7 Complete、MVP Complete、Production Ready、Stable Releaseを区別した。
- `examples/quickstart/` をPackage Source Boundaryとするが、Remote Packageが存在するとは記載しない。
- TODOはPhase 7 Consumer E2Eだけを完了し、Phase 8のRemote Create-projectとInstall Smokeを未完了のまま維持した。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid.

docker compose -f examples/quickstart/compose.yaml config
Result: Valid configuration.

docker compose -f examples/quickstart/compose.yaml config --services
Result: postgres, http.

docker compose run --rm app mago format --check src tests examples
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (647 tests, 2187 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 350 files / Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1489 / Warnings 0 / Errors 0.

bash tests/Consumer/quickstart-e2e.sh
Result: Quickstart consumer E2E passed. Framework mirrored, scenario passed, cleanup completed.

! rg -n 'BlackOps\\Internal' examples/quickstart --glob '*.php'
Result: No matches.

! rg -n '"type"..."path"|"url"..."/framework"|"repositories"...' examples/quickstart/composer.json
Result: No matches.

! test -e examples/quickstart/composer.lock
! test -d examples/quickstart/vendor
Result: Both negated checks exited 0.

! rg -n 'Spec(ification)?...|D...|P...|TODO.md:...' src tests examples --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

Docker commandは初回Sandbox実行でDocker Socket Permissionにより失敗したため、承認済みDocker実行として再実行し、上記の最終結果はすべて成功した。

## Acceptance Criteria

Task Packetの10項目をすべて満たした。Phase Acceptance 9項目はEvidence付きでSatisfiedであり、Installed Tree／Public Boundary／Authoring／Process／Consumer／Phase 8 Handoff／Documentation／Quality／管理状態を同期した。

## Remaining Post-Phase-7 Work

Blockerはない。Skeleton Publication、Remote Create-project、公開後Smoke、Generator、Documentation Website、Stable Releaseは後続PhaseのScopeである。

## Suggested Next Action

Phase 8 Composer Project Bootstrapの最初のTask Packetを確定し、Skeleton Package生成／Split境界から着手する。
