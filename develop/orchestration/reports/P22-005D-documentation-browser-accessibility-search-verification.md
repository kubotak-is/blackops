# P22-005D Report: Documentation Browser, Accessibility, Search, and Production Verification

Status: Accepted — Production Verified

## P22-005G Current Production Verification — Accepted; Sol xHigh P1=0／P2=0／P3=0

2026-08-18T20:14:56+09:00

The user-approved PR #10 delivery is recorded against head `76aa6218ee80f6eaf7ae44cd6d4a0db215a6f1de`: PR CI `32117940838` SUCCESS (6 jobs), Documentation `32117940853` SUCCESS with Artifact build `95651602850` and Access-protected preview `95651988232` SUCCESS, merge `36d3206c37e33165b89b78b4eb333562e9d37b61` at `2026-08-18T08:51:06Z`, main CI `32118499805` SUCCESS (6 jobs), main Documentation `32118499737` SUCCESS, and production job `95653679057` SUCCESS. Deployment is `https://e6391b48.blackops-php.pages.dev`; canonical is `https://blackops-php.pages.dev`.

Orchestrator-provided Production HTTP verification returned 200 for `/`, `/reference/project-cli/`, `/blume-search.json`, `/llms.txt`, `/llms-full.txt`, and `/index.md`. Content types were HTML UTF-8, `application/json`, UTF-8 `text/plain`, and UTF-8 `text/markdown` as applicable. The root `Link` header contained the exact describedby agent-readability/llms relations and alternate `index.md` relation. Redirects returned 301 for `operations/lifecycle` -> `concepts/lifecycle`, `reference/security` -> `security`, `reference/troubleshooting` -> `troubleshooting`, and `reference/current-status` -> `releases/current-status`.

Main Artifact id `9317633470`, name `blackops-documentation-site`, matched production for index and project CLI after only Cloudflare Analytics injection normalization; Search, `llms.txt`, `llms-full.txt`, and `index.md` were byte exact. Hashes are index `0b5c18d16553e7bdf3892e492db95af3a546a845e4e24097d8c89f7eba257b34`, CLI `49ca6f5054a28a6c7903f445a2cf07b159665b32630d519628eb077a7a7cbb26`, Search `dd1968391b3178932b4a1ee4fccb468d2d715222b23d614b0b43d1afabb36fe5`, `llms.txt` `9df281c58d889c6719d36a78a6e48f131b4302ce6787f94b366e6fc312669eec`, `llms-full.txt` `60c8dca85c861f40c262d45eb0c996234eb89aeef1e58b67a1b904d7ef54fd11`, and `index.md` `d9654654a3d2be50001c775d6af91c3d9c0f18328f84b871c6a6ba0a666b0853`.

Current-production Browser evidence is Green at `/tmp/p22-005d-orchestrator/evidence-production-canonical/`: `canonicalRoutes=41`, `executions=127`, `failures=[]`; profiles are desktop-light 41, desktop-dark 41, mobile-light 41, and mobile-dark representative 4. Axe has `entries=127` and `violations=0`; console evidence has `entries=127` with `console=0`, `page=0`, `request=0`; measurements has `entries=127` with horizontal-overflow failures 0; accessibility-name evidence has `entries=127`. Interaction evidence confirms empty and non-empty Search close with `dialogOpen=false` and focus returned to the Search trigger, `tabCount=4`, theme `light→dark→reload dark→route dark`, and reduced motion `matches=true`, `animationName=none`, `transitionDuration=0s`. Browser is Chromium `149.0.7827.55`, Playwright `1.61.1`, Node `v24.17.0`, image `mcr.microsoft.com/playwright:v1.61.1-noble`.

