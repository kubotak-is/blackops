# Deferred Status and Outcome API

## Goal

Deferred受付で返したOperation IDから、Applicationが許可した利用者だけが現在のLifecycle StateとTerminal Resultを安全に取得できるPublic PHP／HTTP Contractを提供する。

Phase 16は、Internal DiagnosticsやDatabase Schemaを公開せず、同じContractをGenerated Operation Objectの`.status()`と`.wait()`から利用できる状態までを扱う。

## Scope

対象は次である。

- Public PHP Status Query、Result、State、Error、Query Authorization Contract
- Inline／Deferred OperationのSafe Status Projection
- `GET /operations/{operationId}`のFramework標準Resource
- Deferred受付Responseの`Location`と`Retry-After`
- Generated Operation Objectの`.status()`と`.wait()`
- PostgreSQL Source、Retention、Quickstart、Skeleton、Guide、Consumer E2E

次は対象外とする。

- List、Search、Bulk Status、Admin API
- Cancel、Pause、Resume、Manual Retry
- WebSocket、Server-Sent Events、Webhook
- Canonical Journal、Payload、Context、Exception、Dead Letter Raw Dataの公開
- Tenant Framework、Encryption Adapter、OpenTelemetry
- Frontend Framework Adapter、NPM Publication
- Documentation WebsiteのPublication／Deploy

## Public State Model

Public Stateは次の7種類に固定する。

| State | 意味 | Terminal | 追加Field |
| --- | --- | --- | --- |
| `accepted` | Durable受付済みで、Active Attemptがない | No | なし |
| `running` | Attemptを実行中またはSupervision中 | No | `attempt` |
| `retry_scheduled` | 次のAttemptを待機中 | No | `attempt`、`retryAt` |
| `completed` | Operationが正常完了した | Yes | `outcome` |
| `rejected` | 業務／認証認可／Validationで拒否された | Yes | `error.category`、`error.code` |
| `failed` | RetryせずFailureで終了した | Yes | `error.code=operation_failed` |
| `dead_lettered` | Retry上限後にDead Letterへ移動した | Yes | `error.code=operation_dead_lettered` |

Internal Stateの`supervising`はPublic `running`へ投影する。Claim、Lease、Heartbeat、Fencing Token、Worker ID、Journal Event名をPublic Stateへ追加しない。

`attempt`は1始まりの正整数とする。`retryAt`は共通Time Codecによるマイクロ秒付きRFC 3339 UTC文字列とする。Stateごとに許可されていないFieldを持つResourceは不正である。

## Public PHP Query Contract

Public型は`BlackOps\Status` Namespaceへ置き、すべて`#[PublicApi]`を持つ。

```text
OperationStatusQuery
  find(OperationId, currentActor|null): OperationStatusResult

OperationStatusResult
  OperationStatusFound
    status: OperationStatus
  OperationStatusUnavailable
  OperationStatusExpired

OperationStatus
  operationId: OperationId
  operationType: string
  state: OperationStatusState
  attempt: int|null
  retryAt: DateTimeImmutable|null
  outcome: Outcome|null
  error: OperationStatusError|null

OperationStatusError
  category: string|null
  code: string
```

`OperationStatus`は不変Objectとし、Constructor／Named ConstructorでState別Invariantを強制する。Completed以外はOutcomeを持たず、Rejected以外はCategoryを持たない。Failed／Dead LetteredのCodeは上表の固定値だけを許可する。

Completed OutcomeはOperation固有の`Outcome` Objectである。型なし配列、Raw JSON、`mixed`へ変換しない。値のない完了は既存`EmptyOutcome`で表す。

Query失敗はSafeなPublic Exceptionで表し、少なくとも次の安定Codeを持つ。

```text
status_query.authorization_failed
status_query.storage_failed
status_query.decode_failed
status_query.integrity_failed
```

Exception MessageへSQL、Table名、Connection Parameter、Payload、Context、Actor ID、Exception Detailを含めない。Unknown／Unauthorized／ExpiredはExceptionではなく`OperationStatusResult`である。

## Query Authorization

Status参照はOperation実行時の`#[Authorize]`と別のPublic Portで評価する。

