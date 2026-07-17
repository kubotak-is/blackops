# P12-006: Consumer Experience and Closeout

Status: Ready

## Goal

Phase 12で実装したPSR-15 Middleware、Authentication、Durable ActorContext、`#[Authorize]`、Deferred Worker再認可をInstalled Applicationから再現できるQuickstartへ統合する。CredentialをOperation Valueへ保存しない認証済みInline／Deferred Example、利用者向けGuide、Consumer E2E、Website Artifactを同期し、Full Quality Gate成功後にPhase 12をCloseする。

## In Scope

- QuickstartのHeader Token Authentication Example
- QuickstartのInline／Deferred Operationへの`#[Authorize]`適用
- CredentialをOperation Value／Transport／Journalへ入れないSample境界
- Authentication Service ProviderとGlobal Middleware Config
- Business上のSensitive Valueを使ったObserved Journal Mask Exampleの維持
- Anonymous／Invalid Credential／Authorized Inline／Authorized Deferred／Worker再認可のConsumer E2E
- Quickstart READMEとFramework Guide／Reference／Security／Troubleshootingの同期
- Website Source Test／Check／Build／Public Artifact Guardの同期
- Skeleton Create-projectとFramework Update Consumer Smokeの同期
- Phase 12 Specification Acceptance Criteria、TODO、Report、STATEのCloseout

## Out of Scope

- Production向けSession／JWT／OAuth／API Key Libraryの選定または実装
- Credential、Role、Permission、ClaimのExecutionContext／Transport／Journal保存
- Actor Repository、User Repository、Permission StoreのFramework具象
- Operation Middleware
- Phase 13 Database／Transaction Runtime
- Documentation WebsiteのCloudflare公開、Wrangler Deploy、GitHub Environment変更
- Framework／SkeletonのVersion Tag、GitHub Release、Packagist Publication

## Relevant Specifications and Decisions

- `develop/decisions/095-phase-12-middleware-and-authorization-runtime.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/63-phase-12-delivery-plan.md`
- `develop/spec/61-experimental-release-contract.md`

## Files Allowed to Change