The exact main-run Artifact evidence is `index.html` SHA-256 `0b5c18d16553e7bdf3892e492db95af3a546a845e4e24097d8c89f7eba257b34` (size `21093`) and `blume-search.json` SHA-256 `dd1968391b3178932b4a1ee4fccb468d2d715222b23d614b0b43d1afabb36fe5` (size `502738`). Same-SHA CI／Documentation, Production HTTP／Artifact, and current-production Browser evidence are recorded. Sol xHigh final verdict is `P1=0/P2=0/P3=0` with no findings, supporting P22-005D acceptance. Not Verified by this management-only acceptance sync: no fresh Worker rerun of Quickstart, Mago, PHP management-ID, CI, Browser, or external publication was performed; no commit, stage, push, PR, CI rerun, deploy, or external mutation occurred here.

## Summary

P22-005C Accepted candidateに対するSol xHighのread-only pre-auditを完了し、P22-005Dを開始した。最初のLuna Max correctionはReleases Search taxonomy、Landing H1 word boundary、detail layoutによるtheme ownership、各route linked-CSS guardを実装した。二回目のbounded accessibility correctionは4 contrast boundaryとMobile local scroller focusabilityを補正した。query-aware targeted reproで確定した`Releases`入力後の最初のEscape問題には、Blume dependencyを変更しない共有SearchFocusBoundaryをLanding／detail layoutへ導入した。Orchestrator full gateで判明したversion-baselineのtyped Blume source assertion mismatchも、実際の`display: /** @type {'flat'} */ ('flat')` contractへ限定修正した。最終候補はSource／Artifact guard、focused 44／44、Website 112／112、check 0／0／0、fresh 42-page build／41-page site check、release source／artifact、version baseline、Mago、PHP management-ID scan、diff checkがGreenである。最終fresh Artifactと同じSHA-256を対象に、補正済みBrowser harnessで41 route×3 profile＋Mobile Dark 4 routeの127／127 execution、Axe critical／serious 0、empty／non-empty SearchのEscape close／focus returnを完走した。Local GateはGreen。Current Production HTTP／Artifact／Browser evidence is recorded in the P22-005G current verification section. Sol xHigh final verdict is `P1=0/P2=0/P3=0` with no findings; P22-005D is Accepted.

## Changed Files

- `develop/orchestration/tasks/P22-005D-documentation-browser-accessibility-search-verification.md`
- `develop/orchestration/reports/P22-005D-documentation-browser-accessibility-search-verification.md`
- `develop/orchestration/tasks/P22-005-documentation-governance-and-information-architecture.md`
- `develop/STATE.md`
- `develop/TODO.md`
- `docs/website/scripts/artifact-stylesheet-contract.mjs`（linked CSS／accessibility／overflowに加え、Search source／artifact predicateを共有）
- `docs/website/scripts/check-artifact.mjs`（Source guardと41-route generated boundary artifact guardを実行）
- `docs/website/tests/reader-experience.test.mjs`（Search source／artifact positive／negative fixturesを追加）
- `docs/website/components/NoEditLayout.astro`（detail layoutからshared SearchFocusBoundaryを1回導入）
- `docs/website/pages/index.astro`（Landingからshared SearchFocusBoundaryを1回導入）
- `docs/website/theme.css`（4 contrast boundaryとlocal-scroller focus-visibleを限定補正済み）
- `docs/website/components/SearchFocusBoundary.astro`（2026-08-17T22:07:41+09:00のSearch focus amendmentで追加許可・実装済み）
- `tests/Consumer/version-baseline.sh`（Releases typed `display` source contractを厳密に検証する1行修正）

## Decisions and Assumptions

