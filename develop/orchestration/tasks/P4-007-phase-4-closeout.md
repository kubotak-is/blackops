# P4-007: Phase 4 Closeout

Status: Completed

## Goal

Phase 4: Resilienceの実装到達点を確認し、最終検証を実行して、残作業をPhase 5以降へ明確に引き継ぐ。

## In Scope

- P4-001からP4-006のTask / Report確認
- Phase 4成果の整理
- TODOの完了状態整理
- Phase 5以降へ送る未実装項目の整理
- 最終品質Command実行

## Out of Scope

- 新規Production Code実装
- Signal Handling実装
- Worker Loop / CLI Command
- Retention実装

## Relevant Specifications

- `develop/spec/31-deferred-claim-and-attempt.md`
- `develop/spec/32-worker-crash-recovery.md`
- `develop/spec/33-execution-transport-contract.md`
- `develop/spec/40-mvp-delivery-plan.md`
- `develop/decisions/046-mvp-delivery-plan.md`

## Files Allowed to Change

- `develop/TODO.md`
- `develop/orchestration/tasks/P4-007-phase-4-closeout.md`
- `develop/orchestration/reports/P4-007-phase-4-closeout.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- Phase 4で未実装の項目を完了扱いにしない
- Signal HandlingやWorker Loopは後続Taskとして残す

## Acceptance Criteria

- [x] P4-001からP4-006のTask / Reportが存在する
- [x] Phase 4成果がResilience scopeを満たす
- [x] Phase 5以降の残作業がReportに整理される
- [x] 必須Commandがすべて成功している
- [x] STATEがPhase 5準備状態へ更新される

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

`develop/orchestration/reports/P4-007-phase-4-closeout.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
