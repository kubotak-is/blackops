# D136: Structured Logging and OpenTelemetry

Status: Decided

## Context

D014はPSR-3 LoggerへExecution Scopeを自動付与し、Application LogとLifecycle Journal LogをVersion付きの共通Envelopeで区別する方針を決めた。OTel Trace ID／Span IDはOperation ID／Correlation IDと分離し、構造化Logで関連付けることも決めている。D015はApplication LogとTelemetry Sinkの失敗をPrimary Operationから分離し、D099はInstalled Applicationの既定BackendをMonolog JSONLへ固定した。

一方、現在の実装と公開契約には次の差がある。

- `ExecutionScopedLogger`は`schemaVersion`、`kind`、`operation`、`context`をPSR-3 Contextへ入れるが、Monolog `JsonFormatter`はそれらを`context`の内側へ包み、外側にMonolog固有の`datetime`、`level_name`等を出す。
- `JsonlJournalRecordEncoder`は独立したTop-level `schemaVersion: 1`／`kind: journal` Envelopeを出すため、Application LogとObserved Journalの外形は実際には共通化されていない。
- `ExecutionContext`とDeferred ContextにTrace Contextはなく、Application Log、Observed Journal、Worker、Outbox、Scheduleを同じTraceへ関連付けられない。
- `composer.json`はOpenTelemetry API／SDK／Exporterを持たず、Tracer／MeterのApplication Compositionもない。
- Operation、Worker、Outbox Relay、Application Scheduler、Maintenance Schedulerの実行結果を安定したMetricへ変換するContractがない。
- Framework-owned Health／Readiness Port、Safe Probe Document、HTTP／CLI Adapterがない。
- D135はTenant Raw ID、Key ID、Ciphertext、Provider DetailをDefault Logへ出さず、Protection FailureをSafe Code／Storage Purposeへ縮約する境界を固定したが、Telemetry SignalごとのProjectionは未確定である。

OpenTelemetry SDKやOTLP ExporterをFrameworkが所有すると、Applicationの既存Instrumentation、Resource、Sampling、Credential、Collector構成と競合する。一方、Providerを全く受け取らない設計では、Operation／Attempt／JournalのFramework-owned Lifecycleを安全に計測できない。Structured Log、Trace、Metric、Healthを一つの責任境界として確定する。

## Inherited Decisions

次は維持し、本Decisionで再選択しない。

- FW LoggerはPSR-3 `LoggerInterface` Decoratorであり、User ContextとFramework予約Fieldを分離する。
- Application LogとObserved JournalはVersion付きStructured Recordとし、標準Lifecycle JournalをSamplingしない。
- OTel Trace ID／Span IDはOperation ID／Correlation IDと同一値にしない。
- Application Log／Telemetry Exporterの失敗でPrimary Operation、Terminal Journal、Outcome、HTTP Responseを変更しない。
- Canonical JournalはLifecycleの正本、Observed JournalはSensitive Projection後の配送Recordである。
- Tenant Raw ID、Actor Raw ID、Credential、Token、Payload、Outcome、Key Material、Ciphertext、Nonce、Tag、Provider DetailをDefault Log／Trace／Metricへ出さない。
- Protection FailureはSafe CodeとStorage Purposeへ縮約し、Rotation Key IDはRestricted CLI Outputだけに表示する。
- Built-in JSONLはstderr既定で、Local Stream以降の収集、配送、Rotation、Retention、Access ControlはApplication／Infrastructure責務である。
- BlackOpsはHeadless Frameworkであり、Applicationの公開Routeや認証境界を暗黙に追加しない。

## Decision Drivers

- JSONL ConsumerがMonolog内部構造に依存せず、一つの公開Schema VersionでApplication／Framework／Journal／Audit Recordを判別できる
- Operation ID、Correlation ID、Trace ID、Span IDを相互変換せず同じRecordで検索できる
- HTTP、Inline、Child、Deferred Worker、Retry、Outbox、ScheduleをW3C Trace ContextでProcess越しに関連付けられる
- Framework PackageはInstrumentation APIだけに依存し、SDK、Exporter、Collector、CredentialをApplicationが所有できる
- Metric Labelを低Cardinalityに固定し、Operation／Attempt／Tenant／Actor／Trace等のIdentityをLabelへ入れない
- LivenessとReadinessを区別し、Probe障害やTelemetry Export障害を混同しない
- Long-running Worker／SchedulerでScope、Span、Metric、Provider Stateが次のIterationへ漏れない
- Existing JSONL、Quickstart、Worker、Outbox、Schedule、Storage ProtectionのFailure境界を維持する

