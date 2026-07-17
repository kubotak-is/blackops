# Core API Types Reference

このReferenceは現在の`main` Sourceで`#[PublicApi]`を持つ129型を一覧化しています。Application Authorはまず「Application構成」「Database」「Operation Authoring」「Validation」「Outcome取得」の型を使い、Transport、Journal、Retention等のPortはAdapterを拡張するときだけ使ってください。

`BlackOps\Core\Attribute\PublicApi` marker自身は利用者向けAPIではないため一覧へ含めません。内部実装Namespaceと`#[PublicApi]`を持たない実装型にも依存しないでください。Attributeの付与対象と標準形は[Attributes Reference](attributes.md)を確認してください。

## Application構成

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Application\Application` | final readonly class | HTTP／Console Processの共通Application | `Application::configure()`からBootstrapする |
| `BlackOps\Application\ApplicationBuilder` | final class | Environment、Config、Providerを組み立てる | `withEnvironment()`、`withConfiguration()`、`create()`を呼ぶ |
| `BlackOps\Application\ConsoleKernel` | final readonly class | Project CLIを実行する | Project Rootの`blackops`から`run()`を呼ぶ |
| `BlackOps\Application\ApplicationBootstrapException` | exception class | Public Bootstrapの失敗を通知する | Entrypointで安全な起動Errorとして扱う |

## Database

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Database\DatabaseManager` | interface | DefaultまたはNamed DBAL Connectionを選択する | 複数Connectionが必要なServiceへConstructor Injectionする |

Default Connectionだけを使うRepositoryは`Doctrine\DBAL\Connection`を直接Constructor Injectionできます。Named ConnectionはContainerやGlobal Helperではなく`DatabaseManager::connection($name)`で選択します。

## Operation Authoringと実行Context

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Core\Operation` | marker interface | Operation Definitionを示す | Typed Self-handled Classで実装する |
| `BlackOps\Core\OperationValue` | marker interface | 型付きOperation Inputを示す | Value DTOで実装する |
| `BlackOps\Core\Outcome` | marker interface | 正常完了の型付きOutputを示す | Outcome DTOで実装する |
| `BlackOps\Core\EmptyOutcome` | final readonly class | `void`成功を型付きOutcomeへ正規化する | 値のない成功を読む |
| `BlackOps\Core\ExecutionContext` | final readonly class | Operation ID、Correlation、Causation、Attempt、Actorを保持する | 必要なOperationだけ`handle()`第二引数で受ける |
| `BlackOps\Core\AttemptContext` | final readonly class | 現在のDeferred Attempt情報を保持する | `ExecutionContext::attempt()`から読む |
| `BlackOps\Core\ActorRef` | final readonly class | ActorのIDとTypeだけを保持する | Credentialを含めず主体を参照する |
| `BlackOps\Core\ActorContext` | final readonly class | Origin、Authorization、Execution Actorを区別する | `ExecutionContext::actorContext()`から読む |
| `BlackOps\Core\OperationEnvelope` | final readonly class | Definition、Value、Context、Strategyをまとめる | Legacy Handler／低Level Dispatcher拡張で使う |
| `BlackOps\Core\OperationHandler` | interface | Separate／Legacy Handler Contract | Compatibility形のHandlerで実装する |
| `BlackOps\Core\OperationResult` | final readonly class | Completed／Rejected Resultと任意のOperation IDを保持する | 拒否時は`rejected($reason, $operationId)`で相関IDを残す |
| `BlackOps\Execution\Dispatcher` | interface | Operationを実行してResultを返す | 独自入口では任意の`ActorContext`を第三引数へ渡す |

## Operation Metadata Attributes

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Core\Attribute\OperationType` | attribute class | 永続Operation Type IDを宣言する | 全Operation Classへ付ける |
| `BlackOps\Core\Attribute\ExecuteWith` | attribute class | Execution Strategyを指定する | Deferred Operationへ付ける |
| `BlackOps\Core\Attribute\Authorize` | attribute class | OperationのAuthorization Policyを指定する | 認可が必要なOperationへ付ける |
| `BlackOps\Core\Attribute\Sensitive` | attribute class | Observed Projection Modeを指定する | SensitiveなValue Propertyへ付ける |
| `BlackOps\Core\Attribute\SensitiveMode` | enum | Omit／Mask／Hashを選ぶ | `#[Sensitive]`の引数に使う |
| `BlackOps\Core\Attribute\Accepts` | attribute class | Accepted Valueを明示する | Legacy／Separate互換形で使う |
| `BlackOps\Core\Attribute\Returns` | attribute class | Outcomeを明示する | Legacy／Separate互換形で使う |
| `BlackOps\Core\Attribute\HandledBy` | attribute class | Separate Handlerを指定する | 責務分離が必要な互換形で使う |

