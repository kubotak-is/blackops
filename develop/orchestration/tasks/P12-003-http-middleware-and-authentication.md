# P12-003: HTTP Middleware and Authentication

Status: Accepted

## Goal

D095／Spec 06／Phase 12 Delivery Planに従い、Application ConfigからGlobal PSR-15 MiddlewareをCompiled Container経由で構成し、Credentialを永続化しないAuthentication ContractとFramework MiddlewareをHTTP Runtimeへ接続する。

## In Scope

- `config/middleware.php`の`http` List読込とFail-fast Validation
- Middleware ClassのBuild-time Autowire登録と既存Service Provider登録の尊重
- Compiled ContainerからのMiddleware解決とPSR-15型検証
- Global Middlewareの登録順を維持する玉ねぎPipeline
- `HttpAuthenticator` Public Contract
- Anonymous／Authenticated／Invalidを表す`AuthenticationResult`
- Framework `AuthenticationMiddleware`
- Authenticated ActorRefだけをFramework予約Request Attributeへ渡す内部境界
- Invalid CredentialのSafe JSON 401、予期外Authenticator例外の伝播
- Application HTTP Runtime／Production Runtime Compositionへの接続
- Empty Middleware Configで既存挙動を維持
- Quickstart／Skeletonの空`config/middleware.php`
- Core API／Configuration／Security Guideの最小同期

## Out of Scope

- Session／JWT／API Keyの具象Authenticator
- CredentialのRefresh／Login Endpoint
- ActorContextをOperation ExecutionContextへ接続する処理
- `#[Authorize]`／Policy／Manifest Metadata
- Inline／Deferred Authorization評価
- Operation単位Middleware Attribute
- Console／Message／Operation Middleware
- Canonical Journal Actor Field／Worker System Actor
- Documentation Website全体とConsumer Tutorialの同期

## Relevant Specifications and Decisions

- `develop/decisions/095-phase-12-middleware-and-authorization-runtime.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/63-phase-12-delivery-plan.md`

## Files Allowed to Change

