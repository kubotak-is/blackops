# P15-006 Consumer Experience and Phase Closeout Report

Status: Accepted

## Summary

Install直後のQuickstart／Composer SkeletonへApplication-owned Frontend Config、Frozen Package／Lockfile、Strict TypeScript Source／Testを追加した。Canonical `build:compile -> frontend:generate -> frontend:check` ChainからGenerated Operation Objectを作り、Welcome Inline、Order Transaction、Report Deferred、Validation、Diagnostics Internal、Injected Fetch Transportを実Worker Mode HTTPに対して検証するConsumer E2Eを常設した。

Skeleton通常／`--no-scripts`、Publication Working-tree Dry-run／Workflow、Framework Update、Backend-only Journeyを同期し、Guide／Documentation Website／CI／Internal Documentationを更新した。Full PHP、Frontend、Consumer、Website Gateは成功し、Websiteの外部Publication／Deployは行っていない。

## Changed Files

- Installed Quickstart: `.gitignore`、`README.md`、`bin/setup`、`config/frontend.php`、`package.json`、`pnpm-lock.yaml`、`tsconfig*.json`、`resources/js/application/operations.ts`、`tests/Frontend/**`
- Consumer／Publication／CI: `.github/workflows/ci.yml`、`tests/Consumer/quickstart-*.sh`、`skeleton-*.sh`、`framework-update-generators.sh`
- Public Guide: `docs/guide/README.md`、`mvp-sample.md`、`installation.md`、`project-cli.md`、`configuration.md`、`security.md`、`troubleshooting.md`、`directory-structure.md`、`testing.md`、`mvp-status.md`
- Website Regression: `docs/website/scripts/check-site.mjs`、`docs/website/tests/guide-code.test.mjs`、`reader-experience.test.mjs`
- Internal／Orchestration: `docs/internal/bootstrap.md`、`installed-application-status.md`、`develop/TODO.md`、`develop/STATE.md`、`develop/spec/68-phase-15-delivery-plan.md`、本Report

`examples/quickstart/tests/.gitignore`は既存Contractどおり変更していないため、新規`examples/quickstart/tests/Frontend/**`は通常の`git status`へ表示されない。OrchestratorはCommit時に`git add -f examples/quickstart/tests/Frontend`で明示的にStageする必要がある。Publication Workflow RegressionもTemporary Cloneで同じPathを`git add --force`する。

## Decisions and Assumptions

- Backend-only利用者へNode.jsを必須化しない。SetupはFrontend Commandを表示するだけで、Install、Build、Generate、HTTP、Workerを暗黙実行しない。
- Frontend Consumer SourceはApplication-owned、`resources/js/blackops/`はFramework-generatedかつIgnore対象とする。
- Generated ObjectへGlobal Mutable Client、Retry、Polling、Frontend Framework Adapterを追加せず、呼出単位の`operationOptions()`でBase URL、Header、Injected Fetchを渡す。
- Deferred 202はAcceptedまでを検証し、Status／Outcome PollingはPhase 16へ維持する。
- Stable `1.1.0`へFrontend Bridgeを遡及せず、Guideは`main` Experimentalとして区別する。
- Documentation WebsiteはLocal／CIで検証するが、外部公開しない。

## Installed Frontend Layout

```text
examples/quickstart/
  config/frontend.php
  package.json
  pnpm-lock.yaml
  tsconfig.json
  tsconfig.runtime.json
  resources/js/application/operations.ts
  tests/Frontend/
    typecheck.ts
    real-http.ts
    write-runtime-package.mjs
    clean.mjs
```

Generated `resources/js/blackops/`、`node_modules/`、`.build/`は`.gitignore`へ追加した。Skeleton通常Installと`--no-scripts`の双方が上記Application-owned Sourceをbytes単位で保持し、生成物をDistributionへ含めないことを確認した。

## Canonical Frontend Journey

```bash
pnpm install --frozen-lockfile
php blackops build:compile
php blackops frontend:generate
php blackops frontend:check
pnpm test
```

