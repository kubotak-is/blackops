# P14-002 Operation Failure and Runtime Correlation Report

Status: Accepted

## Summary

Operation ID発行後の予期しないThrowableを内部Failure Carrierで保持し、Inline Attempt Failureを`attempt.failed -> operation.failed`、Deferred受付のAttempt開始前FailureをAttemptなしの`received -> operation.failed`へTerminal化した。

Internal HTTP Error Boundaryは発行済みOperation IDだけを含むSafe JSON 500を返し、同じOperation／Attempt／Correlation／Causation IDをFramework Error Logへ付与する。Compiled Containerの`Psr\Log\LoggerInterface`は、HTTP RuntimeとDeferred Worker Runtimeが共有する`ExecutionScopeProvider`へ接続した同一`ExecutionScopedLogger` Instanceとして解決する。

Safe JSON 500へ変換した後もApplication HTTP Runtimeは失敗InvocationとしてConnection Lifecycleを終了し、生成済みApplication ConnectionをすべてCloseしてLong-running Processの次Requestへ失敗状態を持ち越さない。

## Changed Files

### Runtime and Failure Lifecycle

- `src/Internal/Execution/InlineDispatcher.php`
- `src/Internal/Execution/DeferredAcceptanceOrchestrator.php`
- `src/Internal/Execution/OperationExecutionFailed.php`
- `src/Internal/Execution/PrimaryFailureCapture.php`
- `src/Internal/Journal/LifecycleStateMachine.php`
- `src/Internal/Http/OperationFailureErrorBoundary.php`
- `src/Internal/Application/ApplicationHttpRequestHandler.php`
- `src/Http/Responder/JsonOperationResponder.php`

### Runtime Logging and Dependency Injection

- `src/Internal/Logging/ExecutionScopedLogger.php`
- `src/Internal/Logging/FrameworkOperationFailureReporter.php`
- `src/Internal/Logging/RuntimeLoggingServiceInjector.php`
- `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php`
- `src/Internal/Application/ApplicationWorkerComposer.php`
- `src/Internal/Runtime/ProductionRuntimeComposer.php`
- `src/Internal/Runtime/ProductionRuntimeComposition.php`
- `src/Internal/Runtime/ProductionRuntimeDependencies.php`

### Tests and Consumer Regression

- HTTP、Execution、Journal、Logging、DI、Runtime、PostgreSQL IntegrationのTask許可済みTestを更新した
- `tests/Internal/Application/ApplicationHttpRequestHandlerTest.php`へ500 Response後のConnection Close／次Request回復Testを追加した
- `tests/Internal/Http/OperationFailureErrorBoundaryTest.php`を追加した
- `tests/Internal/Logging/RuntimeLoggingServiceInjectorTest.php`を追加した
- `tests/Consumer/frankenphp-worker-mode.sh`へWorker／Classic共通Failure Boundary回帰を追加した

### Specifications and Tracking

- `develop/spec/02-lifecycle-and-journal.md`
- `develop/spec/03-execution.md`
- `develop/spec/05-http.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/30-lifecycle-state-machine.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Expected Rejectionは既存のRejected Lifecycle／4xx Responseのままとし、Failureへ変換しない。
- Public HTTP層はInternal Failure型へ依存させず、`ProductionRuntimeComposer`がInternal PSR-15 Error Boundaryを構成する。
- Inline Failureは自動Retryせず、`retryable=false`でTerminal化する。
- Canonical Failure Dataは既存Contractに従いRestrictedなException Messageを保持するが、HTTP、Application Log、Framework Error LogへMessageやStack Traceを投影しない。
- Failure Journalが失敗した場合は`journalRecorded=false`とし、Framework Logには`failure_recording_failed`と二次Throwableの型だけを記録する。Primary ThrowableとOperation IDは置き換えない。
- Failure RecordのObserved Journal Projectionは、Failure専用Safe Projectionを扱う後続Taskへ残した。P14-002ではCanonical Journalを正本としてTerminal Failureを保存する。
- Runtime Logger BackendのPublic設定Contractは追加せず、既定のMonolog JSONL stderr Backendと内部Runtime Compositionだけを実装した。
- Internal Error BoundaryがThrowableを500 Responseへ変換しても、Application HTTP RuntimeはStatus 500以上を失敗Invocationとして扱い、Response返却前に全Application ConnectionをCloseする。

## Failure Correlation Matrix

| Boundary | Operation ID | Attempt | Canonical Journal | HTTP | Framework Log |
| --- | --- | --- | --- | --- | --- |
| Operation成立前のProtocol／System Failure | 発行しない | なし | なし | 既存Fallback、IDなし | 架空のOperation Fieldなし |
| Inline Attempt内Throwable | 既存ID | 既存Attempt ID | Received、Started、Attempt Failed、Operation Failed | 500 `internal_error`＋同じID | 同じID群＋Primary Type |
| Transactional Inline Throwable | 既存ID | 既存Attempt ID | Application Rollback後にFailure Terminal | 同上 | 同上 |
| Deferred受付Policy Throwable | 既存ID | 作らない | 別TransactionでReceived、Operation Failed | 500 `internal_error`＋同じID | 同じOperation／Correlation ID |
| Failure Journal二次障害 | 既存IDを維持 | 元の境界に従う | `journalRecorded=false` | 同じSafe 500 | Primary Type＋`failure_recording_failed`＋Secondary Type |
| Logger Backend二次障害 | 既存IDを維持 | 元の境界に従う | 変更しない | 変更しない | Backend Throwableを外へ出さない |

## Runtime Logging Composition

1. Build時Container Compilerが`Psr\Log\LoggerInterface`をPublic Synthetic Serviceとして定義する。
2. HTTP／Worker Composition Rootが共有`ExecutionScopeProvider`と`ExecutionScopedLogger`を生成し、Compiled Containerへ同じLogger Instanceを注入する。
3. Inline Dispatcher、Deferred受付、Deferred Workerが共有ScopeをOperation境界でPush／Popする。
4. Handler、Authorization Policy、Application Serviceは`LoggerInterface`をConstructor Injectionする。
5. Application Logは予約Operation Fieldを自動付与し、Actor IDを固定Maskする。Operation外ではOperation Fieldを付けない。
6. Internal Error BoundaryがCarrierをCatchし、Safe Framework Error Logを出した後にOperation ID付きJSON 500へ変換する。
7. Application HTTP Request Lifecycleが500 Responseを失敗終了として扱い、Observer Flush後にApplication ConnectionをCloseする。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: 成功。composer.json is valid。

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: 成功。examples/quickstart/composer.json is valid。

docker compose run --rm app mago format --check src tests examples
Result: 成功。All files are already formatted。

docker compose run --rm app mago lint
Result: 成功。No issues found。

docker compose run --rm app mago analyze
Result: 成功。No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations <P14-002 target tests>
Result: OK (131 tests, 504 assertions)。Internal Error Boundary追加Testを含む。

docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Internal/Application/ApplicationHttpRequestHandlerTest.php \
  tests/Integration/ApplicationHttpRuntimeTest.php \
  tests/Internal/Runtime/ProductionRuntimeComposerTest.php \
  tests/Internal/Http/OperationFailureErrorBoundaryTest.php
Result: OK (18 tests, 104 assertions)。500 Responseを失敗InvocationとしてConnection Closeし、次Requestが回復することを含む。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1113 tests, 3918 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2055 / Warnings 0 / Errors 0。

bash tests/Consumer/frankenphp-worker-mode.sh
Result: 成功。Worker／ClassicのSafe 500 Shape、Framework Logとの同一Operation ID、Credential非露出を実プロセスで検証した。

bash tests/Consumer/quickstart-e2e.sh
Result: 成功。Quickstart consumer E2E passed。

bash -n tests/Consumer/frankenphp-worker-mode.sh
Management Comment ID Guard
Public Layer Internal Dependency Search
git diff --check
Result: すべて成功。
```

