# P16-003: PostgreSQL Status Projection and Retention

Status: Ready

## Goal

P16-002のPublic QueryへPostgreSQL Sourceを接続し、Inline／DeferredのLifecycle State、Typed Outcome、Safe Rejection、Retention Evidenceを既存Schemaから安全に投影する。

認可前はOperation ID、Operation Type、Origin Actorだけを取得し、Expired、State、Outcome、Terminal Error、Canonical Recordを読まない。Authorizer Allow後だけDetail Sourceを読み、Foundまたは認可済みExpiredを返す。

## In Scope

- Internal Source ContractのSubject／Detail Result分離
- PostgreSQL認可Subject Reader
- Operations Row、Canonical Journal、Outcome Store、Dead Letter、Purge Auditを使うStatus Source
- Inline／DeferredのSource AuthorityとLifecycle Integrity検査
- Internal `supervising`からPublic `running`への投影
- Accepted／Running／Retry ScheduledのAttempt／Retry At投影
- Completed Typed OutcomeとOperation RegistryのOutcome Type整合
- Rejected Safe Category／Code
- Failed／Dead Lettered固定Public Code
- Outcome／Journal／Dead Letter RetentionとExpired／Integrity境界
- PostgreSQL Unit／Integration Test、既存Diagnostics／Retention Regression
- Internal Documentation、Report、TODO、STATE同期

## Out of Scope

- Public PHP APIの追加または変更
- HTTP Route／Responder／Application Composition／Configuration
- Deferred 202の`Location`／`Retry-After`
- Frontend Contract／Generator／TypeScript／`.status()`／`.wait()`
- Quickstart、Skeleton、Guide、Website、Consumer E2E
- Migration、Table、Index、Column、Constraintの追加または変更
- Encoded ContextからのOrigin Actor復元
- Raw Journal／Payload／Context／Violation／Exception／Dead Letter DetailのPublic投影
- List、Search、Cancel、Retry、Tenant Framework

## Relevant Specifications and Decisions

- `develop/spec/02-lifecycle-and-journal.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/24-lifecycle-event-data.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/30-lifecycle-state-machine.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/37-postgresql-table-layout.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/spec/70-phase-16-delivery-plan.md`
- `develop/decisions/102-phase-16-deferred-status-and-outcome-api.md`

## Files Allowed to Change

### Production

- `src/Internal/Status/**`
- New `src/Transport/PostgreSql/PostgreSqlStatus*.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php`（既存Table名Accessorの再利用だけ。DDL変更禁止）
- `src/Transport/PostgreSql/PostgreSqlJournalSchema.php`（既存Table名Accessorの再利用だけ。DDL変更禁止）
- `src/Internal/Journal/LifecycleStateMachine.php`（副作用なし検証APIが必要な場合だけ）

### Tests and Fixtures

- `tests/Internal/Status/**`
- New `tests/Transport/PostgreSql/PostgreSqlStatus*.php`
- `tests/Internal/Journal/LifecycleStateMachineTest.php`（Production変更時だけ）
- `tests/Internal/Diagnostics/OperationDiagnosticsQueryTest.php`（回帰確認に必要な場合だけ）
- `tests/Transport/PostgreSql/PostgreSqlDiagnosticsReaderTest.php`（共有Reader変更時だけ）
- `tests/Transport/PostgreSql/PostgreSqlDiagnosticsQueryIntegrationTest.php`（共有Reader変更時だけ）
- `tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php`（回帰確認に必要な場合だけ）
- `tests/Transport/PostgreSql/PostgreSqlOutcomeStoreTest.php`（回帰確認に必要な場合だけ）
- P16-003専用の新規`tests/Fixtures/**`

### Documentation and Orchestration

- `docs/internal/status-query.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`（実装不能な矛盾を発見した場合だけ）
- `develop/spec/70-phase-16-delivery-plan.md`（Task境界の誤りを発見した場合だけ）
- `develop/TODO.md`
- `develop/orchestration/reports/P16-003-postgresql-status-projection.md`
- `develop/STATE.md`

上記以外の変更が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Internal Source Contract Correction

P16-002の`OperationStatusSubject`からExpired Flagを削除する。認可順序を次へ固定する。

