# TODO

この文書では、フレームワークの設計課題と実装タスクを管理する。

## 運用ルール

- `[ ]` 未着手
- `[x]` 完了
- `[~]` 検討中
- 重要な設計判断は、結論だけでなく理由もREADMEまたは設計文書へ残す
- 用語の変更時は、コードと文書をまとめて更新する

## MVP Closeout

MVP Definition of Doneは [MVP Status](../docs/guide/mvp-status.md) と [Closeout Report](orchestration/reports/P6-015-mvp-closeout.md) で実装・Test証拠に対応付ける。MVP CompleteはProduction ReadyやStable Releaseを意味しない。

MVP後に残す主要項目:

- [ ] Transactional OutboxのPersistence AdapterとRelay
- [ ] Canonical JournalからObserver Projectionを再送するCLI
- [ ] Authentication／AuthorizationとJournal参照制御
- [ ] Deferred Status／Outcome HTTP EndpointとClient SDK
- [ ] Canonical PayloadとTransportの暗号化
- [ ] OpenTelemetry／CloudWatch／Remote Log Adapter
- [ ] SQLite／MySQL／SQS／Kafka Adapter
- [ ] Generator／Admin UI／Scheduled Operation Strategy
- [ ] Packagist公開／Git Tag／Stable Release

## Post-MVP Developer Experience Roadmap

確定した順序と配布境界は [Developer Experience Roadmap](spec/41-developer-experience-roadmap.md) を正本とする。

### Phase 7: Installed Application Example and Skeleton Layout

- [x] Public Composition APIをFramework外のConsumer視点でAuditする
- [ ] `examples/quickstart/` をInstall直後と同じApplication Layoutへ更新する
- [ ] Inline／Deferred／Worker／Migration／RetentionのConsumer E2Eを整備する
- [ ] Exampleと `blackops/skeleton` の共通Sourceとして配布可能にする

### Phase 8: Composer Project Bootstrap

- [ ] `examples/quickstart/` を `blackops/skeleton` Composer Project Packageとして定義する
- [ ] `composer create-project blackops/skeleton my-app` を提供する
- [ ] Install後Smoke Testを整備する

### Phase 9: Project BlackOps CLI

- [ ] Project所有の薄い `bin/blackops` とFramework Console Kernelを設計する
- [ ] `make:operation` と `make:migration` を提供する
- [ ] Migration／Worker／Build／Retention／Scheduler CommandをApplicationから構成する
- [ ] Framework UpdateでCommand実装とGenerator Stubが更新されることを検証する

### Phase 10: Documentation Website

- [ ] `docs/website/` にAstro Starlightを構築する
- [ ] `docs/internals/` を `docs/internal/` へ移行し、Repository内参照を同期する
- [ ] `docs/guide/` と `docs/internal/` をSource of Truthとする静的Buildを整備する
- [ ] Cloudflare PagesのPreview／Production Deployを整備する

## 現在の優先事項

- [x] Operationの定義と責務を決める
- [x] Operationのライフサイクルを決める
- [x] InlineとDeferred Strategyの実行保証を決める
- [x] Journalの役割と記録形式を決める
- [x] MVP SampleでInline／Deferredの処理全体を検証する

## 1. ユビキタス言語

- [x] 実行される処理単位を `Operation` と呼ぶ
- [x] Operationのログを `Journal` と呼ぶ
- [x] 追跡識別子の正式名称を `Operation ID` とする
- [x] 型付けされた業務入力を `OperationValue` とする
- [x] 成功時の業務結果を `Outcome` とする
- [x] Deferred処理の受付結果を `DeferredAcknowledgement` とする
- [x] 固定的なDispatch ModeではなくExecution Strategyを使用する
- [x] 個々の実行試行を `Attempt` とする
- [x] Journalに記録する一件を `Journal Entry` とする

## 2. Operation

- [x] Operationは要求から最終結果まで続く論理的な処理単位とする
- [x] Operation Envelopeが最低限保持する値を決める
  - [x] Operation ID
  - [x] 発生日時
  - [x] 入力値
  - [x] Execution Strategy
