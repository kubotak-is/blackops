# P4-004: PostgreSQL Worker Heartbeat

Status: Completed

## Goal

PostgreSQL Deferred OperationのRunning LeaseをWorkerがHeartbeatで延長できるようにする。

## In Scope

- `PostgreSqlDeferredOperationReceiver`に`ClaimHeartbeat`実装を追加する
- Heartbeat時にClaim TokenとFencing Tokenを検証する
- Heartbeat時にLease期限と更新時刻を延長する
- Unit Testと内部Documentationを更新する

## Out of Scope

- Claim Settlement
- Lease Expired Recovery
- Graceful Shutdown
- Signal Handling
- Stale Worker Metric

## Relevant Specifications

- `develop/spec/32-worker-crash-recovery.md`
- `develop/spec/33-execution-transport-contract.md`
- `develop/decisions/038-worker-crash-recovery.md`
- `develop/decisions/039-execution-transport-contract.md`

## Files Allowed to Change

- `src/Transport/PostgreSql/**`
- `tests/Transport/PostgreSql/**`
- `docs/internal/**`
- `develop/TODO.md`
- `develop/orchestration/tasks/P4-004-postgresql-worker-heartbeat.md`
- `develop/orchestration/reports/P4-004-postgresql-worker-heartbeat.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Heartbeat失敗後の完了更新禁止やLease Expired Recoveryは後続Taskで扱う

## Acceptance Criteria

- [x] PostgreSQL Receiverが`ClaimHeartbeat`を実装している
- [x] HeartbeatがRunning OperationのLeaseを延長する
- [x] HeartbeatがStale Fencing Tokenを拒否する
- [x] HeartbeatがRunning以外のOperationを拒否する
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

`develop/orchestration/reports/P4-004-postgresql-worker-heartbeat.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
