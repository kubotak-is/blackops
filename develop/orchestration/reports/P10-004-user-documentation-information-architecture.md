# P10-004 User Documentation Information Architecture Report

## Summary

Framework利用者向けDocumentationを18 Pageの日本語単一Locale Siteへ再編した。LandingからStable `1.0.0` Install、Directory Structure、最初のTyped Self-handled Operation、Local Runtimeまでを連続Linkで接続し、Getting Started、Operations、Execution、Database、Referenceの5 Sectionへ全公開Guideを配置した。

`docs/website/content-map.mjs`をSource Relative Pathから公開Slug／Metadataへの正本とし、`site-navigation.mjs`で5 Section Sidebarを明示した。全PageへDocument Channel `main`、Latest Stable `1.0.0`、mainが未Release変更を含み得る旨を表示する。Stable `1.0.0`に未収録のGenerator／Application MigrationをStable機能と誤認しないよう、Install、Generator、Project CLI、Current Statusで区別した。

## Information Architecture

| Section | Public pages |
| --- | --- |
| Landing | `/` |
| Getting Started | `/getting-started/installation/`, `directory-structure`, `first-operation`, `local-runtime`, `quickstart` |
| Operations | `/operations/authoring/`, `generators`, `lifecycle` |
| Execution | `/execution/http-and-deferred/`, `context` |
| Database | `/database/migrations/`, `outcomes`, `retention` |
| Reference | `/reference/configuration/`, `application-bootstrap`, `project-cli`, `current-status` |

Source Fileは通常のRepository Markdownとして`docs/guide/`直下に維持する。Content Mapは全18 Sourceを公開Slugへ対応させ、未登録Source、Mapが指す欠落Source、Slug欠落、不正Slug、重複SlugをFail-fastする。Slugは`index`を含むlowercase kebab-case相対Segmentだけを許可し、Absolute Path、`.`／`..`、Traversal、Backslash、Uppercaseを拒否する。

Sidebar検証はLanding以外の全Slugが5 Sectionへ一度だけ配置され、未知Slug、重複、未配置、Section順序変更がないことを確認する。Starlightが生成するFallback `404.html`はGuide Source／Navigation／Pagefind対象ではないUtility Pageである。

## User Journey Evidence

- Landing HeroからInstallとQuickstartへ1 Actionで到達する。
- InstallからDirectory Structure、First Operation、Local Runtimeへ本文Linkが連続する。
- `site:check`は上記Routeと全18 HTMLの存在を検証し、Source `.md` LinkがStatic Artifactへ残らないことも拒否する。
- First OperationのOperation／Value／Outcome PHP Code Blockは`examples/quickstart/app/Feature/Welcome/ShowWelcome/`の3 Sourceとbyte一致をNode Testで検証する。
- Public API実装とQuickstartを照合し、Typed Self-handled Native Signature、Optional `ExecutionContext`、`void`、`OperationRejectedException`、`#[Route]`、`#[ExecuteWith(Deferred::class)]`、Public `Application` Builder、Project `bin/blackops`を現在のNamespace／Shapeで記載した。
- `docker compose run --rm app mago analyze examples/quickstart/app`はNo issuesで成功した。

## Accessibility and Search Evidence

### Mobile Navigation

独自Navigation ComponentやCSSを追加せずStarlight標準Sidebarを使用した。`site:check`はLandingを除く17 Document PageでMobile Menuの`aria-label="メニュー"`、初期`aria-expanded="false"`、`aria-controls="starlight__sidebar"`を確認した。Built ComponentはViewport変更時のClose、Menu展開時のMain Inert、Escape時のCloseとButton FocusをStarlight標準Scriptで提供する。

### Keyboard and Skip Link

全18 Pageで`コンテンツにスキップ` Linkと`href="#_top"`／H1 Targetを確認した。Search Buttonは`aria-label="検索"`と`aria-keyshortcuts="Control+K"`、Dialog Labelを持つ。Mobile MenuとTheme Selectorも標準のButton／Selectを維持する。

### Contrast

