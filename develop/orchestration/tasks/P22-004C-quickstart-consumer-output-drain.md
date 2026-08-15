# P22-004C: Quickstart Consumer Output Drain

Status: Documentation Review Passed (Commit Approved)

## Goal

Recover immutable Framework release `1.2.0` after Manual Recovery run `31827240918` failed before Skeleton publication because a `grep -q` consumer closed Docker Compose stdout early. Preserve every Consumer assertion while draining producer output, and allow the reviewed current-main Quickstart harness to test the immutable tagged release without changing release Source or tags.

## Authorization

The User authorized end-to-end Composer-installable `1.2.0` publication and explicitly approved continued release recovery. This Task is limited to the exact pre-publication failure exposed by run `31827240918`.

## Fixed Failure Evidence

- Workflow run: `31827240918`
- Workflow source SHA: `f61dc037533f3dea54ba33df9e203c7727d06443`
- Checked-out immutable Framework source: `3332fd1dd0738fc7e79750facd93d49a59054ecf`
- Failed step: `Run consumer and installation gates`
- Exact terminal error: `write /dev/stdout: broken pipe`
- Root cause: `retention:plan | grep -q 'Total:'` exits the reader after the first match while Docker Compose still forwards later output; `set -o pipefail` converts the producer failure into step failure
- Same unsafe contract also exists in the immediately following retention dry-run assertion and the earlier database status assertion
- Credential configuration and Skeleton publication steps were not reached; cleanup passed

## Scope

1. In `tests/Consumer/quickstart-e2e.sh`, capture complete Docker Compose command output before checking the database status, retention plan, and retention dry-run markers. Preserve the existing exact assertions and `pipefail` behavior.
2. Permit an explicit Framework root override for this harness while retaining the repository-relative default for every existing caller.
3. In `.github/workflows/publish-skeleton.yml`, keep checkout fixed to `refs/tags/<release_version>`. During Manual Recovery only, require no drift between the immutable tag and the workflow-dispatch SHA across `src/`, root `composer.json`, `examples/quickstart/`, `resources/`, and `migrations/`; then execute the reviewed Quickstart harness from the workflow-dispatch SHA against the tagged checkout.
4. Keep ordinary tag-push publication on the tagged harness path.
5. Add static regression guards in `tests/Consumer/version-baseline.sh` for complete-output assertions, root override, manual-only reviewed harness selection, and all fail-closed drift paths.
6. Run focused syntax, baseline, full Quickstart Consumer, publication workflow regression, Mago format, management-ID, cleanup, and diff checks.

## Files Allowed to Change

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

## Out of Scope

- Framework／Skeleton Production PHP or distributed Quickstart Source changes
- Existing Framework tag change, deletion, or recreation
- Consumer assertion removal, gate skip, waiver, or `pipefail` relaxation
- Direct Skeleton repository mutation
- Workflow rerun, Manual Dispatch, GitHub Release, Packagist mutation, or deployment by the worker

## Required Commands

```bash
bash -n tests/Consumer/quickstart-e2e.sh tests/Consumer/version-baseline.sh tests/Consumer/skeleton-publication-workflow.sh
bash tests/Consumer/version-baseline.sh
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/skeleton-publication-workflow.sh
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Acceptance Criteria

- [x] Database status, retention plan, and retention dry-run producers complete before marker assertions
- [x] Quickstart harness retains repository-relative default and supports an explicit tagged-checkout root
- [x] Manual Recovery executes the reviewed dispatch-SHA harness against immutable tagged Source only after release-runtime path equality succeeds
- [x] Ordinary tag-push behavior remains unchanged
- [x] Full Quickstart Consumer passes without `write /dev/stdout: broken pipe`
- [x] Static guards and focused repository checks pass
- [x] Only allowed files change
- [x] Worker makes no Commit／Push／CI rerun／merge／Dispatch／publication mutation

## Expected Report

`develop/orchestration/reports/P22-004C-quickstart-consumer-output-drain.md` with Summary, Changed Files, Decisions and Assumptions, Commands and Results, Acceptance Criteria, Remaining Issues, and Suggested Next Action.
