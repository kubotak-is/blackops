# P12-005B: Deferred Worker Reauthorization and System Actor Report

Status: Accepted

## Summary

Deferred Worker RuntimeへConfigured System ActorとAuthorization Evaluatorを追加した。Worker AttemptはTransport Contextのorigin／authorizationを維持し、executionだけを`execution.worker.id`／`system`へ置き換える。Policy付きOperationはAttempt Started後、Handler実行前にCompiled Containerから解決したPolicyで再認可する。Security DenialはTerminal Rejected、Policy Backend例外はAttempt Failureとして既存Supervisionへ渡し、Retryごとの再評価、Fail／Dead Letter、Lease Expired RecoveryまでActor ContextとFailure分類を維持する。

## Changed Files

- `src/Internal/Application/ApplicationWorkerComposer.php`
- `src/Internal/Execution/DeferredWorkerRuntimeServices.php`
- `src/Internal/Execution/DeferredWorkerRuntime.php`
- `src/Internal/Execution/DeferredLeaseExpiredRecovery.php`
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

## Decisions and Assumptions

- `execution.worker.id`をPostgreSQL Lease OwnerとWorker execution Actor IDへ共通利用し、Actor Typeは`system`へ固定した。新しいConfig Keyは追加していない。
- `DeferredWorkerRuntimeServices`は非nullのWorker ActorとAuthorization Evaluatorを必須依存として保持する。Application ComposerとFramework Test Fixtureは両方を明示する。
- Attempt開始TransactionでTransport ContextをDecodeし、`ExecutionContextFactory::startAttempt()`へWorker Actorを渡す。Factoryの既存規則によりorigin／authorizationを維持してexecutionだけを置換する。
- ActorContextなしのOperationもWorker Attemptではorigin／authorization null、execution Worker ActorのActorContextを持つ。
- Policy評価はAttempt StartedをCommitした後、Claim Execution GuardとExecution Scopeの内側、Handler Invocationの直前に置いた。PolicyなしOperationはEvaluatorの既存No-op経路でHandlerへ進む。
- Actorなし、Unauthorized、ForbiddenはRejected OperationResultへ変換し、既存Operation Rejected確定経路を使う。Attempt Failed、Retry、Dead Letterは記録しない。
- Policy Resolver、Compiled Container、Policy Constructor、Policy Decideの予期しない例外はHandler例外と同じFailure境界へ渡す。Failure Recordは元Exception Class／Messageを保持し、既存Supervision規則を変更しない。
- Retryは同じOperationを次AttemptでClaimし直すため、Policyを毎回再評価する。Policyへ渡すActorはTransportから維持したauthorization Actorであり、Worker Actorへ昇格しない。
- Lease Expired RecoveryはDecode済みorigin／authorizationと現在のConfigured Worker ActorからActorContextを再構成する。
- Credential、Role、Permission、ClaimのFieldやSnapshotは追加していない。

## Commands and Results

```text
docker compose run --rm app mago format src tests
Result: Success。全FileがFormat済み。

docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Internal/Execution/DeferredWorkerRuntimeTest.php tests/Integration/ApplicationConsoleKernelTest.php tests/Integration/MvpSampleEndToEndTest.php
Result: OK (23 tests, 407 assertions, Deprecations 0)。

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: 全FileがFormat済み。Lint／AnalyzeともにNo issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (997 tests, 3360 assertions, Deprecations 0)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1844 / Warnings 0 / Errors 0。

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
Result: Management ID違反なし。Diff Check成功。
```

対象Test調整中に、Retry Fixtureの固定UUID不足、Compiled DI FixtureのService非公開、複数Testで同じ生成Container Class名を再宣言する問題を検出した。固定UUID追加、Public Autowire登録、Test Directory由来の一意なContainer Class名で解消した。Production仕様のBlockerは発生していない。

## Acceptance Criteria

- [x] Application Workerが`execution.worker.id`／`system`のexecution Actorで構成される
- [x] Authenticated Deferred Attemptでorigin／authorizationはUser、executionだけWorker System Actorになる
- [x] ActorContextなしのPolicyなしOperationでもexecution Worker Actorが設定される
- [x] PolicyなしOperationは既存どおりHandlerへ進む
- [x] Worker Policy Allowは最新AuthorizationRequestを受け取りHandlerへ進む
- [x] Actorなし／Unauthorized／ForbiddenはHandlerを呼ばずTerminal Rejectedとなる
- [x] DenialはAttempt Failed／Retry／Dead Letterへ誤分類されない
- [x] Policy Backend例外はAttempt Failedとなり、Security Rejectedへ丸められない
- [x] Retryable Policy障害はRetry Scheduled後の次AttemptでPolicyを再評価する
- [x] Fail／Dead LetterでもActor ContextとFailure分類がCanonical Journalへ維持される
- [x] Lease Expired Recovery Recordがorigin／authorization／Worker execution Actorを維持する
- [x] Worker execution Actorをauthorization ActorとしてPolicyへ渡さない
- [x] Application Console／MVP IntegrationでCompiled Policy DIとSystem Actorが動く
- [x] Guide／Internal DocsがWorker ID再利用、再認可、Denial／Failure分離を説明する
- [x] Required Commandsが成功する
- [x] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Remaining Issues

- Operation Middleware、Credential／Permission Snapshot、Actor RepositoryのFramework具象はScope外であり追加していない。
- HTTP受付Authorization、Supervision Policyの判断規則、Worker Process設定は変更していない。
- Blockerはない。

## Suggested Next Action

P12-006 Consumer Experience and Closeoutへ進む。

## Orchestrator Review

2026-07-17T12:40:09+09:00にAcceptedとした。OrchestratorはProduction差分とActor／Authorization境界を確認し、対象PHPUnit 23件／407 assertions、Mago format／lint／analyze、Deptrac、Management ID Guard、`git diff --check`を独立実行して成功を確認した。Worker System Actorはexecutionだけに使用され、受付時のauthorization ActorがPolicy評価へ維持される。認可拒否はTerminal Rejected、Policy Backend例外は既存Supervisionへ分類されるため、Task PacketのAcceptance Criteriaを満たす。
