# Mermaid Diagram Legibility

## Scope

Blume native Mermaid Diagramを、DesktopとMobileの両方で判読できる表示寸法へする。

## Baseline

localhost:4322のP20-009 Accepted Artifactでは、Desktop 1440pxのArticle幅672pxに対して4 DiagramのOutput／SVG幅が約300pxである。最大のExecution DiagramはViewBox幅1450pxを約300pxへ縮小するため、Node Labelを判読できない。

## Layout Contract

- Blume native `<blume-mermaid>`とLocal Mermaid Runtimeを維持する
- Desktop 1440pxではOutput／SVGがArticle幅の95%以上かつ640px以上になる
- SVGはAspect Ratioを維持し、Heightを固定しない
- Mobile 390pxではHostをArticle幅内へ収める
- MobileのDiagram描画幅は640px以上を維持する
- MobileではHost内部だけを横Scroll可能にし、初期位置はDiagram左端とする
- Document ElementとArticleへ横Overflowを発生させない
- Light／Dark Theme切替後も同じ寸法境界とSVG描画を維持する

## Preservation Contract

- 4 DiagramのSource、Node、Edge、`accTitle`、`accDescr`を変更しない
- Public Route、Heading、Link、Sidebar、Searchを変更しない
- Blume PackageとGenerated Contentを直接変更しない
- External CDN、独自Mermaid Renderer、Diagram Imageを追加しない
- Framework `src/**`とPublic APIを変更しない

## Verification Contract

- Source RegressionがLocal ThemeのHost／Output／SVG幅とMobile Overflow Contractを固定する
- Static Artifact Guardが可読性CSSをBuild Artifactで検出する
- Browser Verificationは4 RouteのDesktop Lightを測定する
- 少なくともExecution DiagramをDesktop DarkとMobile 390pxで視覚確認する
- Browser VerificationはSVG幅、Host Client／Scroll幅、Page Overflow、Theme再描画、Error不在を記録する
- Website full gate、Mago format、Management ID Guard、`git diff --check`が成功する

## Traceability

- Decision: [D124 Mermaid Diagram Legibility](../decisions/124-mermaid-diagram-legibility.md)
- Base Rendering: [Specification 89](89-blume-mermaid-rendering.md)
