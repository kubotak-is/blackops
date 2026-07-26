# D124: Mermaid Diagram Legibility

Status: Decided

Extends D122／Specification 89のMermaid Render Contractを、描画成功だけでなく読める表示寸法まで強化する。

## Context

P20-008で4つのMermaid DiagramはBlume native `<blume-mermaid>`からSVGとして描画されるようになった。しかしlocalhost:4322の実Browserでは、Desktop 1440pxでArticle幅が672pxあるのに、全Diagramの内部OutputとSVGが約300pxへ縮退している。

BlumeのMermaid PluginはHostへ`flex justify-center overflow-x-auto`を付け、Client Elementは内部に`div`を生成する。SVGは`width="100%"`だが、内部`div`に幅がないためFlex Itemが約300pxへShrink-to-fitし、ViewBox幅926pxから1450pxのDiagramを約300pxへ縮小している。特にExecution Sequence Diagramは文字を判読できない。

## Decision

[DECISION]

1. Blume native Renderer、Mermaid Source、Public Route、Article本文幅は維持する。
2. Local Theme CSSで`<blume-mermaid>`の内部OutputをDesktopのArticle幅いっぱいへ広げ、SVGをその幅へ追従させる。
3. Desktop 1440pxでは4 DiagramすべてをArticle幅の95%以上、かつ640px以上で表示する。
4. Mobile 390pxではDiagramをDesktop相当の可読幅で維持し、`<blume-mermaid>`内だけを横Scroll可能にする。Page全体へ横Overflowを発生させない。
5. Mobileの初期表示はDiagram左端とし、中央寄せによる左側の到達不能を作らない。
6. Light／Dark Theme再描画、`accTitle`／`accDescr`、Error表示、Local Mermaid Runtimeの既存契約を維持する。
7. Source Regression、Static Artifact Guard、Browser Measurementで表示寸法とOverflow境界を検証する。
8. Blume Package、Generated Content、Diagram Source、Landing、Header、Sidebar、Public Slug、Framework `src/**`は変更しない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Desktopでは約300pxへ縮退していたDiagramが本文幅を使い、文字を判読できる。
- Mobileでは図全体を縮小し続けず、Page Layoutを壊さない局所横Scrollで詳細を読める。
- Diagram描画の有無だけでなく、実際の表示幅をBrowser Acceptanceへ含める。

[/CONSEQUENCES]

## References

- [D122 Blume Mermaid Rendering](122-blume-mermaid-rendering.md)
- [Specification 89](../spec/89-blume-mermaid-rendering.md)
- [Specification 91](../spec/91-mermaid-diagram-legibility.md)
