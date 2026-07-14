# BlackOps Documentation Website

Astro Starlightで構築するBlackOps利用者向けDocumentation Websiteである。公開本文の編集元はRepository Rootの`docs/guide/`だけであり、このProject内へ本文を手動Copyしない。

## Toolchain

Repository RootでNode.jsとpnpmを導入する。

```bash
mise install
mise exec -- pnpm --dir docs/website install --frozen-lockfile
```

Node.jsとpnpmのVersionは`mise.toml`、`package.json`、CIで一致させる。Dependency更新時は`package.json`と`pnpm-lock.yaml`を同じCommitで更新する。

## Development

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
mise exec -- pnpm --dir docs/website run dev
```

`content:generate`は`docs/guide/`からStarlight ContentとManifestを生成する。生成先の`src/content/docs/`と`.generated/`、Astro出力の`dist/`はGit管理しない。生成物を直接編集しても次回実行で全置換されるため、本文変更は必ず`docs/guide/`へ行う。

GeneratorはTitle、Slug、内部Link、Source境界を検証する。`docs/internal/`、`develop/`、Repository Absolute Pathは公開Contentへ取り込まない。

`content-map.mjs`はSource Relative Pathから公開Slug／Page MetadataへのMapping、`site-navigation.mjs`はOverviewからReferenceまでの11 SectionとPage順を管理する。Source追加時は両方を更新する。未登録Source、欠落Source、重複Slug、Sidebar未配置／重複／未知SlugはBuild前に拒否される。

`public/_redirects`は役割変更で移動したLifecycle、Security、Troubleshooting、Current Statusの旧URLを新URLへ301 Redirectする。Slugを変更するときはSource Link、Content Map、Sidebar、Redirect、Search／Artifact Testを同じ変更単位で更新する。

Static Build後の`site:check`はLanding CTA、InstallからLocal Runtimeまでの連続Link、11 Section、301 Redirect、全PageのVersion Notice、Starlight標準Skip Link／Mobile Menu／Search Shortcut／Theme Selector、Pagefind日本語Indexと実Searchを検証する。LandingのProduct Page装飾は`html[data-has-hero]`配下へ限定し、本文Layoutへ広げない。Starlight標準のContrastとKeyboard Interactionを維持し、`prefers-reduced-motion: reduce`ではCard Transitionを停止する。

## Reader-facing Terminology

本文は日本語の能動態を主体にし、Class、Method、Attribute、Command、JSON Field等の正確なSymbolは英語のCode表記を維持する。BlackOps固有Termは初出段落で一行定義するかGlossaryへLinkし、一般的なPHP／HTTP／SQL用語は重複定義しない。同じ概念の日本語名と英語名を無秩序に切り替えない。

Reader Experienceを改善するときもStable `1.0.0`と`main`の差、Failure、未実装機能、Security／運用制約を削らない。利用者が次に行うActionと、その制約が必要な理由へ言い換える。

## Mermaid Diagrams

Mermaid DiagramはStarlight公式Resourcesに掲載された`astro-mermaid`と`mermaid`をExact PinしたLocal Dependencyで描画する。外部CDNへ接続せず、Static HTMLの`pre.mermaid`描画Targetを、同梱したClient RuntimeがBrowser内でSVGへ変換する。各`mermaid` Fenceは`accTitle`と`accDescr`を持ち、直後の自然な本文またはTableにも同じ関係を記載する。

`diagrams:check`は`check`と`build`の前に固定したMermaid Parserで4つのSourceをParseし、構文ErrorとAccessible Metadata欠落をFail-fastする。`check-artifact`と`site:check`は4つの描画Target、Local Renderer Chunk、Text Alternative、外部Diagram CDN不在を検証する。Static ArtifactにSourceとTargetだけがあり、Rendererが同梱されない状態は許可しない。

`autoTheme: true`によりStarlightの`data-theme`を追跡し、LightではMermaid `default`、Darkでは`dark` Themeで再描画する。Diagramの色やClient Runtimeを変更する場合は、両Themeでの可読性、Accessible Name／Description、外部Network Request不在をBrowserで再確認する。

`src/styles/diagram-responsive.css`はContent Containerの最小幅を解除し、`pre.mermaid`を本文幅へ制約する。50 rem以下のViewportではSVGの最小幅を60 remとしてNodeとLabelの可読性を保ち、Diagram Target内のHorizontal Scrollで図の全領域へ移動できるようにする。Page全体にはHorizontal Overflowを発生させない。Responsive RuleはSource Test、Artifact Guard、Site Checkで維持する。

## Delivery

`.github/workflows/docs.yml`はPull Requestと`main`で同じTest／Check／Buildを実行し、検証済みの`dist/`だけをCloudflare Pages Project `blackops-docs`へDirect Uploadする。Fork Pull RequestはSecretなしでBuildまで実行し、DeployだけをSkipする。Cloudflare Project、GitHub Environment、Secret、Rollbackの設定は[Documentation Website Delivery](../internal/documentation-website.md)を参照する。

Wranglerは`package.json`とLockfileでExact Pinする。`pnpm-workspace.yaml`の`allowBuilds`は、Astroが使う`esbuild`に加え、Wranglerの実行Dependencyである`sharp`と`workerd`のInstall Scriptだけを許可する。
