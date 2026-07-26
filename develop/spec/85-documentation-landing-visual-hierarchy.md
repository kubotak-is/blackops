# Documentation Landing Visual Hierarchy

Landing PresentationはD119／Specification 86が置き換える。指定本文、CTA Target、Landing Link Integrityは維持する。

## Scope

Blume Custom Landingだけを再構成し、BlackOpsの名前、Stable Install、Operation／Journal／Headless、Documentation、GitHubの入口を明確にする。

## Hero

- H1は`BlackOps`を主文字列、`The PHP Framework`を補助文字列として別Elementに分ける
- `BlackOps`はDesktop／Mobileの主たる視覚階層を持つ
- `The PHP Framework`は`BlackOps`より小さく、同等のDisplay Sizeにしない
- 旧Hero説明の長文は表示しない
- Primary CTAはInstall、Secondary CTAはGitHubとする
- GitHub URLは`https://github.com/kubotak-is/blackops`とする
- Stable Install Commandとして次を表示する

```text
composer create-project blackops/skeleton my-app 1.1.0
```

## Features

Section H2は`BlackOpsの特徴`とする。

### Operation

`#[Route]`で同期API、`#[Deferred]`で非同期化。HTTPリクエストもコンソールコマンドもJobも、すべてはOperationで統一される。

Link Labelは`Operationを始める`、Targetは`/getting-started/first-operation/`とする。

### Journal

受理・試行・リトライ・拒否・完了をFWが自動でJournalへ記録。「なぜ失敗したか」をフレームワークが記録する。

Link Labelは`Lifecycleを読む`、Targetは`/concepts/lifecycle/`とする。

### Headless

BlackOpsはフロントエンドを持ちません。代わりに、Javascript向けに接続クライアントのコードを自動生成します。フロントエンドはNext.jsでもNuxtJSでもSvelteKitでもお好きなフレームワークと組み合わせることができます。

Link Labelは`Frontendを接続する`、Targetは`/frontend/`とする。

## Layout

- DesktopはOperationを主Featureとする非対称Grid
- JournalとHeadlessは主Featureへ従属するが、本文とLinkのContrastを落とさない
- 3つのFeatureを同じ幅のCardへしない
- MobileはOperation、Journal、Headlessの一列Reading Order
- 実PHP Codeを使う場合はPublic APIとBuild可能な標準Authoring Shapeへ一致させる
- 角丸、Border、Accent、Focus Ringは一貫したTokenを使う
- Light／Dark、Keyboard、390 px Mobile、Reduced Motionを維持する

## Link Integrity

- Build済みLandingのInternal Linkを全件収集する
- `/`、Query、Fragmentを正規化し、対応する`dist/**/index.html`またはStatic Fileが存在することを確認する
- GitHub CTAが正確なRepository URLであることを確認する
- Operation、Journal、Headless LinkはHTTP 200相当のStatic Pageへ解決する
- Custom Landing LinkはBlume Markdown Validatorの対象外でもPermanent Testから漏らさない

## Preserved Contract

- Operation／Journal／Headless本文
- Existing Public SlugとRedirect
- Sidebar、Search、Banner
- `docs/guide/`の公開本文正本
- Static `dist/` Artifact
- Cloudflare Delivery境界
- Framework `src/**`とPublic API

## Verification

- H1の`BlackOps`と`The PHP Framework`が別Element／別Scaleになる
- 旧Hero長文と`BlackOpsの3つの特徴`がLandingに存在しない
- `BlackOpsの特徴`が存在する
- InstallとGitHub CTA、Stable Install Commandが存在する
- Operation／Journal／Headless本文が維持される
- 三FeatureがDesktop非対称／Mobile一列になる
- Landing Internal Link GuardがBroken Routeを拒否する
- Website test、check、buildが成功する
- Desktop／390 px、Light／Dark、Keyboard、Reduced Motionを確認する

## Traceability

- Decision: [D118 Documentation Landing Visual Hierarchy](../decisions/118-documentation-landing-visual-hierarchy.md)
- Blume Experience: [Specification 83](83-blume-documentation-experience.md)
- Learning Journey: [Specification 84](84-documentation-learning-journey.md)
