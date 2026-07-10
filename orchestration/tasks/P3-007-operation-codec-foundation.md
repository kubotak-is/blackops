# P3-007: Operation Codec Foundation

Status: Accepted

## Goal

Deferred OperationをProcess境界へ渡すため、OperationValueとExecutionContextをPHP Serializationへ依存せずCanonical JSONへ変換するOperation Codec Foundationを追加する。

## In Scope

- Operation CodecのPublic Contractを追加する
- ReflectionベースのJSON Operation CodecをInternal実装として追加する
- OperationValueをJSONへEncodeし、同じValue型へDecodeできるようにする
- ExecutionContextをJSONへEncodeし、Decodeできるようにする
- Type IDとSchema Versionを扱うCodec結果を追加する
- Unit Testと内部Documentationを追加する
- Task ReportとSTATEを更新する

## Out of Scope

- HTTP 202 Response変換
- Deferred Acceptance Orchestratorとの結合
- Worker RuntimeでのOperation Envelope復元
- Upcaster Chainの実装
- Sensitive Payload暗号化
- 非Public Property、Union型、Nested Object Collectionの汎用Hydration

## Relevant Specifications

- `spec/13-mvp-technical-stack.md`
- `spec/19-execution-context-api.md`
- `spec/20-identifier-value-objects.md`
- `spec/21-clock-and-time.md`
- `spec/33-execution-transport-contract.md`
- `spec/35-postgresql-transport-schema.md`
- `decisions/018-mvp-technical-stack.md`
- `decisions/039-execution-transport-contract.md`
- `decisions/041-postgresql-transport-schema.md`

## Files Allowed to Change

- `src/Core/Codec/**`
- `src/Core/Time/TimeCodec.php`
- `src/Internal/Codec/**`
- `tests/Core/Codec/**`
- `tests/Core/Time/TimeCodecTest.php`
- `tests/Internal/Codec/**`
- `docs/internals/**`
- `orchestration/tasks/P3-007-operation-codec-foundation.md`
- `orchestration/reports/P3-007-operation-codec-foundation.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- PHP `serialize()` を使用しない
- Public APIへInternal型を露出しない
- MVP CodecはJSON UTF-8を出力する
- Decode対象のOperationValueはConstructor PromotionされたPublic Propertyを対象とする
- ExecutionContextの時刻は共通TimeCodec形式を使用する

## Acceptance Criteria

- [x] Operation Codec Contractが追加される
- [x] Codec済みMessageがType ID、Schema Version、Payload、Contextを保持する
- [x] Reflection JSON CodecでOperationValueをEncode / Decodeできる
- [x] Reflection JSON CodecでExecutionContextをEncode / Decodeできる
- [x] PHP `serialize()` に依存しない
- [x] Unsupported Value Shapeは明示的な例外で拒否される
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Required Commands

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`orchestration/reports/P3-007-operation-codec-foundation.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
