# P20-003 Documentation Accuracy and Navigation Report

## Summary

Review A-6の5件を実装Sourceと公開Guideへ同期し、D117／Specification 84の学習順Sidebar、全Public Slug配置、Fragment Anchor検証、日本語Version Bannerを実装した。LandingのHero、Operation／Journal／Headless本文、三要素Gridは変更していない。

## Changed Files

- `docs/guide/attributes.md`
- `docs/guide/application-bootstrap.md`
- `docs/guide/validation.md`
- `docs/guide/configuration.md`
- `docs/guide/execution.md`
- `docs/guide/README.md`
- `docs/guide/why-blackops.md`
- `docs/guide/core-concepts.md`
- `docs/guide/first-operation.md`
- `docs/guide/operations.md`
- `docs/guide/project-generators.md`
- `docs/guide/execution-context.md`
- `docs/guide/retention.md`
- `docs/website/blume.config.ts`
- `docs/website/content-map.mjs`
- `docs/website/package.json`
- `docs/website/pnpm-lock.yaml`
- `docs/website/site-navigation.mjs`
- `docs/website/scripts/content-pipeline.mjs`
- `docs/website/scripts/check-content.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/scripts/generate-content.mjs`
- `docs/website/scripts/validate-content.mjs`
- `docs/website/pages/index.astro`
- `docs/website/tests/content-pipeline.test.mjs`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/tests/site-navigation.test.mjs`
- `develop/decisions/116-blume-documentation-site.md`
- `develop/decisions/117-documentation-learning-journey.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/spec/84-documentation-learning-journey.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`

## Accuracy Corrections

1. `#[Sensitive]` now documents both `OperationValue` and `Outcome` properties.
2. Application Bootstrap references the complete eleven-file configuration contract instead of the obsolete fixed seven-file assertion.
3. Validation links now target the real Japanese heading fragments.
4. The Database configuration example is a standalone `static fn (Environment $env): array` closure.
5. The Transactional Outbox example includes `final`, `#[OperationType]`, imports, and a fully defined `#[Deferred]` child Operation.

## Navigation

The D117 order and labels are synchronized in both navigation sources. `validateNavigation()` rejects section reorder, missing, duplicate, unknown, and public-entry reorder. Tests assert all 36 non-index public slugs are present once and all sidebar labels match source H1s.

## Anchor Validation

The content pipeline rewrites fragments without inventing a second heading parser. Blume's `validate --strict` is the sole rendered-anchor authority and is mandatory in both `check` and `build` after content generation. The validator passed the real `validation.md` fragments; contract tests verify the validator command is mandatory and the corrected source fragments remain present.

## Version Banner

Blume now renders one Japanese line containing `main`, Stable `1.1.0`, 1.x Experimental policy, the planned 2.x Production Ready milestone, and a link to Releases. The unused `contentMap.versionBanner` source was removed; banner ownership is explicit in `blume.config.ts`.

## Decisions and Assumptions

- D117／Specification 84 supersede D116／Specification 83 navigation and banner provisions; Landing preservation remains active.
- Public slugs, redirects, search routes, and artifact boundaries remain unchanged.
- H1s for the six previously isolated pages were aligned to D117 labels so navigation and source headings stay discoverable and testable.

## Commands and Results

- `mise exec -- pnpm --dir docs/website run test` — 49 passed.
- `mise exec -- pnpm --dir docs/website run check` — content, diagrams, and Blume check passed; 37 pages, 0 errors.
- `mise exec -- pnpm --dir docs/website run build` — passed; Blume built 38 static pages, artifact boundary and search/site checks passed for all 37 public guide pages.
- `docker compose run --rm app mago format --check src tests` — passed; all files were already formatted.
- Management-ID guard (`! rg ... src tests --glob '*.php'`) — passed.
- `git diff --check` — passed.
- Blume Dev Server — started at `http://localhost:4322/`; Landing, What's BlackOps, Configuration, and Validation returned HTTP 200.

## Acceptance Criteria

- [x] Review A-6 five corrections
- [x] Permanent fragment-anchor regression checks
- [x] Public Slug Sidebar uniqueness and Missing／Duplicate／Unknown／Reorder guards
- [x] D117 labels/order and synchronized H1s
- [x] `What's BlackOps` navigation, H1, README, and Landing CTA
- [x] Japanese one-line banner with Releases link
- [x] Landing copy/grid preserved
- [x] Website full gate and quality checks passed
- [x] STATE／TODO／decision/spec synchronization
- [x] Worker did not commit

## Remaining Issues

Stable Quickstart, broader task-oriented content, and Site UX enhancements remain staged follow-up work in Specification 84.

## Suggested Next Action

Proceed to the Stable `1.1.0` learning-journey and Quickstart task. No commit was created.
