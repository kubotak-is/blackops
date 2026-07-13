# P10-005 Cloudflare Pages Delivery Report

## Summary

Pull Requestと`main`で同じDocumentation Test／Check／Buildを実行し、検証済み`docs/website/dist/`だけをCloudflare Pages Project `blackops-docs`へWrangler Direct UploadするGitHub Actions Workflowを追加した。

同一RepositoryのPull Requestは`pr-<number>` BranchへPreview Deployし、Fork Pull RequestはSecretなしでBuildとArtifact検証まで実行してDeployだけをSkipする。Production Deployは`main` Pushまたは`main`からの明示的なWorkflow Dispatchだけに限定した。Cloudflare ProjectとGitHub Environment SecretはExternal Configurationであり、このTaskでは作成もRemote Deployも行っていない。

## Workflow Security Boundary

- Workflow Tokenは`contents: read`だけを持ち、Checkout時のCredential永続化を無効にした。
- Build jobはCloudflare Secretを参照せず、固定したNode.js 24.18.0／pnpm 11.12.0とFrozen LockfileでTest、Check、Build、公開境界検査を実行する。
- Frozen Install直後、Secretを持たないBuild jobでLockfileから実行するWranglerのVersionがWorkflow固定値`4.110.0`と一致することを確認する。`package.json`／LockfileとWorkflow EnvironmentのVersion DriftはDeploy前にFail-fastする。
- Deploy jobはRepositoryをCheckoutせず、Build jobが生成した1日保持の`dist/` ArtifactだけをDownloadする。Artifact以外のSource、Lockfile、Credential FileはUploadしない。
- Previewは`docs-preview`、Productionは`docs-production` GitHub Environmentを使い、同じ名前のSecretでも値と承認境界を分離する。Pull Request jobはProduction Environmentを参照しない。
- Same-repository Pull RequestだけがPreview Deploy条件を満たす。Fork Pull RequestはBuild job内でSkip理由をNoticeし、Deploy jobは起動しない。
- Production jobは`push`／`workflow_dispatch`の両方で`refs/heads/main`を明示条件にする。Setup Guideは`docs-production` EnvironmentのDeployment Branchを`main`に限定し、Required Reviewerを有効にするよう要求する。
- `CLOUDFLARE_API_TOKEN`と`CLOUDFLARE_ACCOUNT_ID`は対応するGitHub Environment SecretからだけStep Environmentへ渡す。値をOutput、Log、Artifact、Repositoryへ保存しない。
- Secretが不足する場合は値を表示せず`available=false`としてDeployだけをNotice付きでSkipする。
- Build、PR Preview、Productionはそれぞれ別Concurrency Groupを持つ。同じPull Requestまたは`main`の古いJobはCancelするが、PreviewとProductionは互いにCancelしない。
- Deploy RuntimeはNode.js 24.18.0、Wrangler 4.110.0へ固定した。WranglerはCredentialを注入する前のStepで導入し、Deploy StepだけがSecretを受け取る。

## Preview and Production Evidence

Workflow静的検証で次を確認した。

| Path | Trigger／condition | Pages branch | Artifact |
| --- | --- | --- | --- |
| Preview | Same-repository `pull_request` | `pr-${{ github.event.pull_request.number }}` | `docs/website/dist/` |
| Fork PR | `pull_request` Buildのみ | Deploy Skip | `docs/website/dist/`を検証／作成 |
| Production | `main` Push | `main` | `docs/website/dist/` |
| Manual Production | `main` Workflow Dispatch | `main` | `docs/website/dist/` |
| Non-main Dispatch | Build／Deploy Skip | なし | なし |

`wrangler pages deploy --help`で、4.110.0がDirectory Position、`--project-name`、`--branch`を受け付けることを確認した。WorkflowはPreview／Productionとも`wrangler pages deploy docs/website/dist --project-name=blackops-docs`を使用し、Production Branchだけを`main`にする。

Remote Workflow Run、Preview URL、Production URLはProject／Secrets未確認のため、このTaskではEvidenceを生成していない。P10-006で実際のGitHub Actions Runと`blackops-docs.pages.dev`を検証する。

## External Configuration Status

Cloudflare側は未実施／未確認である。GitHub側はOrchestratorが2026-07-13にGitHub APIで確認し、`docs-preview`／`docs-production` Environmentが存在せず、Environment Endpointが404を返した。このため必要なEnvironment Secretも未登録である。

