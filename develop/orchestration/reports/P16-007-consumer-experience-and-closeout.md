# P16-007 Consumer Experience and Phase Closeout Report

Status: Accepted

## Summary

QuickstartとComposer SkeletonへApplication所有の`SampleOperationStatusAuthorizer`と明示Service Bindingを追加し、Generated `.status()`／`.wait()`をInstall直後のFrontend Source、実HTTP Consumer E2E、Guide、Documentation Websiteへ統合した。

P16-003Aのexecution Actor ContinuityとP16-003BのCanonical Retry時刻精度を前提にReal HTTP Journeyを先頭から再実行し、202受付、Worker未起動中の`accepted`、第一Attempt後の`retry_scheduled`、第二Attempt後の`completed`とTyped `ReportGenerated` Outcomeまで一つのOperation IDで完走した。別Operationの短いDeadlineは有限`poll_timeout`で停止し、その後のWorker処理を妨げない。

Missing Token／Unknown／Denyの同一404、Invalid Tokenの401、Non-terminal限定`Retry-After`、全Status Responseの`private, no-store`、Terminal Header、Sensitive Input／Credential／Actor／Raw Error非露出を実HTTPで固定した。通常／`--no-scripts` Skeleton、Working-tree Publication Dry-run／Workflow、Framework Update、Full PHP／Frontend／Consumer／Website Gateも成功した。

Phase 16のSpecification、Delivery Plan、Roadmap、TODO、STATEをCompleteへ同期した。Documentation WebsiteはLocal／CI相当のBuildまで実行し、外部Publication／Deploy／Cloudflare変更は行っていない。

## Changed Files

### Installed Quickstart

- `examples/quickstart/app/ApplicationServiceProvider.php`
- `examples/quickstart/app/Security/SampleOperationStatusAuthorizer.php`
- `examples/quickstart/tests/.gitignore`
- `examples/quickstart/tests/Frontend/typecheck.ts`
- `examples/quickstart/tests/Frontend/real-http.ts`
- `examples/quickstart/tests/Frontend/wait-signal.ts`
- `examples/quickstart/README.md`

### Consumer／Skeleton／Publication

- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/skeleton-create-project.sh`
- `tests/Consumer/skeleton-publication.sh`
- `tests/Consumer/skeleton-publication-workflow.sh`
- `tests/Consumer/framework-update-generators.sh`
- `tests/Integration/ApplicationHttpRuntimeTest.php`

### Guide／Website／Internal Documentation

- `docs/guide/configuration.md`
- `docs/guide/core-api.md`
- `docs/guide/deployment.md`
- `docs/guide/directory-structure.md`
- `docs/guide/execution.md`
- `docs/guide/first-operation.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/mvp-status.md`
- `docs/guide/outcome-retrieval.md`
- `docs/guide/security.md`
- `docs/guide/testing.md`
- `docs/guide/troubleshooting.md`
- `docs/internal/bootstrap.md`
- `docs/internal/installed-application-status.md`
- `docs/internal/status-query.md`
- `docs/website/content-map.mjs`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`

### Specification／Orchestration

- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/spec/70-phase-16-delivery-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P16-007-consumer-experience-and-closeout.md`

## Decisions and Assumptions

- Existing `ReportGenerated` Public Contractの`reportName`／`location`を正本とし、Outcome Renameを行っていない。
- Missing Sample TokenはAnonymousとしてStatus AuthorizerがDenyし、Unknownと同じ404になる。不正TokenだけがAuthentication Middlewareで401になる。
- QuickstartのSame-origin Status AuthorizerはLocal Exampleである。Production Tenant／Resource／Role Policyを規定しない。
- `.fetch()`は202受付だけで自動Pollingしない。`.status()`は一回、`.wait()`は購読可能Signalと有限Deadlineを必須とする。
- Retention 410は既存PHP／HTTP RegressionとGuideで維持し、Consumer E2EでRetention Dataを破壊して作らない。
- Worker未Commit差分なのでPublication `HEAD` Gateは成功扱いにしない。Working-tree `--dry-run`だけを実行し、実装Commit直後のHEAD GateはOrchestratorが担当する。
- WebsiteはBuild／Artifact／Search確認までとし、Publication／Deployしない。

## Quickstart Status Authorization Matrix

| Current Actor | Origin Actor | Type／Identity | Decision |
| --- | --- | --- | --- |
| Authenticated | Persisted | 両方`user`、ID／Type完全一致 | Allow |
| `null` | Any | Anonymous／Missing Token | Deny |
| Authenticated | `null` | Origin Actorを復元不能 | Deny |
| Authenticated | Persisted | IDまたはType不一致 | Deny |
| Authenticated | Persisted | `user`以外 | Deny |

Application BindingがなければFrameworkの既定Denyを使う。Quickstart BindingはBuild、Application Runtime Test、Skeleton File Guard、Real HTTP 404／200で固定した。

## Frontend `.status()`／`.wait()` Type Matrix

| Capability | Success | Failure／停止 |
| --- | --- | --- |
| `.fetch()` | Inline completed、Deferred accepted | Protocol／Rejected／Validation／Internal／Transport |
| `.status()` | accepted／running／retry_scheduled／completed／rejected／failed／dead_lettered | authentication／unavailable／expired／internal／transport |
| `.wait()` | completed／rejected／failed／dead_letteredだけ | authentication／unavailable／expired／internal／transport／`poll_timeout`／aborted |

Quickstart TypecheckはCompleted Outcomeの`reportName`／`location`を型付きで読み、Statusの7 StateとWaitのTerminal 4 State／FailureをDiscriminated UnionでNarrowingする。Non-terminal StateはWait Resultへ出ない。

## Real HTTP 202-to-Terminal Journey

1. `GenerateReport.fetch()`が一回のPOSTで202、Operation ID、`Location`、`Retry-After`、`private, no-store`を受け取る。
2. 直後の`.status()`が一回のGETで`accepted` 200と正整数Retry Hintを返す。
3. Node `.wait()`開始Signalを安全なOutput FileでShellへ通知する。
4. Shellが第一Worker Attemptを進め、Operations Rowを`retry_scheduled`へ有限Pollする。
5. Public StatusはAttempt 1とマイクロ秒付き`retryAt`を持つ`retry_scheduled` 200を返す。
6. Retry Delay後に第二Worker Attemptを進める。
7. `.wait()`が同じOperation IDの`completed` 200とTyped `ReportGenerated(reportName, location)`を返す。
8. Worker未起動の別Operationは150msの有限Deadlineで`poll_timeout`となり、後続Worker処理は成功する。

固定Sleepだけに依存せず、Node Wait開始MarkerとDatabase Stateを有限回Pollした。Background Process、Temporary Directory、Container、Network、Volume、Generated ArtifactはTrap／Cleanupで回収した。

## Authentication／Unavailable／Expired／Header Matrix

| Situation | HTTP／Generated Result | Header／Detail Boundary |
| --- | --- | --- |
| Deferred受付 | 202 accepted | `Location`、正整数`Retry-After`、`private, no-store` |
| accepted／retry_scheduled | 200 non-terminal | 正整数`Retry-After`、`private, no-store` |
| completed | 200 terminal | `Retry-After`なし、`private, no-store` |
| Missing Token／Deny | 404 unavailable | Unknownと同じBody、Detailなし |
| Unknown Operation ID | 404 unavailable | Denyと同じBody、Detailなし |
| Invalid Token | 401 authentication | Subject読取前、Operation Detailなし |
| Allow済みRetention Expired | 410 expired | 既存RegressionとGuideで維持、認可後だけ公開 |

## Sensitive／Credential／Actor／Raw Error Evidence

- `recipientEmail`はWrite-only Inputとして送信するが、Status／Wait ResultとGenerated Metadataへ含めない。
- Sample TokenはOperationValue、Context、Journal、Generated Artifact、Typed Resultへ含めない。
- Origin／Authorization／Execution Actor ID、Worker ID、Attempt ID、Correlation／Causation IDをPublic Statusへ返さない。
- Internal 500、Network Error、Malformed Responseは安定Codeだけを返し、Raw Body、Exception Message、Stack Traceを保持しない。
- Completed StatusはPublic Outcome Property `reportName`／`location`だけを返す。
- Generated TreeとWebsite distをSensitive Markerで検査し、禁止値なしを確認後にCleanupした。

## Skeleton／Publication／Framework Update Evidence

- 通常`composer create-project`と`--no-scripts`の両方がStatus Authorizer、Provider Binding、Frontend Typecheck／Real HTTP／Signal Helperをbytes単位で保持した。
- `--no-scripts`は`php bin/setup`を明示実行し、通常Installと同じ準備状態になった。
- Working-tree Publication Dry-runがVersion `1.1.0`、Source `2bfd63cb89bed4779c60784b1853727001ce9e67`、`split=working-tree`で成功した。
- Publication Workflow RegressionがSplit Commit `dc863b6999c3a4389edf8643ce0d36c091306fc1`で成功した。
- Framework UpdateはApplication所有Authorizer、Provider、README、Frontend Source／Testを保持し、更新後のProject Root `blackops`からCurrent Build／Generate／Checkへ到達した。

## Guide／Website／Current Status Evidence

- QuickstartとTutorialを202→Status→Worker→finite Wait→Typed OutcomeのInput／Output対へ更新した。
- Execution、Outcome、Security、Troubleshooting、Configuration、Directory、Testing、Deployment、Core APIへStatus／Wait／Authorization／Retention責務を配置した。
- Current StatusはStable 1.1.0をNot available、`main`のStatus Resource／Generated `.status()`／`.wait()`をAvailable（Experimental）として区別した。
- Website IAとStable／main Bannerを維持し、Content Mapの説明とReader Contract Testを更新した。
- Websiteは39 Source Test、Astro 0 diagnostics、30 Static Page Build、29 Page Navigation／Accessibility／Search Checkを完走した。
- Website Publication／Deploy／Cloudflare変更は行っていない。

## Commands and Results

```text
bash tests/Consumer/quickstart-e2e.sh
Result: Quickstart consumer E2E passed。

