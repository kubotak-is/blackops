# P2-002: Observed Journal Ports

Status: Accepted

## Goal

Canonical JournalRecordとは別に、Observerへ渡す安全なProjection専用RecordとObserver Portを追加する。

## In Scope

- Public `ObservedJournalRecord` を追加する
- Public `JournalObserver` Portを追加する
- Public `FlushableJournalObserver` Capabilityを追加する
- Observer失敗用のPublic Exceptionを追加する
- Internal projectorでCanonical `JournalRecord` から `ObservedJournalRecord` を生成する
- ProjectorがSensitive Projection Filterを使い、Raw PayloadをObserver Recordへ渡さないことを検証する
- Journal Ports Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Observer Aggregator実装
- Delivery Policy実装
- PSR-3 Logger decorator実装
- JSONL/OTel/CloudWatch Adapter実装
- Canonical Journal Storeの保存方針変更
- Execution Scope接続

## Relevant Specifications

- `spec/25-sensitive-projection.md`
- `spec/26-journal-ports.md`
- `spec/10-logging-and-traceability.md`
- `spec/15-source-layout.md`
- `spec/16-namespace-dependencies.md`
- `spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Journal/**`
- `src/Internal/Projection/**`
- `tests/Journal/**`
- `tests/Internal/Projection/**`
- `docs/internals/**`
- `orchestration/tasks/P2-002-observed-journal-ports.md`
- `orchestration/reports/P2-002-observed-journal-ports.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Public APIへInternal型を露出しない
- ObserverへCanonical `JournalRecord` やRaw `JournalData` を渡さない
- `ObservedJournalRecord` のdataはprojection済みarrayとし、JournalData型を保持しない
- Hashは秘密鍵付きHMACとし、平文Hashを実装しない

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

`orchestration/reports/P2-002-observed-journal-ports.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
