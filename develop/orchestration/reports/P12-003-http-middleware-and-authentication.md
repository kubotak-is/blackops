# P12-003: HTTP Middleware and Authentication Report

Status: Accepted

## Summary

Application Configuration SnapshotからGlobal PSR-15 Middlewareを読み、Build時にCompiled Containerへ登録し、Runtimeで解決した順序のままOperation HTTP Handlerを包むPipelineを実装した。Public Authentication Contract、三状態Result、ActorRefだけを渡すFramework Middleware、Operation IDなしのSafe 401境界も追加した。Orchestrator Review後は、長寿命Workerで再利用するMiddleware ChainのConstructor事前構築、Container解決例外のSafe変換、Authentication MiddlewareのCompile回帰、PSR-17既定Factoryの必要時だけの生成を補強した。

## Changed Files

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
- `examples/quickstart/config/middleware.php`
- `tests/Consumer/skeleton-create-project.sh`
- `docs/guide/core-api.md`
- `docs/guide/configuration.md`
- `docs/guide/security.md`
- `develop/orchestration/reports/P12-003-http-middleware-and-authentication.md`
- `develop/STATE.md`

## Decisions and Assumptions

- `middleware.php`はConfiguration Snapshotの既知Fileへ追加し、`http`未指定またはFile欠落を空Listとして扱う。Entryはtrim後の非空文字列、重複なしを要求する。
- Config Listは外側から内側の順を正本とし、Runtime Pipelineは逆順にHandler Chainを構成してA before、B before、Handler、B after、A afterを実現する。
- Service Provider登録済みIDは保持し、未登録の具象PSR-15 ClassだけをAutowired Public Serviceとして追加する。Config値をBuild／Runtime Errorへ含めない。
- Production Runtimeは空Listなら既存OperationRequestHandlerを直接返し、Middlewareがある場合だけPSR-15 Pipelineで包む。Compositionの型は`RequestHandlerInterface`へ一般化した。
- Middleware ChainはPipeline Constructorで一度だけ構築し、各`handle()`は事前構築済みChainへRequestを委譲する。FrankenPHP Workerで同じPipelineを再利用してもRequest状態を共有しないことを回帰Testで保証する。
- Containerの`get()`が任意の例外を投げても、Service IDや内部例外Message、Previous Exceptionを公開せず、固定Messageの`LogicException`へ変換する。
- `AuthenticationResult`はPrivate ConstructorとFactory MethodでAnonymous／Authenticated／Invalidだけを生成し、Invalid Codeは既存Rejection Code Patternで検証する。
- Framework予約Request Attribute Keyは`ActorRef::class`とし、Public HTTP LayerからInternal Layerへの逆依存を作らず、Internal Typed HelperからActorRefだけを取得できるようにした。
- Invalid Credential Responseは`status`、`category=unauthorized`、Safe Codeだけを含む401 JSONとし、Operation ID、Credential、Token、Backend Detailを含めない。
- PSR-17 Factory未注入時は既存Runtimeと同じReflection境界でNyholm Factoryを生成する。両Factoryが注入済みなら既定Factoryを生成しない。Applicationは認証MiddlewareのためだけにPSR-17 Serviceを登録する必要がない。
- Service Providerが`HttpAuthenticator`をBindingすれば、Application側が`AuthenticationMiddleware`やPSR-17 Factoryを登録しなくてもBuild時Auto-registrationとCompiled Containerからの取得が成功する。
- Magoが既存Composer依存のPSR-15 Contractを解析できるよう、OrchestratorがTask許可範囲を拡張した後、`vendor/psr/http-server-middleware`だけをthird-party includeへ追加した。

## Commands and Results

```text
docker compose run --rm app mago format src tests
Result: Success。初回8 files formatted、最終Checkでは変更不要。

docker compose run --rm app vendor/bin/phpunit tests/Http/Authentication/AuthenticationResultTest.php tests/Http/Authentication/AuthenticationMiddlewareTest.php tests/Internal/Http/HttpMiddlewarePipelineTest.php tests/Internal/Application/ApplicationConfigurationTest.php tests/Internal/Application/ApplicationHttpMiddlewareConfigurationTest.php tests/Internal/Application/ApplicationHttpMiddlewareResolverTest.php tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php tests/Internal/Runtime/ProductionRuntimeComposerTest.php tests/Internal/Runtime/ProductionRuntimeSmokeTest.php
Result: OK (41 tests, 104 assertions)。

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstartともにvalid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: Format済み。Lint／AnalyzeともにNo issues found。

docker compose run --rm app vendor/bin/phpunit
Result: OK (924 tests, 2955 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1775 / Warnings 0 / Errors 0。

bash tests/Consumer/skeleton-create-project.sh
Result: Success。通常／no-scripts Create-projectにmiddleware.phpが存在し、通常Setup／Buildも成功。

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
Result: Management ID違反なし。Diff Check成功。
```

初回AnalyzeはMago ConfigにPSR-15 Package Includeがなく型解決に失敗した。Task Packetへ`mago.toml`が追加された後、一行だけ追加して解消した。初回DeptracはPublic HTTP MiddlewareからInternal Attribute Helperへの逆依存を検出したため、ActorRef Class名を予約Keyとして両境界で共有し、最終Deptracを成功させた。Review Follow-upでは、DI回帰TestのAuthenticator BindingをAutowired Serviceとして定義し、Pipeline Constructor内のMiddleware型絞り込みを明示してMago Analyzeを通過させた。

## Acceptance Criteria

- [x] `middleware.php`をConfiguration Snapshotが一度だけ読み込む
- [x] Global MiddlewareがConfig順の玉ねぎPipelineとしてRequest／Responseを加工する
- [x] Middleware ClassをBuild Containerへ自動登録し、Provider登録済みServiceを上書きしない
- [x] 不正Config、未登録ID、非PSR-15 ServiceをSafeにFail-fastする
- [x] AuthenticationResultの三状態とInvariantがTestされる
- [x] AnonymousはDownstreamへ進み、RequestへActorを追加しない
- [x] AuthenticatedはActorRefだけをTyped内部境界へ渡す
- [x] InvalidはOperation IDなしのSafe 401 JSONとなり、Downstreamを呼ばない
- [x] Authenticator例外がSecurity Denialへ丸められない
- [x] Empty Pipelineで既存Runtime TestとConsumer Contractが維持される
- [x] Quickstart／Skeletonに空Middleware Configが存在する
- [x] Guideが登録、Service Provider、責任分界、Credential非保持を説明する
- [x] Required Commandsが成功する
- [x] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Remaining Issues

- HTTP ActorをOperation ExecutionContextへ接続する処理、`#[Authorize]`、Policy、Inline／Deferred Authorizationは後続TaskのScopeである。
- Application固有AuthenticatorとAuthenticationMiddlewareのService Provider登録例はConsumer Experience CloseoutでQuickstartへ追加する。
- Blockerはない。

## Orchestrator Review

- PSR-15 PipelineがConfig順を保ったままConstructorで一度だけ構築され、同一Instanceを複数Requestで再利用してもRequest状態を共有しないことを確認した。
- Provider登録を優先するBuild Container統合、未登録／型不正／解決例外のSafe Fail-fast、Authentication三状態とCredential非保持境界を確認した。
- 対象PHPUnit 41 tests／104 assertions、Mago format／lint／analyze、Deptrac、Management ID Guard、`git diff --check`をOrchestratorが独立再実行し、すべて成功した。
- Acceptance Criteriaを満たすため、本TaskをAcceptedとする。

## Suggested Next Action

P12-004 Authorization Metadata and Inline Runtimeへ進む。
