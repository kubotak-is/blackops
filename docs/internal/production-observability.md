# Production Observability Responsibility Boundary

BlackOpsのProduction ObservabilityはOperation IDを共通Join Keyにする。FrameworkはHTTP Error、Application Log、Framework Error Log、Canonical Journal、Diagnosticsを同じIDで相関可能にするが、Log Aggregatorや監視基盤そのものは運用しない。Repository `main`では、これにOpenTelemetry API-only AdapterとOperational Health Queryを追加している。Stable `1.1.0`のProduction Contractには含めない。

## Correlation Matrix

| Surface | Operation成立前 | Operation成立後 |
| --- | --- | --- |
| HTTP 500 | `status=error`、`code=internal_error`。Operation IDなし | 同じSafe Shapeと発行済みOperation ID |
| Application／Framework Log | `operation` Fieldなし | Operation／Attempt／Correlation／Causation ID |
| Canonical Journal | Recordなし | Terminal Lifecycle、または安全な記録失敗Evidence |
| Terminal／Viewer | Operationを検索できない | 同じSafe Diagnostics Aggregateを表示 |

Application HTTP Handlerの共通最外周Boundaryは、DB prepare、PSR-15 Middleware、Observer flush、Connection cleanup等の非Operation ThrowableをIDなしSafe JSON 500へ変換する。Classic EntrypointとFrankenPHP Worker Modeは同じApplication Handlerを呼ぶため、この境界を共有する。Request生成、Response emit等のHandler外FailureだけはEntrypointの最終Fallbackが扱う。

InlineとDeferredは同じ予約Fieldを使う。Deferred Workerは受付時のOperation／Correlation IDを維持し、AttemptごとにAttempt IDだけを更新する。Nested Operation終了後は親Scopeを復元し、Request／Attempt終了後はScopeを空にする。

## Safe Projection Matrix

| Data | Log | Observed Journal | Terminal | Viewer |
| --- | --- | --- | --- | --- |
| Operation／Correlation ID | 表示 | 表示 | 表示 | 表示 |
| Actor ID | `[masked]` | `[masked]` | `[masked]` | `[masked]` |
| Credential／Raw Value | 非表示 | 非表示 | 非表示 | 非表示 |
| Exception Type／Safe classification | Framework Logだけ | Safe Typeのみ | Safe Typeのみ | Safe Typeのみ |
| Exception／Dead Letter Message | 非表示 | 非表示 | 非表示 | 非表示 |

Canonical JournalとDead Letter Storeは復旧・監査用Restricted DataとしてMessageを保持し得る。Observer、Diagnostics、CLI、Viewerへは共通Safe Projectionを通したAggregateだけを渡す。Missing、Fully Purged、将来のUnauthorized相当は同じ`operation.unavailable` Surfaceへ畳み、存在差を公開しない。

## Ownership

| Area | Framework | Application／Infrastructure |
| --- | --- | --- |
| Correlation | Operation ID発行、Scope、予約Field、HTTP／Journal相関 | Support／Incident WorkflowでIDを維持 |
| Data safety | Sensitive Filter、Actor Mask、Safe Failure Type | Domain Contextへ秘密値を入れない、認証認可 |
| Built-in sink | JSONL、stderr既定、限定Local Stream、Best-effort境界 | Stream以降の収集と配送 |
| Local file | 絶対Pathの受理。Directoryは作らない | Directory、Permission、Disk Capacity、Rotation |
| Retention | Canonical Retention Contract | Log保持期間、Backup、削除Policy |
| Access | Local CLI／Viewerの安全なSurface | OS、Database、Log基盤のAccess Control |
| Detection | Operation IDを検索可能にする | Alert Rule、On-call、Delivery確認 |

Backend FailureはPrimary Throwable、HTTP Result、Journal、Worker Loopを変えず、別SinkへFallbackしない。このためApplication／InfrastructureはSink到達、Disk使用量、Collector Deliveryを別途監視する。

ProductionでViewerは既定無効である。通常HTTP RuntimeやWorker RuntimeはViewer ServerをBindせず、Tokenも生成しない。ViewerはDevelopment Localで明示Commandを実行したときだけ起動する。

## OpenTelemetry API-only Boundary

FrameworkのProduction Dependencyは`open-telemetry/api`だけである。`ApplicationBuilder::withTracerProvider()`と`withMeterProvider()`はApplicationが構成したProviderを受け取り、Framework-owned SDK、OTLP Exporter、Resource、Endpoint、Credential、CollectorをCompiled ContainerやManifestへ保存しない。Provider未登録時はNo-opで、Provider／Exporter／Flush FailureはPrimary Operation、Journal、Outcome、HTTP Response、Readinessを変更しない。

Framework-owned Spanは`blackops.framework`／`1.1.0`で、HTTP／DB InstrumentationやDashboardはApplicationへ委ねる。HTTPのW3C `traceparent`／`tracestate`、Deferred／Outboxの暗号化Context、Worker RetryのAttempt Spanを境界ごとに扱う。Structured JSONLへ投影するのは`traceId`、`spanId`、`sampled`だけで、Actor／Tenantは`[masked]`とする。Metricは固定Name／Type／Unitと有限属性で、Identity／自由文をLabelへ入れない。

## Health／Readiness Boundary

`OperationalHealthQuery`はApplication-owned Checkを組み合わせるPublic Queryである。LivenessはQueryが応答できることだけ、ReadinessはCompiled Artifact、Runtime Configuration、Database、Migration Compatibility、Storage Key Provider、Runtime ServicesのBounded Checkだけを扱う。OTLP Exporter、Collector、Sampling、Dashboard、Remote BackendはReadinessへ含めない。`OperationalHealthRequestHandler`と`OperationalHealthCliAdapter`はApplicationが明示Route／Commandへ登録し、Frameworkは標準 `/health`／`/ready` を自動公開しない。

Local CollectorはConsumer／Integration専用のDocker Networkで、固定DigestのOfficial ImageとOTLP HTTP `4318`を使う。FrameworkのProduction Compose Defaultへ追加せず、Remote Backend、Credential、Production Deployへ流用しない。
