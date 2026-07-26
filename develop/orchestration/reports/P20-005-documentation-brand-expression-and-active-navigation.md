# P20-005 Documentation Brand Expression and Active Navigation

## Summary

Rebuilt the Blume landing as a centered developer-tool entry point with a deep grid canvas, real Operation authoring code, canonical lifecycle states, and generated-client command visuals. Normalized configured sidebar routes to canonical no-trailing-slash paths and added a static artifact regression for the active sidebar anchor.

## Changed Files

- `docs/website/pages/index.astro`
- `docs/website/theme.css`
- `docs/website/blume.config.ts`
- `docs/website/scripts/check-site.mjs`
- `develop/STATE.md`
- `develop/TODO.md`
- this report

Existing uncommitted P20-001 through P20-004 changes were preserved.

## Design Translation

The landing keeps `BlackOps` as the dominant heading and `The PHP Framework` as a smaller supporting line. Install, GitHub, and the Stable 1.1.0 Composer command are grouped in the hero. Product depth comes from a real `#[Route]`/`#[Deferred]` Operation fragment, the documented Deferred success lifecycle (`Received`, `Accepted`, `Running`, `Finalizing`, `Completed`), and generated-client commands (`build:compile`, `frontend:generate`, `frontend:check`). No fake dashboard, testimonial, logo wall, or new marketing claim was added.

## Authentic Product Visuals

The Operation panel uses the implemented `Route`, `Deferred`, `Operation`, `ExecutionContext`, and typed `ReportGenerated` authoring shape. Lifecycle event rows use documented event names, including `operation.received`, `operation.accepted`, `attempt.started`, `attempt.succeeded`, `operation.completed`, and `attempt.retry_scheduled`. Feature body copy and the Operation, Journal, and Headless link targets remain exact.

## Active Navigation Root Cause / Fix

Blume compares the generated current route without a trailing slash against the configured sidebar href. Every sidebar href in `blume.config.ts` is now canonical without a trailing slash; banner and redirect patterns were left unchanged. `check-site.mjs` reads each built non-index public route and requires exactly one `aria-current="page"` anchor whose href equals that route. It also guards the visible active background, foreground, and inset accent marker CSS.

## Responsive / Theme / Accessibility

The hero switches to a single-column flow below 900px, feature reading order is Operation → Journal → Headless on mobile, code panels scroll internally, and the tagline remains one line at 390px. Semantic links retain keyboard focus rings. Theme tokens cover light and dark foreground, surface, border, accent, and action colors. Reduced motion disables landing transitions and hover translation.

## Commands and Results

- `mise exec -- pnpm --dir docs/website run test` — PASS (50 tests)
- `mise exec -- pnpm --dir docs/website run check` — PASS (content, diagrams, Blume validation, typecheck)
- `mise exec -- pnpm --dir docs/website run build` — PASS (38 built pages, artifact boundary, site check for 37 routes)
- `docker compose run --rm app mago format --check src tests` — PASS
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'` — PASS (no matches)
- `git diff --check` — PASS

## Follow-up GitHub and Layout Checkpoint

At `2026-07-26T02:52:21+09:00`, the Blume native Header GitHub icon requirement was synchronized into D119, Specification 86, the Task Packet, TODO, and this report. The final static CSS/test changes keep the desktop brand clear, stack right-side feature content, and place the Operation link below its body. Website tests remain 50/50 and `git diff --check` passes. Normal Blume check/build remain blocked only by the existing shared dev server on port 4322; isolated check/build and isolated artifact inspection pass.

## Browser Review Correction

The supplied 1440px light/dark and 390px light screenshots showed three layout issues. The desktop brand cap is now `8.5rem`, keeping the wordmark clear of the Operation code panel. Journal and Headless right-hand features now stack their visual above copy at every breakpoint, including mobile, and the Operation link is anchored directly after its body copy instead of being pushed to the bottom of the spanning grid row. Landing copy and targets were not changed.

The Blume-native GitHub header icon was enabled with `github: { owner: 'kubotak-is', repo: 'blackops' }`. `check-site.mjs` now requires every built page to contain the exact repository URL, an accessible label, `target="_blank"`, and `rel="noreferrer"`; the test suite also locks the native config. The Hero GitHub CTA remains.

Correction gate results:

- `mise exec -- pnpm --dir docs/website run test` — PASS (50 tests)
- `mise exec -- pnpm --dir docs/website run check` — blocked by the existing shared Blume dev server on `localhost:4322`; isolated `blume check --isolated` — PASS (0 errors)
- `mise exec -- pnpm --dir docs/website run build` — blocked by the same shared server; isolated `blume build --isolated` — PASS (38 pages, output `.blume-verify/dist`)
- Isolated artifact inspection — PASS for native GitHub header URL/label/target/rel on Landing and Installation
- `git diff --check` — PASS

The Blume build emitted its existing dynamic-route conflict warning for the explicit landing route; the build completed successfully and all artifact guards passed.

## Acceptance Criteria

- [x] Landing visual rebuild, hierarchy, and authentic product visuals
- [x] Header visible wordmark is `BlackOps`; metadata title remains unchanged
- [x] Install, GitHub, and Stable command are in the hero
- [x] Operation, Journal, Headless copy and targets preserved
- [x] Canonical sidebar hrefs and one active `aria-current="page"` anchor per non-index route
- [x] Clear active state, responsive mobile order, light/dark tokens, focus and reduced-motion rules
- [x] Existing public slugs, redirects, banner, search, artifact boundary, and Framework `src/**` preserved
- [x] Website full gate and required Framework checks pass
- [x] No commit created

## Remaining Issues

No implementation blocker.

## Suggested Next Action

Proceed to the Stable 1.1.0 onboarding and Quickstart documentation task.

## Orchestrator Acceptance

At `2026-07-26T03:04:54+09:00`, the Orchestrator independently reviewed the source, static artifact, live HTTP routes, and Playwright Chromium renders at desktop 1440px light/dark, mobile 390px light, and the Validation detail page in dark mode.

- Desktop brand and Operation code bounding boxes do not overlap.
- Mobile Journal and Headless features resolve to one full-width column.
- Desktop and mobile document widths equal their viewport widths; no page-level horizontal overflow exists.
- The Header GitHub icon resolves to `https://github.com/kubotak-is/blackops` with `aria-label="GitHub repository"`, `target="_blank"`, and `rel="noreferrer"`.
- Validation has exactly one active Sidebar link for `/operations/validation`, with a visible background and inset accent marker in dark mode.
- Landing, Install, First Operation, Lifecycle, Frontend, and Validation return HTTP 200 from `http://localhost:4322`.
- Orchestrator reruns passed Website 50 tests, the 37-route static site guard, Mago format, the Management ID guard, and `git diff --check`.

P20-005 is accepted. The local Blume development server remains available on port 4322. No commit was created.

## Correction Checkpoint

At `2026-07-26T02:43:54+09:00`, the shared `index.astro` source was inspected around the Headless feature. It contains one necessary close for `landing-client-visual`, immediately followed by `landing-feature-copy`; no additional closing `div` exists in the current worktree. A permanent artifact structure guard was added to `check-site.mjs` to require that exact visual-to-copy order, preventing a future regression. The task status is now `Review Pending` and `STATE.md` was synchronized to the final worker completion timestamp `2026-07-26T02:45:20+09:00`.

Correction verification:

- `mise exec -- pnpm --dir docs/website run test` — PASS (50 tests)
- `mise exec -- pnpm --dir docs/website run check` — PASS
- `mise exec -- pnpm --dir docs/website run build` — PASS (38 pages, artifact and site checks)
- `git diff --check` — PASS
