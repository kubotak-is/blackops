# P20-006 Documentation Second Review and Feature Parity

## Summary

第2回Documentation Reviewで確認された即時退行を回収し、Landing Featureの比較可能なResponsive Layout、Guideの正確性、BlumeのNavigation／GitHub導線を同期した。Framework `src/**`、Public API、Public Slug、Redirect、External Publication／Deployは変更していない。Commitは作成していない。

## Changed Files

- `docs/guide/README.md`
- `docs/guide/attributes.md`
- `docs/guide/community-board.md`
- `docs/guide/core-api.md`
- `docs/guide/deployment.md`
- `docs/guide/installation.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/outcome-retrieval.md`
- `docs/guide/security.md`
- `docs/website/theme.css`
- `docs/website/site-navigation.mjs`
- `docs/website/blume.config.ts`
- `docs/website/components.ts`
- `docs/website/components/NoEditLayout.astro`
- `docs/website/src/styles/diagram-responsive.css` (removed; unused legacy Starlight stylesheet)
- `docs/website/scripts/check-site.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `develop/orchestration/tasks/P20-006-documentation-second-review-and-feature-parity.md`
- `develop/orchestration/reports/P20-006-documentation-second-review-and-feature-parity.md`
- `develop/STATE.md`
- `develop/TODO.md`

## Review Findings Resolved

- `Testing Overview`、`DatabaseとTransaction`、`Current Status`、`チュートリアル: Operationを作る`の旧Page title Linkを現行H1に同期した。Source testとbuilt-site guardが旧Labelの再流入を検出する。
- `#[Sensitive]`をValue／Outcome Propertyの両方へ説明し、`#[ExecuteWith]`をInline以外専用とせずStrategy明示のCompatibility形として説明した。Canonical Deferredは引数なし`#[Deferred]`、Ephemeral Inlineは`#[ExecuteWith(Inline::class)]`の関係を記載した。
- Deferred Outcome例から不存在の`location`を除去し、実在する`reportName`と`operationId`へ同期した。

## Feature Layout

`theme.css`で960px以上のLanding Featureを`repeat(3, minmax(0, 1fr))`の均等列とし、Operation／Journal／Headlessを同一Grid規則で配置した。全Featureへ同じborder／padding／gap／Visual min-heightを適用し、Visual→Copy→Linkの基準を揃えた。959px以下は指定順一列を維持する。Operation／Journal／Headless固有のCode／Lifecycle／Generated Client Visual、指定本文／Link、Hero／Header／CTA／Stable commandは変更していない。

## Edit Link / GitHub Navigation

BlumeのLayout extension pointで`RootLayout`へ`editUrl={null}`を渡す`NoEditLayout`を登録した。無効な`/edit/main/src/content/docs/...` anchorはSemanticに出力されず、Header GitHub iconはBlume native componentから正確なRepository URL、Accessible Label、`target="_blank"`、`rel="noreferrer"`で出力される。`check-site.mjs`は将来の無効edit anchorをartifactで拒否する。

## Sidebar Single Source

`site-navigation.mjs`の`blumeSidebar`変換を`blume.config.ts`が直接参照する構成へ移行し、Sidebar全量の二重定義を除去した。既存のsection順、label、public slug、canonical href、non-indexページの一意`aria-current="page"`は維持される。

## Legacy Style

参照されていない`src/styles/diagram-responsive.css`を削除し、`docs/website`配下の`--sl-*`参照をゼロにした。Blumeのtheme tokenと既存landing stylesheetは維持している。

## Responsive / Theme / Accessibility

既存のLight／Dark theme、reduced-motion、focus-visible、sidebar active marker、Japanese locale、Banner、Search、Redirectを保持した。LandingのDesktop／Mobile構造と全GuideのHeader／Sidebarをartifact guardで検査する。

## Commands and Results

- `mise exec -- pnpm --dir docs/website run test` — PASS (51 tests)
- `mise exec -- pnpm --dir docs/website run content:generate` — PASS
- `mise exec -- pnpm --dir docs/website run content:check` — PASS
- `mise exec -- pnpm --dir docs/website run diagrams:check` — PASS
- `mise exec -- pnpm --dir docs/website run blume:validate` — PASS (no broken links)
- `mise exec -- pnpm --dir docs/website exec blume check --isolated` — PASS (0 errors)
- `mise exec -- pnpm --dir docs/website exec blume build --isolated` — PASS (38 pages; isolated artifact has no invalid GitHub edit anchor and retains native GitHub header link)
- `mise exec -- pnpm --dir docs/website run check` — PASS (Orchestrator normal gate; 0 errors／warnings／hints)
- `mise exec -- pnpm --dir docs/website run build` — PASS (Orchestrator normal gate; 38 pages、37 public routes、artifact／site guard)
- `docker compose run --rm app mago format --check src tests` — PASS
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'` — PASS
- `! rg -n --glob '!pnpm-lock.yaml' -- '--sl-' docs/website` — PASS (Orchestrator corrected the option order before acceptance)
- `git diff --check` — PASS

## Acceptance Criteria

- [x] Desktop equal three-column and narrow-screen ordered one-column Feature layout
- [x] Feature visuals, exact copy, links, Hero/Header/GitHub CTA/Stable command preserved
- [x] Retired page-title links corrected and CI guard added
- [x] Sensitive, ExecuteWith／Deferred／Inline, and Deferred Outcome examples synchronized
- [x] Invalid GitHub edit links omitted semantically while native GitHub icon remains
- [x] Sidebar supplied from `site-navigation.mjs` single source
- [x] Legacy `--sl-*` references removed
- [x] Existing slug, redirect, search, banner, accessibility, and artifact guards retained
- [x] Normal website `check`／`build` full gate
- [x] Framework `src/**` and Public API unchanged; no commit created

## Remaining Issues

P20-006のBlockerはない。第2回ReviewのStable onboarding、Authentication、Reference、Testing／Deployment、文章編集はD120の後続Taskとして残る。

## Review Correction

Orchestrator review identified a duplicated `reportName` line in the completed Outcome example. The second line now uses the real `operationId` property, and the reader-experience test asserts exactly one occurrence of each property in that example. Orchestrator also identified that the Task Packet's first `--sl-*` command placed `--glob` after `--` and could return a false-positive; the option order was corrected before the guard was rerun. Website tests remain 51/51, normal check is 0 errors／warnings／hints, normal build is 38 pages with artifact／site guard success.

Desktop 1440px Light／DarkとMobile 390px LightをPlaywright Chromium imageで独立確認した。Desktopは均等三列、同じVisual上端、本文開始、Link下端を持ち、MobileはOperation／Journal／Headlessの一列でHorizontal Page Overflowを起こさない。確認Artifactは`/tmp/blackops-p20-006-desktop-light.png`、`/tmp/blackops-p20-006-desktop-dark.png`、`/tmp/blackops-p20-006-mobile-light.png`。

## Suggested Next Action

P20-007でStable Installation／Quickstart／First Operation／Authenticationを実行可能なOnboardingへ再構築する。
