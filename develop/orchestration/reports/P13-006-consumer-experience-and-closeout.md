# P13-006: Consumer Experience and Closeout Report

Status: Ready for Review

## Summary

QuickstartへOrder Journeyを追加し、Default DBAL Connectionを使うRepository、Transactional Command、Transactional Operation、After Commit Service、Application Migrationを一続きにした。認証付き`POST /orders`はTyped Outcomeを返し、業務RowをOperation Transaction内で、Commit記録Rowを最外Commit後に保存する。

Quickstart、Skeleton、Framework Update Smoke、Guide、Documentation Websiteを同じPublic Contractへ同期した。P13-006AのProxy-aware HTTP Operation解決を含むFull PHP Gate、相互干渉を避けて逐次実行したConsumer 7本、Website Test／Check／Build／Artifact Guardはすべて成功した。

Phase 13 Delivery PlanのAcceptance Criteriaを実行証拠に基づき全件完了へ更新した。Documentation WebsiteはUser判断どおり公開していない。

## Consumer Journey Evidence

- `operation:list`とBuild Artifactに`order.create`が含まれ、Transactional Operationの解決済みConnectionは`app`となった。
- Application Migration前はOrder Tableが存在せず、`database:migrate`後に`quickstart_orders`と`quickstart_order_commits`が作成された。
- 認証付き`POST /orders`は`{"reference":"consumer-order-001","status":"created"}`を返した。
- Order Rowは最外Operation Transaction内で保存され、After Commit RowはOperationの成功Commit後に保存された。
- 同じOperation IDのCanonical JournalはReceivedからCompletedまで到達した。
- Response、Journal JSONL、Database ArtifactへSample Credentialを保存していない。
- Framework Update SmokeはOrder Feature、Migration、Application Service Providerをbyte-for-byte維持した。
- Skeleton通常／`--no-scripts`はOrder Featureと`migrations/`を含むInstall直後構成を再現した。
- FrankenPHP Worker ModeはDatabase切断／復旧、Multi-request、Restart、Memory Bound、Classic Fallbackを維持した。

## Changed Files

- `examples/quickstart/app/Feature/Order/**`
- `examples/quickstart/migrations/Version20260718000000.php`
- `examples/quickstart/app/ApplicationServiceProvider.php`
- `examples/quickstart/README.md`
- `README.md`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/MvpSampleEndToEndTest.php`
- `tests/Consumer/quickstart-setup.sh`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/framework-update-generators.sh`
- `tests/Consumer/skeleton-create-project.sh`
- `tests/Consumer/skeleton-publication.sh`
- `docs/guide/application-bootstrap.md`
- `docs/guide/configuration.md`
- `docs/guide/database-and-transactions.md`
- `docs/guide/database-migrations.md`
- `docs/guide/directory-structure.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/mvp-status.md`
- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `develop/spec/64-phase-13-delivery-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P13-006-consumer-experience-and-closeout.md`

Website Generated Sourceと`dist/`はCommit対象へ追加していない。

## Decisions and Assumptions

- Order Operationは`handle()`の`#[Transactional]`でFramework固定Lifecycleを選ぶ。
- `CreateOrderCommand::execute()`も同じDefault Connectionの`#[Transactional]`とし、Operation TransactionへNested Requiredで参加する。
- `RecordOrderCommit::record()`は`#[AfterCommit]`で最外Commit後に監査用Rowを追加する。自動RetryやReliable Deliveryを装わない。
- RepositoryはDefault `Doctrine\DBAL\Connection`をConstructor Injectionし、固定Table IdentifierとParameterized SQLだけを使う。
- Operation自体はDiscovery／自動登録へ任せ、Service ProviderはRepository Interface、Command、After Commit Serviceだけを登録する。
- Quickstart Sourceを直接列挙するIntegration FixtureはRepository Interfaceを実装Classより先に読み、Application Service ProviderとDatabase／Transaction Runtime Serviceを実Application bootと同じ境界で構成する。
- Transactional Operationは業務更新と成功Terminal Journal／Outcomeを同じConnection Commitへ含める。Transactional Commandだけを呼ぶNon-transactional Operationでは、Command Commit後のTerminal保存まで原子的にはならない。
- `#[AfterCommit]`は同期Best-effortであり、Callback失敗やProcess Crashを越えるDeliveryにはTransactional Outboxが必要である。OutboxはPhase 13で提供しない。
- `database-and-transactions.md`は`/database/transactions/`としてData & Retentionの先頭へ配置し、Static RouteとPagefind Guardへ含める。
- Stable `1.1.0`とRepository `main` Previewの差をGuide／Current Statusへ維持する。
- Cloudflare Publicationは実行しない。

## Commands and Results

