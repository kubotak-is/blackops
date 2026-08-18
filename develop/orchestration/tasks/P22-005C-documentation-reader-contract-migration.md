# P22-005C: Documentation Reader Contract Migration

Status: Accepted — Sol xHigh P1=0／P2=0／P3=0

## Unified HTML State-Scanner Correction — Accepted

2026-08-17T18:38:10+09:00

The 17:41 nested-template candidate is historical and superseded for Acceptance after Sol xHigh found that global comment stripping treated `<!--` inside `script`/`style` raw text as an HTML comment and that SVG/Math CDATA text could contribute a literal `<template>` to template depth. The reproduced dist contains 362 non-map Artifact files.

The bounded correction routes HTML through the existing direct `jsdom` dependency as one standards-aware DOM state scan, with a quote-aware raw-text preflight for malformed `template`/`script`/`style` start tags. DOM parsing now removes comments only in HTML data state, keeps `script`/`style` raw text opaque, excludes inert `template` content with nested depth, and preserves SVG/Math CDATA literals without treating embedded markup as active HTML. Active JSON-LD, visible HTML, metadata, `noscript`, raw Markdown/MDX, Search, LLM, and hashed-asset routing remain fail-closed as specified.

Sol focused review first established P1=0/P2=0 with only management-evidence corrections remaining. The complete Local Gate passed against this exact candidate: source guard PASS; Website test 109/109 PASS; Website check content/diagrams/links/typecheck PASS for 41 pages with 0 errors, warnings, or hints; fresh build PASS for 42 pages; static artifact boundary PASS; site check PASS for 41 pages with only the known non-fatal chunk-size and root route-priority warnings; separate artifact release guard PASS; Quickstart `bash -n` PASS and full Consumer E2E ending `Quickstart consumer E2E passed.`; version `bash -n` PASS and baseline `published=1.2.0 historical=1.1.0`; Mago format PASS for all files; PHP management-ID inverted `rg` PASS; and `git diff --check` PASS. The final consolidated Sol xHigh review accepted this exact snapshot with P1=0/P2=0/P3=0. The focused rerun was performed against the exact current Test SHA/mtime, with no subsequent Source/Test changes. Mechanical counts remain `actualShikiFixtures=216` (864 four-path executions), `tenthFixtures=76`, and CommonMark/Satteri `66`. Source/test final mtimes are `reader-contract.mjs` 17:58:54 JST and `reader-contract.test.mjs` 18:04:40 JST; hashes are `daef847b0c8aa9b8a0ca128135cd6958fd4cd51b9e2c895718c45ffa147133d3` and `d2b9f1f5439cecfa8d49fffef70b62f310d29ecee5f19e1832d721f025eb3a46`. No Commit/Stage/Push/PR/CI/Deploy/Release occurred.

P22-005C is Accepted. Parent steps remain P22-005D Browser/Accessibility/Search/Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: execute P22-005D against this accepted documentation candidate, then prepare the reviewed Commit and same-SHA delivery. No Commit/Stage/Push/CI/Deploy/Release is authorized before those steps.

## Historical HTML Visible-Boundary and Quote-Aware Script Extraction Correction — Superseded for Acceptance

2026-08-17T16:28:23+09:00

The Full Local Gate evidence is historical and superseded for Acceptance: source guard, Website 107/107, Website check, complete build, and static artifact boundary passed, then `site:check` failed on fresh `_astro/base-path.Ds54SYed.js` containing `export{n,t}`. The failure was an Artifact reader-routing defect, not a reader-surface violation: the previous loop sent every non-map Artifact file, including hashed JavaScript, CSS, fonts, images, and XML, through the Shell-only LGTM parser, and three hashed JavaScript files were falsely interpreted as environment dumps. The 46 HTML, 82 raw Markdown/MDX, Search, `llms.txt`, and `llms-full.txt` reader surfaces had zero such violations.

The bounded correction adds format-routed Artifact reader validation in `reader-contract.mjs`. Raw `.md`/`.mdx`, root LLM text, and `blume-search.json` reader strings remain Shell-validated; HTML validation uses visible/body text and explicit metadata plus JSON-LD, while executable `script`/`style` source is excluded except intentional JSON-LD extraction. Generic protected-decode, Stable/main, and internal-evidence guards still run over every Artifact file. Hashed JS containing `export{n,t}` or `set`, arbitrary non-reader text, and HTML inline scripts/styles now pass the reader Shell guard, while unsafe raw Markdown, visible `<pre><code>`, Search, LLM, metadata, and JSON-LD injections fail closed.

Focused evidence after the final Source/test change is Green: Source/test `node --check` PASS; formal `node --test --test-isolation=none docs/website/tests/reader-contract.test.mjs` PASS 11/11; the format-routed reader regression PASS; existing `/tmp/p22-005c-inline-backtick-counterexamples.mjs` PASS; `release:check:source` PASS; Website `check` PASS with 41 pages, 0 errors, 0 warnings, 0 hints; focused `site:check` PASS with 41 pages; and `git diff --check` PASS. Mechanical counts remain `actualShikiFixtures=216` (864 four-path executions), `tenthFixtures=76`, and CommonMark/Satteri `66` fixtures. Source/test final mtimes are `reader-contract.mjs` 16:27:52 JST and `reader-contract.test.mjs` 16:26:25 JST; hashes are `8810551cea6944aaa3eea7176a96a91620d5f7f8af6bd060e81a602b372fc59b` and `8c5d660be632cebfe2101b1f68e23f0502371018dd7acf65c66b3aee990cec06`. Full Gate restart, Consumer gates, and build are intentionally deferred by this bounded correction request.

Remaining P22-005C steps are Orchestrator review/Local Gate authorization, the complete Required Commands against this exact candidate, and independent Sol xHigh re-review/Acceptance. Parent steps remain P22-005D Browser/Accessibility/Search/Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reviews this focused-Green artifact-routing candidate and authorizes the complete Local Gate; no Commit/Stage/Push/CI/Deploy/Release is authorized.

## Historical Thirteenth Nested CommonMark Marker-Chain Follow-up — Superseded for Acceptance

2026-08-17T16:11:29+09:00

The 15:55 context-closure candidate is superseded by this bounded nested CommonMark marker-chain correction. Continuation prose now treats bare `env` and `readonly` as unsafe by default, while allowing only exact English or Japanese technical descriptions from the normalized physical/rendered line start through its end (`Use env as the command name.`, `Use readonly as a PHP keyword.`, and matching command-name/PHP-keyword Japanese wording). Imperative prefixes such as `Execute this now: Use ...` and appended imperative/dump suffixes such as `Then execute it.` fail closed. The full repeated CommonMark container-marker chain (blockquote plus nested unordered/ordered markers in mixed order) is now structurally normalized before the same continuation classifier, so nested `Run env`, `printenv`, and declaration dumps fail closed while nested `Use env as prose` remains safe in Source, plain Artifact, actual Satteri HTML, and actual-Satteri Artifact. English dump/Try phrasing and Japanese execution/command phrasing fail closed; protected identifiers and LGTM/Docker/Grafana diagnostics remain guarded. Executable Shell fences, raw input, and pre/code remain unconditional Shell validation. CommonMark marker padding remains aligned to Satteri: same-line spaces-before-tab are prose for unordered 0–2 and ordered 0–1, and code for unordered 3+ and ordered 2+; blank-line tab continuations remain prose through seven spaces and code at eight.

Focused evidence is Green after the final Source/test change: `node --check` for Source/test PASS; formal reader-contract test PASS 10/10; the existing counterexample matrix PASS with all expected rows; the focused i18n continuation matrix PASS 56/56 (14 fixtures x Source/plain Artifact/actual Satteri HTML/actual-Satteri Artifact); the CommonMark/Satteri marker-chain matrix now covers 66 fixtures with nested unordered/ordered/blockquote env, printenv, declaration-dump negatives and technical-positive controls; `release:check:source` PASS; Website check PASS (41 pages, 0 errors/warnings/hints); `git diff --check` PASS; and the mechanical fixture count reports `actualShikiFixtures=216` (864 four-path executions) and `tenthFixtures=76`. Final Source/test mtimes are reader-contract.mjs 16:10:58 JST and reader-contract.test.mjs 16:10:58 JST. Full Required Commands remain deferred pending Orchestrator authorization; no blocker or external mutation occurred.

Remaining P22-005C steps are the complete Required Commands, Orchestrator Local Gate, and independent Sol xHigh re-review/Acceptance. Remaining parent steps are P22-005D Browser/Accessibility/Search/Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reviews this focused-Green uncommitted i18n candidate and authorizes the Full Local Gate; no Commit/Push/CI/Deploy/Release is authorized.

## Historical Thirteenth Structural Markdown/Artifact Backtick Follow-up — 13:36 Candidate, Superseded for Acceptance

2026-08-17T13:11:54+09:00

The 12:51:40 candidate is superseded by independent counterexamples showing that arbitrary executable names in legacy backtick substitutions could pass through the prose heuristic, while Markdown/PHP inline-code prose could be rejected. The bounded correction removes the executable-name allowlist and separates Markdown source context from executable Shell fenced/indented blocks. Plain and Artifact-visible text now use structural inline-code boundaries: sentence and paired-prose punctuation is masked as prose, while shell assignment/operator, adjacent command-substitution, and command-like unpunctuated spans remain recursively parsed. Artifact inspection preserves the distinction between inline code, language-tagged Shiki Shell blocks, and untagged prose blocks.

