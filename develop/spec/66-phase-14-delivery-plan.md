# Phase 14 Delivery Plan

## Goal

HTTP ErrorまたはApplication LogのOperation IDから、安全なTerminal／Development Viewerへ到達できるOperation Diagnosticsを実装する。

不完全なLifecycleをQuery Surfaceへ固定しないため、Failure相関、内部Query Aggregate、Terminal、Viewerの順に実装する。Production向けはPSR-3構造化Log相関までとし、Public Status APIとOpenTelemetryを後続Phaseへ分離する。

Operation ID発行後かつAttempt開始前のDeferred受付Failureは、[Deferred Acceptance Failure Lifecycle Decision](../decisions/098-deferred-acceptance-failure-lifecycle.md)に従い、受付TransactionのRollback後に別Transactionで`received -> operation.failed`を記録する。Attemptは作らず、HTTP 500、Framework Log、Journalを同じOperation IDで相関する。

## P14-001: Operation Diagnostics Specification

- HTTP Error／Log／Journal／Outcome／RetentionのOperation ID境界を監査する
- D097でTerminal、Viewer、Safe Projection、Production LogのDepthを確定する
- `OperationDiagnostics` Aggregate、State Authority、Availability、Integrity Failureを仕様化する
- `operation:inspect` Human／JSON／Exit Codeを仕様化する
- `operation:viewer` Enable Gate／Loopback／Token／Read-only境界を仕様化する
- Phase 14のTask順序と後続Phase境界を固定する

Acceptance Gate:

- Specification、TODO、README、Decision Traceabilityが同期する
- Production Code、Test、Migration、Guideを変更しない
- D098のAttemptなしTerminal FailureをDiagnostics ContractとTask順序へ反映する

## P14-002: Inline Failure and Runtime Correlation

- Inline Attempt開始後の予期しないThrowableを`attempt.failed -> operation.failed`へTerminal化する
- Transactional OperationはRollback後の別TransactionでFailure Journalを保存する
- Primary ThrowableをRollback／Journal Failureで置き換えない
- Operation成立後のSafe HTTP 500へ同じOperation IDを返す
- ClassicとFrankenPHP Worker Modeを同じError Responderへ接続する
- Shared `ExecutionScopeProvider`と`ExecutionScopedLogger`をApplication Runtime／DIへ接続する
- Application LogとFramework Error LogへOperation／Attempt／Correlation／Causation IDを付ける
- Actor ID、Credential、Value、Exception Message、Stack TraceをLog／Responseへ出さない
- Deferred受付のAttempt開始前Throwableを、Rollback後の別Transactionによる`received -> operation.failed`へTerminal化する
- Attempt開始前FailureへAttemptを架空に作らず、HTTP 500、Framework Log、Journalを同じOperation IDで相関する
- Lifecycle／State Machine SpecificationへAttemptなしTerminal Failureを同期する

Acceptance Gate:

- Inline Handler／Policy／Transaction ThrowableがTerminal Failed Journalを持つ
- HTTP 500、Framework Log、Journalが同じOperation IDを持つ
- Transaction Rollback、Journal Failure、Worker Modeで相関とPrimary Failureが維持される
- Logger FailureがOperation結果を変更しない
- Existing Binding／Validation／Authorization RejectionとDeferred Worker Supervisionが回帰しない

## P14-003: Diagnostics Readers and Query Aggregate

- Existing Canonical Journal ReaderとOutcome Readerを再利用する
- Internal Deferred State Reader、Dead Letter Reader、Retention Purge Audit Readerを追加する
- ReaderはEncoded Payload、Dead Letter Message、Purge Actor／Policy Detailを返さない
- Safe Diagnostics Journal／Outcome Projectionを実装する
- Internal `OperationDiagnostics` Result／DTO／Query Serviceを実装する
- InlineはJournal、DeferredはOperations StateをCurrent Stateの正本にする
- SourceごとのAvailable／Purged／Not Applicableを集約する
- Missing／Fully Purgedを`operation.unavailable`へ畳む
- Sequence、Transition、Identity、Attempt、State、Outcome、Dead Letter、PurgeのIntegrityを検査する

