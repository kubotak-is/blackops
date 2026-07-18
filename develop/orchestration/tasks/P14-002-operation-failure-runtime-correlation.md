# P14-002: Operation Failure and Runtime Correlation

Status: Ready

## Goal

Operation ID発行後の予期しないThrowableをTerminal Journalへ到達させ、HTTP 500、Framework Error Log、Application Log、Canonical Journalを同じOperation IDで相関できるようにする。

Inline Attempt内Failureは`attempt.failed -> operation.failed`、Deferred受付のAttempt開始前FailureはAttemptを作らず`received -> operation.failed`とする。Application ServiceがConstructor Injectionする`Psr\Log\LoggerInterface`を、実行中Operation Scopeを自動付与するLoggerへ接続する。

## In Scope

- Lifecycle State MachineへAttemptなしの`received -> operation.failed`遷移を追加
- Inline Authorization／Handler Resolution／Transaction／Handler Invocationの予期しないThrowableをTerminal Failure化
- Transactional Inline OperationのRollback後にFailure Journalを別Transactionで記録
- Deferred受付Policy Throwableの受付Transaction Rollback、別TransactionによるReceived／Operation Failed記録
- Operation IDを保持する内部Failure Carrier／Error Boundary
- Operation成立後のSafe JSON 500 `internal_error`とOperation ID
- Classic HTTPとFrankenPHP Worker Modeが同じFramework Error Boundary／Response Shapeを使うことの検証
- `ExecutionScopedLogger`のRuntime Composition、Compiled Containerへの`LoggerInterface`注入
- HTTP RuntimeとDeferred Worker Runtimeの共有Execution Scope／Logger接続
- Framework Error LogへOperation／Attempt／Correlation／Causation IDとSafe Failure Classificationを付与
- Logger Backend Failure、Failure Journal Failure、Rollback Failure時のPrimary Throwable／Operation ID維持
- D098のLifecycleを既存Lifecycle／Execution／HTTP／Logging／Transaction Specificationへ同期

## Out of Scope

- Diagnostics State／Dead Letter／Purge Audit Readerと`OperationDiagnostics` Query実装
- `operation:inspect`、`operation:viewer`の実装
- Public PHP Diagnostics API、Public HTTP Status／Outcome API
- Log Sink選択、Log Retention、Alert、Remote Collector、OpenTelemetry
- Canonical Failure Messageの保存形式変更
- Migration追加
- Quickstartへの新しいFailure Tutorial追加
- Documentation Website公開

## Relevant Specifications and Decisions

- `develop/spec/02-lifecycle-and-journal.md`
- `develop/spec/03-execution.md`
- `develop/spec/05-http.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/30-lifecycle-state-machine.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/decisions/097-phase-14-operation-diagnostics.md`
- `develop/decisions/098-deferred-acceptance-failure-lifecycle.md`

## Files Allowed to Change

### Production

- `src/Http/OperationRequestHandler.php`
- `src/Http/Responder/JsonOperationResponder.php`
- `src/Internal/Http/OperationFailureErrorBoundary.php`
- `src/Internal/Execution/InlineDispatcher.php`
- `src/Internal/Execution/DeferredAcceptanceOrchestrator.php`
- P14-002のFailure相関に必要な新規`src/Internal/Execution/*.php`
- `src/Internal/Journal/LifecycleStateMachine.php`
- P14-002のFailure Record生成に必要な`src/Internal/Journal/*.php`
- `src/Internal/Logging/ExecutionScopedLogger.php`
- `src/Internal/Logging/MonologJsonlLoggerFactory.php`
- P14-002のRuntime Logger Injection／Safe Error Reportingに必要な新規`src/Internal/Logging/*.php`
- `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php`
- `src/Internal/Application/ApplicationWorkerComposer.php`
- `src/Internal/Application/ApplicationHttpRequestHandler.php`
- `src/Internal/Runtime/ProductionRuntimeComposer.php`
- `src/Internal/Runtime/ProductionRuntimeComposition.php`
- `src/Internal/Runtime/ProductionRuntimeDependencies.php`
- `examples/quickstart/public/worker.php`（Framework Error Boundary外のFallback同期が必要な場合だけ）

### Tests

- `tests/Internal/Journal/LifecycleStateMachineTest.php`
- `tests/Internal/Execution/InlineDispatcherTest.php`
- P14-002の新規Failure Correlation単体Testに必要な`tests/Internal/Execution/*.php`
- `tests/Transport/PostgreSql/PostgreSqlInlineDispatcherIntegrationTest.php`
- `tests/Transport/PostgreSql/PostgreSqlDeferredAcceptanceOrchestratorTest.php`
- `tests/Http/OperationRequestHandlerTest.php`
- `tests/Http/DeferredOperationRequestHandlerTest.php`
- `tests/Internal/Http/DeferredHttpOperationAcceptorTest.php`
- `tests/Internal/Http/OperationFailureErrorBoundaryTest.php`
- `tests/Internal/Logging/ExecutionScopedLoggerTest.php`
- `tests/Internal/Logging/MonologJsonlLoggerFactoryTest.php`
- P14-002のRuntime Logger Injection／Safe Error Reportingに必要な新規`tests/Internal/Logging/*.php`
- `tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php`
- `tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- `tests/Internal/Runtime/ProductionRuntimeComposerTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Consumer/frankenphp-worker-mode.sh`（Error Boundary回帰が必要な場合だけ）

### Specification and Orchestration

- `develop/spec/02-lifecycle-and-journal.md`
- `develop/spec/03-execution.md`
- `develop/spec/05-http.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/30-lifecycle-state-machine.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/TODO.md`
- `develop/orchestration/reports/P14-002-operation-failure-runtime-correlation.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を広げずReportのBlockerとしてOrchestratorへ返す。

