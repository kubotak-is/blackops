# P10-005A: Reader Orientation and Diagrams Report

Status: Accepted

## Summary

初見の中級PHP開発者がGetting Startedの前にBlackOpsの採用理由と中核概念を理解できるよう、Why BlackOps、Core Concepts、Glossaryを追加した。Headless、統一Operation Model、`No operation stays in the dark`の意味とProtocol Error境界、Laravel／SymfonyからのMental Modelを公開導線へ配置した。

Core Concept、Inline／Deferred、Lifecycle、Identifierの4図をMermaidで追加した。各図は`accTitle`／`accDescr`と隣接する本文のText Alternativeを持つ。Starlight公式Resources掲載の`astro-mermaid`をLocal Client RendererとしてExact Pinし、Mermaid構文のBuild前検証、Static Artifactの4 Target／Local Renderer Chunk Guard、Pagefind検索を追加した。

## Information Architecture

- Sidebar先頭へ`Overview` Sectionを追加し、Why BlackOps、Core ConceptsをGetting Startedより前に配置した。
- LandingのPrimary ActionをWhy BlackOpsへ向け、`/` → Why BlackOps → Core Concepts → Installの連続導線をGuardした。
- GlossaryをReferenceへ追加し、Attempt、Claim、Lease、Fencing Token、Heartbeat、Projection、Manifest、Dead Letter、Journal、Outcome、Correlation、Causation、Retentionを定義した。
- Execution／Lifecycle Pageの初出用語をGlossaryへLinkし、Pagefindで`Fencing Token`からGlossaryへ到達できることを検証した。
- Stable `1.0.0`／`main` Bannerと`docs/guide/`だけを公開するArtifact Boundaryを維持した。

## Diagram Evidence

- `core-concepts.md`: Operation／OperationValue／Outcome／Journal／ExecutionContext／Execution Strategyの関係図。
- `execution.md`: InlineとDeferredの受付、Attempt、OutcomeのSequence Diagram。
- `operation-lifecycle.md`: ReceivedからTerminal StateまでのState Transition Diagram。
- `execution-context.md`: Operation ID、Attempt ID、Correlation ID、Causation IDの関係図。
- `diagrams:check`が4 Sourceを`mermaid.parse()`し、構文Errorを`check`／`build`前にFail-fastする。
- Static Artifactは4つの`pre.mermaid` Target、1つのLocal Renderer Entry、1つのLocal `mermaid.core` Chunkを持つ。Artifact GuardはTargetだけでRendererがないBuildを拒否する。

## Accessibility Evidence

- 4図すべてにMermaidの`accTitle`と`accDescr`を指定した。
- Mermaid ParserはAccessibility Metadataを含む完全なSourceをParseする。
- 各図の直後に「図のテキスト代替」の本文またはTableを配置した。JavaScript無効時や図を利用しない読者も同じ関係を読める。
- Artifact／Site Guardが4組の`accTitle`／`accDescr` Sourceと4つのText Alternativeを確認する。
- `autoTheme: true`によりStarlightの`data-theme`を監視し、Lightでは`default`、Darkでは`dark` Themeで再描画するLocal RuntimeをArtifactへ同梱した。
- Responsive StyleはSVGの内部文字列を変更せず、Diagram Targetを本文幅へ制約する。MobileではSVGの最小幅を60 remとしてNodeとLabelを読み取れる大きさに保ち、必要な横移動はDiagram Target内だけに閉じ込める。

## Browser Evidence

Orchestrator Browser ReviewではDesktop Dark表示のSVG、本文、Themeは正常だった。一方、Edge headlessの390 x 1000 Mobile表示でCore ConceptsのMermaid SVGが本文のIntrinsic Widthを押し広げ、Diagramだけでなく本文とVersion Bannerも右側でClipする問題を検出した。

Starlightの`customCss`として`diagram-responsive.css`を追加し、Main／Content Containerへ`min-inline-size: 0`、`pre.mermaid`へ本文幅、`max-inline-size: 100%`、内部Horizontal Overflow、SVGへAspect Ratioを維持するBlock Layoutを設定した。Theme色には触れないため、既存のLight `default`／Dark `dark`再描画を維持する。

