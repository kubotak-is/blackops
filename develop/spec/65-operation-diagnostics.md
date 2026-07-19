# Operation Diagnostics

## Goal

Operation成立後のHTTP ErrorとApplication Logに現れるOperation IDから、Lifecycle、Attempt、Failure、Outcome、Retention後のAvailabilityへ安全に到達できるDiagnosticsを提供する。

Phase 14は次を実装する。

- Inline FailureのTerminal LifecycleとOperation ID付きHTTP 500
- Application Runtime／DIへ接続したPSR-3相関Logger
- Framework内部の`OperationDiagnostics` Query Aggregate
- `php blackops operation:inspect <operation-id>`
- Development用のRead-only Local Viewer

Public PHP Query API、Public HTTP Status／Outcome API、Tenant分離、Canonical Raw Access、OpenTelemetryは含めない。

## Operation Establishment and Error Correlation

Operation IDは対象Operation Definitionを特定した後にFrameworkが一度だけ発行する。同じRequest／OperationのResponse、Log、Journal、Diagnosticsで別のOperation IDを再発行してはならない。

| Boundary | Operation成立 | Operation ID | Journal | HTTP Error |
| --- | --- | --- | --- | --- |
| Route不一致 | No | なし | なし | 404、IDなし |
| 禁止されたGET／HEAD Body | No | なし | なし | 400、IDなし |
| Malformed／non-object JSON | No | なし | なし | 400、IDなし |
| Invalid Credential | No | なし | なし | 401、IDなし |
| Authenticatorの予期しないThrowable | No | なし | なし | 500、IDなし |
| Binding Failure | Yes | 発行する | Sequence 1 Rejected | 422、同じID |
| Value Validation Failure | Yes | 発行する | Received、Rejected | 422、同じID |
| Authorization Rejection | Yes | 発行する | Rejected Terminal | 401／403、同じID |
| Handler Business Rejection | Yes | 発行する | Rejected Terminal | Category対応4xx、同じID |
| Inline Attempt内の予期しないThrowable | Yes | 既存IDを維持 | Attempt Failed、Operation Failed | 500、同じID |
| Deferred受付のAttempt開始前Throwable | Yes | 既存IDを維持 | Received、Operation Failed。Attemptなし | 500、同じID |
| Deferred受付成功 | Yes | 発行する | Received、Accepted | 202、同じID |
| Deferred Worker Failure | Yes | 受付IDを維持 | Attempt／Retry／Failed／Dead Letter | 新しいHTTP Responseなし |

Operation ID発行後にCanonical Journal、Database、Responder等が失敗した場合も、Error BoundaryがIDを保持できる限りHTTP 500とFramework Error Logへ同じIDを付ける。Journal保存自体が失敗してDiagnosticsで検索不能でも、別IDへ置き換えない。

## Failure Before an Attempt

Operation ID発行後かつAttempt開始前にDeferred受付のAuthorization PolicyまたはFramework／Application Serviceが予期しないThrowableを投げた場合、受付TransactionをRollbackした後、別Transactionで次を記録する。

```text
operation.received
  -> operation.failed
```

- `received -> operation.failed`を正式なTerminal遷移とする。
- Attemptは開始も作成もせず、Attempt IDとAttempt Numberを持たない。
- `OperationFailedData.retryable`は`false`とする。
- HTTP 500、Framework Error Log、Canonical Journalは発行済みの同じOperation IDを使う。
- Failure記録用Transactionも失敗した場合、最初のThrowableをPrimary Failureとして維持し、二次障害は安全なFramework Error Logへ別Codeで記録する。
- Expected Authorization Rejectionはこの遷移を使わず、従来どおり`operation.rejected`へ到達する。
- Diagnostics AggregateはAttemptsが空のTerminal Failed Operationを表現する。

## Inline Failure Lifecycle

Inline Attempt開始後、`attempt.succeeded`へ到達する前にAuthorization Policy、Handler Resolution、Transaction開始、Handler、またはFramework Invocationが予期しないThrowableを投げた場合、自動Retryしない。

```text
operation.received
  -> attempt.started
    -> attempt.failed
      -> operation.failed
```