- Current Artifactは全41 routeでtheme CSSを読むため、現行表示欠落とは判定しない。Source ownershipとper-route linked-CSS guardをhardeningする。
- ReleasesはBlume 1.3.0のempty-child flat groupへ投影し、公開route／Sidebar direct targetを変えずSearch section／breadcrumbだけをCanonicalへ合わせる。
- Landing H1は既存span間のliteral whitespaceだけを追加し、visible wordsと二行表示を変えない。
- Browser依存はRepositoryへ追加せず、既存pin／offline store／cached imageを`/tmp`で使用する。
- Production／Network mutationはWorker段階では未許可。reviewed exact commitとsame-SHA CI Green後の別段階とする。
- Linked stylesheet fixtureがProduction validatorを複製しないよう、side-effect-freeな`artifact-stylesheet-contract.mjs` 1 fileをFiles Allowedへ追加した。`check-artifact.mjs`とTestは同じexportを直接使う。
- First Browser matrixのactive-navigation／Releases trailing slash、Mermaid role、OKLCH focus parsingはharness defectだった。Product Sourceへworkaroundを入れず、Repository外harnessを実semanticsへ補正する。
- Empty queryのEscapeはtriggerへfocusが戻るが、`Releases` queryの最初のEscapeはnative clearへ消費され、1000ms後もdialog open／input focusである。以前のempty-queryだけのtargeted PASSをProduct全体の証拠へ誤って一般化した判定を撤回する。
- Search correctionはBlume dependency／lockfileを変更せず、Landing／detail routeが共有するsingle focus-boundary componentで最初のEscapeによるclose／trigger focus returnだけを所有する。
- SearchFocusBoundaryはopen dialog内の非composition Escapeだけをcaptureし、`preventDefault`、`dialog.close()`、同じ`blume-search` hostの`[data-blume-search-open]` lookup、focus returnを一つの順序付きhandlerで実行する。Source predicateは両layoutのimport／usageを各1回、handlerのEscape／composition／origin／open filterを要求し、Artifact predicateは41 Search routeのgenerated markerを各1個と同じhandler boundaryを要求する。
- Version baselineはReleasesのflat projectionを弱めず、typed Blume source expression `display: /** @type {'flat'} */ ('flat')`をexactly要求する。旧literal `display: 'flat'`への依存だけがfalse failureであり、existing site-navigation negative fixtureは維持する。
- Axe findingはLight information callout 4.46:1、Dark Shiki comment 4.30:1、Dark Mermaid edge label 4.43:1、Dark skip link 1.47:1、Mobile Landing／Mermaid scrollerのnon-focusabilityである。`theme.css`を全面変更せず、この4 color pairとlocal-scroller focus表示だけを追加許可した。

## Release Documentation Impact

- Authority tuple／Capability ID: 変更なし
- Public Source／route inventory: 41 Source／40 Sidebar pageを維持。Browser correctionは公開本文／slug／H1を変更しない
- Version occurrence before／after分類、historical allowlist: 変更なし
- Source／Search／LLM artifact、positive／negative fixture: Releases taxonomy、non-empty query Escape boundary、H1 boundary、linked CSS、旧contrast pair、non-focusable local scrollerをfail closedにする
- same-SHA CI／Documentation delivery、Production deploy有無: Supplied PR/main CI／Documentation, Production HTTP／Artifact, and current-production Browser evidence are recorded; no rerun or external mutation was performed by this worker
- 残り工程、Next Action: none for P22-005D. The Orchestrator publication workflow owns the exact commit／PR CI／merge snapshot; after parent closeout, proceed to the P23-001 feasibility proposal. BlackOps `1.3.0` remains unreleased.

## Commands and Results

