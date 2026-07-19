# P16-003 PostgreSQL Status Projection and Retention Report

Status: Accepted

## Summary

Public Status Queryへ、既存PostgreSQL Schemaだけを使う`PostgreSqlOperationStatusSource`を実装した。認可前はOperation ID、Operation Type、Origin Actorだけを投影し、Unknown／DenyではJournal Detail、Outcome、Dead Letter、Purge Auditへ触れない。Allow後だけDetailを同一Connectionの`REPEATABLE READ, READ ONLY` Snapshotで読み、Inline／Deferred State、Typed Outcome、Safe Rejection、Dead Letter、Retentionを投影する。

P16-002のInternal Source Contractも補正し、Expired FlagをSubjectから削除した。`readDetail()`の型付きResultでStatusまたはExpiredを返すため、Expired Evidenceも必ずAuthorization後に読む。

## Changed Files

### Production

- `src/Internal/Status/DefaultOperationStatusQuery.php`
- `src/Internal/Status/OperationStatusDetail.php`
- `src/Internal/Status/OperationStatusDetailExpired.php`
- `src/Internal/Status/OperationStatusDetailResult.php`
- `src/Internal/Status/OperationStatusJournalAttempt.php`
- `src/Internal/Status/OperationStatusJournalValidator.php`
- `src/Internal/Status/OperationStatusSnapshot.php`
- `src/Internal/Status/OperationStatusSource.php`
- `src/Internal/Status/OperationStatusSubject.php`
- `src/Internal/Status/PostgreSqlOperationStatusSource.php`
- `src/Internal/Status/ValidatedOperationStatusJournal.php`
- `src/Transport/PostgreSql/PostgreSqlStatusDeferredState.php`
- `src/Transport/PostgreSql/PostgreSqlStatusFailureKind.php`
- `src/Transport/PostgreSql/PostgreSqlStatusReadFailed.php`
- `src/Transport/PostgreSql/PostgreSqlStatusReader.php`
- `src/Transport/PostgreSql/PostgreSqlStatusSubject.php`

### Tests

- `tests/Internal/Status/DefaultOperationStatusQueryTest.php`
- `tests/Transport/PostgreSql/PostgreSqlStatusReaderTest.php`
- `tests/Transport/PostgreSql/PostgreSqlStatusQueryIntegrationTest.php`

### Documentation and Orchestration

- `docs/internal/status-query.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P16-003-postgresql-status-projection.md`

## Decisions and Assumptions

- SubjectはOperation ID、Operation Type、Origin Actorまたはnullだけを保持する。Expired、State、Outcome、Retention Evidenceを保持しない。
- Operations Rowがある場合はそのOperation TypeをAuthorityとし、JournalのTypeと一致する場合だけOrigin Actorを使う。Operations Rowだけが残る場合のOrigin Actorは`null`とした。
- Journalが存在するDetailは、認可に使ったSubjectとJournalのOperation ID、Operation Type、Origin Actorのnull性・ID・Typeを厳密照合する。Journal Retention済みで双方Actor不明のTerminal経路は維持する。
- Detailの複数Table Readは同一DBAL Connectionの単一Snapshotへ固定した。既存の制御外Transactionへの相乗りはStorage Failureとし、Isolation／Read Onlyを実行時にも検証する。
- Canonical JournalはLifecycleだけでなく、Sequence、Operation Identity、Strategy、Actor Context、Attempt、Retry Dataも照合する。
- DateTimeの同一性はInstantとMicrosecondsを保持する`U.u`で比較する。
- Migration、Schema、Public APIは変更していない。

## Subject SQL Projection Evidence

Operations Subject QueryのSELECT Resultは`operation_id`と`operation_type`だけである。Journal Subject Queryは最初のSequenceを対象に、PostgreSQL JSON Pathで次だけを抽出する。

```text
operation_id
operation_type
origin_actor_id
origin_actor_type
```

`PostgreSqlStatusReaderTest::testSubjectSqlProjectionDoesNotSelectRestrictedColumnsOrWholeCanonicalRecord`で、Subject SELECTに`encoded_record`のAlias、Journal Data、Transport Payload、Encoded Context、State、Outcome、Purge Auditが含まれないことを固定した。`testDenyDoesNotReadAnyDetailTable`ではDetail Tableを削除した状態でもDenyがUnavailableを返すため、Deny時にDetail Queryが発行されないことを実DBで証明した。