## Question 1: Structured Log Schema and Version

### Options

- A: `schemaVersion: 1`をBlackOps Structured Recordの正本として確定し、Application／Framework／Journal／Auditを共通Top-level Envelopeへ正規化する。Monologの既定JSON外形はCustom Formatterで置き換え、現在のNested Context形はExperimental `main`の不整合として移行互換を持たない
- B: Monologの標準JSON外形を正本とし、Observed Journalも`channel`／`level_name`／`context`の内側へ移す
- C: Application LogとObserved Journalは別Schemaとして管理し、共通Versionを持たせない

### Recommendation

Aを推奨する。

Public Specificationが既に示す`schemaVersion`、`kind`、`operation`を実際のTop-levelへ揃え、MonologはStream／Level処理の実装詳細に限定する。共通Fieldは少なくとも`schemaVersion`、`kind`、UTC Microsecondsの`occurredAt`、Optional `operation`、Optional `telemetry`とする。Application／Frameworkは`level`、`message`、`channel`、Filtered `context`を持ち、Journalは既存の`recordId`、`event`、`sequence`、`attempt`、`data`を維持する。存在しないTop-level Blockは省略し、Empty ObjectとEmpty ListのWire Shapeを維持する。

`schemaVersion`はJSONL Backendの設定VersionやCanonical Journal Storage Versionではなく、Observed Structured RecordのSchema Versionである。将来のBreaking Field変更だけでVersionを上げ、Optional Field追加は同じVersion内の後方互換拡張として扱う。

[ANSWER]
A
[/ANSWER]

## Question 2: Safe Identity and Failure Projection

### Options

- A: Log／TraceではOperation ID／Type、Attempt ID／Number、Correlation／Causation ID、Strategy、Schedule名を許可し、ActorはType＋`[masked]`、TenantはType＋`[masked]`だけを出す。Metricにはこれらの個別Identityを一切出さず、Operation Type、Strategy、Result等の有限属性だけを許可する。Protection／Provider FailureはSafe CodeとStorage Purposeだけに縮約する
- B: Tenant IDとActor IDをSHA-256 HashにしてLog／Trace／Metricへ共通出力する
- C: Sensitive Filter後なら任意のExecutionContext Fieldを各Adapterが選んで出力できる

### Recommendation

Aを推奨する。

固定`[masked]`はRaw IDを辞書攻撃可能なHashへ変換せず、Tenant／Actorが存在したこととTypeだけを示せる。Operation IDとTrace IDはIncident相関に必要なためLog／Traceへ出すが、Metric Labelへ入れると時系列がOperationごとに増えるため禁止する。Tenant TypeもApplication定義でCardinalityを保証できないためMetric Labelへ入れない。

Framework Errorは安定したFailure Classification／Codeだけを持ち、Throwable Message、Stack、SQL、Payload、Provider Detailを自動記録しない。Rotation Auditに既に許可されたSafe Fingerprint／Scope HashはAudit StoreのContractに留め、Default Log／Metricへ複製しない。

[ANSWER]
A
[/ANSWER]

## Question 3: OpenTelemetry Dependency and Ownership

### Options

- A: Framework Packageは`open-telemetry/api`だけへ依存し、Applicationが明示登録する`TracerProviderInterface`／`MeterProviderInterface`を使う。SDK、Processor、Reader、Exporter、Resource、Sampling、OTLP Endpoint／Header／Credential、CollectorはApplication／Infrastructureが所有し、Provider未登録時はNo-opとする
- B: FrameworkがOpenTelemetry SDKとOTLP HTTP Exporterを必須依存にし、`config/telemetry.php`でEndpointとCredentialを管理する
- C: OpenTelemetry Packageへ依存せず、独自Tracer／Meter Interfaceと独自OTLP Encoderを実装する

### Recommendation

Aを推奨する。

