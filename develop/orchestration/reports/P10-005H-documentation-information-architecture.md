# P10-005H: Documentation Information Architecture and Visual Polish Report

Status: Accepted

## Summary

D090で確定した11 Sectionへ公開Websiteを再編した。Getting StartedはInstallationから始め、OverviewへLifecycleを移し、Security、Troubleshooting、Releasesを独立させた。Testing／Deploymentは現行機能と運用責務への入口だけを追加し、専用Testing APIやProduction Orchestrator Integrationを提供済みとは説明していない。

Landingは`BlackOps — The PHP Framework`、Installation／Why BlackOps CTA、Operation／Journal／Deferredの3 Feature Cardへ変更した。CSS Grid、Gradient、Focus、Reduced MotionはLandingへ限定し、Starlight標準Search、Sidebar、Mobile Menu、Theme、本文Layoutを維持した。移動した4 URLはCloudflare PagesのStatic `_redirects`で301接続した。

## IA Mapping

| Section | Pages |
| --- | --- |
| Overview | Why BlackOps、Core Concepts、Operation Lifecycle |
| Getting Started | Installation、Quickstart、Tutorial、Directory Structure、Local Runtime |
| Operations | Operation Authoring、Generators、Validation |
| Execution & Workers | HTTP、Inline、Deferred、Execution Context |
| Data & Retention | Database Migrations、Outcome Retrieval、Data Retention |
| Testing | Testing Overview |
| Deployment | Production Worker Operations |
| Security | Security & Sensitive Data |
| Troubleshooting | Troubleshooting / FAQ |
| Releases | Current Status |
| Reference | Core API Types、Attributes、Configuration、Project CLI、Application Bootstrap、Glossary |

`site-navigation.mjs`は11 Labelの完全一致、全Pageの一意配置、Missing／Duplicate／Unknown／Reorderedを拒否する。Tutorialだけは公開Page Titleを変えずSidebar Labelを`Tutorial`へ固定した。

## Landing Evidence

- H1: `BlackOps — The PHP Framework`
- Primary CTA: `/getting-started/installation/`
- Secondary CTA: `/concepts/why-blackops/`
- Operation Card: `/operations/authoring/`
- Journal Card: `/concepts/lifecycle/`
- Deferred Card: `/execution/http-and-deferred/`
- Desktopは3 Card一列、50 rem以下は一列、50〜72 remは二列へReflowする。
- Light Heroの初回Browser ReviewでGradient始点のTheme Tokenが白寄りになる問題を検出し、`var(--sl-color-text)`へ修正した。

## Redirect Evidence

`docs/website/public/_redirects`をStatic ArtifactへCopyし、Source TestとSite Checkで次の完全一致を検証した。

```text
/operations/lifecycle/* /concepts/lifecycle/:splat 301
/reference/security/* /security/:splat 301
/reference/troubleshooting/* /troubleshooting/:splat 301
/reference/current-status/* /releases/current-status/:splat 301
```

## Browser Evidence

Actual Windows Edge HeadlessとRepository外の一時Same-origin iframe Harnessで確認した。Harnessは公開Artifactへ含めていない。

| Review | Document client／scroll | Main measurement | Result |
| --- | --- | --- | --- |
| Desktop 1200 px、Dark | 1185／1185 | H1 606 px／84 px、Card 348 px × 3列 | Page Overflowなし、Grid／Glow／CTA／Cardを確認 |
| Mobile 390 px、Light | 375／375 | H1 343 px／48 px、Card 343 px × 1列 | Page Overflowなし、Title／CTA／CardのReading OrderとContrastを確認 |
| Keyboard Focus | 同上 | CTA 2件とCard 3件すべて`activeElement`一致、Card Outline 2 px | Hoverへ依存せず全LinkへFocus可能 |
| Reduced Motion | 1185／1185 | `prefers-reduced-motion: reduce = true`、全Card `transitionDuration = 0s` | 不要なTransition停止 |
| Mobile Documentation | 390／390 | Search／Sidebarあり、Menu `false` → `true` | Starlight標準Mobile Menuが展開 |
| Lifecycle Mermaid 390 px、Light | 375／375 | Target 343／992 px、SVG 960 px、`stateDiagram` | Page Overflowなし、Diagram内Horizontal ScrollとSVG描画を確認 |

Dark／LightともText、Border、Code Accentを判別できる。Landing以外のPageはStarlight標準Layoutを維持し、移動後のLifecycle MermaidもLocal RendererからSVG化された。

## Changed Files

