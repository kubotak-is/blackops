# P8-004: Phase 8 Closeout

Status: Completed

## Goal

公開済みPackagist Packageから通常／`--no-scripts`の`composer create-project blackops/skeleton`を実行し、Install直後のApplication、Documentation、Full Quality、Phase Acceptanceを証拠付きで検証してPhase 8をCloseする。

## In Scope

- Packagist `blackops/framework`／`blackops/skeleton` Stable `1.0.0` Metadata検証
- 空のComposer Homeと一時Directoryを使うRemote通常Create-project Smoke
- Remote `--no-scripts` Create-projectとManual `bin/setup` Smoke
- Installed Composer Lock／Autoload／Package Identity／Framework Version検証
- Post-create `.env`／Generated Directory／再実行非上書き検証
- Install時のDocker／Database／Migration／Build Side Effect不在確認
- Guide／Quickstart README／Publication Documentationの公開状態同期
- Phase 8 Acceptance Criteria Evidence
- Full Quality Suite、既存Consumer E2E、Local Create-project、Publication Dry Run再実行
- TODO、Phase Plan、Task Report、STATEのCloseout同期
- Phase 9 Project CLIへのHandoff

## Out of Scope

- Production Code／Public API／既存Test Scenarioの機能変更
- 新しいCommitted Remote Smoke Script
- Framework／Skeletonの追加Release
- GitHub Release Notes
- Project Generator Command実装
- Documentation Website実装

## Relevant Specifications and Decisions

- `develop/decisions/063-developer-experience-roadmap.md`
- `develop/decisions/065-composer-skeleton-publication.md`
- `develop/decisions/076-framework-and-skeleton-repository-naming.md`
- `develop/decisions/078-initial-stable-release-version.md`
- `develop/decisions/079-immutable-release-publication-recovery.md`
- `develop/spec/41-developer-experience-roadmap.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/52-phase-8-delivery-plan.md`

## Files Allowed to Change

- `develop/TODO.md`
- `develop/DOCS.md`
- `develop/spec/52-phase-8-delivery-plan.md`
- `docs/guide/README.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/guide/installed-application-status.md`
- `docs/guide/mvp-status.md`
- `docs/guide/mvp-sample.md`
- `docs/internals/README.md`
- `docs/internals/mvp-e2e.md`
- `docs/internals/skeleton-publication.md`
- `examples/quickstart/README.md`
- `develop/orchestration/tasks/P8-003-skeleton-distribution-publication.md`
- `develop/orchestration/reports/P8-003-skeleton-distribution-publication.md`
- `develop/orchestration/tasks/P8-004-phase-8-closeout.md`
- `develop/orchestration/reports/P8-004-phase-8-closeout.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は変更を広げず、ReportのBlockerとして返す。

## Constraints

- Remote SmokeはLocal Path Repository、Local Framework Mount、既存Composer Cacheを使用しない
- 通常／`--no-scripts`の詳細SmokeはStable `1.0.0`を明示し、追加でVersion省略の公式Commandが同Versionを選択することを確認する
- Smoke TargetはRepository外の一時Directoryに作成し、終了時に削除する
- Remote SmokeのためにProduction CodeまたはTestを変更しない
- Credential、Token、Composer AuthenticationをRepositoryへ保存しない
- Phase 8 Complete、MVP Complete、Production Readyを混同しない

## Acceptance Criteria

- [x] PackagistのFramework／Skeleton Stable `1.0.0` Metadataが公開Commitと一致する
- [x] Remote通常Create-projectが成功し、Post-create状態が正しい
- [x] Remote `--no-scripts` Create-projectとManual Setupが成功する
- [x] Installed Lockが`blackops/framework:1.0.0`を記録する
- [x] Installed Composer MetadataにPath Repository／固定Versionが混入しない
- [x] Consumer AutoloadからFrameworkとApplicationのPublic ClassをLoadできる
- [x] Install／SetupがDocker、Database、Migration、Buildを暗黙実行しない
- [x] Guide／README／Publication Documentationが公開済み導線と一致する
- [x] Full Quality Suiteと既存Consumer／Local Publication Gateが成功する
- [x] Phase 8 Acceptance Criteria、TODO、Report、STATEがEvidence付きでCloseする

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/skeleton-publication.sh 1.0.0 refs/tags/1.0.0
! rg -n 'BlackOps\\Internal' examples/quickstart --glob '*.php'
! rg -n '"type"[[:space:]]*:[[:space:]]*"path"|"url"[[:space:]]*:[[:space:]]*"/framework"|"repositories"[[:space:]]*:' examples/quickstart/composer.json
! test -e examples/quickstart/composer.lock
! test -d examples/quickstart/vendor
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

Remote通常／`--no-scripts` Create-project Smokeは、空のComposer HomeをmountしたPHP 8.5 ContainerからPackagistへ接続して実行し、Command、Target Path、検証結果、CleanupをReportへ記録する。

## Expected Report

`develop/orchestration/reports/P8-004-phase-8-closeout.md` に次を記録する。

- Summary
- Packagist／Remote Package Evidence
- Remote Normal／No-scripts Create-project Evidence
- Phase 8 Acceptance Evidence
- Documentation／Phase 9 Handoff
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Post-Phase-8 Work
- Suggested Next Action
