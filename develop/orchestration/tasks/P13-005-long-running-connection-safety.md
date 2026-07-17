# P13-005: Long-running Connection Safety

Status: Ready

## Goal

D096とPhase 13 Delivery Planに従い、FrankenPHP Worker Modeの各HTTP RequestとDeferred Workerの各AttemptをNamed DBAL Connectionの安全な再利用境界にする。生成済みConnectionだけを開始時にHealth Checkし、正常終了時はTransaction LeakがないConnectionを再利用する。Throwable、Leak、Health Check FailureではConnectionをCloseし、同じLong-running Processの次Request／AttemptでDBALに再接続させる。

Heartbeat ConnectionはApplication用DatabaseManagerとLifecycleから分離したまま維持し、Application Serviceへ公開しない。

## In Scope

- `DoctrineDatabaseManager`が生成済みNamed Connectionを内部Lifecycleへ列挙する機構
- 未使用Named ConnectionのLazy性を壊さないHealth Check
- HTTP Request開始／成功／失敗の全Named Connection Lifecycle
- Deferred Attempt開始／成功／失敗の全Named Connection Lifecycle
- Handler／Authorization／Transaction／Terminal／Supervision Failure後のClose
- 正常終了時Transaction Leak検出、該当Connection Close、Fail-fast
- Stale Connection Close後の同一Connection Objectによる再接続
- 同じProcess内の複数Request／Attemptでの正常Connection再利用
- Framework Main ConnectionとHeartbeat Connectionの分離Guard
- PostgreSQL停止／復旧、失敗後継続、複数Request／Attemptの回帰Test／Consumer E2E
- Database／Deployment GuideとInternal Bootstrapの同期

## Out of Scope

- Connection Pool実装または外部Pooler設定
- Request／Attemptごとの無条件Close
- Query Retry、Transaction自動Retry、Commit結果不明時のExactly-once
- Heartbeat Algorithm／Lease／Fencingの変更
- ORM Entity Manager／Unit of Work Lifecycle
- Transactional Outbox
- QuickstartのRepository／Transactional Operation実例
- Documentation Website公開

## Relevant Specifications and Decisions

- `develop/decisions/096-phase-13-database-and-transaction-runtime.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/64-phase-13-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/Database/DoctrineDatabaseManager.php`
- `src/Internal/Application/ApplicationDatabaseConnectionLifecycle.php`
- `src/Internal/Application/ApplicationHttpRequestHandler.php`
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php`
- `src/Internal/Application/ApplicationWorkerComposer.php`
- `src/Internal/Execution/DeferredWorkerRuntime.php`
- `src/Internal/Execution/DeferredWorkerRuntimeStorage.php`
- `src/Internal/Execution/DeferredWorkerRuntimeServices.php`
- `src/Internal/Execution/DeferredWorkerLoop.php`
- `tests/Internal/Database/DoctrineDatabaseManagerTest.php`
- `tests/Internal/Application/ApplicationDatabaseConnectionLifecycleTest.php`
- `tests/Internal/Application/ApplicationHttpRequestHandlerTest.php`
- `tests/Internal/Application/ApplicationHttpConfigurationTest.php`
- `tests/Internal/Execution/DeferredWorkerRuntimeTest.php`
- `tests/Internal/Execution/DeferredWorkerLoopTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Consumer/frankenphp-worker-mode.sh`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/database-connection-lifecycle.sh`
- `docs/guide/database-and-transactions.md`
- `docs/guide/deployment.md`
- `docs/internal/bootstrap.md`
- `develop/TODO.md`
- `develop/orchestration/reports/P13-005-long-running-connection-safety.md`
- `develop/STATE.md`

許可されていないFileが必要な場合は実装を広げず、ReportのBlockerへ記録する。

## Generated Connection Contract

- Lifecycleは`DoctrineDatabaseManager`が既に生成したConnectionだけを対象にする
- Lifecycle確認のために未使用Named Connectionを生成しない
- Default／Framework Connectionを含め、Request／Attempt中に初めて生成したNamed Connectionも終了時検査へ含める
- ManagerはConnection Nameごとに同じDBAL `Connection` Objectを維持する。`close()`後もObjectを差し替えず、既にConstructor Injection済みのRepositoryが次回利用時にDBAL再接続できるようにする
- Connection Parameter、Credential、Environment SnapshotをLifecycle Error、Log、Build Artifactへ出さない
- Application Lifecycleへ渡すManagerとHeartbeat用Managerは別Instanceとし、Heartbeat Connectionを生成済みApplication Connection一覧へ含めない

## Start Boundary

HTTPはMiddleware／Handler実行前、DeferredはMetadata／Handler解決とAttempt Started処理前にLifecycleを開始する。

