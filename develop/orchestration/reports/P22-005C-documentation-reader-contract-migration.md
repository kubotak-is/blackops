# P22-005C Report: Documentation Reader Contract Migration

Status: Accepted — Sol xHigh P1=0／P2=0／P3=0
Updated At: 2026-08-17T18:38:10+09:00

## Current Full Local Gate — Accepted

Updated At: 2026-08-17T18:38:10+09:00

### Summary

The 17:41 nested-template candidate is historical and superseded for Acceptance after Sol xHigh found that global comment stripping treated `<!--` inside `script`/`style` raw text as an HTML comment and that SVG/Math CDATA text could contribute a literal `<template>` to template depth. The reproduced dist contains 362 non-map Artifact files.

The bounded correction routes HTML through the existing direct `jsdom` dependency as one standards-aware DOM state scan, with a quote-aware raw-text preflight for malformed `template`/`script`/`style` start tags. DOM parsing now removes comments only in HTML data state, keeps `script`/`style` raw text opaque, excludes inert `template` content with nested depth, and preserves SVG/Math CDATA literals without treating embedded markup as active HTML. Active JSON-LD, visible HTML, metadata, `noscript`, raw Markdown/MDX, Search, LLM, and hashed-asset routing remain fail-closed as specified.

The final consolidated Sol xHigh review accepted this exact snapshot with P1=0/P2=0/P3=0 after the Report-only evidence corrections. Superseded candidate snapshots remain explicitly historical and separate from the current Full Local Gate evidence.

### Changed Files

Source/test changes remain limited to `docs/website/scripts/reader-contract.mjs` and `docs/website/tests/reader-contract.test.mjs`; the existing direct `jsdom` dependency was reused without package changes. Management synchronization is recorded in this Report, the Task Packet, parent P22-005, `develop/STATE.md`, and `develop/TODO.md`. No unrelated dirty worktree changes were modified.

### Commands and Results

- Source release guard: PASS.
- Website test: PASS 109/109.
- Website check content/diagrams/links/typecheck: PASS; 41 pages, 0 errors, 0 warnings, 0 hints.
- Fresh Website build: PASS; 42 pages; static artifact boundary: PASS; site check: PASS for 41 pages, with only known non-fatal chunk-size and root route-priority warnings.
- Separate artifact release guard: PASS.
- Quickstart `bash -n`: PASS; full Consumer E2E: PASS, ending `Quickstart consumer E2E passed.`
- Version `bash -n`: PASS; baseline: PASS, `published=1.2.0 historical=1.1.0`.
- Mago format check: PASS for all files.
- PHP management-ID inverted `rg`: PASS.
- `git diff --check`: PASS.

The final consolidated Sol xHigh review is P1=0/P2=0/P3=0 and Accepted. The complete Local Gate passed against this exact candidate, and the focused rerun was performed against the exact current Test SHA/mtime recorded below, with no subsequent Source/Test changes. Mechanical counts remain `actualShikiFixtures=216` (864 four-path executions), `tenthFixtures=76`, and CommonMark/Satteri `66`. The reproduced dist contains 362 non-map files. Source/test final mtimes are `reader-contract.mjs` 17:58:54 JST and `reader-contract.test.mjs` 18:04:40 JST. SHA-256 values are `daef847b0c8aa9b8a0ca128135cd6958fd4cd51b9e2c895718c45ffa147133d3` and `d2b9f1f5439cecfa8d49fffef70b62f310d29ecee5f19e1832d721f025eb3a46`. No Commit/Stage/Push/PR/CI/Deploy/Release occurred.

### Decisions and Assumptions

HTML reader extraction is structural through the existing direct `jsdom` parser: comments are DOM nodes in HTML data state, script/style raw text and template content are removed as DOM containers, SVG/Math CDATA remains text, and a quote-aware preflight retains fail-closed malformed raw-text start-tag behavior. Visible block tags, `noscript`, metadata, and normalized JSON-LD strings reach the Shell parser; malformed JSON-LD remains raw text so it cannot bypass fail-closed checking. Generic all-file Artifact guards remain unchanged.

### Acceptance Criteria

The unified HTML state-scanner P2 is Full-Gate-green: script/style comment literals no longer hide following visible code, SVG/Math CDATA cannot alter template depth, nested/mixed-case and valid missing-close templates remain inert, active JSON-LD after template close and `noscript` unsafe content reject, malformed raw-text start tags fail closed, prior safe controls remain safe, and the full generated-dist validator integration passes. Final Sol xHigh review is P1=0/P2=0/P3=0; P22-005C is Accepted.

### Remaining Issues

No blocker or remaining P22-005C issue exists. Parent P22-005D Browser/Accessibility/Search/Production verification, exact reviewed Commit, and same-SHA delivery remain.

### Suggested Next Action

Proceed with P22-005D against this accepted documentation candidate, then prepare the reviewed Commit and same-SHA delivery. Do not Commit, Stage, Push, dispatch CI, Deploy, or Release before those steps.

## Historical HTML Visible-Boundary and Quote-Aware Script Extraction Correction — Superseded for Acceptance

Updated At: 2026-08-17T16:28:23+09:00

### Summary

The Full Local Gate result is historical and superseded for Acceptance. Source guard, Website 107/107, Website check, complete build, and static artifact boundary passed, then `site:check` failed on fresh `_astro/base-path.Ds54SYed.js` with `export{n,t}`. The defect was format routing: the prior Artifact loop sent every non-map Artifact file, including hashed JavaScript/CSS, fonts, images, and XML, through the Shell-only LGTM parser. Three hashed JavaScript files were false positives; the 46 HTML, 82 raw Markdown/MDX, Search, `llms.txt`, and `llms-full.txt` reader surfaces had no violation.

The bounded implementation adds `artifactReaderSurfaceText` and `assertArtifactReaderFile`. Raw `.md`/`.mdx`, root LLM text, and `blume-search.json` strings remain Shell-validated. HTML routes visible/body text and explicit `meta` plus JSON-LD content through the parser, while executable `script`/`style` source is excluded except intentional JSON-LD extraction. Existing generic protected-decode, Stable/main, and internal-evidence guards still run on every Artifact file; no Shell-parser allowlist was added for JavaScript `export` or `set`.

### Changed Files

Source/test changes are limited to `docs/website/scripts/reader-contract.mjs` and `docs/website/tests/reader-contract.test.mjs`; management synchronization is recorded in this Report, the Task Packet, parent P22-005, `develop/STATE.md`, and `develop/TODO.md`. No unrelated dirty worktree changes were modified.

### Commands and Results

- `node --check docs/website/scripts/reader-contract.mjs && node --check docs/website/tests/reader-contract.test.mjs`: PASS.
- `node --test --test-isolation=none docs/website/tests/reader-contract.test.mjs`: PASS 11/11, including format-routed raw Markdown/HTML/Search/LLM/metadata/JSON-LD negatives and JS/style/inline-script positives.
- `node /tmp/p22-005c-inline-backtick-counterexamples.mjs`: PASS; all expected rows.
- `mise exec -- pnpm --dir docs/website run release:check:source`: PASS.
- `mise exec -- pnpm --dir docs/website run check`: PASS; 41 pages, 0 errors, 0 warnings, 0 hints.
- `mise exec -- pnpm --dir docs/website run site:check`: PASS; 41 pages.
- `git diff --check`: PASS.

Mechanical counts are unchanged: `actualShikiFixtures=216` (864 four-path executions), `tenthFixtures=76`, and CommonMark/Satteri `66` fixtures. Source/test final mtimes are `reader-contract.mjs` 16:27:52 JST and `reader-contract.test.mjs` 16:26:25 JST. SHA-256 values are `8810551cea6944aaa3eea7176a96a91620d5f7f8af6bd060e81a602b372fc59b` and `8c5d660be632cebfe2101b1f68e23f0502371018dd7acf65c66b3aee990cec06`. Full Gate restart, Consumer gates, and build were not run for this bounded correction.

### Decisions and Assumptions

Artifact reader validation is routed by reader format rather than filename content: only raw Markdown/MDX, LLM text, Search strings, and HTML visible/metadata/JSON-LD surfaces reach the LGTM Shell parser. HTML `script` and `style` executable source is not reader text; JSON-LD remains intentional metadata. Generic Artifact guards remain format-independent.

### Acceptance Criteria

The build-gate P1 is focused-Green: generated hashed JS `export{n,t}` and `set` no longer produce Shell-parser false positives; unsafe raw Markdown, visible HTML code, Search, LLM, metadata, and JSON-LD injections fail closed; inline script/style controls pass; and current generated `site:check` passes. Full Local Gate and independent Sol Acceptance remain pending, so no Acceptance claim is made.

### Remaining Issues

No blocker exists in this correction scope. The complete Required Commands and Orchestrator Local Gate were intentionally deferred by the task instruction; independent Sol xHigh re-review/Acceptance remains. Parent P22-005D Browser/Accessibility/Search/Production verification, exact reviewed Commit, and same-SHA delivery remain.

### Suggested Next Action

Orchestrator reviews this exact uncommitted artifact-routing candidate and authorizes the complete P22-005C Local Gate. Do not Commit, Stage, Push, dispatch CI, Deploy, or Release before review.

## Historical Thirteenth Nested CommonMark Marker-Chain Follow-up — Superseded for Acceptance

Updated At: 2026-08-17T16:16:37+09:00

### Summary