The exact `custom-tool prefix `env` suffix`, `/usr/local/bin/custom-tool prefix `readonly` suffix`, and `MODE=check custom-tool prefix `printenv` suffix` counterexamples reject through direct and Shiki-shaped Artifact paths; `result=pre`env`post` and `prefix`env`suffix` are also rejected. Japanese／ASCII／parenthesized prose (`ValueとOutcomeは空の`readonly` Classです。`, `The type is `readonly` Class metadata.`, `Use `readonly`.`, `値は`readonly`。`, `（`readonly`）`) pass. Shell fenced and indented blocks remain executable, while non-Shell fenced blocks and Markdown inline prose are ignored.

Focused evidence is Green: `node --check docs/website/scripts/reader-contract.mjs`, `node --check docs/website/tests/reader-contract.test.mjs`, the exact `/tmp/p22-005c-inline-backtick-counterexamples.mjs` matrix (3 negatives／2 positives across direct and Artifact paths), the extended adjacent/punctuation/context matrix (60/60 expected outcomes across direct／plain Artifact／actual Shiki representations and Source／Artifact validators), `node --test --test-isolation=none docs/website/tests/reader-contract.test.mjs` PASS 10/10, and `mise exec -- pnpm --dir docs/website run check` (41 pages, 0 errors／warnings／hints). The actualShikiFixtures array contains 218 entries; every entry executes Source, plain Artifact, actual Shiki HTML, and actual-Shiki Artifact paths (872 four-path executions), including the nine new exact counterexample/prose cases. Full Required Commands remain intentionally deferred pending review; no blocker or external mutation occurred.

Remaining P22-005C steps are the complete Required Commands／Orchestrator Local Gate and independent Sol xHigh re-review／Acceptance. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reviews this focused-Green, Changes-Requested candidate and authorizes the full gate only after review; no Commit／Push／CI／Deploy／Release is authorized.

## Historical Thirteenth Prefix-Option Sol P2 Follow-up — Focused Green, Superseded for Acceptance

2026-08-17T12:51:40+09:00

The 12:44:53 candidate is superseded by the Full Local Gate false positive in `project-generators.md:25`: Markdown prose `ValueとOutcomeは空の\`readonly\` Classです。` was treated as legacy Shell backtick substitution. The thirteenth correction remains Changes Requested after this bounded guard correction. `shellCommandSubstitutions` now recognizes a prose inline-code boundary only when word-like text surrounds the span without shell assignment／operator or known executable context; executable backticks at command start, assignment, or known Shell command positions remain recursively parsed. The existing masked outer Docker attribution, protected-context preflight, no-space `|` tokenization, declaration dump guard, and prefix-option parser remain intact.

Focused evidence is Green: `node --check docs/website/scripts/reader-contract.mjs`, `node --check docs/website/tests/reader-contract.test.mjs`, the direct inline-backtick matrix (3 negatives／2 positives), the combined prefix matrix (30 negatives／33 positives), and `node --test --test-isolation=none docs/website/tests/reader-contract.test.mjs` PASS 9/9. The failed command `mise exec -- pnpm --dir docs/website run check` now PASSes Content validation, diagrams, Blume validation, and Blume check (41 pages, 0 errors／warnings／hints). The actualShikiFixtures array contains 209 entries; 135 thirteenth-correction cases run Source, plain Artifact, actual Shiki HTML, and actual-Shiki Artifact paths (836 four-path executions), with negatives rejected and safe controls accepted. Source/test freeze mtimes are `reader-contract.mjs` 12:50:40 JST and `reader-contract.test.mjs` 12:50:40 JST. Full Required Commands remain intentionally deferred; no blocker or external mutation occurred.

Remaining P22-005C steps are complete Required Commands／Orchestrator Local Gate and independent Sol xHigh re-review／Acceptance. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reviews this focused-Green, Changes-Requested candidate and authorizes the full gate only after review; no Commit／Push／CI／Deploy／Release is authorized.

## Sol xHigh Ninth-candidate Review — P1=0／P2=1／P3=1, Tenth Correction Confirmed

2026-08-17T10:01:10+09:00

Independent Sol xHigh read-only review denied Acceptance with P1=0／P2=1／P3=1. P2 confirms one structural root: the guard does not split independent Shell command segments, honor unquoted comments, identify Docker inspect after global options, recognize the LGTM parameter identifier across Bash expansion operators, or reject Grafana secret expansion outside the two explicit safe input classes. Direct／plain Artifact／real-Shiki fixtures must reject parameter-expansion, `--context[=]`／`-H`／`--debug`, fake commented format, `cat`／`tee`／`curl`, later-segment, and allowed-input-plus-later-sink cases; safe formatted modifier／global-option inspect, pure assignment, Docker env input, literal prompt, and ordinary comment must pass. P3 is one stale `105-test Website suite` Report statement that must become `106-test`. Shiki／raw continuation／pretty-printed `<br>`／entity behavior is confirmed closed and must remain. All ninth Green evidence is historical for Acceptance after Source changes. Remaining P22-005C steps are the tenth structural correction, complete post-change Local Gate, final Sol review, and Acceptance sync. Remaining parent steps are P22-005D, exact reviewed Commit, and same-SHA delivery. Next Action: Luna Max implements the consolidated command-segment parser and P3 report correction without Commit, then reruns every Required Command.

## Sol xHigh Tenth-candidate Review — P1=0／P2=4／P3=1, Eleventh Correction Requested

2026-08-17T10:48:22+09:00

Independent Sol xHigh read-only review denied Acceptance for the tenth candidate with P1=0／P2=4／P3=1. P2-1 finds the tokenizer splits atomic braced Bash words at spaces, so unformatted `docker inspect ${LGTM:?LGTM required}` and nested `${LGTM:-${FALLBACK:-fallback container}}` bypass while formatted State／Health controls pass. P2-2 finds recursive shell payloads／wrappers are not analyzed, allowing `sh -c`／`bash -c`, `docker exec ... sh -c`, and `xargs printenv` forms. P2-3 finds Go templates can reference bare `$` or root-derived data outside State／Health. P2-4 finds the test evidence labels a synthetic single `token plain` wrapper as real Shiki rather than exercising actual Shiki output. P3 identified stale Report suite-count wording; the current Report states 106 tests. The tenth Worker／Local Gate Green is historical false-positive evidence and is superseded for Acceptance.

## Historical Eleventh Consolidated Structural Parser Correction — Focused Green Superseded for Acceptance

2026-08-17T11:17:18+09:00

The eleventh bounded correction is complete within the existing Files Allowed. `tokenizeShellSegment` keeps braced parameter words atomic across spaces and nested braces; the shared guard recursively analyzes literal `sh`／`bash -c` payloads, combined and value-bearing shell options, Docker exec shell payloads, and xargs environment commands; Go-template actions reject every `$` root or root-derived reference; and every tenth fixture class runs through raw Source, plain Artifact, actual Shiki HTML, and actual-Shiki Artifact validation. Synthetic nested spans remain supplementary and are explicitly labeled synthetic.

The eleventh focused evidence was Green but is historical／superseded for Acceptance after the Sol focused review P1=0／P2=4／P3=0. Its 9/9 reader test and 24-case／96-fixture matrix remain regression evidence only; no full gate was authorized for that candidate.

Remaining: complete Required Commands／Orchestrator Local Gate and independent Sol xHigh re-review／Acceptance; parent P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: after authorization, run the full gate against this exact frozen eleventh candidate.

## Historical Twelfth Bounded Structural Parser Correction — Superseded for Acceptance

2026-08-17T11:41:27+09:00

The twelfth correction is complete within the existing Files Allowed. Outer unresolved inspect attribution is fail-closed independently of nested command substitutions while safe formatted inspect calls with unrelated substitutions remain valid. Process substitutions `<(...)`／`>(...)`, literal `eval` payloads, and `sh`／`bash` here-string payloads are recursively analyzed with bounded depth; bare `set`, `export -p`, `declare -p`, `typeset -p`, and `readonly -p` dumps fail closed while `set -Eeuo pipefail` and safe assignment／export inputs pass. Parameter expansion depth increments only for nested `${...}`, so literal `{` remains a valid value while the LGTM target is still rejected unless State／Health formatted.

Focused evidence was Green but is historical／superseded for Acceptance: the twelfth candidate's 11:46 JST Source／test freeze and actual-Shiki four-path matrix passed before the thirteenth review; no Full Required Commands were run for that candidate.

Remaining: complete Required Commands／Orchestrator Local Gate and independent Sol xHigh re-review／Acceptance; parent P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: after authorization, run the full gate against this exact frozen twelfth candidate.

## Historical Tenth Structural Parser Correction — Superseded for Acceptance

2026-08-17T10:41:03+09:00

The tenth bounded correction is complete within the existing Files Allowed. `assertNoUnsafeLgtmDiagnostics` now uses one dependency-free logical Shell segment/token parser for Source and Artifact-visible text: it joins continuations, splits unquoted separators, terminates unquoted comments, parses Docker global options and command substitutions／wrappers, identifies Bash parameter names structurally, validates every LGTM inspect as exactly one State／Health-only format, rejects secret sinks and environment dumps per segment, and fails closed when an inspect cannot be attributed to a parsed executable. Shell redirection remains visible to the parser, while Shiki／entity／`<br>` normalization and all prior source／artifact inventory behavior remain intact. The P3 Report sentence is corrected from 105 to 106 tests.

