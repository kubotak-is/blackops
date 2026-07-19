# D102: Phase 16 Deferred Status and Outcome API

Status: Proposed

## Context

Phase 15で、HTTP OperationからFramework-neutral TypeScript Operation Objectを生成し、`.fetch()`、`.toRequest()`、`.url()`、Readonly Metadataを提供する境界が確立した。Deferred Operationの`.fetch()`はHTTP 202とOperation IDを`accepted` Resultとして返すが、その後のRunning、Retry Scheduled、Completed、Rejected、Failed、Dead LetteredをHTTPから参照するPublic Contractはまだない。

Internal Diagnostics Queryと`operation:inspect`はLifecycle調査に必要な情報を持つが、Local／Operator向けであり、利用者向けStatus APIのPublic DTOではない。Canonical Journal、Payload、Actor ID、Attempt Error、Lease／FencingのRaw情報をそのままHTTPへ出してはならない。

D093はPhase 16をStatus／Outcome HTTP APIとPolling ContractのPhaseとし、Generated Client統合はFrontend Bridge完成後に別途決めるとした。Phase 15は完了したため、ここでOperation ObjectへのStatus／Wait Capabilityも含めて判断する。

## Decision Drivers

- 202 Acceptedと完了を混同させない
- ApplicationがFrameworkのDatabase Table／Journal SchemaをHTTPへ直結しない
- Operation IDの存在、Actor、Tenant、Outcomeを無認可で漏らさない
- Pending／Running／Retry／TerminalをClientが安定したStateで判別できる
- Typed OutcomeとSafe Terminal ErrorをOperationごとに復元できる
- Retention後のExpiredとUnknown／Unauthorizedの情報漏洩境界を明示する
- Pollingが無制限、解除不能、複数Request間のGlobal State保持にならない
- Phase 15のimmutable Operation Objectと呼出単位Optionを維持する

## Question 1: HTTP Resource Shape

Operation IDからStatusとOutcomeをどのResourceで参照するか。

### Options

- A: `GET /operations/{operationId}`の一つのResourceがStateを返し、Terminal時だけOutcomeまたはSafe Errorを含む
- B: `GET /operations/{operationId}/status`と`GET /operations/{operationId}/outcome`を分ける
- C: `/reports/{operationId}`のようにOperationごとのApplication Routeを必須とする

### Recommendation

Aを推奨する。

StatusとTerminal Resultを一つのVersion付きResourceとして読めば、ClientはStatus取得後にOutcome Endpointを追加Requestしなくてよい。Outcome未作成とOutcome保持期限切れもStateで説明できる。Applicationが別の業務URLを提供することは禁止しないが、Framework標準はGeneric Resourceとする。

[ANSWER]

[/ANSWER]

## Question 2: Public State Model

Internal Lifecycleをどの粒度でPublic Stateへ投影するか。

### Options

- A: `accepted`、`running`、`retry_scheduled`、`completed`、`rejected`、`failed`、`dead_lettered`の7 Stateを安定Contractとする
- B: Journal Event名、Claim、Lease、Heartbeat、Fencing、AttemptをそのままPublic Stateとする
- C: `pending`、`succeeded`、`failed`の3 Stateだけに丸める

### Recommendation

Aを推奨する。

`accepted`はDurable受付後かつAttempt前、`running`はActive Attemptあり、`retry_scheduled`は次回実行時刻あり、残りはTerminalとする。`attempt`番号と`retryAt`は必要なStateのみOptionalにする。Claim Token、Lease Owner、Heartbeat、Fencing Token、Raw Journal EventはPublicにしない。

[ANSWER]

[/ANSWER]

## Question 3: HTTP Status, Location, and Polling Hints

Deferred受付ResponseとStatus ResourceのHTTP Contractをどうするか。

### Options

- A: 受付は202と`Location: /operations/{id}`、`Retry-After`を返す。Status Resourceは存在する全Stateで200、ClientはJSONの`state`で継続／停止を判断する
- B: Status ResourceもNon-terminalは202、Terminalは200を返す
- C: Completed時は303でOutcome EndpointへRedirectする

### Recommendation

Aを推奨する。

Status Resource自体の取得は成功しているためHTTP 200とし、業務処理の未完了をHTTP 202へ重ねない。`Retry-After`はServer Hintであり完了予定の保証ではない。`Location`はOperation IDをPath SegmentとしてEncodeした相対URLをCanonicalにする。

[ANSWER]

[/ANSWER]

## Question 4: Authentication and Authorization Boundary

Status／Outcome参照をどのPolicyで許可するか。

### Options

