# P23-001S Report — Stable list documentation truth correction

Status: Round 26 Correction Implemented / Review Requested — child management
sync; Round 21 Accepted is a Historical prerequisite

Started At: 2026-08-19T15:16:07+09:00

Completed At: 2026-08-20T00:41:09+09:00
Accepted At: 2026-08-20T02:21:22+09:00
Round 21 Completed At: 2026-08-20T00:41:09+09:00
Round 21 Accepted At: 2026-08-20T02:21:22+09:00
Round 26 Completed At: 2026-08-20T18:39:33+09:00

Historical Accepted At: 2026-08-19T18:01:23+09:00

Round 14 Checkpoint At: 2026-08-19T19:56:30+09:00

Base: `main` @ `5ad87557c08e20c3bcc6d77be83804001ba3eb80`

Branch: `agent/p23-001-blackops-1-3-feasibility`

## Round 26 management synchronization report

### Summary

The child management surface is synchronized to the parent Round26 correction
in the ten permitted management/specification files. Current status is
`Round 26 Correction Implemented / Review Requested`; P23-001 parent
acceptance remains pending. Round21 Accepted is retained as a Historical child
prerequisite, not a new acceptance. Stable `1.2` remains current and Phase
23/`1.3` remains an unreleased proposal.

This child report records the parent current authority for fixed-head
AuditQuery/cursor continuation, Local Audit Outbox state/nullability and
protected-body/AAD transitions, AnchorIntent prepared/submitted/accepted/
rejected and accepted-only triplet/CAS recovery, and exact
AuditRetentionState/AuditHold wire and lifecycle rules. It does not implement,
re-review, or accept P23-003 work.

### Changed Files

The current worker changed only the ten permitted management/specification
files, including this child Task/Report. Existing shared `docs/guide/**`,
`docs/website/**`, and `tests/**` changes belong to the parallel worker and
were not edited. No Production Code, dependency, generated artifact, commit,
stage, push, CI, deploy, publication, tag, or release action occurred.

### Decisions and Assumptions

The `blackops-japanese-docs` skill and required style checklist were read in
full before editing. Round21 and earlier child status, gate, Acceptance
Criteria, Remaining, and Next Action blocks are historical where retained.
Stable documentation truth remains separate from the unreleased 1.3 proposal,
and the child does not claim parent acceptance.

### Commands and Results

- Read-only `rg`, `sed`, `nl`, and `git status` inspections — PASS; the child
  Task/Report, parent current status, and Historical headings were checked.
- Pure read-only current-wire/scope inspection — PASS; the child handoff names
  all four current authority roots and preserves the ten-file boundary.
- `git diff --check --` over the ten permitted files — PASS; no whitespace
  errors.
- Build, format, dependency, Website, PHP, CI, and external commands — NOT RUN
  by this worker because the assignment forbids them; no new build PASS is
  claimed. Round21 gate evidence remains historical Orchestrator evidence.

### Acceptance Criteria

- [x] Current child status is Round26 Review Requested and Round21 acceptance
  is explicitly historical.
- [x] Parent current Query/Outbox/AnchorIntent/Retention/Hold handoff is
  recorded without reviving old current-looking blocks.
- [x] Stable `1.2`, unreleased `1.3`, and no-external-action boundaries remain.
- [ ] Parent complete Local Gate rerun, fresh General/Audit reviews, and parent
  acceptance.

### Remaining Issues

The parent Orchestrator must rerun the complete Local Gate against the combined
shared worktree, route fresh independent General and Audit reviews, and record
parent acceptance only after both reviews. P23-001R and ordered Phase 23 work
remain after acceptance. No release or implementation completion is claimed.

### Suggested Next Action

Orchestrator verifies the child and parent synchronization with the parallel
artifact patch, reruns the complete Local Gate, and routes the fresh parent
reviews. No commit, stage, push, CI dispatch, deploy, publication, tag, or
release is authorized.

## Historical Round 21 and prior summary

Corrected the confirmed Stable `1.2` documentation overclaim at
`docs/guide/project-cli.md:10` to the observed Symfony-backed behavior. The
Guide now explains Global `list` for first-time readers in five short
paragraphs: its purpose, explicit Application command construction during
Console Kernel composition, default/raw Lazy behavior, non-short structured
JSON/XML/Markdown resolution of argument/Option definitions (`Definition`) and
usage/help text (`Help`), and the diagnostic-use limit. It defines `factory` as
the process that creates a Command instance, `Definition` as the definition of
Command arguments and Options, and `Container`
as the boundary that resolves a Command from built dependencies. The Japanese
wording uses ordinary terms such as `通常表示` and `診断Command`, while retaining
the exact command and format identifiers. It explicitly says that Global `list`
is not a diagnostic command fully independent of Application initialization or
Container resolution.

