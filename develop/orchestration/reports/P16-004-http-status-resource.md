# P16-004 HTTP Status Resource Report

Status: Accepted

## Summary

Public `OperationStatusQuery`をFramework予約`GET /operations/{operationId}`へ接続した。Status ResourceはApplicationのGlobal PSR-15 Middleware／Authenticationの内側で処理し、Current ActorをQueryへ渡す。Application Containerに`OperationStatusAuthorizer` Bindingがあれば使用し、未登録時は既定DenyへFail-closedする。

7 StateのSchema Version 1 JSON、Safe 404／410／500、`private, no-store`、Non-terminalの`Retry-After: 1`を実装した。Deferred受付202は既存Bodyを維持し、同じResourceを指す相対`Location`、共有Polling Hint、Cache Headerを追加した。Classic EntrypointとFrankenPHP Worker ModeはEntrypoint分岐を追加せず、`Application::http()`が構成する同一Handler Graphを使う。

## Changed Files

### Production

- `src/Http/OperationRequestHandler.php`
- `src/Http/Responder/JsonOperationResponder.php`
- `src/Http/Routing/HttpRouteCompiler.php`
- `src/Http/Status/OperationStatusHttpContract.php`
- `src/Http/Status/OperationStatusJsonResponder.php`
- `src/Http/Status/OperationStatusRequestHandler.php`
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php`
- `src/Internal/Http/OperationStatusAuthorizerResolver.php`
- `src/Internal/Runtime/ProductionRuntimeComposer.php`
- `src/Internal/Runtime/ProductionRuntimeDependencies.php`
- `deptrac.yaml`

### Tests

- `tests/Http/DeferredOperationRequestHandlerTest.php`
- `tests/Http/OperationRequestHandlerTest.php`
- `tests/Http/Status/OperationStatusRequestHandlerTest.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- `tests/Internal/Http/OperationStatusAuthorizerResolverTest.php`
- `tests/Internal/Runtime/ProductionRuntimeComposerTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`

### Documentation and Orchestration

- `docs/internal/status-query.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P16-004-http-status-resource.md`

## Decisions and Assumptions

- Status Handlerを`OperationRequestHandler`の予約Resource分岐として統合した。Production Runtimeの既存Global Middleware ChainがHandler全体を包むため、Status ResourceもAuthentication後に到達する。
- `/operations/`直下の1 SegmentだけをStatus Resource候補とし、GET以外と追加Segmentは既存Application Route規則へ渡す。空、非UUIDv7、非Canonical UUIDv7はSourceを呼ばずSafe 404にする。
- Build時CollisionはGETかつ`/operations/`直下の1 Segmentを持つ全Routeとした。Dynamic Parameter名やStatic値に依存せず拒否し、MethodまたはSegment数が異なるRouteは維持する。
- Application ContainerにAuthorizer Bindingがなければ`DenyOperationStatusAuthorizer`を使用する。解決失敗と型不一致はRaw Detailなしの安全なBootstrap Failureにする。
- Status Resourceの独自Throwable BoundaryでQuery／Projection／JSON Encode失敗をSafe 500へ正規化する。Application共通Boundaryは500 Responseを検知して既存Connection Cleanupを実行する。
- Completed OutcomeはインスタンスのPublic PropertyだけをObjectへ写す。Private PropertyとPublic Static Propertyは除外し、`EmptyOutcome`は`{}`とする。
- Polling HintとCache Contractは`OperationStatusHttpContract`に集約し、Deferred 202とStatus 200の値を共有する。
- Status GETの空でないBodyはStatus Queryより先に既存同等のBare 400へ拒否する。Application Route未一致は従来どおりBody検査より先に404を返す。
- Public PHP API、PostgreSQL Schema／Migration、Frontend、Quickstart、Websiteは変更していない。

## HTTP Contract Matrix

| Request／Query | HTTP | Additional headers | Body |
| --- | --- | --- | --- |
| Found `accepted`／`running`／`retry_scheduled` | 200 | `Retry-After: 1` | Schema Version 1 Resource |
| Found Terminal State | 200 | なし | Schema Version 1 Resource |
| Invalid ID／Unknown／Deny | 404 | なし | `operation_unavailable` |
| Authorized Expired | 410 | なし | `operation_expired` |
| Query／Projection／Encode Failure | 500 | なし | `internal_error` |
| Status GET with Body | 400 | なし | 空Body |
| Deferred Acceptance | 202 | `Location`、`Retry-After: 1` | 既存Bodyを維持 |

