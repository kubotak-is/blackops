# P22-005D: Documentation Browser, Accessibility, Search, and Production Verification

Status: Accepted — Production Verified

Started At: 2026-08-17T20:25:34+09:00

## P22-005G Production Verification — Accepted; Sol xHigh P1=0／P2=0／P3=0

2026-08-18T20:14:56+09:00

The user-approved PR #10 delivery is recorded against head `76aa6218ee80f6eaf7ae44cd6d4a0db215a6f1de`: PR CI `32117940838` SUCCESS (6 jobs), Documentation `32117940853` SUCCESS with Artifact build `95651602850` and Access-protected preview `95651988232` SUCCESS, merge `36d3206c37e33165b89b78b4eb333562e9d37b61` at `2026-08-18T08:51:06Z`, main CI `32118499805` SUCCESS (6 jobs), main Documentation `32118499737` SUCCESS, and production job `95653679057` SUCCESS. Deployment is `https://e6391b48.blackops-php.pages.dev`; canonical is `https://blackops-php.pages.dev`.

Orchestrator-provided Production HTTP verification returned 200 for `/`, `/reference/project-cli/`, `/blume-search.json`, `/llms.txt`, `/llms-full.txt`, and `/index.md`, with HTML／JSON／plain-text／Markdown UTF-8 content types, the exact root `Link` describedby agent-readability/llms plus alternate `index.md` relations, and 301 redirects from `operations/lifecycle` -> `concepts/lifecycle`, `reference/security` -> `security`, `reference/troubleshooting` -> `troubleshooting`, and `reference/current-status` -> `releases/current-status`. Main Artifact id `9317633470` (`blackops-documentation-site`) matched production for index and project CLI after only Cloudflare Analytics injection normalization; Search／llms／llms-full／index.md were byte exact. Hashes: index `0b5c18d16553e7bdf3892e492db95af3a546a845e4e24097d8c89f7eba257b34`, CLI `49ca6f5054a28a6c7903f445a2cf07b159665b32630d519628eb077a7a7cbb26`, Search `dd1968391b3178932b4a1ee4fccb468d2d715222b23d614b0b43d1afabb36fe5`, `llms.txt` `9df281c58d889c6719d36a78a6e48f131b4302ce6787f94b366e6fc312669eec`, `llms-full.txt` `60c8dca85c861f40c262d45eb0c996234eb89aeef1e58b67a1b904d7ef54fd11`, `index.md` `d9654654a3d2be50001c775d6af91c3d9c0f18328f84b871c6a6ba0a666b0853`.

Current-production Browser evidence is Green at `/tmp/p22-005d-orchestrator/evidence-production-canonical/`: `canonicalRoutes=41`, `executions=127`, `failures=[]`; profiles are desktop-light 41, desktop-dark 41, mobile-light 41, and mobile-dark representative 4. Axe has `entries=127` and `violations=0`; console evidence has `entries=127` with `console=0`, `page=0`, `request=0`; measurements has `entries=127` with horizontal-overflow failures 0; accessibility-name evidence has `entries=127`. Interaction evidence confirms both empty and non-empty Search close with `dialogOpen=false` and focus returned to the Search trigger, `tabCount=4`, theme `light→dark→reload dark→route dark`, and reduced motion `matches=true`, `animationName=none`, `transitionDuration=0s`. Browser is Chromium `149.0.7827.55`, Playwright `1.61.1`, Node `v24.17.0`, image `mcr.microsoft.com/playwright:v1.61.1-noble`.

The exact main-run Artifact evidence is `index.html` SHA-256 `0b5c18d16553e7bdf3892e492db95af3a546a845e4e24097d8c89f7eba257b34` (size `21093`) and `blume-search.json` SHA-256 `dd1968391b3178932b4a1ee4fccb468d2d715222b23d614b0b43d1afabb36fe5` (size `502738`). Together with the HTTP／Artifact evidence above, both delivery criteria are factual. Sol xHigh final verdict is `P1=0/P2=0/P3=0` with no findings, supporting P22-005D acceptance. Not Verified by this management-only acceptance sync: no fresh Worker rerun of Quickstart, Mago, PHP management-ID, CI, Browser, or external publication was performed; no commit, stage, push, PR, CI rerun, deploy, or external mutation occurred here.

