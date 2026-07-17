# P14-000: Operation Diagnostics Design Audit Report

Status: Accepted

## Summary

Phase 14の実装前監査を完了した。Terminal、Local Viewer、Production Logを同じOperation ID Query Modelへ接続できる既存部品はあるが、そのまま縦に接続できる状態ではない。

最優先GapはInline Failure相関である。HandlerまたはAuthorization Policyの予期外ThrowableはOperation ID発行後に起きても、Canonical Journalが`operation.received`／`attempt.started`で止まる。FrankenPHP WorkerはOperation IDなしの500とException Classだけの`error_log`を返す。これはSpec 03の「Inline例外をFailure Responseへ変換」とSpec 11の「Throwable後にFailure／Supervisionを別Transactionで記録」に反する。

既存`ExecutionScopedLogger`はOperation、Attempt、Correlation、Causation IDを自動付与できるが、Application Runtime／Compiled Containerへ自動接続されていない。Query側ではCanonical JournalとOutcomeのReaderを再利用できる一方、Deferred State、Dead Letter、Retention Purge AuditのReaderとAggregateが不足している。

Recommendationは、Inline Failure／HTTP 500／Logger相関を先に修復し、次に内部Diagnostics Query Aggregate、Terminal `operation:inspect`、Development限定Local Viewerを段階実装することである。Remote OTel、Public Status API、Tenant／Raw Accessは後続Phaseへ送る。

## Evidence Inventory

### HTTP and Lifecycle

- `src/Http/OperationRequestHandler.php:37-59`: Route不一致404と禁止GET／HEAD Body 400はOperation生成前。Binding後だけLifecycleへ進む。
- `src/Http/OperationRequestHandler.php:62-85`: Protocol ErrorはIDなし400、Binding／Validation FailureはRecorderがOperation IDを発行して422へ返す。
- `src/Http/Responder/JsonOperationResponder.php:30-85`: RejectionはOperationResultにIDがあればResponseへ含め、Deferred AcceptedとValidation 422は必ずIDを返す。Protocol 400はIDを返さない。
- `src/Http/Authentication/AuthenticationMiddleware.php:42-74`: Invalid CredentialはOperation Handlerの前で401を返し、Operation IDを持たない。
- `src/Internal/Execution/InlineDispatcher.php:103-162`: InlineはReceivedとAttempt Startedを先に記録するが、Handler／Policy ThrowableをFailure Lifecycleへ変換するcatchを持たない。
- `tests/Internal/Execution/InlineDispatcherTest.php:140-145`: 現行TestもHandler Throwableの伝播だけを期待し、Terminal Failure Journalを検証しない。
- `examples/quickstart/public/worker.php:40-50`: Worker Error BoundaryはException Classだけを`error_log`へ書き、IDなし`internal_error` 500を返す。

### Logging

