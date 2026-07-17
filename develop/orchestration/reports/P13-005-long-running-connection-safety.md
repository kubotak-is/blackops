# P13-005: Long-running Connection Safety Report

## Summary

HTTP RequestとDeferred Attemptへ、生成済みNamed DBAL Connectionを対象にした共通Lifecycleを統合した。開始境界では全生成済みConnectionをHealth Checkし、失敗時は同じDBAL ObjectをCloseして一度だけ再接続する。前InvocationでCloseされたObjectも次回開始時に再接続し、再接続失敗時はHandler／Metadata解決前に処理を停止する。

正常終了時はInvocation中に生成されたConnectionも含めてTransaction Leakを検査し、Leakした全ConnectionをCloseして成功Response／Claim Acknowledgeを阻止する。Runtime、Supervision、Terminal、Observer Cleanup等の失敗時は全生成済みApplication ConnectionをBest-effortでCloseし、Primary Throwableを維持する。Deferred Heartbeatは別DatabaseManagerのままApplication Lifecycleから除外した。

## Changed Files

- `src/Internal/Database/DoctrineDatabaseManager.php`
  - 生成済みConnection Objectだけを列挙する内部Lifecycle APIを追加した。
- `src/Internal/Application/ApplicationDatabaseConnectionLifecycle.php`
  - Named Connection全体のHealth Check、Close／一度だけのReconnect、Leak検査、Best-effort Cleanupを実装した。
- `src/Internal/Application/ApplicationHttpRequestHandler.php`
  - Request State／Observer Cleanupを含む全失敗後にConnection Cleanupを行い、Primary Throwableを保持するよう順序を統合した。
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php`
  - 単一Framework ConnectionではなくApplication DatabaseManager全体をHTTP Lifecycleへ渡した。
- `src/Internal/Application/ApplicationWorkerComposer.php`
  - Application DatabaseManagerのLifecycleをDeferred Runtimeへ渡し、Heartbeat用の別Managerを維持した。
- `src/Internal/Execution/DeferredWorkerRuntime.php`
  - Metadata／Handler解決前の開始境界と、Terminal／Supervision完了後の成功／失敗境界を追加した。
- `tests/Internal/Database/DoctrineDatabaseManagerTest.php`
  - 未生成Nameを生成せず、生成順とObject Identityを維持して列挙する契約を追加した。
- `tests/Internal/Application/ApplicationDatabaseConnectionLifecycleTest.php`
  - Healthy reuse、Stale reconnect、Reconnect failure、複数Name、Invocation中生成、全Leak、全Close、Close failure、次回再接続を固定した。
- `tests/Internal/Application/ApplicationHttpRequestHandlerTest.php`
  - Throwable、Leak、Observer Cleanup failure、次Request回復を固定した。
- `tests/Internal/Execution/DeferredWorkerRuntimeTest.php`
  - Handler解決前Health Check、Reconnect failure、Supervision後Close、Leak、Retry後の同一Object再接続を固定した。
- `tests/Integration/ApplicationHttpRuntimeTest.php`
  - 生成済みDefault／Framework Connectionが開始時に到達可能である新Lifecycle前提へFixtureを同期した。
- `tests/Integration/ApplicationConsoleKernelTest.php`
  - Worker Main ConnectionがApplication Lifecycle対象で、Heartbeat Connectionが対象外であることを固定した。
- `docs/guide/database-and-transactions.md`
  - Request／Attempt再利用、Close、Reconnect、Leak、非Retry境界を説明した。
- `docs/guide/deployment.md`
  - Database停止／復旧時の運用とIdempotency責任を追加した。
- `docs/internal/bootstrap.md`
  - HTTP／Worker Composition、生成済み一覧、Heartbeat分離を記録した。
- `develop/TODO.md`
  - Worker Mode Connection Lifecycleを完了へ更新した。
- `develop/STATE.md`
  - 開始／完了Checkpointと検証結果を更新した。

## Decisions and Assumptions

- `generatedConnections()`はManagerから一度でも要求され生成されたDBAL Objectだけを返す。未生成のNamed ConnectionをLifecycle目的で生成しない。
- 生成済みObjectが`close()`済みでも開始時の`SELECT 1`対象とする。Doctrine DBALが同じObjectで再接続するため、Constructor Injection済みRepositoryを再生成しない。
- Health Checkは一回失敗時にCloseして一度だけ再試行する。Query、Transaction、Commit自体はRetryしない。
- 開始失敗時はLifecycle自身が全生成済みConnectionをCloseするため、HTTP／Deferred Wrapperは同じ開始失敗に対して重複Cleanupしない。
- 正常終了のLeak検査は全Connectionを最後まで検査する。LeakしたConnectionだけをCloseし、健康なConnectionは再利用する。Connection State検査自体が失敗した場合は全ConnectionをCloseして元のThrowableを返す。
- Deferred Terminalが保存済みでも、終了LeakはRuntimeからThrowableとして返るためWorker LoopはClaimをAcknowledgeしない。Application ConnectionにActive Transactionは残らない。
- Heartbeat Receiverは従来どおり別DatabaseManager／別Connection Objectで構成し、Application Synthetic ServiceまたはLifecycleへ渡さない。
- 新規`tests/Consumer/database-connection-lifecycle.sh`は作らず、既存`frankenphp-worker-mode.sh`のPostgreSQL停止／500／復旧／複数Request検証と、Deferred Runtime Integration Testを組み合わせた。

## Commands and Results

```text
docker compose run --rm app mago format src/Internal/Database src/Internal/Application src/Internal/Execution tests/Internal/Database tests/Internal/Application tests/Internal/Execution tests/Integration
Result: 成功。対象Fileは整形済み。

docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Internal/Database/DoctrineDatabaseManagerTest.php tests/Internal/Application/ApplicationDatabaseConnectionLifecycleTest.php tests/Internal/Application/ApplicationHttpRequestHandlerTest.php tests/Internal/Execution/DeferredWorkerRuntimeTest.php tests/Internal/Execution/DeferredWorkerLoopTest.php tests/Integration/ApplicationHttpRuntimeTest.php tests/Integration/ApplicationConsoleKernelTest.php
Result: OK (60 tests, 520 assertions)。HTTP／Deferred、closed Object再接続、Leak、Heartbeat分離を含む。

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstartともvalid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: すべて成功。Lint／AnalyzeともIssueなし。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1085 tests, 3766 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1990。

bash tests/Consumer/frankenphp-worker-mode.sh
Result: 成功。PostgreSQL停止時500、復旧後Reconnect、32 Request、Worker restart／memory bound、Classic fallbackを確認した。

bash tests/Consumer/quickstart-e2e.sh
Result: 成功。Build、Migration、HTTP、Deferred Worker、Generatorを完走した。

bash tests/Consumer/skeleton-create-project.sh
Result: 成功。通常／--no-scripts create-projectを確認した。

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
Result: いずれも成功。
```

## Acceptance Criteria

- [x] 生成済みConnectionだけを列挙し、未使用Named ConnectionをHealth Checkで生成しない
- [x] HTTP／Deferredの開始時にHandler解決前Health Checkを行う
- [x] Healthy Connection Objectを複数Request／Attemptで再利用する
- [x] Stale ConnectionをCloseし、同じObjectで一度だけ再接続して継続する
- [x] Reconnect FailureではApplication Codeを実行せずConnectionをCloseする
- [x] 成功終了時の全Named Connection Leakを検出し、該当ConnectionをCloseしてFail-fastする
- [x] Throwable終了時に生成済みApplication ConnectionをすべてCloseする
- [x] Primary ThrowableをCleanup Failureで隠さない
- [x] 次Request／Attemptが失敗前のTransaction／Connection状態を引き継がない
- [x] HTTP Worker ModeでPostgreSQL停止時に失敗し、復旧後に同じWorker系のRequestが成功する
- [x] Deferred Workerで失敗Attempt後も次Attemptが同じObjectで再接続して処理できる
- [x] Framework MainとHeartbeat Connectionが別Objectで、HeartbeatをApplication Serviceへ公開しない
- [x] Transaction／Fencing／Retry／Outcomeの既存保証が回帰しない
- [x] Guide／Internal Bootstrapが再利用、Close、Reconnect、非Retry境界を説明する
- [x] Target／Full Quality CommandsとConsumer E2Eが成功する
- [x] Report／STATEを更新し、CommitせずReviewへ返す

## Remaining Issues

- 新規Blockerはない。
- Query／Transaction Retry、Commit結果不明時のExactly-once、Transactional OutboxはTask Scope外のまま維持した。
- Documentation Website公開はUser方針どおり実行していない。

## Orchestrator Review

- 初期差分の`prepare()`がClose済みConnectionをHealth Check対象外にしていたため、生成済みObjectは接続状態にかかわらず次Request／Attempt開始時に`SELECT 1`で再接続するよう修正を要求した。
- 修正後の差分、Lifecycle Cleanup順序、Primary Throwable優先、ManagerのObject Identity、Heartbeat分離、許可File範囲を確認した。
- Targetは60 tests／520 assertions、Fullは1085 tests／3766 assertionsで成功した。
- Composer、Mago Format／Lint／Analyze、Deptracは成功し、Deptrac Violationsは0だった。
- FrankenPHPのPostgreSQL停止／500／復旧、Multi-request、Quickstart、Skeleton create-projectを独立再実行して成功した。SkeletonはConsumer間の干渉を避けるため単独再実行した。
- Comment ID Guardと`git diff --check`も成功し、P13-005をAcceptedとした。

## Suggested Next Action

P13-005をTask単位でCommitし、その後P13-006 Consumer Experience and Closeoutへ進む。
