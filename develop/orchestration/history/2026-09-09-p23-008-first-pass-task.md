# P23-008: Archify Documentation Diagrams

Status: Implementation preparation — user-requested diagrams; no deployment

## Goal

Use the user-selected https://github.com/tt-a1i/archify to explain BlackOps's
runtime architecture and Outbox data flow in the public documentation website.
Keep authored Japanese diagrams grounded in Experimental Stable1.2.0. Preserve
the four existing Mermaid diagrams; the new runtime map answers how execution
entrypoints reach the shared Operation, complementing the existing concept map.

## Authority and Boundary

Read AGENTS, STATE, this Task, spec109/92/104, Release Authority, the guide
sources below and the pinned Archify Skill. The user's explicit Archify request
authorizes this new renderer/image scope; spec91's old no-Diagram-Image rule
remains historical to its Mermaid sizing Task and does not prohibit these two
new diagrams. Existing Mermaid source/layout/a11y contracts remain unchanged.
No Public API, capability, stable version, public H1/slug, navigation or reader
route count change. No PHP source/tests/config/dependency changes, no push/deploy.

D2S1 is Accepted and clean Git/Composer export passed at
`70f9c4733bed8bb0960165cddf8c451b78b96184`. P23-007 workflow checkpoint is
`4fbd647`. This Task may record that delivery in its prior Report/STATE without
re-running unchanged PHP verification. The Status adapter and parent D remain
separate incomplete work.

Archify upstream is pinned to `2ead014aa8ec91f104cd052f1a6ca82de5e26c31`
(package2.17.0-dev.1). Use its actual validate/deliver CLI, not a visual imitation.
No global Skill install or new package-manager dependency is required.
For this run the extracted package is in
`/tmp/blackops-archify/tt-a1i-archify-2ead014/archify`.
Keep update checks disabled during generation. Pin provenance and reproducible
generation/export instructions in the website README and diagram manifest.

## Scope and Ownership

Root owns this Task/Report, STATE/archive, spec Index/TODO links, the D2S1
completion receipt, guide prose and mechanical import/export of frozen diagram
artifacts. Luna owns website implementation in the paths below. A separate
Luna draft worker owns only `/tmp/blackops-archify/candidates/`; it cannot edit
the website or shared state. Root owns final serial website/browser checks.
Documentation Reviewer is read-only. No worker stages or commits.

```text
docs/guide/core-concepts.md
docs/guide/outbox.md
docs/guide/assets/diagrams/runtime.png
docs/guide/assets/diagrams/outbox.png
docs/website/public/diagrams/runtime.html
docs/website/public/diagrams/runtime.json
docs/website/public/diagrams/outbox.html
docs/website/public/diagrams/outbox.json
docs/website/diagrams/manifest.json
docs/website/diagrams/offline-fonts.patch
docs/website/scripts/archify-diagrams.mjs
docs/website/scripts/archify-visual-check.mjs
docs/website/scripts/validate-content.mjs
docs/website/scripts/content-pipeline.mjs
docs/website/scripts/check-artifact.mjs
docs/website/scripts/release-claim-checker.mjs
docs/website/scripts/reader-contract.mjs
docs/website/tests/archify-diagrams.test.mjs
docs/website/tests/content-pipeline.test.mjs
docs/website/tests/reader-contract.test.mjs
docs/website/tests/reader-experience.test.mjs
docs/website/tests/release-claim-checker.test.mjs
docs/website/package.json
docs/website/README.md
docs/website/theme.css
```

The Root may stage only these reviewed new assets before the website gate,
because the existing pipeline requires Git-index tracking. Staging is not
Acceptance. Root may make a reviewed local documentation commit after checks;
same-SHA remote CI/delivery is Not Run unless separately authorized/executed.

## Implementation Contract

- Runtime architecture: HTTP/CLI entry, Inline or durable Deferred execution,
  Worker and the common typed Operation, Outcome and Lifecycle Journal. Show
  only relationships supported by current guide/Stable source evidence.
