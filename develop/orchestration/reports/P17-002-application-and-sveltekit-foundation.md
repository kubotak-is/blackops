# P17-002 Application and SvelteKit Foundation Report

## Summary

`examples/community-board/`へQuickstart／Skeletonと独立したPHP Application、PostgreSQL、FrankenPHP、Deferred Worker、SvelteKit adapter-nodeのFoundationを追加した。Unauthenticated Inline GET Operation `ShowBoardWelcome`をServer-only Generated Operation ObjectからApplication-owned `.server.ts` Wrapper経由で呼び、SvelteKit SSR LandingへSafe View Modelとして表示する。

Composer／pnpm lock、明示Setup、Compose、Strict TypeScript、Unit Test、Production Build、Real HTTP Consumer E2E、Server-only Import／Browser Artifact／Tracking Guard、独立CI Jobを固定した。Identity、Post、Comment、Digest、Final Visual Design、Framework Production Code、Quickstartは変更していない。User指定のReiconは後続Design Taskで採用するため、このTaskではIcon Library、Placeholder Icon、Hand-written Iconを追加していない。

## Changed Files

- New `examples/community-board/**`
  - Application identity、Composer path repository、lockfile
  - Bootstrap、config、Project `blackops` CLI、idempotent setup
  - `ShowBoardWelcome` Operation／Value／Outcome
  - PostgreSQL／PHP tooling／FrankenPHP／Deferred Worker／SvelteKit Compose topology
  - SvelteKit server-only wrapper、SSR landing、unit test、adapter-node build configuration
  - Application／Frontend ignore policy、README
- New `tests/Consumer/community-board-foundation.sh`
- `.github/workflows/ci.yml`
- `develop/TODO.md`
- `develop/STATE.md`
- New `develop/orchestration/reports/P17-002-application-and-sveltekit-foundation.md`

## Decisions and Assumptions

- Root FrameworkはDevelopment-only Composer path repository `../..`として`dev-main`を消費し、Application自身の`vendor/`へinstallする。Framework SourceをCopyしない。
- Community Boardは`blackops/community-board-example`というSkeletonと異なるApplication identityを持つ。
- `ShowBoardWelcome`は`board.welcome.show`、`GET /welcome`、Inline、Authenticationなしとし、Outcomeは表示用`message`と`summary`だけを持つ。
- Generated Outputは`frontend/src/lib/server/blackops/generated/`だけに出す。Generated ESMをImportするApplication Sourceは`operations.server.ts`だけである。
- SvelteKit Request EventのFetchはDOM Native `RequestInit`、Generated `OperationFetch`はFramework-neutral Structural Signalを使うため、`.server.ts`内の薄いAdapterで接続する。Global Mutable Clientは追加しない。
- `+page.server.ts`はApplication-owned WrapperだけをImportする。`.svelte`からGenerated Module、Private Environment、BlackOps URLへ依存しない。
- WrapperはTransport／Protocol／Rejected／Validation／Internal／Unexpectedを同じ安定Unavailable Viewへ閉じ、Raw Body、URL、Exception DetailをPage Dataへ出さない。
- Foundation UIはText-only Neutral UIとし、Taste SkillのFinal Visual、Image、Motion、Reicon適用はP17-007へ送る。
- pnpm 11.12.0のSupply-chain Gateが直近Stable SvelteKitを許可するために生成した`pnpm-workspace.yaml`をApplication-owned設定として追跡する。

## Composer and SvelteKit Dependency Versions

### Composer

- PHP: `>=8.5`
- `blackops/framework`: `dev-main`、lock時Source Reference `7b04513`、path repository
- Doctrine DBAL: `4.4.3`
- Doctrine Migrations: `3.9.7`
- PHP Dotenv: `5.6.4`
- Nyholm PSR-7: `1.8.2`

### Frontend

- Node: `24.18.0`
- pnpm: `11.12.0`
- SvelteKit: `2.70.1`
- Official `@sveltejs/adapter-node`: `5.5.7`
- Svelte: `5.56.6`
- TypeScript: `6.0.3`
- Vite: `8.1.5`
- Vitest: `4.1.10`
- svelte-check: `4.7.3`