OpenTelemetry PHPの公式Guidanceは、Library InstrumentationがAPI Packageへだけ依存し、ApplicationがSDKを初期化する責任分離を推奨している。BlackOpsはFramework-owned LifecycleをAPIで計測し、Applicationが既存のAuto／Manual Instrumentationと同じProviderへ接続できるようにする。

Compiled ArtifactとBlackOps ConfigへExporter Credential、Resolved Resource、Endpoint Headerを保存しない。FrameworkはNetwork Exporterを生成せず、OTLP／Vendor BackendのDeliveryを保証しない。No-op ProviderでもOperation動作とJSONL出力は同一に保つ。

[ANSWER]
A
[/ANSWER]

## Question 4: Trace Context and Propagation

### Options

- A: W3C Trace Contextを採用し、Validated `traceparent`とOptional Bounded `tracestate`をFrameworkのTelemetry Contextとして保持する。HTTP Entryで抽出し、Child／Deferred Context／Outbox／Worker Retryへ伝播する。Baggageは初期Scope外とし、Trace ContextはEncrypted ExecutionContext内へ保存する。Replayは新Traceを開始して元ContextへLinkし、元TraceのChildとして偽装しない
- B: Correlation IDをTrace IDへ変換し、Process越しのParent／Span Contextは保存しない
- C: HTTP Header伝播だけをAuto-instrumentationへ任せ、Deferred Worker／Outbox／Retryは別Traceにする

### Recommendation

Aを推奨する。

OpenTelemetryの公式PropagationはW3C Trace Contextを標準のText Carrierとしている。Operation ID／Correlation IDをTrace IDへ変換せず、Remote Parentを検証後に別Contextとして保持する。`tracestate`はDefault Logへ出さず、Protected Deferred Context内だけで伝播する。任意Baggageは個人情報やCredentialを運ぶ危険があるためFrameworkは受理／保存／出力しない。

Lifecycle RecordはRecord生成時の有効なTrace ID／Span ID／Trace FlagsだけをSafe Telemetry Projectionとして保持し、Observer Replayは元Recordの相関を上書きしない。Invalid CarrierはRaw値をErrorへ含めず、Remote Parentなしとして新Traceを開始する。

[ANSWER]
A
[/ANSWER]

## Question 5: Span Lifecycle and Attributes

### Options

- A: Deferred Acceptance／Outbox送信はProducer Span、Inline実行はInternal Span、Worker AttemptはConsumer Spanとして分ける。Retryごとに新しいAttempt Spanを作り、Operation IDを共通属性にする。Schedule評価、Maintenance、Outbox Relayは独立Runtime Spanとし、既存HTTP Server／DB Client Spanを重複生成しない
- B: Operation受理から全Retry完了まで一つのSpanを開いたままにし、Process越しに同じSpan IDを再利用する
- C: Journal Eventごとに短いSpanを作り、Operation実行Spanは作らない

### Recommendation

Aを推奨する。

Spanは実際のProcess／時間境界で開始・終了し、Deferred待機中にSpanを開いたままにしない。Operation／Attempt／Correlation／Scheduleの安全な識別子をTrace属性へ持たせ、Lifecycle Eventは該当SpanのEventとして追加できる。Point-in-time Journal Eventだけを大量のSpanへ変換しない。

失敗時はSafe `error.type`／Result CodeとSpan StatusだけをFrameworkが設定し、Throwable Message／Stackを自動で`recordException()`しない。Spanは`finally`で終了し、Scopeは必ずdetachして長期Processの次Iterationへ漏らさない。

[ANSWER]
A
[/ANSWER]

## Question 6: Metric Schema and Cardinality

### Options

- A: `blackops.*` NamespaceのCounter／Histogram／UpDownCounterを安定契約として定義し、UCUM Unitを使う。Operation Duration／Result、Worker Claim／Heartbeat、Outbox Relay、Application Schedule／Maintenance、Observer／Protection Failureを計測する。LabelはOperation Type、Strategy、Runtime Kind、有限Result／Failure Codeだけに限定する
- B: Operation ID、Attempt ID、Tenant、Actor、Trace IDをMetric Labelへ含め、Logなしでも一件ずつ検索可能にする
- C: Metric名／LabelはAdapter実装へ任せ、FrameworkとしてSchemaを公開しない