- `attempt.failed`と`operation.failed`は同じOperation IDとAttempt IDを使う。
- `AttemptFailedData.retryable`と`OperationFailedData.retryable`は`false`とする。
- Transactional OperationはApplication TransactionをRollbackした後、Failure Journalを別Transactionで記録する。
- RollbackまたはFailure Journal記録も失敗した場合、最初のThrowableをPrimary Failureとして維持する。追加Failureは安全なFramework Error Logへ別Codeで記録する。
- Canonical Failure DataはException TypeとMessageを保持し得るRestricted Dataである。
- HTTP、PSR-3 Log、CLI、ViewerへException Message、Stack Trace、Argument、Previous Exception Detailを出さない。
- Error Responseは次のSafe JSONだけを返す。

```json
{
  "status": "error",
  "code": "internal_error",
  "operationId": "019..."
}
```

Operation成立前またはOperation外の500は`operationId`を持たない。Application HTTP Compositionの共通最外周BoundaryでDB prepare、PSR-15 Middleware、Observer flush、Connection cleanup等のThrowableをSafe JSONへ変換する。同じError ResponderをClassic EntrypointとFrankenPHP Worker Modeで使用し、EntrypointごとにResponse Shapeを変えない。Operation成立後の`OperationExecutionFailed`は内側Boundaryで発行済みOperation IDを維持する。

## PSR-3 Runtime Correlation

Application Runtimeは共有`ExecutionScopeProvider`を使う`ExecutionScopedLogger`を構成し、Compiled Containerの`Psr\Log\LoggerInterface`へ同じInstanceをRuntime Serviceとして注入する。

HandlerとApplication Serviceは`LoggerInterface`をConstructor Injectionし、Log呼出ごとにOperation IDを手書きしない。

Operation Scope内のApplication LogとFramework Error Logは、存在する次のFieldをFramework予約Fieldとして付与する。

```text
kind
operation.id
operation.type
operation.attemptId
operation.correlationId
operation.causationId
operation.strategy
operation.actors.origin.id
operation.actors.origin.type
operation.actors.authorization.id
operation.actors.authorization.type
operation.actors.execution.id
operation.actors.execution.type
```

- Actor IDは`[masked]`とし、Actor Typeだけを維持する。
- Credential、OperationValue、Outcome、Exception Messageを自動Contextへ含めない。
- User Contextは予約Fieldと分け、共通Sensitive Filterを通す。
- Operation外のSystem LogはOperation Fieldを持たない。
- Nested Operation終了後は親Scopeを復元する。
- HTTP Request間、Deferred Attempt間、Long-running Worker Loop間でScopeを残さない。
- Application Log Backend失敗はOperationを失敗させず吸収し、別SinkへFallbackしない。

ApplicationはLog Sink、Delivery、Retention、Alertを所有する。FrameworkはPSR-3相関Decoratorと安全な既定Backendを構成できるが、外部CollectorへのDeliveryを保証しない。OpenTelemetry Adapterと安定したRemote Log SchemaはPhase 18で扱う。

## Internal Query Boundary

Phase 14のQuery ServiceとDTOは`BlackOps\Internal\Diagnostics`に置く。`#[PublicApi]`を付けず、Application Service／Controllerから直接利用するPublic PHP Contractにしない。

TerminalとViewerは同じ内部Query Serviceを使用する。Canonical Store、PostgreSQL Table、Outcome Readerを個別に呼び分けたり、SurfaceごとにStateを再計算したりしない。

Query結果は次の二状態だけを返す。

```text
OperationDiagnosticsResult
  Found(OperationDiagnostics)
  Unavailable(operation.unavailable)
```

Storage、Decode、Integrity FailureはUnavailableへ畳まず、内部Diagnostics Exceptionとして失敗させる。

```text
diagnostics.storage_failed
diagnostics.decode_failed
diagnostics.integrity_failed
```

ExceptionのPublic MessageへSQL、Table名、Connection Parameter、Payload、Codec Detailを含めない。

## OperationDiagnostics Aggregate

`OperationDiagnostics`はSafe Projectionだけを保持する。不変DTOとし、Canonical `JournalRecord`、Raw `Outcome`、Encoded Payload、Connection、ThrowableをPropertyへ保持しない。