全Frontend DependencyはExact Versionと`frontend/pnpm-lock.yaml`で固定した。

## Runtime and Compose Topology

```text
Browser :5173
  -> SvelteKit adapter-node :3000
    -> BLACKOPS_BASE_URL=http://http (private environment)
      -> FrankenPHP :80
        -> PostgreSQL 18 :5432

PHP tooling profile: app
Deferred worker profile: worker -> php blackops worker:run
Local debug only: localhost:${BLACKOPS_DEBUG_PORT:-8081} -> FrankenPHP :80
```

SvelteKitがBrowser向けApplication Portを所有し、PHP Portは名前と既定Portを明示したLocal Debug用途に分離した。Composer Install、pnpm Install、Migration、Build、Generate、Check、Service Startは別Commandであり、Setupは`.env` CopyとRuntime Directory準備だけを行う。

## Server-only Import Boundary

```text
frontend/src/lib/server/blackops/generated/**
  -> frontend/src/lib/server/blackops/operations.server.ts
    -> frontend/src/routes/+page.server.ts
      -> Safe Page Data
        -> frontend/src/routes/+page.svelte
```

Consumer E2EはApplication Source内のGenerated ImportがWrapper一件だけであること、Browser Client Buildへ`BLACKOPS_BASE_URL`、`http://http`、PostgreSQL Credential Marker、Root Absolute Path、Raw Error Markerが入らないことを検証する。

## Welcome Request and Result Journey

1. BrowserがSvelteKit `/`へGETする。
2. `+page.server.ts`がRequest Event Fetchを`loadBoardWelcome()`へ注入する。
3. `.server.ts` WrapperがPrivate `BLACKOPS_BASE_URL`とFetch AdapterをGenerated `ShowBoardWelcome.fetch()`へ渡す。
4. Generated OperationがFrankenPHPの`GET /welcome`を呼ぶ。
5. PHP OperationがSafe `BoardWelcomeShown`を返す。
6. Wrapperが成功Resultを`BoardWelcomeView`へ投影し、SSR Landingが表示する。
7. Backend停止時は同じSSR Routeが安定したUnavailable Messageだけを返す。

## Commands and Results

```text
docker compose -f examples/community-board/compose.yaml config
Result: Success。PostgreSQL、app、http、worker profile、frontend topologyを解決した。

docker compose -f examples/community-board/compose.yaml build app http frontend
Result: Success。PHP 8.5 CLI、FrankenPHP 1 PHP 8.5、Node 24.18.0 imageをBuildした。

docker compose -f examples/community-board/compose.yaml run --rm app composer validate --strict
Result: ./composer.json is valid。

docker compose -f examples/community-board/compose.yaml run --rm app composer update --no-interaction --prefer-dist --no-progress
docker compose -f examples/community-board/compose.yaml run --rm app composer install --no-interaction --prefer-dist --no-progress
Result: 42 locked packages、path repository Framework、No security advisories。installはE2E／Frozen構成で成功した。

mise exec -- pnpm --dir examples/community-board/frontend install
mise exec -- pnpm --dir examples/community-board/frontend install --frozen-lockfile
Result: Exact dependency lockを生成し、Frozen Lock／Supply-chain Policy Check成功。

docker compose -f examples/community-board/compose.yaml run --rm app php blackops database:migrate
Result: Database migrations applied、2 migrations。

docker compose -f examples/community-board/compose.yaml run --rm app php blackops build:compile
docker compose -f examples/community-board/compose.yaml run --rm app php blackops frontend:generate
docker compose -f examples/community-board/compose.yaml run --rm app php blackops frontend:check
Result: Build成功。4 frontend filesをServer-only Directoryへ生成しFresh Check成功。

mise exec -- pnpm --dir examples/community-board/frontend run check
Result: svelte-check 0 errors、0 warnings。

mise exec -- pnpm --dir examples/community-board/frontend run test
Result: 1 file、4 tests passed。

mise exec -- pnpm --dir examples/community-board/frontend run build
Result: Vite SSR／Client BuildとOfficial adapter-node output成功。

bash tests/Consumer/community-board-foundation.sh
Result: Success。Real HTTP SvelteKit SSR -> .server.ts -> Generated Operation -> BlackOps、Backend停止Unavailable、Server-only Import、Browser Artifact、Tracking、Quickstart Guard成功。

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstart valid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: Artifact cleanup後に全成功。初回Format CheckはCommunity Board `vendor/blackops/framework` path symlink loopを走査して失敗したため、Task Artifact Boundaryどおり依存／Generated／Build ArtifactをCleanupして再実行した。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1430 tests, 5679 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2530 / Warnings 0 / Errors 0。

docker compose run --rm app php examples/community-board/bin/setup (twice)
Result: 初回は.env作成、二回目は既存.envを保持。Runtime Directoryを保持し、Install／Migration／Build／Startなし。

git diff --exit-code -- examples/quickstart
Tracking Guard、Management ID Guard、git diff --check
Result: 全成功。
```

