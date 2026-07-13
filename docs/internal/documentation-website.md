# Documentation Website Delivery

BlackOpsの利用者向けDocumentation Websiteは、GitHub Actionsで検証した`docs/website/dist/`だけをCloudflare Pages Project `blackops-docs`へDirect Uploadする。Cloudflare Git Integrationは使用しない。

## Delivery Boundary

`.github/workflows/docs.yml`は`docs/guide/`から毎回Contentを生成し、Unit Test、Content／Astro Check、Static Build、公開境界検査を同じBuild jobで実行する。Build jobはCloudflare Secretを受け取らない。成功した`dist/`だけを1日保持のGitHub Actions Artifactへ格納し、Deploy jobはRepositoryをCheckoutせず、このArtifactだけを取得する。

| Event | Build | Deploy target | Secret environment |
| --- | --- | --- | --- |
| 同一RepositoryのPull Request | 実行 | `pr-<number>` Preview | `docs-preview` |
| ForkのPull Request | 実行 | Skip | なし |
| `main`へのPush | 実行 | Production (`main`) | `docs-production` |
| `main`からのWorkflow Dispatch | 実行 | Production (`main`) | `docs-production` |
| `main`以外からのWorkflow Dispatch | Skip | Skip | なし |

PreviewとProductionは別のEnvironment、Secret、Concurrency Groupを使う。Pull Request jobは`docs-production` Environmentを参照しない。WranglerはRepositoryで固定した`4.110.0`を、Secretを渡す前にDeploy jobへ導入する。

## Cloudflare Project Setup

External ConfigurationはRepositoryへ保存せず、Repository管理者がCloudflare Dashboardで行う。

1. Cloudflare DashboardのWorkers & PagesからPages Applicationを作成する。
2. Git Repository連携ではなくDirect Uploadを選択する。
3. Project名を`blackops-docs`、Production Branchを`main`にする。
4. 初期Hostが`blackops-docs.pages.dev`であることを確認する。

Direct Upload Projectは同じProjectのままGit Integrationへ切り替えられない。作成方法と制約は[Cloudflare Pages Direct Upload](https://developers.cloudflare.com/pages/get-started/direct-upload/)を参照する。Custom DomainとDNSはこのDeliveryの対象外である。

## API Token and GitHub Environments

CloudflareのCustom API TokenをPreview用とProduction用に分けて作成する。Permissionは対象AccountのCloudflare Pagesを編集できる最小範囲に限定し、不要なZone／Worker権限を付けない。Token作成手順は[Cloudflare API Token](https://developers.cloudflare.com/fundamentals/api/get-started/create-token/)を参照する。

GitHub RepositoryのSettings、Environmentsで次の2 Environmentを作成する。

- `docs-preview`: 同一RepositoryのPull Request Preview専用
- `docs-production`: `main` Production専用

各Environmentへ、対応するCloudflare CredentialをEnvironment Secretとして登録する。

- `CLOUDFLARE_API_TOKEN`: 対象Environment用のCustom API Token
- `CLOUDFLARE_ACCOUNT_ID`: `blackops-docs`を所有するAccount ID

`docs-production`にはDeployment Branch ruleで`main`だけを許可し、Required Reviewerを有効にする。これによりPull RequestがWorkflowを変更してもProduction Secret／Environmentへ進めない境界をGitHub側でも維持できる。Preview TokenとProduction Tokenを同じ値にしない。Token値とAccount IDをRepository File、Artifact、Workflow Logへ記録しない。

Secretが未登録の場合、WorkflowはCredentialの値を表示せずDeployだけをNotice付きでSkipする。ProjectとSecretの作成後にWorkflowを再実行する。

## Local Verification

Repository Rootで固定ToolchainとArtifactを検証する。

```bash
mise install
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
mise exec -- pnpm --dir docs/website exec wrangler --version
! rg -n 'docs/internal|develop/' docs/website/dist
```

Local検証ではDeployしない。Remote UploadはGitHub Actionsへ任せ、Project、Branch、Credential境界をWorkflowに固定する。

## CI and Live Verification

Pull Requestでは`Build documentation artifact`が成功した後、同一Repositoryの場合だけ`Deploy pull request preview`を確認する。Fork Pull RequestではBuild成功とPreview DeployのSkipを確認する。`main`では`Deploy main production`が成功してから、少なくとも次を確認する。

```bash
curl --fail --silent --show-error --location https://blackops-docs.pages.dev/
curl --fail --silent --show-error --location https://blackops-docs.pages.dev/getting-started/installation/
curl --fail --silent --show-error --location https://blackops-docs.pages.dev/pagefind/pagefind.js
```

さらにBrowserでMobile Navigation、Keyboard Navigation、Search、主要Assetを確認する。Production URLまたはPreview URLが作成されるまでは、Live Verificationを成功扱いにしない。

## Rollback and Credential Rotation

Production障害時はCloudflare DashboardでWorkers & Pages、`blackops-docs`、Deploymentsを開き、直前の正常なProduction DeploymentのMenuからRollbackする。Preview DeploymentはRollback先にできない。詳細は[Cloudflare Pages Rollbacks](https://developers.cloudflare.com/pages/configuration/rollbacks/)を参照する。

Token漏えいの疑いがある場合は、Cloudflareで該当Tokenを直ちに無効化し、新しい最小権限Tokenを作成して対応するGitHub Environment Secretだけを更新する。過去のWorkflow LogとArtifactも確認し、Preview用とProduction用を独立してRotationする。