The focused formal matrix covered 52 cases and 120 direct／plain Artifact／real-Shiki fixtures with `failures=[]`. It rejected all parameter-operator／nested-expansion, Docker-global-option, fake-comment, command-segment／wrapper, environment-dump, bare-dot-template, secret-sink, redirection, and unknown-attribution negatives; State／Health controls, safe assignments／Docker env input, literal prompts／comments, quoted Docker paths, and `${LGTM_EXTRA}` identifier-boundary controls passed. After the final Source／test change, every Required Command passed: source guard; Website 106/106; check; complete 42-page build／41-page site check with only existing non-fatal warnings; artifact guard; direct Source `3／18／10／8／1`, 40 pages, Artifact `{"routes":40,"searchRoutes":40}`; Quickstart syntax／full E2E ending `Quickstart consumer E2E passed.`; version syntax／baseline; Mago; PHP management-ID; and `git diff --check`. The initial sandbox Quickstart invocation was blocked only by Docker socket permission; the approved rerun passed. Post-management source guard, version baseline, and diff check also passed. No blocker, Commit, Stage, Push, PR, CI dispatch, Deploy, Release, or external mutation occurred.

Remaining P22-005C steps are the complete Orchestrator Local Gate and independent Sol xHigh re-review／Acceptance. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator runs the complete Local Gate against this exact frozen uncommitted tenth-correction candidate, then requests Sol xHigh review; no Commit／Push／CI／Deploy／Release is authorized.

## Orchestrator Ninth-candidate Target／Docker-option Counterexamples — Tenth Correction Required

2026-08-17T09:53:46+09:00

The ninth candidate is not accepted despite its complete Orchestrator Local Gate Green. Independent post-gate counterexamples show the parser recognizes only exact `${LGTM}` and only `docker inspect`／`docker container inspect` without Docker global options: unformatted `docker inspect "${LGTM:?LGTM required}"`, `docker inspect "${LGTM:-fallback}"`, and `docker --context default inspect "$LGTM"` all pass both direct and Artifact paths. This contradicts the checked contract that every LGTM inspect requires exactly one State／Health-only format. Sol xHigh is completing a read-only parser-boundary sweep so the same Luna Max worker can implement one consolidated structural correction rather than more isolated regex patches. All ninth-candidate Green evidence is retained as historical false-positive evidence and must be rerun after the next Source change. No Commit／Stage／Push／PR／CI／Deploy／Release／external mutation occurred. Remaining P22-005C steps are consolidated tenth correction, complete post-change Local Gate, independent Sol xHigh final review, and Acceptance sync. Remaining parent steps are P22-005D, exact reviewed Commit, and same-SHA delivery. Next Action: Sol xHigh returns the complete bounded target／Docker-command finding set; then Luna Max applies one structural correction without Commit.

## Historical Orchestrator Complete Local Gate — Ninth-correction Candidate Superseded for Acceptance

2026-08-17T09:50:01+09:00

Orchestrator independently reran the complete Local Gate against the exact final ninth-correction Working Tree after the last Source／test change. Release source guard, Website 106/106, content／diagram／link／type check, complete 42-page build／41-page site check, release artifact guard, direct Source `3／18／10／8／1`／40-page and Artifact `40／40` validation, Quickstart syntax／full Consumer E2E, version syntax／baseline, Mago, PHP management-ID, and `git diff --check` all passed. The independent normalization matrix rejected Shiki unformatted／`Config.Env`, raw multiline unformatted／`Config.Env`, and pretty-printed `<br>` unformatted／`Config.Env` through both direct and Artifact paths; Shiki State, raw multiline Health, Shiki multiline State, pretty-printed `<br>` State, and entity decode passed. No Source changed afterward. No Commit／Stage／Push／PR／CI／Deploy／Release／external mutation occurred. Remaining P22-005C step is independent Sol xHigh review／Acceptance sync. Remaining parent steps are P22-005D, exact reviewed Commit, and same-SHA delivery. Next Action: Sol xHigh reviews this exact frozen Working Tree read-only.

## Orchestrator Eighth-candidate `<br>` Boundary Counterexample — Ninth Correction Required

2026-08-17T09:32:20+09:00

The eighth Worker Green is not accepted. Independent Orchestrator fixtures confirmed that `<br>` followed by HTML pretty-print whitespace becomes two physical newlines after normalization, although the rendered boundary is one line break. A backslash therefore joins only the synthetic blank line, and `<code>docker inspect \\<br>\n "$LGTM"</code>` plus its `Config.Env` form pass both direct and Artifact paths when they must reject; the State-only control passes. Collapse only formatting whitespace after a `<br>` boundary into that single boundary, add pretty-printed `<br>` unformatted／`Config.Env` negatives and State／Health-only positives through direct and Artifact paths, preserve the Shiki／raw continuation fixes, and rerun every Required Command after the final Source／test change. No Commit／Stage／Push／PR／CI／Deploy／Release／external mutation occurred. Remaining P22-005C steps are this ninth one-point correction, complete post-change Local Gate, independent Sol xHigh review, and Acceptance sync. Remaining parent steps are P22-005D, exact reviewed Commit, and same-SHA delivery. Next Action: the same Luna Max worker corrects only the `<br>` boundary normalization and reruns every Required Command without Commit.

## Historical Ninth Bounded Correction Checkpoint — Superseded for Acceptance

2026-08-17T09:40:59+09:00

The ninth one-point correction is complete within the existing Files Allowed. `normalizeArtifactVisibleText` now collapses formatting whitespace immediately following supported `<br>` tags, including attribute and self-closing forms, into the single visible line boundary before Bash continuation joining. Existing Shiki `.line`, entity, raw continuation, and unrelated physical-line behavior remains covered. Pretty-printed `<br>` unformatted and `Config.Env` fixtures reject through direct Source and `assertArtifactReaderText`; pretty-printed State／Health-only control passes.

After the final Source／test change, every Required Command passed: source guard; Website 106/106; check; complete 42-page build／41-page site check with existing non-fatal chunk／route warnings; artifact guard; direct Source／Artifact counts (`3／18／10／8／1`, 40 pages, `40／40` routes); Quickstart syntax／full Consumer E2E (`Quickstart consumer E2E passed.`); version syntax／baseline; Mago; PHP management-ID; and `git diff --check`. The expanded focused matrix rejected Shiki unformatted／`Config.Env`, raw multiline unformatted／`Config.Env`, pretty-printed `<br>` unformatted／`Config.Env`, and passed Shiki／raw／pretty-printed State／Health controls through both paths. No blocker and no Commit／Stage／Push／PR／CI dispatch／Deploy／Release／external mutation occurred. Remaining P22-005C steps are the complete post-change Orchestrator Local Gate and independent Sol xHigh re-review／Acceptance. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reruns the complete Local Gate against this exact uncommitted ninth-correction candidate.

## Sol xHigh Seventh-candidate Review — P1=0／P2=1／P3=0, Artifact Normalization Required

2026-08-17T09:10:04+09:00

