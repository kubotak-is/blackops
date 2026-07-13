# P6-010: Typed PostgreSQL Outcome Store Report

Status: Completed

## Summary

- Typed Public `OutcomeRecord`、`OutcomeReader`、`OutcomeWriter`、`OutcomeStore`と専用`OutcomeStoreException`を追加した。
- Public RecordはOperation ID、復元済み`Outcome`、UTC正規化済みCompletion時刻だけを公開し、Persistence型を露出しないようにした。
- PostgreSQL `outcomes` Table、Operation ID一対一Primary Key、Operationsへの`ON DELETE RESTRICT` FK、Completion時刻Indexを追加した。
- Adapter内部のType／Schema Version／JSON Payload CodecとPostgreSQL Storeを実装した。
- Decode前にRow TypeとPayload Classの一致、Class存在、`Outcome`実装を確認し、不正Class Constructorを呼ばず拒否するようにした。
- Duplicate、Unknown Operation、Non-completed Operation、Unknown Schema、Corrupt Payload、Type mismatch、Non-Outcomeを専用Exceptionで拒否した。
- Deferred Worker完了TransactionへOutcome保存を接続し、State／Canonical Journal／Outcomeを同一ConnectionでCommitするようにした。
- Outcome保存失敗時にCompleted StateとCompletion Journalを含むTransaction全体がRollbackすることを確認した。
- Rejected、Failed、Retry Scheduled、Dead LetteredではOutcomeを保存しないことを確認した。
- Retention PlannerへOutcome候補を追加し、Active HoldをPlanから除外した。
- Outcome削除時にもHoldを再確認し、Row削除とPurge Auditを同一TransactionでCommitするServiceを追加した。
- `RetentionPurgeResult`へOutcome削除件数を追加し、既存Constructor呼出しとの互換を維持した。

## Changed Files

- `src/Outcome/OutcomeRecord.php`
- `src/Outcome/OutcomeReader.php`
- `src/Outcome/OutcomeWriter.php`
- `src/Outcome/OutcomeStore.php`
- `src/Outcome/Exception/OutcomeStoreException.php`
- `tests/Outcome/OutcomeRecordTest.php`
- `src/Transport/PostgreSql/PostgreSqlEncodedOutcome.php`
- `src/Transport/PostgreSql/PostgreSqlOutcomeCodec.php`
- `src/Transport/PostgreSql/PostgreSqlOutcomeSchema.php`
- `src/Transport/PostgreSql/PostgreSqlOutcomeStore.php`
- `src/Transport/PostgreSql/PostgreSqlOutcomeRetentionDeleteService.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php`
- `src/Transport/PostgreSql/PostgreSqlRetentionPlanner.php`
- `src/Transport/PostgreSql/PostgreSqlRetentionPurgeService.php`
- `tests/Transport/PostgreSql/PostgreSqlOutcomeStoreTest.php`
- `tests/Transport/PostgreSql/PostgreSqlOutcomeRetentionDeleteServiceTest.php`
- `tests/Transport/PostgreSql/PostgreSqlRetentionPlannerTest.php`
- `tests/Transport/PostgreSql/PostgreSqlRetentionPurgeServiceTest.php`
- `src/Internal/Execution/DeferredWorkerRuntime.php`
- `src/Internal/Execution/DeferredWorkerRuntimeStorage.php`
- `tests/Internal/Execution/DeferredWorkerRuntimeTest.php`
- `src/Core/Retention/RetentionPurgeResult.php`
- `tests/Core/Retention/RetentionPurgeResultTest.php`
- `deptrac.yaml`
- `docs/guide/outcome-retrieval.md`
- `docs/guide/README.md`
- `docs/internal/outcome-store.md`
- `docs/internal/README.md`
- `docs/internal/worker-runtime.md`
- `docs/internal/retention-plan.md`
- `develop/TODO.md`
- `develop/decisions/060-typed-outcome-store-contract.md`
- `develop/orchestration/tasks/P6-010-typed-postgresql-outcome-store.md`
- `develop/orchestration/reports/P6-010-typed-postgresql-outcome-store.md`
- `develop/STATE.md`

## Decisions and Assumptions

