# P20-004 Documentation Landing Refinement Report

## Summary

Custom Landingを短い入口と明確な階層へ再構成した。`BlackOps`を主見出し、`The PHP Framework`を補助見出しとして分離し、Install、GitHub、Stable 1.1.0 create-project command、Operation主の非対称Featureを配置した。

## Changed Files

- `docs/website/pages/index.astro`
- `docs/website/theme.css`
- `docs/website/blume.config.ts`
- `docs/website/scripts/check-site.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P20-004-documentation-landing-refinement.md`

## Link Integrity

Operation、Journal、Headlessのリンクをそれぞれ `/getting-started/first-operation`、`/concepts/lifecycle`、`/frontend`へ修正した。Blume devの正規route形式（末尾slashなし）に合わせ、InstallとNext linksも同期した。GitHub CTAは`https://github.com/kubotak-is/blackops`へ固定した。`check-site.mjs`はBuild Artifact上のLanding hrefを収集し、extensionless same-origin URLを`<path>/index.html`へ、拡張子付きassetをそのまま解決する。既定CTAとGuard failure messageのcontract assertionも追加した。

## Visual Hierarchy

Hero長文と`BlackOpsの3つの特徴`を削除し、H2を`BlackOpsの特徴`へ変更した。Operationを2行分の主Feature、Journal／Headlessを補助Featureとする非対称Gridへ変更し、MobileではOperation→Journal→Headlessの一列になる。Feature本文は変更していない。Fake UI、未実装Claim、Section番号、自動Animation、visible em dashは追加していない。

## Responsive／Theme／Accessibility

既存のLight／Dark semantic tokens、focus-visible ring、Keyboard link、reduced-motionを維持した。390px相当の狭幅ではFeatureをflex columnへ戻し、指定順で読み上げる。

Taglineは小さいScaleを維持したまま`white-space: nowrap`で390pxでも1行表示する。Version BannerはBlumeの`link: { href, text }` APIを使い、Releases CTAを実リンクとして描画する。Escaped raw HTML banner markupは禁止する。

## Commands and Results

- `mise exec -- pnpm --dir docs/website run test` — 50 passed。
- `mise exec -- pnpm --dir docs/website run build` — Blume validate、38-page static build、Artifact、Search、Landing link guardを含めてpassed。
- Playwright 1.61.1／Chromium — Desktop 1440px、Mobile 390px、Dark Themeを目視Reviewし、Tagline一行、非対称Feature、Mobile一列、Banner CTAを確認。
- `curl` against `http://localhost:4322` — Landing、Install、Operation、Journal、Frontend、Quickstart、Executionの7 routeがHTTP 200。
- `docker compose run --rm app mago format --check src tests` — passed。
- `! rg -n 'Spec(ification)?...' src tests --glob '*.php'` — passed.
- `git diff --check` — passed.

## Acceptance Criteria

- [x] Operation／Journal／Headless links resolve to specified pages and are permanently guarded
- [x] `BlackOps` and `The PHP Framework` have separate scales/elements
- [x] Legacy Hero long paragraph removed from Custom Landing
- [x] H2 is `BlackOpsの特徴`
- [x] Install, exact GitHub CTA, and Stable install command present
- [x] Preserved Feature copy and public delivery boundaries
- [x] Asymmetric desktop layout and mobile one-column order
- [x] Light／Dark, Keyboard, focus, and reduced-motion behavior retained
- [x] Full production build and static artifact guard passed
- [x] Report／STATE／TODO synchronized
- [x] Worker did not commit

## Remaining Issues

なし。

## Suggested Next Action

OperationValueのCustom／Cross-field Validation APIを、Marker Interface互換性とDeferred受理境界を維持した別Decisionで議論する。
