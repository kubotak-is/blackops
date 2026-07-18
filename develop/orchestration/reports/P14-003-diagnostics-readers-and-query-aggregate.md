# P14-003 Diagnostics Readers and Query Aggregate Report

Status: Accepted

## Summary

Operation ID一件からInline／DeferredのIdentity、Current State、Source Availability、Safe Timeline、Attempts、Safe Outcomeを取得する内部`OperationDiagnosticsQuery`を実装した。

Canonical JournalとOutcome Readerを既存Portから再利用し、PostgreSQLのOperations、Dead Letters、Retention Purge AuditsはRestricted Columnを取得しない専用Readerで参照する。MissingとIdentityを再構成できないFully Purgedは`operation.unavailable`へ畳み、Storage、Decode、Integrity Failureは安全な別Codeで失敗させる。

## Changed Files

- `src/Internal/Diagnostics/*.php`
  - Immutable Result／Aggregate／Identity／State／Availability／Timeline／Attempt／Outcome DTO
  - Safe Projector、Journal／Attempt／Source Integrity Validationを含む`OperationDiagnosticsQuery`
  - Diagnostics Source PortとPostgreSQL Transport Adapter
- `src/Transport/PostgreSql/PostgreSqlDiagnostics*.php`
  - Restricted Columnを読まないPostgreSQL Reader
  - Transport固有のSafe Row DTO、Failure Kind、Safe Exception
- `tests/Internal/Diagnostics/OperationDiagnosticsQueryTest.php`
  - Inline／Deferred／Retention／Failure分類／Integrity Validationの単体Test
- `tests/Transport/PostgreSql/PostgreSqlDiagnosticsReaderTest.php`
  - Safe SELECT結果とStorage FailureのPostgreSQL Test
- `tests/Transport/PostgreSql/PostgreSqlDiagnosticsQueryIntegrationTest.php`
  - Canonical Journal／Outcome Store／Operations Row／Retentionを接続したIntegration Test
- `develop/spec/66-phase-14-delivery-plan.md`
  - Internal Query AggregateのPhase Acceptanceを完了へ同期
- `develop/TODO.md`
  - P14-003のInternal Query作業を完了へ同期
- `develop/STATE.md`
  - Worker完了Checkpointと検証結果を同期

## Decisions and Assumptions

- Internal Query／DTOへ`#[PublicApi]`を付けず、P14-004以降のCLI／Viewerだけが利用する内部Contractとした。
- PostgreSQL Transport層からInternal層への逆依存を避けるため、Transport ReaderはTransport固有のSafe Row DTOを返し、`PostgreSqlDiagnosticsSourceReader`がInternal DTOとFailure Codeへ変換する。
- Operations Row作成前のDeferred Binding／Value Validation／Expected Authorization Rejectionは、Lifecycle検証済み・Attemptなし・`operation.accepted`なしのJournal-only Rejectedとして扱う。Bindingは`operation.rejected`、Validation／Authorizationは`operation.received -> operation.rejected`を許可する。
- Operations Row作成前の予期しないDeferred受付Failureは、`operation.received -> operation.failed`の厳密な二EventかつAttemptなしのJournal-only Failedとして扱う。Accepted後またはAttemptありでOperations Rowが欠落する場合はIntegrity Failureとする。
- Deferred StateだけがRetention後に残る場合はCorrelation／Causation／Actorを補完せず`null`とする。
- Query AggregateへCanonical `JournalRecord`、Raw `Outcome`、Encoded Data、DBAL Connection、Throwableを保持しない。

## Reader Boundaries

| Reader | 取得するField | 取得しないField |
| --- | --- | --- |
| Deferred State | Operation ID、Type、Schema Version、State、Next Sequence、Payload Purged有無、Attempt番号／現在Attempt ID／開始時刻 | Encoded Payload、Encoded Context、Lease Owner、Fencing Detail |
| Dead Letter | Operation ID、Final Attempt ID／番号、Reason Type、Moved At | Failure Message |
| Purge Audit | Target、Affected Count、Purged At | Policy、Purge Actor、Hold Detail |
| Canonical Journal | 既存ReaderからLocal VariableへDecodeし即時Safe Projection | AggregateへのRaw Record保持 |
| Outcome | 既存ReaderからLocal VariableへDecodeし即時Safe Projection | AggregateへのRaw Outcome保持 |

PostgreSQL ReaderのSQL GuardはRestricted Column名を検出せず成功した。DDL、Migration、Table Accessor変更はない。