The 16:03 list-marker candidate is historical and superseded. Continuation prose now treats bare `env` and `readonly` as unsafe by default; only exact English/Japanese technical descriptions are prose-safe from the normalized physical/rendered line start through its end (`Use env as the command name.`, `Use readonly as a PHP keyword.`, and matching command-name/PHP-keyword Japanese wording). Imperative prefixes such as `Execute this now: Use ...` and appended imperative/dump suffixes such as `Then execute it.` reject through every lane. The full repeated CommonMark container-marker chain (blockquote plus nested unordered/ordered markers in mixed order) is structurally normalized before the same continuation classifier, so nested `Run env`, `printenv`, and declaration dumps reject while nested `Use env as prose` remains safe through Source/plain Artifact/actual Satteri HTML/actual-Satteri Artifact. The reported Japanese execution/command forms, English dump/Try forms, no-argument `printenv`/`set`, declaration dumps, protected identifiers, and LGTM/Docker/Grafana diagnostics fail closed. Markdown blockquote and list markers are removed structurally before exact line classification; Markdown and actual Satteri HTML share the suffix-aware full-context classifier; executable Shell fences, raw input, and pre/code remain unconditional Shell validation. Satteri marker padding is explicit: same-line spaces-before-tab are prose for unordered 0–2 and ordered 0–1, code for unordered 3+ and ordered 2+; blank-line tab continuations remain prose through seven spaces and code at eight. Handcrafted HTML remains supplementary.

### Changed Files

Source/test changes remain limited to docs/website/scripts/reader-contract.mjs and docs/website/tests/reader-contract.test.mjs. Management synchronization is recorded in this Report, the Task Packet, parent P22-005, TODO, and develop/STATE.md. No unrelated dirty worktree changes were modified.

### Commands and Results

- node --check for Source and test: PASS.
- node --test --test-isolation=none docs/website/tests/reader-contract.test.mjs: PASS 10/10, including default-fail-closed bare `env`/`readonly`, English/Japanese technical positives, Satteri-rendered marker padding, and prior Source/plain Artifact/actual HTML/actual-HTML Artifact coverage.
- node /tmp/p22-005c-inline-backtick-counterexamples.mjs: PASS; all expected negative/positive rows, including Japanese/English continuation contexts, Markdown/llms-full/HTML env, printenv, declaration, technical-prose, continuation, and marker-padding cases.
- Focused i18n continuation matrix: PASS 56/56 (14 fixtures x Source/plain Artifact/actual Satteri HTML/actual-Satteri Artifact), covering the four reported English/Japanese negatives, English dump/Try negatives, Japanese technical positives, exact English technical positives, appended English imperative/dump suffix negatives, and English/Japanese imperative-prefix negatives.
- CommonMark/Satteri marker-chain matrix: PASS with 66 actual Satteri fixtures, including nested unordered/ordered/blockquote env, printenv, declaration-dump negatives and technical-positive controls through Source/plain Artifact/actual Satteri HTML/actual-Satteri Artifact; existing padding/tab/container/fence cases remain covered.
- Focused multiline direct/plain Artifact matrix: PASS; 3 negative and 1 positive cases each pass through Source and Artifact paths (single/double multiline env and Docker inspect negatives reject; multiline technical prose passes).
- CommonMark container/Artifact matrix: PASS for 66 Satteri-rendered fixtures (264 actual Source/plain Artifact/HTML/HTML Artifact assertions) plus two top-level indented controls (8 direct/plain/handcrafted-HTML Artifact assertions); continuation no-argument environment/declaration-dump negatives, technical prose positives, blockquote/list lazy and indented controls, blockquote Shell/text-fence exit controls, list non-Shell-fence positive, marker-relative space and tab-padding controls, nested marker-chain controls, and top-level indented env/protected-dump negatives all match expected outcomes. Two explicit prefixed/outcome marker Artifact assertions also pass. A further 132 handcrafted-HTML assertions remain labeled supplementary.
- Focused Satteri continuation/marker matrix: PASS, 12 fixtures x 4 Source/plain Artifact/actual Satteri HTML/actual-Satteri Artifact lanes (48/48), including `Run env` rejection, `Use env` and same-line/blank-line tab-padding boundaries, and marker-code negatives.
- Independent fence/lazy-container matrix: PASS, 10 fixtures x 4 Source/plain/HTML Artifact paths (40/40).
- Focused direct/plain Artifact environment matrix: PASS; seven protected Markdown/HTML/llms negative cases and six bare technical-prose positives.
- mise exec -- pnpm --dir docs/website run release:check:source: PASS.
- mise exec -- pnpm --dir docs/website run check: PASS; 41 pages, 0 errors, 0 warnings, 0 hints.
- git diff --check: PASS.

The mechanical test-source count is `actualShikiFixtures=216`, with each entry running Source, plain Artifact, actual Shiki HTML, and actual-Shiki Artifact paths: 864 four-path executions. The separate `tenthFixtures=76` loop also invokes actual Shiki and validates Source/plain Artifact/actual-Shiki Artifact lanes. The 66 CommonMark fixtures invoke the resolved `@astrojs/markdown-satteri` processor for actual HTML; handcrafted HTML is supplemental and explicitly labeled. Source/test final mtimes are reader-contract.mjs 16:10:58 JST and reader-contract.test.mjs 16:10:58 JST. Full Required Commands were intentionally not run before Orchestrator authorization.

### Decisions and Assumptions

Only structured non-fenced Markdown inline code containing a parsed protected environment/declaration command or LGTM/Docker/Grafana diagnostic is promoted to an independently delimited guard segment. Continuation prose defaults bare `env`/`readonly` to unsafe and admits only exact full-context technical descriptions; the current physical/rendered line is normalized, the complete Markdown blockquote/list marker chain is removed structurally, and both prefix and suffix are consumed so imperative/dump wording cannot be hidden. The actual Satteri renderer is used for the production HTML lane; handcrafted HTML remains a clearly named supplemental fixture. Equal-length multiline spans normalize line endings before classification; raw direct input and executable pre/Shiki input remain unconditional Shell validation.

### Acceptance Criteria

The nested CommonMark marker-chain continuation correction is focused-Green with Source, plain Artifact, actual Satteri HTML, and actual-Satteri Artifact evidence. Nested unordered/ordered/blockquote env/printenv/declaration dumps fail closed, nested technical-positive list prose passes, exact full-context English/Japanese descriptions and prefix/suffix closure remain enforced, and the prior CommonMark/renderer guards remain in the formal suite. Full Local Gate and independent Sol Acceptance remain pending; no Acceptance claim is made here.

### Remaining Issues

No blocker exists in this correction scope. Full Required Commands, Orchestrator Local Gate, and independent Sol xHigh re-review/Acceptance remain. Parent P22-005D Browser/Accessibility/Search/Production verification, exact reviewed Commit, and same-SHA delivery remain.

### Suggested Next Action

Orchestrator reviews this exact uncommitted focused-Green candidate and authorizes the complete P22-005C Local Gate. Do not Commit, Stage, Push, dispatch CI, Deploy, or Release before review.

## Historical Thirteenth Structural Markdown/Artifact Backtick Follow-up — 13:36 Candidate, Superseded for Acceptance

Updated At: 2026-08-17T13:36:18+09:00

### Summary

The 13:11 candidate is historical and superseded. The final bounded correction makes Markdown inline backtick masking delimiter-run aware and preserves protected LGTM/Docker-inspect/Grafana-secret inline spans for the shared guard. HTML inline code outside pre is masked as prose except for those protected markers; pre/code and actual Shiki output remain executable. Literal single backticks inside equal double-backtick spans are handled without a punctuation or executable-name heuristic.

### Changed Files

Source/test changes are limited to docs/website/scripts/reader-contract.mjs and docs/website/tests/reader-contract.test.mjs. Management synchronization is recorded in this Report, the Task Packet, parent P22-005, TODO, and develop/STATE.md. No unrelated dirty worktree changes were modified.

### Commands and Results

- node --check for Source and test: PASS.
- node --test --test-isolation=none docs/website/tests/reader-contract.test.mjs: PASS 10/10.
- node /tmp/p22-005c-inline-backtick-counterexamples.mjs: PASS; all listed negative/positive outcomes matched expectations, including double-backtick Markdown and protected inline State/Health controls.
- Focused direct/plain Artifact/actual-Shiki matrix: PASS; seven raw/actual-Shiki negative classes, four protected Markdown/HTML classes, five prose positives, double-backtick safe/protected cases, standalone inline HTML, and pre/code negative.
- mise exec -- pnpm --dir docs/website run release:check:source: PASS.
- mise exec -- pnpm --dir docs/website run check: PASS; 41 pages, 0 errors, 0 warnings, 0 hints.
- git diff --check: PASS.

The actualShikiFixtures array contains 216 entries and runs each through Source, plain Artifact, actual Shiki HTML, and actual-Shiki Artifact paths: 864 four-path executions. Source/test final mtimes are reader-contract.mjs 13:35:32 JST and reader-contract.test.mjs 13:29:09 JST. Full Required Commands were intentionally not run before Orchestrator authorization.

### Decisions and Assumptions

Raw direct input and executable pre/Shiki input are always parsed as Shell. Markdown .md/.mdx and llms inline code is prose only when unprotected; protected LGTM/Docker-inspect/Grafana-secret spans remain visible. HTML code outside pre is the analogous structural prose boundary. This preserves the exact technical-token prose case while preventing inline diagnostic bypass.

### Acceptance Criteria

The bounded inline-code and Markdown/Artifact guard correction is focused-Green with direct, plain Artifact, and actual-Shiki evidence. Full Local Gate and independent Sol Acceptance remain pending; no Acceptance claim is made here.

### Remaining Issues

No blocker exists in this correction scope. Full Required Commands, Orchestrator Local Gate, and independent Sol xHigh re-review/Acceptance remain. Parent P22-005D Browser/Accessibility/Search/Production verification, exact reviewed Commit, and same-SHA delivery remain.

### Suggested Next Action

Orchestrator reviews this exact uncommitted focused-Green candidate and authorizes the complete P22-005C Local Gate. Do not Commit, Stage, Push, dispatch CI, Deploy, or Release before review.

