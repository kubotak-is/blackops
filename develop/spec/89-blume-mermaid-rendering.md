# Blume Mermaid Rendering

表示寸法とMobile局所横Scrollは[Mermaid Diagram Legibility](91-mermaid-diagram-legibility.md)が拡張する。

## Scope

公開Documentationにある4つのMermaid Diagramを、Code BlockではなくBlume native Diagramとして表示する生成・Artifact・Browser Contractを定義する。

## Source and Generation Contract

- `docs/guide/*.md`を編集正本として維持する
- Mermaid Fence ` ```mermaid `を含むPageはGenerated Contentを`.mdx`とする
- Mermaidを含まないPageはGenerated Contentを`.md`とする
- Generated Manifestの`generated`は実際の拡張子へ一致する
- 再生成時に旧`.md`／`.mdx` Counterpartを残さない
- Source Markdown、Public Slug、Heading、Link、Diagram Sourceを生成処理で変更しない

## Render Contract

対象Pageは次の4つとする。

| Public Route | Source |
| --- | --- |
| `/concepts/core-concepts` | `docs/guide/core-concepts.md` |
| `/concepts/lifecycle` | `docs/guide/operation-lifecycle.md` |
| `/execution/http-and-deferred` | `docs/guide/execution.md` |
| `/execution/context` | `docs/guide/execution-context.md` |

各Pageは次を満たす。

- Static Artifactに`<blume-mermaid>` Render Targetが一つある
- Mermaid Sourceを示す`data-source`がある
- `data-language="mermaid"`のSyntax-highlighted Code Blockがない
- `accTitle`と`accDescr`がSourceとArtifactにある
- Local Mermaid Runtimeを使い、外部CDNへ依存しない
- Browser上でRender Target内にSVGが一つ描画される
- Diagram Error Messageが表示されない
- Light／Dark Theme切替後もSVGが描画される
- Mobile幅でDocument全体の横Overflowを発生させない

## Verification Contract

- Content Pipeline TestがMermaid `.mdx`／通常 `.md`の選択とSource非変更を検証する
- Artifact／Site Guardは`<blume-mermaid>`を数え、Code Block状態を拒否する
- Browser Verificationは4 RouteのSVG描画を確認する
- Desktop Light／DarkとMobileの少なくとも一つのDiagram Pageを視覚確認する
- Existing Website full gate、Framework Mago format、Management ID Guard、`git diff --check`が成功する

## Out of Scope

- Diagramの意味、Node、Edge、本文の書き換え
- Custom Mermaid Renderer／Themeの追加
- External CDN／Remote Runtime
- 全PageのMDX化
- Landing、Header、Sidebar、Public Slug、Redirectの変更
- Framework `src/**`、Public APIの変更
- External Publication／Deploy

## Traceability

- Decision: [D122 Blume Mermaid Rendering](../decisions/122-blume-mermaid-rendering.md)
- Documentation Experience: [Specification 83](83-blume-documentation-experience.md)