### Public Guide

- `docs/guide/README.md`
- `docs/guide/core-concepts.md`
- `docs/guide/operation-lifecycle.md`
- `docs/guide/installation.md`
- `docs/guide/first-operation.md`
- `docs/guide/directory-structure.md`
- `docs/guide/testing.md`
- `docs/guide/deployment.md`

### Website

- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/public/_redirects`
- `docs/website/src/styles/diagram-responsive.css`
- `docs/website/tests/site-navigation.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/scripts/check-artifact.mjs`
- `docs/website/README.md`

### Orchestration and Specification Sync

- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/58-phase-10-delivery-plan.md`
- `develop/spec/59-documentation-reader-experience.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P10-005H-documentation-information-architecture.md`
- `develop/orchestration/reports/P10-005H-documentation-information-architecture.md`
- `develop/STATE.md`

## Decisions and Assumptions

- D090の11 Section、URL、Copy、Link、Redirectを変更せず実装した。
- `Execution & Workers`と`Data & Retention`はSidebar Labelだけを変更し、既存URLを維持した。
- Testing／Deploymentは入口Pageに限定し、Local ComposeをProduction推奨Topologyとして提示しない。
- Landing背景はCSSだけで構成し、外部画像、CDN、Starlight DOMを変更するJavaScriptを追加しない。
- Generated `src/content/docs/`と`dist/`はCommandから再生成し、直接編集していない。

## Commands and Results

```text
mise exec -- pnpm --dir docs/website install --frozen-lockfile
Result: exit 0。Already up to date、pnpm 11.12.0。Network制限によるpnpm metadata fetch warningは出たが、Frozen Lockfile Installは成功。

mise exec -- pnpm --dir docs/website run test
Result: 35 tests / 35 passed / 0 failed。11 Section、Getting Started、Reference、移動Page、3 Card、CTA、Redirect、Missing／Duplicate／Unknown／Reorderedを検証。

mise exec -- pnpm --dir docs/website run check
Result: Content Determinism、Mermaid Syntax／Accessibility、Astro Checkが成功。16 files / 0 errors / 0 warnings / 0 hints。

mise exec -- pnpm --dir docs/website run build
Result: 28 Public Pages plus 404、Pagefind 29 HTML、Sitemap、Artifact／Site／Search Guardが成功。既知のMermaid Chunk Size Warningだけを出力。

! rg -n 'docs/internal|develop/|ghp_|gho_|github_pat_' docs/website/dist
Result: No matches。

! rg -n 'operations/lifecycle|reference/security|reference/troubleshooting|reference/current-status' docs/guide docs/website/src/content/docs --glob '*.md'
Result: No matches。

docker compose run --rm app mago format --check src tests
Result: Sandbox内初回はDocker Socket Permissionで失敗。承認済みDocker実行で`INFO All files are already formatted.`。

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches。

git diff --check
Result: Success。

Windows Edge Headless Browser Review
Result: Desktop／Mobile、Dark／Light、Keyboard Focus、Reduced Motion、Search／Sidebar／Mobile Menu、Lifecycle Mermaidを実測し、最終状態はすべて合格。
```

## Acceptance Criteria

- [x] SidebarがD090の11 SectionとPage順に一致する
- [x] Getting StartedがInstallation、Quickstart、Tutorial、Directory Structure、Local Runtimeの順である
- [x] Referenceが指定6 Pageだけを持つ
- [x] Lifecycle、Security、Troubleshooting、Current Statusが新Section／URLへ移動する
- [x] Testing／Deployment入口が未提供機能を実装済みと説明しない
- [x] Landing Title、CTA、3 Feature CardのCopy／LinkがD090と一致する
- [x] 旧4 URLがStatic 301 Redirectを持つ
- [x] Navigation Validationが11 Sectionと配置Errorを検証する
- [x] Desktop／Mobile、Dark／Light、Keyboard Focus、Reduced MotionでLandingを利用できる
- [x] Page Level Overflowがなく、Search／Sidebar／Mobile Menu／Mermaidを維持する
- [x] Stable／main BannerとCurrent Statusの既知制約を維持する

## Remaining Issues

P10-005H Scope内のBlockerはない。Cloudflare Project／Token／GitHub Environment SecretsとProtection RuleはUser所有のExternal Configuration待ちであり、Production URLはまだ検証できない。このExternal BlockerはP10-006で継続管理する。

## Suggested Next Action

P10-005HをCommitし、P10-006 Phase 10 Closeoutを再開する。