```text
OperationStatusAuthorizer
  decide(OperationStatusAuthorizationRequest): OperationStatusAuthorizationDecision

OperationStatusAuthorizationRequest
  operationId: OperationId
  operationType: string
  currentActor: ActorRef|null
  originActor: ActorRef|null

OperationStatusAuthorizationDecision
  allow
  deny
```

Frameworkは既定のDeny Authorizerを登録する。Applicationが明示的に置き換えない限り、認証済みでもAnonymousでもStatusは取得できない。Execution AuthorizationをPersisted OperationValueで再実行しない。

Queryは次の順序を守る。

1. Restricted Sourceから認可に必要な最小Subjectだけを読む
2. Subjectがなければ`OperationStatusUnavailable`を返す
3. 現在Actor、Operation Type、Origin ActorのSafe Reference、Operation IDで専用Authorizerを評価する
4. 未登録、Deny、または認可対象を安全に構成できない場合は`OperationStatusUnavailable`を返す
5. Allow後だけStatus、Outcome、Rejected Error、Retention Evidenceを読む
6. Allow後にTombstoneで保持期限切れを証明できる場合だけ`OperationStatusExpired`を返す

Origin ActorがRetentionにより失われた場合は`null`を渡し、架空のActorや現在Actorで補わない。Applicationは`null`を含むRequestをAllowするかDenyするか明示できる。

AuthorizerがThrowableを投げた場合はDenyへ丸めず、`status_query.authorization_failed`として安全に失敗させる。認可障害をUnknownに偽装して運用障害を隠さない。

## Source Authority and Projection

Internal Diagnostics AggregateをPublic DTOとして再利用しない。Status Query専用の最小Source PortとSafe Projectorを持ち、認可前のSubject読取と認可後のDetail読取を型レベルで分離する。

```text
findSubject(OperationId): OperationStatusSubject|null
  operationId
  operationType
  originActor|null

readDetail(OperationStatusSubject): OperationStatusDetailResult
  OperationStatusDetail(status)
  OperationStatusDetailExpired
```

Subjectは認可入力だけを保持し、Expired Flag、Retention Evidence、State、Outcome、Terminal Errorを持たない。Expired判定を含むDetail ResultはAuthorizerのAllow後だけ取得する。PostgreSQL Subject ReaderはCanonical `encoded_record`、Encoded Payload／Context全体をPHPへ返さず、SQL ProjectionでOperation TypeとOrigin Actorだけを取得する。

### Inline

- Identity、Current State、Terminal Error、Outcomeの正本はSequence順Canonical Journalとする
- Lifecycle State Machineで先頭からStateを再構築する
- Completed Outcomeは`operation.completed`のCanonical Outcomeを型付きでDecodeする
- Journalが利用可能なのに不正な遷移、Operation Type不一致、Outcome Type不一致がある場合はIntegrity Failureとする
- JournalがRetention削除され、認可用SubjectとTombstoneを構成できる場合だけExpiredになり、それ以外はUnavailableになる

### Deferred

- Current State、Operation Type、Schema Version、Current Attempt、Retry時刻の正本はOperations Rowとする
- `operation.accepted`前にJournalだけで終端したRejected／FailedはCanonical Journalを正本とする
- `operation.accepted`後にOperations Rowが欠落した場合はJournal-onlyへFallbackせずIntegrity Failureとする
- Completed Outcomeの正本はOutcome Storeとする。Retention削除後にCompleted Journal OutcomeへFallbackしない
- Rejected Category／Codeの正本はCanonical Journalとし、Raw Violation、Raw Value、Exception Detailを投影しない
- Dead LetterはState確認に利用できるが、Payload、Reason Message、Worker MetadataをSELECT／Decode／返却しない
- Operations RowとJournalの両方が利用可能な場合、State／Typeの不一致はIntegrity Failureとする

Canonical JournalのOperation Identity検証ではRecord Schema Version、Operation ID、Type、Operation Schema Version、Strategy、Correlation／Causation、origin Actor、authorization Actorを全Recordで一致させる。execution ActorはRecord生成主体であり、HTTP受付からWorkerへの移行、Retry、Lease Recoveryで変化できるため同一性を要求しない。Sequence、Lifecycle、Attempt、Retry参照の整合性は引き続き検証する。

DeferredのOperations Rowが残りJournalだけがRetention削除された場合、Origin Actorは`null`になる。Operations RowのEncoded ContextへFallbackしてActorを復元しない。Application AuthorizerはOrigin Actorなしの参照を明示的にAllowまたはDenyする。

