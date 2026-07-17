# SecurityとSensitive Data

BlackOpsはOperation Lifecycleを追跡し、Observed Sinkへ出すSensitive値を制御する境界を提供します。一方、Application全体のSecurity Policyは決めません。Frameworkが提供する保護と、Application／運用が実装する保護を分けて設計してください。

[Projection](glossary.md#projection)はCanonical Dataから用途に必要なFieldだけを選び、Mask／Exclude／Hashを適用した表現です。`#[Sensitive]`はObserved Journal Projectionを指定します。

## 責任分界

| 領域 | Frameworkが提供する境界 | Application／運用の責務 |
| --- | --- | --- |
| Typed Input | `OperationValue`とBinding Metadataを検証する | 業務Validation、入力Size制限、Content Policyを実装する |
| Sensitive Projection | `#[Sensitive]`に従いObserved JournalでOmit／Mask／Hashする | 対象PropertyとModeを選び、Raw値を独自Logへ出さない |
| Lifecycle Journal | Event、Sequence、Operation／Attempt MetadataのShapeを提供する | Sinkの保存先、閲覧権限、監査、可用性を構成する |
| Public／Internal API | `#[PublicApi]`付き型とInternal Namespaceを区別する | Public APIだけへ依存し、Upgrade時に互換性を確認する |
| Deferred Claim | Lease、Heartbeat、FencingでStale Claimの確定を拒否する | 外部副作用の冪等性、Downstreamの重複防止を設計する |
| Authentication | PSR-15統合、三状態Result、Actorだけを渡す境界、Invalid Credentialの安全な401を提供する | Session／Bearer Token／API Key／External IdPの解析と検証を実装する |
| Authorization | `#[Authorize]`、Policy Contract、型付きRequest／Decision、Build時DI登録を提供する | Operation、Resource、TenantごとのPolicyと現在権限の検索を実装する |
| Tenant Isolation | 提供しない | Query、Credential、Schema／Database、Cache、LogでTenantを分離する |
| Transport Security | HTTP Adapter境界を提供する | TLS終端、Certificate、Network Policyを構成する |
| 保存時暗号化 | 提供しない | Canonical Journal、Transport Payload、Outcome、Backupを暗号化する |
| Key管理 | 提供しない | KMS／HSM、権限、Rotation、失効手順を運用する |
| Sink Access Control | Journal Observer Contractを提供する | JSONL、Log Backend、Database、Object Storageの権限を制限する |
| Backup／Restore | 提供しない | 暗号化Backup、Restore Test、破棄手順を運用する |
| Retention | 対象別Period、Hold、Plan、Purge、AuditのPrimitiveを提供する | 保持期間、Legal Hold Policy、承認、監査保管を決める |
| Credential Rotation | 提供しない | Database、API、Cloud Credentialを安全に更新する |

## `#[Sensitive]`が行うこと

Value PropertyへAttributeを付けます。

```php
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;

public function __construct(
    #[Sensitive(SensitiveMode::Mask)]
    public string $apiToken,
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

## HTTP Authenticationの境界

Applicationは`HttpAuthenticator`を実装し、Credentialなしを`AuthenticationResult::anonymous()`、有効なCredentialを`authenticated(new ActorRef($id, $type))`、不正Credentialを`invalid('authentication.invalid')`として返します。具体的なSession／JWT／API Key Libraryと検証PolicyはApplicationが選びます。

Frameworkの`AuthenticationMiddleware`はCredential自体をResult、Request Attribute、ExecutionContext、Journalへコピーしません。Authenticated時に渡すのはID／Typeだけの`ActorRef`です。Invalid時はOperation IDを発行せず、安定Codeだけを含む401 JSONを返します。AuthenticatorのBackend障害はInvalidへ丸めず、上位のHTTP Error境界へ伝播します。

Authenticated Resultの`ActorRef`は予約Request Attributeを経由し、Operationの`ActorContext`へ接続されます。HTTP入口では同じ参照がorigin／authorization／execution Actorになります。Anonymous RequestにはActorContextを追加しません。`config/middleware.php`へAuthentication Middlewareを登録しても認可Policyは自動では決まらないため、Operation単位で`#[Authorize]`を宣言してください。Deferred Worker実行時の再認可とSystem Actorへの置換は現在の提供範囲に含まれません。

## Operation Authorizationの責任境界

認可が必要なOperationには`#[Authorize(ApplicationPolicy::class)]`を一度だけ付けます。Policyは`AuthorizationRequest`からOperation、Value、ExecutionContext、非nullのAuthorization Actorを読み、`AuthorizationDecision::allow()`、`unauthorized($code)`、`forbid($code)`のいずれかを返します。

Policy ClassはBuild時にCompiled ContainerへAutowired登録されます。RepositoryやPermission Service等のInterface BindingはApplicationのService Providerへ登録してください。PolicyはCredential、Token、Session、Role／Permission Snapshot、Backend例外をRequestやDecisionへ保存しません。現在のRole、Permission、Resource状態はDIしたApplication Serviceから評価します。

FrameworkはPolicy評価を固定Lifecycle Stageとして実行します。Inlineでは`operation.received`、`attempt.started`の後、Handler解決／実行前に評価します。Deferred受付では`operation.received`の後、Transport Enqueue前に評価します。拒否時は次のSequenceへ`operation.rejected`を記録し、Handler／Enqueueへ進みません。

ActorがないPolicy付きOperationはPolicyを呼ばず、`authorization.authentication_required`でUnauthorizedになります。Policyが返したUnauthorized／ForbiddenはOperation ID付きの401／403 JSONへ変換されます。ResponseとJournalへ出すCodeには外部公開可能な安定Codeだけを使ってください。

Policy BackendのTimeout、接続障害、Policy解決／構築失敗は拒否Decisionへ丸めません。Frameworkは元の例外をRuntime Error境界へ渡し、401／403として扱いません。Credential、Role、Permission SnapshotもExecutionContext、Result、Journalへ追加しません。

## Operation受理前のError

Route不一致、壊れたJSON、必要Header欠落等はOperation受理前のProtocol Errorです。Operation IDやLifecycle Journalはまだ存在しません。Reverse Proxy／HTTP AdapterのAccess LogとError Responseを安全に構成し、Request BodyやAuthorization Headerを無条件に記録しないでください。

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