## Historical Thirteenth Structural Markdown/Artifact Backtick Follow-up — 13:11 Candidate, Superseded for Acceptance

Updated At: 2026-08-17T13:11:54+09:00

### Summary

The 12:51:40 candidate is superseded by independent direct and Shiki-shaped Artifact counterexamples. The previous inline-code heuristic could pass arbitrary executable names with legacy backticks and reject Markdown/PHP inline-code prose. The bounded correction removes the executable-name allowlist and uses structural input context: Markdown source prose outside executable Bash／Shell fenced or indented blocks is masked; executable blocks remain recursive Shell input; plain and Artifact-visible text use punctuation and shell-operator boundaries without a command-name allowlist; language-tagged Shiki blocks are retained as executable Artifact context.

Exact negatives `custom-tool prefix `env` suffix`, `/usr/local/bin/custom-tool prefix `readonly` suffix`, `MODE=check custom-tool prefix `printenv` suffix`, `result=pre`env`post`, and `prefix`env`suffix` reject through direct／plain Artifact／actual Shiki／actual-Shiki Artifact lanes. Japanese／ASCII／parenthesized prose positives `ValueとOutcomeは空の`readonly` Classです。`, `The type is `readonly` Class metadata.`, `Use `readonly`.`, `値は`readonly`。`, and `（`readonly`）` pass. Shell fenced／indented negatives remain rejected and non-Shell fenced／Markdown inline prose remains safe.

Focused Commands and Results:

- `node --check docs/website/scripts/reader-contract.mjs`: PASS.
- `node --check docs/website/tests/reader-contract.test.mjs`: PASS.
- `node /tmp/p22-005c-inline-backtick-counterexamples.mjs`: PASS; 3 negatives／2 positives across direct and Shiki-shaped Artifact.
- Extended adjacent／punctuation／Markdown-context matrix: PASS, 60/60 expected outcomes across direct／plain Artifact／actual Shiki representations and Source／Artifact validators.
- `node --test --test-isolation=none docs/website/tests/reader-contract.test.mjs`: PASS 10/10.
- `mise exec -- pnpm --dir docs/website run check`: PASS; Content／diagrams／Blume validation／check, 41 pages, 0 errors／warnings／hints.

The actualShikiFixtures array contains 218 entries and executes every entry through Source, plain Artifact, actual Shiki HTML, and actual-Shiki Artifact paths: 872 four-path executions. The nine new correction fixtures are included. Full Required Commands remain intentionally deferred until Orchestrator review. No blocker or external mutation occurred.

Remaining P22-005C steps are all Required Commands, Orchestrator Local Gate, and independent Sol xHigh re-review／Acceptance. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reviews this focused-Green uncommitted candidate and authorizes the Full Local Gate; no Commit／Push／CI／Deploy／Release is authorized.

### Historical Thirteenth prefix-option Sol P2 follow-up — focused Green, superseded for Acceptance

The 12:44:53 candidate is superseded by the Full Local Gate false positive in `project-generators.md:25`: Markdown prose `ValueとOutcomeは空の\`readonly\` Classです。` was treated as legacy Shell backtick substitution. The thirteenth correction remains Changes Requested. `shellCommandSubstitutions` now recognizes prose inline-code boundaries only when word-like text surrounds the span without shell assignment／operator or known executable context; executable backticks at command start, assignment, or known Shell command positions remain recursively parsed. The existing outer Docker, protected-context, no-space pipeline, declaration dump, and prefix-option guards remain intact.

Focused evidence is Green: `node --check docs/website/scripts/reader-contract.mjs`, `node --check docs/website/tests/reader-contract.test.mjs`, a direct inline-backtick matrix with 3 negatives／2 positives, a direct Sol P2 prefix matrix with 30 negatives／33 positives, and `node --test --test-isolation=none docs/website/tests/reader-contract.test.mjs` PASS 9/9. The failed command `mise exec -- pnpm --dir docs/website run check` now passes Content validation, diagrams, Blume validation, and Blume check (41 pages, 0 errors／warnings／hints). The actualShikiFixtures array contains 209 entries; 135 thirteenth-correction cases execute across Source, plain Artifact, actual Shiki HTML, and actual-Shiki Artifact (836 four-path executions). Source/test freeze mtimes are `reader-contract.mjs` 12:50:40 JST and `reader-contract.test.mjs` 12:50:40 JST. Full Required Commands remain intentionally deferred; no blocker or external mutation occurred.

Remaining P22-005C steps are complete Required Commands／Orchestrator Local Gate and independent Sol xHigh re-review／Acceptance. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reviews this focused-Green, Changes-Requested candidate and authorizes the full gate only after review; no Commit／Push／CI／Deploy／Release is authorized.

### Sol xHigh ninth-candidate review — P1=0／P2=1／P3=1, tenth correction confirmed

Independent read-only review denied Acceptance. P2 confirms the same structural command-segment root across Bash LGTM parameter operators, Docker global options, unquoted comment termination, and Grafana secret expansion outside pure assignment／Docker env input; all negative and safe-control classes must run through direct Source, plain Artifact, and real-Shiki Artifact paths. P3 identified stale Report suite-count wording; the current suite is 106. Shiki／continuation／pretty-printed `<br>`／entity handling and all prior public-content findings remain closed. Tenth correction, complete post-change Local Gate, final Sol review, and Acceptance sync remain.

### Tenth structural parser correction — historical false-positive evidence
### Sol xHigh tenth-candidate review — P1=0／P2=4／P3=1, eleventh correction requested

Independent Sol xHigh read-only review denied Acceptance for the tenth candidate with P1=0／P2=4／P3=1. P2-1 finds the tokenizer splits atomic braced Bash words at spaces, so unformatted `docker inspect ${LGTM:?LGTM required}` and nested `${LGTM:-${FALLBACK:-fallback container}}` bypass while formatted State／Health controls pass. P2-2 finds recursive shell payloads／wrappers are not analyzed, allowing `sh -c`／`bash -c`, `docker exec ... sh -c`, and `xargs printenv` forms. P2-3 finds Go templates can reference bare `$` or root-derived data outside State／Health. P2-4 finds the test evidence labels a synthetic single `token plain` wrapper as real Shiki rather than exercising actual Shiki output. P3 identified stale Report suite-count wording; the current suite is 106. The tenth Worker／Local Gate Green is historical false-positive evidence and is superseded for Acceptance.

### Historical eleventh consolidated correction — focused Green superseded for Acceptance

The eleventh bounded correction is complete within the existing Files Allowed. `tokenizeShellSegment` keeps braced parameter words atomic across spaces and nested braces; the shared guard recursively analyzes literal `sh`／`bash -c` payloads, combined and value-bearing shell options, Docker exec shell payloads, and xargs environment commands; Go-template actions reject every `$` root or root-derived reference; and every tenth fixture class runs through raw Source, plain Artifact, actual Shiki HTML, and actual-Shiki Artifact validation. Synthetic nested spans remain supplementary and are explicitly labeled synthetic.

The eleventh focused evidence was Green but is historical／superseded for Acceptance after Sol focused review P1=0／P2=4／P3=0. Its 9/9 reader test and 24-case／96-fixture matrix remain regression evidence only; full Required Commands were intentionally not run for that candidate.

Remaining P22-005C steps are the complete Required Commands／Orchestrator Local Gate and independent Sol xHigh re-review／Acceptance. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: after authorization, run the full gate against this exact frozen eleventh candidate.

### Historical Twelfth bounded correction — focused Green superseded for Acceptance

The twelfth correction is complete within the existing Files Allowed. Outer unresolved inspect attribution is fail-closed independently of nested command substitutions while safe formatted inspect calls with unrelated substitutions remain valid. Process substitutions `<(...)`／`>(...)`, literal `eval` payloads, and `sh`／`bash` here-string payloads are recursively analyzed with bounded depth; bare `set`, `export -p`, `declare -p`, `typeset -p`, and `readonly -p` dumps fail closed while `set -Eeuo pipefail` and safe assignment／export inputs pass. Parameter expansion depth increments only for nested `${...}`, so literal `{` remains a valid value while the LGTM target is still rejected unless State／Health formatted.

Focused evidence was Green but is historical／superseded for Acceptance: the twelfth candidate's 11:46 JST Source／test freeze and actual-Shiki four-path matrix passed before the thirteenth review; no Full Required Commands were run for that candidate.

Remaining P22-005C steps are complete Required Commands／Orchestrator Local Gate and independent Sol xHigh re-review／Acceptance. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: after authorization, run the full gate against this exact frozen twelfth candidate.

### Historical Tenth structural parser correction — superseded for Acceptance

The tenth bounded correction is complete within the existing P22-005C Files Allowed. `assertNoUnsafeLgtmDiagnostics` now uses one dependency-free logical Shell segment/token parser for Source and Artifact-visible text: it joins continuations, splits unquoted separators, terminates unquoted comments, parses Docker global options and command substitutions／wrappers, identifies Bash parameter names structurally, validates every LGTM inspect as exactly one State／Health-only format, rejects secret sinks and environment dumps per segment, and fails closed when an inspect cannot be attributed to a parsed executable. Shell redirection remains visible to the parser, while Shiki／entity／`<br>` normalization and prior source／artifact inventory behavior remain intact. The P3 Report sentence is corrected from 105 to 106 tests.

