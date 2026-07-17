# P12-006 Consumer Experience and Closeout Report

Status: Accepted

## Summary

Installed QuickstartへLocal Sample Token Authentication、Application所有Service Provider、Global Authentication Middleware、Welcome／Report共通Authorization Policyを統合した。CredentialをOperation Valueから除外し、Deferred Reportの業務上のSensitive値を`recipientEmail`へ置き換えた。

Consumer、Guide、Websiteを同じPublic Contractへ同期し、Full PHP／Consumer／Website Quality Gateを完走した。Phase 12 Delivery PlanのAcceptance Criteriaはすべて満たした。

Orchestrator Review後、Sample Token設定をFail-closedへ変更し、Stable InstallとRepository `main` Previewを実行可能な別Journeyへ分離した。また、HTTP Observed JSONLをCross-process Journalとして扱っていた説明を修正し、Deferred Worker EventはCanonical PostgreSQL Journalで確認する手順へ置き換えた。

## Consumer Journey Evidence

- Valid `X-Sample-Token: local-example`でWelcomeがHTTP 200、ReportがHTTP 202を返した。
- Header欠落はAnonymousとしてOperationへ到達し、Operation ID付きHTTP 401と`operation.received`／`attempt.started`／`operation.rejected`を記録した。
- Header不一致は`authentication.invalid_sample_token`のOperation IDなしHTTP 401となり、Canonical Journal件数を増やさなかった。
- Report Validation 422、Retry Scheduled、二回目Attempt Completion、Typed Outcome保存を維持した。
- FrankenPHP Worker Modeは複数Request、Database切断後Reconnect、Process再起動、Memory Bound、Classic Fallbackを認証付きRequestで完走した。
- 通常／`--no-scripts` Create-projectはAuthenticator、Policy、Provider、Middleware Config、Environment Exampleを含むSkeletonを再現した。
- Framework `1.0.0`から`1.1.0`へのUpdate SmokeはApplication所有の認証Surfaceと既存Operation／MigrationをHash一致で保持し、Framework所有Command／Stubだけを更新した。
- Repository `main` PreviewはQuickstart SourceをCopyし、Frameworkを`symlink: false`のLocal Path RepositoryとVersion MappingでMirror InstallするConsumer実証済み手順とした。

## Credential and Actor Boundary Evidence

- `SampleTokenAuthenticator`はExpected TokenをConstructorで一度だけSnapshotし、RequestごとのEnvironment参照を行わない。未設定、空文字、空白だけの値は`RuntimeException`でFail-closedとし、既知TokenへFallbackしない。
- Token比較は`hash_equals()`を使い、Authenticated Resultへ`quickstart-user`／`user`の`ActorRef`だけを渡す。
- `WelcomeValue`はCredential Propertyを持たず、`GenerateReportValue`は業務入力`reportName`／`recipientEmail`だけを持つ。
- Consumer E2EはCredentialがExecution Transport Payload／Context、Canonical Journal、Outcome、Observed JSONLのいずれにも存在しないことを確認した。
- Deferred受付Contextではorigin／authorization／executionが`quickstart-user`で一致し、Worker Journalではauthorizationを維持したままexecutionだけが`quickstart-worker-1`／`system`へ変わった。
- Workerは各Attemptで同じApplication Policyを再評価した。
- Canonical JournalはWorker Event、Raw Business Value、Actor IDを監査用に保持する。Observed JSONLはHTTP ProcessでProjectionしたInline／Validation Eventだけを保持し、User Actor IDとValidation入力の`recipientEmail`を`[masked]`へ変換する。Valid Deferred Operation IDがWorker完了後もObserved JSONLに存在しないことをConsumer E2Eで固定した。

## Changed Files

