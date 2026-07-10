# P4-006: Claim Settlement

Status: Completed

## Goal

`ClaimSettlement` のPostgreSQL実装方針を確定し、WorkerがClaimを明示的にacknowledge / releaseできるTransport境界を実装する。

## In Scope

- PostgreSQL Deferred Transportの`ClaimSettlement`実装
- Claim TokenとFencing Tokenの検証
- `acknowledge()` / `release()` のState遷移
- Unit Testと内部Documentation更新

## Out of Scope

- Worker Loop / CLI Command
- Signal Handling
- Stale Worker Metric
- Attempt開始前Crashの自動復旧
- Outcome保存API

## Relevant Specifications

- `spec/31-deferred-claim-and-attempt.md`
- `spec/32-worker-crash-recovery.md`
- `spec/33-execution-transport-contract.md`
- `decisions/037-deferred-claim-and-attempt.md`
- `decisions/038-worker-crash-recovery.md`
- `decisions/039-execution-transport-contract.md`

## Files Allowed to Change

- `src/Core/Execution/**`
- `src/Transport/PostgreSql/**`
- `tests/Core/Execution/**`
- `tests/Transport/PostgreSql/**`
- `docs/internals/**`
- `spec/33-execution-transport-contract.md`
- `TODO.md`
- `orchestration/tasks/P4-006-claim-settlement.md`
- `orchestration/reports/P4-006-claim-settlement.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Claim TokenはHandlerへ渡さない
- Stale Fencing Tokenは最新WorkerのStateを上書きしない

## Resolved Decision

`ClaimSettlement` のPostgreSQL Semanticsは次の方針で確定した。

- `ClaimSettlement` は低レベルTransport Portとして扱い、Journal Eventは発行しない
- `acknowledge()` はTerminal StateかつClaim Token一致を確認する
- `release()` はAttempt開始前のRunning Claimだけを`accepted`へ戻し、`available_at`を引数で更新する
- `release()` は`current_attempt_id`があるRunning Operationを拒否する
- Completion / Failure / Retry / Dead Letterは引き続きLifecycle StoreがJournal込みで確定する

## Acceptance Criteria

- [x] `acknowledge()` / `release()` のPostgreSQL State Semanticsが確定している
- [x] Claim TokenとFencing Tokenが検証される
- [x] Stale ClaimからのSettlementが拒否される
- [x] `ClaimSettlement` がPostgreSQL Transportで実装される
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

`orchestration/reports/P4-006-claim-settlement.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