## Goal

P22-005A／B／Cで整備した公開Documentation候補をfresh production buildと実Browserで検証し、Releases Search taxonomy、Landing H1のaccessible name、全routeへのtheme deliveryをfail closedにしたうえで、独立Sol xHigh Reviewとsame-SHA Production canonical verificationへ渡せる候補を完成させる。

## Preconditions

- P22-005CはAcceptedで、final Sol xHigh reviewはP1=0／P2=0／P3=0である。
- P22-005C accepted Source SHA-256は`daef847b0c8aa9b8a0ca128135cd6958fd4cd51b9e2c895718c45ffa147133d3`、Test SHA-256は`d2b9f1f5439cecfa8d49fffef70b62f310d29ecee5f19e1832d721f025eb3a46`である。
- Current `blume-search.json`の`/releases/current-status`は`section: "Docs"`／empty breadcrumbとなり、Canonical Content Mapの`Releases`と不一致である。
- Current Landing H1のDOM／Browser textは`BlackOpsThe PHP Framework`であり、語境界がない。
- Current fresh Artifactは全41 routeでtheme CSSを読み込むが、detail layoutのSource ownershipと各HTMLが実際にlinkするCSSのArtifact guardは未確立である。
- 2026-08-17T21:27:44+09:00のfresh Artifact実Browser baselineは41 routeをDesktop Light／Darkと390px Mobile Light、追加Mobile Dark 4 routeで計127 execution検査した。AxeはLight information callout `#6f6f6f`／`#eaf2ff` 4.46:1、Dark Shiki comment `#6a737d`／`#020202` 4.30:1、Dark Mermaid edge label `#cccccc`／`#585858` 4.43:1、Dark skip link `#ffffff`／`#5eead4` 1.47:1、およびMobile Landing code／commandとMermaidのnon-focusable local scrollerを検出した。
- 同baseline後のquery-aware targeted reproで、empty queryのEscapeはdialogを閉じてsearch triggerへfocusを戻すが、`Releases`入力後の最初のEscapeはnative search clearへ消費され、1秒後もdialogがopen／input focusのままであることを再現した。これはharness false positiveではなくProduct keyboard boundaryのFindingである。active-navigation trailing slash、Releases trailing slash、Mermaid `graphics-document document` role、およびOKLCH未対応focus parserはharness側false positiveである。

## In Scope

- `NoEditLayout.astro`で既存`theme.css`のownershipを明示する
- Singleton ReleasesをBlumeのempty-child flat groupへ投影し、Sidebarの直接linkを維持したままSearch taxonomyをCanonical `Releases`へ一致させる
- Landing H1の二つの既存`span`間へliteral whitespaceを追加し、見た目と公開文言を変えずaccessible nameを`BlackOps The PHP Framework`にする
- 各generated HTMLが実際にlinkするCSSだけからactive navigation、inline code wrapping、Mermaid legibility contractを解決できることをArtifact guardする
- Source／Artifactのpositive／negative fixtureを追加し、CSSがdist内の別fileに存在するだけ、Releasesが`Docs`／empty breadcrumb、H1 boundary欠落をfail closedにする
- Fresh production buildを実BrowserでDesktop Light／Dark、390px Mobile、Keyboard、Search、Theme persistence、reduced motion、contrast、Mermaid、overflow、landmark／H1／active navigationまで検証する
- Browser evidenceをRepository外の`/tmp/p22-005d-browser/evidence/`へ保存する
- Browser baselineで確認した4 contrast boundaryだけを既存theme language内で補正し、通常text 4.5:1以上、skip／focus UI 3:1以上を満たす
- Mobileで実際に横scrollするLanding command／code panelとMermaidをKeyboard focusableにし、page全体ではなくlocal scrollerのまま維持する
- Landingとdetail routeが共有するSearch focus boundaryを追加し、non-empty queryでも最初のEscape 1回でdialogを閉じ、同じrouteのsearch triggerへfocusを戻す
- Browser harnessのroute canonicalization、Mermaid role、OKLCH、非同期focus predicateを実Browser semanticsへ一致させる。HarnessはRepository外`/tmp`だけで補正し、Production Sourceへfalse-positive workaroundを入れない
- Local candidateの独立Sol xHigh Documentation Review
- reviewed exact SHAのsame-SHA CI／Documentation delivery後に、別途明示された外部変更権限の範囲でProduction Website／Search／LLM canonical verificationを行う

