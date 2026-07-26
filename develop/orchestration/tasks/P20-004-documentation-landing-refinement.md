# P20-004: Documentation Landing Refinement

Status: Accepted

## Goal

Custom LandingのLink Integrityを恒久化し、Astro公式Websiteを参考に、BlackOpsの名前、Stable Install、Operation／Journal／Headless、GitHubを明確な視覚階層で伝える。

## Source of Truth

- `develop/decisions/118-documentation-landing-visual-hierarchy.md`
- `develop/spec/85-documentation-landing-visual-hierarchy.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/spec/84-documentation-learning-journey.md`
- `develop/spec/57-documentation-website-delivery-contract.md`

矛盾時はD118／Specification 85がLanding Hero、CTA、Feature Layout、Link Validationを置き換える。Operation／Journal／Headless本文とDelivery境界は維持する。

## In Scope

- Landing H1の`BlackOps`／`The PHP Framework`階層化
- 旧Hero長文のLanding表示削除
- Install／GitHub CTAとStable Install Command
- `BlackOpsの特徴`へのH2変更
- Operation主Featureの非対称Layout
- Operation／Journal／Headless Link Target修正
- Build Artifact上のCustom Landing Internal Link Guard
- Desktop／390 px、Light／Dark、Keyboard、Reduced Motion
- Report／STATE／TODO／Decision／Specification同期

## Out of Scope

- `OperationValue`、`Outcome`、`Operation`のMarker Interface変更
- Custom Validation Hook
- Stable Quickstart本文の再構成
- Sidebar、Public Slug、Redirect変更
- Blume Package Update
- Framework `src/**`、Public API、Migration
- External Publication／Deploy

## Files Allowed to Change

- `docs/website/pages/index.astro`
- `docs/website/theme.css`
- `docs/website/blume.config.ts`
- `docs/website/scripts/check-site.mjs`
- `docs/website/tests/**`
- `develop/decisions/116-blume-documentation-site.md`
- `develop/decisions/118-documentation-landing-visual-hierarchy.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/spec/84-documentation-learning-journey.md`
- `develop/spec/85-documentation-landing-visual-hierarchy.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-004-documentation-landing-refinement.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Design Contract

- Redesign mode: Landing-only targeted overhaul
- `DESIGN_VARIANCE: 7`
- `MOTION_INTENSITY: 3`
- `VISUAL_DENSITY: 4`
- Existing Blume Header／Search／Banner／Themeを壊さない
- 同じ幅の三列Feature Cardを作らない
- Heroへ長い説明を戻さない
- Fake Screenshot、未実装Claim、自動Animation、Section Numberを追加しない
- Visible Copyへem dashを追加しない

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

- [ ] Operation／JournalのLinkが実Pageへ解決し、Custom Landing Link Guardで保護される
- [ ] `BlackOps`と`The PHP Framework`が別Scaleで表示される
- [ ] 旧Hero長文がLandingから削除される
- [ ] H2が`BlackOpsの特徴`になる
- [ ] InstallとGitHub CTA、Stable Install Commandが存在する
- [ ] GitHub CTAが指定Repositoryへ接続する
- [ ] Operation／Journal／Headless本文が維持される
- [ ] Desktop非対称／390 px一列Layoutが成立する
- [ ] Light／Dark、Keyboard、Reduced Motionを維持する
- [ ] Existing Slug、Redirect、Sidebar、Search、Artifactを維持する
- [ ] Website full gateが成功する
- [ ] Report／STATEがEvidenceへ一致する
- [ ] WorkerはCommitしない

## Completion Report

`develop/orchestration/reports/P20-004-documentation-landing-refinement.md`へSummary、Changed Files、Link Integrity、Visual Hierarchy、Responsive／Theme／Accessibility、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