### Recommendation

Aを推奨する。

MetricはDashboard／Alertで集計できる低Cardinality Contractにする。Durationは秒のHistogram、離散件数は`{operation}`等のUCUM Annotation、Current In-flightはUpDownCounterを使う。成功／失敗でMetric名を分けず、有限Result属性で同じInstrumentへ記録する。

Operation ID、Attempt ID、Correlation／Causation ID、Trace／Span ID、Tenant／Actor ID、Key ID、Schedule Occurrence ID、自由文Reason、Throwable MessageをMetric Labelへ入れない。Queue Depth／Oldest Age等のDatabase SnapshotはHealth／Readiness Queryと同じBounded SourceからObservable Metricにできるが、Table／Row／Tenant IdentityをLabelへ出さない。

[ANSWER]
A
[/ANSWER]

## Question 7: Health and Readiness Surface

### Options

- A: FrameworkはLiveness／ReadinessのPublic Query PortとSafe Versioned Resultを提供し、PSR-15 Handler／CLI Formatterは明示的に組み立てられるAdapterとする。Routeを自動公開しない。LivenessはProcess生存だけ、ReadinessはCompiled Artifact、Database接続／Migration Compatibility、Required Provider構成等をBounded Checkで判定し、Telemetry Export失敗はReadinessを落とさない
- B: Frameworkが`/health`と`/ready`を全Applicationへ認証なしで自動登録し、詳細Exception Messageを返す
- C: Health／Readinessは完全にInfrastructure責務とし、FrameworkはPortもResultも提供しない

### Recommendation

Aを推奨する。

BlackOpsはHeadlessであり、既存ApplicationのRoute／認証／Network公開を暗黙変更しない。ApplicationはKubernetes Probe、Load Balancer、CLI、Supervisorへ同じSafe Resultを接続できる。Responseは`schemaVersion`、`status`、安定したCheck Codeだけを返し、DSN、Host、Schema、SQL、Provider Class、Key ID、Credential、Raw Exceptionを含めない。

LivenessへDatabase／Exporterを含めると一時的な依存障害でProcess再起動Loopを起こすため分離する。Readinessは新規Workを安全に受けられるかを表し、OTel Collector／Exporter障害はPrimary Operationを停止させない既存Decisionに従ってDegraded Telemetryとして別Signalへ記録する。Worker／Schedulerは同じQuery PortをCLI／Supervisorへ接続し、HTTP Serverを内蔵しない。

[ANSWER]
A
[/ANSWER]

## Question 8: Failure, Sampling, Flush, and Shutdown

### Options

- A: Telemetry API呼出、Exporter、Flush／Shutdownの失敗はBest-effortでPrimary結果を変更しない。Sampling／Batch／Retry／Exporter ShutdownはApplication-owned SDK Policyとし、FrameworkはSpan Scopeを決定的に終了し、Operation／Loop／Process境界で登録済みLifecycle HookへFlush機会を通知する。Journal JSONLはSamplingしない
- B: Trace／Metric Export失敗時はOperationを失敗させ、Readinessも直ちにNot Readyにする
- C: Frameworkが同期Exportと全Operation終了時`forceFlush()`を必須にし、Application SDK設定を上書きする

### Recommendation

Aを推奨する。

Exporter障害をOperation Failureへ変えるとObservability障害が業務停止になる。FrameworkはAPI呼出をSafe Boundaryで囲み、Telemetry内部ErrorをRaw Messageなしの有限Failure Codeへ縮約する。Application-owned SDKがSampling、Batch、Retry、Network Timeout、Shutdownを管理し、FrameworkはProviderを所有物として強制Shutdownしない。

D015のFlush機会はOperation終了、Worker／Scheduler Loop終了、Process ShutdownでLifecycle Hookへ通知するContractとして維持する。Hook実装が実際にBatchを送るかNo-opにするかはProvider／Application Policyで決め、Frameworkが毎Operationの同期Network送信を要求しない。Canonical／Observed Journalの生成とJSONL配送はTrace Samplingから独立させる。

[ANSWER]
A
[/ANSWER]

## Decision

[DECISION]