The focused formal matrix covered 52 cases and 120 direct／plain Artifact／real-Shiki fixtures with `failures=[]`. It rejected all parameter-operator／nested-expansion, Docker-global-option, fake-comment, command-segment／wrapper, environment-dump, bare-dot-template, secret-sink, redirection, and unknown-attribution negatives; State／Health controls, safe assignments／Docker env input, literal prompts／comments, quoted Docker paths, and `${LGTM_EXTRA}` identifier-boundary controls passed. After the final Source／test change, every Required Command passed: source guard; Website 106/106; check; complete 42-page build／41-page site check with only existing non-fatal warnings; artifact guard; direct Source `3／18／10／8／1`, 40 pages, Artifact `{"routes":40,"searchRoutes":40}`; Quickstart syntax／full E2E ending `Quickstart consumer E2E passed.`; version syntax／baseline; Mago; PHP management-ID; and `git diff --check`. The initial sandbox Quickstart invocation was blocked only by Docker socket permission; the approved rerun passed. Post-management source guard, version baseline, and diff check also passed. No blocker, Commit, Stage, Push, PR, CI dispatch, Deploy, Release, or external mutation occurred.

Remaining P22-005C steps are the complete Orchestrator Local Gate and independent Sol xHigh re-review／Acceptance. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator runs the complete Local Gate against this exact frozen uncommitted tenth-correction candidate, then requests Sol xHigh review; no Commit／Push／CI／Deploy／Release is authorized.

### Orchestrator ninth-candidate target／Docker-option counterexamples — tenth correction requested

The ninth-correction complete Local Gate is not Acceptance evidence. Independent post-gate direct／Artifact fixtures show unformatted `${LGTM:?message}`, `${LGTM:-fallback}`, and `docker --context default inspect "$LGTM"` forms all pass undetected. The guard recognizes only exact `${LGTM}` and Docker invocations without global options, contradicting the every-LGTM-inspect contract. Sol xHigh is completing a read-only boundary sweep before one consolidated structural Luna Max correction; all post-change Required Commands must then be rerun.

### Historical Orchestrator complete Local Gate — ninth-correction candidate superseded for Acceptance

Orchestrator independently reran the complete Local Gate after the final ninth-correction Source／test change. Release source guard, Website 106/106, check, complete 42-page build／41-page site check, release artifact guard, direct Source `3／18／10／8／1`／40-page and Artifact `40／40` validation, Quickstart syntax／full Consumer E2E, version syntax／baseline, Mago, PHP management-ID, and diff check all passed. An independent matrix rejected six Shiki／raw multiline／pretty-printed `<br>` unsafe forms through both Source and Artifact paths and passed four State／Health controls plus entity decoding. No Source changed afterward. Independent Sol xHigh review／Acceptance remains the only P22-005C step; P22-005D, exact reviewed Commit, and same-SHA delivery remain parent steps.

### Orchestrator eighth-candidate `<br>` boundary counterexample — ninth correction requested

The eighth Worker Green is not Acceptance evidence. Independent direct／Artifact fixtures proved that `<br>` followed by HTML pretty-print newline／indent becomes two physical newlines after normalization. The trailing backslash joins only the synthetic blank line, so pretty-printed `<br>` unformatted and `Config.Env` LGTM commands pass undetected while the State-only control passes. The ninth correction is limited to collapsing formatting whitespace after `<br>` into its one visible boundary, adding direct／Artifact negative and positive fixtures, preserving all Shiki／raw-continuation behavior, and rerunning every Required Command.

### Historical Ninth bounded correction — superseded for Acceptance

The `<br>` boundary bypass is corrected within the existing P22-005C Files Allowed. `normalizeArtifactVisibleText` now consumes formatting whitespace immediately following supported `<br>` tags, including attribute and self-closing forms, so each rendered break becomes one logical newline before Bash continuation joining. Existing Shiki `.line` boundaries, entity decoding, raw continuation behavior, and unrelated physical line boundaries remain intact. Pretty-printed `<br>` unformatted and `Config.Env` fixtures reject through both direct `assertNoUnsafeLgtmDiagnostics` and `assertArtifactReaderText`; the pretty-printed State／Health-only control passes.

After the final Source／test change, every P22-005C Required Command passed: `release:check:source`; Website test 106/106; Website check; complete build with 42 pages and site check for 41 pages (existing non-fatal chunk-size／route-conflict warnings only); `release:check:artifact`; direct Source counts `{"tutorial":3,"how-to":18,"concept":10,"reference":8,"troubleshooting":1}`, 40 pages, and Artifact inventory `{"routes":40,"searchRoutes":40}`; Quickstart syntax and full Consumer E2E ending `Quickstart consumer E2E passed.`; version syntax and full baseline; Mago format; PHP management-ID; and `git diff --check`. The expanded ten-case matrix rejected Shiki unformatted／`Config.Env`, raw multiline unformatted／`Config.Env`, and pretty-printed `<br>` unformatted／`Config.Env`; Shiki, multiline, raw, and pretty-printed State／Health controls passed through both validator paths. No blocker and no Commit／Stage／Push／PR／CI dispatch／Deploy／Release／external mutation occurred. Remaining P22-005C steps are the complete Orchestrator Local Gate and independent Sol xHigh re-review／Acceptance. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator runs the complete Local Gate against this exact uncommitted ninth-correction candidate.

Focused matrix exact result: `[{"name":"shiki-unformatted","direct":false,"artifact":false},{"name":"shiki-config-env","direct":false,"artifact":false},{"name":"multiline-unformatted","direct":false,"artifact":false},{"name":"multiline-config-env","direct":false,"artifact":false},{"name":"pretty-br-unformatted","direct":false,"artifact":false},{"name":"pretty-br-config-env","direct":false,"artifact":false},{"name":"shiki-state-health","direct":true,"artifact":true},{"name":"multiline-state-health","direct":true,"artifact":true},{"name":"pretty-br-state-health","direct":true,"artifact":true},{"name":"raw-safe-state","direct":true,"artifact":true}]`.

After management-document synchronization, the requested read-only reruns also passed: `release:check:source`; `bash -n tests/Consumer/version-baseline.sh` plus `bash tests/Consumer/version-baseline.sh` (`published=1.2.0 historical=1.1.0`); and `git diff --check`.

### Sol xHigh seventh-candidate review — P1=0／P2=1／P3=0, eighth correction requested