Added fresh-kernel PHP fixtures for the explicit and discovered registration
paths. Explicit construction is counted once during each fresh Console Kernel
composition for default/raw/JSON/XML/Markdown. A valid discovered manifest
keeps its Lazy factory unresolved for default/raw and resolves it once for
non-short JSON/XML/Markdown Definition/help lookup. Every case asserts exit
`0`, a valid manifest setup before execution, and the format-specific wire:
normal-list header/row, raw row, decoded JSON target/description/definition,
XML declaration/root/target id, and Markdown index/heading/description. The
discovered default row also asserts its observed `fixture:hello` alias.

Added a fail-closed Website Source/Artifact guard. It classifies the exact
primary `cli-html|cli-raw|cli-search|cli-llm` keys plus the observed
`page:/reference/project-cli` and `raw:reference/project-cli.md` aliases;
unknown CLI surface keys fail closed while primary presence remains required.
It normalizes visible text
through Markdown and HTML tag/entity differences, safely decodes valid decimal
and hexadecimal numeric character references to Unicode scalars, and rejects
NUL, surrogate, out-of-range, malformed, or ambiguous numeric references. It
requires the boundary in CLI HTML/raw/Search/LLM-like surfaces and their
observed aliases, rejects the old
absolute claim and missing or one-sided surfaces, and tests decimal/hex
equivalence, named entities, inline/code tags, Source-side negatives,
individual Artifact-surface mutations, marker reorder, paragraph/block split,
missing Artifact surfaces, transformed-HTML fixtures, and HTML comments or
script/style/template visibility boundaries. Non-visible required markers are
rejected; a claim confined to non-visible content does not contaminate visible
contract text.

This bounded correction addresses the fresh Sol xHigh P2 finding and preserves
the prior bounded behavior matrix. It changes no runtime behavior, does not
generalize the verified matrix to `--short`, and keeps Stable `1.2.0` current
with BlackOps `1.3` unreleased.

This remains a separate non-1.3 documentation correction. Stable `1.2.0` is
current; BlackOps `1.3` remains an unreleased proposal. No Production Code,
release authority, dependency, generated artifact, commit, stage, push, PR, CI
dispatch, deploy, release, or publication changed.

## Changed Files

