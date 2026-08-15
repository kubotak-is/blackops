# P22-004E: Generator Consumer Post-release Tag Lifecycle Report

Status: Documentation Review Passed (Commit Approved)

Updated At: 2026-08-15T19:46:03+09:00

## Summary

The bounded Generator Consumer recovery is implemented. The pre-release absent-tag lane remains disposable; the existing published `1.2.0` lane now requires an annotated tag, matching root／clone peeled commits, and zero drift across the five release-runtime paths before selecting the published commit. Manual Recovery overlays only the reviewed dispatch-SHA Quickstart and Generator harnesses after immutable release-runtime equality, verifies both blobs, runs all Consumer gates, and restores both harnesses on success or failure. Ordinary tag-push behavior remains unchanged.

Fixed inputs remain Framework tag `1.2.0` peeled to `3332fd1dd0738fc7e79750facd93d49a59054ecf`, current dispatch SHA `8c8e975b62dcdb31b5cdf0474cdc5c313c458467`, and failed run `31878757317`. No external publication state was mutated.

## Changed Files

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

Existing Orchestrator／management changes outside this bounded implementation were preserved.

## Decisions and Assumptions

- The immutable Framework tag remains the release and publication Source; dispatch SHA content is limited to reviewed test harnesses.
- The exact drift set is `src`, `composer.json`, `examples/quickstart`, `resources`, and `migrations`.
- The Manual Recovery branch is statically guarded and is not remotely dispatched by this Worker because Dispatch／CI mutation is prohibited until independent review and PR integration.
- Generator temporary repositories clean up through the existing script trap; no credential values were read or recorded.

## Commands and Results

- PASS: `bash -n tests/Consumer/framework-update-generators.sh tests/Consumer/version-baseline.sh tests/Consumer/skeleton-publication-workflow.sh`.
- PASS: `bash tests/Consumer/version-baseline.sh` (`Version baseline guard passed: stable=1.1.0 candidate=1.2.0`).
- PASS: `bash tests/Consumer/framework-update-generators.sh` on the current published-tag lane; Composer upgraded Framework `1.1.0` to `1.2.0`, checked out exact peeled `3332fd1`, source invariants and cleanup passed.
- PASS: `bash tests/Consumer/skeleton-publication-workflow.sh` (deterministic split `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`).
- PASS: `docker compose run --rm app mago format --check src tests` (`All files are already formatted`).
- PASS: `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'`.
- PASS: `git diff --check`.
- PASS: Orchestrator independently reviewed both Workflow branches, dispatch-SHA blob verification, success／failure restoration, immutable tagged `HEAD`, Generator annotated/root-clone/drift lifecycle, and exact ten-path scope.
- PASS: Orchestrator independently reran shell syntax, version baseline, published-tag Generator Consumer, deterministic Skeleton publication regression, Mago format, management-ID, and diff guards. The published lane resolved Framework `1.2.0` from exact `3332fd1`.
- PASS: Orchestrator-requested correction strengthens `assert_manual_recovery_harness` to require both exact harness members inside the `harness_paths` array before its closing parenthesis, restore definition, and overlay. Shell syntax and version baseline pass after the correction.
- PASS: Documentation P1 correction strengthens the same guard to require the restore member loop／checkout, dispatch overlay hash equality, Manual／ordinary `skeleton-create-project.sh`, and trap install／overlay／manual gates／direct restore／trap clear／ordinary gate ordering. Shell syntax, version baseline, and diff checks pass.
- PASS: Orchestrator created a disposable `/tmp` clone, overlaid only the reviewed Generator／baseline scripts, removed `1.2.0` only from that clone, and dynamically reran the absent-tag lane. Composer upgraded to disposable `1.2.0` at current `8c8e975`; generator assertions, source invariants, and cleanup passed. The disposable clone was removed afterward.
- PASS: Current Working Tree contains exactly the ten allowed P22-004E paths: three Source files and seven management files.
- PASS: Corrected independent Documentation Review returned P1=0／P2=0／P3=0. Reviewer confirmed current run／Skeleton remote evidence, both Generator tag lifecycle lanes, immutable Manual Recovery Source, dual harness overlay／hash／restore, ordinary tag lane, corrected static guard, exact ten-path scope, synchronized management state, and no external mutation. The exact current Working Tree is approved for one Commit and dedicated PR.
- Not run: Manual Recovery dispatch／remote CI rerun, tag／release／Packagist／Skeleton mutation, and deploy; all are explicitly prohibited before review and Green PR integration.

## Acceptance Criteria

- [x] Absent-tag disposable Generator lane retained.
- [x] Existing annotated tag, root／clone peeled equality, and five-path drift checks are fail-closed.
- [x] Published peeled commit is selected only after all checks pass.
- [x] Manual Recovery overlays and hash-verifies both reviewed harnesses while preserving immutable release Source.
- [x] Success and failure restoration plus ordinary tag-push behavior are guarded.
- [x] Static guard covers restore loop／checkout, overlay hash equality, both Skeleton gates, and trap lifecycle ordering.
- [x] Current published-tag Generator Consumer and required focused checks pass.
- [x] Static guard proves both Manual Recovery harness array members before restore／overlay.
- [x] No prohibited external mutation occurred.

## Remaining Issues

- Dedicated Commit／PR, required all-Green CI, main merge／fetch, and one new Manual Recovery Dispatch remain pending; Worker, Orchestrator, and Documentation Review pass.
- Skeleton publication, Skeleton Packagist `1.2.0`, GitHub Release, remote package smoke, and Phase 22 closeout remain pending.

## Suggested Next Action

Create the exact reviewed ten-path Commit and dedicated PR, then require all CI Green before merge／fetch and another one-shot Manual Recovery Dispatch.
