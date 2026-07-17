# P12-002: Actor Context Foundation

Status: Ready

## Goal

D095／Spec 06／19に従い、Credentialを含まないPublic Actor Modelを追加し、ExecutionContextのRoot受信、Attempt開始、子Operation生成、Deferred CodecでID／Typeだけを安全に伝播できるようにする。

## In Scope

- `ActorRef(id, type)`のPublic APIと入力Invariant
- `ActorContext(origin, authorization, execution)`のPublic API
- `ExecutionContext`末尾Optional Constructor Parameterと`actorContext()` Getter
- `ExecutionContextFactory`のreceive／startAttempt／createChild Actor伝播
- Attempt／Childでexecution Actorだけを明示置換できるInternal API
- Execution Context JSON Codecの`actors` Encode／Decode
- Actor Field欠落を許容する既存Message互換
- Public API／Factory／CodecのUnit Test
- ExecutionContext／Core API Guideの最小同期

## Out of Scope

- HTTP Middleware Pipeline
- `HttpAuthenticator`／`AuthenticationMiddleware`
- Request AttributeからActorContextを生成するHTTP接続
- `#[Authorize]`／Policy／Manifest Metadata
- Inline／Deferred Authorization評価
- Canonical JournalのActor FieldとObserver Mask
- Worker System Actor Configuration
- Quickstart／Skeleton／Website全体の同期

## Relevant Specifications and Decisions

- `develop/decisions/095-phase-12-middleware-and-authorization-runtime.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/63-phase-12-delivery-plan.md`

## Files Allowed to Change

- `src/Core/ActorRef.php`
- `src/Core/ActorContext.php`
- `src/Core/ExecutionContext.php`
- `src/Internal/ExecutionContext/ExecutionContextFactory.php`
- `src/Internal/Codec/ExecutionContextNormalizer.php`
- `src/Internal/Codec/ExecutionContextHydrator.php`
- `tests/Core/ActorRefTest.php`
- `tests/Core/ActorContextTest.php`
- `tests/Core/ExecutionContextTest.php`
- `tests/Internal/ExecutionContext/ExecutionContextFactoryTest.php`
- `tests/Internal/Codec/ReflectionJsonOperationCodecTest.php`
- `docs/guide/core-api.md`
- `docs/guide/execution-context.md`
- `develop/orchestration/reports/P12-002-actor-context-foundation.md`
- `develop/STATE.md`

## Implementation Constraints

- `ActorRef`と`ActorContext`は`BlackOps\Core`の`#[PublicApi] final readonly class`とする
- ActorRefのIDとTypeはtrim後の非空文字列を要求する。Framework独自のUUID／formatを強制しない
- ActorContextのorigin／authorizationはNullable、executionは必須とする
- `ExecutionContext`の新Parameterは既存Positional Callを壊さないようConstructor末尾へ追加する
- `startAttempt()`と`createChild()`はorigin／authorizationを維持し、明示Actorがある場合だけexecutionを置き換える
- Codecは`actors`にorigin／authorization／executionをEncodeし、各Actorは`id`／`type`以外を持たない
- 既存Context JSONに`actors`がない場合と`actors: null`はどちらも`actorContext() === null`へDecodeする
- Actor Objectの必須Field欠落、非文字列、空文字、未知Fieldは`OperationCodecException`として拒否する
- Credential、Token、Session、Role、Permission、ClaimをActor API／Encoded Contextへ追加しない
- Production Code／TestのCommentとDocBlockへDecision／Spec／Task管理番号を書かない

## Acceptance Criteria

- [ ] ActorRefがID／Typeを保持し、空文字と空白だけを拒否する
- [ ] ActorContextがorigin／authorization／executionを型付きで公開する
- [ ] ExecutionContextが既存Constructor Callと互換のままOptional ActorContextを公開する
- [ ] FactoryのRoot／Attempt／Child Actor伝播とexecution置換がTestされる
- [ ] Codec Round-tripでActor ID／Typeだけが一致する
- [ ] 旧Context JSONをDecodeできる
- [ ] Malformed／Unknown Actor FieldとCredential系FieldをCodecが拒否する
- [ ] Guideが新Public APIとCredential非保持境界を説明する
- [ ] Required Commandsが成功する
- [ ] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Required Commands

```bash
docker compose run --rm app mago format src/Core/ActorRef.php src/Core/ActorContext.php src/Core/ExecutionContext.php src/Internal/ExecutionContext/ExecutionContextFactory.php src/Internal/Codec/ExecutionContextNormalizer.php src/Internal/Codec/ExecutionContextHydrator.php tests/Core/ActorRefTest.php tests/Core/ActorContextTest.php tests/Core/ExecutionContextTest.php tests/Internal/ExecutionContext/ExecutionContextFactoryTest.php tests/Internal/Codec/ReflectionJsonOperationCodecTest.php
docker compose run --rm app vendor/bin/phpunit tests/Core/ActorRefTest.php tests/Core/ActorContextTest.php tests/Core/ExecutionContextTest.php tests/Internal/ExecutionContext/ExecutionContextFactoryTest.php tests/Internal/Codec/ReflectionJsonOperationCodecTest.php
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P12-002-actor-context-foundation.md`へ次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
