# P22-004A: Skeleton Workflow Toolchain Recovery

Status: Review Passed (Checkpoint Commit Approved)

## Goal

Recover immutable Framework release `1.2.0` after Skeleton publication run `31809007808` failed at the Quickstart Consumer because `mise` was unavailable. Add only the repository-pinned frontend toolchain setup and a static recurrence guard, then prepare D079 Manual Dispatch of `release_version=1.2.0` without changing the release source or tags.

## Authorization

User instructed: `リリースもしてよ、composerでインストールできるように`.

This authorizes the bounded Workflow correction, review／CI／main integration, and subsequent immutable-tag Manual Recovery needed for Composer-installable Framework／Skeleton `1.2.0`.

## Fixed Evidence

- Framework tag direct object: `00e8c5875047a3c47acbebfe57f75b0e581d18b9`
- Framework tag peeled source: `3332fd1dd0738fc7e79750facd93d49a59054ecf`
- Expected Skeleton split: `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`
- Failed run: `31809007808`
- Exact failure: `tests/Consumer/quickstart-e2e.sh: line 63: mise: command not found`
- Failed step exit: `127`
- Credential／publication steps: not reached; always-run cleanup passed

## Scope

1. In `.github/workflows/publish-skeleton.yml`, install the repository-pinned toolchain with `jdx/mise-action@v4`, `install: true`, and `cache: true` before any Consumer invokes `mise`／`pnpm`.
2. Verify exact Node `v24.18.0` and pnpm `11.12.0` versions in the Workflow, matching existing CI contracts.
3. In `tests/Consumer/version-baseline.sh`, add static assertions that the Skeleton publication Workflow retains this setup and version verification.
4. Run focused syntax／baseline／Workflow regression and repository guards.
5. Record implementation evidence. Do not commit before Orchestrator review.

## Files Allowed to Change

- `.github/workflows/publish-skeleton.yml`
- `tests/Consumer/version-baseline.sh`
- `develop/orchestration/tasks/P22-004A-skeleton-workflow-toolchain-recovery.md`
- `develop/orchestration/reports/P22-004A-skeleton-workflow-toolchain-recovery.md`
- `develop/STATE.md`

## Out of Scope

- Framework／Skeleton Production PHP or distributed Quickstart source changes
- Existing Framework tag change, deletion, or recreation
- Direct Skeleton repository changes
- Skipping or weakening Quality／Consumer／Publication gates
- Secret retrieval or output
- Manual Dispatch, tag push, GitHub Release, Packagist mutation, or deployment by the worker

## Required Commands

```bash
bash -n tests/Consumer/version-baseline.sh tests/Consumer/skeleton-publication-workflow.sh
bash tests/Consumer/version-baseline.sh
bash tests/Consumer/skeleton-publication-workflow.sh
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Acceptance Criteria

- [x] Skeleton publication Workflow installs pinned mise toolchain before Consumer gates
- [x] Workflow verifies Node `v24.18.0` and pnpm `11.12.0`
- [x] Static baseline guard rejects removal of the recovery contract
- [x] Focused commands and repository guards pass
- [x] Only allowed files change
- [x] Worker Report and STATE describe the exact failure and immutable-tag boundary
- [x] Worker does not Commit／Push／Dispatch／publish

## Expected Report

`develop/orchestration/reports/P22-004A-skeleton-workflow-toolchain-recovery.md` with Summary, Changed Files, Decisions and Assumptions, Commands and Results, Acceptance Criteria, Remaining Issues, and Suggested Next Action.
