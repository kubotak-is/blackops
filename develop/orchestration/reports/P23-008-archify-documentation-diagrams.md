# P23-008: Documentation Diagram and Website Redesign Report

Status: Locally accepted by Orchestrator, 2026-09-09; publication not requested

## Summary

TOP and the documentation now share a wider neutral light/dark design with the
BlackOps lime accent. The hero aligns its title, useful description and real PHP
code. Its actions are What's BlackOps and Install, with HTTP, Console and
Schedule entry links. Decorative microheadings and the redundant hero sentence
were removed; the local design Skill was updated permanently.

TOP contains one full-width Archify execution graph, landscape on desktop and
portrait on mobile, with the explanation BELOW at every width. Node clicks pause
playback and show a description and 詳しく見る link. The paused phase number stays
on the graph and panel; selection has its own highlight. The seven Inline and
nine Deferred phases synchronize nodes, moving traces and Journalイベント.
Arrowheads retain a fixed small size. Empty-event labels and the full-transcript
disclosure were removed. The concepts overview remains in its guide.

Native scroll reveals introduce the hero content and primary TOP sections.
Finite opacity/transform transitions and IntersectionObserver preserve keyboard
and no-JS visibility, respect reduced motion and clean up page lifecycle state.
The timed processing loop remains independent, with manual controls and
hidden-document/offscreen pause. No runtime dependency was added.

All ten logical diagrams and the desktop overview variant use actual Archify
creation/export. Noto Sans JP fixes the confirmed Chinese CJK fallback. Static
sublabel contrast and four three-row Failure nodes were repaired and reviewed.
The final small corrections remove a duplicate unmasked hero background and
update guards to current tokens and emitted CSS. Ubuntu font delivery is reduced from1,245,192 to405,832bytes (67.4%),
without changing the current typefaces, glyphs, weight ranges or metrics.

## Changed Files and Ownership

- Root: Packet/Report/spec109/spec110/D159, STATE/archive/index/TODO, scoped guide prose,
  maintenance README, canonical imports, external Skill update and acceptance.
- Site Luna: TOP, ExecutionWalkthrough component/player, theme.css and affected
  reader/product/site guards and tests.
- Docs Luna: shared tokens, documentation.css, Blume configuration, LandingMotion,
  registry/artifact/release guards and fixtures, and bounded font delivery.
- Documentation Reviewer: read-only Stable facts, reader journey and visual review.

Guide sources: README, Core Concepts, Execution, Execution Context, Operation
Lifecycle and Outbox. Asset scope: registered JSON/HTML/SVG, guide PNGs, manifest,
offline patch, fonts and provenance. Root owns shared build/browser outputs and
STATE. Preserve the unrelated P23-003D2S1 report change. No PHP source/test,
lockfile, public route or Release change. No commit/push/deploy.
Local preview: http://localhost:4321/.

## Decisions and Assumptions

React Bits was inspected at1440 and390px; evidence is under
/tmp/blackops-archify/retake/reference-reactbits. It supplies visual direction,
not product claims, source/assets or a React migration.

Archify revision2ead014aa8ec91f104cd052f1a6ca82de5e26c31, package2.17.0-dev.1
and its actual Skill/schema/showcase checks remain authoritative. The reviewed
patch preserves offline operation, MIT attribution, Japanese font embedding and
readable text. Delivered HTML/canonical SVG are not post-edited. Website playback
styles an isolated SVG and positions native buttons from registered JSON geometry.
JSON failure disables the geometry-dependent buttons.

HTTP binding and Strategy selection precede operation.received. Inline records
attempt.succeeded then operation.completed without an Outcome Record. Deferred
acceptance commits Value/Context and received/accepted Journal together; Worker
completion commits Outcome and succeeded/completed Journal together. HTTP202
arrival and Worker activity can overlap. The two illustrative examples reset
the Journal and make no live-log or measured-timing claim. Schedule does not
claim the identical HTTP input timeline; Console means Operation CLI. Previously
accepted unchanged Stable semantic review is reused.

## Commands and Results

Evidence root: /tmp/blackops-archify/retake. Node24.18.0/pnpm11.12.0.
Browser: Playwright1.61.1, Chromium1228 and axe4.12.1 in cached
mcr.microsoft.com/playwright:v1.61.1-noble with network disabled. Lighthouse13.4.1
uses an uncompressed local static server and simulated mobile conditions; this
is neither production field data nor an INP measurement.

Final source binding:20331774b830195997926c996b5087c6d974023eff3ac9339ba9cdb52436466e,
1184 inputs, HEAD70f9c4733bed8bb0960165cddf8c451b78b96184 plus this working tree.
Inputs stayed unchanged throughout the checks; gate/inputs.json records each hash.