- `src/Internal/Logging/ExecutionScopedLogger.php:25-64`: User ContextをSensitive Filterへ通し、Operation／Attempt／Correlation／Causation／Strategyを自動付与できる。
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php:45-96`: Execution ScopeはRuntime間で共有するが、PSR-3 Logger生成、Container Alias、ExecutionScopedLogger注入を行っていない。
- `src/Internal/Runtime/ProductionRuntimeDependencies.php`: Logger Dependencyを持たない。
- `tests/Internal/Runtime/ProductionRuntimeComposerTest.php:80-108`: Logging ScopeのTestは、Test自身がExecutionScopedLoggerをHandlerへ注入しており、Application Compositionの自動登録を証明しない。
- `src/Internal/Transaction/DefaultAfterCommitFailureReporter.php`: After Commit Failureだけは独立LoggerへOperation／Attempt／Correlation／Causation IDを渡す。

### Journal, State, Outcome, and Dead Letter

- `src/Journal/CanonicalJournalReader.php`: Public ReaderはOperation ID順の`JournalRecord` iterableを返す。
- `src/Transport/PostgreSql/PostgreSqlCanonicalJournalStore.php:80-105`: Sequence昇順QueryとCanonical Decodeを再利用できる。空結果はMissingとPurgedを区別しない。
- `src/Outcome/OutcomeReader.php`と`src/Transport/PostgreSql/PostgreSqlOutcomeStore.php:81-116`: Operation IDのTyped Outcomeを取得でき、Missingは`null`、Decode／Storage FailureはExceptionになる。
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php:30-145`: DeferredのState、Attempt Number、Current Attempt、Payload Tombstone、Outcome、Dead Letterの物理情報は存在する。
- `src/Transport/PostgreSql/PostgreSqlDeferredOperationLifecycleStore.php`: State更新専用で、Operation IDによる読み取りContractはない。
- `src/Transport/PostgreSql/PostgreSqlDeadLetterStore.php:11-47`: Dead LetterはInsert専用でReaderがない。
- `src/Core/Retention/RetentionPurgeAuditPort.php:9-13`: Purge Audit PortはWriterだけで、Purged判定用Readerがない。
- `src/Transport/PostgreSql/PostgreSqlRetentionHoldStore.php:122-142`: Active HoldだけはOperation IDで取得できる。

### Sensitive and Retention

- `src/Internal/Projection/ObservedJournalRecordProjector.php:22-82`: Canonical Journalを安全なObserved Shapeへ変換し、Actor IDをMask、Rejectedを安定Category／Code／Violationへ限定する。
- `src/Internal/Projection/SensitiveProjectionFilter.php`: `#[Sensitive]`と予約Key Patternを適用する。
- `src/Transport/PostgreSql/PostgreSqlFailureJournalDataCodec.php:24-97`: Failure／Dead Letterの自由文`error_message`／`reason_message`をCanonicalへ保存する。
- `ObservedJournalRecordProjector`はRejected以外のDataへ汎用Object Projectionを使うため、`errorMessage`／`reasonMessage`は予約Key Patternに一致せずObserved側へ残り得る。Diagnosticsで再利用する前に安全なFailure Projectionが必要である。
- Transport PayloadはTombstone後もOperations行と`payload_purged_at`が残る。Journal、Outcome、Dead Letterは別Policyで削除され、Purge Auditは別Tableへ残る。
- Inline OperationはOperations行を作らないため、Journal削除後に存在証拠を得るにはPurge Audit Readerが必要である。

### Console and Configuration

- `src/Internal/Application/ApplicationConsoleKernel.php`: Framework Commandを固定ListとLazy Factoryで登録し、Application Commandとの名前衝突を検出する。`operation:inspect`／Viewerは未登録。
- `src/Internal/Application/ApplicationConsoleCommandFactory.php`: Application Configuration SnapshotからBuild、Migration、Worker用Commandを遅延構築するため、Diagnostics Factoryも同じPatternを再利用できる。
- `src/Internal/Application/ApplicationDatabaseConfiguration.php`: Named Connection、Framework Connection、Schema、DatabaseManagerを既存Configurationから復元できる。
- `src/Internal/Application/ApplicationConfigurationSnapshot.php`: Process起動時SnapshotをConsoleでも再利用でき、QueryごとのEnvironment再評価は不要。

## Lifecycle Correlation Matrix

