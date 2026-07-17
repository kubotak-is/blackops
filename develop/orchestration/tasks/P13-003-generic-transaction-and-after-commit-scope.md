# P13-003: Generic Transaction and After Commit Scope

Status: Accepted

## Goal

D096とPhase 13 Delivery Planに従い、DI管理されたCommand Service／Application Serviceの`#[Transactional]`をNamed Doctrine DBAL ConnectionのRequired Transactionとして実行し、`#[AfterCommit]` Invocationを最外Commit後に処理するRuntimeを実装する。

Operation Definition／`handle()`のTransactionはP13-004のFramework固定Lifecycleが所有するため、このTaskではGeneric Transactionを開始しない。P13-002のBuild-time Proxy／Validationを維持しながら、一般Serviceだけに実行Semanticsを接続する。

## In Scope

- Public Transaction Runtime Exception
- Public After Commit Failure ValueとFailure Reporter Contract
- Default After Commit Failure ReporterとApplication提供Reporterの選択
- Named ConnectionごとのRequired Transaction Scope
- 同一ConnectionのNested ScopeとRollback-only
- 異なるNamed Connectionの独立Scope
- 開始済みManual Transactionとの混在Fail-fast
- Transactional Method Interceptor
- After Commit Method Interceptor
- Invocation Queue、登録順実行、Rollback時破棄
- Commit後Callback FailureのBest-effort継続、相関Report、非Retry
- HTTP／Deferred WorkerでExecution ScopeとTransaction RuntimeをCompiled Containerへ同期
- Operation Transactional BindingをGeneric Transactionから除外するGuard
- Public API／Attribute／Transaction GuideとInternal Bootstrapの同期
- PostgreSQL Integration／Runtime／Consumer回帰Test

## Out of Scope

- Operation ManifestへのTransactional Metadata保存
- Operation Definition／`handle()`を囲むFramework固定Transaction Stage
- 業務更新とTerminal Journal／Outcomeの同一Transaction Commit
- Rejected／Throwable後のOperation Lifecycle記録統合
- Request／Attempt開始時の全Connection Health Check
- Connection Close／Reconnect、Transaction Leak後のProcess継続
- Quickstartの業務Repository／Transactional Command実例
- ORM、Repository基底Class、Query Builder Wrapper
- Transactional Outbox
- Documentation Website公開

## Relevant Specifications and Decisions

- `develop/decisions/096-phase-13-database-and-transaction-runtime.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/64-phase-13-delivery-plan.md`

## Files Allowed to Change