- `mago.toml`
- `src/Http/Authentication/HttpAuthenticator.php`
- `src/Http/Authentication/AuthenticationResult.php`
- `src/Http/Authentication/AuthenticationMiddleware.php`
- `src/Internal/Http/HttpActorRequestAttribute.php`
- `src/Internal/Http/HttpMiddlewarePipeline.php`
- `src/Internal/Application/ApplicationConfigurationLoader.php`
- `src/Internal/Application/ApplicationHttpMiddlewareConfiguration.php`
- `src/Internal/Application/ApplicationHttpMiddlewareResolver.php`
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`
- `src/Internal/Runtime/ProductionRuntimeDependencies.php`
- `src/Internal/Runtime/ProductionRuntimeComposer.php`
- `src/Internal/Runtime/ProductionRuntimeComposition.php`
- `tests/Http/Authentication/AuthenticationResultTest.php`
- `tests/Http/Authentication/AuthenticationMiddlewareTest.php`
- `tests/Internal/Http/HttpMiddlewarePipelineTest.php`
- `tests/Internal/Application/ApplicationConfigurationTest.php`
- `tests/Internal/Application/ApplicationHttpMiddlewareConfigurationTest.php`
- `tests/Internal/Application/ApplicationHttpMiddlewareResolverTest.php`
- `tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php`
- `tests/Internal/Runtime/ProductionRuntimeComposerTest.php`
- `tests/Internal/Runtime/ProductionRuntimeSmokeTest.php`
- `examples/quickstart/config/middleware.php`
- `tests/Consumer/skeleton-create-project.sh`
- `docs/guide/core-api.md`
- `docs/guide/configuration.md`
- `docs/guide/security.md`
- `develop/orchestration/reports/P12-003-http-middleware-and-authentication.md`
- `develop/STATE.md`

## Implementation Constraints

- HTTP Middleware Public ContractはPSR-15 `MiddlewareInterface`を直接使用し、BlackOps markerを追加しない
- `middleware.http`はListだけを許可し、trim後の非空Service ID／Class-stringを外側から内側の順で保持する
- 重複EntryはBuild／Startup前に拒否し、数値Priorityや自動並び替えを導入しない
- Listed ClassがContainerに未登録で、存在するPSR-15 Middleware ClassならBuild Compile時にAutowired／Public Serviceとして自動登録する
- Service Providerが同じIDを登録済みなら上書きしない
- 未登録ID、存在しないClass、PSR-15でない解決結果はCredentialやConfig値を露出しない例外でFail-fastする
- PipelineはMiddleware A before → B before →Operation Handler→B after→A afterの順をTestする
- `HttpAuthenticator::authenticate(ServerRequestInterface): AuthenticationResult`とする
- `AuthenticationResult`は`#[PublicApi] final readonly class`で、Factory MethodによりAnonymous／Authenticated／Invalidだけを構築できるようInvariantを持つ
- Invalid Codeは`RejectionReason`と同じ安定Code Patternを要求する
- `AuthenticationMiddleware`は`#[PublicApi] final readonly class`でPSR-15を実装し、AuthenticatorをConstructor Injectionする
- Optional PSR-17 Factory未注入時はFramework既定Nyholm Factoryを使い、Applicationが認証Middleware利用のためだけにPSR-17 Serviceを登録しなくてよい形にする
- AnonymousはRequestを変更せず次へ渡す
- AuthenticatedはActorRefだけをFramework予約Request Attributeへ設定する
- InvalidはDownstreamを呼ばず、Operation IDなしの401 JSON `status=error`、`category=unauthorized`、Safe Codeを返す
- Authenticatorの予期外例外をInvalidへ丸めず、そのまま上位HTTP Error境界へ渡す
- Password、Session、Bearer Token、API Key、JWT Claim等をResult、Request Attribute、Response、Log、Contextへ追加しない
- Production Runtimeの`httpHandler`型はPipelineを返せる`RequestHandlerInterface`とする
- Middleware Configが空またはFile欠落の場合、現在のOperationRequestHandlerを意味的にそのまま実行する
- Production Code／TestのCommentとDocBlockへDecision／Spec／Task管理番号を書かない

## Acceptance Criteria

- [ ] `middleware.php`をConfiguration Snapshotが一度だけ読み込む
- [ ] Global MiddlewareがConfig順の玉ねぎPipelineとしてRequest／Responseを加工する
- [ ] Middleware ClassをBuild Containerへ自動登録し、Provider登録済みServiceを上書きしない
- [ ] 不正Config、未登録ID、非PSR-15 ServiceをSafeにFail-fastする
- [ ] AuthenticationResultの三状態とInvariantがTestされる
- [ ] AnonymousはDownstreamへ進み、RequestへActorを追加しない
- [ ] AuthenticatedはActorRefだけをTyped内部境界へ渡す
- [ ] InvalidはOperation IDなしのSafe 401 JSONとなり、Downstreamを呼ばない
- [ ] Authenticator例外がSecurity Denialへ丸められない
- [ ] Empty Pipelineで既存Runtime TestとConsumer Contractが維持される
- [ ] Quickstart／Skeletonに空Middleware Configが存在する
- [ ] Guideが登録、Service Provider、責任分界、Credential非保持を説明する
- [ ] Required Commandsが成功する
- [ ] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Required Commands

```bash
docker compose run --rm app mago format src tests
docker compose run --rm app vendor/bin/phpunit tests/Http/Authentication/AuthenticationResultTest.php tests/Http/Authentication/AuthenticationMiddlewareTest.php tests/Internal/Http/HttpMiddlewarePipelineTest.php tests/Internal/Application/ApplicationConfigurationTest.php tests/Internal/Application/ApplicationHttpMiddlewareConfigurationTest.php tests/Internal/Application/ApplicationHttpMiddlewareResolverTest.php tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php tests/Internal/Runtime/ProductionRuntimeComposerTest.php tests/Internal/Runtime/ProductionRuntimeSmokeTest.php
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/skeleton-create-project.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P12-003-http-middleware-and-authentication.md`へ次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
