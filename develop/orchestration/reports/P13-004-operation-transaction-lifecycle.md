# P13-004: Operation Transaction Lifecycle Report

Status: Accepted

## Summary

Operation Definitionまたは自己処理`handle()`の`#[Transactional]`をBuild時にDefault／Named Connectionへ解決し、Operation Manifestへ保存するMetadata Contractを実装した。Application-aware `build:compile`と`operation:list`は同じDatabase Configuration Snapshotで解決し、Database Configurationなしまたは未知のConnectionをRuntime前に拒否する。

Inline／Deferredへ共通`OperationTransactionCoordinator`を注入し、Authorization後にP13-003の`TransactionRuntime`を使う固定Transaction Stageを開始する。同一Connectionは名前ではなく`DatabaseManager`が返す`Connection`のObject Identityで判定する。Inlineは業務更新と成功Terminal Canonical Journalを一つのCommitへ含め、Observed ProjectionをCommit後に配送する。Deferredは業務更新、Fencing、Result State、Sequence、Terminal Journal、Outcomeを一つのCommitへ含める。

Rejected、`OperationRejectedException`、Throwable、Rollback-only、Fencing Failure、Outcome FailureはApplication TransactionをRollbackしてから既存Rejected／Supervision境界へ渡す。別ConnectionはApplication Commit／After Commit後にFramework Terminal Transactionを実行し、非原子的保証を維持する。Operation AOP BindingはFoundation Pass-throughのままとし、固定Lifecycleとの二重Transactionを開始しない。

## Changed Files

- `src/Core/Registry/OperationMetadata.php`: 解決済みOptional Transaction Connection Metadataを追加
- `src/Internal/Registry/**`: Transaction Attribute Compile、Default／Known Connection検証、Manifest Encode／Decode互換を追加
- `src/Internal/Transaction/OperationTransactionCoordinator.php`: Operation固定Transaction、同一Connection Object Identity、Rejected Rollback境界を追加
- `src/Internal/Execution/InlineDispatcher.php`: Authorization後Transaction、同一Connection Terminal Commit、Commit後Observationを統合
- `src/Internal/Execution/DeferredWorkerRuntime.php`: Authorization後Transaction、同一Connection Completion、Rollback後Supervisionを統合
- `src/Internal/Runtime/**`、`src/Internal/Application/**`: HTTP／Workerへ同一DatabaseManager、Framework Connection、Transaction Runtime、Execution ScopeからCoordinatorを注入
- `src/Internal/Console/ApplicationBuildCompileCommand.php`、`src/Internal/Console/ApplicationOperationListCommand.php`: Database Snapshotに基づくTransaction Metadata Compileへ同期
- `tests/Internal/Registry/**`、`tests/Internal/Transaction/**`: Metadata、Manifest、Object Identity、Nested Rollback-only、別Connectionを検証
- `tests/Internal/Execution/**`: Inline Commit／Rollback／Commit後Observation／Authorization順序、Deferred PostgreSQL Atomic Completion／Rejected／Throwable／Retry／Fencing／Outcome Failureを検証
- `tests/Internal/Console/**`、`tests/Internal/Application/**`、`tests/Fixtures/Aop/**`: Application Buildとoperation:listの解決済みMetadata、Operation AOP Foundationを検証
- `docs/guide/attributes.md`、`docs/guide/core-api.md`、`docs/guide/database-and-transactions.md`、`docs/internal/bootstrap.md`: 公開保証と内部Compositionを同期
- `develop/TODO.md`、`develop/STATE.md`: Phase 13進捗を同期

## Decisions and Assumptions

- Operation TransactionはP13-003の`TransactionRuntime`を唯一のScope／After Commit Queueとして再利用し、Operation専用Scope Mapを追加しない。
- HandlerがRejected Resultを返した場合は内部SignalでRoot TransactionをRollbackし、Signal自体を既存Failure／Supervisionへ露出しない。
- 同一Connectionの判定はConnection NameではなくObject Identityを必須とする。同名でも別Instanceなら非原子的Pathを選ぶ。
- Deferred同一Connection Completionは既存`Connection::transactional()`を重ねず、Operation Root内でState／Journal／Outcomeを直接保存する。
- 別Connectionの成功Application Commit後にFramework Terminalが失敗しても通常Supervisionへ変換しない。Commit済み業務更新をRetryするPathを作らないためである。
- Deferred Acceptance時のAuthorizationは既存Framework Acceptance Transaction内で実行される。Worker再認可はApplication Operation Transaction開始前に実行される。
- `operation:list`にもBuildと同じTransaction Metadata Contextが必要なため、OrchestratorがTask PacketのAllowed Filesを追加した後に同期した。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --display-deprecations <P13-004 target tests>
Result: OK (225 tests, 1096 assertions)。

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstartともにvalid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: Format済み。Lint／AnalyzeともにNo issues found。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1985 / Warnings 0 / Errors 0。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1072 tests, 3695 assertions)。

bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-create-project.sh
Result: Quickstart E2E、Skeleton通常／no-scripts Create-projectが成功。

Public API Count 134型、Management ID Guard、Runtime Temporary Proxy API Guard、git diff --check
Result: すべて成功。
```

## Acceptance Criteria

- [x] Operation Class／自己処理`handle()`のTransactional ConnectionをBuild時に解決・検証しManifestへ保存した
- [x] Method-level指定がClass-level指定を上書きする
- [x] Metadataなしの既存Manifestを`null`としてDecodeし、非Transactional Operationを回帰させない
- [x] Authorization Rejection／Backend FailureではApplication Transactionを開始しない
- [x] Inline／Deferred HandlerをAuthorization後のOperation Transaction内で一度だけ実行する
- [x] 同一Connection一般Service TransactionがOperation RootへRequired参加する
- [x] Inner Failureを握りつぶしてもRollback-only RootをCommitしない
- [x] Inline同一Connectionで業務更新と成功Terminal Canonical Journalを一つのCommitへ含める
- [x] Inline Terminal ObservationをCommit後だけ配送する
- [x] Deferred同一Connectionで業務更新、Fencing、State、Sequence、Terminal Journal、Outcomeを一つのCommitへ含める
- [x] Terminal／Outcome／Fencing Failureで業務更新と成功TerminalをRollbackする
- [x] Rejected／ThrowableをApplication Transaction Rollback後に既存Lifecycleへ記録する
- [x] Retry／Dead Letter判定をRollback後に行い、Commit済み業務更新を通常RetryするPathを追加しない
- [x] Operation Transaction内After Commitを成功Commit後だけ実行し、Rollbackで破棄する
- [x] 別ConnectionとTransactional Commandだけの非Atomic GuaranteeをTest／Guideへ明示する
- [x] Operation AOP FoundationをPass-throughのまま維持し、二重Transactionを開始しない
- [x] PostgreSQL Integrationで同一Connection、Rejected、Throwable、Retry、Fencing、Outcome Failureを検証する
- [x] Public API／Attribute／Transaction Guide／Internal Bootstrapを同期する
- [x] Target／Full Quality Commandsが成功した
- [x] Report／STATEを更新し、CommitせずReviewへ返す

## Remaining Issues

- Request／Attempt開始時のConnection Health Check、終了時Leak検査、障害後Close／ReconnectはP13-005のScopeであり未実装。
- Commit結果がDriver／Database障害で不明な場合のExactly-onceは保証しない。
- Transactional Outbox Persistence／Relayは後続PhaseのScopeである。

## Suggested Next Action

P13-005 Long-running Connection SafetyのTask Packetを作成し、Request／Attempt単位のHealth Check、Leak検査、Close／Reconnect境界を実装する。

## Orchestrator Review

2026-07-18T06:01:34+09:00にOrchestratorがTask許可範囲、Operation Metadata／Manifest、Build／operation:list、同一Connection Object Identity、Inline Commit後Observation、Deferred Fencing／State／Journal／Outcome Atomicity、Rejected／Throwable／Retry／Supervision、別Connection非Atomic境界、AOP Foundation、公開DocumentationをReviewした。Blocking findingはない。

拡張Target PHPUnit 212 tests／1055 assertions、Full PHPUnit 1072 tests／3695 assertions、Composer Root／Quickstart Validation、Mago Format／Lint／Analyze、Deptrac 0、Quickstart E2E、Skeleton通常／no-scripts Create-projectを独立再実行して成功した。Public API 134型、Management ID、Runtime Temporary Proxy API、`git diff --check`の各Guardも成功したため、P13-004をAcceptedとする。
