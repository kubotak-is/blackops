# SecurityとSensitive Data

BlackOpsはOperation Lifecycleを追跡し、Observed Sinkへ出すSensitive値を制御する境界を提供します。一方、Application全体のSecurity Policyは決めません。Frameworkが提供する保護と、Application／運用が実装する保護を分けて設計してください。

[Projection](glossary.md#projection)はCanonical Dataから用途に必要なFieldだけを選び、Mask／Exclude／Hashを適用した表現です。`#[Sensitive]`はObserved Journal Projectionを指定します。

## 責任分界

| 領域 | Frameworkが提供する境界 | Application／運用の責務 |
| --- | --- | --- |
| Typed Input | `OperationValue`とBinding Metadataを検証する | 業務Validation、入力Size制限、Content Policyを実装する |
| Frontend Contract | HTTP Operationの入力名／型、Request Binding、Typed Resultを生成し、Sensitive実値をArtifact／Resultへ含めない | Credential注入、Authentication／Authorization、CORS／CSRF、Browser Storage、生成物の配布範囲を管理する |
| Sensitive Projection | `#[Sensitive]`に従いObserved JournalでOmit／Mask／Hashする | 対象PropertyとModeを選び、Raw値を独自Logへ出さない |
| Lifecycle Journal | Event、Sequence、Operation／Attempt MetadataのShapeを提供する | Sinkの保存先、閲覧権限、監査、可用性を構成する |
| Public／Internal API | `#[PublicApi]`付き型とInternal Namespaceを区別する | Public APIだけへ依存し、Upgrade時に互換性を確認する |
| Deferred Claim | Lease、Heartbeat、FencingでStale Claimの確定を拒否する | 外部副作用の冪等性、Downstreamの重複防止を設計する |
| Authentication | PSR-15統合、三状態Result、Actorだけを渡す境界、Invalid Credentialの安全な401を提供する | Session／Bearer Token／API Key／External IdPの解析と検証を実装する |
| Console Operation | `#[ConsoleCommand]`を明示したScalar入力だけを公開し、CLI値とThrowable Detailを出力しない。Execution Actorを固定する | `ConsoleActorProvider`で安全なActor参照だけを返し、OS／運用側でCommand実行権限を制御する |
| Authorization | `#[Authorize]`、Policy Contract、型付きRequest／Decision、Build時DI登録を提供する | Operation、Resource、TenantごとのPolicyと現在権限の検索を実装する |
| Status Authorization | Subjectを最小情報へ投影し、Allow前にOutcome／Journal Detailを読まず、Unknown／Denyを同じ404へする | `OperationStatusAuthorizer`をBindingし、Current Actor、Origin Actor、Tenant／Resource Policyを評価する |
| Tenant Isolation | 提供しない | Query、Credential、Schema／Database、Cache、LogでTenantを分離する |
| Transport Security | HTTP Adapter境界を提供する | TLS終端、Certificate、Network Policyを構成する |
| 保存時暗号化 | 提供しない | Canonical Journal、Transport Payload、Outcome、Backupを暗号化する |
| Key管理 | 提供しない | KMS／HSM、権限、Rotation、失効手順を運用する |
| Sink Access Control | Journal Observer Contractを提供する | JSONL、Log Backend、Database、Object Storageの権限を制限する |
| Backup／Restore | 提供しない | 暗号化Backup、Restore Test、破棄手順を運用する |
| Retention | 対象別Period、Hold、Plan、Purge、AuditのPrimitiveを提供する | 保持期間、Legal Hold Policy、承認、監査保管を決める |
| Credential Rotation | 提供しない | Database、API、Cloud Credentialを安全に更新する |
| Diagnostics | Safe Projection、Mask済みActor、安定Failure Classificationを提供する | Canonical StoreとLogの閲覧権限、Retention、Incident手順を管理する |

## `#[Sensitive]`が行うこと

Value PropertyへAttributeを付けます。

```php
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;

public function __construct(
    #[Sensitive(SensitiveMode::Mask)]
    public string $recipientEmail,
) {}
```

| Mode | Observed Projection |
| --- | --- |
| `SensitiveMode::Omit` | Fieldを出力しない |
| `SensitiveMode::Mask` | 値を`[masked]`へ置き換える |
| `SensitiveMode::Hash` | 値そのものではなく一方向のDigestを出力する |

Hashは同一値の相関が必要な場合だけ使います。低Entropy値は推測攻撃の対象になり得るため、Hashを暗号化やTokenizationとして扱いません。

## `#[Sensitive]`が行わないこと

`#[Sensitive]`は認証、認可、暗号化、Access Control、Retentionを代替しません。具体的には次を置き換えません。

- Authentication／Authorization
- Tenant Isolation
- TLS
- Canonical Store／Databaseの暗号化
- Encryption Key管理
- Journal／Log SinkのAccess Control
- Backup暗号化
- Retention Period／Legal Hold
- Credential Rotation

Observed JSONLでMaskできても、Canonical JournalやTransport Payloadには再現に必要な値が残る場合があります。保存先の暗号化、最小権限、保持期間、削除手順を必ず構成してください。

Actorも同じ責任分界に従います。Canonical Journalは監査正本としてorigin／authorization／execution ActorのIDとTypeを保持します。Observed JournalとJSONLではActor Typeとnull関係を維持しながら、すべてのActor IDを`[masked]`へ置き換えます。Role、Permission、Credential、Token、Session、ClaimはCanonical／Observedのどちらにも保存しません。

## Frontend Operation Contractの境界

`#[Route]`を持つOperationはRepository `main`のFrontend Contractへ含まれます。SensitiveなOperationValue PropertyもRequest送信に必要なWrite-only Inputとして名前と型を生成しますが、Constructor Default、実値、Example、Fixture、Log HelperはFrontend Contract ManifestとGenerated Treeへ入れません。OperationValueを成功Outcomeへ混ぜず、Sensitive OutcomeはBuild Errorにします。

Generated `.fetch()`はHTTP Responseを検証し、Validation／Internal／Transport ResultへRaw Body、Credential、Thrown Error Message、Stack Traceを残しません。Operation IDはServer Responseに存在するときだけ保持します。Generated Tree、Typed Result、Observed LogのどこにもSensitive実値を置かないことをApplicationのConsumer E2Eでも確認してください。

Generated Typeは認証、認可、暗号化、Access Control、Retentionを代替しません。Server-only `createBlackOpsClient()`へRequestごとのSession／TokenからDefault HeaderをBindingし、Browser向けGlobal Mutable Clientへ保存しないでください。FactoryとCall HeaderはCopy／Freezeされ、Case-insensitiveにMergeされます。Operation由来Header、Generated `Content-Type`、専用Optionから作る`Idempotency-Key`は任意Headerで上書きできません。

ApplicationはCORS、CSRF、TLS、Browser Storage、Token Rotation、Frontend Source Map／Build Artifactの公開範囲を管理します。`config/frontend.php`へCredentialやBase URLを保存しません。不正なBase URL、Fetch、Header、Credential、Idempotency KeyはNetwork Call前に`invalid_client_options`へ丸め、値やThrown ErrorをResultへ含めません。Backend側のIdempotency保存／重複抑止はPhase 19まで保証されません。

## Status参照の認可

`GET /operations/{operationId}`はGlobal MiddlewareとAuthenticationの内側で動きますが、Operation実行時の`#[Authorize]`とは別にStatus参照を認可します。Applicationは`OperationStatusAuthorizer`を`ServiceProvider`からBindingしてください。未登録時はFrameworkが常にDenyし、Status Detailを読みません。

`OperationStatusAuthorizationRequest`が渡すのはOperation ID、Operation Type、Current Actor、受付時のOrigin Actorだけです。Credential、Role、Token、Payload、Outcome、Journal DetailをRequestへ追加しません。Operation IDは相関KeyでありSecretではありませんが、知っているだけでは参照権限を得ません。

Quickstartの`SampleOperationStatusAuthorizer`は、両Actorが存在し、どちらも`user`で、ID／Typeが完全一致するときだけAllowします。これは単一UserのLocal Exampleです。ProductionではApplicationがTenant、Resource、Role、Delegationを含むPolicyへ置き換えてください。

| Request | Public Response | 理由 |
| --- | --- | --- |
| 不正Credential | 401 | Subjectを読む前にAuthentication Middlewareが停止する |
| Anonymous、Unknown ID、Deny | 404 `operation_unavailable` | 存在と認可結果を区別させない |
| Allow済み、Detailあり | 200 | 7 Stateのいずれかを返す |
| Allow済み、Retention期限切れを証明 | 410 `operation_expired` | 認可後だけ期限切れを明かす |

すべてのStatus Responseは`Cache-Control: private, no-store`を持ち、Non-terminalだけに正整数`Retry-After`が付きます。Completed OutcomeはPublic Propertyだけを返します。Sensitive Input、Credential、Actor ID、Raw Error、Canonical Journal Detailは返しません。

## HTTP Authenticationの境界

Applicationは`HttpAuthenticator`を実装し、Credentialなしを`AuthenticationResult::anonymous()`、有効なCredentialを`authenticated(new ActorRef($id, $type))`、不正Credentialを`invalid('authentication.invalid')`として返します。具体的なSession／JWT／API Key Libraryと検証PolicyはApplicationが選びます。

Frameworkの`AuthenticationMiddleware`はCredential自体をResult、Request Attribute、ExecutionContext、Journalへコピーしません。Authenticated時に渡すのはID／Typeだけの`ActorRef`です。Invalid時はOperation IDを発行せず、安定Codeだけを含む401 JSONを返します。AuthenticatorのBackend障害はInvalidへ丸めず、上位のHTTP Error境界へ伝播します。

Authenticated Resultの`ActorRef`は予約Request Attributeを経由し、Operationの`ActorContext`へ接続されます。HTTP入口では同じ参照がorigin／authorization／execution Actorになります。Anonymous RequestにはActorContextを追加しません。`config/middleware.php`へAuthentication Middlewareを登録しても認可Policyは自動では決まらないため、Operation単位で`#[Authorize]`を宣言してください。

Quickstartの`X-Sample-Token`はLocal Development用の最小Exampleです。AuthenticatorはExpected TokenをApplication Runtime構成時に一度だけSnapshotし、比較に`hash_equals()`を使います。`SAMPLE_API_TOKEN`の未設定、空文字、空白だけの値は構成ErrorとしてFail-closedにし、既知TokenへFallbackしません。Header値はOperation ValueへBindせず、Response、ExecutionContext、Transport、Journal、Outcomeへ保存しません。ProductionではApplicationがSession、Bearer Token、External IdP等とSecret管理へ置き換えてください。

Header欠落と不正Headerは同じ401でも境界が異なります。

| Request | Authentication | Operation Lifecycle | Response |
| --- | --- | --- | --- |
| Header欠落 | Anonymousとして通過 | `#[Authorize]`がRejectedを記録 | Operation ID付き401 |
| Header不一致 | Invalidとして停止 | Operationを受け付けずJournalなし | Operation IDなし401 |
| Header一致 | `ActorRef`だけを追加 | Policy評価後にHandler／Deferred受付へ進む | Inline 200／Deferred 202 |

## Operation Authorizationの責任境界

認可が必要なOperationには`#[Authorize(ApplicationPolicy::class)]`を一度だけ付けます。Policyは`AuthorizationRequest`からOperation、Value、ExecutionContext、非nullのAuthorization Actorを読み、`AuthorizationDecision::allow()`、`unauthorized($code)`、`forbid($code)`のいずれかを返します。

Policy ClassはBuild時にCompiled ContainerへAutowired登録されます。RepositoryやPermission Service等のInterface BindingはApplicationのService Providerへ登録してください。PolicyはCredential、Token、Session、Role／Permission Snapshot、Backend例外をRequestやDecisionへ保存しません。現在のRole、Permission、Resource状態はDIしたApplication Serviceから評価します。

FrameworkはPolicy評価を固定Lifecycle Stageとして実行します。Inlineでは`operation.received`、`attempt.started`の後、Handler解決／実行前に評価します。Deferred受付では`operation.received`の後、Transport Enqueue前に評価します。拒否時は次のSequenceへ`operation.rejected`を記録し、Handler／Enqueueへ進みません。

ActorがないPolicy付きOperationはPolicyを呼ばず、`authorization.authentication_required`でUnauthorizedになります。Policyが返したUnauthorized／ForbiddenはOperation ID付きの401／403 JSONへ変換されます。ResponseとJournalへ出すCodeには外部公開可能な安定Codeだけを使ってください。

Policy BackendのTimeout、接続障害、Policy解決／構築失敗は拒否Decisionへ丸めません。Frameworkは元の例外をRuntime Error境界へ渡し、401／403として扱いません。Credential、Role、Permission SnapshotもExecutionContext、Result、Journalへ追加しません。

## Deferred Workerでの再認可

Deferred Operationは受付時だけでなく、各Worker Attemptでも同じPolicyを評価します。Workerは`attempt.started`を記録した後、Handlerを呼ぶ直前に、Transportから復元した最新のValueとExecutionContextをPolicyへ渡します。PolicyはDIしたRepository等から現在の権限やResource状態を取得してください。RetryではPolicyを再評価するため、受付後に失効した権限をそのまま使い続けません。

Worker AttemptのActor Contextは、受付時のorigin／authorizationを維持し、executionだけを`execution.worker.id`／`system`へ置き換えます。Policyへ渡すActorはauthorization Actorです。Worker System Actorを代わりに使って権限を強化することはありません。

認可結果と障害は次のように分離します。

| 状況 | Lifecycle | Handler | Retry |
| --- | --- | --- | --- |
| Policyなし | 通常実行 | 実行する | Handler結果に従う |
| authorization Actorなし | `operation.rejected` | 実行しない | しない |
| Unauthorized／Forbidden | `operation.rejected` | 実行しない | しない |
| Policy解決／構築／実行の予期しない例外 | `attempt.failed`後にSupervisionへ渡す | 実行しない | Supervision Policyに従う |

RetryableなPolicy Backend障害はBackoff後の次Attemptで再評価されます。Fail／Dead Letterへ到達した場合も、FailureのException Class／Messageと、受付Actor／Worker execution Actorの分離をCanonical Journalへ維持します。Credential、Role、Permission、ClaimのSnapshotはTransportやJournalへ保存しません。

## Operation受理前のError

Console OperationはCredential、Actor ID、Secret入力をOptionとして受け取りません。`#[Sensitive]`がValueまたはOutcomeの到達可能PropertyにあるOperationはConsole公開できません。`--json`とHuman Outputはいずれも安定Code、Field、Rule、Operation ID等のSafe Fieldだけを表示し、入力値、Exception Message、Path、SQLを反射しません。

Route不一致、壊れたJSON、必要Header欠落等はOperation受理前のProtocol Errorです。Operation IDやLifecycle Journalはまだ存在しません。Reverse Proxy／HTTP AdapterのAccess LogとError Responseを安全に構成し、Request BodyやAuthorization Headerを無条件に記録しないでください。

## Canonical DataとSafe Diagnostics

Canonical Journal、Deferred Transport Payload、Outcome Storeは再現性のためRaw Value、Raw Actor ID、Exception Messageを含み得るRestricted Dataです。Databaseへの最小権限、保存時暗号化、Backup、Retention、Purge AuditはApplication／運用が設計します。

HTTP Error、Application／Framework JSONL Log、Observed Journal、`operation:inspect`、Local ViewerはSafe Diagnostics Surfaceです。ここではCredentialを除外し、`#[Sensitive]`を適用し、Actor IDを`[masked]`へ置き換え、Exception MessageではなくFailure Type／Classificationだけを示します。Raw表示に切り替えるCLI Optionはありません。

Local Viewerは既定無効、明示起動、Loopback限定、起動ごとのRandom Bootstrap Token、Session Cookie、Read-only GET／HEAD、`Cache-Control: no-store`を組み合わせます。TokenをShell History、Chat、Ticket、共有Logへ貼らず、調査後はViewer Processを終了してください。このLocal GateはProductionのAuthentication／AuthorizationやRemote Support UIの代替ではありません。

## Production Check

- Authentication／AuthorizationをOperation入口へ適用する
- Tenant境界をDatabase、Cache、Log、Outcome取得で確認する
- TLSとNetwork Policyを構成する
- Canonical Data、Backup、Credentialを暗号化する
- Sinkごとに最小権限と監査を設定する
- Workerの外部副作用を冪等にする
- Retention Period、Legal Hold、Purge承認を文書化する
- Credential RotationとIncident Responseを検証する

既知の提供範囲は[Current Status](mvp-status.md)、設定は[Configuration Reference](configuration.md)を確認してください。