## Aggregate Shape

- `OperationDiagnosticsFound(OperationDiagnostics)`または`OperationDiagnosticsUnavailable(operation.unavailable)`
- Identity: Operation ID、Type、Schema Version、Strategy、Correlation／Causation、Mask済みActors
- State: Current Lifecycle State、Terminal、Authority Source
- Availability: Transport Payload、Journal、Outcome、Dead LetterのAvailable／Purged／Not Applicable
- Timeline: Sequence順のSafe Event Data
- Attempts: Attempt ID、番号、UTC Started At、関連Sequence
- Outcome: Type、UTC Completed At、Source、Safe Data

## Availability Matrix

| Strategy／State | Transport Payload | Journal | Outcome | Dead Letter |
| --- | --- | --- | --- | --- |
| Inline Completed | Not Applicable | Available | Available | Not Applicable |
| Inline Rejected／Failed | Not Applicable | Available | Not Applicable | Not Applicable |
| Deferred non-terminal | AvailableまたはPurged | AvailableまたはPurged | Not Applicable | Not Applicable |
| Deferred Completed | AvailableまたはPurged | AvailableまたはPurged | AvailableまたはPurged | Not Applicable |
| Deferred Rejected／Failed with State Row | AvailableまたはPurged | AvailableまたはPurged | Not Applicable | Not Applicable |
| Deferred pre-transport Rejected／Failed | Not Applicable | Available | Not Applicable | Not Applicable |
| Deferred Dead Lettered | AvailableまたはPurged | AvailableまたはPurged | Not Applicable | AvailableまたはPurged |
| Sourceなし／Identity再構成不能 | - | - | - | `operation.unavailable` |

期待されるSourceがなくPurge証拠もない場合はFoundへ`missing`を返さず`diagnostics.integrity_failed`とする。Deferred OutcomeがPurgedの場合、Completed Journal OutcomeへFallbackしない。Transport Tombstoneは単独でPurgedを証明し、Purge AuditがあるのにOperations RowがPayload Availableを示す場合だけ矛盾とする。

## Integrity Validation

- Journal Sequenceが1開始、入力順のまま連続すること
- Lifecycle State Machineで全Eventが遷移可能であること
- Operation ID、Type、Schema Version、Strategy、Correlation／Causation、Actor Contextが全Recordで一致すること
- Attempt ID、番号、Started Atが一致し、番号が1から連続すること
- Retry Scheduledが直前までにFailedとなった同一Attemptを参照し、次番号が現在番号+1であること
- Deferred Operations State、Next Sequence、Attempt番号、現在Attempt ID／Started AtがJournal導出結果と一致すること
- Journal Purged後のState-only Runningは現在Attempt ID／Started At／1以上のAttempt番号を必須とし、non-Runningは現在Attempt Pairを禁止すること
- Completed DeferredだけがOutcomeを持ち、欠落時はPurge証拠を要求すること
- Dead Letter RowがDead Lettered Stateだけに存在し、Journal Detailと一致すること
- Operations Row／JournalがなくDead Letter Rowだけが残る場合はUnavailableではなくIntegrity Failureとすること
- Available Sourceと同TargetのPurge Auditが共存しないこと
- Transport PayloadがAvailableなら同TargetのPurge Auditが存在しないこと

並べ替え、欠落補完、暗黙Fallbackは行わない。

## Sensitive Projection Evidence

- Actor IDは固定`[masked]`へ変換し、Actor Typeだけを保持する。
- `#[Sensitive]` Omit、Mask、Reserved Key FilterをOperation ValueとOutcomeへ適用する。
- Attempt／Operation FailureはError TypeとRetryableだけを返し、Messageを返さない。
- Dead LetterはReason Type、Final Attempt、Moved Atだけを返し、Messageを返さない。
- PostgreSQL Integration TestでRestrictedなPayload／Context／Outcome／Dead Letter Message／Purge Policy／Actorを保存した状態からSafe DTOだけを取得した。
- Exception Messageは`diagnostics.storage_failed`、`diagnostics.decode_failed`、`diagnostics.integrity_failed`またはTransport内部の固定Messageだけで、SQL、Table、Payload、Codec Detailを含めない。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: composer.json is valid。

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid。

docker compose run --rm app mago format --check src tests examples
Result: All files are already formatted。

docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations <P14-003 required targets>
Result: OK (77 tests, 272 assertions)。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1141 tests, 4062 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2175 / Warnings 0 / Errors 0。

Management Comment ID Guard
Internal Diagnostics PublicApi Guard
PostgreSQL Diagnostics Restricted Column Guard
git diff --check
Result: すべて成功。
```

実装途中の初回DeptracでTransportからInternal Diagnosticsへの逆依存を検出した。Transport固有Safe Row DTOとInternal Adapterへ依存反転し、最終DeptracはViolation 0で成功した。

## Acceptance Criteria

- [x] Inline Completed／Rejected／FailedをFoundとしてQueryできる
- [x] Deferred Accepted／Running／Retry Scheduled／Completed／Rejected／Failed／Dead LetteredをTransport State AuthorityでQueryできる
- [x] Identity、State、Availability、Timeline、Attempts、OutcomeをSafe Immutable Shapeで返す
- [x] Sensitive Field、Reserved Key、Actor ID、Failure／Dead Letter MessageをSurfaceから除外する
- [x] Inline OutcomeはJournal、Deferred OutcomeはOutcome Storeを正本とする
- [x] Partially Purged DeferredをFound＋Source別Availabilityで返す
- [x] Missing／Fully Purgedを同じUnavailable Resultへ畳む
- [x] AttemptなしのDeferred pre-transport Rejected／FailedをJournal-only Terminalとして返し、Accepted後／AttemptありのState Row欠落を拒否する
- [x] Tombstone単独をTransport Payload Purgedとして扱い、Available＋Purge Audit矛盾を拒否する
- [x] State-only Running／non-RunningのCurrent Attempt Pair InvariantとDangling Dead Letterを検査する
- [x] Sequence、Transition、Identity、Attempt、State、Outcome、Dead Letter、Purge不整合を検出する
- [x] Storage／Decode／Integrity Failureを安全な別Codeで区別する
- [x] Restricted ColumnをPostgreSQL Readerが取得しない
- [x] Migration、Public API、CLI、Viewerを追加していない
- [x] Report／STATEを更新し、WorkerはCommitしていない

## Orchestrator Review Fixes

- Deferred RouteのBinding一Record RejectedとReceived→RejectedをJournal-only Foundとして追加し、実際のDeferred Acceptance Authorization Rejectionに同期した。
- Journal-only DeferredはAttemptなし・AcceptedなしのRejected、または厳密なReceived→Failedだけに限定した。
- 全StrategyのJournal Attempt番号を1開始・新規Attemptごとの連続番号として検証した。
- Transport TombstoneをPurge AuditなしでもPurgedとし、AuditだけがPurgedを主張するAvailable RowをIntegrity Failureとした。
- Journal Purged後のState-only AttemptはRunningだけに構成し、Current Attempt Pair欠落またはnon-RunningでのPair残留をIntegrity Failureとした。
- Identity SourceなしでDead Letter Rowだけが残る状態をIntegrity Failureとした。

## Orchestrator Review

OrchestratorはReader SQL、Safe DTO Property、Journal-only Deferred Rejection、Attempt番号、Tombstone、State-only Current Attempt、Dangling Dead Letter、Transport／Internal依存方向を独立に確認した。

```text
docker compose run --rm app vendor/bin/phpunit --display-deprecations <P14-003 orchestrator critical targets>
Result: OK (85 tests, 365 assertions)。Diagnostics単体／PostgreSQL、実Deferred Acceptance Rejection、HTTP Binding Lifecycle、State Machine、Canonical Journal、Outcome Storeを含む。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1141 tests, 4062 assertions)。

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac
Result: Composer Root／Quickstart valid。Mago全成功。Deptrac Violations 0 / Allowed 2175。

Management Comment ID、Internal PublicApi、Restricted Column、Raw DTO Property、git diff --check Guard
Result: 成功。
```

Review指摘修正と独立品質Gateがすべて成功したため、P14-003をAcceptedとした。

## Remaining Issues

P14-003 Scope内のRemaining IssueとBlockerはない。Unauthorizedを`operation.unavailable`へ畳むSurface Access境界はP14-004／P14-005の責務として残る。

## Suggested Next Action

OrchestratorがSafe Projection、Reader SQL、Availability、Attempt／State Integrity、依存方向をReviewし、AcceptedならP14-003をCommitする。その後P14-004で同じ内部Query Aggregateを`operation:inspect` Human／JSON Encoderへ接続する。
