# P16-004: HTTP Status Resource

Status: Ready

## Goal

P16-002／P16-003のPublic Status QueryをFramework標準の`GET /operations/{operationId}`へ接続し、ApplicationのGlobal PSR-15 Middleware／Authenticationを通したうえで、7 State、Safe Error、RetentionをVersion付きJSON Resourceとして返す。

Deferred受付の既存202 Bodyは維持し、同じResourceを指す相対`Location`、正整数`Retry-After`、`Cache-Control: private, no-store`を追加する。Classic EntrypointとFrankenPHP Worker Modeは`Application::http()`が構成する同一Contractを使う。

## In Scope

- Framework予約`GET /operations/{operationId}`のBuild-time Collision検査
- Status ResourceのRuntime Route／Request Handler／JSON Responder
- Public `OperationStatusQuery`とPostgreSQL SourceのApplication HTTP Composition
- Application Service Providerによる`OperationStatusAuthorizer` Bindingと未登録時の既定Deny
- Current ActorのStatus Queryへの引き渡し
- 7 StateのSchema Version 1 JSON
- 200／404／410／500とSafe Error Body
- `Cache-Control: private, no-store`
- Non-terminal 200の正整数`Retry-After`
- Deferred受付202の相対`Location`／`Retry-After`／`Cache-Control`
- Invalid Operation ID、Unknown、Deny、Expired、Query FailureのHTTP境界
- Authentication Middlewareを含むPSR-15順序
- Classic／FrankenPHP共通CompositionのUnit／Integration Test
- Internal Documentation、Report、TODO、STATE同期

## Out of Scope

- Public PHP Status APIまたは7 Stateの変更
- PostgreSQL Schema／Migration／Status Projection Authorityの変更
- Generated Frontend Contract、TypeScript、`.status()`、`.wait()`
- Quickstart／SkeletonのStatus Authorizer例
- Guide／Documentation Website Source／Website Publication
- Application固有Status Route、List／Search／Bulk／Cancel／Retry
- WebSocket／SSE／Webhook
- Global Mutable Client、Status Cache、Polling Loop
- 401 Authentication Response Shapeの変更

## Relevant Specifications and Decisions

- `develop/spec/05-http.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/42-installed-application-boundary.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/spec/70-phase-16-delivery-plan.md`
- `develop/decisions/102-phase-16-deferred-status-and-outcome-api.md`

## Files Allowed to Change

### Production

- `src/Http/OperationRequestHandler.php`
- `src/Http/Responder/JsonOperationResponder.php`
- `src/Http/Routing/HttpRouteCompiler.php`
- New `src/Http/Status/**`
- New `src/Internal/Http/*Status*.php`
- `src/Internal/Runtime/ProductionRuntimeComposer.php`
- `src/Internal/Runtime/ProductionRuntimeDependencies.php`
- `src/Internal/Runtime/ProductionRuntimeComposition.php`（必要な場合だけ）
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php`
- `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`（Status AuthorizerのBuild登録または保護に必要な場合だけ）
- `src/Internal/Console/CompileBuildArtifactsCommand.php`（Container Build統合が必要な場合だけ）
- `src/Internal/Console/ApplicationBuildCompileCommand.php`（Application-aware Build統合が必要な場合だけ）
- `deptrac.yaml`（新しい内部HTTP依存のLayer同期が必要な場合だけ）

既存Public InterfaceへMethodを追加しない。新しいHTTP Resource用型は可能な限りInternalまたはHTTP Adapter型とし、`#[PublicApi]`を追加しない。

### Tests and Fixtures

