# D097: Phase 14 Operation Diagnostics

Status: Decided

## Context

Phase 14は、HTTP ErrorまたはApplication Logに現れたOperation IDから、Lifecycle、Attempt、Error、Outcomeへ到達できるDiagnosticsを提供する。

既存Runtimeには、Operation ID順のCanonical Journal Reader、Typed Outcome Reader、安全なObserved Journal Projection、Deferred State／Dead Letter／Retention Schema、Symfony ConsoleのApplication統合がある。一方、全体を一つに集約するQuery Model、Deferred State Reader、Dead Letter Reader、Purge Audit Reader、Diagnostics Access Policyはない。

監査では実装前に修正すべき相関Gapも見つかった。

- Inline HandlerまたはAuthorization Policyの予期外Throwableは、`operation.received`と`attempt.started`の後に伝播し、`attempt.failed`／`operation.failed`を記録しない
- FrankenPHP Workerの500 Responseと`error_log`はOperation IDを含まない
- `ExecutionScopedLogger`はOperation／Attempt／Correlation／Causation IDを付与できるが、Application RuntimeまたはCompiled Containerへ自動登録されていない
- Observed ProjectionはActor IDと`#[Sensitive]` Valueを保護するが、Failure／Dead Letterの自由文MessageはそのままProjectionされ得る

したがってTerminalやViewerを先に実装すると、不完全なLifecycleと安全でないError Detailをそのまま表示する。Phase 14はFailure相関を先に閉じてからQuery Surfaceを作る必要がある。

## Confirmed Baseline

- Protocol Error、Route不一致、禁止されたGET／HEAD Body、不正CredentialはOperation成立前であり、Operation IDとJournalを作らない。
- Binding Failure、Value Validation、Authorization、Handler RejectionはOperation IDを持ち、Canonical JournalへRejected Lifecycleを記録する。
- Deferred受付成功はHTTP 202とOperation IDを返し、Worker Failureは同じOperation IDのAttempt／Retry／Failed／Dead Letter Journalへ残る。
- Canonical Journalは再現可能性のためRaw Value、Actor ID、Error Messageを含み得るRestricted Dataである。
- Terminal、Viewer、LogへCanonical Dataを直接表示してはならず、安全なDiagnostics Projectionを介する。
- Documentation Website公開はPhase 14のScope外である。

## Question 1: Delivery Depth and Order

Phase 14をどこまで実装するか。

### Options

- A: Inline Failure／Error Log相関を先に修復し、内部Diagnostics Query Model、`operation:inspect`、Development限定Local ViewerまでをPhase 14へ含める。Productionは構造化Log相関までとし、Remote OTelはPhase 18へ送る
- B: Inline Failure相関と`operation:inspect`だけをPhase 14へ含め、Local Viewerを後続Phaseへ送る
- C: Aに加えてOpenTelemetry Adapter、Remote Collector、DashboardまでPhase 14で実装する

### Recommendation

Aを推奨する。

Terminalを最小SliceとしてQuery Contractを固定し、その同じContractを薄いLocal Viewerで再利用できる。ProductionはOperation ID付き構造化Logまでに留めることで、Collector選定、Metric、Tenant分離をPhase 18のSecurity／Observability設計へ送れる。

[ANSWER]

A

[/ANSWER]

## Question 2: Diagnostics Query Model Visibility

TerminalとViewerが共有するQuery ModelをPublic PHP APIにするか。

### Options

- A: Phase 14では`BlackOps\Internal\Diagnostics`のApplication Service／DTOとして実装し、CLI Human／JSON出力だけを利用者Contractにする。Phase 16のStatus／Outcome API設計でAccess Policy込みのPublic Query APIへ昇格する
- B: Phase 14からPublic `OperationDiagnosticsReader`と全DTOを`#[PublicApi]`として公開する
- C: Canonical Journal、Outcome、PostgreSQL TableのReaderを個別に公開し、利用者が集約する

### Recommendation

Aを推奨する。