```text
OperationDiagnostics
  identity
    operationId: string
    type: string
    schemaVersion: int
    strategy: string
    correlationId: string|null
    causationId: string|null
    actors: SafeActorContext|null
  state
    current: LifecycleState
    terminal: bool
    source: journal|transport
  availability
    transportPayload: available|purged|not_applicable
    journal: available|purged|not_applicable
    outcome: available|purged|not_applicable
    deadLetter: available|purged|not_applicable
  timeline: list<DiagnosticsTimelineEntry>
  attempts: list<DiagnosticsAttempt>
  outcome: DiagnosticsOutcome|null
```

`SafeActorContext`はorigin、authorization、executionを保持する。各Actorは`id: "[masked]"`と`type`だけを持ち、存在しないActorは`null`とする。

`correlationId`は通常必須だが、Deferred Stateだけが残ってJournalとContextがRetention削除済みの場合は`null`を許す。欠落を新しいIDで補わない。

### Timeline Entry

```text
DiagnosticsTimelineEntry
  sequence: int
  event: string
  occurredAt: canonical UTC microseconds
  attemptId: string|null
  attemptNumber: int|null
  data: safe object
```

Event Dataは次だけを表示できる。

| Event | Safe Data |
| --- | --- |
| `operation.received` | Sensitive Filter後のValue |
| `operation.accepted` | Empty Object |
| `attempt.started` | Empty Object |
| `attempt.succeeded` | Empty Object |
| `attempt.failed` | `errorType`、`retryable`。`errorMessage`禁止 |
| `attempt.retry_scheduled` | Failed Attempt ID、次回番号、予定時刻、Delay |
| `operation.completed` | Empty Object。OutcomeはAggregateのOutcome Fieldへ分離 |
| `operation.rejected` | Category、Code、Field／Rule／CodeだけのViolation |
| `operation.failed` | `errorType`、`retryable`。`errorMessage`禁止 |
| `operation.dead_lettered` | Final Attempt ID／番号、`reasonType`、Moved At。`reasonMessage`禁止 |

### Attempt

```text
DiagnosticsAttempt
  attemptId: string
  number: int
  startedAt: canonical UTC microseconds
  events: list<int sequence>
```

Historic AttemptはJournalから構成する。DeferredのCurrent AttemptがState RowにありJournalがPurged済みの場合、State Rowから現在Attemptだけを構成できる。架空のAttemptや欠落したTimestampを生成しない。

### Outcome

```text
DiagnosticsOutcome
  type: string
  completedAt: canonical UTC microseconds|null
  source: journal|outcome_store
  data: safe object
```

- Deferred Outcomeの正本はOutcome Storeとし、Outcome RetentionでPurgedされた後にCompleted Journal DataへFallbackしない。
- Inline Outcomeは専用Outcome Storeを持たないため、`operation.completed`のCanonical Journalから読み、即時Safe Projectionする。
- Rejected、Failed、Dead Lettered、未完了OperationのOutcome Availabilityは`not_applicable`とする。
- Outcome Decode FailureはMissingまたはPurgedとして扱わず`diagnostics.decode_failed`とする。

## Source Authority

### Inline

- Current StateとIdentityの正本はSequence順Canonical Journalとする。
- Lifecycle State Machineを先頭から適用してCurrent Stateを導出する。
- JournalがすべてPurgedされ、Purge Auditだけが残る場合は外部ResultをUnavailableとする。
- InlineのTransport PayloadとDead Letterは`not_applicable`とする。

### Deferred

- Current State、Operation Type、Schema Version、Transport Payload Availabilityの正本はOperations Rowとする。
- Operations Row作成前に終端するBinding Failure、Value Validation Failure、Expected Authorization Rejectionは例外とし、Sequence順JournalからCurrent StateとIdentityを導出する。これらはAttemptなしのRejectedであり、`operation.rejected`または`operation.received -> operation.rejected`を持つ。
- Operations Row作成前の予期しない受付Failureも例外とし、Sequence順JournalからAttemptなしのFailedを導出する。この経路は`operation.received -> operation.failed`を持つ。
- `operation.accepted`到達後またはAttempt開始後にOperations Rowが欠落している場合はJournal-onlyへFallbackせずIntegrity Failureとする。
- Strategyは`deferred`とする。
- JournalはTimelineとHistoric Attemptの正本とする。
- Outcome StoreはDeferred Outcomeの正本とする。
- Dead Letter RowはDead Letter Detailの正本とする。
- Purge Auditは削除済みSourceを確認する補助証拠とする。
- Transport PayloadのTombstoneは単独で`purged`を証明できる。Purge Auditが存在してもOperations RowがAvailableを示す場合はIntegrity Failureとする。
- State RowとJournalが両方存在する場合、導出Stateが一致しなければIntegrity Failureとする。