The exact seventh-correction candidate is not accepted despite its complete Worker／Orchestrator Local Gate Green. Independent Sol xHigh review and Orchestrator reproduction confirmed one remaining Artifact fail-closed defect: real Shiki HTML splits `docker inspect --format "$LGTM"` across nested spans, while Bash `\` continuation splits one invocation across physical lines; both bypass the raw line-oriented security guard. Shiki-shaped unformatted／`Config.Env` and multiline unformatted／`Config.Env` fixtures all pass both direct and Artifact paths when they must reject. Normalize Artifact HTML to decoded visible text while preserving Shiki line boundaries, join Bash continuation lines into logical commands before inspect parsing, and add real-Shiki-shaped plus continuation negative／positive fixtures. Keep Source and raw Artifact checks, generated inventory, and all prior guard positives intact. All seventh-candidate Green evidence is superseded for Acceptance after the next Source change and must be rerun. No Commit／Stage／Push／PR／CI／Deploy／Release／external mutation occurred. Remaining P22-005C steps are this eighth bounded guard correction, complete post-change Local Gate, independent Sol xHigh re-review, and Acceptance sync. Remaining parent steps are P22-005D, exact reviewed Commit, and same-SHA delivery. Next Action: the same Luna Max worker corrects only Artifact visible-text normalization and logical-command joining, then reruns every Required Command without Commit.

## Historical Eighth Bounded Correction Checkpoint — Superseded for Acceptance

2026-08-17T09:23:45+09:00

The Artifact fail-closed bypass is corrected within the existing P22-005C Files Allowed. `normalizeArtifactVisibleText` decodes required numeric and named HTML entities, preserves `<br>` and generated Shiki `.line` boundaries (including multi-class／attribute openings), and removes remaining tags before the shared LGTM guard runs. Bash physical lines ending in an unescaped backslash are joined into logical commands while unrelated physical line boundaries remain separate. Real-Shiki nested-span fixtures reject unformatted and `Config.Env` inspect calls, State／Health-only Shiki calls pass, and raw／Shiki multiline unformatted／`Config.Env` negatives reject while multiline State／Health-only positives pass through both direct Source and `assertArtifactReaderText` paths. The exact four reported bypasses are independently rejected and safe Shiki／multiline／raw positives pass.

After the final Source／test change, every Required Command passed: source guard; Website 106/106; check; complete 42-page build／41-page site check; artifact guard; direct Source／Artifact counts (`3／18／10／8／1`, 40 pages, `40／40` routes); Quickstart syntax／full Consumer E2E (`Quickstart consumer E2E passed.`); version syntax／baseline; Mago; PHP management-ID; and `git diff --check`. No blocker remains in this bounded correction. No Commit／Stage／Push／PR／CI dispatch／Deploy／Release／external mutation occurred. Remaining P22-005C steps are the complete post-change Orchestrator Local Gate, independent Sol xHigh re-review, and Acceptance sync. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reruns the complete Local Gate against this exact uncommitted eighth-correction candidate, then requests Sol xHigh re-review.

## Historical Seventh Bounded Correction Checkpoint — Superseded for Acceptance

2026-08-17T08:37:07+09:00

The sixth Worker Green is not accepted. Independent pure invocations prove the line-oriented parser validates only the first inspect／format and misses direct secret output: a safe first `--format` followed by a second `--format '{{json .Config.Env}}'`, a safe inspect followed by an unformatted second inspect on the same line, a State format containing `$GRAFANA_PASSWORD`, and `printf`／`echo` expansion of Grafana password variables all pass undetected. Refactor the guard to validate every `docker [container] inspect` invocation independently, require exactly one State／Health-only format per LGTM invocation, and reject secret-variable expansion in inspect formats or output commands; add Source／Artifact negatives plus current safe positives. All sixth-candidate Green evidence is superseded for Acceptance after the next Source change and must be rerun. No Commit／Stage／Push／PR／CI／Deploy／Release／external mutation occurred. Remaining P22-005C steps are this seventh bounded security hardening, complete post-change Local Gate, independent Sol xHigh re-review, and Acceptance sync. Remaining parent steps are P22-005D, exact reviewed Commit, and same-SHA delivery. Next Action: Luna Max corrects only these guard counterexamples and reruns every Required Command without Commit.

## Historical Seventh Correction Checkpoint — Superseded for Acceptance

2026-08-17T08:54:55+09:00

The sixth-candidate multi-invocation and secret-output bypasses are corrected within the existing P22-005C Files Allowed. The shared guard enumerates every `docker inspect`／`docker container inspect` occurrence, bounds each command, requires exactly one `--format` for every `$LGTM`／`${LGTM}` target, and accepts only State／Health fields without Grafana credential identifiers or expansions. `printf`／`echo` credential expansions are rejected while literal configured-password prompts, assignments, and `docker run --env` input remain allowed. Direct Source／Artifact matrix evidence rejects all five reported counterexamples and passes safe multi-inspect HTML, State／Health, prompt, assignment, and env-input positives. All sixth-candidate Green evidence is historical and superseded for Acceptance.

After the final Source／test change, every Required Command passed: source guard, Website 106/106, check, complete 42-page build／41-page site check, artifact guard, direct Source／Artifact validation (`3／18／10／8／1`, 40 pages, `40／40` routes), Quickstart syntax／full Consumer E2E (`Quickstart consumer E2E passed.`), version syntax／baseline, Mago format, PHP management-ID, and `git diff --check`. No blocker remains in this bounded correction. No Commit／Stage／Push／PR／CI dispatch／Deploy／Release／external mutation occurred. The current-candidate Orchestrator Local Gate is recorded below. Remaining P22-005C step is independent Sol xHigh re-review／Acceptance sync. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Sol xHigh performs the final read-only review of this frozen candidate.

## Orchestrator Complete Local Gate — Historical Seventh-correction Candidate

2026-08-17T09:00:32+09:00

Orchestrator independently reran the complete Local Gate after the final seventh-correction Source／test change. The focused security matrix rejected all five exploit cases and passed four safe cases; source guard, Website 106/106, check, complete 42-page build／41-page site check, artifact guard, direct Source `3／18／10／8／1`／40 pages and Artifact `40／40`, Quickstart syntax／full E2E, version syntax／baseline, Mago format, PHP management-ID, and `git diff --check` all passed. No source changed afterward. No Commit／Stage／Push／PR／CI dispatch／Deploy／Release／external mutation occurred. Remaining P22-005C step is independent Sol xHigh Documentation Review／Acceptance sync. Remaining parent steps are P22-005D, exact reviewed Commit, and same-SHA delivery. Next Action: Sol xHigh reviews this exact fixed Working Tree read-only.

## Orchestrator Fifth-candidate Counterexample — Historical Braced LGTM Target Bypass

2026-08-17T08:23:13+09:00

The fifth Worker Green is not accepted. Independent Orchestrator pure invocation proved that unformatted `docker inspect "${LGTM}" >&2` passes `assertNoUnsafeLgtmDiagnostics`, although the equivalent unbraced target is rejected; safe formatted `State` output with the braced target also passes only because the invocation is not parsed. This leaves the P1 recurrence guard open to ordinary Bash braced-variable syntax. Extend the exact LGTM target matcher to both `$LGTM` and `${LGTM}` forms, including `docker container inspect` if supported by the documented guard; require any matching inspect invocation to use a State／Health-only format and reject unformatted／whole-object／Config／Env output. Add Source／Artifact negatives for braced unformatted and braced whole-object formats plus braced State／Health positives. All fifth-candidate Green evidence is superseded for Acceptance after this Source change and must be rerun. No Commit／Stage／Push／PR／CI／Deploy／Release／external mutation occurred. Remaining P22-005C steps are this sixth one-point Luna Max hardening, complete post-change Local Gate, independent Sol xHigh re-review, and Acceptance sync. Remaining parent steps are P22-005D, exact reviewed Commit, and same-SHA delivery. Next Action: the same Luna Max worker closes only this parser bypass and reruns every Required Command without Commit.

## Historical Sixth Bounded Correction Checkpoint — Superseded for Acceptance

2026-08-17T08:30:49+09:00

The one-point braced-target guard bypass is corrected within the existing P22-005C Files Allowed. `assertNoUnsafeLgtmDiagnostics` now treats `$LGTM` and `${LGTM}` as the same target, covers `docker container inspect`, and requires any matching inspect to use a parsed State／Health-only `--format`; unformatted, whole-object, Config／Env, and environment-dump forms fail closed. Direct Source／Artifact fixtures reject braced unformatted／whole-object／Config forms and preserve unbraced／braced State／Health positives. The focused direct counterexample reports all six cases as expected: three negative pairs reject and three positive pairs pass. All fifth-candidate Green evidence is historical and superseded for Acceptance.

After the final Source／test change, every Required Command passed: source guard, Website 106/106, check, complete 42-page build／41-page site check, artifact guard, direct Source／Artifact validation (`3／18／10／8／1`, 40 pages, `40／40` routes), Quickstart syntax／full Consumer E2E (`Quickstart consumer E2E passed.`), version syntax／baseline, Mago format, PHP management-ID, and `git diff --check`. No blocker remains in this bounded correction. No Commit／Stage／Push／PR／CI dispatch／Deploy／Release／external mutation occurred. Remaining P22-005C steps are the complete post-change Orchestrator Local Gate, independent Sol xHigh re-review, and Acceptance sync. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reruns the complete Local Gate against this exact uncommitted sixth-correction candidate, then requests Sol xHigh re-review.

## Sol xHigh Final Review — P1=1／P2=2／P3=0, Changes Requested

2026-08-17T07:57:14+09:00

The exact uncommitted fourth-correction candidate is not accepted despite its complete Worker／Orchestrator Local Gate Green. Independent read-only Sol xHigh review confirmed three residuals inside the existing Files Allowed scope:

1. The Interactive LGTM failure branch runs unformatted `docker inspect "$LGTM"`, whose `Config.Env` includes `GF_SECURITY_ADMIN_PASSWORD`; this contradicts the public no-password-output contract. Limit diagnostics to explicitly formatted non-secret State／Health fields, forbid unformatted inspect／`Config.Env`, and prove with a non-default sentinel password that failure stdout／stderr／log output does not contain the sentinel.
2. The Core API validator checks Method names and Return／Error tokens independently, so two Method Returns or Errors can be swapped and still pass. Parse `<br>`-separated Method-to-Return and Method-to-Error maps and require exact equality with the Source-derived contract; add two-method swapped Return／Error negative fixtures while retaining all 216-type, helper Error, Enum, and constant positives.
3. `community-board.md` and `testing.md` retain Japanese Local／CI Repository-evidence voice. Rewrite them as reader-observable Application／hosted-instance boundaries, reject the current Japanese phrases and synonymous variants in Source／Artifact paths, and preserve only the authorized historical Framework Update Consumer sentence.

All fourth-correction Green evidence remains valid as historical execution evidence but is superseded for Acceptance after the next Source change and must not be reused. No Commit／Stage／Push／PR／CI dispatch／Deploy／Release／external mutation occurred. Remaining P22-005C steps are the fifth Luna Max bounded correction, complete post-change Local Gate, independent Sol xHigh re-review, and Acceptance sync. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: the same Luna Max worker corrects only these three residuals and reruns every Required Command without Commit.

## Historical Fifth Bounded Correction Checkpoint — Superseded for Acceptance

2026-08-17T08:10:32+09:00

The three Sol findings are corrected within the existing P22-005C Files Allowed. Interactive and automatic LGTM failure paths now emit only explicitly formatted Container State／Health and a fixed safe startup diagnostic; unformatted `docker inspect`, `Config.Env`, and environment-dump patterns are rejected by the shared Source／Artifact guard. A dependency-free sentinel-password shim forces an unhealthy lane and proves captured output contains no sentinel while retaining State／Health and startup diagnostics. The source-derived Core API validator now parses `<br>`-separated Return and Error／Safe Code cells into exact Method-name maps, rejecting missing, duplicate, extra, mismatched, and swapped associations while preserving the 216-type catalog and constructor no-return marker. Community Board and Testing now describe Application／Reference Application／Hosted Instance boundaries without Japanese Local／CI Repository-evidence voice; English／Japanese variants fail through Source and Artifact paths while the exact historical Framework Update Consumer sentence remains allowlisted. Focused fifth counterexamples pass; Website tests pass 106/106; Source／Artifact counts remain `3／18／10／8／1`, 40 pages, and `40／40` routes.

After the final Source／test change, every Required Command passed: source guard, Website 106/106, check, complete 42-page build／41-page site check, artifact guard, direct Source／Artifact validation, Quickstart syntax／full Consumer E2E, version syntax／baseline, Mago format, PHP management-ID, and `git diff --check`. All fourth-correction Green evidence is historical and superseded for Acceptance. No blocker remains in this bounded correction. No Commit／Stage／Push／PR／CI dispatch／Deploy／Release／external mutation occurred. Remaining P22-005C steps are the complete post-change Orchestrator Local Gate, independent Sol xHigh re-review, and Acceptance sync. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reruns the complete Local Gate against this exact uncommitted fifth-correction candidate, then requests Sol xHigh re-review.

## Historical Heading Level Counterexample — Fourth Correction (superseded changes requested)

2026-08-17T07:19:58+09:00

The third correction was not accepted despite its Worker and Orchestrator complete Local Gate PASS. The guard accepted wrong-level and artifact-only variants of the historical `Repository main Preview` unit. This residual was bounded to the existing Files Allowed and corrected below; the third-correction evidence is historical and superseded for Acceptance.

## Fourth Bounded Correction Checkpoint — Historical Reviewed Candidate

2026-08-17T07:42:47+09:00

The exact historical exception now permits only Markdown `### Repository main Preview`, generated HTML H3 with `id="repository-main-preview"` and exact visible text, exact `href="#repository-main-preview"` anchors, exact Markdown fragment links, and the known generated Search content context. H2／H4, prefix／suffix, wrong IDs, normalized case／spacing／hyphen／HTML-space variants, plain text, Search JSON title drift, and fragment suffix／query／path variants fail closed through Source／Artifact validator paths. The final case-sensitive ID／href／fragment hardening was directly exercised after the prior gate; existing exact Markdown／HTML H3 and exact anchored Search／ToC／LLM representations remain positive. Website tests pass 105/105.

