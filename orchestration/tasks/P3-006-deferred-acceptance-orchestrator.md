# P3-006: Deferred Acceptance Orchestrator

Status: Accepted

## Goal

Deferred受付時に、Operation State保存、`operation.received` Journal、`operation.accepted` Journal、次Sequence更新を同一DBAL TransactionでCommitするInternal Orchestratorを追加する。

## In Scope

- Internal Deferred Acceptance Orchestratorを追加する
- Deferred用 `operation.accepted` Journal Record生成を追加する
- Deferred受付時のJournal Sequenceを1, 2として保存し、Operation Stateの次Sequenceを3へ進める
- PostgreSQL Integration Testで同一Transaction成功を検証する
- Journal Record Factory TestをDeferredへ拡張する
- Deferred Transport / PostgreSQL Documentation、Task Report、STATEを更新する

## Out of Scope

- HTTP 202 Response変換
- Deferred Dispatcher統合
- Operation Codec実装
- Claim、Heartbeat、Acknowledge、Release実装
- Worker Runtime
- Doctrine Migrations Command実装

## Relevant Specifications

- `spec/27-journal-sequence-allocation.md`
- `spec/28-mvp-lifecycle-events.md`
- `spec/30-lifecycle-state-machine.md`
- `spec/33-execution-transport-contract.md`
- `spec/36-postgresql-transaction-boundaries.md`
- `decisions/034-mvp-lifecycle-events.md`
- `decisions/036-lifecycle-state-machine.md`
- `decisions/042-postgresql-transaction-boundaries.md`

## Files Allowed to Change

- `src/Internal/Execution/**`
- `src/Internal/Journal/**`
- `src/Transport/PostgreSql/**`
- `tests/Internal/Execution/**`
- `tests/Internal/Journal/**`
- `tests/Transport/PostgreSql/**`
- `docs/internals/**`
- `orchestration/tasks/P3-006-deferred-acceptance-orchestrator.md`
- `orchestration/reports/P3-006-deferred-acceptance-orchestrator.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Public APIへInternal型を露出しない
- 同じDBAL Connection / Transaction内でState保存とCanonical Journal保存を行う
- `operation.accepted` はDeferred受付に限定する
- Handlerは実行しない
- Observer配送は行わない

## Acceptance Criteria

- [x] Deferred Acceptance Orchestratorが追加される
- [x] Deferred受付成功時にOperation Stateが保存される
- [x] Deferred受付成功時に`operation.received` と `operation.accepted` Journalが保存される
- [x] Journal Sequenceが1, 2として保存される
- [x] Operation Stateの次Sequenceが3へ進む
- [x] 受付成功時に`DeferredAcknowledgement`が返る
- [x] Duplicate Operation ID等の失敗時にTransactionがRollbackされる
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

`orchestration/reports/P3-006-deferred-acceptance-orchestrator.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
