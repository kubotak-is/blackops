# P10-005H: Documentation Information Architecture and Visual Polish

Status: Accepted

## Goal

公開Documentation Websiteを一般的なFramework Documentationの学習順と参照用途へ再編し、Starlight標準機能を維持しながらLandingをBlackOpsの価値が伝わるProduct Pageへ改善する。

## In Scope

- 11 Section SidebarとPage順序
- Installationから始まるGetting Started
- Lifecycle、Security、Troubleshooting、Current StatusのSection／Slug移動
- `Execution & Workers`／`Data & Retention` Label
- Testing／Deployment入口Page
- Referenceを6 Pageへ限定
- Landing Product Title、CTA、3 Feature Link Card
- Starlight制約内のResponsive Visual Polish
- 旧URLのStatic 301 Redirect
- Content Map、Navigation Validation、Link、Search、Artifact Test
- Website README、Spec、TODO、Report、STATE同期

## Out of Scope

- 既存Guide本文の全面改稿
- 新しいFramework／Testing API
- Production Orchestrator Integration実装
- Changelog生成、Release Archive、Version Selector
- Cloudflare External Configuration

## Relevant Specifications and Decisions

- `develop/decisions/081-documentation-website-delivery-contract.md`
- `develop/decisions/082-documentation-reader-experience.md`
- `develop/decisions/090-documentation-information-architecture.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/58-phase-10-delivery-plan.md`
- `develop/spec/59-documentation-reader-experience.md`

## Files Allowed to Change

- `docs/guide/**`
- `docs/website/**`
- `develop/TODO.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/58-phase-10-delivery-plan.md`
- `develop/spec/59-documentation-reader-experience.md`
- `develop/orchestration/tasks/P10-005H-documentation-information-architecture.md`
- `develop/orchestration/reports/P10-005H-documentation-information-architecture.md`
- `develop/STATE.md`

## Constraints

- GPT-5.6 Luna High workerが実装し、Review前にCommitしない
- D090で確定したSidebar、URL、Landing Copyを正とする
- Generated Contentを直接編集しない
- Testing／Deploymentは入口Pageに留め、未提供機能を実装済みとして説明しない
- Starlight標準のSidebar、Search、Mobile Navigation、Theme、Accessibilityを維持する
- Landing装飾を本文Pageへ広げない
- 外部画像、CDN、壊れやすいDOM Patchへ依存しない
- Stable／main Bannerと既知制約を維持する

## Acceptance Criteria

- [x] SidebarがD090の11 SectionとPage順に一致する
- [x] Getting StartedがInstallation、Quickstart、Tutorial、Directory Structure、Local Runtimeの順である
- [x] ReferenceがCore API、Attributes、Configuration、BlackOps CLI、Application Bootstrap、Glossaryだけを持つ
- [x] Lifecycle、Security、Troubleshooting、Current Statusが新Section／URLへ移動する
- [x] Testing／Deployment入口Pageが未提供機能を誤認させない
- [x] Landing Titleが`BlackOps — The PHP Framework`である
- [x] Landingが指定CopyとLinkを持つ3 Feature Cardを表示する
- [x] Primary CTAがInstallation、Secondary CTAがWhy BlackOpsである
- [x] 旧4 URLがStatic 301 Redirectを持つ
- [x] Navigation Validationが11 Section、Missing、Duplicate、Unknown、Reorderedを検証する
- [x] Desktop／Mobile、Dark／Light、Keyboard Focus、Reduced MotionでLandingが利用できる
- [x] Page Level Horizontal Overflowがなく、Search／Sidebar／Mobile Menu／Mermaidを壊さない
- [x] Stable／main BannerとCurrent Statusの正直さを維持する

## Required Commands

```bash
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
! rg -n 'docs/internal|develop/|ghp_|gho_|github_pat_' docs/website/dist
! rg -n 'operations/lifecycle|reference/security|reference/troubleshooting|reference/current-status' docs/guide docs/website/src/content/docs --glob '*.md'
git diff --check
```

Browser ReviewはDesktopと390 px相当Mobile、Dark／Light、Keyboard Focus、Reduced Motionを確認し、Viewport、Overflow、主要Elementの実測値をReportへ記録する。

## Expected Report

`develop/orchestration/reports/P10-005H-documentation-information-architecture.md`へSummary、IA Mapping、Landing Evidence、Redirect Evidence、Browser Evidence、Changed Files、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