Quickstartの`pnpm test`はDOMなしStrict ES2022 Type CheckとCommonJS Runtime Emitを分ける。Application SourceはGenerated Welcome／Report／Order／Diagnostics Objectを再Exportし、呼出単位の認証Header／Base URL Optionを提供する。READMEとGuideは`.url()`、`.toRequest()`、`.fetch()`、Readonly MetadataのInput／Outputを対で示す。

## Real HTTP Result Matrix

| Operation／Scenario | HTTP | Typed Result | Evidence |
| --- | ---: | --- | --- |
| `ShowWelcome.fetch()` | 200 | `ok: true`, `kind: completed` | Outcome messageとInline metadataを検証 |
| `CreateOrder.fetch()` | 200 | `ok: true`, `kind: completed` | Transactional order reference／statusを検証 |
| `GenerateReport.fetch()` | 202 | `ok: true`, `kind: accepted` | Non-empty Operation IDを検証。Pollingはしない |
| Invalid `GenerateReport.fetch()` | 422 | `ok: false`, `kind: validation` | `reportName` violationを実ResponseからDecode |
| `TriggerFailure.fetch()` | 500 | `ok: false`, `kind: internal` | Stable `internal_error`だけをDecode |
| Injected Fetch Throw | `null` | `ok: false`, `kind: transport` | Stable `network_error`へ変換 |

同じTestで`.url()`のRelative URL、`.toRequest()`のMethod／Absolute URL／Protected Header／JSON Body、frozen Operation ObjectとReadonly Literal Metadataを検証した。既存Curl、Journal、Transaction、Deferred Retry／Outcome、Worker再起動、Diagnostics ViewerのE2Eは維持した。

## Sensitive／Credential／Raw Error Non-disclosure Evidence

Runtimeで一意なReport Sensitive Email、Diagnostics Sensitive Note、Sample Token、Raw Transport Errorを生成した。Consumer E2EはこれらがGenerated Tree、PHP Build Artifact、Typed Result Summary、Application Logへ存在しないことを検査した。JournalではSensitive Inputの実値不在と`[masked]` Projectionを確認し、Diagnostics Exception DetailもFrontend Resultへ出さない。

Quickstart E2EはGuard実行後に`pnpm clean`を実行し、Generated TreeとFrontend Emitを削除する。独立Frontend FixtureとWebsite Generated ContentもCloseoutでCleanupする。

## Skeleton／Publication／Framework Update Evidence

- `skeleton-create-project.sh`: 通常／`--no-scripts`双方が成功し、Frontend Sourceのbytes一致、Node／Generated／Build Artifact不在を確認した。
- `skeleton-publication.sh --dry-run`: Working TreeからFrontend Sourceを含むDistribution Allowlist、Composer Metadata、生成物不在を確認した。
- `skeleton-publication-workflow.sh`: Temporary Commitから同一のSubtree Splitを再現し、Frontend Test Sourceを含むPublication Regressionが成功した。
- `framework-update-generators.sh`: Local `1.0.0 -> 1.1.0` UpdateでApplication-owned Frontend Config／Package／Lock／Source／TestのSHA-256を維持し、更新後Project Root CLIからCanonical Chainへ到達した。

Task指定の`bash tests/Consumer/skeleton-publication.sh 1.1.0 HEAD`は、OrchestratorがTask Commit作成後のHEADで実行し成功した。Repeated Splitの一致、Frontend Sourceを含むDistribution、Composer Metadata、Generated Artifact非混入を確認した。Working-tree Dry-runとTemporary Commit Workflow Regressionも成功している。

## Guide／Website Evidence

GuideへFrontend Installation／Tutorial、Project CLI Exit Contract、`config/frontend.php`、Security責任境界、4系統のTroubleshooting、Install直後／Generate後Directory、Frontend Testing、Stable／main Statusを追加した。Core API／Attribute ReferenceへInternal Generator DTOを混ぜず、既存Stable／main Banner、Landing Title、Information Architectureを維持した。

