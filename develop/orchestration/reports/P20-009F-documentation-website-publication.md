# P20-009F Documentation Website Publication Report

## Summary

D129／Specification 57のDocumentation Website Publication ContractへRepository設定を同期した。GitHub ActionsはPreview／Productionとも検証済み`docs/website/dist/`だけをCloudflare Pages Direct Uploadし、Project `blackops-php`へ分離されたEnvironment SecretとConcurrencyを使う。BlumeのCanonical Site URL、現行運用手順、D081の部分Supersession、Task／TODO／STATEを更新した。Remote DeployとProduction Live Verificationは実行していない。

## Changed Files

- `.github/workflows/docs.yml`
- `docs/website/blume.config.ts`
- `docs/internal/documentation-website.md`
- `develop/decisions/081-documentation-website-delivery-contract.md`（D129部分Supersessionを確認済みの現行差分）
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P20-009F-documentation-website-publication.md`
- `develop/orchestration/reports/P20-009F-documentation-website-publication.md`
- `develop/STATE.md`

既存Phase 20のWorking Tree差分、履歴Task／Report／Checkpoint、Website本文／Landing／Navigation／Theme、Framework Production Codeは変更していない。`docs/website/dist/`はBuild生成物でありGit管理対象外である。

## Decisions and Assumptions

- D129に従い、Cloudflare Pages Projectは`blackops-php`、初期Host／Blume Canonical URLは`https://blackops-php.pages.dev`とした。
- Previewは同一Repository Pull Requestだけを`docs-preview`でDeployし、Fork Pull RequestはBuild後DeployをSkipする。Productionは`main` Pushまたは`main`上のWorkflow Dispatchだけを`docs-production`でDeployする。
- Build jobはSecretを受け取らず、Deploy jobは同じArtifactの`docs/website/dist/`だけをUploadする。Token値、Account ID、Credential literalは記録していない。
- TODOのPublication項目はRemote Deploy／Live Verificationが未実行のため未完了のまま残し、Repository同期済み・Review Pendingであることを明記した。
- D081は履歴として旧Project名を保持し、先頭のD129 SupersessionでProject／Hostだけが置換されたことを示す。

## External Configuration Evidence

OrchestratorがGitHub API Metadataだけを確認した。`docs-preview`／`docs-production`に各2件のCloudflare Secretが存在し、`docs-production`にCustom Deployment Branch policy `main`がある。Required Reviewerは未設定で、main repository branch protectionも存在しない。Secret値、Account ID、Tokenは取得・表示・保存していない。Cloudflare Projectの存在、Credential有効性、Remote Deploy結果、Production URLは未検証である。

## Commands and Results

- `mise exec -- pnpm --dir docs/website run test` — PASS（62 tests）
- `mise exec -- pnpm --dir docs/website run check` — PASS（Blume 38 pages、0 errors／warnings／hints）
- `mise exec -- pnpm --dir docs/website run build` — PASS（39 pages、artifact／site guard PASS）。既存のchunk-size／route conflict warningあり
- `python3 -c 'import pathlib, yaml; yaml.safe_load(pathlib.Path(".github/workflows/docs.yml").read_text())'` — PASS
- Workflow trigger／permissions／Environment／Concurrency／Wrangler／Project名確認用`rg` — PASS
- Credential literal Guard、Artifact Internal Path Guard、旧Project名`blackops-docs` Guard — PASS
- `docker compose run --rm app mago format --check src tests` — PASS（All files are already formatted）
- PHP Management-ID Guard — PASS（該当なし）
- `git diff --check` — PASS
- Website test／check／buildは再実行していない。今回のCorrectionは`docs/internal/`、Report、STATEの運用記録だけで、Website Workflow／Blume設定／生成Contentを変更していないため、直前の62／38 pages／39 pages PASSが有効である。

Remote GitHub Workflow、Cloudflare Deploy、Production Browser Live Verificationは実行していない（Task制約）。Orchestratorの`curl -I https://blackops-php.pages.dev/` probeは2026-07-27 01:58 JST時点でHTTP 522だった。Hostは応答するがProduction公開未完了のEvidenceである。

## Acceptance Criteria

- [x] Preview／Production deployがProject `blackops-php`を指定する
- [x] Blume Canonical Site URLが`https://blackops-php.pages.dev`である
- [x] Specificationと現行運用手順がProject名、Host、Environment状態へ同期する
- [x] D081がProject名に関してD129で部分的に置き換えられたことを示す
- [x] Workflow syntax、Website test／check／build、Artifact GuardがPASSする
- [x] Workflow／Website／現行Spec／現行運用文書にCredential literalがない
- [x] Active configuration／contractにCloudflare Projectとしての`blackops-docs`が残らない
- [x] Report／TODO／STATEがReview Pendingへ同期する
- [x] Worker Commitがない

## Remaining Issues

初回Remote Deploy、Cloudflare Credential有効性、Production URLのHTTP 200、Installation Page、Pagefind Asset、Browser／SearchのLive Verificationは未完了である。Required Reviewerは未設定、main repository branch protectionも存在しないため、remote deploy前の追加保護として設定を推奨する。`https://blackops-php.pages.dev/`は2026-07-27 01:58 JST時点でHTTP 522を返しており、Hostは応答するがProduction公開未完了である。Cloudflare Project／Branch／Secretの実在はExternal Configuration Metadataでのみ確認済みで、推測で成功扱いにしていない。

## Suggested Next Action

Orchestrator Review後、User承認のもと`main`上でGitHub ActionsのProduction Workflowを実行し、`https://blackops-php.pages.dev/`、Installation Page、Pagefind AssetをHTTP確認する。同一Repository Pull Request Previewも必要に応じて検証し、Live Verification完了後にTODOを完了へ更新する。

## Orchestrator Review

OrchestratorはWorkflowのEvent／Ref条件、Environment分離、Artifact境界、Project名、Blume Canonical URL、D129／Specification／運用文書、GitHub Environment Metadataを独立Reviewした。YAML Parse、Project／Environment Guard、旧Project名Guard、`git diff --check`を再実行してPASSした。Required Reviewerとmain repository branch protectionが未設定であること、Production HostがHTTP 522で未公開であることをRemote Deploy前のRemaining Issueとして明示したうえで、Repository同期Task P20-009FをAcceptedとする。Commit／Push／Remote Deploy／Live Verificationは未実行である。