| Boundary | Operation ID created | HTTP surface | Canonical Journal | Application／Framework Log | Diagnostics readiness |
| --- | --- | --- | --- | --- | --- |
| Route not found | No | 404、Bodyなし | None | System scope only | Operationとして検索不可、仕様どおり |
| Forbidden GET／HEAD body | No | 400、Bodyなし | None | System scope only | Operationとして検索不可、仕様どおり |
| Malformed／non-object JSON | No | 400 `http.*`、IDなし | None | System scope only | Operationとして検索不可、仕様どおり |
| Invalid credential | No | 401 safe code、IDなし | None | System scope only | Operationとして検索不可、仕様どおり |
| Binding failure | Yes | 422＋ID＋safe violations | Seq 1 `operation.rejected` | Runtime自動Loggerなし | Journal Readerで追跡可能 |
| Value validation failure | Yes | 422＋ID＋safe violations | `received → rejected` | Runtime自動Loggerなし | Canonical RawとSafe Projectionの分離が必要 |
| Authorization rejection | Yes | 401／403＋ID | Inlineは`received → attempt.started → rejected`、Deferred受付は`received → rejected` | Runtime自動Loggerなし | Journal Readerで追跡可能 |
| Handler business rejection | Yes | Category対応4xx＋ID | `received → attempt.started → rejected` | Runtime自動Loggerなし | Journal Readerで追跡可能 |
| Inline success | Yes | 200／204、IDなし | `received → attempt.started → attempt.succeeded → completed` | Manual注入時だけ相関可 | JournalにIDはあるがResponseからは到達不可 |
| Inline Handler／Policy Throwable | Yes | Classicは未捕捉、Workerは500 `internal_error`、IDなし | `received → attempt.started`で停止 | WorkerはException Classのみ、IDなし | **Gap: terminal failureと入口相関がない** |
| Deferred acceptance success | Yes | 202＋ID | `received → accepted` | Runtime自動Loggerなし | Operations行＋Journalで追跡可能 |
| Deferred acceptance Policy Throwable | Yes | 未捕捉Throwable、IDなしのServer Errorになり得る | Transaction rollbackによりReceivedも残らない | Runtime自動Loggerなし | **Gap: 発行済みIDがResponse／Journalへ残らない** |
| Deferred worker success | Already exists | 元Responseは202＋ID | `attempt.started → attempt.succeeded → completed` | Manual注入時だけ相関可 | State＋Journal＋Outcomeあり |
| Deferred worker rejection | Already exists | 元Responseは202＋ID | `attempt.started → rejected` | Manual注入時だけ相関可 | State＋Journalあり |
| Deferred worker retry／failure | Already exists | 元Responseは202＋ID | `attempt.failed → retry_scheduled`または`operation.failed` | Manual注入時だけ相関可 | State＋Journalあり |
| Deferred dead letter | Already exists | 元Responseは202＋ID | `attempt.failed → dead_lettered` | Manual注入時だけ相関可 | State＋Journal＋Dead Letter rowあり、Reader不足 |

## Reusable Contracts

| Need | Reusable contract | File | Reuse decision |
| --- | --- | --- | --- |
| Operation ID parse | `OperationId::fromString()` | `src/Core/Identifier/OperationId.php` | CLI Input validationに再利用 |
| Lifecycle timeline | `CanonicalJournalReader::records()` | `src/Journal/CanonicalJournalReader.php` | Internal Queryへ注入。ただし直接表示禁止 |
| PostgreSQL journal | `PostgreSqlCanonicalJournalStore` | `src/Transport/PostgreSql/PostgreSqlCanonicalJournalStore.php` | Sequence順取得とDecodeを再利用 |
| Safe journal shape | `ObservedJournalRecordProjector` | `src/Internal/Projection/ObservedJournalRecordProjector.php` | Value／Actor処理を再利用し、Failure専用Projectionを補強 |
| Sensitive filtering | `SensitiveProjectionFilter` | `src/Internal/Projection/SensitiveProjectionFilter.php` | Outcome／Log Contextへ再利用 |
| Typed outcome | `OutcomeReader`, `OutcomeRecord` | `src/Outcome/` | Deferred Outcome取得へ再利用。表示前にProjection |
| Deferred physical metadata | `PostgreSqlDeferredOperationSchema` | `src/Transport/PostgreSql/PostgreSqlDeferredOperationSchema.php` | Table名／Column契約を新Readerで共有 |
| Retention hold | `RetentionHoldPort::activeFor()` | `src/Core/Retention/RetentionHoldPort.php` | Availability補助に再利用 |
| Console composition | Lazy Framework Command／Factory | `src/Internal/Application/ApplicationConsoleKernel.php`、`ApplicationConsoleCommandFactory.php` | inspect／viewer登録Patternに再利用 |
| Configuration snapshot | Application Configuration Snapshot／Database Configuration | `src/Internal/Application/` | Framework ConnectionとSchemaを一度だけ解決 |
| Log correlation | `ExecutionScopedLogger`／`ExecutionScopeProvider` | `src/Internal/Logging/`、`src/Internal/Execution/` | Runtime DI接続を追加して再利用 |

