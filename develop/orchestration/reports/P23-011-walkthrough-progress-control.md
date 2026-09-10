# P23-011: Five-second Walkthrough and Frosted Progress Control

Status: Accepted — local website delivery; not published

## Summary

Every HTTP walkthrough phase now uses5,000ms of active playback. A lime ring in
the native pause/resume button shows elapsed time. Pause, reading and visibility
holds freeze that clock; resume consumes the remaining time. Phase/mode/manual
step changes reset it. Manual pause still leaves visible arrow traces moving.

GenerateReport.php and the HTTP walkthrough use translucent frosted card surfaces.
The existing lime background is visible through one outer glass layer; inner
reading/visual containers avoid duplicate opaque fills. Code and labels remain
readable in both themes. Local preview: http://localhost:4321/.

## Changed Files and Ownership

Site Luna implemented docs/website/scripts/execution-walkthrough.mjs,
components/ExecutionWalkthrough.astro, tests/execution-walkthrough.test.mjs and
bounded control/card changes in theme.css. Root synchronized spec110/D159 and
owns integration, Task/Report, TODO, STATE, shared build and browser review.
P23-010 independently owns five guide sources. pages/index.astro and canonical
Archify assets/geometry were not changed by this Task. No dependency or PHP change.

## Decisions and Assumptions

The explicit five-second request supersedes P23-008A's10–16-second reading
allowance. One monotonic clock and one50ms timer update progress and advance the
phase; there is no additional animation-frame loop or per-tick SVG update.
The ring is aria-hidden and fixed-size; the changing value is not continuously
announced. Existing native button labels/focus, Inline-first tabs, phase/event
mappings, diagram inspection and detail links remain intact.

Glass uses native backdrop-filter blur18px/saturate125% and a76% surface tint.
Unsupported filtering uses the opaque base rule. Reduced transparency also
restores an opaque surface and removes the blur. Reduced motion preserves manual
reading, and offscreen/hidden/cleanup suspend work. Canonical diagram export and
unchanged no-JS/link behavior retain P23-008A's evidence.

## Frozen Inputs and Environment

Primary HEAD remains `70f9c4733bed8bb0960165cddf8c451b78b96184`. Final1185-file
source/config/lock/spec inventory:
`/tmp/blackops-p23-011/gate-final/inputs.json`, aggregate SHA256
`084737ab153dda4a10db8e67e3330b87ec4676005afb53c43538148796db089f`.
All final gates confirm unchanged inputs. Worker JS/component/test/theme hashes
match `source-sha256-final.txt`. Only five guide anchors changed after the initial
type/test gate; explicit reuse reasoning is in `verification-reuse.json`.

Runtime: Node24.18.0, pnpm11.12.0, Blume1.3.0, Astro7.0.7. Actual browser:
Chromium149.0.7827.55 in cached `mcr.microsoft.com/playwright:v1.61.1-noble`,
nonroot user1000:1000, network none, repo read-only, temporary evidence writable.
The local HTTP fixture serves the final dist inside that container. No dependency
installation, external service or production request was needed for the browser.

## Commands and Results

Evidence root is `/tmp/blackops-p23-011/`. Timed wrapper JSON stores literal inner
command, UTC start, exit, seconds, source hash and log path. Combined gates are
shared with P23-010, not rerun for its Report.

| Command / owner | Result | Seconds | Evidence |
| --- | --- | ---: | --- |
| node --check docs/website/scripts/execution-walkthrough.mjs / Luna | PASS | Included in worker receipt | verification-receipt-final.txt |
| node --test --test-isolation=none docs/website/tests/execution-walkthrough.test.mjs / Luna | PASS4/4,0 failures | 0.990 | execution-walkthrough.test.v3.log |
| pnpm --dir docs/website run blume:check / Root | PASS38 checked files;0 errors/warnings/hints | 10.256 | gate/blume-check.json |
| Named Journal-parameter and Stable-release guide tests / Root | PASS2/2 | 0.061 | gate/guide-test.json |
| node --test --test-isolation=none docs/website/tests/release-claim-checker.test.mjs / Root | PASS10/10 | 0.162 | gate/release-fixtures.json |
| pnpm --dir docs/website build / Root | PASS41 pages; diagrams, content, artifact/site guards | 29.796 | gate-final/build.json |
| node docs/website/scripts/release-claim-checker.mjs source / Root | PASS | 0.997 | gate-final/release-source.json |
| node docs/website/scripts/release-claim-checker.mjs artifact / Root | PASS | 1.602 | gate-final/release-artifact.json |
| Browser timing/control/visual run / Root | Five timing checks pass before harness focus issue | 25.239 | gate-final/browser-timing-pass-harness-focus.json |
| Browser resumed at controls/visuals / Root | Twelve further checks pass before media style sampling issue | 12.612 | gate-final/browser-visual-pass-media-recalc.json |
| Browser resumed at fallback and guide pages / Root | PASS8 remaining checks | 5.007 | gate-final/browser-finish.json |
| python3 /tmp/blackops-p23-011/verify-guide-artifacts.py / Root | PASS5 routes ×5 surfaces | 0.012 | guide-artifacts.json |

