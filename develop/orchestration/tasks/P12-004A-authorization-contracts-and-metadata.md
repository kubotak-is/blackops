# P12-004A: Authorization Contracts and Metadata

Status: Ready

## Goal

D095／Spec 06／Phase 12 Delivery Planに従い、Operation AuthorizationのPublic Contractを追加し、`#[Authorize]`からOperation ManifestとCompiled ContainerまでPolicy Classを安全に運ぶ。Runtime評価は後続P12-004Bで接続する。

## In Scope

- Operation Class用の非Repeatable `#[Authorize]` Attribute
- `AuthorizationPolicy` Public Contract
- 読み取り専用`AuthorizationRequest`
- Allow／Unauthorized／Forbiddenだけを表す`AuthorizationDecision`
- Operation MetadataへのOptional Policy Class追加
- Discovery／CompilerでのAttribute個数・Policy Contract検証
- Operation ManifestのPolicy Metadata Encode／Decodeと旧Manifest互換
- Compiled ContainerへのPolicy Autowire登録
- Service Providerによる既存Policy Bindingの優先
- Build Command両経路からのPolicy登録
- Core API／Attribute／Security Guideの最小同期

## Out of Scope

- HTTP ActorからActorContextを作る処理
- Inline Handler前のPolicy評価
- Deferred受付時／Worker実行時のPolicy評価
- Authorization拒否のJournal／401／403変換
- Actor Journal Canonical Field
- Policy Backendの具象実装
- Role／Permission／Credentialの保存
- Operation Middleware
- Documentation Website全体とQuickstart Example

## Relevant Specifications and Decisions

- `develop/decisions/095-phase-12-middleware-and-authorization-runtime.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/63-phase-12-delivery-plan.md`

## Files Allowed to Change

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

## Implementation Constraints

- `Authorize`は`#[PublicApi] final readonly`、Class target、非Repeatableとし、`class-string<AuthorizationPolicy>`を一つだけ保持する
- Attribute ConstructorだけでClassの存在を要求せず、Metadata Compile時にPolicy Contractを検証する
- 同一Operationへ複数`Authorize`がある場合はBuild時に拒否する
- `AuthorizationPolicy::decide(AuthorizationRequest): AuthorizationDecision`を唯一のMethodとする
- `AuthorizationRequest`は`#[PublicApi] final readonly`とし、Operation、OperationValue、ExecutionContext、非nullのauthorization ActorをAccessorで提供する
- `AuthorizationRequest` ConstructorはExecutionContextのauthorization Actorと渡されたActorの不一致を許容しない。Credential、Role、Permission、Claimを保持しない
- `AuthorizationDecision`は`#[PublicApi] final readonly`、Private Constructor、`allow()`／`unauthorized(string)`／`forbid(string)` Factoryだけで構築する
- Unauthorized／Forbidden Codeは`RejectionReason`と同じ安定Code規則を再利用する。DecisionはBackend例外を保持しない
- `OperationMetadata`のPolicyは後方互換のためConstructor末尾のOptional Propertyとする
- Manifest EncodeはPolicyがある場合だけ`authorizationPolicy`を出力し、DecodeはField欠落をPolicyなしとして扱う
- ManifestにPolicy Fieldがある場合は非空Class-stringかつ`AuthorizationPolicy`実装を要求し、不正値やClass名をError Messageへ露出しない
- `RuntimeContainerCompiler::registerAuthorizationPolicies()`はMetadataに現れるPolicyをAutowired Public Serviceとして一度だけ登録する
- Service Providerが同じPolicy ID／Alias／Definitionを登録済みなら上書きしない
- Application BuildとLegacy Compile Buildの両方がHandler登録後、Container Compile前にPolicy登録を呼ぶ
- Production Code／TestのCommentとDocBlockへDecision／Spec／Task管理番号を書かない

## Acceptance Criteria

- [ ] `#[Authorize]`がPublic API、Class-only、非RepeatableとしてTestされる
- [ ] PolicyなしOperationのMetadataと旧Manifest Decodeが従来どおり動く
- [ ] Policy付きOperationがMetadata／Manifestで同一Policy Classを保持する
- [ ] 重複Attribute、非Policy Class、改ざんManifestをSafeにBuild／Load拒否する
- [ ] AuthorizationRequestがOperation／Value／Context／authorization Actorだけを提供しInvariantを守る
- [ ] AuthorizationDecisionの三状態、Accessor、Stable Code InvariantがTestされる
- [ ] PolicyがCompiled ContainerへAutowired登録される
- [ ] Service Provider登録済みPolicyが優先される
- [ ] Build Command両経路の生成ContainerからPolicyを解決できる回帰Testがある
- [ ] GuideがPublic Contract、DI、Application責務を説明する
- [ ] Required Commandsが成功する
- [ ] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Required Commands

```bash
docker compose run --rm app mago format src tests
docker compose run --rm app vendor/bin/phpunit tests/Core/Attribute/OperationAttributeTest.php tests/Core/Authorization/AuthorizationRequestTest.php tests/Core/Authorization/AuthorizationDecisionTest.php tests/Internal/Registry/OperationMetadataCompilerTest.php tests/Internal/Registry/OperationManifestMetadataCodecTest.php tests/Internal/Registry/OperationManifestFileTest.php tests/Internal/DependencyInjection/RuntimeContainerCompilerTest.php tests/Internal/Console/ApplicationBuildCompileCommandTest.php tests/Internal/Console/CompileBuildArtifactsCommandTest.php
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

`develop/orchestration/reports/P12-004A-authorization-contracts-and-metadata.md`へ次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
