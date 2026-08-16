# P22-004H: PR #9 CI Contract Correction Report

Status: Documentation Review Passed, Commit Approved
Updated At: 2026-08-16T23:32:33+09:00

## Summary

The three PR #9 P1 contracts are corrected within the authorized source, static-guard, and Website local-font scope. The Documentation Review P2=3 correction adds an external-checkout fixture assertion, provider-only／remote `@font-face` rejection, and expected raw/emitted font/license SHA-256 plus license-title checks. Published Framework／Skeleton `1.2.0`, GitHub Release, and Packagist metadata remain immutable and were not republished. The earlier Blume `1.1.4` build was invalidated because its cached generated config still used Google fonts; the current evidence is exact Blume `1.3.0` with generated local provider config. Orchestrator Review and corrected Documentation Review P1=0／P2=0 passed on the exact uncommitted Working Tree; Commit／PR #9 Push is approved.

## Changed Files

- `.github/workflows/publish-skeleton.yml`
- `tests/Consumer/framework-update-runtime.sh`
- `tests/Consumer/version-baseline.sh`
- `docs/website/blume.config.ts`
- `docs/website/package.json`
- `docs/website/pnpm-lock.yaml`
- `docs/website/theme.css`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/scripts/check-artifact.mjs`
- `docs/website/public/fonts/UbuntuSans.ttf`
- `docs/website/public/fonts/UbuntuMono.ttf`
- `docs/website/public/licenses/Ubuntu-Font-License-1.0.txt`
- `develop/TODO.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/orchestration/tasks/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/orchestration/reports/P22-004-stable-1-2-publication-and-closeout.md`
- `develop/orchestration/tasks/P22-004G-stable-1-2-public-documentation-closeout.md`
- `develop/orchestration/reports/P22-004G-stable-1-2-public-documentation-closeout.md`
- `develop/orchestration/tasks/P22-004H-pr9-ci-contract-correction.md`
- `develop/orchestration/reports/P22-004H-pr9-ci-contract-correction.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Treat the PR #9 failures and independent final re-review as a correction Task, not as a release republication.
- Preserve exact runtime drift rejection while excluding only `examples/quickstart/README.md` from the runtime Source definition.
- Remove build-time dependency on external Google Font URLs; changing to another external URL is not deterministic enough.
- Strengthen the Manual Recovery restore contract with executable negative fixtures rather than static positive string presence alone.
- Use exact Blume `1.3.0` local font variants with repository-owned Ubuntu Sans／Mono assets and the Ubuntu Font License. Generated config, artifact, and reader/baseline guards reject Google/remote providers and verify emitted local references.
- Require exactly two generated `fontProviders.local()` calls, reject every non-local provider and remote `@font-face` URL, and verify the raw source assets, emitted copies, and `Ubuntu-Font-Licence-1.0` license against the recorded SHA-256 values.

## Commands and Results

- PASS: `bash -n tests/Consumer/framework-update-runtime.sh tests/Consumer/version-baseline.sh`.
- PASS: `bash tests/Consumer/version-baseline.sh` — published `1.2.0`／historical `1.1.0` baseline and Manual Recovery, runtime-drift, Website-font, and site-artifact static guards.
- PASS: disposable Manual Recovery fixtures reject an empty restore function, function-external checkout, removed restore hash equality, and early EXIT trap clear.
- PASS: the function-external checkout fixture retains exactly one checkout line outside `restore_harnesses()` and zero inside it before the negative contract assertion.
- PASS: current Workflow and Runtime Consumer each first satisfy exactly one README-only `:(exclude)` contract; README-only drift is accepted, `src` runtime drift is rejected, and broad/extra exclusion fixtures fail closed.
- PASS: `mise exec -- pnpm --dir docs/website test` — 83/83.
- PASS: `mise exec -- pnpm --dir docs/website run check` — content, diagrams, validation, Blume check 0 errors／warnings／hints.
- PASS: `mise exec -- pnpm --dir docs/website run build` — 42 pages; generated config used only `fontProviders.local()` paths, artifact boundary verified emitted local font references/license, and site check passed.
- PASS: `mise exec -- pnpm --dir docs/website install --frozen-lockfile` — exact Blume `1.3.0` installed; no cached `1.1.4` dependency remained.
- PASS: generated `.blume/astro.config.mjs` contains `fontProviders.local()` with `UbuntuSans.ttf`／`UbuntuMono.ttf`, and contains no Google provider or remote font URL.
- PASS: SHA-256 provenance — `UbuntuSans.ttf` `28c4c189a44803b1986fd16074187034dc6d94ad35f5e87de13dd0e786b70b73`; `UbuntuMono.ttf` `fbf1e748836994f730e602f7dcf2525564d6d78aa336080cbb73af909d0e08ee`; license `bca346a561b9668925ff55af1fcf0e10e65e07b1b40dd057bb4f3ded848ef8cf`; source `/usr/share/fonts/truetype/ubuntu` and `/usr/share/doc/fonts-ubuntu/copyright`.
- PASS: generated config uses exactly two local providers; reader negative fixture rejects `fontProviders.fontsource()`, and artifact guard rejects provider variants and remote `@font-face` URLs independently of provider name.
- PASS: provider matching accepts argument-bearing calls; the negative fixture uses `fontProviders.fontsource({ family: 'Inter' })` while retaining both provider calls.
- PASS: `mise exec -- pnpm --dir docs/website run site:check` — 41 search routes and existing source Markdown link rejection passed.
- PASS: `docker compose run --rm app mago format --check src tests` — all files already formatted.
- PASS: `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\\.md:[0-9]+' src tests --glob '*.php'`.
- PASS: `git diff --check`.
- Orchestrator evidence (not run by this worker): full `framework-update-runtime.sh` completed PASS — stable `e3df5576...`, candidate `3332fd1...`, migrations `11`, provider-present `http-worker`, provider-missing `classic-http-worker-safe-negative`.
- Not rerun for this P2=3 guard-only correction: Mago and the long full Runtime Consumer; no PHP/runtime source changed and the prior Mago/full-runtime PASS evidence remains attributed to the earlier validation/Orchestrator run.
- Not run: Remote CI, PR mutation, Commit/Push, publication, deploy, or public-ref mutation; prohibited by Task.

## Acceptance Criteria

- [x] Manual Recovery restore, runtime equality, and deterministic local-font/artifact contracts pass locally with negative fixtures.
- [x] Required local syntax, baseline, Website, Mago, management-ID, and diff checks pass.
- [x] Orchestrator Review passes with P1=0／P2=0.
- [x] Documentation Review P2=3 correction is implemented: external checkout placement/count, provider-only／remote URL guards, and raw/emitted/license SHA-256/title checks.
- [x] Documentation Review returns P1=0／P2=0／P3=1; the non-blocking provider-call wording P3 is corrected before Commit.
- [ ] PR #9 CI and Documentation delivery are all Green before merge.

## Remaining Issues

- The exact reviewed Commit／PR #9 Push and same-SHA Remote Green CI／Documentation delivery remain. Merge is prohibited until both workflows are Green.
- The separate `operation:inspect` bind-mount ownership limitation remains outside this Task.

## Suggested Next Action

Create one exact reviewed Commit and push it to PR #9, then require same-SHA CI and Documentation delivery Green before any merge decision.
