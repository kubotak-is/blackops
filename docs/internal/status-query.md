# Status Query Contract

Public Status Queryは、Operation IDから現在のLifecycle StateとTerminal Resultを安全に参照するためのPHP Contractである。Database、HTTP、Frontendから独立しており、Adapterは`BlackOps\Internal\Status\OperationStatusSource`を実装する。

## Public API

`BlackOps\Status\OperationStatusQuery`は次のResultのいずれかを返す。

| Result | 意味 |
| --- | --- |
| `OperationStatusFound` | 認可済みでStatusを取得できた |
| `OperationStatusUnavailable` | Unknown、Subject完全Purge、またはDeny |
| `OperationStatusExpired` | 認可済みでRetentionによる期限切れを証明できた |

`OperationStatus`はNamed Constructorでのみ生成できる。許可されるFieldはStateごとに固定される。

| State | `attempt` | `retryAt` | `outcome` | `error` |
| --- | --- | --- | --- | --- |
| `accepted` | - | - | - | - |
| `running` | Required | - | - | - |
| `retry_scheduled` | Required | Required | - | - |
| `completed` | - | - | Required | - |
| `rejected` | - | - | - | Safe category／code |
| `failed` | - | - | - | `operation_failed` |
| `dead_lettered` | - | - | - | `operation_dead_lettered` |

Attemptは1始まりで、`retryAt`はObject生成時にUTCへ正規化する。値のないCompletedは`EmptyOutcome`を使う。

## Authorization Boundary

Status参照はOperation実行時のAuthorizationとは別である。Applicationは`OperationStatusAuthorizer`を実装し、`OperationStatusAuthorizationRequest`のOperation ID、Operation Type、Current Actor、Origin ActorだけでAllow／Denyを判断する。

Frameworkの`DenyOperationStatusAuthorizer`は常にDenyする。Applicationが明示的に置き換えない限り、Status Detailは読まれない。

```text
Operation ID
  -> findSubject()
    -> not found: Unavailable
    -> Subject
      -> Authorizer
        -> Deny: Unavailable
        -> Allow
          -> readDetail()
            -> retained detail: Found
            -> proven retention: Expired
```

Subject DTOは認可に必要な最小情報だけを持ち、Outcome、Terminal Error、Payload、Context、Journalを持てない。Detail SourceはAllow後にだけ呼ばれる。Journalが存在するDetailでは、SubjectとJournalのOperation ID、Operation Type、Origin Actorのnull性・ID・Typeを厳密に照合し、不一致をIntegrity Failureにする。

Origin Actorを復元できない場合は`null`を渡す。Current Actorで補完してはならない。

## Safe Failure

Source Adapterは失敗を`OperationStatusSourceException`へ分類する。Default QueryはPublic `OperationStatusQueryException`へ次のように正規化する。

| Failure | Public code |
| --- | --- |
| Authorizer Throwable | `status_query.authorization_failed` |
| Storage failure、分類不能なSource Throwable | `status_query.storage_failed` |
| Decode failure | `status_query.decode_failed` |
| Subject／Detail不整合、Source integrity failure | `status_query.integrity_failed` |

Public ExceptionのMessageは安定Codeだけであり、元Exception、SQL、Credential、Actor ID、Payloadを含めない。Unknown、Deny、ExpiredはExceptionではなくResultとして返す。

## PostgreSQL Adapter

`PostgreSqlOperationStatusSource`は認可前のSubject読取と認可後のDetail読取を分離する。Subject QueryがPHPへ返す列は次だけである。

| Source | Projected fields |
| --- | --- |
| Operations Row | Operation ID、Operation Type |
| Canonical Journalの先頭Record | Operation ID、Operation Type、Origin Actor ID／Type |

Journal QueryはPostgreSQLのJSON Pathで必要な値だけを抽出する。`encoded_record`全体、Journal Data、Transport Payload、Encoded Context、State、Outcome、Purge AuditはSubject Resultへ含めない。Operations Rowだけが残る場合、Origin Actorは`null`である。Operations RowとJournalのType不一致やOrigin Actorの片側欠落はIntegrity Failureになる。

認可後にJournalのOrigin Actorが変化しても、認可時のSubjectとDetail Journalの照合で拒否する。Journal Retention済みでSubjectがOperations Rowだけから構成される場合は、SubjectとDetailの双方でActor不明のままとし、別Actorを推測しない。

