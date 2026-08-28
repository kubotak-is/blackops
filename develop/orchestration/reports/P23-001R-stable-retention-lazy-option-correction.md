# P23-001R Task Report

Status: Accepted — Stable 1.2 five-option Retention contract restored

Updated At: 2026-08-26T14:14:17+09:00
Accepted At: 2026-08-26T14:14:17+09:00

## Summary

P23-001R restores the Stable `1.2.0` five-period Retention contract at the
Project Root. The shared Lazy definition now accepts
`--idempotency-record-days` as a required-value option for both
`retention:plan` and `retention:purge`. Fresh-kernel Help and Binding coverage,
the two public Guide pages, Website Source/Artifact guards, reader coverage,
and the Stable version baseline are synchronized. Retention calculation,
omission fallback, purge mutation, AuditRecord, and the unreleased BlackOps
`1.3` boundary are unchanged.

The bounded guard correction is complete in the exact two production guard/test
files. The subsequent management-only synchronization is limited to this
Report, Task, STATE, TODO, and Specification README. No frozen source, PHP,
Guide, reader, dependency, or generated artifact path was edited by the
management correction.

## Documentation Review and Correction

The initial read-only Sol review is Historical input: Changes Requested
`P1=0/P2=1/P3=0`. Its sole technical P2 root was the aggregate closure in
`assertProductFramingArtifactContract`: a missing five-option boundary,
omission fallback, or `--idempotency-record-days` in one HTML/Search/raw/LLM
surface could pass because another surface supplied the marker. Production
Retention Search/LLM content exists only in `search-all`／`llm-full-all`, so a
CLI page could falsely substitute for the Retention page.

The correction validates CLI HTML/raw/search/LLM independently after visible
normalization and binds Retention HTML/raw/search/LLM independently to fixture
aliases or the production page/raw/search-all/llm-full-all alternatives. The
search aggregate is fail-closed JSON with exactly one
`route=/database/retention` record whose `content` is a string. The LLM
aggregate is fail-closed to exactly one correctly sourced `# Retention`
segment ending at the next page delimiter. Negative fixtures cover all eight
lanes, current truth omissions, and production missing/wrong/duplicate route or
segment cases. A generated `raw:database/retention.mdx` companion is excluded
as non-public source, while the public raw `.md` surface remains required.

After the exact-five-file management synchronization, the final read-only Sol
Documentation re-review returned PASS `P1=0/P2=0/P3=0`. It confirmed the
technical root and management finding are closed, exact2/exact5 hashes match,
post-correction and inherited evidence are separated, and the Stable／roadmap
boundary is unchanged. Orchestrator independent review accepted P23-001R.

## Current Changed Scope and Hashes

- Correction implementation: `docs/website/scripts/product-framing-contract.mjs`
  SHA-256 `453e347673f36fd1f12795c448bf1183a4ed4b4d159774adf5942bfecc1dd3a0`;
  `docs/website/tests/product-framing-contract.test.mjs` SHA-256
  `aede7f9f2f1ea1f3271a0735bd20fe69928f28d16b777dc678e74c086b59ebad`.
- Management-only synchronization: this Report, the P23-001R Task, STATE,
  TODO, and Specification README. No other file is part of this correction.
- Frozen hash verification: Search reader `6dcc3601d3ff70c8ec8ab3a4515d2d28eb684ac0ee2a4866ce51c305396f8fe7`, Search test
  `5728c9bc45c7dc150fd378207f3bebeecf6dc4805de95b32dec8d1ad813030fb`, lock
  `77403af7259c1948d56db4f53a05bf6e172aea1090083e28506f2bb84ca2db01`, Audit
  D145 `7ae4247423199ed8f428a50b372ded6b148ed2e19b5e24c860765d441ec33709`,
  Spec105 `587996196efa2510863de0b4e1aac57bd9a9d9ad5a4113617a7d088bd897e907`,
  and Spec106 `6f58ec12fe8df8893cf6c1499134ccc5fa2d58fe49f46b84e880fbfddbbb6a5b`
  match the frozen candidate.

## Post-correction Evidence

Evidence below was produced against the corrected two-file candidate before
this management-only synchronization; it is retained by hash and was not
rerun by this management worker.

- Focused Retention tests: `5/5 PASS`, including all eight independently
  guarded lanes and production-shaped route/segment negatives.
- `release:check:source`: PASS.
- Website check: `41` pages, `0` errors/warnings/hints.
- Website build: `42` pages, static artifact PASS, embedded site check `41`
  PASS. Existing non-fatal chunk-size and empty-route priority warnings only.
- `release:check:artifact`: PASS.
- Version baseline: PASS, `published=1.2.0`, `historical=1.1.0`.
- `git diff --check`: PASS; staged files: `0`.
- Actual generated HTML/raw/Search/LLM artifacts contain the exact Retention
  route/segment and five-option markers; the production aggregate selectors
  were exercised by the build/site evidence.
- PHPUnit `30/192` and Mago checks are retained guard-only evidence from before
  the correction; PHP/source/Guide hashes are unchanged, so they are not
  claimed as post-correction reruns.

## Historical P23-001R Implementation Scope

The original implementation packet allowed these 13 files; this historical
scope is retained for traceability. The current management correction is the
exact five-file scope recorded above.