Acceptance Gate:

- Inline Completed／Rejected／FailedとDeferred Accepted／Running／Retry／Completed／Rejected／Failed／Dead LetteredをQueryできる
- Partially Purged Deferred OperationをFound＋Availabilityで返す
- Missing／Fully Purgedを同じUnavailable Resultで返す
- Decode／Storage／Integrity Failureを相互に区別する
- AggregateまたはTest FailureへRaw Value、Actor ID、Exception／Dead Letter Messageが残らない
- MigrationとPublic PHP APIを追加しない

## P14-004: Operation Inspect CLI

- `operation:inspect <operation-id>`をFramework Console KernelへLazy登録する
- Human既定表示と`--json` Version 1 Encoderを同じQuery Aggregateへ接続する
- Operation、State、Availability、Actors、Timeline、Attempts、Outcomeを表示する
- stdoutへData、stderrへCommand／Query Errorを分離する
- Found 0、Invalid Input 2、Unavailable 3、Storage／Decode／Integrity 4を実装する
- `--show-sensitive`／`--show-error-detail`を提供しない
- Database Configuration SnapshotとFramework Connectionを再利用する

Acceptance Gate:

- Human／JSONが同じAggregate内容を表す
- JSON stdoutにDecoration／Progress／Debug Detailが混ざらない
- Error時stdoutが空で、Human／JSON stderrとExit Codeが仕様どおりである
- Unknown Command、Application Command衝突、Database unavailableを回帰Testする
- PrefixなしCanonical Commandだけを登録する

## P14-005: Development Local Viewer

- `operation:viewer`をFramework Console KernelへLazy登録する
- `diagnostics.viewer.enabled`を既定`false`で検証する
- Quickstart LocalだけViewerを明示有効にする
- `127.0.0.1:8082`既定とし、Non-loopback Bindを拒否する
- 起動ごとの256 bit以上Random TokenとSession Cookie Bootstrapを実装する
- Read-only Server-rendered Summary／Availability／Timeline／Attempt／Outcome画面を作る
- Security Header、No-store、No-referrer、Method制限を実装する
- UnavailableとInternal Query FailureのDetailを隠す

Acceptance Gate:

- Disabled ConfigではBind前にFailする
- 明示CommandなしにServerを起動しない
- Tokenなし／不一致は同じ404、正しいTokenだけがCookie Sessionを開始する
- Wildcard／LAN Bind、Write Method、Raw Endpoint、List／Search／Retry操作が存在しない
- HTMLとResponseからRaw Value、Actor ID、Error Message、Tokenが漏れない
- SIGINT／SIGTERMでForeground Serverが終了する

## P14-006: Production Correlation and Security Regression

- PSR-3 Backend ConfigurationをApplication Configuration Snapshotから一度だけ解決する
- HTTP Request、Deferred Attempt、Nested Operation、Long-running LoopのScope Leakを検査する
- Operation成立前／後の500 ResponseとLog ID境界を回帰Testする
- Failure／Dead Letter専用Safe ProjectionをObserver、Terminal、Viewerで一貫させる
- Missing／Fully Purged／Unauthorizedを`operation.unavailable`へ畳む境界をTest Fixtureで固定する
- ProductionではViewerが既定無効で、自動起動しないことを検証する
- Log Sink／Retention／AlertとFrameworkの責任分界をInternal Documentationへ同期する

Acceptance Gate:

- Multi-request／Multi-attemptでOperation Contextが混線しない
- Application LogとFramework Error Logが同じ予約Field Shapeを使う
- Logger／Observer／Database FailureでもCredential、Raw Value、Actor ID、Exception MessageがSurfaceへ出ない
- Classic／Worker、Inline／DeferredのCorrelation MatrixをIntegration Testで再現する
- OpenTelemetry、Remote Collector、Metric、Dashboardを追加しない

## P14-007: Consumer Experience and Closeout

