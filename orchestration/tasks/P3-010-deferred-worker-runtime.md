# P3-010: Deferred Worker Runtime

Status: Accepted

## Goal

WorkerがClaim済みOperationをDecodeし、Attemptを開始してHandlerを実行し、成功または業務RejectをOperation StateとCanonical Journalへ反映できるようにする。

## In Scope

- Internal Deferred Worker Runtimeを追加する
- Claim済みMessageをOperationValue / ExecutionContextへDecodeする
- Operation DefinitionをMetadataから復元する
- Attempt開始時に`attempt.started` JournalとOperation State / Sequenceを同一Transactionで更新する
- Handler成功時に`attempt.succeeded` と `operation.completed` Journal、Operation State / Sequenceを同一Transactionで更新する
- Handler業務Reject時に`operation.rejected` Journal、Operation State / Sequenceを同一Transactionで更新する
- PostgreSQL Fencing Tokenを検証するState更新Storeを追加する
- PostgreSQL Integration Test、Documentation、Task Report、STATEを更新する

## Out of Scope

- Handler例外のRetry / Failure / Dead Letter
- Heartbeat
- Claim Settlement acknowledge / release
- Outcome取得用Outcomes Table
- Worker Loop / CLI Command
- Lease Expired Recovery

## Relevant Specifications

- `spec/28-mvp-lifecycle-events.md`
- `spec/30-lifecycle-state-machine.md`
- `spec/31-deferred-claim-and-attempt.md`
- `spec/33-execution-transport-contract.md`
- `spec/36-postgresql-transaction-boundaries.md`
- `decisions/034-mvp-lifecycle-events.md`
- `decisions/036-lifecycle-state-machine.md`
- `decisions/037-deferred-claim-and-attempt.md`
- `decisions/042-postgresql-transaction-boundaries.md`

## Files Allowed to Change

- `src/Internal/Execution/**`
- `src/Transport/PostgreSql/**`
- `tests/Internal/Execution/**`
- `tests/Transport/PostgreSql/**`
- `docs/internals/**`
- `orchestration/tasks/P3-010-deferred-worker-runtime.md`
- `orchestration/reports/P3-010-deferred-worker-runtime.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Handler実行中にDatabase Transactionを保持しない
- Attempt開始BoundaryとResult反映BoundaryではState、Sequence、Canonical Journalを同一Transactionで更新する
- Fencing Tokenが一致しないClaimからのState更新を拒否する
- Retry / Crash Recovery判断はこのTaskで実装しない

## Acceptance Criteria

- [x] Deferred Worker Runtimeが追加される
- [x] Claim済みMessageをOperationValue / ExecutionContextへDecodeできる
- [x] Attempt開始時に`attempt.started` Journalが保存される
- [x] Handler成功時に`attempt.succeeded` と `operation.completed` Journalが保存される
- [x] Handler業務Reject時に`operation.rejected` Journalが保存される
- [x] 成功時にOperation Stateが`completed`へ更新される
- [x] 業務Reject時にOperation Stateが`rejected`へ更新される
- [x] Sequenceが受付後の次Sequenceから継続する
- [x] Handler実行中にDatabase Transactionを保持しない
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

`orchestration/reports/P3-010-deferred-worker-runtime.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