- `develop/orchestration/tasks/P23-001S-stable-list-documentation-truth.md`
- `develop/orchestration/reports/P23-001S-stable-list-documentation-truth.md`
- `develop/STATE.md`
- `develop/TODO.md`
- `docs/guide/project-cli.md`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php`
- `docs/website/scripts/product-framing-contract.mjs`
- `docs/website/tests/product-framing-contract.test.mjs`

The parent P23-001 proposal files outside STATE/TODO were preserved. No other
file was intentionally changed; generated Website output remained out of the
working-tree scope.

Final worktree scope evidence is the union of `15` paths: the P23-001S child
exact `9` allowed paths plus the `6` preserved parent-only proposal paths
outside shared STATE/TODO (`P23-001` Task, Spec README, D145, parent Report,
Spec 105, and Spec 106). Staged files: `0`.

## Decisions and Assumptions

1. The current direction is one-way: correct the Guide to observed Stable
   behavior. No fixture is allowed to reinterpret or certify a false
   side-effect-free guarantee.
2. The fresh behavior matrix is explicit command constructor count `1` during
   each fresh Kernel composition for default/raw/JSON/XML/Markdown; discovered
   Lazy factory count `0` for default/raw and `1` for non-short JSON/XML/Markdown.
   The tests intentionally make no general claim about `--short`.
3. Stable `1.2.0` current behavior and unreleased `1.3` proposal behavior stay
   separate. P23-001S changes documentation truth and regression guards only;
   it changes no capability or release authority.
4. The `blackops-japanese-docs` skill was read in full with its style checklist
   and applied in this order: technical facts and fixture evidence; Stable
   current versus unreleased proposal boundary; first-time-reader outcome and
   Source/Artifact parity; concise natural Japanese. This ordering kept prose
   within the verified runtime boundary and made the Guide searchable without
   reintroducing an absolute claim.
5. HTML/Markdown differences are handled as visible-text normalization. The
   guard decodes only valid named/numeric references, rejects invalid numeric
   references before comparison, and still requires all named artifact surface
   classes. Artifact parity cannot be inferred from Guide/Source evidence alone.

## Commands and Results

All results below are fresh worker evidence after the Sol xHigh bounded
correction, its end-to-end numeric-entity fixture update, the final
visible-container fixture correction, and the closed exact-alias classifier
fixture correction. Earlier Phase 2 PASS results are
superseded by this snapshot and are not reused as proof.

The final implementation and Website gates below were rerun after the latest
Guide/Website source and fixture changes. This Report, Task, STATE, and TODO
timestamp update follows those gates; no implementation evidence is reused
from the prior snapshot.

- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Application/ApplicationConsoleKernelTest.php tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php` — PASS; `29 tests`, `225 assertions`.
- `pnpm --dir docs/website exec node --test --test-isolation=none tests/product-framing-contract.test.mjs` — PASS; `7 tests`, including exact `page:`/`raw:` aliases, unknown-key rejection, required-marker block/paragraph splits, old-claim block splits, and hidden comment/script/style/template visibility fixtures.
- `pnpm --dir docs/website test` — PASS; `119 tests`.
- `pnpm --dir docs/website check` — PASS; content, diagrams, links, and Blume checks reported `0 errors`, `0 warnings`, `0 hints`.
- `pnpm --dir docs/website build` — PASS; `42 pages` built, Static Artifact, Site check for `41 pages`, Search/LLM files, redirects, and generated parity passed. Non-fatal existing Vite chunk-size and route-priority warnings were emitted.
- `pnpm --dir docs/website release:check:source` — PASS; Release claim source guard passed.
- `pnpm --dir docs/website release:check:artifact` — PASS; Release claim artifact guard passed.
- `bash tests/Consumer/version-baseline.sh` — PASS; `published=1.2.0 historical=1.1.0`.
- `docker compose run --rm app mago format --check src tests` — PASS; all files already formatted after the review-correction assertion formatting.
- `docker compose run --rm app mago lint` — PASS; baseline filtered `186` issues and reported no issues.
- `docker compose run --rm app mago analyze` — PASS, exit `0`; existing whole-repository output contains `71 issues` (`46 warning`, `25 help`) and no error. No Production Code was changed to address those existing findings.
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'` — PASS; no management-ID violation.
- Read-only JSON/XML/Markdown reconstruction — PASS; Specification 105 has `7/7` JSON fences, XML is `465` bytes with SHA-256 `579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968`, and Markdown is `247` bytes with SHA-256 `7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`.
- Read-only local Markdown link validation for the five P23-001S Markdown files — PASS; `10` total, `10` local, `0` missing. Website `check` independently passed the generated content/link contract.
- Read-only generated Artifact parity — PASS; whole-file UTF-8 byte counts are HTML `101987` bytes and raw Markdown `16052` bytes. Their Node String lengths are respectively `96525` and `11754` UTF-16 code units. The Search record `content` field (`record.content.length`) is `10204` UTF-16 code units, its serialized `JSON.stringify(record).length` is `10430` UTF-16 code units, and the extracted LLM CLI section is `11719` UTF-16 code units. All contained the ordered current boundary markers with no old claim.
- `git diff --check` — PASS.
- Final union scope and staged-none guard — PASS; `15` worktree paths are present: child exact `9` plus preserved parent-only `6`, with no staged file.
- Exact nine-file trailing-whitespace guard — PASS.
- Old Guide absolute-claim, numeric-entity, marker-order, block/hidden-container, and roadmap-leak guards — PASS; the old claim is present only in intentional Website negative-test fixture data.

The `2026-08-19T17:44:31+09:00` checkpoint follows the final Guide,
Website script, and Website fixture correction. All implementation, Website,
release, Mago, and Artifact parity gates above were rerun after that source
change. The management synchronization after those gates updates only the
current timestamp, evidence labels, Remaining Issues, and Suggested Next
Action; the implementation paths remain unchanged during that sync.

The root-review correction first exposed one exact-output mismatch in the
discovered default row: Symfony includes the observed `[fixture:hello]` alias.
The fixture was corrected to assert that real wire, then the focused suite
passed with `225` assertions. The full Mago format check identified the two
new assertion files before formatting; a dry-run isolated both files and
apply_patch corrected them. The fresh full check then passed. A prior
redirected analyze probe had a transient Docker API permission error; the
required direct command was rerun and completed with exit `0` as recorded
above.