- Quickstartへ失敗するInline OperationとOperation ID診断Journeyを追加する
- HTTP 500のOperation IDを`operation:inspect` Human／JSONへ渡すConsumer E2Eを追加する
- Local ViewerのEnable、起動、Token、Read-only、TimelineをConsumer E2Eで検証する
- SkeletonへDiagnostics Config、CLI、Guideを同期する
- Guide、Security、Troubleshooting、CLI／Configuration Referenceを実装へ同期する
- Framework UpdateとSkeleton Create-project Consumer Testを更新する
- Full PHP／Consumer／Website Quality Gateを実行する
- TODO、Report、STATEを同期してPhase 14をCloseする

Acceptance Gate:

- Install直後のQuickstartでHTTP ErrorからTerminalとViewerへ到達できる
- HumanとJSONの例が実出力と一致する
- Sensitive ValueとError MessageがHTTP、Log、CLI、Viewer、JSONLへ出ない
- Stable／main表示とExperimental Compatibilityの正直さを維持する
- Framework／Skeleton／Quickstart／Guide／Website Testが同期する
- Documentation Websiteを外部公開しない

## Dependency Order

```text
P14-001 Operation Diagnostics Specification
  -> P14-002 Inline Failure and Runtime Correlation
    -> P14-003 Diagnostics Readers and Query Aggregate
      -> P14-004 Operation Inspect CLI
        -> P14-005 Development Local Viewer
          -> P14-006 Production Correlation and Security Regression
            -> P14-007 Consumer Experience and Closeout
```

P14-006で見つかったSafe Projection不整合はCloseoutへ先送りせず修正する。P14-003のQuery Aggregate Shapeを変更する必要がある場合は、CLI／Viewerを個別PatchせずSpecificationと両Surfaceを同じTaskで同期する。

## Phase Acceptance Criteria

- [x] Operation成立後の予期しないFailureがHTTP、Log、Journalで同じOperation IDを持つ
- [x] Inline Attempt FailureがTerminal Journalへ到達する
- [x] Application ServiceがPSR-3 LoggerをConstructor Injectionし、Operation Contextを自動取得できる
- [x] Internal Query AggregateがInline／DeferredのState、Timeline、Attempts、Outcome、Availabilityを一貫して返す
- [x] Missing／Fully Purged／Unauthorizedが`operation.unavailable`へ畳まれる
- [x] `operation:inspect` Human／JSON／Exit Code／stdout／stderrが仕様どおりである
- [ ] Local Viewerが既定無効、明示起動、Loopback限定、Random Token必須、Read-onlyである
- [ ] HTTP、Log、Terminal、ViewerへCredential、Raw Value、Actor ID、Exception／Dead Letter Messageを出さない
- [ ] Production Log Sink／Retention／AlertとFramework相関の責任境界が明文化される
- [ ] Public PHP Query API、Status／Outcome HTTP API、Tenant分離、OpenTelemetryをPhase 14へ追加しない
- [ ] Quickstart、Skeleton、Guide、Consumer E2EがPublic CLI／Viewer Contractを再現する
- [ ] Full PHP／Consumer／Website Quality Gateが成功する

## Deferred Scope

- Phase 15: Operation Frontend BridgeからOperation IDへ接続するが、Diagnostics UIを生成しない。
- Phase 16: Public Status／Outcome Query API、HTTP Endpoint、Polling、Authentication／Authorization、Tenant境界。
- Phase 18: Canonical Raw Access、暗号化、Tenant分離、特権Audit、OpenTelemetry、Metric、Remote Exporter、Health／Readiness。

## Traceability

- Decision: [D097 Phase 14 Operation Diagnostics](../decisions/097-phase-14-operation-diagnostics.md)
- Lifecycle Decision: [D098 Deferred Acceptance Failure Lifecycle](../decisions/098-deferred-acceptance-failure-lifecycle.md)
- Diagnostics Contract: [Operation Diagnostics](65-operation-diagnostics.md)
- Lifecycle: [Lifecycle and Journal](02-lifecycle-and-journal.md)
- Logging: [Logging and Traceability](10-logging-and-traceability.md)
- Retention: [Data Retention and Deletion](38-data-retention-and-deletion.md)
- Roadmap: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)
