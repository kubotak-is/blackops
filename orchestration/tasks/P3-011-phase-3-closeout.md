# P3-011: Phase 3 Closeout

Status: Accepted

## Goal

Phase 3: Deferred Vertical Sliceの実装到達点を確認し、最終検証を実行して、残作業をPhase 4以降へ明確に引き継ぐ。

## In Scope

- Phase 3で実装済みのDeferred Vertical Sliceを整理する
- Phase 4以降へ送る未実装項目を整理する
- `orchestration/STATE.md` をPhase 3完了状態へ更新する
- Phase 3 Closeout Reportを作成する
- 必須品質Commandを全件実行する

## Out of Scope

- 新規Production Code実装
- Retry / Heartbeat / Crash Recovery / Dead Letter実装
- Worker Loop / CLI Command実装
- Outcome取得API実装
- Retention実装

## Relevant Specifications

- `spec/33-execution-transport-contract.md`
- `spec/35-postgresql-transport-schema.md`
- `spec/36-postgresql-transaction-boundaries.md`
- `spec/40-mvp-delivery-plan.md`
- `decisions/046-mvp-delivery-plan.md`

## Files Allowed to Change

- `orchestration/tasks/P3-011-phase-3-closeout.md`
- `orchestration/reports/P3-011-phase-3-closeout.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Closeoutでは新規Production Codeを変更しない

## Acceptance Criteria

- [x] Phase 3の実装済み範囲がReportに整理される
- [x] Phase 4以降の残作業がReportに整理される
- [x] STATEがPhase 3完了状態へ更新される
- [x] 必須品質Commandが成功する
- [x] Working TreeがCommit可能な状態になる

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

`orchestration/reports/P3-011-phase-3-closeout.md` に次を記録する。

- Summary
- Changed Files
- Phase 3 Completed Scope
- Deferred to Later Phases
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
