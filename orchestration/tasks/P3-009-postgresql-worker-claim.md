# P3-009: PostgreSQL Worker Claim

Status: Accepted

## Goal

PostgreSQL TransportへWorker Claimを追加し、EligibleなAccepted Operationを`FOR UPDATE SKIP LOCKED`で1件取得してRunning State、Lease、Fencing Tokenを同一Transactionで更新できるようにする。

## In Scope

- PostgreSQL Operation State SchemaへClaim Metadata列を追加する
- PostgreSQL Operation Receiverを追加する
- `ClaimRequest`の基準時刻でEligible Operationを1件Claimする
- Claim成功時にStateを`running`へ更新し、Lease Owner、Lease期限、Fencing Token、State Versionを更新する
- Claim成功時に`OperationClaim`を返す
- Eligible Operationがない場合は`null`を返す
- PostgreSQL Integration Test、Documentation、Task Report、STATEを更新する

## Out of Scope

- Heartbeat
- Claim Settlement acknowledge / release
- Attempt開始Journal
- Worker Runtime
- Handler実行
- Lease Expired Recovery
- Retry Scheduling

## Relevant Specifications

- `spec/31-deferred-claim-and-attempt.md`
- `spec/32-worker-crash-recovery.md`
- `spec/33-execution-transport-contract.md`
- `spec/35-postgresql-transport-schema.md`
- `spec/36-postgresql-transaction-boundaries.md`
- `spec/37-postgresql-table-layout.md`
- `decisions/037-deferred-claim-and-attempt.md`
- `decisions/038-worker-crash-recovery.md`
- `decisions/039-execution-transport-contract.md`
- `decisions/040-mvp-database-transport.md`
- `decisions/041-postgresql-transport-schema.md`

## Files Allowed to Change

- `src/Transport/PostgreSql/**`
- `tests/Transport/PostgreSql/**`
- `docs/internals/**`
- `orchestration/tasks/P3-009-postgresql-worker-claim.md`
- `orchestration/reports/P3-009-postgresql-worker-claim.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Claimは短いDB Transaction内で完了し、Handler実行中にTransactionを保持しない
- Claim MetadataをExecutionContextや業務Handlerへ露出しない
- Claim対象は`accepted`または`retry_scheduled`かつ`available_at <= claimedAt`のOperationに限定する
- MVP Claimは1回に1件とする

## Acceptance Criteria

- [x] PostgreSQL Operation State SchemaにClaim Metadata列が追加される
- [x] PostgreSQL Operation Receiverが追加される
- [x] Eligible Operationを1件Claimできる
- [x] Claim成功時にStateが`running`へ更新される
- [x] Claim成功時にLease Owner、Lease期限、Fencing Token、State Versionが更新される
- [x] Claim成功時に`OperationClaim`が返る
- [x] Eligible Operationがない場合は`null`が返る
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

`orchestration/reports/P3-009-postgresql-worker-claim.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
