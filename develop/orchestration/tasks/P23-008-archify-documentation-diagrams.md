# P23-008: Documentation Diagram and Website Redesign

Status: Locally accepted, 2026-09-09; publication not requested

## Goal and Authority

Redesign the entire documentation site using https://reactbits.dev as the
visual reference, while retaining the BlackOps lime accent. The user rejects
the old design and the subsequent narrow figure/right-panel composition.
Use wider composition, strong typography, a dynamic hero and readable diagrams.
The first screen must show useful content and actions without excessive empty
space. Align the real Operation code with the hero message. Use visible lime
ambient light and meaningful scroll reveals on TOP, with reduced-motion support.

Latest user instructions supersede older right-sidebar and transcript contracts:

- Use one full-width animated Archify explanation on TOP, with the explanation
  BELOW the graph at every viewport. Desktop is landscape; mobile is portrait.
- Clicking a node pauses playback and changes the element explanation. Only the
  詳しく見る link navigates. Keep the current phase number visible during
  inspection; selection and the paused execution phase remain distinguishable.
- Synchronize graph, phase numbers, exact Journal events and visible directional
  motion. Use small fixed arrowheads and a moving trace on the real edges.
- Remove the redundant アプリケーションの仕事を、受付から完了まで読める実行単位にします。
  sentence, 全段階とJournalイベントを表示 disclosure, and 追加なし labels.
- Use Journalイベント as the heading. Remove meaningless decorative microheadings,
  including brand/category repetition and numbered English section labels.
- Hero actions remain What's BlackOps then Install. Keep HTTP, Console and
  Schedule entry links. The separate concepts map remains in its guide only.
- All explanatory diagrams use the actual Archify Skill. Japanese glyphs use
  local Noto Sans JP. No diagram horizontal scroller or clipping workaround.

Authority: AGENTS, STATE, spec110, spec109/92/104 and Release Authority.
Experimental Stable1.2.0 / roadmap1.3 unreleased. Preserve existing public routes,
40 reader pages,41 Search records, product facts, source/raw/Search/LLM parity.
No PHP/API/version change. No commit, push, publication or production deployment.
The user authorized local viewing; keep http://localhost:4321/ available.

## Ownership and Allowed Files

Root owns integration, acceptance, Packet/Report/spec110/D159/STATE/archive/index/
TODO, scoped guide prose, maintenance README, canonical asset imports and final
serial checks. Workers do not edit STATE, stage or commit. At most4 active agents.
Root also records the observed Node file-wrapper-only test evidence issue in
spec109: preserve package-script isolation settings and verify actual case counts.
This narrow workflow correction follows the user's permanent efficiency request.

Site Luna owns pages/index.astro, theme.css, ExecutionWalkthrough.astro,
execution-walkthrough.mjs and related reader/product/site guards and tests.
It owns TOP motion imports and semantic data-landing-reveal hooks.

Docs Luna owns design-tokens.css, documentation.css, shared Blume configuration,
NoEditLayout if required, isolated LandingMotion.astro/landing-motion.mjs,
Archify registry/artifact/release guards and their focused tests. It also owns the
independent browser harness. No simultaneous ownership of production files.
Documentation Reviewer is read-only; final review covers changed facts and
unresolved findings without repeating accepted unchanged investigations.

Diagram scope: docs/website/diagrams manifest and offline patch, public/diagrams
JSON/HTML/SVG, docs/guide/assets/diagrams PNG. Guide prose scope: README,
core-concepts, execution, execution-context, operation-lifecycle and outbox.
Local Japanese font, license, provenance and regeneration instructions are allowed.
No new runtime dependency, remote font/CDN, React migration or fake product UI.
The local Lighthouse measurement exposed large inherited TTF preloads. A bounded
lossless WOFF2 conversion of the existing Ubuntu fonts is allowed, retaining all
glyphs, weights, metrics, font metadata and the exact current visual typefaces.
Record source/output hashes and remeasure the final build. No font redesign or
unrelated runtime optimization is included in this delivery correction.
Docs Luna owns this correction's font configuration/provenance, artifact font
bindings and the two font-source assertions in reader-experience.test.mjs after
Site Luna freezes that test file. Root retains final build/browser ownership.

The user explicitly authorized updating the local design-taste-frontend Skill
outside the repository. Root used skill-creator for the narrow validated update
removing decorative overline defaults while retaining useful processing numbers.
This does not authorize memory edits or external publication.

## Diagram Inventory and Provenance

Ten logical diagrams cover runtime/core concepts, TOP HTTP overview, Inline,
Deferred acceptance, Worker execution, ExecutionContext, success, rejection,
failure and Outbox. A desktop Overview variant represents the same explanation.
Preserve complete topology and equivalent Japanese prose when splitting figures.

