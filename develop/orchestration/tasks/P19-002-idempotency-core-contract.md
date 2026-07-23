# P19-002: Idempotency Core Contract

Status: Complete

## Goal

HTTP／PHP入口とPostgreSQL Adapterを追加する前に、Idempotency Key、Opaque Hash、ExecutionContext伝播、Version付きScope／Fingerprint、Atomic Storage PortをFramework Core Contractとして実装する。

## In Scope

- Public `BlackOps\Idempotency\IdempotencyKey`
- Public `BlackOps\Idempotency\IdempotencyKeyHash`
- `ExecutionContext`のOptional Idempotency Key Hash Getter／Constructor末尾引数
- Root受信時設定、Attempt維持、Deferred Codec往復、child非伝播
- Version付きScope HasherとOperation Value Fingerprinter
- Processing／Terminal Record、Atomic Claim Result、Store Port
- In-memory Store FixtureによるConcurrent Claim／Conflict／Terminal／Expired Contract Test
- Public API ArchitectureとExisting Context／Transport互換回帰

## Out of Scope

- `Idempotency-Key` HTTP Header
- Public Dispatcher Option／実Operation Lifecycleへの重複制御接続
- PostgreSQL Idempotency Store／Migration
- HTTP Response Snapshot／Replay Header
- Retention Plan／Purge／Audit
- Transactional Outbox、Relay、Replay
- Quickstart／Skeleton／Community Board変更
- External Publication／Deploy

## Relevant Specifications

- `develop/spec/01-core-model.md`
- `develop/spec/18-operation-envelope.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/24-lifecycle-event-data.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/33-execution-transport-contract.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/81-phase-19-delivery-plan.md`
- `develop/decisions/109-phase-18-idempotency-and-outbox.md`

## Files Allowed to Change

- `src/Core/ExecutionContext.php`
- `src/Idempotency/**`
- `src/Internal/Idempotency/**`
- `src/Internal/ExecutionContext/ExecutionContextFactory.php`
- `src/Internal/Codec/ExecutionContextHydrator.php`
- `src/Internal/Codec/ExecutionContextJsonCodec.php`
- `src/Internal/Codec/ExecutionContextNormalizer.php`
- `src/Internal/Runtime/ProductionRuntimeComposer.php`
- `tests/Idempotency/**`
- `tests/Internal/Idempotency/**`
- `tests/Internal/ExecutionContext/ExecutionContextFactoryTest.php`
- `tests/Internal/Codec/ExecutionContextJsonCodecTest.php`
- `tests/Internal/Codec/ReflectionJsonOperationCodecTest.php`
- `tests/Architecture/PublicApiArchitectureTest.php`
- `develop/spec/17-core-api.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/orchestration/tasks/P19-002-idempotency-core-contract.md`
- `develop/orchestration/reports/P19-002-idempotency-core-contract.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへBlockerとして記載する。

## Contract

### Public Value

- `IdempotencyKey`は1〜255文字の空白／Control Characterを含まないPrintable ASCIIだけを受理する
- InvalidArgumentExceptionへRaw Keyを含めない
- `__toString()`、JSON Serialization、Public Propertyを持たない
- Public APIは`hash(): IdempotencyKeyHash`だけとし、Raw Getterを持たない
- HashはDomain Separator、String byte length、Raw Keyを順にSHA-256へ投入するVersion 1とする
- `IdempotencyKeyHash`はAlgorithm／Codec Versionと64文字Lowercase Hex Digestを不変に保持する
- Key／Hashの比較はConstant-timeとし、Debug／String化へRaw Keyを出さない

### Execution Context

- Constructor末尾へOptional Hashを追加して既存Call Siteを維持する
- `idempotencyKeyHash(): ?IdempotencyKeyHash`だけを公開する
- Root `receive()`はOptional Keyを受け、Framework HasherでHashを設定する
- `startAttempt()`はHashを維持する
- `createChild()`はHashを継承しない
- CodecはRaw KeyではなくVersion＋DigestだけをEncodeし、Field欠落を`null`としてDecodeする
- Unknown Version、Invalid Digest、Unexpected FieldをCodec Failureにする

### Scope and Fingerprint

- ScopeはOperation Type ID、authorization Actor type／id、KeyをField境界付きでHash化する
- FingerprintはOperation Type、Value Type、宣言Property順、型、null／値境界、String byte lengthを含む
- Sensitive PropertyはHash入力へ含めるが、Canonical Representation全体をBuffer化・保存・返却・Error表示しない
- 既存Canonical Operation Codecが扱う有限Floatは決定的に表現し、非有限FloatとUnsupported Value Shapeは既存Operation Value Contractと同じBuild／Runtime Failureへ閉じる
- Algorithm／Canonical Codec VersionをResultへ保持する

### Storage Port

- Scope Hashに対する最初のClaimだけがProcessing Recordを作る
- Claim Resultは`claimed`、`existing_same_fingerprint`、`existing_conflict`を型で区別する
- TerminalizeはClaimしたOperation IDと期待Stateが一致する場合だけ成功する
- Same ScopeのConcurrent Claim、Stale Terminalize、Expired Snapshot、Unknown Versionを一致扱いにしない
- Store PortへRaw Key、Actor、Canonical Value、Credentialを渡さない
- P19-002のIn-memory実装はTest FixtureでありProduction Runtimeへ登録しない

## Constraints

- Production Code実装はGPT-5.6 Luna High workerが行う
- WorkerはCommitしない
- HTTP／Dispatcherへ未実装のIdempotency動作を公開しない
- Existing KeyなしExecution Context Codecを後方互換にDecodeする
- Public Signatureへ`BlackOps\Internal`型を露出しない
- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する

## Acceptance Criteria

- [ ] Valid／Invalid Key ShapeがPublic Valueで固定される
- [ ] Raw Keyを永続化・Codec・Error・Debug Surfaceへ出さない
- [ ] Root／Attempt／Deferred／ChildのHash伝播規則がTestされる
- [ ] ScopeとFingerprintが型／Field境界付きで決定的になる
- [ ] Same／Conflict／Concurrent／Stale／Expired Storage ContractがTestされる
- [ ] Existing Context PayloadとKeyなしRuntimeが回帰しない
- [ ] Public API Architecture GuardとDeptracが成功する
- [ ] HTTP／PostgreSQL／Retention／Outbox／Consumer差分がない

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit \
  tests/Idempotency \
  tests/Internal/Idempotency \
  tests/Internal/ExecutionContext/ExecutionContextFactoryTest.php \
  tests/Internal/Codec/ExecutionContextJsonCodecTest.php \
  tests/Internal/Codec/ReflectionJsonOperationCodecTest.php \
  tests/Architecture/PublicApiArchitectureTest.php
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint src tests
docker compose run --rm app mago analyze src tests
docker compose run --rm app vendor/bin/deptrac analyse --no-progress
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
git status --short
```

## Expected Report

`develop/orchestration/reports/P19-002-idempotency-core-contract.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Public Value Matrix
- ExecutionContext Propagation Matrix
- Scope／Fingerprint Matrix
- Storage Claim／Terminal Matrix
- Sensitive Evidence
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