Public `running`はInternal `running`と`supervising`を含む。Public `attempt`はCurrent Attempt Numberを使い、Attempt IDを返さない。

## Retention and Availability

Status APIのためにOperation ID、Operation Type、Actor、Outcome、Tombstoneを無期限保存しない。既存Retention PolicyとLegal Holdを正本とする。

| 条件 | Public PHP | HTTP |
| --- | --- | --- |
| ID形式不正、Unknown、Subject完全Purge | Unavailable | 404 |
| Authorizer未登録／Deny | Unavailable | 404 |
| Allow後にRetention Tombstoneで期限切れを証明可能 | Expired | 410 |
| Allow後にCompleted Outcomeが理由なく欠落 | Integrity Failure | 500 |
| Storage／Decode／Source不整合 | Safe Query Exception | 500 |

UnknownとUnauthorizedはResponse Status、Header、Body、Timing対策可能なQuery Pathを同一にする。403を返さない。ExpiredはSubjectを認可でき、かつ削除Evidenceが残る場合だけ公開する。Tombstoneも完全Purge済みなら404へ戻る。

## HTTP Resource

Framework標準Resourceは次である。

```text
GET /operations/{operationId}
```

このPathはFramework予約Routeであり、Application RouteとのCollisionはBuild Errorにする。Global PSR-15 MiddlewareとHttpAuthenticatorを通常どおり通過した後、Status Query Authorizerを評価する。Invalid CredentialはOperation IDの存在確認前に既存401を返してよい。

見つかったすべてのStateはHTTP 200を返す。Non-terminal Stateは正整数秒の`Retry-After`を付ける。すべてのStatus Responseへ`Cache-Control: private, no-store`を付ける。

```json
{
  "schemaVersion": 1,
  "operationId": "019f32ab-2be0-7b38-a0a7-1ab2f9687697",
  "operationType": "report.generate",
  "state": "retry_scheduled",
  "attempt": 1,
  "retryAt": "2026-07-19T09:30:00.000000Z"
}
```

```json
{
  "schemaVersion": 1,
  "operationId": "019f32ab-2be0-7b38-a0a7-1ab2f9687697",
  "operationType": "report.generate",
  "state": "completed",
  "outcome": {
    "reportId": "report-1042"
  }
}
```

```json
{
  "schemaVersion": 1,
  "operationId": "019f32ab-2be0-7b38-a0a7-1ab2f9687697",
  "operationType": "report.generate",
  "state": "rejected",
  "error": {
    "category": "validation",
    "code": "validation_failed"
  }
}
```

```json
{
  "schemaVersion": 1,
  "operationId": "019f32ab-2be0-7b38-a0a7-1ab2f9687697",
  "operationType": "report.generate",
  "state": "failed",
  "error": {
    "code": "operation_failed"
  }
}
```

404は`operation_unavailable`、410は`operation_expired`、Query内部障害は`internal_error`だけを返す。Raw Source DetailとAuthorizerのDeny理由を返さない。

Deferred受付成功Responseは既存202 Bodyを維持し、次を追加する。

```text
Location: /operations/{operationId}
Retry-After: <positive integer seconds>
Cache-Control: private, no-store
```

`Location`は相対URLとし、Operation IDの正規文字列表現をPath Segmentに使う。`Retry-After`は次回QueryのHintであり、完了予定の保証ではない。

## Generated Operation Object

全HTTP Operation Objectへ次を追加する。

```text
.status(operationId, options)
.wait(operationId, options)
```

`.fetch()`はDeferred 202を`accepted`として返すだけで、自動Pollingしない。

### status

`.status()`は一回だけ`GET /operations/{operationId}`を実行する。既存の呼出単位`baseUrl`、Headers、Credentials、Fetch、Abort Signal境界を再利用し、Global Clientを導入しない。

- Operation IDをUUIDv7の正規形式として検証する
- Responseの`schemaVersion`、State別Field、Scalar、Outcomeを厳密Decodeする
- Responseの`operationType`がOperation ObjectのReadonly Typeと一致しなければ`unexpected_response`にする
- Completed OutcomeはPhase 15と同じOperation固有型へDecodeする
- Terminal Failed／Rejected／Dead LetteredはQuery成功のStateであり、Transport Errorにしない
- 401、404、410、5xx、Network Error、Malformed Responseを区別可能なResult Unionへする
- Raw Error Body、Credential、Sensitive値をResultへ含めない