## Out of Scope

- `docs/guide/**`本文、公開H1文言、public slug、redirect、Release Authority、historical allowlist、Content Map classificationの変更
- `theme.css`の全面visual redesign、Typography token変更、新しいAnimation、画像Asset追加。今回許可するColor変更はbaselineで失敗した4 contrast boundaryへの局所overrideだけとする
- Blume patch／upgrade、package dependency／lockfile変更
- PHP／Application／Framework／Skeleton Source変更
- Version archive／selector、Custom Domain、BlackOps `1.3.0` Capability実装
- `docs/website/dist/**`／generated contentの直接編集
- WorkerによるCommit、Stage、Push、PR、CI dispatch、Deploy、Release、Production HTTP mutation

## Relevant Specifications

- `develop/decisions/143-documentation-release-truth-and-information-architecture.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/59-documentation-reader-experience.md`
- `develop/spec/83-blume-documentation-runtime.md`
- `develop/spec/91-mermaid-documentation-diagrams.md`
- `develop/spec/92-documentation-review-agent.md`
- `develop/spec/104-documentation-release-lifecycle-and-information-architecture.md`

## Files Allowed to Change

- `docs/website/components/NoEditLayout.astro`
- `docs/website/components/SearchFocusBoundary.astro`
- `docs/website/pages/index.astro`
- `docs/website/theme.css`
- `docs/website/site-navigation.mjs`
- `docs/website/scripts/artifact-stylesheet-contract.mjs`
- `docs/website/scripts/check-artifact.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/tests/site-navigation.test.mjs`
- `tests/Consumer/version-baseline.sh`
- `develop/orchestration/tasks/P22-005D-documentation-browser-accessibility-search-verification.md`
- `develop/orchestration/reports/P22-005D-documentation-browser-accessibility-search-verification.md`
- `develop/orchestration/tasks/P22-005-documentation-governance-and-information-architecture.md`
- `develop/STATE.md`
- `develop/TODO.md`

許可されていないFileの変更が必要な場合は実装を止め、ReportへBlockerとして記録する。既存の無関係なWorking Tree変更を編集、Stage、復元しない。

## Constraints

- Production実装はRepository profileのGPT-5.6 Luna Max Workerが行い、Review前にCommitしない
- Documentation ReviewerはGPT-5.6 Sol xHighのread-only Reviewとする
- `theme.css`は4 contrast boundaryの局所overrideとlocal-scroller focus-visible表現だけを許可し、既存Landing／active-nav／inline-wrap／Mermaid containment contractを保持する
- `docs/website/content-map.mjs`、`docs/website/content-pipeline.mjs`、`docs/website/components.ts`、`docs/website/blume.config.ts`、`docs/website/package.json`、lockfile、`docs/guide/**`、redirect、Release Authorityはnegative scope guardで不変を証明する
- H1 visible textは`BlackOps`と`The PHP Framework`をそのまま保ち、視覚的な二行表示も維持する
- ReleasesはSidebarに一つの直接targetだけを持ち、追加disclosure、same-name child、duplicate anchorを作らない
- Browser packageとbrowser binaryはRepository dependencyへ追加しない。既存pinとcached imageを`/tmp`で利用し、offline cache不足時はNetworkへ自動Fallbackしない
- Browser-visible claimはSource／HTMLだけでAcceptedにせず、fresh Artifactの実Browser evidenceを必須とする
- Linked stylesheetのProduction predicateはpure module 1件へ分離し、Artifact commandとfixtureが同じexportを直接使う。Test-localの再実装や`check-artifact.mjs` import時のdist副作用へ依存しない
- Search focus boundaryはBlume dependencyをpatchせず、Landingとdetail layoutの両方から同じcomponentを1回だけ読み込む。Open中のSearch dialog内で発生した非composition Escapeだけを扱い、default clearを抑止してdialog closeとtrigger focus returnを一つのbounded handlerで行う
- Source／Artifact guardはLandingだけ／detailだけの導入、重複script、`preventDefault`／`dialog.close()`／trigger focusの欠落をfail closedにし、generated 41 routeすべてにexactly one shared boundaryがあることを証明する
- `design-taste-frontend`のredesign-preserve方針を適用し、既存Blume／theme languageを保つ。Design dialはvariance 4、motion 2、density 5とする
- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない

## Release Documentation Impact

- Authority tuple／Capability ID: Stable `1.2.0` Authority tupleとCapability内容は不変。Search／Browser delivery representationだけを修正する
- Public Source／route inventory: Source 41、Sidebar page 40、public slug／H1文言／redirectは不変。Browser baseline由来のcontrast／focusability correctionだけを追加する
- Version occurrence before／after分類、historical allowlist: 不変。既存release claim guardを再実行する
- Source／Search／LLM artifact、positive／negative fixture: Releases Search section／breadcrumb、non-empty query Escape close／focus return、route-linked CSS、Landing H1 boundary、4 contrast boundary、local-scroller focusabilityをSource／Artifactでfail closedにする。Search／LLM route inventoryは41を維持する
- same-SHA CI／Documentation delivery、Production deploy有無: PR/main CI／Documentation, Production HTTP／Artifact, and current Production Browser evidence are recorded for the reviewed merge; no rerun or mutation occurred in this Task
- 残り工程、Next Action: none for P22-005D. The Orchestrator publication workflow owns the exact commit／PR CI／merge snapshot; after parent closeout, proceed to the P23-001 feasibility proposal. BlackOps `1.3.0` remains unreleased.

## Acceptance Criteria

- [x] `NoEditLayout.astro`が既存`theme.css`を明示的に所有し、Landing／detail routeともvisual contractが保持される
- [x] Fresh Artifactの全41 HTMLが、自身のlinked stylesheetだけからactive navigation、inline code wrapping、Mermaid legibility contractを解決できる
- [x] CSSがdistの別fileに存在するだけのfixtureはFAILし、実際にlinkedされたfixtureだけがPASSする
- [x] `/releases/current-status` Search recordは厳密に`section === 'Releases'`、`breadcrumb === ['Releases']`である
- [x] Releases Sidebarは一つの直接targetと一つの`aria-current="page"`だけを持ち、追加`details`／same-name child／duplicate anchorがない
- [x] Landing H1のBrowser accessible nameは厳密に`BlackOps The PHP Framework`で、visible words／two-line presentationは不変である
- [x] H1 boundary欠落、visible word変更、Releases `Docs`／empty breadcrumbのnegative fixtureがFAILする
- [x] 全41 routeを1440x900 Light／Darkと390x844 Mobile Lightで実Browser検査し、single main、single H1、skip target、global viewport overflowなし、console／page errorなしを満たす
- [x] 40 non-Landing routeでactive navigationが一つだけあり、Keyboard focusが視認できる
- [x] SearchをKeyboardで開き、input focus、`Releases` query、canonical section／breadcrumb、最初のEscape 1回によるdialog closeとtrigger focus returnを確認する
- [x] Theme toggleはreloadとroute遷移後も保持され、`prefers-reduced-motion`で不要なmotionを抑制する
- [x] empty queryとnon-empty queryの両方でSearch Escape boundaryをBrowser検証し、native `type=search` clearへ最初のEscapeを消費させない
- [x] Light information callout、Dark Shiki comment、Dark Mermaid edge labelは通常text 4.5:1以上、Dark skip linkとfocus indicatorは3:1以上を満たす。旧pair 4.46／4.30／4.43／1.47を再導入するnegative fixtureがFAILする
- [x] Light／Darkの全routeで通常text 4.5:1以上、large text／UI／focus indicator 3:1以上を満たし、Axe critical／serious violationが0である
- [x] Mobileでoverflowする`.landing-command`、`.landing-code-panel > pre`、`blume-mermaid`がKeyboard focusableで、accessible focus indicatorを持つ。Desktopで不要なpage-level overflowやfocus trapを追加しない
- [x] 代表12 routeと4 Mermaid routeでDesktop／Mobile screenshot、DOM measurement、accessibility name、console evidenceを`/tmp`へ残し、Mermaid／table／codeのoverflowはpage全体でなくlocal scrollerへ閉じる
- [x] Source／Artifact release guard、Website test／check／fresh build／site check、version baseline、Mago、PHP management-ID、diff checkがすべてPASSする
- [x] 独立Sol xHigh Documentation ReviewがP1=0／P2=0である
- [x] reviewed exact commitのsame-SHA CI／Documentation deliveryがGreenである（PR/main CI／Documentation evidence recorded）
- [x] Production Websiteの41 canonical route、Search／raw Markdown／LLM artifact、Light／Dark／Mobile代表journeyをsame SHAとしてHTTP／Browser確認する（Production HTTP／Artifact parity and current-production Browser evidence recorded at `/tmp/p22-005d-orchestrator/evidence-production-canonical/`）
- [x] 完了報告に残り工程とNext Actionを明記する