| Command | Result |
| --- | --- |
| Sol xHigh read-only Source／dist pre-audit | PASS。2 Confirmed correctionと1 hardeningへ限定 |
| `node`による`dist/blume-search.json`のReleases record抽出 | Reproduced: `section: "Docs"`, `breadcrumb: []`, total 41 records |
| `/tmp`へのPlaywright 1.61.1／Axe 4.12.1 offline install | PASS。5 packages reused、download 0 |
| cached Playwright Chromiumで`http://127.0.0.1:4173/` smoke | PASS。title正常、main=1、viewport overflowなし。H1 text `BlackOpsThe PHP Framework`を再現 |
| Focused Website test | Historical pre-amendment PASS、52／52。Final amended evidence is recorded below as 44／44 |
| Fresh production build | Historical pre-amendment artifact。Final amended `index.html`／Search hashes are recorded below |
| Orchestrator Browser matrix | Diagnostic complete。41 route×3 profile＋Mobile Dark 4 route＝127 execution。Product correction前のためAcceptance evidenceへ再利用しない |
| Axe | FAIL。`color-contrast` 105 node／17 route、`scrollable-region-focusable` 9 node／5 route |
| Luna Max accessibility correction | PASS。Focused reader 44／44、Website 112／112、check 0 issue、fresh build／artifact／site check、diff check |
| Post-correction Browser matrix | Partial。125 completed executionでAxe critical／serious node 0。2 mobile executionはnavigation drawer closeのpointer harness exception |
| Query-aware Search focus comparison | Historical pre-amendment FAIL。empty queryはclose／focusしたが、`Releases` queryのfirst Escapeがnative clearへ消費されたためSearchFocusBoundary correctionへ移行 |
| SearchFocusBoundary source／artifact correction | PASS。shared componentをLanding／NoEditLayoutへ各1回導入。Source／Artifact predicateはpreventDefault、close、same-host trigger lookup、focus orderとEscape／composition／origin／open filterをfail closed。Artifact markerは41／41 routeでexactly one |
| Focused amended reader test | PASS、44／44。global trigger、reordered handler、missing filter、missing operation、one-layout-only、duplicate markerのnegative fixtureを含む |
| Final Website test | PASS、112／112（87.431s） |
| Final Website check | PASS、0 errors／0 warnings／0 hints |
| Final fresh production build | PASS。42 pages、static artifact boundary、embedded site:check 41 pages。`dist/index.html` SHA-256 `c4b7ad809f61ef5a5e06ad709e89deb63d1def11874550a7f89cbc896a1b2d87`、mtime `2026-08-17 22:43:32 +0900`; `blume-search.json` SHA-256 `e989799350584a8156ac81ea5506ce60405994c25464621f0e65b77bf0d7faeb` |
| Explicit `site:check`／`release:check:source`／`release:check:artifact` | PASS。site 41 pages、release source／artifact guards pass |
| Targeted Search Browser repro (Orchestrator) | PASS。Chromium 1.61.1、empty／`Releases` queryとも after 0／50／250／1000msでdialog close／trigger focus return |
| Final full Browser／Axe Gate (Orchestrator) | PASS。cached `mcr.microsoft.com/playwright:v1.61.1-noble`／Chromium 149.0.7827.55、41 canonical route、Desktop Light 41、Desktop Dark 41、Mobile Light 41、Mobile Dark代表4の計127／127 execution、failure 0、Axe critical／serious violation 0／node 0。Evidence root `/tmp/p22-005d-orchestrator/evidence-search-correction/` |
| Final Sol xHigh read-only Review | PASS。P1=0／P2=0／P3=0、D blocking findingなし、P22-005D Local Acceptance支持。Current dist／evidence hash、127／127 Browser、Axe／console／overflow／focus contrast、Search、theme、reduced motion、代表Screenshot、管理同期を独立確認 |
| `git diff --check` | PASS |
| Final Source SHA-256 inventory | `SearchFocusBoundary.astro` `4a6fd3eee2368d14a2d6a3f1d3c46e76c940f7c5dc3e9465564c49467baf4c96`; `artifact-stylesheet-contract.mjs` `b35889c5813d24fbda91ba57f4afb3c2c43ff8f89b326eba04a6301b30569dcd`; `check-artifact.mjs` `b72047bb9ab5ec224e1cac416d031c1aa1e8a73dfd76fbf243cbb868569049d0`; `reader-experience.test.mjs` `08d31dd2dce43629299bd3813101cc1417882be8b253d7eeb21d48c36d932925`; `NoEditLayout.astro` `1e6033740f665583cdc2ef39a929a101c2e19ea6478aa67b8c727c255125277e`; `index.astro` `9dbc7867704bf52e65103f909d391b0c9b86cfa728a92d58d376df2bfc7965c6`; `theme.css` `48e9fd1b27b6f4cbc5bc8a49a3ea4a4336faa5076a374d5597d55293e146516b`; `version-baseline.sh` `e2f62ff74dd0f3ab723c5330010db111220e3ba1c2f7f69c92a4126f455315f2` |
| Full PHP／Consumer gate | 未実行。TaskのWorker bounded Website scopeにより実施しない |
| Superseding full Required Command gate | PASS。`bash -n`／version baseline (`published=1.2.0 historical=1.1.0`)、Mago format、PHP management-ID scan、diff check。The baseline false failure was resolved by the typed source assertion; no full Consumer E2E was requested or run |