Independent Sol xHigh documentation review was not run by the worker. The
Orchestrator subsequently reran the focused and complete Local Gate against a
historical implementation snapshot and obtained the historical read-only Sol
xHigh verdict `P1=0 / P2=0 / P3=0`. The reviewer independently checked the exact 15-path
freeze, all 15 SHA-256 values, the Stable runtime/source/vendor boundary, the
six-key Artifact classifier, 37/37 counterexample probes, generated Artifact
measurements, and Japanese reader journey. Browser Light/Dark/Mobile remains
Not Verified because the local Chromium lacked `libnspr4.so`; this copy-only
Task has no visual-layout change and the limitation is not an acceptance
finding.

## Acceptance Criteria

- [x] Guide uses observed Stable behavior only, introduces factory/Definition/
  Container in general terms, uses natural Japanese paragraph structure, and
  removes the absolute side-effect-free claim.
- [x] Explicit and discovered Lazy fresh-kernel PHP matrices assert output,
  exit, constructor/factory counts, valid manifest setup, and exact
  format-specific output shape.
- [x] Website Source and Artifact contracts require the boundary across CLI
  HTML/raw/Search/LLM-like surfaces, decode valid numeric references, and
  reject invalid references, entity-encoded old claims, missing/order-split
  markers, hidden-container-only required markers, and missing surfaces while
  ignoring old claims confined to non-visible HTML content.
- [x] Stable `1.2`/unreleased `1.3` boundary and no-production-change scope are
  preserved.
- [x] Required local tests, Website gates, release guards, baseline, Mago,
  management-ID, scope, whitespace, and diff checks have actual results above.
- [x] Historical Sol xHigh documentation review returned `P1=0 / P2=0 / P3=0`.
- [x] Historical root acceptance was recorded; Round 13 reopens this child.
- [ ] Fresh P23-001S General review.
- [ ] Fresh parent P23-001 General/Audit review.

## Historical Round 13 bounded correction — superseded review

Round 13 reopens P23-001S from the historical acceptance after the bounded
DOM visibility finding. The Website script now uses existing jsdom with a
closed six-key classifier: `cli-html`/`page:/reference/project-cli` are HTML,
`cli-raw`/`raw:reference/project-cli.md` are raw, and `cli-search`/`cli-llm`
are Search/LLM. HTML hidden subtrees and comments/script/style/template are
excluded while block boundaries and entities remain meaningful. Root
`html`/`body`, `!important`, and same-property cascade fixtures use jsdom's
effective inline declaration; external CSS and computed layout are explicitly
outside the claim. Primary+alias fixtures cover hidden required rejection,
hidden old-claim acceptance, visible current acceptance, literal-tag rejection
on raw/Search/LLM, and unknown alias failure.
The prior Guide/PHP behavior matrix is unchanged.

Fresh Round 13 gates after the final source correction are PASS: PHP
`29/225`, focused Website `7/7`, full Website `119/119`, check `0/0/0`, fresh
build `42` pages/site `41`, Source/Artifact release guards, version baseline,
Mago format/lint/analyze exit `0`, management-ID, JSON `7/7`, canonical
XML/Markdown hashes, generated Artifact parity, exact scope `15`, staged `0`,
diff/whitespace, and links `313` total / `277` local / `0` missing. Prior PASS
evidence remains historical and is not reused.

## Remaining Issues

Fresh child P23-001S General review remains, followed by parent P23-001
General/Audit review and Orchestrator acceptance; no 1.3 capability is public
or released.

## Suggested Next Action

Request child General review of the frozen exact-15 Local Gate, then request
parent General/Audit review and Orchestrator acceptance. Parent
acceptance must precede P23-001R or ordered downstream work.

## Historical Round 14 completion checkpoint

### Summary

Round 14 corrects the visibility semantics identified by fresh review.
Rendered text under `aria-hidden` or `inert` remains visible to this contract;
hard-hidden subtrees are limited to `hidden`, `display:none`,
`content-visibility:hidden`, comments, script, style, and template. Inline
`visibility:hidden|collapse` is inherited and an inline
`visibility:visible` descendant restores visible text. Root `html`/`body` use
the same rule. External stylesheet and computed-layout visibility are not
claimed. Stable `1.2` remains current and the `1.3` proposal remains
unreleased.

### Changed Files

