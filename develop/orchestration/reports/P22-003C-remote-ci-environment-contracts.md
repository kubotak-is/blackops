# P22-003C Remote CI Environment Contract Correction Report

Status: Accepted — Committed as `96383e1bbe1a0914d1eddc9e1dea160042804f7c`

## Summary

Closed the three bounded environment-contract gaps observed in the first same-SHA Remote CI run. The Quality checkout now retains full Git history, the Runtime Consumer configures an annotated-tag identity only in its disposable Framework clone, and the Website PHP syntax fixture preserves an existing opening tag while adding one only to fragments. No Production PHP, Runtime/Public API, or Guide正文 was changed. The worker performed no Commit or external mutation; after independent review the Orchestrator committed the correction as `96383e1bbe1a0914d1eddc9e1dea160042804f7c`, and the parent P22-003 complete Local Gate now passes at that exact SHA.

## Changed Files

- `.github/workflows/ci.yml`
- `tests/Consumer/framework-update-runtime.sh`
- `docs/website/tests/guide-code.test.mjs`
- `develop/orchestration/reports/P22-003C-remote-ci-environment-contracts.md`
- `develop/STATE.md`

Existing unrelated management-file changes were preserved.

## Decisions and Assumptions

- `fetch-depth: 0` is applied only to the Quality checkout that runs `version-baseline.sh`; other jobs were not broadened.
- The Runtime Consumer writes `user.name` and `user.email` with `git config --local` in the disposable clone immediately before the local annotated candidate tag. It does not modify global or system Git configuration.
- PHP examples are normalized by checking for an existing `<?php` prefix. Complete examples retain their existing prefix; fragments receive exactly one prefix.
- The source correction supersedes candidate `577cc224` after review. Its previous Local Gate and Remote CI evidence must not be reused for acceptance.

## Commands and Results

- PASS: `bash -n tests/Consumer/*.sh`.
- PASS: `bash tests/Consumer/version-baseline.sh` (`stable=1.1.0 candidate=1.2.0`).
- PASS: `mise exec -- pnpm --dir docs/website test` (`79` tests, `79` passed, `0` failed).
- NOT RUN TO COMPLETION: initial `GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL=/dev/null bash tests/Consumer/framework-update-runtime.sh` reached Docker setup but could not access the Docker socket in the restricted sandbox (permission denied).
- PASS: escalated `GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL=/dev/null bash tests/Consumer/framework-update-runtime.sh` completed (`stable=e3df5576c7216cfe8bd9e10e12ee6795f7674088`, candidate `577cc224e0628ccbb9d91027ca214a4625a5228a`, `migrations=11`, Provider-present Worker HTTP, Provider-missing Classic HTTP/Worker safe-negative) and cleanup returned exit 0.
- UNSATISFIED: `docker compose run --rm app mago format --check src tests` reported 5 existing PHP files requiring formatting. Those files are outside the Packet's allowed Source list and were not changed.
- PASS after generated cleanup: Orchestrator identified all five as Git-ignored files under `tests/Frontend/fixture/var/build/`, ran the CI-equivalent `mise exec -- pnpm --dir tests/Frontend run clean`, and reran `docker compose run --rm app mago format --check src tests` successfully (`All files are already formatted`). No tracked Source was changed by cleanup.
- PASS: `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'`.
- PASS: `git diff --check`.
- PASS: source scope inspection: only the three Packet Source files changed in addition to pre-existing management files and this Report/STATE.
- PASS: Orchestrator independently reran all Consumer shell syntax, version-baseline (`stable=1.1.0 candidate=1.2.0`), Website 79/79, PHP management-ID guard, and `git diff --check`.
- PASS: Orchestrator independently reran the complete Runtime Consumer with `GIT_CONFIG_NOSYSTEM=1` and `GIT_CONFIG_GLOBAL=/dev/null`; it passed Stable `e3df5576`, candidate `577cc224`, 11 migrations, Provider-present HTTP／Worker, Provider-missing Classic HTTP／Worker safe-negative, and cleanup with exit 0.
- PASS: Independent Documentation Reviewer returned P1=0／P2=0／P3=0 after one management-document correction cycle and permitted the P22-003C Correction Commit. The Reviewer confirmed all three Source corrections, no skip／retry／allow-failure, the new-candidate gate reset, and publication prohibition.

## Acceptance Criteria

- [x] Runtime Consumer creates the annotated local candidate tag without global/system Git identity and completes the journey.
- [x] Identity configuration is limited to the disposable repository.
- [x] Quality checkout uses `fetch-depth: 0`, allowing the version guard to inspect Stable `1.1.0` history.
- [x] Guide PHP syntax test handles complete examples and fragments with one opening tag; all 79 Website tests pass.
- [x] No failed CI or Documentation step was skipped, retried, allow-failed, or severity-downgraded.
- [x] Shell syntax, version guard, Mago format after generated cleanup, management-ID guard, and `git diff --check` pass.
- [x] Report and STATE record commands, outcomes, the known unexecuted/unsatisfied check, and remaining work.
- [x] Worker made no Commit, Push, or PR mutation.

## Remaining Issues

- The initial Mago format check was contaminated by five Git-ignored Frontend build artifacts; CI-equivalent generated cleanup removed them and the exact check now passes.
- The parent P22-003 complete Local Gate passes at `96383e1bbe1a0914d1eddc9e1dea160042804f7c`. Same-SHA Remote CI, final Documentation Review, and P22-003 acceptance remain pending.

## Suggested Next Action

Update Draft PR #3's candidate branch to exact correction Commit `96383e1bbe1a0914d1eddc9e1dea160042804f7c`, run same-SHA Remote CI, and then perform final Documentation Review. Do not publish or deploy.
