# D122: Blume Mermaid Rendering

Status: Decided

Partially supersedes D121／Specification 88のDelivery Order。Reference欠番よりP20-008の公開DocumentationのMermaid表示退行を先に回収した。P20-009以降の順序はD123／Specification 90が更新する。

表示寸法とMobile局所横ScrollはD124／Specification 91が拡張する。

## Context

Blume移行後の公開Documentationでは、4つのMermaid Fenceが図ではなくSyntax-highlighted Code Blockとして表示されている。

`docs/guide/*.md`をBlume Contentへ生成するPipelineは、内容にかかわらず出力拡張子を`.md`へ固定している。一方、BlumeのMermaid PluginはMDX ProcessorでFenceを`<blume-mermaid>`へ変換し、Browser上でLocal Mermaid Runtimeを使ってSVGを描画する。このためSource Diagramは正しいが、生成形式がBlumeのRendering Contractを満たしていない。

既存Artifact／Site Guardは`data-language="mermaid"`をRender Targetとして数えており、Code Block表示を成功と誤判定する。

## Decision

[DECISION]

1. `docs/guide/*.md`は引き続き公開Documentationの編集正本とする。
2. Content PipelineはMermaid Fenceを含むPageだけを`.mdx`へ生成する。Mermaidを含まないPageは`.md`を維持する。
3. Blume native Mermaid PluginとLocal Dependencyを使用する。独自Renderer、外部CDN、Diagram Imageの手動生成は追加しない。
4. Public Slug、Navigation、Search、Raw Source、Diagram Source、隣接する本文説明は変更しない。
5. Artifact Guardは各Diagram Pageに`<blume-mermaid>`が一つあり、`data-language="mermaid"`のCode Blockがないことを検証する。
6. Browser Verificationは各Diagram Pageで`<blume-mermaid>`内にSVGが描画され、Error Text、横方向Page Overflow、Theme切替の破綻がないことを確認する。
7. `accTitle`／`accDescr`をSourceとArtifactで維持し、Diagramの説明は本文でも理解できる状態を保つ。
8. Content Pipeline TestはMermaid Pageの`.mdx`選択、通常Pageの`.md`維持、決定的Manifest、Source非変更、旧生成Fileの除去を検証する。
9. Existing Landing、Header、Sidebar、Public Slug、Redirect、Framework `src/**`、External Publication／Deployは変更しない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Mermaidを含む4 PageはBlume native Componentとして描画される。
- 通常Markdownを一括でMDX化せず、MDX Syntaxとの予期しない衝突範囲を限定できる。
- Code BlockをRender Targetと誤認するRegression Guardが解消される。
- DiagramはLight／Dark ThemeとResponsive LayoutへBlume標準の動作で追従する。

[/CONSEQUENCES]

## References

- [D116 Blume Documentation Site](116-blume-documentation-site.md)
- [D121 Executable Stable Onboarding](121-executable-stable-onboarding.md)
- [Specification 89](../spec/89-blume-mermaid-rendering.md)
