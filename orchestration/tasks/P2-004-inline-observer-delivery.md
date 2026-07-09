# P2-004: Inline Observer Delivery

Status: Accepted

## Goal

Inline DispatcherでCanonical Journal append成功後に、ObservedJournalRecordへprojectしてObserver Aggregatorへ配送する。

## In Scope

- Inline DispatcherへObserver Aggregator接続を追加する
- Canonical append成功後にのみObserver deliveryする
- Observer未設定時はObserved projectionを実行しない
- Observer deliveryではSensitive Projection済みdataだけが渡ることを検証する
- BestEffort observer failureでInline dispatchが継続することを検証する
- Task Report、STATEを更新する

## Out of Scope

- Operation Definitionの `#[JournalDelivery]` Attribute
- Manifest CompilerへのDelivery Policy検証接続
- PSR-3 Logger decorator実装
- JSONL/OTel/CloudWatch Adapter実装
- Durable Local Store/Outbox実装
- Execution Scope接続

## Relevant Specifications

- `spec/10-logging-and-traceability.md`
- `spec/25-sensitive-projection.md`
- `spec/26-journal-ports.md`
- `spec/36-postgresql-transaction-boundaries.md`
- `spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/Execution/**`
- `src/Internal/Journal/**`
- `src/Internal/Projection/**`
- `tests/Internal/Execution/**`
- `tests/Internal/Journal/**`
- `docs/internals/**`
- `orchestration/tasks/P2-004-inline-observer-delivery.md`
- `orchestration/reports/P2-004-inline-observer-delivery.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Public APIへInternal型を露出しない
- ObserverへCanonical `JournalRecord` やRaw `JournalData` を渡さない
- Observer未設定時にprojection副作用を発生させない
- Canonical append失敗時にObserver deliveryしない

## Acceptance Criteria

- [x] Inline DispatcherがCanonical append成功後にObserver deliveryする
- [x] Observerへ `ObservedJournalRecord` が渡る
- [x] Sensitive propertyはObserver Recordのdataへ漏れない
- [x] Observer未設定時はprojectionを実行しない
- [x] Canonical append失敗時はObserver deliveryしない
- [x] BestEffort observer failureではdispatchが継続する
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

`orchestration/reports/P2-004-inline-observer-delivery.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
