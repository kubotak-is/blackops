# P22-005D: Documentation Browser, Accessibility, Search, and Production Verification

Status: Local Accepted

Started At: 2026-08-17T20:25:34+09:00

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
- same-SHA CI／Documentation delivery、Production deploy有無: Worker段階ではなし。Accepted exact commitのsame-SHA CI Green後にだけauthorized delivery／Production canonical verificationを行う
- 残り工程、Next Action: Luna Max限定修正とLocal Browser Gate、Sol xHigh Review、Orchestrator Acceptance、reviewed Commit／same-SHA CI、authorized Production canonical verification

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
- [ ] reviewed exact commitのsame-SHA CI／Documentation deliveryがGreenである
- [ ] Production Websiteの41 canonical route、Search／raw Markdown／LLM artifact、Light／Dark／Mobile代表journeyをsame SHAとしてHTTP／Browser確認する
- [x] 完了報告に残り工程とNext Actionを明記する

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