| Command | Result | Seconds | Log |
| --- | --- | ---: | --- |
| `pnpm --dir docs/website run content:check` | PASS | 3.2 | gate/content-check.log |
| `pnpm --dir docs/website run blume:check` | PASS,0errors/0warnings/0hints | 8.595 | gate/blume-check.log |
| `pnpm --dir docs/website build` | PASS,41pages + artifact/site checks | 28.102 | gate/build.log |
| `node --test --test-isolation=none (five files below)` | 127/127 PASS | 266.273 | gate/affected-test.log |

The five files are archify-diagrams.test.mjs, content-pipeline.test.mjs,
reader-contract.test.mjs, release-claim-checker.test.mjs and reader-experience.test.mjs
under docs/website/tests. gate/affected-test.json preserves the exact literal
command. The prior successful guide-code/site-navigation tests are unchanged.
The 44 reader-experience cases are included in the127, not added to that count.

Earlier final builds exposed an omitted desktop artifact inventory, an obsolete
ambient-gradient assertion, minified @supports syntax and old focus tokens.
Each was repaired with a bounded guard/fixture correction. The final full build
above passes the entire pipeline; earlier failed builds are not pass evidence.

Authored source diff checks pass. The unified generator patch's blank context
lines, exact exported SVG whitespace (both overview variants) and verbatim Noto
OFL whitespace remain intentional exceptions. Canonical exports/licenses were
not rewritten to satisfy whitespace cosmetics.
Guarded STATE rotation passed before/after; the final summary is52lines/3239bytes.
The previous body was archived automatically. gate/closeout.json confirms the
verified source stayed unchanged and18 new authored text files have no trailing
whitespace. The local URL returned HTTP200 again at2026-09-09T07:44:40Z.


The prior full website run passed167/168 in195.243s at input
34b1eac8de236a26fa07ec95c3c4d296124639c3dce559345f8722b425b8ab90.
Its sole failure was an obsolete Core Concepts Mermaid mutation fixture, now
retargeted to the registered Archify runtime PNG without weakening the negative
guard. Do not call that old full run successful. Unchanged passing checks are
reused under spec109; PHP suites/PHPStan/Mago/Deptrac inputs are unchanged.

Worker focused checks: walkthrough2/2 in0.756s, reader experience44/44 in1.307s,
framing10/10 in10.871s after the minified @supports repair. Logs/commands/hashes
are in site-worker-final. The emitted focus-token assertion was then corrected;
site:check passed in15.844s (gate/site-check-final.log). Positive generated CSS
and negative unrelated-selector fixtures preserve the bounded ambient rule.

Browser evidence under final-browser:

| Scope | Result | Receipt |
| --- | --- | --- |
| Five guide routes,390/768/1440,light/dark |30/30; axe/overflow0 | guides-final/site-review.json |
| TOP,390/768/1440,light/dark |6/6; axe/overflow0 | top-final/site-review.json |
| All16 manual mappings and natural loop/reset | PASS,61.088s | player-review.json |
| Seven keyboard inspections, links, phase number and responsive state | PASS | player-review.json |
| Trace motion, markers, pause, themes, reduced/no-JS and browser back | PASS | player-review.json |
| Real scroll reveal, nested focus, reduced/no-JS, lifecycle and JSON failure | PASS,4.216s | scroll-review.json |
| Final component pixels and computed state | Four width/theme conditions | visual-final/receipt.json |

The hidden-document test uses the real handler with a controlled document.hidden
fixture, not an OS background-tab observation. The separate headful/Xvfb attempt
stalled and is not pass evidence. Component captures hide fixed-header descendants
only during capture, preserve layout space, provide sufficient viewport height
and wait two paint frames. Natural hero/surface images are unaltered. Earlier
obscured/transient fallback captures are superseded, not product pass evidence.
Article review closed both P3 findings. The reviewer also closed the final
hero boundary finding and Light1440 pixel verification on the final source.
No unresolved visual finding remains. Font delivery is byte-equivalent after
normalizing only format header fields, so unchanged guide/interaction evidence
is reused; final captures verify the affected hero and font presentation.

Lighthouse after font delivery: performance64/accessibility100/best-practices96/
SEO100, FCP4.6s/LCP8.9s/CLS0/TBT0. The same uncompressed local mobile simulation
previously measured LCP13.4s; total score remains64. Both reports are retained
(final-browser/lighthouse-summary.json and gate/before-font-delivery/).
The LCP2.5s Skill target is not met in this simulation. Remaining measured costs
include Blume shared CSS and uncached delivery; production compression/cache and
field INP have not been measured. The local server's favicon.ico404 accounts for
the console error. These are recorded limits, not a claim of production speed.
The frozen Task requires a Lighthouse measurement, not a production delivery or
new shared-CSS optimization project; local visual acceptance does not close them.