After this final Source／test change, every Required Command passed: source guard, Website test, check, complete 42-page build／41-page site check, artifact guard, Quickstart syntax／full Consumer E2E, version syntax／baseline, Mago format, PHP management-ID, and `git diff --check`. Direct reader validation passed with Source counts `{"tutorial":3,"how-to":18,"concept":10,"reference":8,"troubleshooting":1}` and Artifact inventory `{"routes":40,"searchRoutes":40}`. No blocker was known at this checkpoint and no Commit／Stage／Push／PR／CI dispatch／Deploy／Release／external mutation occurred. The current-candidate Orchestrator Local Gate is recorded below. The subsequent Sol xHigh review at the top of this Task denied Acceptance and supersedes this checkpoint's remaining-step statement.

## Orchestrator Complete Local Gate — Historical Fourth-correction Candidate

2026-08-17T07:47:49+09:00

Orchestrator independently reran the complete Local Gate after the final fourth-correction Source／test change. The exact-unit counterexample suite rejected all 15 wrong-level／normalized／plain／prefix／suffix／ID／href／fragment variants while preserving two exact positives and rejecting the wrong semantic owner. Source guard, Website 105/105, check, complete 42-page build／41-page site check, artifact guard, direct Source `3／18／10／8／1` and Artifact `40／40` validation, Quickstart syntax／full Consumer E2E, version syntax／baseline, Mago format, PHP management-ID, and `git diff --check` all passed. No source changed before the Sol review and no Commit／Stage／Push／PR／CI dispatch／Deploy／Release／external mutation occurred. The later P1=1／P2=2 review proves this Local Gate was insufficient for Acceptance; its evidence is historical and must be rerun after the fifth correction.

## Orchestrator Complete Local Gate — Historical Superseded Evidence

2026-08-17T07:14:25+09:00

Orchestrator independently reran the complete Local Gate against the exact uncommitted third-correction candidate. All listed commands passed, but the fourth-correction counterexamples then exposed a remaining exact-unit guard defect. This gate is historical and superseded; it is not evidence for the current candidate and does not make Sol the sole pending step. Remaining P22-005C steps are a new Orchestrator Local Gate and independent Sol xHigh Documentation Review／Acceptance. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reruns the complete Local Gate against the fourth-correction candidate.

## Second Correction Counterexample Review — Changes Requested

2026-08-17T06:56:43+09:00

The second bounded correction is not accepted despite its 105/105 Worker Green. Independent Orchestrator source／guard review found three residual counterexamples inside the existing Files Allowed scope:

1. Semantic topic／recipe references are only required to name an existing Page. A reference can point to a Page that does not own the declared identity and still pass. Collect each identity globally, require exactly one owner whose reference resolves to itself, require every reference role to resolve to that exact owner, and add wrong-owner-reference／non-self-owner／missing-owner／duplicate-owner negative fixtures.
2. The optional Interactive LGTM lane can leave its readiness loop after a port is assigned even when Grafana never becomes healthy, does not hand a usable Docker Network／OTLP endpoint to the second Terminal, and prints the supplied Grafana password. Require a final health assertion, print the exact non-secret Network／OTLP handoff needed by the documented emitter path, and never echo the password.
3. The `Repository main Preview` historical heading exception accepts a prefixed or suffixed current-facing claim. Match only the exact preserved heading and add full-path prefix／suffix negative fixtures.

All second-correction Green evidence is superseded for Acceptance and must be rerun after the final Source change. Luna Max must correct only these residuals, rerun every Required Command, synchronize Task／Report／parent／TODO／STATE, and return without Commit. Orchestrator complete Local Gate and independent Sol xHigh re-review remain required afterward.

## Third Bounded Correction Checkpoint — Correction Complete — Review Pending

2026-08-17T07:05:51+09:00

The three residual counterexamples are corrected within the existing Files Allowed. Semantic topic／recipe declarations are collected globally and now require exactly one self-referencing owner plus owner-targeted references; wrong-owner, non-self-owner, missing-owner, duplicate-owner, and full-duplicate-recipe fixtures fail closed. The Interactive LGTM lane now asserts final Grafana status／health after readiness, prints the exact non-secret Docker Network／`http://collector:4318`／4318 handoff and copy-paste Docker emitter command for the second Terminal, and never prints the configured password. The main-preview guard accepts only exact `### Repository main Preview` (and its generated exact HTML representation), while prefix／suffix fixtures fail through Source／Artifact paths. Every Required Command passed after the final Source change: source guard, Website 105/105, check, 42-page build／41-page site check, artifact guard, Quickstart syntax／full E2E, version syntax／baseline, Mago, PHP management-ID, and diff check. Direct Source counts are `{"tutorial":3,"how-to":18,"concept":10,"reference":8,"troubleshooting":1}`; direct Artifact inventory is `{"routes":40,"searchRoutes":40}`. No blocker remains in this correction scope. No Commit／Stage／Push／PR／CI dispatch／Deploy／Release／external mutation occurred. Orchestrator complete Local Gate and independent Sol xHigh re-review／Acceptance remain pending. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, then exact reviewed Commit and same-SHA delivery. Next Action: Orchestrator reviews this exact uncommitted candidate and requests independent Sol xHigh re-review.

## Orchestrator Counterexample Review

2026-08-17T06:03:14+09:00

The first bounded correction is not accepted despite its Local Gate PASS. Independent Orchestrator source／reader-path review found residual counterexamples inside the existing Files Allowed scope:

1. `mvp-sample.md` documents `ORDER_OPERATION_ID` from clear `operation_type='order.create'` metadata, but then discards it and returns to `OPERATION_ID='<operation-id-from-authorized-status>'` while claiming the HTTP 200 response may provide an ID even though the shown response does not. The terminal inspect step must reuse the documented `ORDER_OPERATION_ID`; the Consumer and negative fixture must remain bound to the same path.
2. `core-api.md` still contains the old per-type four-column catalog and the new per-type nine-column catalog, while `reader-contract.mjs` requires both. This preserves two 216-type lookup authorities instead of one namespace-split canonical index. Replace the duplicate per-type tables with one reader-oriented exact catalog, preserving public headings／fragments.
3. Core API Error extraction is not behaviorally accurate. `Environment::string()`／`int()`／`bool()` call or throw helper-produced `InvalidArgumentException`, yet the generated table states no direct Error. Arbitrary static factory calls such as `AuthenticationResult::anonymous()` are labeled `safe` in the Error／Safe Code column even when they are neither Errors nor safe codes. Derive direct throw expressions and bounded helper-propagated Exceptions accurately; keep safe stable codes in the exact public-constant contract. Add current-source negative fixtures for helper-propagated Error drift and reject unrelated static calls as Error entries.
4. The Observability fixed-digest lanes are not copy-paste complete. The LGTM snippet exits immediately after `docker port`, so its `trap` removes the resources before the documented Explore／Trace／Metric steps; it contains no Emitter／Probe despite prose claiming their output, and the documented `admin/admin` credential disagrees with the shell default `local-admin`. The Collector result commands also rely on `$COLLECTOR` from a blocked or different shell. Provide one internally consistent automatic lane and, if retained, one explicit interactive lane with executable lifecycle, observable signal, failure, isolation, and cleanup.
5. Topic／recipe ownership is synthesized from each route at module load, making every value unique by construction. A duplicated full recipe can keep distinct route-derived IDs and pass. Replace the tautological defaults with explicit semantic topic／recipe identities and owner／reference roles, enforce exactly one owner for each shared identity, and add a full duplicate-recipe counterexample that fails without manually making two route IDs equal.
6. Public release prose still contains non-allowlisted internal evidence voice (`Remote create-project smoke`, `Local／CI only`, `Real Browser E2E`, and `Local／CI Build`), while the guard only rejects the narrower `Remote smoke` spelling. Rewrite current-facing release guidance as user-observable package／application behavior and reject the confirmed variants in Source and Artifact fixtures. Preserve only the exact authorized historical Framework Update Consumer sentence.
7. Artifact inventory must reject an extra raw Markdown／HTML reader route as well as Search／LLM routes. Add a full-path fixture rather than relying on the generator not to emit an extra page.

