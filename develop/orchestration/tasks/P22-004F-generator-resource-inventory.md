# P22-004F: Generator Resource Inventory

Status: Documentation Review Passed (Commit Approved)

## Goal

Recover immutable Framework release `1.2.0` after Manual Recovery run `31887488249` passed all Framework quality and Consumer gates but failed before credentials／Skeleton publication because the Workflow compared the release's complete Generator stub inventory against an obsolete four-file list. Replace the stale duplicate inventory with a fail-closed filesystem／Git ownership comparison.

## Authorization

The User authorized end-to-end Composer-installable `1.2.0` publication. This Task is limited to the exact pre-publication failure exposed by run `31887488249`; it does not authorize release Source changes, tag mutation, gate waivers, or direct Skeleton repository changes.

## Fixed Failure Evidence

- Workflow run: `31887488249`
- Workflow dispatch SHA: `f454e34d317e37b51085b1b87432561c9dd1ad44`
- Checked-out immutable Framework source: `3332fd1dd0738fc7e79750facd93d49a59054ecf`
- Passed before failure: immutable tag validation, pinned toolchain, Framework quality, Quickstart, Skeleton create-project, and Generator Consumer
- Failed step: `Verify generator resource ownership`
- Obsolete expected inventory: four migration／operation stubs
- Actual immutable-tag inventory: 32 files under `resources/stubs`, all `.stub` files and all tracked by Git
- Credential configuration and Skeleton publication steps were skipped; always-run cleanup passed

## In Scope

1. Replace the Workflow's hard-coded four-file expected list with sorted root-relative filesystem and Git-tracked stub inventories.
2. Require the inventory to be non-empty and exact-equal so missing tracked files, untracked files, non-stub files, or wrong ownership fail closed.
3. Preserve the existing prohibition on Generator stubs inside `examples/quickstart`.
4. Add a static baseline guard that requires the filesystem／Git equality contract, non-empty check, root ownership check, and rejects restoration of the stale four-file literal.
5. Run shell syntax, version baseline, deterministic Skeleton workflow regression, Mago format, management-ID, scope, cleanup, and diff checks.

## Out of Scope

- Framework／Skeleton Production PHP, Generator stubs, or distributed Quickstart Source changes
- Existing Framework tag movement, deletion, or recreation
- Fixed Framework Source `3332fd1` or Skeleton Split `fa5e8247` replacement
- Consumer assertion removal, gate skip／waiver, or failure masking
- Direct Skeleton repository mutation
- Commit, Push, PR, CI rerun, Manual Dispatch, GitHub Release, Packagist mutation, or deployment by the worker

## Relevant Specifications and Decisions

- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/61-experimental-release-contract.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/decisions/079-immutable-release-publication-recovery.md`
- `develop/orchestration/tasks/P22-004-stable-1-2-publication-and-closeout.md`

## Files Allowed to Change

- `.github/workflows/publish-skeleton.yml`
- `tests/Consumer/version-baseline.sh`
- `develop/orchestration/tasks/P22-004F-generator-resource-inventory.md`
- `develop/orchestration/reports/P22-004F-generator-resource-inventory.md`
- `develop/orchestration/tasks/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/orchestration/reports/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`

Files outside this list must not change. If another Source file is required, stop and report the blocker rather than broadening implementation.

## Acceptance Criteria

- [x] Workflow compares sorted filesystem and Git-tracked root stub inventories
- [x] Empty inventory, untracked extra, missing tracked file, non-stub file, and Quickstart-owned stub all fail closed
- [x] Obsolete four-file expected inventory is absent
- [x] Immutable release Source and ordinary／Manual publication sequencing remain unchanged
- [x] Static guards and focused repository checks pass
- [x] Worker makes no Commit／Push／CI／Dispatch／tag／release／publication mutation

## Required Commands

```bash
bash -n tests/Consumer/version-baseline.sh tests/Consumer/skeleton-publication-workflow.sh
bash tests/Consumer/version-baseline.sh
bash tests/Consumer/skeleton-publication-workflow.sh
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P22-004F-generator-resource-inventory.md` with Summary, Changed Files, Decisions and Assumptions, Commands and Results, Acceptance Criteria, Remaining Issues, and Suggested Next Action.