### wait

`.wait()`はTerminal Stateまで明示的にPollingする。次を必須Optionとする。

- 解除通知を購読できる呼出単位Abort Signal
- 有限かつ正の`maxWaitMilliseconds`

動作は次に固定する。

1. `.status()`と同じQueryを直ちに一回実行する
2. TerminalならそのResultを返す
3. Non-terminal 200ならServerの正整数`Retry-After`を上限時間内で尊重する
4. Abort時は待機と未完了Requestを解除し、安全な`aborted` Resultを返す
5. 上限到達時は安全な`poll_timeout` Resultを返す
6. 401、404、410、5xx、Network Error、Malformed Responseは自動Retryせず直ちに返す

Timer、Clock、FetchはTestで注入可能な構造型とし、DOM型へのCompile依存を追加しない。Default Runtimeを使う場合もCredential、Cache、Timer ID、Polling StateをModule Globalへ保持しない。複数の`.wait()`は完全に独立する。

## Security Boundary

| Frameworkが保証する | Applicationの責務 |
| --- | --- |
| Query Authorizerの既定Deny | Allow／Deny PolicyとTenant条件 |
| Unknown／Unauthorizedの同一404 | HttpAuthenticatorとCredential発行 |
| Allow後だけDetail／Outcomeを読む二段階Query | どのActorがどのOperation Typeを読めるか |
| Raw Journal／Payload／Exception／Leaseを返さない | Outcome Property自体の業務上の公開可否 |
| `private, no-store`とSafe Error | Reverse Proxy／GatewayのAccess Control |
| Retention Evidenceがある場合だけ410 | Retention期間、Legal Hold、削除運用 |

Actor ID、Origin Actor、Authorization Actor、Execution Actor、Correlation ID、Causation ID、Attempt IDはPublic Resourceへ返さない。Operation IDはOperator Diagnosticsとの相関Keyだが、HTTP Status ResourceからInternal DiagnosticsへのLinkやRaw Detailを公開しない。

## Compatibility

- Stable 1.1の公開Surfaceは変更しない
- Phase 16のPublic API／JSON／Generated APIは`main`のExperimental Surfaceとして導入する
- Resource Schemaは`schemaVersion: 1`から開始する
- Future Tenant Context追加はQuery Authorization Requestの互換性方針を別Decisionで扱う
- Documentation Website Sourceは同期できるが、Publication／Deployは実行しない

## Acceptance Criteria

- [x] Public PHP Queryが7 State、Typed Outcome、Safe Error、Unavailable、Expiredを表現できる
- [x] 未登録／Deny AuthorizerがFail-closedになり、認可後だけDetailを読む
- [x] Inline／DeferredのSource Authorityと不整合FailureがTestで固定される
- [x] `GET /operations/{operationId}`が200／404／410／500とSafe Header／Bodyを実装する
- [x] Deferred 202が`Location`／`Retry-After`を返す
- [x] Generated `.status()`がOperation TypeとTyped Outcomeを検証する
- [x] Generated `.wait()`がAbort可能、有限、Retry-After準拠で、任意Errorを無制限Retryしない
- [x] Quickstart／Skeleton／Framework Update／Real HTTP Consumer E2Eが同期する
- [x] Public ResourceとGenerated ResultへSensitive／Canonical／Credential／Raw Errorが露出しない
- [x] Full PHP／Frontend／Consumer／Website Quality Gateが成功する

## Traceability

- Decision: [D102 Phase 16 Deferred Status and Outcome API](../decisions/102-phase-16-deferred-status-and-outcome-api.md)
- Lifecycle: [Lifecycle State Machine](30-lifecycle-state-machine.md)
- HTTP: [HTTP Adapter](05-http.md)
- Authorization: [Authentication and HTTP Middleware](06-auth-and-middleware.md)
- Retention: [Data Retention and Deletion](38-data-retention-and-deletion.md)
- Diagnostics Boundary: [Operation Diagnostics](65-operation-diagnostics.md)
- Frontend Contract: [Operation Frontend Bridge](67-operation-frontend-bridge.md)
- Roadmap: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)
