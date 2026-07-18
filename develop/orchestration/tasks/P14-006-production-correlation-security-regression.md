# P14-006: Production Correlation and Security Regression

Status: Ready

## Goal

D099で確定したBuilt-in JSONL Backend ConfigurationをInstalled ApplicationのHTTP／Worker Runtimeへ接続し、Operation成立前後、Inline／Deferred、Classic／Long-running、Nested Operationで相関FieldとExecution Scopeが混線しないことを固定する。

Application Log、Framework Error Log、Observer、Terminal、Viewerは同じSafe Projection境界を維持し、Credential、Raw Value、Mask前Actor ID、Exception／Dead Letter Messageを出さない。Production運用ではFrameworkがOperation ID相関までを所有し、Sink Delivery、Retention、Rotation、Alert、CollectorをApplication／Infrastructure責務として明文化する。

## In Scope

- Optional `config/logging.php`のConfiguration Snapshot読込
- Built-in JSONL Backendの`driver`／`stream`／`channel`／`minimum_level`
- Config欠落時の`jsonl`／`php://stderr`／`blackops`／`info`既定
- `php://stderr`、`php://stdout`、絶対Local File PathだけのStream許可
- Invalid ConfigのRuntime Composition時Fail-fastと安全なError
- HTTP／Worker Runtime CompositionごとにBackendを一度だけ構成
- `ExecutionScopedLogger`をContainerの`LoggerInterface`とFramework Failure Reporterで共有
- Runtime Backend Open／Write FailureのBest-effort吸収と暗黙Fallback禁止
- Operation成立前のSystem／Middleware Failure 500とOperation ID非発行境界
- Operation成立後の500、Framework Error Log、Journalの同一Operation ID境界
- HTTP Multi-request、Deferred Multi-attempt、Nested Operation、Failure後Request／AttemptのScope復元
- Classic CompositionとFrankenPHP Worker Modeが同じHTTP／Log相関Contractを使う回帰
- Inline／DeferredのApplication Log予約Field Shape比較
- Failure／Dead Letter Safe ProjectionのObserver／CLI／Viewer一貫性回帰
- Missing／Fully Purged／Unauthorized相当を同じUnavailable Surfaceへ畳むFixture
- Production Config欠落時にViewerが無効で、自動起動／Bindしない回帰
- Production Log Sink／Retention／Rotation／AlertとFramework責務のInternal Documentation

## Out of Scope

- Custom PSR-3 Backend Public Selection、Driver Registry、Container Service優先順位
- Logging Disable Switch、Null Logger設定、別Sinkへの暗黙Fallback
- Remote Handler、Network URI、任意PHP Wrapper
- Log Rotation、Directory自動作成、File Permission変更、Disk管理
- OpenTelemetry、Remote Collector、Metric、Dashboard、Tracing Adapter
- Public PHP Diagnostics API、Status／Outcome HTTP API、Tenant Authorization
- Canonical Raw Download、Sensitive／Error Detail Override
- Migration、DDL、Schema変更
- Quickstart Failure Tutorial、Skeleton、Guide、Documentation Website同期
- Documentation Website公開

## Relevant Specifications and Decisions

- `develop/spec/02-lifecycle-and-journal.md`
- `develop/spec/05-http.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/decisions/085-http-configuration-snapshot-lifecycle.md`
- `develop/decisions/097-phase-14-operation-diagnostics.md`
- `develop/decisions/098-deferred-acceptance-failure-lifecycle.md`
- `develop/decisions/099-production-logging-configuration.md`

## Files Allowed to Change

### Production