## Gaps

1. Inline ThrowableのAttempt Failed／Operation Failed LifecycleとFailure Responseがない。
2. Inline／Deferred受付のServer Error BoundaryがOperation IDをResponse／Logへ露出できない。
3. `ExecutionScopedLogger`がApplication Containerの`LoggerInterface`へBindingされていない。
4. Deferred Operations State Readerがない。
5. Dead Letter Readerがない。
6. Retention Purge Audit Readerがなく、MissingとFully PurgedをStore Contractで判定できない。
7. Journal／State／Outcome／Dead Letter／Retentionを集約するQuery ServiceとIntegrity Checkがない。
8. Failure／Dead Letter自由文MessageのSafe Projectionがない。
9. OutcomeはTyped Raw Objectであり、Diagnostics用Sensitive Projectionがない。
10. Inline OutcomeはOutcome Storeへ保存されず、Completed Journalから読む必要がある。
11. Journal Decodeが一件壊れるとReader Exceptionになり、Partial Timeline／Corrupt表示Contractがない。
12. Diagnostics Access Policy、Tenant、Unauthorized判定がない。
13. CLI Human／JSON Schema、Exit Code、stdout／stderr契約がない。
14. Local ViewerのEnvironment、Bind、Token、Read-only境界がない。
15. OTel Context／Adapterは未実装であり、Operation IDとTrace IDの相関は設計だけである。
16. Application Configuration SnapshotはEnvironment値を保持するが、Frameworkが解釈するCanonical Runtime Environment名とDevelopment判定Contractはない。

## Security and Retention Boundaries

| Surface | Allowed by default | Forbidden by default | Owner |
| --- | --- | --- | --- |
| Canonical Store | Reproducible Value、Actor ID／Type、Error Detail | Uncontrolled adapter access | Application／Infrastructure access control、encryption、retention |
| Terminal inspect | ID、Type、State、safe timeline、masked actors、safe outcome | Credential、Raw Value、Raw Actor ID、Exception message、DB secret | Framework projection。OS shell accessはApplication運用責務 |
| Local Viewer | Terminalと同じsafe aggregate | Canonical Raw、non-loopback公開、production自動起動 | Frameworkが既定無効Config／明示CLI／loopback／tokenを強制 |
| Production Log | Structured IDs、safe error classification | Raw Value、Credential、Backend detail | Framework envelope＋Application sink／retention／alert |
| Status／Outcome HTTP | Phase 16で定義 | Phase 14 ViewerのProduction転用 | Application access policy＋Framework transport |
| Raw privileged diagnostics | Phase 18で検討 | Phase 14の`--show-sensitive` | Tenant、encryption、auditを含むPhase 18 |

Retention後の表示はSourceごとに扱う。

- Transport Payload Tombstone後もDeferred Operations row、State、Type、`payload_purged_at`は残る。
- Journal／Outcome／Dead Letterは個別に削除され、Purge Auditだけが残り得る。
- Hold中は各削除を停止するが、Hold Reason／Actor自体もSensitive運用DataとしてTerminal既定表示へ含めない。
- Fully Purged InlineはPurge Audit ReaderがなければMissingと区別できない。
- Unauthorized判定Contractがないため、Phase 14 Local CLI／ViewerはDevelopment Local Authorityに限定し、Remote／HTTP Access PolicyはPhase 16／18へ送る。

## Recommended Vertical Slices