## Constraints

- Expected RejectionをFailureへ変換しない
- Inline Failureに自動Retryを追加しない
- Deferred受付のAttempt開始前FailureにAttempt ID／Attempt Started／Attempt Failedを作らない
- Operation ID発行後にFailure記録が失敗しても、別Operation IDを発行しない
- Rollback、Failure Journal、Loggerの二次FailureでPrimary Throwableを置き換えない
- HTTP／LogにException Message、Stack Trace、Raw Value、Credential、Mask前Actor IDを出さない
- `LoggerInterface`はOperation外でも利用でき、Operation Fieldを架空に付与しない
- Runtime Logger Backendの設定／差し替えPublic ContractはP14-006まで固定しない。P14-002は安全な既定Backendと内部Compositionに留める
- Public APIを追加しない
- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する

## Acceptance Criteria

- [ ] Lifecycle State Machineが`received -> operation.failed`を許可し、その他の無効遷移を引き続き拒否する
- [ ] InlineのHandler／Policy／Transaction ThrowableがReceived、Attempt Started、Attempt Failed、Operation Failedを同じOperation／Attempt IDで記録する
- [ ] Inline Failureの`retryable`が`false`で、Expected Rejectionの既存Lifecycle／HTTPが変わらない
- [ ] Transactional Inline FailureはApplication TransactionをRollbackした後にTerminal Failure Journalを記録する
- [ ] Deferred受付Policy Throwableが受付TransactionをRollbackし、別TransactionでReceived、Operation FailedをSequence 1／2として記録する
- [ ] Deferred受付FailureはTransport Row、Attempt、Outcome、Dead Letterを作成しない
- [ ] Operation成立後のHTTP 500が`status=error`、`code=internal_error`、発行済みOperation IDだけを返す
- [ ] Operation成立前のProtocol／Authentication／System FailureはOperation IDを架空に作成しない
- [ ] Classic HTTPとFrankenPHP Worker ModeがOperation成立後Failureで同じSafe 500 Shapeを返す
- [ ] Compiled Containerの`LoggerInterface`がRuntimeで同じ`ExecutionScopedLogger`へ解決され、Handler／Policy／Application ServiceにConstructor Injectionできる
- [ ] Application LogにOperation／Attempt／Correlation／Causation IDが自動付与され、Operation外／実行終了後にScopeが残らない
- [ ] Framework Error LogがSafe Failure Classificationと同じCorrelation ID群を持つ
- [ ] Actor IDはMaskまたはOmitされ、Credential、Raw Value、Exception Message、Stack TraceがHTTP／Logへ露出しない
- [ ] Logger Backendが投げてもLifecycle／HTTP Resultを変更しない
- [ ] Rollback／Failure Journalの二次FailureでPrimary Throwableと発行済みOperation IDが維持される
- [ ] HTTP Request間、Deferred Attempt間、Nested OperationでLogger Scopeが混線しない
- [ ] D098のLifecycleが関連Specificationへ同期される
- [ ] Public API、Migration、Diagnostics Query／CLI／Viewerを追加しない
- [ ] Report／STATEを更新し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Internal/Journal/LifecycleStateMachineTest.php \
  tests/Internal/Execution/InlineDispatcherTest.php \
  tests/Transport/PostgreSql/PostgreSqlInlineDispatcherIntegrationTest.php \
  tests/Transport/PostgreSql/PostgreSqlDeferredAcceptanceOrchestratorTest.php \
  tests/Http/OperationRequestHandlerTest.php \
  tests/Http/DeferredOperationRequestHandlerTest.php \
  tests/Internal/Http/DeferredHttpOperationAcceptorTest.php \
  tests/Internal/Logging/ExecutionScopedLoggerTest.php \
  tests/Internal/Logging/MonologJsonlLoggerFactoryTest.php \
  tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php \
  tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php \
  tests/Internal/Console/ApplicationBuildCompileCommandTest.php \
  tests/Internal/Runtime/ProductionRuntimeComposerTest.php \
  tests/Integration/ApplicationHttpRuntimeTest.php
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/frankenphp-worker-mode.sh
bash tests/Consumer/quickstart-e2e.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Consumer ScriptがP14-002と無関係なExternal Network／Publicationを要求する場合は、範囲を広げず実行できない理由をReportへ記録する。

## Expected Report

`develop/orchestration/reports/P14-002-operation-failure-runtime-correlation.md`へSummary、Changed Files、Decisions and Assumptions、Failure Correlation Matrix、Runtime Logging Composition、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
