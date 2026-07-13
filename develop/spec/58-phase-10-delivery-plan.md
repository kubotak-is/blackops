# Phase 10 Delivery Plan

## Goal

Framework利用者向けMarkdownをSingle SourceとするAstro Starlight Websiteを構築し、Cloudflare PagesのPreview／Productionへ検証済みStatic Artifactを公開する。

## P10-001: Documentation Website Contract

- Public Audienceと公開対象外の確定
- Markdown AdaptationとInformation Architecture
- mise／pnpm ToolchainとVersion表示
- Cloudflare Pages Publication Boundary
- Phase 10 Production Task分割

## P10-002: Documentation Directory Migration

- `docs/internals/`から`docs/internal/`へのAtomic Rename
- AGENTS、README、Specification、Task／Report、Guide Linkの同期
- Acceptance Evidence中心のGuideをInternalへ移動
- 旧Path、壊れたRepository Link、History改変の検証

## P10-003: Starlight Single-source Foundation

- miseで固定したNode.js 24 LTS／pnpm 11
- `docs/website/` Astro Starlight Project
- `docs/guide/`からの決定的Content生成
- Title／Slug／Link／公開対象外Content Guard
- Astro Check、Static Build、Unit Test

## P10-004: User Documentation Information Architecture

- Framework利用者向けLandingとGetting Started
- Operations、Execution、Database、Reference Navigation
- 既存Guideの移行と必要最小限の現行API同期
- `main`／Stable Version Notice
- Mobile、Keyboard、Accessibility、Search検証

## P10-005: Cloudflare Pages Delivery

- GitHub Actions Build／Artifact Gate
- Wrangler Direct Upload
- Pull Request Previewと`main` Production境界
- Fork Pull RequestのSafe Skip
- Secret／Artifact／Concurrency Guard
- Deployment Setup Guide

## P10-006: Phase 10 Closeout

- Full Website Quality Suite
- Repository Documentation／README／TODO同期
- Production URLと主要PageのLive Verification
- Preview／Production Evidence
- Phase 10 Acceptance、Report、STATE Closeout

## Dependency Order

```text
P10-001 Website Contract
  -> P10-002 Documentation Directory Migration
    -> P10-003 Starlight Single-source Foundation
      -> P10-004 User Documentation Information Architecture
        -> P10-005 Cloudflare Pages Delivery
          -> P10-006 Phase 10 Closeout
```

## Commit Boundaries

各Taskを一つのReview／Commit単位とする。P10-005はWorkflow実装CommitとExternal Cloudflare Setupの証拠を分離できる。User CredentialまたはDashboard操作待ちになった場合、実装済みLocal変更を先にReview／Commitし、STATEへ明示して停止する。

## Phase Acceptance Criteria

- [x] `docs/internals/`が`docs/internal/`へ移行し、有効なRepository参照が同期している
- [ ] Websiteが`docs/guide/`だけを公開Sourceとして使用する
- [ ] Internal／Contributor／Develop ContentがArtifactとSearchへ含まれない
- [ ] mise、Node.js 24 LTS、pnpm 11、Lockfileが再現可能に固定される
- [ ] Content生成、Title／Slug／Link Guard、Astro Check、Static Buildが成功する
- [ ] Landingと利用者目的別Navigationが完成している
- [ ] `main` DocumentとStable Versionの差が表示される
- [ ] Mobile、Keyboard、Accessibility、Searchを検証している
- [ ] Pull Request Previewと`main` Production Deployが成功する
- [ ] Production Hostの主要PageとAssetがLive Verificationに成功する

## Traceability

- Decision: [D081 Documentation Website Delivery Contract](../decisions/081-documentation-website-delivery-contract.md)
- Contract: [Documentation Website Delivery Contract](57-documentation-website-delivery-contract.md)
- Roadmap: [Developer Experience Roadmap](41-developer-experience-roadmap.md)
