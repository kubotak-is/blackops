# P20-008A Mermaid Diagram Legibility Report

## Summary

Implemented local Theme CSS for the four native Blume Mermaid diagrams. The host now uses the article width on Desktop, while the rendered output keeps a 42rem minimum width for readable labels on Mobile and scrolls inside the existing Mermaid host. The website README, static artifact guard, and reader-experience tests now document and protect this contract.

## Root Cause

The Blume Mermaid element renders an SVG inside a direct child output container. Without an explicit output width, the flex/overflow host allowed the SVG to shrink to roughly 300px on a 672px Desktop article. Wide diagrams therefore remained technically present but became unreadable.

## Changed Files

- `docs/website/theme.css`
- `docs/website/README.md`
- `docs/website/scripts/check-artifact.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `develop/orchestration/tasks/P20-008A-mermaid-diagram-legibility.md`
- `develop/orchestration/reports/P20-008A-mermaid-diagram-legibility.md`
- `develop/STATE.md`
- `develop/TODO.md`

No Diagram Source, Blume Package, Generated Content, Public Route, or Framework `src/**` files were changed.

## Desktop Contract

`blume-mermaid` is a block-level host capped at 700px and sized to 100% of the article. Its direct output child and SVG are also 100% wide, so the four Desktop diagrams use the 672px article width (above the 95% and 640px minimum thresholds).

## Mobile Contract

The output child has `min-width: 42rem` and the SVG keeps that width through its 100% sizing. The Blume host's existing `overflow-x-auto` provides local horizontal scrolling at 390px; the page and article remain width-contained. The host is not given a negative transform or forced centering, preserving left-edge reachability.

## Theme/Accessibility Preservation

Only dimensions and display behavior are changed. No Mermaid colors, generated SVG markup, accessibility metadata, or theme-switching behavior are overridden. `height: auto` preserves the SVG aspect ratio, and the existing local Mermaid runtime remains unchanged.

## Commands and Results

- `mise exec -- pnpm --dir docs/website run test` — PASS (57/57)
- `mise exec -- pnpm --dir docs/website run check` — PASS (content, diagrams, validation, Blume check: 37 pages, 0 errors/warnings/hints)
- `mise exec -- pnpm --dir docs/website run build` — PASS (38 pages, 37 public routes; Artifact and Site guards passed)
- `docker compose run --rm app mago format --check src tests` — PASS (`All files are already formatted.`)
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\\.md:[0-9]+' src tests --glob '*.php'` — PASS (no matches)
- `git diff --check` — PASS

Correction verification: after synchronizing `docs/website/README.md`, `mise exec -- pnpm --dir docs/website run test` — PASS (57/57); `git diff --check` — PASS.

## Browser Evidence

The Orchestrator ran Playwright Chromium against the actual localhost:4322 implementation. All four Desktop Light routes measured Article／Host／Output／SVG at 672px, with one SVG, no busy or error state, and no Document／Article overflow. The Execution Diagram kept the same 672px by 542.69px dimensions after switching to Dark Theme. At Mobile 390px, all four routes measured Article／Host client width 342px, Host scroll width 672px, Output／SVG width 672px, and initial `scrollLeft=0`. The Execution Diagram was then scrolled to `scrollLeft=330`, proving that its right edge is reachable without Page or Article overflow. Light, Dark, Mobile left-edge, and Mobile right-edge screenshots were visually reviewed.

## Acceptance Criteria

- [x] Desktop width contract implemented and guarded.
- [x] Mobile minimum diagram width and host-only overflow implemented and guarded.
- [x] Aspect ratio, theme, accessibility metadata, local runtime, and routes preserved.
- [x] Source/artifact regression checks added.
- [x] Website test, check, and build gates passed.
- [x] Required Docker Mago, management-ID, and diff gates.
- [x] Orchestrator Browser Review (Desktop Light/Dark and Mobile 390px).

## Remaining Issues

No remaining issue within P20-008A. External Publication／Deploy and the third Documentation Review P2／P3 remain separate tasks.

## Suggested Next Action

Proceed to P20-010 for the third Documentation Review P2 Task-oriented Guide improvements.
