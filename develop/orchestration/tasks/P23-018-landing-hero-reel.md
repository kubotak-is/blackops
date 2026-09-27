# P23-018: Landing Hero Reel

Status: Accepted (local) — isolated commit and production delivery pending

## Goal and authority

Review and deliver the owner's 2026-09-27 Landing Hero Reel handoff as a separate
documentation commit and same-SHA documentation delivery. Preserve the ongoing
D5P candidate and all earlier documentation edits, including the pre-Reel portion
of theme.css. Root owns integration, Task/Report/Decision/STATE and Acceptance.
Production changes go to the configured Luna/max worker; the Documentation
Reviewer is read-only under spec92. Verification selection follows spec109.
The controlling workspace supplies the current profiles and spec109. The isolated
published-main base does not contain those newer orchestration tools; use Root's
existing state tool with its explicit root API rather than importing unrelated
workflow changes into this documentation delivery.

The owner explicitly authorizes a large video hero, replacement of the previous
hero, What's BlackOps and Install links, autoplay without looping, BlackOps CLI
content, and autoplay of BOTH Reel and Execution Walkthrough even under reduced
motion. Do not reverse that choice or infer a blanket accessibility exemption.

Read specs59/85/86/92/96/109/110, D118/D119/D159 and D167, website README,
release-authority.json and the affected guides. Current Stable is Experimental
1.2.1;1.3.0 remains unreleased. Check the immutable Stable implementation for each
CLI/API claim rather than treating arbitrary current source as Stable evidence.

## Scope and ownership

Inherited Product scope:

- docs/website/components/LandingReel.astro
- docs/website/pages/index.astro
- docs/website/theme.css, only the appended `/* Reel hero:` block
- docs/website/components/ExecutionWalkthrough.astro
- docs/website/scripts/execution-walkthrough.mjs
- docs/website/tests/execution-walkthrough.test.mjs
- docs/website/public/assets/reel/blackops-reel.mp4
- docs/website/public/assets/reel/blackops-reel.webm
- docs/website/public/assets/reel/blackops-reel-poster.jpg
- docs/website/reel/{reel.html,capture.mjs,render.sh,README.md,.gitignore}

Bounded corrective scope, only for confirmed review findings or render portability:
reel-related tests under docs/website/tests/, a Reel player module under scripts/,
docs/website/package.json and pnpm-lock.yaml, and a Reel-specific website README
paragraph. Preserve unrelated existing README/package/source bytes. Do not edit
PHP, Core APIs, Release Authority, guide prose, existing diagrams/fonts or D5P.

Root management scope: this Task/Report, D167, narrowly related clauses in
specs59/85/86/96/110, spec index/TODO pointers and STATE/archive. Do not rewrite
historical D118; record supersession precisely in D167 and current specs.

Handoff bytes, original index/worktree patches and the exact theme prefix are in
`/tmp/blackops-reel-20260927/`. The 16 inherited source/asset paths are pinned in
handoff-baseline.json. fonts/, stills/ and frames/ are not committed. D5P's
independent full continues under Root's serial PHP/DB ownership; Reel commands
must not alter those inputs or share its generated outputs. Do not advance HEAD
until D5P's frozen-input checks are complete. Use an isolated documentation
candidate for commit/delivery if the main worktree has unrelated changes.

## Decisions to close

1. Keep What's BlackOps/Install and the Reel in the first desktop viewport; allow
   the complete copyable Stable install command and Operation code in the next
   source section. Amend spec86's former command-in-first-viewport requirement.
2. Scope the reduced-motion exception to these two explanatory players. Retain
   reduced transitions/other motion defaults, keyboard controls, no-JS readable
   content, visibility/offscreen suspension and explicit pause persistence.
   Verify that a reader can actually stop all movement, including arrow traces;
   a present button alone is not WCAG evidence. Do not claim full WCAG conformance.
3. Preserve the framework-name hierarchy, reader links and delivery boundaries
   from spec85/D118; the new video presentation supersedes their layout clauses.
4. Provide a repository-owned pinned Playwright dependency and documented ffmpeg
   prerequisites/version/codec checks, without relying on another checkout's
   node_modules or committing downloaded fonts. Do not require rendering on each
   normal site build. Record actual reproducibility limits, not size equality as
   proof of byte-identical output.

## Acceptance

- [x] Exact owner requirements and release-safe, guide-supported visible claims;
  illustrative IDs/Journal and inspect annotation cannot be mistaken for captured
  real output. No version omission is used to conceal a release mismatch.
- [x] Hero/CTA/command placement and reduced-motion exceptions are consistent in
  current specs and D167; historical decisions remain identifiable as historical.
- [x] Autoplay, no-loop/end/replay, seek/guide links, pause persistence, offscreen/
  hidden/cleanup, autoplay rejection, no-JS and media failure behavior work.
  Keyboard, accessible names, focus and pointer controls remain usable.
- [x] Browser evidence for 1440x900/1920x1080 and390px, Light/Dark, reduced motion,
  no page overflow, no JS error, real media duration/end state and pause behavior.
  Record the required Lighthouse run and control contrast/focus checks.
- [x] Render source and committed assets match the reviewed content; tool/font
  identities and dimensions/duration/codecs are recorded. Each asset is below
  Cloudflare Pages' current per-file limit and browser transfer is measured.
- [x] Focused tests during correction; final website test/check/build and release
  Source/Artifact checks once on stable inputs. HTML/Search/raw/LLM, public route
  inventory and positive/negative boundary fixtures are explicitly accounted for.
  Inherited Claude results are attributed and not silently adopted without hashes.
- [x] Independent Documentation Reviewer Green, including actual browser evidence.
- [ ] Isolated commit contains only this Task's approved deltas and metadata;
  preexisting theme prefix, other docs, staged content and D5P remain preserved.
- [ ] Same-SHA CI and Documentation delivery succeed; external production HTML,
  Reel assets and browser behavior are verified against the delivered artifact.

## Verification and completion

Root assigns exclusive output/cache/browser ownership before execution and records
commands, input hashes, environment, result, elapsed time and log paths in Report.
PHP gates are not Reel validation. Inspect the clean documentation commit on its
actual delivery base before publishing; dirty-worktree build success is insufficient.
User has authorized the isolated commit and delivery. No unrelated framework
release, tag or D5P commit is selected.

残り工程: Isolated commit, same-SHA CI, production delivery and live verification.
Next Action: Root commits the frozen reviewed candidate and opens its delivery PR.
