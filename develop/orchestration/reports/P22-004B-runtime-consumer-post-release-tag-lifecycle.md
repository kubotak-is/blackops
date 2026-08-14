# P22-004B Runtime Consumer Post-release Tag Lifecycle Report

Status: Review Passed (Correction Commit Approved)

## Summary

Implemented the bounded pre-release/post-release tag lifecycle. When 1.2.0 is absent, the Runtime Consumer retains its disposable local annotated-tag lane. When the published 1.2.0 exists, it requires an annotated tag, verifies the peeled commit against the root checkout, rejects drift across src/, root composer.json, examples/quickstart/, resources/, and migrations, and uses the immutable peeled commit as the candidate source. No Production Source, tag, publication, CI, or external state was changed.

## Changed Files

- tests/Consumer/framework-update-runtime.sh
- tests/Consumer/version-baseline.sh
- develop/orchestration/tasks/P22-004B-runtime-consumer-post-release-tag-lifecycle.md
- develop/orchestration/reports/P22-004B-runtime-consumer-post-release-tag-lifecycle.md
- develop/orchestration/reports/P22-004A-skeleton-workflow-toolchain-recovery.md
- develop/orchestration/reports/P22-004-stable-1-2-publication-and-closeout.md
- develop/STATE.md

Existing Orchestrator management differences were preserved.

## Decisions and Assumptions

- The published Framework 1.2.0 tag remains immutable and currently peels to 3332fd1dd0738fc7e79750facd93d49a59054ecf.
- The pre-release branch is selected only when refs/tags/1.2.0 is absent; an existing lightweight or malformed tag fails closed.
- The post-release branch compares release-runtime paths between the published peeled commit and current HEAD before Composer uses the tag source.
- The local tagger identity remains limited to the disposable repository and is used only by the absent-tag branch.

## Commands and Results

- PASS: bash -n tests/Consumer/framework-update-runtime.sh tests/Consumer/version-baseline.sh.
- PASS: bash -n tests/Consumer/*.sh.
- PASS: bash tests/Consumer/version-baseline.sh (stable=1.1.0 candidate=1.2.0), including both tag-lifecycle and drift static guards.
- NOT RUN TO COMPLETION: initial GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL=/dev/null bash tests/Consumer/framework-update-runtime.sh could not access Docker in the restricted sandbox (permission denied).
- PASS: escalated GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL=/dev/null bash tests/Consumer/framework-update-runtime.sh in current published-tag state. It verified annotated 1.2.0, root/clone peeled commit 3332fd1dd0738fc7e79750facd93d49a59054ecf, release-runtime path equality, Composer candidate 3332fd1, 11 migrations, Provider-present HTTP/Worker, Provider-missing Classic HTTP/Worker safe-negative, source invariants, and cleanup (exit 0).
- PASS: docker compose run --rm app mago format --check src tests (INFO All files are already formatted.).
- PASS: management-ID guard: ! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'.
- PASS: git diff --check.
- PASS: Runtime cleanup guard restored the repository status and removed disposable Compose resources; no credentials or secrets were logged.
- PASS: Orchestrator independently reviewed both lifecycle branches and reran the full published-tag Runtime Consumer, which resolved Composer Framework 1.2.0 from exact `3332fd1`, completed all runtime lanes, and cleaned resources.
- PASS: corrected independent Documentation Review returned P1=0／P2=0／P3=0 and permits the bounded Correction Commit／PR #5 push.
- No Commit, Push, CI rerun, merge, Dispatch, Tag, Release, Packagist mutation, or Deploy was performed.

## Acceptance Criteria

- [x] Pre-release absent-tag lane still creates a disposable local candidate tag.
- [x] Post-release existing-tag lane requires annotated type and exact peeled commit/root checkout equality.
- [x] Post-release lane rejects release-runtime Source drift before using the immutable tag.
- [x] Full Runtime Consumer passes in current published-tag state with cleanup.
- [x] Static guards and repository quality checks pass.
- [x] Worker made no Commit, Push, CI rerun, merge, Dispatch, or publication mutation.

## Remaining Issues

- Correction Commit／PR #5 push and new required CI remain pending after all reviews passed.
- PR #5 still requires the reviewed correction Commit, new CI qualification, Green-only integration, and subsequent publication recovery sequence.

## Suggested Next Action

Create the reviewed Correction Commit and update PR #5 for new required CI; do not mutate tags or dispatch before Green integration.