All first-correction Green evidence is superseded for Acceptance. Luna Max must correct only these items, rerun every Required Command after the final source change, synchronize Task／Report／parent／TODO／STATE, and return without Commit. Orchestrator full Local Gate and independent Sol xHigh re-review remain required afterward.

## Second Bounded Correction Checkpoint — Correction Complete — Review Pending

2026-08-17T06:49:42+09:00

The seven Orchestrator counterexamples are corrected within the existing Files Allowed. Quickstart and its Consumer use one clear `operation_type='order.create'` Journal lookup and reuse `ORDER_OPERATION_ID`; the Core API is one namespace-split nine-column source-derived catalog for all 216 PublicApi types with helper-propagated Error and unrelated-static-call drift fixtures; Observability／Troubleshooting use self-contained fixed-digest automatic／optional interactive lanes; Content Map uses explicit semantic topic／recipe identities with exactly one owner and a full duplicate-recipe negative fixture; non-allowlisted release evidence variants and unknown raw Markdown／HTML／Search／LLM routes fail closed; the test-local reader matrix is gone and `execution.md` links canonical `outbox.md`. All Required Commands were rerun after the final Source change: Website 105/105, source／artifact release guards, check, complete build／site check, Quickstart Consumer, version baseline, Mago, PHP management-ID, and diff checks PASS. Direct artifact validation passes with `{"routes":40,"searchRoutes":40}`. No blocker remains in this correction scope. No Commit／Stage／Push／PR／CI dispatch／Deploy／Release／external mutation occurred. Independent Orchestrator Local Gate and Sol xHigh re-review／Acceptance remain pending. Remaining parent steps are P22-005D Browser／Accessibility／Search／Production verification, then exact reviewed Commit and same-SHA delivery. Next Action: Orchestrator reviews this exact uncommitted candidate and requests independent Sol xHigh re-review.

## First Bounded Correction Checkpoint — Superseded False-Positive Green

2026-08-17T05:53:16+09:00

The Sol xHigh P1=3／P2=5／P3=0 findings were corrected within the existing Files Allowed scope. Current Stable／main visible claims now use precise Stable 1.2.0 wording with explicit historical anchors only; Quickstart and Consumer use the same `operation_type='order.create'` Journal path; Observability／Troubleshooting use self-contained fixed-digest lanes; the Reference index is a source-derived nine-column contract for all 216 PublicApi types with parameter／return／default／Error／enum／constant drift fixtures; Content Map requires unique topic／recipe owners; Troubleshooting H2／H3 classifications are explicit and fail closed; non-allowlisted internal evidence voice and unknown Search／llms routes are rejected; `execution.md` summarizes and links to canonical `outbox.md`; and reader-experience no longer contains a second type matrix. Required Commands, including Quickstart Consumer, pass on the final uncommitted candidate. No blocker remains inside this correction scope. Independent Sol xHigh re-review／Acceptance is still pending.

## Goal

Landingを除く公開40 Pageを、Tutorial、How-to、Concept、Reference、Troubleshootingの単一reader typeへ移行する。各Pageが固有のprimary outcomeとtype別の完結条件を持ち、Stable `1.2.0`の正確なSurface、実行可能な手順、観測可能な成功／失敗、canonical topic owner、次の導線をSource、HTML、Search、raw Markdown、LLM artifactで同じ契約としてfail closedに検証できる状態を完成させる。

## In Scope

- 40 non-Landing public Pageのcanonical reader type、primary outcome、type別role、next-page metadata
- Stable `1.2.0`提供済みSurfaceをmain-onlyと誤表示するcurrent-facing claimの修正と再発防止
- QuickstartのBOPD保護Blobを平文JSONとしてdecodeするSQL、誤ったLifecycle Event名の修正
- Tutorial／How-toのprerequisite、Host／Container、Application-owned File、runnable step、observable success、negative verification、recovery、next-page境界
- Conceptのmental model、invariant、responsibility／non-goal boundary、next-page境界
- Referenceのscope、signature／parameter／return／default／error／typical-use lookup、source-derived coverage、next-page境界
- Troubleshootingの全diagnostic Sectionに対する症状、原因、確認方法、修正方法の完全性
- Public GuideからContributor／Task／Acceptance Evidence voiceを除き、Application利用者が実行できる説明へ置き換える
- Sourceと生成Artifactで同じpure reader-contract validatorを使うpermanent CI guard
- Task／Report／Parent／TODO／STATE／Website README同期

## Out of Scope

- Production PHP、Framework／Skeleton runtime、Example Application behaviorの変更
- Public H1、public slug、Redirect、canonical URL、Sidebar Section／順序の変更
- Landing component、Landing structure、theme、asset、dependencyの変更
- Release Authorityのcurrent Stable、immutable refs、release state、Capability集合／since／surfaceの変更
- BlackOps `1.3.0` Roadmapの公開Documentation化
- Generated Content／`dist`の手編集
- Browser visual、responsive、keyboard、Accessibility tree、Search UI、Production canonical verification（P22-005D）
- Commit、Push、PR、CI dispatch、Deploy、Release、外部操作

## Relevant Specifications

- `develop/decisions/143-documentation-release-truth-and-information-architecture.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/59-documentation-reader-experience.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/spec/92-documentation-review-agent.md`
- `develop/spec/104-documentation-release-lifecycle-and-information-architecture.md`
- `develop/spec/release-authority.json`

Specification 59の旧Stable／main、旧Sidebar、旧Landing presentationはD143／Specification 83／104により置き換えられている。Reader-facing content、runnable journey、Reference、Troubleshooting contractは引き続き適用する。

## Sol xHigh Audit Verdict

Read-only full-source／existing-artifact auditはP1=2／P2=5／P3=0を返した。

## Sol xHigh Post-implementation Review

2026-08-17のexact uncommitted candidateに対する独立Read-only ReviewはP1=3／P2=5／P3=0で、Acceptanceを許可しなかった。Worker／Orchestrator Local GateのGreenは、次の反例を検出できないためCorrection後のEvidenceとして再利用しない。

### P1 Corrections Required

1. `mvp-status.md`と`project-cli.md`の可視`Stableとmain`／`Stable／main境界`／`Stableと main の差`を、実際のStable 1.1 historical／Stable 1.2／Repository Example境界へ直す。旧generated fragmentが必要な場合だけexplicit stable anchorで保持し、可視stale claimはallowlistしない。
2. Quickstart Inline OrderのHTTP 200 Responseと相関するOperation IDを、clear `operation_type`または認可済みPublic Queryで実際に取得するcopy-paste commandを示す。Consumerは先行する別Operationではなく同じOrderを選択する。
3. Observability／Troubleshootingから存在しないCompose service `collector`／`grafana`／`tempo`／`prometheus`の直接起動を除く。既存のself-contained fixed-digest `docker run` laneへ一本化するか、Application-owned Compose定義を完全提示する。

### P2 Corrections Required

1. Core API Referenceはfull Parameter type／name／order／default、runtime Error／Safe Code、enum backing／case、public constant name／type／valueをSource-derivedで比較する。216行の重複catalogを一つのreader-oriented indexへ整理し、wrong parameter／Error placeholder／enum／constant drift fixtureを追加する。
2. 全40 metadataへ必要なtopic／recipe owner identityを宣言し、duplicate owner／full recipe fixtureを有効にする。Concept `execution.md`の完全Outbox recipeはcanonical How-to `outbox.md`へ集約する。
3. Troubleshootingの全headingをdiagnostic／FAQ／group／補助Sectionへ明示分類し、未分類headingと4-part欠落を拒否する。
4. exact historical allowlist以外のRepository Consumer／Remote smoke／root比較／Consumer E2E voiceをApplication-owned guidanceへ置換し、Source／Artifactで再発を拒否する。
5. `llms.txt`の未知extra routeを捨てず即時拒否し、missing／duplicate／extra fixtureを追加する。
6. `reader-experience.test.mjs`の旧test-local `pageTypes`／`How-to/Tutorial` matrixを削除し、Content Mapだけを分類正本にする。

Correctionは既存Files Allowedだけに限定する。Source変更後は全Required Commands、Quickstart Consumer、生成Artifact、独立Sol xHigh再Reviewを最初から実行する。

### P1-01: Stable 1.2.0 Surfaceのmain-only誤表示

次のcurrent-facing表記は、immutable `1.2.0^{}` SourceとRelease Authorityに反する。

