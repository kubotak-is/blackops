# P19-004A: Documentation Artifact Task ID CI Correction

Status: Accepted

## Goal

P19-004で追加した公開Execution GuideからOrchestration Task IDを除去し、公開artifact boundaryを維持したままGitHub ActionsのDocumentation build回帰を解消する。

## Evidence

- Commit `0dae891`のDocumentation Delivery Run `30061210194`
- Failed Job `89383051558`: `Build documentation artifact`
- 同CommitのCI Run `30061210185`
- Failed Job `89383052029`: `Documentation website`
- `docs/guide/execution.md`の公開文面に`P19-004`が一箇所残り、`check-artifact.mjs`のOrchestration Identifier Guardが検出した

## Files Allowed to Change

- `docs/guide/execution.md`
- `develop/orchestration/tasks/P19-004A-documentation-artifact-task-id-ci.md`
- `develop/orchestration/reports/P19-004A-documentation-artifact-task-id-ci.md`
- `develop/STATE.md`

## In Scope

- 公開GuideのTask IDを機能ベースの表現へ置換する
- Documentation website buildとartifact boundaryを再実行する
- Replacement GitHub Actionsを確認する

## Out of Scope

- Outbox Production Code、Migration、Schema、Public API、Test Contractの変更
- Relay／Retry／Dead Letter／Replayの実装
- Artifact Guardの緩和

## Acceptance Criteria

- [x] 公開Guideと生成artifactにOrchestration Task IDが残らない
- [x] Documentation website build、artifact boundary、site checkが成功する
- [x] Outbox Production Code／Migration／Schema差分がない
- [x] Replacement CIとDocumentation Deliveryが成功する

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run build
git diff --check
git status --short
```

## Expected Report

`develop/orchestration/reports/P19-004A-documentation-artifact-task-id-ci.md`
