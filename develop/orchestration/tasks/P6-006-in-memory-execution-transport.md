# P6-006: InMemory Execution Transport

Status: Accepted

## Goal

Databaseへ依存しないUnit Test向け`InMemoryExecutionTransport`を実装し、Deferred enqueue、deterministic claim、lease / fencing、heartbeat、acknowledge、releaseをPublic Execution Transport Contractどおり検証できるようにする。

## In Scope

- `ExecutionTransport`を実装するInMemory Adapter
- Operation ID単位のenqueue重複拒否
- Message `availableAt`とClaim時刻に基づくEligibility
- `availableAt`、Operation ID順の決定的な一件Claim
- Claimごとの単調増加Fencing Token
- Lease期限切れ後の再Claim
- HeartbeatによるLease延長
- AcknowledgeによるTerminal Settlement
- Releaseによる指定時刻への再投入
- Stale / Unknown / Settled Claim Token拒否
- Test用Clock注入と明示Lease Duration
- Unit Test利用方法とPostgreSQL Adapterとの差分Documentation

## Out of Scope

- Durable Storage
- Process間共有
- Batch Claim
- Supervision Policy
- Worker Runtime変更
- Canonical Journal / Outcome Store
- Production Service Provider登録
- Public Contract変更

## Relevant Specifications

- `develop/spec/12-mvp-scope.md`
- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/31-deferred-claim-and-attempt.md`
- `develop/spec/32-worker-crash-recovery.md`
- `develop/spec/33-execution-transport-contract.md`
- `develop/spec/40-mvp-delivery-plan.md`
- `develop/decisions/017-mvp-scope.md`
- `develop/decisions/018-mvp-technical-stack.md`
- `develop/decisions/037-deferred-claim-and-attempt.md`
- `develop/decisions/038-worker-crash-recovery.md`
- `develop/decisions/039-execution-transport-contract.md`

## Files Allowed to Change

- `src/Transport/InMemory/**`
- `tests/Transport/InMemory/**`
- `docs/internals/in-memory-execution-transport.md`
- `docs/internals/deferred-transport-contract.md`
- `docs/internals/README.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P6-006-in-memory-execution-transport.md`
- `develop/orchestration/reports/P6-006-in-memory-execution-transport.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- InMemory AdapterをDurableまたはProcess-safeとして扱わない
- Claimは一回に一件だけ返す
- Claim TokenへPayloadやContext等の機密値を含めない
- Stale Fencing Tokenから現在Stateを変更させない
- 時刻比較は`DateTimeImmutable`の瞬間として行う
- PostgreSQL AdapterとPublic PortのSemanticsを変えない

## Acceptance Criteria

- [x] Adapterが`ExecutionTransport`を実装する
- [x] enqueueが同じOperation IDの重複を拒否する
- [x] 未来の`availableAt`を持つMessageは期限前にClaimされない
- [x] eligible Messageを`availableAt`、Operation ID順で一件Claimする
- [x] ClaimごとにFencing Tokenが単調増加する
- [x] Lease期限切れMessageを新しいTokenで再Claimできる
- [x] heartbeatが現在ClaimのLeaseを延長する
- [x] acknowledge済みMessageは再Claimされない
- [x] releaseしたMessageは指定時刻以後に再Claimできる
- [x] Stale / Unknown / Settled Claim操作が専用Exceptionで拒否される
- [x] Unit Test向け非Durable AdapterであることがDocumentationへ記録される
- [x] 必須Commandがすべて成功する

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit --filter InMemoryExecutionTransport
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

`develop/orchestration/reports/P6-006-in-memory-execution-transport.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
