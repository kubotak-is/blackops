# P2-005: JSONL Journal Observer Report

Status: Accepted

## Summary

ObservedJournalRecordをline-delimited JSONへ変換してstreamへ出力できるJSONL Journal Observerを追加した。

`JsonlJournalObserver` は `ObservedJournalRecord` だけを受け取り、`JsonlJournalRecordEncoder` が `kind: journal` の構造化JSON envelopeへ変換する。TimestampはUTC microseconds + `Z` 形式へ正規化する。

## Changed Files

- `src/Logging/JsonlJournalObserver.php`
- `src/Logging/JsonlJournalRecordEncoder.php`
- `tests/Logging/JsonlJournalObserverTest.php`
- `docs/internals/jsonl-journal-observer.md`
- `docs/internals/README.md`
- `develop/orchestration/tasks/P2-005-jsonl-journal-observer.md`
- `develop/STATE.md`

## Decisions and Assumptions

- JSONL ObserverはPublic APIとして `BlackOps\Logging` に置いた。
- 出力先はcaller-provided stream resourceとした。file path configやRuntime Composer接続は後続Taskへ分離する。
- EncoderもPublic APIにし、出力schemaを単体検証・再利用できるようにした。
- ObserverはRaw `JournalRecord` / Raw `JournalData` を受け取らず、ObservedJournalRecordだけを扱う。
- write/flush失敗とJSON encode失敗は `JournalObservationFailed` に変換する。
- PSR-3 Logger decorator、Execution Scope metadata接続、Monolog integration、OTel/CloudWatch Adapterは後続Taskへ送る。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter JsonlJournalObserverTest
Result: OK (4 tests, 19 assertions).

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (297 tests, 679 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 462 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] JSONL Journal ObserverがPublic APIとして追加される
- [x] JSONL EncoderがPublic APIとして追加される
- [x] ObservedJournalRecordが `kind: journal` のJSON envelopeへ変換される
- [x] TimestampがUTC microseconds + `Z` 形式で出力される
- [x] `observe()` が1 record 1 lineを書き込む
- [x] `flush()` がstreamをflushする
- [x] write/flush失敗時に `JournalObservationFailed` を投げる
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- Runtime ComposerからJSONL Observerを構成する入口は未実装。
- File path configは未実装。
- PSR-3 Logger decorator、Execution Scope metadata接続、Monolog integrationは未実装。
- OTel/CloudWatch Adapterは未実装。

## Suggested Next Action

Execution Scope Providerを追加し、Operation実行境界のcurrent contextをLogger decoratorから参照できる土台を作る。
