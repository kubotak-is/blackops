# P14-001: Operation Diagnostics Specification Report

Status: Accepted

## Summary

Decision 097で確定したPhase 14のOperation Diagnosticsを、内部Query Aggregate、CLI、Development Local Viewer、PSR-3相関、安全なProjection、Retention Availability、Task分割へ落とし込んだ。

仕様化中に確認したOperation ID発行後かつAttempt開始前のDeferred受付Failure Lifecycleは、Decision 098の選択Aで確定した。受付TransactionをRollbackした後、別Transactionで`received -> operation.failed`を記録し、Attemptを作らず、HTTP 500、Framework Log、Canonical Journalを同じOperation IDで相関する。

## Changed Files

- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/spec/README.md`
- `develop/decisions/098-deferred-acceptance-failure-lifecycle.md`（User回答とOrchestrator確定を保持）
- `develop/TODO.md`
- `develop/orchestration/reports/P14-001-operation-diagnostics-specification.md`
- `develop/STATE.md`

Production Code、Test、Migration、Guide、既存Specificationは変更していない。

## Decisions and Assumptions

- D097のA／A／A／A／A／A／Aを正本とした。
- Diagnostics Query Modelは`BlackOps\Internal\Diagnostics`に置き、Public PHP APIにしない。
- `operation:inspect`はHuman既定とVersion 1 JSONを持ち、Found 0、Invalid Input 2、Unavailable 3、Storage／Decode／Integrity Failure 4とする。
- Missing、Fully Purged、Unauthorizedは`operation.unavailable`へ畳み、Partially PurgedはSource別Availabilityを持つFoundとする。
- CLIとViewerはCanonical Raw Data、Actor ID、Credential、Exception／Dead Letter Messageを表示しない。
- Local Viewerは既定無効、明示Command、Loopback限定、起動ごとのRandom Token、Read-onlyとする。
- Production向けはPSR-3構造化Log相関までをPhase 14とし、OpenTelemetryとRemote CollectorはPhase 18へ送る。
- D098の選択Aに従い、Attempt開始前FailureはAttemptなしの`received -> operation.failed`へ到達する。
- Deferred受付TransactionのRollback後、Failure Journalを別Transactionで記録する。
- Attempt開始前FailureのHTTP 500、Framework Log、Canonical Journalは同じOperation IDを使う。

## Specification Coverage

### Failure and Correlation

- Operation成立前／後のError BoundaryとOperation ID有無をMatrix化した。
- Inline Throwableの`attempt.failed -> operation.failed`、Rollback後の別Transaction、HTTP 500、Framework Logの同一ID Contractを固定した。
- Deferred受付のAttempt開始前Throwableを、Attemptsが空の`received -> operation.failed` Terminal Operationとして固定した。
- Classic EntrypointとFrankenPHP Worker Modeで同じSafe Error Response Shapeを要求した。
- `ExecutionScopeProvider`を共有する`ExecutionScopedLogger`を`LoggerInterface`へ注入し、Operation／Attempt／Correlation／Causation IDを自動付与する境界を固定した。

### Query and Availability

- `OperationDiagnostics`のIdentity、State、Availability、Timeline、Attempts、Outcomeを定義した。
- Inline Journal、Deferred Operations State、Outcome Store、Dead Letter、Purge AuditのAuthorityを分離した。
- Storage、Decode、Integrity FailureをUnavailableと区別した。
- Sequence、Lifecycle、Identity、Attempt、Outcome、Dead Letter、Purge間のIntegrity Validationを定義した。

### User Surfaces

- `php blackops operation:inspect <operation-id>`のHuman／JSON／stdout／stderr／Exit Codeを固定した。
- `php blackops operation:viewer`のEnable Gate、Loopback Bind、Token Bootstrap、Session Cookie、Security Header、Read-only Scopeを固定した。
- TerminalとViewerが同じ内部Query Aggregateだけを使うようにした。

### Security and Retention

- Canonical Restricted DataとSafe Diagnostics Projectionを分離した。
- Credential、Raw Value／Outcome、Actor ID、Failure Message、Stack Trace、Database Secret、Retention内部Detailを全Diagnostics Surfaceで禁止した。
- Source別のavailable／purged／not_applicableと、証拠なしMissingをIntegrity Failureとして定義した。

## Task Breakdown

1. P14-002: Inline Failure and Runtime Correlation
2. P14-003: Diagnostics Readers and Query Aggregate
3. P14-004: Operation Inspect CLI
4. P14-005: Development Local Viewer
5. P14-006: Production Correlation and Security Regression
6. P14-007: Consumer Experience and Closeout

D098で確定したFailure LifecycleをP14-002へ含め、各Taskへ単一責務とAcceptance Gateを定義した。

## Commands and Results

```text
rg -n "OperationDiagnostics|operation:inspect|operation:viewer|operation.unavailable|diagnostics.viewer|ExecutionScopedLogger" develop/spec/65-operation-diagnostics.md develop/spec/66-phase-14-delivery-plan.md develop/TODO.md
Result: 成功。Specification、Delivery Plan、TODOの必須Contractを確認した。

rg -n "65-operation-diagnostics|66-phase-14-delivery-plan" develop/spec/README.md
Result: 成功。新しい仕様書2件がSpecification Indexへ登録されている。

git diff --check
Result: 成功。

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: 成功。Production Code／Testの管理番号Comment違反はない。

docker compose run --rm app mago format --check src tests
Result: 成功。All files are already formatted。Sandbox内ではDocker Socket権限不足となったため、承認済みDocker実行で再確認した。

git status --short
Result: P14-001で許可されたSpecification、Decision 098、README、TODO、Report、STATEだけが変更対象である。
```

## Acceptance Criteria

- [x] Operation成立前／後のFailureとOperation ID境界が明記されている
- [x] Inline ThrowableのJournal、HTTP 500、PSR-3相関要件が固定されている
- [x] 内部Diagnostics AggregateのField、State Authority、Availability、Integrity Failureが定義されている
- [x] CLIのInput、Human出力、Version付きJSON、stdout／stderr、Exit Codeが定義されている
- [x] Local Viewerの既定無効、明示起動、Loopback、Random Token、Read-only境界が定義されている
- [x] Sensitive、Actor、Credential、Error Message、Canonical Raw Dataの禁止境界が定義されている
- [x] P14-002からP14-007の各Taskが単一責務とAcceptance Gateを持つ
- [x] `develop/spec/README.md`と`develop/TODO.md`が同期されている
- [x] Production Codeを変更していない
- [x] Report／STATEを更新し、WorkerはCommitしない

## Remaining Issues

- P14-001を妨げる未決事項はない。
- D098で追加したAttemptなしTerminal Failureを、P14-002で既存Lifecycle／State Machine Specification、Production Code、Testへ同期する。

## Suggested Next Action

1. OrchestratorがD097／D098、Operation Diagnostics Specification、Phase 14 Delivery Planの整合をReviewする。
2. P14-001をAcceptedにする。
3. P14-002でInline／Attempt開始前Failure LifecycleとRuntime相関を実装する。

## Orchestrator Review

Accepted。D097の7つの選択とD098のAttemptなし`received -> operation.failed`を、Operation成立境界、Safe Projection、内部Query Aggregate、CLI、Local Viewer、PSR-3相関、Phase 14の実装順に矛盾なく反映している。

Orchestratorは必須Contract検索、Specification Index、D098未決表現Guard、Management Comment ID Guard、`git diff --check`、Docker内Mago Format Checkを独立再実行し、成功を確認した。Production Codeの変更はなく、P14-001をAcceptedとする。
