# D095: Phase 12 Middleware and Authorization Runtime

Status: Proposed

## Context

Phase 12はPSR-15 HTTP Middleware、入口に依存しないOperation Middleware、Authentication、ActorContext、`#[Authorize]`、Deferred再認可をRuntimeへ実装する。

D010とSpec 06は基本方針を定めているが、その後にPublic APIと利用者体験が変化した。

- D011はCommand／Queryや入口別marker interfaceを公式Structureへ導入せず、一つのOperationとConfig／AttributeでMetadataを構築すると決めた
- D071／D074はSelf-handled Operationの`handle(OperationValue, ?ExecutionContext): Outcome`を標準とした
- D075は正常時にNative Outcomeだけを返し、予期された拒否は`OperationRejectedException`でFramework Lifecycleへ参加させると決めた
- D010には利用者が`HttpOperation`／`ConsoleOperation`を実装し、Operation Middlewareが`OperationResult::rejected()`を返す旧前提が残る

Current RuntimeにはPSR-15 Pipeline、Operation Middleware Metadata、Actor Type、Authorization Policyはまだない。`ExecutionContext`はOperation／Attempt／Correlation／Causation／Deadlineだけを保持し、Deferred CodecもActorをEncodeしない。HTTP Handler、Inline Dispatcher、Deferred Acceptor、Worker RuntimeはContextへActorを受け渡す経路を持たない。

ServiceProviderとCompiled Containerは実装済みであり、Application所有のAuthenticator、Policy、Actor Repository、MiddlewareをConstructor Injectionする基盤に利用できる。

## Question 1: Phase 12の入口とOperation Model

Phase 12でどのAdapter Middlewareまで実装し、Operationを入口別に分類するか。

### Options

- A: PSR-15 `HttpMiddleware`と入口共通`OperationMiddleware`を実装する。Operationは一種類のままとし、`#[Route]`等のMetadataでHTTP入口に接続する。Console Adapter MiddlewareはConsole Operation入口を実装する将来Phaseへ延期する
- B: HTTP／Console Adapter Middlewareを同時実装し、`HttpOperation`／`ConsoleOperation`のmarker interfaceを追加する
- C: PSR-15 HTTP Middlewareだけを実装し、Operation MiddlewareとAuthorizationは後続に分ける

### Recommendation

Aを推奨する。

Current Operation Authoringと「HTTPもConsoleもJobもOperation」という中心Modelを維持し、入口別markerとディレクトリ強制を戻さない。Phase 12 Roadmapの必須AdapterはHTTPであり、ConsoleにはまだApplication Operation入口自体がない。

[ANSWER]

[/ANSWER]

## Question 2: Operation MiddlewareのPublic API

Typed Self-handled OperationとNative Outcomeに合わせ、Middlewareが何を受け取り何を返すか。

### Options

- A: Publicな読み取り専用`OperationInvocation`と`OperationMiddlewareHandler`を追加し、`process(OperationInvocation $invocation, OperationMiddlewareHandler $next): Outcome`とする。InvocationはOperation、Value、ExecutionContext、Strategy、Dispatch／Execution Phaseを提供する。拒否は`OperationRejectedException`、予期外例外はSupervisionへ渡す
- B: D010の旧案どおり`OperationEnvelope`を受け、`OperationResult`を返す
- C: 戻り値を持たないBefore／After Hookへ分ける

### Recommendation

Aを推奨する。

Handlerと同じ「正常系はOutcome、予期された拒否はFramework例外」に統一できる。`OperationResult`はLifecycle実装のInternal Normalizationに残しても、新しい利用者APIでは意識させない。

[ANSWER]

[/ANSWER]

## Question 3: Middlewareの登録、除外、順序

GlobalとOperation単位のPipelineをどう固定するか。

### Options

- A: `config/middleware.php`でGlobal HTTP／Dispatch／Execution Middlewareを登録し、Operationの`#[UseMiddleware(...)]`／`#[WithoutMiddleware(...)]`で差分を宣言する。順序はClass単位の`before`／`after`依存と安定した登録順でBuild時に解決し、未登録、重複、循環をCompile Errorにする。Framework Security StageはOperationから除外できない
- B: Middlewareごとに数値Priorityを付け、数字のみでソートする
- C: Operationごとの完全なMiddleware配列をRuntime Configで組み立て、Build時検証は行わない

### Recommendation

Aを推奨する。

数値Priorityは間の値と不可視な順序依存を生む。Class間依存なら「Authenticationの後」「Transactionの前」を意図として表現でき、ManifestとCIでDriftを防げる。

[ANSWER]

[/ANSWER]