Only the child Website script/test and the management/specification files in
the parent union are in scope. The Guide and both PHP behavior tests remain
unchanged. No Production Code, dependency, generated Artifact, commit, stage,
push, PR, CI, deploy, release, or publication action occurred.

### Decisions and Assumptions

- Paired `cli-html`/`page:/reference/project-cli` fixtures are the artifact
  boundary; raw/Search/LLM literal tags remain reader-visible.
- `aria-hidden` and `inert` are accessibility/inertness metadata, not a proof
  of visual absence. Hard subtree removal and inherited visibility are
  deliberately separate.
- The child remains `Correction Implemented / Review Requested`, not
  Green/Accepted/Decided.

### Commands and Results

Fresh Round 14 gates after the final Website/source correction and management/
specification synchronization are PASS: focused PHP `29/225`; focused Website
`7/7`; full Website `119/119`; Website check `0 errors / 0 warnings / 0 hints`;
fresh build `42 pages` and site check `41 pages`; release source/artifact;
version baseline `published=1.2.0 historical=1.1.0`; Mago format/lint PASS and
Mago analyze exit `0` with the unchanged `71 issues` baseline (`46 warning`,
`25 help`); PHP management-ID guard; Spec105 JSON `7/7`; XML `465` bytes SHA
`579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968`; Markdown
`247` bytes SHA
`7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`; and
Markdown links `313 total / 277 local / 0 missing`. Exact union `15`, staged
`0`, diff, trailing-whitespace, and current-truth/static guards also pass.
The build emitted only existing non-fatal chunk-size/route-priority warnings.
Browser, Remote CI, deployment, publication, and release execution were not
run. Historical Round 13 evidence is not reused as Round 14 evidence.

### Acceptance Criteria

- [x] Hard-hidden subtree and visibility inheritance rules are implemented in
  the existing dependency-backed Website guard.
- [x] Paired HTML primary/alias fixtures cover aria/inert, hard-hidden, root,
  visible-child, old-claim, and required-marker cases.
- [x] Guide/PHP behavior matrix and Stable/unreleased release boundary remain
  unchanged.
- [ ] Fresh child General review and parent review/acceptance.

### Remaining Issues

Fresh child General review remains, followed by parent P23-001 General/Audit
review and Orchestrator acceptance. No 1.3 capability is public or released.

### Suggested Next Action

The fresh Local Gate is recorded and frozen; request child General review,
then request parent General/Audit review and Orchestrator acceptance.

## Historical Round 15 Completion Report — superseded review checkpoint

### Summary

Round 15 closes the remaining visible-body contract cases: `head/title` is
excluded, `visibility:initial` descendants remain visible, and block-element
boundaries prevent marker concatenation. The primary HTML and
`page:/reference/project-cli` alias fixtures are paired. The Guide and PHP
behavior matrix remain unchanged, and Stable `1.2` is not described as a
side-effect-free diagnostic surface.

### Changed Files

Only the existing Website contract script/test and the shared parent/child
management/specification union are allowed. The Guide and both PHP tests are
byte-identical. No Production Code, dependency, generated artifact, commit,
stage, push, PR, CI, deploy, release, or public 1.3 capability changed.

### Decisions and Assumptions

The guard evaluates visible HTML body text and preserves block boundaries;
external stylesheet/computed-layout visibility remains outside the claim.
Paired primary/alias fixtures are the artifact boundary. Historical Round 14
gate evidence is not reused after this source edit.

### Commands and Results

Fresh Round 15 implementation gates after the final Website source edit:

- focused Website contract: PASS, `7/7`;
- full Website test: PASS, `119/119`;
- Website check: PASS, `0 errors / 0 warnings / 0 hints`;
- Website build: PASS, `42` pages and `41`-page site check, with only the
  existing non-fatal chunk-size/route-priority warnings;
- release source/artifact: PASS;
- focused PHP: PASS, `29 tests / 225 assertions`;
- version baseline: PASS, `published=1.2.0 historical=1.1.0`;
- Mago format/lint/analyze: PASS; analyze exit `0` with unchanged baseline
  `71 issues` (`46 warning / 25 help`);
- management-ID: PASS; exact union: PASS, 15 paths and staged 0;
  `git diff --check`: PASS; trailing-whitespace: PASS; Spec105 JSON: PASS,
  7/7; XML: PASS, 465 bytes SHA
  `579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968`;
  Markdown: PASS, 247 bytes SHA
  `7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`;
  links: PASS, 313 total, 277 local, 0 missing; current-truth/static guard:
  PASS. These final checks are management-only after the fresh source gates;
  historical Round14 evidence is not reused.

