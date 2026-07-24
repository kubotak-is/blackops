# P19-004A Documentation Artifact Task ID CI Correction

Status: Correction Accepted - Replacement CI Pending

## Summary

Removed the P19-004 Orchestration Task ID from the public Transactional Outbox guide. The wording now describes the current capability boundary without exposing repository workflow metadata.

## Changed Files

- `docs/guide/execution.md`
- `develop/orchestration/tasks/P19-004A-documentation-artifact-task-id-ci.md`
- `develop/orchestration/reports/P19-004A-documentation-artifact-task-id-ci.md`
- `develop/STATE.md`

No Outbox Production Code, Migration, Schema, Public API, Relay, Retry, Dead Letter, or Replay file was changed.

## Decisions and Assumptions

- The artifact guard is correct and remains unchanged.
- Public documentation describes the capability boundary directly instead of referring to an internal Task identifier.
- The correction is one source-line replacement; generated website content remains build output and is not committed.

## Commands and Results

- `mise exec -- pnpm --dir docs/website run build` — PASS; 32 static pages built, artifact boundary passed, and site navigation／accessibility／Pagefind checks passed for 31 content pages.
- `git diff --check` — PASS.
- GitHub Actions CI Run `30061210185` — FAIL for the pre-correction Commit `0dae891`; Documentation website Job `89383052029` detected the Task ID in the public artifact.
- Documentation Delivery Run `30061210194` — FAIL for the same pre-correction source; Build documentation artifact Job `89383051558`.

## Acceptance Criteria

- [x] Public guide and generated artifact contain no Orchestration Task ID.
- [x] Documentation website build, artifact boundary, and site check pass.
- [x] No Outbox Production Code, Migration, or Schema diff.
- [ ] Replacement CI and Documentation Delivery pass.

## Remaining Issues

Replacement GitHub Actions verification is pending.

## Suggested Next Action

Commit and push the bounded documentation correction, then verify both replacement workflows at the final HEAD.