- `src/Internal/Application/ApplicationConfigurationLoader.php`
- 新規`src/Internal/Application/ApplicationLogging*.php`
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php`
- `src/Internal/Application/ApplicationWorkerComposer.php`
- `src/Internal/Application/ApplicationHttpRequestHandler.php`（成立前Failure／Scope cleanupに必要な場合だけ）
- `src/Internal/Logging/RuntimeLoggingServiceInjector.php`
- `src/Internal/Logging/MonologJsonlLoggerFactory.php`
- `src/Internal/Logging/ExecutionScopedLogger.php`
- `src/Internal/Logging/FrameworkOperationFailureReporter.php`
- P14-006のSystem／Deferred Safe Failure相関に必要な新規`src/Internal/Logging/*.php`
- `src/Internal/Runtime/ProductionRuntimeComposer.php`
- `src/Internal/Runtime/ProductionRuntimeDependencies.php`
- `src/Internal/Runtime/ProductionRuntimeComposition.php`
- `src/Internal/Http/OperationFailureErrorBoundary.php`
- P14-006のOperation成立前Safe 500に必要な新規`src/Internal/Http/*.php`
- `src/Http/Responder/JsonOperationResponder.php`
- `src/Internal/Execution/ExecutionScopeProvider.php`
- `src/Internal/Execution/DeferredWorkerRuntime.php`
- `src/Internal/Execution/DeferredWorkerRuntimeStorage.php`
- `src/Internal/Execution/DeferredFailureSupervisor.php`
- `src/Internal/Execution/DeferredLeaseExpiredRecovery.php`
- `src/Internal/Projection/ObservedJournalRecordProjector.php`（回帰でSafe Projection不整合が判明した場合だけ）
- `src/Internal/Diagnostics/DiagnosticsSafeProjector.php`（同上）
- `src/Internal/Console/OperationInspect*.php`（同じSafe Aggregateから逸脱している場合だけ）
- `src/Internal/Diagnostics/Viewer/*.php`（同じSafe Aggregateから逸脱している場合だけ）
- `examples/quickstart/public/index.php`／`worker.php`（Classic／Workerの成立前500 Contract同期が必要な場合だけ。Tutorial追加はしない）

### Tests

- 新規`tests/Internal/Application/ApplicationLogging*.php`
- `tests/Internal/Application/ApplicationConfigurationLoaderTest.php`
- `tests/Internal/Logging/RuntimeLoggingServiceInjectorTest.php`
- `tests/Internal/Logging/MonologJsonlLoggerFactoryTest.php`
- `tests/Internal/Logging/ExecutionScopedLoggerTest.php`
- P14-006のCorrelation Matrixに必要な新規`tests/Internal/Logging/*.php`
- `tests/Internal/Runtime/ProductionRuntimeComposerTest.php`
- `tests/Internal/Application/ApplicationHttpRequestHandlerTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Internal/Http/OperationFailureErrorBoundaryTest.php`
- P14-006の成立前Safe 500に必要な新規`tests/Internal/Http/*.php`
- `tests/Http/OperationRequestHandlerTest.php`
- `tests/Http/DeferredOperationRequestHandlerTest.php`
- `tests/Internal/Execution/ExecutionScopeProviderTest.php`
- `tests/Internal/Execution/DeferredWorkerRuntimeTest.php`
- `tests/Internal/Execution/DeferredWorkerLoopTest.php`
- `tests/Internal/Diagnostics/*.php`
- `tests/Internal/Console/OperationInspect*.php`
- `tests/Internal/Diagnostics/Viewer/*.php`
- `tests/Internal/Projection/*.php`
- `tests/Logging/*.php`
- `tests/Consumer/frankenphp-worker-mode.sh`（追加のCorrelation回帰に必要な場合だけ）

### Documentation, Specification and Orchestration

- `docs/internal/execution-scoped-logger.md`
- `docs/internal/monolog-jsonl-backend.md`
- 新規`docs/internal/production-observability.md`
- `docs/internal/README.md`
- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/TODO.md`
- `develop/orchestration/reports/P14-006-production-correlation-security-regression.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Logging Configuration Contract

Canonical Shapeは次とする。

```php
return [
    'backend' => [
        'driver' => 'jsonl',
        'stream' => 'php://stderr',
        'channel' => 'blackops',
        'minimum_level' => 'info',
    ],
];
```

- File欠落、`logging` Key欠落、`backend` Key欠落は上記Framework既定を使用する
- `logging`と`backend`はArray、4 KeyはStringだけを受け付ける
- `driver`は厳密な`jsonl`だけを受け付ける
- `stream`は厳密な`php://stderr`、`php://stdout`、または`/`から始まる絶対Local File Pathだけを受け付ける
- Relative Path、空文字、NUL、その他の`://` Wrapper、Network URIを拒否する
- `channel`は空文字、前後Whitespace、Control Characterを拒否する。Config値をErrorへ反射しない
- `minimum_level`はPSR-3の8 Level名をlowercase完全一致で受け付ける。Trim、Case変換、Numeric変換を行わない
- `enabled`、Custom Driver、Handler List、Formatter、Credential Keyを受け付けない
- Invalid ConfigはHTTP／Worker Runtime Composition時に安全なBootstrap Failureとし、Request／Attempt開始前に失敗する
- Local File Directoryを暗黙作成せず、FileをValidation目的で事前truncateしない
- Backend Open／Write Failureは最初の発生時を含め`ExecutionScopedLogger`が吸収し、別StreamへFallbackしない

## Snapshot and Composition Contract

- `ApplicationConfigurationLoader`は`logging.php`をApplication作成時に一度だけ読む
- Application作成後のConfig Fileと`$_ENV`変更はHTTP Request、Worker Attempt、Log Recordへ反映しない
- HTTP Runtime CompositionとWorker Runtime Compositionは各ProcessのSnapshotからBackendを一度だけ生成する
- Handler／Policy／Application Serviceが注入される`LoggerInterface`とFramework Failure Reporterは同じ`ExecutionScopedLogger` Instanceを共有する
- `ExecutionScopedLogger`を通さずMonolog BackendをApplication Serviceへ直接注入しない
- Operation外LogはOperation Fieldを持たず、Operation内Logは予約Fieldを持つ
- Framework Error LogとApplication Logは`schemaVersion`、`kind`、`context`、`operation`の同じEnvelope Shapeを使う

## Correlation Matrix

| Surface | Before Operation ID | After Operation ID |
| --- | --- | --- |
| HTTP 500 | `status=error`, `code=internal_error`, Operation IDなし | 同じShape + 発行済みOperation ID |
| Framework Log | Safe classification/type、Operation Fieldなし | Safe classification/type + Operation／Attempt／Correlation／Causation |
| Canonical Journal | Recordなし | Terminal Lifecycleまたは安全な記録失敗Evidence |

- Middleware／Runtime FailureがOperation成立前ならOperation IDを架空発行しない
- Operation成立後はHTTP、Framework Log、Canonical Journalが同じOperation IDを使う
- ClassicとWorker Modeで同じ境界を使い、Entrypoint固有ResponseへException Messageを出さない
- Nested Operation終了後は親Scopeを復元し、Request／Attempt終了後はScopeをEmptyにする
- Handler Failure、Logger Failure、Observer Failure、Database Cleanup Failureの後も次のRequest／AttemptへScopeを持ち越さない

## Safe Projection and Availability Contract

- Logger Context、Observed Journal、Diagnostics、CLI、ViewerはCredential、Raw Operation Value、Mask前Actor IDを出さない
- Framework FailureはSafe ClassificationとTypeだけを表示可能とし、Exception Message、Stack Trace、Argument、Previous Detailを出さない
- Dead LetterはSafe Type／Classification／時刻だけをProjectionし、Stored MessageをCLI／Viewer／Logへ出さない
- Logger／Observer／Diagnostics Backend FailureのMessage、DSN、SQL、File内容をHTTP／Terminal／Viewerへ出さない
- Missing、Fully Purged、Unauthorized相当は同じ`OperationDiagnosticsUnavailable`からCLI Exit 3／Viewer 404へ到達し、存在差を出さない
- Production Config欠落時のViewerはDisabledであり、HTTP Runtime／Worker Runtime／Console listでSocket BindまたはToken生成を行わない

## Responsibility Boundary

FrameworkはOperation ID相関、Safe Envelope、Sensitive Filter、既定JSONL Backend、Best-effort書込境界を所有する。

Application／Infrastructureはstdout／stderrまたはLocal File以降の収集、Delivery保証、Rotation、Retention、Disk Capacity、Access Control、Alert、Collectorを所有する。FrameworkはLog到達、保存期間、Alert発火を保証しない。

## Acceptance Criteria

- [ ] D099のCanonical Configと安全な既定を実装する
- [ ] ConfigなしでもJSONL stderr／blackops／infoになり、Disableされない
- [ ] stderr／stdout／絶対Pathを受理し、Relative／Wrapper／Network／Invalid Level／Custom Keyを拒否する
- [ ] Invalid ConfigがRequest／Attempt前にFail-fastし、値、Path、CredentialをErrorへ出さない
- [ ] Snapshot作成後にConfig／Environmentを変更してもRuntime Backendが変わらない
- [ ] HTTPとWorkerが各ProcessでBackendを一度だけ構成し、Log Recordごとに再構成しない
- [ ] Application ServiceとFramework Failure Reporterが同じExecutionScopedLoggerを使う
- [ ] Runtime Backend FailureがPrimary Throwable、HTTP Result、Journal、Worker継続を変えず、Fallbackしない
- [ ] Operation成立前500がSafe JSONでOperation IDなし、成立後500が発行済みIDを持つ
- [ ] Application LogとFramework Error Logが同じ予約Field Shapeを使う
- [ ] Inline／Deferred、Classic／Worker、Nested／SequentialのCorrelation Matrixを再現する
- [ ] Multi-request／Multi-attempt／Failure後でもScopeがEmptyまたは親へ復元される
- [ ] Actor ID、Credential、Raw Value、Exception／Dead Letter MessageがHTTP／Log／Observer／CLI／Viewerへ出ない
- [ ] Missing／Fully Purged／Unauthorized相当が同じUnavailable Surfaceになる
- [ ] Production Config欠落時にViewerが無効で自動起動しない
- [ ] Internal DocumentationがFrameworkとApplication／Infrastructureの責任分界を明示する
- [ ] Logging Disable、Custom Driver、Remote Handler、OTel、Metric、Dashboard、Migrationを追加しない
- [ ] Target／Full PHPUnit、Composer、Mago、Deptrac、Guardが成功する
- [ ] Report／STATEを更新し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Internal/Application/ApplicationLoggingConfigurationTest.php \
  tests/Internal/Application/ApplicationConfigurationLoaderTest.php \
  tests/Internal/Logging \
  tests/Internal/Runtime/ProductionRuntimeComposerTest.php \
  tests/Internal/Application/ApplicationHttpRequestHandlerTest.php \
  tests/Integration/ApplicationHttpRuntimeTest.php \
  tests/Internal/Http \
  tests/Http/OperationRequestHandlerTest.php \
  tests/Http/DeferredOperationRequestHandlerTest.php \
  tests/Internal/Execution/ExecutionScopeProviderTest.php \
  tests/Internal/Execution/DeferredWorkerRuntimeTest.php \
  tests/Internal/Execution/DeferredWorkerLoopTest.php \
  tests/Internal/Diagnostics \
  tests/Internal/Console/OperationInspectCommandTest.php \
  tests/Internal/Diagnostics/Viewer \
  tests/Internal/Projection
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/frankenphp-worker-mode.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
! rg -n '#\[PublicApi\]' src/Internal/Application/ApplicationLogging*.php src/Internal/Logging
! rg -n 'enabled|NullLogger|http://|https://|tcp://|udp://' src/Internal/Application/ApplicationLogging*.php
! rg -n 'OpenTelemetry|Metric|Dashboard|Collector' src tests --glob '*.php'
git diff --check
```

責務分割によりTest File名が異なる場合は、実在するP14-006対象Testをすべて指定して同等以上の範囲を実行する。否定Testや既存FixtureへGuardが反応する場合は、Production Surfaceに禁止機能がないことをReportへ具体的に記録する。

Consumer ScriptがExternal NetworkまたはPublicationを要求する場合は範囲を広げず、実行できない理由をReportへ記録する。

## Expected Report

`develop/orchestration/reports/P14-006-production-correlation-security-regression.md`へSummary、Changed Files、Decisions and Assumptions、Logging Configuration Matrix、Snapshot／Composition Evidence、Correlation Matrix、Scope Cleanup Matrix、Safe Projection Matrix、Failure Injection、Responsibility Boundary、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