Query用PostgreSQL Readerは既存Schemaを読み、Migrationを追加しない。Operation State ReaderはEncoded Payload／Contextを返さず、Dead Letter Readerは`reason_message`をSELECTせず、Purge Audit ReaderはTarget、Affected Count、Purged Atだけを返す。

## Availability and Visibility

AvailabilityはSourceごとに判定する。

- `available`: Sourceが存在し、安全にDecode／Projectできた
- `purged`: TombstoneまたはPurge Auditが削除を証明する
- `not_applicable`: Lifecycle／Strategy上、そのSourceを作らない

期待されるSourceがなく、Purge証拠もない場合はAvailabilityを`missing`としてFoundへ返さず、`diagnostics.integrity_failed`にする。

次は同じ外部Codeへ畳む。

```text
operation.unavailable
```

- Operation IDがどのSourceにも存在しない
- Fully PurgedでIdentityを構成できない
- 呼出SurfaceのAccess判定が拒否した

Phase 14のCLIはLocal OS Authority、ViewerはLocal Enable GateとSession TokenをAccess境界とし、Application User／Tenant Authorizationは行わない。将来のHTTP SurfaceはStorage Query前にAccessを判定し、Unauthorizedを同じUnavailableへ変換する。

Partially PurgedでもOperations RowまたはJournalからIdentityを構成できる場合はFoundとし、SourceごとのAvailabilityを返す。Purge AuditのPolicy名、実行Actor、Hold Reasonを既定Diagnosticsへ含めない。

## Integrity Validation

Query Serviceは少なくとも次を検証する。

- Journal Sequenceが1から始まり、重複せず連続している
- Lifecycle EventがState Machineの正しい順序である
- 全Journal RecordのRecord Schema Version、Operation ID、Type、Schema Version、Strategy、Correlation／Causation、origin Actor、authorization Actorが一貫する
- execution ActorはRecord生成主体であり、HTTP受付からWorkerへの移行、Retry、Lease Recoveryで変化できるためOperation Identityの一貫性検証へ含めない
- Attempt ID、Attempt番号、Started Atが同じAttempt内で一貫し、Attempt番号が1から連続する
- Retry Scheduledが存在するFailed Attemptを参照する
- Deferred Operations StateとJournal導出Stateが一致する
- Completed Deferred OperationだけがOutcomeを持つ
- Dead Letter RowはDead Lettered Stateだけに存在する
- Identityを構成できるOperations Row／JournalがなくDead Letter Rowだけが存在する場合はIntegrity Failureとなる
- Available Sourceと同じTargetのPurge Auditが矛盾しない

不整合を自動修復、補完、並べ替えで隠さない。CLIとViewerは安全なIntegrity Errorだけを表示し、Framework Error LogへOperation IDと`diagnostics.integrity_failed`を記録する。

## Safe Diagnostics Projection

Canonical Journal、Outcome、Transport Context、Dead Letter MessageはRestricted Dataである。ReaderはRaw DataをLocal Variableとして扱い、`OperationDiagnostics`を構築する前にProjectionする。

次をすべてのDiagnostics Surfaceで禁止する。

- Credential、Token、Session ID、API Key、JWT Claim
- `#[Sensitive]`のOmit対象
- Mask前のActor ID
- Canonical Raw OperationValue／Outcome
- Exception／Dead Letter Message
- Stack Trace、Throwable Argument、Previous Exception Detail
- SQL、Connection Parameter、Database Secret
- Retention Hold Reason、Purge Actor、Policy内部Detail

Failure Type、Dead Letter Reason Type、Rejection Category／Codeは表示できる。Type自体へ秘密値を埋め込んではならない。

Phase 14は`--show-sensitive`、`--show-error-detail`、Raw Download、Raw JSON Endpointを提供しない。Local TerminalとLoopback Viewerも例外にしない。

## Operation Inspect CLI

Canonical Commandは次とする。