Websiteは`content:generate`、39 Content／Reader Regression、Astro Check、Static Build、Artifact Boundary、Navigation／Accessibility／Pagefind Checkが成功した。30 PageをLocal BuildしたがDeploy／Cloudflare変更は行っていない。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstartともvalid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: 全成功。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1332 tests, 5101 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2273 / Warnings 0 / Errors 0。

mise exec -- pnpm --dir tests/Frontend install --frozen-lockfile
docker compose run --rm app php tests/Frontend/fixture/blackops build:compile
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:generate
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:check
mise exec -- pnpm --dir tests/Frontend run test
Result: Frozen Install、Canonical Chain、Strict TypeScript、Injected Fetch Runtime、Module Shape成功。

bash tests/Consumer/quickstart-setup.sh
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/skeleton-publication.sh --dry-run
bash tests/Consumer/skeleton-publication-workflow.sh
bash tests/Consumer/framework-update-generators.sh
Result: 全成功。Quickstart実HTTP、通常／no-scripts Skeleton、Working-tree／Temporary Commit Publication、Framework Updateを確認。

bash tests/Consumer/skeleton-publication.sh 1.1.0 HEAD
Result: OrchestratorがTask Commit作成後のHEADで実行し成功。Version 1.1.0、Repeated Splitの一致、Distribution Contractを確認。

mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run content:generate
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
Result: 39/39 Tests、Astro 0 errors／0 warnings／0 hints、30 pages Build、Artifact／Site Check成功。

Management Comment ID Guard、Generated Sensitive Guard、Generated Artifact Tracking Guard、bash -n、git diff --check
Result: 成功。Quickstart Sensitive GuardはConsumer E2E内でGenerated Tree Cleanup前に実行した。
```

## Phase Acceptance Criteria

- [x] Install直後のQuickstart／SkeletonがFrontend Config／Package／Lockfile／Application Source／Testを持つ
- [x] SetupがFrontend Stepを表示するが暗黙実行しない
- [x] Canonical ChainとStrict TypeScriptが成功する
- [x] Generated ObjectからWelcome／Report／Order／Diagnosticsを実HTTP実行できる
- [x] Inline／Deferred／Validation／Internal／Transport Resultを実行時に検証する
- [x] `.fetch()`／`.toRequest()`／`.url()`／Readonly Metadata例が実Contractと一致する
- [x] Sensitive Input値／Credential／Raw Error BodyをArtifact／Result／Observed Logへ出さない
- [x] Skeleton／Publication／Framework UpdateがApplication-owned Fileを保持する
- [x] Backend-only／Worker／Database／Transaction／Diagnostics Journeyが回帰しない
- [x] Guide／Website／Stable-main Statusを同期する
- [x] Generated／Build／Node／Website ContentをCommitしない
- [x] Full PHP／Frontend／Consumer／Website Gateが成功する
- [x] Documentation Websiteを外部公開しない
- [x] Delivery Plan／TODO／STATEをPhase 15 Closedへ同期する
- [x] WorkerはCommitしない

## Remaining Issues

P15-006を妨げる残課題はない。Deferred Status／Outcome HTTP APIとGenerated Pollingは意図どおりPhase 16 Scopeである。

## Suggested Next Action

Task CommitをPushし、Phase 16 Deferred Status and Outcome APIのDecision／Specificationへ進む。

## Orchestrator Review

Install直後Layout、Application-owned／Generated境界、Canonical CLI Chain、Generated Operation Objectの実HTTP Result Matrix、Sensitive／Credential／Raw Error非露出、Skeleton／Framework Update／Website回帰を実装とTestで確認した。Orchestrator側のWebsite 39 TestsとQuickstart Setup回帰も成功した。Ignoredな`examples/quickstart/tests/Frontend/**`を明示Force Stageし、Generated Tree／Node Modules／Frontend Emit／Website Generated ContentがCommitに含まれないことを確認した。Task Commit作成後のExact HEAD Skeleton Publication Gateも成功したため、仕様矛盾とBlockerはなくAcceptedとした。
