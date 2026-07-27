# D129: Documentation Website Publication

Status: Decided

## Context

D081／D093で延期していたDocumentation Website公開をUserが再開した。Cloudflare PagesはGit IntegrationではなくDirect Upload Projectとして作成し、GitHub Environment `docs-preview`／`docs-production`へそれぞれ`CLOUDFLARE_API_TOKEN`と`CLOUDFLARE_ACCOUNT_ID`が登録された。`docs-production`はDeployment Branch ruleで`main`だけを許可する。

D081で仮決定したProject名`blackops-docs`より、Repository／Productとの対応が明確な`blackops-php`を公開Project名として使用する。

## Decision

[DECISION]

1. Cloudflare Pages Direct Upload Project名を`blackops-php`、初期Production Hostを`https://blackops-php.pages.dev`とする。
2. `.github/workflows/docs.yml`はPreview／Productionとも`docs/website/dist/`だけをProject `blackops-php`へUploadする。
3. BlumeのCanonical Site URL、運用手順、Live Verification、Rollback手順を`blackops-php.pages.dev`へ同期する。
4. GitHub Environmentは`docs-preview`と`docs-production`を分離し、各EnvironmentのSecretからCloudflare Credentialを読む。
5. `docs-production`はDeployment Branch ruleで`main`だけを許可する。`docs-preview`は同一Repository Pull Requestに限定するWorkflow条件を維持する。
6. Secret値、Account ID、TokenをRepository、Artifact、Workflow Log、Task Reportへ記録しない。
7. 初回Production Deployは`main`の`workflow_dispatch`または`main` Pushから行い、Production URL、Installation Page、Blume Orama Search IndexをHTTPで確認する。Search Artifactの詳細はD130を正本とする。
8. Custom Domain、Cloudflare Git Integration、Version別Documentation Deployは今回扱わない。
9. D081のProject名`blackops-docs`とHost `blackops-docs.pages.dev`に関する決定だけを本Decisionで置き換える。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Product名とCloudflare Pages Project名が`blackops-php`で一致する。
- Preview／ProductionのCredentialとBranch境界を維持したまま、延期していた公開を開始できる。
- Repository内の旧Project名をWorkflowから除去し、誤ったProjectへのUploadを防止する。
- Custom Domainは`pages.dev`上の初回公開が検証できた後に独立して判断する。

[/CONSEQUENCES]

## References

- [D081 Documentation Website Delivery Contract](081-documentation-website-delivery-contract.md)
- [D093 Post Phase 10 Roadmap](093-post-phase-10-roadmap.md)
- [D116 Blume Documentation Site](116-blume-documentation-site.md)
- [Specification 57 Documentation Website Delivery Contract](../spec/57-documentation-website-delivery-contract.md)
- [D130 Blume Production Search Verification](130-blume-production-search-verification.md)