```text
php blackops operation:inspect <operation-id>
php blackops operation:inspect <operation-id> --json
```

`operation-id`はRequired Argumentであり、`OperationId::fromString()`で検証する。Whitespace補正、短縮ID、Prefix検索、最新Operationの暗黙選択は行わない。

Symfony Console上のMissing ArgumentもFramework Command内でMalformed IDと同じSafe Errorへ畳み、Exit 2とする。Helpは`operation:inspect <operation-id> [--json]`の論理必須Usageを表示する。Operation ID検証に成功するまでDatabase Connection、Reader、Queryを構成しない。

### Human Output

Found時は次の順序で表示する。

1. Operation: ID、Type、Strategy、Schema Version、Correlation／Causation
2. State: Current、Terminal、Authority Source
3. Availability: Transport Payload、Journal、Outcome、Dead Letter
4. Actors: Mask済みIDとType
5. Timeline: Sequence、UTC Timestamp、Event、Attempt、Safe Data
6. Attempts: ID、番号、Started At、関連Sequence
7. Outcome: Availability、Type、Completed At、Safe Data

Human出力はColorの有無に意味を持たせず、Non-interactive Terminalでも同じ情報を読めるようにする。
Safe Aggregate内の可変文字列もRaw連結せず、改行、ANSI Escape、その他Control Character、Quote、Backslashを一行内のescaped representationへ変換する。通常のASCII／Unicode文字列は可読表示を維持し、Data ObjectはJSON escapingを使う。

### JSON Output

Found時はstdoutへ一つのJSON Objectと末尾改行だけを出す。

```json
{
  "schemaVersion": 1,
  "status": "found",
  "operation": {},
  "state": {},
  "availability": {},
  "timeline": [],
  "attempts": [],
  "outcome": null
}
```

- KeyはcamelCase、時刻はUTC RFC 3339マイクロ秒形式とする。
- `operation`はIdentityとSafe Actor Contextを含む。
- JSON KeyをHuman Labelから生成せず、専用Encoderで固定する。
- `--json`時にProgress、Decoration、SQL、Debug Detailをstdoutへ混ぜない。

### Failure Output and Exit Codes

| Result | Exit Code | stderr Code | stdout |
| --- | ---: | --- | --- |
| Found | 0 | なし | HumanまたはJSON Data |
| Invalid Input | 2 | `operation.invalid_id` | Empty |
| Missing／Fully Purged／Unauthorized | 3 | `operation.unavailable` | Empty |
| Storage Failure | 4 | `diagnostics.storage_failed` | Empty |
| Decode Failure | 4 | `diagnostics.decode_failed` | Empty |
| Integrity Failure | 4 | `diagnostics.integrity_failed` | Empty |

Human Modeは安全な一行Message、`--json`は次のVersion付きJSONをstderrへ出す。

```json
{"schemaVersion":1,"status":"error","code":"operation.unavailable"}
```

Error DetailはFramework LogへもSafe CodeとOperation IDだけを記録する。

## Development Local Viewer

Canonical Commandは次とする。

```text
php blackops operation:viewer
```

ViewerはFramework内蔵のRead-only Server-rendered Toolであり、Applicationの通常RouteまたはPSR-15 Pipelineへ登録しない。

### Enable and Bind

`config/diagnostics.php`のCanonical Shapeは次とする。

```php
return [
    'viewer' => [
        'enabled' => false,
        'bind' => '127.0.0.1',
        'port' => 8082,
    ],
];
```

- Framework既定は`enabled: false`とする。
- Quickstart Local Configだけが`true`を設定する。
- Command明示実行とEnable Gateの両方を必須にする。
- `127.0.0.1`またはIPv6 Loopbackだけを許可し、Wildcard、LAN Address、Hostname、Unix SocketをPhase 14では拒否する。
- Portは1から65535とし、既定8082を使用する。Bind失敗またはPort競合時はstderrへ安全なErrorを出しExit 1で終了する。
- Background Daemon化せず、Foregroundで実行しSIGINT／SIGTERMで停止する。

### Session Token

