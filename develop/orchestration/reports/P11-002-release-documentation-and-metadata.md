# P11-002: Release Documentation and Metadata Report

Status: Accepted

## Summary

Experimental `1.1.0` Release CandidateのPackage Constraint、Local／Consumer Fixture、Release Note、Upgrade Guide、README、利用者向けGuide、Website Version Noticeを一つのRelease Surfaceへ同期した。`1.0.0`からのProject Entrypoint／Command／HTTP Error／Default RuntimeのBreaking Changeを隠さず、Generator、Application Migration、Validation、Worker ModeをAdded／Changed Surfaceとして記録した。

全PHP Quality Gate、6本のConsumer／Publication Smoke、Website Unit／Check／Build／Artifact Guardが成功した。Tag、Packagist、GitHub Release、Documentation Website公開は行っていない。

## Changed Files

- Root Release Surface: `CHANGELOG.md`、`UPGRADE.md`、`README.md`、`Dockerfile`
- Skeleton: `examples/quickstart/composer.json`、`examples/quickstart/README.md`
- Public Guide: Installation、Quickstart、Tutorial、Current Status、BlackOps CLI、Generator、Guide Index
- Internal Documentation: Development Setup、Consumer E2E、Publication、Installed Application Status
- Website: README、Content Map、Site Check、Guide Code Test
- Consumer Fixture: Quickstart E2E、Worker Mode、Create-project、Publication Dry Run
- Project Tracking: TODO、Phase 11 Plan、Task Packet、Report、STATE

## Release Metadata

- Root Docker development versionを`1.1.0@dev`へ更新した。
- SkeletonのFramework Constraintを`^1.1`へ更新した。
- Current ConsumerのLocal Framework／Skeleton Fixtureを`1.1.0`へ更新した。
- Framework Update Consumerは意図的に`1.0.0`から`1.1.0`へ更新するScenarioを維持し、成功した。
- Skeleton Publication Dry RunのDefault Versionを`1.1.0`へ更新した。
- `CHANGELOG.md`へKeep a Changelog互換の`1.1.0` Added／Changed／Removed／Known Limitationsを追加した。
- `UPGRADE.md`へComposer、Project Root Entrypoint、9 Command、Worker／Classic Runtime、Environment、HTTP 400／422、Build／MigrationのMigration手順を追加した。

## Documentation Coverage

- README、Guide、Website BannerをLatest Stable `1.1.0`へ同期した。
- Experimental、1.x Minor間のBackward Compatibility未保証、Production Ready未保証を明示した。
- Quickstartを`composer create-project blackops/skeleton my-app 1.1.0`から開始する手順へ変更した。
- Project Root `blackops`、PrefixなしCommand、Generator、Application Migration、7 Validation Attribute、FrankenPHP Worker ModeをStable `1.1.0` Surfaceとして説明した。
- Historical `1.0.0` Tag／Packagist EvidenceはInternal Documentationに維持し、Current `1.1.0` Metadataが未公開であることも明記した。
- Website Generated Contentは直接編集せず、Source GenerationとBuildで検証した。

## Decisions and Assumptions

- Experimental Release Contractに従い、旧`bin/blackops`と旧`blackops:*` Project Commandの互換性を提供しない。
- `Latest Stable 1.1.0`はRelease Candidateの利用者向けMetadataとして同期する。一方、Internal Publication Evidenceでは`1.1.0` Tag／Packagistが後続Taskであることを明示し、公開済みとは記載しない。
- Application所有Entrypoint、生成済みOperation／Migration、ConfigurationはFramework Updateで自動変更されないため、Upgrade Guideは手動Merge手順として記載した。
- 旧`bin/blackops`はDirectory階層を前提にするため単純移動しない。Upgrade GuideはSkeleton `1.1.0`の完全なRoot Entrypointを実行可能な置換Commandとして掲載し、TestでSkeleton Sourceとのbyte一致を保証する。
- Framework Database Migrationは`1.0.0`から不変であり、Application Migration Runtimeの追加だけを記載した。
- PSR Packageの`^1.0` DependencyとSemVer ValidatorのInvalid Input例はRelease Version Fixtureではないため変更しなかった。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Skeletonともにstrict validation成功。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: Format済み。Lint／AnalyzeはNo issues found。