- [x] Operation IDはFWがUUIDv7で発行する
- [ ] Operationを不変オブジェクトとするか決める
- [x] 初期設計ではCommandとQueryを区別せずOperationとして扱う
- [x] Operation DefinitionとOutcome型を `#[Returns]` で関連付ける
- [x] Operation DefinitionとHandlerを `#[HandledBy]` で関連付ける
- [x] Operation DefinitionとOperationValue型を `#[Accepts]` で関連付ける
- [x] Handlerは読み取り専用Operation Envelopeを一つだけ受け取る
- [x] ContextなどのメタデータをOperation Envelopeへ分離する
  - [x] Actor IDとTypeをOptional要素として保持する
  - [ ] Trace ID
  - [x] Correlation ID
  - [x] Causation ID
  - [x] Tenant IDをOptional要素として扱う
- [ ] 冪等性キーをコア仕様に含めるか決める
- [ ] 期限、優先度、キャンセルをコア仕様に含めるか決める

## 3. ライフサイクル

- [x] OperationとAttemptの基本ライフサイクルを定義する
- [x] Inline Strategyの正常系を詳細化する
  - [x] Received
  - [x] Started
  - [x] Completed
- [x] Deferred Strategyの正常系を詳細化する
  - [x] Received
  - [x] Accepted
  - [x] Started
  - [x] Completed
- [x] 業務上の拒否とAttempt/Operationの失敗を区別する
  - [x] Rejected
  - [x] Attempt Failed
  - [x] Retry Scheduled
  - [x] Operation Failed
  - [x] Dead Lettered
  - [ ] Cancelled
  - [ ] Expired
- [x] 不正な状態遷移をJournal生成前に拒否する
- [ ] 現在状態をJournal Entryから導出するか決める
- [ ] 状態スナップショットを保持するか決める

## 4. Journal

- [x] Journal RecordをRetention削除まで不変の追記記録とする
- [x] Journal Recordの共通スキーマを定義する
- [x] Journal Recordに記録する時刻の意味を定義する
- [x] Operation入力を `operation.received` でCanonical記録する
- [x] Inline実行時のJournal Observer失敗をDelivery Policyで扱う
- [x] 正規Journal形式を共有し、Journal ObserverとExecution Transportを分離する
- [x] Canonical JournalとObserved／Purge Auditの責務を分離する
- [ ] JournalとDomain Eventの関係を定義する
- [x] schema versionとUpcasterによるJournal Recordのバージョニングを採用する
- [x] 保持期間と削除方針を決める
- [ ] 改ざん検知が必要か検討する
- [ ] 個人情報の削除要求と不変記録の両立方法を検討する

## 5. 実行方式

### Inline Strategy

- [x] Handlerの戻り値と例外の扱いを決める
- [ ] タイムアウトの扱いを決める
- [x] Canonical Journal失敗とObserver Delivery Policyの継続条件を決める
- [ ] HTTP接続切断時のOperationの扱いを決める

### Deferred Strategy

- [x] StateとReceived／Accepted JournalのCommit成功をDeferred受付完了とする
- [x] Deferred配送はat-least-onceで重複実行し得ると定義する
- [x] Deferred実行は重複し得るものとしExactly Onceを保証しない
- [x] Operation IDによるInbox/Deduplication機構を提供する
- [x] 既定のリトライ回数を定義する
- [x] 指数BackoffとJitterを採用する
- [ ] タイムアウトを定義する
- [x] Lease、Heartbeat、可視性タイムアウト、Fencingを定義する
- [x] Worker停止時は新規Claimを止め、Grace超過時はLease Expired Recoveryへ委ねる
- [x] Dead Letter Transportへ隔離しJournalへ記録する
- [ ] 順序保証の有無と単位を決める
- [ ] 並列実行の単位を決める
- [x] 非同期OutcomeをTyped Outcome Storeへ保存しOperation IDで取得する

## 6. トランザクションと整合性