1. BlackOps Structured Recordは`schemaVersion: 1`の共通Top-level Envelopeを正本とし、Application、Framework、Journal、Auditを`kind`で区別する。MonologのNested Context形はExperimental `main`の不整合として移行互換を持たず、Canonical Formatterで置き換える。
2. Log／TraceはOperation／Attempt／Correlation／Causation／Scheduleの安全な相関Fieldを持てる。Actor／Tenant IDは`[masked]`とし、Metric Labelへ個別Identityを出さない。Protection FailureはSafe CodeとStorage Purposeへ縮約する。
3. Framework Packageは`open-telemetry/api`だけへProduction依存し、ApplicationがTracer／Meter Provider、SDK、Processor／Reader、Exporter、Resource、Sampling、OTLP Endpoint／Credential、Collectorを所有する。Provider未登録時はNo-opとする。
4. W3C Trace Contextを採用し、Validated `traceparent`とOptional Bounded `tracestate`をHTTP、Child、Deferred Context、Outbox、Worker Retryへ伝播する。Baggageは扱わず、Process越しContextはEncrypted ExecutionContext内へ保存する。
5. Deferred Acceptance／Outbox送信はProducer、Inline実行はInternal、Worker AttemptはConsumer Spanとし、Retryごとに新しいSpanを作る。Schedule、Maintenance、Outbox Relayは独立Runtime Spanとし、既存HTTP Server／DB Client Spanを重複生成しない。
6. `blackops.*` MetricはCounter／Histogram／UpDownCounter、UCUM Unit、有限属性の安定契約とする。Operation／Attempt／Tenant／Actor／Trace／Key／Occurrence等の個別Identityと自由文をLabelへ入れない。
7. FrameworkはLiveness／ReadinessのPublic Query PortとSafe Versioned Result、明示Composition用PSR-15／CLI Adapterを提供する。Routeを自動公開せず、Telemetry Export失敗はReadinessを落とさない。
8. Telemetry API、Exporter、Flush／Shutdown FailureはBest-effortとし、Primary Operation／Journal／Outcome／HTTP Responseを変更しない。Sampling／Batch／Retry／Exporter ShutdownはApplication-owned SDK Policyとする。
9. Local OpenTelemetry CollectorはFramework Production CapabilityではなくConsumer Verification Infrastructureとして利用できる。Application FixtureがSDK／OTLP Exporterを構成し、Collector `debug` ExporterでTrace／Metricを検証する。

[/DECISION]

## Cross-cutting Contract

- Structured JSONL、Trace、Metric、Probeは同じSafe Projection Policyを共有し、Signalごとの追加FieldでRaw Data制限を緩めない。
- Trace ID／Span IDは有効なOpenTelemetry Span Contextからだけ取得し、Operation ID／Correlation IDから生成しない。
- Lifecycle Recordは生成時のTelemetry Correlationを保持し、Observer ReplayでReplay SpanのIDへ上書きしない。
- Invalid Remote ContextはRaw Headerを記録せずRemote Parentなしとして扱う。
- Frameworkが自動記録するSpan ErrorはSafe Type／Resultだけとし、Throwable Message／Stackを既定で記録しない。
- Span／Scopeは`finally`で終了／detachし、Worker、Relay、Schedulerの次IterationへContextを残さない。
- Application LogとObserved Journal JSONLはTrace Samplingから独立し、標準Lifecycle JournalをSamplingしない。
- Health／Readiness ResultはDSN、Host、Schema、SQL、Provider Class、Key ID、Credential、Raw Exceptionを返さない。
- Framework Config／Compiled ArtifactへExporter Endpoint Header、Credential、Resolved Resource、SDK Instanceを保存しない。

## Local Docker Verification

Official OpenTelemetry Collector Docker Imageと最小Configを使い、OTLP gRPC `4317`／HTTP `4318` ReceiverからTrace／Metricを受け、`debug` Exporterへ出力できる。BlackOpsの初期Consumer EvidenceはPHPで追加Extensionを要求しないOTLP HTTPを使う。

検証Fixtureは次を満たす。

