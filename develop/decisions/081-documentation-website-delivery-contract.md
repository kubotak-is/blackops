# D081: Documentation Website Delivery Contract

Status: In Discussion

## Context

Phase 10では、Repository内の`docs/guide/`と`docs/internal/`をMarkdownの正本とし、`docs/website/`のAstro Starlight Projectから静的Documentation WebsiteをBuildする。

D063でAstro Starlight、Markdown Single Source、`docs/internals/`から`docs/internal/`への移行、Cloudflare Pagesへの静的Deployまでは決定済みである。一方、既存MarkdownはRepository上で直接読める通常のMarkdownとして作られており、Starlight標準Contentが要求するFrontmatterやPage Titleとの適合方法、公開URL、Version表示、Package Manager、Cloudflare Pages連携方式はまだ決まっていない。

このDecisionでは、Production Taskを分割する前に、WebsiteのBuild／Publication Contractを確定する。

## Question 1: Markdown Adaptation

既存MarkdownをStarlight Contentへどのように適合させるか。

### Options

- A: Build前に`docs/guide/`と`docs/internal/`から未追跡の生成Contentを作り、Frontmatter補完と先頭H1の正規化を決定的に行う
- B: 正本MarkdownへStarlight用Frontmatterを直接追加し、Custom Content LoaderでRepository上のDirectoryを直接読む
- C: Website用Contentを`docs/website/src/content/docs/`へCopyしてCommitし、手動で同期する

### Recommendation

Aを推奨する。

既存MarkdownはRepository、GitHub、Editorでも単独で読みやすい先頭H1を維持する。Website Buildでは標準のStarlight Content Layoutへ一時生成し、PathからSectionとSlug、先頭H1からTitleを補う。生成先はGit管理せず、Source Hashまたは再生成後Diffで決定性を検証する。

これにより、編集対象は常に`docs/guide/`と`docs/internal/`だけになり、Website用の二重管理を避けられる。Custom LoaderへStarlight固有の変換責務を持ち込むより、標準Routing／Sidebar／Searchとの互換性も保ちやすい。

[ANSWER]



[/ANSWER]

## Question 2: URL and Language

初期WebsiteのURL構造と表示言語をどうするか。

### Options

- A: 日本語単一Localeで開始し、`/`をLanding、利用者向けを`/guide/...`、実装者向けを`/internal/...`に分ける
- B: 日本語単一Localeで開始し、利用者向け文書をRoot直下、実装者向けだけを`/internal/...`に置く
- C: 最初から日本語／英語のLocale別URLを用意する

### Recommendation

Aを推奨する。

Reader層とSource DirectoryがURLでも一致するため、Repository MarkdownとWebsiteの対応を追いやすい。Starlight UIは日本語にし、現行文書内の英語／日本語は初回移行で一律翻訳しない。英語版は翻訳対象と運用責任が決まってからLocaleとして追加する。

[ANSWER]



[/ANSWER]

## Question 3: JavaScript Toolchain

`docs/website/`のPackage ManagerとRuntime固定をどうするか。

### Options

- A: Node.js LTSとnpmを使用し、`package-lock.json`と`packageManager`をCommitする
- B: Node.js LTSとpnpmを使用し、Corepackと`pnpm-lock.yaml`で固定する
- C: Bunと`bun.lock`を使用する

### Recommendation

Aを推奨する。

このRepositoryには既存のJavaScript Toolchainがなく、WebsiteはAstroの静的Buildだけである。追加のPackage Manager Bootstrapを必要としないnpmを初期Baselineにし、Local、GitHub Actions、CloudflareのBuild環境で同じLockfileを使う。Node.jsのMajor VersionはWorkflowとRepository Metadataで一致させる。

[ANSWER]



[/ANSWER]

## Question 4: Documentation Version Display

Main Branchの文書とPackagistで公開済みの安定版をどのように見せるか。

### Options

- A: WebsiteはMain Branchの最新Documentを公開し、Header／Bannerで`main`向けであることと最新Stable Versionを別表示する
- B: Websiteは最新Stable Tagだけを公開し、Main Branchの文書はRepositoryでのみ読む
- C: 初回からVersionごとのWebsite BuildとVersion Selectorを提供する