初回のDocker CommandはSandbox内Docker Socket権限不足で失敗した。承認済みのDocker実行へ切り替えて全Commandを再実行し、上記の成功結果を得た。

## Acceptance Criteria

- [x] `received -> operation.failed`を許可し、既存の無効遷移を拒否する
- [x] Inline Handler／Policy／Transaction Throwableを同じOperation／Attempt IDでTerminal化する
- [x] Inline Failureは`retryable=false`で、Expected Rejectionを変更しない
- [x] Transactional Inline FailureはRollback後にFailure Journalを記録する
- [x] Deferred受付Failureは別TransactionでSequence 1／2のReceived／Failedを記録する
- [x] Deferred受付FailureはTransport Row、Attempt、Outcome、Dead Letterを作らない
- [x] Operation成立後のSafe JSON 500は`status`、`code`、発行済みOperation IDだけを返す
- [x] Operation成立前FailureへOperation IDを架空に作らない
- [x] Classic HTTPとFrankenPHP Worker Modeが同じFailure BoundaryとResponse Shapeを使う
- [x] Compiled Containerの`LoggerInterface`が同じ`ExecutionScopedLogger`へ解決される
- [x] Application／Framework LogがExecution Scopeの相関IDを自動取得し、終了後にScopeを残さない
- [x] Actor ID、Credential、Raw Value、Exception Message、Stack TraceをHTTP／Logへ露出しない
- [x] Logger、Rollback、Failure Journalの二次障害でPrimary ThrowableとOperation IDを置き換えない
- [x] Nested／Request／Attempt間のScope分離を維持する
- [x] Safe 500へ変換後もApplication Connectionを失敗終了し、次Requestへ再利用しない
- [x] D098 Lifecycleを関連Specificationへ同期する
- [x] Public API、Migration、Diagnostics Query／CLI／Viewerを追加しない
- [x] Report／STATEを更新し、WorkerはCommitしない

## Remaining Issues

- Diagnostics Query／CLI／ViewerはTask Scope外であり、後続P14 Taskで実装する。
- Failure／Dead LetterのObserved Journal専用Safe Projectionは後続Taskで実装する。Canonical Terminal Failureは本Taskで保存済みである。
- Runtime Logger BackendのPublic選択Contract、Retention、Remote Sink、OpenTelemetryは後続Scopeである。

## Suggested Next Action

OrchestratorがFailure Lifecycle、Internal Error Boundary、Runtime DI、Safe Logging、Consumer E2EをReviewし、問題がなければP14-002をCommitしてP14-003 Internal Diagnostics Queryへ進む。

## Orchestrator Review

Accepted。初回ReviewでPublic HTTP層からInternal Failure型への依存逆転を検出し、Internal PSR-15 Error Boundaryへ移動した。次に、Error BoundaryがThrowableをSafe 500へ変換した後にApplication HTTP RuntimeがConnectionを成功終了する回帰を検出し、500を失敗Invocationとして全Application ConnectionをCloseする実装と次Request回復Testを追加した。

OrchestratorはFailure／Connection Lifecycleの中核78 tests／310 assertions、Full 1113 tests／3918 assertions、Mago Format／Analyze、Deptrac Violations 0、Worker／Classic Consumer E2E、Sensitive／Public Dependency／Management Comment／Whitespace Guardを独立再実行し、成功を確認した。D097／D098、P13 Long-running Connection、Public API境界と整合し、P14-002をAcceptedとする。
