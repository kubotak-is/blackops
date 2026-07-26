# Documentation Second Review and Feature Parity

## Scope

第2回Documentation Reviewの即時退行を閉じ、LandingのOperation／Journal／Headlessを同格のFeatureとして表示し、Documentation Websiteの編集導線、Navigation正本、Style正本を整合させる。

## Landing Feature Contract

### Preserved Copy and Links

#### Operation

`#[Route]`で同期API、`#[Deferred]`で非同期化。HTTPリクエストもコンソールコマンドもJobも、すべてはOperationで統一される。

Link Labelは`Operationを始める`、Targetは`/getting-started/first-operation`とする。

#### Journal

受理・試行・リトライ・拒否・完了をFWが自動でJournalへ記録。「なぜ失敗したか」をフレームワークが記録する。

Link Labelは`Lifecycleを読む`、Targetは`/concepts/lifecycle`とする。

#### Headless

BlackOpsはフロントエンドを持ちません。代わりに、Javascript向けに接続クライアントのコードを自動生成します。フロントエンドはNext.jsでもNuxtJSでもSvelteKitでもお好きなフレームワークと組み合わせることができます。

Link Labelは`Frontendを接続する`、Targetは`/frontend`とする。

### Layout

- 960 px以上では三Featureを`1fr 1fr 1fr`相当の均等三列にする
- 960 px未満では三FeatureをOperation、Journal、Headlessの一列にする
- 三Featureの外枠、見出し、Visual領域、本文領域、Link位置は同じ寸法規則を使う
- Visual領域は同じ最小高を持ち、本文の開始位置を揃える
- Linkは各Featureの下端で揃える
- Operationだけを複数RowへSpanさせない
- JournalとHeadlessだけを同じColumnへ積み上げない
- Operation Code、Journal Lifecycle、Headless CLI／Generated Clientという異なる実物表現は維持する
- Generic Card Grid、過剰な角丸、Decorative Iconだけの置換、未指定Marketing Copyを追加しない
- Light／Dark、Keyboard Focus、Reduced Motion、Horizontal Overflowなしを維持する

### Preserved Landing and Navigation

- Hero Canvas、BlackOps主見出し、小さい`The PHP Framework`、Install／GitHub CTA、Stable Install Commandを維持する
- Header GitHub iconは`https://github.com/kubotak-is/blackops`へ解決し、Accessible Label、`target="_blank"`、`rel="noreferrer"`を持つ
- 全non-index Public PageのSidebar Current Linkは一意の`aria-current="page"`を持つ
- Public Slug、Redirect、Search、Banner、Static Artifactを変更しない

## Immediate Documentation Corrections

### Page Title Links

- H1変更前のPage titleをLink Labelとして残さない
- 少なくとも`Testing Overview`、`DatabaseとTransaction`、`Current Status`、`チュートリアル: Operationを作る`の旧Labelを現Page titleへ同期する
- 文脈を説明するLabelはH1との完全一致を強制しない
- CIは廃止済みPage titleの再流入を検出する

### Sensitive

`#[Sensitive]`の対象はValue PropertyとOutcome Propertyの両方であることをCore APIへ記載する。

### ExecuteWith

- `#[ExecuteWith]`はInline以外専用ではなく、実行Strategyを明示するAttributeとして説明する
- CanonicalなDeferred記法は引数なし`#[Deferred]`である
- Ephemeral OperationはStrategy Attribute省略時にInlineへ解決され、既存`#[ExecuteWith(Inline::class)]`は互換形として受理する
- 互換性目的の`#[ExecuteWith(Deferred::class)]`とCanonicalな`#[Deferred]`の関係を混同させない

### Deferred Outcome Example

`ReportGenerated`に存在しない`location` Propertyを使わない。実在する`reportName`と`operationId`に例と説明を揃える。

## Site Integrity

### Edit Link

- Built Pageに存在しないGenerated SourceへのGitHub edit anchorを出力しない
- Header GitHub iconとLanding GitHub CTAは維持する
- edit linkをCSSで隠すだけの実装は禁止する
- Artifact Testは`/edit/main/`等の無効なSource URLが出力されないことを検証する

### Sidebar Single Source

- Public Sidebar構造、Label、Slug、順序の正本は`site-navigation.mjs`とする
- `blume.config.ts`は同じ正本からBlume形式を得る
- Site Guardも同じ正本を参照する
- ConfigとGuardへSidebar全量を複製しない
- Missing、Duplicate、Unknown、順序、Label／H1、Canonical href、Current Pageの既存Guardを維持する

### Legacy Style

- Blumeが参照しないStarlight専用CSSは削除する
- Diagram表示に必要なRuleだけを維持する場合はBlume Tokenへ移植する
- SourceとBuilt CSSに未定義の`--sl-*`参照を残さない

## Out of Scope

- Stable Installation／Quickstart／First Operation／Authenticationの全面再構築
- Reference欠番、Testing、Deploymentの増補
- 全Guideの文章編集
- Shared Callout、Pagination、日本語Fontの導入
- Public Slug、Redirect、Blume Versionの変更
- Framework `src/**`、Public API、Migration
- External Publication／Deploy

## Verification

- Website unit tests、Blume validate／check、static build、artifact／site checkが成功する
- Desktop 1440 px／Mobile 390 px、Light／Darkを実Browserで確認する
- 三FeatureのBounding Box、Visual領域、本文開始、Link下端が同じLayout規則へ従う
- Header GitHub icon、Sidebar Active State、全Landing Internal Linkを実BrowserまたはArtifactで確認する
- Built Pageに無効なGitHub edit linkが存在しない
- Sidebar Configが単一正本から生成される
- 未定義`--sl-*`参照が存在しない
- Framework Mago format、Management ID Guard、`git diff --check`が成功する

## Traceability

- Decision: [D120 Documentation Second Review and Feature Parity](../decisions/120-documentation-second-review-and-feature-parity.md)
- Review: [Documentation Review Second Pass](../../docs/documentation-review.md)
- Previous Landing Contract: [Specification 86](86-documentation-brand-expression-and-active-navigation.md)
- Learning Journey: [Specification 84](84-documentation-learning-journey.md)
