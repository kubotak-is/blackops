# P1-014: Lifecycle State Machine - Implementation Report

Status: Accepted

## Summary

Lifecycle State Machineを実装し、InlineDispatcherがJournal RecordをWriterへ渡す前にLifecycle Eventの遷移を検証するようにした。

不正遷移では `LifecycleTransitionException` を投げ、対象Recordはappendされない。

## Changed Files

- `src/Journal/LifecycleState.php` (add): MVP Operation State EnumとTerminal判定を追加。
- `src/Journal/Exception/LifecycleTransitionException.php` (add): 不正遷移用Exceptionを追加。
- `src/Internal/Journal/LifecycleStateMachine.php` (add): 標準Lifecycle Eventの遷移表を実装。
- `src/Internal/Execution/InlineDispatcher.php` (edit): Execution Scope上のState遷移をJournal append前に検証。
- `tests/Internal/Journal/LifecycleStateMachineTest.php` (add): 許可遷移、初期状態、Terminal拒否、Terminal判定を検証。
- `tests/Internal/Execution/InlineDispatcherTest.php` (edit): 不正Terminal追加がappendされないことを検証。
- `docs/internal/journal-record.md` (edit): Lifecycle State Machineの責務を追記。
- `docs/internal/inline-dispatcher.md` (edit): InlineDispatcherのState検証を追記。
- `develop/orchestration/tasks/P1-014-lifecycle-state-machine.md` (add): Task Packet。
- `develop/STATE.md` (edit): P1-014進行・完了状態へ更新。

## Decisions and Assumptions

- `LifecycleState` は後続の永続Operation Stateや診断にも使う可能性が高いため `BlackOps\Journal` に置いた。
- State Machine本体は実行時の内部Invariant検証なので `BlackOps\Internal\Journal` に置いた。
- 遷移表は分岐メソッドではなくテーブル駆動にした。Mago lintのKan defectを避けつつ、Event Wire NameとState Wire Nameを直接対応させるため。
- State Machine通過後にJournal Recordを生成し、State更新はJournal append成功後に行う。Writer失敗時にExecution Scope上のStateだけ進むことを避けるため。
- Critical System Log出力はLogging実装がまだないためOut of Scopeとして残した。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid。

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (178 tests, 415 assertions)。Runtime PHP 8.5.7。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 150 / Warnings 0 / Errors 0。

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] 標準Lifecycle Eventの許可遷移がすべて表現されている
- [x] 初期状態からは `operation.received` だけが許可される
- [x] Terminal Stateから新しいLifecycle Eventが拒否される
- [x] Inline Completed列がState Machineを通過する
- [x] Inline Rejected列がState Machineを通過する
- [x] InlineDispatcherが不正なTerminal追加を生成しない構造になっている
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- Critical System Log出力はLogging実装後に接続する。
- JournalからのState再構築器は未実装。
- Deferred実行時の永続Operation Stateとの統合は未実装。

## Suggested Next Action

PostgreSQL Canonical Journal StoreとMigration SQLの実装へ進む。

## Codex Review

Accepted at `2026-07-08T01:32:57+09:00`。
