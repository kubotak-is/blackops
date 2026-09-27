# Documentation Brand Expression and Active Navigation

## Scope

Blume Custom LandingをDeveloper Toolとして再構築し、詳細PageのSidebar Current Page表示を修復する。

## Preserved Reader Copy

H2は`BlackOpsの特徴`とする。

### Operation

`#[Route]`で同期API、`#[Deferred]`で非同期化。HTTPリクエストもコンソールコマンドもJobも、すべてはOperationで統一される。

Link Labelは`Operationを始める`、Targetは`/getting-started/first-operation`とする。

### Journal

受理・試行・リトライ・拒否・完了をFWが自動でJournalへ記録。「なぜ失敗したか」をフレームワークが記録する。

Link Labelは`Lifecycleを読む`、Targetは`/concepts/lifecycle`とする。

### Headless

BlackOpsはフロントエンドを持ちません。代わりに、Javascript向けに接続クライアントのコードを自動生成します。フロントエンドはNext.jsでもNuxtJSでもSvelteKitでもお好きなフレームワークと組み合わせることができます。

Link Labelは`Frontendを接続する`、Targetは`/frontend`とする。

## Landing Composition

### Header

- Visible Wordmarkは`BlackOps`
- Browser TitleとMetadataは`BlackOps - The PHP Framework`
- Existing Banner、Search、Theme Toggleを維持する
- GitHub導線はHeaderに常時表示する（D167でHero内への重複配置を不要とする）
- HeaderにはBlume native GitHub icon linkを表示し、全PageでRepository URL `https://github.com/kubotak-is/blackops`、Accessible Label、`target="_blank"`、`rel="noreferrer"`を満たす

### Hero

- Deep developer-tool canvasと中央集約した構図を使う
- H1内で`BlackOps`を主文字、`The PHP Framework`を小さい補助文字にする
- 長い説明文と新しいMarketing Claimを追加しない
- D167により、最初のViewportはH1、価値文、What's BlackOps／Install CTAとHero Reelを優先する。コピー可能なStable Install CommandとOperation Codeは直後の「Operationを書く」Sectionへ置ける。HeaderのGitHub導線を維持する
- Stable Install CommandのVersionは`release-authority.json`のcurrentStable Skeleton tupleへ一致させる。動画のVersion非表示は公開CommandのVersion省略を許可しない

- Hero直下に実物のOperation Modelを示す。少なくとも実在する`#[Route]`、`#[Deferred]`、Typed Operation SignatureとInline／DeferredまたはLifecycleの表現を含める
- Syntax Fragmentは実装済みPublic APIだけを使い、架空APIを追加しない

### Features

- Operationは実PHP Authoring ShapeをVisualへ使う
- Journalは実在するLifecycle EventまたはStateを時系列としてVisualへ使う
- Headlessは実在する`build:compile`、`frontend:generate`、`frontend:check`またはGenerated Client呼出をVisualへ使う
- 三Featureを同一Templateの均等Cardにしない
- Body CopyとLinkはVisual Decorationへ埋没させない
- MobileはOperation、Journal、Headlessの一列Reading Order

## Visual System

- `DESIGN_VARIANCE: 8`
- `MOTION_INTENSITY: 5`
- `VISUAL_DENSITY: 5`
- 1440×900ではH1、価値文、What's BlackOps／Install CTA、Hero Reelの主要部分が最初のViewportで理解できる。完全なInstall Commandは直後のSectionに表示し、HeroからInstall Guideへ直接進める（D167）
- 390 pxではHorizontal Overflow、Tagline三行化、CTA切断、CodeによるViewport破壊を起こさない
- Light／DarkでForeground Contrastを維持する
- Radius、Surface、Accent、Code Syntax、Focus Ringを一貫したTokenで表現する
- Animationは背景の奥行きまたはLifecycle Sequenceの理解へ寄与するものだけに限定する
- `prefers-reduced-motion: reduce`ではAnimationと不要なTransitionを停止する。例外として、Landing Hero ReelとExecution Walkthroughは製品説明の主要表示として自動再生を続け、一時停止操作を常に提供する（2026-09-27 Owner判断、[D167](../decisions/167-landing-hero-reel-and-motion-controls.md)）。明示した一時停止は矢印を含む各Playerの動きを止め、再開操作まで維持する。JavaScript無効時の動画停止操作も残す
- Hand-drawn SVG、Fake Dashboard、Testimonial、利用企業Logo、未指定Marketing Copyを追加しない

## Active Navigation

- `blume.config.ts`のSidebar `href`は全て末尾SlashなしのCanonical Routeとする
- Redirect `from`／`to`は既存契約を維持する
- 各non-index Public PageのBuilt HTMLで、対応するSidebar Anchorが次を満たす
  - `href`がPage Routeへ一致する
  - `aria-current="page"`を持つ
  - Current Page Anchorは一つだけ
- Active Anchorは背景、左Accent Marker、Foreground Contrast、Font Weightで現在地を明示する
- Hover／Focus／Collapsed Navigation／Mobile Navigationを壊さない

## Link Integrity

- Landingの全same-origin LinkはBuild Artifact上の実PageまたはStatic Assetへ解決する
- GitHub Linkは`https://github.com/kubotak-is/blackops`
- Operation、Journal、Headless Linkは各指定Targetへ一致する
- Current Sidebar RouteとSidebar ConfigのCanonical形式をTestで固定する

## Out of Scope

- `OperationValue`、`Operation`、`Outcome`のInterface変更
- `SelfValidatingOperationValue`その他のValidation Hook
- Guide本文とStable Quickstartの再構成
- Public Slug、Redirect、Search Provider、Blume Versionの変更
- Framework `src/**`、Public API、Migration
- External Publication／Deploy

## Verification

- Website unit tests、Blume validate／check、static build、artifact／site checkが成功する
- 1440 px Desktop、390 px Mobile、Light、Darkを実Browserで確認する
- Detail PageでActive Sidebar Linkを実Browserで確認する
- Keyboard FocusとReduced Motionを確認する
- Framework Mago format、Management ID Guard、`git diff --check`が成功する

## Traceability

- Decision: [D119 Documentation Brand Expression and Active Navigation](../decisions/119-documentation-brand-expression-and-active-navigation.md)
- Previous Landing Contract: [Specification 85](85-documentation-landing-visual-hierarchy.md)
- Blume Experience: [Specification 83](83-blume-documentation-experience.md)
- Learning Journey: [Specification 84](84-documentation-learning-journey.md)
