# P23-008A: Landing Reader Flow and Motion

Status: Locally accepted by Orchestrator, 2026-09-09; publication not requested

## Goal and Authority

Apply the user's follow-up to the locally accepted P23-008 TOP walkthrough:
manual pause must retain moving arrows, automatic steps must leave enough time
to read, and individual explanations/events need links to the relevant guides.
The subsequent user correction requires explicit Inline/Deferred tabs, a clear
explanation for each mode and looping only within the selected mode.
The user also rejects the duplicate authoring section and vague lower navigation.
Make TOP read as overview, execution explanation, then a concrete tutorial journey.
The latest visual request revisits https://reactbits.dev and asks for a much
stronger background. Use the observed broad luminous bands and subtle texture
as direction for a native lime/neutral background with visible depth and motion.
The subsequent visual correction makes Inline the initial mode, simplifies the
tab and panel borders, replaces Journal timelines with linked event tags and a
short Journal definition, removes redundant example/status prose, fixes text
alignment/wrapping and adds a brief irregular neon arrival to the BlackOps title.
AGENTS, spec109 and spec110 govern this bounded follow-up. This supersedes only
the former rule coupling manual pause to all flow motion and the 2.8-second
cadence and automatic mode switching. Preserve the accepted design, canonical
Archify geometry and execution semantics.

## Ownership and Allowed Files

Root owns this Packet/Report, spec110/D159, TODO/STATE rotation, integration,
browser checks and Acceptance. Site Luna owns only:

- docs/website/scripts/execution-walkthrough.mjs
- docs/website/components/ExecutionWalkthrough.astro
- docs/website/theme.css (player controls and the revised lower tutorial layout)
- docs/website/tests/execution-walkthrough.test.mjs

Luna returns its evidence in chat; Root writes the combined Report. Existing
documentation reviewer may review the new link destinations read-only. No
shared DB, dependency, PHP/API, canonical diagram, guide-body or Release change.
Docs Luna independently owns pages/index.astro, only affected landing assertions
in tests/reader-experience.test.mjs, and obsolete authoring selector requirements
in scripts/artifact-stylesheet-contract.mjs, all under docs/website/. Site Luna
alone edits theme.css, coordinating lower-layout needs with Docs Luna.
Docs Luna also owns obsolete section guards in scripts/check-site.mjs and
scripts/product-framing-contract.mjs and their corresponding existing fixtures
in tests/product-framing-contract.test.mjs; new components/LandingBackdrop.astro;
and existing components/LandingMotion.astro/scripts/landing-motion.mjs for
decorative-motion lifecycle, scroll integration and the finite title arrival.
It may add a focused motion
regression case in its already-owned reader-experience test if behavior changes.
Root captures and inspects the real reference and local artifacts. Background
decoration is not an explanatory diagram and does not alter Archify assets.
Only Root builds into docs/website/dist and writes browser evidence. Preserve
all prior uncommitted work, especially the unrelated D2S1 Report. No stage,
commit, push or publication. Keep http://localhost:4321/ available.

## Acceptance Criteria

1. Manual pause freezes phase, number, active relationship and Journal while
   the active arrow trace continues when visible and reduced motion is off.
   Resume advances normally. Hidden/offscreen, reduced motion and cleanup
   stop the trace. Responsive SVG replacement inherits the correct trace state.
   Element inspection retains its separate selected-node semantics.
2. Replace the fixed 2.8-second interval with a reading allowance of at least
   10 seconds per phase, increasing with explanation and newly introduced event
   text, capped at 16 seconds. This is an editorial allowance, not measured
   human reading speed or product runtime. Loop only within the selected mode.
3. Hovering over the explanation on pointer devices or focusing within it
   holds automatic progression so links cannot move while being read/used.
   Leaving starts a fresh allowance, respecting manual pause and visibility.
   Pointer/touch behavior must not leave a sticky hover pause on touch devices.
   Arrows remain independent from this reading hold as from manual pause.
4. Every phase has a visible 詳しく見る link to a relevant existing guide/section.
   Every displayed Journal event is itself a link to its documented event
   reference. Existing per-node inspection links remain. Use native anchors,
   meaningful accessible names, focus indication and readable mobile targets.
   Initial/no-JS markup includes the initial phase link. Do not add microheadings.
5. Preserve exact phase/event mappings and counts, node-inspection numbers,
   topology, reduced-motion/manual/no-JS behavior and responsive layout.
6. Inline/Deferred are user-selected accessible tabs above the graph with a
   prominent mode explanation. Inline is selected initially, including SSR/no-JS.
   Give tabs one consistent visual treatment with an unambiguous selected state;
   avoid mixing outlined buttons with an attached underline.
   Inline runs within the HTTP Request and returns
   the result; Deferred commits acceptance, returns202/Operation ID and allows
   a separate Worker to complete the work. Tab changes reset to that mode's
   first phase; automatic progression never switches modes and resets the
   illustrative Journal at that mode's next loop. Preserve one shared graph.
   Provide tab/tabpanel relationships, selected state and arrow/Home/End keyboard
   operation. Avoid relying on a mixed bottom note to explain the two modes.
