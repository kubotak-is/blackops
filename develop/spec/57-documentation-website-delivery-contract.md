# Documentation Website Delivery Contract

## Scope

BlackOpsの公開Documentation Websiteは、Framework利用者がInstallからOperation実装、HTTP／Deferred実行、Database／運用機能まで辿れる日本語の静的Siteとする。Framework Contributor、内部Architecture、Task／Acceptance Evidenceは公開対象にしない。

## Content Ownership

Directoryの責務は次とする。

```text
docs/guide/     公開WebsiteのMarkdown Source of Truth
docs/internal/  Repository内で読むFramework実装者向け資料
docs/website/   Astro Starlight Project、生成処理、Website固有Asset
```

公開本文は`docs/guide/`だけを編集する。Website用Markdownを手動で複製または編集しない。`docs/internal/`、`develop/`、Task Report、Decision、SpecificationをPage、Navigation、Search Index、Static Artifactへ含めない。

`docs/internals/`は`docs/internal/`へRenameする。Repository内の有効なPath／Link、AGENTS、README、Specification、Task Packet、Report、CI参照を同じ変更単位で同期する。旧Directoryは残さない。Renameの判断経緯を説明する文書内の旧Path表記だけは履歴として保持できる。

## Generated Content

Starlightが読むContentはBuild前に`docs/guide/`から生成する。

- Source Relative Pathまたは明示Metadataから公開Slugを決定する
- 先頭H1をPage Titleとして取得し、Starlight Frontmatterへ補う
- Websiteで重複する先頭H1を生成本文から除く
- SourceのCode Fence、Mermaid、Table、Link、Heading Hierarchyを保持する
- Source外Path参照、重複Slug、Title不在、不正Frontmatter、壊れた内部LinkをFail-fastする
- 同じSourceとConfigurationからbyte-for-byte同じContent Manifestを生成する
- 生成DirectoryをGit管理せず、Build開始時に全置換する

生成処理はSourceを変更しない。生成物へ手作業を加えても次回Buildで失われることをREADMEに明記する。

## Public Information Architecture

Websiteは日本語単一Localeで開始する。Source Directory名の`guide`はURLへ露出させない。

```text
/                     Landing、Install CTA、主要Conceptへの入口
/getting-started/...  Install、Quickstart、Directory Structure、Local Runtime
/operations/...       Operation、Value、Outcome、Generator、Lifecycle
/execution/...        HTTP、Inline、Deferred、Worker、Context
/database/...         Migration、Outcome Retrieval、Retention
/reference/...        Configuration、Project CLI、Current Status／制約
```

Navigationは利用者がApplicationを構築する順序を優先する。LandingとGetting Startedから、最小のApplication作成、最初のOperation、Local実行まで連続して辿れるようにする。各Pageは現在のPublic APIだけで完結し、Internal Namespace、Orchestration ID、Acceptance Hash、Framework実装手順を利用者向け説明として要求しない。

`docs/internal/installed-application-status.md`はGuideから移動したAcceptance EvidenceとしてRepository内に維持する。`mvp-status.md`は利用者に影響する実装済み機能と既知制約へ整理し、Contributor向け証拠は公開本文から除く。

## Website Presentation

Astro Starlightの標準Search、Sidebar、Table of Contents、Code Highlight、Dark Mode、Mobile Navigation、SEO Metadata、Skip Linkを使用する。独自UIはBlackOps Brand、Landing、Version Noticeなど必要最小限にする。

WebsiteはMain Branchの最新Documentを公開する。Headerまたは全Pageで認識できる位置に次を表示する。

- Document Channel: `main`
- Latest Stable: 初期値`1.0.0`
- Main Documentが未Release変更を含み得る旨

初期VersionではVersion別Archive／Selectorを提供しない。Install CommandはStable Packageを取得する形式とし、Main Branch Sourceの利用を暗黙に勧めない。

## JavaScript Toolchain

Repository Rootの`mise.toml`でNode.js 24 LTSとpnpm 11のPatch VersionをExact Pinする。`docs/website/package.json`の`packageManager`も同じpnpm Versionを指定し、`pnpm-lock.yaml`をCommitする。

Localの標準入口は次とする。

```bash
mise install
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
```

少なくとも次のScriptを提供する。

- `content:generate`: Sourceから未追跡Starlight ContentとManifestを再生成する
- `content:check`: 重複Slug、Title、内部Link、公開対象外Path、生成決定性を検証する
- `check`: Content CheckとAstro Type Checkを実行する
- `build`: Contentを再生成して静的Siteを`dist/`へBuildする
- `dev`: Contentを生成してLocal Development Serverを起動する

CIはLockfileを更新せず、miseと同じNode／pnpm MajorおよびExact pnpm Versionを使う。Package更新はLockfileを含む明示Commitで行う。

## Static Artifact Boundary

`docs/website/dist/`だけをCloudflare PagesへDeployする。Artifactには次を含めない。

- `docs/internal/`
- `develop/`
- `.git/`、Credential、Environment File
- Source Mapに埋め込まれたRepository Absolute Path
- 未追跡の開発用Content

Build後に公開禁止Path／代表的Internal Title／管理用IdentifierがArtifactまたはSearch Indexへ含まれないことを検査する。

## Cloudflare Pages Delivery

Cloudflare Pages Project名は`blackops-docs`とし、初期Production Hostは`blackops-docs.pages.dev`とする。Custom Domainは初期Scope外とする。

GitHub Actionsは同一のInstall／Check／Buildを実行したArtifactをWrangler Direct Uploadする。

- Pull Request: 非Production Branch名でPreview Deploy
- `main` Push: Production Deploy
- Fork Pull Request: Check／Buildは行い、Secretが使えないPreview DeployだけSkip
- Workflow Dispatch: 再実行可能。ただしProduction Deploy条件は`main`に限定

Workflowは`contents: read`を基本権限とし、Cloudflare API TokenとAccount IDをGitHub Secretsから受け取る。Token値、Account ID、Deploy Output内のSecretをRepositoryへ保存しない。ProductionとPreviewはConcurrency Groupを分離し、同じRefの古いRunをCancelする。

Direct Upload ProjectはGit Integration Projectと混在させない。Project作成、Secret登録、Custom Domain、DNS変更はUser所有のExternal Configurationであり、Task Reportへ実施／未実施を記録する。

## Verification

- `docs/guide/`だけからContentが生成される
- 同一入力から生成Manifestが決定的である
- Title不在、重複Slug、壊れた内部Linkが失敗する
- Astro Type CheckとStatic Buildが成功する
- LandingからGetting Started、Operation、Execution、Database、Referenceへ到達できる
- Mobile Navigation、Keyboard Navigation、Color Contrast、Skip Linkを確認する
- `docs/internal/`、`develop/`、Credential、Absolute PathがArtifactへ含まれない
- Pull Request Previewと`main` Productionが同じBuild Artifact Contractを使う
- Production URLが200を返し、主要Page／Asset／Searchが利用できる

## Traceability

- Decision: [D081 Documentation Website Delivery Contract](../decisions/081-documentation-website-delivery-contract.md)
- Roadmap: [Developer Experience Roadmap](41-developer-experience-roadmap.md)
- Delivery Plan: [Phase 10 Delivery Plan](58-phase-10-delivery-plan.md)