- [x] Durable基本保証とTransactional Guaranteeを区別する
- [x] Transactional OutboxのPortを初期設計へ含める
- [ ] Transactional OutboxのPersistence AdapterとRelayを実装する
- [ ] Inboxパターンを採用するか検討する
- [ ] Handlerに冪等性を要求するか決める
- [ ] Frameworkが提供する冪等性支援を決める
- [x] Outcome保存失敗時はWorker完了Transaction全体をRollbackする
- [x] Deferred受付のStateとJournalを同一Transactionで保存する

## 7. セキュリティとプライバシー

- [x] センシティブ値は `#[Sensitive]` Attributeを基本として宣言する
- [ ] マスク、除外、暗号化の使い分けを決める
- [ ] 認証情報をJournalへ保存しない仕組みを決める
- [ ] Journal参照権限を定義する
- [ ] Tenant間の分離方法を決める
- [ ] 保存データの暗号化要件を決める
- [ ] Execution Transportの暗号化Capabilityを設計する
- [ ] Type IDとAttributeの整合性を検査するPHPStan拡張を検討する
- [ ] 監査対象となる操作を定義できるようにする

## 8. アダプタ

- [x] Journal ObserverとExecution Transportの責務を分離する
- [x] 遅延配送を担う抽象を `Execution Transport` とする
- [x] `Journal Observer` インターフェースを設計する
- [x] `Execution Transport` を責務別Portとして設計する
- [x] `Outcome Store` インターフェースを設計する
- [x] `FlushableJournalObserver` を追加Capabilityとして設計する
- [x] MVP Reference DB AdapterをPostgreSQLとする
- [ ] KVSアダプタの候補を決める
- [ ] Queueアダプタの候補を決める
- [x] PSR-3ログアダプタにMonolog JSONL Backendを採用する
- [ ] OpenTelemetryアダプタを検討する
- [ ] CloudWatchアダプタを検討する

## LoggingとTraceability

- [x] FW LoggerをPSR-3互換Decoratorとして設計する
- [x] LoggerへExecutionContextを自動付与する
- [x] Operation IDをすべてのOperation内Application Logへ自動付与する
- [x] Attempt ID、Correlation ID、Causation IDを自動付与する
- [x] Operation Type ID、Execution Strategy、Journal Event名を構造化フィールドとして扱う
- [x] originActor、executionActor、authorizationActorはIDだけを自動付与する
- [x] Application LogとLifecycle Journal RecordをRecord Kindで区別する
- [x] FWが標準Operation lifecycle logを自動生成する
- [ ] 構造化ログの安定したSchemaとVersionを定義する
- [x] FW予約Fieldの上書きを禁止しユーザーContextを別namespaceへ格納する
- [x] `#[Sensitive]` とLogger Contextの共通Filterを統合する
- [x] Operation外のLogをOperation IDなしで記録する
- [x] PHP-FPM、長期Worker、Fiber対応のExecution Scopeを採用する
- [x] Application Log障害時はOperationを継続する
- [x] Journal DeliveryをBestEffort／Required／Durableに分ける
- [x] PSR-3 Logger AdapterとMonolog JSONL Backendを実装する
- [x] OTel IDをOperation IDと分離し構造化Logで関連付ける
- [ ] CloudWatch向け構造化ログAdapterを設計する
- [x] Application LogだけをSampling可能としLifecycle JournalはSamplingしない

## Frontend IntegrationとClient Generation

