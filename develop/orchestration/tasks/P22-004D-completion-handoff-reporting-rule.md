# P22-004D: Completion Handoff Reporting Rule

Status: Documentation Review Passed (Commit Approved)

## Goal

Require every Task completion handoff to state the remaining steps toward the higher-level Goal and the concrete next action, including an explicit `none` when no work remains.

## Authorization

User explicitly instructed: `作業完了時に残り工程とネクストアクションについて言及するようにルール化してほしい`.

## Scope

1. Extend the Repository completion-reporting rule in `AGENTS.md`.
2. Require both remaining steps and a concrete next action in the final User-facing completion handoff.
3. Require an explicit no-remaining-work statement when applicable.
4. Prevent Task completion from being presented as completion of a higher-level Goal that still has work remaining.

## Files Allowed to Change

- `AGENTS.md`
- `develop/orchestration/tasks/P22-004D-completion-handoff-reporting-rule.md`
- `develop/orchestration/reports/P22-004D-completion-handoff-reporting-rule.md`
- `develop/STATE.md`

## Out of Scope

- Production Code／Test／Workflow changes
- Release gate, publication sequence, or external state changes
- Retroactive rewriting of historical Reports

## Acceptance Criteria

- [x] Completion handoff must state remaining steps toward the higher-level Goal
- [x] Completion handoff must state a concrete next action
- [x] No remaining work must be stated explicitly
- [x] Task completion and higher-level Goal completion must remain distinct
- [x] No Production Code or external state changes

## Expected Report

`develop/orchestration/reports/P22-004D-completion-handoff-reporting-rule.md` with Summary, Changed Files, Decisions and Assumptions, Commands and Results, Acceptance Criteria, Remaining Issues, and Suggested Next Action.