DetailはSubjectが認可された後、同じDBAL Connection上の`REPEATABLE READ, READ ONLY`トランザクションで読む。AdapterはIsolation LevelとRead Onlyを検証し、既存の制御外Transactionには相乗りしない。成功時はCommit、失敗時はRollbackし、ConnectionへTransactionを残さない。これにより、Operations Row、Journal、Outcome、Dead Letter、Retention Auditの途中更新を混ぜたStatusを返さない。

## Source Authority

| Operation | State authority | Terminal detail authority |
| --- | --- | --- |
| Inline | Canonical Journal | Completed Outcome、Rejected Safe Reason、Failed Event |
| Deferred | Operations Row | CompletedはOutcome Store、RejectedはJournal、Failedは固定Code、Dead LetteredはRow／Purge Evidence |
| Deferred受付前Terminal | Canonical Journal | Rejected Safe ReasonまたはFailed Event |

DeferredではOperations RowのType、Schema Version、State、Next Sequence、Attempt、Retry時刻をJournalと照合する。Internal `supervising`はPublic `running`へ投影する。Public Stateは次のようになる。

| Internal state | Public state | Attempt／retryAt |
| --- | --- | --- |
| `accepted` | `accepted` | Attempt 0を内部検証、Public Fieldなし |
| `running` | `running` | Attempt 1以上 |
| `supervising` | `running` | Attempt 1以上 |
| `retry_scheduled` | `retry_scheduled` | Attempt 1以上、Operations Rowの`available_at`をUTCで返す |
| `completed` | `completed` | Registryの期待型と一致するTyped Outcome |
| `rejected` | `rejected` | Safe Category／Codeだけ |
| `failed` | `failed` | 固定`operation_failed` |
| `dead_lettered` | `dead_lettered` | 固定`operation_dead_lettered` |

Canonical JournalはSequence 1始まりの連番、Lifecycle遷移、Operation Identity、Attempt ID／Number／Started At、Retry Dataを検証する。検証できない欠落や矛盾を推測でPublic Stateへ丸めない。

## Retention Boundary

| Situation | Result |
| --- | --- |
| Allow後、Completed OutcomeがなくOutcome Purge Auditあり | Expired |
| Allow後、Rejected JournalがなくJournal Purge Auditあり | Expired |
| Failed JournalがPurge済み | Found、固定Failed Code |
| Dead Letter Journal／RowがPurge済みでAudit整合 | Found、固定Dead Letter Code |
| Transport PayloadだけPurge済み | Statusを継続して投影 |
| Evidenceなしの欠落、RowとPurge Auditの併存 | Integrity Failure |
| Subject Identityも完全にPurge済み | Unavailable |

Unknown／DenyではPurge Auditを読まないため、存在やRetention状態を推測できない。Dead LetterのReason Message、Raw Payload、Attempt ID、`purged_by`もPublic StatusまたはSafe Exceptionへ出さない。

## HTTP Resource

Application RuntimeはPublic QueryをFramework予約Routeへ接続する。

```text
GET /operations/{operationId}
```

Status HandlerはOperation Handlerと同じFramework Routerの内側にあり、Global PSR-15 MiddlewareとAuthenticationを先に通る。Invalid CredentialはStatus Subjectを読む前に既存401を返す。Authenticated Actorは`currentActor`としてQueryへ渡し、Anonymous Requestは`null`を渡す。

Status GETに空でないRequest Bodyがある場合は、既存HTTP Protocol境界の400をStatus Queryより先に返す。Application Routeは従来どおりRoute未一致の404をBody検査より優先する。

Compiled Containerに`OperationStatusAuthorizer` BindingがあればApplication実装を使用し、なければ`DenyOperationStatusAuthorizer`へFail-closedする。取得したServiceがContractを満たさない場合はRuntime Compositionを安全に失敗させる。FrameworkはApplication Bindingを上書きしない。

| Query／Request | HTTP | Body |
| --- | --- | --- |
| Found | 200 | Schema Version 1のState Resource |
| Invalid ID／Unknown／Deny | 404 | `{"status":"error","code":"operation_unavailable"}` |
| Allow後のExpired | 410 | `{"status":"error","code":"operation_expired"}` |
| Query／Encode Failure | 500 | `{"status":"error","code":"internal_error"}` |