1. `src/Internal/Application/ApplicationConsoleKernel.php`
2. `tests/Internal/Application/ApplicationConsoleKernelTest.php`
3. `docs/guide/project-cli.md`
4. `docs/guide/retention.md`
5. `docs/website/scripts/product-framing-contract.mjs`
6. `docs/website/tests/product-framing-contract.test.mjs`
7. `docs/website/tests/reader-experience.test.mjs`
8. `tests/Consumer/version-baseline.sh`
9. `develop/orchestration/tasks/P23-001R-stable-retention-lazy-option-correction.md`
10. `develop/orchestration/reports/P23-001R-stable-retention-lazy-option-correction.md`
11. `develop/STATE.md`
12. `develop/TODO.md`
13. `develop/spec/README.md`

Generated Website artifacts were not edited directly. The accepted parent
snapshot's 15 frozen files were preserved byte-for-byte.

## Decisions and Assumptions

- The inherited one-line Lazy definition correction is the bounded runtime
  change: `idempotency-record-days` uses `InputOption::VALUE_REQUIRED` and is
  applied through the shared definition to Plan and Purge.
- Existing direct command fallback remains authoritative: omission uses the
  configured Idempotency Record period, or the longest of the four base
  periods when that setting is absent. No fallback code was changed.
- Project Root examples explicitly show all five period options. Stable
  `1.2.0` remains current; BlackOps `1.3` remains unreleased.
- Product framing guards require the five-option current wording and reject the
  temporary four-option wording in Source and Artifact fixtures.
- No commit, stage, push, CI, deploy, publication, tag, or release was run.

## Historical Inherited Commands and Results

The following implementation-gate record predates the guard correction and is
retained as hash-bound evidence only.

### Before guard correction: Fast Gate

All commands in this historical block were run before the guard correction;
they are retained as inherited evidence only:

- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Application/ApplicationConsoleKernelTest.php tests/Internal/Console/RetentionPlanCommandTest.php tests/Internal/Console/RetentionPurgeCommandTest.php` — PASS, 30 tests, 192 assertions.
- `mise exec -- pnpm --dir docs/website exec node --test --test-isolation=none --test-name-pattern='Retention|retention' tests/product-framing-contract.test.mjs tests/reader-experience.test.mjs` — PASS, 3 tests, 3 passes.
- `mise exec -- pnpm --dir docs/website run release:check:source` — PASS.
- `bash -n tests/Consumer/version-baseline.sh && bash tests/Consumer/version-baseline.sh` — PASS, `published=1.2.0`, `historical=1.1.0`.
- `git diff --check` — PASS.

### Before guard correction: Final Gate

Final Gate was run exactly once after the public Source freeze and passed:

- `docker compose run --rm app mago format --check src tests` — PASS. The first
  attempt identified formatting in the allowed kernel test only; it was fixed
  with the bounded `apply_patch` correction, and this required check was then
  rerun successfully.
- `docker compose run --rm app mago lint` — PASS; 186 existing baseline issues
  were filtered and no new issue was reported.
- `docker compose run --rm app mago lint --verify-baseline` — PASS.
- `docker compose run --rm app mago analyze` — PASS, exit 0; existing output is
  71 issues (46 warning, 25 help).
- `mise exec -- pnpm --dir docs/website run check` — PASS; 41 pages, 0 errors,
  warnings, or hints.
- `mise exec -- pnpm --dir docs/website run build` — PASS; 42 pages, static
  artifact boundary PASS, embedded site check 41 pages PASS. Existing
  non-fatal chunk-size and empty-route priority warnings remain.
- `mise exec -- pnpm --dir docs/website run release:check:artifact` — PASS.
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'` — PASS; no matches.
- `git diff --check` — PASS.

## Acceptance Criteria

- [x] Shared Lazy definitions expose the five period options to both Retention commands.
- [x] Fresh-kernel Help and Binding coverage proves the fifth option is listed once and accepted by Plan and Purge; Purge uses `--dry-run` and checks migration state is unchanged.
- [x] Existing Retention semantics and omission fallback remain unchanged; direct Plan/Purge tests retain five-target coverage.
- [x] `project-cli.md` and `retention.md` document five explicit options and the omission fallback.
- [x] Source and Artifact guards require current five-option wording and reject the old four-option claim; reader coverage checks the Retention journey.
- [x] Version baseline keeps Stable `1.2.0` current and BlackOps `1.3` unreleased.
- [x] Final Gate PASS — one post-freeze run completed; the initial format
  diagnostic was corrected within the allowed test file before the passing
  required run.
- [x] Documentation Reviewer `P1=0/P2=0/P3=0` — final read-only re-review PASS.

## Remaining Issues

- P23-001R内の残件はない。
- Full Website test、Browser、same-SHA CI、deploy、publication、tag、release、
  commit／stageはTask範囲外のためNot Verified／未実行。
- 上位BlackOps `1.3` GoalにはSpecification 106のordered Phase 23 tasksと
  P23-006 release gateが残る。

## Suggested Next Action

Start the P23-002A Task Packet within the P23-002A/B/C atomic projection slice
defined by Specification 106. Commit, stage, push, CI, deploy, and release
remain unapproved and unexecuted.