- Outbox: business mutation and child registration share a named DB transaction;
  Commit precedes relay and child execution. Distinguish Outbox from durable
  transport and Worker. Preserve duplicate-tolerance/at-least-once, no external
  broker or exactly-once guarantee. Do not add unreleased Audit Trail.
- Keep Japanese labels and equivalent prose/alt text. Use Archify's default
  static/classic viewer; omit unsupported Japanese locale and disclose English
  fixed UI beside the full-view link. No animation is enabled by default.
- Deliver exactly two typed JSON sources and validated standalone HTML files.
  Inline PNGs are canonical full-diagram exports of those exact HTML files,
  without viewer controls, focus overlays or cropped content. Capture and
  retain artifact hashes, source hashes and Archify9/9 showcase receipts.
- Use one explicit diagram manifest for owner guide, exact local/public paths,
  upstream revision, content hashes and receipt provenance. Reuse existing PNG
  handling. Authorize only registered `/diagrams/*.html` and `.json` links,
  with existing-file/path/tracking checks; never exempt a wildcard directory.
- Treat viewer HTML/JSON as supplemental public artifacts, not reader routes.
  Reject unknown flat viewer HTML, missing assets, unsafe/escaping paths, orphan
  registrations and mismatched source/output hashes. Preserve40 reader routes,
  Search/raw/LLM parity and their existing guards. Do not rely on the current
  index.html-only route inventory gap.
- Only registered viewers may skip BlackOps page-specific layout/focus guards;
  keep common release/forbidden-content checks and their own browser/a11y gate.
- Extend release source checks to registered public diagram JSON/HTML text.
  Keep artifact release checks and negative current1.3/stale1.1 fixtures.
- Ordinary check/build verifies checked-in outputs without network or renderer
  installation. Document a pinned regeneration path; avoid new runtime CDN,
  framework dependency, global settings or broad pipeline exemptions.
- The pinned upstream template hardcodes Google Fonts and exposes no offline
  option. A minimal, checked-in `offline-fonts.patch` may remove only that
  template's remote-font loading block before generation and retain the exact
  upstream MIT notice in a non-rendered HTML comment. The actual screenshot
  review also permits replacing only the dataflow renderer's stage-heading
  `t-dim` class with `t-muted`; the original headings have about2.5:1 contrast.
  A template-only selector did not survive canonical PNG export, so preserve
  the correction in the generated SVG class. No other colors/geometry/runtime
  logic change. Record both template and renderer before/after hashes and the
  single patch hash. Keep its before/after
  template and patch hashes in provenance, apply it to the temporary upstream
  checkout, and run the actual validate/deliver CLI against that exact template.
  This is a documented local template patch; do not describe it as unmodified
  upstream output or edit the delivered HTML. Keep all remote-font guards.
- The upstream visual-check reuses one Chrome process; in the verified Docker
  environment its fourth viewport can time out. A small optional wrapper may
  call the exported, unmodified `runVisualCheck` with one `ChromeVisualBrowser`
  per observation. Keep its full measurement/capture/assertion logic unchanged,
  record the runner in provenance, and use it only for regeneration/browser
  evidence, never as a network/runtime dependency of ordinary check/build.
- Guide images and viewer links must work with keyboard, both themes and mobile.
  Include meaningful Japanese accessible descriptions and a clear way back to
  the owning guide without altering the trusted generated HTML after delivery.
  Keep inline PNG text readable at its authored width inside a named, keyboard-
  scrollable figure region; do not shrink it to unreadable labels to fit mobile.
  Limit theme changes to that new figure class. Same-tab viewer navigation keeps
  the browser's Back action as the path to the owning guide.

## Verification Plan

### Discovered Stable Reference Boundary

