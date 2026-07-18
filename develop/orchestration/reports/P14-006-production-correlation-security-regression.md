# P14-006 Production Correlation and Security Regression Report

Status: Accepted

## Summary

D099のBuilt-in JSONL Backend ConfigurationをInstalled ApplicationのHTTP／Worker Runtimeへ接続した。Optional `config/logging.php`はApplication Configuration Snapshotへ一度だけ読み込み、`jsonl`、限定Local Stream、Channel、PSR-3 Levelを厳密検証する。Config欠落時も`php://stderr`／`blackops`／`info`を使用し、Disable、Custom Driver、Remote Handler、暗黙Fallbackは追加していない。

HTTPとWorkerはProcess CompositionごとにMonolog Backendと`ExecutionScopedLogger`を一度だけ生成する。Compiled Containerの`LoggerInterface`とFramework Failure Reporterは同じDecoratorを共有する。Deferred Worker Failureも受付時のOperation／Correlation IDと現在Attempt IDを使う安全なFramework Error Logへ接続した。

既存のHTTP Error、Execution Scope、Diagnostics、CLI、Viewer、Observed Projection回帰をTask指定Targetで再検証し、Production Observabilityの責任分界をInternal Documentationへ同期した。

## Changed Files

- `src/Internal/Application/ApplicationConfigurationLoader.php`
- `src/Internal/Application/ApplicationLoggingConfiguration.php`
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php`
- `src/Internal/Application/ApplicationHttpRequestHandler.php`
- `src/Internal/Application/ApplicationWorkerComposer.php`
- `src/Http/Responder/JsonOperationResponder.php`
- `src/Internal/Execution/DeferredWorkerRuntime.php`
- `src/Internal/Execution/DeferredWorkerRuntimeStorage.php`
- `src/Internal/Logging/FrameworkOperationFailureReporter.php`
- `src/Internal/Logging/ExecutionScopedLogger.php`
- `tests/Internal/Application/ApplicationConfigurationLoaderTest.php`
- `tests/Internal/Application/ApplicationHttpRequestHandlerTest.php`
- `tests/Internal/Application/ApplicationLoggingConfigurationTest.php`
- `tests/Internal/Execution/DeferredWorkerRuntimeTest.php`
- `tests/Internal/Logging/ExecutionScopedLoggerTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `docs/internal/execution-scoped-logger.md`
- `docs/internal/monolog-jsonl-backend.md`
- `docs/internal/production-observability.md`
- `docs/internal/README.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`

## Decisions and Assumptions

- `config/logging.php`の正規化はInternal immutable valueである`ApplicationLoggingConfiguration`へ隔離した。
- Config LoaderがFile return valueをArrayへ限定し、Logging normalizerが`backend`と4 Canonical Keyを限定する二段階Validationにした。
- Local File Pathは`/`開始、NULなし、`://`なしを条件とし、Directory作成、Permission変更、事前Open／truncateを行わない。
- Backend Open／Write FailureはFactoryで隠さず、Application ServiceとFailure Reporterが必ず通る`ExecutionScopedLogger`だけで吸収する。
- Deferred Failure Reporterは既存の`FrameworkOperationFailureReporter`を再利用し、Journal supervision成功可否と二次Failure Typeを同じSafe Envelopeへ渡す。
- Diagnostics／CLI／Viewer／Observed Projectionは既存の共通Safe AggregateでTask Contractを満たしていたため、Production Codeを変更せず回帰Testで固定した。

## Logging Configuration Matrix

| Input | Result |
| --- | --- |
| File／`logging`／`backend`欠落 | `jsonl`, `php://stderr`, `blackops`, `info` |
| `php://stderr`／`php://stdout` | Accepted |
| `/var/log/blackops/application.jsonl` | Accepted |
| Relative／empty／NUL／任意Wrapper／Network URI | Rejected |
| PSR-3 lowercase 8 Level | Accepted |
| Uppercase／Numeric／Unknown Level | Rejected |
| Empty／trim不一致／Control／invalid UTF-8 Channel | Rejected |
| Disable Key／Unknown Key／Custom Driver／non-string | Rejected |

Invalid Exceptionは固定分類Messageだけを持ち、Config値、Path、Credentialを反射しない。

## Snapshot / Composition Evidence

