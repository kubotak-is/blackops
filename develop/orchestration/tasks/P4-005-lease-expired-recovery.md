# P4-005: Lease Expired Recovery

Status: Completed

## Goal

Lease期限切れのRunning Operationを検出し、前Attemptを`lease_expired`の`attempt.failed`として閉じ、Supervision Policyへ渡せるようにする。

## In Scope

- Lease期限切れRunning Operationの検出
- 前Attemptの`attempt.failed`記録
- Supervision Policyへの接続
- Retry / Fail / Dead Letterへの遷移
- Unit Testと内部Documentation更新

## Out of Scope

- Signal Handling
- Graceful Shutdown
- Stale Worker Metric
- Claim Settlement

## Relevant Specifications

- `develop/spec/31-deferred-claim-and-attempt.md`
- `develop/spec/32-worker-crash-recovery.md`
- `develop/spec/33-execution-transport-contract.md`
- `develop/decisions/037-deferred-claim-and-attempt.md`
- `develop/decisions/038-worker-crash-recovery.md`

## Files Allowed to Change

- `src/Internal/Execution/**`
- `src/Internal/Journal/**`
- `src/Journal/**`
- `src/Transport/PostgreSql/**`
- `tests/Internal/**`
- `tests/Transport/PostgreSql/**`
- `docs/internal/**`
- `develop/spec/32-worker-crash-recovery.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P4-005-lease-expired-recovery.md`
- `develop/orchestration/reports/P4-005-lease-expired-recovery.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Attempt開始前にCrashしたClaimはAttemptとして数えない

## Acceptance Criteria

- [x] 失効Running AttemptのAttempt ID復元方式が確定している
- [x] Lease期限切れRunning Operationが`attempt.failed`として閉じられる
- [x] `lease_expired`が安全な構造化Errorとして保存される
- [x] Supervision Policyの判断に基づきRetry / Fail / Dead Letterへ遷移する
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

`develop/orchestration/reports/P4-005-lease-expired-recovery.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
