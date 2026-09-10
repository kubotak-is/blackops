# P23-011: Five-second Walkthrough and Progress Control

Status: Accepted — local documentation delivery; not published

## Goal and Authority

The user now requests a uniform five-second HTTP flow cadence and a pause button
that visually indicates time until the next phase. This explicitly supersedes
P23-008A's text-length-based 10–16-second allowance. Preserve its accepted design,
Inline-first tabs, phase/event links, diagram inspection and independently moving
arrows during manual pause. AGENTS/spec109/spec110 govern the bounded change.
The subsequent request adds frosted translucent surfaces to the GenerateReport.php
hero code card and HTTP walkthrough cards, allowing the existing lime background
to show through while keeping text, code and diagram contrast readable.

## Scope and Team

Primary workspace `/home/kubotak/projects/blackops` only.
Site Luna owns docs/website/scripts/execution-walkthrough.mjs,
components/ExecutionWalkthrough.astro, tests/execution-walkthrough.test.mjs and
related player control/progress, hero code card and walkthrough surface CSS in
docs/website/theme.css. pages/index.astro is allowed only if a targeted hero card
hook is necessary; do not change content or layout.
Use design-taste-frontend for a compact lime progress ring within the existing
native button. The canonical Archify diagram/geometry/assets are unchanged.
Root owns this Task/Report, spec110/D159 cadence synchronization, STATE/TODO,
integration, generated dist, browser checks and Acceptance.
Docs Luna independently owns P23-010 guide prose. P23-009 maintenance sources
are frozen after its worker correction; preserve that separate checkout.
No new dependency, PHP, release metadata, unrelated design, commit or deploy.

## Acceptance Criteria

- [x] Every Inline/Deferred phase has exactly 5000ms of active autoplay time.
  Remove the variable reading allowance logic and obsolete constants/claims.
- [x] The pause/resume button remains a native, keyboard-operable control with
  a clear accessible name and at least44px target. Its progress ring shows elapsed
  time consistently, leaving a visible portion for time until the next phase.
  Use the existing palette and a clear pause/play state; no extra decorative title.
- [x] The ring and phase change use the same timing state. At half a phase the
  ring is halfway; phase/mode/manual step changes reset it. Pausing freezes the
  elapsed value and resuming consumes only the remaining time. Reading hover/
  focus holds, hidden/offscreen states also freeze elapsed time without catch-up.
  Thus all phases receive5000ms of active time even across interruptions.
- [x] Manual pause and reading holds leave arrow traces moving when visible and
  reduced motion is off. Existing reduced-motion/no-JS manual reading and cleanup
  remain intact. Hidden/offscreen/cleanup cancel timer/frame work. No continuous
  screen-reader announcements of a changing number and no layout shift.
- [x] Preserve phase count/order, exact Journal mappings, per-mode loops, default
  Inline, node inspection/numbering and all detail links.
- [x] Focused behavioral tests and real browser elapsed/pause/resume evidence
  pass; controls remain readable at390/1440 in light/dark with no new overflow.
- [x] GenerateReport.php and the walkthrough's existing card surfaces use visible
  translucent frost: native backdrop-filter blur, restrained tint and the existing
  lime light behind them. Preserve code/diagram readability and subtle depth in
  both themes. Provide an opaque fallback when backdrop filtering is unavailable
  or reduced transparency is preferred; no canvas, new texture/image or heavy
  perpetual effect. Do not add extra dividers, stretch/crop diagrams or stack
  redundant opaque fills that prevent the requested translucency.

## Verification Plan

Luna: node --check and node --test --test-isolation=none
docs/website/tests/execution-walkthrough.test.mjs. Extend the existing behavioral
coverage for5000ms, shared progress, hold/resume remaining-time semantics,
phase/mode reset and lifecycle cleanup. Use controlled time, verify actual named
case counts and retain exact phase/event mapping assertions. No other test suite
or shared build. Record commands, inputs, runtime, exit, elapsed and logs under
/tmp/blackops-p23-011/; return a concise chat handoff, Root owns the Report.

Root reviews delta and timer lifecycle, then performs typecheck and one combined
website build after both P23-010 and P23-011 source changes settle. Do not build
twice for the same integrated input. Reuse the build/artifact/site guard receipt
across those tasks. Browser verifies a real five-second transition, halfway ring,
pause freeze/resume remaining time, arrow motion under pause, mode reset, reduced
motion and affected control pixels/contrast/targets. Check no browser errors or
document overflow. Reuse unchanged earlier full diagram and other design evidence.
Inspect the actual hero/walkthrough frost against the brightest background in both
themes, plus the reduced-transparency/unsupported fallback. Contrast must remain
readable for code, labels and controls after compositing the new surfaces.
No PHP tests or full website suite for this bounded player change.

## Release Documentation Impact

Presentation timing only; no Framework runtime duration or new event/capability
claim. Authority remains Experimental Stable1.2.0 / roadmap1.3.0 unreleased.
Public route `/` and its assets only. Source/Search/LLM guards run in the combined
build. No version occurrences or historical allowlist change.
same-SHA CI/Documentation delivery/Production deploy: Not Run, not requested.

## Completion

Report: develop/orchestration/reports/P23-011-walkthrough-progress-control.md.
Root closes local implementation separately from any later authorized publication.
