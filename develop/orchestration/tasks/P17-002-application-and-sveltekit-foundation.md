# P17-002: Application and SvelteKit Foundation

Status: Ready

## Goal

`examples/community-board/`へQuickstart／Skeletonと独立したBlackOps PHP ApplicationとSvelteKit BFFのFoundationを構築し、BlackOpsの最小Welcome OperationをServer-only Generated Operation Wrapperから呼び、SSR Landingへ表示するLocal／CI Journeyを完成させる。

## In Scope

- Independent `examples/community-board/` Application Layout
- Root FrameworkをComposer Path Repositoryとして消費するDevelopment-only構成
- PHP 8.5、FrankenPHP、PostgreSQL 18、Deferred Worker、Node 24／pnpm 11 SvelteKitのCompose Topology
- Application-owned `.env.example`、Bootstrap、Config、Project `blackops` CLI、Setup
- `ShowBoardWelcome` Inline HTTP OperationとSafe Outcome
- `frontend/src/lib/server/blackops/generated/`へのFrontend Generate／Check
- Application-owned `.server.ts` WrapperだけがGenerated OperationをImportする構成
- `+page.server.ts`からWrapperを呼び、SSR LandingへWelcome Resultを表示するJourney
- SvelteKit Strict TypeScript、Check、Unit Test、Production Build
- Generated／Build／Node Modules／Credential／Browser Bundle／Quickstart Tracking Guard
- Community Board Foundation Consumer E2E
- GitHub Actionsの独立Community Board Foundation Job
- Minimal READMEとLocal Command
- Report、TODO、STATE同期

## Out of Scope

- User、Password、Session、Registration、Login、Logout
- Application-owned Authentication Router、BFF Cookie、`HttpAuthenticator`
- Post、Comment、Digest Table／Repository／Operation
- Authorization、Owner Policy、Status Authorizer
- Deferred Business Operation、`.status()`／`.wait()` UI
- Final Visual Design、Taste Skill適用、Animation、Screenshot
- Browser Automation／Playwright
- Framework `src/**`とRoot TestのProduction Contract変更
- `examples/quickstart/**`、Skeleton Source、Publication Workflowの変更
- Documentation Website Content／Publication／Deploy
- Root Composer Dependency追加

## Relevant Specifications and Decisions

- `develop/decisions/103-full-stack-reference-application.md`
- `develop/spec/41-developer-experience-roadmap.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/71-full-stack-reference-application.md`
- `develop/spec/72-phase-17-delivery-plan.md`
- `docs/internal/installed-application-status.md`
- `docs/guide/directory-structure.md`

## Files Allowed to Change

### Reference Application

- New `examples/community-board/**`

Generated／Dependency／Runtime Artifactは作業中に生成してよいが、Task完了前にIgnore／Cleanup／Tracking Guardを固定する。

### Consumer and CI

- New `tests/Consumer/community-board-foundation.sh`
- `.github/workflows/ci.yml`

### Documentation and Orchestration

- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P17-002-application-and-sveltekit-foundation.md`
- `develop/spec/71-full-stack-reference-application.md`（実装不能な矛盾を発見した場合だけ）
- `develop/spec/72-phase-17-delivery-plan.md`（Task境界の誤りを発見した場合だけ）

上記以外の変更が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。特に`src/**`、既存`tests/**`、`examples/quickstart/**`を変更しない。

## Foundation Contract

### Composer Boundary

- Package Nameは`blackops/community-board-example`等、Skeletonと異なるApplication Identityにする
- Repository内Developmentでは`../..`のRoot FrameworkをComposer Path Repositoryとして消費する
- Framework SourceをExampleへCopyしない
- Exampleは自身の`vendor/`をRuntimeで持てるが追跡しない
- Exampleは配布Skeletonではないため、再現可能なComposer Lockを追跡してよい
- Root `composer.json`とRoot Dependencyを変更しない

### SvelteKit Boundary

- SvelteKitのStable ReleaseとOfficial `@sveltejs/adapter-node`を使い、`frontend/pnpm-lock.yaml`へ固定する
- Node `24.18.0`とpnpm `11.12.0`をRepository ToolchainとComposeで一致させる
- TypeScriptはStrictとし、`check`、`test`、`build` Scriptを持つ
- Generated Outputは`frontend/src/lib/server/blackops/generated`に限定する
- Application-owned Wrapperは`frontend/src/lib/server/blackops/operations.server.ts`等の`.server.ts`とする
- `+page.server.ts`はApplication-owned WrapperだけをImportし、Generated Moduleへ直接依存しない
- `.svelte`、`+page.ts`、`+layout.ts`、Browser用Shared ModuleはGenerated ModuleをImportしない
- SvelteKit ServerはPrivate EnvironmentからBlackOps Base URLを読み、Browser Bundleへ埋め込まない

### Welcome Vertical Slice

- `ShowBoardWelcome`はAuthentication不要のInline GET Operationとする
- OutcomeはLanding表示に必要なSafe Stringだけを持ち、Environment、Path、Credential、Build Artifactを返さない
- `build:compile -> frontend:generate -> frontend:check`でOperation Objectを生成する
- `.server.ts` WrapperはInjected FetchとServer-only Base URLで`.fetch()`を呼ぶ
- `+page.server.ts`はResultをSafe Landing View Modelへ変換する
- Transport／Unexpected／5xx時はRaw BodyやURLをBrowserへ返さず、安定したUnavailable Stateにする
- BrowserからBlackOps Endpointへ直接Fetchしない

### Compose and Commands

- PostgreSQL、PHP Tooling、FrankenPHP HTTP、Deferred Worker、SvelteKitを一つのExample-owned Compose Projectで定義する
- SvelteKitはBrowser向け唯一の公開Application Portとし、PHP PortはLocal Debug用途で明示的に分離する
- SetupはEnvironment／Runtime Directoryだけを準備し、Install、Migration、Build、Generate、Service Startを暗黙実行しない
- READMEはComposer Install、pnpm Install、Database Migration、Build、Frontend Generate／Check、Test、Compose Startを別Commandとして示す
- P17-002ではApplication Data Seedを実装せず、後続Identity／Post Taskで追加する

## CI and Artifact Boundary

- Community Board用CI JobはRoot Quality／Quickstart Jobと分離する
- Composer／pnpm Frozen Install、Build Compile、Frontend Generate／Check、Svelte Check／Test／Build、Foundation Consumer E2Eを実行する
- Cleanupは失敗時も実行する
- 次を追跡しない
  - `.env`
  - `vendor/`
  - `node_modules/`
  - `var/build/`
  - `var/log/`
  - Generated `frontend/src/lib/server/blackops/generated/`
  - SvelteKit `.svelte-kit/`、`build/`、Coverage／Test Artifact
- Generated Tree、Svelte Build、Browser BundleへCredential Marker、Root Absolute Path、Raw Error Bodyを含めない
- `examples/quickstart/`のTracked BytesとSkeleton Publication Sourceを変更しない

## Acceptance Criteria

- [ ] `examples/community-board/`が独立Application IdentityとLayoutを持つ
- [ ] Root FrameworkをPath Dependencyとして消費し、Framework SourceをCopyしない
- [ ] Example Composer／pnpm Lockが再現可能である
- [ ] PHP、PostgreSQL、Worker、SvelteKitのCompose Configurationが有効である
- [ ] SetupがSide-effectを暗黙実行せず再実行可能である
- [ ] `ShowBoardWelcome`がBlackOps Inline HTTP Operationとして応答する
- [ ] Frontend ContractがSvelteKit Server-only Directoryへ生成／検証される
- [ ] Generated OperationはApplication-owned `.server.ts` WrapperからだけImportされる
- [ ] SSR LandingがBlackOps Welcome Outcomeを表示する
- [ ] Failure時にRaw Body、Internal URL、CredentialをBrowserへ出さない
- [ ] Svelte Check／Unit Test／Production Buildが成功する
- [ ] Foundation Consumer E2Eが実HTTPのSvelteKit→BlackOps Journeyを確認する
- [ ] Generated／Dependency／Build Artifactが追跡されない
- [ ] Quickstart／Skeleton Sourceを変更しない
- [ ] Framework `src/**`を変更しない
- [ ] Required Quality Gateが成功する
- [ ] WorkerはCommitしない

## Required Commands

実際のService名やScript名を変更する必要がある場合、ReportにCanonical Commandを記録する。

```bash
docker compose -f examples/community-board/compose.yaml config
docker compose -f examples/community-board/compose.yaml build app http frontend
docker compose -f examples/community-board/compose.yaml run --rm app composer validate --strict
docker compose -f examples/community-board/compose.yaml run --rm app composer install --no-interaction --prefer-dist --no-progress
pnpm --dir examples/community-board/frontend install --frozen-lockfile
docker compose -f examples/community-board/compose.yaml run --rm app php blackops database:migrate
docker compose -f examples/community-board/compose.yaml run --rm app php blackops build:compile
docker compose -f examples/community-board/compose.yaml run --rm app php blackops frontend:generate
docker compose -f examples/community-board/compose.yaml run --rm app php blackops frontend:check
pnpm --dir examples/community-board/frontend run check
pnpm --dir examples/community-board/frontend run test
pnpm --dir examples/community-board/frontend run build
bash tests/Consumer/community-board-foundation.sh
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
git diff --exit-code -- examples/quickstart
! git ls-files \
  examples/community-board/.env \
  examples/community-board/vendor \
  examples/community-board/var/build \
  examples/community-board/var/log \
  examples/community-board/frontend/node_modules \
  examples/community-board/frontend/src/lib/server/blackops/generated \
  examples/community-board/frontend/.svelte-kit \
  examples/community-board/frontend/build | grep -q .
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' \
  src tests examples --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P17-002-application-and-sveltekit-foundation.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Composer／SvelteKit Dependency Versions
- Runtime／Compose Topology
- Server-only Import Boundary
- Welcome Request／Result Journey
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