The first full content check found a pre-existing mismatch: current source has
218 PublicApi types while the Stable Reference correctly documents216. Root
identified two unreleased Audit types, six unreleased CLI commands and the
Audit configuration source. All nine exact paths are absent from the authority's
Stable framework commit (`/tmp/blackops-archify/stable-reference-boundary.json`).
An exhaustive diff of PublicApi sources against Stable also found one added
method: `src/Application/ApplicationBuilder.php::withOperationalHealthQuery`.
Its existing public signatures are unchanged. The only other changed PublicApi
files are the two new Audit types (`stable-public-api-drift.json` evidence).
This Packet permits a bounded fix in the already-owned reader contract/tests
and website README: exclude only those nine exact source paths and this one
exact file/type/method combination while the
Stable1.2.0 framework tuple matches. Do not exclude namespaces/directories,
derive exclusions from missing documentation, increase216/25, or publish the
unreleased API/commands/configuration. Unknown source additions remain checked;
a changed release tuple must require reevaluating these exclusions. Test exact
matching, changed authority, unknown paths and similarly named methods.
Ordinary checks must remain
offline and work in CI's shallow checkout. No PHP source changes are permitted.
The existing reader-experience tests also need their source inventory bound to
the same exact Stable boundary, and their package-script expectation updated
to include the new Archify checker. Keep assertions on local Mermaid and page
layout. Re-run those affected tests only after the completed full run; unchanged
passing tests retain their evidence.

### Commands

The first build revealed that the existing strict Blume validation wrapper
temporarily stages only the Board PNG. Extend that existing staging/cleanup to
the exact manifest-registered diagram PNGs as well. Keep strict validation,
source assets canonical and public staging temporary; do not add permanent
duplicate assets or disable broken-asset checks. Verify cleanup after validation.

Change category is public documentation plus website tooling. PHP full suites,
Mago and Deptrac are unaffected and not part of this Task's new gate. Root
audits worker results rather than repeating same-input successful commands.

1. Worker runs syntax and focused meaningful failure/positive tests for the
   manifest, tracked assets, registered/unknown links, source/artifact parity,
   unknown flat HTML and diagram release claims. No broad website build yet.
2. Each diagram runs pinned Archify validate/deliver with showcase9/9, zero
   errors/warnings. Frozen candidates are imported without post-delivery edits.
3. Root runs one website gate from Repository root using `pnpm --dir docs/website`:
   `content:check`, `test`, `blume:check`, `build`. The content check includes
   source Release checks and generation; build includes diagram checks, Blume
   validation, artifact Release/layout checks and site checks. Reuse those
   results instead of repeating the containing commands. This explicitly closes
   General's editorial P3 about duplicated verification commands.
4. Root runs Archify `visual-check` on exact HTML in a browser-capable context,
   inspects its screenshots, and tests the two website routes at1440 Light/Dark
   and390 mobile. Check complete diagram readability, navigation, focus/zoom,
   page containment, a11y, runtime errors, image/HTML/JSON availability and
   equivalent guide/Search/raw/LLM meaning. Capture hashes/viewports/results.
5. Documentation Reviewer reviews facts, reader outcome and actual browser
   evidence. Root closes editorial P3 without reopening unrelated checks.
6. `git diff --check`, STATE check, scoped diff and final input preservation.
   Explicitly record local-only delivery, remote CI and Production deploy status.

## Acceptance Criteria

Both diagrams use real Archify outputs, explain the documented Stable flows,
appear in their guide pages and open as usable standalone views. The build
accepts only the registered artifacts, preserves existing routes/Mermaid and
release boundaries, and has positive/negative evidence. Frozen source/artifact
receipts, browser measurements and visual review are recorded with limitations.

## Release Documentation Impact

Authority: Experimental Stable1.2.0, Framework peeled source
`3332fd1dd0738fc7e79750facd93d49a59054ecf`, Skeleton
`fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`; roadmap1.3 unreleased.
Existing framework-core and Outbox explanations only; no new capability ID.
Owners: `/concepts/core-concepts/`, `/execution/outbox/`; supplemental assets
are manifest-controlled. Record old-version before/after classification and
Source/Search/LLM checks in Report. No release closeout or Production deployment.

## Next Action

Review the diagram facts and bounded integration contract, dispatch Luna for
the website implementation, then verify the final artifacts and reader paths.