## HTTP Attributes

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Http\Attribute\Route` | attribute class | HTTP Method／PathをOperationへ結び付ける | HTTP公開するOperationへ付ける |
| `BlackOps\Http\Attribute\FromBody` | attribute class | JSON Body FieldをValueへBindする | Body Inputへ付ける |
| `BlackOps\Http\Attribute\FromHeader` | attribute class | HeaderをValueへBindする | Header Inputへ付ける |
| `BlackOps\Http\Attribute\FromPath` | attribute class | Path ParameterをValueへBindする | Path Inputへ付ける |
| `BlackOps\Http\Attribute\FromQuery` | attribute class | Query ParameterをValueへBindする | Query Inputへ付ける |

## HTTP Authentication

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Http\Authentication\HttpAuthenticator` | interface | HTTP CredentialをApplication Actorへ解決する | Application固有のSession／Token検証を実装する |
| `BlackOps\Http\Authentication\AuthenticationResult` | final readonly class | Anonymous／Authenticated／Invalidを表す | Authenticatorから安全なActorまたはCodeを返す |
| `BlackOps\Http\Authentication\AuthenticationMiddleware` | final readonly middleware | Authentication結果をPSR-15 Pipelineへ接続する | `config/middleware.php`へServiceとして登録する |

## Operation Authorization

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Core\Authorization\AuthorizationPolicy` | interface | Operation認可をApplicationへ委譲する | Policy Serviceで`decide()`を実装する |
| `BlackOps\Core\Authorization\AuthorizationRequest` | final readonly class | Operation、Value、Context、Authorization Actorを渡す | Policy内で現在権限の検索に使う |
| `BlackOps\Core\Authorization\AuthorizationDecision` | final readonly class | Allow／Unauthorized／Forbiddenを表す | 安定Code付きの認可判断を返す |

HTTP入口はAuthenticationで得た`ActorRef`から、同じ主体をorigin／authorization／executionへ設定した`ActorContext`を作ります。独自入口から`Dispatcher`を呼ぶ場合も、Credentialを含めず、このActor参照だけを任意の第三引数へ渡してください。従来の二引数呼び出しはAnonymous Contextとして動作します。

## Value Validation

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Core\Validation\Attribute\NotBlank` | attribute class | 空文字や空相当を拒否する | 必須のValue Propertyへ付ける |
| `BlackOps\Core\Validation\Attribute\Length` | attribute class | Stringの文字数を検証する | `min`／`max`を指定する |
| `BlackOps\Core\Validation\Attribute\Range` | attribute class | 数値そのものを検証する | `int`／`float` Propertyへ付ける |
| `BlackOps\Core\Validation\Attribute\Email` | attribute class | Email形式を検証する | Email Propertyへ付ける |
| `BlackOps\Core\Validation\Attribute\Regex` | attribute class | PCRE Patternとの一致を検証する | String Propertyへ付ける |
| `BlackOps\Core\Validation\Attribute\Count` | attribute class | Collectionの要素数を検証する | Array等のPropertyへ付ける |
| `BlackOps\Core\Validation\Attribute\Choice` | attribute class | Scalarの許可Listを検証する | 列挙候補を持つPropertyへ付ける |
| `BlackOps\Core\Validation\Violation` | final readonly value object | Field、Rule、安定Codeを保持する | Rejected Response／Journalで読む |

## Identifier

