# D119: Documentation Brand Expression and Active Navigation

Status: Decided

Supersedes D118／Specification 85のLanding PresentationとSidebarを変更しない規定。Operation／Journal／Headlessの指定本文、Public Slug、Documentation Delivery境界は維持する。

## Context

D118に基づくLandingはFramework名、Install、GitHub、三Featureを配置したが、余白、巨大文字、罫線へ依存し、Developer Toolとしての奥行きと実物感が不足した。Astro公式Websiteを参考にする要件に対して、現在の実装は中央集約された価値訴求、実行可能な開始導線、Codeを中心にしたProduct Expressionを十分に反映できていない。

詳細PageではBlumeのNavigation Componentが`aria-current="page"`を実装している一方、`blume.config.ts`のSidebar `href`だけが末尾Slash付き、Blumeが生成するCurrent Routeが末尾Slashなしとなっていた。両者の厳密比較が失敗し、現在PageがSidebarで選択されない。

## Decision

[DECISION]

1. LandingをAstro公式Websiteの情報設計を参考にしたDeveloper Tool Landingへ再設計する。AstroのBrand、Copy、Gradientを複製せず、BlackOpsのPublic API、Lifecycle、Generated ClientをProduct Visualとして使う。
2. Heroは深さのあるCanvas、中央集約したFramework名、小さい`The PHP Framework`、Install、GitHub、Stable Install Commandを最初のViewportへ置く。長いHero説明と新しいMarketing Claimは追加しない。
3. HeaderのWordmarkは`BlackOps`だけを表示する。Browser TitleとSite Metadataの`BlackOps - The PHP Framework`は維持し、`The PHP Framework`はHeroで小さい補助文字として表示する。
4. HeroまたはFeature内のCode Visualは、実装済みの`#[Route]`、`#[Deferred]`、`Operation`、Typed Value／Outcome、BlackOps CLI、Generated Clientだけを使う。架空Dashboard、未実装API、実在しないStatus、実在しない利用企業Logoは追加しない。
5. `BlackOpsの特徴`とOperation／Journal／Headlessの指定本文は変更しない。三Featureは同じ見た目のCardへせず、Code、Lifecycle、Client Generationをそれぞれ異なる実物表現で補強する。
6. Primary CTAはInstall、Secondary CTAはGitHubとする。Operation、Journal、Headlessの各Link Targetと全Landing Internal Link Guardを維持する。
7. LandingはDesktop 1440 px、Mobile 390 px、Light、Darkで完成した構図を持つ。Animationは意味のある背景またはLifecycle表現に限定し、`prefers-reduced-motion`で停止する。
8. Sidebarの全`href`はBlume Current Routeと同じ末尾Slashなしへ正規化する。Redirect Patternは変更しない。
9. 全詳細Pageで対応するSidebar Linkへ`aria-current="page"`が一つ付くことをBuild Artifactで検証する。
10. 現在PageはBlume既定表示より明確な背景、Accent Marker、Foreground Contrastで示し、Light／Dark、Hover、Focusを維持する。
11. Framework `src/**`、Value Validation Extension、Operation／Outcomeの型設計、Public API、Stable Quickstart本文、External Publication／Deployは変更しない。
12. HeaderのRepository導線はBlume native GitHub設定（`github.owner`／`github.repo`）を使い、`https://github.com/kubotak-is/blackops`へのアイコン、Accessible Label、`target="_blank"`、`rel="noreferrer"`を全Pageで提供する。LandingのGitHub CTAは維持する。

[/DECISION]

## Design Direction

- Redesign mode: Landing full visual rebuild plus Sidebar active-state correction
- `DESIGN_VARIANCE: 8`
- `MOTION_INTENSITY: 5`
- `VISUAL_DENSITY: 5`
- Developer audience: PHP Frameworkを比較し、最初のOperationを試す技術者
- Dominant visual: authentic Operation code and execution lifecycle
- Reuse: Blume Header、Search、Banner、Theme、Sidebar structure
- Avoid: plain paper with hairline sections, empty oversized hero, equal feature cards, fake product screenshot, logo wall

## Consequences

[CONSEQUENCES]

- Landingは説明文だけでなく、BlackOpsを使うと何を書くか、どの経路で実行されるかを最初のViewportから理解できる。
- Sidebarの現在Page表示はBlumeの既存Capabilityを正しいRoute形式で利用し、将来のNavigation変更でもArtifact Testが不一致を検出する。
- Validation APIの設計議論は再開しない。`SelfValidatingOperationValue`その他の型は追加されない。

[/CONSEQUENCES]

## References

- [D118 Documentation Landing Visual Hierarchy](118-documentation-landing-visual-hierarchy.md)
- [Specification 86](../spec/86-documentation-brand-expression-and-active-navigation.md)
- [Astro](https://astro.build/)
- [BlackOps GitHub Repository](https://github.com/kubotak-is/blackops)
