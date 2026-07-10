# P4-003: Dead Letter Boundary

Status: Completed

## Goal

Deferred OperationをDead Letterへ隔離し、Operationsを`dead_lettered` Terminal Stateへ遷移させ、Dead Letters Tableと`operation.dead_lettered` Journalへ記録する。

## In Scope

- PostgreSQL Dead Letters TableのSchemaを実装する
- Dead Letter予約処理をLifecycle Storeへ追加する
- Supervision DecisionがDead Letterを返した場合のRuntime連携を実装する
- `operation.dead_lettered` Journal DataとCodecを実装する
- Unit Testと内部Documentationを更新する

## Out of Scope

- Manual Replay
- Dead Letter管理UI
- Retention Purge実装
- Lease Expired Recovery
- Heartbeat

## Relevant Specifications

- `spec/02-lifecycle-and-journal.md`
- `spec/03-execution.md`
- `spec/28-mvp-lifecycle-events.md`
- `spec/30-lifecycle-state-machine.md`
- `spec/37-postgresql-table-layout.md`
- `decisions/007-supervision-policy.md`
- `decisions/034-mvp-lifecycle-events.md`
- `decisions/043-postgresql-table-layout.md`

## Files Allowed to Change

- `src/Internal/Execution/**`
- `src/Internal/Journal/**`
- `src/Journal/**`
- `src/Transport/PostgreSql/**`
- `tests/Internal/**`
- `tests/Journal/**`
- `tests/Transport/PostgreSql/**`
- `docs/internals/**`
- `spec/03-execution.md`
- `spec/37-postgresql-table-layout.md`
- `TODO.md`
- `orchestration/tasks/P4-003-dead-letter-boundary.md`
- `orchestration/reports/P4-003-dead-letter-boundary.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Dead Lettered Operationへ`operation.failed`を併記しない
- Operations行をDead Letters Tableへ移動しない

## Acceptance Criteria

- [x] Dead Letters Tableの具体Schemaが確定している
- [x] Dead Letter Journal Dataの形が確定している
- [x] Supervision DecisionがDead Letterを返すと`operation.dead_lettered`だけがTerminal Eventとして記録される
- [x] Dead Letters Tableに調査用Recordが一対一で保存される
- [x] 必須Commandがすべて成功している

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`orchestration/reports/P4-003-dead-letter-boundary.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