The exact seventh-correction candidate is not Acceptance evidence. Independent Sol xHigh review and Orchestrator reproduction confirmed that raw Artifact scanning does not reconstruct real Shiki visible text and physical-line scanning does not join Bash `\` continuations. Shiki-shaped unformatted／`Config.Env` and multiline unformatted／`Config.Env` commands all pass direct and Artifact paths. The eighth correction is limited to dependency-free HTML entity／visible-text normalization with Shiki line preservation, logical-command joining, and full Source／Artifact positive／negative fixtures.

### Eighth bounded correction — historical superseded evidence

The Artifact fail-closed bypass is corrected within the existing P22-005C Files Allowed. `normalizeArtifactVisibleText` decodes the required named／numeric entities, preserves generated Shiki `.line` boundaries and `<br>` breaks, and removes remaining HTML tags before the shared LGTM guard runs. Bash physical lines ending in an unescaped backslash are joined into logical commands before every inspect invocation is parsed, while unrelated line boundaries remain intact. Real Shiki-shaped and plain continuation fixtures now reject unformatted and `Config.Env` LGTM inspection through both direct Source and `assertArtifactReaderText` paths; State／Health-only Shiki, multiline, and raw positives pass. The entity-decoding assertion and focused matrix cover the reported Artifact forms without adding a dependency.

After the final Source／test change, every P22-005C Required Command passed: `release:check:source`; Website test 106/106; Website check; complete build with 42 pages and site check for 41 pages; `release:check:artifact`; direct Source counts `{"tutorial":3,"how-to":18,"concept":10,"reference":8,"troubleshooting":1}`, 40 pages, and Artifact inventory `{"routes":40,"searchRoutes":40}`; Quickstart syntax and full Consumer E2E ending `Quickstart consumer E2E passed.`; version syntax and full baseline; Mago format; PHP management-ID; and `git diff --check`. The focused seven-case matrix rejected Shiki unformatted, Shiki `Config.Env`, multiline unformatted, and multiline `Config.Env`, while Shiki State／Health, multiline State／Health, and raw continuation State positives passed through both validator paths. This eighth-candidate Green is historical and superseded by the ninth correction for Acceptance.

Focused matrix exact result: `[{"name":"shiki-unformatted","direct":false,"artifact":false},{"name":"shiki-config-env","direct":false,"artifact":false},{"name":"multiline-unformatted","direct":false,"artifact":false},{"name":"multiline-config-env","direct":false,"artifact":false},{"name":"shiki-state-health","direct":true,"artifact":true},{"name":"multiline-state-health","direct":true,"artifact":true},{"name":"raw-safe-state","direct":true,"artifact":true}]`.

### Orchestrator sixth-candidate security counterexamples — historical seventh correction request

The sixth Worker Green is not Acceptance evidence. The guard validates only the first inspect／format per line and does not reject direct Grafana password expansion. Pure counterexamples pass for duplicate safe-plus-Config formats, a safe inspect followed by an unformatted inspect, a State format containing `$GRAFANA_PASSWORD`, and `printf`／`echo` password output. Every inspect invocation must be parsed independently, each LGTM inspect must have exactly one State／Health-only format, and output commands must reject secret-variable expansion through Source／Artifact paths. This remains inside the existing Files Allowed.

### Seventh bounded correction — historical superseded evidence

The sixth-candidate multi-invocation and secret-output bypasses are corrected within the existing P22-005C Files Allowed. The shared guard enumerates every `docker inspect`／`docker container inspect` occurrence, bounds each command, requires exactly one `--format` for every `$LGTM`／`${LGTM}` target, and accepts only State／Health fields without Grafana credential identifiers or expansions. `printf`／`echo` credential expansions are rejected while literal configured-password prompts, assignments, and `docker run --env` input remain allowed. Direct Source／Artifact matrix evidence rejects all five reported counterexamples and passes safe multi-inspect HTML, State／Health, prompt, assignment, and env-input positives. All sixth-candidate Green evidence is historical and superseded for Acceptance.

After the final Source／test change, every Required Command passed: `release:check:source`; Website test 106/106; Website check; complete build with 42 pages and site check for 41 pages; `release:check:artifact`; direct Source／Artifact counts `{"tutorial":3,"how-to":18,"concept":10,"reference":8,"troubleshooting":1}`, 40 pages, and `{"routes":40,"searchRoutes":40}`; `bash -n tests/Consumer/quickstart-e2e.sh`; full Quickstart Consumer E2E ending `Quickstart consumer E2E passed.`; `bash -n` and full version baseline; Mago format; PHP management-ID; and `git diff --check`. Orchestrator independently reran this complete Gate on the exact final candidate with the same Green result and no subsequent Source change. No blocker remains. No Commit／Stage／Push／PR／CI dispatch／Deploy／Release／external mutation occurred. Remaining P22-005C step is independent Sol xHigh re-review／Acceptance sync. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Sol xHigh reviews the frozen Working Tree read-only.

### Orchestrator fifth-candidate counterexample — historical sixth one-point request

The fifth Worker Green is not Acceptance evidence. Direct pure invocation shows that unformatted `docker inspect "${LGTM}" >&2` passes the security guard because only unbraced `$LGTM` is recognized. The ordinary braced Bash form must be parsed identically, with Source／Artifact negatives for unformatted／whole-object output and positives only for formatted State／Health fields. This is one bounded parser correction inside the existing Files Allowed; all post-change Required Commands and independent review remain required.

### Sixth bounded correction — historical superseded evidence

The one-point `${LGTM}` parser bypass is corrected within the existing P22-005C Files Allowed. `assertNoUnsafeLgtmDiagnostics` recognizes both ordinary `$LGTM` and braced `${LGTM}` Bash target forms, including `docker container inspect`, and parses the `--format` value before allowing only `.State.Status`／`.State.Health` fields. Unformatted, whole-object, Config／Env, and environment-dump forms fail closed. Direct Source／Artifact fixtures reject braced unformatted／whole-object／Config forms; unbraced State, braced State, and braced Health positives pass through both validator paths. The focused six-case counterexample output was: three negative pairs rejected and three positive pairs passed.

After the final Source／test change, every Required Command passed: `release:check:source`; Website test 106/106; Website check; complete build with 42 pages and site check for 41 pages; `release:check:artifact`; direct Source／Artifact counts `{"tutorial":3,"how-to":18,"concept":10,"reference":8,"troubleshooting":1}`, 40 pages, and `{"routes":40,"searchRoutes":40}`; `bash -n tests/Consumer/quickstart-e2e.sh`; full Quickstart Consumer E2E ending `Quickstart consumer E2E passed.`; `bash -n` and full version baseline; Mago format; PHP management-ID; and `git diff --check`. No blocker remains. No Commit／Stage／Push／PR／CI dispatch／Deploy／Release／external mutation occurred. All fifth-candidate Green evidence is historical and superseded for Acceptance. Remaining P22-005C steps are the complete post-change Orchestrator Local Gate, independent Sol xHigh re-review, and Acceptance sync. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reruns the complete Local Gate against this exact uncommitted sixth-correction candidate.

### Sol xHigh final review — P1=1／P2=2／P3=0, changes requested

The exact fourth-correction candidate is not Acceptance evidence despite its complete Worker／Orchestrator Local Gate Green. Independent read-only Sol xHigh review confirmed one P1 and two P2 residuals within the existing Files Allowed: the Interactive LGTM failure branch leaks the configured Grafana password through unformatted `docker inspect`／`Config.Env`; the Core API validator permits Method Return／Error mappings to be swapped; and Japanese Local／CI Repository-evidence voice remains in Community Board／Testing public prose and passes the current English-focused guard. Luna Max must correct only these three items, add full-path negative fixtures, rerun every Required Command after the final Source change, and return without Commit. The complete post-change Orchestrator Local Gate and independent Sol xHigh re-review／Acceptance remain required.

### Fifth bounded correction — historical superseded evidence

The three Sol findings are corrected within the existing P22-005C Files Allowed. LGTM automatic／Interactive failure paths now emit only explicitly formatted State／Health and a fixed safe startup diagnostic; the shared Source／Artifact guard rejects unformatted LGTM inspect, `Config.Env`, and environment-dump output. The dependency-free sentinel-password shim forces an unhealthy health path and proves stdout／stderr contains no sentinel while State／Health and startup diagnostics remain observable. Core API Return and Error／Safe Code cells are parsed as exact `<br>`-separated Method maps and compared completely to source-derived contracts; missing, duplicate, extra, mismatched, and swapped associations fail closed, including two-method swapped Return／Error fixtures. Community Board／Testing now use Application／Reference Application／Hosted Instance reader boundaries; Japanese Local／CI and Repository-evidence variants fail through Source／Artifact paths while the exact historical Framework Update Consumer sentence remains allowed.

Focused fifth counterexamples pass. After the final Source／test change, every Required Command passed: source guard, Website 106/106, check, complete 42-page build／41-page site check, artifact guard, direct Source／Artifact validation, Quickstart syntax／full Consumer E2E, version syntax／baseline, Mago format, PHP management-ID, and `git diff --check`. Source counts remain `{"tutorial":3,"how-to":18,"concept":10,"reference":8,"troubleshooting":1}` with 40 pages; Artifact inventory remains `{"routes":40,"searchRoutes":40}`. All fourth-correction Green evidence is historical and superseded for Acceptance. No blocker remains; no Commit／Stage／Push／PR／CI dispatch／Deploy／Release／external mutation occurred. Complete post-change Orchestrator Local Gate and independent Sol xHigh re-review／Acceptance remain pending.

### Historical heading-level counterexample — superseded changes requested

The third correction Worker／Orchestrator Green is not Acceptance evidence. Read-only counterexamples proved that wrong-level and artifact-only variants of the historical `Repository main Preview` unit still passed although the preserved Source／generated representation are exact H3. This bounded residual is corrected in the current fourth-correction evidence below; all third-correction Green evidence is historical and superseded for Acceptance.

### Fourth bounded correction — historical reviewed evidence

The exact historical exception now permits only the preserved Markdown `### Repository main Preview`, generated HTML H3 with `id="repository-main-preview"` and exact visible text, exact `href="#repository-main-preview"` anchors, exact Markdown fragment links, and the known generated Search content context. H2／H4, prefix／suffix, wrong IDs, normalized case／spacing／hyphen／HTML-space variants, plain text, Search JSON title drift, and fragment suffix／query／path variants fail closed through Source／Artifact validator paths. The final case-sensitive ID／href／fragment hardening was directly exercised after the prior gate; existing exact Markdown／HTML H3 and exact anchored Search／ToC／LLM representations remain positive.

After the final Source／test change, every Required Command passed: source guard, Website 105/105, check, complete 42-page build／41-page site check, artifact guard, Quickstart syntax／full Consumer E2E, version syntax／baseline, Mago format, PHP management-ID, and `git diff --check`. Direct reader validation passed with Source counts `{"tutorial":3,"how-to":18,"concept":10,"reference":8,"troubleshooting":1}` and Artifact inventory `{"routes":40,"searchRoutes":40}`. Direct counterexamples passed only for exact Markdown／HTML H3／anchor／Search JSON units; all wrong-level, normalized, plain, prefix／suffix, case-variant ID／href, and fragment drift fixtures were rejected. Orchestrator independently reran the same complete Gate against this exact candidate and obtained the same Green result, including 15／15 rejected exact-unit counterexamples, two exact positives, and a rejected wrong-owner reference. No blocker was known at that checkpoint and no Commit／Stage／Push／PR／CI dispatch／Deploy／Release／external mutation occurred. The later P1=1／P2=2 Sol review supersedes this Green result for Acceptance.

### Orchestrator complete Local Gate — historical superseded evidence

Orchestrator independently reran the complete Local Gate against the exact uncommitted third-correction candidate. All listed commands passed, but the fourth-correction counterexamples then exposed a remaining exact-unit guard defect. This gate is historical and superseded; it is not evidence for the current candidate and does not make Sol the sole pending step. The next gate must run against this fourth-correction candidate.

### Second-correction counterexample review — historical superseded status

The second bounded correction Worker Green was not Acceptance evidence. Independent Orchestrator review proved that a semantic topic／recipe reference could point to a non-owner Page and still pass; the optional Interactive LGTM lane could continue without final Grafana health, did not provide the second Terminal with a usable Network／OTLP handoff, and echoed the configured password; and the historical `Repository main Preview` heading exception was not exact against prefix／suffix injection. These three residuals were returned to the same Luna Max worker as the third bounded correction. The second-correction 105/105 and complete Worker gate are retained only as superseded false-positive evidence and were rerun after correction.

### Third bounded correction — historical superseded evidence

The three residuals were corrected within the existing Files Allowed. That candidate passed its Worker and Orchestrator Local Gates, but the fourth-correction heading／artifact counterexamples later exposed a remaining exact-unit guard defect. This section is historical only; its Green evidence is superseded by the fourth-correction evidence above.

The third-correction Required Commands passed before the fourth-correction Source changes. No Commit／Stage／Push／PR／CI dispatch／Deploy／Release／external mutation occurred. Its direct counts and Green result are retained only as superseded historical evidence.

### First-correction counterexample review — historical superseded status

The first bounded correction Local Gate was not Acceptance evidence. Orchestrator review found residual copy-paste and fail-closed defects in Quickstart, Core API, Observability, semantic ownership, public evidence voice, and raw／HTML inventory. Those items were returned to the same Luna Max worker as the second bounded correction. The first-correction 102/102 and complete Worker gate are retained only as superseded false-positive evidence.

### Historical pre-correction review (not reused as acceptance evidence)