- [ ] FWをHTML Rendering機能を持たないAPI-only／Headless Frameworkとして定義する
- [ ] HTML Responseを標準Responderの対象外とするか決める
- [ ] React、Vue、Next.js、Nuxt等のFrontend Frameworkと協調する境界を定義する
- [ ] Operation DefinitionをFrontend向けContractのSource of Truthにする
- [ ] OperationValueから入力Schemaを生成する
- [ ] Outcomeから成功Response Schemaを生成する
- [ ] Rejection ReasonからError Response Schemaを生成する
- [ ] AcknowledgementからDeferred受付Response Schemaを生成する
- [ ] PHP型からJSON Schemaへの変換規則を定義する
- [ ] PHP型からTypeScript型への変換規則を定義する
- [ ] nullable、union、enum、日時、UUID、Collection、Value Objectの型変換規則を決める
- [ ] `#[Sensitive]` Propertyを生成SchemaとClientから除外する規則を決める
- [ ] Route、HTTP Method、Binding MetadataからClient Methodを生成する
- [ ] HTTP通信を隠蔽する型安全なClient SDK Generatorを設計する
- [ ] Client Methodの命名をOperation Type IDまたはDefinition Classから生成する規則を決める
- [ ] Path、Query、Header、Bodyの組み立てをClient内部へ隠蔽する
- [ ] Completed、Rejected、FailedをClient側で表現するResult型を設計する
- [ ] Deferred Operationの202、Operation ID、状態確認、PollingをClient APIで抽象化する
- [ ] Cancellation、Timeout、Retry、AbortSignal相当のClient APIを検討する
- [ ] 認証Tokenの付与と更新をClient Middleware／Interceptorとして設計する
- [ ] Correlation ID等のTrace ContextをClientから伝播する方法を決める
- [ ] OpenAPIを生成するか、独自Operation Manifestから直接Clientを生成するか決める
- [ ] OpenAPI生成時のOperation ID、Schema名、Error定義の規則を決める
- [ ] TypeScript以外のClient SDK生成を拡張可能にするGenerator Portを設計する
- [ ] Generated ClientのVersionとServer Manifestの互換性検証を設計する
- [ ] Breaking ChangeをCIで検出するContract Diffを設計する
- [ ] CORS、CSRF、Cookie／Bearer認証などFrontend接続時のSecurity要件を整理する
- [ ] File Upload／Download、Streaming、SSE、WebSocketを対象に含めるか決める
- [ ] Frontend Integration専用の設計対話をMVP後に作成する

## 9. HTTP境界

- [x] Adapter Middlewareで認証しCredentialを除いたActorContextを生成する
- [x] Adapter MiddlewareとOperation Middlewareを玉ねぎ構造として分離する
- [x] origin、execution、authorizationのActor責務を分離する
- [x] HTTPリクエストからOperationValueへのBindingとValidationを定義する
- [x] Operation Definitionの `#[Route]` でHTTPルートを宣言する
- [x] BindingとOperationValue Validationの境界を定義する
- [x] WebアダプタのResponderがOutcomeをHTTPレスポンスへ変換する
- [x] Deferred受付成功は既定でHTTP 202を返す
- [ ] Deferred Operationの状態確認APIを設計する
- [ ] 認証・認可をOperation生成前後のどちらで行うか決める

## 10. 最小プロトタイプ

