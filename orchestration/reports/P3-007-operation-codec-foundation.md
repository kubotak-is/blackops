# P3-007: Operation Codec Foundation Report

Status: Accepted

## Summary

Deferred OperationをProcess境界へ渡すため、OperationValueとExecutionContextをCanonical JSONへ変換するOperation Codec Foundationを追加した。

Public Contractとして`OperationCodec`、`EncodedOperationMessage`、`OperationCodecException`を追加し、Internal実装としてReflectionベースのJSON Codecを追加した。PHP `serialize()` は使用していない。

## Changed Files

- `src/Core/Codec/EncodedOperationMessage.php`
- `src/Core/Codec/OperationCodec.php`
- `src/Core/Codec/OperationCodecException.php`
- `src/Core/Time/TimeCodec.php`
- `src/Internal/Codec/ExecutionContextHydrator.php`
- `src/Internal/Codec/ExecutionContextJsonCodec.php`
- `src/Internal/Codec/ExecutionContextNormalizer.php`
- `src/Internal/Codec/JsonDocumentCodec.php`
- `src/Internal/Codec/JsonObjectReader.php`
- `src/Internal/Codec/OperationValueArgumentCoercer.php`
- `src/Internal/Codec/OperationValueHydrator.php`
- `src/Internal/Codec/OperationValueNormalizer.php`
- `src/Internal/Codec/ReflectionJsonOperationCodec.php`
- `tests/Core/Codec/OperationCodecContractTest.php`
- `tests/Core/Time/TimeCodecTest.php`
- `tests/Internal/Codec/ReflectionJsonOperationCodecTest.php`
- `docs/internals/operation-codec.md`
- `orchestration/tasks/P3-007-operation-codec-foundation.md`
- `orchestration/reports/P3-007-operation-codec-foundation.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- Codec結果はType ID、Schema Version、Encoded Payload、Encoded Contextを保持する中間表現とし、Operation IDとAvailable Atは受付境界で`DeferredOperationMessage`へ付与する。
- MVPのReflection JSON CodecはConstructor PromotionされたPublic Property中心の単純DTOを対象にする。
- Contextの時刻Decodeに必要なため、既存のTimeCodecへCanonical UTCマイクロ秒文字列の`parse()`を追加した。
- Object Property、Union型、Intersection型、Nested Object Collection、Upcaster Chain、Payload Encryptionは後続Taskへ残した。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'OperationCodecContractTest|ReflectionJsonOperationCodecTest|TimeCodecTest'
Result: OK (16 tests, 44 assertions). Runtime PHP 8.5.7.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (340 tests, 841 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 576 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Operation Codec Contractが追加される
- [x] Codec済みMessageがType ID、Schema Version、Payload、Contextを保持する
- [x] Reflection JSON CodecでOperationValueをEncode / Decodeできる
- [x] Reflection JSON CodecでExecutionContextをEncode / Decodeできる
- [x] PHP `serialize()` に依存しない
- [x] Unsupported Value Shapeは明示的な例外で拒否される
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- HTTP 202 Response変換は未実装。
- Deferred Acceptance OrchestratorとのHTTP接続は未実装。
- Worker RuntimeでのOperation Envelope復元は未実装。
- Upcaster ChainとPayload Encryptionは未実装。

## Suggested Next Action

HTTP入口からDeferred Operationを受け付け、Operation CodecでMessage化し、Deferred Acceptance Orchestratorへ渡して`DeferredAcknowledgement`をHTTP 202 Responseへ変換する。