## Remaining Issues

none.

## Suggested Next Action

Orchestrator publication workflow records the reviewed exact commit, PR CI, merge, and authorized publication gates. After parent closeout, the next work is the P23-001 feasibility proposal; BlackOps `1.3.0` remains a proposal and is not released.

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run release:check:source
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
mise exec -- pnpm --dir docs/website run site:check
mise exec -- pnpm --dir docs/website run release:check:artifact
bash -n tests/Consumer/version-baseline.sh
bash tests/Consumer/version-baseline.sh
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
git status --short
```

Browser Gateはfresh `dist`を`blume preview`または同等のlocal-only serverで公開し、`/tmp/p22-005d-browser`のPlaywright 1.61.1／Axe 4.12.1とcached `mcr.microsoft.com/playwright:v1.61.1-noble`を使って実行する。実行Command、route／profile count、evidence path、browser versionをReportへ記録する。

## 2026-08-17 Browser Baseline Amendment

最初のfresh Browser matrixはProduction correction前のdiagnostic evidenceであり、Acceptanceへ再利用しない。対象Artifactは`index.html` SHA-256 `d0cc7028339818eb1fb2379a272941e987e16e73e653a2e8895296b7d2229e1e`、`blume-search.json` SHA-256 `e989799350584a8156ac81ea5506ce60405994c25464621f0e65b77bf0d7faeb`である。Axeは`color-contrast` 105 node／17 routeと`scrollable-region-focusable` 9 node／5 routeを検出した。Correction後はfresh build、同じ全route matrix、Axe、Keyboard、Search、theme、reduced-motion、representative screenshotをすべて取り直す。

Harness correctionはRepository Sourceへ含めない。Canonical navigation hrefはtrailing slashなし、Mermaid accessible roleはBrowserが返す`graphics-document document`を有効とし、focus colorはcomputed OKLCHを含めて実contrastへ変換し、mobileではhidden sidebar itemをfocus対象にしない。これらのfalse positiveを除いたうえでProduct findingだけを判定する。

## 2026-08-17 Search Focus Boundary Amendment

2026-08-17T22:07:41+09:00のtargeted Browser comparisonは、empty queryならEscape 1回でdialog close／trigger focus returnする一方、`Releases` queryでは最初のEscapeがnative search clearに消費され、1000ms後もdialog open／input focusのままであることを再現した。したがって以前の「bounded waitで復帰するharness false positive」という判定は撤回し、Search keyboard boundaryをProduct correctionへ追加する。

修正はBlume dependencyを変更せず、`SearchFocusBoundary.astro`をLandingとdetail layoutへ共有する範囲に限定する。Open dialog内の非composition Escapeでdefaultを抑止し、最初の1回でdialogをcloseして同routeのsearch triggerへfocusを戻す。Source／Artifact fixtureは両layoutへの導入、single marker、handlerのclose／focus／preventDefaultをguardし、fresh Browserはempty／`Releases` queryの両方を検証する。Accessibility correction後の125 completed executionではAxe critical／serious node 0だが、2 mobile routeはnavigation drawer closeのpointer harness defectで未完了であり、このSource変更後に127 executionすべてを再取得する。

## Expected Report

`develop/orchestration/reports/P22-005D-documentation-browser-accessibility-search-verification.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Release Documentation Impact
- Commands and Results
- Browser／Accessibility／Search Evidence
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