- `examples/quickstart/.env.example`
- `examples/quickstart/README.md`
- `examples/quickstart/app/**`
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
- `docs/guide/**`
- `docs/internal/application-bootstrap.md`
- `docs/internal/worker-runtime.md`
- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/scripts/**`
- `docs/website/tests/**`
- `develop/spec/63-phase-12-delivery-plan.md`
- `develop/TODO.md`
- `develop/orchestration/reports/P12-006-consumer-experience-and-closeout.md`
- `develop/STATE.md`

## Implementation Constraints

- Quickstartは既存`X-Sample-Token`をAuthentication Credentialとして使用し、`.env.example`の`SAMPLE_API_TOKEN=local-example`をLocal Example値とする
- Quickstart Authenticatorは`HttpAuthenticator`を実装し、Header欠落をAnonymous、一致を`ActorRef`付きAuthenticated、不一致を安定Code `authentication.invalid_sample_token`のInvalidとして返す
- Token比較は`hash_equals()`を使用する。AuthenticatorはExpected TokenをApplication Runtimeで一度だけSnapshotし、Requestごとに`$_ENV`を参照しない
- Example TokenはLocal Development専用であり、ProductionではApplicationが認証方式とSecret管理を置き換える責務をREADME／Security Guideへ明記する
- Quickstart Service Providerは`HttpAuthenticator`をExample AuthenticatorへBindingし、`config/app.php`の`services`へ登録する
- `config/middleware.php`はFramework `AuthenticationMiddleware`をGlobal HTTP Pipelineへ登録する
- WelcomeとReport Operationは`#[Authorize]`で同じApplication Policyを宣言する。Policyはauthorization Actorを評価し、Authenticated `user` ActorだけをAllow、それ以外を安定CodeでForbiddenにする
- Credentialを`WelcomeValue`、`GenerateReportValue`、Outcome、ExecutionContext、Deferred Payload、Canonical／Observed Journalへ含めない。既存`sampleToken`／`apiToken` Propertyは削除する
- Sensitive Projectionの利用例はCredential以外のBusiness Dataへ置き換える。Deferred ReportにMask対象のSensitive Fieldを持たせ、Raw値がObserved JSONLへ出ず`[masked]`になることをConsumer E2Eで確認する
- Missing HeaderはAuthentication Middlewareを通過するが、Policy付きOperationがOperation ID付き401 Rejectedとなる。Invalid HeaderはOperation受付前の401でOperation ID／Journalを作らない。この違いをConsumer E2EとTroubleshootingで固定する
- Valid HeaderのInline Operationは200、Deferred Operationは202となり、Worker Attemptで同じauthorization Actorを再評価し、execution ActorだけConfigured Worker System ActorになることをDatabase／Journal Evidenceで検証する
- Quickstart E2EはValidation 422、Retry Scheduled、二回目Attempt Completion、Outcome保存、Observed Actor Mask、Credential不在を維持する
- `docs/guide/application-bootstrap.md`の認識Config File一覧は`middleware.php`を含む実装と一致させる
- Guideのコード例とCurlはQuickstartの実装から実行可能な形へ同期し、CredentialをJSON BodyのOperation Valueとして教えない
- Current mainの未Release SurfaceとStable `1.1.0`の差、Experimental Compatibility、Documentation Website未公開を削除または誤魔化さない
- Websiteは`docs/guide/`をSource of Truthとし、生成物をCommitしない。Cloudflare Deploy Commandを実行しない
- Phase Acceptance Criteriaは実装と全GateのEvidenceを確認してからだけ完了へ更新する
- Production Code／TestのCommentとDocBlockへDecision／Spec／Task管理番号を書かない

## Acceptance Criteria

- [ ] Installed QuickstartがService Provider経由でHeader AuthenticatorをBindingし、Global Authentication Middlewareを実行する
- [ ] Expected TokenはApplication Runtimeで一度だけSnapshotされ、RequestごとにEnvironmentを読み直さない
- [ ] Welcome／Reportが`#[Authorize]`を宣言し、Valid TokenでInline 200／Deferred 202となる
- [ ] Missing TokenはOperation ID付き401 Rejected、Invalid TokenはOperation IDなしの受付前401となる
- [ ] CredentialがOperation Value、Response、ExecutionContext、Transport、Canonical／Observed Journalへ保存されない
- [ ] Credential以外のSensitive Business FieldがObserved JSONLで`[masked]`となる
- [ ] Deferred Workerがauthorization User Actorとexecution System Actorを分離してPolicyを再評価する
- [ ] Validation 422、Retry／Backoff、Completion、Outcome保存のQuickstart E2Eが維持される
- [ ] Create-project通常／`--no-scripts`が認証済みSkeleton SurfaceとBuildを再現する
- [ ] Framework Update SmokeがApplication所有Exampleを上書きせず、更新済みFramework Commandを使う
- [ ] Guide／Reference／Security／TroubleshootingとWebsite Artifactが実装Surfaceに一致する
- [ ] Phase 12 Delivery Planの全Acceptance CriteriaがEvidence付きで完了する
- [ ] Full PHP／Consumer／Website Quality Gateが成功する
- [ ] TODO、Report、STATEを更新し、WorkerはCommitせずReviewへ返す

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format src tests examples
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/frankenphp-worker-mode.sh
bash tests/Consumer/quickstart-setup.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/framework-update-generators.sh
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
! rg -n 'docs/internal|develop/|ghp_|gho_|github_pat_' docs/website/dist
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
! rg -n 'sampleToken|apiToken' examples/quickstart/app tests/Consumer/quickstart-e2e.sh
! test -e examples/quickstart/composer.lock
! test -d examples/quickstart/vendor
git diff --check
```

## Expected Report

`develop/orchestration/reports/P12-006-consumer-experience-and-closeout.md`へ次を記録する。

- Summary
- Consumer Journey Evidence
- Credential and Actor Boundary Evidence
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Phase 12 Closeout
- Remaining Issues
- Suggested Next Action
