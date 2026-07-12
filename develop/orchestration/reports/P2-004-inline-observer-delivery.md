# P2-004: Inline Observer Delivery Report

Status: Accepted

## Summary

Inline DispatcherでCanonical Journal append成功後に、ObservedJournalRecordへprojectしてObserver Aggregatorへ配送する接続を追加した。

Observerが設定されている場合だけSensitive Projectionを実行し、ObserverへはRaw `JournalRecord` / Raw `JournalData` ではなく `ObservedJournalRecord` を渡す。Observer未設定時はprojectionを実行しないため、Observerが存在しない構成でHash modeのHMAC keyが未設定でもdispatchを妨げない。

## Changed Files

- `src/Internal/Execution/InlineDispatcher.php`
- `src/Internal/Journal/JournalObserverAggregator.php`
- `tests/Internal/Execution/InlineDispatcherTest.php`
- `docs/internals/journal-ports.md`
- `develop/orchestration/tasks/P2-004-inline-observer-delivery.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Inline Dispatcherの既存Constructor互換を維持するため、ObservedJournalRecordProjectorとJournalObserverAggregatorは後方のoptional依存として追加した。
- Observer未設定時はProjectionもObserver deliveryも行わない。不要なHMAC key要求やprojection副作用を避けるため。
- Canonical append成功後にのみObserver deliveryする。Canonical append失敗時はObserverへ渡さない。
- BestEffort observer failureはAggregatorで集約され、Inline dispatchを妨げない。
- Required/Durable policyのOperation Definition接続、Manifest Compiler検証、Runtime構成ファイル接続は後続Taskへ送る。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter InlineDispatcherTest
Result: OK (10 tests, 19 assertions).

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (293 tests, 660 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 446 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Inline DispatcherがCanonical append成功後にObserver deliveryする
- [x] Observerへ `ObservedJournalRecord` が渡る
- [x] Sensitive propertyはObserver Recordのdataへ漏れない
- [x] Observer未設定時はprojectionを実行しない
- [x] Canonical append失敗時はObserver deliveryしない
- [x] BestEffort observer failureではdispatchが継続する
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- Production Runtime ComposerからObserver Aggregatorを構成する入口は未実装。
- Operation Definition AttributeとManifest CompilerによるDelivery Policy検証は未実装。
- JSONL Logger Adapter、PSR-3 Logger decorator、Execution Scope metadata接続は未実装。

## Suggested Next Action

JSONL structured journal observerを追加し、ObservedJournalRecordをline-delimited JSONへ変換できるLogging adapter foundationを作る。
