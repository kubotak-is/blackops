# P20-011: Documentation Site UX

Status: Accepted

## Goal

Blume native UXを利用してCallout、Japanese Code Copy、Previous／Next、正しいGitHub Edit Link、日本語Font Stack、Responsive Inline Codeを全Public Guideへ接続する。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/132-documentation-site-ux.md`
- `develop/spec/96-documentation-site-ux.md`
- `develop/decisions/117-documentation-learning-journey.md`
- `develop/spec/84-documentation-learning-journey.md`
- `develop/spec/87-documentation-second-review-and-feature-parity.md`
- `develop/spec/90-documentation-third-review-accuracy.md`
- `develop/spec/92-documentation-review-agent.md`
- Current Blume `1.1.4` source under installed `docs/website/node_modules/blume`
- Current `docs/guide/**`、`docs/website/**`、`site-navigation.mjs`、`content-map.mjs`

## In Scope

- Blume native Calloutを使うChannel／Risk境界
- Callout PageのMDX生成判定
- Native Code Copyの日本語Accessible Labelと成功／失敗通知
- Canonical Sidebar順のPrevious／Next Adapter
- Current RouteからTracked `docs/guide` SourceへのEdit Link Mapping
- Japanese-aware Font StackとInline Code Wrap
- First Operation 390px Page Overflowの解消
- Landing Decorative Section Numberの削除
- Website Unit／Content／Artifact／Browser Regression
- Decision／Specification／TODO／STATE／Report同期

## Out of Scope

- 全Page文章編集、表記Guideline、一般語の日本語化
- Public Slug、Sidebar Label／順序、Redirect、Header、Banner、Search
- Landing指定Copy、CTA、三Featureの同格Layout
- New Marketing Section、Image、Icon Family、Animation、Dependency
- Blume Version変更
- Framework `src/**`、Test、Generator、Example、Migration
- Stable Tag、Commit、Push、PR、External Deploy

## Files Allowed to Change

- `docs/guide/installation.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/first-operation.md`
- `docs/guide/authentication.md`
- `docs/guide/frontend.md`
- `docs/guide/outbox.md`
- `docs/guide/deployment.md`
- `docs/website/blume.config.ts`
- `docs/website/components.ts`
- `docs/website/components/**`
- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/pages/index.astro`
- `docs/website/theme.css`
- `docs/website/*.css`
- `docs/website/scripts/**`
- `docs/website/tests/**`
- `docs/website/README.md`
- `develop/decisions/132-documentation-site-ux.md`
- `develop/spec/96-documentation-site-ux.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-011-documentation-site-ux.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- Blume native Callout、Code Copy、Pagination、Page Actionの表示を再実装しない。Code CopyはNative Clipboard処理を維持し、Accessible Statusだけを薄く補う
- PaginationとEdit Linkは`site-navigation.mjs`／`content-map.mjs`を正本とし、Page一覧を複製しない
- Generated Contentと`dist`を直接編集しない
- Runtime Font CDN、External Script、New Dependencyを追加しない
- CalloutでPublic Contractを変更せず、既存のChannel／Risk説明を短く再配置する
- Long Inline CodeをPage全体でScrollさせず、Code Block／Table／Mermaidの局所Scrollは維持する
- Existing Phase 20 Working Tree差分を保持する
- WorkerはCommitしない

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Websiteの`check`と`build`は同時実行しない。Browser VerificationはOrchestratorが最新SourceのDev ServerまたはStatic Artifactを使い、Desktop 1440px Light／DarkとMobile 390pxで実行する。

## Acceptance Criteria

- [x] Callout Directiveを持つPageだけがMermaid Pageと同様にMDX生成される
- [x] Stable／`main`またはRisk境界を少なくとも7対象PageでNative Callout表示する
- [x] Code Copyが日本語Accessible Labelと成功／失敗のAccessible Statusを持ち、Native Clipboard処理とCheck表示を維持する
- [x] Previous／NextがCanonical Sidebar順でFirst／Middle／Last Pageに正しく出る
- [x] Edit LinkがTracked `docs/guide` Sourceへ解決しGenerated Sourceを指さない
- [x] Japanese Font StackがLandingとContent Pageへ適用される
- [x] Long Inline Codeが390px Page Overflowを生まない
- [x] Landing Decorative Section Numberを削除し、指定Copy／CTA／Feature Layoutを維持する
- [x] Existing Public Slug、Navigation、Header、Banner、Search、Redirectを維持する
- [x] Website RegressionとRequired Commandsが成功する
- [x] WorkerはReport／STATE／TODOを同期し、Commitしない

## Completion Report

`develop/orchestration/reports/P20-011-documentation-site-ux.md`へSummary、Changed Files、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
