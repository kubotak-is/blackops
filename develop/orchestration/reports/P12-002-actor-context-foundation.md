# P12-002: Actor Context Foundation Report

Status: Accepted

## Summary

Credentialを保持しないPublic `ActorRef`／`ActorContext`を追加し、`ExecutionContext`、Internal Factory、Deferred Context CodecへActor ID／Typeだけを伝播するFoundationを実装した。既存のActorなしContextとCodec Payloadは引き続き利用できる。Review Follow-upではContext top-levelのSecurity Reserved Fieldもcase表記を正規化してfail-closedにした。

## Changed Files

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

## Decisions and Assumptions

- `ActorRef`はID／Typeをtrimして保持し、trim後の空文字だけを拒否する。Application固有formatやUUIDは強制しない。
- `ExecutionContext`のActor Parameterは既存Positional Callを維持するためConstructor末尾へ追加した。
- Internal Factoryの`receive()`はActorContext全体を受け取り、`startAttempt()`／`createChild()`はOptionalなexecution Actorだけを受け取る。置換元Contextがnullの場合もorigin／authorizationがnullのActorContextを生成できる。
- Codecの`actors`はorigin／authorization／executionだけ、各Actor Objectはid／typeだけを許可する。未知FieldはCredential系に限定せずすべて拒否する。
- Context top-levelは将来の一般拡張Fieldを許容しつつ、password、token、secret、credential、session、api key、bearer、JWT、claim、role、permissionに一致するSecurity Reserved Keyだけを拒否する。snake／camel／kebab caseを同じ境界で判定する。
- `actors` Field欠落と`actors: null`はActor未設定の既存PayloadとしてDecodeする。
- Website Source全体の同期はTask Scope外であり、Framework GuideのPublic APIとExecutionContext説明だけを最小同期した。

## Commands and Results

```text
docker compose run --rm app mago format <P12-002 source and test files>
Result: Success。5 files formatted。

docker compose run --rm app vendor/bin/phpunit tests/Core/ActorRefTest.php tests/Core/ActorContextTest.php tests/Core/ExecutionContextTest.php tests/Internal/ExecutionContext/ExecutionContextFactoryTest.php tests/Internal/Codec/ReflectionJsonOperationCodecTest.php
Result: OK (54 tests, 140 assertions)。

docker compose run --rm app mago format --check src tests
Result: Success。All files are already formatted。

docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: Success。No issues found。

docker compose run --rm app vendor/bin/phpunit
Result: OK (896 tests, 2885 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1729 / Warnings 0 / Errors 0。

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
Result: Management ID違反なし。Diff Check成功。
```

最初のDocker CommandはSandbox内からDocker Socketへ接続できず失敗した。承認済みのDocker Compose実行へ切り替え、同一Commandを含むRequired Commandsをすべて成功させた。

Review Follow-upの最初のLintではExecutionContextHydratorのComplexity／Method Count違反を検出した。Reserved Key判定を単一の正規化Patternへ整理して解消し、対象TestからFull Quality Gateまで再実行して成功した。

## Acceptance Criteria

- [x] ActorRefがID／Typeを保持し、空文字と空白だけを拒否する
- [x] ActorContextがorigin／authorization／executionを型付きで公開する
- [x] ExecutionContextが既存Constructor Callと互換のままOptional ActorContextを公開する
- [x] FactoryのRoot／Attempt／Child Actor伝播とexecution置換がTestされる
- [x] Codec Round-tripでActor ID／Typeだけが一致する
- [x] 旧Context JSONをDecodeできる
- [x] Malformed／Unknown Actor Fieldとactors／top-levelのCredential系FieldをCodecが拒否する
- [x] Guideが新Public APIとCredential非保持境界を説明する
- [x] Required Commandsが成功する
- [x] Report／STATEを更新し、WorkerはCommitせずReviewへ返す

## Remaining Issues

- HTTP RequestからActorContextを生成する接続、Authentication、Authorization、Journal Actor Projection、Worker System Actor設定は後続TaskのScopeである。
- Blockerはない。

## Suggested Next Action

P12-003 HTTP Middleware and AuthenticationのTask Packetを作成し、Global PSR-15 PipelineとCredentialを保持しないAuthentication境界を実装する。

## Orchestrator Review

- Task Packetの許可File外に変更がないことを確認した
- ActorRef／ActorContextのPublic APIとExecutionContext Constructor末尾追加を確認した
- Root／Attempt／Childでorigin／authorizationを維持し、executionだけを置換するInvariantを確認した
- Actor ObjectのStrict Field検証、旧Payload互換、top-level Security Reserved Key拒否、一般Context拡張許容を確認した
- 対象54 tests／140 assertions、Format、Lint、Analyze、Deptrac 0、Management ID Guard、diff checkを独立再実行して成功した
- P12-002をAcceptedとする