- 生成済み各Connectionへ`SELECT 1`を実行する
- 成功したConnectionは同じObjectのまま再利用する
- Health Checkが失敗したConnectionは`close()`して一度だけHealth Checkを再試行する
- 再試行成功なら同じRequest／Attemptを継続する
- 再試行も失敗した場合は再度CloseしてThrowableを伝播し、Application Codeを実行しない
- 一つのConnection FailureをCredentialや全Connection Parameter付きMessageへ展開しない

## Finish Boundary

### Success

- 終了時点で生成済みの全Connectionを検査する
- Active TransactionがないConnectionはCloseせず次回へ再利用する
- Active TransactionがあるConnectionはCloseし、他の生成済みConnectionも最後まで検査する
- 一件以上LeakがあればSecret非露出の`LogicException`でFail-fastし、成功Resultとして扱わない
- Closed Connectionは次回Start Health CheckまたはRepository利用時に再接続できる

### Failure

- Handler、Authorization、Binding後Runtime、Transaction、Terminal、Supervision、CleanupのThrowableでは生成済みApplication ConnectionをすべてBest-effortでCloseする
- Primary Throwableがある場合、Close／Cleanup Failureで置き換えない
- Lifecycle自身の開始Failureでも生成済みConnectionを安全側へCloseする
- Operation Transaction Runtime／After Commit Queueの既存Cleanupを重複所有せず、DBAL Connection境界だけを扱う

## HTTP and Deferred Integration

- `ApplicationHttpRequestHandler`は既存Execution Scope／Journal Flush CleanupとConnection Lifecycleを両方最後まで実行する
- HTTP Error Response化より前にRequest LifecycleがClose／Leak検査を完了する
- `DeferredWorkerRuntime::run()`はClaimごとにStart／Finishを実行し、Handler解決前にHealth Checkする
- `SupervisedHandlerFailureException`を含む失敗AttemptはConnection Close後にWorker Loopへ返す
- 正常AttemptのLeak FailureはClaimを成功Acknowledgeせず、次のClaimへActive Transactionを持ち越さない
- Retry／Dead Letter／Completed Stateが既にFramework Storeへ記録済みでもApplication Connection Cleanupを必ず行う
- Heartbeat Connectionは既存の独立Connectionを使い、Application LifecycleのClose対象にしない

## Acceptance Criteria

- [ ] 生成済みConnectionだけを列挙し、未使用Named ConnectionをHealth Checkで生成しない
- [ ] HTTP／Deferredの開始時にHandler解決前Health Checkを行う
- [ ] Healthy Connection Objectを複数Request／Attemptで再利用する
- [ ] Stale ConnectionをCloseし、同じObjectで一度だけ再接続して継続する
- [ ] Reconnect FailureではApplication Codeを実行せずConnectionをCloseする
- [ ] 成功終了時の全Named Connection Leakを検出し、該当ConnectionをCloseしてFail-fastする
- [ ] Throwable終了時に生成済みApplication ConnectionをすべてCloseする
- [ ] Primary ThrowableをCleanup Failureで隠さない
- [ ] 次Request／Attemptが失敗前のTransaction／Connection状態を引き継がない
- [ ] HTTP Worker ModeでPostgreSQL停止時に失敗し、復旧後に同じProcessまたは次Worker Requestが成功する
- [ ] Deferred Workerで失敗Attempt後も次Attemptが再接続して処理できる
- [ ] Framework MainとHeartbeat Connectionが別Objectで、HeartbeatをApplication Serviceへ公開しない
- [ ] Transaction／Fencing／Retry／OutcomeのP13-004保証が回帰しない
- [ ] Guide／Internal Bootstrapが再利用、Close、Reconnect、非Retry境界を説明する
- [ ] Target／Full Quality CommandsとConsumer E2Eが成功する
- [ ] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Required Commands

```bash
docker compose run --rm app mago format src/Internal/Database src/Internal/Application src/Internal/Execution tests/Internal/Database tests/Internal/Application tests/Internal/Execution tests/Integration
docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Internal/Database/DoctrineDatabaseManagerTest.php tests/Internal/Application/ApplicationDatabaseConnectionLifecycleTest.php tests/Internal/Application/ApplicationHttpRequestHandlerTest.php tests/Internal/Execution/DeferredWorkerRuntimeTest.php tests/Internal/Execution/DeferredWorkerLoopTest.php tests/Integration/ApplicationHttpRuntimeTest.php tests/Integration/ApplicationConsoleKernelTest.php
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/frankenphp-worker-mode.sh
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

新規Consumer Scriptを作らず既存Scriptへ統合した場合は、存在しないCommandを省略してReportへ記録する。

## Expected Report

`develop/orchestration/reports/P13-005-long-running-connection-safety.md`へSummary、Changed Files、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