- `ApplicationConfigurationLoader`の固定File Listへ`logging`を追加した。
- Loader Testで読込後に`logging.php`を変更しても既に得たSnapshot inputが変わらないことを確認した。
- HTTP／Worker Composerはそれぞれ`configuration()`から一度だけnormalizerを呼び、Factoryを一度だけ呼ぶ。Request、Attempt、Record pathにはConfig／Environment参照を置いていない。
- Container injectorが返す一つの`ExecutionScopedLogger`をHTTP Runtime DependenciesとWorker Failure Reporterへ渡す。
- ConfigなしのIntegration／Full Test実出力で、stderrに`channel=blackops`、`level_name=INFO`のJSONLが出ることを確認した。
- HTTP Runtimeはcustom absolute stream／`http-custom` channel／`warning` minimum levelを実Compositionへ渡し、INFOを除外してWARNING一行だけをJSONLへ出すIntegration Testを追加した。
- Worker Runtimeはcustom absolute stream／`worker-custom` channel／`error` minimum levelを実Compositionへ渡し、Deferred Framework FailureのERROR一行をJSONLへ出すIntegration Testを追加した。

## Correlation Matrix

| Surface | Before Operation ID | After Operation ID |
| --- | --- | --- |
| HTTP 500 | Safe `internal_error`、Operation IDなし | Safe `internal_error`、発行済みOperation ID |
| Framework Log | Operation Fieldなし | Operation／Attempt／Correlation／Causation |
| Canonical Journal | Recordなし | Terminal Lifecycleまたは安全な記録失敗Evidence |

Application HTTP Handlerの共通最外周Boundaryが、DB prepare、PSR-15相当Throwable、Observer flush、Transaction cleanup FailureをIDなしSafe JSON 500へ変換する。Framework LogはSafe classification／Typeだけを持ち、`operation`とException Messageを持たない。ClassicとFrankenPHP Workerは同じHandlerを使う。

Operation成立後のInline Failureは共通外側Boundaryへ到達する前に既存内側Boundaryが処理し、Application IntegrationでもResponse／Framework Logの同一Operation IDを維持する。Deferred handler failureも受付Operation ID、Correlation ID、現在Attempt ID、Operation TypeをFramework Logへ保持する。Exception MessageはLogへ出ない。

## Scope Cleanup Matrix

| Case | Evidence |
| --- | --- |
| Nested Operation | Child終了後にparentを復元 |
| Throwable | `finally`でemptyへ復元 |
| HTTP failure | Response／Journal／Log後にempty |
| DB prepare／Middleware failure | IDなし500後、次Requestのconnection／scope回復 |
| Observer／cleanup failure | IDなし500後、次Requestのconnection／scope回復 |
| Deferred failure | Supervision／Framework Log後にempty |
| Multi-request | FrankenPHP Worker ConsumerでIsolation成功 |
| Classic fallback | Consumerで同じfailure boundary成功 |

## Safe Projection Matrix

| Surface | Safe Evidence |
| --- | --- |
| Application／Framework Log | Sensitive Filter、Actor ID `[masked]`、Failure Typeのみ |
| Observed Journal | Raw Value、Credential、unmasked Actor IDを除外 |
| Diagnostics Aggregate | Canonical Object／Throwable／Stored Messageを保持しない |
| CLI／Viewer | 同じAggregate、Exception／Dead Letter Message非表示 |
| Availability | Missing／Fully Purged／Unauthorized相当をUnavailableへ統一 |
| Viewer default | Config欠落時Disabled、明示CommandなしでBind／Token生成なし |

Target SuiteがLogging、HTTP、Diagnostics、CLI、Viewer、Projectionをまとめて検証し、218 tests / 1255 assertionsで成功した。

## Failure Injection

- Inner loggerがThrowableを投げてもApplication LogとFramework Logから外へ出ない。
- HTTP logger failureが500、Terminal Journal、Primary Failure、Scope cleanupを変えない。
- Deferred WorkerはFramework Log backend failure時もDecoratorが吸収し、Supervision／Worker resultを変えない共通境界を使用する。
- Deferred supervisionのJournal記録が失敗した場合は二次Failure Typeだけを安全にLogへ渡し、既存のthrow semanticsを維持する。
- 別StreamへFallbackしない。

## Orchestrator Review Correction

初回Worker ReportはOperation成立前500を既存lower-level TestとWorker Entrypoint fallbackだけで充足したと誤認していた。実際には`ApplicationHttpRequestHandler`のDB prepare／Middleware／cleanup Throwableがescapeし、Classic `public/index.php`には同等catchがなかった。

Review修正としてApplication Compositionの共通最外周Boundaryを`ApplicationHttpRequestHandler`へ実装した。非Operation Throwableを`{"status":"error","code":"internal_error"}`へ変換し、同じExecutionScopedLoggerへOperation fieldなしのSafe classification／Typeだけを記録する。成立後`OperationExecutionFailed`は既存内側BoundaryでOperation ID付きのまま維持する。Entrypoint catchはRequest作成／Emitter等の最終Fallbackとして維持できる。

初回Reportのcustom Backend Evidenceもnormalizer中心で不十分だったため、HTTPとWorkerの実Application Compositionからcustom stream／channel／minimum levelのJSONLを検証するIntegration Testへ補強した。

