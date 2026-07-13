# P9-004: Framework Update Generator Smoke and Phase 9 Closeout Report

Status: Accepted

## Summary

Framework Update前後を再現するLocal Consumer Smokeを追加した。Repository History上の固定Commitへ依存せず、一時Directory内の同じFramework Sourceから旧版相当`1.0.0`とCurrent `1.1.0`のLocal Composer Packageを作成する。

旧版相当でOperation／Migrationを生成した後、Framework DependencyだけをCurrentへ更新し、Project `bin/blackops`と既存生成Sourceがbyte-for-byte不変であること、Update後の新規生成がCurrent Framework Command／Stubを使うことを検証した。Quickstart Consumer E2EとLocal Create-project SmokeにもGenerator経路を追加し、Publication WorkflowへFramework Stub AllowlistとSkeleton非複製Gateを追加した。

Guide、Internals、Quickstart、MVP Status、TODO、Phase PlanをPhase 9完了内容へ同期した。Production Codeは変更していない。

## Framework Update Evidence

`tests/Consumer/framework-update-generators.sh`は次を検証する。

1. Current Framework SourceとCurrent StubをRepositoryの現在のCommitから一時Directoryへ展開する
2. Framework Stubへ`Legacy fixture stub` Marker、2 Generator Command出力へ`Legacy Created:` Prefixを追加したCommitをLocal `1.0.0`とする
3. Current StubとCurrent Command Sourceへ戻した次のCommitをLocal `1.1.0`とする
4. Committed QuickstartをConsumerへ展開し、Local VCSからFramework `1.0.0`をInstallする
5. 旧版相当CommandでOperation 3 FileとMigration 1 Fileを生成し、両出力の`Legacy Created:` Prefixを確認してからEntrypointと4生成FileのSHA-256を保存する
6. Composer Lock内のFramework以外のPackage Version集合を保存する
7. `composer update blackops/framework`で`1.0.0`から`1.1.0`へ更新する
8. ComposerがFramework 1 Packageだけを更新し、他Dependency集合が一致することを確認する
9. Project Entrypointと既存生成SourceのSHA-256がすべて一致することを確認する
10. Vendor Stubと2 Generator Command SourceがCurrent Framework Sourceとbyte一致することを確認する
11. 新規Operation／Migrationの出力が旧Prefixを持たずCurrent `Created:` Prefixであり、生成Sourceにも旧MarkerがなくCurrent Contractを持つことを確認する
12. Update後の新規Operationを含むApplication Buildが成功することを確認する

実行結果ではComposerが`blackops/framework (1.0.0 => 1.1.0)`の1件だけを更新し、`bin/blackops`、旧Operation 3 File、旧Migration 1 Fileのhash checkがすべて`OK`となった。Update前の`Legacy Created:`とUpdate後のCurrent `Created:`、Vendor Command Sourceのbyte一致、新規生成、Buildも成功した。

Smokeは一時Framework Repository、Consumer、Composer Homeを成功／失敗の両方でCleanupする。Remote Mutation、Credential保存、Project Entrypoint／既存生成SourceのTest都合による書換は行わない。

## Stub Ownership Evidence

- Framework Stub Allowlistは`resources/stubs/`直下の次の4 Fileとする。
  - `migration.php.stub`
  - `operation-outcome.php.stub`
  - `operation-value.php.stub`
  - `operation.php.stub`
- Publication WorkflowはAllowlistの完全一致と4 FileがGit管理対象であることを確認する。
- Publication Workflow、Required Guard、Local Create-project Smokeは`examples/quickstart/`と生成Projectに`resources/stubs/`がないことを確認する。
- Framework Update SmokeはUpdate後のVendor StubとCurrent Framework Stubのbyte comparisonを行う。

## Changed Files

