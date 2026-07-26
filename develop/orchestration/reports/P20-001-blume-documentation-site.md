# P20-001: Blume Documentation Site Report

## Summary

公開Documentation WebsiteをAstro StarlightからBlume `1.1.4`へ移行した。`docs/guide/`単一正本、既存Public URL、Redirect、Static `dist/`、Cloudflare Pages Direct Upload境界を維持し、Operation／Journal／HeadlessのLanding、1.x Experimental／2.x Production Ready予定の全Page Banner、指定Navigationを実装した。

Production実装はLuna High Workerが行い、Commitしていない。Orchestrator ReviewでTutorial導線、`ConsoleCommand` Label、Headless Source同期、Redirect、旧Reader Contract Test保持、Specification Supersessionを補正した。

## Changed Files

- Blume Runtime: `docs/website/blume.config.ts`、`package.json`、`pnpm-lock.yaml`、`tsconfig.json`、`.gitignore`
- Landing: `docs/website/pages/index.astro`、`docs/website/theme.css`、`docs/guide/README.md`
- Content／Navigation: `content-map.mjs`、`site-navigation.mjs`、Content Pipeline
- Reader Guide: `authentication.md`、`authorization.md`、`console-command.md`、`frontend.md`、`outbox.md`
- Verification: Website Test、Artifact Guard、Site／Search Check
- Design／Orchestration: D081、D090、D116、Specification 57、59、61、83、README、TODO、Task、Report、STATE
- Removed Starlight Runtime: `docs/website/astro.config.mjs`、`docs/website/src/content.config.ts`

## Blume Runtime and Content Pipeline

- `blume@1.1.4`をProduction Dependency、Blume Custom Pageが必要とする`astro@7.0.7`をDevelopment DependencyとしてExact Pinした。
- Starlightと`astro-mermaid`をPackage／Lockfile／Configから削除した。
- `content:generate`は37件のGuide SourceをBlume Contentへ決定的に生成する。
- Generated Content、`.blume/`、`.generated/`、`dist/`、`node_modules/`はCommit対象外である。
- Mermaid Source Parse、Accessible Metadata、Local Renderer、外部CDN不在を維持した。

## Landing and Version Notice

- Blume `PageLayout`を使うCustom `/` Pageを実装した。
- Operationを主役にし、JournalとHeadlessを補助面へ置く非対称Layoutとした。
- Primary CTAはInstall、Secondary CTAはWhat’s BlackOpsである。
- Light／Dark Token、390 px相当の一列化、Visible Focus、Reduced MotionをCSSとStatic Markup Testで固定した。
- 全PageのDismiss不可Bannerへ`main`、Latest Stable `1.1.0`、1.x Experimental、Minor互換／Production Readiness非保証、Production Readyは2.xから予定を表示した。

## Navigation and New Reader Pages

SidebarをIntroduction、Getting Started、Operation、Execution and Workers、Database、Auth、Frontend、Testing、Tutorial、Deployment、Security、Troubleshooting、Releases、Referenceの順へ変更した。

入力案の誤記はWhat’s BlackOps、Quickstart and Skeleton、Deferred、Execution and Workersへ正規化し、Public Attribute名は`ConsoleCommand`を維持した。TutorialはBlackOps Board Reference Applicationへ接続する。

ConsoleCommand、Outbox、Authentication、Authorization、FrontendのGuideを追加し、Next.js／Nuxt／SvelteKit固有Adapter、Exactly Once、Scheduled Operationを提供済みと主張しない責任境界を明記した。

## Compatibility and Artifact Boundary

- 既存37 Public RouteをBuild／Search対象として維持した。
- Sidebar外のCore Concepts、Operation Authoring、Generator、Execution Context、Retention等も直LinkとSearchで到達できる。
- 既存4 Redirectを維持した。
- `docs/internal/`、`develop/`、Internal Namespace、管理ID、Test Secret、Repository Absolute Path、Source MapをArtifact Guardで拒否する。
- `.github/workflows/ci.yml`と`.github/workflows/docs.yml`は既存の`test`／`check`／`build` Script名と`dist/` Artifactを使用できるため変更不要だった。

## Commands and Results

```text
mise exec -- pnpm --dir docs/website install --frozen-lockfile
PASS (Worker)

mise exec -- pnpm --dir docs/website run test
PASS: 45 tests

mise exec -- pnpm --dir docs/website run check
PASS: Content determinism、Mermaid、Blume 37 pages、0 errors／0 warnings／0 hints

mise exec -- pnpm --dir docs/website run build
PASS: Static 38 pages、Artifact Guard、Site／Search 37 routes

docker compose run --rm app mago format --check src tests
PASS

Management ID guard
PASS

git diff --check
PASS

mise exec -- pnpm --dir docs/website run dev
PASS: Blume Dev Server started with network exposure

curl -I http://127.0.0.1:4322/
PASS: HTTP 200
```

BuildはCustom `/`がGenerated Indexより優先される旨と、Mermaidを含むChunkが500 kBを超える旨をWarning表示する。Static Output、Artifact Guard、Searchは成功しており、本TaskのBlockerではない。

## Acceptance Criteria

- [x] Blume SiteがLocal／Static Buildで成功する
- [x] Starlight固有Dependency／Config／生成文言が残らない
- [x] Operation／Journal／Headless Landingが指定内容を伝える
- [x] 1.x Experimental／2.x Production Ready予定が全Pageで目立つ
- [x] SidebarがSpecification 83へ完全一致する
- [x] 新しい5 Guide Pageが実装済みContractだけを説明する
- [x] Existing URL／Redirect／Search／Mermaid／Artifact Guardを維持する
- [x] Desktop／Mobile、Light／Dark、Keyboard／Reduced MotionのStatic Contractを検証する
- [x] Documentation Workflowが`dist/` Artifact Contractを維持する
- [x] Report／STATEが実装とEvidenceに一致する
- [x] WorkerはCommitしない

## Remaining Issues

- External Publication、Cloudflare Project／Credential／Custom Domainは既存のDormant Publication Taskに残る。
- Browser Screenshot Regressionは導入していない。Responsive／Theme／Focus／Reduced MotionはCSSとStatic Markupで検証した。
- Local Port `4321`は既存Processが使用中だったため、Blumeは自動選択した`4322`で起動した。

## Suggested Next Action

Local `http://localhost:4322/`でLandingとSidebarを確認し、内容とVisualが承認された後にImplementation Commitを作成する。External DeployはUserが公開を明示的に再開するまで行わない。