Phase 14時点ではUnauthorized／Tenant／Remote Access Contractが未確定である。内部AggregateならTerminalとViewerの重複を防ぎつつ、Phase 16でHTTP Status APIに必要な最小Public Shapeを証拠に基づいて選べる。CはCanonical Raw Dataを利用者Surfaceへ漏らしやすい。

[ANSWER]

A

[/ANSWER]

## Question 3: `operation:inspect` Contract

CLIのInput、Output、Exit Codeをどうするか。

### Options

- A: `php blackops operation:inspect <operation-id>`をHuman既定とし、`--json`でVersion付き同一Query結果を出す。Exit CodeはFound=`0`、Invalid Input=`2`、Unavailable=`3`、Storage／Decode Failure=`4`とする。Dataはstdout、診断自体のErrorはstderrへ分ける
- B: Human Tableだけを提供し、Machine-readable ContractとExit Codeは固定しない
- C: JSONだけを提供し、Human表示は外部Toolへ委ねる

### Recommendation

Aを推奨する。

Human表示は開発時に速く、Version付きJSONはScript、将来Viewer Test、Support Toolで再利用できる。Invalid ID、存在を明かせないID、Infrastructure FailureをExit Codeで区別すれば、秘密情報を本文へ出さずにAutomationへ失敗種別を伝えられる。

Human既定は次を表示する。

- Operation ID、Type、Strategy、Correlation／Causation、現在State
- Sequence順Lifecycle Timeline
- Attempt ID／番号／開始時刻／Retry予定
- Safe Rejection／Failure Summary
- Outcomeの有無、型、Safe Projection
- Journal／Outcome／Dead Letter／Transport PayloadのAvailability

`--json`は最低限、`schemaVersion`、`status`、`operation`、`state`、`availability`、`timeline`、`outcome`を持つ。

[ANSWER]

A

[/ANSWER]

## Question 4: Sensitive, Actor, and Error Defaults

Diagnosticsへ何を表示するか。

### Options

- A: 常にSafe Projectionだけを表示する。Actor IDはMask、Credentialは常に除外、`#[Sensitive]`を適用し、Failureは安全なType／Classificationだけを既定表示する。Phase 14ではCanonical Rawまたは例外Messageを表示するOverrideを提供しない
- B: Aを既定にし、Local Terminal限定の`--show-sensitive`／`--show-error-detail`でCanonical Rawを表示できるようにする
- C: Local CLIとViewerではCanonical Rawを既定表示し、ProductionだけMaskする

### Recommendation

Aを推奨する。

Local ShellやBrowser履歴も安全な保管先とは限らない。現状のFailure MessageはApplication例外の自由文であり、CredentialやBackend Detailを含まない保証がない。Raw Access、暗号化Capability、監査付き特権表示はPhase 18でAccess Controlと一緒に設計する。

[ANSWER]

A

[/ANSWER]

## Question 5: Missing, Purged, and Unauthorized

Operation IDの存在情報をどこまで区別するか。

### Options

- A: 全情報が取得不能なMissing／Fully Purged／Unauthorizedは、外部表示を同じ`operation.unavailable`へ畳む。Operationの存在が既に認可済みで一部Dataだけ削除済みの場合はFoundとして返し、`availability`で対象ごとの`available`／`purged`／`not_applicable`を示す
- B: Missing、Purged、Unauthorizedを常に別CodeとMessageで返す
- C: MissingとPurgedだけを区別し、UnauthorizedだけMissingへ畳む

### Recommendation

Aを推奨する。

Operation IDの存在、Retention対象、Actorとの関係を未認可利用者へ明かさない。一方、認可済みのSupport担当者には、残っているTombstone／Purge Auditから部分的な削除状態を示す方が調査に有用である。

[ANSWER]

A

[/ANSWER]

## Question 6: Local Viewer Boundary

Development Viewerをどう起動するか。

### Options

