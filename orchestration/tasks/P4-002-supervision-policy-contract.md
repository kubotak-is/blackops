# P4-002: Supervision Policy Contract

Status: Completed

## Goal

Supervision Policy Contract、Supervision Decision、Retry Scheduling Dataを確定し、Deferred実行の失敗後にRetry、Fail、Dead Letterを判断できる境界を追加する。

## In Scope

- Supervision PolicyのPublic API Contractを実装する
- Supervision DecisionのPublic APIを実装する
- Retry予定を表すJournal Dataを実装する
- PostgreSQL Deferred OperationをRetry Scheduledへ遷移させる予約処理を実装する
- 必要なCodec、Factory、Lifecycle Store、Runtime連携を追加する
- Unit Testと必要な内部Documentationを更新する

## Out of Scope

- Dead Letter Transportの実体実装
- Manual Replay
- Lease Expired Recovery
- Inbox/Deduplication Store
- Operation固有Policy解決のManifest統合

## Relevant Specifications

- `spec/03-execution.md`
- `spec/24-lifecycle-event-data.md`
- `spec/28-mvp-lifecycle-events.md`
- `spec/30-lifecycle-state-machine.md`
- `spec/32-worker-crash-recovery.md`
- `decisions/007-supervision-policy.md`
- `decisions/030-lifecycle-event-data.md`
- `decisions/034-mvp-lifecycle-events.md`

## Files Allowed to Change

- `src/Core/**`
- `src/Internal/Execution/**`
- `src/Internal/Journal/**`
- `src/Journal/**`
- `src/Transport/PostgreSql/**`
- `tests/Core/**`
- `tests/Internal/**`
- `tests/Journal/**`
- `tests/Transport/PostgreSql/**`
- `docs/internals/**`
- `docs/guide/**`
- `spec/03-execution.md`
- `TODO.md`
- `orchestration/tasks/P4-002-supervision-policy-contract.md`
- `orchestration/reports/P4-002-supervision-policy-contract.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- 承認済みの既定Backoff値と最大Attempt回数だけをProduction Codeへ反映する
- Attempt Timeoutは後続Taskで扱う

## Acceptance Criteria

- [x] Supervision Policy ContractがPublic APIとして実装されている
- [x] Supervision DecisionがRetry、Fail、Dead Letterを型安全に表現できる
- [x] Retry Scheduling DataがJournal Dataとして実装されている
- [x] `attempt.retry_scheduled` がCodecとFactoryで扱える
- [x] PostgreSQL Lifecycle StoreがSupervisingからRetry Scheduledへ原子的に遷移できる
- [x] Deferred Worker RuntimeがSupervision Decisionに基づきRetry予定を記録できる
- [x] 未確定の既定Backoff値、最大Attempt回数、Attempt Timeoutが仕様判断なしに実装されていない
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

`orchestration/reports/P4-002-supervision-policy-contract.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
