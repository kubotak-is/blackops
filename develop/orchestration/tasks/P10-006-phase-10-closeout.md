# P10-006: Phase 10 Closeout

Status: Accepted / Phase Complete

## Goal

Documentation WebsiteのLocal／CI証拠を統合し、利用者向けSite、Repository Documentation、Credential-gated Publication Workflowを同期してPhase 10をCloseする。

## In Scope

- Website Content／Check／Build／Artifact Guardの再検証
- Full PHP Quality SuiteのRegression確認
- GitHub Actions Documentation Workflow結果確認
- Cloudflare Live PublicationをPhase 10のBlockerから分離する
- README、Guide、Internal Setup、TODO、Phase Plan同期
- Accessibility／Mobile Evidenceの最終確認
- Phase 10 Report／STATE Closeout

## Out of Scope

- Custom Domain
- Version別Archive／Selector
- Documentation全面翻訳
- Framework Public API変更
- 新機能実装
- Cloudflare Project／Credential設定
- Preview／Production DeployとLive Verification

## Relevant Specifications and Decisions

- `develop/decisions/081-documentation-website-delivery-contract.md`
- `develop/decisions/082-documentation-reader-experience.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/58-phase-10-delivery-plan.md`
- `develop/spec/59-documentation-reader-experience.md`
- `develop/decisions/093-post-phase-10-roadmap.md`
- `develop/spec/60-post-phase-10-roadmap.md`

## Files Allowed to Change

- `README.md`
- `docs/guide/**`
- `docs/internal/**`
- `docs/website/**`
- `.github/workflows/ci.yml`
- `.github/workflows/docs.yml`
- `develop/TODO.md`
- `develop/spec/README.md`
- `develop/spec/41-developer-experience-roadmap.md`
- `develop/spec/58-phase-10-delivery-plan.md`
- `develop/spec/59-documentation-reader-experience.md`
- `develop/decisions/081-documentation-website-delivery-contract.md`
- `develop/decisions/093-post-phase-10-roadmap.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/orchestration/tasks/P10-006-phase-10-closeout.md`
- `develop/orchestration/reports/P10-006-phase-10-closeout.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は変更を広げず、ReportのBlockerとして返す。

## Constraints

- GPT-5.6 Luna High workerが検証と必要な範囲の修正を行い、Review前にCommitしない
- External DeployはPhase 10 Acceptanceに含めず、Userが公開を再開した時点の明示Publication Taskで扱う
- CI Run IDとCommit SHAをReportへ記録する
- Credential、Token、Account IDをReport／STATEへ記録しない
- Closeout都合で新機能や大規模本文改稿を追加しない
- P10-005A／P10-005BのReader Experience Acceptanceを再検証してからCloseする
- P10-005E1／P10-005E2で実装済みとなったValidation AttributeとHTTP 422境界をSpec 59へ同期し、旧「未実装」記述を残さない

## Acceptance Criteria

- [x] Phase 10 Delivery PlanのPhase Acceptance Criteriaが証拠付きでSatisfiedである
- [x] Website Unit／Check／Buildが成功する
- [x] PHP Composer／Mago／PHPUnit／Deptracが成功する
- [x] GitHub Actions CIとDocumentation Artifact Buildが成功する
- [x] Credential未設定時にDeploy Stepだけが安全にSkipされる
- [x] Internal／Develop／CredentialがArtifactへ含まれない
- [x] README、Guide、Internal Setup、TODO、Phase Planが実装と一致する
- [x] ReportとSTATEがPhase 10 Completeを示す

## Required Commands

```bash
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'docs/internal|develop/|ghp_|gho_|github_pat_' docs/website/dist
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

GitHub Runは実際のRun IDをReportへ記録する。Live URL検証CommandはWebsite公開を再開した場合のPublication Taskで実行する。

## Expected Report

`develop/orchestration/reports/P10-006-phase-10-closeout.md` に次を記録する。

- Summary
- Local Quality Evidence
- CI and Deployment Evidence
- Deferred Publication Evidence
- Accessibility Evidence
- Changed Files
- Decisions and Assumptions
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