最初のResponsive修正ではPage全体のHorizontal Overflowは解消したが、SVGを本文幅へ縮小した結果、Core Conceptsの右側NodeがSVG内部でClipした。`clientWidth`と`scrollWidth`の一致だけでは内部描画の欠落を検出できないため、Orchestrator Reviewで不合格とした。

最終修正では、50 rem以下のViewportでSVGへ60 remの最小幅を与え、PageではなくDiagram Targetだけを横Scroll可能にした。Edge headlessの390 x 1000 Mobile Screenshotで、本文とVersion BannerがViewport内に収まり、図の文字サイズを維持した左端表示とDiagram内の実Horizontal Scrollbarを確認した。残りのNodeはDiagram Target内を横Scrollして読める。各図の直後に同じ関係を記したText Alternativeも維持する。

Artifact GuardとSite CheckはResponsive CSSのTarget Selector、Container Width Constraint、60 rem SVG最小幅、Overflow RuleがBuild Artifactへ一度だけ含まれ、4 Diagram Pageすべてから参照されることを検証する。

## Dependency Decision

実装時点のStarlight公式ResourcesとPackage Registryを照合し、Astro 7／Starlight互換の`astro-mermaid` `2.1.0`と`mermaid` `11.16.0`をExact Pinした。Server-side構文検証用のDOMとして`jsdom` `29.1.1`もExact Pinした。外部CDNとRemote Diagram Serviceは使用しない。

最初に`rehype-mermaid` `3.0.0`、`playwright` `1.61.1`のBuild-time inline SVG方式を検証したが、Workspace-local Chromiumの起動に失敗した。`ldd`で`libnspr4.so`、`libnss3.so`、`libnssutil3.so`、`libsmime3.so`、`libasound.so.2`の不足を確認し、Fresh環境にOS Package導入またはWorkflow変更を要求するため中止した。Orchestrator判断により、公式Resources掲載のClient-side Local Rendererへ切り替えた。

Client方式は初回PageでMermaid ChunkをDownload／ParseするCostがあり、Viteは500 kB超Chunk Warningを出す。一方でOS Browser Dependencyを持たず、Frozen InstallだけでLocal／CI Buildを再現でき、Starlight Theme変更時にLight／Darkを再描画できる。本Taskでは構文ParseとArtifact GuardによりClient Render前の決定的な品質境界を設けた。

## Changed Files

- `docs/guide/README.md`
- `docs/guide/why-blackops.md`
- `docs/guide/core-concepts.md`
- `docs/guide/execution.md`
- `docs/guide/operation-lifecycle.md`
- `docs/guide/execution-context.md`
- `docs/guide/glossary.md`
- `docs/website/README.md`
- `docs/website/astro.config.mjs`
- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/package.json`
- `docs/website/pnpm-lock.yaml`
- `docs/website/scripts/check-diagrams.mjs`
- `docs/website/scripts/check-artifact.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/src/styles/diagram-responsive.css`
- `docs/website/tests/content-pipeline.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/tests/site-navigation.test.mjs`
- `develop/orchestration/reports/P10-005A-reader-orientation-and-diagrams.md`
- `develop/STATE.md`

## Commands and Results

```text
mise exec -- pnpm --dir docs/website install --frozen-lockfile
Result: Already up to date。pnpm 11.12.0で成功し、Browser／OS Package導入は不要。

mise exec -- pnpm --dir docs/website run test
Result: 20 tests / 20 passed / 0 failed。

mise exec -- pnpm --dir docs/website run check
Result: Content Determinism、4 Mermaid Syntax／Accessibility Metadata、Astro 16 filesが成功。0 errors / 0 warnings / 0 hints。

mise exec -- pnpm --dir docs/website run build
Result: 21 Public Pages plus 404を生成。Pagefind、Sitemap、Artifact Guard、Site／Search Checkが成功。ViteのMermaid Chunk Size WarningはDependency Decisionへ記録。

! rg -n '```mermaid' docs/website/dist
Result: No matches。Source Fence MarkerはStatic Artifactにない。

rg -n 'Why BlackOps|Core Concepts|No operation stays in the dark|Claim|Fencing|Dead Letter' docs/website/dist
Result: Public HTMLとPagefind IndexにReader Orientation／Glossary語を確認。