- A: Application必須の専用Query AuthorizerをPublic Portとし、現在Actor、Operation Type、Origin ActorのSafe Reference、Operation IDを入力にAllow／Denyする。未登録はFail-closed
- B: 元Operationの`#[Authorize]`をPersisted OperationValueで再実行する
- C: Framework Endpoint自体は無認証とし、Global Middlewareだけに任せる

### Recommendation

Aを推奨する。

Execution AuthorizationとResult Read Authorizationは異なる。Persisted Raw ValueをPolicyへ再投入せず、Status参照専用のApplication Policyを必須にする。既定はDenyとし、Anonymous公開もApplicationの明示Allowが必要である。Tenant固有APIを先に固定せず、Phase 18でTenant Contextを追加できるPolicy Inputにする。

[ANSWER]

[/ANSWER]

## Question 5: Outcome and Terminal Error Projection

Completed／Rejected／Failed／Dead Letteredで何を返すか。

### Options

- A: `completed`はOperation固有のTyped Outcome、`rejected`はSafe Category／Code、`failed`／`dead_lettered`は安定したPublic Codeだけを同じDiscriminated Resourceへ含む
- B: Terminalはすべて同じ`failed`とし、Outcomeは別StoreのPublic APIで取得する
- C: Exception Class／Message／Stack、Canonical Journal Reason、Dead Letter Payloadを返す

### Recommendation

Aを推奨する。

Phase 15のOutcome Decoderと同じScalar Contractを使い、Operation TypeとOutcome Typeの不一致をFail-closedする。`failed`／`dead_lettered`はException Detailを公開せず、Operation IDでOperator Diagnosticsへ相関する。Sensitive OutcomeはPhase 15と同様にBuild Errorを維持する。

[ANSWER]

[/ANSWER]

## Question 6: Unknown, Unauthorized, and Expired

Operationが見つからない、許可されない、Retention後である場合をどう見せるか。

### Options

- A: UnknownとUnauthorizedは同じ404に丸める。Authorizedで保持期限切れTombstoneが残る場合だけ`expired`を410で返し、TombstoneもPurge済みなら404とする
- B: Unknown、Unauthorized、Expiredを404／403／410で常に区別する
- C: Retention後もOperation IDとTypeを無期限に保存し、常に`expired`を返す

### Recommendation

Aを推奨する。

Unauthorizedに403を返すとOperation IDの存在を推測できる。ExpiredはApplicationが参照を許可し、かつRetention Tombstoneが存在する場合だけ示す。Status APIのために無期限保存を導入しない。

[ANSWER]

[/ANSWER]

## Question 7: Generated Operation Object Integration

Phase 15のOperation ObjectへStatus／Pollingをどこまで追加するか。

### Options

- A: 全Generated Operation Objectへ`.status(operationId, options)`と`.wait(operationId, options)`を追加する。`.fetch()`は202を受け取るだけで自動Pollingしない
- B: `.status()`だけ追加し、Polling LoopはApplicationが実装する
- C: Phase 16はPHP／HTTP APIだけにし、Generated Clientは後続Phaseへ送る

### Recommendation

Aを推奨する。

UserはCallable／ThenableではなくCapability Methodを持つOperation Objectを選んでおり、Status参照はその拡張点に合う。`GenerateReport.status(id, options)`は一回だけ取得し、`GenerateReport.wait(id, options)`はTerminalまでの明示Pollingとする。ResponseのOperation Type IDがObjectのtypeと一致しなければ`unexpected_response`にする。

`.wait()`は呼出単位のAbort Signalと有限の`maxWaitMilliseconds`を必須とし、Serverの`Retry-After`をHintとして尊重する。Global Timer／Credential／Cacheを保持せず、Network Error、401／404／410／5xxを無制限Retryしない。

[ANSWER]

[/ANSWER]

## Proposed Public Resource

Q1～Q7の推奨を採用する場合、概念上のResponseは次のようになる。KeyとOptional境界は後続Specificationで固定する。

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
  "state": "failed",
  "error": {
    "code": "operation_failed"
  }
}
```

## Non-goals

- Cancel／Pause／Resume／Manual Retry Endpoint
- Admin Search／List／Bulk Status／Remote Diagnostics UI
- Canonical Journal／Payload／Exception／Lease／FencingのPublic公開
- Infinite Polling、Automatic Polling付き`.fetch()`、Global Mutable Client
- WebSocket／Server-Sent Events／Webhook Subscription
- Tenant Frameworkの完成、Encryption Adapter、OpenTelemetry
- Documentation WebsiteのPublication／Deploy

## Response

`[ANSWER]`へA／B／Cまたは修正案を記入する。Question 4とQuestion 6はSecurity Contractのため、空欄のままProduction Code実装へ進まない。
