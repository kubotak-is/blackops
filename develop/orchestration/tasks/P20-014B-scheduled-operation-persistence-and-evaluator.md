# P20-014B: Scheduled Operation Persistence and Evaluator

Status: Accepted

## Goal

Scheduled Application OperationのPostgreSQL Schedule State／OccurrenceとCalendar Evaluatorを実装する。

複数Process、再実行、Process Crashが同じSchedule Slotへ収束し、Misfireは最新一件だけを実行候補、Overlapは新しい実行候補をSkipとして記録する状態までを完成させる。OperationのValue構築、Actor／Authorization、Inline／Deferred Invocation、Journal、CLI、Application Compositionは接続しない。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/134-scheduled-application-operation.md`
- `develop/spec/98-scheduled-application-operation.md`
- `develop/spec/21-clock-and-time.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/37-postgresql-table-layout.md`
- `develop/spec/55-project-generators-and-application-migrations.md`
- `develop/decisions/057-database-access-and-migration-library.md`
- Accepted P20-014A Source／Task／Report
- Current Framework Migration、DBAL Store、Identifier Factory Source and Tests

## In Scope

- Internal Calendar Evaluator using P20-014A `CronExpression`
- UTC minute-floor evaluation with configured IANA Timezone
- DST spring-gap suppression and fall-overlap first-UTC-only semantics
- PostgreSQL `schedule_states` and `schedule_occurrences`
- Versioned Framework Migration `Version20260728133000`
- Schedule State row lock and Operation Type reassignment rejection
- First-evaluation、Cursor、FireOnce Misfire、Overlap Skip
- `(schedule_name, scheduled_at)` uniqueness and nullable unique Operation ID
- Execution-candidate Operation ID allocation exactly once
- Recoverable claimed occurrence query for P20-014C
- Two-connection concurrency and rollback integration evidence
- Framework Migration inventory／current-schema／package-export regression
- Report／STATE／TODO synchronization

## Out of Scope

- OperationValue construction／Validation
- ScheduledActorProvider、Actor Context、Authorization
- Inline Dispatcher／Deferred Acceptance／Outbox
- ExecutionContext creation、Transport Codec、Journal projection
- Occurrence accepted／terminal transition wiring
- `operation:schedule:run`、Daemon、Console Kernel
- Application Runtime／Container／Configuration composition
- HTTP／Console／Frontend Manifest changes
- Retention purge implementation
- Guide、Website、Example、Consumer journey
- New Composer Dependency
- Commit、Push、PR、External Deploy

## Persistence Contract

`schedule_states`は最低限、次を保持する。

| Column | Contract |
| --- | --- |
| `schedule_name` | Primary Key。P20-014A Schedule Identity Grammar |
| `operation_type` | ManifestのOperation Type ID。既存Rowと異なるTypeへの付け替えを拒否 |
| `cursor_at` | 評価を完了したUTC Minute |
| `created_at`／`updated_at` | UTC `timestamptz` |

`schedule_occurrences`は最低限、次を保持する。

| Column | Contract |
| --- | --- |
| `schedule_name`／`scheduled_at` | Composite Primary Key。`scheduled_at`はUTC Minute |
| `evaluated_at` | Candidateを評価したUTC Instant |
| `state` | `claimed`、`accepted`、`completed`、`rejected`、`failed`、`dead_lettered`、`skipped_misfire`、`skipped_overlap` |
| `category` | Skip／Failureの安全なCategory。Canonical Value、Credential、Raw Errorを保存しない |
| `operation_id` | 実行候補だけが持つNullable UUID。非null値は一意 |
| `accepted_at` | P20-014Cが接続するNullable UTC Instant |
| `created_at`／`updated_at` | UTC `timestamptz` |

`claimed`と`accepted`を非Terminal、`completed`、`rejected`、`failed`、`dead_lettered`をTerminalとする。`skipped_misfire`と`skipped_overlap`は実行対象外で、Operation IDを持たない。P20-014Bは`claimed`を作成し、後続Stateへの遷移は接続しない。

Schema HelperとVersioned Migrationは同じTable／Constraint／Index Contractを持つ。Migrationは明示的な`database:migrate`だけが適用し、Evaluator、Build、HTTP、WorkerはDDLを実行しない。

## Evaluation Contract

- `ClockInterface::now()`をUTC Minuteへ切り下げ、その値を一回の評価上限とする
- ScheduleごとにPostgreSQL Transactionを開始し、State Rowを作成または`FOR UPDATE`でLockする
- 初回は現在Minuteだけを評価し、過去をBackfillしない
- 二回目以降はCursorより後、現在Minute以下のUTC Minuteを列挙する
- 各UTC MinuteをSchedule Timezoneへ変換し、Parsed Cron Fieldへ一致させる
- Spring Forwardで存在しないLocal Minuteは候補にしない
- Fall Backで同一Local Minuteが二回現れる場合、最初のUTC Instantだけを候補にする
- 一致Slotが複数なら、最新以外を`skipped_misfire`、最新だけを実行候補とする
- 同じScheduleに`claimed`または`accepted`があれば、最新Slotも`skipped_overlap`とする
- 実行候補だけへ新しいOperation IDを一度発行し、`claimed`として保存する
- Occurrence作成とCursor前進は同じTransactionで行う
- Transaction失敗時はOccurrenceもCursorも残さない
- 同じManifest／Slotの再評価と複数Processは同じOccurrence／Operation IDへ収束する
- 未受理`claimed` OccurrenceはP20-014CがCrash Recoveryできるよう、決定的順序で取得できる
- MatchingしないSlotとFall Back二回目の重複Local MinuteはOccurrence Rowを作らない

## Files Allowed to Change

- `src/Internal/Scheduling/**`
- `src/Transport/PostgreSql/PostgreSqlScheduleSchema.php`
- `migrations/postgresql/Version20260728133000.php`
- `tests/Internal/Scheduling/**`
- `tests/Transport/PostgreSql/PostgreSqlScheduleSchemaTest.php`
- `tests/Internal/Migration/DatabaseMigrationRunnerTest.php`
- `tests/Internal/Console/DatabaseMigrationCommandTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Consumer/framework-package-export.sh`
- `develop/spec/98-scheduled-application-operation.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-014B-scheduled-operation-persistence-and-evaluator.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- PSR-20 ClockとDBAL Connectionを注入し、Framework内部で現在時刻やConnectionを生成しない
- P20-014A `CronExpression`／`CronField`を再利用し、Cron Grammarを複製しない
- Calendar EvaluatorはOperation AttributeやSource ReflectionへFallbackせず、Compiled `OperationMetadata`だけを使う
- PostgreSQL Transaction、row lock、unique constraintを正本とし、Process-local lockへ依存しない
- ScheduleごとにTransactionを分離し、一Scheduleの失敗で他Scheduleの確定済みTransactionをRollbackしない
- Occurrenceの一意制約Conflictを別Operation IDの再発行で回避しない
- Schedule名のOperation Type付け替えはCursorを進める前にSafe Errorで拒否する
- ErrorへCron全文、Timezone、Operation／Value Class、Credential、Raw SQL、Raw Database Errorを露出しない
- UTC Timestampはmicrosecondsを失わず保存する。ただしCursor／Scheduled Atは秒・microsecond `00`のMinute境界にする
- DST判定をHost Default Timezoneへ依存させない
- Public APIを追加しない
- Existing Maintenance SchedulerのClass／Command／意味を変更しない
- Existing Migrationを編集せず、Forward-only Migrationを追加する
- New Dependencyを追加しない
- Existing Phase 20 Working Tree差分を保持する
- WorkerはCommitしない

## Acceptance Criteria

- [x] Schema HelperとMigrationがState／OccurrenceのColumn、Check、FK、Unique、Indexを一致して作る
- [x] Fresh Migration Inventoryが7件となり、Current Schema／dry-run／package exportが新Migrationを含む
- [x] Database Migration Command／Application Consoleのpending／migrate件数が7件へ追従する
- [x] Cron EvaluatorがWildcard／List／Range／StepとDOW Sunday 0／7を一致判定する
- [x] 初回評価が現在Minuteだけを対象にし、CursorをMinute境界へ保存する
- [x] 二回目以降がCursor exclusive／Now inclusiveで評価し、該当なしでもCursorを進める
- [x] 複数Due Slotで古い一致Slotを`skipped_misfire`、最新を一件だけ`claimed`にする
- [x] 既存`claimed`／`accepted`が最新Slotを`skipped_overlap`にし、Terminal／SkippedはOverlapを阻害しない
- [x] Misfire／Overlap SkipがOperation IDを持たず、Claimだけが固定Operation IDを持つ
- [x] `(schedule_name, scheduled_at)`とOperation ID uniquenessをDatabaseで保証する
- [x] Schedule名のOperation Type付け替えをCursor前進なしで拒否する
- [x] Spring GapとFall Overlap first-UTC-onlyを固定Timezone FixtureでTestする
- [x] 同じSlotの再評価と二Connection同時評価が一Occurrence／一Operation IDへ収束する
- [x] Transaction FailureがOccurrence／Cursorの部分確定を残さない
- [x] 未受理`claimed`を決定的順序でRecovery Queryできる
- [x] Runtime Invocation／Actor／Authorization／Transport／Journal／CLI／Guideを変更しない
- [x] Required Commandsを実行し、Repository既存Blockerには変更Sourceの代替Evidenceを残す
- [x] Report／STATE／TODOを同期し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Internal/Scheduling tests/Transport/PostgreSql/PostgreSqlScheduleSchemaTest.php tests/Internal/Migration/DatabaseMigrationRunnerTest.php
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago analyze src tests
docker compose run --rm app vendor/bin/deptrac analyse --no-progress
bash tests/Consumer/framework-package-export.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

CommandがRepository既存問題または実行環境で失敗する場合は、未実行理由、Exact Error、変更Sourceに限定した代替CommandをReportへ記録する。

## Completion Report

`develop/orchestration/reports/P20-014B-scheduled-operation-persistence-and-evaluator.md`へSummary、Changed Files、Decisions and Assumptions、Persistence Contract、Evaluation Matrix、DST Matrix、Concurrency／Crash Evidence、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
