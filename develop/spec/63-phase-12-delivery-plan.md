# Phase 12 Delivery Plan

## Goal

Stable `1.1.0`のOperation Authoringを維持したまま、PSR-15 HTTP Middleware、Credentialを永続化しないAuthentication、Durable ActorContext、`#[Authorize]`、Inline／Deferred再認可をFramework Runtimeへ追加する。

Operation Middlewareは導入せず、AuthorizationをFramework固定のOperation Lifecycle Stageとして実装する。

## P12-001: Middleware and Authorization Design

- D010／Spec 06とCurrent Runtimeを監査する
- Operation Middlewareを採用しないPhase Scopeを確定する
- Authentication責任境界、Durable Actor、Deferred Failure分類をD095で決定する

## P12-002: Actor Context Foundation

- Public `ActorRef`／`ActorContext`とExecutionContext getterを追加する
- Root受信、Attempt開始、子Operation生成でActorを安全に伝播する
- Execution Context CodecへID／Typeだけを追加し、既存PayloadをDecode可能に保つ
- Credential／Role／Permission／ClaimをEncodeしないことをTestで固定する

## P12-003: HTTP Middleware and Authentication

- `config/middleware.php`のGlobal PSR-15 PipelineをApplication Build／Runtimeへ接続する
- MiddlewareをCompiled Containerから解決し、登録順と玉ねぎResponse加工を検証する
- `HttpAuthenticator`、`AuthenticationResult`、Framework `AuthenticationMiddleware`を実装する
- Anonymous／Authenticated／Invalid／Authenticator FailureのHTTP境界を固定する
- Skeleton ConfigとApplication Bootstrapを同期する

## P12-004: Authorization Metadata and Inline Runtime

- `#[Authorize]`、`AuthorizationPolicy`、Request／Decision型を追加する
- Discovery／Manifest／Compiled ContainerへPolicy MetadataとDI登録を追加する
- HTTP ActorをExecutionContextへ渡し、Inline HandlerまたはDeferred配送前にPolicyを評価する
- Unauthorized／ForbiddenをOperation ID付きRejected JournalとHTTP 401／403へ変換する
- Policy Backend例外をSecurity Denialへ丸めない

## P12-005: Deferred Reauthorization and Actor Journal

- Actor ID／TypeをExecution Transport ContextとCanonical Journalへ保存する
- Observer ProjectionでActor IDを既定Maskする
- Worker Attempt開始時にexecution ActorだけをSystem Actorへ置き換える
- Handler実行前に最新状態で同じPolicyを再評価する
- Actor不在／無効とForbiddenをTerminal Rejected、Backend障害をSupervision対象のAttempt Failureにする
- Retry／Backoff／Dead LetterでActor ContextとFailure分類が維持されることをIntegration Testで検証する

## P12-006: Consumer Experience and Closeout

- Quickstartへ認証済みInline／Deferred Operationの動作例を追加する
- Framework Guide、Reference、Security、TroubleshootingをPublic APIとConfigへ同期する
- Framework UpdateとSkeleton Create-project Consumer Testを更新する
- Full PHP／Consumer／Website Quality Gateを実行する
- TODO、Report、STATEを同期してPhase 12をCloseする

Documentation WebsiteのCloudflare公開は実行せず、Repository内Source、Build、Search、Artifact Guardだけを維持する。

## Dependency Order

```text
P12-001 Middleware and Authorization Design
  -> P12-002 Actor Context Foundation
    -> P12-003 HTTP Middleware and Authentication
      -> P12-004 Authorization Metadata and Inline Runtime
        -> P12-005 Deferred Reauthorization and Actor Journal
          -> P12-006 Consumer Experience and Closeout
```

## Phase Acceptance Criteria

- [x] Operation Middlewareを追加しないPhase ScopeがDecisionとSpecificationに一致する
- [x] ApplicationがGlobal PSR-15 MiddlewareをConfig順に実行でき、Responseも加工できる
- [x] CredentialがOperationValue、ExecutionContext、Journal、Log、Transportへ保存されない
- [x] ActorContextがorigin／authorization／executionを区別し、Durable DataはID／Typeだけである
- [x] `#[Authorize]`がInlineとDeferred受付で同じPolicyを評価する
- [x] Deferred Workerが最新状態で再認可し、execution ActorだけをSystem Actorへ置き換える
- [x] Unauthorized／ForbiddenがJournal付き401／403、Backend障害がSupervision対象Failureになる
- [x] Skeleton、Quickstart、Guide、Consumer E2EがPublic Contractを再現する
- [x] Full PHP／Consumer／Website Quality Gateが成功する

## Traceability

- Decision: [D095 Phase 12 Middleware and Authorization Runtime](../decisions/095-phase-12-middleware-and-authorization-runtime.md)
- Runtime Contract: [Authentication and HTTP Middleware](06-auth-and-middleware.md)
- Context Contract: [ExecutionContext API](19-execution-context-api.md)
- Roadmap: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)
