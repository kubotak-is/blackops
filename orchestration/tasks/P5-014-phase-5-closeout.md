# P5-014: Phase 5 Closeout

Status: Completed

## Goal

Phase 5: Retentionの実装到達点を確認し、最終検証を実行して、残作業をPhase 6以降へ明確に引き継ぐ。

## In Scope

- P5-001からP5-013のTask / Report確認
- Phase 5成果の整理
- TODOの完了状態整理
- Phase 6以降へ送る未実装項目の整理
- 最終品質Command実行

## Out of Scope

- 新規Production Code実装
- Framework標準Factory / ProviderによるScheduler Task登録
- Framework内DB Lock / File Lock
- Journal / Outcomeの実削除実装

## Relevant Specifications

- `spec/38-data-retention-and-deletion.md`
- `spec/39-retention-runtime.md`
- `spec/40-mvp-delivery-plan.md`
- `decisions/044-data-retention-and-deletion.md`
- `decisions/045-retention-mvp-scope.md`
- `decisions/046-mvp-delivery-plan.md`

## Files Allowed to Change

- `TODO.md`
- `orchestration/tasks/P5-014-phase-5-closeout.md`
- `orchestration/reports/P5-014-phase-5-closeout.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- Phase 5で未実装の項目を完了扱いにしない
- Phase 6のCompile and Polish実装は行わない

## Acceptance Criteria

- [x] P5-001からP5-013のTask / Reportが存在する
- [x] Phase 5成果がRetention scopeを満たす
- [x] Phase 6以降の残作業がReportに整理される
- [x] 必須Commandがすべて成功している
- [x] STATEがPhase 6準備状態へ更新される

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

`orchestration/reports/P5-014-phase-5-closeout.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