- `.github/workflows/publish-skeleton.yml`
- `tests/Consumer/framework-update-generators.sh`
- `tests/Consumer/skeleton-create-project.sh`
- `tests/Consumer/quickstart-e2e.sh`
- `docs/guide/README.md`
- `docs/guide/project-generators.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/guide/installed-application-status.md`
- `docs/guide/mvp-status.md`
- `docs/internals/README.md`
- `docs/internals/project-generators.md`
- `docs/internals/skeleton-publication.md`
- `examples/quickstart/README.md`
- `develop/TODO.md`
- `develop/spec/56-phase-9-delivery-plan.md`
- `develop/orchestration/tasks/P9-004-framework-update-generator-smoke.md`
- `develop/orchestration/reports/P9-004-framework-update-generator-smoke.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Userの2026-07-13「進めて」は、Model／Profileを明示できない現在のWorkerでPhase 9 Closeoutまで継続する承認としてTask Packetに記録済みである。
- Framework Update Smokeの旧版相当は過去Commitではなく、Current SourceのFramework所有Stubと2 Generator Command出力だけへ合法な識別Markerを加えて構成する。これにより将来のRepository History変更から独立しつつ、Command実装切替を直接観測する。
- Local `1.0.0`／`1.1.0`はSmoke内だけのFixture Versionであり、公開済みReleaseまたは将来のRelease Versionを表さない。
- Phase 9完了はMain Branch上の実装と検証の完了を表し、新しいPackagist Release公開を意味しない。外部Package PublicationはTask Scope外である。
- SkeletonはProject Entrypointを所有し、FrameworkはCommand実装とStubを所有する。Framework UpdateとSkeleton Updateを同一視しない。

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

docker compose run --rm app vendor/bin/phpunit
Result: Worker／Orchestrator final rerunともにOK (771 tests, 2544 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Worker／Orchestrator final rerunともに368 files / Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1578 / Warnings 0 / Errors 0.

bash tests/Consumer/quickstart-e2e.sh
Result: Worker／Orchestrator final rerunともにQuickstart consumer E2E passed. Generated OperationをOperation List／Buildへ含め、Generated Application MigrationをFramework Migrationの後に適用した。Migrations 3.

bash tests/Consumer/skeleton-create-project.sh
Result: Worker／Orchestrator final rerunともにSkeleton create-project smoke passed. Normal InstallでOperation／Migration生成とBuild、Skeleton Stub非複製を検証した。通常／no-scripts Installも成功した。

bash tests/Consumer/framework-update-generators.sh
Result: Worker／Orchestrator final rerunともにFramework update generator smoke passed. Frameworkだけを1.0.0から1.1.0へ更新し、Entrypoint／既存生成Source hash不変、旧`Legacy Created:`／Current `Created:`出力切替、Vendor 2 Command Source／StubのCurrent一致、新規生成、Buildを検証した。

Internal Import Guard
Result: examples/quickstartのPHPにBlackOps\\Internal importなし。

Skeleton Stub Guard
Result: examples/quickstartにstubs配下のFileなし。

Management ID Comment Guard
Result: src／tests／examplesのPHP Comment／DocBlockに禁止管理IDなし。

Framework Stub Allowlist／Tracked File／Skeleton非複製Gate
Result: 4 Stubの完全一致、Git管理、Skeleton非複製を確認した。

python3 -c YAML parse
Result: .github/workflows/publish-skeleton.ymlを正常にparseした。

bash -n tests/Consumer/framework-update-generators.sh tests/Consumer/skeleton-create-project.sh tests/Consumer/quickstart-e2e.sh
Result: Shell syntax errorなし。

git diff --check
Result: Errorなし。
```

## Acceptance Criteria

- [x] 既存ProjectのFramework DependencyだけをLocal旧版相当からCurrentへ更新できる
- [x] Update前後で`bin/blackops`がbyte-for-byte不変である
- [x] Update前生成Operation／Migrationがbyte-for-byte不変である
- [x] Update後にCurrent Frameworkの`make:operation`／`make:migration`とStubを利用できる
- [x] Framework StubがFramework Packageへ含まれ、Skeletonへ複製されていない
- [x] Local Create-project／Quickstart Consumer E2EがGenerator込みで成功する
- [x] Full Quality Suiteが成功する
- [x] TODO／Phase Plan／Report／STATEがPhase 9 Completeへ同期する

## Remaining Issues

P9-004およびPhase 9 Scope内の既知Blockerはない。Phase 9変更を含む新しいFramework／Skeleton Package ReleaseはこのTaskのScope外であり、公開していない。

## Suggested Next Action

P9-004 Accepted ChangeをCommit／Pushし、Phase 10 Documentation Website計画へ進む。