## Generator, Font and Skill Provenance

All ten diagrams plus the desktop layout passed actual9/9 validate/deliver with
zero errors/warnings. All eleven actual PNG exports are zoom invariant/offline;
71 Japanese SVG text nodes use custom Noto Sans JP. Both overview SVG exports
embed the exact font and pass zoom invariance. Persistent source/output hashes
and actual receipts are in docs/website/diagrams/manifest.json. The upstream
maintenance viewer's portrait first-screen limitation is separate from article
presentation; visualCheck:null remains explicit.

Noto Sans JP:298584bytes, SHA256
f7ed72f468ec54f8fb3025f460018685c9f00102179fc91e4cbec5f3f5eda5eb.
Pinned Google Fonts source, OFL license, subset generation and coverage are in
docs/website/fonts. Generator patch SHA256:
69dde0626b865a2db2ffddfb296da6fb2c6c058b473d7d689b0e69705bbebee3.
Patched template SHA256:
06dbd5bfa47885f5820d0699ee2c9cfa8cb0c25cf532f9284faa1c31ff4677ea.
Applying the patch to pristine upstream produced byte-identical output.

Ubuntu WOFF2 generation uses temporary FontTools4.64.0/Brotli1.2.0 with
transform_tables=[] and retains the TTF sources and original license. Reproduction
passed in3.113s; source/output hashes, metadata/glyph checks and all20 table-byte
comparisons per font are in font-reproduction/receipt.json and
font-woff2-verification.log. Only the recalculated head checksum and compression
marker bit11 differ from TTF table bytes; glyph outlines, metrics, names and
variation data remain identical. The first diagnostic comparison omitted that
format marker and was corrected after inspecting FontTools' implementation.

The worker's default Node test result showed one file wrapper only and is not
used as44-case proof. The final isolation-disabled127-case run supplies the
actual case evidence. spec109 now requires matching test names/counts and using
package-script isolation settings for direct scoped runs. This narrow permanent
workflow correction follows the user's earlier efficiency request.

Local Skill: /home/kubotak/.codex/skills/design-taste-frontend/SKILL.md.
Before:a7367eb85fce370a4fc7a9aa0a8cd08e2e0cc829a3f1f203d8720133f5c1656e.
After:6077a5549d4766e317f3296ba0779a74345d256f20f2073fbeb628ee5b875947.
/tmp/blackops-archify/skill-update/receipt.json records the explicit user authority
and skill-creator validation before/after. The update removes decorative labels
and preserves useful action/progress numbers. No memory edits.

## Acceptance Criteria

All frozen local criteria pass: actual Skill provenance, responsive readable
figures, Stable execution/Journal accuracy, node inspection and numbers, looping
and scroll/reduced/no-JS behavior, article/TOP visual review, build/artifact/Release
checks, relevant tests and recorded Lighthouse measurement. Root reviewed the
final worker diffs and all unresolved findings are closed. The local URL returned
HTTP200 after the final build. This is local acceptance; no publication claim.

## Release Documentation Impact

Experimental Stable1.2.0 / roadmap1.3 unreleased. Framework direct ref
00e8c5875047a3c47acbebfe57f75b0e581d18b9, peeled source
3332fd1dd0738fc7e79750facd93d49a59054ecf. Skeleton direct ref
fedcfda5f39caf320ad67196e8ced459176cedb1, peeled source
fa5e8247fc8cf789cf73685e5be59cc498ffb4ce. Capability IDs changed:none.
Documentation-site presentation changes; no capability/API/configuration behavior
change. Six public surfaces change content; shared layout affects all40 reader
pages. Preserve41 Search records,216 type/25 Attribute references, raw/Search/LLM
parity and exact nine-path/one-ApplicationBuilder-method Stable exclusions.

Old-version classifications/historical allowlist are unchanged; current and
historical sources/artifacts remain checked. Desktop JSON/HTML have Release
positive/negative fixtures. Remote same-SHA CI/documentation delivery: Not Run.
Production deploy:none. This local retake does not replace release/export gates.

## Remaining Issues and Suggested Next Action

Remaining required work for this local UI Task:none. Review the updated site at
http://localhost:4321/. Publication is a separate unrequested action. The local
mobile performance limitation above is retained for a future delivery/performance
Task and is not hidden by the passing functional checks.

Separate Framework work remains: D2S Status adapter, other reader
children, D3/D4/D5, parent D integration and post-Accepted-F control. D2S1 remains
Accepted at70f9c4733bed8bb0960165cddf8c451b78b96184; workflowP23-007 at4fbd647.
The rejected first pass remains under orchestration/history.