- Cloudflare Direct Upload Project `blackops-docs`の作成
- Production Branch `main`と初期Host `blackops-docs.pages.dev`の確認
- Preview用／Production用の別Cloudflare Custom API Token作成
- GitHub Environments `docs-preview`／`docs-production`の作成（GitHub APIで未作成を確認済み）
- 各Environmentへの`CLOUDFLARE_API_TOKEN`／`CLOUDFLARE_ACCOUNT_ID`登録（Environment未作成のため未登録）
- `docs-production`の`main` Deployment Branch ruleとRequired Reviewer設定
- Pull Request Preview、`main` Production、RollbackのRemote Smoke

CredentialまたはProjectの存在を推測せず、Remote Deploymentは実行していない。Setup手順、Local／CI検証、Rollback、Token Rotationは`docs/internal/documentation-website.md`へ記載した。

## Changed Files

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

Task Packetへの`docs/website/pnpm-workspace.yaml`追加はOrchestratorが実施した既存変更であり、Workerはその許可後にBuild Script allowlistを更新した。

## Decisions and Assumptions

- 2026-07-13のnpm Registry Live QueryでWrangler latestを`4.110.0`と確認し、RangeではなくExact VersionをDev DependencyとWorkflowへ固定した。
- Wrangler追加時、pnpm 11.12.0は新しいTransitive Dependency `sharp@0.34.5`と`workerd@1.20260708.1`のBuild Scriptを未判断として拒否した。OrchestratorがTask Packetへ`pnpm-workspace.yaml`を追加した後、既存`esbuild`を維持し、Wranglerの実行に必要な`sharp`と`workerd`だけを`allowBuilds: true`にした。Frozen Installで両Postinstall成功を確認した。
- GitHub Actions Artifact ActionはOfficial Repositoryの現行Usageに合わせ、Upload v7／Download v8を使用した。Repositoryの既存CIに合わせてCheckout v7、mise-action v4を使用する。
- Deploy jobでPull Request SourceをCheckoutまたは実行しない。Exact WranglerはArtifactと分離してCredential注入前にGlobal Installする。
- Cloudflare側のProduction判定はProjectのProduction Branch設定にも依存するため、Setup Guideで`main`を必須にした。Workflow側でも`--branch=main`とEvent／Ref条件を重ねる。
- Same-repository Pull RequestのContributorは信頼済みであることを前提にPreview Tokenへアクセスできる。Productionは別Token、Environment Branch rule、Required Reviewerで追加防御する。
- Custom Domain、DNS、Cloudflare Git Integrationは対象外である。

## Commands and Results

```text
mise exec -- npm view wrangler@latest version dist-tags --json
Result: Live npm Registry returned latest 4.110.0.

mise exec -- pnpm --dir docs/website install --frozen-lockfile
Result: Succeeded with pnpm 11.12.0; sharp and workerd postinstall scripts completed.

mise exec -- pnpm --dir docs/website run test
Result: 16 tests / 16 passed / 0 failed.

mise exec -- pnpm --dir docs/website run check
Result: Content validation and determinism passed; Astro check 14 files / 0 errors / 0 warnings / 0 hints.

mise exec -- pnpm --dir docs/website run build
Result: 19 HTML files (18 public pages plus 404), Pagefind, sitemap, artifact and site checks passed.

mise exec -- pnpm --dir docs/website exec wrangler --version
Result: 4.110.0.

env XDG_CONFIG_HOME=/tmp/blackops-wrangler bash -lc 'test "$(mise exec -- pnpm --dir docs/website exec wrangler --version)" = "4.110.0"'
Result: Succeeded; package／lockから実行したWranglerとWorkflow固定値が一致した。Local sandbox外へのWrangler Log書込を避けるためTemporary XDG_CONFIG_HOMEを使用した。

env XDG_CONFIG_HOME=/tmp/blackops-wrangler mise exec -- pnpm --dir docs/website exec wrangler pages deploy --help
Result: Succeeded; directory positional argument, --project-name, and --branch are supported. Temporary XDG_CONFIG_HOME avoided writing Wrangler debug logs outside the writable workspace.

python3 -c 'import pathlib, yaml; yaml.safe_load(pathlib.Path(".github/workflows/docs.yml").read_text())'
Result: Parsed successfully.

rg -n 'pull_request|push|main|wrangler pages deploy|blackops-docs|CLOUDFLARE_API_TOKEN|CLOUDFLARE_ACCOUNT_ID|concurrency|contents: read' .github/workflows/docs.yml
Result: Expected trigger, branch, deploy, project, secret, concurrency, and permission references were found.

! rg -n 'ghp_|gho_|github_pat_|CF_API|api[_-]?token[[:space:]]*[:=][[:space:]]*[A-Za-z0-9]' .github/workflows/docs.yml docs/website docs/internal/documentation-website.md
Result: Passed; no literal credential pattern found.

! rg -n 'docs/internal|develop/' docs/website/dist
Result: Passed; no non-public path found.

docker compose run --rm app mago format --check src tests
Result: All files are already formatted. Initial sandbox execution could not access /var/run/docker.sock; approved WSL2 Docker execution succeeded.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: Passed; no management identifier found in PHP comments or docblocks.

git diff --check
Result: Passed with no whitespace errors.
```