- `tests/Http/OperationRequestHandlerTest.php`
- `tests/Http/Responder/JsonOperationResponderTest.php`
- `tests/Http/Routing/HttpRouteCompilerTest.php`
- New `tests/Http/Status/**`
- New `tests/Internal/Http/*Status*.php`
- `tests/Internal/Runtime/ProductionRuntimeComposerTest.php`
- `tests/Internal/Runtime/ProductionRuntimeArtifactTest.php`（必要な場合だけ）
- `tests/Internal/Application/ApplicationHttpRuntimeComposerTest.php`
- `tests/Internal/Application/ApplicationHttpRequestHandlerTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Internal/Console/CompileBuildArtifactsCommandTest.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- P16-004専用の新規`tests/Fixtures/**`

実在するTest名が上記と異なる場合は、同じ責務の既存TestとP16-004専用Testを変更できる。

### Documentation and Orchestration

- `docs/internal/status-query.md`
- `docs/internal/http-runtime.md`（実在する場合だけ）
- `develop/spec/05-http.md`（実装済みContractへの同期だけ）
- `develop/spec/47-public-http-runtime-configuration.md`（実装済みCompositionへの同期だけ）
- `develop/spec/69-deferred-status-and-outcome-api.md`（実装不能な矛盾を発見した場合だけ）
- `develop/spec/70-phase-16-delivery-plan.md`（Task境界の誤りを発見した場合だけ）
- `develop/TODO.md`
- `develop/orchestration/reports/P16-004-http-status-resource.md`
- `develop/STATE.md`

上記以外の変更が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Runtime Request Order

Status ResourceはOperation Routeと同じApplication HTTP Handlerの内側に統合する。

```text
Server Request
  -> Global PSR-15 Middleware
    -> Authentication Middleware
      -> Framework HTTP Router
        -> GET /operations/{operationId}: Status Resource
        -> Application Operation Route: OperationRequestHandler
```

- Status ResourceをMiddlewareより外側へ置かない
- Authentication MiddlewareがInvalid Credentialを返した場合は、Status Subjectを読まず既存401を返す
- Authenticated RequestはRequest AttributeのCurrent `ActorRef`をStatus Queryへ渡す
- Anonymous Requestは`null`を渡す
- Status Query AuthorizerはExecution `#[Authorize]`とは別であり、Persisted Valueを読まない
- Unknown／Denyは同じ404 Body／Headerにする

## Authorizer Composition

- Compiled Application Containerに`OperationStatusAuthorizer::class` Bindingがあれば、それを取得して使用する
- Bindingがなければ`DenyOperationStatusAuthorizer`を使用する
- Containerから取得したServiceがContractを満たさない場合は安全なBootstrap Failureとする
- FrameworkはApplicationのAuthorizerを上書きしない
- Status AuthorizerのためにPublic `Application` GetterまたはRaw Container Getterを追加しない
- P16-004ではQuickstartへBinding例を追加しない。P16-007でConsumer Exampleを同期する

## Reserved Route and Build Collision

Frameworkは`GET /operations/{operationId}`を予約する。Application OperationのRouteがこのResourceとRuntimeで競合し得る場合、Build／Manifest Compile時に明確なErrorで拒否する。

少なくとも次をCollisionとする。

```text
GET /operations/{operationId}
GET /operations/{id}
GET /operations/example
```

MethodがGET以外、またはSegment数が異なりStatus Resourceと競合しないRouteは既存規則を維持する。Collision ErrorへApplication SecretやSource内容を含めない。Runtime側のRoute順序だけでApplication Routeを隠さない。

## Request Validation and HTTP Mapping

- MethodはGETだけを受理する。HEADや他Methodは既存Route規則に従い404とする
- Pathは`/operations/`直下の1 SegmentだけをStatus Resourceとして扱う
- Operation IDは既存`OperationId`のCanonical UUIDv7形式としてParseする
- 空、追加Slash、追加Segment、非Canonical／非UUIDv7 IDはStatus Sourceを呼ばず404にする
- Path SegmentからRaw値をError Body、Log Context、Exception Messageへコピーしない

| Query result | HTTP | Body code |
| --- | --- | --- |
| Found: 7 State | 200 | State Resource |
| Unavailable: Invalid／Unknown／Deny | 404 | `operation_unavailable` |
| Authorized Expired | 410 | `operation_expired` |
| Query Exception／Unexpected Failure | 500 | `internal_error` |

404／410／500のSafe Error Bodyは既存HTTP Errorの最小Shapeに合わせ、`status: error`と上表の`code`だけを返す。Operation ID、Operation Type、Actor、Source分類、Exception、SQL、Retention Detailを含めない。

Status Resourceの200／404／410／500すべてに次を付ける。

```text
Content-Type: application/json
Cache-Control: private, no-store
```

Status Resource自身のUnexpected ThrowableはApplication共通Error BoundaryへRaw Detailを渡さずSafe 500へ正規化する。既存Operation Execution FailureのCorrelation付き500 Contractは変更しない。

## Schema Version 1 Resource

すべてのFound Responseは次の共通Fieldをこの意味で持つ。

```text
schemaVersion: 1
operationId: canonical UUIDv7 string
operationType: registered dotted type ID
state: accepted|running|retry_scheduled|completed|rejected|failed|dead_lettered
```

State別Fieldを厳密にする。

| State | Required additional fields | Forbidden additional fields |
| --- | --- | --- |
| `accepted` | なし | `attempt`、`retryAt`、`outcome`、`error` |
| `running` | 正整数`attempt` | `retryAt`、`outcome`、`error` |
| `retry_scheduled` | 正整数`attempt`、Time CodecのUTC `retryAt` | `outcome`、`error` |
| `completed` | Object `outcome` | `attempt`、`retryAt`、`error` |
| `rejected` | `error.category`、`error.code` | `attempt`、`retryAt`、`outcome` |
| `failed` | `error.code=operation_failed` | `attempt`、`retryAt`、`outcome`、`error.category` |
| `dead_lettered` | `error.code=operation_dead_lettered` | `attempt`、`retryAt`、`outcome`、`error.category` |

- Completed `EmptyOutcome`は空JSON Object `{}`として表現する
- Outcomeは既存HTTP Outcome正規化境界を再利用または同等に共有し、Public PropertyだけをJSON Objectへ写す
- JSON Encode失敗はRaw値を含まないSafe 500にする
- Actor、Attempt ID、Correlation／Causation ID、Journal／Lease／Fencing、Raw Violation、Exceptionを追加しない

## Retry and Cache Headers

P16-004のFramework既定Polling Hintは`1`秒とする。Application Configurationは追加しない。

- Found `accepted`／`running`／`retry_scheduled`の200へ`Retry-After: 1`
- Terminal 200へ`Retry-After`を付けない
- 404／410／500へ`Retry-After`を付けない
- Deferred受付成功202へ`Retry-After: 1`
- Deferred受付成功202へ`Location: /operations/{canonicalOperationId}`
- Deferred受付成功202へ`Cache-Control: private, no-store`
- 既存202 JSON BodyのKey／Valueは変更しない
- Inline 200／204、Rejected 4xx、Protocol 400、Validation 422のHeaderはこのTaskで変更しない

Hint値は内部の一箇所で共有し、202とStatus Non-terminalで不一致にしない。

## Classic and Worker-mode Consistency

`Application::http()`が作るHandler GraphへStatus ResourceとQueryを一度だけ組み込む。Classic EntrypointとFrankenPHP Worker ModeのEntrypoint固有分岐を追加しない。

- Request終了時の既存Database Connection Lifecycleを維持する
- Status QueryのSnapshot TransactionをRequest間へ残さない
- 404／410／500後の次Requestを汚染しない
- RuntimeでSource Discovery、Build、Migrationを実行しない
- Operation Manifest／HTTP Manifest／Compiled Containerの既存Build ID整合を維持する

## Acceptance Criteria

- [ ] Application Routeと競合し得る予約GET ResourceをBuild Errorにする
- [ ] Global Middleware／Authenticationの内側でStatus Resourceを処理する
- [ ] Invalid CredentialがStatus Query前に既存401を返す
- [ ] Application `OperationStatusAuthorizer` Bindingを使い、未登録はDenyになる
- [ ] Invalid ID／Unknown／Denyが同じSafe 404になる
- [ ] Authorized ExpiredがSafe 410になる
- [ ] Query FailureがRaw DetailなしのSafe 500になる
- [ ] 7 StateのSchema Version 1 JSONとState別Field境界を実装する
- [ ] Completed OutcomeをObjectとして返し、`EmptyOutcome`を`{}`にする
- [ ] Status 200／404／410／500に`private, no-store`を付ける
- [ ] Non-terminal 200だけに正整数`Retry-After`を付ける
- [ ] Deferred受付202のBodyを維持し、相対`Location`／`Retry-After`／`private, no-store`を追加する
- [ ] Status ResourceへSensitive／Actor／Canonical／Diagnostics／Raw Errorを露出しない
- [ ] Classic／FrankenPHP Worker Modeが同一Handler GraphとResponse Contractを使う
- [ ] PostgreSQL Schema、Public PHP API、Frontend、Quickstart、Websiteを変更しない
- [ ] Required PHP／PostgreSQL Quality Gateが成功する
- [ ] WorkerはCommitしていない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Http \
  tests/Internal/Http \
  tests/Internal/Runtime \
  tests/Internal/Application/ApplicationHttpRuntimeComposerTest.php \
  tests/Internal/Application/ApplicationHttpRequestHandlerTest.php \
  tests/Internal/Console/CompileBuildArtifactsCommandTest.php \
  tests/Internal/Console/ApplicationBuildCompileCommandTest.php \
  tests/Status \
  tests/Internal/Status \
  tests/Transport/PostgreSql/PostgreSqlStatusReaderTest.php \
  tests/Transport/PostgreSql/PostgreSqlStatusQueryIntegrationTest.php
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
! rg -n '#\[PublicApi\]' src/Internal/Http src/Http/Status
! rg -n 'reason_message|purged_by|encoded_payload|encoded_context' src/Http/Status src/Internal/Http --glob '*.php'
! git diff -- src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php src/Transport/PostgreSql/PostgreSqlJournalSchema.php | rg '^\+.*(CREATE|ALTER|DROP|INDEX|CONSTRAINT|COLUMN)'
git diff --check
```

新規Test File名が責務分割で異なる場合は、実在するP16-004対象Testをすべて指定して同等以上の範囲を実行する。Classic／Worker ModeのEvidenceは同じApplication Compositionを使うことと、Request間CleanupをTest／Reportへ記録する。

## Expected Report

`develop/orchestration/reports/P16-004-http-status-resource.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Request Order and Authentication Evidence
- Reserved Route Collision Matrix
- HTTP Status／Body／Header Matrix
- State Resource Shape Matrix
- Authorizer Composition and Default-deny Evidence
- Classic／Worker-mode Evidence
- Sensitive and Safe Failure Evidence
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