### Acceptance Criteria

- [x] `head/title`, `visibility:initial`, and block-boundary behavior is
  covered by paired HTML fixtures.
- [x] Guide/PHP behavior and Stable/unreleased boundary remain unchanged.
- [x] Fresh child Local Gate is PASS; fresh child General review and
  parent/Orchestrator review remain.

### Remaining Issues

Fresh child General review remains, then parent P23-001 General/Audit review
and Orchestrator acceptance. No 1.3 capability is public or released.

### Suggested Next Action

The complete fresh child Local Gate is PASS; request child General review,
then route the parent review and Orchestrator acceptance.

## Historical Round 17 Completion Report — superseded review checkpoint

### Summary

Round 17 closes the fresh child General finding about explicit command
registration and the HTML visibility override. The Guide now distinguishes a
class-string, which the Console Kernel constructs, from a caller-created
prebuilt Command instance, which is reused. The PHP fixture proves the
prebuilt construction count remains one through default, raw, and non-short
JSON/XML/Markdown list invocations. The Website contract treats plain
`hidden` as hidden but honors an inline non-`none` display override, with
paired primary/alias fixtures.

### Changed Files

The exact 15-path union remains the maximum scope. Round 17 changed only the
Guide, Website contract script/test, two PHP behavior tests, and the parent /
child management and specification files required for synchronization. No
Production Code, dependency, generated artifact, commit, stage, push, CI,
deploy, publication, or release action occurred.

### Decisions and Assumptions

The Stable `1.2` behavior is observed Symfony behavior, not a new 1.3 claim.
The HTML guard remains bounded to parsed inline HTML visibility; external CSS
and computed layout are outside its claim. `hidden style="display:block"` and
`display:block!important` are visible for this contract, while a plain hidden
subtree remains excluded. Round 16 child findings `P1=1/P2=2/P3=0` remain
historical and are not aggregated with any other lane.

### Commands and Results

Round 17 focused evidence after the source edits: Website contract `7/7`
PASS; full Website `119/119` PASS; Website check `0/0/0` PASS; build `42`
pages with a `41`-page site check PASS; release source/artifact PASS; version
baseline PASS (`published=1.2.0 historical=1.1.0`); focused PHP `30 tests /
250 assertions` PASS; Mago format/lint PASS and analyze exit `0` with the
unchanged `71`-issue baseline; PHP management-ID PASS; JSON `7/7`, XML/MD
wire hashes, links `313/277/0`, exact scope `15/15` staged `0`, diff, and
whitespace guards PASS. No earlier Round 16 PASS is reused.

### Acceptance Criteria

- [x] Class-string and prebuilt-instance construction boundaries are stated
  and tested.
- [x] Paired primary/alias hidden/display HTML fixtures are present.
- [x] Fresh complete Local Gate; [ ] fresh child General review.

### Remaining Issues

Child General re-review, then parent General/Audit review and Orchestrator
acceptance remain. No 1.3 capability is public or released.

### Suggested Next Action

Freeze the complete Round 17 Local Gate snapshot, route fresh child General
review, then route parent General/Audit review and Orchestrator acceptance.

### Round 17 bounded follow-up evidence

At `2026-08-19T21:59:03+09:00`, the Guide and Website required marker were
synchronized to the exact format-independent generation/reuse sentence.
Focused Website `7/7`, full Website `119/119`, check `0/0/0`, build `42` pages
with a `41`-page site check, release source/artifact, focused PHP `30/250`,
version baseline, Mago, management-ID, JSON `7/7`, XML/Markdown hash, links
`313/277/0`, exact15/staged0, diff/whitespace, and current-truth guards all
PASS. No external action occurred.

## Historical Round 18 Completion Report — superseded review checkpoint

### Summary

Round 18 implements the child General native-visibility correction within the
existing exact-15 union. The inline HTML guard now limits a closed `details`
element to its first direct `summary`, hides its body until `open`, hides a
plain closed `dialog`, and honors an explicit non-`none` inline display
override while retaining `display:none` hiding. Paired primary/alias fixtures
cover closed/open details and dialogs, old claims, required-only content,
summary visibility, and visibility overrides. Round 17 is historical Changes
Requested; Round 18 was then-current `Correction Implemented / Review
Requested` and is now historical after child General returned
`P1=0/P2=1/P3=0`. Stable `1.2` remains current and 1.3 remains unreleased.