すべてのIdentifierはUUIDv7文字列を`fromString()`で検証し、`toString()`で取得します。同じUUID値でも型は相互に交換できません。

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Core\Identifier\OperationId` | final readonly value object | Operationを一意に識別する | Response、Journal、Outcomeを相関する |
| `BlackOps\Core\Identifier\AttemptId` | final readonly value object | 一回のHandler Attemptを識別する | Retryごとの実行を追跡する |
| `BlackOps\Core\Identifier\CorrelationId` | final readonly value object | 関連Operationを一つのTraceへまとめる | Root／子Operationを相関する |
| `BlackOps\Core\Identifier\CausationId` | final readonly value object | 原因となるOperationを示す | 子Operationの因果関係を表す |
| `BlackOps\Core\Identifier\JournalRecordId` | final readonly value object | Journal Recordを識別する | Journal AdapterでRecordを扱う |
| `BlackOps\Core\Identifier\RetentionHoldId` | final readonly value object | Retention Holdを識別する | Holdの作成／解除を相関する |
| `BlackOps\Core\Identifier\RetentionPurgeAuditId` | final readonly value object | Purge Auditを識別する | Database AuditとSystem Logを相関する |

## Execution StrategyとTransport Port

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Core\Execution\ExecutionStrategy` | marker interface | 実行経路を表す | Custom Strategyの型境界に使う |
| `BlackOps\Core\Execution\Inline` | final readonly class | Request内の同期実行を表す | 既定Strategyとして使う |
| `BlackOps\Core\Execution\Deferred` | final readonly class | Durable受付後のWorker実行を表す | `#[ExecuteWith(Deferred::class)]`で選ぶ |
| `BlackOps\Core\Execution\DeferredAcknowledgement` | final readonly value object | Deferred受付結果を保持する | Operation IDと受付時刻を読む |
| `BlackOps\Core\Execution\DeferredOperationMessage` | final readonly value object | Durable Transport Messageを保持する | Transport Adapterで送受信する |
| `BlackOps\Core\Execution\ClaimRequest` | final readonly value object | WorkerのClaim条件を表す | Receiverへ現在時刻等を渡す |
| `BlackOps\Core\Execution\OperationClaim` | final readonly value object | 取得したClaimとLease情報を保持する | Worker AdapterでAttemptを開始する |
| `BlackOps\Core\Execution\OperationSender` | interface | Deferred Message送信Port | Custom Transportの送信側を実装する |
| `BlackOps\Core\Execution\OperationReceiver` | interface | Deferred Claim取得Port | Custom TransportのWorker側を実装する |
| `BlackOps\Core\Execution\ClaimHeartbeat` | interface | Lease更新Port | Handler実行中のHeartbeatを実装する |
| `BlackOps\Core\Execution\ClaimSettlement` | interface | Claim成功／失敗確定Port | Fencing付きSettlementを実装する |
| `BlackOps\Core\Execution\ExecutionTransport` | aggregate interface | Sender／Receiver／Heartbeat／Settlementを束ねる | 一体型Transport Adapterを実装する |
| `BlackOps\Core\Exception\DeferredTransportException` | exception class | Deferred Transport失敗を通知する | Adapter Errorを境界Exceptionへ変換する |

## CodecとDependency Injection

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Core\Codec\OperationCodec` | interface | Operation Value／ContextをTransport形式へ変換する | Custom Codecを実装する |
| `BlackOps\Core\Codec\EncodedOperationMessage` | final readonly value object | Encode済みType／Value／Contextを保持する | Transport Payloadとして渡す |
| `BlackOps\Core\Codec\OperationCodecException` | exception class | Encode／Decode失敗を通知する | 不正Payloadを安全に拒否する |
| `BlackOps\Core\DependencyInjection\ServiceProvider` | interface | Application Service登録を定義する | Repository Interface等をBindingする |
| `BlackOps\Core\DependencyInjection\ServiceRegistry` | interface | Service Definition登録Port | Service Providerの`register()`で使う |

## Registry

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Core\Registry\OperationMetadata` | final readonly value object | Type、Value、Outcome、Handler、Strategyを保持する | Registry／Build Extensionで読む |
| `BlackOps\Core\Registry\OperationRegistry` | final readonly class | Compile済みOperation Metadataを検索する | Type IDまたはDefinitionで解決する |
| `BlackOps\Core\Registry\OperationProvider` | interface | Discovery外Operationを列挙する | Package／Generated Sourceを登録する |