- A: `php blackops operation:viewer`で明示起動するFramework内蔵のRead-only Server-rendered Viewerとする。Canonical `diagnostics.viewer.enabled`は既定`false`、Quickstart Localだけ`true`とし、明示CLIとEnable Gateの両方を要求する。既定Bindを`127.0.0.1`、既定Portを`8082`、起動ごとのRandom Access Tokenを必須にし、Phase 14ではNon-loopback Bindを拒否する
- B: Applicationの通常HTTP RouteへViewerを組み込み、Application Authentication／Authorizationへ委ねる
- C: Static HTML／JavaScriptだけを生成し、Database Query APIは別途利用者が用意する

### Recommendation

Aを推奨する。

Application Routeへ混ぜるとProduction露出、Middleware、Route Conflict、認証設定がPhase 14へ入り込む。既定無効Config、明示CLI、Loopback、Session Token、Read-only Server-renderingなら最小のDevelopment SurfaceでTerminalと同じQuery Modelを再利用できる。現行FrameworkにCanonical Runtime Environment判定がないため、曖昧な`APP_ENV`推測ではなく明示Enable Gateを正本とする。

[ANSWER]

A

[/ANSWER]

## Question 7: Production Logging and Observability

Phase 14でProduction向けに何を提供するか。

### Options

- A: PSR-3 LoggerをApplication Runtime／DIへ接続し、Operation ScopeのApplication LogとFramework Error LogへOperation／Attempt／Correlation／Causation IDを安全に付与する。HTTP 500にもOperation IDを返す。Sink、Retention、Alert、OTel AdapterはApplication／Phase 18へ委ねる
- B: HTTP Error ResponseへOperation IDを追加するだけで、Logger CompositionはApplicationへ委ねる
- C: Aに加えてOpenTelemetry Trace／Metric AdapterとRemote Exporterを標準実装する

### Recommendation

Aを推奨する。

既存`ExecutionScopedLogger`をRuntimeへ接続すれば、Handlerが各LogでOperation IDを手書きせず相関できる。Phase 14では安全な構造化EnvelopeとError BoundaryまでをFramework責務とし、外部Sink固有のDelivery、Retention、Dashboardは分離できる。

[ANSWER]

A

[/ANSWER]

## Confirmed Query Aggregate

Phase 14の内部Query結果は、Store固有Objectを直接露出せず次を集約する。

```text
OperationDiagnostics
  identity
    operationId, type, schemaVersion, strategy
    correlationId, causationId
  state
    current, terminal, source
  availability
    transportPayload, journal, outcome, deadLetter
  timeline[]
    sequence, event, occurredAt, safe data
  attempts[]
    attemptId, number, startedAt, events[]
  outcome
    available, type, completedAt, safe data
```

- Deferredの現在Stateは`operations.state`を正本とし、JournalはTimelineを担う。
- InlineはOperations行を持たないため、Sequence順Journalの最後のEventから現在Stateを導出する。
- StateとJournalが矛盾する場合は自動補正せず、Diagnostics Integrity Errorとして表示／報告する。
- Outcome Decode FailureをMissingへ畳まずStorage／Decode Failureとする。Human表示では安全なCodeだけ、詳細はstderr／Framework Logへ出す。
- Partially Purged Recordは残存Sourceを返し、削除済み対象を`availability`へ示す。

## Confirmed Delivery Plan

1. P14-001: Decision確定、Operation Diagnostics Specification、Phase 14 Delivery Plan
2. P14-002: Inline Throwable Lifecycle、Operation ID付きHTTP 500、Runtime PSR-3相関
3. P14-003: Deferred State／Dead Letter／Purge Audit Readerと内部Diagnostics Query Aggregate
4. P14-004: `operation:inspect` Human／JSON CLI
5. P14-005: Development限定Local Viewer
6. P14-006: Production Correlation Contract、Configuration、Failure／Sensitive回帰
7. P14-007: Quickstart／Guide／Consumer Experience／Phase Closeout

## Deferred Scope

- Phase 15: Frontend ContractからOperation IDへの接続。Diagnostics UIを生成Clientへ組み込まない。
- Phase 16: Public Status／Outcome Query API、HTTP Endpoint、Application Access Policy、Polling Contract。
- Phase 18: Tenant分離、Canonical Raw Access Control、保存時暗号化、特権表示Audit、OpenTelemetry、Metric、Remote Exporter、Health／Readiness。