Use actual Archify pinned revision2ead014aa8ec91f104cd052f1a6ca82de5e26c31,
package2.17.0-dev.1, restored under /tmp/blackops-archify. Apply its Skill,
schema and showcase quality. Every authored layout requires actual9/9 validate/
deliver receipts, zero errors/warnings and source/output hash bindings.
Do not post-edit delivered HTML. Generate all images through the real viewer.

Public figures use width:100%, max-width:100%, height:auto and readable geometry.
Canonical JSON/HTML remain maintenance artifacts; readers need not open an
English viewer. Overview SVG variants require actual export receipts, exact
Noto font embedding and zoom invariance. Preserve fixed paths, Git tracking,
no-orphan, safe-path, asset-hash, Release and accessibility guards. Root may stage
exact generated assets solely for existing tracking checks without committing.

## Execution and Interaction Contract

Normal HTTP binds/validates Value, selects Strategy, then records
operation.received. Inline starts an Attempt in the HTTP process, runs the
Operation, records attempt.succeeded then operation.completed, and maps Outcome
to Response; no Outcome Record is stored. Deferred commits Value/Context and
received/accepted Journal in the acceptance Transaction, returns202 with
Operation ID, and allows a separate Worker to Claim and run. Worker completion
commits Outcome storage and succeeded/completed Journal in one Transaction.
HTTP202 arrival and Worker execution may overlap after acceptance.

Seven node IDs: http-entry, value-binding, strategy, durable-transport, worker,
operation, outcome. Seven edge IDs: request-input, input-strategy,
inline-dispatch, deferred-accept, worker-claim, worker-dispatch, operation-outcome.
Inline has7 phases; Deferred has9. Counts are phases, not permanent node IDs.
Reset the illustrative Journal between the two examples; retain natural looping.
No invented relationship, live-log claim, timing measurement or fabricated ID.

Native buttons preserve keyboard/touch operation and selection semantics.
Inspection preserves the phase/Journal and highlights the chosen element;
resume/manual/mode controls restore flow. Responsive switching preserves state.
Geometry failure must not leave clickable buttons on mismatched positions.

Offscreen/hidden pages pause timers. Reduced motion disables autoplay and visual
motion while manual controls remain available. No automatic frame announcements.
No-JS users retain a static figure, useful explanation and guide navigation.
TOP scroll reveals use native CSS/IntersectionObserver and finite transform/
opacity motion, with no-JS visibility, keyboard focus and page-lifecycle cleanup.
Do not force scroll position or replace the timed execution loop with scrolling.

## Verification Plan

Use focused worker syntax/tests with command, time, logs and source hashes.
Root reviews an actual pilot BEFORE broad final checks. Final checks cover:

- Content determinism, Blume types, build and artifact/site guards.
- Changed reader/player/registry/release negative fixtures. Reuse unchanged
  successful tests from the prior full168-test run and focused worker receipts.
- Actual desktop/mobile diagrams,390/768/1440 widths and both themes;1024 for
  responsive transitions. No horizontal overflow, clipping or unreadable labels.
- Node inspection/link navigation, phase numbers, all16 phase/event mappings,
  natural loop/reset, pause/resume, responsive state, visibility and reduced motion.
- Meaningful scroll motion, no-JS/focus behavior, hero actions, search/navigation.
- Axe on changed public surfaces and a local mobile Lighthouse measurement.
- Focused closure of prior P3 contrast and Failure-node line-spacing findings.

Do not rerun PHP suites, PHPStan, Mago or Deptrac: their inputs are unchanged.
Do not repeat passing commands merely because reviewer ownership changes.
If source changes or a real failure appears, broaden only the affected checks.
Remote same-SHA CI/export/release gates remain unchanged and are not claimed here.
Update STATE through guarded rotation and record remaining work and Next Action.

## Current Evidence and Remaining Work

Local criteria are complete at frozen input20331774b830195997926c996b5087c6d974023eff3ac9339ba9cdb52436466e.
Content/type/build/artifact/site checks pass; affected127 tests pass. Browser
coverage includes36 route/width/theme cases, all16 phases/natural loop, inspection,
scroll/reduced/no-JS behavior and final pixels. The read-only reviewer closed all
findings. The Report records exact provenance, command receipts and reused inputs.
Lighthouse is measured honestly; its uncompressed local LCP remains above the
Skill target, despite font delivery reducing LCP13.4s to8.9s. That limitation is
recorded separately from local visual/functional acceptance.

Local Task remaining work:none. Keep http://localhost:4321/ available for review.
Publication is unrequested. Separate Framework parent work (D2S adapter, other
reader children, D3/D4/D5, parent D and post-Accepted-F control) remains outside
this Task. Historical rejected work stays under orchestration/history.
