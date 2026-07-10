# P5-008: PostgreSQL Transport Payload Tombstone

Status: Completed

## Goal

Retention Planに基づき、PostgreSQL Operations TableのTransport Payloadを安全にTombstone化できるようにする。

## In Scope

- PostgreSQL Transport Payload Tombstone Service
- Purge Audit書き込み連携
- 実行時のTerminal State / 未Tombstone / Active Hold再確認
- Integration Test
- 内部Documentation更新

## Out of Scope

- Dead Letter削除
- Canonical Journal削除
- Outcome削除
- System Log配送
- Retention CLI
- Framework Maintenance Scheduler Worker

## Relevant Specifications

- `spec/38-data-retention-and-deletion.md`
- `spec/39-retention-runtime.md`
- `decisions/044-data-retention-and-deletion.md`
- `decisions/045-retention-mvp-scope.md`
- `orchestration/tasks/P5-007-retention-plan-contract-and-postgresql-planner.md`

## Files Allowed to Change

- `src/Transport/PostgreSql/**`
- `tests/Transport/PostgreSql/**`
- `docs/internals/**`
- `orchestration/tasks/P5-008-postgresql-transport-payload-tombstone.md`
- `orchestration/reports/P5-008-postgresql-transport-payload-tombstone.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- Transport Payload TombstoneではOperations行を削除しない
- Encoded Payload / Encoded ContextだけをNULL化し、`payload_purged_at` を記録する
- Terminal OperationだけをTombstone化する
- Active Hold中のOperationは実行時にも除外する
- 成功したTombstoneだけPurge Auditへ記録する

## Acceptance Criteria

- [x] Plan内のTransport Payload候補をTombstone化できる
- [x] Plan内の非Transport Payload候補は無視される
- [x] Terminal State、未Tombstone、Active Holdなしを実行時にも再確認する
- [x] 成功したTombstoneについてPurge Auditが記録される
- [x] PayloadやContextはAuditへ保存されない
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

`orchestration/reports/P5-008-postgresql-transport-payload-tombstone.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