## RejectionとSupervision

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Core\Exception\OperationRejectedException` | exception class | 予期された業務拒否を通知する | Category Factoryへ安定Codeを渡してthrowする |
| `BlackOps\Core\Rejection\RejectionCategory` | enum | 拒否Categoryを表す | Rejected Resultを分類する |
| `BlackOps\Core\Rejection\RejectionReason` | final readonly value object | Categoryと安定Codeを保持する | Rejection Response／Journalで読む |
| `BlackOps\Core\Supervision\RetryableException` | interface | Retry可能なThrowableを示す | 一時障害Exceptionで実装する |
| `BlackOps\Core\Supervision\SupervisionPolicy` | interface | FailureからActionを決める | Custom Retry Policyを実装する |
| `BlackOps\Core\Supervision\ExponentialBackoffSupervisionPolicy` | final readonly class | Exponential Backoffを提供する | 既定のRetry Policyとして構成する |
| `BlackOps\Core\Supervision\SupervisionAction` | enum | Retry／Fail／Dead Letter Actionを表す | Policy結果を分岐する |
| `BlackOps\Core\Supervision\SupervisionDecision` | final readonly value object | Action、Delay、Reasonを保持する | Worker RuntimeへPolicy判断を返す |
| `BlackOps\Core\Exception\InvalidIdentifierException` | exception class | 不正UUIDv7を通知する | Identifier Inputを400等へ変換する |

## Outcome Store

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Outcome\OutcomeReader` | interface | Operation IDからOutcomeを読む | ApplicationのStatus／Result入口へ注入する |
| `BlackOps\Outcome\OutcomeWriter` | interface | 完了Outcomeを保存する | Runtime／Store Adapterで実装する |
| `BlackOps\Outcome\OutcomeStore` | aggregate interface | ReaderとWriterを束ねる | 一体型Store Adapterで実装する |
| `BlackOps\Outcome\OutcomeRecord` | final readonly value object | Operation ID、Outcome、完了時刻を保持する | `OutcomeReader::find()`の結果を読む |
| `BlackOps\Outcome\Exception\OutcomeStoreException` | exception class | 保存、復元、Schema不整合を通知する | Store境界Errorとして扱う |

## Journal Core

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Journal\JournalEvent` | enum | Lifecycle Event名を定義する | RecordのEventを分岐する |
| `BlackOps\Journal\LifecycleState` | enum | Operation Lifecycle Stateを表す | Status Viewで現在Stateを示す |
| `BlackOps\Journal\JournalData` | marker interface | Event Data型を示す | Custom／Canonical Data境界で実装する |
| `BlackOps\Journal\EmptyJournalData` | final readonly class | DataなしEventを表す | Payloadを持たないRecordで使う |
| `BlackOps\Journal\JournalOperation` | final readonly value object | Journal内のOperation、相関ID、Actor Contextを保持する | Canonical RecordからID／TypeだけのActorを読む |
| `BlackOps\Journal\JournalAttempt` | final readonly value object | Journal内のAttempt Metadataを保持する | Attempt ID／番号／開始時刻を読む |
| `BlackOps\Journal\JournalRecord` | final readonly value object | Canonical Journal Recordを表す | Canonical Storeで読み書きする |
| `BlackOps\Journal\ObservedJournalRecord` | final readonly value object | Projection済みRecordを表す | Observer／Log Sinkへ渡す |
| `BlackOps\Journal\JournalDeliveryPolicy` | enum | Best effort／Required配送を選ぶ | Observer Pipelineを構成する |
| `BlackOps\Journal\JournalObserver` | interface | Projection済みRecord受信Port | Custom Log／Telemetry Sinkを実装する |
| `BlackOps\Journal\FlushableJournalObserver` | interface | Flush可能なObserver Contract | Bufferを持つSinkで実装する |
| `BlackOps\Journal\CanonicalJournalReader` | interface | Operation IDのCanonical Recordを読む | Status／監査Adapterで使う |
| `BlackOps\Journal\CanonicalJournalWriter` | interface | Canonical Recordを追記する | Durable Journal Adapterで実装する |
| `BlackOps\Journal\CanonicalJournalStore` | aggregate interface | ReaderとWriterを束ねる | 一体型Canonical Storeで実装する |
| `BlackOps\Logging\JsonlJournalRecordEncoder` | final readonly class | Observed RecordをJSONLへEncodeする | File／Stream Observerで使う |
| `BlackOps\Logging\JsonlJournalObserver` | final readonly class | Projection済みRecordをStreamへ追記する | Local JSONL Sinkを構成する |

## Journal Event Data

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Journal\Data\OperationReceivedData` | final readonly class | 受付Valueを保持する | `operation.received`を読む |
| `BlackOps\Journal\Data\AttemptFailedData` | final readonly class | Attempt Failure概要を保持する | `attempt.failed`を読む |
| `BlackOps\Journal\Data\AttemptRetryScheduledData` | final readonly class | Retry時刻／理由を保持する | `attempt.retry_scheduled`を読む |
| `BlackOps\Journal\Data\OperationCompletedData` | final readonly class | 完了Outcomeを保持する | `operation.completed`を読む |
| `BlackOps\Journal\Data\OperationRejectedData` | final readonly class | Rejection Reasonを保持する | `operation.rejected`を読む |
| `BlackOps\Journal\Data\OperationFailedData` | final readonly class | 最終Failure概要を保持する | `operation.failed`を読む |
| `BlackOps\Journal\Data\OperationDeadLetteredData` | final readonly class | Dead Letter概要を保持する | `operation.dead_lettered`を読む |

