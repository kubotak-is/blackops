# P12-004A: Authorization Contracts and Metadata Report

Status: Accepted

## Summary

Operation AuthorizationのPublic Contractとして非Repeatableな`#[Authorize]`、`AuthorizationPolicy`、読み取り専用`AuthorizationRequest`、三状態`AuthorizationDecision`を追加した。Policy ClassはOperation MetadataとManifestを後方互換に通過し、Application BuildとLegacy Compile Buildの両経路でCompiled ContainerへAutowired Public Serviceとして登録される。RuntimeでのPolicy評価、Actor接続、Journal、HTTP 401／403変換は後続Taskへ残した。

## Changed Files

- `src/Core/Attribute/Authorize.php`
- `src/Core/Authorization/AuthorizationPolicy.php`
- `src/Core/Authorization/AuthorizationRequest.php`
- `src/Core/Authorization/AuthorizationDecision.php`
- `src/Core/Registry/OperationMetadata.php`
- `src/Internal/Registry/OperationMetadataCompiler.php`
- `src/Internal/Registry/OperationManifestMetadataCodec.php`
- `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/Console/CompileBuildArtifactsCommand.php`
- `tests/Core/Attribute/OperationAttributeTest.php`
- `tests/Core/Authorization/AuthorizationRequestTest.php`
- `tests/Core/Authorization/AuthorizationDecisionTest.php`
- `tests/Internal/Registry/OperationMetadataCompilerTest.php`
- `tests/Internal/Registry/OperationManifestMetadataCodecTest.php`
- `tests/Internal/Registry/OperationManifestFileTest.php`
- `tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- `tests/Internal/Console/CompileBuildArtifactsCommandTest.php`
- `docs/guide/attributes.md`
- `docs/guide/core-api.md`
- `docs/guide/security.md`
- `develop/orchestration/reports/P12-004A-authorization-contracts-and-metadata.md`
- `develop/STATE.md`

## Decisions and Assumptions

- `Authorize` ConstructorはClass存在を検証せず、Metadata Compile時に`AuthorizationPolicy` ContractをSafeな固定Messageで検証する。重複AttributeもInstance化前に拒否する。
- `AuthorizationRequest`はOperation、Value、ExecutionContext、Authorization Actorだけを保持する。Actor一致はObject IdentityではなくID／Typeの値で比較し、ContextにAuthorization Actorがない場合も拒否する。
- `AuthorizationDecision`はAllow／Unauthorized／Forbiddenだけを内部Stateで表し、拒否Codeの検証は`RejectionReason`の安定Code規則を再利用する。Backend例外やCredentialを保持しない。
- `OperationMetadata`のPolicyは既存Constructor Callとの互換性を保つ末尾Optional Propertyとした。既存Lint Constructor閾値を一つ超えるため、この不変Metadata ConstructorにだけMagoの局所的な期待注釈を付けた。
- ManifestはPolicyがある場合だけ`authorizationPolicy`をEncodeする。Field欠落はPolicyなしとしてDecodeし、空値、非文字列、存在しないClass、非Policy ClassをClass名非露出の固定Errorで拒否する。
- Container登録はSymfony `ContainerBuilder::has()`がDefinition、Alias、Instanceを包含する性質を使用し、Service Provider登録済みServiceを上書きしない。Metadata Compile／Manifest Decode済みのPolicyだけを登録対象とする。
- GuideはContractとDI責任分界だけを同期し、未実装のLifecycle評価が既に利用可能であるとは説明しない。

## Commands and Results

```text
docker compose run --rm app mago format src tests
Result: Success。実装とTestを整形し、最終Checkで全FileがFormat済み。

docker compose run --rm app vendor/bin/phpunit tests/Core/Attribute/OperationAttributeTest.php tests/Core/Authorization/AuthorizationRequestTest.php tests/Core/Authorization/AuthorizationDecisionTest.php tests/Internal/Registry/OperationMetadataCompilerTest.php tests/Internal/Registry/OperationManifestMetadataCodecTest.php tests/Internal/Registry/OperationManifestFileTest.php tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php tests/Internal/Console/ApplicationBuildCompileCommandTest.php tests/Internal/Console/CompileBuildArtifactsCommandTest.php
Result: OK (86 tests, 204 assertions)。

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: 全FileがFormat済み。Lint／AnalyzeともにNo issues found。

docker compose run --rm app vendor/bin/phpunit
Result: OK (948 tests, 3019 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1781 / Warnings 0 / Errors 0。

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
Result: Management ID違反なし。Diff Check成功。
```

初回対象TestはAuthorizationRequestのPrivate Property列挙を`get_object_vars()`で行ったTest実装だけが失敗し、Reflection Property列挙へ修正して解消した。初回Lintは末尾Optional追加によるConstructor閾値とContainer Compiler Complexityを検出した。局所的な期待注釈とSymfony `has()`への重複判定集約で解消した。初回AnalyzeはPolicy型の冗長再検証と一行DocBlockの戻り型認識を検出し、Compile／Decode境界の責務に合わせて修正した。最終Required Commandsはすべて成功した。

## Acceptance Criteria

- [x] `#[Authorize]`がPublic API、Class-only、非RepeatableとしてTestされる
- [x] PolicyなしOperationのMetadataと旧Manifest Decodeが従来どおり動く
- [x] Policy付きOperationがMetadata／Manifestで同一Policy Classを保持する
- [x] 重複Attribute、非Policy Class、改ざんManifestをSafeにBuild／Load拒否する
- [x] AuthorizationRequestがOperation／Value／Context／Authorization Actorだけを提供しInvariantを守る
- [x] AuthorizationDecisionの三状態、Accessor、Stable Code InvariantがTestされる
- [x] PolicyがCompiled ContainerへAutowired登録される
- [x] Service Provider登録済みPolicyが優先される
- [x] Build Command両経路の生成ContainerからPolicyを解決できる回帰Testがある
- [x] GuideがPublic Contract、DI、Application責務を説明する
- [x] Required Commandsが成功する
- [x] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Remaining Issues

- HTTP ActorからActorContextを作る処理、Inline／Deferred受付前のPolicy評価、Unauthorized／Forbidden Journal、HTTP 401／403変換はP12-004BのScopeである。
- Deferred Worker再認可とActor JournalはP12-005のScopeである。
- Blockerはない。

## Orchestrator Review

- Public Contractの構築可能状態、Authorization Actor一致Invariant、Stable Code検証を確認した。
- Policyなし旧Manifest互換、Policy付きRound-trip、改ざんFieldのSafe拒否、Service Provider優先、Application／Legacy Build両経路を確認した。
- 対象PHPUnit 86 tests／204 assertions、Mago format／lint／analyze、Deptrac、Management ID Guard、`git diff --check`をOrchestratorが独立再実行し、すべて成功した。
- Acceptance Criteriaを満たすため、本TaskをAcceptedとする。

## Suggested Next Action

P12-004B Actor Propagation and Authorization Runtimeへ進む。
