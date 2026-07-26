# P20-008 Blume Mermaid Rendering

## Summary

Mermaidを含む4 PageだけをGenerated `.mdx`へ切り替え、通常Pageは`.md`のまま維持した。BlumeのMDX専用Pluginが出力するnative `<blume-mermaid>`をArtifact／Site Guardの正本へ変更し、Syntax-highlighted `data-language="mermaid"`を退行として拒否するRegressionを追加した。

## Root Cause

Content Pipelineが全Pageを`.md`へ固定していたため、BlumeのMarkdown Mermaid Pluginが実行されず、4つのFenceがCode Blockとして配信された。既存Guardも`data-language="mermaid"`をDiagram Targetとして数え、壊れたArtifactを成功判定していた。

## Changed Files

- `docs/website/scripts/content-pipeline.mjs`
- `docs/website/scripts/check-artifact.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/tests/content-pipeline.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/README.md`
- `develop/orchestration/tasks/P20-008-blume-mermaid-rendering.md`
- `develop/STATE.md`
- `develop/TODO.md`
- `develop/orchestration/reports/P20-008-blume-mermaid-rendering.md`

Diagram Source、`docs/guide/*.md`、Public Slug、Landing、Header、Sidebar、Redirect、Search、Banner、Framework `src/**`は変更していない。

## Generation Contract

- Fence開始行を解析し、` ```mermaid `または`~~~mermaid`を含むPageだけを`.mdx`へ出力する。
- Mermaidを含まないPageは従来どおり`.md`へ出力する。
- `replaceGeneratedContent`の全置換により、再生成時は旧拡張子のCounterpartを残さない。
- Manifestの`generated`、Source Hash、生成Contentは拡張子と一致し、Source Markdownは変更しない。

## Artifact / Browser Contract

Static Build後の対象4 Routeはすべてnative `<blume-mermaid>`を1個含み、`data-language="mermaid"`は0個である。各Routeで`accTitle`と`accDescr`を1個ずつ保持し、Mermaid Error Markerは存在しない。Local Mermaid Runtime BundleはBlume Buildへ同梱され、外部CDNは追加していない。

Built Artifact evidence:

| Route | native target | syntax block | `accTitle` / `accDescr` | error marker |
| --- | ---: | ---: | ---: | ---: |
| `/concepts/core-concepts/` | 1 | 0 | 1 / 1 | 0 |
| `/concepts/lifecycle/` | 1 | 0 | 1 / 1 | 0 |
| `/execution/context/` | 1 | 0 | 1 / 1 | 0 |
| `/execution/http-and-deferred/` | 1 | 0 | 1 / 1 | 0 |

Worker環境ではBrowser Runtimeを起動できなかったが、OrchestratorのBrowser Reviewで4 RouteのSVG描画とResponsive／Theme契約を確認した。

Orchestrator Browser evidence:

- `localhost:4322`の4 Routeすべてで`targets=1`、`svgs=1`、`codeBlocks=0`、`busy=0`、`error=false`、`pageOverflow=false`。
- Core ConceptsのDark Theme切替後も`svg=1`、`error=false`。
- 390px Mobileで`svg=1`、`pageOverflow=false`、`targetOverflow=false`。
- SVG accessibilityは`role="graphics-document document"`、title「BlackOpsの中核概念」、`accDescr`本文のdescription。
- Screenshots: `/tmp/blackops-p20-008-browser/core-concepts-desktop-light.png`, `/tmp/blackops-p20-008-browser/core-concepts-desktop-dark.png`, `/tmp/blackops-p20-008-browser/core-concepts-mobile-light.png`。

## Responsive / Theme / Accessibility

既存Blume native elementのLocal Mermaid Runtime、Theme MutationObserver、Diagram内Horizontal Overflow、`accTitle`／`accDescr` Sourceを再利用した。新しい独自Renderer／Theme／CDNは追加していない。Static Guardで4 SourceのAccessible Metadata保持を確認した。

## Commands and Results

- `mise exec -- pnpm --dir docs/website run test`: PASS、55 tests。
- `mise exec -- pnpm --dir docs/website run check`: PASS、Content／Diagram／Blume validate、Blume check 37 pages、0 errors／warnings／hints。
- `mise exec -- pnpm --dir docs/website run build`: PASS、38 pages／37 public routes、Static Artifact／Site Guard PASS。既存のchunk size warningとroute conflict warningのみ。
- `docker compose run --rm app mago format --check src tests`: PASS、All files are already formatted。
- Management-ID PHP guard: PASS、該当なし。
- `git diff --check`: PASS。
- Browser Verification: PASS、Orchestrator提供の4 Route／Desktop Light・Dark／390px Mobile evidenceを確認（上記参照）。

## Acceptance Criteria

- Mermaid 4 Pageの`.mdx`生成、通常Pageの`.md`維持、Manifest一致: 実装・Test・Buildで確認済み。
- 再生成時の旧拡張子除去、Source非変更: Regression Testで確認済み。
- Static Artifactのnative target、Syntax Block不在、`accTitle`／`accDescr`: 4 Routeで確認済み。
- Browser SVG、Light／Dark、Mobile visual evidence: Orchestrator Browser Reviewで確認済み。
- External CDN、既存Route／Landing／Navigation、Framework `src/**`: 変更なし、Website full gate PASS。
- Report／STATE同期、Worker Commitなし: 完了。

## Remaining Issues

Worker環境のloopback／Browser制約は残るが、Orchestrator Browser Reviewで必要なSVG／Theme／Responsive evidenceを取得済みであり、機能上のRemaining Issueはない。

## Suggested Next Action

P20-009でReference欠番とLifecycle／Configurationの検索性を扱う。

## Correction

Orchestrator ReviewでREADMEのArtifact GuardとBrowser Verificationの責務が混在していたため、READMEを修正した。Artifact Guardはnative Render Target、Syntax-highlighted Code Block不在、Local Renderer Bundleだけを確認し、SVG描画・Theme切替・Responsive OverflowはBrowser Verificationの責務として明記した。Correction後にWebsite test 55件と`git diff --check`を再実行してPASS（check／build、Mago、Management-ID guardは直前の同一実装でPASS済み）。TaskはReview Pendingのまま、WorkerはCommitしていない。

## Orchestrator Acceptance

2026-07-26T11:34:14+09:00にSource、Generated Content、Static Artifact、Playwright Chromiumの4 Route、Desktop Light／Dark、390px Mobileを独立Reviewした。4 Routeはすべてnative target／SVG各1、Code Block／Busy／Error／Page Overflow各0であり、Theme再描画とSVG accessible title／descriptionを確認した。README Correction後のWebsite 55 tests、check、build、Mago、Management-ID guard、`git diff --check`も成功した。P20-008をAcceptedとする。Worker Commitなし。