200／404／410／500は`Content-Type: application/json`と`Cache-Control: private, no-store`を持つ。`accepted`、`running`、`retry_scheduled`の200だけ`Retry-After: 1`を持つ。Terminal ResponseとError ResponseにPolling Hintを付けない。

Found Bodyの共通Fieldは`schemaVersion`、`operationId`、`operationType`、`state`である。State別に`attempt`、UTC Microsecondsの`retryAt`、Public Propertyだけの`outcome`、Safe `error`を追加する。`EmptyOutcome`は`{}`になる。Actor、Attempt ID、Correlation／Causation ID、Journal、Exceptionを追加しない。

Deferred受付202は既存Bodyを変えず、次のHeaderを付ける。

```text
Location: /operations/{operationId}
Retry-After: 1
Cache-Control: private, no-store
```

Polling Hintは202とStatus Resourceで同じ内部定数を使用する。Classic EntrypointとFrankenPHP Worker Modeはどちらも`Application::http()`が構成した同一Handler Graphを使用し、Entrypoint固有のStatus分岐を持たない。

ApplicationのGET Routeが`/operations/{operationId}`と同じ二Segmentを使用する場合、Build時に予約Route Collisionとして拒否する。Parameter名が異なるDynamic RouteとStatic Segmentも同様である。GET以外またはSegment数が異なるRouteは既存規則を維持する。

## Generated TypeScript Client

全HTTP OperationのGenerated Objectは、Operationを実行する`.fetch()`とは独立した`.status(operationId, options)`を持つ。このMethodはCanonical lowercase UUIDv7を送信前に検証し、一回だけStatus ResourceをGETする。ValueやOperation RouteのBindingは使わず、呼出単位の`baseUrl`、Headers、Credentials、Signal、Injected Fetchだけを再利用する。

Generated Resultは`accepted`、`running`、`retry_scheduled`、`completed`、`rejected`、`failed`、`dead_lettered`をLiteralでNarrowingできる。CompletedはOperation固有のScalar／Nullable OutcomeへDecodeし、`EmptyOutcome`のWire `{}`は`undefined`へ変換する。Rejected、Failed、Dead LetteredはStatus Query成功なので`ok: true`であり、401、404、410、500とTransport Failureは`ok: false`で区別する。

DecoderはSchema Version、Requested Operation ID、Generated Operation Type、State別のExact Key、Attempt、UTC Microseconds、Safe Error Codeを検査する。Non-terminal StateだけCanonicalな正整数`Retry-After`を要求し、`retryAfterSeconds`として返す。Terminal Stateに同Headerがある場合やContract不一致は`unexpected_response`になる。Raw Body、Credential、Exception DetailはResultへ保持しない。

`.status()`はPolling、Retry、Timer、Cacheを実装しない。`.fetch()`もDeferred 202の後にStatusを自動取得しない。

`.wait(operationId, options)`はTerminal Stateまでの明示的な有限待機を提供する。購読可能なStructural Abort Signalと正のSafe Integer `maxWaitMilliseconds`は必須である。各Status RequestはAbortと固定DeadlineのTimerに競合し、Non-terminal ResponseだけがStrict Decode済み`Retry-After`に従って次のQueryへ進む。Terminal 4 State、401／404／410／500、Transport Failureは即時返却し、Wait固有Result型からNon-terminal Stateを除外する。

Clock、Timer、Fetchは呼出単位のStructural Typeとして注入でき、DOM／Node型へ依存しない。Pre-abort、in-flight Abort、sleep中Abort、Timeout、成功、Errorの全経路でInvocation所有TimerをClearしListenerをRemoveする。一つのWaitのSignal、Deadline、Timer、Fetchは別のWaitと共有されず、`poll_timeout`／`invalid_wait_options`／Abort Reason／Elapsed／Deadline／Raw Errorを詳細情報として露出しない。

## Adapter Rules

- `findSubject()`は認可前に呼ばれるため、最小Subject以外をSELECT／Decodeしない
- `readDetail()`は渡されたSubjectに対応するStatusだけを返す
- Retention Evidenceがない欠落をExpiredとして推測しない
- Database固有のExceptionやDTOをPublic Signatureへ露出しない
- Internal Diagnostics AggregateをPublic Statusへ直接返さない
