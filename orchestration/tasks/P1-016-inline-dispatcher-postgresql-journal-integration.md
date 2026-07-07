# P1-016: Inline Dispatcher PostgreSQL Journal Integration

Status: Accepted

## Goal

InlineDispatcherへPostgreSQL Canonical Journal Storeを注入し、Completed／RejectedのLifecycle Journal列がDBへ永続化されることを検証する。

## In Scope

- InlineDispatcher + PostgreSQLCanonicalJournalStoreの統合Testを追加する
- Completed列がDBに `operation.received` → `attempt.started` → `attempt.succeeded` → `operation.completed` の順で保存されることを検証する
- Rejected列がDBに `operation.received` → `attempt.started` → `operation.rejected` の順で保存されることを検証する
- DBから読み戻した `JournalRecord` のSequence、Operation Type、Event Dataを検証する
- DocumentationとOrchestration状態を更新する

## Out of Scope

- DI Container／Runtime wiring本体
- HTTP `GET /welcome`
- PostgreSQL `operations` Table
- Deferred Strategy
- Codec本格化、暗号化、Upcaster Chain

## Relevant Specifications

- `spec/26-journal-ports.md`
- `spec/27-journal-sequence-allocation.md`
- `spec/28-mvp-lifecycle-events.md`
- `spec/30-lifecycle-state-machine.md`
- `spec/36-postgresql-transaction-boundaries.md`
- `spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `tests/Transport/**`
- `docs/internals/**`
- `orchestration/tasks/P1-016-inline-dispatcher-postgresql-journal-integration.md`
- `orchestration/reports/P1-016-inline-dispatcher-postgresql-journal-integration.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Production起動時に暗黙DDLを実行しない

## Acceptance Criteria

- [ ] Inline Completedの4 EventがPostgreSQL Journalへ保存される
- [ ] Inline Rejectedの3 EventがPostgreSQL Journalへ保存される
- [ ] DBから読み戻したRecordがSequence順である
- [ ] DBから読み戻したRecordのOperation TypeがMetadata由来である
- [ ] Completed DataとRejected DataがDB往復後も復元される
- [ ] Formatterを含む必須品質Commandが成功する
- [ ] PHP Comment／DocBlockに管理番号を含めない

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

`orchestration/reports/P1-016-inline-dispatcher-postgresql-journal-integration.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