### Slice 1: Correlatable Inline Failure

- Handler／Policy Throwableを`attempt.failed → operation.failed`へ記録する。
- Transaction rollback後にFailure Journalを別Transactionで記録する。
- Safe HTTP 500へOperation IDを含める。
- Framework Error LogへOperation／Attempt／Correlation／Causation IDと安全なFailure Typeを出す。
- ClassicとFrankenPHP Workerで同じError Responderを使う。

### Slice 2: Internal Diagnostics Aggregate

- Deferred State、Dead Letter、Purge Audit Readerを追加する。
- Existing Journal／Outcome Readerを集約する。
- Inline StateはJournal、Deferred StateはOperations rowを正本とする。
- Safe ProjectionとIntegrity ErrorをDTO化する。
- Missing／Fully Purged／Unauthorizedを外部`operation.unavailable`へ畳む余地を持たせる。

### Slice 3: Terminal Inspect

```text
php blackops operation:inspect <operation-id>
php blackops operation:inspect <operation-id> --json
```

- HumanはSummary、Availability、Timeline、Attempts、Outcomeを表示する。
- JSONは`schemaVersion: 1`の同一Aggregateを出す。
- Exit Code推奨: 0 Found、2 Invalid Input、3 Unavailable、4 Storage／Decode Failure。
- stdoutはQuery Data、stderrはCLI／Infrastructure Error。
- Raw／Sensitive OverrideはPhase 14へ含めない。

### Slice 4: Development Local Viewer

- `php blackops operation:viewer`で明示起動。
- Canonical `diagnostics.viewer.enabled`を既定`false`、Quickstart Localだけ`true`とし、明示CLIとEnable Gateの両方を要求する。
- `127.0.0.1:8082`既定、Random Session Token、Read-only、Server-rendered HTML。
- Operation ID一件の検索とTimeline表示だけを初期Scopeにする。
- List、全文検索、Retry、Replay、Cancel、編集は含めない。

### Slice 5: Production Correlation Boundary

- `LoggerInterface`をCompiled Containerへ安全にBindingし、同じExecution Scopeを共有する。
- Error BoundaryとApplication Logの共通ID Fieldを固定する。
- External Sink、OTel、Metric、Dashboardを実装しない。

## Decision Questions

Decision 097 Draftへ次のUser判断をRecommendation付きで記録した。

1. Phase 14へLocal Viewerまで含めるDepthと実装順序
2. Diagnostics Query ModelをInternalに留めるかPublic API化するか
3. `operation:inspect` Human／JSON／Exit Code
4. Sensitive／Actor／Error表示Default
5. Missing／Purged／Unauthorized表示
6. Local Viewerの起動、Bind、Token境界
7. Production LoggingとRemote ObservabilityのPhase境界

## Proposed Specification and Task Split

### Specifications

- `65-operation-diagnostics.md`: Query Aggregate、State authority、Availability、Projection、CLI／Viewer／Log共通契約
- `66-phase-14-delivery-plan.md`: Task順序、Acceptance、Consumer／Documentation／Quality Gate

### Task Packets

1. P14-001 Decision Finalization and Specification
2. P14-002 Inline Failure and Runtime Log Correlation
3. P14-003 Diagnostics Readers and Query Aggregate
4. P14-004 Operation Inspect CLI
5. P14-005 Development Local Viewer
6. P14-006 Production Correlation and Security Regression
7. P14-007 Consumer Experience and Phase Closeout

## Commands and Results

