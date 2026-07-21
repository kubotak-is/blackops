# P17-008: Visual, Accessibility, and Browser E2E

Status: In Progress

## Goal

機能完成済みのBlackOps Boardを、初見でも利用経路とBlackOpsの価値が分かるAccessible／Responsive Product UIへ仕上げる。Reicon、System-aware Light／Dark Theme、実Browser E2E、Accessibility Scan、Credentialを含まない実画面Screenshotを追加する。

## Context

P17-002からP17-007で、SvelteKit BFF、Application-owned Authentication、Post／Comment Journey、Deferred Digestを実装した。現在のPageはSemantic HTMLとProgressive Enhancementを持つが、Routeごとの最小Styleに留まり、Global Design Token、統一Component、Icon、Focus、Responsive Product Layout、Dark Theme、Browser Automationがない。

D108はA／B／Cで確定した。Ray.Aopのliteral Strategy回避はP17-008のVisual／Accessibilityに影響しないため、Phase 17を先行する。

## Source of Truth

- `develop/decisions/103-full-stack-reference-application.md`
- `develop/decisions/108-ray-aop-upstream-and-phase-order.md`
- `develop/spec/71-full-stack-reference-application.md`
- `develop/spec/72-phase-17-delivery-plan.md`
- `develop/orchestration/reports/P17-006-generated-operations-and-sveltekit-product-journey.md`
- `develop/orchestration/reports/P17-007-deferred-digest-and-progress.md`
- `design-taste-frontend` Skill
- [Reicon](https://reicon.dev/)

## Design Read

技術者向けCommunity Boardの既存機能を仕上げるRedesignであり、落ち着いたDeveloper-tool／Editorial調、Native Svelte CSSとReiconを使う。

```text
DESIGN_VARIANCE: 6
MOTION_INTENSITY: 4
VISUAL_DENSITY: 5
```

Landing／Global ShellにはTaste SkillのRedesign AuditとPre-flightを適用する。Feed、Form、Detail、Digest ProgressはMulti-page Product UIであるため、SkillのLanding向け演出を一律適用せず、操作の明瞭さ、Progressive Enhancement、Accessibilityを優先する。

## Existing UI Audit

### Preserve

- 全Route Path、Server Load／Form Action、Field Name、HTTP Method
- `data-testid`、Role、Live Region、Label／Input関連付け
- JavaScript無効時も成立する標準Link／Form Journey
- Generated OperationとCredentialをServer-onlyに保つ境界
- 401／404／409／422／503のSafe Projection
- Current English Product Copyの簡潔なRegister

### Improve

- Route内へ重複した局所StyleをGlobal Token／Reusable Componentへ統合する
- Header、Page Width、Typography、Spacing、Control、Status、Errorを一つのVisual Languageへ揃える
- Signed-out Landingの古い`Deferred digests arrive in a later phase`を現在の機能に合わせる
- Desktop一行Navigationと明示的なMobile Collapseを実装する
- Keyboard Focus、Form Error、Destructive Action、Loading／Empty／Retry／Failure／Completedを視覚だけに依存せず区別する
- Light／Darkを`prefers-color-scheme`で切り替え、両ThemeのContrastとHierarchyを揃える
- Static Textのem dash／en dashを使用しない

## Visual Direction

### Foundation

- Off-white／graphiteのNeutral Baseへ、一つのSignal Orange Accentを使用する
- Light／DarkのSemantic CSS VariableをGlobal Styleで定義する
- Pure `#000000`／`#ffffff`、AI-purple、Neon Glow、Glassmorphismを使わない
- FontはSelf-hosted Variable SansとMonoをPackageへ固定し、Network Fontを読み込まない
- Body CopyはSans、Operation／Week／Count／Status等の短いTechnical MetadataだけMonoを使う
- Page Containerは最大74rem。Reading Columnは最大68ch。Mobileは1 Columnへ明示Collapseする
- Panelは14px、Input／Buttonは9pxを基本とし、PillはStatusまたはCompact Identityだけに限定する
- Cardは実際のHierarchyがあるFeed／Statusだけに使い、全要素をContainerへ入れない

### Motion

- MotionはPage Entry、State Transition、Button Feedbackだけに限定する
- `transform`と`opacity`以外をAnimationしない
- `prefers-reduced-motion: reduce`ではAnimation／Smooth Behaviorを停止する
- Infinite Decorative Loop、Scroll Listener、Parallax、Marqueeを追加しない
- DigestのPending Animationは状態を伝えるPurposeを持ち、Text／Iconでも同じ状態を表す

### Product Visual Exception

これはMarketing Landingではなく実Applicationである。Stock／Generated PhotoやFake Screenshotは追加せず、実際のPost／Digest／Journal-oriented Product SurfaceをVisual Contentとして扱う。ScreenshotはPlaywrightが実Runtimeから生成する。

## Reicon Contract

- 公式`reicon-svelte`をFrontend PackageへVersion固定する
- 使用IconだけをNamed Static Importし、Dynamic Name Registry、CDN、別Icon Libraryを使わない
- Standard Sizeは20px、Primary Empty／Status Visualは24px、Weightは原則`Outline`
- Decorative IconはAccessibility Treeから除外する
- Icon-only ControlはAccessible Nameを必須にする
- IconはText Labelを置き換えず、Navigation、Action、Semantic StateのScanを補助する
- Hand-written SVG Pathを追加しない

## Page Requirements

### Global Shell

- Skip Link、Brand、Primary Navigation、Account Action、Main Landmarkを実装する
- Desktop Navigationは一行、80px以下。Mobileは横Overflowに逃げず、明示的なCompact LayoutへCollapseする
- Current Routeを視覚と`aria-current="page"`で示す
- Signed-out／Signed-inのNavigation優先順位を分ける
- LogoutはButtonのまま保ち、Linkへ偽装しない

### Landing

- Asymmetricだが初期Viewportへ収まるProduct Introductionにする
- BlackOps FrameworkのReference Applicationであること、Post／Comment、Deferred Digestの三つを短く説明する
- Signed-outはRegisterをPrimary、LoginをSecondaryにする
- Signed-inはFeedをPrimary、WriteをSecondaryにする
- Backend／Identity unavailableはCTAと混同しないContextual Statusにする
- 古いRoadmap Copy、Version Label、Fake Metric、Scroll Cueを置かない

### Authentication and Account

- LabelをInput上、Helper／ErrorをInput下に統一する
- ErrorはFieldとの`aria-describedby`で関連付け、Colorだけに依存しない
- Password Requirementを送信前から読めるHelperとして表示する
- Account DetailはDefinition ListをResponsiveにする

### Feed, Post, Comment, Forms

- FeedはTitle、Preview、Author、Time、Comment CountのReading Orderを明確にする
- Empty StateはWrite Actionへ導く
- PaginationはCurrent PageとPrevious／NextをKeyboardで明確にする
- Post Detailは本文を読みやすいColumnへ置き、Owner ActionとComment Composerを分離する
- DeleteはDestructive Visualを持つが、AuthorizationとConfirmation Contractを変更しない
- New／Edit／Comment FormはServer Validation、Value Replay、Error Associationを維持する
- User-generated Textを装飾目的で書き換えない

### Digest

- ISO Week FormにFormat Helperを置く
- accepted／running／retry_scheduled／failedをIcon、Label、Descriptionで区別する
- Progressは`aria-live`を維持し、Client Pollが失敗してもRefresh Linkを残す
- Completed DetailはContentを主、Post／Comment CountとGenerated Atを補助情報にする
- Filled Progress Bar、Fake Percentage、Decorative Status Dotを使わない

## Browser E2E Contract

- Playwright ChromiumをVersion固定し、Frontend Package Scriptを追加する
- BrowserはSvelteKit Originだけへ接続し、BlackOps Debug Portを直接呼ばない
- TestはRegister、LoginまたはSession継続、Create Post、Feed、Detail、Comment、Edit、Deferred Digest Retry／Complete、Logoutを実UIで完走する
- Keyboard Tab／Focus、Form Validation Error、Mobile Viewport、Dark Color Scheme、Reduced Motionを検証する
- `@axe-core/playwright`等のVersion固定されたScannerで主要PageにCritical／Serious Violationがないことを確認する
- Screenshotは実Runtime、Fixture User／Content、Credential非表示、Internal URL非表示で生成する
- Testは有限Timeout、明示Cleanup、独立Database Volumeを持ち、失敗時もContainer／Generated ArtifactをCleanupする

## Files Allowed to Change

- `examples/community-board/frontend/package.json`
- `examples/community-board/frontend/pnpm-lock.yaml`
- `examples/community-board/frontend/vite.config.ts`
- New `examples/community-board/frontend/playwright.config.ts`
- `examples/community-board/frontend/src/app.html`
- `examples/community-board/frontend/src/**/*.svelte`
- New `examples/community-board/frontend/src/lib/components/**`
- New `examples/community-board/frontend/src/lib/styles/**`
- New `examples/community-board/frontend/e2e/**`
- `examples/community-board/frontend/.gitignore`
- New `tests/Consumer/community-board-browser.sh`
- `.github/workflows/ci.yml`
- New `docs/guide/assets/community-board/**`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P17-008-visual-accessibility-and-browser-e2e.md`
- New `develop/orchestration/reports/P17-008-visual-accessibility-and-browser-e2e.md`

既存Server Load／ActionまたはPHP Application変更が必要に見える場合は実装を広げず、ReportへBlockerとして返す。

## Out of Scope

- PHP Framework／Community Board Backend／Database Schema／Operation Contract変更
- Route、Form Field、BFF Safe Projection、Authentication Contract変更
- Quickstart／Skeleton変更
- README／Guide本文の最終同期（P17-009）
- Community Board／Documentation Websiteの外部Publication
- Generic Svelte Component LibraryまたはFramework-owned Frontend Adapter
- Ray.Aop置換実装

## Implementation Constraints

- Existing Route／Form／`data-testid`を先にInventoryし、Browser Contractを壊さない
- Dependency追加前に`package.json`を確認し、公式Reicon Svelte PackageだけをIcon Sourceにする
- StylingはGlobal TokenとSmall Reusable Componentを優先し、同一CSSをRouteへ複製しない
- Svelte 5の既存Runes／SSR Contractを維持する
- BrowserへGenerated Operation、Private Env、Credential、Raw Backend Error、Internal URLを渡さない
- Static Visible Copyに`—`または`–`を残さない
- User-generated Contentは上記Dash Guardの対象外とする
- External Font／Icon CDN、Runtime Network Assetを追加しない
- Screenshot以外のGenerated Build Artifact、Node Modules、Playwright Report、Trace、VideoをCommitしない
- WorkerはCommitしない

## Acceptance Criteria

- [ ] Design Read、Dial、Auditに沿う一貫したVisual Languageが全Routeへ適用される
- [ ] Reicon SvelteをStatic Importし、別Icon Family／CDN／hand-written SVGを使わない
- [ ] Global Token、Light／Dark、Typography、Spacing、Shape、Focusが統一される
- [ ] Desktop／MobileでHorizontal OverflowがなくNavigationとFormが利用できる
- [ ] Landing、Authentication、Feed、Detail、Form、Digestの全Stateが完成する
- [ ] Existing Server Contract、Progressive Enhancement、Test ID、Safe Error境界を維持する
- [ ] Keyboard、Focus、Label、Error Association、Contrast、Reduced Motionを検証する
- [ ] PlaywrightがRegisterからLogoutまでの実Browser Journeyを完走する
- [ ] 主要PageのCritical／Serious Accessibility Violationが0である
- [ ] Credential／Internal URLを含まない実画面ScreenshotをCommitする
- [ ] Frontend check、Vitest、Build、Browser E2E、既存5 Consumer Journeyが成功する
- [ ] Root Mago／PHPUnit／DeptracとQuickstart Guardが成功する
- [ ] Runtime／Generated／Browser ArtifactをCleanupする
- [ ] Report／TODO／STATEが実装と一致する
- [ ] WorkerはCommitしない

## Required Commands

```bash
docker compose -f examples/community-board/compose.yaml run --rm app composer validate --strict
docker compose -f examples/community-board/compose.yaml run --rm app composer install --no-interaction --prefer-dist --no-progress
pnpm --dir examples/community-board/frontend install --frozen-lockfile
pnpm --dir examples/community-board/frontend run check
pnpm --dir examples/community-board/frontend run test
pnpm --dir examples/community-board/frontend run build
bash tests/Consumer/community-board-browser.sh
bash tests/Consumer/community-board-foundation.sh
bash tests/Consumer/community-board-identity.sh
bash tests/Consumer/community-board-post-comment.sh
bash tests/Consumer/community-board-product-journey.sh
bash tests/Consumer/community-board-digest.sh

docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
! rg -n '—|–' examples/community-board/frontend/src --glob '*.svelte' --glob '*.ts' --glob '*.css'
git diff --check
git diff --exit-code -- examples/quickstart src
```

## Completion Report

`develop/orchestration/reports/P17-008-visual-accessibility-and-browser-e2e.md`へ少なくとも次を記載する。

- Summary
- Design Read and Audit
- Visual System and Reicon Usage
- Accessibility and Responsive Evidence
- Browser Journey and Screenshot
- Sensitive and Server-only Boundary
- Changed Files
- Commands and Results
- Skill Pre-flight Matrix
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