- 起動ごとにCryptographically Secureな256 bit以上のRandom Tokenを生成する。
- 起動時にBootstrap URLを一度だけTerminalへ表示する。
- Bootstrap RequestはTokenをConstant-time比較し、成功時にHttpOnly／SameSite=Strict Session Cookieを設定してTokenなしURLへRedirectする。
- Token、Cookie、Operation IDをAccess Logへ出さない。
- TokenをHTML、Error、Referrerへ再出力しない。
- Tokenなし、不一致、期限切れSessionは同じ404 Responseへ畳む。
- Process終了時にTokenとSessionは無効になる。

### HTTP Safety

- GETとHEADだけを許可し、その他Methodは405とする。
- Native ParserはRequest Line 2048 bytes、Request全Header 8192 bytes、Header 32件、Read Timeout 2秒を上限とし、Body、Chunked、Upgrade、Pipeliningを受け付けない。
- Responseへ`Cache-Control: no-store`、`Referrer-Policy: no-referrer`、`X-Content-Type-Options: nosniff`、`X-Frame-Options: DENY`、制限的Content Security Policyを付ける。
- Operation ID一件のLookup、Summary、Availability、Timeline、Attempt、Outcome表示だけを提供する。
- List、全文検索、Raw表示、Retry、Replay、Cancel、Delete、Hold操作、Configuration変更を提供しない。
- Viewer HTMLは`OperationDiagnostics`だけを受け取り、Canonical Store Objectへアクセスしない。
- 可変文字列はHTML ContextでEscapeし、Control Characterは一行のescaped representationへ変換する。
- Unavailableは404相当の同一画面、Storage／Decode／Integrity FailureはDetailなし500相当画面にする。

Viewer Session TokenはApplication User Authenticationではない。Production Route、Remote Support UI、Tenant Accessには再利用しない。

## Framework and Application Responsibilities

| Area | Framework | Application／Infrastructure |
| --- | --- | --- |
| Operation ID correlation | Lifecycle、HTTP Error、PSR-3予約Field | IDをSupport／Alert Workflowで維持 |
| Canonical Data | Reader、Retention連携、Restricted境界 | DB Access Control、At-rest Encryption、Backup |
| Safe Projection | Sensitive／Actor／Failure Filtering | Domain Type／Contextへ秘密値を埋め込まない |
| Terminal | Command、JSON Schema、Exit Code | Local Shell／CI Access Control |
| Viewer | Enable Gate、Loopback、Token、Read-only | Quickstart Localだけ有効化、Productionで有効化しない |
| Production Log | Correlation Decorator、Safe Framework Error | Sink、Delivery、Retention、Alert、Collector |
| Remote Observability | Operation ID Fieldだけ準備 | Phase 18までAdapterなし |

Quickstart Consumerは`diagnostics.failure.trigger`を使い、Safe HTTP 500のOperation IDをHuman／JSON CLI、Local Viewer、Application／Framework JSONLへそのまま渡す。ViewerはPCNTLを持つ明示的なCLI Processで起動し、HTTP Clientと同じLocal Network NamespaceのLoopbackだけを使う。Token／CookieはTemporary Artifactとして回収し、通常Console、Report、Sourceへ残さない。

## Deferred Scope

- Phase 15: Generated Frontend ContractとOperation IDの接続。Diagnostics UIは生成しない。
- Phase 16: Public Status／Outcome PHP／HTTP API、Polling、Authentication／Authorization、Tenant Access Policy。
- Phase 18: Canonical Raw Access Control、Tenant分離、暗号化Capability、特権表示Audit、OpenTelemetry、Metric、Remote Exporter、Health／Readiness。

## Traceability

- Decision: [D097 Phase 14 Operation Diagnostics](../decisions/097-phase-14-operation-diagnostics.md)
- Lifecycle Decision: [D098 Deferred Acceptance Failure Lifecycle](../decisions/098-deferred-acceptance-failure-lifecycle.md)
- Lifecycle: [Lifecycle and Journal](02-lifecycle-and-journal.md)
- Execution: [Execution](03-execution.md)
- HTTP: [HTTP Adapter](05-http.md)
- Logging: [Logging and Traceability](10-logging-and-traceability.md)
- Sensitive Projection: [Sensitive Projection](25-sensitive-projection.md)
- Retention: [Data Retention and Deletion](38-data-retention-and-deletion.md)
- Delivery: [Phase 14 Delivery Plan](66-phase-14-delivery-plan.md)