- `examples/quickstart/.env.example`
- `examples/quickstart/README.md`
- `examples/quickstart/app/ApplicationServiceProvider.php`
- `examples/quickstart/app/Security/SampleUserAuthorizationPolicy.php`
- `examples/quickstart/app/UserInterface/Http/SampleTokenAuthenticator.php`
- `examples/quickstart/app/Feature/Welcome/ShowWelcome/*`
- `examples/quickstart/app/Feature/Report/GenerateReport/*`
- `examples/quickstart/config/app.php`
- `examples/quickstart/config/middleware.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Integration/MvpSampleEndToEndTest.php`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/frankenphp-worker-mode.sh`
- `tests/Consumer/skeleton-create-project.sh`
- `tests/Consumer/framework-update-generators.sh`
- `docs/guide/application-bootstrap.md`
- `docs/guide/attributes.md`
- `docs/guide/configuration.md`
- `docs/guide/first-operation.md`
- `docs/guide/installation.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/mvp-status.md`
- `docs/guide/security.md`
- `docs/guide/troubleshooting.md`
- `docs/internal/application-bootstrap.md`
- `docs/internal/worker-runtime.md`
- `docs/website/content-map.mjs`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `develop/spec/63-phase-12-delivery-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`

Website Generated Sourceと`dist/`はCommit対象へ追加していない。

## Decisions and Assumptions

- Sample Token方式はLocal Development専用とし、Production Authentication Library、Secret Store、Actor／Permission RepositoryはApplication責務のままとした。
- Missing HeaderとInvalid Headerは同じ401でも異なるLifecycle境界として公開した。
- WelcomeとReportは同じPolicyを宣言し、authorization ActorのTypeが`user`の場合だけAllowする最小Exampleとした。
- CredentialのMask保存は採用せず、Operation境界へ入れない。Sensitive Projection Exampleには別の業務値を使った。
- Websiteは`main` Document ChannelとしてPhase 12を説明するが、Stable `1.1.0`に未収録であることをInstallation、Quickstart、Current Statusへ明記した。Quickstartの後続手順はRepository `main` Previewだけを前提にする。
- Observed JSONLはProcess-local Projectionであり、Canonical PostgreSQL Journalを置き換えない。現行Worker ComposerへObserverを追加したとは扱わない。
- Cloudflare Publication、Version Tag、GitHub Release、Packagist更新は実行していない。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Architecture/QuickstartApplicationArchitectureTest.php tests/Integration/ApplicationHttpRuntimeTest.php tests/Integration/ApplicationConsoleKernelTest.php tests/Integration/MvpSampleEndToEndTest.php
Result: OK (16 tests, 333 assertions)。Review修正後は未設定／空／空白TokenのFail-closedも検証。

bash tests/Consumer/quickstart-e2e.sh
Result: Quickstart consumer E2E passed。Fail-closed、明示`.env`、Deferred Operation IDのObserved JSONL不在、Canonical Worker Eventを検証。

bash tests/Consumer/frankenphp-worker-mode.sh
Result: FrankenPHP worker mode consumer E2E passed。

bash tests/Consumer/quickstart-setup.sh
Result: Quickstart setup tests passed。

bash tests/Consumer/skeleton-create-project.sh
Result: Skeleton create-project smoke passed。

bash tests/Consumer/framework-update-generators.sh
Result: Framework update generator smoke passed。

mise exec -- pnpm --dir docs/website run test
Result: 36 tests / 36 passed。

mise exec -- pnpm --dir docs/website run check
Result: Content／Mermaid check成功。Astro 16 files、0 errors、0 warnings、0 hints。

mise exec -- pnpm --dir docs/website run build
Result: 29 pages built。Artifact boundary、Navigation、Accessibility、Pagefind search check成功。

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstartともにvalid。

docker compose run --rm app mago format src tests examples
docker compose run --rm app mago format --check src tests examples
Result: All files are already formatted。

docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (999 tests, 3391 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1844 / Warnings 0 / Errors 0。

Public Artifact Guard、Management ID Guard、Credential Property Guard、Quickstart Generated State Guard、git diff --check
Result: すべて成功。
```

最初のSandbox内Quickstart E2EはDocker API Permissionで開始できなかった。承認済みのDocker実行境界で再実行し、上記の最終結果を得た。実装調整中のE2E期待値失敗は修正後に全Consumer Gateを最初から再実行した。

## Acceptance Criteria

- [x] Installed QuickstartがService Provider経由でHeader AuthenticatorをBindingし、Global Authentication Middlewareを実行する
- [x] Expected TokenはApplication Runtimeで一度だけSnapshotされ、RequestごとにEnvironmentを読み直さない
- [x] Welcome／Reportが`#[Authorize]`を宣言し、Valid TokenでInline 200／Deferred 202となる
- [x] Missing TokenはOperation ID付き401 Rejected、Invalid TokenはOperation IDなしの受付前401となる
- [x] CredentialがOperation Value、Response、ExecutionContext、Transport、Canonical／Observed Journalへ保存されない
- [x] HTTP ProcessがProjectionしたCredential以外のSensitive Business FieldがObserved JSONLで`[masked]`となる
- [x] Deferred Workerがauthorization User Actorとexecution System Actorを分離してPolicyを再評価する
- [x] Validation 422、Retry／Backoff、Completion、Outcome保存のQuickstart E2Eが維持される
- [x] Create-project通常／`--no-scripts`が認証済みSkeleton SurfaceとBuildを再現する
- [x] Framework Update SmokeがApplication所有Exampleを上書きせず、更新済みFramework Commandを使う
- [x] Guide／Reference／Security／TroubleshootingとWebsite Artifactが実装Surfaceに一致する
- [x] Phase 12 Delivery Planの全Acceptance CriteriaがEvidence付きで完了する
- [x] Full PHP／Consumer／Website Quality Gateが成功する
- [x] TODO、Report、STATEを更新し、WorkerはCommitせずReviewへ返す

## Phase 12 Closeout

Middleware、Authentication、Durable ActorContext、Authorization Metadata／Inline Runtime、Canonical／Observed Actor Journal、Deferred Worker再認可、Installed Consumer Experienceの全Taskが実装済みである。`develop/spec/63-phase-12-delivery-plan.md`のAcceptance Criteriaを全件完了へ更新した。

## Remaining Issues

- Stable `1.1.0`にはPhase 12 Surfaceが未収録であり、次回Releaseまでは`main` DocumentとのChannel差がある。
- Production Authentication方式、Actor／Permission Store、Journal参照制御、Tenant分離、保存時暗号化は未提供である。
- Documentation WebsiteはUser判断どおり未公開である。
- Phase 13 Database and Transaction Runtimeは未着手である。

## Suggested Next Action

Phase 13 Database and Transaction Runtimeの設計監査へ進む。

## Orchestrator Review

2026-07-17T13:35:45+09:00にAcceptedとした。OrchestratorはQuickstart認証境界、Credential非保存、Deferred Actor分離、Observed JSONL／Canonical PostgreSQL Journal境界、Stable／`main` Preview手順を差分レビューした。初回Reviewで検出した既知Token Fallback、実行不能なChannel混在、Worker EventをObserved JSONLへ記載する誤りは修正済みである。

独立検証では対象PHPUnit 16件／333 assertions、Quickstart Consumer E2E、Website 36 tests／check／29ページbuild、Mago format／lint／analyze、Deptrac、Public Artifact／Management ID／Credential Property／Generated State／Diff Guardが成功した。Worker実行のFull PHPUnit 999件／3391 assertionsと残りConsumer GateもReportのEvidenceと一致する。Phase 12 Acceptance Criteriaを満たす。
