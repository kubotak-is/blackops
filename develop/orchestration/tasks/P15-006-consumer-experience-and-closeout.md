# P15-006: Consumer Experience and Phase Closeout

Status: Ready

## Goal

P15-005までに確立したFrontend Contract、Operation Object、Typed Fetch Runtime、Drift Checkを、Install直後のQuickstart／Composer Skeletonと利用者向けGuideへ統合する。

QuickstartのGenerated Operation Objectから実HTTP RuntimeへWelcome Inline、Report Deferred、Order Transaction、Diagnostics Failureを実行し、Inline／Deferred／Validation／Internal／TransportのResultが実Responseと一致するConsumer E2Eを常設する。Skeleton通常／`--no-scripts`、Publication Dry-run／Workflow、Framework Update、Backend-only Journeyを回帰させず、Full PHP／Frontend／Consumer／Website Gateを完走してPhase 15をCloseする。

Documentation WebsiteはLocal／CIでTest／Check／Buildするが、外部公開、Deploy、Cloudflareへの変更は行わない。

## In Scope

- Quickstart／Composer Skeletonの`config/frontend.php`、Frontend Toolchain、TypeScript Config、Application-owned Example／Test Source
- Install直後のDirectory LayoutとSetup／READMEのFrontend Journey同期
- Canonical `build:compile -> frontend:generate -> frontend:check -> TypeScript compile/test`
- Generated Tree／Node Modules／Frontend Build EmitのIgnore、Tracking Guard、Cleanup
- Generated Welcome／Report／Order／Diagnostics Operation Objectから実HTTPへ接続するConsumer E2E
- `.fetch()`、`.toRequest()`、`.url()`、Readonly Metadata、Typed ResultのInput／Output対
- Inline Success、Deferred Accepted、Validation Rejection、Internal Failure、Transport Failureの実行検証
- Sensitive Input／Credential／Raw Error Body非露出のGenerated Artifact／Result／Log Guard
- Skeleton通常／`--no-scripts`、Publication Dry-run／Workflow、Framework UpdateのFrontend同期
- GuideのFrontend Journey、Project CLI、Configuration、Security、Troubleshooting、Directory Structure、Current Status同期
- Documentation WebsiteのContent／Code Regression、Test／Check／Build
- Internal Documentation、TODO、Report、STATEのPhase 15 Closeout同期

## Out of Scope

- Documentation WebsiteのPublication／Deploy／Cloudflare設定
- Deferred Status／Outcome HTTP API、Generated Polling
- Retry／Backoff／Cache／Offline Queue／Global Mutable Client
- React／Vue／Svelte／Inertia Adapter、Form Helper、Vite Plugin、NPM Package Publication
- Typed Collection、Nested DTO、Enum、Date／Time、Upload／Stream
- Public PHP API、Attribute、Migration、Database Schemaの追加／変更
- Stable `1.1.0`の再公開、Framework／Skeleton Release、Tag作成
- ApplicationのProduction認証方式、CSRF Token取得、Secret保存

## Relevant Specifications and Decisions