Remote Deploy commandはExternal Configuration未確認のため未実行である。

## Acceptance Criteria

- [x] Pull Requestと`main` Pushで同じTest／Check／Build Contractが実行される
- [x] Same-repository Pull Requestが`pr-<number>`非Production BranchへPreview DeployするWorkflow条件を持つ
- [x] `main` Pushまたは`main` Workflow DispatchだけがProduction Deploy条件を満たす
- [x] Fork Pull RequestはSecretなしでBuild成功可能で、Deploy jobをSkipする
- [x] Workflowは`contents: read`とBuild／Preview／Production別Concurrencyを持つ
- [x] Wranglerは`docs/website/dist/`だけを`blackops-docs`へUploadする
- [x] Setup GuideはProject作成、Environment Secret登録、Local／CI検証、Rollback、Rotationを説明する
- [x] Workflowと関連文書にLiteral Credential／Account IDがない
- [x] Build jobがLockfile由来のWranglerとWorkflow固定値のVersion DriftをDeploy前に拒否する
- [ ] Remote Pull Request Previewが成功する
- [ ] Remote `main` Production DeployとLive Verificationが成功する

Repository内で検証可能なAcceptance Criteriaは満たした。Remote 2項目はExternal Configuration完了後のP10-006 EvidenceでCloseする。

## Remaining Issues

- Cloudflare Project／TokenのExternal Configuration状況は未確認である。GitHub APIでは`docs-preview`／`docs-production` Environment未作成とEnvironment Secret未登録を確認した。
- GitHub ActionsによるWorkflowのRemote受理とSafe Skip結果はまだない。
- Preview URL、Production URL、Search／AssetのLive Verification、Rollback Smokeは未実施である。
- Wrangler updateはDependency更新Taskとして、npm Registry、Release Note、Lockfile、Build Script allowlistを再確認する必要がある。

## Suggested Next Action

1. OrchestratorがWorkflowのEvent／Ref／Environment／Secret／Artifact境界をReviewする。
2. Repository内変更をTask単位でCommit／Pushし、Secret未設定時のSafe SkipをGitHub Actions Runで確認する。
3. User所有のCloudflare Project、Token、GitHub Environment設定が必要な時点で停止して指示を確認する。
4. 設定完了後、同一Repository Pull Request Previewと`main` Productionを実行し、P10-006でRun URL、Deployment URL、Live HTTP／Browser EvidenceをCloseする。

## Orchestrator Review

Orchestrator Reviewで、`package.json`／LockfileのWranglerとWorkflowの`WRANGLER_VERSION`が独立して更新され得る点を確認した。Build jobのFrozen Install直後に`pnpm --dir docs/website exec wrangler --version`を実行し、`${WRANGLER_VERSION}`との完全一致を要求するStepを追加した。これはCloudflare Secretを一切持たない段階で実行され、Version DriftがあるArtifactをDeploy jobへ渡さない。

同ReviewでGitHub APIによるExternal Configuration確認を実施した。`docs-preview`／`docs-production` Environment Endpointはいずれも404であり、Environmentと対応Secretは未作成／未登録である。WorkflowのRemote受理とSafe Skipを確認するには、まずRepository変更をCommit／Pushする。実DeployにはEnvironmentとSecretのUser所有設定が必要である。
