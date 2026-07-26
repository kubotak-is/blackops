# P20-008A: Mermaid Diagram Legibility

Status: Accepted

## Goal

約300pxへ縮退している4つのBlume native Mermaid Diagramを、Desktopでは本文幅いっぱい、Mobileでは局所横Scrollで読める表示寸法へ修正する。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/124-mermaid-diagram-legibility.md`
- `develop/spec/91-mermaid-diagram-legibility.md`
- `develop/spec/89-blume-mermaid-rendering.md`
- Blume Local SourceのMermaid Plugin／Client Element

矛盾時はD124／Specification 91を表示寸法の正本とし、D122／Specification 89の描画・Accessibility契約を維持する。

## Baseline Evidence

Playwright Chromiumのlocalhost:4322計測では、Desktop 1440pxのArticle幅672pxに対して4 RouteすべてのOutput／SVG幅が約300pxだった。Execution DiagramのViewBoxは1450px幅で、約300px表示ではLabelを判読できない。

## In Scope

- Local Theme CSSによるMermaid Host／Output／SVGの寸法補正
- Desktop本文幅利用
- Mobile局所横Scrollと左端初期表示
- Source／Artifact Regression Guard
- 4 RouteのDesktop Browser Measurement
- Execution DiagramのLight／Dark／390px Mobile視覚確認
- README、Report、STATE、TODO、Decision、Specification同期

## Out of Scope

- Diagram Source／意味／Node／Edge／本文の変更
- Blume Package／`node_modules`の変更
- 独自Renderer／External CDN／Diagram Image
- Article全体の幅、Sidebar、TOC、Navigation、Public Slugの変更
- Landing／Header／Bannerの変更
- Framework `src/**`、Public API
- 第3回Review P2／P3、External Publication／Deploy

## Files Allowed to Change

- `docs/website/theme.css`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/scripts/check-artifact.mjs`
- `docs/website/README.md`
- `develop/decisions/122-blume-mermaid-rendering.md`（D124参照だけ）
- `develop/decisions/124-mermaid-diagram-legibility.md`
- `develop/spec/89-blume-mermaid-rendering.md`（Specification 91参照だけ）
- `develop/spec/91-mermaid-diagram-legibility.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-008A-mermaid-diagram-legibility.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- `blume-mermaid`の内部Output幅を明示し、約300pxのShrink-to-fitを解消する
- Desktop 1440pxでは4図のOutput／SVGをArticle幅95%以上かつ640px以上にする
- Mobile 390pxではDiagram幅640px以上を維持し、Host内部だけを横Scroll可能にする
- Hostの中央寄せによって左端を到達不能にしない
- SVGのAspect Ratioを維持し、固定Heightで歪めない
- Page全体へ横Overflowを発生させない
- Light／Dark、Accessibility Metadata、Local Runtimeを維持する
- Generated Content／`dist`／`node_modules`を直接編集しない
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

Browser Verificationで4 RouteのDesktop Light、Execution DiagramのDesktop Dark／Mobile 390pxを確認し、Article／Host／Output／SVG幅、Host Scroll幅、Page Overflow、Error不在をReportへ記録する。

## Acceptance Criteria

- [x] Desktop 1440pxの4 DiagramがArticle幅95%以上かつ640px以上で表示される
- [x] Mobile 390pxのDiagram描画幅が640px以上である
- [x] MobileはHost内部だけが横Scrollし、初期表示が左端である
- [x] Document／Articleに横Overflowがない
- [x] SVGのAspect Ratioが維持される
- [x] Light／Dark Theme切替後もSVGが描画され、寸法境界を満たす
- [x] Diagram Error Messageが表示されない
- [x] 4 DiagramのSource／Accessibility Metadata／Public Routeを変更しない
- [x] Blume Package／Generated Content／Framework `src/**`を変更しない
- [x] Source／Artifact Regression Guardが可読性Contractを検証する
- [x] Website full gateとRequired Commandsが成功する
- [x] Report／STATEがEvidenceへ一致する
- [x] WorkerはCommitしない

## Completion Report

`develop/orchestration/reports/P20-008A-mermaid-diagram-legibility.md`へSummary、Root Cause、Changed Files、Desktop Contract、Mobile Contract、Theme／Accessibility Preservation、Commands and Results、Browser Evidence、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