## Decision

[DECISION]

1. Phase 14はInline Failure／Error Log相関を先に修復し、内部Diagnostics Query Model、`operation:inspect`、Development限定Local Viewerまでを実装する。OpenTelemetryとRemote ObservabilityはPhase 18へ延期する。
2. Query Modelは`BlackOps\Internal\Diagnostics`配下のApplication Service／DTOとし、Phase 14の利用者ContractはCLIのHuman／Version付きJSON出力とする。Public PHP Query APIはPhase 16でAccess Policyと同時に設計する。
3. CLIは`php blackops operation:inspect <operation-id>`をHuman表示の既定とし、`--json`を提供する。Exit CodeはFound=`0`、Invalid Input=`2`、Unavailable=`3`、Storage／Decode Failure=`4`で固定し、Dataをstdout、診断自体のErrorをstderrへ分離する。
4. CLI、Viewer、Framework Log、HTTP Errorは常にSafe Projectionだけを使用する。Actor IDをMaskし、Credentialと`#[Sensitive]`対象を除外し、Failureは安全なType／Classificationだけを既定表示する。Phase 14でRawまたは例外Messageの表示Overrideは提供しない。
5. Missing／Fully Purged／Unauthorizedは、全情報が取得不能な場合に同じ`operation.unavailable`へ畳み込む。認可済みで一部Dataだけ削除済みのOperationはFoundとし、対象ごとのAvailabilityを返す。
6. Local Viewerは`php blackops operation:viewer`で明示起動するRead-only Server-rendered Viewerとする。`diagnostics.viewer.enabled`は既定`false`、Quickstart Localだけ`true`とし、明示CLIとEnable Gateを両方必須にする。既定は`127.0.0.1:8082`、起動ごとのRandom Access Tokenを必須とし、Non-loopback Bindを拒否する。
7. PSR-3 LoggerをApplication Runtime／DIへ接続し、Operation ScopeのApplication LogとFramework Error LogにOperation／Attempt／Correlation／Causation IDを付与する。Operation成立後のHTTP 500はOperation IDを返す。Log Sink、Retention、AlertはApplication、OTel AdapterはPhase 18の責務とする。

[/DECISION]

## Consequences

[CONSEQUENCES]

- TerminalとViewerの前にInline ThrowableのTerminal LifecycleとHTTP／Log相関を閉じ、不完全なTimelineを新しいQuery Surfaceへ固定しない。
- Canonical JournalはRestricted Dataの正本として維持し、Diagnostics Surfaceは独立したSafe Projectionを通す。
- InlineはJournal、Deferredは`operations.state`を現在Stateの正本とし、不整合を自動修復せずIntegrity Errorとして報告する。
- Local ViewerはApplication RouteやProduction Authenticationに組み込まず、既定無効、Loopback限定、Token必須のDevelopment Toolとする。
- Public Status／Outcome API、Tenant境界、特権Raw Access、OpenTelemetryはそれぞれPhase 16／18までPublic Contractにしない。

[/CONSEQUENCES]

## References

- [Lifecycle and Journal](../spec/02-lifecycle-and-journal.md)
- [Execution](../spec/03-execution.md)
- [HTTP Adapter](../spec/05-http.md)
- [Logging and Traceability](../spec/10-logging-and-traceability.md)
- [Sensitive Projection](../spec/25-sensitive-projection.md)
- [Journal Ports](../spec/26-journal-ports.md)
- [Data Retention and Deletion](../spec/38-data-retention-and-deletion.md)
- [Post Phase 10 Roadmap](../spec/60-post-phase-10-roadmap.md)
- [D089 Validation Rejection Sensitive Journal](089-validation-rejection-sensitive-journal.md)
- [D093 Post Phase 10 Roadmap](093-post-phase-10-roadmap.md)
- [D095 Phase 12 Middleware and Authorization Runtime](095-phase-12-middleware-and-authorization-runtime.md)
