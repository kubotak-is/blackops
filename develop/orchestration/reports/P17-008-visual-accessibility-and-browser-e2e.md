# P17-008 Visual Accessibility and Browser E2E Report

## Summary

Community Boardの既存Route、Server Load／Action、Form Field、`data-testid`、Progressive Enhancement、Server-only Generated Client境界を維持したまま、全Routeを一つのAccessible／Responsive Visual Systemへ統合した。公式`reicon-svelte`、self-hosted Geist Variable Sans／Mono、Light／Dark Semantic Token、Keyboard Focus、Error Association、Reduced Motion、Mobile Collapseを追加した。

Playwright Chromiumの実Browser JourneyはSvelteKit Originだけを使用し、Register、Logout、Login、Post Validation／Create／Feed／Detail／Comment／Edit、Digest Validation／Accepted／Retry／Completed、最終Logoutを完走した。主要Pageのaxe Critical／Serious Violationは0件である。実Runtimeからcredential-freeの固定Landing Screenshotを生成した。

## Design Read and Audit

Design Readは、Marketing Siteではなく技術者向けCommunity Board Reference Applicationのpreserve-redesignとした。DialはTaste Skill指定どおり`6 / 4 / 5`。落ち着いたDeveloper Tool／Editorial Languageを採用し、Landingだけに非対称性を持たせ、認証後の操作画面はReading Orderと状態判別を優先した。

Auditでは、Route／Form／Server Contractは健全だが、Routeごとの局所Style重複、48rem中心の単一幅、Theme／Token／Focus／Responsive State不足、古いDigest Roadmap Copyを確認した。Global ShellとSemantic Tokenへ統合し、全要素をCardへ入れず、Feed／Progress／Form等の実HierarchyだけをPanel化した。

## Visual System and Reicon Usage

- Off-white／graphiteをNeutral Base、Signal Orangeを唯一のAccentとした。
- `74rem` Application Container、`68ch` Reading Column、Panel `14px`、Control `9px`をGlobal Tokenで固定した。
- `@fontsource-variable/geist`と`geist-mono`をVersion固定し、Network Fontを使用しない。
- `prefers-color-scheme`でLight／Dark Semantic Colorを切り替える。
- 公式`reicon-svelte@1.0.102`だけを使用し、Iconは個別export moduleからStatic Importした。CDN、別Icon Family、Dynamic Registry、hand-written SVGはない。
- 公式package root barrelには`Icon`の重複exportがありVite 8 Production Parseに失敗するため、packageが公式にexportする`reicon-svelte/icons/*` subpathへ限定した。

## Accessibility and Responsive Evidence

- Skip Link、Main Landmark、Primary Navigation Label、`aria-current`、ButtonのLogoutを維持した。
- LabelはInput上、Helper／Field Errorは下へ統一し、Server Errorを`aria-invalid`／`aria-describedby`／`role=alert`で関連付けた。
- `:focus-visible`を全操作へ適用し、Playwright Keyboard TabでSkip Linkの実FocusとOutlineを検証した。
- 320 x 720で`documentElement.scrollWidth <= 320`、Primary NavigationとLanding Primary CTAがViewport内であることを検証した。
- 1440 x 900のLanding Primary CTAが初期Viewport内であることを検証した。
- Light／Darkのcomputed canvas color差を検証し、両ThemeのLandingへaxeを実行した。
- Reduced MotionではPage Entry durationが`0.01ms`相当へ縮退し、Digest Pending Motionは`prefers-reduced-motion: no-preference`だけで動作する。
- Landing、Register、Feed、Post Detail、Digest Form、Retry Progress、Completed Detailでaxe Critical／Serious 0件を確認した。

## Browser Journey and Screenshot

`tests/Consumer/community-board-browser.sh`は独立Compose Project／PostgreSQL Volumeを作成し、BlackOps HTTP、SvelteKit、1 iterationずつ同期したDigest Workerを使用する。Browserだけをlock済みPlaywrightと同版の公式`mcr.microsoft.com/playwright:v1.61.1-noble`で実行し、HostのBrowser Shared Library差を排除した。有限TimeoutとExit TrapでContainer、Volume、Generated Tree、Build、Trace／Videoを成功／失敗双方でcleanupする。

ScreenshotはFixture作成前のSigned-out LandingをLight／Reduced Motion、1440pxで撮影するため、User、Timestamp、Credentialによる再実行driftがない。

- Path: `docs/guide/assets/community-board/blackops-board.png`
- Size: 1440 x 901 RGB PNG
- SHA-256: `a7619b25d97b6ac1e4eba42888968d71fd1633102836a105a2d6c1c94501945d`

## Sensitive and Server-only Boundary

Browser request listenerはApplication Origin以外を拒否し、BlackOps Debug Portへ直接接続しない。ScreenshotはRegistration前に生成する。Journey後のDOMでPassword、Cookie名、Internal Base URL、Workspace Path、SQL markerが不在であることを検証した。

既存`.server.ts`、Generated Wrapper、Load／Action、Cookie、Safe Projectionは変更していない。P17-007で追加済みの正規`digest.server.ts`へFoundation／IdentityのGenerated Import GuardだけをOrchestrator Scope Extensionどおり同期した。

## Changed Files

