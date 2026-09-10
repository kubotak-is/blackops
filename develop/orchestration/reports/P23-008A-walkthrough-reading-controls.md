# P23-008A Report: Landing Reader Flow and Motion

Status: Locally accepted by Orchestrator, 2026-09-09; publication not requested

## Summary

TOP now starts with Inline and uses consistent Inline/Deferred tabs above one
shared graph. Each mode has its own explanation and automatic loop. The previous
2.8-second cadence becomes a text-dependent10–16-second allowance; current phases
receive12.52–15.295 seconds. Pointer hover or keyboard focus in the explanation
holds progression. Manual pause and reading holds preserve moving arrow traces.

Every phase has a 詳しく見る link, and Journal events are linked tags. New events
use an accent state; accumulated events keep their tag shape. Journal now has a
short definition instead of a redundant per-stage event sentence. Removed the
graph-header, phase/control and Journal timeline rules, the duplicate example
notes and the unnecessary timing disclaimer. The sole remaining note preserves
the Deferred acceptance/HTTP202/Worker overlap. Intro copy is right-aligned;
mode descriptions use available width and natural wrapping. Mobile tabs fill
their rail equally and have44px minimum targets.

The lower page now has one concrete tutorial journey: prepare the environment,
run the sample, create an Operation. Removed the repeated PHP-authoring section
and vague category navigation. The background uses broad feathered lime light,
a subtle dot field and slow native CSS motion. The BlackOps title briefly lights
up character by character, with irregular timing, then stays fully readable.
No new runtime dependency, remote font, raster, canvas or WebGL loop was added.

## Changed Files and Ownership

Website paths below are relative to docs/website/.

- Site Luna: components/ExecutionWalkthrough.astro, scripts/execution-walkthrough.mjs,
  theme.css, tests/execution-walkthrough.test.mjs.
- Docs Luna: pages/index.astro, new components/LandingBackdrop.astro,
  components/LandingMotion.astro, scripts/landing-motion.mjs,
  scripts/artifact-stylesheet-contract.mjs, scripts/check-site.mjs,
  scripts/product-framing-contract.mjs, tests/product-framing-contract.test.mjs,
  tests/reader-experience.test.mjs.
- Root: Packet/Report, spec110/D159, TODO/STATE/archive, source/visual review,
  shared dist build, browser evidence and Acceptance.
- Documentation Reviewer: read-only definitions, link destinations and reader flow.

No PHP, dependency/lockfile, guide-body, public-route or canonical Archify asset
change belongs to this follow-up. The unrelated P23-003D2S1 report was preserved.
No stage, commit, push or publication. Preview: http://localhost:4321/.

## Decisions and Assumptions

Progression, arrow traces and decorative motion have separate gates. The dwell
is an editorial allowance, not a runtime measurement or a measured reading speed.
Only explicit tab selection changes modes; each loop resets its Journal.
Touch entry does not leave a persistent hover hold. Existing node inspection,
phase numbers and guide navigation remain.

All five wire events link to the existing Lifecycle Event table, which has no
individual event anchors. All16 phase links use relevant existing sections.
Inline completion does not link to Deferred's Outcome Store. The reviewer
approved both the16 destinations/tutorial journey and the final Journal
definition/sole overlap note, with no remaining P2/P3 finding.

