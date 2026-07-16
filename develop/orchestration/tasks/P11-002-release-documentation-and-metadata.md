# P11-002: Release Documentation and Metadata

Status: Accepted

## Goal

Experimental `1.1.0` Release CandidateのPackage Constraint、Local／Consumer Version Fixture、CHANGELOG、UPGRADE、README、Guide、Website Version Noticeを一貫したRelease Surfaceへ同期する。

## In Scope

- Skeletonの`blackops/framework` Constraintを`^1.1`へ更新
- Local／Consumer Release Candidate Fixtureを`1.1.0`へ更新
- `CHANGELOG.md`と`UPGRADE.md`の作成
- `1.0.0`からのBreaking／Added／Changed／Known Limitationの利用者向け記録
- Project Root `blackops`、Canonical Command、Worker Mode、Validation、Generator、Application MigrationのUpgrade手順
- README、Quickstart、Installation、Current Status、Project CLI、Generator PageのStable `1.1.0`同期
- Experimental／Backward Compatibility未保証の明示
- Website Version Banner、Description、Test、Artifact Checkの`1.1.0`同期
- Internal Release／Consumer Documentationの現行化
- ReportとSTATE更新

## Out of Scope

- 新Feature
- Project CLI Surfaceの再変更
- Release Candidate Full Gate
- `1.1.0` Tag、Skeleton Distribution、Packagist、GitHub Release作成
- Documentation WebsiteのCloudflare公開

## Relevant Specifications and Decisions

- `develop/decisions/094-stable-1-1-release-contract.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/59-documentation-reader-experience.md`
- `develop/spec/61-experimental-release-contract.md`
- `develop/spec/62-phase-11-delivery-plan.md`
- `develop/orchestration/reports/P11-001-release-surface-reset.md`

## Files Allowed to Change

- `CHANGELOG.md`
- `UPGRADE.md`
- `README.md`
- `Dockerfile`
- `examples/quickstart/composer.json`
- `examples/quickstart/README.md`
- `docs/guide/**`
- `docs/internal/**`
- `docs/website/README.md`
- `docs/website/content-map.mjs`
- `docs/website/scripts/**`
- `docs/website/tests/**`
- `tests/Consumer/**`
- `develop/TODO.md`
- `develop/spec/61-experimental-release-contract.md`
- `develop/spec/62-phase-11-delivery-plan.md`
- `develop/orchestration/tasks/P11-002-release-documentation-and-metadata.md`
- `develop/orchestration/reports/P11-002-release-documentation-and-metadata.md`
- `develop/STATE.md`

範囲外Fileの変更が必要な場合は実装を広げず、ReportへBlockerとして記録する。

## Constraints

- Production Code／Release Metadata／利用者向けDocumentationはGPT-5.6 Luna High workerが変更し、Review前にCommitしない
- `CHANGELOG.md`はKeep a Changelog互換のVersion Sectionを持ち、存在しない機能を記載しない
- `UPGRADE.md`はApplication所有Fileを自動更新できると説明しない
- `1.0.0`から`1.1.0`へ破壊的変更があることを隠さない
- `1.1.0`をProduction ReadyまたはPublic Readiness達成済みと表現しない
- Historical Decision／Report／STATE内の`1.0.0`証拠を機械置換しない
- Framework Update Consumerは意図的に`1.0.0`から`1.1.0`へのScenarioを維持する
- PSR Packageの`^1.0` ConstraintをRelease Versionと誤認して変更しない
- Website Generated Contentを直接編集しない
- Source／Test CommentへDecision／Spec／Task管理番号を書かない

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
bash tests/Consumer/frankenphp-worker-mode.sh
bash tests/Consumer/quickstart-setup.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/framework-update-generators.sh
bash tests/Consumer/skeleton-publication.sh --dry-run
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
! rg -n 'docs/internal|develop/|ghp_|gho_|github_pat_' docs/website/dist
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P11-002-release-documentation-and-metadata.md`へ次を記録する。

- Summary
- Changed Files
- Release Metadata
- Documentation Coverage
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
