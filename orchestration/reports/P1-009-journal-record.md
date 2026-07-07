# P1-009: Journal Record - Implementation Report

Status: Accepted

## Summary

JournalRecord、JournalOperation、JournalAttemptと共通EnvelopeのInvariantを実装した。

## Commands and Results

- Composer Validate: 成功
- Mago Lint／Analyze: No issues found
- PHPUnit: OK (152 tests, 370 assertions)
- Deptrac: Violations 0、Uncovered 0、Allowed 72
- Comment Guardrail: 該当0件

JournalRecordの8必須Fieldに合わせ、MagoのConstructor専用Thresholdを8へ変更した。通常MethodのThresholdは変更していない。

## Acceptance Criteria

- [x] 3型がPublic final readonly classである
- [x] Schema Version、Sequence、Attempt番号を1以上に制限する
- [x] Type IDとStrategy Wire Nameを検証する
- [x] 時刻をUTCへ正規化する
- [x] JournalDataだけをDataとして受け付ける
- [x] 全品質CommandとComment Guardrailが成功する

## Remaining Issues

- Actor、Trace、Event固有Data、Factory、Codec

## Codex Review

Accepted at `2026-07-06T23:23:48+09:00`。
