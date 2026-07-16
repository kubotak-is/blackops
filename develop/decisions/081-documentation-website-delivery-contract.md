# D081: Documentation Website Delivery Contract

Status: Partially Superseded by D093

## Supersession

D093はCloudflare Pages公開の時期だけを置き換える。Phase 10はRepository内のWebsite Content、Build、Search、Artifact Guard、Credential-gated Workflowを完了条件とし、Project作成、Credential設定、Preview／Production Deploy、Live VerificationはUserが公開を再開した時点の明示Publication Taskへ延期する。

本DecisionのMarkdown Single Source、Astro Starlight、公開Artifact境界、Cloudflare Direct Upload方式、Credential分離に関するContractは将来の公開経路として維持する。

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

A

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

？
FWの利用者向けのみでよいです。Laravelやその他言語のFWのサイトを参考にしてください。
FWへコントリビュートする人の目線は不要です。

[/ANSWER]

### Resolution

Recommendation Aの利用者向け部分だけを採用する。公開Websiteは日本語単一Localeで開始し、`docs/guide/`だけをContent Sourceとする。`docs/internal/`はRepository内のFramework実装者向け資料として維持するが、WebsiteのPage、Navigation、Search Indexへ含めない。

公開URLからSource Directory名の`guide`を外し、`/`をLanding、`/getting-started/...`、`/operations/...`、`/execution/...`、`/database/...`、`/reference/...`を利用者の目的別に構成する。LaravelとSymfonyのDocumentationと同様に、InstallからApplication構築、個別機能、Referenceへ進めるNavigationを採用する。

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

miseつかってpnpm

[/ANSWER]

### Resolution

Node.js 24 LTSとpnpm 11をRepository Rootの`mise.toml`で管理する。実装時点のPatch VersionをExact Pinし、`package.json`の`packageManager`とGitHub Actionsも同じpnpm Versionを使用する。`pnpm-lock.yaml`をCommitし、CIではFrozen Lockfileを要求する。

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

A

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

A

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

A

[/ANSWER]

### Resolution

Aを採用する。ただし「全既存文書」は公開対象である`docs/guide/`の利用者向け文書を意味する。Framework実装者向け本文をWebsiteへ移行しない。`installed-application-status.md`のようなAcceptance Evidence中心の文書は`docs/internal/`へ移すか公開対象から除外し、利用者に必要な現行Status／制約だけをGuideへ残す。

## Proposed Delivery Contract

回答がRecommendationどおりの場合、初期Contractは次となる。

```text
docs/guide/*.md       -- Framework利用者向けMarkdown Source of Truth
docs/internal/*.md    -- Repository内だけで読むFramework実装者向け資料
docs/website/         -- Astro Starlight Project、生成処理、Website Asset
  generated content  -- Build時だけ作成しGit管理しない

/                     -- Japanese landing page
/getting-started/...  -- Install、Quickstart、Directory Structure
/operations/...       -- Operation Authoring、Generator、Lifecycle
/execution/...        -- HTTP、Inline、Deferred、Worker
/database/...         -- Migration、Outcome、Retention
/reference/...        -- Configuration、Project CLI、Status
```

LocalとCIはmiseで固定したNode.js 24 LTS／pnpm 11とLockfileを使用し、Content生成、Astro Type Check、Static Build、Internal Link Checkを順に実行する。GitHub ActionsはPull RequestをPreview Branch、`main`をProductionとしてDirect Uploadする。

## Decision

[DECISION]

1. Website Contentの唯一の編集元は`docs/guide/`とする。
2. Build前に`docs/guide/`から未追跡のStarlight Contentを決定的に生成し、Frontmatter補完と先頭H1正規化を行う。
3. `docs/internal/`はRepository内のFramework実装者向け資料として維持するが、Website、Navigation、Search Indexへ公開しない。
4. Websiteは日本語単一Localeで開始し、Source Directory名ではなく利用者の目的別URLとNavigationを使う。
5. LandingからGetting Started、Operations、Execution、Database、Referenceへ到達できる構成とする。
6. JavaScript ToolchainはmiseでExact PinしたNode.js 24 LTSとpnpm 11を使用し、`pnpm-lock.yaml`をCommitする。
7. WebsiteはMain Branchの最新文書を公開し、`main`向けであることと最新Stable Versionを別表示する。初期Version別Archiveは提供しない。
8. GitHub Actionsで検証済みStatic Artifactを作り、Wrangler Direct UploadでPull Request Previewと`main` ProductionをCloudflare PagesへDeployする。
9. Cloudflare Pages Project名は`blackops-docs`、初期Hostは`blackops-docs.pages.dev`とし、Custom Domainは後続判断とする。
10. 初回公開では全利用者向けSource、Landing、Getting Started、Navigation、Linkを整備する。本文の全面改稿と内部Contributor Documentation公開は行わない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- D063の`docs/guide/`と`docs/internal/`をWebsiteへ読み込む決定は、Website公開対象について本Decisionが置き換える。両DirectoryのRepository上の役割と`docs/internal/`へのRenameは維持する。
- Generated ContentはBuild Artifactであり、Commitまたは直接編集しない。
- `docs/guide/`のPath変更は公開URLと内部Linkへ影響するため、生成ManifestとLink Checkで検証する。
- `docs/internal/`の内容はCloudflare Pages Artifactへ含めず、漏えいを機械検証する。
- Localは`mise install`後にRepositoryで固定されたpnpm Commandを使用する。CIは同じNode／pnpm VersionとFrozen Lockfileを使う。
- Pull RequestがForkから作成されCloudflare Secretへアクセスできない場合もBuild／Link Checkは実行し、Preview DeployだけをSkipする。
- Main Branch DocumentとStable Releaseの差を隠さず、Website上で明示する。
- Initial Siteは利用者向けであり、Contribution Guide、Task Report、Architecture InternalsをNavigationへ含めない。

[/CONSEQUENCES]

## References

- [D093 Post Phase 10 Roadmap](093-post-phase-10-roadmap.md)
- [D063 Developer Experience Roadmap](063-developer-experience-roadmap.md)
- [D077 Implementation Worker Model Upgrade](077-implementation-worker-model-upgrade.md)
- [Developer Experience Roadmap](../spec/41-developer-experience-roadmap.md)
- [Laravel Documentation](https://laravel.com/docs/12.x)
- [Symfony Documentation](https://symfony.com/doc/current/index.html)
- [Node.js Releases](https://nodejs.org/en/about/previous-releases)
- [mise Node.js](https://mise.jdx.dev/lang/node.html)
- [pnpm Continuous Integration](https://pnpm.io/continuous-integration)
- [Starlight Project Structure](https://starlight.astro.build/guides/project-structure/)
- [Starlight Authoring Content](https://starlight.astro.build/guides/authoring-content/)
- [Cloudflare Pages Build Configuration](https://developers.cloudflare.com/pages/configuration/build-configuration/)
- [Cloudflare Pages Direct Upload](https://developers.cloudflare.com/pages/get-started/direct-upload/)
- [Cloudflare Pages Direct Upload with CI](https://developers.cloudflare.com/pages/how-to/use-direct-upload-with-continuous-integration/)