Status Resourceの全ResponseとDeferred受付202は`Cache-Control: private, no-store`を持つ。Status Resourceの200／404／410／500は`Content-Type: application/json`を持つ。

## Request Order and Authentication Evidence

```text
Global PSR-15 Middleware
  -> Authentication Middleware
    -> Status／Application Handler
```

Runtime Composition TestはGlobal MiddlewareのResponse HeaderがStatus 200にも付くこと、Authenticated ActorがStatus Queryへ渡ることを確認する。Invalid Credentialは既存401を返し、Status Query Callは0である。空でないGET BodyもQuery前に400となる。一方、未知Application Routeは既存順序を維持して404を返す。

## Reserved Route Collision Matrix

| Route | Result |
| --- | --- |
| `GET /operations/{operationId}` | Build Error |
| `GET /operations/{id}` | Build Error |
| `GET /operations/example` | Build Error |
| `POST /operations/{operationId}` | Existing Route Rule |
| `GET /operations/{operationId}/outcome` | Existing Route Rule |

## State Resource Shape Matrix

| State | Additional fields | Retry-After |
| --- | --- | --- |
| `accepted` | なし | `1` |
| `running` | `attempt` | `1` |
| `retry_scheduled` | `attempt`、UTC `retryAt` | `1` |
| `completed` | Object `outcome` | なし |
| `rejected` | `error.category`、`error.code` | なし |
| `failed` | `error.code=operation_failed` | なし |
| `dead_lettered` | `error.code=operation_dead_lettered` | なし |

全Stateは`schemaVersion`、Canonical `operationId`、Dotted `operationType`、`state`を持つ。State固有でないFieldは追加しない。

## Authorizer Composition and Default-deny Evidence

Application Build TestでService Providerの`OperationStatusAuthorizer` BindingがCompiled Containerから解決されることを確認した。実Application E2EではAllow AuthorizerへOperation ID／Type、Current／Origin Actorが渡り、実PostgreSQLのaccepted Projectionを200で返す。Bindingなしの別Applicationは受付済みOperationでも同じSafe 404を返し、Detailを露出しない。

## Classic and Worker-mode Evidence

Status Queryは`ApplicationHttpRuntimeComposer`から`ProductionRuntimeComposer`へ一度だけ注入する。ClassicとFrankenPHP Worker ModeのEntrypointにはStatus固有分岐を追加せず、どちらも`Application::http()`が返す同一`ApplicationHttpRequestHandler`を使う。既存Application Request Lifecycle Testは500後のConnection Close、Transaction Cleanup、次Requestの回復を固定する。

## Sensitive and Safe Failure Evidence

Status 404／410／500は固定Codeだけを返し、Operation ID、Type、Actor、SQL、Exception、Retention Detailを含めない。Completed OutcomeはPublic Instance Propertyだけを出力し、Private／Static Propertyを除外する。Actor、Attempt ID、Correlation／Causation ID、Journal、Dead Letter Detail、Canonical PayloadはSchema Version 1 Resourceへ投影しない。Restricted Field GuardとJSON Encode Failure Testがこの境界を固定する。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid。

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid。

docker compose run --rm app mago format --check src tests
Result: All files are already formatted。

docker compose run --rm app mago lint
Result: No issues found。

docker compose run --rm app mago analyze
Result: No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations <P16-004 actual responsibility targets>
Result: OK (265 tests, 1188 assertions)。実Application E2Eを含む。

docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Integration/ApplicationHttpRuntimeTest.php \
  tests/Http/OperationRequestHandlerTest.php \
  tests/Internal/Runtime/ProductionRuntimeComposerTest.php
Result: OK (42 tests, 182 assertions)。Review Correction後に成功。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1419 tests, 5529 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2526 / Warnings 0 / Errors 0。

