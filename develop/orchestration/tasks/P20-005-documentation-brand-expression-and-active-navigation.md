# P20-005: Documentation Brand Expression and Active Navigation

Status: Accepted

## Goal

Astro公式Websiteを参考にBlackOps LandingをDeveloper Toolとして全面再設計し、Blume Sidebarの現在Page表示をCanonical RouteとArtifact Testで修復する。

## Source of Truth

- `develop/decisions/119-documentation-brand-expression-and-active-navigation.md`
- `develop/spec/86-documentation-brand-expression-and-active-navigation.md`
- `develop/spec/85-documentation-landing-visual-hierarchy.md`
- `develop/spec/84-documentation-learning-journey.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/spec/57-documentation-website-delivery-contract.md`

矛盾時はD119／Specification 86がLanding Presentation、Header Wordmark、Sidebar Current Routeを置き換える。Operation／Journal／Headless本文、Public Slug、Delivery境界は維持する。

## In Scope

- Landing full visual rebuild
- Header Wordmarkの`BlackOps`化
- Authentic Operation Code／Lifecycle／Generated Client Visual
- Existing Install／GitHub／Stable Command／Feature Linkの再配置
- Blume native Header GitHub Repository icon link（全Page、Accessible Label、target／rel）
- Sidebar `href`の末尾SlashなしCanonical化
- Sidebar Active Stateの明確化
- 全詳細Pageの`aria-current="page"` Artifact Guard
- Desktop 1440 px／Mobile 390 px、Light／Dark、Keyboard、Reduced Motion
- Report／STATE／TODO／Decision／Specification同期

## Out of Scope

- `OperationValue`、`Operation`、`Outcome`のInterface変更
- `SelfValidatingOperationValue`その他のValidation Hook
- Guide本文、Stable Quickstart、Public Slug、Redirect変更
- Blume Package Update
- Framework `src/**`、Public API、Migration
- External Publication／Deploy

## Files Allowed to Change

- `docs/website/pages/index.astro`
- `docs/website/theme.css`
- `docs/website/blume.config.ts`
- `docs/website/scripts/check-site.mjs`
- `docs/website/tests/**`
- `develop/decisions/118-documentation-landing-visual-hierarchy.md`
- `develop/decisions/119-documentation-brand-expression-and-active-navigation.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/spec/84-documentation-learning-journey.md`
- `develop/spec/85-documentation-landing-visual-hierarchy.md`
- `develop/spec/86-documentation-brand-expression-and-active-navigation.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-005-documentation-brand-expression-and-active-navigation.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Acceptance Criteria

- [ ] LandingがSpec 86のHero／Product Visual／Feature構成を満たす
- [ ] `BlackOps`と小さい`The PHP Framework`が明確な階層で表示される
- [ ] Header Wordmarkが`BlackOps`だけを表示する
- [ ] Install、GitHub、Stable Install Commandが最初のViewportで理解できる
- [ ] 実在Public APIのOperation Code、Lifecycle、Generated Client／CLIがVisualに使われる
- [ ] Operation／Journal／Headless本文と各Link Targetが維持される
- [ ] 未指定Marketing Copy、Fake Dashboard、利用企業Logoを追加しない
- [ ] Desktop 1440 px／Mobile 390 px、Light／Darkで構図が成立する
- [ ] Sidebar Configの全`href`が末尾SlashなしCanonical Routeになる
- [ ] 全non-index Public Pageで対応Sidebar Anchorへ`aria-current="page"`が一つ付く
- [ ] Active Sidebar AnchorがLight／Darkで明確に視認できる
- [ ] Existing Public Slug、Redirect、Search、Banner、Artifactを維持する
- [ ] Header native GitHub icon linkが全Built Pageで正確なRepository URL、Accessible Label、`target="_blank"`、`rel="noreferrer"`を満たす
- [ ] Website full gateが成功する
- [ ] Framework `src/**`とValidation Public APIを変更しない
- [ ] Report／STATEがEvidenceへ一致する
- [ ] WorkerはCommitしない

## Completion Report

`develop/orchestration/reports/P20-005-documentation-brand-expression-and-active-navigation.md`へSummary、Changed Files、Design Translation、Authentic Product Visuals、Active Navigation Root Cause／Fix、Responsive／Theme／Accessibility、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