- [x] 対象をPHP 8.5以上とする
- [x] MVPのReference Execution TransportをPostgreSQLへ変更する
- [x] 公式開発環境にDocker Composeを採用する
- [x] PostgreSQL TransportのSchema、Index、Migrationを定義する
- [x] PostgreSQL PayloadとContextをCodec済み `bytea` で保存する
- [x] StateをTEXT + CHECK、時刻をTIMESTAMPTZとする
- [x] Claimへ `FOR UPDATE SKIP LOCKED` を採用する
- [x] Framework管理のVersion付きMigration CLIを提供する
- [x] PostgreSQL Table、Partial Index、Migration SQLを実装する
- [x] PostgreSQL AdapterへCanonical Journal Storeを含める
- [x] Deferred受付のStateとJournalを同一Transactionで保存する
- [x] WorkerのLifecycle境界を短いTransactionへ分割する
- [x] ObserverをCommit後にBestEffort配送する
- [ ] Canonical JournalからObserver Projectionを再送するCLIを設計する
- [x] PostgreSQL専用Schema `blackops` を採用する
- [x] DB Adapter間で論理Table名を共通化する
- [x] Canonical Journalを検索Column + Encoded RecordのHybrid構造にする
- [x] OutcomeとDead Letterを別Tableにする
- [ ] MySQL AdapterをMVP後の候補として検討する
- [x] Payload、Journal、Outcome、Dead LetterのRetentionを分離する
- [x] Terminal OperationのPayloadをTombstone化可能にする
- [x] Retention対象外部キーを `ON DELETE RESTRICT` とする
- [x] Operation単位のLegal Holdを設ける
- [x] Retention Policy Contractを実装する
- [x] Retention Serviceを設計・実装する
- [x] Retention Hold Portを設計・実装する
- [x] Retention SchedulerをMVPへ含める
- [x] Retention期間はProductionで明示設定を要求する
- [x] Holdを `retention_holds` として一般化する
- [x] Purge Auditを別Tableとfail-closed System Logへ記録する
- [x] Retention CLIとFramework Maintenance Scheduler Workerを実装する
- [x] Inline／Deferred Canonical JournalのRetention削除を実装する
- [x] Inline OperationへRetention HoldとPurge Auditを保存可能にする
- [x] MVP実装Phaseと最初のVertical Sliceを確定する
- [x] LintとStatic AnalysisにMagoを採用する
- [x] Test RunnerにPHPUnitを採用する
- [x] Phase 0: Foundationを実装する
- [x] Phase 1: Journal付きInline Vertical Sliceを実装する
- [x] Frontend接合方式をD047で確定する
- [x] Codex GPT-5.4-mini workerへの実装依頼方式へOrchestrationを更新する
- [x] Codex GPT-5.4-mini workerへ渡すTask Packet Templateを維持する
- [x] `develop/STATE.md` のCheckpoint Templateを作成する
- [x] Orchestrator Codex／GPT-5.4-mini worker共通規約をRootの `AGENTS.md` に記述する
- [x] MVP範囲のFramework実装者向け `docs/internals/` を整備する
- [x] MVP範囲のFramework利用者向け `docs/guide/` を整備する
- [x] WSL2 Distributionを導入する
- [x] WSL2の `/home/kubotak/projects/blackops` に実装Repositoryを準備する
- [x] WSL2内のOpenCode CLI導入は旧方式の履歴として保持する
- [x] GLM-5.2 Provider非対話実行確認は新方式では不要とする
- [x] Docker ComposeでPHP 8.5、Composer、Mago、PHPUnit、Deptrac、PostgreSQL環境を準備する
- [x] InMemory TransportをUnit Test向けに実装する
- [ ] SQLite AdapterをMVP後の候補として検討する
- [x] HTTP ContractにPSR-7／15／17を採用する
- [x] FrankenPHP 1／PHP 8.5のReference HTTP RuntimeとPSR-15 Front Controllerを実装する
- [x] RouterにFastRouteを採用し、Compile済みDispatcher DataをHTTP Manifestへ保存する
- [x] 開発用Dynamic Operation DiscoveryでPSR-4、Classmap、Token Scanを統合する
- [x] `operation:list`と開発用Operation／HTTP Manifest CompileへDynamic Discoveryを接続する
- [x] UUIDv7生成にSymfony UIDを採用する
- [x] CLIにSymfony Consoleを採用する
- [x] Logger BackendにMonolog 3を採用する
- [x] Monolog 3をExecutionScopedLogger向けJSONL Backendとして構成する
- [x] Test FrameworkにPHPUnitを採用する
- [x] MVPは `blackops/framework` 単一Composer Packageとする
- [x] 単一Package内を責務別Namespaceへ分割する
- [x] 内部専用実装を `BlackOps\Internal` へ配置する
- [x] Namespace間の依存方向を定義する
- [x] Deptracで依存違反をCI検証する
- [x] `deptrac.yaml` を実装する
- [x] Operation、OperationValue、OutcomeをMarker Interfaceとする
- [x] Handlerを単一の `handle()` Contractとする
- [x] 互換性を保証するPHP Public APIへ `#[PublicApi]` を付ける
- [x] `#[PublicApi]` の付与とInternal型露出をCI検証する
- [x] Operation EnvelopeをFramework管理の `final readonly class` とする
- [x] Envelopeの識別情報はExecutionContextを正本とする
- [x] Operation Envelopeを実装する
- [x] ExecutionContextをFramework管理の `final readonly class` とする
- [x] Attempt IDと開始時刻をOptionalなAttemptContextへまとめる
- [x] ExecutionContextの生成と遷移を内部Factoryへ限定する
- [x] ExecutionContext、AttemptContext、内部Factoryを実装する
- [x] Framework IDを意味ごとの独立した `final readonly class` とする
- [x] UUIDv7生成を内部IdentifierFactoryへ集約する
- [x] IDの正規文字列表現と変換APIを定義する
- [x] IDの同値比較APIと不正入力時の例外型を決める
- [x] ID Value ObjectとIdentifierFactoryを実装する
- [x] PSR-20 Clockを採用する
- [x] 時刻をUTCの `DateTimeImmutable` で扱う
- [x] 時刻文字列をマイクロ秒付きRFC 3339 UTCへ統一する
- [x] TimestampとLifecycle順序保証を分離する
- [x] 共通Time Codecを実装する
- [x] Journal RecordをNested Envelopeとする
- [x] Lifecycle EventのWire NameをDot-separated形式とする
- [x] Operationごとの単調増加SequenceをJournal Recordへ必須化する
- [x] Event固有Fieldを型付き `data` Objectへ格納する
- [ ] Journal Record共通SchemaのJSON Schemaを定義する
- [x] Sequenceの永続割当と競合制御を設計する
- [x] Journal Recordを共通の `final readonly class` とする
- [x] Lifecycle EventをString-backed Enumで表す
- [x] Event Dataを型付き `JournalData` とする
- [x] Journal Record生成を内部Factoryへ限定する
- [x] JournalRecord、JournalEvent、JournalData、内部Factoryを実装する
- [x] Received Journalへ再現可能なCanonical Payloadを保持する
- [x] Completed JournalへCanonical Outcomeを保持する
- [x] Failure Journalの安全な構造化Errorを定義する
- [x] DataなしEventを `EmptyJournalData` として表す
- [x] Lifecycle EventごとのData ClassとCodecを実装する
- [x] Canonical JournalからObserver ProjectionをFW共通Pipelineで生成する
- [x] `#[Sensitive]` にOmit、Mask、HMACを定義する
- [x] 予約Key Patternによる防御的Omitを行う
- [x] ObserverとCanonicalJournalStoreを型レベルで分離する
- [x] Sensitive FilterとObserver Projectionを実装する
- [ ] Canonical StoreのCapability検証を設計する
- [x] Observer専用の `ObservedJournalRecord` を定義する
- [x] Journal Port失敗を専用Exceptionで表す
- [x] Flushを追加Capabilityとして分離する
- [x] Canonical JournalのWriterとReaderを分離する
- [x] Journal Port InterfaceとExceptionを実装する
- [x] InlineとDeferredのSequence管理場所を定義する
- [x] Deferred SequenceをTransactionで原子的に予約する
- [x] Sequenceの欠番を許容し監視対象とする
- [x] 再配送時にRecord IDとSequenceを維持する
- [x] Deferred Operation StateのVersionと `next_sequence` を実装する
- [x] `attempt.retry_scheduled` を標準Lifecycle Eventへ追加する
- [x] `operation.accepted` をDeferredのDurable受付に限定する
- [x] Attempt SucceededとOperation Completedを区別する
- [x] FailedとDead Letteredを排他的なTerminal Eventとする
- [x] Lifecycle状態遷移表と検証器を設計する
- [x] Handlerの戻り値を `OperationResult<TOutcome>` に統一する
- [x] OperationResultの生成をStatic Factoryへ限定する
- [x] 値のない成功を `EmptyOutcome` として扱う
- [x] OperationResult、RejectionReason、EmptyOutcomeを実装する
- [x] MVP Lifecycle Stateと遷移を定義する
- [x] Lifecycle状態遷移をMermaid図で記録する
- [x] 不正遷移をJournal生成前に拒否する
- [x] Terminal後の新規EventとHandler実行を拒否する
- [x] Lifecycle Transition Tableと検証器を実装する
- [ ] JournalからLifecycle Stateを再構築する専用Readerを実装する
- [x] AttemptContextへID、番号、開始時刻を保持する
- [x] Lease MetadataをTransport内部へ限定する
- [x] Attempt Started記録後にHandlerを呼び出す
- [x] Claimへ単調増加Fencing Tokenを付与する
- [x] Deferred Claim、Attempt開始、Fencing検証を実装する
- [x] Handler実行中にHeartbeatでLeaseを延長する
- [x] CrashしたRunning AttemptをLease Expired Failureとして閉じる
- [x] Heartbeat失敗後の完了更新を禁止する
- [x] Graceful Shutdown時はLeaseを自然失効させる
- [x] Worker Heartbeatを実装する
- [x] Crash Recoveryを実装する
- [x] Signal処理を実装する
- [x] Transport境界をCodec済みDeferredOperationMessageとする
- [x] Durable受付結果をDeferredAcknowledgementとする
- [x] MVPのClaimを一件単位とする
- [x] Execution Transportを責務別Portへ分割する
- [x] Execution Transport PortとMessage型を実装する
- [x] `Operation` を実装する
- [x] `OperationId` を実装する
- [x] `OperationValue` を実装する
- [x] `Outcome` を実装する
- [x] `OperationHandler` を実装する
- [x] `Dispatcher` を実装する
- [x] `DispatchMode`の確定名称である `ExecutionStrategy` を実装する
- [x] `Journal Entry`の確定名称である `JournalRecord` を実装する
- [ ] インメモリJournal Observerを実装する
- [x] Unit Test用InMemory Execution Transportを実装する
- [x] Inline Strategyを実装する
- [x] Deferred Strategyを実装する
- [x] 最小Workerを実装する
- [ ] 冪等性の基本機構を実装する

