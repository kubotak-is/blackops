# P13-004: Operation Transaction Lifecycle

Status: Accepted

## Goal

D096とPhase 13 Delivery Planに従い、Operation Definitionまたは自己処理`handle()`へ付与した`#[Transactional]`をCompiled Operation Metadataへ保存し、Authorization後のInline／Deferred共通固定Lifecycleとして実行する。

Application ConnectionとFramework Storeが同じ`DatabaseManager`内の同一Connection Instanceである場合、Handlerの業務更新と成功Terminal書込みを一つのCommitへ含める。Rejected／Throwable／Fencing FailureではApplication TransactionをRollbackしてから既存Lifecycleで記録し、一般Service用AOPだけでは得られないOperation固有の保証を実装する。

## In Scope

- Operation Definition／自己処理`handle()`のTransactional Metadata Compile
- Default／Named ConnectionのBuild-time解決とManifest保存
- Operation Manifest Encode／Decode／互換Validation
- Authorization後に開始するInline／Deferred共通Operation Transaction Stage
- 同一ConnectionでのHandler業務更新と成功Terminal Journal／OutcomeのAtomic Commit
- Inline Canonical Journal ObservationのCommit後配送
- Rejected／Throwable／Rollback-only／Transaction Failure時のRollback後Lifecycle記録
- Deferred Fencing、Retry、Dead LetterとApplication Transactionの境界
- Operation内から呼ぶ同一Connection Transactional ServiceのRequired参加
- Operation Transaction内のAfter Commit Queue
- 別Connection時の非原子的Guaranteeと一般Service Transactionとの差のTest／Documentation
- Public Core API Reference、Attribute Guide、Database Transaction Guide、Internal Bootstrapの同期

## Out of Scope

- Request／Attempt開始時のConnection Health Check
- 正常終了時のConnection Leak検査、Close／Reconnect
- Transaction失敗後のLong-running Process Connection再利用判断
- Heartbeat ConnectionのLifecycle変更
- ORM、Repository基底Class、Query Builder Wrapper
- Transactional Outbox、二相Commit、Exactly-once
- Inline OperationのDeferred Outcome Storeへの永続化
- QuickstartのRepository／Transactional Operation実例
- Documentation Website公開

## Relevant Specifications and Decisions

- `develop/decisions/096-phase-13-database-and-transaction-runtime.md`
- `develop/spec/02-lifecycle-and-journal.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/28-mvp-lifecycle-events.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/64-phase-13-delivery-plan.md`

## Files Allowed to Change

