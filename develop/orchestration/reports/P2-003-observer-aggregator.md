# P2-003: Observer Aggregator Report

Status: Accepted

## Summary

複数JournalObserverを独立して実行し、Delivery Policyに従ってObserver失敗を扱うInternal Aggregatorを追加した。

`JournalDeliveryPolicy` はPublic APIとして `BestEffort`、`Required`、`Durable` を表現する。Internal Aggregatorは、BestEffort失敗をOperation継続可能な集約結果として返し、Required/Durable失敗は全Observer試行後に `JournalObservationFailed` として扱う。

Flushは `FlushableJournalObserver` のみを対象にする。

## Changed Files

- `src/Journal/JournalDeliveryPolicy.php`
- `src/Internal/Journal/JournalObserverBinding.php`
- `src/Internal/Journal/JournalObserverAggregator.php`
- `src/Internal/Journal/JournalObservationResult.php`
- `src/Internal/Journal/JournalObserverFailure.php`
- `tests/Journal/JournalPortTest.php`
- `tests/Internal/Journal/JournalObserverAggregatorTest.php`
- `docs/internals/journal-ports.md`
- `develop/orchestration/tasks/P2-003-observer-aggregator.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Delivery Policy enumは将来のOperation Definition AttributeやManifest Compilerから参照されるためPublic APIにした。
- Observer Binding、Aggregator、Result、Failureは内部実装境界として `BlackOps\Internal\Journal` に置いた。
- BestEffort observer failureは例外として漏らさず、結果のfailureとして返す。
- Required/Durable observer failureは他Observerを試行した後で `JournalObservationFailed` として扱う。
- Aggregatorは現時点では `JournalObservationFailed` をObserver失敗として捕捉する。任意の `Throwable` をObserver失敗へ変換するかは後続Taskで判断する。
- Operation Definition Attribute、Manifest Compiler検証、Inline Dispatcher接続、Durable Store/Outbox実装は後続Taskへ送る。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'JournalObserverAggregatorTest|JournalPortTest'
Result: OK (5 tests, 21 assertions).

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (289 tests, 652 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 445 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] `JournalDeliveryPolicy` がPublic APIとして追加される
- [x] BestEffort observer failureはAggregatorから例外として漏れない
- [x] Required observer failureはAggregatorが例外として扱う
- [x] Durable observer failureはAggregatorが例外として扱う
- [x] 失敗Observerがあっても他Observerの実行を継続する
- [x] Flushable observerだけをflushできる
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- Inline DispatcherへのObserver配信接続は未実装。
- Operation Definition AttributeとManifest CompilerによるDelivery Policy検証は未実装。
- Durable Store/Outboxは未実装。
- JSONL/OTel/CloudWatch AdapterとPSR-3 Logger decoratorは未実装。

## Suggested Next Action

Inline Journal生成後に、Canonical Journalへのappend成功後、ObservedJournalRecordへprojectしてObserver AggregatorへBestEffort配送する接続を追加する。