- `BlackOps\Outcome`を独立Public LayerとしてDeptracへ追加し、依存をCoreだけに限定した。TransportとInternalはOutcome Public Contractへ依存できる。
- `OutcomeStoreException`は派生Hierarchyを現時点で拡張点にしないため`final`とした。
- `OutcomeRecord`はConstructorでCompletion時刻をUTCへ正規化し、復元済み`Outcome`を保持する。
- PostgreSQL Outcome Schema Versionは1とし、`outcome_type`とEncoded JSON内Classを重複保持してHydration前に照合する。
- Type照合、Class存在、`Outcome`実装確認をすべてHydrationより先に行う。Corrupt Persistence Dataが無関係なConstructorを実行しないことをTestした。
- Store Saveは`INSERT ... SELECT`でOperations Rowが`completed`の場合だけ一件保存する。DBAL Driver境界を考慮し、Affected Countは`(int)`へ正規化して判定する。
- Duplicate SaveはPrimary Keyで拒否し、既存Outcomeを更新しない。Unknown／Non-completed OperationはRowを作成しない。
- Workerは完了時刻を一度だけClockから取得し、State更新とOutcome Recordへ同じ値を使う。Outcome WriterはLifecycle／Journalと同じDBAL ConnectionのStoreを注入する。
- Canonical Journalの既存Completed Outcome保存は維持し、Typed Outcome Tableは取得と独立RetentionのためのProjectionとして同じTransactionで保存する。
- Outcome RetentionのBasisは`completed_at`、EligibilityはBasis + Outcome Policy期間とする。PlannerとDeleteの双方でActive Holdを確認する。
- Outcome Delete ServiceはAudit Portが同じConnectionへ参加するCompositionを前提とし、一つのDBAL Transaction内でDeleteとAuditを実行する。Audit例外時のDelete rollbackをIntegration Testした。
- `RetentionPurgeResult`は既存の`plan, transportPayloads, deadLetters`引数順を維持し、OptionalなOutcome Countを第4引数へ追加した。既存Console／Scheduler Test Doubleは変更不要である。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'OutcomeRecord|OutcomeStore|DeferredWorkerRuntime|RetentionPlanner|RetentionPurge'
Result: OK (48 tests, 258 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (560 tests, 1754 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 307 files / Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1244 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

初回Targeted Testは47件・254 Assertionsで成功した。Non-completed Operationへの直接SaveもStore自体で拒否すべき境界を差分Reviewで追加し、FK／Index検証と合わせて最終48件・258 Assertionsで成功した。

Outcome Codecの初期実装はGeneric Value CodecでHydrateした後にRow Typeを比較していた。Reviewで不正Class Constructor実行の危険を検出し、JSON ClassとRow Type、Class存在、Outcome実装をHydration前に検証する順序へ修正した。

## Acceptance Criteria

- [x] Typed Public Outcome Record／Reader／Writer／Storeが追加される
- [x] Public Outcome APIがInternal／Library型を露出しない
- [x] PostgreSQL Schemaが一対一`outcomes` Tableを作成する
- [x] Outcome StoreがTyped Outcomeを保存・取得できる
- [x] Unknown Operationはnullを返す
- [x] Duplicate、Unknown Schema、Corrupt Payload、Non-Outcome型を専用Exceptionで拒否する
- [x] Worker成功時にState／Journal／Outcomeが同一TransactionでCommitされる
- [x] Outcome保存失敗時に完了Transaction全体がRollbackする
- [x] Rejected／Failed／Retry／Dead LetterはOutcome Rowを作らない
- [x] Retention Plannerが期限切れOutcomeを計画しActive Holdを除外する
- [x] Outcome PurgeがRowとAuditを同一Transactionで保存し件数を返す
- [x] Typed Outcome取得とRetentionがDocumentationへ記録される
- [x] 必須Commandがすべて成功する

## Remaining Issues

- なし。

## Suggested Next Action

- MVP残作業の次Task Packetへ進む。

## Orchestrator Review

- Public Outcome LayerがCore型とPHP標準型だけを公開し、Internal／Doctrine／PostgreSQL／Encoded Payloadを露出しないことを確認した。
- Outcome RecordのUTC正規化、Reader／Writer分離、Store結合、専用final Exceptionを確認した。
- Row Type、Payload Class、Class存在、Outcome実装をHydration前に検証し、不正Class Constructorを実行しないことをTestで確認した。
- Completed Operationだけを保存し、Duplicate／Unknown／Non-completed Operationで既存Rowを上書きしないことを確認した。
- Worker成功時にState／Canonical Journal／Outcomeが同一TransactionでCommitされ、Outcome失敗時に全体Rollbackすることを確認した。
- Rejected／Failed／Retry／Dead LetterがOutcomeを作らないことを確認した。
- Outcome RetentionがCompleted Atを基準にActive HoldをPlanner／Delete双方で確認し、Audit失敗時にDeleteをRollbackすることを確認した。
- Targeted PHPUnitを再実行し、`OK (48 tests, 258 assertions)`を確認した。
- Mago LintとDeptracを再実行し、Issues、Violations、Warnings、Errorsが0であることを確認した。
- Review指摘およびBlockerはない。
