# P20-006: Documentation Second Review and Feature Parity

Status: Accepted

## Goal

LandingのOperation／Journal／Headlessを同格で比較できる均等Layoutへ補正し、第2回Documentation Reviewの即時退行とWebsite正本の不整合を閉じる。

## Source of Truth

- `docs/documentation-review.md`
- `develop/decisions/120-documentation-second-review-and-feature-parity.md`
- `develop/spec/87-documentation-second-review-and-feature-parity.md`
- `develop/decisions/119-documentation-brand-expression-and-active-navigation.md`
- `develop/spec/86-documentation-brand-expression-and-active-navigation.md`
- `develop/spec/84-documentation-learning-journey.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/spec/57-documentation-website-delivery-contract.md`

矛盾時はD120／Specification 87がFeature Layout、即時退行、edit link、Sidebar正本、Legacy Styleを置き換える。Hero、Brand、指定本文、Header GitHub icon、Sidebar Current Page、Public Slug、Delivery境界は維持する。

## In Scope

- Landing FeatureをDesktop均等三列／Mobile一列へ補正
- 三Featureの見出し、Visual、本文、Linkの寸法と基準線統一
- Operation／Journal／Headless固有の実物Visual維持
- H1変更後の旧被Link文言回収と再流入Guard
- Core APIのSensitive対象補正
- ExecuteWith／Deferred／Inline説明の整合
- Deferred Outcome Exampleの実在Property同期
- Header GitHub iconを維持した無効GitHub edit linkのSemanticな無効化
- Sidebar定義の`site-navigation.mjs`一本化
- 未使用Starlight CSSの削除または必要RuleのBlume Token移植
- Existing Artifact／Navigation／Accessibility Guardの維持
- Report／STATE／TODO／Decision／Specification同期

## Out of Scope

- Stable Installation／Quickstart／First Operation／Authenticationの全面再構築
- Reference欠番、Testing、Deploymentの増補
- 全Guideの文章編集
- Shared Callout、Pagination、日本語Font
- Framework `src/**`、Public API、Migration
- Public Slug、Redirect、Blume Version
- External Publication／Deploy

## Files Allowed to Change

- `docs/guide/**`
- `docs/website/pages/index.astro`
- `docs/website/theme.css`
- `docs/website/blume.config.ts`
- `docs/website/site-navigation.mjs`
- `docs/website/content-map.mjs`
- `docs/website/components.ts`
- `docs/website/components/**`
- `docs/website/src/styles/**`
- `docs/website/scripts/**`
- `docs/website/tests/**`
- `develop/decisions/117-documentation-learning-journey.md`
- `develop/decisions/119-documentation-brand-expression-and-active-navigation.md`
- `develop/decisions/120-documentation-second-review-and-feature-parity.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/spec/84-documentation-learning-journey.md`
- `develop/spec/86-documentation-brand-expression-and-active-navigation.md`
- `develop/spec/87-documentation-second-review-and-feature-parity.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-006-documentation-second-review-and-feature-parity.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- Feature本文、Link Label、Link Targetを変更しない
- Accepted HeroとHeader visual hierarchyを再設計しない
- OperationだけをRow Spanさせず、Journal／Headlessだけを積み上げない
- 三FeatureをDecorative IconだけのGeneric Cardへ単純化しない
- Header GitHub iconのために手書きSVGを追加しない。Blumeの既存Asset／Componentを再利用する
- 無効edit linkをCSSだけで隠さない
- Navigation全量を複数Fileへ複製しない
- WorkerはBlume extension pointとBuilt Artifactを確認してからedit linkの実装を選ぶ
- Framework `src/**`、Public API、Public Slug、Redirectを変更しない
- WorkerはCommitしない

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
! rg -n --glob '!pnpm-lock.yaml' -- '--sl-' docs/website
git diff --check
```

## Acceptance Criteria

- [ ] 960 px以上でOperation／Journal／Headlessが均等三列になる
- [ ] 960 px未満でOperation／Journal／Headlessが指定順の一列になる
- [ ] 三Featureの見出し、Visual領域、本文開始、Link位置が同じLayout規則へ従う
- [ ] Operation／Journal／Headless固有の実物Visualと指定本文／Linkを維持する
- [ ] Accepted Hero、Header Wordmark、Install／GitHub CTA、Stable Commandを維持する
- [ ] `Testing Overview`、`DatabaseとTransaction`、`Current Status`、`チュートリアル: Operationを作る`の旧Page title Linkが回収される
- [ ] 廃止済みPage titleの再流入をCIが検出する
- [ ] Core APIがSensitive対象をValue／Outcome Propertyとして説明する
- [ ] ExecuteWith、Canonical Deferred、Ephemeral Inlineの関係が矛盾なく説明される
- [ ] Deferred Outcome Exampleが実在する`reportName`／`operationId`だけを使う
- [ ] Header GitHub iconとLanding GitHub CTAが正確なRepository URLを維持する
- [ ] Built Pageに存在しないSourceへのGitHub edit linkが出力されない
- [ ] Public Sidebarの構造、Label、Slug、順序が`site-navigation.mjs`の単一正本から供給される
- [ ] 全non-index Public PageのCanonical hrefと一意の`aria-current="page"`を維持する
- [ ] 未定義`--sl-*`参照を残さない
- [ ] Desktop 1440 px／Mobile 390 px、Light／DarkでHorizontal Overflowなく構図が成立する
- [ ] Existing Public Slug、Redirect、Search、Banner、Artifactを維持する
- [ ] Website full gateが成功する
- [ ] Framework `src/**`とPublic APIを変更しない
- [ ] Report／STATEがEvidenceへ一致する
- [ ] WorkerはCommitしない

## Completion Report

`develop/orchestration/reports/P20-006-documentation-second-review-and-feature-parity.md`へSummary、Changed Files、Review Findings Resolved、Feature Layout、Edit Link／GitHub Navigation、Sidebar Single Source、Legacy Style、Responsive／Theme／Accessibility、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