```text
rg -n "OperationId|operationId|operation_id|Journal|Outcome|DeadLetter|Throwable|Logger" src tests
Result: 成功。2779件。HTTP、Lifecycle、Journal、Outcome、Retention、Loggerの候補を抽出した。

rg -n "add\(|Command|operation:list|outcome" src/Internal/Console src/Application tests 2>/dev/null
Result: 成功。518件。Framework Command登録、Lazy Factory、既存Outcome Testを抽出した。

tests/Internal/Outcome directory check
Result: 存在しないため指定Commandから除外した。実在するtests/Outcomeを補助対象へ追加した。

docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Internal/Http tests/Internal/Journal tests/Internal/Console \
  tests/Transport/PostgreSql tests/Outcome
Result: OK (171 tests, 663 assertions)。

docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Http/HttpValidationLifecycleTest.php \
  tests/Http/Authentication/AuthenticationMiddlewareTest.php \
  tests/Http/OperationRequestHandlerTest.php \
  tests/Http/DeferredOperationRequestHandlerTest.php \
  tests/Internal/Execution/InlineDispatcherTest.php \
  tests/Internal/Execution/DeferredWorkerRuntimeTest.php \
  tests/Internal/Logging/ExecutionScopedLoggerTest.php \
  tests/Internal/Logging/MonologJsonlLoggerFactoryTest.php \
  tests/Internal/Runtime/ProductionRuntimeComposerTest.php
Result: OK (104 tests, 678 assertions)。現行挙動としてInline Throwable伝播、Deferred Supervision、HTTP ID境界、Logger単体相関を確認した。

docker compose run --rm app mago format --check src tests
Result: 成功。All files are already formatted。

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
Result: Management Comment ID Guard、Whitespace Guardともに成功。
```

## Acceptance Criteria

- [x] HTTP Error／Log／Journal／Outcome相関の現状をLifecycle別に一覧化した
- [x] 再利用可能なReader／Store／Console／Configuration ContractをFile単位で示した
- [x] Terminal Inspect最小SliceのInput／Output／Failure Contract案を示した
- [x] Local ViewerとProduction Observabilityの安全境界案を示した
- [x] Phase 15／16／18へ送るScopeを明確にした
- [x] Decision 097 DraftへRecommendation付き選択肢を記録した
- [x] Phase 14のSpecification／Task Packet分割案を示した
- [x] Production Codeを変更していない
- [x] Report／STATEを更新し、WorkerはCommitしない

## Remaining Issues

- Decision 097の7 QuestionはUser回答待ちである。
- Inline Failure Gapは既存仕様との不整合だが、本AuditではProduction Codeを修正していない。
- Observed Failure Messageの非露出Contractは実装前に確定し、Regression Testが必要である。
- Public Status／Outcome APIのAccess PolicyとTenant境界はPhase 16／18まで未確定である。

## Suggested Next Action

1. OrchestratorがEvidence、Lifecycle Matrix、Decision 097 DraftをReviewする。
2. UserがDecision 097の7 Questionへ回答する。
3. 回答確定後にP14-001でSpecificationとDelivery Planを作る。
4. P14-002でTerminal／Viewerより先にInline FailureとRuntime Log相関を修復する。

## Orchestrator Review

Accepted。HTTP／Lifecycle／Journal／Outcome／Deferred State／Dead Letter／Retention／Console／LoggingのEvidenceをSourceと既存Specへ照合した。Inline ThrowableがTerminal Journalへ到達せず、500 Response／Framework LogにもOperation IDがない不整合、Runtime Logger未接続、Failure MessageのSafe Projection不足、State／Dead Letter／Purge Audit Reader不足をPhase 14の先行Gapとして認める。

Decision 097の推奨順序、Internal Query Aggregate、Human＋Version付きJSON CLI、Safe-only Projection、Unavailable表示、Local Viewer、Production PSR-3相関は実装判断に必要な選択肢を満たす。Local Viewer推奨は既存Classic HTTP Portとの衝突を避けて`127.0.0.1:8082`とし、曖昧なEnvironment推測ではなく既定無効の`diagnostics.viewer.enabled`、明示CLI、Random Token、Read-onlyを組み合わせる形へ修正した。

Production Code変更はなく、Worker対象275 tests、1341 assertionsとDocument Guardが成功している。P14-000をAcceptedとし、Decision 097のUser回答後にSpecificationへ進む。
