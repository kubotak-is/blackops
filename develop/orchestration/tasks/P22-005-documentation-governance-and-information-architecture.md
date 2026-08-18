# P22-005: Documentation Governance and Information Architecture

Status: Accepted

## Goal

公開済みStable `1.2.0`の正確な利用者向けDocumentationを復旧し、今後のRelease／Capability更新が全公開Page、Search、LLM artifact、CI、Production Websiteへ一貫して反映される情報設計と運用契約を完成させる。

## In Scope

- Release Authorityとhistorical allowlist
- Source／Artifact共有release claim guard
- Stable `1.2.0` current-facing stale claim修正
- Landing、Sidebar、Content Map、Search導線の新IA
- 全公開PageのReader Contract分類
- Task／Report／Documentation Reviewのrelease inventory契約
- Browser／Accessibility／Production verification

## Out of Scope

- Framework／Skeleton公開済みTagの変更
- Packagist／GitHub Releaseの再発行
- BlackOps `1.3.0` CapabilityのProduction実装
- Version archive／selectorの実装
- Custom Domain

## Child Tasks

1. `P22-005A`: Release claim authority and current-source correction (Accepted; final Documentation Review P1=0／P2=0／P3=0)
2. `P22-005B`: Information architecture and navigation (Accepted; final Sol xHigh re-review P1=0／P2=0／P3=0)
3. `P22-005C`: Full public-page content migration (Accepted; final Sol xHigh review P1=0／P2=0／P3=0)
4. `P22-005D`: Browser, accessibility, Search, and production verification (Accepted — Production Verified; current Production Browser 127/127 Green; final Sol xHigh P1=0／P2=0／P3=0)
5. `P22-005E`: Product framing, BlackOps CLI chapter, Journal／Audit boundary, and Landing visual follow-up (Accepted; final Sol xHigh P1=0／P2=0／P3=0; Local Acceptance supported)
6. `P22-005F`: Documentation clean-checkout artifact fixture (Accepted; final Sol xHigh P1=0／P2=0／P3=0; no public Artifact or workflow change)
7. `P22-005G`: Documentation production closeout (Accepted; management-only PR #10 merge／Production HTTP／Artifact／Browser evidence Green; final Sol xHigh P1=0／P2=0／P3=0)

## P22-005G Documentation Production Closeout — Accepted; Sol xHigh P1=0／P2=0／P3=0

2026-08-18T20:14:56+09:00

P22-005G records the user-approved PR #10 head `76aa6218ee80f6eaf7ae44cd6d4a0db215a6f1de`, PR CI `32117940838` (6 jobs) and Documentation `32117940853`, merge `36d3206c37e33165b89b78b4eb333562e9d37b61` at `2026-08-18T08:51:06Z`, main CI `32118499805` (6 jobs), main Documentation `32118499737`, and production job `95653679057`. The deployment preview is `https://e6391b48.blackops-php.pages.dev`, canonical is `https://blackops-php.pages.dev`, and Artifact id `9317633470` is `blackops-documentation-site`.

Orchestrator-provided Production HTTP evidence measured 200 for `/`, `/reference/project-cli/`, `/blume-search.json`, `/llms.txt`, `/llms-full.txt`, and `/index.md`, with the required content types, root `Link` relations, and four 301 redirects. Production matched Artifact for index/CLI after only Cloudflare Analytics injection normalization; Search／llms／llms-full／index.md were byte exact. Artifact hashes are index `0b5c18d16553e7bdf3892e492db95af3a546a845e4e24097d8c89f7eba257b34`, CLI `49ca6f5054a28a6c7903f445a2cf07b159665b32630d519628eb077a7a7cbb26`, Search `dd1968391b3178932b4a1ee4fccb468d2d715222b23d614b0b43d1afabb36fe5`, `llms.txt` `9df281c58d889c6719d36a78a6e48f131b4302ce6787f94b366e6fc312669eec`, `llms-full.txt` `60c8dca85c861f40c262d45eb0c996234eb89aeef1e58b67a1b904d7ef54fd11`, and `index.md` `d9654654a3d2be50001c775d6af91c3d9c0f18328f84b871c6a6ba0a666b0853`.

The parent Production HTTP／Artifact criterion and P22-005D current Production Browser confirmation are satisfied. Orchestrator evidence at `/tmp/p22-005d-orchestrator/evidence-production-canonical/` covers 41 canonical routes and 127 executions: desktop-light 41, desktop-dark 41, mobile-light 41, mobile-dark representative 4; failures empty; Axe violations 0; console/page/request 0; horizontal-overflow failures 0; accessibility-name entries 127; Search empty/non-empty close and trigger focus return, tabCount 4, theme persistence, and reduced-motion checks Green. Browser is Chromium 149.0.7827.55, Playwright 1.61.1, Node v24.17.0, image `mcr.microsoft.com/playwright:v1.61.1-noble`. Exact main Artifact index hash is `0b5c18d16553e7bdf3892e492db95af3a546a845e4e24097d8c89f7eba257b34` (size 21093) and Search hash is `dd1968391b3178932b4a1ee4fccb468d2d715222b23d614b0b43d1afabb36fe5` (size 502738). Retained P22-005F／D 127/127 same-source candidate evidence remains separately identified. Sol xHigh final verdict is `P1=0/P2=0/P3=0` with no findings and supports parent acceptance. Remaining Issues: none. Next Action: Orchestrator publication workflow records the exact commit／PR CI／merge snapshot and authorized publication gates; after parent closeout, proceed to the P23-001 feasibility proposal. BlackOps `1.3.0` remains unreleased.

## P22-005F Clean-Checkout Artifact Fixture — Accepted; Sol xHigh P1=0／P2=0／P3=0

2026-08-18T17:26:05+09:00

PR #10 CI runs `32088975752` / Job `95567261225` and `32088975758` / Job `95567261797` failed at `reader-contract.test.mjs:851` (117/118) because the HTML injection test copied ignored local `docs/website/dist` before validation. P22-005F removed that dependency in `docs/website/tests/reader-contract.test.mjs`, generated a synthetic complete 40-page artifact from `contentMap` excluding `README.md`, passed the baseline full artifact validator, and preserved all 37 HTML active/inert/malformed cases. Independent evidence is focused 1/1 and full 118/118 with real `dist` unavailable and restored by trap; source/check/build/site/artifact/public-boundary/diff all PASS. Sol xHigh final read-only verdict is P1=0／P2=0／P3=0 and supports Local Acceptance. Accepted current Artifact hashes are index `1f0128c49c4908f798dfa4fcd8c302dbe2a893c2eeffee922e9347ec3b1d47ef` and Search `dd1968391b3178932b4a1ee4fccb468d2d715222b23d614b0b43d1afabb36fe5`.
Browser, Quickstart, Mago, and PHP management-ID verification were Not Run because this correction changed only website tests and management documents; public Source/Artifact and visual routes are unchanged. P22-005F Remaining Issues: none. Parent remaining is reviewed exact Commit, same-SHA CI/Documentation delivery, Production canonical verification, and parent closeout. Next Action: fix the exact accepted snapshot to the reviewed exact Commit and proceed to remote gates. No Commit/Stage/Push/CI rerun/Merge/Deploy/Production mutation was performed here.

## P22-005E Final Sol xHigh Acceptance — Accepted

2026-08-18T03:02:48+09:00

Final Sol xHigh read-only verdictはP1=0／P2=0／P3=0で、P22-005E Local Acceptanceを支持した。P22-005EのTask／Report／STATE／TODO／parent current statusをAccepted／完了へ同期した。このfinal management-only closeoutでは管理文書だけを変更し、公開Guide／PHP／API／Quickstart／visual、website scripts／tests、dist、Production Codeは変更していない。

P22-005E Local evidenceはWebsite 118／118、check 0／0／0、fresh 42-page build／41-page site、Release Source／Artifact、version baseline、Quickstart Consumer E2E、Mago、PHP management-ID、diff check全PASS。既存Browser evidenceは`/tmp/p22-005d-orchestrator/evidence-p22-005e-final-truth-correction`に保持し、41 canonical routes／127 executions、failure 0、Axe 0、console／page／request 0、overflow 0、Search focus return／theme persistence／reduced motion PASS、hash index `1f0128c49c4908f798dfa4fcd8c302dbe2a893c2eeffee922e9347ec3b1d47ef`／Search `dd1968391b3178932b4a1ee4fccb468d2d715222b23d614b0b43d1afabb36fe5`を確認済みとする。公開Artifact不変のためBrowserは再実行していない。

Post-sync Sol xHigh reviewはP1=0／P2=1／P3=0で、唯一のP2はこのParent Acceptance Criteriaの未同期だった。独立Documentation Review完了済みのChild A〜Eと、各Child ReportのRemaining Issues／Suggested Next Actionを確認して該当criteriaを[x]へ補正した。Production WebsiteとSearch／LLM artifactのcanonical HTTP／redirect／header verificationは未実施のため[ ]を維持する。

P22-005Eの残りはなく、上位parent P22-005の残り工程はreviewed exact Commit、same-SHA CI／Documentation delivery、authorized Production canonical verification、parent closeoutである。Commit／Stage／Push／CI／Deploy／Production mutationは未実施・未許可。Next Action: OrchestratorがAccepted exact snapshotをreviewed exact Commitへ固定する。

## Historical P22-005E Final Sol xHigh P2×2 Bounded Correction — Changes Requested

2026-08-18T02:43:53+09:00

The latest Sol xHigh read-only review returned P1=0／P2=2／P3=0. P2-1 required the shared guard to reject duplicate `Relevant Specifications` headings and missing P22-005E authority paths, rather than reading only a hard-coded correct Specification 83 file. P2-2 required the Report's old Browser finding to be explicitly Historical／Superseded and its old current-tense must-run／pending wording to be reconciled with the later fulfilled evidence.

The bounded correction now passes Task text and a real Repository file inventory to both Source／Artifact guards. It requires exactly one `Relevant Specifications` heading, validates every relative path listed there as safe, unique, and present, requires D143 plus Specs 57／59／83／92／100／104, and rejects both an existing Spec 84 replacement and a second heading in Source／Artifact negative fixtures. The Report now labels the prior Browser finding Historical／Superseded and records the later fulfilled Browser evidence without a current rerun mandate. The implementation changed only the allowed guard／test and management files; public Guide／PHP／API／Quickstart／visual／Artifact content is unchanged. Current evidence is Website 118／118, check 0／0／0, fresh 42-page build／41-page site, Release Source／Artifact, version baseline, Quickstart Consumer E2E, Mago, PHP management-ID, and diff all PASS.

The retained Browser evidence at `/tmp/p22-005d-orchestrator/evidence-p22-005e-final-truth-correction` remains 41 canonical routes／127 executions, failure 0, Axe 0, console／page／request errors 0, and overflow 0, with Search focus return／theme persistence／reduced motion passing. Served hashes are index `1f0128c49c4908f798dfa4fcd8c302dbe2a893c2eeffee922e9347ec3b1d47ef` and Search `dd1968391b3178932b4a1ee4fccb468d2d715222b23d614b0b43d1afabb36fe5`. Browser is not rerun because public Source／Artifact content is unchanged. Latest Sol remains P2=2, so P22-005E is not accepted. No Commit／Push／CI／Deploy／Production mutation is authorized. Remaining parent steps are independent Sol re-review／Acceptance, reviewed exact Commit／same-SHA delivery, and authorized Production canonical verification. Next Action: Orchestrator requests the bounded correction's independent Sol xHigh re-review.

## P22-005D Browser／Accessibility／Search Local Verification — Accepted

2026-08-17T22:51:20+09:00

The bounded Luna Max corrections implemented canonical Releases Search taxonomy, Landing H1 word boundary, explicit detail-layout theme ownership, per-HTML linked-CSS validation, four measured contrast fixes, Mobile local-scroller focusability, and the shared Search focus boundary. The version baseline now asserts the actual typed Blume `flat` source expression without weakening the Releases negative fixtures.

The complete Orchestrator Local Gate is Green: release source／artifact guards, Website 112／112, check 0／0／0, fresh 42-page build／41-page site check, version syntax／baseline, Mago, PHP management-ID scan, and diff check. The final fresh Artifact hashes match the corrected Browser evidence. The external harness completed 127／127 executions across all 41 routes in Desktop Light／Dark and Mobile Light plus four Mobile Dark routes, with failure 0, Axe critical／serious 0, and both empty／non-empty Search returning focus on the first Escape. Final Sol xHigh read-only review returned P1=0／P2=0／P3=0 and supported Local Acceptance.

P22-005D Local verification is Accepted. Remaining parent steps are P22-005E product framing／CLI／Journal-Audit／Landing visual follow-up, reviewed exact Commit and same-SHA CI／Documentation delivery, then authorized Production canonical verification. No Commit／Push／CI／Deploy／Production mutation is authorized yet. Next Action: Orchestrator starts the P22-005E Task Packet.

## P22-005C Unified HTML State-Scanner Correction — Accepted

2026-08-17T18:38:10+09:00

The 17:41 nested-template candidate is historical/superseded after Sol xHigh found that global comment stripping treated `<!--` inside `script`/`style` raw text as an HTML comment and that SVG/Math CDATA text could contribute a literal `<template>` to template depth. The reproduced dist contains 362 non-map files.

The bounded correction routes HTML through the existing direct `jsdom` dependency with a quote-aware raw-text preflight. It handles comments only in HTML data state, keeps `script`/`style` raw text opaque, removes nested inert templates, and preserves SVG/Math CDATA literals without interpreting embedded tags. Active JSON-LD, visible HTML, metadata, `noscript`, malformed-start behavior, raw Markdown/MDX, Search, LLM, and hashed-asset routing remain fail-closed. The complete Local Gate is Green: source guard; Website test 109/109; Website check 41 pages with 0 errors/warnings/hints; fresh 42-page build; static artifact boundary; 41-page site check with only known non-fatal chunk-size and root route-priority warnings; separate artifact release guard; Quickstart syntax/full Consumer E2E ending `Quickstart consumer E2E passed.`; version syntax/baseline `published=1.2.0 historical=1.1.0`; Mago; PHP management-ID; and diff check all PASS. The final Sol xHigh review accepted the exact snapshot with P1=0/P2=0/P3=0. The rerun used the exact current Test SHA/mtime below with no subsequent Source/Test changes. Counts remain actualShikiFixtures=216/864 four-path, tenthFixtures=76, CommonMark/Satteri=66. Source/test mtimes are 17:58:54/18:04:40 JST, with SHA-256 `daef847b0c8aa9b8a0ca128135cd6958fd4cd51b9e2c895718c45ffa147133d3` and `d2b9f1f5439cecfa8d49fffef70b62f310d29ecee5f19e1832d721f025eb3a46`. No Commit/Push/CI/Deploy/Release occurred.

Remaining parent steps are P22-005D Browser/Accessibility/Search/Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: execute P22-005D against the accepted P22-005C candidate, then prepare the reviewed same-SHA delivery.

## Historical P22-005C HTML Visible-Boundary and Quote-Aware Script Extraction Correction — Superseded for Acceptance

2026-08-17T16:28:23+09:00

The prior thirteenth CommonMark candidate and its Full Local Gate failure are historical/superseded for Acceptance. Source guard, Website 107/107, Website check, complete build, and static artifact boundary passed, then `site:check` failed on fresh hashed `_astro/base-path.Ds54SYed.js` `export{n,t}` because all non-map text assets were sent to the Shell-only LGTM parser. The bounded correction routes only reader surfaces—46 HTML, 82 raw Markdown/MDX, Search, `llms.txt`, and `llms-full.txt`—through that parser; HTML executable script/style source is excluded, with meta and JSON-LD intentionally retained. Generic Artifact guards remain all-file. Hashed JS `export{n,t}`/`set` and inline script/style controls pass, while unsafe raw Markdown, visible HTML code, Search, LLM, metadata, and JSON-LD injections fail.

Focused evidence is Green: Source/test node checks; formal reader test 11/11; existing inline-backtick counterexample matrix; release source guard; Website `check` (41 pages, 0 errors/warnings/hints); focused `site:check` (41 pages); and diff check. Counts remain actualShikiFixtures=216/864 four-path, tenthFixtures=76, CommonMark/Satteri=66. Source/test mtimes are 16:27:52/16:26:25 JST. The complete Required Commands and build restart are deferred by this bounded correction; no Commit/Push/CI/Deploy/Release occurred.

Remaining parent steps are complete P22-005C Required Commands, Orchestrator Local Gate, independent Sol review/Acceptance, P22-005D Browser/Accessibility/Search/Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reviews this exact uncommitted candidate and authorizes the complete Local Gate.

## Historical P22-005C Thirteenth Nested CommonMark Marker-Chain Follow-up — Superseded for Acceptance

2026-08-17T16:11:29+09:00

The previous 16:03 list-marker candidate is superseded. Continuation prose now defaults bare `env`/`readonly` to fail-closed and admits only exact full-context English/Japanese technical descriptions from the normalized physical/rendered line start through its end; the reported Japanese execution/command and English dump/Try contexts reject, while exact English and Japanese command-name/PHP-keyword descriptions pass. Imperative English/Japanese prefixes and appended English imperative/dump suffixes reject through all four lanes. The complete repeated CommonMark container-marker chain (blockquote plus nested unordered/ordered markers in mixed order) is structurally normalized before the same continuation classifier, so nested `Run env`, `printenv`, and declaration dumps reject while nested `Use env as prose` remains safe through Source/plain Artifact/actual Satteri HTML/actual-Satteri Artifact. Markdown blockquote and list markers are removed structurally before exact line classification; Markdown and actual Satteri HTML share suffix-aware full-context classification. Executable Shell fences, raw input, and pre/code remain guarded. Same-line spaces-before-tab are prose for unordered 0–2 and ordered 0–1, code for unordered 3+ and ordered 2+; blank-line tab continuations remain prose through seven spaces and code at eight. Focused formal tests pass 10/10; the i18n matrix passes 56/56 across Source/plain Artifact/actual Satteri HTML/actual-Satteri Artifact; the CommonMark/Satteri marker-chain matrix contains 66 actual fixtures with the new nested negatives/positives; existing CommonMark/fence/lazy coverage remains in the formal suite. Website check is 41 pages with zero errors/warnings/hints; source guard and diff check pass. Mechanical counts are actualShikiFixtures=216 (864 four-path executions) and tenthFixtures=76; Source/test mtimes are 16:10:58 JST. Remaining parent steps are full P22-005C Required Commands, Orchestrator Local Gate, Sol re-review/Acceptance, P22-005D Browser/Accessibility/Search/Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reviews this exact uncommitted i18n/list-marker candidate and authorizes the Full Local Gate; no Commit/Push/CI/Deploy.

## Historical P22-005C Thirteenth Structural Markdown/Artifact Backtick Follow-up — 13:36 Candidate, Superseded for Acceptance

2026-08-17T13:36:18+09:00

The 13:36 correction is historical and superseded by the environment-inline follow-up. Markdown and HTML inline-code boundaries preserved protected LGTM/Docker-inspect/Grafana-secret spans, equal-length multi-backtick delimiters were parsed structurally, and executable pre/code, actual Shiki, raw direct, and Markdown Shell fences remained guarded. Focused formal tests were 10/10; the actualShikiFixtures array contained 216 entries across 864 four-path executions; source guard, Website check, and diff check passed. Remaining parent steps were the complete P22-005C Local Gate, independent Sol review/Acceptance, P22-005D Browser/Accessibility/Search/Production verification, exact reviewed Commit, and same-SHA delivery.
## P22-005C Sol xHigh Tenth-candidate Review — P1=0／P2=4／P3=1, Eleventh Correction Requested

2026-08-17T10:48:22+09:00

Independent Sol xHigh read-only review denied Acceptance for the tenth candidate. Atomic braced Bash words split at spaces; recursive literal shell payload／wrapper commands and `xargs printenv` are not analyzed; Go-template root／root-derived data escapes the State／Health-only contract; and the test used a synthetic token wrapper rather than actual Shiki output. P3 requires the current Report sentence to say 106 tests. The tenth Worker／Local Gate Green is historical false-positive evidence. Remaining parent steps are this eleventh correction, focused evidence, complete Local Gate, Sol re-review／P22-005C Acceptance, P22-005D, exact reviewed Commit, and same-SHA delivery. Next Action: Luna Max applies only the bounded eleventh correction and defers the full gate until focused Green; no Commit／Push／CI／Deploy／Release is authorized.

## P22-005C Historical Fourth Bounded Correction Checkpoint

2026-08-17T07:47:49+09:00

The fourth bounded correction completed within the existing child Files Allowed. The historical `Repository main Preview` exception accepts only exact Markdown／generated HTML H3 and exact anchored Search／ToC／LLM units; wrong levels, normalized variants, plain／JSON／anchor prefix／suffix, case-variant ID／href, and fragment suffix／query／path drift fail closed. Orchestrator independently reran that candidate's complete Local Gate: exact-unit negatives 15/15, positives 2/2, wrong semantic owner rejected, Website 105/105, Source／Artifact release guards, check, complete build／site check, Quickstart syntax／full E2E, version syntax／baseline, Mago, PHP management-ID, diff check, and direct Source／Artifact validation all passed. The following Sol xHigh review denied Acceptance with P1=1／P2=2, so this Green is historical and must be rerun after the fifth correction.

## P22-005C Sol xHigh Final Review — Fifth Correction Requested

2026-08-17T07:57:14+09:00

Independent read-only review returned P1=1／P2=2／P3=0 and denied P22-005C Acceptance. The bounded residuals are: Interactive LGTM failure output can leak its configured password through unformatted `docker inspect`／`Config.Env`; Core API Method Return／Error mappings can be swapped without validator failure; and Japanese Local／CI Repository-evidence voice remains in public Community Board／Testing prose and passes the current guard. Remaining parent steps are the fifth bounded correction, complete post-change Local Gate, Sol xHigh re-review／P22-005C Acceptance, P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Luna Max corrects only these three findings and reruns every Required Command without Commit; no Commit／Push／CI／Deploy／Release is authorized.

## P22-005C Historical Fifth Bounded Correction Checkpoint

2026-08-17T08:10:32+09:00

The fifth bounded correction is complete within the existing child Files Allowed. LGTM failure output is State／Health-only with a fixed safe startup diagnostic and sentinel-password behavioral proof; Core API Return／Error `<br>` Method maps are exact with swap／missing／duplicate／extra negatives; and Community Board／Testing use Application／Reference Application／Hosted Instance boundaries with Japanese Local／CI／Repository-evidence Source／Artifact negatives. Website 106/106, source／artifact guards, check, complete build／site check, direct Source／Artifact validation, Quickstart syntax／full E2E, version syntax／baseline, Mago, PHP management-ID, and diff check pass after the final Source／test change. Fourth-correction Green evidence is historical／superseded for Acceptance. Remaining parent steps are a new Orchestrator Local Gate, independent Sol xHigh review／P22-005C Acceptance, P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reruns the complete Local Gate against this exact uncommitted candidate; no Commit／Push／CI／Deploy／Release is authorized.

## P22-005C Historical Fifth-candidate Braced-target Counterexample

2026-08-17T08:23:13+09:00

The fifth correction implemented all three Sol findings and passed its Worker Gate, but Orchestrator direct invocation proved unformatted `docker inspect "${LGTM}"` bypasses the guard that rejects the equivalent `$LGTM` form. Remaining parent steps are this sixth one-point guard hardening, complete post-change Local Gate, Sol re-review／C Acceptance, P22-005D, exact reviewed Commit, and same-SHA delivery. Next Action: Luna Max corrects only the braced-target parser gap and reruns every Required Command without Commit.

## P22-005C Historical Sixth Bounded Correction Checkpoint

2026-08-17T08:30:49+09:00

The braced LGTM target bypass is corrected within the existing child Files Allowed. The shared reader guard recognizes `$LGTM` and `${LGTM}` equally, covers `docker container inspect`, and requires parsed State／Health-only formats for every matching inspect; unformatted／whole-object／Config／Env forms fail closed. Direct Source／Artifact fixtures reject braced unformatted／whole-object／Config forms and preserve unbraced／braced State／Health positives. Focused direct evidence is Green for all six cases. Website 106/106, source／artifact release guards, check, complete 42-page build／41-page site check, direct counts／inventory, Quickstart syntax／full E2E, version syntax／baseline, Mago, PHP management-ID, and diff check all pass after the final Source／test change. Fifth-candidate Green evidence is historical／superseded for Acceptance. Remaining parent steps are the complete post-change Orchestrator Local Gate, independent Sol xHigh review／P22-005C Acceptance, P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reruns the complete Local Gate against this exact uncommitted sixth-correction candidate; no Commit／Push／CI／Deploy／Release is authorized.

## P22-005C Historical Sixth-candidate Security Counterexamples

2026-08-17T08:37:07+09:00

Orchestrator pure invocations show duplicate formats, multiple same-line inspect calls, and direct Grafana password expansion still pass the sixth guard. Remaining parent steps are the seventh bounded hardening, complete post-change Local Gate, Sol re-review／C Acceptance, P22-005D, exact reviewed Commit, and same-SHA delivery. Next Action: Luna Max validates every invocation and rejects secret output, then reruns all Required Commands without Commit.

## P22-005C Historical Seventh Bounded Correction Checkpoint

2026-08-17T08:54:55+09:00

The sixth-candidate multi-invocation and secret-output bypasses are corrected within the existing child Files Allowed. The guard now enumerates and bounds every `docker inspect`／`docker container inspect`, requires exactly one State／Health-only `--format` for every `$LGTM`／`${LGTM}` target, rejects Grafana credential identifiers／expansions in formats and `printf`／`echo` output, and preserves literal prompts, assignments, and Docker env input. Direct Source／Artifact evidence rejects all five reported negatives and passes safe multi-inspect HTML, State／Health, literal prompt, assignment, and env-input positives. Orchestrator independently reran the complete Local Gate after the final change: Website 106/106, source／artifact guards, check, complete 42-page build／41-page site check, direct counts／inventory, Quickstart syntax／full E2E, version syntax／baseline, Mago, PHP management-ID, and diff check all pass. Sixth-candidate Green evidence is historical／superseded for Acceptance. Remaining parent steps are independent Sol xHigh review／P22-005C Acceptance, P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Sol xHigh reviews this exact frozen Working Tree; no Commit／Push／CI／Deploy／Release is authorized.

## P22-005C Sol xHigh Seventh-candidate Review — Eighth Correction Requested

2026-08-17T09:10:04+09:00

Independent Sol xHigh review returned P1=0／P2=1／P3=0 and denied P22-005C Acceptance. Real generated Shiki HTML tokenizes one `docker inspect` command across nested spans, and Bash backslash continuation splits one logical command across physical lines; the raw line-oriented guard therefore passes Shiki-shaped and multiline unformatted／`Config.Env` diagnostics. Orchestrator reproduced all four bypasses through both direct and Artifact paths. Remaining parent steps are this eighth bounded correction, complete post-change Local Gate, Sol xHigh re-review／P22-005C Acceptance, P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Luna Max adds only dependency-free visible-text normalization, logical-command joining, and real-Shiki／multiline negative and positive fixtures, then reruns every Required Command without Commit; no Commit／Push／CI／Deploy／Release is authorized.

## P22-005C Orchestrator Eighth-candidate `<br>` Counterexample — Ninth Correction Requested

2026-08-17T09:32:20+09:00

The eighth correction closes real Shiki and raw continuation cases, but Orchestrator found one residual inside the same Artifact normalization contract: `<br>` followed by pretty-print newline／indent becomes two physical newlines, causing a continuation backslash to join only the synthetic blank line. Pretty-printed `<br>` unformatted and `Config.Env` commands pass both direct and Artifact paths; the State-only control passes. Remaining parent steps are the ninth one-point correction, complete post-change Local Gate, Sol xHigh review／P22-005C Acceptance, P22-005D, exact reviewed Commit, and same-SHA delivery. Next Action: Luna Max collapses only post-`<br>` formatting whitespace into the single visible boundary, adds full-path fixtures, and reruns every Required Command without Commit; no Commit／Push／CI／Deploy／Release is authorized.

## P22-005C Historical Eighth Bounded Correction Checkpoint — Superseded for Acceptance

2026-08-17T09:23:45+09:00

The eighth bounded correction is complete within the existing child Files Allowed. Dependency-free Artifact normalization decodes named／numeric entities, preserves Shiki `.line` and `<br>` boundaries, removes remaining tags, and feeds visible text to the shared LGTM guard; unescaped Bash backslash continuations are joined before every inspect invocation is parsed. Real Shiki and plain continuation fixtures reject unformatted／`Config.Env` diagnostics through direct Source and Artifact paths, while State／Health-only Shiki, multiline, and raw positives pass. After the final Source／test change, source guard, Website 106/106, check, complete 42-page build／41-page site check, artifact guard, direct Source `3／18／10／8／1` and Artifact `40／40` validation, Quickstart syntax／full E2E ending `Quickstart consumer E2E passed.`, version syntax／baseline, Mago, PHP management-ID, and diff check all pass. No blocker and no Commit／Push／CI／Deploy／Release／external mutation occurred. Remaining parent steps are P22-005C Orchestrator complete Local Gate, independent Sol xHigh re-review／Acceptance, P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reruns the complete Local Gate against this exact uncommitted eighth-correction candidate; no Commit／Push／CI／Deploy／Release is authorized.

## P22-005C Ninth Bounded Correction Checkpoint

2026-08-17T09:40:59+09:00

The ninth one-point correction is complete within the existing child Files Allowed. The shared Artifact normalizer collapses formatting whitespace immediately after supported `<br>` tags, including attribute and self-closing forms, into one visible boundary before logical Bash continuation joining. Existing Shiki `.line`, entity, raw continuation, and unrelated physical-line behavior remains covered. Pretty-printed `<br>` unformatted／`Config.Env` negatives reject through direct Source／Artifact paths and the pretty-printed State／Health-only control passes. After the final Source／test change, source guard, Website 106/106, check, complete 42-page build／41-page site check with existing non-fatal warnings, artifact guard, direct Source `3／18／10／8／1` and Artifact `40／40` validation, Quickstart syntax／full E2E ending `Quickstart consumer E2E passed.`, version syntax／baseline, Mago, PHP management-ID, and diff check all pass. No blocker and no Commit／Push／CI／Deploy／Release／external mutation occurred. Remaining parent steps are P22-005C Orchestrator complete Local Gate, independent Sol xHigh re-review／Acceptance, P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Orchestrator reruns the complete Local Gate against this exact uncommitted ninth-correction candidate; no Commit／Push／CI／Deploy／Release is authorized.

## P22-005C Historical Heading Level Counterexample Checkpoint

2026-08-17T07:19:58+09:00

The third Worker／Orchestrator Green is not accepted because the historical exception still accepts Markdown／HTML heading levels other than the preserved exact H3. The one residual fit the child Task's existing Files Allowed. This checkpoint is historical／superseded by the fourth correction above. No Commit／Push／CI／Deploy／Release was authorized.

## P22-005C Second Correction Counterexample Checkpoint

2026-08-17T06:56:43+09:00

The second bounded correction Worker Green is not accepted. Orchestrator proved three residuals within the child Task's existing Files Allowed: semantic references do not have to resolve to their identity's owner; the Interactive LGTM lane lacks final health and a usable non-secret Network／OTLP handoff while echoing its password; and the historical main-preview heading exception accepts prefix／suffix injection. The 105/105 and other second-correction evidence are superseded for Acceptance and must be rerun after Source changes. Remaining parent steps are the third bounded correction, Orchestrator complete Local Gate, independent Sol xHigh review／Acceptance, then P22-005D Browser／Accessibility／Search／Production verification, exact reviewed Commit, and same-SHA delivery. Next Action: Luna Max applies only the three bounded corrections and reruns all Required Commands without Commit; no Commit／Push／CI／Deploy／Release is authorized.

## Allowed Checkpoint Scope

P22-005 and its child checkpoints explicitly allow `develop/decisions/143-documentation-release-truth-and-information-architecture.md` and `develop/spec/104-documentation-release-lifecycle-and-information-architecture.md` for release authority and documentation lifecycle scope; both are included in the P22-005A candidate. The untracked `develop/orchestration/tasks/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md` is a separate Phase 23 proposal and is excluded from the P22-005A commit candidate.

## Relevant Specifications

- `develop/decisions/143-documentation-release-truth-and-information-architecture.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/92-documentation-review-agent.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/spec/104-documentation-release-lifecycle-and-information-architecture.md`

## Acceptance Criteria

- [x] Stable `1.2.0`のcurrent claimが全公開Source／Artifactで一致する
- [x] 正当なhistorical referenceだけがexact allowlistで保持される
- [x] 新IAから主要Journeyへ一Actionまたは明示的な連続導線で到達できる
- [x] Release／Capability変更が共有CI guardなしにmergeできない
- [x] 全公開Pageの独立Documentation ReviewがP1=0／P2=0となる（Child A〜Eの独立Review完了、最終verdictはいずれもP1=0／P2=0）
- [x] Production WebsiteとSearch／LLM artifactのcanonical HTTP／Artifact verificationが成功する（P22-005G exact HTTP／Artifact evidence; current Production Browser confirmation is also Green at the recorded 41-route／127-execution evidence root）
- [x] 各Child Task完了時に上位Goalの残り工程とNext Actionが報告される（P22-005A〜Gの各Task／ReportにRemaining Issues／Suggested Next Actionを確認済み。公開Page reviewのChild A〜E注記は別criteriaで保持）
- [x] Independent Sol xHigh final verdictはP1=0／P2=0／P3=0、findingなしで、P22-005D／P22-005G／parent acceptanceを支持する

## Expected Report

各Child Task ReportとParent Reportへ、Summary、Changed Files、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
## P22-005C Sol Eleventh Focused Review — Twelfth Correction Requested

2026-08-17T11:26:19+09:00

Independent focused review denied the eleventh candidate with P1=0／P2=4／P3=0. The twelfth bounded residuals are unresolved outer inspect attribution independent of nested substitutions, missing process-substitution／literal-eval／shell here-string recursive parsing and bare shell environment-dump rejection, and `${...}` depth that treats literal `{` as nested. Full Required Commands remain forbidden until focused Green. Remaining parent steps are this twelfth correction, complete Local Gate, Sol re-review／Acceptance, P22-005D, exact reviewed Commit, and same-SHA delivery. Next Action: Luna Max implements only the twelfth parser/test correction without Commit.