- `project-cli.md`: `（main）`、`mainでは`、`Stable／main境界`
- `configuration.md`: Framework Proxy Profileを`main`限定として表示
- `testing.md`: Frontend／Full-stack Browserを`main`限定として表示
- `observability.md`: historical Stable `1.1.0` sentence直後にcurrent SurfaceをRepository `main`限定として表示
- `glossary.md`: `#[Deferred]`をmainのCanonical Authoringとして表示
- `core-api.md`: 216 Public API型をcurrent `main` Sourceとして表示
- `mvp-status.md`: Stable `1.2.0`列で`main build:compile`と表示し、未提示のStable／main差を要求
- `README.md`: Start Here説明がStableとmainの前提を現在Journeyとして混在

`1.2.0^{}`は`3332fd1dd0738fc7e79750facd93d49a59054ecf`であり、tag／currentの`#[PublicApi]` Fileはともに216、`git diff --name-only 1.2.0^{} -- src`は0である。Tagは`#[Deferred]`、Frontend、Scheduled、Outbox、Diagnostics、Storage Protection、Framework Proxy、各CLIを含む。

### P1-02: Quickstart Protected Blob Decode

`mvp-sample.md`は`encoded_record`を`convert_from(..., 'UTF8')`／`::jsonb`として検索し、Raw Actorを読む。Stable `1.2.0` Migrationは先頭4 byte `BOPD`のencrypted Envelopeを強制するため、このSQLは実行不能かつSecurity boundaryに反する。HTTPで得たOperation ID、認可済みStatus／Inspect、または明示的なClear lifecycle metadataだけを使う。期待Event `retry.scheduled`は実装どおり`attempt.retry_scheduled`へ直す。

### P2 Findings

1. test-local `How-to/Tutorial` matrixを廃止し、Content Mapをtype／outcome／role／nextの単一正本にする。
2. `testing.md`、`observability.md`、`community-board.md`、`troubleshooting.md`、`mvp-status.md`からInstalled Applicationに存在しないContributor／Consumer Evidence voiceを除く。
3. Troubleshootingの全diagnostic Sectionを4-part contractへ統一する。FAQ／group headingは明示的に別分類する。
4. Tutorial／How-toの前提、Runtime、File、complete／fragment境界、期待結果、negative path、recoveryをPage固有に補う。
5. Core API 216型、Public Attribute 25件、Configuration、CLI、Bootstrap等を、source-derivedの実用Referenceとしてexact lookup可能にする。

## Canonical Reader Contract

### Metadata

`docs/website/content-map.mjs`を単一正本とし、Landing以外の各entryは次のshapeを持つ。

```js
reader: {
  type: 'tutorial',
  outcome: 'このPage固有の観測可能なreader outcome。',
  roles: {
    prerequisites: ['既存または追加したPage固有heading'],
    runnable: ['Page固有heading'],
    success: ['Page固有heading'],
    failure: ['Page固有heading'],
  },
  next: ['target-source.md#preserved-fragment'],
}
```

- enumは`tutorial`、`how-to`、`concept`、`reference`、`troubleshooting`だけを許可する。
- Landing `README.md`はreader type inventoryから明示的に除外し、既存descriptionを維持する。
- non-Landingの生成descriptionは`reader.outcome`だけを正本とし、旧descriptionとの二重管理を残さない。
- roleはPage固有の既存／追加headingを参照する。共通boilerplate headingへの一括改名、既存fragment破壊、Pipelineによるgeneric本文挿入は禁止する。
- outcomeは全40 Pageで一意かつ具体的にし、placeholder、self-reference、generic boilerplateを拒否する。
- `next`はContent Map内の非self source／preserved fragmentへ解決し、本文の実Linkと一致する。

### Type-specific Roles

- Tutorial: `prerequisites`、連続した`runnable` journey、各主要stepの`success`、少なくとも一つの`failure`／recovery、`next`
- How-to: goal、`prerequisites`、Host／ContainerとApplication-owned File、bounded `runnable`、具体的`success`、negative `failure`／recovery、`next`
- Concept: `mentalModel`、invariant／relationship、Framework／Applicationまたは提供／非提供`boundary`、`next`
- Reference: `scope`、source／release境界、signature／parameter／return／default／error／typical useを引ける`lookup`、提供／非提供`boundary`、`next`
- Troubleshooting: diagnostic Sectionごとの`symptom`、`cause`、`verify`、`fix`、Page全体の`next`。FAQとgroup headingはdiagnosticとして誤列挙しない

Action Pageのrunnable roleはCommandまたは完全なApplication-owned Codeを持つ。意図的fragmentはfragmentと明示し、前提型／File／canonical complete exampleへ接続する。Success／Failure roleはStatus、Exit Code、Response、生成File、State、Error Code等の観測値を持つ。

## Canonical 40-page Assignment

| Type | Source | Primary outcome |
| --- | --- | --- |
| Concept | `why-blackops.md` | Operation中心モデルがApplicationに適合するか判断する |
| How-to | `installation.md` | Stable 1.2.0 Skeletonを作成しHTTP 200まで確認する |
| Tutorial | `mvp-sample.md` | InstallからInline、Transaction、Deferred、Worker、Outcomeまで完走する |
| Tutorial | `first-operation.md` | 独自Operationを生成・実装し202受付からOutcomeまで完走する |
| Concept | `directory-structure.md` | Feature、Process、Config、生成物の所有境界を理解する |
| Concept | `core-concepts.md` | Operation、Value、Outcome、Context、Journalの関係を説明できる |
| How-to | `runtime-bootstrap.md` | Docker Runtimeを起動しHTTPとWorkerを検証する |
| How-to | `operations.md` | Typed self-handled Operationと業務拒否を実装する |
| How-to | `project-generators.md` | Operation、Migration、Seederを安全に生成・確認する |
| How-to | `validation.md` | Validation成功と422 Rejectionを実装・検証する |
| Concept | `execution.md` | InlineとDeferredの受付・完了・耐久性境界を理解する |
| How-to | `console-command.md` | OperationをCLIへ公開しhelp、実行、Exitを確認する |
| How-to | `scheduled-operation.md` | one-shot Scheduleを構成し実行・回復を検証する |
| How-to | `authentication.md` | Session Starterを生成しRegister／Login／Logoutを確認する |
| How-to | `authorization.md` | Operation／Deferred／Status Policyを実装しDenyを検証する |
| How-to | `frontend.md` | Clientを生成しServer-sideからOperationとStatusを呼ぶ |
| Tutorial | `community-board.md` | Reference Applicationを起動しfull-stack journeyを完走する |
| Concept | `operation-lifecycle.md` | Lifecycle stateと遷移・不変条件を理解する |
| Concept | `execution-context.md` | ID、Actor、Tenant、Attemptの伝播を理解する |
| Reference | `outcome-retrieval.md` | Status／Outcome state、404／410、PHP query contractを引く |
| How-to | `outbox.md` | Dispatch、Commit、Relay、Worker、Retryを一続きで検証する |
| Concept | `journal.md` | Canonical JournalとObserved Projectionの役割差を理解する |
| Concept | `database-and-transactions.md` | Transaction ownership、nesting、After Commit、Outbox保証を理解する |
| How-to | `database-migrations.md` | Migrationをinspect、dry-run、apply、verifyする |
| How-to | `database-seeding.md` | Root／child Seederを作り安全な順序で適用する |
| How-to | `retention.md` | Retentionをplan、dry-run、confirmしAuditを検証する |
| Concept | `security.md` | FrameworkとApplicationのSecurity責任／非目標を理解する |
| How-to | `tenant-protection.md` | Tenant Provider、保護Schema、認可、Key Rotationを導入する |
| Reference | `configuration.md` | Config file／key／type／default／errorを正確に調べる |
| How-to | `deployment.md` | Build、Migration、配備、Smoke、Shutdown、Rollbackを実行する |
| How-to | `observability.md` | Provider、Health、Collectorを構成しsignalを検証する |
| Concept | `testing.md` | 変更リスクに合う検証層とnegative pathを選択する |
| Troubleshooting | `troubleshooting.md` | 症状から原因、確認、修正へ進む |
| Reference | `project-cli.md` | Command、option、mutation、output、exit、release laneを調べる |
| Reference | `application-bootstrap.md` | Builder／Process bootstrapの署名、順序、失敗境界を調べる |
| Reference | `core-api.md` | Public APIの型、署名、default、error、利用箇所を調べる |
| Reference | `attributes.md` | 25 Attributeのtarget、引数、default、制約を調べる |
| How-to | `observer-replay.md` | Replayをdry-run、confirm、resumeし安全性を検証する |
| Reference | `glossary.md` | BlackOps固有語の正確な定義と関連行動を調べる |
| Reference | `mvp-status.md` | 現行Stable、履歴、Capability、制約、Upgrade境界を調べる |

Exact countはTutorial 3、How-to 18、Concept 10、Reference 8、Troubleshooting 1とする。

## Implementation Batches

1. C0: canonical inventory、metadata、pure Source／Artifact validator、positive／negative fixturesを先に置く。
2. C1: Start Here 7 Page。Quickstart P1を最優先で修正し、documented clear-metadata／Status／Inspect pathをConsumerで実行する。
3. C2: Build 10 Page。Tutorial／How-toの前提、complete／fragment、success／failure／recoveryを揃える。
4. C3: Async 5 + Data and Security 6 Page。Concept／How-to／Reference ownershipと重複recipeを整理する。
5. C4: Operate 5 Page。Contributor voice、Observability user journey、全Troubleshooting diagnosticを修正する。
6. C5: Reference 6 + Releases 1 Page。source-derived exact lookupとStable release laneを確定する。
7. 全Source変更後に完全Local GateとQuickstart Consumerを一括再実行する。Source変更後にpre-change Green evidenceを再利用しない。