```text
findSubject(OperationId)
  -> null: Unavailable
  -> Subject(operationId, operationType, originActor|null)
    -> Authorizer Deny: Unavailable
    -> Authorizer Allow
      -> readDetail(Subject)
        -> Detail(status): Found
        -> DetailExpired: Expired
```

- SubjectはOperation ID、Operation Type、Origin Actorまたはnullだけを持つ
- Subject SourceはRetention Evidence、State、Outcome、Terminal Errorを読まない
- `readDetail()`はAllow後だけ呼ばれる
- Detail ResultはStatusまたはExpiredを型で区別する
- Unknown／Denyは同じPublic Unavailable、Allow後のExpiredだけPublic Expiredになる
- Public `BlackOps\Status`型のShapeは変更しない

P16-002 Unit Testを新しい呼出順へ更新し、Deny時にPurge Auditを含むDetail Sourceへ触れないことを固定する。

## Pre-authorization Subject Projection

PostgreSQL Subject Readerは次だけを返す。

```text
operationId
operationType
originActor.id|null
originActor.type|null
```

- Operations Rowがある場合、Operation Typeの正本はOperations Row
- Journalがある場合、最初のSequenceのOperation TypeとOrigin ActorだけをPostgreSQL JSON Pathで抽出する
- Operations RowがなくJournalがある場合、Journalの最初のSequenceからInline／受付前Terminal Subjectを構成する
- Operations RowとJournalのOperation Typeが不一致ならIntegrity Failure
- Operations Rowだけが残る場合、Origin Actorは`null`
- Operations RowもJournalもなければUnavailable。Purge AuditだけからOperation Typeを推測しない
- Encoded ContextへFallbackしない
- `encoded_record`全体、`encoded_payload`、`encoded_context`、State、Outcome、Journal Data、Purge AuditをSELECT Resultへ含めない
- Actor ID／Typeは両方存在するか両方nullでなければIntegrity Failure

SQL内でCanonical RecordのJSONを解析することは許可するが、Canonical Record全体または`data`をPHPへ返してはならない。

## Detail Source Authority

Detail SourceはAllow後だけCanonical JournalとOutcomeをDecodeする。

### Journal Validation

- Sequenceは1から欠番／重複なし
- 全RecordのOperation ID、Type、Schema Version、Strategy、Correlation／Causation、Actor Contextは一致する
- Lifecycle State Machineで遷移を検証する
- Attempt ID／Number／Started AtとRetry Scheduled Dataを検証する
- Raw Value、Violation、Error Message、Exception、ActorをStatusへ投影しない

### Inline and Journal-only

- Operations RowなしのInlineはCanonical JournalをState Authorityとする
- CompletedはCompleted JournalのTyped Outcome、RejectedはSafe Category／Code、Failedは固定Codeを返す
- Deferred StrategyのJournal-onlyは`operation.accepted`前にTerminalとなったRejected／Failedだけ許可する
- Deferred Accepted後、Attempt開始後、Completed、Dead LetteredでOperations Rowがない場合はIntegrity Failure
- Publicに対応しない不完全なJournal Stateを推測でAccepted／Terminalへ丸めない

### Deferred

- Operations RowをCurrent State、Operation Type、Schema Version、Attempt Number、Retry Atの正本とする
- `supervising`はPublic `running`へ投影する
- AcceptedはAttempt 0、Running／Supervising／Retry ScheduledはAttempt 1以上を要求する
- Retry Scheduledの`retryAt`はOperations Rowの`available_at`
- Journalがある場合、導出State／Type／Schema／Sequence／AttemptとOperations Rowを照合する
- Journalがない場合、Journal Purge Auditが必要である。Non-terminal StateのJournal PurgeはIntegrity Failure
- RejectedはJournalのSafe Category／Codeが必要であり、Journal Purge済みならDetailExpired
- FailedはOperations Rowと固定Codeで復元でき、Journal Purge済みでもFound

### Completed Outcome

- Deferred Completedの正本はOutcome Store、Inline Completedの正本はCompleted Journal
- Operation RegistryのExpected Outcome Classと実Outcome Classを厳密に一致させる
- Deferred Outcomeあり＋Purge AuditありはIntegrity Failure
- Deferred Outcomeなし＋Purge AuditありはDetailExpired
- Deferred Outcomeなし＋Purge AuditなしはIntegrity Failure
- Non-completedでOutcomeまたはOutcome Purge Auditがある場合はIntegrity Failure
- Deferred Completed Journal OutcomeへFallbackしない

