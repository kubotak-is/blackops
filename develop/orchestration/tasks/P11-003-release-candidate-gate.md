# P11-003: Release Candidate Gate

Status: Ready (Resumed)

## Goal

Experimental Framework／Skeleton `1.1.0`のRelease Candidate Sourceを固定し、Full PHP／Website Quality Suite、全Consumer E2E、Split／Publication Gate、GitHub Actions Evidence、Known Limitations、Publication Checklistを一つのReportへ確定する。

## Fixed Release Candidate

- Source Commit: `e3df5576c7216cfe8bd9e10e12ee6795f7674088`
- Source Commit Subject: `fix: publish annotated skeleton release tags`
- CI Run: `29511467022`（Success）
- Documentation Delivery Run: `29511466795`（Artifact Build Success、Credential不在のためProduction DeployはSkip）
- Release Version: `1.1.0`

Task／Report／STATEだけを更新するCommitはRelease Sourceへ含めない。Production、Skeleton、Release Metadata、利用者向けDocumentationに修正が必要になった場合はCandidate SHAを暗黙に変更せず、ReportへBlockerを記録してOrchestratorへ返す。

最初のFixed Candidate `49b42efe5a0671cbae9212203a07271c1cf36f2b`はSkeleton annotated tag契約を満たさずBlockedとなった。P11-003A Accepted Commit `e3df5576c7216cfe8bd9e10e12ee6795f7674088`でRelease Automationを修正し、Local GateとGitHub Actionsが成功したため、このCommitを新Fixed CandidateとしてFull Gateを最初から再実行する。

## In Scope

- Fixed Release Candidate SHAとGitHub Actions Evidenceの検証
- Composer Strict Validation
- Mago Format／Lint／Analyze
- Full PHPUnit／Deptrac
- 全6 Consumer／Installation／Worker／Framework Update Smoke
- Skeleton `1.1.0` Publication Dry Run
- Split Artifactと通常／`--no-scripts` Create-project境界の監査
- Website Unit／Check／Build／Public Artifact Guard
- Public API、Management ID、Credential、Generated State、Working Tree Guard
- CHANGELOG Known LimitationsとUPGRADE Migration手順の最終照合
- Framework／Skeleton／GitHub Release／PackagistのPublication前状態のRead-only確認
- P11-004で使用するPublication ChecklistとRecovery条件の固定
- ReportとSTATE更新

## Out of Scope

- 新Feature、Production Code、Public APIの変更
- Release Version、Compatibility Policy、Project CLI Surfaceの変更
- `1.1.0` Tag作成またはPush
- Skeleton Distribution Repository更新
- Packagist Mutation
- GitHub Release作成
- Documentation WebsiteのCloudflare公開

## Relevant Specifications and Decisions

- `develop/decisions/094-stable-1-1-release-contract.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/61-experimental-release-contract.md`
- `develop/spec/62-phase-11-delivery-plan.md`
- `develop/orchestration/reports/P11-001-release-surface-reset.md`
- `develop/orchestration/reports/P11-002-release-documentation-and-metadata.md`

## Files Allowed to Change

- `tests/Consumer/**`（Gateの実証不足があり、既存仕様内で補強できる場合のみ）
- `.github/workflows/ci.yml`（Release Gateの既存仕様内修正が必要な場合のみ）
- `.github/workflows/publish-skeleton.yml`（Publication Gateの既存仕様内修正が必要な場合のみ）
- `docs/internal/installed-application-status.md`
- `docs/internal/mvp-e2e.md`
- `docs/internal/skeleton-publication.md`
- `develop/TODO.md`
- `develop/spec/62-phase-11-delivery-plan.md`
- `develop/orchestration/tasks/P11-003-release-candidate-gate.md`
- `develop/orchestration/reports/P11-003-release-candidate-gate.md`
- `develop/STATE.md`

範囲外Fileの変更が必要な場合は実装を広げず、ReportへBlockerとして記録する。

## Constraints

- Production Code／Test／Release Automationを変更する場合はGPT-5.6 Luna High workerが実装し、Review前にCommitしない
- Fixed Release Candidateの検証中にSource Commitを別SHAへ読み替えない
- Full GateはCommitted Sourceを対象にし、未CommitのProduction／Skeleton／Release Metadataを混入させない
- External StateはRead-onlyで確認し、Tag、Release、Repository、Packagistを変更しない
- Documentation Website Publicationは実行しない
- Credential、Token、Private Key、Composer AuthenticationをRepository／Report／Logへ保存しない
- Existing immutable tagを移動または削除しない
- Release Gateで新しい仕様判断が必要になった場合は実装を止め、Decisionへ戻す
- Source／Test CommentへDecision／Spec／Task管理番号を書かない

## Acceptance Criteria

- [ ] Fixed Release Candidate SHA `e3df5576c7216cfe8bd9e10e12ee6795f7674088`がLocal／Remote main Historyに存在し、P11-003A Accepted Commitと一致する
- [ ] GitHub Actions CI Run `29511467022`とDocumentation Delivery Run `29511466795`が成功Evidenceとして記録される
- [ ] Composer、Mago、Full PHPUnit、Deptracが成功する
- [ ] 全6 Consumer／Installation／Worker／Framework Update Smokeが成功する
- [ ] Skeleton `1.1.0` Publication Dry RunがFixed Sourceから決定的なSplit Commitを生成する
- [ ] 通常／`--no-scripts` Create-projectがSkeleton／Framework `1.1.0`をLockし、Root `blackops`を検証する
- [ ] Website Unit／Check／Build／Public Artifact Guardが成功する
- [ ] Public API、Management ID、Credential、Generated State、Working Tree Guardが成功する
- [ ] CHANGELOG Known LimitationsとUPGRADE手順が実装Surfaceと一致する
- [ ] Framework／Skeleton `1.1.0` Tag、GitHub Release、Packagist Stableが未公開であることをRead-onlyで確認する
- [ ] P11-004 Publication Checklist、Success条件、Recovery条件がReportへ固定される
- [ ] ReportとSTATEが更新される

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
bash tests/Consumer/skeleton-publication.sh 1.1.0 e3df5576c7216cfe8bd9e10e12ee6795f7674088
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
! rg -n 'docs/internal|develop/|ghp_|gho_|github_pat_' docs/website/dist
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
! test -e examples/quickstart/composer.lock
! test -d examples/quickstart/vendor
git diff --check
```

External Read-only Evidenceは`gh`、Git Remote、Composer／Packagist Metadataを利用し、Command、Result、Checked AtをReportへ記録する。外部状態を変更するCommandは実行しない。

## Expected Report

`develop/orchestration/reports/P11-003-release-candidate-gate.md`へ次を記録する。

- Summary
- Fixed Release Candidate Evidence
- Local Full Gate Evidence
- GitHub Actions Evidence
- Split／Create-project Evidence
- Release Surface／Known Limitations Review
- Publication Preflight State
- P11-004 Publication Checklist and Recovery
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