- `deptrac.yaml`
- `mago.toml`
- `src/Database/AfterCommitFailure.php`
- `src/Database/AfterCommitFailureReporter.php`
- `src/Database/Exception/**`
- `src/Internal/Aop/**`
- `src/Internal/Transaction/**`
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php`
- `src/Internal/Application/ApplicationWorkerComposer.php`
- `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`
- `src/Internal/Execution/DeferredWorkerRuntimeStorage.php`
- `src/Internal/Runtime/ProductionRuntimeDependencies.php`
- `tests/Database/**`
- `tests/Internal/Aop/**`
- `tests/Internal/Transaction/**`
- `tests/Internal/Application/ApplicationHttpConfigurationTest.php`
- `tests/Internal/Application/ApplicationWorkerComposerTest.php`
- `tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php`
- `tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- `tests/Internal/Execution/DeferredWorkerRuntimeTest.php`
- `tests/Internal/Runtime/ProductionRuntimeSmokeTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Architecture/PublicApiArchitectureGuard.php`
- `tests/Architecture/PublicApiArchitectureTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `tests/Fixtures/Aop/**`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/skeleton-create-project.sh`
- `examples/quickstart/config/**`
- `docs/guide/attributes.md`
- `docs/guide/configuration.md`
- `docs/guide/core-api.md`
- `docs/guide/database-and-transactions.md`
- `docs/internal/bootstrap.md`
- `develop/TODO.md`
- `develop/orchestration/reports/P13-003-generic-transaction-and-after-commit-scope.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を広げずReportのBlockerへ記録する。

## Public Failure Contract

- `BlackOps\Database\Exception\TransactionException`は`#[PublicApi]`を持つRuntime Exceptionとする
- Manual Transaction衝突、Rollback-only、Begin／Commit／Rollback失敗をSecret非露出のMessageとPrevious Throwableで表現する
- `BlackOps\Database\AfterCommitFailure`は`#[PublicApi]`のImmutable Valueとし、Service Class、Method、Cause、登録時のOptional `ExecutionContext`を公開する
- Callback引数やDatabase CredentialをFailure Valueへ保存しない
- `BlackOps\Database\AfterCommitFailureReporter`は`#[PublicApi]` Interfaceとし、`report(AfterCommitFailure $failure): void`だけを公開する
- ApplicationがService ProviderでReporterを登録した場合はそれを使い、未登録時はFramework Default Reporterを使う
- Default ReporterはPSR-3／Monolog経由で`php://stderr`へGenericなStructured Errorを出し、Operation／Attempt／Correlation／Causation IDが存在すれば付与する
- Default LogへCallback引数、Throwable Message／Trace、Credentialを出さない

## Transaction Semantics

### Root Scope

1. Build時に解決済みのNamed ConnectionをDatabaseManagerから取得する
2. Framework Scopeがない状態でConnectionが既にActiveなら、所有者不明のManual TransactionとしてMethodを実行せずFail-fastする
3. Frameworkが`beginTransaction()`し、Methodを一度だけ実行する
4. 正常ReturnかつRollback-onlyでなければ`commit()`する
5. MethodがThrowableを投げるかRollback-onlyなら`rollBack()`し、After Commit Queueを破棄する
6. Commit成功後だけ登録順にAfter Commit Queueを実行する

### Nested Required

- 同じNamed ConnectionのNested `#[Transactional]`はDBALのNested Transaction／Savepointを開始せず、外側Scopeへ参加する
- Inner MethodがThrowableを投げた時点でRoot ScopeをRollback-onlyにする
- OuterがInner Throwableを握りつぶしてもRootはCommitせずRollbackし、`TransactionException`を投げる
- Nested ScopeのAfter Commit Queueは同じRoot Queueへ登録順で合流する

### Different Connections

- 異なるNamed Connectionは独立したRoot Scopeを開始できる
- Inner ConnectionのCommit／After CommitはInner Method Return時に完了する
- 後からOuter ConnectionがRollbackしてもInner Commitを巻き戻さない
- 二相CommitまたはConnection間の原子性を保証しない

### Manual Mixing and Cleanup

- Attribute Scope開始前に`Connection::isTransactionActive()`がtrueならFail-fastする
- Attribute管理MethodがDBAL Transaction Nesting Levelを直接変更して返った場合も混在として検出し、可能な限りFramework所有RootまでRollbackして`TransactionException`を投げる
- Begin／Commit／Rollback失敗ではScopeとQueueを必ず破棄し、次のInvocationへ状態を持ち越さない
- Connection Close／ReconnectはP13-005へ残す

## After Commit Semantics

- ActiveなFramework Transaction内で`#[AfterCommit]` Methodを呼ぶと、Invocation、Service／Method識別子、登録時Execution Contextを現在のRoot ScopeへQueueし、その場ではMethod本体を実行しない
- Outermost Commit成功後に登録順で各Invocationを一度だけ実行する
- Rollback／Rollback-only／Commit失敗ではQueueを破棄する
- Transaction外ではMethod本体を即時に一度実行し、Return／Throwableを通常どおり呼出元へ返す
- Commit後CallbackがThrowableを投げた場合はAfterCommitFailureReporterへ渡し、後続Callbackを続行する
- Reporter自身がThrowableを投げても後続Callback、Commit済みDatabase、呼出元Resultを変更しない
- Commit後Callback／Reporterを自動Retryしない
- Process Crash時のDeliveryを保証せず、Reliable DeliveryはPhase 17 Outboxへ残す

## AOP and Runtime Integration Constraints

- General ServiceのTransactional BindingだけをTransaction Interceptorへ置き換える
- `BlackOps\Core\Operation`を実装するDefinition／`handle()`はBuild ValidationとProxyを維持するが、P13-003ではGeneric Transactionを開始せずFoundation pass-throughにする
- `#[AfterCommit]`はDI管理ServiceのProxyだけで有効にする
- Build時にConnection Nameを解決し、Production InvocationごとのAttribute Reflection／Source Scanを行わない
- Ray.Aop生成Proxy、Symfony Compiled Container、Direct `new`境界を維持する
- Shared Transaction RuntimeとExecution ScopeはSyntheticまたはPrivate Runtime ServiceとしてContainerへ注入し、Public Service Locatorを追加しない
- HTTPはMiddleware／Policy／Handler解決前、WorkerはHandler／Policy解決前にRuntime Serviceを設定する
- HTTP Inline DispatcherとDeferred Worker RuntimeがReporterへ同じExecutionScopeProviderを共有する
- Application Reporter登録をFramework Defaultで上書きしない
- Runtime ArtifactへConnection Parameter、Credential、Environment Snapshot、Live Connectionを保存しない
- Callback引数をLog／Report Metadataへ自動展開しない
- Production Code／TestのCommentとDocBlockへDecision／Spec／Task管理番号を書かない

## Acceptance Criteria

- [ ] 一般ServiceのTransactional MethodがReturn時にCommitし、Throwable時にRollbackする
- [ ] 同一ConnectionのNested Scopeが一つのDB Transactionを共有する
- [ ] Inner FailureをOuterが握りつぶしてもRollback-onlyによりCommitしない
- [ ] 異なるNamed Connectionが独立Commitし、Outer RollbackでInner Commitが戻らない
- [ ] 開始済みManual TransactionとAttribute管理をMethod実行前に拒否する
- [ ] Attribute Method内のManual Nesting Leakを検出し、Framework Scope／Queueを残さない
- [ ] After Commit InvocationがCommit後に登録順で一度実行される
- [ ] Rollback／Rollback-only／Commit失敗でAfter Commit Queueが実行されない
- [ ] Transaction外のAfterCommit Methodが即時実行され、Throwableを通常どおり伝播する
- [ ] Commit後Callback失敗を全件Reporterへ渡し、後続CallbackとCommit済みDatabaseを変更しない
- [ ] Reporter失敗も後続Callbackを止めず、自動Retryしない
- [ ] Failureへ登録時Execution ContextのOperation／Attempt／Correlation／Causationを関連付けられる
- [ ] Default ReporterがGeneric Structured Errorを出し、Callback引数／Throwable詳細／Credentialを漏らさない
- [ ] Application提供AfterCommitFailureReporterを優先し、Framework Defaultで上書きしない
- [ ] OperationのTransactional AttributeがGeneric Service Commitを開始しない
- [ ] HTTP／Deferred Workerが同じExecution ScopeをTransaction Runtime／Reporterへ注入する
- [ ] Direct `new`、AttributeなしService、P13-002 Build Artifact Contractが回帰しない
- [ ] PostgreSQL IntegrationでCommit／Rollback／Nested／Manual／複数Connectionを検証する
- [ ] GuideがOperationと一般Serviceの保証差、Nested、Manual、After Commit Best-effort、Outbox境界を説明する
- [ ] Target／Full Quality Commandsが成功する
- [ ] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Required Commands

```bash
docker compose run --rm app mago format src/Database src/Internal/Aop src/Internal/Transaction src/Internal/Application src/Internal/DependencyInjection tests/Database tests/Internal/Aop tests/Internal/Transaction tests/Internal/Application tests/Internal/DependencyInjection tests/Internal/Execution tests/Internal/Runtime tests/Integration tests/Architecture tests/Fixtures/Aop
docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Database tests/Internal/Aop tests/Internal/Transaction tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php tests/Internal/Application/ApplicationHttpConfigurationTest.php tests/Internal/Execution/DeferredWorkerRuntimeTest.php tests/Internal/Runtime/ProductionRuntimeSmokeTest.php tests/Integration/ApplicationHttpRuntimeTest.php tests/Integration/ApplicationConsoleKernelTest.php tests/Architecture/PublicApiArchitectureTest.php tests/Architecture/QuickstartApplicationArchitectureTest.php
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
! rg -n 'Aspect::newInstance|sys_get_temp_dir|tempnam' src/Internal/Aop src/Internal/Transaction src/Internal/DependencyInjection src/Internal/Console/ApplicationBuildCompileCommand.php --glob '*.php'
git diff --check
```

Directoryが実装前に存在しない場合、対応Commandの該当Pathだけを省略し、Reportへ明記する。

## Expected Report

`develop/orchestration/reports/P13-003-generic-transaction-and-after-commit-scope.md`へ次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
