# P4-001: Attempt Failed Boundary

Status: Accepted

## Goal

Deferred WorkerのHandler例外を捕捉し、`attempt.failed` JournalとOperation State更新を同一Transactionで記録して、OperationをSupervising Stateへ進める。

## In Scope

- `AttemptFailedData`を追加する
- Journal Data Codecへ`AttemptFailedData`対応を追加する
- JournalRecordFactoryへ`attempt.failed`生成を追加する
- PostgreSQL Lifecycle StoreへFencing付きFailure予約を追加する
- Deferred Worker RuntimeでHandler例外時に`attempt.failed`を記録する
- Handler例外は記録後に再throwする
- PostgreSQL Integration Test、Documentation、Task Report、STATEを更新する

## Out of Scope

- Supervision Policy Contract
- Retry Scheduling
- Operation Failed / Dead Letter
- Heartbeat
- Lease Expired Recovery
- Worker Loop / CLI Command

## Relevant Specifications

- `spec/28-mvp-lifecycle-events.md`
- `spec/30-lifecycle-state-machine.md`
- `spec/32-worker-crash-recovery.md`
- `spec/36-postgresql-transaction-boundaries.md`
- `decisions/007-supervision-policy.md`
- `decisions/034-mvp-lifecycle-events.md`
- `decisions/036-lifecycle-state-machine.md`
- `decisions/038-worker-crash-recovery.md`
- `decisions/042-postgresql-transaction-boundaries.md`

## Files Allowed to Change

- `src/Journal/**`
- `src/Internal/Journal/**`
- `src/Internal/Execution/**`
- `src/Transport/PostgreSql/**`
- `tests/Journal/**`
- `tests/Internal/Journal/**`
- `tests/Internal/Execution/**`
- `tests/Transport/PostgreSql/**`
- `docs/internals/**`
- `orchestration/tasks/P4-001-attempt-failed-boundary.md`
- `orchestration/reports/P4-001-attempt-failed-boundary.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Handler例外は`attempt.failed`記録後に再throwする
- Failure BoundaryではFencing Tokenを検証する
- Handler実行中にDatabase Transactionを保持しない
- Retry判断はこのTaskで行わない

## Acceptance Criteria

- [x] `AttemptFailedData`が追加される
- [x] Journal Data Codecが`AttemptFailedData`を永続化 / 復元できる
- [x] Handler例外時に`attempt.failed` Journalが保存される
- [x] Handler例外時にOperation Stateが`supervising`へ更新される
- [x] Handler例外時にSequenceが継続する
- [x] Handler例外は記録後に再throwされる
- [x] Fencing Token不一致時はFailure更新が拒否される
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
git diff --check
```

## Expected Report

`orchestration/reports/P4-001-attempt-failed-boundary.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
