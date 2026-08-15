# P22-004F: Generator Resource Inventory Report

Status: Documentation Review Passed (Commit Approved)

Updated At: 2026-08-15T23:03:32+09:00

## Summary

The obsolete four-file Generator stub ownership assertion is replaced with a fail-closed inventory contract. The Workflow now rejects a missing or empty `resources/stubs` inventory, invalid non-stub entries, and any filesystem／Git ownership mismatch. It compares sorted root-relative filesystem `.stub` paths with `git ls-files -- 'resources/stubs/*.stub'`, and retains the prohibition on Generator stubs under `examples/quickstart`.

The current immutable release inventory contains 32 root-relative tracked `.stub` files and the filesystem／Git inventories are exactly equal. Fixed Framework Source remains `3332fd1dd0738fc7e79750facd93d49a59054ecf`; the failed Manual Recovery was run `31887488249` at dispatch SHA `f454e34d317e37b51085b1b87432561c9dd1ad44`. No external publication state was mutated.

## Changed Files

- `.github/workflows/publish-skeleton.yml`
- `tests/Consumer/version-baseline.sh`
- `develop/orchestration/tasks/P22-004F-generator-resource-inventory.md`
- `develop/orchestration/reports/P22-004F-generator-resource-inventory.md`
- `develop/orchestration/tasks/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/orchestration/reports/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`

Existing management changes from the Orchestrator were preserved.

## Decisions and Assumptions

- The immutable Framework tag and publication Source remain unchanged.
- Root-relative equality is the ownership contract: filesystem paths are emitted as `resources/stubs/<name>.stub`, and Git paths come directly from `git ls-files -- 'resources/stubs/*.stub'`.
- Any empty inventory, untracked `.stub`, missing tracked `.stub`, non-stub entry, or nested Quickstart stub fails closed.
- Manual Recovery／CI rerun, Commit, Push, PR mutation, Dispatch, Tag, Release, Packagist, Skeleton mutation, and Deploy remain outside Worker authorization.

## Commands and Results

- PASS: `bash -n tests/Consumer/version-baseline.sh tests/Consumer/skeleton-publication-workflow.sh`.
- PASS: `bash tests/Consumer/version-baseline.sh` (`Version baseline guard passed: stable=1.1.0 candidate=1.2.0`).
- PASS: direct inventory contract check; 32 non-empty root-relative filesystem paths exactly matched 32 tracked Git paths, with no invalid stub entry.
- PASS: `bash tests/Consumer/skeleton-publication-workflow.sh` (`fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`).
- PASS: `docker compose run --rm app mago format --check src tests` (`All files are already formatted`).
- PASS: `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'`.
- PASS: `git diff --check`.
- PASS: Orchestrator independently reviewed the exact nine-path scope and confirmed the Workflow emits identical root-relative path formats for filesystem and Git inventories. Missing／empty directory, non-file or non-stub entry, empty inventory, untracked extra, missing tracked file, and Quickstart nested stub all fail closed.
- PASS: Orchestrator independently reran shell syntax, version baseline, the exact 32-file inventory comparison, deterministic Skeleton split `fa5e8247`, Mago format, management-ID, release-runtime Source equality, diff, scope, and cleanup checks.
- PASS: Documentation P1 correction synchronized the parent Report's current ownership narrative: P22-004E is integrated through PR #7, while P22-004F owns the currently pending inventory correction.
- PASS: Corrected independent Documentation Review returned P1=0／P2=0／P3=0 and approved the exact nine-path Working Tree for one Commit／dedicated PR. Reviewer confirmed live failure／remote evidence, immutable 32-file inventory, all fail-closed cases, static recurrence guards, corrected parent ownership, synchronized management state, and no external mutation.
- Not run: Manual Recovery dispatch／remote CI rerun and all publication or deployment mutations; prohibited before independent review and Green PR integration.

## Acceptance Criteria

- [x] Workflow compares sorted non-empty filesystem and Git-tracked root stub inventories.
- [x] Empty, extra, missing, non-stub, wrong-root, and Quickstart-owned stub cases fail closed.
- [x] Obsolete four-file expected inventory is absent.
- [x] Immutable release Source and ordinary／Manual publication sequencing are unchanged.
- [x] Static guards and focused repository checks pass.
- [x] Worker made no prohibited external mutation.

## Remaining Issues

- Dedicated Commit／PR, required all-Green CI, main merge／fetch, and one new Manual Recovery Dispatch remain pending; Worker, Orchestrator, and Documentation Review pass.
- Skeleton publication, Skeleton Packagist `1.2.0`, GitHub Release, remote package smoke, and Phase 22 closeout remain pending.

## Suggested Next Action

Create the exact reviewed nine-path Commit and dedicated PR, then require all CI Green before merge／fetch and another one-shot Manual Recovery Dispatch.