## Journal Exceptions

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Journal\Exception\JournalReadFailed` | exception class | Canonical Journal読取失敗を通知する | Store Errorを境界で扱う |
| `BlackOps\Journal\Exception\JournalWriteFailed` | exception class | Canonical Journal書込失敗を通知する | Durable書込を失敗させる |
| `BlackOps\Journal\Exception\JournalObservationFailed` | exception class | Observer配送失敗を通知する | Delivery Policyに従って扱う |
| `BlackOps\Journal\Exception\LifecycleTransitionException` | exception class | 不正なState遷移を通知する | Stale／Terminal更新を拒否する |

## Retention

| Namespace／Type | Kind | Purpose | Typical Use |
| --- | --- | --- | --- |
| `BlackOps\Core\Retention\RetentionTarget` | enum | Payload／Journal／Outcome／Dead Letter対象を表す | PolicyとPlanを分類する |
| `BlackOps\Core\Retention\RetentionPeriod` | final readonly value object | 保持日数を表す | 対象別Periodを作る |
| `BlackOps\Core\Retention\RetentionPolicy` | final readonly value object | 対象別保持期間をまとめる | Plan／PurgeへAccepted Policyを渡す |
| `BlackOps\Core\Retention\RetentionPolicyRef` | final readonly value object | Policy Version／承認参照を表す | Purge Auditへ残す |
| `BlackOps\Core\Retention\RetentionActorRef` | final readonly value object | Purge実行主体を表す | CLI／Scheduler Actorを記録する |
| `BlackOps\Core\Retention\RetentionPlan` | final readonly value object | 削除候補一覧を保持する | Dry-run結果を表示する |
| `BlackOps\Core\Retention\RetentionPlanItem` | final readonly value object | Operation単位の候補を表す | 対象、基準時刻、Operation IDを読む |
| `BlackOps\Core\Retention\RetentionPlanner` | interface | 削除候補を計画する | Database Adapterで実装する |
| `BlackOps\Core\Retention\RetentionPurgeTarget` | enum | Purge対象を表す | 実削除結果を分類する |
| `BlackOps\Core\Retention\RetentionPurgeService` | interface | Planを再検証して削除する | CLI／Schedulerから呼ぶ |
| `BlackOps\Core\Retention\RetentionPurgeResult` | final readonly value object | 対象別削除件数を保持する | Purge結果とAuditを表示する |
| `BlackOps\Core\Retention\RetentionHoldCategory` | enum | Hold理由Categoryを表す | Legal／Incident等を分類する |
| `BlackOps\Core\Retention\RetentionHold` | final readonly value object | Operation単位のHoldを表す | Plan／Purgeから除外する |
| `BlackOps\Core\Retention\RetentionHoldPort` | interface | Hold保存／検索Port | Hold Store Adapterで実装する |
| `BlackOps\Core\Retention\RetentionPurgeAuditRecord` | final readonly value object | PayloadなしPurge監査を表す | 変更件数とPolicyを記録する |
| `BlackOps\Core\Retention\RetentionPurgeAuditPort` | interface | Purge Audit保存Port | Database／System Log監査を実装する |

## Source auditの読み方

この一覧はPublic互換性の境界を示しますが、すべての型を通常のApplicationが直接使うという意味ではありません。次を目安に依存範囲を小さくしてください。

- 通常のFeatureは`Operation`、`OperationValue`、`Outcome`、必要なAttributeだけを使います。
- Deferred Result入口は`OutcomeReader`と`OperationId`へ依存します。
- Repository Bindingは`ServiceProvider`と`ServiceRegistry`を使います。
- Transport、Journal、RetentionのPortはAdapterを実装するときだけ使います。
- 内部実装Namespaceや`#[PublicApi]`のない具象実装をApplicationのContractにしません。

現行機能と未提供Surfaceは[Current Status](mvp-status.md)を確認してください。