! rg -n 'docs/internal|develop/' docs/website/dist
Result: No matches。Public Artifact Boundaryを維持。

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO.md:[0-9]+' src tests --glob '*.php'
Result: No matches。

git diff --check
Result: Success。

Build-time Mermaid Investigation
Result: Workspace-local Chromium起動時にlibnspr4.so不足でContent Loaderが失敗。lddで5 Shared Library不足を確認し、Host Packageは変更せず方式を中止した。

Orchestrator Edge Headless Browser Review
Result: Desktop Darkは正常。初回390 x 1000 Mobile ReviewでMermaidが本文幅を押し広げ、Diagram、本文、Version Bannerの右側Clipを検出した。

Responsive CSS追加後の最初のmise exec -- pnpm --dir docs/website run build
Result: Astro Buildは成功。Artifact GuardがViteによる`overflow-x: auto; overflow-y: hidden`から`overflow: auto hidden`への等価な最適化を認識せず失敗したため、Source Testを維持したままCompiled CSS Guardへ両表現を許可した。

Responsive CSS追加後のmise exec -- pnpm --dir docs/website run test / run check / run build
Result: 20 tests、Astro 16 files、21 Public Pages plus 404、Artifact／Site／Search Guardがすべて成功した。

Edge headless 390 px Mobile Review after first Responsive Fix
Result: Page全体のHorizontal Overflowは解消したが、Core Conceptsの右側NodeがSVG内部でClipしたためReview不合格。

Edge headless 390 x 1000 Mobile Review after Diagram-local Scroll Fix
Result: 本文とVersion BannerはViewport内に収まり、Core Concepts図は可読な文字サイズとDiagram内Horizontal Scrollbarを持つ。図の残りはPage全体をScrollせずDiagram Target内から到達できる。
```

## Acceptance Criteria

- [x] Why BlackOpsとCore ConceptsがGetting Startedより前にSidebarへ配置される
- [x] Headless、統一Operation Model、No operation stays in the darkをProtocol Error境界込みで説明する
- [x] Operation／Value／Outcome／Journal／ExecutionContext／Strategyを一枚の図と短い定義で示す
- [x] Laravel／Symfony Mental Model Tableと非一対一の注意がある
- [x] 指定された4 Mermaid DiagramがLocal Client Renderer付きStatic ArtifactからSVG化される
- [x] 各DiagramにAccessible Descriptionと本文のText Alternativeがある
- [x] Glossaryが指定Termを定義しNavigation／Searchへ含まれる
- [x] Mermaid Dependency、Syntax Check、Content Map、Sidebar、Artifact Guardが決定的である
- [x] 全PageのVersion BannerとPublic Artifact Boundaryを維持する

## Remaining Issues

P10-005A Scope内の既知Blockerはない。Client-side Mermaidは初回Load CostとJavaScript実行を必要とする。Desktop Darkと、MobileでPage OverflowがなくDiagram Target内に実Scrollbarを持つことはActual Edgeで確認した。Light／DarkのScreenshot Regressionは自動化していないが、固定Parser、Local Renderer Artifact、Theme切替Code、60 rem Responsive CSS、Accessible Source／Text Alternativeを自動検証している。

Cloudflare Project／Environment Secret／Protection RuleのExternal Configuration待ちはP10-005から継続しており、本TaskのRepository実装を妨げない。

## Suggested Next Action

P10-005Aを単独Commitし、P10-005Bへ進む。

## Orchestrator Review

公開文言をSpec 59とPublic APIへ照合し、4図の構文、Accessible Metadata、Text Alternative、Local Renderer、Artifact Boundaryを確認した。Workerの初回Responsive修正は390 px幅でSVG内部Clipを残したため差し戻し、Diagram Target内だけを横Scrollする最終修正を採用した。

OrchestratorがFrozen Install、20 Test、Astro Check、Static Build、Artifact／Site／Search Guard、Mago Format、PHP Management ID Guard、External CDN／Internal Document Guard、`git diff --check`を再実行し、すべて成功した。Edge headlessのDesktop Darkと390 x 1000 MobileでSVG描画、本文幅、Diagram内Horizontal Scrollbarを実確認した。P10-005AをAcceptedとする。
