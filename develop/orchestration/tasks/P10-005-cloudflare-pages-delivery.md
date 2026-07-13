# P10-005: Cloudflare Pages Delivery

Status: In Progress

## Goal

検証済みDocumentation Artifactを、Pull Request Previewと`main` Productionへ安全にDirect UploadするGitHub Actions Workflowを提供する。

## In Scope

- Documentation Build／Deploy専用GitHub Actions Workflow
- miseと一致するNode.js／pnpm、Frozen Lockfile、Check／Build
- Wrangler Direct Uploadと`blackops-docs` Project境界
- Pull Request Preview Branchと`main` Production条件
- Fork Pull RequestのBuild継続／Deploy Safe Skip
- 最小権限、Concurrency、Artifact／Secret Guard
- Cloudflare Project／GitHub Secrets Setup Guide
- Workflow構文と可能な範囲のRemote Dry Verification
- Report／STATE更新

## Out of Scope

- Custom Domain／DNS変更
- Cloudflare Git Integration
- Cloudflare Account／API TokenのRepository保存
- Version別Documentation Deploy
- Framework Production Code変更

## Relevant Specifications and Decisions

- `develop/decisions/081-documentation-website-delivery-contract.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/58-phase-10-delivery-plan.md`

## Files Allowed to Change

- `.github/workflows/docs.yml`
- `docs/website/package.json`
- `docs/website/pnpm-lock.yaml`
- `docs/website/pnpm-workspace.yaml`
- `docs/website/README.md`
- `docs/internal/documentation-website.md`
- `docs/internal/README.md`
- `README.md`
- `develop/orchestration/reports/P10-005-cloudflare-pages-delivery.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は変更を広げず、ReportのBlockerとして返す。

## Constraints

- 原則としてGPT-5.6 Luna High workerが実装し、Review前にCommitしない
- Userは2026-07-13の回答「Y」により、Phase 10に限り、Model／Profileを明示できない現在利用可能なWorkerで進めることを承認した
- この承認はPhase 10以外へ自動継続しない
- Direct Uploadを使用し、Git Integration設定を前提にしない
- Production Deployは`main` Pushまたは明示的に許可した`main` Workflow Dispatchだけに限定する
- Pull Request CodeがProduction Environment／Secretを利用できないようにする
- Fork Pull RequestはCheck／Buildを成功可能にし、DeployだけSkipする
- Workflow Tokenは`contents: read`を基本とし、不要なWrite権限を付けない
- `CLOUDFLARE_API_TOKEN`と`CLOUDFLARE_ACCOUNT_ID`はGitHub Secretsからだけ読む
- Secret値、Account ID、Credential FileをLog／Artifact／Repositoryへ保存しない
- `docs/website/dist/`以外をUploadしない

## Acceptance Criteria

- [ ] Pull Requestと`main` Pushで同じCheck／Build Contractが実行される
- [ ] Pull Requestは非Production BranchとしてPreview Deployされる
- [ ] `main`だけがProduction Deployされる
- [ ] Fork Pull RequestはSecretなしでBuild成功可能でDeployをSkipする
- [ ] Workflowが最小権限とRef別Concurrencyを持つ
- [ ] Wranglerが`dist/`だけを`blackops-docs`へUploadする
- [ ] Setup GuideがProject作成、Secret登録、Local／CI検証、Rollbackを説明する
- [ ] WorkflowにLiteral Credential／Account IDがない

## Required Commands

```bash
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
python3 -c 'import pathlib, yaml; yaml.safe_load(pathlib.Path(".github/workflows/docs.yml").read_text())'
rg -n 'pull_request|push|main|wrangler pages deploy|blackops-docs|CLOUDFLARE_API_TOKEN|CLOUDFLARE_ACCOUNT_ID|concurrency|contents: read' .github/workflows/docs.yml
! rg -n 'ghp_|gho_|github_pat_|CF_API|api[_-]?token[[:space:]]*[:=][[:space:]]*[A-Za-z0-9]' .github/workflows/docs.yml docs/website docs/internal/documentation-website.md
! rg -n 'docs/internal|develop/' docs/website/dist
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Cloudflare ProjectまたはSecretsが未作成ならRemote Deployを推測で実行せず、Local／Workflow実装結果とExternal BlockerをReport／STATEへ記録する。

## Expected Report

`develop/orchestration/reports/P10-005-cloudflare-pages-delivery.md` に次を記録する。

- Summary
- Workflow Security Boundary
- Preview and Production Evidence
- External Configuration Status
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
