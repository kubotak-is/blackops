# P2-003: Observer Aggregator

Status: Accepted

## Goal

複数JournalObserverを独立して実行し、Delivery Policyに従ってObserver失敗を扱うInternal Aggregatorを追加する。

## In Scope

- Public `JournalDeliveryPolicy` enumを追加する
- Internal Observer Bindingを追加する
- Internal Observer Aggregatorを追加する
- BestEffort observer failureはOperation継続可能として集約されることを検証する
- Required/Durable observer failureはAggregatorが失敗として扱うことを検証する
- Flushable observerをflushできることを検証する
- Journal Ports Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Operation Definitionの `#[JournalDelivery]` Attribute
- Manifest CompilerへのDelivery Policy検証接続
- Inline DispatcherへのObserver配信接続
- PSR-3 Logger decorator実装
- JSONL/OTel/CloudWatch Adapter実装
- Durable Local Store/Outbox実装

## Relevant Specifications

- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/26-journal-ports.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Journal/**`
- `src/Internal/Journal/**`
- `tests/Journal/**`
- `tests/Internal/Journal/**`
- `docs/internals/**`
- `develop/orchestration/tasks/P2-003-observer-aggregator.md`
- `develop/orchestration/reports/P2-003-observer-aggregator.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Public APIへInternal型を露出しない
- Observer同士は独立して実行し、1つのObserver失敗で残りのObserver実行を止めない
- Durable PolicyでMemory Bufferだけを成功扱いにする実装はしない

## Acceptance Criteria

- [x] `JournalDeliveryPolicy` がPublic APIとして追加される
- [x] BestEffort observer failureはAggregatorから例外として漏れない
- [x] Required observer failureはAggregatorが例外として扱う
- [x] Durable observer failureはAggregatorが例外として扱う
- [x] 失敗Observerがあっても他Observerの実行を継続する
- [x] Flushable observerだけをflushできる
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

`develop/orchestration/reports/P2-003-observer-aggregator.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