### Recommendation

Aを推奨する。

現段階では一つのSourceを継続更新し、Websiteの内容が未Release変更を含み得ることを明示する。Install例にはStable Versionを示す。Version別ArchiveとSelectorは、互換性を保った複数Releaseを並行保守する段階で追加する。

[ANSWER]



[/ANSWER]

## Question 5: Cloudflare Pages Integration

Preview／Production Deployをどこから制御するか。

### Options

- A: GitHub ActionsでBuildとTestを実行し、Wrangler Direct UploadでPull Request Previewと`main` ProductionをDeployする
- B: Cloudflare PagesのGit IntegrationにRepositoryを接続し、Cloudflare側でPreviewとProductionをBuildする
- C: Phase 10ではBuild Artifactまで整備し、Cloudflare Deployは後続Phaseへ分離する

### Recommendation

Aを推奨する。

Repository CIで使用したNode Version、Install、Build、Link CheckをそのままDeploy Gateにできる。Productionは`main`だけ、Pull Requestは非Production BranchとしてDeployし、WorkflowはCloudflare Account IDと最小権限API TokenをGitHub Secretsから受け取る。

Direct Upload Projectは後から同じProjectのままGit Integrationへ切り替えられないため、Bを望む場合はProject作成前に決める必要がある。

Project名は`blackops-docs`、初回公開先は`blackops-docs.pages.dev`を推奨する。Custom Domainは所有DomainとDNS運用が決まるまで後続設定とする。

[ANSWER]



[/ANSWER]

## Question 6: Initial Content Scope

初回公開時に既存文書をどこまで再編集するか。

### Options

- A: 全既存文書を移行し、Landing、Getting Started、Navigation Metadata、壊れたLinkだけを整備する。本文の全面改稿は分離する
- B: 利用者向け文書を全面改稿してからWebsiteを公開し、実装者向け文書は後から追加する
- C: 既存文書をそのまま全件表示し、LandingやNavigation整理も後続にする

### Recommendation

Aを推奨する。

Phase 10の主目的をSingle Source Buildと公開経路の確立に置きながら、初見の利用者がInstall、Quickstart、Project CLIへ到達できる入口は用意する。古いAPI説明や管理番号参照など、移行時に判明した本文課題は一覧化し、公開を止める重大な誤りだけ同じPhaseで修正する。

[ANSWER]



[/ANSWER]

## Proposed Delivery Contract

回答がRecommendationどおりの場合、初期Contractは次となる。

```text
docs/guide/*.md       -- Framework利用者向けMarkdown Source of Truth
docs/internal/*.md    -- Framework実装者向けMarkdown Source of Truth
docs/website/         -- Astro Starlight Project、生成処理、Website Asset
  generated content  -- Build時だけ作成しGit管理しない

/                    -- Japanese landing page
/guide/...           -- User guide
/internal/...        -- Framework internals
```

LocalとCIはLockfileどおりにInstallし、Content生成、Astro Type Check、Static Build、Internal Link Checkを順に実行する。GitHub ActionsはPull RequestをPreview Branch、`main`をProductionとしてDirect Uploadする。

## Decision

[DECISION]

User回答後に確定する。

[/DECISION]

## Consequences

[CONSEQUENCES]

User回答後に確定する。

[/CONSEQUENCES]

## References

- [D063 Developer Experience Roadmap](063-developer-experience-roadmap.md)
- [D077 Implementation Worker Model Upgrade](077-implementation-worker-model-upgrade.md)
- [Developer Experience Roadmap](../spec/41-developer-experience-roadmap.md)
- [Starlight Project Structure](https://starlight.astro.build/guides/project-structure/)
- [Starlight Authoring Content](https://starlight.astro.build/guides/authoring-content/)
- [Cloudflare Pages Build Configuration](https://developers.cloudflare.com/pages/configuration/build-configuration/)
- [Cloudflare Pages Direct Upload](https://developers.cloudflare.com/pages/get-started/direct-upload/)
- [Cloudflare Pages Direct Upload with CI](https://developers.cloudflare.com/pages/how-to/use-direct-upload-with-continuous-integration/)