The two named guide tests use node --test --test-isolation=none with the exact
name pattern recorded in gate/guide-test.json. Actual case names/counts were
checked; no file-wrapper-only PASS is accepted. Full website/PHP suite runs: zero.
Useful combined gate command time is approximately87s, excluding setup, failed
build attempts, agent/review waits and P23-010's separate1.45s PHP probe.

## Browser Evidence and Visual Review

Root accepts the25 successful browser checks across three preserved receipts;
a failed whole-script status is not relabeled as a pass. The later script resumes
at the failing harness assertion and closes it without repeating successful
five-second waits or changing product inputs. Aggregate binding and reasons are
in `browser/acceptance.json`.

- Sampled Inline4.980s and Deferred5.009s, with up to50ms progress sampling
  quantization; controlled tests verify the5,000ms deadline exactly.
- Halfway pause retained2676ms/53.51% across more than a full phase's wall time.
  Resume reached the next phase after approximately5.014s total active time.
- Arrow dash offsets changed during both manual pause and reading hold. Mode/
  manual steps reset progress; keyboard control has a3px visible focus outline
  and an86.8×46.8px paused target. Offscreen time preserves elapsed state.
- Reduced motion stops autoplay/arrows and keeps manual steps. Reduced
  transparency produces opaque cards with backdrop-filter:none after media
  style recalculation. Unsupported-filter fallback was reviewed in source and
  emitted CSS; a legacy browser was not installed for this check.
- Both cards use alpha0.76 and blur18px in light/dark at390/1440px; the code pre
  is transparent. All four TOP conditions pass axe with0 violations and no
  document overflow. Progress updates do not change the control's geometry.
- Root inspected actual hero, flow and halfway-ring screenshots in `browser/`.
  The lime light remains visible behind the code and walkthrough, with legible
  labels. Code samples retain their existing bounded horizontal scrolling;
  the page and diagrams do not gain horizontal scrolling.
- P23-010's five routes render at both widths, new heading anchors resolve,
  and Releases/Security pass axe with0 violations. Five cross-page encoded
  links also resolve in the final artifact (`guide-cross-page-links.json`).
- Browser pageerror lists are empty. Existing Python preview remains running;
  curl -I http://127.0.0.1:4321/ returned200 at2026-09-09T15:22:28Z, with the
  new37807-byte index last modified at15:12:48Z.

## Recovery and Evidence Reuse

Initial source inventory temporarily observed release-fixture directories created
by a concurrent test. Guide assertions had passed2/2, but that inventory receipt
was not accepted; after fixture cleanup only the small guide test was rerun.
The final inventory equals the frozen source. Diagnosis and initial receipt are
preserved in gate/guide-test-fixture-overlap*.

The first build caught five unsupported manual heading anchors in P23-010; Luna
replaced them with supported heading slugs. A second attempt passed content
validation but hit Astro's internal font-server listen permission. The approved
identical retry completed. These failures remain in gate/build.* and
gate-final/build-permission.*; source guards were not weakened.

A sandbox browser attempt produced no assertions and was treated as unverified.
Host Chromium then reported missing libnspr4.so, so Root reused the previous
Task's cached browser container. No host library install was added. The first
real run used programmatic focus after pointer input, which correctly did not
activate :focus-visible; the corrected keyboard interaction passed. The next
run sampled CSS immediately after CDP media emulation; waiting for media style
recalculation confirmed the fallback. Both harness corrections preserve earlier
successful checks and required no product modification or new build.

## Review and Acceptance Criteria

All local Task criteria pass. Root reviewed timer cancellation and shared elapsed
state, the native accessible control, single-layer glass CSS, actual pixels and
browser evidence. The specialized documentation review belongs to P23-010.
Unrelated D2S1 Report remains SHA256
`dc5ed5b6415be054b4b265e0e97a08007f3bfe253e9163cee5941e5b0f2fc955`.

## Release Documentation Impact

Only TOP playback/presentation changes: five seconds is a reader-facing cadence,
not a Framework runtime-duration claim. Published Experimental Stable1.2.0,
immutable Framework/Skeleton refs, roadmap1.3.0/unreleased, Capability IDs,
historical allowlist and event meanings remain unchanged. Source, Search, LLM
and artifact guards pass. same-SHA CI/delivery/deploy are Not Run: publication
was not requested. No new performance score is claimed; P23-008A's recorded
local uncompressed-delivery/LCP limitations remain applicable.

## Remaining Issues / 残り工程

Local requested changes: none. Public deployment remains a separate unrequested
operation. Framework1.2.1 release qualification/publication and actual1.3 feature
completion remain outside this website Task.

## Suggested Next Action

Review the updated cards and five-second player at http://localhost:4321/.
Retain this local result until a commit/publication operation is requested.