### Dead Letter

- Dead LetteredはDead Letter RowまたはDead Letter Purge Auditのどちらか一方を要求する
- 両方存在、両方不在、Non-dead StateにRow／Auditがある場合はIntegrity Failure
- Public Statusは固定`operation_dead_lettered`だけを返す
- `reason_message`、Reason Type、Payload、Attempt IDをStatusへ返さない

Transport Payload TombstoneはStatusをExpiredにしない。Origin Actorが取得できない場合は`null`のままAuthorizerへ渡す。

## Failure Mapping

- DB接続／Query失敗はSource Storage Failure
- Canonical Journal／Outcome Decode失敗はSource Decode Failure
- Row Field、Lifecycle、Identity、State、Attempt、Retention不整合はSource Integrity Failure
- Source Exception MessageへSQL、Table名、Payload、Actor、Codec Detailを含めない
- MissingをStorage／Decode Failureへ丸めず、上記Authority MatrixでUnavailable／Expired／Integrityを決める

## Acceptance Criteria

- [ ] SubjectからExpiredを除き、Allow後のDetail ResultでExpiredを表現する
- [ ] Unknown／DenyでDetail、Journal、Outcome、Purge Auditを読まない
- [ ] Subject SQLがOperation TypeとOrigin Actorだけを投影し、Canonical Record／Payload／Context全体を返さない
- [ ] Inline Completed／Rejected／FailedをJournalから投影する
- [ ] Deferred 7 StateをOperations Row Authorityで投影する
- [ ] `supervising`をPublic `running`へ投影する
- [ ] Retry Scheduledが正しいAttemptとUTC Retry Atを返す
- [ ] Completed Typed OutcomeがRegistry Expected Typeと一致する
- [ ] RejectedがSafe Category／Codeだけを返す
- [ ] Outcome／Journal RetentionがAllow後だけExpiredを返す
- [ ] Dead Letter Row／Purgeの排他性を検証し、固定Public Codeだけを返す
- [ ] Sequence／Lifecycle／Identity／Attempt／State／Retention不整合をIntegrity Failureにする
- [ ] Raw Value、Violation、Actor、Exception、Dead Letter MessageをStatus／Exceptionへ露出しない
- [ ] Migration、Public API、HTTP、Frontendを変更しない
- [ ] Required PHP／PostgreSQL Quality Gateが成功する
- [ ] WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Status \
  tests/Internal/Status \
  tests/Transport/PostgreSql/PostgreSqlStatusReaderTest.php \
  tests/Transport/PostgreSql/PostgreSqlStatusQueryIntegrationTest.php \
  tests/Internal/Diagnostics/OperationDiagnosticsQueryTest.php \
  tests/Transport/PostgreSql/PostgreSqlDiagnosticsReaderTest.php \
  tests/Transport/PostgreSql/PostgreSqlDiagnosticsQueryIntegrationTest.php \
  tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php \
  tests/Transport/PostgreSql/PostgreSqlOutcomeStoreTest.php
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
! rg -n '#\[PublicApi\]' src/Internal/Status src/Transport/PostgreSql/PostgreSqlStatus*.php
! rg -n 'reason_message|purged_by' src/Transport/PostgreSql/PostgreSqlStatus*.php
! git diff -- src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php src/Transport/PostgreSql/PostgreSqlJournalSchema.php | rg '^\+.*(CREATE|ALTER|DROP|INDEX|CONSTRAINT|COLUMN)'
git diff --check
```

新規Test File名が責務分割で異なる場合は、実在するP16-003対象Testをすべて指定して同等以上の範囲を実行する。Subject SQLのSecurity Evidenceは、実行QueryのSELECT ResultにRestricted Fieldが含まれないことをTestとReportへ記録する。

## Expected Report

`develop/orchestration/reports/P16-003-postgresql-status-projection.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Subject SQL Projection Evidence
- Source Authority Matrix
- State／Attempt／Retry Projection Matrix
- Outcome／Rejection／Dead Letter Matrix
- Retention／Expired Matrix
- Integrity and Safe Failure Matrix
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
