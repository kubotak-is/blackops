# P14-003: Diagnostics Readers and Query Aggregate

Status: Ready

## Goal

Operation ID一件からInline／DeferredのIdentity、Current State、Source Availability、Timeline、Attempts、Safe Outcomeを一つの内部Query Aggregateとして取得できるようにする。

InlineはCanonical Journal、DeferredはOperations RowをCurrent Stateの正本とする。Retention後も残存SourceからIdentityを構成できる場合はFoundとしてSource別Availabilityを返し、Missing／Fully Purgedは同じ`operation.unavailable`へ畳む。

## In Scope

- Existing `CanonicalJournalReader`と`OutcomeReader`を再利用する内部Diagnostics Query Service
- Encoded Payload／Contextを返さないPostgreSQL Deferred State Reader
- `reason_message`をSELECTしないPostgreSQL Dead Letter Reader
- Target、Affected Count、Purged Atだけを返すPostgreSQL Retention Purge Audit Reader
- `BlackOps\Internal\Diagnostics`配下の不変Result／DTO／Exception／Projection／Integrity Validation
- Journal EventごとのSafe Timeline Projection
- Actor IDの固定MaskとSensitive Attribute／Reserved Key Filter
- Inline OutcomeのCompleted Journal DataからのSafe Projection
- Deferred OutcomeのOutcome StoreからのSafe Projection
- Available／Purged／Not ApplicableのSource別Availability集約
- Missing／Fully PurgedのUnavailable Result
- Journal Sequence、Lifecycle Transition、Identity、Attempt、Deferred State、Outcome、Dead Letter、Purge AuditのIntegrity検査
- Storage／Decode／Integrity Failureの安全な内部Exception分類
- Inline／DeferredおよびRetentionを含む単体／PostgreSQL Integration Test

## Out of Scope

- `operation:inspect` Console CommandとHuman／JSON Encoder
- `operation:viewer`とHTTP／HTML Surface
- Public PHP Diagnostics API、Public HTTP Status／Outcome API
- Diagnostics Runtime／Console KernelへのPublic Composition
- Authorization／Tenant Access Policy
- Canonical Raw Access、Sensitive Detail表示、Error Message表示
- Retry／Replay／Cancel／Delete／Hold等の操作
- Migration、Table、Index、Columnの追加または変更
- Log Backend設定、OpenTelemetry、Metric、Remote Collector
- Quickstart、Skeleton、Guide、Documentation Websiteの更新
- Documentation Website公開

## Relevant Specifications and Decisions

- `develop/spec/01-core-model.md`
- `develop/spec/02-lifecycle-and-journal.md`
- `develop/spec/03-execution.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/12-deferred-execution.md`
- `develop/spec/16-outcome-storage.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/30-lifecycle-state-machine.md`
- `develop/spec/37-postgresql-table-layout.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/decisions/097-phase-14-operation-diagnostics.md`
- `develop/decisions/098-deferred-acceptance-failure-lifecycle.md`

## Files Allowed to Change

### Production

