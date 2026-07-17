# P12-004B: Actor Propagation and Authorization Runtime

Status: Accepted

## Goal

D095／Spec 06／Phase 12 Delivery Planに従い、HTTP Authenticationで得たActorをOperation ExecutionContextへ接続し、`#[Authorize]` PolicyをInline HandlerまたはDeferred配送前の固定Lifecycle Stageとして評価する。拒否はOperation ID付きJournal／HTTP 401／403へ変換し、Policy Backend例外はSecurity Denialへ丸めない。

## In Scope

- HTTP Request予約AttributeからAuthenticated Actorを取得する境界
- Authenticated HTTP Actorからorigin／authorization／executionを持つActorContext生成
- Dispatcher／Deferred AcceptorへのOptional ActorContext伝播
- Compiled ContainerからのAuthorization Policy解決
- Policyなし／Actorなし／Allow／Unauthorized／Forbiddenを扱うAuthorization Evaluator
- Inline Received／Attempt Started後、Handler前のPolicy評価
- Deferred Received後、Transport Enqueue前のPolicy評価
- Authorization拒否のCanonical `operation.rejected`記録
- Authorization拒否のOperation ID付きHTTP 401／403 JSON
- Authorization Allow時だけHandler／Deferred Transportへ進む保証
- Policy Backend／Policy構築例外をDenialへ丸めない保証
- Existing OperationResult rejectionへOperation IDを関連付けられる後方互換拡張
- Core API／Security Guideの最小同期

## Out of Scope

- Deferred Worker実行時の再認可
- Worker execution ActorのSystem Actor置換
- Canonical Journal Actor Field／Observer Mask
- Application固有Policy／Actor Repository実装
- Role／Permission／Credential／Claimの保存
- Operation Middleware
- Console Operation入口
- Documentation Website全体とQuickstart Example

## Relevant Specifications and Decisions

- `develop/decisions/095-phase-12-middleware-and-authorization-runtime.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/63-phase-12-delivery-plan.md`

## Files Allowed to Change

- `src/Core/OperationResult.php`
- `src/Execution/Dispatcher.php`
- `src/Http/DeferredOperationAcceptor.php`
- `src/Http/OperationRequestHandler.php`
- `src/Http/Responder/JsonOperationResponder.php`
- `src/Internal/Authorization/AuthorizationEvaluator.php`
- `src/Internal/Authorization/AuthorizationPolicyResolver.php`
- `src/Internal/Execution/InlineDispatcher.php`
- `src/Internal/Execution/DeferredAcceptanceOrchestrator.php`
- `src/Internal/Http/DeferredHttpOperationAcceptor.php`
- `src/Internal/Application/ApplicationHttpRuntimeComposer.php`
- `src/Internal/Runtime/ProductionRuntimeComposer.php`
- `tests/Core/OperationResultTest.php`
- `tests/Http/OperationRequestHandlerTest.php`
- `tests/Http/DeferredOperationRequestHandlerTest.php`
- `tests/Http/HttpValidationLifecycleTest.php`
- `tests/Internal/Authorization/AuthorizationEvaluatorTest.php`
- `tests/Internal/Execution/InlineDispatcherTest.php`
- `tests/Internal/Http/DeferredHttpOperationAcceptorTest.php`
- `tests/Internal/Runtime/ProductionRuntimeComposerTest.php`
- `tests/Transport/PostgreSql/PostgreSqlDeferredAcceptanceOrchestratorTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/MvpSampleEndToEndTest.php`
- `docs/guide/core-api.md`
- `docs/guide/security.md`
- `develop/orchestration/reports/P12-004B-actor-propagation-and-authorization-runtime.md`
- `develop/STATE.md`

## Implementation Constraints