## Question 4: AuthenticationのFramework／Application責任境界

Session、JWT、API Key、External IdPをFrameworkとApplicationのどちらが実装するか。

### Options

- A: Frameworkは`HttpAuthenticator` Contractと中立なPSR-15 `AuthenticationMiddleware`を提供し、ApplicationがCredential解析／検証を実装する。MiddlewareはCredentialをRequest外へ持ち出さずActorだけをTyped Request AttributeでFrameworkへ渡す。Credential不在はAnonymousのまま通過、不正CredentialはOperation生成前の401、`#[Authorize]`付きOperationのAnonymous拒否はOperation ID／Journal付き401、認証済みActorの権限不足は403とする
- B: FrameworkはActorの受け取り口だけを提供し、Authentication MiddlewareはApplicationがすべて実装する
- C: FrameworkがSession／JWT／API Keyの具体実装まで標準提供する

### Recommendation

Aを推奨する。

Security SchemeをFrameworkへ固定せず、CredentialをExecutionContext／Journal／Deferred Transportへ流さないInvariantとHTTP統合はFramework側で一度実装できる。

[ANSWER]

[/ANSWER]

## Question 5: Durable Actor Model

Inline受付からDeferred Workerまで、何をExecutionContextへ保存するか。

### Options

- A: Publicな`ActorRef(id, type)`と`ActorContext(origin, authorization, execution)`を追加し、Durable ContextにはID／Typeだけを保存する。PolicyはDIされたApplication Serviceから必要な最新情報を読む。Workerはorigin／authorizationを維持し、executionだけをSystem Actorにする
- B: Actor ID／Typeに加え、Role／Permission／JWT Claimを受付時のままDeferred Contextへ保存する
- C: Actorを一つだけ保持し、origin／authorization／executionを区別しない

### Recommendation

Aを推奨する。

Credentialや古いPermission SnapshotをDurable Dataにせず、「誰が原因か」「誰の権限を判定するか」「何が実行したか」を監査で分離できる。

[ANSWER]

[/ANSWER]

## Question 6: Deferred再認可の拒否と障害

Worker実行時にAuthorization Actorが消失／権限喪失した場合と、Actor／Policy Backend自体が障害の場合をどう分けるか。

### Options

- A: Actor不在／無効はUnauthorized、認証済みActorの権限不足はForbiddenのTerminal Rejectedとする。Actor Resolver／Policy Backendの接続障害やTimeoutはAttempt FailureとしてSupervisionのRetry／Backoff／Dead Letterへ渡す
- B: Actor不在、権限不足、Backend障害をすべてTerminal Rejectedにする
- C: Actor不在、権限不足、Backend障害をすべてRetry対象にする

### Recommendation

Aを推奨する。

確定したSecurity Decisionと一時的なInfrastructure Failureを分離できる。権限剥奪をRetryし続けず、障害中のBackendを「権限なし」と誤判定しない。

[ANSWER]

[/ANSWER]

## Proposed Consequences

上記をA／A／A／A／A／Aで確定する場合、D010とSpec 06は次のように更新する。

- `HttpOperation`／`ConsoleOperation`の利用者marker前提を削除し、Operation＋Attribute Metadataを維持する
- HTTPはPSR-15、OperationはNative OutcomeとFramework Rejection Exceptionに合わせた二つの玉ねぎPipelineとする
- `config/middleware.php`とOperation Attributeから決定的PipelineをCompileする
- FrameworkはAuthentication IntegrationとActor／Policy Contractを所有し、Credential SchemeとDomain Permission LookupはApplicationが所有する
- Actor ID／TypeだけをDeferred ContextとCanonical JournalのSensitive Boundary内で保持する
- Deferredは受付時とWorker実行時に同じPolicyを評価し、Security DenialとInfrastructure FailureをLifecycleで分離する

## References

- [D009 ExecutionContext](009-execution-context.md)
- [D010 Authentication and Middleware](010-authentication-and-middleware.md)
- [D011 Project Structure](011-project-structure.md)
- [D071 Operation Authoring and Discovery](071-operation-authoring-and-discovery.md)
- [D074 Typed Self-handled Operation Signature](074-typed-self-handled-operation-signature.md)
- [D075 Native Outcome and Rejection Exception](075-native-outcome-and-rejection-exception.md)
- [D093 Post Phase 10 Roadmap](093-post-phase-10-roadmap.md)
- [Authentication and Middleware Specification](../spec/06-auth-and-middleware.md)
- [ExecutionContext API](../spec/19-execution-context-api.md)
- [Post Phase 10 Roadmap](../spec/60-post-phase-10-roadmap.md)