各Batchを別Commitにせず、一つのuncommitted Task PacketとしてWorkerが完了し、Orchestrator／Documentation Reviewerへ返す。

## Files Allowed to Change

- `docs/guide/*.md`
- `docs/website/content-map.mjs`
- `docs/website/scripts/check-content.mjs`
- `docs/website/scripts/content-pipeline.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/scripts/release-claim-checker.mjs`
- `docs/website/scripts/reader-contract.mjs`（new）
- `docs/website/tests/content-pipeline.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/release-claim-checker.test.mjs`
- `docs/website/tests/reader-contract.test.mjs`（new）
- `docs/website/README.md`
- `develop/spec/release-authority.json`（Glossary historical exact sentenceのみ）
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/version-baseline.sh`
- `develop/orchestration/tasks/P22-005-documentation-governance-and-information-architecture.md`
- This Task Packet
- `develop/orchestration/reports/P22-005C-documentation-reader-contract-migration.md`
- `develop/TODO.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げずReportのBlockerとして返す。

## Constraints

- Luna Max Workerが実装し、Commitしない。
- Public H1、40 slug、four Redirect、canonical seven Section／orderを変更しない。
- 既存heading／fragmentは維持する。release-lane headingを正確化する必要がある場合だけ、旧generated fragmentをexplicit stable anchorで保持し、Source／Artifact guardを追加する。
- `mvp-sample.md`の`### Repository main Preview`とgenerated `#repository-main-preview`は歴史的互換anchorとして保持する。
- Release Authority tuple、Capability集合／since／surface、historical reference 6件を維持する。Glossary `Execution Strategy` entryは旧`1.1.0`とcurrent Stable `1.2.0`の正しい比較sentenceへ本文とexactly同期し、category／reasonを変えない。
- `observability.md`のallowlisted `Stable 1.1.0` historical sentence自体は完全保持し、その直後のcurrent laneだけをStable `1.2.0`へ直す。
- current main-only guardはMarkdown Link URL、code `main()`、WebsiteがRepository mainから生成される正当なprovenance、Quickstart互換headingを誤検出しない。`（main）`、`mainでは`、`mainの...`、`main Source`等のavailability claimをSource／Artifact fixtureで拒否する。
- Protected `encoded_record`、payload、context、outcomeをPublic SQLでdecode／JSON castしない。Clear lifecycle columnまたは認可済みPublic Queryだけを使う。
- Public proseで`Task Recipe`、`Framework保守用Evidence`、非allowlist `tests/Consumer/`、Repository checkout前提を利用者手順として表示しない。
- type分類をtest内の第二matrix、slug／Sectionからの推測、文字数だけのheuristicとして重複実装しない。
- Bash Workflowへ自然言語判定を再実装しない。`version-baseline.sh`はpure validator、fixtures、CI wiringを静的guardする。
- 新規Dependencyを追加しない。

## Permanent Guard Contract

新規dependency-free `reader-contract.mjs`へ少なくとも次を集約し、`check-content.mjs`からSource、`check-site.mjs`からArtifactを検証する。

- all 40 non-Landing mapped exactly once、Landing excluded
- missing／unknown／multiple type、wrong exact count、empty／duplicate／placeholder outcomeを拒否
- missing／empty／unknown role、role heading不在、role section空、self／broken nextを拒否
- Action PageのrunnableにCode／Commandなし、success／failureにobservable valueなしを拒否
- Concept／Referenceのboundary欠落を拒否
- all Troubleshooting diagnostic sectionsの4-part欠落を拒否し、FAQ／group headingを識別
- Searchのmissing／duplicate／extra route、別record outcome流用を拒否
- raw Markdownのmissing file／frontmatter outcome／role driftを拒否
- `llms.txt`のmissing／stale outcomeを拒否
- `llms-full.txt`のmissing／duplicate／adjacent segment混入／prefix／suffix／terminal truncationを拒否
- Sourceが正しくArtifactだけdriftしたsynthetic fixtureを拒否
- 216 Public API、25 Public Attribute、CLI／Configuration contractのsource-derived coverage欠落を拒否
- Protected Blob decode、Protected JSON cast、誤Event `retry.scheduled`をSource／Artifactで拒否
- main-only availabilityの旧P1 fixtureをSource／HTML／Search／raw／LLMで拒否

Search、raw、`llms.txt`、`llms-full.txt`の期待route集合はContent Mapから導出し、件数下限や選択8 routeだけでPASSさせない。

## Release Documentation Impact

- Authority tuple: unchanged Stable `1.2.0`; Framework `00e8c587...`／`3332fd1...`; Skeleton `fedcfda5...`／`fa5e8247...`
- Capability ID／surface: unchanged
- Historical reference: exact six entries retained; Glossary comparison sentence only updated in body／allowlist as one unit
- Public inventory: 41 source including Landing、40 non-Landing route、four Redirect、seven Sections unchanged
- Version occurrence: current Stable wording corrected; legitimate Stable `1.1.0` history retained
- Artifact: full HTML、raw Markdown、Search、`llms.txt`、`llms-full.txt` exact inventory and outcome verification required
- Delivery: Local only。Commit／Push／CI／Deploy／Production verificationなし
- Remaining parent work: P22-005C independent review／Acceptance、P22-005D Browser／Accessibility／Search／Production、exact reviewed Commit and same-SHA delivery

## Acceptance Criteria

- [x] 40 Pageがcanonical tableどおりexactly once分類され、countが3／18／10／8／1である
- [x] Content Mapのreader outcomeがSource／HTML metadata／Search／raw／LLM artifactの単一正本である
- [x] 全Pageがtype別roleと非self next-page contractを満たす
- [x] Stable `1.2.0` Surfaceのcurrent-facing main-only誤表示が0で、旧P1 positive／negative Source／Artifact fixtureがfail closedである
- [x] historical referenceはexact six entriesを維持し、Glossary sentenceだけ正しいStable 1.1／1.2比較へ同期する
- [x] QuickstartがProtected Blobをdecode／JSON castせず、clear lifecycle／authorized queryで完走し、`attempt.retry_scheduled`を使う
- [x] Quickstart Consumerが文書化した取得経路とBOPD非decode境界を実行検証する
- [x] Public proseのContributor／Task／Consumer Evidence voiceが除かれ、Application利用者の手順へ置換される
- [x] Interactive LGTM failure diagnostics are State／Health-only, every LGTM inspect—including Bash parameter-expansion and Docker global-option forms—has exactly one safe format, secret expansions／output are rejected, recursive shell payloads／wrappers and atomic braced words are parsed, Go-template roots are rejected, actual Shiki output is exercised, and all Source／Artifact fixtures fail closed; Community Board／Testing Japanese Local／CI variants are rejected while the exact historical Framework Update Consumer sentence remains allowed
- [x] 全Troubleshooting diagnosticが症状／原因／確認／修正を持ち、FAQ／group headingは別分類される
- [x] Tutorial／How-toがprerequisite、Runtime、File、runnable、observable success、negative verification、recovery、nextを持つ
- [x] Conceptがmental model／invariant／boundary／nextを持つ
- [x] Referenceがscope、signature／parameter／return／default／error／typical use、boundary、nextを引け、Return／ErrorのMethod association swap／missing／duplicate／extra driftがfail closedである
- [x] 216 Public API、25 Public Attribute、CLI／Configurationのsource-derived coverage欠落がfixtureで失敗する
- [x] duplicate topic owner／duplicated full recipeをtargeted fixtureで拒否し、全referenceがexact ownerへ解決する
- [x] H1、source filename、40 slug、four Redirect、seven Section／order、Release Authority tuple／Capability集合がbefore／after同一である
- [x] Source／Artifact release guard、Website test 109／109、check、complete 42-page build／static artifact boundary／41-page site checkが第十三補正の全Source／test変更後にPASSする
- [x] version baseline、Quickstart Consumer、Mago、PHP management-ID、diff checkが第十三補正後にPASSする
- [x] Sol xHigh Documentation Reviewが全40 PageでP1=0／P2=0となる（final P1=0／P2=0／P3=0）
- [x] Commit、Push、PR、CI dispatch、Deploy、Release、外部操作を行わない

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run release:check:source
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
mise exec -- pnpm --dir docs/website run release:check:artifact
bash -n tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/quickstart-e2e.sh
bash -n tests/Consumer/version-baseline.sh
bash tests/Consumer/version-baseline.sh
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P22-005C-documentation-reader-contract-migration.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Canonical Reader Inventory and Outcome
- Decisions and Assumptions
- Commands and Results
- Source／Artifact positive and negative fixtures
- Stable release claim before／after classification
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

完了時はP22-005上位Goalの残り工程とNext Actionを明示する。
## Sol xHigh Eleventh Focused Review — P1=0／P2=4／P3=0, Twelfth Correction Requested

2026-08-17T11:26:19+09:00

Independent focused review denied Acceptance. The remaining bounded defects are unresolved outer inspect attribution even when nested substitutions exist, missing process-substitution／literal-eval／shell here-string recursive payload analysis and shell environment-dump rejection, and parameter-brace depth that treats literal `{` as nested structure. The twelfth correction is limited to dependency-free structural parsing and four-path Source／plain Artifact／actual Shiki fixtures; full Required Commands remain forbidden until focused Green.