bash tests/Consumer/quickstart-setup.sh
Result: Quickstart setup tests passed。

mise exec -- pnpm --dir tests/Frontend install --frozen-lockfile
docker compose run --rm app php tests/Frontend/fixture/blackops build:compile
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:generate
docker compose run --rm app php tests/Frontend/fixture/blackops frontend:check
mise exec -- pnpm --dir tests/Frontend run test
mise exec -- pnpm --dir tests/Frontend run clean
Result: Build／Generate／Fresh、Typecheck、Injected Fetch、Status／Finite Wait、Module Shape、Cleanup全成功。

bash tests/Consumer/skeleton-create-project.sh
Result: 通常／--no-scripts Skeleton create-project smoke passed。

bash tests/Consumer/skeleton-publication.sh --dry-run
Result: Working-tree publication dry run passed。

bash tests/Consumer/skeleton-publication-workflow.sh
Result: Publication workflow regression passed。

bash tests/Consumer/framework-update-generators.sh
Result: Framework update generator smoke passed。

mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run content:generate
mise exec -- pnpm --dir docs/website run content:check
mise exec -- pnpm --dir docs/website run test
Result: Content deterministic、39 tests passed。初回は旧Tutorial／Status期待3件を検出し、Public Status主経路へ同期後に全成功。

mise exec -- pnpm --dir docs/website run check
Result: Mermaid／Accessibility成功、Astro 0 errors / 0 warnings / 0 hints。