## Responsibility Boundary

FrameworkはOperation ID相関、Safe Envelope、Sensitive Filter、Actor Mask、Built-in JSONL、Best-effort書込境界を所有する。

Application／Infrastructureはstdout／stderrまたはLocal File以降の収集、Delivery保証、Directory／Permission、Rotation、Retention、Disk Capacity、Access Control、Alertを所有する。FrameworkはLog到達、保存期間、Alert発火を保証しない。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstart valid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: 成功。No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations <P14-006 required target tests>
Result: OK (218 tests, 1255 assertions)。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1232 tests, 4497 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2225 / Warnings 0 / Errors 0。

bash tests/Consumer/frankenphp-worker-mode.sh
Result: Worker bootstrap、per-request flush、rejection／disconnect後isolation、DB reconnect、multi-request、restart／memory、Classic fallback、correlated failure boundary成功。Consumer E2E passed。

Management Comment ID、Internal PublicApi、Logging Disable／Remote URI、diff check Guard
Result: 成功。

Global forbidden-observability Guard
Result: 既存class名の`OperationDefinitionCollector`とFastRouteの`RouteCollector`が`Collector` substringへ一致した。P14-006差分の`src`／`tests`にはTelemetry、Metric、Dashboard、Remote Collectorを追加していないことを差分Guardで確認した。
```

## Acceptance Criteria

- [x] Canonical Configと安全な既定を実装した
- [x] ConfigなしでもJSONL stderr／blackops／infoとなりDisableされない
- [x] 限定Stream／Level／Keyを厳密Validationした
- [x] Invalid ConfigをRequest／Attempt前に安全にFail-fastする
- [x] Snapshot後のFile変更がRuntime inputへ反映されない
- [x] HTTP／WorkerがProcess CompositionごとにBackendを一度だけ生成する
- [x] Application ServiceとFramework Failure Reporterが同じDecoratorを共有する
- [x] Backend FailureをBest-effortで吸収し、Fallbackしない
- [x] Operation成立前後の500／Log／Journal ID境界を回帰した
- [x] Application／Framework Logが共通予約Field Shapeを使う
- [x] Inline／Deferred、Classic／Worker、Nested／Sequential相関を回帰した
- [x] Multi-request／Multi-attempt／Failure後のScope cleanupを回帰した
- [x] Credential、Raw Value、Actor ID、Exception／Dead Letter MessageをSafe Surfaceへ出さない
- [x] Unavailable SurfaceとViewer既定無効を回帰した
- [x] Production Observability責任分界をInternal Documentationへ同期した
- [x] 禁止機能、Migration、Public APIを追加していない
- [x] Target／Full PHPUnit、Composer、Mago、Deptrac、Consumer、Guardを完走した
- [x] WorkerはCommitしていない

## Remaining Issues

P14-006を妨げるBlockerはない。Global `Collector` substring Guardは既存のDefinition／Routing class名へ一致するため、差分に禁止Observability機能がないことを別途確認した。

Quickstart／Skeleton／Guide／Consumer diagnostics journeyの同期はTask PacketどおりP14-007で行う。

## Orchestrator Review

Logging ConfigのStrict Validation、HTTP／Worker Composition、Operation成立前後の500境界、Deferred Failure Reporter、Scope復元、Safe Projection、運用責任分界を差分Reviewした。初回指摘だった共通外側Safe 500境界と実Backend Integration Evidenceが修正されていることを確認し、Orchestratorが次を独立実行した。

```text
docker compose run --rm app vendor/bin/phpunit --display-deprecations <P14-006 orchestrator critical targets>
Result: OK (220 tests, 1318 assertions)。Logging Config、HTTP／Console Integration、外側Safe 500、Inline／Deferred、Scope、Diagnostics、CLI、Viewer、Projectionを含む。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1232 tests, 4497 assertions)。

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app vendor/bin/mago format --check src tests examples
docker compose run --rm app vendor/bin/mago lint
docker compose run --rm app vendor/bin/mago analyze
docker compose run --rm app vendor/bin/deptrac
Result: Composer Root／Quickstart valid。Mago全成功。Deptrac Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2225 / Warnings 0 / Errors 0。

bash tests/Consumer/frankenphp-worker-mode.sh
Result: Worker bootstrap、Request isolation、DB reconnect、Classic fallback、correlated failure boundaryを含めConsumer E2E passed。

Management Comment ID、Internal PublicApi、Logging Disable／Remote URI、P14-006差分Forbidden Observability、git diff --check Guard
Result: 成功。
```

Review指摘修正と独立品質Gateがすべて成功したため、P14-006をAcceptedとした。

## Suggested Next Action

P14-006をCommit／Pushし、P14-007 Consumer Experience and Closeoutへ進む。