## Source Authority Matrix

| Kind | State authority | Terminal detail authority |
| --- | --- | --- |
| Inline | Canonical Journal | Completed Outcome／Rejected Safe Reason／Failed Event |
| Deferred受付前Terminal | Canonical Journal | Rejected Safe Reason／Failed Event |
| Deferred受付後 | Operations Row | Outcome Store／Journal Safe Reason／固定Failure Code／Dead Letter Evidence |

DeferredのJournalが残る場合はOperations RowのOperation Type、Schema Version、State、Next Sequence、Attemptと照合する。JournalがPurge済みの場合はTerminal StateとPurge Auditを要求する。受付済みDeferredのOperations Row欠落はIntegrity Failureである。

## State／Attempt／Retry Projection Matrix

| Internal state | Public state | Validation |
| --- | --- | --- |
| `accepted` | `accepted` | Attempt 0、Current Attemptなし |
| `running` | `running` | Attempt 1以上、Current AttemptとJournal一致 |
| `supervising` | `running` | Attempt 1以上、Current Attemptなし、最後のJournal Attempt番号と一致 |
| `retry_scheduled` | `retry_scheduled` | Attempt 1以上、`available_at`とRetry JournalをMicrosecondsまで照合 |
| `completed` | `completed` | Terminal Journal／Outcome整合 |
| `rejected` | `rejected` | Terminal Journal必須 |
| `failed` | `failed` | Operations Rowから固定Codeへ投影可能 |
| `dead_lettered` | `dead_lettered` | Dead Letter Row／Purge Auditの排他性を要求 |

Integration Testで7 Stateすべてと`supervising -> running`を実DB Projectionした。

## Outcome／Rejection／Dead Letter Matrix

| Evidence | Projection |
| --- | --- |
| Completed + Outcome Row | Registry期待ClassとDecode済みOutcome Classを厳密照合してTyped Outcomeを返す |
| Rejected + Journal | Safe Category／Codeだけを返す |
| Failed | 固定`operation_failed`を返す |
| Dead Lettered + RowまたはPurge Audit | 固定`operation_dead_lettered`を返す |
| Dead Letter RowとPurge Auditが両方存在／両方不在 | Integrity Failure |

Journal Rejection Message、Exception、Violation、Actor、Dead Letter Reason／Payload／Attempt IDはStatusへ投影しない。

## Retention／Expired Matrix

| Situation | Result |
| --- | --- |
| Allow + Completed Outcomeなし + Outcome Purge Audit | Expired |
| Allow + Rejected Journalなし + Journal Purge Audit | Expired |
| Deny + 同じRetention Evidence | Unavailable、Detail未読 |
| Failed Journal Purge | Found、固定Code |
| Dead Letter Journal／Row Purge | Found、固定Code |
| Transport Payload Tombstoneのみ | Found |
| Subject Identityも完全Purge | Unavailable |
| Evidenceなしの欠落／Auditとの併存 | Integrity Failure |

## Integrity and Safe Failure Matrix

| Failure | Source classification |
| --- | --- |
| DBAL／PDO接続・Query失敗 | Storage |
| Canonical Journal／Outcome Decode失敗 | Decode |
| Invalid Operation ID／Type、Sequence Gap、Lifecycle／Identity／Attempt／State不整合 | Integrity |
| Outcome型不一致、Retention Evidence競合 | Integrity |

Detail Snapshotは`begin -> SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY -> read -> commit`とし、例外時はRollbackする。Integration Testは同じConnectionがOutcome Decode中もTransaction Activeであること、Isolationが`repeatable read`、Read Onlyが`on`であること、正常時とIntegrity／Decode失敗時の両方でTransactionが残らないことを確認した。

Orchestrator Reviewで、`previous`を持たないOutcome Codec ExceptionがStorageへ分類される経路を検出した。分類を例外連鎖ベースへ修正し、DBAL／PDOを含む場合だけStorage、それ以外のCanonical Journal／Outcome読取失敗をDecodeとした。Completed Outcomeの`schema_version = 99`を実DBへ投入する回帰TestでPublic `status_query.decode_failed`とRollback済みConnectionを確認した。

