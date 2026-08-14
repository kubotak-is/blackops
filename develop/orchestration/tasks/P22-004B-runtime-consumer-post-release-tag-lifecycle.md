# P22-004B: Runtime Consumer Post-release Tag Lifecycle

Status: Review Passed (Correction Commit Approved)

## Goal

Allow the Stable `1.1.0` to `1.2.0` Runtime Consumer to operate both before and after immutable Framework `1.2.0` publication without replacing tags or masking release-runtime Source drift.

## Fixed Failure Evidence

- PR: #5, head `aa74ef57c97ec0199c9c456f963b31789f6da405`
- CI run: `31823195147`
- Failed job: Stable 1.1 to candidate 1.2 runtime consumer
- Exact failure: `fatal: tag '1.2.0' already exists`
- Exit: `128`
- Framework tag peeled source: `3332fd1dd0738fc7e79750facd93d49a59054ecf`
- Release runtime paths are unchanged between `3332fd1` and `aa74ef5`

## Scope

1. Preserve the current pre-release lane when `refs/tags/1.2.0` is absent: create the disposable local annotated candidate tag at current `HEAD`.
2. Add a post-release lane when the tag exists: require object type `tag`, resolve its peeled commit, verify it matches the repository tag, and use that immutable commit as the `1.2.0` update source.
3. Before using the published tag, require no drift from current `HEAD` across release-runtime paths: `src/`, root `composer.json`, `examples/quickstart/`, `resources/`, and `migrations/`.
4. Add static regression guards for both tag lifecycle lanes.
5. Run focused Runtime Consumer, version baseline, shell syntax, Mago format, management-ID, cleanup, and diff checks.

## Files Allowed to Change

- `tests/Consumer/framework-update-runtime.sh`
- `tests/Consumer/version-baseline.sh`
- `develop/orchestration/tasks/P22-004B-runtime-consumer-post-release-tag-lifecycle.md`
- `develop/orchestration/reports/P22-004B-runtime-consumer-post-release-tag-lifecycle.md`
- `develop/orchestration/tasks/P22-004A-skeleton-workflow-toolchain-recovery.md`
- `develop/orchestration/reports/P22-004A-skeleton-workflow-toolchain-recovery.md`
- `develop/orchestration/reports/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/STATE.md`

## Out of Scope

- Framework／Quickstart Production Source changes
- Existing Framework tag change／delete／recreate
- CI gate skip, waiver, or Runtime Consumer removal
- Skeleton direct changes, Workflow Dispatch, GitHub Release, or Packagist mutation

## Acceptance Criteria

- [x] Pre-release absent-tag lane still creates and tests a disposable local candidate tag
- [x] Post-release existing-tag lane requires annotated type and exact peeled commit
- [x] Post-release lane rejects release-runtime Source drift before using the immutable tag
- [x] Full Runtime Consumer passes in current published-tag state with cleanup
- [x] Static guards and repository quality checks pass
- [x] Worker does not Commit／Push／rerun CI／merge／Dispatch／publish

## Expected Next Sequence

Documentation Review → correction Commit pushed to PR #5 → new required CI → Green-only merge／fetch → P22-004 Manual Recovery.
