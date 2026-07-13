# P2-002: Observed Journal Ports Report

Status: Accepted

## Summary

Canonical JournalRecordとは別に、Observerへ渡す安全なProjection専用RecordとObserver Portを追加した。

`ObservedJournalRecord` はCanonical Recordと同じ論理Envelopeを保持するが、dataはprojection済みarrayであり、Raw `JournalData` objectを保持しない。Internalの `ObservedJournalRecordProjector` はCanonical `JournalRecord` を受け取り、Sensitive Projection Filterを通して `ObservedJournalRecord` を生成する。

## Changed Files

- `src/Journal/ObservedJournalRecord.php`
- `src/Journal/JournalObserver.php`
- `src/Journal/FlushableJournalObserver.php`
- `src/Journal/Exception/JournalObservationFailed.php`
- `src/Internal/Projection/ObservedJournalRecordProjector.php`
- `tests/Journal/ObservedJournalRecordTest.php`
- `tests/Journal/JournalPortTest.php`
- `tests/Internal/Projection/ObservedJournalRecordProjectorTest.php`
- `docs/internal/journal-ports.md`
- `develop/orchestration/tasks/P2-002-observed-journal-ports.md`
- `develop/STATE.md`

## Decisions and Assumptions

- `ObservedJournalRecord` はPublic APIとし、`JournalRecord` と同じEnvelope情報を持たせた。
- `ObservedJournalRecord::$data` は `array<string, mixed>` とし、Raw `JournalData` 型を保持しない型契約にした。
- `JournalObserver` は成功時にvoidを返す。Observer失敗は `JournalObservationFailed` で表現する。
- Buffer型Observerのflush capabilityは `FlushableJournalObserver` としてPortを分離した。
- Canonical `JournalRecord` からObserver Recordへの変換はInternal projectorへ集約した。
- Observer Aggregator、Delivery Policy、Logger decorator、Adapter実装、Execution Scope接続は後続Taskへ送る。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'ObservedJournalRecordTest|JournalPortTest|ObservedJournalRecordProjectorTest'
Result: OK (5 tests, 20 assertions).

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (285 tests, 641 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 428 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] `ObservedJournalRecord` がPublic APIとして追加される
- [x] `JournalObserver` がPublic APIとして追加される
- [x] `FlushableJournalObserver` がPublic APIとして追加される
- [x] Observer失敗用ExceptionがPublic APIとして追加される
- [x] `ObservedJournalRecord` はRaw `JournalData` を保持しない
- [x] Canonical `JournalRecord` からsafe projectionを生成できる
- [x] Sensitive propertyはObserver Recordのdataへ漏れない
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- Observer AggregatorとDelivery Policyは未実装。
- Observerへの自動配信はまだInline Dispatcherへ接続していない。
- PSR-3 Logger decorator、JSONL/OTel/CloudWatch Adapter、Execution Scope metadata接続は未実装。

## Suggested Next Action

Observer Aggregatorを追加し、JournalObserverの失敗をBestEffort/Required/Durable policyで扱うための内部境界を作る。
