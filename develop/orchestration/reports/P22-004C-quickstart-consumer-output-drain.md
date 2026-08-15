# P22-004C: Quickstart Consumer Output Drain Report

Status: Documentation Review Passed (Commit Approved)

## Summary

Implemented the bounded recovery for Manual Recovery run `31827240918`. The Quickstart Consumer now drains complete output from database status, retention plan, and retention dry-run commands before applying marker assertions. The harness keeps its repository-relative default and accepts an explicit `BLACKOPS_REPOSITORY_ROOT` for execution from an immutable tagged checkout.

Manual Recovery retains the tagged release checkout as the publication Source. It fetches and verifies the workflow dispatch SHA, requires no drift across `src`, root `composer.json`, `examples/quickstart`, `resources`, and `migrations`, overlays only the dispatch-SHA Quickstart harness, runs it against the tagged root, verifies the release-runtime paths again, and restores the tagged harness before the remaining publication gates. Ordinary tag-push execution remains the original tagged harness path.

## Changed Files

- `.github/workflows/publish-skeleton.yml`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/version-baseline.sh`
- `develop/orchestration/tasks/P22-004C-quickstart-consumer-output-drain.md`
- `develop/orchestration/reports/P22-004C-quickstart-consumer-output-drain.md`
- `develop/orchestration/tasks/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/orchestration/reports/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/orchestration/tasks/P22-004A-skeleton-workflow-toolchain-recovery.md`
- `develop/orchestration/reports/P22-004A-skeleton-workflow-toolchain-recovery.md`
- `develop/orchestration/tasks/P22-004B-runtime-consumer-post-release-tag-lifecycle.md`
- `develop/orchestration/reports/P22-004B-runtime-consumer-post-release-tag-lifecycle.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`

P22-004C owns the fourteen files listed above: three Source files and eleven current-state management files. The same Working Tree also contains the separately authorized process-only P22-004D changes in `AGENTS.md`, its Task／Report, and the shared `develop/STATE.md`; those files are reviewed under P22-004D rather than treated as unrelated P22-004C differences. No Framework Production PHP, distributed Quickstart Source, tag, Skeleton remote, or external publication state was changed.

## Decisions and Assumptions

- `refs/tags/${RELEASE_VERSION}` remains the only publication Source and must resolve to the checked-out `HEAD`.
- `github.sha` is the reviewed dispatch Source for Manual Recovery. It is fetched only when absent locally and must resolve to the exact 40-character SHA.
- Manual Recovery compares the immutable release commit and dispatch SHA over the same release-runtime path set used by P22-004B before executing the overlaid harness.
- The harness overlay is worktree-only and is restored with `git checkout --` before the subsequent create-project, generator, publication dry-run, and external credential steps.
- No `pipefail` relaxation or assertion removal is permitted.

## Commands and Results

- PASS: `bash -n tests/Consumer/quickstart-e2e.sh tests/Consumer/version-baseline.sh tests/Consumer/skeleton-publication-workflow.sh`.
- PASS: `bash tests/Consumer/version-baseline.sh` — output-drain, root override, Manual Recovery dispatch-SHA, release-runtime drift, and ordinary tag-path guards passed.
- PASS: `bash tests/Consumer/quickstart-e2e.sh` — full Docker Consumer journey passed, including both Worker lanes and retention assertions; no `write /dev/stdout: broken pipe`; script cleanup completed.
- PASS: `bash tests/Consumer/skeleton-publication-workflow.sh` — deterministic split `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`.
- PASS: `docker compose run --rm app mago format --check src tests` — all files already formatted.
- PASS: `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'`.
- PASS: `git diff --check`.
- PASS: allowed-file review confirmed P22-004C contains exactly its fourteen listed paths. The complete seventeen-path Working Tree equals the union of those fourteen paths and P22-004D's four allowed process-document paths, with `develop/STATE.md` shared by both Tasks.
- PASS: Orchestrator independently reviewed the Workflow overlay/trap lifecycle, immutable release `HEAD`, exact dispatch commit resolution, pre-run release-runtime equality, post-run tagged-root invariants, and ordinary tag-push branch.
- PASS: Orchestrator independently reran `bash -n`, `bash tests/Consumer/version-baseline.sh`, and `bash tests/Consumer/skeleton-publication-workflow.sh`; deterministic split remained `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`.
- PASS: Orchestrator independently reran the full Docker `bash tests/Consumer/quickstart-e2e.sh`; it passed database status, both Worker retries, retention plan／dry-run, Frontend HTTP journey, redaction, cleanup, and exited 0 without a broken pipe.
- PASS: Orchestrator independently reran Mago format, management-ID, diff, allowed-file, and disposable-container cleanup checks.
- PASS: corrected independent Documentation Review returned P1=0／P2=0／P3=0 for P22-004C and P22-004D. Reviewer confirmed the exact fourteen-path P22-004C scope, four-path P22-004D scope, shared STATE, and complete seventeen-path Working Tree union, and permits one reviewed Commit／dedicated PR.
- NOT RUN: Manual Recovery workflow dispatch and remote CI; explicitly prohibited for the worker. No tag, release, Packagist, Skeleton remote, or deployment mutation was attempted.

## Acceptance Criteria

- [x] Database status, retention plan, and retention dry-run producers complete before marker assertions.
- [x] Quickstart harness retains repository-relative default and supports an explicit tagged-checkout root.
- [x] Manual Recovery uses the dispatch-SHA harness only after fail-closed release-runtime equality against immutable tagged Source.
- [x] Ordinary tag-push behavior remains unchanged.
- [x] Full Quickstart Consumer passes without `write /dev/stdout: broken pipe`.
- [x] Static guards and focused repository checks pass.
- [x] Only allowed files changed.
- [x] Worker made no Commit, Push, CI rerun, merge, Dispatch, publication, tag, release, Packagist, or deployment mutation.

## Remaining Issues

- Reviewed Commit, dedicated PR, new required CI, Green-only merge/fetch, and the authorized one-shot Manual Recovery Dispatch remain pending.
- Manual Recovery branch was statically guarded but not remotely dispatched by this worker.

## Suggested Next Action

Create the exact reviewed seventeen-path Commit, open a dedicated PR, and require new all-Green CI before merge／fetch. Only after that sequence should the immutable `1.2.0` Manual Recovery Dispatch be considered.
