# P2-001: Sensitive Projection Foundation Report

Status: Accepted

## Summary

Sensitive Projectionの基礎として、Public APIの `#[Sensitive]` Attributeと `SensitiveMode` enum、InternalのProjection Filterを追加した。

Object public propertyはAttribute Metadataに基づいてOmit/Mask/Hashできる。Array projectionはlogger contextやexternal error details向けの防御的fallbackとして、password/token/secret系の予約KeyをOmitする。

Hash modeはHMAC-SHA-256のみを実装し、HMAC keyがない場合は失敗する。

## Changed Files

- `src/Core/Attribute/Sensitive.php`
- `src/Core/Attribute/SensitiveMode.php`
- `src/Internal/Projection/SensitiveProjectionFilter.php`
- `src/Internal/Projection/SensitiveKeyMatcher.php`
- `src/Internal/Projection/SensitiveValueHasher.php`
- `tests/Core/Attribute/SensitiveAttributeTest.php`
- `tests/Internal/Projection/SensitiveProjectionFilterTest.php`
- `docs/internal/sensitive-projection.md`
- `docs/internal/README.md`
- `develop/orchestration/tasks/P2-001-sensitive-projection-foundation.md`
- `develop/STATE.md`

## Decisions and Assumptions

- `#[Sensitive]` はpublic propertyのみを対象にした。現在のprojection境界はpublic DTO/value objectを安全にObserver/Loggingへ渡すための基礎であり、private stateの強制抽出は行わない。
- `SensitiveMode::Omit` を既定値にした。明示されていないsensitive propertyは出力しない。
- `Mask` は固定tokenへ置換する。元値の長さや型を推測できる情報は残さない。
- `Hash` はHMAC-SHA-256のみを使い、HMAC keyなしではprojectionを失敗させる。平文hash fallbackは実装しない。
- Array projectionの予約Key fallbackは大文字小文字を無視した部分一致にした。
- Observer Port、ObservedJournalRecord、PSR-3 Logger decorator、Execution Scope、Adapter固有redactor、Canonical Journal StoreのRaw Payload保存方針は後続Taskへ送る。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'SensitiveAttributeTest|SensitiveProjectionFilterTest'
Result: OK (6 tests, 14 assertions).

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (281 tests, 627 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 419 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] `#[Sensitive]` AttributeがPublic APIとして追加される
- [x] `SensitiveMode` enumがPublic APIとして追加される
- [x] `#[Sensitive]` の既定ModeはOmitである
- [x] Object public propertyをOmit/Mask/Hashできる
- [x] Array key fallback patternで予約KeyをOmitできる
- [x] Hashは秘密鍵付きHMACである
- [x] Sensitive Projection Internals Documentationが更新される
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- Observer projection boundaryへはまだ接続していない。
- PSR-3 logger decoratorとadapter-specific redactorは未実装。
- Execution Scope由来のmetadataはまだprojection対象に含めていない。

## Suggested Next Action

ObservedJournalRecordとJournalObserver portsを追加し、Canonical JournalからObserverへ渡すsafe projection boundaryを作る。