- Public `Dispatcher::dispatch()`と`DeferredOperationAcceptor::accept()`は末尾Optional `?ActorContext`を受け、既存2引数Call Siteを維持する
- HTTP予約AttributeはP12-003と同じ`ActorRef::class` Keyを使用し、ActorRef以外の値をActorとして扱わない
- Authenticated HTTP Requestでは同じActorRefをorigin／authorization／executionへ設定する。Anonymous RequestはActorContextなしで既存経路を維持する
- Credential、Authorization Header、Token、Session、Role、Permission、ClaimをActorContextやPolicy Requestへコピーしない
- Policy ResolverはOperation MetadataのPolicy ClassをCompiled Containerから取得し、`AuthorizationPolicy`型をFail-fast検証する
- PolicyなしOperationはActor有無に関係なくEvaluatorを通過する
- Policy付きOperationでauthorization Actorがない場合、Policyを呼ばず`authorization.authentication_required`のUnauthorizedとする
- Policyが返したUnauthorized／Forbiddenは同じStable Codeの`RejectionReason`へ変換する。Allowだけが次段へ進む
- Container／Policyの予期外例外はUnauthorized／Forbiddenへ変換せず、そのままRuntime Error境界へ渡す
- InlineはReceived、Attempt Startedを記録した後、Handler解決／実行前に評価する。拒否時はOperation Rejectedを次Sequenceで記録し、Handlerを呼ばない
- Deferred受付はOperation IDとContextを生成し、Receivedを記録した後、Transport Enqueue前に評価する。拒否時はOperation Rejectedを次Sequenceで記録し、Accepted記録／Enqueueを行わない
- Deferred受付のReceived／Policy／RejectedまたはEnqueue／Acceptedは既存DB Transaction境界とSequence Invariantを壊さない
- Authorization拒否をHTTPへ戻すためのResultは既存Public APIを最小拡張し、`OperationResult::rejected()`の末尾Optional OperationIdとNullable Accessorで既存Call Siteを維持する
- Inline DispatcherはAuthorization拒否だけでなくHandler由来のRejected Resultにも現在のOperation IDを関連付け、HTTP Rejection JSONが一貫してOperation IDを返せるようにする
- Deferred AcceptorはAcknowledgementまたはOperation ID付きRejected OperationResultを返せる。Completed ResultをDeferred受付結果として許可しない
- `JsonOperationResponder`はRejected ResultにOperation IDがある場合だけ`operationId`を返し、既存IDなしResultのJSON互換を維持する
- Authorization HTTP Responseは`status=rejected`、`operationId`、`category=unauthorized|forbidden`、Safe Codeだけを含み、Credential／Backend Detailを含めない
- Production Code／TestのCommentとDocBlockへDecision／Spec／Task管理番号を書かない

## Acceptance Criteria

- [ ] PolicyなしOperationと既存2引数Dispatcher／Acceptor Callが従来どおり動く
- [ ] Authenticated HTTP ActorがExecutionContextのorigin／authorization／executionへ伝播する
- [ ] Anonymousな`#[Authorize]` OperationはPolicyを呼ばずOperation ID付き401／Received→Rejectedとなる
- [ ] Authenticated AllowはPolicyへOperation／Value／Context／Actorを渡し、HandlerまたはDeferred配送へ進む
- [ ] Unauthorized／ForbiddenはHandler／EnqueueせずOperation ID付き401／403とJournal Rejectedになる
- [ ] Inline AuthorizationはReceived→Attempt Started→RejectedまたはHandlerの順を守る
- [ ] Deferred AuthorizationはReceived→RejectedまたはReceived→Acceptedを守り、拒否時Transport Rowを作らない
- [ ] Policy Backend／Policy解決例外がDenialへ丸められず、401／403 Responseとして扱われない
- [ ] CredentialやPermission SnapshotがContext／Result／Journal Dataへ追加されない
- [ ] Rejected OperationResultのOptional Operation IDが既存IDなし構築と両立する
- [ ] Application Runtime CompositionでCompiled Policy DIとHTTP Actor伝播が動く
- [ ] GuideがActor伝播、固定Lifecycle Stage、401／403、Backend障害責任を説明する
- [ ] Required Commandsが成功する
- [ ] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Required Commands

```bash
docker compose run --rm app mago format src tests
docker compose run --rm app vendor/bin/phpunit tests/Core/OperationResultTest.php tests/Http/OperationRequestHandlerTest.php tests/Http/DeferredOperationRequestHandlerTest.php tests/Http/HttpValidationLifecycleTest.php tests/Internal/Authorization/AuthorizationEvaluatorTest.php tests/Internal/Execution/InlineDispatcherTest.php tests/Internal/Http/DeferredHttpOperationAcceptorTest.php tests/Internal/Runtime/ProductionRuntimeComposerTest.php tests/Transport/PostgreSql/PostgreSqlDeferredAcceptanceOrchestratorTest.php tests/Integration/ApplicationHttpRuntimeTest.php tests/Integration/MvpSampleEndToEndTest.php
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P12-004B-actor-propagation-and-authorization-runtime.md`へ次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