### Changed Files

Only the existing exact-15 union is in scope. This correction changed the
Website contract script/test and shared management/specification files. The
Guide and both PHP behavior tests remain byte-identical. No Production Code,
dependency, generated artifact, commit, stage, push, CI, deploy, publication,
or release action occurred.

### Decisions and Assumptions

HTML boolean `open` is presence-based. Closed `details` exposes only its first
direct `summary`; descendant visibility overrides do not expose its body. A
closed `dialog` remains hidden unless open or its effective inline display is
non-`none`; `display:none` remains hidden. External stylesheet/computed-layout
behavior remains outside the contract.

### Commands and Results

Fresh focused Website evidence after the final source edit: PASS, `7/7`.
The complete Local Gate is fresh after this source change: full Website
`119/119`, check `0/0/0`, build `42` pages/site `41`, release source/artifact,
focused PHP `30 tests / 250 assertions`, version baseline PASS, Mago format
PASS, lint PASS, and analyze exit `0` with `71` existing baseline issues, PHP
management-ID PASS, requestRef Node+Docker PHP `JCS 121 bytes / LP 165 bytes /
SHA-256 692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c`
PASS, JSON `7/7`, XML `465 bytes SHA-256
579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968`, Markdown
`247 bytes SHA-256
7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`, links
`313/277/0`, exact15/staged0, diff/whitespace, current-status guard with
historical allowlist, and static guards PASS. No external action occurred.

### Acceptance Criteria

- [x] Native closed `details`/`dialog` traversal and paired primary/alias
  fixtures are implemented.
- [x] Guide/PHP behavior, Stable `1.2`, and unreleased `1.3` boundaries remain
  unchanged.
- [x] Fresh complete Local Gate; [ ] fresh child General review.

### Remaining Issues

Fresh child P23-001S General review remains, followed by parent P23-001
General/Audit review and Orchestrator acceptance. P23-001R and ordered
downstream remain after acceptance; no 1.3 capability is public or released.

### Suggested Next Action

Historical next action: freeze the exact-15 Round 18 Local Gate and route
fresh child General review; Round 19 supersedes this checkpoint.

## Historical Round 19 Completion Report — superseded review checkpoint

### Summary

Round 19 recorded the round-neutral child Task/Report authority correction in
the parent DAG. Round 18 is historical Changes Requested (`P1=0/P2=1/P3=0`),
and Round 19 was then the sole-current `Correction Implemented / Review
Requested` status. No Guide, Website, PHP test, production, dependency, generated
artifact, or release file changed in this round.

### Changed Files

Only the ten permitted management/specification files changed in the shared
parent/child union. The Guide, Website script/test, and both PHP tests remain
byte-identical. No commit, stage, push, CI, deploy, publish, or release
occurred.

### Decisions and Assumptions

The child Task and Report are status authority only for the sole-current review
checkpoint; Round 18 and earlier are historical. Stable `1.2` remains current
and the `1.3` proposal remains unreleased. Report self-hashes are returned in
the final exact-15 handoff because embedding a report's own hash would change
its bytes.

### Commands and Results

Fresh complete Local Gate: focused Website `7/7` and full Website `119/119`
PASS; check/build and release source/artifact PASS; focused PHP `30 tests /
250 assertions` PASS; version baseline PASS; Mago format/lint PASS and analyze
exit `0` with `71` existing baseline issues; management-ID PASS. Node and
Docker PHP requestRef recomputation both matched JCS `121` / LP `165` / SHA-256
`692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c`; JSON
`7/7`, XML `465` bytes SHA-256
`579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968`,
Markdown `247` bytes SHA-256
`7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`, and
links `313/277/0` PASS. Exact-15/staged0/diff/whitespace/current-status
guards PASS. Expected build and fixture warnings were non-fatal.

### Acceptance Criteria

- [x] Round-neutral child status authority is synchronized.
- [x] Round 19 status, gate state, Remaining, and Next Action are synchronized.
- [x] Stable behavior and the unreleased `1.3` boundary are preserved.
- [ ] Fresh child General review, parent review, and Orchestrator acceptance.

### Remaining Issues

Fresh P23-001S General review remains, followed by parent P23-001 General/Audit
review and Orchestrator acceptance. P23-001R and ordered downstream remain
after acceptance; no 1.3 capability is public or released.

### Suggested Next Action

Route the fresh child General review, then request independent parent review;
wait for Orchestrator acceptance before P23-001R or downstream work.