React Bits (https://reactbits.dev) supplied visual direction. Root opened the
actual site (HTTP200) and inspected reference/reactbits-top.png. No source/assets
were copied. Archify provenance/topology and guide evidence remain the accepted
parent's inputs; this task changes playback, presentation and native decoration.

## Release Documentation Impact

Presentation/navigation-only impact on documentation-site; no Capability
definition, API, Command, Configuration or release claim changed. Authority stays
Experimental Stable1.2.0, with roadmap1.3.0 unreleased:

- Framework direct00e8c5875047a3c47acbebfe57f75b0e581d18b9;
  peeled3332fd1dd0738fc7e79750facd93d49a59054ecf.
- Skeleton directfedcfda5f39caf320ad67196e8ced459176cedb1;
  peeledfa5e8247fc8cf789cf73685e5be59cc498ffb4ce.

Changed public source: TOP and its assets. Existing linked routes cover
installation, quickstart, first-operation, validation, HTTP/Deferred, Journal and
Core Concepts. Guide bodies/routes are unchanged. Old-version occurrences and
the historical allowlist have no before/after changes. The full build validates
source, release claims, Search/LLM artifacts and all41 generated pages. Positive
current-surface/CSS and negative stale/roadmap/unrelated-selector fixtures pass.
No release closeout, same-SHA CI/delivery or deployment is claimed.

## Commands and Results

Evidence root: /tmp/blackops-archify/p23-008a/. Node24.18.0/pnpm11.12.0.
Node test isolation is explicitly none; no NODE_OPTIONS/NODE_TEST_CONTEXT override.
Root browser checks use cached mcr.microsoft.com/playwright:v1.61.1-noble,
Playwright1.61.1/Chromium1228/axe4.12.1, network disabled, UID1000 and a read-only
repository mount. Only Root writes dist and STATE.

Final source:a69f18ecbb2a69d5a0ee1169d327f016f25dd76b579e7364ce382632a187a938,
1185 inputs, HEAD70f9c4733bed8bb0960165cddf8c451b78b96184 plus the working tree.
gate-complete/inputs.json records each hash. The final build's inputs stayed
unchanged. The last changes are only the hero-note color and mobile-tab sizing
in theme.css; other passing input/behavior evidence below is explicitly reused.

| Command / assigned scope | Owner and result | Seconds | Receipt/log |
| --- | --- | ---: | --- |
| node --check scripts/execution-walkthrough.mjs and its test, with docs/website/ prefix | Site Luna, exit0 | 0.049 combined | site-worker-final.txt |
| node --test --test-isolation=none docs/website/tests/execution-walkthrough.test.mjs | Site Luna,4/4 PASS | 0.999 wall | execution-walkthrough-final.log |
| Two title cases, literal command below | Docs Luna,2/2 PASS | 0.821 wall | landing-title-final-receipt.txt |
| node --check docs/website/scripts/landing-motion.mjs | Docs Luna, exit0 | 0.028 | landing-title-final-receipt.txt |
| Presentation cases, literal command below | Root,9/9 PASS | 2.456 | gate-contrast/presentation-test.json/.log |
| pnpm --dir docs/website run blume:check | Root,0errors/warnings/hints | 8.199 | gate-delivery/blume-check.json/.log |
| pnpm --dir docs/website build | Root,PASS41pages + artifact/site guards | 29.370 | gate-complete/build.json/.log |
| reading-controls-review.cjs | Root,22 checks PASS | 51.840 | browser/review.json |
| final-ui-review.cjs | Root,13 checks PASS | 9.350 | final-ui/review.json |
| background-review.cjs, final background | Root,10 checks PASS | 12.270 | background-final/review.json |
| neon-review.cjs | Root,8 checks PASS | 5.990 | neon/review.json |
| final-pixels.cjs, final source | Root,4 theme/width captures, equal44px mobile tabs and contrast PASS | not separately timed | pixels-complete/ |

Literal title command:

    node --test --test-isolation=none --test-name-pattern='landing H1 keeps one literal word boundary and fails closed on drift|landing title motion preserves the literal name and settles on offscreen cleanup' docs/website/tests/reader-experience.test.mjs

Literal presentation command:

    node --test --test-isolation=none --test-name-pattern='Blume runtime keeps diagrams local and the landing responsive|the detail layout owns the existing theme and linked CSS guards fail closed|custom landing links have a permanent static-artifact guard|product framing source and artifact contracts accept|product framing source contract rejects|product framing artifact contract rejects' docs/website/tests/reader-experience.test.mjs docs/website/tests/product-framing-contract.test.mjs

The pattern matched six framing and three reader cases:9 actual passes, not an
assumed6. Together with the four player and two title cases,15 distinct final
cases pass. Do not add earlier overlapping iteration counts to this total.
Worker receipts contain hashes, commands, dates and exits. The final player run
started21:53:26+09:00 and title run21:57:19+09:00. Some earlier worker start times
were not recorded; raw logs and recorded finish times are retained without
inventing starts. Root gate/browser JSON records exact starts and elapsed times.

Literal browser invocation, using each assigned harness named in the table:

    docker run --rm --network none --user 1000:1000 -v /tmp/blackops-archify:/tmp/blackops-archify -v /home/kubotak/projects/blackops:/home/kubotak/projects/blackops:ro -w /home/kubotak/projects/blackops mcr.microsoft.com/playwright:v1.61.1-noble node /tmp/blackops-archify/browser/final-ui-review.cjs

The background invocation additionally sets
BLACKOPS_BACKGROUND_EVIDENCE_DIR=/tmp/blackops-archify/p23-008a/background-final
with docker -e. Root's timed wrappers preserve literal inner commands in JSON.

Root compared all16 phase maps with the accepted parent (phase-semantics.json):
IDs, labels/descriptions, nodes/edges and cumulative/new Journal lists match.
Real browser checks resolve all10 distinct detail destinations/fragments with
HTTP200, perform native phase/event navigation, hold paused phase/Journal beyond
16.2seconds while dashoffsets move, and observe a13.104-second Inline loop/reset.
Controlled timers cover both full loops. Latest tests/captures confirm initial
Inline in SSR/client/no-JS, keyboard tabs,44px linked tags, no duplicated rules,
reading holds, responsive replacement, touch, reduced motion and cleanup.

TOP axe findings/overflow are0 in both themes at390 and1440. Final natural hero
and unobscured tall-viewport component captures were inspected. Neon snapshots
show different letters glowing, then all letters at opacity1 with no layout
shift or recurring loop. Hidden/offscreen/reduced/cleanup settle the title without
replay. Background transform/opacity changes, visibility suspension and static
no-JS behavior pass. No new network asset class or rendering dependency appears.

## Findings and Verification Reuse

- First type attempt: TS7053 at firstStep.mode,9.237s; an erased step annotation
  fixed it. The failure/old input remain in gate-type-attempt-1.
- Early framing/reader failures were stale section/route fixtures. Corrections
  retain negative coverage and the actual relevant case counts. The gradient
  allowlist accepts only named decorative selectors, including emitted nested CSS.
- The first behavior-passing background had hard clipped edges. Root rejected
  those pixels, requested feathered tails and stronger light, and checked drafts
  before the next build. Initial background behavior was6 checks/6.490s.
- Neon source review fixed the opacity endpoint and preference-change settlement.
  A type pass then reported three deprecated legacy-listener hints; the unused
  compatibility branch was removed. The final type pass has no hints.
- Final pixel sampling found low hero-note contrast under maximum decorative
  light despite axe reporting no violations. Using the normal ink color improves
  the measured minimum from2.289 to5.022:1 dark and3.485 to9.632:1 light.
  pixels-complete/contrast.json records the sampled colors and locations.
- Final mobile review found empty space in the full-width tab rail. Equal flex
  widths and44px targets close that finding; final source/captures prove it.
- Earlier component screenshots obscured by the fixed header are superseded by
  tall-viewport pixels-complete captures. The first contrast probe sampled before
  smooth scrolling settled and is invalid; its corrected instant-scroll probe
  identifies the actual text background. No source change was made for that
  harness correction.

Hidden-document checks use a controlled document.hidden fixture with the real
handler, not an OS background-tab observation. Decorative offscreen tests add a
temporary trailing spacer. Contrast sampling hides only the measured text while
preserving layout and samples the backdrop at maximum layer intensity. Draft
CSS-injection captures are iteration evidence, not final delivery proof.

The first passing source wasc305432c6e60c623deb1bb1b80a901c9951d99c98e9a4e433632f9e82d866abd.
Latest content/behavior gates used4c9aa70c1fb5c9b6faf713c41b36d173fab15aa859eee96733bfd6d75ea1f9ac.
Only the specified CSS refinements separate those latest behavior results from
the final source; no stale phase initialization or old Journal markup is reused.
Canonical/all-guide evidence remains in the accepted P23-008 Report.

Recorded validation command time totals259.128s, including failed/changed-input
iterations and earlier scoped Node logs. Untimed syntax/pixel captures, review,
implementation, approval and agent waits are excluded. Four website builds ran:
first implementation, new user-directed UI/title, contrast repair, mobile-tab
repair. No full PHP/PHPStan/website suite or Lighthouse rerun was performed.
Narrow tests/source review closed findings; unchanged long dwell/neon/guide
checks were not repeated for color or sizing changes. Specialist review had two
bounded passes; Root closed the specific findings above without restarting the
whole documentation audit. Future CSS iterations should inspect all changed
mobile controls and actual gradient-backed text before freezing the build.

## Acceptance Criteria

All ten Task criteria pass: independent paused traces; readable dwell and
same-mode loops; reading holds; native phase/event links; unchanged semantics;
initial Inline and accessible coherent tabs; concrete tutorial journey; native
background with readable themes/lifecycle; simpler panels/tags/prose; finite
accessible neon arrival. No open finding remains within this Task.

## Remaining Issues

None for this local follow-up. Parent P23-008's uncompressed local mobile
LCP8.9s/shared CSS delivery and favicon404 remain recorded performance limits;
no new Lighthouse score or production performance is claimed. Separate Framework
work and any future publication remain outside this Task. The preview stays up;
HTTP200 was verified after the final build. STATE is rotated with its hash guard.

## Suggested Next Action

Reload http://localhost:4321/ to view the final local design and title arrival.
Publication remains unrequested and would require its own authorized delivery
checks. No further implementation or validation remains for this local Task.