- `develop/spec/05-http.md`
- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/67-operation-frontend-bridge.md`
- `develop/spec/68-phase-15-delivery-plan.md`
- `develop/decisions/090-documentation-information-architecture.md`
- `develop/decisions/092-project-cli-command-names.md`
- `develop/decisions/093-post-phase-10-roadmap.md`
- `develop/decisions/094-stable-1-1-release-contract.md`
- `develop/decisions/100-phase-15-operation-frontend-bridge.md`
- `develop/orchestration/reports/P15-005-drift-and-frontend-build-integration.md`

## Files Allowed to Change

### Installed Quickstart and Skeleton Source

- `examples/quickstart/.gitignore`
- `examples/quickstart/README.md`
- `examples/quickstart/bin/setup`
- New `examples/quickstart/config/frontend.php`
- New `examples/quickstart/package.json`
- New `examples/quickstart/pnpm-lock.yaml`
- New `examples/quickstart/tsconfig*.json`
- New `examples/quickstart/resources/js/application/**`
- New `examples/quickstart/tests/Frontend/**`
- P15-006のFrontend Journeyに必要な既存`examples/quickstart/app/Feature/**`のMetadata／Validation調整だけ。仕様矛盾がある場合は実装を広げずBlockerとする

### Consumer, Publication, CI, and Regression

- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/quickstart-setup.sh`
- `tests/Consumer/skeleton-create-project.sh`
- `tests/Consumer/skeleton-publication.sh`
- `tests/Consumer/skeleton-publication-workflow.sh`
- `tests/Consumer/framework-update-generators.sh`
- New `tests/Consumer/fixtures/frontend-*`
- `.github/workflows/ci.yml`
- `.github/workflows/publish-skeleton.yml`はLocal Dry-run／Regressionを同期する必要がある場合だけ
- `.gitignore`はRootのTemporary Frontend Artifact Guardに必要な場合だけ

### Guide and Website Regression

- `docs/guide/README.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/installation.md`
- `docs/guide/project-cli.md`
- `docs/guide/configuration.md`
- `docs/guide/security.md`
- `docs/guide/troubleshooting.md`
- `docs/guide/directory-structure.md`
- `docs/guide/testing.md`
- `docs/guide/mvp-status.md`
- New `docs/guide/frontend-operations.md`、または既存Guideへの合理的な統合
- `docs/website/src/content/config.ts`
- `docs/website/astro.config.mjs`
- `docs/website/scripts/*.mjs`
- `docs/website/tests/*.test.mjs`
- Generated `docs/website/src/content/docs/**`は固定せず、既存Content Generation Contractに従う

### Internal Documentation and Orchestration

- `docs/internal/bootstrap.md`
- `docs/internal/installed-application-status.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/spec/68-phase-15-delivery-plan.md`のPhase Acceptance Check同期
- New `develop/orchestration/reports/P15-006-consumer-experience-and-closeout.md`

変更可能Fileの追加が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Installed Frontend Layout Contract

`examples/quickstart/`Client SourceはPackagist `blackops/skeleton`のSource of Truthであり、`composer create-project` 直後のDirectory Layoutを表す。少なくとも次をApplication-owned Sourceとして含める。

```text
config/frontend.php
package.json
pnpm-lock.yaml
tsconfig*.json
resources/js/application/
tests/Frontend/
```

Generated `resources/js/blackops/`、`node_modules/`、Frontend Build EmitはInstall Artifactへ固定せずIgnore対象とする。`config/frontend.php`はApplication Root配下のCanonical `resources/js/blackops` を明示する。SetupはEnvironmentとWritable Runtime Directoryの準備だけを維持し、Composer Install、pnpm Install、Build、Generate、HTTP、Workerを暗黙実行しない。次手順にFrontend Install／Generate／Check／TypeScript Testを表示する。

Frontend Package Scriptは明示的な小さいStepとし、Backend-only利用者へNodeを必須化しない。Canonical CLI名はPrefixなし`php blackops build:compile`、`php blackops frontend:generate`、`php blackops frontend:check`だけを使う。

## Quickstart Frontend Journey

READMEとGuideは、次のInputとOutputを対で示す。

1. Skeleton Install、PHP／Frontend Dependency Install
2. `php blackops build:compile`
3. `php blackops frontend:generate`
4. `php blackops frontend:check`
5. DOMなしStrict TypeScript Compile／Test
6. Generated Operation ObjectのImport
7. `.url()`とInput -> URL Output
8. `.toRequest()`とInput -> Method／URL／Headers／Body Output
9. `.fetch()`とInput -> Inline／Deferred／Failure Result Output
10. Readonly `type`／`method`／`path`／`strategy`参照

CodeはCallable／Thenableではなく`ShowWelcome.fetch(...)`、`GenerateReport.fetch(...)`、`CreateOrder.fetch(...)`、`TriggerFailure.fetch(...)`を使う。Generated TypeScriptを利用者に手書きさせず、生成CommandとApplication-owned Consumer Sourceを使う。

## Real HTTP Consumer E2E Contract

`tests/Consumer/quickstart-e2e.sh`または追加専用Fixtureは、QuickstartをTemporary ConsumerへCopyした後に次を実行する。

- PHP Dependency Install、Database Migration、`build:compile`、`frontend:generate`、`frontend:check`
- Quickstart所有のFrozen Frontend Lockfile InstallとStrict TypeScript Compile
- Worker Mode HTTPへGenerated Operation ObjectのInjected Fetchを使って実Requestを送信
- Welcome Inline 200が`completed`、Report Deferred 202が`accepted`、Order Transaction 200が`completed`、Diagnostics 500が`internal`にDecodeされる
- 少なくともWelcomeまたはReportのInvalid Inputで422 `validation`を実ResponseからDecodeする
- 到達不能なBase URLまたはInjected Fetch Throwを`transport`／`network_error`にDecodeする
- `.url()`、`.toRequest()`、Readonly MetadataがCompiled Contractと一致する
- ReportのSensitive Input、Sample Token Credential、Diagnostics Raw Error DetailがGenerated Tree、Typed Result、Application／Observed Journal Logへ漏れない
- Existing Curl／Journal／Transaction／Worker／Diagnostics Viewer E2Eを弱めない

Deferred 202後のStatus／Outcome Pollingは行わない。Workerの既存Journeyは回帰として維持するが、Generated ClientにPhase 16 Capabilityを追加しない。

## Skeleton and Framework Update Contract

- 通常`composer create-project`と`--no-scripts`の両方がFrontend Config／Package／Lockfile／Application Source／Test Sourceをbytes単位で持つ
- `--no-scripts`は引き続き`php bin/setup`を利用者が明示実行する
- Skeleton Publication Dry-runとWorkflow RegressionがFrontend所有FileをSplit Commitへ含める
- Framework UpdateはApplication-owned `config/frontend.php`、Frontend Package／Source／Testを書き換えない
- Update後のProject Root `blackops`からCurrent `build:compile -> frontend:generate -> frontend:check`へ到達する
- Generated Tree、Build Artifact、Node Modules、Frontend EmitをSkeleton PublicationとFramework Packageへ混入させない

## Guide and Website Contract

- 新設または既存PageはDiátaxisのHow-to／Tutorialとして読者の手順を主体に書く
- Core APIとAttribute ReferenceにInternal Generator DTOを混ぜない
- `frontend:generate`／`frontend:check`／`config/frontend.php`とExit ContractをProject CLI／Configuration Referenceへ追加する
- SecurityはGenerated TypeがAuthorizationを代替しないこと、Sensitive Inputの名前／型はWrite-only Contractに含まれるが実値をArtifact／Result／Logに含めない責任境界を明示する
- TroubleshootingはInvalid／Stale Frontend Artifact、Missing／Drift、TypeScript Compile、Missing Fetch／Network／Unexpected Responseを症状／原因／確認／修正で説明する
- Directory StructureはInstall直後のFrontend Config／Application Source／Testと、Generate後のIgnore Treeを分ける
- Current StatusはStable `1.1.0`にFrontend Bridgeを追加せず、`main` Available／Experimental、Deferred Status／Outcome ClientはNot Availableと正直に示す
- Stable／main Banner、`BlackOps — The PHP Framework`、Information Architecture、Current Statusの正直さを維持する
- Website Generated ContentはCommitせず、Local／CIの`content:generate`／`content:check`／Test／Astro Check／Buildで検証する

## Acceptance Criteria

- [ ] Install直後のQuickstart／SkeletonがFrontend Config／Package／Lockfile／Application Source／Testを持つ
- [ ] SetupがFrontend Stepを表示するがDependency／Build／Generateを暗黙実行しない
- [ ] Canonical CLI ChainからGenerated Treeを作り、`frontend:check`とStrict TypeScriptが成功する
- [ ] Generated Operation ObjectからQuickstartの実HTTPへWelcome／Report／Order／Diagnosticsを実行できる
- [ ] Inline／Deferred／Validation／Internal／Transport Resultを実行時に検証する
- [ ] `.fetch()`／`.toRequest()`／`.url()`／Readonly MetadataのInput／Output例が実Contractと一致する
- [ ] Sensitive Input値／Credential／Raw Error BodyがGenerated Artifact／Result／Observed Logへ露出しない
- [ ] 通常／`--no-scripts` Skeleton、Publication Dry-run／Workflow、Framework UpdateがFrontend Application-owned Fileを保持する
- [ ] Backend-only Journey、Worker Mode、Database／Transaction／Diagnostics E2Eが回帰しない
- [ ] GuideとWebsiteがFrontend Journey／CLI／Config／Security／Troubleshooting／Directory／Statusを同期する
- [ ] Stable `1.1.0`と`main`、Experimental Compatibility、Deferred Status／Outcome不在を正しく区別する
- [ ] Generated Tree／Build Artifact／Node Modules／Frontend Emit／Website Generated ContentをCommitしない
- [ ] Full PHP／Frontend／Consumer／Skeleton／Publication／Website Gateが成功する
- [ ] Documentation Websiteを外部公開しない
- [ ] Phase 15 Delivery Plan／TODO／STATEをClosedへ同期する
- [ ] WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
mise exec -- pnpm --dir tests/Frontend install --frozen-lockfile
docker compose run --rm app php tests/Frontend/fixture/blackops build:compile
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:generate
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:check
mise exec -- pnpm --dir tests/Frontend run test
bash tests/Consumer/quickstart-setup.sh
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/skeleton-publication.sh 1.1.0 HEAD
bash tests/Consumer/skeleton-publication-workflow.sh
bash tests/Consumer/framework-update-generators.sh
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run content:generate
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
! rg -n 'credential-secret|sensitive-value|default-must-not-appear|raw-body' \
  examples/quickstart/resources/js/blackops
! git ls-files \
  examples/quickstart/resources/js/blackops \
  examples/quickstart/node_modules \
  examples/quickstart/.build \
  tests/Frontend/fixture/var/build \
  tests/Frontend/fixture/resources/js/blackops \
  tests/Frontend/.build \
  docs/website/src/content/docs | rg .
git diff --check
```

Generated Content GuardはCleanup前に実行する。Quickstart E2EはOwn CleanupでTemporary Consumer、Docker Resource、Frontend Emitを必ず片付け、Repositoryの`examples/quickstart` Sourceを変更しない。

## Expected Report

`develop/orchestration/reports/P15-006-consumer-experience-and-closeout.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Installed Frontend Layout
- Canonical Frontend Journey
- Real HTTP Result Matrix
- Sensitive／Credential／Raw Error Non-disclosure Evidence
- Skeleton／Publication／Framework Update Evidence
- Guide／Website Evidence
- Commands and Results
- Phase Acceptance Criteria
- Remaining Issues
- Suggested Next Action