## Browser／Accessibility／Search Evidence

- Pre-correction diagnostic matrixと125-execution途中結果は履歴 evidenceでありAcceptanceへ再利用しない。
- Candidate Orchestrator evidence root: `/tmp/p22-005d-orchestrator/evidence-search-correction/`; current Production evidence root: `/tmp/p22-005d-orchestrator/evidence-production-canonical/`
- `summary.json`、`axe.json`、`interaction.json`、`measurements.json`、`accessibility-names.json`、`console-errors.json`、Browser version、representative screenshotsを保存した。
- Search recordは`section: "Releases"`／`breadcrumb: ["Releases"]`、Landing H1は`BlackOps The PHP Framework`、Theme light→dark persistenceとreduced-motion `0s`、Mermaid SVG title／desc／`aria-labelledby`、local overflow containmentはPASS。
- Candidate final 127／127 executionはfailure 0、Axe critical／serious violation 0／node 0である。Current Production 127 executions are separately Green with failures empty, Axe violations 0, console/page/request 0, horizontal-overflow failures 0, and accessibility-name entries 127; Desktop Light／Dark 41 route、Mobile Light 41 route、Mobile Dark代表4 routeを含む。
- Search focusはempty／non-emptyの両方で最初のEscapeを補正済み。Orchestrator targeted reproは`Releases` queryを含め、after 0／50／250／1000msのclose／focus returnを確認した。
- Fresh final ArtifactのSearch Indexは41 route、generated SearchFocusBoundary markerはmin=1／max=1／total=41。`404.html`はSearch Index外のfallbackでありroute marker契約の対象外。
- Served Artifactの`index.html`／Search SHA-256は最終fresh buildと一致する。Current Production HTTP／Artifact parity and current-production Browser evidence are verified from the supplied evidence at `/tmp/p22-005d-orchestrator/evidence-production-canonical/`; retained 127/127 same-source candidate evidence remains separately identified.

## Acceptance Criteria

- [x] Worker bounded Source／Artifact／Website Local evidence（Search correction、accessibility correction、focused／full Website test、check、fresh build、site／release guards、diff check）
- [x] Superseding version-baseline correction and Required Command management checks（typed Releases flat predicate、version syntax／baseline、Mago、PHP management-ID scan、diff check）
- [x] Full 127 Browser／Axe／Search／theme／keyboard matrixと全management gate
- [x] Task PacketのLocal Acceptance（独立Reviewを含む）
- [x] 独立Sol xHigh Documentation Review P1=0／P2=0
- [x] 独立Sol xHigh final verdict P1=0／P2=0／P3=0、findingなし、P22-005D acceptance支持
- [x] reviewed exact commitのsame-SHA CI／Documentation delivery Green (supplied PR/main evidence)
- [x] current Production Browser confirmation for 41 canonical routes and representative Light／Dark／Mobile journeys (127 executions; Axe, console/page/request, horizontal overflow, accessibility names, Search, theme, and reduced-motion evidence Green)
- [x] 残り工程とNext Actionを完了報告へ記録

## Remaining Issues

none.

## Suggested Next Action

- Orchestrator publication workflow records the reviewed exact commit, PR CI, merge, and authorized publication gates. After parent closeout, the next work is the P23-001 feasibility proposal; BlackOps `1.3.0` remains a proposal and is not released.
