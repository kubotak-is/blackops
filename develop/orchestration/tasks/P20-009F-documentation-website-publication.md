# P20-009F: Documentation Website Publication

Status: Accepted

## Goal

Cloudflare Pages Project `blackops-php`へ、検証済みBlume static artifactをGitHub Actionsから安全にDirect Uploadできる状態へ同期し、初回Production Deployの実行前Gateを完了する。

## In Scope

- D129とSpecification 57に従うProject名／Production Hostの同期
- Preview／Production Workflowの`blackops-php` Direct Upload
- Blume Canonical Site URLの同期
- Cloudflare／GitHub Environment Setup、Current Status、Live Verification、Rollback手順の現行化
- Website Unit／Check／Build、Workflow syntax、Artifact／Secret Guard
- Active configurationから旧Project名`blackops-docs`が除去されたことの検証
- Report／TODO／STATE更新

## Out of Scope

- Custom Domain／DNS
- Cloudflare Git Integration
- Secret値、Account ID、Tokenの取得またはRepository保存
- Website本文／Landing／Navigation／Themeの変更
- Framework Production Code
- `main`へのCommit／PushとRemote Workflow実行
- Historical Task Report／Checkpointの書き換え

## Relevant Specifications and Decisions

- `develop/decisions/081-documentation-website-delivery-contract.md`
- `develop/decisions/093-post-phase-10-roadmap.md`
- `develop/decisions/116-blume-documentation-site.md`
- `develop/decisions/129-documentation-website-publication.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/83-blume-documentation-experience.md`

## Files Allowed to Change

- `.github/workflows/docs.yml`
- `docs/website/blume.config.ts`
- `docs/internal/documentation-website.md`
- `develop/decisions/081-documentation-website-delivery-contract.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P20-009F-documentation-website-publication.md`
- `develop/orchestration/reports/P20-009F-documentation-website-publication.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は変更を広げず、ReportのBlockerとして返す。

## External Configuration Evidence

OrchestratorがSecret値を取得せずGitHub APIのMetadataだけを確認した。

- `docs-preview`: `CLOUDFLARE_API_TOKEN`／`CLOUDFLARE_ACCOUNT_ID`あり
- `docs-production`: `CLOUDFLARE_API_TOKEN`／`CLOUDFLARE_ACCOUNT_ID`あり
- `docs-production`: Custom Deployment Branch policy `main`あり
- `docs-preview`: Deployment Branch policyなし。Workflowのsame-repository Pull Request条件を使用

Cloudflare Projectの存在、Production Branch、Credential有効性はRemote Deployで確認する。推測で成功扱いにしない。

## Constraints

- GPT-5.6 Luna High workerが実装し、Review前にCommitしない
- 既存Phase 20 Working Tree差分を保持し、許可Fileだけを変更する
- Preview／ProductionのEnvironment、Secret、Concurrency分離を維持する
- Fork Pull RequestはBuild可能、Deploy Skipを維持する
- Productionは`main` Pushまたは`main`上のWorkflow Dispatchだけに限定する
- `docs/website/dist/`以外をUploadしない
- Temporary test directory prefixの`blackops-docs-test-*`／`blackops-docs-check-*`はCloudflare Project名ではないため変更しない
- Historical Task Packet、Report、STATE Checkpoint内の旧名は履歴として変更しない

## Acceptance Criteria

- [ ] Preview／Production deployがProject `blackops-php`を指定する
- [ ] Blume Canonical Site URLが`https://blackops-php.pages.dev`である
- [ ] Specificationと現行運用手順がProject名、Host、Environment状態へ同期する
- [ ] D081がProject名に関してD129で部分的に置き換えられたことを示す
- [ ] Workflow syntax、Website test／check／build、Artifact GuardがPASSする
- [ ] Workflow／Website／現行Spec／現行運用文書にCredential literalがない
- [ ] Active configuration／contractにCloudflare Projectとしての`blackops-docs`が残らない
- [ ] Report／TODO／STATEがReview Pendingへ同期する
- [ ] Worker Commitがない

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
python3 -c 'import pathlib, yaml; yaml.safe_load(pathlib.Path(".github/workflows/docs.yml").read_text())'
rg -n 'pull_request|push|main|wrangler pages deploy|blackops-php|CLOUDFLARE_API_TOKEN|CLOUDFLARE_ACCOUNT_ID|concurrency|contents: read' .github/workflows/docs.yml
! rg -n 'ghp_|gho_|github_pat_|CF_API|api[_-]?token[[:space:]]*[:=][[:space:]]*[A-Za-z0-9]' .github/workflows/docs.yml docs/website docs/internal/documentation-website.md
! rg -n 'docs/internal|develop/' docs/website/dist
! rg -n 'blackops-docs(?:\.pages\.dev)?' .github/workflows/docs.yml docs/website/blume.config.ts docs/internal/documentation-website.md develop/spec/57-documentation-website-delivery-contract.md
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P20-009F-documentation-website-publication.md`に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- External Configuration Evidence
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