Independent Sol xHigh post-implementation Review did not permit Acceptance. The exact pre-correction candidate returned P1=3／P2=5／P3=0: stale visible Stable／main boundaries remained, the Inline Order Operation ID path was not executable, Observability invoked undefined Compose services, the Core API exactness guard missed parameter／Error／enum／constant drift, duplicate owner and all-diagnostic guards were ineffective, public Consumer evidence voice remained, and `llms.txt` ignored extra routes. Its Green commands are retained as historical false-positive evidence and were not reused after correction.

### First bounded-correction evidence (superseded false-positive Green)

At the first bounded-correction checkpoint, the uncommitted candidate classified the 40 non-Landing public pages through `docs/website/content-map.mjs`, with the inventory Tutorial 3、How-to 18、Concept 10、Reference 8、Troubleshooting 1. Later counterexamples superseded that checkpoint and its validator claims; it is retained only as history.

The claims from that checkpoint were not reused for Acceptance. Their subsequent corrections and current evidence are recorded in the third bounded-correction and Orchestrator Local Gate sections above.

### Second bounded correction evidence (superseded false-positive Green)

At the second bounded-correction checkpoint, seven earlier counterexamples had been corrected within the existing Files Allowed. Quickstart and Core API corrections remained valid, while later Orchestrator review found three residual guard／Interactive-lane defects. The third bounded-correction section above is the current evidence; this section is historical only.

The second-correction Required Commands passed at 105/105 with direct Artifact inventory `{"routes":40,"searchRoutes":40}`, but that run predated the three third-correction Source changes and is superseded for Acceptance. The complete third-correction Worker and Orchestrator evidence is recorded above.

## Changed Files

P22-005C changes are within the Task Packet Files Allowed list. The relevant implementation groups are:

- `docs/guide/*.md`: all reader metadata support text, runnable journeys, current Stable claims, Quickstart Journal／inspect path, public Application voice, diagnostic sections, and Reference pages. `core-api.md` includes the checked 216-row exact source-derived lookup index; `attributes.md`, `project-cli.md`, and `configuration.md` include source-derived lookup coverage.
- `docs/website/content-map.mjs`: canonical 40-page reader types, outcomes, roles, and next-page contracts.
- `docs/website/scripts/reader-contract.mjs`: current Source／Artifact reader validator, reusing the existing direct `jsdom` dependency for HTML structural projection alongside source-derived Reference, protected-data, and current main-only guards and fixture helpers.
- `docs/website/scripts/check-content.mjs`, `content-pipeline.mjs`, and `check-site.mjs`: Source／raw／HTML／Search／LLM contract wiring and generated reader-outcome markers.
- `docs/website/tests/reader-contract.test.mjs`, `guide-code.test.mjs`, and `reader-experience.test.mjs`: positive and negative reader, Reference, release-claim, diagnostic, and Quickstart fixtures.
- `docs/website/README.md`: reader-contract and generated-artifact maintenance contract.
- `tests/Consumer/quickstart-e2e.sh`: canonical Journal event sequence, authorized inspect, and masked actor assertions.
- `tests/Consumer/version-baseline.sh`: static recurrence guard for reader validation, protected decode／event names, and main-only fixture coverage.
- `develop/spec/release-authority.json`: the Glossary `Execution Strategy` historical normalized sentence only; Release Authority tuple, capability set, and the other five historical entries are unchanged.
- `docs/website/scripts/reader-contract.mjs`: dependency-free Artifact visible-text normalization and logical Bash-continuation joining now protect the LGTM Source／Artifact guard.
- `docs/website/scripts/reader-contract.mjs`: the shared Artifact normalizer now collapses formatting whitespace immediately after supported `<br>` tags into one visible line boundary.
- `docs/website/tests/reader-contract.test.mjs`: real Shiki, plain, entity, multiline, and pretty-printed `<br>` Source／Artifact positive／negative fixtures cover the ninth correction.
- `develop/orchestration/tasks/P22-005C-documentation-reader-contract-migration.md`, `develop/orchestration/reports/P22-005C-documentation-reader-contract-migration.md`, and `develop/STATE.md`: Thirteenth Prefix-Option Sol P2 Follow-up focused Green／Changes Requested evidence; prior twelfth Green explicitly historical／superseded.

Existing unrelated P22-005A／P22-005B、P20-009H、and P23-001 worktree changes were preserved and were not staged.

## Canonical Reader Inventory and Outcome

The Content Map excludes only Landing `README.md` and validates exactly 40 non-Landing entries. The count is:

| Reader type | Count |
| --- | ---: |
| Tutorial | 3 |
| How-to | 18 |
| Concept | 10 |
| Reference | 8 |
| Troubleshooting | 1 |
| Total | 40 |

Each entry has one unique concrete outcome, type-specific roles, and one or more non-self next-page links to preserved source fragments. The page／outcome inventory is:

| Type | Source | Primary outcome |
| --- | --- | --- |
| concept | `why-blackops.md` | Operation中心モデルがApplicationの同期処理と非同期処理を一貫して追跡できるか判断する。 |
| concept | `core-concepts.md` | Operation、Value、Outcome、Context、Journalの関係を説明し、実装時の境界を選択する。 |
| how-to | `installation.md` | Stable 1.2.0 Skeletonを作成し、認証付きHTTP 200まで確認する。 |
| concept | `directory-structure.md` | Feature、Process、Config、生成物の所有境界をApplicationの配置判断へ変換する。 |
| tutorial | `first-operation.md` | 独自Operationを生成・実装し、HTTP 202受付からWorker完了とTyped Outcome取得まで完走する。 |
| how-to | `runtime-bootstrap.md` | Docker Runtimeを起動し、Migration、HTTP、Workerの最初の応答を検証する。 |
| tutorial | `mvp-sample.md` | InstallからInline、Transaction、Deferred、Worker、Outcomeまで公開StableのJourneyを完走する。 |
| how-to | `operations.md` | Typed self-handled Operationと業務拒否をApplication-owned PHPへ実装する。 |
| how-to | `scheduled-operation.md` | one-shot Scheduleを構成し、実行結果、Misfire、Overlap、Crash Recoveryを観測する。 |
| how-to | `project-generators.md` | Operation、Migration、SeederをCLIで生成し、生成FileとBuild結果を確認する。 |
| how-to | `validation.md` | Value Validationの成功とHTTP 422 Rejectionを実装し、Protocol境界を検証する。 |
| concept | `execution.md` | InlineとDeferredの受付、完了、耐久性の境界をOperation選択へ結び付ける。 |
| how-to | `console-command.md` | OperationをCLIへ公開し、Help、Human／JSON結果、Exit Codeを確認する。 |
| how-to | `authentication.md` | Session Starterを生成し、Register、Login、Logoutと認証境界をHTTPで確認する。 |
| how-to | `authorization.md` | Operation、Deferred、Status Policyを実装し、許可とDenyの結果を検証する。 |
| how-to | `frontend.md` | Clientを生成し、Server-sideからOperationとStatusを呼び出して結果を確認する。 |
| tutorial | `community-board.md` | Reference Applicationを起動し、Authentication、Inline、Deferred、Browser outcomeまで完走する。 |
| how-to | `outbox.md` | Dispatch、Commit、Relay、Worker、Retryを一続きで実行し、at-least-once境界を確認する。 |
| concept | `operation-lifecycle.md` | Lifecycle stateと遷移、不変条件、Terminal Outcomeの意味を説明する。 |
| concept | `execution-context.md` | Operation ID、Actor、Tenant、Attemptの伝播とCorrelation／Causationを追跡する。 |
| reference | `outcome-retrieval.md` | Status、Outcome、404／410とPHP Query契約を必要な時に引く。 |
| concept | `journal.md` | Canonical JournalとObserved Projectionの役割、Event、Replay、Securityの境界を説明する。 |
| concept | `database-and-transactions.md` | Transaction ownership、Nested、After Commit、Outbox保証の関係を選択できる。 |
| how-to | `database-migrations.md` | Migrationをinspect、dry-run、apply、verifyし、Framework／Application所有境界を保つ。 |
| how-to | `database-seeding.md` | Root／child Seederを明示順で適用し、Migration／Build／Seedの結果を安全に確認する。 |
| how-to | `retention.md` | Retentionをplan、dry-run、confirmし、Purge Auditと保留対象を検証する。 |
| concept | `security.md` | FrameworkとApplicationのSecurity責任、非目標、Status／Data境界を判断する。 |
| how-to | `tenant-protection.md` | Tenant Provider、BOPD Protected Schema、認可、Key Rotationを導入し安全性を確認する。 |
| concept | `testing.md` | 変更リスクに合う検証層とnegative pathを選び、Applicationの確認順を設計する。 |
| how-to | `deployment.md` | Build、Migration、配備、Smoke、Shutdown、Rollbackを運用手順として実行する。 |
| reference | `configuration.md` | Config file、key、type、default、errorをApplicationの責務別に正確に調べる。 |
| how-to | `observability.md` | Provider、Health、Collectorを構成し、Trace／Metric／Correlationの観測結果を確認する。 |
| troubleshooting | `troubleshooting.md` | 症状から原因、確認、修正へ進み、Operation、Storage、Observabilityの復旧を完了する。 |
| reference | `application-bootstrap.md` | BuilderとProcess bootstrapの署名、登録順、HTTP／Consoleの失敗境界を引く。 |
| reference | `project-cli.md` | BlackOps CLIのCommand、Option、mutation、output、exit、release laneを調べる。 |
| reference | `core-api.md` | 216 Public API型の署名、default、error、利用箇所をsource-derived lookupで調べる。 |
| reference | `attributes.md` | 25 Public Attributeのtarget、引数、default、制約、典型利用を調べる。 |
| how-to | `observer-replay.md` | Observer Replayをdry-run、confirm、resumeし、AuditとCanonical Journalの安全境界を検証する。 |
| reference | `glossary.md` | BlackOps固有語を正確に定義し、関連する実装と運用行動へ進む。 |
| reference | `mvp-status.md` | 現行Stable、履歴、Capability、制約、Upgrade境界をrelease authorityに照合して調べる。 |