## 11. 検証用ユースケース

- [x] MVP検証用の `ShowWelcome`／`GenerateReport` Operationを定義する
- [x] HTTP入力からOperationを生成する
- [x] Inline Strategyで正常終了させる
- [x] Deferred Strategyで受付後にWorkerから実行する
- [x] Journalからライフサイクルを確認する
- [x] Handler失敗後に再試行する
- [x] Operation IDの一意制約とFencingで重複受付／Stale更新を防止する
- [x] 再試行上限後にデッドレターへ移動する
- [x] 非同期Outcomeを取得する
- [x] Canonical Journalが再現用の値を保持し、Observed Projection／JSONLで機密値を安全化する境界を確認する

## 12. ドキュメント

- [x] 旧 `SPECIFICATION.md` の入口を `develop/spec/README.md` へ統合する
- [x] 確定仕様を分野別の `spec/` 文書へ分割する
- [x] READMEの用語を `Operation` と新しい `Journal` の定義へ更新する
- [x] アーキテクチャ概要を作成する
- [x] Operationのライフサイクル図を作成する
- [x] Inline Strategyのシーケンス図を作成する
- [x] Deferred Strategyのシーケンス図を作成する
- [x] 障害時のシーケンス図を作成する
- [x] `develop/decisions/` の設計対話形式で判断履歴を記録する
- [ ] Welcome Pageと初期Application Skeletonを後工程で設計する

## 後で検討すること

- [ ] BlackOpsの正式公開前に、主要公開地域とソフトウェア関連区分を対象とした商標クリアランスを行う
- [ ] `BlackOps` / `BlackOpsPHP` のComposer Vendor、GitHub Organization、Domain、SNS識別子の利用可能性を確認する
- [ ] Operation同士の依存関係
- [ ] Operationの連鎖とSaga
- [ ] 複数のDeferred Operationを一定期間で集約するCoalesceの意味論
- [ ] Coalesce後も個々のOperationをJournal上で追跡できる設計
- [ ] センシティブ値を持つOperationで許可するExecution Strategyを定義する
- [ ] スケジュール実行
- [ ] バッチ処理
- [ ] 複数Worker間の負荷分散
- [ ] 管理画面からの検索、再試行、キャンセル
- [ ] Event Sourcingを採用するアプリケーション向けの拡張
- [ ] Actor Modelに近い逐次処理単位の提供
- [ ] Operation Feature一式を生成するCLIを設計する
- [ ] OperationValue、Handler、Outcome、Responderの選択生成を設計する
- [ ] Http／Console／Internal Operationの雛形生成を設計する
- [ ] Middlewareの雛形生成を設計する