7. Remove the entire 実際のPHPコードから始める section and the generic six-category
   purpose navigation. Replace 必要な場所から読む with BlackOpsを動かす: three
   ordered links to environment installation, running the sample and creating
   an Operation, with descriptions supported by the actual destination guides.
   Use the section title/lead beside the ordered journey on desktop, stacked on
   mobile. Preserve the hero code, primary actions, CLI and entry links, routes,
   Release context and scroll-reveal behavior. Remove only obsolete styling/
   fixture requirements associated with deleted sections.
8. Replace the small diffuse glow with broad asymmetric lime light bands, quiet
   texture and depth across TOP. Slow transform/opacity motion and existing
   scroll reveals provide movement without interrupting reading. Use intentional
   light and dark treatments; preserve text/diagram contrast, focus and no
   horizontal overflow. Decoration is noninteractive and hidden from assistive
   technology. No new runtime dependency, remote font, canvas/WebGL loop or
   raster payload. Reduced/no-JS views retain a complete static design; hidden,
   offscreen and lifecycle cleanup stop ambient work. Keep foreground content
   and diagram rendering independent from decorative clipping or masks.
9. Remove the separator below the current-phase heading, the duplicate rules
   below phase details and the Journal's left timeline rules. Render linked event
   tags with a clear visual difference for newly added events. Replace dynamic
   この段階で記録されるJournalイベント prose with a short accurate Journal definition.
   Remove HTTPからOperationを実行する説明用の例です and the normal-example/timing
   disclaimer; retain only the user's requested Deferred Commit/HTTP202/Worker
   overlap note. Right-align the walkthrough intro lead. Mode descriptions use
   their available width and natural Japanese wrapping, without forced balanced
   short lines. Preserve native links, focus, reading holds and phase semantics.
10. The large BlackOps word arrives through irregular per-character light pulses,
    like a neon sign starting, then remains fully readable. Use one short finite
    sequence with restrained pulse frequency, no continuous blinking, no layout
    shift and no new dependency. No-JS/reduced-motion views show the whole title
    immediately. Hidden/offscreen/cleanup settle the letters and cancel pending
    work. Preserve the accessible heading name and current source/search text.

## Verification Plan

Luna: node --check docs/website/scripts/execution-walkthrough.mjs and
node --test --test-isolation=none docs/website/tests/execution-walkthrough.test.mjs.
Add focused behavioral regression for pause versus trace, readable scheduling,
interaction hold, correct links and lifecycle/visibility. Use controlled timers
to check both complete mode loops, including Journal resets and keyboard tabs;
confirm real individual test names/counts. Record command, runtime,
input hashes, exit, elapsed time and logs under /tmp/blackops-archify/p23-008a/.

Docs Luna runs only relevant existing reader-experience cases with
node --test --test-isolation=none --test-name-pattern <related names>
docs/website/tests/reader-experience.test.mjs, recording actual matching counts,
and syntax-checks its changed MJS files. No duplicate build or full44-case run.
Run related named product-framing cases only if its guard contract changes.

Root: review the delta against P23-008, run pnpm --dir docs/website run blume:check
for the changed component contract, build once with pnpm --dir docs/website
build (includes artifact/site guards), then focused Chromium desktop/mobile
checks on the actual artifact. Prove dashoffset changes while phase/Journal stay
fixed longer than the allotted dwell; cover real automatic transition, reading
hold, responsive replacement, reduced/hidden/offscreen/cleanup, all phase/event
link destinations and actual navigation. Inspect changed link pixels and run
axe on the changed TOP surface. Inspect the revised lower page at desktop/mobile,
verify its three destination links and omitted redundant sections. Include a real timed same-mode loop boundary,
visible mode explanation and keyboard tab switching. Review new reader routes with the documentation
reviewer without repeating the unchanged full documentation audit.
Capture the new background in both themes and desktop/mobile, verify actual
decorative motion and reduced/hidden/offscreen/no-JS/cleanup behavior, and record
the bounded asset/runtime change. Parent Lighthouse measurement remains a
historical baseline; do not assert a new performance score without measuring it.
After the final correction, cover initial Inline SSR/client state, the simplified
tabs/panels and event-tag pixels, current prose/line layout and the finite title
arrival (irregular letters, settled readability, reduced/no-JS/lifecycle). Reuse
unchanged long reading-dwell evidence; rerun only affected behavioral cases and
the final build after the source is frozen.

Reuse P23-008 evidence for unchanged canonical assets, product semantics,
all-guide surfaces and scroll motion. No full PHP or full website test run,
Lighthouse rerun or full16-phase wall-clock loop is required.
Broaden only after a changed input or concrete failure. Record Release impact
as presentation/navigation-only with the existing authority and no new claim.

## Remaining Work and Next Action

All ten criteria are locally accepted. Final source:
a69f18ecbb2a69d5a0ee1169d327f016f25dd76b579e7364ce382632a187a938.
See the Report for command/input reuse, final pixels and limitations.
No local work remains. Next Action: reload http://localhost:4321/.
Publication remains unrequested.
