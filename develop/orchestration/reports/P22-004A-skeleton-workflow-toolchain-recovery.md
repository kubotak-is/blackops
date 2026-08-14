# P22-004A Skeleton Workflow Toolchain Recovery Report

Status: Review Passed (Checkpoint Commit Approved)

## Summary

Added the repository-pinned mise frontend toolchain setup to the Skeleton publication Workflow before Consumer gates, including exact Node and pnpm version checks. Added a static version-baseline guard that requires the setup and verifies it occurs before `quickstart-e2e.sh`. The immutable Framework tag/source and publication boundary were not changed. No Commit, Push, Dispatch, Tag, Release, Packagist mutation, or Deploy was performed.

## Changed Files

- `.github/workflows/publish-skeleton.yml`
- `tests/Consumer/version-baseline.sh`
- `develop/orchestration/reports/P22-004A-skeleton-workflow-toolchain-recovery.md`
- `develop/STATE.md`

Existing Orchestrator management-document changes were preserved and not edited.

## Decisions and Assumptions

- The toolchain steps are placed after release validation/container-user setup and before development image build and all Consumer gates; therefore every Consumer invocation has the repository-pinned `mise` and `pnpm` toolchain available.
- The static guard requires `jdx/mise-action@v4`, `install: true`, `cache: true`, exact Node `v24.18.0`, exact pnpm `11.12.0`, and ordering before `quickstart-e2e.sh`.
- The fixed Framework `1.2.0` tag and expected Skeleton split remain immutable. Manual Dispatch, credential use, publication, and release recovery remain Orchestrator-controlled follow-up actions.

## Commands and Results

- PASS: `bash -n tests/Consumer/version-baseline.sh tests/Consumer/skeleton-publication-workflow.sh`.
- PASS: `bash tests/Consumer/version-baseline.sh` (`stable=1.1.0 candidate=1.2.0`; includes the new ordered toolchain guard).
- PASS: `bash tests/Consumer/skeleton-publication-workflow.sh` (`split=fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`).
- PASS: `docker compose run --rm app mago format --check src tests` (`INFO All files are already formatted.`).
- PASS: `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'`.
- PASS: `git diff --check`.
- PASS: source scope inspection: only the two permitted Source files changed; this Report and STATE are the only Task management files changed by the worker. Existing unrelated management diffs remain present and untouched.
- PASS: Orchestrator independently reviewed exact Workflow／guard order and reran shell syntax, version-baseline, workflow regression with exact split, Mago format, management-ID guard, and `git diff --check`.
- PASS: corrected independent Documentation Review returned P1=0／P2=0／P3=0 and approved checkpoint Commit／dedicated PR integration.
- No workflow dispatch, external publication, tag mutation, release creation, or deployment was attempted.

## Acceptance Criteria

- [x] Skeleton publication Workflow installs pinned mise before Consumer gates.
- [x] Workflow verifies Node `v24.18.0` and pnpm `11.12.0`.
- [x] Static baseline guard rejects removal or reordering of the recovery toolchain contract.
- [x] Focused commands and repository guards pass.
- [x] Only allowed files changed.
- [x] Report and STATE describe the exact failure and immutable-tag boundary.
- [x] Worker made no Commit, Push, Dispatch, publication, or deployment mutation.

## Remaining Issues

- Checkpoint Commit／dedicated PR／required CI／main integration remain pending.
- After approved integration, the immutable `release_version=1.2.0` Manual Dispatch recovery and complete publication evidence remain Orchestrator-controlled; the worker did not perform them.

## Suggested Next Action

Commit the reviewed P22-004A and current publication tracking checkpoint, integrate through a dedicated PR with required checks, fetch／verify main and fixed inputs, then execute the authorized immutable `release_version=1.2.0` Manual Dispatch recovery.