Hostには`php` Commandがないため、Setup再実行TestはRoot PHP 8.5 Containerで実行した。Host `pnpm`は直接PATHにないため、Repository固定Toolchainの`mise exec -- pnpm`を使用した。

## Acceptance Criteria

- [x] Independent Application identity／layout
- [x] Root Framework path dependency、Framework source copyなし
- [x] Composer／pnpm lock
- [x] PHP／PostgreSQL／Worker／SvelteKit Compose valid
- [x] Side-effectを暗黙実行しないidempotent setup
- [x] Unauthenticated Inline `ShowBoardWelcome`
- [x] Server-only generated contract generate／check
- [x] Application-owned `.server.ts`だけがGenerated OperationをImport
- [x] SSR landingにWelcome Outcomeを表示
- [x] Failure時にRaw Body／Internal URL／CredentialをBrowserへ非露出
- [x] Svelte Check／Unit Test／Production Build
- [x] Real HTTP Foundation Consumer E2E
- [x] Dependency／Generated／Build／Runtime Artifact非追跡、Session終了時Cleanup
- [x] Quickstart／Skeleton Source変更なし
- [x] Framework `src/**`変更なし
- [x] Required Quality Gate成功
- [x] Worker Commitなし

## Remaining Issues

なし。Identity、Post／Comment、Digest、Final Visual／Accessibility、Reicon、Browser AutomationはTask境界どおり後続Taskに残る。

## Suggested Next Action

Orchestratorが差分と独立VerificationをReviewし、Accepted後にCommitする。その後P17-003でApplication-owned Identity／Session／BFF Credential Boundaryを実装する。

## Orchestrator Review

Accepted。

OrchestratorはTask Packet、全変更File、Composer／SvelteKit Lock、Compose Topology、Welcome Operation、Server-only Wrapper、SSR Page、Unit Test、Consumer E2E、CI Jobを独立Reviewした。Production `src/**`、既存Root Test、Quickstart／Skeletonへの変更はなく、Identity、Post、Digest、Final Visual Design、ReiconをP17-002へ混在させていない。

Clean状態からCommunity Board Image Build、Composer Frozen Install、pnpm Frozen Installを再実行した。Real HTTP Consumer E2EはSvelteKit SSRからGenerated Operationを経由したWelcome表示、Backend停止時のSafe Unavailable、Server-only Import、Browser Artifact／Sensitive／Path Guardを再度完走した。E2E後のGenerated／Build／Runtime Artifactと専用Container／VolumeはCleanupされた。

Root Composer／Quickstart／Community Board Validate、Mago format／lint／analyze、Full PHPUnit 1430 tests／5679 assertions、Deptrac 0違反／0警告／0エラー、Management ID Guard、`git diff --check`も成功した。

Userが実装中に指定したReiconは、独立Commit `209489e`でD103とPhase 17 Specification／Delivery Planへ固定した。P17-002はText-only Foundationを維持し、`reicon-svelte`導入はP17-007へ残した。
