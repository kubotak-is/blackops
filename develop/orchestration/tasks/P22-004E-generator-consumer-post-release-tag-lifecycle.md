# P22-004E: Generator Consumer Post-release Tag Lifecycle

Status: Documentation Review Passed (Commit Approved)

## Goal

Recover immutable Framework release `1.2.0` after Manual Recovery run `31878757317` passed Quickstart and Skeleton create-project but failed before credentials／Skeleton publication because the Generator Consumer unconditionally recreated the existing Framework tag. Preserve the pre-release disposable-tag journey while adding a fail-closed published-tag journey and a Manual-Recovery-only reviewed harness overlay.

## Authorization

The User authorized end-to-end Composer-installable `1.2.0` publication. This Task is limited to the exact pre-publication failure exposed by run `31878757317`; it does not authorize release Source changes, tag mutation, gate waivers, or direct Skeleton repository changes.

## Fixed Failure Evidence

- Workflow run: `31878757317`
- Workflow dispatch SHA: `8c8e975b62dcdb31b5cdf0474cdc5c313c458467`
- Checked-out immutable Framework source: `3332fd1dd0738fc7e79750facd93d49a59054ecf`
- Failed step: `Run consumer and installation gates`
- Passed before failure: Framework quality, full Quickstart Consumer, and Skeleton create-project
- Exact terminal error: `fatal: tag '1.2.0' already exists`
- Root cause: `tests/Consumer/framework-update-generators.sh` unconditionally runs `git ... tag 1.2.0 "${current_commit}"` even when the full-history clone already contains published annotated tag `1.2.0`
- Credential configuration and Skeleton publication steps were skipped; always-run cleanup passed
- Live Skeleton remote after failure: `main=293f880940636669f28ded756a888a8d6ba65f1b`; direct／peeled `1.2.0` refs absent

## In Scope

1. Preserve the Generator Consumer's pre-release lane when `refs/tags/1.2.0` is absent by creating only a disposable local candidate tag at the current commit.
2. When `refs/tags/1.2.0` exists, require object type `tag`, resolve its peeled commit, verify exact root／clone peeled equality, and reject drift between the published commit and current `HEAD` across `src`, root `composer.json`, `examples/quickstart`, `resources`, and `migrations` before using the published commit as candidate Source.
3. In `.github/workflows/publish-skeleton.yml`, keep checkout and publication Source fixed to `refs/tags/<release_version>`. During Manual Recovery only, overlay the reviewed dispatch-SHA `tests/Consumer/framework-update-generators.sh` alongside the already-reviewed Quickstart harness after release-runtime equality, verify both blobs, execute all Consumer gates, and restore both tagged harnesses on success or failure.
4. Keep ordinary tag-push publication on the immutable tagged harnesses.
5. Add static regression guards for both Generator tag lifecycle branches, annotated／peeled／drift checks, Manual Recovery overlay／restore ordering, and the ordinary tag-push lane.
6. Run the full Generator Consumer and focused Workflow／quality／scope／cleanup checks.

## Out of Scope

- Framework／Skeleton Production PHP or distributed Quickstart Source changes
- Existing Framework `1.2.0` tag movement, deletion, or recreation
- Fixed Framework Source `3332fd1` or Skeleton Split `fa5e8247` replacement
- Consumer assertion removal, gate skip／waiver, or failure masking
- Direct Skeleton repository mutation
- Commit, Push, PR, CI rerun, Manual Dispatch, GitHub Release, Packagist mutation, or deployment by the worker

## Relevant Specifications and Decisions

- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/61-experimental-release-contract.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/decisions/079-immutable-release-publication-recovery.md`
- `develop/decisions/139-stable-1-2-version-baseline.md`
- `develop/orchestration/tasks/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/orchestration/tasks/P22-004B-runtime-consumer-post-release-tag-lifecycle.md`

## Files Allowed to Change

- `.github/workflows/publish-skeleton.yml`
- `tests/Consumer/framework-update-generators.sh`
- `tests/Consumer/version-baseline.sh`
- `develop/orchestration/tasks/P22-004E-generator-consumer-post-release-tag-lifecycle.md`
- `develop/orchestration/reports/P22-004E-generator-consumer-post-release-tag-lifecycle.md`
- `develop/orchestration/tasks/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/orchestration/reports/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`

Files outside this list must not change. If another Source file is required, stop and report the blocker rather than broadening implementation.

## Constraints

- The immutable Framework tag checkout remains the publication Source; the dispatch SHA supplies reviewed test harnesses only.
- Existing annotated tag validation fails closed on missing root tag, lightweight tag, peeled mismatch, or release-runtime drift.
- Workflow cleanup restores both harnesses after success and through the failure trap.
- Worker must not Commit before Orchestrator and Documentation Review.
- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない。

## Acceptance Criteria

- [x] Generator Consumer retains its pre-release absent-tag journey
- [x] Existing `1.2.0` must be annotated and have identical root／clone peeled commits
- [x] Published tag is used only after zero drift across all five release-runtime paths
- [x] Manual Recovery overlays and verifies both reviewed harnesses without changing tagged release Source
- [x] Success and failure cleanup restore both tagged harnesses
- [x] Ordinary tag-push behavior remains unchanged
- [x] Full current published-tag Generator Consumer passes
- [x] Static guards, Workflow regression, format, management-ID, diff, scope, and cleanup checks pass
- [x] Worker makes no Commit／Push／CI／Dispatch／tag／release／publication mutation

## Required Commands

```bash
bash -n tests/Consumer/framework-update-generators.sh tests/Consumer/version-baseline.sh tests/Consumer/skeleton-publication-workflow.sh
bash tests/Consumer/version-baseline.sh
bash tests/Consumer/framework-update-generators.sh
bash tests/Consumer/skeleton-publication-workflow.sh
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P22-004E-generator-consumer-post-release-tag-lifecycle.md` with Summary, Changed Files, Decisions and Assumptions, Commands and Results, Acceptance Criteria, Remaining Issues, and Suggested Next Action.