Management ID／Internal PublicApi／Restricted Field／Schema DDL／git diff --check Guards
Result: 全成功。Migration／Schema差分なし。
```

Task Packet記載の対象Commandは、Repositoryに存在しない`tests/Internal/Application/ApplicationHttpRuntimeComposerTest.php`を指定するため、そのPathだけで初回実行が停止した。同じ責務の実在Testである`ApplicationHttpRequestHandlerTest.php`、Application Build Test、Runtime Composition Test、HTTP／Status／PostgreSQL Status Test、実`ApplicationHttpRuntimeTest.php`をすべて指定して再実行し、265 tests／1188 assertionsが成功した。Task Packetが許可する「実在する同じ責務のTest」代替に従っている。

初回Deptracは新しいHTTP AdapterからPublic Status Contractへの依存14件を検出した。`Http -> Status`をLayer規則へ追加し、最終実行は0違反となった。

## Orchestrator Review Corrections

実`Application::http()` CompositionのEvidenceを追加した。QuickstartのDeferred `POST /reports`が返す相対`Location`を、同じActorを持つGETへそのまま使用する。Compiled `ApplicationRuntimeServiceProvider`の`OperationStatusAuthorizer` Binding、`PostgreSqlOperationStatusSource`、`DefaultOperationStatusQuery`を通り、Schema Version 1の`accepted` 200、`Retry-After: 1`、`private, no-store`を返すことを実PostgreSQLで確認した。Authorizer RequestへOperation ID、`report.generate`、Current Actor、Persisted Origin Actorが届くことも固定した。

Authorizerを登録しない別の実Applicationでは、同じく受付済みのOperation IDでもGETが`operation_unavailable`のSafe 404になる。これによりCompiled Binding維持と未登録default-denyの両方をApplication境界で証明した。

追加Reviewで、Status分岐が既存GET Body禁止より先にQueryへ到達する経路を修正した。Status Handlerが空でないBodyをQuery前にBare 400へ拒否し、Query Callが0であることを回帰Testで固定した。通常の未知Application GET RouteにBodyがある場合は、従来どおり404が400より優先される。

## Orchestrator Review

予約Route Collision、Middleware／Authentication順序、Current Actor伝播、既定Deny、State別JSON、Safe Error、Outcome Property境界、202 Header、Classic／Worker共通Compositionを確認した。初回Reviewで実`Application::http()` Compositionの直接Evidence不足を検出し、Task PacketへIntegration Testを追加した。さらにStatus GETのBody禁止順序を補正し、どちらも回帰TestとReportへ固定した。

Orchestrator再実行でComposer Root／Quickstart、Mago format／lint／analyze、対象265 tests／1188 assertions、全1419 tests／5529 assertions、Deptrac 0違反／0警告／0エラー、Management ID／Internal Public API／Restricted Field／DDL／`git diff --check`の全Guardが成功した。Public PHP API、PostgreSQL Schema、Frontend、Quickstart、Websiteの範囲逸脱と仕様矛盾はなくAcceptedとした。

## Acceptance Criteria

- [x] Application Routeと競合し得る予約GET ResourceをBuild Errorにする
- [x] Global Middleware／Authenticationの内側でStatus Resourceを処理する
- [x] Invalid CredentialがStatus Query前に既存401を返す
- [x] Status GETの空でないBodyがStatus Query前に400を返し、Application Routeの404優先順を維持する
- [x] Application `OperationStatusAuthorizer` Bindingを使い、未登録はDenyになる
- [x] Invalid ID／Unknown／Denyが同じSafe 404になる
- [x] Authorized ExpiredがSafe 410になる
- [x] Query FailureがRaw DetailなしのSafe 500になる
- [x] 7 StateのSchema Version 1 JSONとState別Field境界を実装する
- [x] Completed OutcomeをObjectとして返し、`EmptyOutcome`を`{}`にする
- [x] Status 200／404／410／500に`private, no-store`を付ける
- [x] Non-terminal 200だけに正整数`Retry-After`を付ける
- [x] Deferred受付202のBodyを維持し、相対`Location`／`Retry-After`／`private, no-store`を追加する
- [x] Status ResourceへSensitive／Actor／Canonical／Diagnostics／Raw Errorを露出しない
- [x] Classic／FrankenPHP Worker Modeが同一Handler GraphとResponse Contractを使う
- [x] PostgreSQL Schema、Public PHP API、Frontend、Quickstart、Websiteを変更しない
- [x] Required PHP／PostgreSQL Quality Gateが成功する
- [x] WorkerはCommitしていない

## Remaining Issues

実装上のBlockerと仕様矛盾はない。Generated `.status()`とTyped Outcome Decoder、有限な`.wait()`、Consumer Example／Guide同期は後続Taskの責務である。

## Suggested Next Action

P16-004をCommit／Pushし、P16-005 Generated Status Clientへ進む。