```text
docker compose run --rm app mago format examples tests
Result: 成功。MigrationとIntegration Fixtureを含む全対象Fileを整形した。

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstartともにvalid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: All files are already formatted。Lint／AnalyzeはNo issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1096 tests, 3806 assertions)。P13-006A、3 Operation Artifact、Application Console／HTTP／MVP Fixtureを含む。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2002 / Warnings 0 / Errors 0。

bash tests/Consumer/quickstart-setup.sh
Result: Quickstart setup tests passed。

bash tests/Consumer/quickstart-e2e.sh
Result: Quickstart consumer E2E passed。Outcome、Order Row、After Commit Row、Terminal Journal、Credential非露出を検証。

bash tests/Consumer/framework-update-generators.sh
Result: Framework update generator smoke passed。Order Source／Migration／ProviderをHash一致で保持。

bash tests/Consumer/frankenphp-worker-mode.sh
Result: FrankenPHP worker mode consumer E2E passed。切断／再接続、Multi-request、Restart、Memory Bound、Classic Fallbackを検証。

bash tests/Consumer/skeleton-create-project.sh
Result: Skeleton create-project smoke passed。通常／`--no-scripts`が成功。

bash tests/Consumer/skeleton-publication.sh --dry-run
Result: Skeleton publication dry run passed。

bash tests/Consumer/skeleton-publication-workflow.sh
Result: Skeleton publication workflow regression passed。

mise exec -- pnpm --dir docs/website install --frozen-lockfile
Result: LockfileとLocal Cacheからpnpm 11.12.0で成功。Registry metadata fetch warningは出たが、依存はAlready up to dateでexit 0。

mise exec -- pnpm --dir docs/website run test
Result: 37 tests / 37 passed。

mise exec -- pnpm --dir docs/website run check
Result: Content／Mermaid check成功。Astro 16 files、0 errors、0 warnings、0 hints。

mise exec -- pnpm --dir docs/website run build
Result: 30 HTML pagesをBuild。29 Japanese pagesをPagefindへ登録し、Artifact Boundary、Navigation、Accessibility、Search Check成功。

! git ls-files docs/website/src/content/docs docs/website/.generated docs/website/dist | grep -q .
! rg -n 'docs/internal|develop/' docs/website/dist docs/website/.generated
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
Result: すべて成功。
```

## Orchestrator Review Corrections

Root READMEのStable `1.1.0` Installation直後に、未Releaseの認証付きPhase 13 Journeyを実行できるように読める境界を修正した。StableにはHeader Authenticationと`POST /orders`がないことを明記し、Order JourneyはRepository `main` Preview専用として、利用者向けQuickstartのLocal Path Repository準備手順へ導いた。

`examples/quickstart/README.md`もWorking TreeのQuickstart Sourceへ直接`composer install`するとPackagist Stable Frameworkが解決されることを明記し、`main` PreviewではFramework SourceをLocal Path Repositoryとして組み合わせるよう統一した。

修正後にWebsite Reader Test 37件、Content／Mermaid／Astro Check、30 HTML／29 Japanese Pagefind pagesのBuild、Artifact／Navigation／Accessibility／Search Checkを再実行し、すべて成功した。機能Codeは変更していない。

## Acceptance Criteria

- [x] QuickstartのOrder JourneyがRepository、Command、Operation、After Commitを一続きで実行する
- [x] Default DBAL Connection DIとService Provider BindingがInstall直後のSourceで理解できる
- [x] Transactional OperationとCommand-only Transactionの保証差をGuideで説明する
- [x] After CommitのBest-effort／非RetryとOutbox責任境界を明示する
- [x] Quickstart E2EがOutcome、Business Row、After Commit Row、Terminal Journalを検証する
- [x] Framework Update Smokeが新Exampleを保持する
- [x] Skeleton通常／`--no-scripts`とPublication Dry-runが新Directory構成を再現する
- [x] Canonical／Legacy Database Config、Credential非露出、AOP Build Artifact、Nested／Rollback-only／Manual／Named保証の既存Testが回帰しない
- [x] Full PHP、全Relevant Consumer、Documentation Website Quality Gateが成功する
- [x] `develop/spec/64-phase-13-delivery-plan.md`のPhase Acceptance Criteriaを実行証拠に基づき完了へ更新する
- [x] TODO、Report、STATEを同期し、Phase 13をClosedにする
- [x] Documentation Websiteを公開しない
- [x] WorkerはCommitせずOrchestrator Reviewへ返す

## Phase 13 Closeout

Named DBAL Connection、Default Connection DI、Build-time Ray.Aop Proxy、Transactional Service／Operation、Nested Required、Rollback-only、Manual Transaction Guard、After Commit、Operation Terminal Commit、Long-running Connection Safety、Installed Consumer Experienceの全Taskが実装済みである。

`develop/spec/64-phase-13-delivery-plan.md`のAcceptance Criteriaは全件完了した。Full PHP、Consumer 7本、Website Test／Check／Build／Artifact Guardが同じWorking Treeで成功しているため、Phase 13をClosedとする。

## Remaining Issues

- `#[AfterCommit]`は同期Best-effortであり、自動Retry、Persistence、Relay、Replayを提供しない。Reliable Deliveryは将来のTransactional Outbox Taskで扱う。
- 複数Named Connection間は原子的ではなく、二相Commitを提供しない。
- Stable `1.1.0`にはPhase 13 Surfaceが未収録であり、次回ReleaseまではRepository `main` DocumentとのChannel差がある。
- Documentation WebsiteはUser判断どおり未公開である。

## Suggested Next Action

OrchestratorがP13-006差分とCloseout EvidenceをReviewし、Accepted後にTask単位でCommitする。その後Phase 14 Operation Diagnosticsの設計監査へ進む。

## Orchestrator Review

Accepted。Order ExampleのRepository／Transactional Command／Transactional Operation／After Commit境界、Application Migration、Service Provider Binding、Stable `1.1.0`とRepository `main` Previewの表示、Consumer／Website Closeout Evidenceを確認した。

独立検証ではComposer root／Quickstart、Mago format／lint／analyze、Deptrac、Full PHPUnit 1096 tests／3806 assertions、Quickstart Consumer E2E、Website 37 tests、Astro check、30 HTML／29 Japanese Pagefind pagesのbuild、Artifact／Internal Path／Management ID／diff guardが成功した。Reviewで検出したREADMEのStable／Preview境界は修正後にWebsite Gateを再実行している。既知Blockerはなく、Phase 13をClosedとしてP13-006をTask単位でCommitできる。
