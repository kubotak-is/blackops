# Documentation Site UX

## Scope

Blume WebsiteへCallout、Japanese Copy Label、Previous／Next、正しいEdit Link、日本語Font Stack、Responsive Inline Codeを接続する。Information Architectureと公開本文の正本は維持する。

## Design Direction

- Mode: Preserve redesign
- Audience: PHP Application Developer
- Design variance: 5
- Motion intensity: 3
- Visual density: 5
- Foundation: Blume 1.1.4 native components and BlackOps existing theme

LandingはCode-firstなProduct表現、Guideは可読性と探索性を優先する。新しいImage、Marketing Section、Decorative Animation、Generic Card Gridを追加しない。

## Callout Contract

- Sourceは`docs/guide/*.md`を維持し、CalloutはBlumeの`:::note`、`:::info`、`:::warning`、`:::danger`、`:::success` Directiveを使う
- Content PipelineはMermaid FenceまたはCallout Directiveを持つPageを`.mdx`へ生成する
- DirectiveはFence外のBlockだけを検出し、Code Example内の文字列をMDX判定へ使わない
- CalloutはStable／`main`差、破壊的変更、運用上の注意等、見落とすと誤操作につながる情報へ限定する
- 少なくともInstall、Quickstart and Skeleton、First Operation、Authentication、Frontend、Outbox、DeploymentのChannel／Risk境界をCalloutで判読できるようにする
- 同じ注意をCallout直後の本文で繰り返さない
- Static ArtifactとRaw Markdown／LLM Artifactの可読性を維持する

## Code Copy Contract

- Fenced Code BlockはBlume native Copy Buttonを使う
- Japanese UI OverrideでAccessible Labelを`コードをコピー`とする
- Native Clipboard処理とCheck表示を維持し、Copy成功時は更新Labelまたは`aria-live` Statusで`コピーしました`を通知する
- Clipboard失敗時は更新Labelまたは`aria-live` Statusで`コピーできませんでした`を通知する
- 成功／失敗後もKeyboard FocusをButtonへ保持し、状態を一定時間後に初期Labelへ戻す
- Code Text、Indent、改行をClipboardへ正確に渡す
- Keyboard Focusで操作でき、ButtonがCodeや横Scrollを遮らない
- LandingのCustom Code Panelへ別実装のCopy Buttonを追加しない

## Previous and Next Contract

- Pagination順は`site-navigation.mjs`の全non-index Public Page順と完全一致する
- First PageはNextだけ、Last PageはPreviousだけ、中間Pageは両方を表示する
- Link LabelはSidebar Label、Hrefは既存Public Routeを使う
- `前へ`、`次へ`、`ページネーション`を日本語で表示する
- Mobileでは一列にCollapseし、長いLabelはPage Overflowを出さない
- Pagination用にSidebar全量を別Fileへ複製しない

## Edit Link Contract

- non-index Public GuideのEdit URLはCurrent Routeを`content-map.mjs`へ逆引きして作る
- URL Prefixは`https://github.com/kubotak-is/blackops/edit/main/docs/guide/`
- Target Sourceは存在するTracked `docs/guide/*.md`だけとする
- `docs/website/src/content/docs/**`、`.generated/**`、`dist/**`、Repository Absolute Pathを出力しない
- Labelは`GitHub で編集`、External Link属性は`target="_blank"`と`rel="noreferrer"`を維持する
- Mapping不能なRouteはEdit Linkを出力せず、推測でSourceを組み立てない

## Japanese UI and Typography

- Blume Japanese Packに不足するVisible Labelを`i18n.ui.ja`で補う
- 少なくともCode Copy、Generating、Export、Theme Toggle、Navigation Toggle、GitHub Repositoryを日本語化する
- BodyとHeadingは既存Self-hosted Latin Fontを先頭にし、Japanese FallbackをHiragino Sans、Yu Gothic UI／Yu Gothic、Noto Sans JP、System Sansの順で指定する
- Mono Fontは既存IBM Plex MonoとSystem Monoを維持する
- External Runtime Font Requestを追加しない
- Articleの長いInline Codeは折り返す。Fence、Table、MermaidはHost内の局所横Scrollを使う

## Landing Boundary

- HeroのBlackOps、`The PHP Framework`、Install／What's BlackOps CTA、Stable Commandを維持する
- Operation／Journal／Headlessの指定Copy、Link、同格Desktop三列／Mobile一列を維持する
- Decorative `01`／`02`だけを削除する
- Landingへ新しいFeature、Image、Version Footer、Scroll Cue、Section Number、Marketing Copyを追加しない
- Light／Dark、Reduced Motion、Keyboard Focus、No Page Overflowを維持する

## Information Architecture Boundary

- Existing Public Slug、Sidebar Label／順序、Redirect、Header、Banner、Searchを変更しない
- 全Page文章編集、表記Guideline、一般語の日本語化はP20-012で扱う
- Blume Upgrade、Framework `src/**`、Stable Tag、External Deployを変更しない

## Verification

- Content PipelineがCallout PageをMDXへ決定的に生成し、DirectiveをArtifactで描画する
- Code Copyに日本語Accessible Labelと成功／失敗のAccessible Statusがあり、Native Clipboard処理とCheck表示を維持する
- PaginationがCanonical Sidebar順でFirst／Middle／Last Pageを正しくLinkする
- Edit LinkがCurrent RouteのTracked `docs/guide` Sourceへ解決する
- Japanese Font StackがContent PageとLandingで有効になる
- 全Public Page ArtifactにGenerated Source Edit URL、Literal Callout Directive、Decorative Section Numberを残さない
- Desktop 1440px Light／DarkとMobile 390pxで代表PageのCallout、Copy成功／失敗通知、Pagination、Edit、Focus、Overflowを実Browser確認する
- Website test、check、build、Mago format、Management ID Guard、`git diff --check`が成功する
- Documentation ReviewerがAccuracy、IA、Editorial、Visual、AccessibilityをRead-onlyで再Reviewする

## Traceability

- Decision: [D132 Documentation Site UX](../decisions/132-documentation-site-ux.md)
- Learning Journey: [Specification 84](84-documentation-learning-journey.md)
- Review Agent: [Specification 92](92-documentation-review-agent.md)