docker compose run --rm app vendor/bin/phpunit
Result: OK (871 tests, 2831 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1712 / Warnings 0 / Errors 0。

bash tests/Consumer/quickstart-e2e.sh
Result: `1.1.0` mirror install、Generator、Build、Migration、HTTP、Validation、Worker、Outcomeを含むConsumer E2E成功。

bash tests/Consumer/frankenphp-worker-mode.sh
Result: Worker bootstrap、Request isolation、Journal flush、DB failure／reconnect、memory bounds、Classic fallbackを含むE2E成功。

bash tests/Consumer/quickstart-setup.sh
Result: Post-create／Manual Setupの再実行、安全境界を検証して成功。

bash tests/Consumer/skeleton-create-project.sh
Result: Skeleton／Framework `1.1.0`の通常／`--no-scripts` Create-project Smoke成功。

bash tests/Consumer/framework-update-generators.sh
Result: Framework `1.0.0`から`1.1.0`へのUpdate、Project Root Entrypoint／生成済みSource不変、Current Command／Stubを検証して成功。

bash tests/Consumer/skeleton-publication.sh --dry-run
Result: version=1.1.0、source=5b636058cc47d7b31b0a79bd991914c0da364c45、split=working-treeで成功。

mise exec -- pnpm --dir docs/website run test
Result: 36 tests / 36 passed。Upgrade GuideのRoot Entrypoint掲載内容がSkeleton `1.1.0` Sourceとbyte一致し、単純な`mv`を実行手順に含めないことも検証。

mise exec -- pnpm --dir docs/website run check
Result: Content／Mermaid／Astro Check成功。16 files / 0 errors / 0 warnings / 0 hints。

mise exec -- pnpm --dir docs/website run build
Result: 28 Public Pages plus 404、Pagefind 29 HTML、Artifact／Site／Search Guard成功。既知のChunk Size Warningのみ。

Public Artifact Guard
PHP Management ID Guard
git diff --check
Result: すべてMatch／Errorなし。
```

Review指摘対応後にWebsite Test／Check／Build、PHP Management ID Guard、`git diff --check`を再実行し、上記最終結果を確認した。

## Acceptance Criteria

- [x] Skeletonが`blackops/framework: ^1.1`を要求する
- [x] Local／ConsumerのCurrent Release Fixtureが`1.1.0`で整合する
- [x] Framework Update Consumerが`1.0.0`から`1.1.0`へのBreaking Upgradeを検証する
- [x] CHANGELOGがAdded／Changed／Removed／Known Limitationsを正確に記録する
- [x] UPGRADEがEntrypoint、Command、Composer、Runtime／Environmentの移行手順を提供する
- [x] README／Guide／WebsiteがLatest Stable `1.1.0`とExperimental Policyを表示する
- [x] QuickstartがStable `1.1.0`から実行できる手順を示す
- [x] Stableと`main`が同じRelease Surfaceになった項目を未Releaseと表現しない
- [x] Website Unit／Check／BuildとPublic Artifact Guardが成功する
- [x] PHP／Consumer／Publication Dry RunのRequired Gateが成功する
- [x] ReportとSTATEが更新される

## Orchestrator Review

Upgrade Guideの旧`bin/blackops`移行手順が単純なFile移動と誤解されないよう、Skeleton `1.1.0`と同一のRoot Entrypoint完全版へ修正した。Website Testで掲載内容とSkeleton Sourceのbyte一致、単純な`mv`不在、実行確認、旧Entrypoint削除手順を固定した。

Orchestratorが次を独立再検証し、すべて成功した。

- Composer Strict Validation: Root／Skeleton成功
- Mago Format／Lint／Analyze: 成功
- PHPUnit: 871 tests / 2831 assertions
- Deptrac: Violations 0
- Website: 36 tests / 36 passed、Astro diagnostics 0、Build／Artifact／Site Check成功
- Skeleton Create-project: 通常／`--no-scripts`成功
- Framework Update: `1.0.0`から`1.1.0`への更新成功
- Skeleton Publication Dry Run: version `1.1.0`、working-tree split成功
- Public Artifact Guard、PHP Management ID Guard、`git diff --check`: 成功

Review Findingはすべて解消され、P11-002をAcceptedとした。

## Remaining Issues

P11-002のBlockerはない。`1.1.0` Tag／Packagist／GitHub Releaseはまだ作成しておらず、P11-003 Release Candidate GateとP11-004 PublicationのScopeである。Documentation WebsiteのCloudflare公開はUserが再開を明示するまで延期する。

## Suggested Next Action

P11-002をTask単位でCommitしてmainへPushし、P11-003 Release Candidate Gateへ進む。