追加Reviewでは、Deferred Detailが認可時SubjectのOrigin ActorをJournalと再照合していない経路を検出した。Authorizer callbackで全Journal RecordのOrigin Actor IDを変更する実DB回帰Testを追加し、Allow済みでもPublic `status_query.integrity_failed`となりStatusを返さず、Snapshot TransactionがCleanupされることを確認した。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid。

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid。

docker compose run --rm app mago format --check src tests
Result: All files are already formatted。

docker compose run --rm app mago lint
Result: No issues found。

docker compose run --rm app mago analyze
Result: No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Status tests/Internal/Status \
  tests/Transport/PostgreSql/PostgreSqlStatusReaderTest.php \
  tests/Transport/PostgreSql/PostgreSqlStatusQueryIntegrationTest.php \
  tests/Internal/Diagnostics/OperationDiagnosticsQueryTest.php \
  tests/Transport/PostgreSql/PostgreSqlDiagnosticsReaderTest.php \
  tests/Transport/PostgreSql/PostgreSqlDiagnosticsQueryIntegrationTest.php \
  tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php \
  tests/Transport/PostgreSql/PostgreSqlOutcomeStoreTest.php
Result: OK (110 tests, 498 assertions)。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1393 tests, 5379 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2481 / Warnings 0 / Errors 0。

Management ID／Internal PublicApi／Restricted Field／Schema DDL／git diff --check Guards
Result: 全成功。Migration／Schema差分なし。
```

初回Mago Format Checkは6 Fileの整形差分を検出したため、Mago Formatterを適用した。初回Lintは複雑度と冗長構文を検出し、Snapshot DTOへの責務集約とTimestamp比較Helperで解消した。最終のFormat／Lint／Analyzeはすべて成功している。

## Acceptance Criteria

- [x] SubjectからExpiredを除き、Allow後のDetail ResultでExpiredを表現した
- [x] Unknown／DenyでDetail、Journal、Outcome、Purge Auditを読まない
- [x] Subject SQLはOperation TypeとOrigin Actorだけを投影し、Canonical Record／Payload／Context全体を返さない
- [x] Inline Completed／Rejected／FailedをJournalから投影した
- [x] Deferred 7 StateをOperations Row Authorityで投影した
- [x] `supervising`をPublic `running`へ投影した
- [x] Retry Scheduledが正しいAttemptとUTC Retry Atを返す
- [x] Completed Typed OutcomeをRegistry Expected Typeと照合した
- [x] RejectedはSafe Category／Codeだけを返す
- [x] Outcome／Journal RetentionがAllow後だけExpiredを返す
- [x] Dead Letter Row／Purgeの排他性を検証し固定Public Codeだけを返す
- [x] Sequence／Lifecycle／Identity／Attempt／State／Retention不整合をIntegrity Failureにする
- [x] Raw Value、Violation、Actor、Exception、Dead Letter MessageをStatus／Exceptionへ露出しない
- [x] Migration、Public API、HTTP、Frontendを変更していない
- [x] Required PHP／PostgreSQL Quality Gateが成功した
- [x] WorkerはCommitしていない

## Remaining Issues

実装上のBlockerと仕様矛盾はない。HTTP Resource、Deferred 202 Header、Generated `.status()`／`.wait()`は後続Taskの責務である。

## Orchestrator Review

認可前SubjectのSQL Projection、Unknown／Deny時のDetail未読、Allow後のRetention判定、単一Connection上の`REPEATABLE READ, READ ONLY` Snapshotを確認した。追加Reviewで、Codec失敗のDecode／Storage分類と、認可後にJournal Origin Actorが変化した場合のIntegrity検出を補正し、どちらも実DB回帰Testで固定した。

Orchestrator再実行でComposer Root／Quickstart、Mago format／lint／analyze、対象110 tests／498 assertions、全1393 tests／5379 assertions、Deptrac 0違反／0警告／0エラー、Management ID／Internal Public API／Restricted Field／DDL／`git diff --check`の全Guardが成功した。Public API、Migration、Schemaの範囲逸脱と仕様矛盾はなくAcceptedとした。

## Suggested Next Action

P16-003をCommit／Pushする。その後、P16-004で`GET /operations/{operationId}`とDeferred 202の`Location`／`Retry-After`を実装する。