mise exec -- pnpm --dir docs/website run build
Result: 30 pages built、Artifact Boundary成功、29 pages Navigation／Accessibility／Pagefind Search成功。

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstart Composer valid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: 全成功。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1430 tests, 5679 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2530 / Warnings 0 / Errors 0。

Management ID／Sensitive／Tracking／Artifact／git diff --check Guards
Result: 全成功。Generated／Build／Node Modules／Website Content／distを追跡せずCleanup済み。
```

```text
bash tests/Consumer/skeleton-publication.sh 1.1.0 HEAD
Result: 実装Commit直後のPublication HEAD Gate成功。Version 1.1.0、Committed SourceからSkeleton Splitを構成し、配布対象File／除外Artifact／Install Contractを確認した。
```

## Phase 16 Acceptance Criteria

- [x] QuickstartがApplication所有Status Authorizerと明示Bindingを持つ
- [x] Same-origin Actor一致だけAllowし、欠落／不一致をDenyする
- [x] Install直後のSkeleton通常／`--no-scripts`へAuthorizerとFrontend Journeyが含まれる
- [x] Generated `.status()`が実HTTP acceptedを一回取得する
- [x] Generated `.wait()`がWorker Retry後のCompleted Typed Outcomeを返す
- [x] Worker未起動が有限Timeoutとなり、`.fetch()`は自動Pollingしない
- [x] 401／404／Safe Header／No-store／Retry-Afterを実HTTPで確認する
- [x] Sensitive Input／Credential／Actor／Raw Error／Canonical DetailがPublic Result／Artifact／Logへ露出しない
- [x] Skeleton Publication Dry-run／WorkflowとFramework UpdateがApplication-owned Fileを保持する
- [x] Guide／Website SourceがStatus／Wait／Authorization／Retention／Troubleshootingを同期する
- [x] Stable 1.1.0と`main` Experimental Surfaceを正しく区別する
- [x] Full PHP／Frontend／Consumer／Skeleton／Publication／Website Gateが成功する
- [x] Generated／Build／Node Modules／Website Content／Dist ArtifactをCommitしない
- [x] Documentation Websiteを外部公開しない
- [x] Phase 16 Delivery Plan／Roadmap／TODO／STATEをClosedへ同期する
- [x] WorkerはCommitしていない

## Remaining Issues

P16-007とPhase 16の実装範囲にRemaining Issueはない。

P16-007とPhase 16の実装範囲にRemaining Issueはない。Documentation Website PublicationはUser判断どおり別の明示TaskまでDeferredである。

## Suggested Next Action

Phase 16 CloseoutをPushし、Phase 17 Reliability and DeliveryのDecision Planningへ進む。

## Orchestrator Review

QuickstartのApplication所有Status Authorizer、Generated `.status()`／`.wait()` Consumer、Skeleton／Publication／Framework Update Guard、Guide／Website／Internal Documentation、Phase 16 Closeoutの差分がTask Packet範囲内であることを確認した。Framework既定Denyは専用Resolver／Authorization Testで維持し、Quickstartの明示BindingだけをApplication Runtime 200契約へ同期している。

Orchestrator再実行でComposer Root／Quickstart、Mago format／lint／analyze、Full 1430 tests／5679 assertions、Deptrac 0違反／0警告／0エラーが成功した。WebsiteもContent Generate／Check、39 tests、Astro 0 diagnostics、30 Page Build、29 Page Navigation／Accessibility／Search Checkが成功した。Real HTTP Quickstart E2Eは202→accepted→retry_scheduled→completed Typed Outcome、finite `poll_timeout`、401／404、Header、Sensitive境界を再度完走し、専用Container／VolumeをCleanupした。

Website Generated Content／dist／node_modules／Astro CacheをCleanupし、Artifact／Sensitive／Tracking／Management ID／`git diff --check` Guardを再確認した。実装Commit直後のPublication HEAD Gateも成功したためAcceptedとした。
