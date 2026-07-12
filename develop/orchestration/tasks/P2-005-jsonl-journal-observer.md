# P2-005: JSONL Journal Observer

Status: Accepted

## Goal

ObservedJournalRecordをline-delimited JSONへ変換して出力できるJSONL Journal Observerを追加する。

## In Scope

- Public JSONL Journal Observerを追加する
- Public JSONL Journal Record Encoderを追加する
- ObservedJournalRecordを構造化JSON envelopeへ変換する
- UTC microseconds timestamp形式で出力する
- ObserverはRaw JournalDataではなくObservedJournalRecordだけを受け取る
- stream write/flush失敗時にJournalObservationFailedを投げる
- Logging Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- PSR-3 Logger decorator実装
- Execution Scope metadata接続
- JSONL file path config
- Monolog integration
- OTel/CloudWatch Adapter実装
- Runtime ComposerからObserverを構成する入口

## Relevant Specifications

- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/21-clock-and-time.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/26-journal-ports.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Logging/**`
- `tests/Logging/**`
- `docs/internals/**`
- `develop/orchestration/tasks/P2-005-jsonl-journal-observer.md`
- `develop/orchestration/reports/P2-005-jsonl-journal-observer.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Public APIへInternal型を露出しない
- ObserverへCanonical `JournalRecord` やRaw `JournalData` を渡さない
- JSONLは1 record 1 lineとする
- TimestampはUTC RFC 3339 microseconds + `Z` 形式にする

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

## Required Commands

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
```

## Expected Report

`develop/orchestration/reports/P2-005-jsonl-journal-observer.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
