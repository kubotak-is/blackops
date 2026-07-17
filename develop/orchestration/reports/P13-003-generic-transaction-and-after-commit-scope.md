# P13-003: Generic Transaction and After Commit Scope Report

Status: Accepted

## Summary

- DI管理された一般Serviceの`#[Transactional]`を、Named DBAL ConnectionごとのRequired Transactionとして実行するRuntimeとRay.Aop Interceptorを実装した。
- 同じConnectionのNested呼出はDBAL Nested Transaction／Savepointを作らず外側Scopeへ参加し、Inner ThrowableでRollback-onlyにする。
- 異なるNamed Connectionは独立Root ScopeとしてCommitし、外側Rollbackで内側Commitを戻さない。
- 開始済みManual TransactionをMethod実行前に拒否し、Attributed Methodが残したNesting Leakを可能な限りRollbackしてRuntime Scope／Queueを破棄する。
- `#[AfterCommit]` Invocationを現在のRoot Scopeへ登録し、最外Commit後に登録順で一度実行する。Rollback、Rollback-only、Commit失敗ではQueueを破棄し、Transaction外では即時実行する。
- Public `AfterCommitFailure`、`AfterCommitFailureReporter`、`TransactionException`と、DefaultのSecret非露出Reporterを追加した。
- HTTPとDeferred WorkerがHandler／Policy／Middleware解決前に、DatabaseManager、Execution Scope、Transaction RuntimeをCompiled Containerへ注入するようにした。
- OperationのTransactional BindingはFoundation pass-throughを維持し、一般ServiceだけをGeneric Transactionへ接続した。

## Changed Files

- Public Database API:
  - `src/Database/AfterCommitFailure.php`
  - `src/Database/AfterCommitFailureReporter.php`
  - `src/Database/Exception/TransactionException.php`
- AOP／Transaction Runtime:
  - `src/Internal/Aop/**`
  - `src/Internal/Transaction/**`
- Runtime Composition／DI:
  - `src/Internal/Application/ApplicationHttpRuntimeComposer.php`
  - `src/Internal/Application/ApplicationWorkerComposer.php`
  - `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`
- Tests／Fixtures:
  - `tests/Database/**`
  - `tests/Internal/Aop/**`
  - `tests/Internal/Transaction/**`
  - `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
  - `tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php`
  - `tests/Fixtures/Aop/FoundationTransactionalOperation.php`
- Documentation／Orchestration:
  - `docs/guide/attributes.md`
  - `docs/guide/configuration.md`
  - `docs/guide/core-api.md`
  - `docs/guide/database-and-transactions.md`
  - `docs/internal/bootstrap.md`
  - `develop/TODO.md`
  - `develop/STATE.md`
  - `develop/orchestration/tasks/P13-003-generic-transaction-and-after-commit-scope.md`
  - `develop/orchestration/reports/P13-003-generic-transaction-and-after-commit-scope.md`

## Decisions and Assumptions

- Transaction RuntimeはConnection NameごとのRoot Scope Mapと、Root／Nestedの全Transactional呼出を表す論理Stackを分離する。同名NestedはRootを追加しないが論理Stackへ積み、After Commit登録先はStack topから決める。
- After Commit InvocationはCallback ClosureをQueueへ保持するが、Failure ValueとDefault Logへ引数を展開しない。登録時のExecution Contextだけを相関情報としてSnapshotする。
- Commit後CallbackまたはReporterがThrowableを投げても、後続Callbackを継続し、Commit済みDatabaseとMethod Returnを変更しない。
- Compiled ProxyのRuntime Bindingは内部Accessorを経由する。HTTP／Worker CompositionがAccessorへRuntimeを注入する前に一般ServiceのTransactional／AfterCommit Methodを呼んだ場合は、安全な`TransactionException`でFail-fastする。
- Orchestrator Reviewで、未注入Runtimeの一般Service pass-throughは保証を黙って無効化するため不可と指摘された。Operation Foundation以外をFail-fastへ修正し、低Level Container TestもProduction同様にRuntimeを明示注入する形へ変更した。
- 追加Reviewで、Root作成順だけではA→B→A再入時に内側AのAfter CommitがBへ誤登録されると指摘された。全Transactional Invocationの論理Stackへ変更し、B Commit／A RollbackとB Rollback／A Commitの両方向をPostgreSQL Testへ追加した。
- Default ReporterはService、Method、存在するOperation／Attempt／Correlation／Causation IDだけをPSR-3へ渡す。Callback引数、Cause Message／Trace、Database Credentialは渡さない。

## Commands and Results

```text
docker compose run --rm app mago format <P13-003 required paths>
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: Format済み。Lint／AnalyzeともにNo issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations <P13-003 target tests>
Result: OK (102 tests, 742 assertions)。PostgreSQL実接続のCommit／Rollback／Nested／Manual／複数ConnectionとA→B→A再入を含む。
Task Packet記載の`tests/Internal/Application/ApplicationWorkerComposerTest.php`はRepositoryに存在しないため省略し、Worker Composition回帰はApplication Console／Deferred Worker／Full Suiteで検証した。

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstartともにvalid。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1056 tests, 3608 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1948 / Warnings 0 / Errors 0。

bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
Result: Quickstart E2E、Skeleton通常／no-scripts Create-projectが成功。

Public API Count Guard
Result: 134型でdocs/guide/core-api.mdと一致。

Management ID Guard
Runtime Temporary Proxy API Guard
git diff --check
Result: すべて成功。
```

初回Full PHPUnitでは、既存の低Level Container Test 2件がTransaction Runtime未注入のまま一般Service Proxyを解決して失敗した。一時的なpass-through互換はOrchestrator Reviewで却下し、一般Serviceは未注入時Fail-fast、TestはProduction同様にRuntimeを明示注入する形へ修正した。修正後のTarget／Full Suiteは成功した。

追加ReviewでA→B→A再入時のAfter Commit登録先にRoot作成順と論理呼出順の差があることを修正した。完了したRootをCommit後Callback実行中だけ論理Stackから外すことで、外側Rootが残る場合も正しいScopeを参照する。修正後の最終Target／Full SuiteとDeptracは成功した。

## Acceptance Criteria

- [x] 一般ServiceのTransactional MethodがReturn時にCommitし、Throwable時にRollbackする
- [x] 同一ConnectionのNested Scopeが一つのDB Transactionを共有する
- [x] Inner FailureをOuterが握りつぶしてもRollback-onlyによりCommitしない
- [x] 異なるNamed Connectionが独立Commitし、Outer RollbackでInner Commitが戻らない
- [x] 開始済みManual TransactionとAttribute管理をMethod実行前に拒否する
- [x] Attribute Method内のManual Nesting Leakを検出し、Framework Scope／Queueを残さない
- [x] After Commit InvocationがCommit後に登録順で一度実行される
- [x] Rollback／Rollback-only／Commit失敗でAfter Commit Queueが実行されない
- [x] Transaction外のAfterCommit Methodが即時実行され、Throwableを通常どおり伝播する
- [x] Commit後Callback失敗を全件Reporterへ渡し、後続CallbackとCommit済みDatabaseを変更しない
- [x] Reporter失敗も後続Callbackを止めず、自動Retryしない
- [x] Failureへ登録時Execution ContextのOperation／Attempt／Correlation／Causationを関連付けられる
- [x] Default ReporterがGeneric Structured Errorを出し、Callback引数／Throwable詳細／Credentialを漏らさない
- [x] Application提供AfterCommitFailureReporterを優先し、Framework Defaultで上書きしない
- [x] OperationのTransactional AttributeがGeneric Service Commitを開始しない
- [x] HTTP／Deferred Workerが同じExecution ScopeをTransaction Runtime／Reporterへ注入する
- [x] Direct `new`、AttributeなしService、Build Artifact Contractが回帰しない
- [x] PostgreSQL IntegrationでCommit／Rollback／Nested／Manual／複数Connectionを検証した
- [x] GuideがOperationと一般Serviceの保証差、Nested、Manual、After Commit Best-effort、Outbox境界を説明する
- [x] Target／Full Quality Commandsが成功した
- [x] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Remaining Issues

- Operation Definition／`handle()`のTransaction、Manifest Metadata、Terminal Journal／Outcomeとの同一Connection Commitは次Taskの範囲である。
- Request／Attempt開始時のConnection Health Check、Leak後のClose／Reconnectは後続Taskの範囲である。
- ReliableなAfter Commit DeliveryとRetryは提供せず、Transactional OutboxはPhase 17へ残す。
- Documentation Websiteは意図的に未公開のままである。

## Suggested Next Action

P13-004 Operation Transaction LifecycleのTask Packetを作成し、Operationの固定Transaction境界とTerminal Journal／Outcomeの原子的Commitを実装する。

## Orchestrator Review

2026-07-18T05:28:26+09:00にOrchestratorがTask許可範囲、一般Service Transaction、Nested Required／Rollback-only、異なるConnection、Manual Transaction Guard、After Commit Queue／Failure Reporter、AOP Runtime Binding、HTTP／Worker Scope共有、公開APIとDocumentationをReviewした。

初回Reviewで未注入Runtimeのpass-throughをFail-fastへ修正した。追加ReviewではRoot作成順だけを使う実装がA→B→A再入時にAfter Commit登録先を誤る問題を発見し、全Transactional Invocationの論理StackとPostgreSQL回帰Testへ修正した。修正後、Target PHPUnit 106 tests／766 assertions、Full PHPUnit 1056 tests／3608 assertions、Composer Root／Quickstart Validation、Mago Format／Lint／Analyze、Deptrac 0、Quickstart E2E、Skeleton通常／no-scripts Create-projectを独立再実行して成功した。Public API 134型、Management ID、Runtime Temporary Proxy API、`git diff --check`の各Guardも成功したため、P13-003をAcceptedとする。