- Frontend package／tooling: `package.json`、`pnpm-lock.yaml`、`.gitignore`、`playwright.config.ts`
- Visual system: `src/lib/styles/global.css`、`src/lib/components/BrandMark.svelte`、`StatusIcon.svelte`
- Product UI: root layout／landing、register／login／me、posts feed／new／detail／edit、digests form／progress／detailのSvelte component
- Browser E2E: `e2e/community-board.spec.ts`、`tests/Consumer/community-board-browser.sh`
- CI／existing guard: `.github/workflows/ci.yml`、Foundation／Identity Generated Import Allowlist
- Guide asset: `docs/guide/assets/community-board/blackops-board.png`
- Orchestration: Task Scope Extension、`develop/TODO.md`、`develop/STATE.md`、本Report

## Commands and Results

- Community Composer strict validate: success
- Community locked Composer install／pnpm frozen install: success
- Database migration 5、build compile、frontend generate 13 files、fresh check: success
- Svelte check: `0 errors and 0 warnings`
- Vitest: `6 files, 40 tests` passed
- adapter-node Production Build: success
- Browser E2E: `1 passed (11.9s)`、RegisterからLogout、axe、Theme、320px、Reduced Motion、Screenshot、Origin／Sensitive Guard success
- Existing Consumer: Foundation、Identity、Post Comment、Product Journey、Digestの5本 success
- Example PHPUnit: `59 tests, 545 assertions` success（Consumer内）
- Root Composer strict validate: success
- Root Mago format／lint／analyze: success
- Root PHPUnit: `1471 tests, 5810 assertions` success
- Root Deptrac: `0 violations`、`0 warnings`、`0 errors`
- Management ID、static dash、`git diff --check`、Framework／Quickstart Scope Guard: success
- Runtime／Generated／Dependency／Browser Artifact cleanup: success

最初のRoot MagoはCommunity Boardへ導入済みの`vendor` symlink cycleを`examples`配下で走査して停止した。Task指定のDependency Artifact cleanup後、同じRoot Gateを再実行して全成功した。Production PHP Sourceの違反ではない。

## Skill Pre-flight Matrix

| Check | Evidence | Result |
| --- | --- | --- |
| Hierarchy | Landing asymmetric hero、操作画面reading column、state panel | Pass |
| Visual language | Neutral base、single accent、semantic token、fixed shape scale | Pass |
| Typography | Self-hosted variable sans／mono、metadata限定mono | Pass |
| Responsive | 320px overflow 0、navigation／form explicit collapse | Pass |
| Accessibility | keyboard focus、labels、error association、axe major pages | Pass |
| Theme and contrast | Light／Dark computed difference、axe both themes | Pass |
| Motion | transform／opacity only、Reduced Motion停止 | Pass |
| Icons | official Reicon static subpath imports only | Pass |
| Product integrity | Route／Action／Field／Test ID／Server-only境界維持 | Pass |
| Visual review | credential-free 1440px Screenshotをoriginal sizeで目視 | Pass |

## Acceptance Criteria

- [x] 全RouteへTaste SkillのDesign Read／Audit／Pre-flightを適用した
- [x] 公式Reicon SvelteだけをStatic Importした
- [x] Global Token、Light／Dark、Typography、Spacing、Shape、Focusを統一した
- [x] Desktop／MobileでHorizontal OverflowなくNavigationとFormを利用できる
- [x] Landing、Authentication、Feed、Detail、Form、Digestの状態を完成した
- [x] Existing Server Contract、Progressive Enhancement、Test ID、Safe Error境界を維持した
- [x] Keyboard、Focus、Label、Error Association、Contrast、Reduced Motionを検証した
- [x] Browser JourneyをRegisterからLogoutまで完走した
- [x] 主要Pageのaxe Critical／Serious Violation 0件を確認した
- [x] Credential／Internal URLを含まない実Runtime Screenshotを生成した
- [x] Frontend check、Vitest、Build、Browser E2E、既存5 Consumer Journeyが成功した
- [x] Root Mago／PHPUnit／DeptracとQuickstart Guardが成功した
- [x] Runtime／Generated／Browser Artifactをcleanupした
- [x] Report／TODO／STATEを実装へ同期した
- [x] WorkerはCommitしていない

## Remaining Issues

P17-008のImplementation Blockerはない。公式`reicon-svelte@1.0.102` root barrelの重複`Icon` exportはpackage側の問題だが、公式individual export subpathに限定してProduction BuildとTree Shakingを成立させた。

## Suggested Next Action

P17-009 Task Packetを作成し、README／Guide、Clean Install、全品質Gateを同期してPhase 17をCloseする。

## Orchestrator Review

Orchestrator Codexは実Screenshotをoriginal sizeで確認し、Design Skill Pre-flight、Reicon境界、Browser Journey、変更ScopeをReviewした。Visible DashのHTML Entity、Destructive Button Hover、CI Timeout、Screenshot Drift Guardを修正した。正規の生成順序でBrowser Consumerを独立再実行し、Svelte check 0 errors／0 warnings、Vitest 6 files／40 tests、Production Build、Browser E2E 1 passed（12.1s）を確認した。Screenshot SHA-256はReport記録と一致したため、P17-008をAcceptedとした。Phase 17のCloseはP17-009で行う。