- Framework ArchiveのProduction Dependencyは`open-telemetry/api`だけである。
- Consumer FixtureだけがOpenTelemetry SDK、OTLP Exporter、HTTP Client実装をDevelopment Dependencyとして持つ。
- Collector Imageは`latest`を使わず、Task開始時にSupply Chain Reviewした固定Versionを使う。初期候補は公式最新Release `0.157.0`である。
- Collector ConfigはOTLP Receiverと`debug` Exporterだけを有効にし、Vendor Credential／Remote Backendを使わない。
- HTTP Inline、Deferred Acceptance、Worker Retry、Outbox、ScheduleのTrace Continuity、異なるSpan ID／Kind、Metric名／Unit／有限属性をCollector Logで機械検証する。
- Structured JSONLのTrace／Span相関とCollector Spanを照合する。
- Raw Tenant／Actor／Key／Payload／Outcome／Provider／Exception Sentinelと高Cardinality Metric LabelがCollector Logに存在しないことを否定検証する。
- Collector停止中もPrimary OperationとReadinessが既存Contractどおり動作することを確認する。
- Consumer終了時にCollector、Network、Volume、一時CredentialなしArtifactを削除する。

Local CollectorはRuntime Default、Framework-owned Exporter、Production Deployment Templateではない。

## Recommended Delivery Boundary

Question 1〜8でAを採用する場合、Production Deliveryは少なくとも次へ分割する。

1. Structured Record Schema、Canonical Formatter、Application／Framework／Journal／Audit Safe Projection
2. Telemetry Context、W3C Propagation、Deferred／Outbox／Retry Persistence、Lifecycle Record Correlation
3. OpenTelemetry Trace Adapter、Span Lifecycle、Application Provider Composition
4. Metric Adapter、低Cardinality Operation／Worker／Outbox／Scheduler／Failure Metrics
5. Liveness／Readiness Query、Safe Result、Explicit HTTP／CLI Adapter、Operational Consumer Evidence
6. Guide、Security、Deployment、Troubleshooting、Reference、Documentation Review

各Production SliceはTask Packet作成後にRepository設定どおりGPT-5.6 Luna High workerへ依頼する。DocumentationはProduction Acceptance後に更新し、Read-only Documentation Reviewerで受入する。

## Non-goals

- OpenTelemetry SDK／Collector／OTLP ExporterのFramework-owned構築
- Vendor固有Backend、Dashboard、Alert Rule、SLO／Retention Policy
- Exporter Endpoint／Header／CredentialのBlackOps Config管理
- Baggageの受理／永続化／伝播
- Application任意Message／Contextの完全な機密情報検出
- Raw Tenant／Actor／Key／Payload／Outcome／Exception DetailのTelemetry出力
- Public Probe Routeの自動登録、Built-in HTTP Server
- Database／Host／Container／PHP Runtimeの汎用Monitoring Agent
- Exactly-once Telemetry Delivery
- CloudWatch専用Adapter、Remote Log Sink

## External References

- [OpenTelemetry PHP](https://opentelemetry.io/docs/languages/php/)
- [OpenTelemetry PHP Manual Instrumentation](https://opentelemetry.io/docs/languages/php/instrumentation/)
- [OpenTelemetry PHP Context Propagation](https://opentelemetry.io/docs/languages/php/propagation/)
- [OpenTelemetry Semantic Conventions](https://opentelemetry.io/docs/specs/semconv/)
- [OpenTelemetry Metric Naming](https://opentelemetry.io/docs/specs/semconv/general/naming/)
- [Install the Collector with Docker](https://opentelemetry.io/docs/collector/install/docker/)
- [OpenTelemetry Collector v0.157.0](https://github.com/open-telemetry/opentelemetry-collector-releases/releases/tag/v0.157.0)

## Traceability

- [D014 Logging and Traceability](014-logging-and-traceability.md)
- [D015 Log Delivery and Retention](015-log-delivery-and-retention.md)
- [D099 Production Logging Configuration](099-production-logging-configuration.md)
- [D135 Tenant Isolation and Protected Operation Data](135-tenant-isolation-and-protected-operation-data.md)
- [Logging and Traceability](../spec/10-logging-and-traceability.md)
- [Runtime and Dependency Injection](../spec/09-runtime-and-di.md)
- [Post Phase 10 Roadmap](../spec/60-post-phase-10-roadmap.md)
- [Journal Documentation](../spec/94-journal-documentation.md)
- [Tenant Isolation and Protected Operation Data](../spec/99-tenant-isolation-and-protected-operation-data.md)