- `src/Core/Registry/OperationMetadata.php`
- `src/Internal/Registry/**`
- `src/Internal/Aop/**`
- `src/Internal/Transaction/**`
- `src/Internal/Execution/**`
- `src/Internal/Runtime/ProductionRuntimeComposer.php`
- `src/Internal/Runtime/ProductionRuntimeDependencies.php`
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php`
- `src/Internal/Application/ApplicationWorkerComposer.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/Console/ApplicationOperationListCommand.php`
- `src/Internal/Console/CompileBuildArtifactsCommand.php`
- `tests/Core/Registry/**`
- `tests/Internal/Registry/**`
- `tests/Internal/Aop/**`
- `tests/Internal/Transaction/**`
- `tests/Internal/Execution/**`
- `tests/Internal/Runtime/ProductionRuntimeSmokeTest.php`
- `tests/Internal/Application/ApplicationHttpConfigurationTest.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- `tests/Internal/Console/CompileBuildArtifactsCommandTest.php`
- `tests/Internal/Console/CompileHttpManifestCommandTest.php`
- `tests/Internal/Codec/ReflectionJsonOperationCodecTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Architecture/PublicApiArchitectureTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `tests/Fixtures/Aop/**`
- `docs/guide/attributes.md`
- `docs/guide/core-api.md`
- `docs/guide/database-and-transactions.md`
- `docs/internal/bootstrap.md`
- `develop/TODO.md`
- `develop/orchestration/reports/P13-004-operation-transaction-lifecycle.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerへ記録する。

## Metadata Contract

- Public `OperationMetadata`へBuild時に解決済みのOptional Transaction Connection Nameを追加する
- Operation Definition Classの`#[Transactional]`をOperation固定Lifecycleの宣言として扱う
- 自己処理OperationのPublic `handle()`にMethod-level `#[Transactional]`がある場合はClass-level指定を上書きする
- Attributeの`connection`が`null`ならApplication Database ConfigurationのDefault Connection NameへBuild時に解決する
- Explicit Connection Nameと解決済みDefaultはBuild時にKnown Connectionとして検証し、Manifestへ解決済みNameだけを保存する
- Database ConfigurationなしでTransactional OperationをCompileした場合は、実行時まで延期せずSecret非露出のBuild Errorにする
- Transactional Metadataなしの既存Manifest Entryは`null`としてDecodeし、既存非Transactional Operationの動作を変えない
- `#[HandledBy]`で分離したHandler側だけに付けた`#[Transactional]`は一般Service AOP Semanticsのままとし、Operation固定LifecycleのAtomic Terminal Guaranteeを与えない。Operation Definition自身が宣言した場合だけ固定Lifecycleを有効にする
- Operation Definition／自己処理`handle()`のAOP ProxyはP13-003同様Foundation pass-throughとし、固定Lifecycleとの二重Transactionを開始しない

## Fixed Lifecycle

### Common Ordering

```text
Received／Attempt Startedを既存境界でCommit
Authorizationを評価
Transactional MetadataがあればApplication Transactionを開始
Handlerを実行
  Completed -> 同一Connectionなら成功TerminalをTransaction内で保存 -> Commit
  Rejected  -> Rollback -> 既存Rejected境界で記録
  Throwable -> Rollback -> 既存Failure／Supervision境界で記録
Commit成功後にAfter Commit Queueを実行
```

- Authorization Rejection／Authorization Backend FailureではApplication Transactionを開始しない
- 同じConnectionの一般Service `#[Transactional]`はOperation Root ScopeへRequired参加する
- Inner FailureをHandlerが握りつぶしてもRollback-only RootをCommitしない
- Operation Transaction中に登録した`#[AfterCommit]` Invocationは成功Commit後だけ実行し、Rollbackでは破棄する
- Transaction外／Non-transactional Operationの既存Semanticsを回帰させない

### Same Connection

Transaction ConnectionとFramework ConnectionはConnection Nameだけでなく、`DatabaseManager`が返す同一Object Instanceかで判定する。

- Inline CompletedではHandler業務更新、`attempt.succeeded`、`operation.completed` Canonical Journal AppendをApplication Transactionへ含める
- Inline OutcomeはHTTP／Dispatcher Returnとして扱い、Deferred用Outcome Storeへ新規Rowを作らない
- Inline Terminal Canonical RecordのObserver配送はCommit成功後に行い、Rollback／Commit Failureでは配送しない
- Deferred CompletedではHandler業務更新、Claim Fencing検証、Result State、Sequence、`attempt.succeeded`、`operation.completed` Canonical Journal、Typed Outcomeを一つのCommitへ含める
- Terminal Journal／Outcome／Fencingのいずれかが失敗した場合はHandler業務更新もRollbackし、Completed StateやOutcomeを残さない
- Framework Storeの既存`Connection::transactional()`をApplication Root内で重ねず、一つの所有RootとしてCommitする

### Different Connection

- Application TransactionはHandler完了時にCommitし、その後にFramework Connectionの既存Terminal Transactionを実行する
- Application Commit、After Commit Callback、Framework Terminalの間に原子性、二相Commit、Exactly-onceを保証しない
- Framework Terminal FailureでCommit済みApplication更新を巻き戻せないことをGuideとTestで明示する
- Transactional Commandだけを呼ぶNon-transactional Operationも、Command Commit後にOperation Terminalを書く既存の非Atomic Guaranteeを維持する

## Rejected, Throwable, Fencing, and Supervision

- HandlerのRejected Result／`OperationRejectedException`はApplication TransactionをRollbackしてから既存Rejected Lifecycleへ渡す
- Handler Throwable、Rollback-only、Begin／Commit／Rollback Failure、同一Connection成功Terminal Failureは、Commit前なら業務更新をRollbackしてからInlineでは既存Throwable Surface、Deferredでは既存Supervisionへ渡す
- Deferred Claim Fencing検証は同一Connection成功Transaction内でCommit前に行い、Claim喪失時は業務更新、Terminal Journal、OutcomeをすべてRollbackする
- Retry／Dead Letter判定と記録はApplication Transaction Rollback後の既存Framework Transactionで行う
- Rollback済みAttemptだけをRetry可能とし、成功Commit済み業務更新を通常のRetry／Dead Letter経路で再実行しない
- Commit結果がDatabase／Driver障害で不明な場合までExactly-onceを主張しない
- After Commit Callback／Reporter FailureはP13-003どおりBest-effortであり、Commit済みOutcome、Journal、Settlement、呼出元Resultを変更しない

## Runtime Integration Constraints

- Operation TransactionはP13-003のTransaction Runtime／Scope／After Commit Queueを再利用し、別のConnection Scope Mapを重複実装しない
- Inline DispatcherとDeferred WorkerへOptionalな内部Operation Transaction Coordinatorを注入し、Transactional Metadataがない場合は既存Pathを維持する
- HTTP／Worker Composerが注入済みDatabaseManager、Framework Connection、Transaction Runtime、Execution Scopeを同じCoordinatorへ渡す
- Production InvocationごとにAttribute Reflection／Source Scanを行わずManifest Metadataだけを読む
- Runtime ArtifactへConnection Parameter、Credential、Environment Snapshot、Live Connectionを保存しない
- Canonical ObservationをDatabase Commit条件にせず、Commit成功後のBest-effort配送とする
- Production Code／TestのCommentとDocBlockへDecision／Spec／Task管理番号を書かない

## Acceptance Criteria

- [ ] Operation Class／自己処理`handle()`のTransactional ConnectionがBuild時に解決・検証されManifestへ保存される
- [ ] Method-level指定がClass-level指定を上書きする
- [ ] Transaction Metadataなしの既存Manifest／Operationが回帰しない
- [ ] Authorization Rejection／FailureではApplication Transactionを開始しない
- [ ] Inline／DeferredのHandlerがAuthorization後のOperation Transaction内で一度だけ実行される
- [ ] 同一Connectionの一般Service TransactionがOperation RootへRequired参加する
- [ ] Inner Failureを握りつぶしてもRollback-onlyにより成功TerminalをCommitしない
- [ ] Inline同一Connectionで業務更新と成功Terminal Journalが一つのCommitになる
- [ ] Inline Terminal ObservationがCommit後だけ配送される
- [ ] Deferred同一Connectionで業務更新、Fencing、State、Sequence、Terminal Journal、Outcomeが一つのCommitになる
- [ ] Terminal／Outcome／Fencing Failureで業務更新と成功Terminalが残らない
- [ ] Rejected／ThrowableはApplication Transaction Rollback後に既存Lifecycleへ記録される
- [ ] Retry／Dead LetterがRollback後に記録され、Commit済み業務更新を再実行するPathを作らない
- [ ] Operation Transaction内のAfter CommitがCommit後だけ実行され、Rollbackで破棄される
- [ ] 別ConnectionとTransactional Commandだけの非Atomic GuaranteeをTest／Guideが明示する
- [ ] Operation AOP Foundationが二重Transactionを開始しない
- [ ] PostgreSQL Integrationで同一／別Connection、Rejected、Throwable、Rollback-only、Fencing、Outcome Failureを検証する
- [ ] Public API／Attribute／Transaction Guide／Internal Bootstrapが同期する
- [ ] Target／Full Quality Commandsが成功する
- [ ] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Required Commands

```bash
docker compose run --rm app mago format src/Core/Registry src/Internal/Registry src/Internal/Aop src/Internal/Transaction src/Internal/Execution src/Internal/Runtime src/Internal/Application src/Internal/Console tests/Core/Registry tests/Internal/Registry tests/Internal/Aop tests/Internal/Transaction tests/Internal/Execution tests/Internal/Runtime tests/Internal/Application tests/Internal/Console tests/Integration tests/Architecture tests/Fixtures/Aop
docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Core/Registry tests/Internal/Registry tests/Internal/Aop tests/Internal/Transaction tests/Internal/Execution/InlineDispatcherTest.php tests/Internal/Execution/DeferredWorkerRuntimeTest.php tests/Internal/Console/ApplicationBuildCompileCommandTest.php tests/Internal/Console/CompileBuildArtifactsCommandTest.php tests/Internal/Runtime/ProductionRuntimeSmokeTest.php tests/Integration/ApplicationHttpRuntimeTest.php tests/Integration/ApplicationConsoleKernelTest.php tests/Architecture/PublicApiArchitectureTest.php tests/Architecture/QuickstartApplicationArchitectureTest.php
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

## Expected Report

`develop/orchestration/reports/P13-004-operation-transaction-lifecycle.md`へ次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
