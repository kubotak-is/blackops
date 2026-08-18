# P22-005F: Documentation Clean-Checkout Artifact Fixture

Status: Accepted — Sol xHigh P1=0／P2=0／P3=0

Started At: 2026-08-18T17:07:54+09:00

Completed At: 2026-08-18T17:14:54+09:00

Accepted At: 2026-08-18T17:26:05+09:00

Independent Review: Sol xHigh final read-only verdict P1=0／P2=0／P3=0; P22-005F Local Acceptance supported.

Assigned Worker: Production Implementation Worker (GPT-5.6 Luna Max / max)

Base Candidate: `4d755cdb75c9b2f2c4d7eab9ee547bf011042511`

Branch: `agent/p22-005-documentation-governance`

## Goal

Make the full generated-page reader-contract test hermetic on a clean checkout. The HTML injection matrix must validate against a synthetic complete 40-page artifact created in the test temporary directory, without copying or reading the ignored local `docs/website/dist` artifact.

## Trigger and Root Cause

PR #10 CI runs `32088975752` / Job `95567261225` and `32088975758` / Job `95567261797` fail at `reader-contract.test.mjs:851` (117/118) because the test copies ignored `docs/website/dist` before running the HTML cases. A locally stale generated `dist` masks this dependency. The bounded Sol xHigh review verdict is P1=1, P2=0, P3=0; the correction is limited to the test fixture and management evidence.

## Scope

- Remove the `cp` import and its use from `docs/website/tests/reader-contract.test.mjs`.
- Add a hermetic test-local helper that writes a synthetic complete artifact from `contentMap`, excluding `README.md`.
- The helper must create, for all 40 reader pages:
  - `<slug>.md` with the exact JSON-quoted `description` reader outcome;
  - `<slug>/index.html` containing exactly its mapped reader outcome;
  - `blume-search.json` with exactly the 40 expected routes and outcomes;
  - `llms.txt` with exactly the 40 expected route lines and outcomes;
  - `llms-full.txt` with a `Source: https://.../<route>` line and the required reader-outcome marker for every route/segment.
- Rename the HTML full-artifact test so its synthetic-complete-artifact scope is explicit.
- Preserve all existing 37 HTML active/inert/malformed injection cases on the installation route, including the baseline `validateArtifactReaderContract` pass before mutation.

## Files Allowed to Change

Functional implementation:

- `docs/website/tests/reader-contract.test.mjs`

Management/evidence only:

- `develop/orchestration/tasks/P22-005F-documentation-clean-checkout-artifact-fixture.md`
- `develop/orchestration/reports/P22-005F-documentation-clean-checkout-artifact-fixture.md`
- `develop/orchestration/tasks/P22-005-documentation-governance-and-information-architecture.md`
- `develop/STATE.md`
- `develop/TODO.md`

Do not change `reader-contract.mjs`, website scripts, workflows, `package.json`, README, public Guide, public generated Artifact, or any other file. Do not stage, commit, push, dispatch/re-run CI, merge, deploy, or mutate Production.

## Acceptance Criteria

- [x] The test has no `cp` import/use and no dependency on `docs/website/dist`.
- [x] The synthetic helper creates a complete 40-page artifact from the Content Map and `validateArtifactReaderContract` passes before HTML mutation.
- [x] The 37 existing HTML active/inert/malformed cases remain present and retain their expected outcomes.
- [x] With the real `docs/website/dist` moved aside recoverably, the focused full-artifact test passes and the complete 118-test website suite passes; the artifact is restored by a trap.
- [x] `pnpm check`, fresh build, site/artifact guards, and Git diff/status checks pass as required below.
- [x] Required non-applicable Browser/Quickstart/Mago commands are recorded as Not Run because this is a website test plus management-only correction.
- [x] Task Report and STATE contain exact failure evidence, Release Documentation Impact, remaining work, and Next Action.

## Release Documentation Impact

- Authority tuple / Capability ID: Stable `1.2.0` and its capability inventory are unchanged; this is a test-hermeticity correction only.
- Public Source / route inventory: unchanged (41 sources, 40 reader routes); no public page, Search, LLM, slug, redirect, or Artifact content is changed.
- Version occurrence classification / historical allowlist: unchanged; no new public occurrence.
- Source / Search / LLM positive-negative fixture: the synthetic artifact is test-only and covers all 40 routes/outcomes plus the existing HTML active/inert/malformed negatives; no production artifact is regenerated as a deliverable.
- Same-SHA CI / Documentation delivery / Production deploy: not run or authorized in this task. P22-005F is Accepted; the orchestrator must fix the exact accepted snapshot before later remote gates.

## Required Verification

Use a recoverable move plus trap to make `docs/website/dist` unavailable, restore it after every command, and never delete it. Run:

1. Focused synthetic-complete-artifact test with `docs/website/dist` unavailable.
2. Full `docs/website` 118-test suite with `docs/website/dist` unavailable.
3. `mise exec -- pnpm --dir docs/website run check`.
4. Fresh `mise exec -- pnpm --dir docs/website run build`.
5. Site/artifact guards required by the parent CI contract.
6. `git diff --check` and `git status --short`.

Browser, Quickstart, Mago, PHP management-ID scan, CI, Commit, Push, Deploy, and Production verification are Not Run for this bounded website-test correction and must be stated in the Report.

## Expected Report

`develop/orchestration/reports/P22-005F-documentation-clean-checkout-artifact-fixture.md` must include Summary, Changed Files, Decisions and Assumptions, exact CI failure evidence, Commands and Results, Release Documentation Impact, Acceptance Criteria, Remaining Issues, Suggested Next Action, and explicit no-commit/no-CI-rerun/no-deploy boundaries.