- 新規`src/Internal/Diagnostics/*.php`
- `src/Internal/Journal/LifecycleStateMachine.php`（Query用の副作用なし検証APIが必要な場合だけ）
- `src/Internal/Projection/SensitiveProjectionFilter.php`
- P14-003のSafe Projection再利用に必要な`src/Internal/Projection/*.php`
- 新規`src/Transport/PostgreSql/PostgreSqlDiagnostics*.php`
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php`（既存Table名Accessorの再利用だけ。DDL変更禁止）

### Tests

- 新規`tests/Internal/Diagnostics/*.php`
- P14-003のProjection回帰に必要な`tests/Internal/Projection/*.php`
- 新規`tests/Transport/PostgreSql/PostgreSqlDiagnostics*.php`
- `tests/Internal/Journal/LifecycleStateMachineTest.php`（検証APIを追加した場合だけ）

### Specification and Orchestration

- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/TODO.md`
- `develop/orchestration/reports/P14-003-diagnostics-readers-and-query-aggregate.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Constraints

- Internal Query／DTO／Result／Exceptionへ`#[PublicApi]`を付けない
- Aggregate PropertyへCanonical `JournalRecord`、Raw `Outcome`、Encoded Payload／Context、DBAL Connection、Throwableを保持しない
- Deferred State Readerは`encoded_payload`、`encoded_context`、Lease OwnerをSELECTしない
- Dead Letter Readerは`reason_message`をSELECTしない
- Purge Audit ReaderはPolicy、Purge Actor、Hold DetailをSELECTしない
- Readerは既存Schemaだけを読み、Migrationを追加しない
- JournalとOutcomeのRaw DataはLocal Variableとしてだけ扱い、Aggregate構築前にSafe Projectionする
- Failure／Dead Letter Message、Stack Trace、Credential、Mask前Actor IDをResult、Exception Message、Test Failureへ残さない
- Lifecycle不整合を並べ替え、補完、暗黙Fallbackで隠さない
- Deferred OutcomeがPurgedされた場合、Completed Journal DataへFallbackしない
- Deferred Stateだけが残る場合、Correlation／Causation IDを新規生成しない
- Storage、Decode、Integrity FailureをUnavailableへ畳まない
- Public API、Console Command、Viewer、Migrationを追加しない
- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する

## Acceptance Criteria

- [ ] Inline Completed／Rejected／FailedをCanonical JournalからFoundとしてQueryできる
- [ ] Deferred Accepted／Running／Retry Scheduled／Completed／Rejected／Failed／Dead LetteredをOperations RowをState AuthorityとしてQueryできる
- [ ] AggregateがIdentity、State、Availability、Timeline、Attempts、Outcomeを仕様のSafe Shapeで返す
- [ ] Actor IDが`[masked]`になり、Sensitive FieldとReserved KeyがProjectionから除外される
- [ ] `attempt.failed`／`operation.failed`はError TypeとRetryableだけを返し、Messageを返さない
- [ ] `operation.dead_lettered`とDead Letter DetailはReason Type等の許可Fieldだけを返し、Messageを返さない
- [ ] Inline OutcomeはCompleted Journal、Deferred OutcomeはOutcome Storeを正本としてSafe Projectionされる
- [ ] Completed Deferred Outcomeの欠落はPurge証拠がある場合Purged、ない場合Integrity Failureになる
- [ ] Partially Purged Deferred OperationをFoundとして返し、SourceごとのAvailable／Purged／Not Applicableを区別する
- [ ] Operation IDが全Sourceに存在しない場合とFully PurgedでIdentityを構成できない場合が同じUnavailable Resultになる
- [ ] Attemptなしの`received -> operation.failed`を有効なTerminal Failed Operationとして返す
- [ ] Sequence、Transition、Identity、Attempt、State、Outcome、Dead Letter、Purgeの不整合をIntegrity Failureとして検出する
- [ ] Storage／Decode／Integrity Failureが安全な別Codeとして区別され、SQL／Table／Payload／Codec Detailを公開Messageへ含めない
- [ ] ReaderのSQLがEncoded Payload／Context、Dead Letter Message、Purge Actor／Policy Detailを取得しない
- [ ] Migration、Public API、CLI、Viewerを追加しない
- [ ] Report／STATEを更新し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Internal/Diagnostics \
  tests/Transport/PostgreSql/PostgreSqlDiagnosticsReaderTest.php \
  tests/Transport/PostgreSql/PostgreSqlDiagnosticsQueryIntegrationTest.php \
  tests/Internal/Projection \
  tests/Internal/Journal/LifecycleStateMachineTest.php \
  tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php \
  tests/Transport/PostgreSql/PostgreSqlOutcomeStoreTest.php
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
! rg -n '#\[PublicApi\]' src/Internal/Diagnostics src/Transport/PostgreSql/PostgreSqlDiagnostics*.php
! rg -n 'encoded_payload|encoded_context|reason_message|purged_by|policy' src/Transport/PostgreSql/PostgreSqlDiagnostics*.php
git diff --check
```

新規Test File名が責務分割により異なる場合は、実在するP14-003対象Testをすべて指定して同等以上の範囲を実行する。GuardがSQL Aliasや安全なDTO名まで誤検知する場合は、SELECT ListにRestricted ColumnがないことをReportへ実行結果とともに記録する。

## Expected Report

`develop/orchestration/reports/P14-003-diagnostics-readers-and-query-aggregate.md`へSummary、Changed Files、Decisions and Assumptions、Reader Boundaries、Aggregate Shape、Availability Matrix、Integrity Validation、Sensitive Projection Evidence、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