## Historical Round 20 Completion Report — superseded review checkpoint

### Summary

Round 20 corrects the two remaining active Specification 106 DAG phrases that
named Round 18 as current. They now use the round-neutral current child
Task/Report authority. Round 19 child General `P1=0/P2=1/P3=0` is historical
Changes Requested; this child is not Accepted.

### Changed Files

Only the ten permitted management/specification files changed in Round 20.
The Guide, Website script/test, and both PHP behavior tests remained
byte-identical. No Production Code, dependency, generated Artifact authority,
or release authority changed, and no external or Git publication action ran.

### Decisions and Assumptions

Historical review evidence is preserved, while active DAG status is
round-neutral. Stable `1.2` remains current and Phase 23/`1.3` remains
unreleased. Final self-hashes are returned outside this Report.

### Commands and Results

Fresh complete Local Gate: focused Website `7/7`, full Website `119/119`,
check `0/0/0`, build `42` pages/site check `41`, release source/artifact,
focused PHP `30/250`, version baseline, Mago format/lint and analyze exit `0`
with the unchanged `71` issues, PHP management-ID, Node/Docker PHP requestRef
`121/165/692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c`,
JSON `7/7`, XML `465` bytes, Markdown `247` bytes, links `313/277/0`,
exact-15/staged0, diff/whitespace, active-status, and frozen-file guards PASS.

### Acceptance Criteria

- [x] Both active DAG status phrases are round-neutral.
- [x] Round 20 management truth and complete Local Gate are synchronized.
- [x] Guide/PHP behavior and Stable/unreleased boundaries remain unchanged.
- [ ] Fresh child General review.

### Remaining Issues

Fresh child General review remains, followed by parent General/Audit review
and Orchestrator acceptance. P23-001R and ordered downstream work remain.

### Suggested Next Action

Freeze the exact-15 Round 20 snapshot and route fresh child General review.

## Historical Round 21 Completion Report — superseded child checkpoint

### Summary

Round 21 completes the bounded management correction requested after the fresh
Round 20 child General review (`P1=0/P2=1/P3=0`). The parent D145 and
Specifications 105/106 active gate paragraphs now state the fresh Round 21
Local Gate, and Specification 106's child-authority cutoff is round-neutral for
all prior checkpoints. Fresh child General review returned `P1=0/P2=0/P3=0`,
and the Orchestrator accepted this child at `2026-08-20T02:21:22+09:00`.
Stable `1.2` remains current and Phase 23/`1.3` remains unreleased.

### Changed Files

Only the ten permitted management/specification files changed in Round 21.
The Guide, Website contract script/test, and both PHP behavior tests remained
byte-identical. No Production Code, dependency, generated Artifact, release
authority, commit, stage, push, CI, deploy, publication, or release action
occurred.

### Decisions and Assumptions

Round 20 child General `P1=0/P2=1/P3=0` is historical Changes Requested and
is not aggregated. Historical sections remain unchanged; active status,
gate, Remaining, and Next Action are synchronized to Round 21. Final
self-hashes are supplied outside the Report.

### Commands and Results

Fresh complete Round 21 Local Gate: focused Website `7/7`, full Website
`119/119`, check `0/0/0`, build `42` pages/site check `41`, release
source/artifact, focused PHP `30/250`, version baseline, Mago format/lint and
analyze exit `0` with the unchanged `71` issues, PHP management-ID,
Specification 105 JSON `7/7`, canonical XML/Markdown, links `313/277/0`,
exact-15/staged0, frozen-file, diff/whitespace, and active-status guards PASS.
Exact command output and final hashes are provided in the handoff.

### Acceptance Criteria

- [x] The three active stale Round 19 gate paragraphs are current for Round 21.
- [x] Specification 106's cutoff includes all prior checkpoints as historical.
- [x] Round 21 status, gate, Remaining, and Next Action are synchronized.
- [x] Fresh child General re-review returned `P1=0/P2=0/P3=0`; the
  Orchestrator accepted P23-001S.
- [ ] Parent General/Audit review and parent Orchestrator acceptance.

### Remaining Issues

Parent P23-001 General/Audit review and parent Orchestrator acceptance remain.
P23-001R and ordered downstream work remain after acceptance; no 1.3
capability is public or released.

### Suggested Next Action

Freeze the accepted-child exact-15 Round 21 snapshot and request independent
parent General/Audit review. Parent acceptance remains separate.
