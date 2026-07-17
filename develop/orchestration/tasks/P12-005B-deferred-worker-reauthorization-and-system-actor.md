# P12-005B: Deferred Worker Reauthorization and System Actor

Status: Ready

## Goal

D095／Spec 06／Phase 12 Delivery Planに従い、Deferred Worker Attempt開始時にexecution ActorだけをWorker System Actorへ置き換え、Handler実行前に受付時と同じPolicyを最新状態で再評価する。Security DenialはTerminal Rejected、Policy Backend障害はAttempt FailureとしてSupervisionへ参加させ、Retry／Backoff／Dead LetterまでActor Contextと分類を維持する。

## In Scope

- Worker Runtime ServicesへのSystem execution ActorとAuthorization Evaluator追加
- `execution.worker.id`からWorker System Actorを構成するApplication Composition
- Attempt開始時のexecution Actor置換
- origin／authorization Actorの維持
- Deferred Handler前のPolicy再評価
- Actorなし／Policy Unauthorized／ForbiddenのTerminal Rejected
- Policy Backend／Policy解決例外のAttempt Failure化
- Retry／Backoff／Dead Letterへの既存Supervision参加
- Retry Attemptごとの最新Policy再評価
- Lease Expired RecoveryでのActor Context維持
- Canonical Journalでの受付ActorとWorker execution Actor分離
- Worker Runtime／Security／Configuration文書の最小同期

## Out of Scope

- Credential、Role、Permission、ClaimのTransport／Journal保存
- Workerごとに独立した新しいActor Config Key
- Actor RepositoryのFramework具象実装
- Supervision Policyの規則変更
- HTTP受付Authorizationの変更
- Operation Middleware
- Documentation Website全体とQuickstart認証Example

## Relevant Specifications and Decisions

- `develop/decisions/095-phase-12-middleware-and-authorization-runtime.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/32-worker-crash-recovery.md`
- `develop/spec/63-phase-12-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/Application/ApplicationWorkerComposer.php`
- `src/Internal/Execution/DeferredWorkerRuntimeServices.php`
- `src/Internal/Execution/DeferredWorkerRuntime.php`
- `src/Internal/Execution/DeferredLeaseExpiredRecovery.php`
- `src/Internal/Execution/HandlerInvocationFailedException.php`
- `tests/Internal/Execution/DeferredWorkerRuntimeTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Integration/MvpSampleEndToEndTest.php`
- `docs/guide/configuration.md`
- `docs/guide/execution-context.md`
- `docs/guide/security.md`
- `docs/internal/worker-runtime.md`
- `docs/internal/application-bootstrap.md`
- `develop/orchestration/reports/P12-005B-deferred-worker-reauthorization-and-system-actor.md`
- `develop/STATE.md`

## Implementation Constraints

- Application WorkerのSystem Actorは既存`execution.worker.id`をActor ID、固定文字列`system`をActor Typeとして構築する。Lease Ownerと監査主体の識別子を揃え、新Config Keyを追加しない
- `DeferredWorkerRuntimeServices`は非nullのWorker execution Actorを必須依存として保持し、Application Compositionと全Framework Test Fixtureが明示する
- WorkerがTransport ContextをDecodeした後、`ExecutionContextFactory::startAttempt()`へSystem Actorを渡し、origin／authorizationを維持してexecutionだけを置き換える
- Anonymous／System起点でActorContextがないDeferred OperationもAttempt開始時にはorigin／authorization null、execution Worker System ActorのActorContextを持つ
- PolicyなしOperationは再認可せずHandlerへ進む
- Policy付きOperationはAttempt Started記録後、Handler解決済みObjectの実行前にP12-004Bと同じ`AuthorizationEvaluator`で評価する
- Authorization Actorがない場合とPolicyのUnauthorized／Forbidden Decisionは`OperationResult::rejected()`へ変換し、Operation Rejectedを記録してTerminal `rejected`へ確定する。Attempt Failed／Retryは記録しない
- Policy Backend、Container、Policy構築、Policy実行の予期外例外はDenialへ変換せず、Handler例外と同じAttempt Failure／Supervision境界へ渡す
- Transient Policy例外が`RetryableException`なら既存Exponential BackoffによりRetryされ、次AttemptでPolicyを再評価する
- SupervisionがFail／Dead Letterを選ぶ場合もAttempt Failed後の既存Terminal Eventを維持する
- Authorization DenialではHandlerを呼ばず、Backend FailureでもHandlerを呼ばない
- Retry、Attempt Failure、Retry Scheduled、Operation Rejected／Failed／Dead LetterのCanonical Journalはorigin／authorizationを受付時Actor、executionをWorker System Actorとして保持する
- Lease Expired RecoveryはTransport Contextのorigin／authorizationとConfigured Worker System Actorを復元したEnvelopeを使用し、ActorContextを欠落させない
- Worker execution Actorをauthorization Actorとして使用して元Actorの権限を強化しない
- Failure Recordの既存Exception Class／Message／retryable規則は変更せず、Credentialを新しいContext Fieldへ追加しない
- Production Code／TestのCommentとDocBlockへDecision／Spec／Task管理番号を書かない

## Acceptance Criteria

- [ ] Application Workerが`execution.worker.id`／`system`のexecution Actorで構成される
- [ ] Authenticated Deferred Attemptでorigin／authorizationはUser、executionだけWorker System Actorになる
- [ ] ActorContextなしのPolicyなしOperationでもexecution Worker Actorが設定される
- [ ] PolicyなしOperationは既存どおりHandlerへ進む
- [ ] Worker Policy Allowは最新AuthorizationRequestを受け取りHandlerへ進む
- [ ] Actorなし／Unauthorized／ForbiddenはHandlerを呼ばずTerminal Rejectedとなる
- [ ] DenialはAttempt Failed／Retry／Dead Letterへ誤分類されない
- [ ] Policy Backend例外はAttempt Failedとなり、Security Rejectedへ丸められない
- [ ] Retryable Policy障害はRetry Scheduled後の次AttemptでPolicyを再評価する
- [ ] Fail／Dead LetterでもActor ContextとFailure分類がCanonical Journalへ維持される
- [ ] Lease Expired Recovery Recordがorigin／authorization／Worker execution Actorを維持する
- [ ] Worker execution Actorをauthorization ActorとしてPolicyへ渡さない
- [ ] Application Console／MVP IntegrationでCompiled Policy DIとSystem Actorが動く
- [ ] Guide／Internal DocsがWorker ID再利用、再認可、Denial／Failure分離を説明する
- [ ] Required Commandsが成功する
- [ ] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Required Commands

```bash
docker compose run --rm app mago format src tests
docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Internal/Execution/DeferredWorkerRuntimeTest.php tests/Integration/ApplicationConsoleKernelTest.php tests/Integration/MvpSampleEndToEndTest.php
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P12-005B-deferred-worker-reauthorization-and-system-actor.md`へ次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