Custom CSS、Custom Color Token、Component Overrideを追加していない。Built SiteがStarlight標準Dark／Light Theme、Theme Selectorの視覚外Label、Focus時に表示されるSkip Link、標準Text／Accent／Background Tokenを使用することをStatic Artifactで確認した。独自Version NoticeもStarlight標準BannerをFrontmatterから使用するため、標準Contrastを変更しない。

### Search

Pagefind Entryが日本語18 PageをIndexし、Fallback 404を除外することを確認した。`site:check`はNetwork不要のFetch Adapterから生成済みPagefind WASM／Indexを実際にLoadし、`Operation` QueryがOperationsまたはFirst Operation Pageを返すことを検証した。Version Bannerは`data-pagefind-ignore`により検索本文へ混入しない。

## Existing Guide Migration and Removed Content

- `runtime-bootstrap.md`: Install／SetupをInstallation、Compose起動をLocal Runtime、HTTP／WorkerをExecution、Config／BuildをApplication Bootstrap／Project CLIへ移行した。
- `runtime-bootstrap.md`: 旧Standalone `bin/console`、`config/blackops/*`、Application Provider File、`ProductionRuntimeComposer`を使う説明は、現行Installed Application Public APIではないため公開Guideから除去した。現行`bin/blackops`／`config/*.php`／Build-time Discoveryへ置換済みである。
- `mvp-sample.md`: Repository Root PHPUnit／Dev Autoload等のFramework検証手順を公開Quickstartから除外し、利用者がInstall後に実行するInline／Deferred／Worker手順へ置換した。検証履歴はInternal StatusとCloseout Reportに維持される。
- `mvp-status.md`: Test Class名、Commit／Phase根拠を含むDefinition of Done Tableを公開Current Statusから除外した。利用者向けCapability／Stable-main差／制約を残し、完了根拠は`docs/internal/installed-application-status.md`のMVP Completion Evidenceへ移した。
- `database-migrations.md`: Integration Test helperの既存Schema互換説明を公開対象から除外した。利用者向けの明示Migration、Application Migration、Fail-fast境界は維持した。
- `application-bootstrap.md`: JSONL Journal設定はConfiguration Referenceへ移し、Environment、Config、HTTP、Console、Service Bindingを現行Public Builderへ整理した。
- `project-generators.md`、`outcome-retrieval.md`、`retention.md`の利用者向け情報は削除せず、それぞれOperations／Database Sectionへ配置した。

## Changed Files

- `README.md`
- `docs/guide/README.md`
- `docs/guide/installation.md`
- `docs/guide/directory-structure.md`
- `docs/guide/first-operation.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/operations.md`
- `docs/guide/project-generators.md`
- `docs/guide/operation-lifecycle.md`
- `docs/guide/execution.md`
- `docs/guide/execution-context.md`
- `docs/guide/database-migrations.md`
- `docs/guide/configuration.md`
- `docs/guide/application-bootstrap.md`
- `docs/guide/project-cli.md`
- `docs/guide/mvp-status.md`
- `docs/internal/installed-application-status.md`
- `docs/website/README.md`
- `docs/website/astro.config.mjs`
- `docs/website/package.json`
- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/scripts/content-pipeline.mjs`
- `docs/website/scripts/generate-content.mjs`
- `docs/website/scripts/check-content.mjs`
- `docs/website/scripts/check-artifact.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/tests/content-pipeline.test.mjs`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/site-navigation.test.mjs`
- `develop/orchestration/tasks/P10-004-user-documentation-information-architecture.md`
- `develop/orchestration/reports/P10-004-user-documentation-information-architecture.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Stable Install導線は`composer create-project blackops/skeleton my-app 1.0.0`を使用し、main SourceのInstallを暗黙に勧めない。
- Stable `1.0.0`にはTyped Self-handled Welcome／Reportが含まれるため、最初のOperationはInstall済みWelcome Sourceを正として説明する。
- `make:operation`、`make:migration`、Application Migration Runtimeはmainに実装済みだがStable `1.0.0`に未収録であることを明示する。
- LandingだけはStarlight Splash／Heroを使用し、Document Pageは標準Sidebar／Table of Contentsを使用する。
- Version NoticeはCustom Componentを作らずStarlight標準Bannerへ決定的に生成する。
- Browser Runtimeを追加Dependencyとして導入せず、Starlight標準Componentを維持したStatic Markup／Script、Pagefind実Searchで検証した。

## Commands and Results

```text
mise exec -- pnpm --dir docs/website install --frozen-lockfile
Result: Already up to date; pnpm 11.12.0.

