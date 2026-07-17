# P12-004B: Actor Propagation and Authorization Runtime Report

Status: Accepted

## Summary

HTTP AuthenticationがRequest Attributeへ置いた`ActorRef`を、origin／authorization／executionが同じ主体を指す`ActorContext`としてInline／Deferred実行へ伝播した。Compiled ContainerからPolicyを解決して固定Lifecycle Stageで評価し、Anonymous、Unauthorized、ForbiddenをOperation ID付きRejected Result、Canonical Journal、HTTP 401／403へ接続した。Allowの場合だけHandlerまたはDeferred Transportへ進み、Policy Backend／解決例外はSecurity Denialへ丸めず元の例外を伝播する。

## Changed Files

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
- `docs/guide/core-api.md`
- `docs/guide/security.md`
- `develop/orchestration/reports/P12-004B-actor-propagation-and-authorization-runtime.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Public `Dispatcher::dispatch()`とDeferred AcceptorのActorContextは末尾Optional引数とし、従来の二引数呼び出しを維持した。
- HTTP境界は`ActorRef::class` Keyの値が実際に`ActorRef`である場合だけActorとして扱う。Credential、Header、Role、Permission、ClaimはContextへコピーしない。
- PolicyなしOperationはResolverへ触れず通過する。Policy付きでAuthorization Actorがない場合はPolicyを呼ばず`authorization.authentication_required`へ変換する。
- Policy Serviceの未登録／型違反は固定Messageの`LogicException`とした。Container取得、Policy構築、Policy実行の予期外例外はCatchしてDenialへ変換しない。
- Inline AuthorizationはReceived、Attempt Startedの後に評価し、拒否を次Sequenceへ記録する。Handler由来のRejected Resultにも現在のOperation IDを関連付ける。
- Deferred Authorizationは既存Transaction内でReceived記録後に評価する。拒否時はReceived／RejectedをCommitし、Transport RowとAccepted Recordを作らない。Authorization例外はTransactionをRollbackし、元のThrowable Objectを再throwする。
- Deferred Acceptorが返す`OperationResult`はRejectedだけを許可し、Completed ResultはHTTP境界の契約違反としてFail-fastする。
- Application Runtime統合Testでは実際のBuild CommandでOperation Manifest、HTTP Manifest、Compiled Containerを生成し、DIされたPolicyへHTTP Actorが到達することを確認した。

## Commands and Results

```text
docker compose run --rm app mago format src tests
Result: Success。最終実行で1 fileを整形。

docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Core/OperationResultTest.php tests/Http/OperationRequestHandlerTest.php tests/Http/DeferredOperationRequestHandlerTest.php tests/Http/HttpValidationLifecycleTest.php tests/Internal/Authorization/AuthorizationEvaluatorTest.php tests/Internal/Execution/InlineDispatcherTest.php tests/Internal/Http/DeferredHttpOperationAcceptorTest.php tests/Internal/Runtime/ProductionRuntimeComposerTest.php tests/Transport/PostgreSql/PostgreSqlDeferredAcceptanceOrchestratorTest.php tests/Integration/ApplicationHttpRuntimeTest.php tests/Integration/MvpSampleEndToEndTest.php
Result: OK (79 tests, 361 assertions, Deprecations 0)。

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: 全FileがFormat済み。Lint／AnalyzeともにNo issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (972 tests, 3117 assertions, Deprecations 0)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1822 / Warnings 0 / Errors 0。

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
Result: Management ID違反なし。Diff Check成功。
```

初回Mago AnalyzeはNullable Operation IDの重複Accessor呼び出しと、Closure内で捕捉したAuthorization例外の同一性追跡を検出した。Operation IDをローカル変数化し、例外Objectを型付き`WeakMap`で追跡して解消した。Review時に一時実装の`SplObjectStorage::attach()`／`contains()`がPHP 8.5で非推奨と判明したため、非推奨APIを使用しない`WeakMap`へ置き換えた。最終Lint／Analyzeは問題なし、対象／全PHPUnitはDeprecation 0で成功した。

## Acceptance Criteria

- [x] PolicyなしOperationと既存二引数Dispatcher／Acceptor Callが従来どおり動く
- [x] Authenticated HTTP ActorがExecutionContextのorigin／authorization／executionへ伝播する
- [x] AnonymousなPolicy付きOperationはPolicyを呼ばずOperation ID付き401／Received→Rejectedとなる
- [x] Authenticated AllowはPolicyへOperation／Value／Context／Actorを渡し、HandlerまたはDeferred配送へ進む
- [x] Unauthorized／ForbiddenはHandler／EnqueueせずOperation ID付き401／403とJournal Rejectedになる
- [x] Inline AuthorizationはReceived→Attempt Started→RejectedまたはHandlerの順を守る
- [x] Deferred AuthorizationはReceived→RejectedまたはReceived→Acceptedを守り、拒否時Transport Rowを作らない
- [x] Policy Backend／Policy解決例外がDenialへ丸められず、401／403 Responseとして扱われない
- [x] CredentialやPermission SnapshotがContext／Result／Journal Dataへ追加されない
- [x] Rejected OperationResultのOptional Operation IDが既存IDなし構築と両立する
- [x] Application Runtime CompositionでCompiled Policy DIとHTTP Actor伝播が動く
- [x] GuideがActor伝播、固定Lifecycle Stage、401／403、Backend障害責任を説明する
- [x] Required Commandsが成功する
- [x] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Remaining Issues

- Deferred Worker実行時の再認可、Worker execution ActorのSystem Actor置換、Canonical Journal Actor Fieldは後続TaskのScopeである。
- Blockerはない。

## Orchestrator Review

- HTTP Actorのorigin／authorization／execution伝播、Policyなし／Anonymous／Allow／Unauthorized／Forbiddenの分岐、Handler／Queue非実行Invariantを確認した。
- InlineのReceived→Attempt Started→Rejected、Deferred受付のReceived→RejectedまたはAccepted、Policy Backend例外時のTransaction Rollbackと同一Throwable再throwを確認した。
- Reviewで検出したPHP 8.5 Deprecationを非推奨APIを使わない型付き`WeakMap`へ修正し、対象／全PHPUnitでDeprecation 0になったことを確認した。
- 対象PHPUnit 79 tests／361 assertions、Mago format／lint／analyze、Deptrac、Management ID Guard、`git diff --check`をOrchestratorが独立再実行し、すべて成功した。
- Acceptance Criteriaを満たすため、本TaskをAcceptedとする。

## Suggested Next Action

P12-005 Deferred Reauthorization and Actor Journalへ進む。