## Decisions and Assumptions

- Content Map is the only reader classification source. No test-local type matrix, slug／section inference, or character-count heuristic remains as a competing authority.
- `reader-contract.mjs` reuses the existing direct `jsdom` dependency for HTML structural projection and exposes the current Content Map／Source／Artifact checks. Source-derived coverage is explicitly awaited by `validateSourceReaderContract`, so a source drift rejection cannot be lost as an unhandled Promise. Generated reader outcomes are emitted as markers so raw Markdown and `llms-full.txt` can be checked against the same Content Map value.
- Reference coverage is source-derived rather than a legend-only check. The guard scans all current `#[PublicApi]` declarations (216 types), 25 Public Attributes, public CLI constants, and configuration keys; it checks one exact nine-column row per source type in `core-api.md`, including full source method parameters／returns／defaults, direct Error／Safe Code evidence, enum backing／cases, public constants, and non-empty Typical Use. The earlier thematic rows are reader-oriented namespace navigation only; they are not a second Source contract. `attributes.md` has one row per 25 source Attributes.
- Source method parameter parsing balances nested parentheses and quoted defaults. A source method with no declared default is represented explicitly as `なし（Defaultなし）`; a method with no declared thrown error is represented explicitly as `宣言なし（ErrorはSource signatureで宣言なし）`, so the Reference does not invent runtime exceptions. Typical Use is retained per source type from the existing public catalog and is required in the exact index.
- The six historical entries remain represented. The five non-Glossary historical sentences are byte-for-byte retained by the release authority guard. The Glossary `Execution Strategy` normalized sentence was changed only as authorized, from a current `main` claim to the Stable `1.2.0` Canonical Authoring comparison.
- The exact `### Repository main Preview` heading, generated exact H3 representation, exact `#repository-main-preview` anchor units, and the `Stable／main境界` historical compatibility anchor remain. Main-only recurrence guards reject wrong levels, normalized variants, plain／JSON／anchor prefix／suffix injection, and fragment suffix／query／path drift while preserving only exact generated forms; current availability forms including `（main）`、`mainでは`、`mainのbuild:compile`、and `main Source` fail closed.
- LGTM failure diagnostics deliberately use only formatted `.State.Status`／`.State.Health.Status` values plus a fixed safe startup diagnostic. The shared guard rejects unformatted `$LGTM` inspection, `Config.Env`, `docker exec ... env`, and password environment dumps; the sentinel shim verifies behavior without pulling an image or contacting a network.
- Core API Return maps omit constructor no-return markers exactly as documented, while every source method with a return and every source method Error／Safe Code has one exact `<br>`-separated Method association. Map size, keys, values, duplicates, extras, missing entries, and swaps are all fail-closed.
- Community Board／Testing use reader-observable Application／Reference Application／Hosted Instance boundaries. Japanese Local／CI and Repository-evidence patterns are rejected in both Source／Artifact validators; the exact historical Framework Update Consumer sentence remains the only allowlisted evidence sentence.
- Quickstart’s protected-data inspection is intentionally limited to an authorized `operation:inspect` path and clear Journal lifecycle metadata. It does not weaken the BOPD boundary or expose raw actor／payload data.
- P22-005D browser visual, keyboard/accessibility tree, responsive, Search UI, and canonical Production verification are not inferred from these local Source／Artifact checks.

## Source／Artifact Positive and Negative Fixtures

The 109-test Website suite and shared validators include these fail-closed cases:

- Content Map positive inventory plus duplicate outcome, missing role, broken next target, and self-link negatives.
- Source positive Journal query and historical anchor; negatives for `convert_from(encoded_record, ...)`, protected JSON casts, `retry.scheduled`, `Stable 1.2.0 is main-only`, exact `（main）`, `mainでは`, `mainのbuild:compile`, and `main Source` forms.
- Artifact positive mapped outcome; negatives for foreign outcome, adjacent outcome injection, protected decode, and stale main-only availability.
- Source-derived Reference positive exact signature／parameter／return／default／Error／Typical Use fixture and negative signature drift fixture.
- Fifth-correction fixtures cover sentinel-password LGTM failure with safe State／Health／startup diagnostics, unformatted inspect／`Config.Env`／env negatives, two-method swapped／missing／duplicate Return／Error maps, and English／Japanese Local／CI／Repository-evidence Source／Artifact negatives. Sixth-correction fixtures cover braced `$LGTM`／`${LGTM}` unformatted／whole-object／Config negatives plus unbraced／braced State／Health positives through direct Source／Artifact paths. Seventh-correction fixtures cover duplicate／later inspect calls, multiple formats, format／output secret expansions, and safe multi-inspect／prompt／assignment／env-input positives.
- Eighth-correction fixtures cover required HTML entity decoding, real Shiki nested `.line` spans with unformatted／`Config.Env` negatives and State／Health positives, raw and Shiki-shaped Bash continuation negatives／positives, and both direct Source／Artifact validator paths.
- Ninth-correction fixtures cover pretty-printed `<br>` unformatted／`Config.Env` negatives, attribute and self-closing break forms, a pretty-printed State／Health positive, and all prior Shiki／raw continuation fixtures through both direct Source／Artifact paths.
- Full Artifact validation positively checks exactly 40 Search routes, raw Markdown descriptions, HTML markers, 40 `llms.txt` routes, and 40 `llms-full.txt` segments; duplicate／missing／foreign route or outcome drift fails closed.
- Correction-specific Reference fixtures reject wrong parameter type／name／order, Error placeholder／safe-code drift, enum backing／case drift, and public constant name／type／value drift; the actual 216-row index is compared against source extraction rather than a legend.
- Ownership／recipe fixtures reject missing or duplicate topic／recipe identities and preserve the full Outbox recipe only in `outbox.md`; Troubleshooting fixtures reject unclassified, symptom-only, and FAQ／group-misclassified H2／H3 headings.
- Source／Artifact fixtures reject stale Stable／main headings and body claims, non-allowlisted Remote／Consumer evidence voice, and unknown Search／`llms.txt` routes. Positive historical coverage retains only the explicit legacy anchors and the exact Framework Update Consumer sentence.
- Quickstart positive evidence selects the latest clear `order.create` Journal receipt and the Consumer exercises the same path; fixed-digest Observability／Troubleshooting lanes have negative fixtures for all four undefined Compose service starts.
- Third-correction fixtures reject a reference role resolving to a non-owner, a non-self owner, a missing owner, duplicate/full duplicate recipes, an Interactive LGTM lane without final status／health or the exact second-Terminal Network／OTLP handoff, password echo, and prefix／suffix `Repository main Preview` heading injection through artifact text validation.
- Fourth-correction fixtures reject Markdown／HTML H2 and H4, wrong HTML IDs, normalized case／spacing／hyphen／HTML-space variants, plain／Search JSON／HTML anchor／Markdown fragment prefix／suffix units, and exact-fragment suffix／query／path drift. Positive fixtures preserve only exact Markdown H3, generated HTML H3, exact anchor／fragment, exact Search JSON title with exact fragment, and the known generated Search context.
- Thirteenth-correction fixtures cover unresolved outer-wrapper／nested-Docker negatives and formatted outer／nested controls, Bash `case`／nested-process protected `env`／`printenv`／secret negatives, brace groups, leading redirection, `builtin` and command-prefix option boundaries, spaced／no-space literal producer-to-shell pipeline negatives and literal-only／lookup controls, bare no-argument／`-p`／option-only declaration dumps, redirection-aware dump negatives, and named declaration／assignment positives through the actual `codeToHtml` Source／plain Artifact／actual Shiki／actual-Shiki Artifact loop.

The correction fixtures now reject parameter type／name drift, enum／constant／runtime Error drift, duplicate topic／recipe owners, an unclassified or misclassified diagnostic, symptom-only diagnostics, stale visible Stable／main wording, and an extra Search／`llms.txt` route. The old test-local `How-to/Tutorial` matrix was removed; Content Map is the only classification authority.

## Stable Release Claim Before／After Classification

Before, current-facing guide prose incorrectly presented Stable `1.2.0` capabilities as Repository `main`-only in Project CLI, Configuration, Testing, Observability, Glossary, Core API, MVP Status, and Start Here content. Quickstart also represented Protected `encoded_record` as UTF-8／JSON and used `retry.scheduled`.

After, current Stable `1.2.0` wording is used for the shipped Surface and the source／artifact release guards reject the former availability phrases. The exact six historical reference slots remain present: two `mvp-status.md` migration records, the `Stable 1.1.0 historical` comparison header, two `observability.md` wire-history records, and the Glossary `Execution Strategy` comparison slot. Only the Glossary normalized sentence and its matching authority entry changed, as authorized; the release tuple, capabilities, since values, and surfaces did not change.

## Historical Ninth-Correction Commands and Results — Superseded by Current Full Local Gate

This entire commands, acceptance, remaining-issues, suggested-action, and release-impact block records the ninth-correction snapshot only. It is historical and superseded for Acceptance; the current report is the Accepted section at the top of this file, where Website test is 109/109, all Required Commands are Green, and final Sol xHigh review is P1=0/P2=0/P3=0.

All Required Commands were run after the ninth-correction final Source／test change. Website build warnings below are existing non-fatal Blume chunk-size／route-conflict warnings; they did not alter the exit status.