mise exec -- pnpm --dir docs/website run test
Result: 16 tests / 16 passed / 0 failed. Generator、Slug、Sidebar、Guide Code／Channel Tests included.

mise exec -- pnpm --dir docs/website run check
Result: Content validation and determinism passed. Astro check 14 files / 0 errors / 0 warnings / 0 hints.

mise exec -- pnpm --dir docs/website run build
Result: 18 public pages plus fallback 404 built. Pagefind, sitemap, artifact boundary, site navigation, accessibility markup, actual search checks passed.

rg -n 'main|1\.0\.0' docs/website/dist
Result: Document Channel and Latest Stable were present in the static artifact.

! rg -n 'BlackOps\\Internal|P[0-9]+-[0-9]+|Acceptance Evidence|docs/internal' docs/website/dist
Result: No public boundary violation; negated command exited 0.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago analyze examples/quickstart/app
Result: INFO No issues found.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No management ID references; negated command exited 0.

git diff --check
Result: No output.
```

## Orchestrator Review

- Stable `1.0.0`のInstall済みWelcomeをGetting Startedの標準例とし、`make:operation`／`make:migration`／Application Migration Runtimeをmainの未Release機能として分離できていることを確認した。
- First Operationの3 Code BlockとQuickstart Sourceのbyte一致、Typed Self-handled Signature、Optional Context、Native Outcome／Void、Rejection Exception、Application Builder、Project CLIのPublic API整合を確認した。
- 初回Reviewで明示SlugのPath Traversal／欠落Guardを追加させた。再Review中にWorkerがSource相対Linkの`.md` href残存を検出し、全内部LinkをPublic Routeへ変換してArtifact Guardを追加した。
- Frozen Install、Node Test、Content／Astro Check、Static Build、18 PageのUser Journey／Version Notice／Accessibility Markup、Pagefind実Search、Artifact Boundary、Quickstart Mago Analyze、PHP Format、管理番号Guard、`git diff --check`を再実行し、すべて成功した。
- Guideから除外したAcceptance Evidenceと旧Runtime説明の移行先がReportに列挙され、利用者向け情報が失われていないことを確認した。
- Worker差分はFiles Allowed to Change内に限定され、Orchestrator所有のTask Packetは受入Statusだけを更新した。

### GitHub Actions Evidence

```text
Commit: 557f5a9bbae2dff66a81afd33db8b080e5a6cc21
Run: 29240094053
Documentation website: success (25s)
Mago / PHPUnit / Deptrac: success (1m6s)
```

## Acceptance Criteria

- LandingからInstall／Quickstartへ1 Action: Satisfied
- InstallからFirst Operation／Local Runtimeへの連続導線: Satisfied
- 5利用者Sectionへの全公開Guide配置: Satisfied
- Current Typed Self-handled Operation／Value／Outcome: Satisfied
- Project CLI、HTTP／Deferred、Migration／Outcome／Retention入口: Satisfied
- `main`とLatest Stable `1.0.0`の差: Satisfied
- Internal／Contributor／Acceptance Content排除: Satisfied
- Mobile／Keyboard／Skip Link／Contrast／Search: Satisfied
- 全内部Link／Static Build: Satisfied

## Remaining Issues

P10-004のAcceptance Criteriaに残作業や仕様Blockerはなく、Orchestrator Reviewで受け入れた。

Starlightは専用Guide Source `404`を持たないため、Build時にFallback 404を生成して`Entry docs → 404 was not found.`と表示する。FallbackはBuild成功、Navigation外、Pagefind外でありP10-004の公開IAへ影響しない。

## Suggested Next Action

P10-005 Cloudflare Pages Deliveryへ進む。
