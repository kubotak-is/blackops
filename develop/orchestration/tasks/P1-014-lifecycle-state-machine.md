# P1-014: Lifecycle State Machine

Status: Accepted

## Goal

Lifecycle Eventの遷移表を実装し、不正なJournal列をRecord生成前に拒否できるようにする。

## In Scope

- MVP Lifecycle Stateを表すEnumを追加する
- 不正遷移を表すExceptionを追加する
- 標準Lifecycle Eventの遷移表を実装する
- InlineDispatcherへExecution Scope上のState遷移を統合する
- State MachineとInlineDispatcherのUnit Testを追加する
- Framework実装者向けDocumentationを更新する

## Out of Scope

- PostgreSQL上の永続Operation State
- JournalからのState再構築器
- Retry、Dead Letter、Worker Supervision本体
- Critical System Log出力

## Relevant Specifications

- `develop/spec/02-lifecycle-and-journal.md`
- `develop/spec/23-journal-record-api.md`
- `develop/spec/28-mvp-lifecycle-events.md`
- `develop/spec/30-lifecycle-state-machine.md`

## Files Allowed to Change

- `src/Journal/**`
- `src/Internal/Journal/**`
- `src/Internal/Execution/InlineDispatcher.php`
- `tests/Journal/**`
- `tests/Internal/Journal/**`
- `tests/Internal/Execution/InlineDispatcherTest.php`
- `docs/internal/**`
- `develop/orchestration/tasks/P1-014-lifecycle-state-machine.md`
- `develop/orchestration/reports/P1-014-lifecycle-state-machine.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- 不正遷移ではJournal Recordを生成しない

## Acceptance Criteria

- [ ] 標準Lifecycle Eventの許可遷移がすべて表現されている
- [ ] 初期状態からは `operation.received` だけが許可される
- [ ] Terminal Stateから新しいLifecycle Eventが拒否される
- [ ] Inline Completed列がState Machineを通過する
- [ ] Inline Rejected列がState Machineを通過する
- [ ] InlineDispatcherが不正なTerminal追加を生成しない構造になっている
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

`develop/orchestration/reports/P1-014-lifecycle-state-machine.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