| Command | Result |
| --- | --- |
| `mise exec -- pnpm --dir docs/website run release:check:source` | PASS — Release claim source guard |
| `mise exec -- pnpm --dir docs/website run test` | PASS — 106/106 tests |
| `mise exec -- pnpm --dir docs/website run check` | PASS — content, diagrams, Blume validation, and type checks |
| `mise exec -- pnpm --dir docs/website run build` | PASS — complete 42-page build／41-page site check; Artifact guard and site check passed; expected non-fatal warnings only |
| `mise exec -- pnpm --dir docs/website run release:check:artifact` | PASS — generated HTML／Search／raw／LLM release guard |
| Direct `validateSourceReaderContract` invocation | PASS — `{"tutorial":3,"how-to":18,"concept":10,"reference":8,"troubleshooting":1}`, 40 pages; LGTM and Japanese evidence guards included |
| Direct `validateArtifactReaderContract` invocation | PASS — `{"routes":40,"searchRoutes":40}`, LGTM and Japanese evidence guards included |
| Focused fifth-correction counterexample suite | HISTORICAL PASS — sentinel-password forced unhealthy LGTM, safe diagnostics, swapped／missing／duplicate Return／Error maps, and Japanese Source／Artifact evidence negatives |
| Focused sixth-correction direct counterexample | HISTORICAL PASS — braced unformatted／whole-object／Config pairs rejected through direct Source／Artifact paths; unbraced State, braced State, and braced Health pairs passed |
| Focused seventh-correction direct counterexample matrix | HISTORICAL PASS — all five reported negatives rejected through direct Source／Artifact paths; safe multi-inspect HTML, State／Health, literal prompt, assignment, and docker env-input positives passed |
| Focused eighth-correction Shiki／continuation matrix | HISTORICAL PASS — Shiki unformatted／`Config.Env` and multiline unformatted／`Config.Env` negatives rejected; Shiki State／Health, multiline State／Health, and raw continuation State positives passed through direct Source／Artifact paths |
| Focused tenth-correction structural parser matrix | PASS — 52 cases／120 fixtures with `failures=[]`; all parameter, global-option, segment, comment, wrapper, environment-dump, secret-sink, redirection, and unknown-attribution negatives rejected; State／Health and safe input controls passed |
| Focused ninth-correction `<br>`／continuation matrix | HISTORICAL PASS — ten-case matrix rejected Shiki, raw multiline, and pretty-printed `<br>` unformatted／`Config.Env` cases; Shiki, multiline, raw, and pretty-printed State／Health controls passed through direct Source／Artifact paths |
| Orchestrator complete Local Gate on seventh-correction candidate | HISTORICAL PASS — focused security negative 5/5, safe positive 4/4, all Required Commands independently Green before the eighth-correction Source／test change |
| `bash -n tests/Consumer/quickstart-e2e.sh` | PASS |
| `bash tests/Consumer/quickstart-e2e.sh` | PASS — final line `Quickstart consumer E2E passed.`; canonical Journal events, authorized inspect, BOPD non-decode boundary, and masked actors passed |
| `bash -n tests/Consumer/version-baseline.sh` | PASS — no output |
| `bash tests/Consumer/version-baseline.sh` | PASS — `published=1.2.0 historical=1.1.0` |
| `docker compose run --rm app mago format --check src tests` | PASS — all files already formatted |
| `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'` | PASS — no forbidden PHP management references |
| `git diff --check` | PASS |
| Orchestrator complete Local Gate on fourth-correction candidate | HISTORICAL／SUPERSEDED — exact-unit negative 15/15, positive 2/2, wrong owner rejected before later fifth-correction Sol findings |
| Orchestrator complete Local Gate on fourth-correction candidate | SUPERSEDED — passed before fifth-correction Sol P1／P2 counterexamples exposed LGTM secret diagnostics, Method association, and Japanese evidence defects |
| Historical pre-correction Sol xHigh post-implementation Review | FAIL — P1=3／P2=5／P3=0; Acceptance denied (not reused) |
| Independent Sol xHigh fourth-correction Review | FAIL — P1=1／P2=2／P3=0; Acceptance denied; three bounded residuals returned to Luna Max |

## Historical Ninth-Correction Acceptance Criteria — Superseded

- [x] 40-page canonical Content Map classification and exact 3／18／10／8／1 counts.
- [x] Content Map outcome is propagated and checked as the Source／HTML／Search／raw／LLM reader contract.
- [x] Type-specific roles, non-self next-page contracts, role headings, and observable action outcomes are validated.
- [x] Current Stable `1.2.0` main-only claims and former P1 fixture phrases fail closed; the preserved historical heading and exact anchored artifact units reject wrong-level, normalized, prefix／suffix, and fragment drift injection.
- [x] Exact six historical entries are retained; only the authorized Glossary normalized sentence changed and is synchronized with Release Authority.
- [x] Quickstart uses authorized inspect／clear lifecycle metadata, does not decode Protected BOPD data, uses `attempt.retry_scheduled`, and reuses the resolved Order ID without a placeholder.
- [x] Quickstart Consumer executes the exact documented Order operation path and BOPD boundary using `operation_type='order.create'`.
- [x] Public Contributor／Task／Consumer Evidence voice is removed except the exact allowlisted historical Framework Update Consumer sentence; confirmed Remote create-project smoke／CI／E2E variants are rejected in Source／Artifact.
- [x] All Troubleshooting diagnostics carry symptom／cause／verify／fix and every FAQ／group／auxiliary heading is explicitly classified; unclassified／symptom-only／misclassified fixtures fail closed.
- [x] Tutorial／How-to pages carry executable prerequisites and runtime; the Interactive LGTM lane requires final health, usable Network／OTLP handoff, and State／Health-only diagnostics for every LGTM inspect, including Bash parameter-expansion and Docker global-option forms; atomic braced words, recursive shell payloads／wrappers, Go-template roots, protected-context case／process forms, and actual Shiki output are covered by focused Source／Artifact evidence.
- [x] Concept pages carry mental model, invariant／relationship, boundary, and next contracts.
- [x] Reference pages carry one canonical namespace-split exact signature／parameter／return／default／Error／Typical Use catalog plus enum backing／cases and public constants; helper-propagated Error, unrelated-static-call, and swapped／missing／duplicate／extra Method Return／Error fixtures fail closed.
- [x] Source-derived coverage guard checks the complete 216 Public API contract, 25 Public Attributes, CLI, and Configuration with targeted drift fixtures.
- [x] Explicit semantic topic／recipe identities enforce one exact owner per shared identity, require every reference to resolve to that owner, and reject wrong／missing／duplicate owner fixtures.
- [x] Public H1、source filenames、40 slugs、four Redirects、seven Sections／order, Release Authority tuple, capability collection, and historical allowlist remain preserved.
- [ ] Source／Artifact release and reader guards, Website tests, check, complete build／site check, version baseline, Quickstart Consumer, Mago, management-ID, and diff checks must be rerun after the thirteenth correction. (Historical ninth-correction state; superseded by the current Full Local Gate Green evidence above.)
- [ ] Independent Sol xHigh Documentation Review must still return P1=0／P2=0; Worker evidence is not a substitute for the read-only review. (Historical ninth-correction state; superseded by the current Accepted state above.)
- [x] No Commit、Push、PR、CI dispatch、Deploy、Release、or external operation was performed.

## Historical Ninth-Correction Remaining Issues — Superseded

- The tenth and eleventh full／focused Green evidence and the twelfth 11:46 focused Green are historical false-positive evidence and superseded for Acceptance; the current thirteenth focused evidence is recorded above.
- Prior content and parser findings remain implemented. The thirteenth outer-attribution, protected-context, no-space-pipeline, and shell-dump boundaries are focused-tested through Source／plain Artifact／actual Shiki paths.
- Full Required Commands were deferred for the ninth-correction candidate. This statement is historical and superseded by the current Accepted evidence at the top of this Report; P22-005C has no remaining issue.
- P22-005D remains for Browser／Accessibility／Search UI／Production canonical verification after C Acceptance.
- Exact reviewed Commit and same-SHA CI／Documentation delivery remain parent-goal steps and were not authorized in this worker turn.
- No out-of-scope blocker is known; Artifact normalization and logical-command joining fit the existing Files Allowed.

## Historical Ninth-Correction Suggested Next Action — Superseded

Orchestrator: run the complete Local Gate against the exact frozen thirteenth-correction Working Tree, then request independent Sol xHigh final review／Acceptance. This action is historical and superseded; P22-005C is now Accepted and the current next action is P22-005D. Exact reviewed Commit and same-SHA delivery remain later parent steps; no Commit／Push／CI／Deploy／Release is authorized in this worker turn.

## Historical Ninth-Correction Release Documentation Impact — Superseded

- Authority tuple: unchanged current Stable `1.2.0`; Framework／Skeleton refs, Capability IDs, `since`, and `surface` values unchanged.
- Public inventory: 41 Source entries including Landing, 40 non-Landing routes, four Redirects, and seven Sections／order unchanged.
- Version occurrences: current main-only claims corrected; five historical sentences retained exactly, and only the authorized Glossary normalized sentence changed in both body and authority.
- Source／Search／HTML／raw Markdown／LLM artifacts: all 40 mapped outcomes and route sets are checked by the shared validator; positive／negative fixtures are recorded above.
- Delivery: local verification only; no same-SHA CI, Documentation delivery, Production deploy, Release, or external mutation.
- Remaining parent work at that historical snapshot was P22-005C complete Local Gate／Sol re-review／Acceptance, P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. P22-005C is now Accepted; current remaining work is P22-005D, exact reviewed Commit, and same-SHA delivery.
### Historical Twelfth bounded correction request — superseded by focused Green

The twelfth correction request is historical. Its focused Green at the 11:46 JST Source／test freeze was superseded by the thirteenth bounded review; no Full Required Commands or external mutation occurred for that candidate.
