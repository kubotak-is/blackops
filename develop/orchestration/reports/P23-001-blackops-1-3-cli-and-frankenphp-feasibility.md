# P23-001 Report: BlackOps 1.3 CLI and FrankenPHP Feasibility

Status: P23-001 Accepted — Stable 1.2 current / BlackOps 1.3 unreleased proposal

This Report preserves the accepted feasibility checkpoint below. Its suggested
next action records that checkpoint's date. Current Phase23 delivery and
remaining work are tracked in [STATE](../../STATE.md) and [TODO](../../TODO.md),
which link to the active implementation Task and Report.

Updated At: 2026-08-26T10:34:43+09:00

Historical Round29 Local Gate Green Checkpoint At: 2026-08-24T18:48:37+09:00
Round 26 Completed At: 2026-08-20T18:39:33+09:00
Round 27 Completed At: 2026-08-21T10:01:15+09:00
Round 27 Post-review Correction Completed At: 2026-08-23T00:58:22+09:00
Round 28 Dual-lane Management Synchronization Completed At: 2026-08-23T01:18:24+09:00
Round 29 Fresh Sol Verdict Synchronization Started At: 2026-08-23T03:33:10+09:00
Round29 Management Post-gate Correction Started At: 2026-08-24T19:14:40+09:00
Round29 Management Post-gate Correction Completed At: 2026-08-24T19:22:59+09:00
Round29 Post-gate Management Sync Started At: 2026-08-25T01:22:48+09:00
Round29 Post-gate Management Sync Completed At: 2026-08-25T01:31:39+09:00
Round29 Sol Final Correction Sync Started At: 2026-08-25T01:51:39+09:00
Round29 Sol Final Correction Sync Completed At: 2026-08-25T01:51:39+09:00
Round29 Post-gate Management Sync 2 Started At: 2026-08-25T10:03:33+09:00
Round29 Post-gate Management Sync 2 Completed At: 2026-08-25T10:19:26+09:00
Round29 Post-gate Management Sync 3 Started At: 2026-08-25T11:56:04+09:00
Round29 Post-gate Management Sync 3 Completed At: 2026-08-25T12:01:54+09:00
Round29 Management Checkpoint Sync 4 Started At: 2026-08-25T13:25:51+09:00
Round29 Management Checkpoint Sync 4 Completed At: 2026-08-25T13:25:51+09:00
Round29 Combined Gate Green Management Sync Started At: 2026-08-25T13:56:20+09:00
Round29 Combined Gate Green Management Sync Completed At: 2026-08-25T13:56:20+09:00
Round29 Exact5 Management Sync Started At: 2026-08-25T14:55:51+09:00
Round29 Exact5 Management Sync Completed At: 2026-08-25T14:55:51+09:00
Round29 Post-correction Combined Local Gate Green Management Sync Started At: 2026-08-25T16:31:07+09:00
Round29 Post-correction Combined Local Gate Green Management Sync Completed At: 2026-08-25T16:31:07+09:00
Round29 Management Correction-complete / Gate-pending Sync Started At: 2026-08-25T17:32:04+09:00
Round29 Management Correction-complete / Gate-pending Sync Completed At: 2026-08-25T17:32:04+09:00
Round29 Audit Exact3 Block Resync Started At: 2026-08-25T17:50:37+09:00
Round29 Audit Exact3 Block Resync Completed At: 2026-08-25T17:50:37+09:00
Round29 Fresh Search/Audit Bounded Corrections Started At: 2026-08-26T00:20:39+09:00
Round29 Fresh Search/Audit Bounded Corrections Completed At: 2026-08-26T00:20:39+09:00
Round29 Search Raw-start Boundary Parity Correction Started At: 2026-08-26T00:41:04+09:00
Round29 Search Raw-start Boundary Parity Correction Completed At: 2026-08-26T00:41:04+09:00
Round29 Combined Local Gate Green Management Sync Started At: 2026-08-26T01:46:59+09:00
Round29 Combined Local Gate Green Management Sync Completed At: 2026-08-26T01:51:40+09:00
Child Accepted At: 2026-08-20T02:21:22+09:00
Round 22 Completed At: 2026-08-20T03:25:45+09:00
Round 23 Completed At: 2026-08-20T04:18:00+09:00
Round 24 Completed At: 2026-08-20T06:18:18+09:00
Round 25 Completed At: 2026-08-20T07:46:09+09:00

Base: `main` @ `5ad87557c08e20c3bcc6d77be83804001ba3eb80`

## Current P23-001 acceptance handoff — Accepted / Stable 1.2 current / BlackOps 1.3 unreleased proposal

Round30 P23-001 Acceptance Sync Completed At: 2026-08-26T10:34:43+09:00

### Summary

Status: `P23-001 Accepted — Stable 1.2 current / BlackOps 1.3 unreleased proposal`. Search Sol, Audit, and Parent General Sol decisions are all Accepted with `P1=0/P2=0/P3=0`; Search and Audit remain separate lanes with no severity aggregation. Stable `1.2` remains current, BlackOps `1.3` remains an unreleased proposal, and no release claim is made.

Risk-based gate facts retained exactly: the preceding Full Website run was `147` with `145` pass and `2` fail (`145/147`); the final affected HTML/Search Satteri/JSDOM set was `7/7` PASS; `pnpm check` covered `41` pages; build `42`, static artifact PASS, and site `41` PASS. The `145` unaffected tests were reused through hash-bound delta evidence; final `147/147` was deliberately not rerun after the bounded fix. The separate Fast/Full gate split and generated-fixture reuse remain a performance follow-up and are not P23-001R.

Ending freeze remains PASS for exact22, staged `0`, and `git diff --check`; Search exact4 and Audit exact3 hashes are unchanged. This management acceptance sync did not run tests, build, network, CI, deploy, release, commit, or stage actions; CI/deploy/release/commit remain unapproved and unexecuted.

### Changed Files

This management-only acceptance sync changes exactly five files: `develop/STATE.md`, `develop/TODO.md`, the Task, this Report, and `develop/spec/README.md`. Search/Audit, public Guide, Production PHP, child packets, generated artifacts, and unrelated worktree changes remain frozen.

### Decisions and Assumptions

P23-001 is Accepted for the frozen exact22 with Search Sol, Audit, and Parent General Sol all at `P1=0/P2=0/P3=0`. Stable `1.2` remains current and BlackOps `1.3` remains an unreleased proposal. The Fast/Full gate split and generated-fixture reuse are a separate performance follow-up, not P23-001R.

### Acceptance

P23-001 Remaining Issues are none. CI, deploy, release, and commit are not claimed or authorized by this worker.

### Remaining Issues

None.

### Suggested Next Action

P23-001R (Stable retention Lazy option correction preflight); then Spec106's ordered Phase 23 tasks.

## Historical Round30 management handoff — Search and Audit Slices Accepted / Parent General Sol Review Requested

Round30 Search and Audit Slices Accepted Sync Completed At: 2026-08-26T10:20:10+09:00

### Historical Summary

Historical status: `Round30 Search and Audit Slices Accepted / Parent General Sol Review Requested — parent not Accepted`. This prior slice-acceptance checkpoint is Historical evidence superseded by the current P23-001 Accepted snapshot. Search Sol slice decision was Accepted with `P1=0/P2=0/P3=0`; Audit slice decision was Accepted with `P1=0/P2=0/P3=0`. Search and Audit remained separate lanes with no severity aggregation; Stable `1.2` remained current and Phase 23/`1.3` remained unreleased.

Search Sol positives: the bounded final Search exact4 correction, independent Satteri/JSDOM oracle coverage, and final affected `7/7` evidence covering the 23-fixture x3=`69`-case matrix. Not Verified: final `147/147` Website rerun after the bounded fix, Browser, CI, deploy, and release. The prior risk-based Combined Local Gate is Historical evidence; no final `147/147` claim is made.

Ending freeze remains PASS for exact22, staged `0`, and `git diff --check`; exact Search/Audit hashes are unchanged. Search hashes remain patch `d6d3c2f61b7e6e7177cf3d4a422258f28ad7c5f758ea45078572a7d16ec5233c`, lock `77403af7259c1948d56db4f53a05bf6e172aea1090083e28506f2bb84ca2db01`, reader `6dcc3601d3ff70c8ec8ab3a4515d2d28eb684ac0ee2a4866ce51c305396f8fe7`, test `5728c9bc45c7dc150fd378207f3bebeecf6dc4805de95b32dec8d1ad813030fb`, and installed helper `a8c5c0306045f742745adb6919fe5f8c6729673d9ed1b39d55e27f3e8100d397`. Audit exact3 remains D145 `7ae4247423199ed8f428a50b372ded6b148ed2e19b5e24c860765d441ec33709` / Spec105 `587996196efa2510863de0b4e1aac57bd9a9d9ad5a4113617a7d088bd897e907` / Spec106 `6f58ec12fe8df8893cf6c1499134ccc5fa2d58fe49f46b84e880fbfddbbb6a5b`; frozen public/PHP/child evidence is unchanged and not rerun.

### Changed Files

This management-only sync changes exactly five files: `develop/STATE.md`, `develop/TODO.md`, the Task, this Report, and `develop/spec/README.md`. Search/Audit, public Guide, Production PHP, child packets, generated artifacts, and unrelated worktree changes remain frozen.

### Acceptance

Search and Audit slice decisions are Accepted independently at `P1=0/P2=0/P3=0`; exact22/staged0/diff-check freeze and unchanged exact hashes are recorded. Parent acceptance is not claimed.

No tests/build/network/commit/stage/push action was taken by this management sync.

### Remaining Issues

Parent General Sol review; parent Orchestrator acceptance.

### Suggested Next Action

Parent General Sol review.

## Historical Round30 management handoff — Risk-based Combined Local Gate Green / Fresh Search Sol Re-review Requested

Round30 Search Corrections Started At: 2026-08-26T02:38:02+09:00
Round30 Search Corrections Completed At: 2026-08-26T03:10:26+09:00
Round30 Risk-based Combined Local Gate Sync Completed At: 2026-08-26T09:59:03+09:00

### Historical Summary

Historical status: `Round30 Risk-based Combined Local Gate Green / Fresh Search Sol Re-review Requested — parent not Accepted`. This prior risk-based gate block is Historical current-snapshot evidence after the Search Sol review and is retained without rewriting its evidence. Search review was `P1=0/P2=3/P3=0`; Audit Sol review was Accepted with `P1=0/P2=0/P3=0`. The source-before-final-fix Full Website test ran `147` with `145` pass and `2` fail in `299201.308ms`; the only failing tests were `Search excludes script and style raw bodies opened at physical line endings` and `Search masks incomplete CommonMark HTML blocks with an independent reader oracle`, both sharing the same top-level raw opener continuation root. The bounded Luna correction changed Search exact4. The risk-based delta gate was Green, while the full `147/147` test was deliberately not rerun after the final fix; risk-based reuse was limited to 145 unaffected predecessor tests plus the final 7 affected tests and current check/build/site/release evidence. The prior Round29 Green handoff is Historical evidence after this source change and is not reusable as current Green evidence. Stable `1.2` remains current and Phase 23/`1.3` remains an unreleased proposal.

### Changed Files

Exactly nine files are in this Task scope: the Search patch, lock, independent reader, and focused test, plus `develop/STATE.md`, `develop/TODO.md`, this Task, this Report, and `develop/spec/README.md`. Audit exact3, public Guide, PHP, child P23-001S, generated artifacts, and unrelated worktree changes are frozen. No commit, stage, push, CI, deploy, publication, or release action is claimed.

### Decisions and Assumptions

The Production patch and independent reader each implement bounded state-machine corrections for quote-aware/container-stripped multiline script/style openers, including initial recognized-tag prefix quote state across physical lines; CommonMark HTML-flow activity versus DOM visibility, including visible completion-line tails; and lazy blockquote/shallower interruption across nested/list/tab/blank-line and LF/CRLF/lone-CR input. The focused matrix uses Satteri rendering and JSDOM reader text or Satteri `<pre>` location as an independent oracle. Current target-bearing Access is `374/418`; `103/147` is historical target-less negative evidence, not a current Query wire. The post-P23-001 performance split remains a management follow-up, with no full-validator performance change in this candidate.

### Commands and Results

- Source-before-final-fix Full Website test: `147` with `145` pass and `2` fail in `299201.308ms`; the only failures were `Search excludes script and style raw bodies opened at physical line endings` and `Search masks incomplete CommonMark HTML blocks with an independent reader oracle`, both sharing the same top-level raw opener continuation root.
- Current final candidate delta gate: Node syntax `2/2` PASS; 7 affected HTML/Search Satteri/JSDOM tests `7/7` in `2459.996ms`, including those two failures and the 23-fixture x3=`69`-case matrix; `pnpm check` 41 pages with `0` errors/warnings/hints; the sandbox build failed only at `listen EPERM`, then the approved exact rerun PASSed with 42 pages, static artifact PASS, and site `41` PASS; explicit site/source/artifact release checks PASS; `bash -n` and version baseline PASS (`published=1.2.0`, `historical=1.1.0`); exact22, staged `0`, and `git diff --check` PASS.
- Full `147/147` Website test was deliberately not rerun after the bounded final fix. The risk-based delta reuses 145 unaffected tests from the immediate predecessor plus the final 7 affected tests plus current check/build/site/release evidence. Frozen Audit exact3/public/PHP/child hashes are unchanged and the immediately preceding Green evidence was reused without rerun: D145 `7ae4247423199ed8f428a50b372ded6b148ed2e19b5e24c860765d441ec33709` / Spec105 `587996196efa2510863de0b4e1aac57bd9a9d9ad5a4113617a7d088bd897e907` / Spec106 `6f58ec12fe8df8893cf6c1499134ccc5fa2d58fe49f46b84e880fbfddbbb6a5b`. No tests/build/network/commit/stage/push action was taken by this management sync.

Search hashes: patch `d6d3c2f61b7e6e7177cf3d4a422258f28ad7c5f758ea45078572a7d16ec5233c`; lock `77403af7259c1948d56db4f53a05bf6e172aea1090083e28506f2bb84ca2db01`; reader `6dcc3601d3ff70c8ec8ab3a4515d2d28eb684ac0ee2a4866ce51c305396f8fe7`; test `5728c9bc45c7dc150fd378207f3bebeecf6dc4805de95b32dec8d1ad813030fb`; installed helper `a8c5c0306045f742745adb6919fe5f8c6729673d9ed1b39d55e27f3e8100d397`. Frozen Audit exact3 is unchanged at D145 `7ae4247423199ed8f428a50b372ded6b148ed2e19b5e24c860765d441ec33709`, Spec105 `587996196efa2510863de0b4e1aac57bd9a9d9ad5a4113617a7d088bd897e907`, and Spec106 `6f58ec12fe8df8893cf6c1499134ccc5fa2d58fe49f46b84e880fbfddbbb6a5b`.

### Acceptance Criteria

1. Quote-aware/container-stripped raw opener handling is fixed in both Search implementations.
2. Only script/style bodies are inert; reader-visible div/pre/textarea content stays searchable and incomplete div controls stay masked.
3. Independent Satteri/JSDOM fixtures cover lazy/shallower blockquote interruption, nested/list/tab/blank-line cases, and LF/CRLF/lone CR.

### Remaining Issues

Fresh separate Search Sol re-review; Search slice decision; parent General Sol review; Orchestrator acceptance.

### Suggested Next Action

Send the current Search correction packet for fresh Sol review. Keep the Audit Accepted `0/0/0` record and treat the source-before-final-fix Green evidence as Historical for unchanged frozen lanes.

## Historical Round29 management handoff — Combined Local Gate Green / Fresh Separate Sol Search/Audit Re-reviews Requested

Round29 Combined Local Gate Green Management Sync Started At: 2026-08-26T01:46:59+09:00
Round29 Combined Local Gate Green Management Sync Completed At: 2026-08-26T01:51:40+09:00

### Summary

Historical status: `Round29 Combined Local Gate Green / Fresh Separate Sol Search/Audit Re-reviews Requested — parent not Accepted`. Stable `1.2` remains current; Phase 23/`1.3` remains an unreleased proposal. Search and Audit remain separate lanes and severity is not aggregated. The parent is not Accepted, Decided, or Released.

### Changed Files

This management-only sync changes only the management exact5. Search exact4, Audit exact3, the other exact22 paths, public Guides, Production PHP, child packets, generated artifacts, and tests remain frozen. No commit, stage, push, CI, deploy, publication, or release action is claimed.

### Decisions and Assumptions

Fresh Combined Local Gate evidence is PASS: offline frozen install; Node syntax `4/4`; independent Orchestrator exact probe HTML `22` plus fence `9` PASS; product framing `8/8`; Search focused `6/6`; full Website `146/146` in `277824ms`; `pnpm check` `41` pages with `0` errors/warnings/hints`; the initial sandbox build stopped only at the Astro font listener (`listen EPERM 0.0.0.0`), the approved same-command rerun PASSed with a `42`-page build, static artifact boundary PASS, embedded site check `41` PASS, and existing nonfatal chunk-size/route-priority warnings only; explicit site check `41` PASS; release source/artifact PASS; `bash -n` and version baseline PASS (`published=1.2.0`, `historical=1.1.0`); PHPUnit `30/30` and `250` assertions; Mago format PASS; canonical lint PASS (`186` baseline-filtered/no new issue); verify-baseline PASS; analyze exit `0` with existing `71` issues (`46` warning / `25` help); PHP management-ID guard no matches; exact22/trailing-whitespace/diff/staged `0` PASS. Node/PHP Read/Access goldens match. The prior Pending handoff is Historical source-change-before evidence and is not reusable as current Green evidence.

### Commands and Results

Search exact4 remains frozen: patch `81ca291976660fbcdfe84aeaf91fbb3226fa1ac9aee185eb73dd4c37806f2ee2`, lock `ede585914285f744982ddba7b2464a3b1d6dbb41978aa8df40fdc6e4da3b6e05`, reader `eb97a88bebbc3886e333d0336053f914414c251b705c9aec7a381e9002aa247d`, tests `ae02dd1743f2b7621db47796536f56ee23d995ed3dac0680ee150558e3a21779`, installed helper `f3eb2215cb349776f2d2f09636346d7591d8e43f18af6a836490656bd3538bb5`. Audit exact3 remains frozen: D145 `7ae4247423199ed8f428a50b372ded6b148ed2e19b5e24c860765d441ec33709`, Spec105 `587996196efa2510863de0b4e1aac57bd9a9d9ad5a4113617a7d088bd897e907`, Spec106 `6f58ec12fe8df8893cf6c1499134ccc5fa2d58fe49f46b84e880fbfddbbb6a5b`; Round29 block `71,378` bytes / `7eb769350824b5e52341bcd3432d689b50287dc2134e14b6fac51df65debd9e5`, JSON `7/7`, links `35/11/0`. Management links are `271/259/0` (total/local/missing).

### Acceptance

The Combined Local Gate is Green for final exact22. Fresh separate Sol Search/Audit re-reviews, separate slice decisions, parent General Sol review, and Orchestrator acceptance remain pending; no parent Accepted, Decided, or Released claim is made.

A non-blocking post-P23-001 test-performance follow-up is proposed: split Fast Gate from final Full Gate, isolate the two slow integration cases (`~192s` / `~76s`), and reuse one generated 41-page fixture. No test changes are made in this Green candidate.

### Remaining Issues

Fresh separate Sol Search/Audit re-reviews; separate slice decisions; parent General Sol review; Orchestrator acceptance.

### Suggested Next Action

Freeze final exact22 and new exact5 hashes, send separate Search/Audit packets, then begin the fresh separate Sol re-reviews. Do not claim parent Accepted, Decided, or Released.

## Historical Round29 management handoff — Search/Audit Bounded Corrections Implemented / Combined Local Gate Pending — source-change-before Combined Local Gate snapshot

Historical status: `Round29 Search/Audit Bounded Corrections Implemented / Combined Local Gate Pending — parent not Accepted`. This Pending snapshot predates the fresh Green gate and is retained as Historical evidence only; it is not reusable as current evidence.

Round29 Fresh Search/Audit Bounded Corrections Started At: 2026-08-26T00:20:39+09:00
Round29 Fresh Search/Audit Bounded Corrections Completed At: 2026-08-26T00:20:39+09:00
Round29 Search Raw-start Boundary Parity Correction Started At: 2026-08-26T00:41:04+09:00
Round29 Search Raw-start Boundary Parity Correction Completed At: 2026-08-26T00:41:04+09:00

### Summary

Status: `Round29 Search/Audit Bounded Corrections Implemented / Combined Local Gate Pending — parent not Accepted`. Stable `1.2` remains current; Phase 23/`1.3` remains an unreleased proposal. Search and Audit remain separate lanes and severity is not aggregated. The parent is not Accepted, Decided, or Released.

Search review record: `P1=0/P2=3/P3=0`. Production Search and the independent reader scanner now separately normalize visual-column/list continuation before same-container Setext `-+`/`=+` detection, preserve a block-start empty list marker as a non-paragraph interruption, and mask incomplete CommonMark type-3 through type-6 HTML blocks. RawStarter lines without a standalone `>` now treat the first later `>` as opening completion, not as the closing raw tag, preserving parity with rendered Satteri/JSDOM text. Same-line `<template>` remains inert and multiline `<template` remains visible. Audit review record: `P1=0/P2=1/P3=0`; the technical A5 contract is Green and the stale active D145 index status is corrected.

### Changed Files

This bounded correction changes only Search exact4 plus management exact5. Audit exact3 and the other exact22 paths remain frozen. No Production PHP, public Guide, child packet, generated artifact, commit, stage, push, CI, deploy, or release action is claimed.

### Decisions and Assumptions

The previous Combined Local Gate Green is Historical source-change-before evidence and is not reusable after the Search source edits. The fresh Combined Local Gate has not run and remains Pending. Stable `1.2` and unreleased Phase 23/`1.3` remain separate; Search and Audit severity is not aggregated.

### Commands and Results

Focused reader-contract command: `7/7` PASS, including Setext relative/dedent/tab/list/blockquote, empty markers, physical script/style with and without a standalone `>`, incomplete type-3 through type-6 HTML, complete HTML regressions, fence scanners, and the CommonMark matrix. The explicit no-standalone script/style Satteri/JSDOM parity is `6/6` PASS; independent reader ranges agree. Node syntax: `2/2` PASS. Offline frozen install: PASS. Patch-lock-installed binding: PASS; active helper SHA `f3eb2215cb349776f2d2f09636346d7591d8e43f18af6a836490656bd3538bb5`.

Search exact4 hashes: patch `81ca291976660fbcdfe84aeaf91fbb3226fa1ac9aee185eb73dd4c37806f2ee2`; lock `ede585914285f744982ddba7b2464a3b1d6dbb41978aa8df40fdc6e4da3b6e05`; reader `eb97a88bebbc3886e333d0336053f914414c251b705c9aec7a381e9002aa247d`; tests `ae02dd1743f2b7621db47796536f56ee23d995ed3dac0680ee150558e3a21779`. Frozen Audit exact3: D145 `7ae4247423199ed8f428a50b372ded6b148ed2e19b5e24c860765d441ec33709`; Spec105 `587996196efa2510863de0b4e1aac57bd9a9d9ad5a4113617a7d088bd897e907`; Spec106 `6f58ec12fe8df8893cf6c1499134ccc5fa2d58fe49f46b84e880fbfddbbb6a5b`; Round29 block `71,378` bytes, SHA `7eb769350824b5e52341bcd3432d689b50287dc2134e14b6fac51df65debd9e5`, JSON `7/7`, links `35/11/0`.

### Acceptance

The three Search roots are independently fixed and covered by production/reader/Satteri/JSDOM parity fixtures. The Audit management root is synchronized in the active D145 index. Current-versus-Historical guards, hashes, links, scope, staged state, and diff-check are required below; Full Website/build/Docker/Mago/CI gates are not run by this worker.

### Remaining Issues

Fresh Combined Local Gate from scratch on the new exact22; fresh separate Sol Search/Audit reviews; separate slice decisions; parent General Sol review; Orchestrator acceptance.

### Suggested Next Action

Freeze final exact22 and new exact9 hashes, send separate Search/Audit packets, then run the fresh Combined Local Gate. Do not claim parent Accepted, Decided, or Released.

## Historical Round29 management handoff — Combined Local Gate Green / Fresh Separate Sol Re-reviews Requested — source-change-before Search correction snapshot

Round29 Combined Local Gate Green Management Sync Started At: 2026-08-25T23:15:57+09:00
Round29 Combined Local Gate Green Management Sync Completed At: 2026-08-25T23:15:57+09:00

Historical status: `Round29 Combined Local Gate Green / Fresh Separate Sol Re-reviews Requested — parent not Accepted`. This source-change-before snapshot is retained for auditability only and is not reusable as current evidence.

### Historical Summary

Historical status: `Round29 Combined Local Gate Green / Fresh Separate Sol Re-reviews Requested — parent not Accepted`. Stable `1.2` remains current; Phase 23/`1.3` remains an unreleased proposal. Search and Audit remain separate lanes and severity is not aggregated. The parent is not Accepted, Decided, or Released.

Search exact4 is frozen: patch `4e2c182b35aacae1fa10ce8cff971f01babeaa75d78ad612161ac31163f3557f`, lock `c37b758001203c23706b7d2f7b70ae1d8a5f1515045b2930da1d6e02dbc99377`, reader `1eb569184a1b2cb1391d8721e4712a93658bac130e9b88b6e59c6b565e1241a1`, tests `127680f26bfd6d9d2ca814849af3183857f1657deee3b6b9bd240032410aaef8`, active installed helper `8999af023b9c353b1719ea5c6e0ce0da182e833ae8d09a941a24302822e4897e`; focused `2/2` and syntax/offline install/binding/diff-check PASS.

Audit exact3 is frozen: D145 `7ae4247423199ed8f428a50b372ded6b148ed2e19b5e24c860765d441ec33709`, Spec105 `587996196efa2510863de0b4e1aac57bd9a9d9ad5a4113617a7d088bd897e907`, Spec106 `6f58ec12fe8df8893cf6c1499134ccc5fa2d58fe49f46b84e880fbfddbbb6a5b`; the current Round29 block is `71,378` bytes, SHA `7eb769350824b5e52341bcd3432d689b50287dc2134e14b6fac51df65debd9e5`, byte-identical exact3, JSON `7/7`, Audit links `35 total / 11 local / 0 missing`.

### Commands and Results

Combined Local Gate evidence is PASS: offline frozen install; Node syntax `4/4`; product framing `8/8`; Search focused `2/2`; full Website `144/144` in `279725ms`; Website check `41` pages with `0` errors/warnings/hints; the first sandbox build stopped only at the Astro font listener (`listen EPERM 0.0.0.0`), the approved same-command rerun PASSed with a `42`-page build, static artifact boundary PASS, embedded site check `41` PASS, and only existing nonfatal chunk-size/route-priority warnings; explicit site check `41` PASS; release source/artifact PASS; `bash -n` and version baseline PASS (`published=1.2.0`, `historical=1.1.0`); PHPUnit `30/30` and `250` assertions; Mago format PASS; canonical lint PASS (`186` baseline-filtered, no new issue); verify-baseline PASS; analyze exit `0` with existing `71` issues (`46` warning / `25` help); PHP management-ID guard no matches.

Read Node+Docker scope is `150/192` with digest `bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`; Access is `103/147` with digest `1c505234fd297ef6f9e3cf595106228578833e41a27dda011b17449cb128e241`; domain/algorithm/nonce negatives PASS. Management links are `271 total / 259 local / 0 missing` before this sync and remain bounded; exact22 hashes PASS, staged `0`, `git diff --check` PASS, and `HEAD=main` is `5ad87557c08e20c3bcc6d77be83804001ba3eb80`.

### Acceptance

The Combined Local Gate is Green for the frozen exact22. Fresh separate Sol Search/Audit read-only reviews, separate slice decisions, parent General Sol review, and Orchestrator acceptance remain pending. No parent Accepted, Decided, or Released claim is made.

### Remaining Issues

Fresh separate Sol Search/Audit read-only reviews; separate slice decisions; parent General Sol review; Orchestrator acceptance.

### Suggested Next Action

freeze final exact22 and new exact5 hashes and send separate Search/Audit packets. Do not claim parent Accepted, Decided, or Released.

## Historical Round29 management handoff — Post-review Search/Audit Corrections Implemented / Combined Local Gate Pending snapshot

Historical status: `Round29 Post-review Search/Audit Corrections Implemented / Combined Local Gate Pending — parent not Accepted`. This is the immediately preceding Pending snapshot and its evidence before the current Combined Local Gate Green result; it is management-before evidence, not current and not reusable as Green evidence.

### Summary

Historical status: `Round29 Post-review Search/Audit Corrections Implemented / Combined Local Gate Pending — parent not Accepted`. Stable `1.2` remains current; Phase 23/`1.3` remains an unreleased proposal. Search and Audit remain separate lanes and severity is not aggregated. Luna corrections and Orchestrator review are complete; the parent is not Accepted, Decided, or Released.

Search final Sol verdict: Changes Requested `P1=0/P2=2/P3=0`; the two P2 roots are the Setext H2 boundary and script/style tag-name physical-line boundary. Search exact4 is frozen: patch `4e2c182b35aacae1fa10ce8cff971f01babeaa75d78ad612161ac31163f3557f`, lock `c37b758001203c23706b7d2f7b70ae1d8a5f1515045b2930da1d6e02dbc99377`, reader `1eb569184a1b2cb1391d8721e4712a93658bac130e9b88b6e59c6b565e1241a1`, tests `127680f26bfd6d9d2ca814849af3183857f1657deee3b6b9bd240032410aaef8`, active installed helper `8999af023b9c353b1719ea5c6e0ce0da182e833ae8d09a941a24302822e4897e`; focused `2/2`, syntax/offline install/binding/diff-check PASS.

Audit final Sol verdict: Changes Requested `P1=1/P2=0/P3=0`; the P1 root is the active attempt32 pre_call/post_call phase conflict. Audit exact3 is frozen: D145 `7ae4247423199ed8f428a50b372ded6b148ed2e19b5e24c860765d441ec33709`, Spec105 `587996196efa2510863de0b4e1aac57bd9a9d9ad5a4113617a7d088bd897e907`, Spec106 `6f58ec12fe8df8893cf6c1499134ccc5fa2d58fe49f46b84e880fbfddbbb6a5b`; independently checked heading-inclusive Round29 block through the next-H2 boundary (excluding that boundary LF) is `71,378` bytes, byte-identical exact3, SHA `7eb769350824b5e52341bcd3432d689b50287dc2134e14b6fac51df65debd9e5`, JSON `7/7`, links `35 total / 11 local / 0 missing`, phrase guards, and diff-check PASS.

### Changed Files

Only exact5 management files are changed in this sync: `develop/STATE.md`, `develop/TODO.md`, the parent Task, this Report, and `develop/spec/README.md`. Search exact4, Audit exact3, and the other17 exact22 paths are frozen.

### Decisions

The previous `Round29 Post-correction Combined Local Gate Green / Fresh Separate Sol Search/Audit Re-review Requested` record is Historical source-change-before evidence and is not reusable. The fresh Combined Local Gate for the new exact22 has not run and is Pending; Search/Audit lanes remain disjoint and no severity aggregation is permitted. No CI, Browser, deploy, publication, release, commit, stage, or push is claimed.

### Commands and Results

Management-only static checks executed in this sync: exact5 link/current-vs-Historical/hash guards PASS; Search/Audit and exact5 hash probes PASS; Round29 block JSON/phrase guards PASS; `git diff --check` PASS; staged0 PASS; exact22 path count PASS (`22`). The full Combined Local Gate was intentionally not run in this worker slice.

### Acceptance

Search/Audit corrections and Orchestrator review are complete. Fresh Combined Local Gate, fresh separate Sol Search/Audit reviews, separate slice decisions, parent General Sol review, and Orchestrator acceptance remain pending.

### Remaining Issues

Fresh Combined Local Gate from scratch on new exact22; fresh separate Sol Search/Audit reviews; separate slice decisions; parent General Sol review; Orchestrator acceptance.

### Suggested Next Action

Freeze exact22 and new exact5 hashes, then run the Combined Local Gate from scratch. Do not claim Accepted, Decided, or Released.

## Historical Round29 correction handoff — Post-correction Combined Local Gate Green / Fresh Separate Sol Search/Audit Re-review Requested — source-change-before snapshot

Historical status: `Round29 Post-correction Combined Local Gate Green / Fresh Separate Sol Search/Audit Re-review Requested — parent not Accepted`. This source-change-before snapshot is retained for auditability only and is not reusable for the new exact22.

Fresh Search Sol verdict: Changes Requested `P1=0/P2=2/P3=0`. The roots were CommonMark thematic-break/list and type-6 slash-boundary divergence, plus complete HTML comments and script/style/template content leaking into Search. Search exact4 corrected these roots.

Search exact4 hashes: patch `ba3218266569613f93a0e0275b3f679d34c43efb96f564e5a4674faf3c56c8c7`; lock `634d955259e7250f269af53785d4768155c95895521f14282677ee6bee4578f8`; reader `4d6f9c5b5f7eec7cfee13285a0dec44687be789c62064c2220d2e47b6e45f666`; test `1ba40a38557fa165664b2d4a5d2e81dd885ab2226fcb257ddaf40529b1ab2a9b`. Offline frozen install PASS; syntax PASS; new focused Satteri/reader/DOM `3/3 PASS`; existing Satteri HTML/search `2/2 PASS`; patch-lock-installed helper binding PASS; installed helper SHA `357879303ecb3f2dbeb675174450dc358450674089f3bc2a9a45fe157b1742ce`; exact4 diff-check/staged0 PASS.

Fresh Audit Sol verdict: Changes Requested `P1=1/P2=0/P3=0`. The root was `AuditIngressEnvelope` binding `recordedAt` to nonexistent `logicalRecord.recordedAt` instead of closed-schema `logicalRecord.time.recordedAt`. Audit exact3 corrected this root.

Audit exact3 hashes: D145 `b7903d679f60b5af9d7eb6974e1ff0346042af6dbd8de5077b5f570c3ae2991f`; Specification 105 `b4c09319563cb590161b3fa8c93ff8d99e9cbe42277b2bb0c9d3cc5d54892a4e`; Specification 106 `2934acf7c135664342dc0233e45366ef875c34caa0e58002f9e490892ff86e14`. The current Round29 block is `71,066` bytes in each file, byte-identical, SHA-256 `4128251cd29dfb2e9f103e3a533027cd9314169f98e32dcb0fad8921c6d5c269`; JSON `7/7`; links `35 total / 11 local / 0 missing`; nested-path positive and four negative object-path guards PASS; exact3 staged0/diff-check PASS.

Historical post-correction Combined Local Gate evidence: offline frozen install PASS; Node syntax `4/4` PASS; product framing focused `8/8` PASS; Search correction focused new Satteri/reader/DOM `3/3` and existing HTML/Search `2/2` PASS; patch-lock-installed helper binding PASS; approved-retry build PASS. This evidence belongs only to the source-change-before snapshot and is not current.

Historical Audit Round29 blocks were `71,066` bytes each, byte-identical, SHA-256 `4128251cd29dfb2e9f103e3a533027cd9314169f98e32dcb0fad8921c6d5c269`; JSON fences `7/7`; Markdown links `35 total / 11 local / 0 missing`; nested recordedAt path object guard `5/5`; Node + Docker PHP golden parity scope `311/348`, Access `374/418`.

Freeze evidence: exact22, staged `0`, `HEAD=main` `5ad87557c08e20c3bcc6d77be83804001ba3eb80`, and `git diff --check` PASS; exact5 links `271 total / 259 local / 0 missing`. No CI, Browser, deploy, publication, release, commit, or push is claimed.

Historical acceptance at that snapshot was the post-correction Combined Local Gate Green; it does not satisfy the new gate requirement.

### Remaining Issues

Fresh separate Sol Search and Audit reviews; separate slice decisions; parent General Sol review; and Orchestrator acceptance remain.

### Suggested Next Action

Request fresh separate Sol Search/Audit reviews against this exact22. Do not claim Accepted, Decided, or Released.

## Historical pre-exact5 Round29 handoff — Combined Local Gate Green / Fresh Separate Sol Reviews Requested

### Summary

Historical status is `Round29 Combined Local Gate Green / Fresh Separate Sol
Search/Audit Re-review Requested — parent not Accepted` for the superseded
pre-exact5 source snapshot only.
Stable `1.2` remains current and Phase 23/`1.3` remains an unreleased proposal.
Search and Audit remain separate and are not aggregated. The prior Local Gate
Green/Sol re-review requested checkpoint is Historical evidence for its old
source snapshot and is not reusable. Combined Local Gate Green is an
Orchestrator result on the frozen pre-management exact22; fresh separate Sol
Search/Audit re-reviews are requested, and the parent is not Accepted, Decided,
or Released.

Separate correction inputs from the immediately prior source snapshot remain
distinct: Search Sol Changes Requested `P1=0/P2=1/P3=0`, rooted in the
CommonMark type-6 incomplete block-tag prefix and type-7 no-whitespace tag-name
boundary; Audit Sol Changes Requested `P1=1/P2=0/P3=0`, rooted in non-unique
`nextAvailableAt` generation for positive-attempt prepared rest rows. Counts are
not aggregated.

Combined Local Gate evidence on the frozen pre-management exact22 is PASS:
offline frozen install; Node syntax `4/4`; product framing `8/8`; Search/
CommonMark focused `2/2`; full Website `140/140` with `0` failures in
`270020.279299ms`; Website check `41` pages with `0` errors/warnings/hints;
fresh build `42` pages with static artifact boundary PASS and embedded site
check `41` PASS; explicit site check `41` PASS; release source/artifact PASS;
`bash -n` and version baseline `published=1.2.0 historical=1.1.0` PASS;
PHPUnit `30/30` and `250` assertions; Mago format PASS; canonical lint PASS
with `186` baseline-filtered and `0` new issues; verify-baseline PASS; analyze
exit `0` with existing `71` issues (`46` warning / `25` help); and PHP
management-ID guard with no matches. Only existing nonfatal chunk-size and
route-priority warnings remain.

Search evidence remains separate: patch-lock-installed binding PASS with patch
`11d1ac9...`, lock patch hash equal, and installed helper SHA `197f23de...`.
Audit evidence remains separate: current exact3 Round29 block `70,561` bytes,
SHA-256 `c60bfad4977987c25c76340475a25192ebe89cdce31b26dd958719132d4d97bb`,
byte-identical; JSON `7/7`; Markdown links `35 total / 11 local / 0 missing`;
Node+Docker PHP scope `311/348` with digest
`d7ec64b8c02b0ff123257682988db5794ed0e166cc7ddc21477a6754953aecc7`; Access
`374/418` with requestRef
`oid:v1:2318e23f23c08231b595e2b53d7c3727df900acc626ff61091021a79cc8e38b8`;
and six scheduling branches/external-wire guards PASS. One overly literal Audit
substring assertion failed in a diagnostic harness and the equivalent
12-needle guard then PASSed; this was not a product or gate failure. Earlier
`ERR_PNPM_INVALID_PATCH` and `31/32` reader results remain Historical
diagnostic evidence only.

### Changed Files

This management sync changes only exact5:

- `develop/STATE.md`
- `develop/TODO.md`
- the parent Task
- this Report
- `develop/spec/README.md`

Search exact4 and Audit exact3 remain disjoint frozen source slices. The
other17 paths, generated dist, production code, tests, and external state are
frozen.

### Decisions and Assumptions

The new Local Gate is an Orchestrator result only. Fresh separate Sol Search
and Audit re-reviews, separate slice decisions, parent General review, and
parent acceptance remain pending. Stable `1.2` and unreleased `1.3` remain
separate. No Browser, remote CI, runtime implementation, deploy, publication,
release, commit, stage, or push action is claimed.

### Correction Evidence and Gate State

The Combined Local Gate is Green on the frozen pre-management exact22. Fresh
separate Sol Search and Audit re-reviews are requested; no parent acceptance is
claimed.

### Historical prior Local Gate Green evidence (superseded source snapshot)

The following prior evidence is retained for auditability only and cannot be
used as current evidence.

- Offline frozen `pnpm install`: PASS, Already up to date.
- Node syntax: `4/4 PASS`.
- Product-framing focused tests: `8/8 PASS`.
- Changed CommonMark focused tests: `5/5 PASS`.
- Full Website test: `140/140 PASS`, `0` failures, `267668ms`.
- Website check: PASS, `41` pages, `0` errors/warnings/hints.
- Fresh build: PASS without sandbox retry, `42` pages; static artifact boundary
  PASS; embedded site check `41` PASS; only existing nonfatal chunk-size and
  route-priority warnings.
- Explicit `site:check` `41` PASS; `release:check:source` PASS;
  `release:check:artifact` PASS.
- `bash -n tests/Consumer/version-baseline.sh`: PASS; version baseline PASS,
  `published=1.2.0 historical=1.1.0`.
- PHPUnit: `30/30` tests, `250` assertions PASS.
- Mago format: PASS; canonical lint PASS with `186` baseline-filtered/no
  issues; verify-baseline PASS; analyze exit `0` with existing `71` issues
  (`46` warning / `25` help); PHP management-ID guard: no matches.

Audit exact3 inclusive-heading extraction through the LF before the next `##`:
`65,269` bytes, byte-identical, SHA-256
`6129ff07ffdf1ae6f50030836a5c5e9e30240cb2a8b0c372786645110b0b951c`; JSON
`7/7`; Markdown links `35 total / 11 local / 0 missing`. Node/Docker PHP
parity PASS: scope JCS/LP `311/348`, digest
`d7ec64b8c02b0ff123257682988db5794ed0e166cc7ddc21477a6754953aecc7`; Access
JCS/LP `374/418`, requestRef
`oid:v1:2318e23f23c08231b595e2b53d7c3727df900acc626ff61091021a79cc8e38b8`;
negative matrices PASS. Worker evidence `14d07ea5...` is explicitly not reused
because its extraction was shifted by one byte despite the matching byte count.

Freeze: branch `agent/p23-001-blackops-1-3-feasibility`;
`HEAD=main=5ad87557c08e20c3bcc6d77be83804001ba3eb80`; exact22 paths; staged
`0`; no repository-scoped temporary path; active Blume new patch; and
`git diff --check` PASS. One inline Node link-regex diagnostic raised a
SyntaxError and was corrected immediately by an equivalent passing guard. A
Node `spawnSync git` aggregate diagnostic hit sandbox `EPERM` after all 22
hash assertions passed; direct status, staged-count, and HEAD/main checks
passed. These are diagnostic command issues, not product or gate failures.

Historical prior exact7 hashes for the superseded Local Gate candidate were:
Search patch `896b653128be21f0698848ee7a58b9b53b7c34a0ba0bd24a36f827f776e46f14`,
lock `088c9b2f46112e101460218d701968796b38fc251b62203445f60f853107d415`,
reader `4f74827dc8b6a66346ed7a2f74633fa600a56cea2f5fbd49116f8ffb1a4cce3d`,
test `e63ee1dc6f4c3a6b16488ec7a3ef17bbc026ba5375f0f8649dbab3fd7e3c1cc9`;
Audit D145 `f376f71dc476972abcf57c25491f49ccfd288cbb36c30961266c9a3fabab37a9`,
Spec105 `e005b49bc2c1d4f24465ac53ed8f084f786b00cca5dddea754ef7a49d9bc86d0`,
Spec106 `8b406ab4aef1c24a11ec1a91370a4b0ede5b53d82ac6f0b513274dbdfdf4e7e2`.

### Current correction freeze

### Frozen Source Hashes

Search exact4: patch
`11d1ac9ae2d1c143771930464e9feb909d8aa0c1e21bd1ccd2ba51a5cf15fc9e`, lock
`22d145a4d602917cab0cb85346cb6ef65b231fe7fdeb4fdbeb6f74d7ef1f3917`, reader
`3e8fb77ecbbc82884ef0d5f00a39c0d70dd29cce98793400d3fd367e82c4a6fb`, test
`713a867bb59eaf76e3f58705435c8456404156826764ec0df9b6638478dc0d3f`.
Audit exact3: D145
`ceec23a3112e91ff618a96e8b820ac82ffa0321da14e701af610a3803e9ce815`,
Specification 105
`aaf8c326f22e764ed23c45a3a33a77cad0ec6a9d464bedf6883ae8ca928bb70a`, and
Specification 106
`4233fc7eb379b38ac20c3d18161d94c8fdbccf2d26f0209385fb7fd694c0003f`.

Final exact5 management hashes at handoff: `STATE.md`
`6989b327b4dd2087230994e959f12e70bb961c9f7a1b521649c56dd148786a9f`,
`TODO.md` `defa05cae9e466d8410207db341e38fdae3c706c134be9aad016927ed8268c65`,
parent Task
`b34304faaacfa678276058e386c9834224db3612598c5c17f90f9ffd8ec5c9f9`, and
Specification README
`6bda7d62608c30010971a394de97bae632cbc124765f89c4682d2e9948874896`.
The Report's own final hash is emitted in the worker handoff after the bounded
checks because embedding it here would make the Report hash self-referential.

### Commands and Results

- Exact5 status/source guard: PASS.
- Source exact7 hashes unchanged: PASS.
- Exact22/staged0/HEAD-main/diff-check: PASS.
- Current/historical status guard: PASS.
- No source, test, build, Browser, CI, deployment, publication, release,
  commit, stage, or push command is run in this management sync.

### Acceptance Criteria

- [x] Fresh separate Sol correction inputs, counts, and ownership are recorded without aggregation.
- [x] Search exact4 correction and focused Search evidence are recorded.
- [x] Audit exact3 correction and static Audit evidence are recorded.
- [x] Combined source-changing Local Gate for frozen pre-management exact22.
- [ ] Fresh separate Sol Search and Audit re-reviews (requested).
- [ ] Separate slice decisions.
- [ ] Parent General review.
- [ ] Orchestrator Acceptance.

### Remaining Issues

Fresh separate Sol Search/Audit read-only re-reviews, separate slice decisions,
parent General review, and Orchestrator Acceptance remain. After acceptance,
P23-001R and ordered Phase 23 work remain.

### Suggested Next Action

Freeze the post-sync exact22 and route fresh separate Sol Search and Audit
reviews. Do not claim Accepted, Decided, or Released.

## Historical Round29 prior post-gate Local Gate checkpoint — superseded

### Summary

The exact current status is `Round29 Audit Local Gate Green / Sol Re-review
Requested; Round29 Search Local Gate Green / Sol Re-review Requested — parent
not Accepted`. Stable `1.2` remains current and Phase 23/`1.3` remains an
unreleased proposal. Search and Audit remain separate and are not aggregated.
Prior Changes Requested/Correction In Progress and prior gate snapshots are
Historical evidence for their source snapshots and are not reusable. Local Gate
Green is an Orchestrator gate result only; the parent is not Accepted, Decided,
or Released.

Fresh separate Sol Search and Audit re-reviews are requested after this new
candidate gate. Prior Sol Changes Requested findings remain Historical
correction inputs and are not aggregated.

Orchestrator evidence (start reference `2026-08-25T10:03:33+09:00`): offline
frozen `pnpm install` PASS; Node syntax `4/4 PASS`; focused product-framing
tests `8/8 PASS`; focused Search/Satteri/legacy regression tests `9/9 PASS`;
full Website test `139/139 PASS`; Website check `0 errors / 0 warnings /
0 hints`; sandbox build first failed only `listen EPERM: operation not permitted
0.0.0.0`, then approved sandbox-outside rerun PASSed with `42` pages, static
artifact boundary PASS, and site check `41` pages PASS; existing nonfatal
chunk/route warnings only; explicit `site:check`, `release:check:source`, and
`release:check:artifact` PASS; `bash -n` and version baseline PASS
(`published=1.2.0 historical=1.1.0`); PHPUnit `30 tests / 250 assertions`
PASS; Mago format PASS; canonical lint PASS with `186` baseline-filtered/no
issues; verify-baseline PASS; analyze exit `0` with existing `71` issues
(`46 warning / 25 help`); PHP management-ID guard no matches.

Audit exact3 evidence: current block `52,679` bytes, SHA-256
`a2734271a864c24fd4f1932e4a0b0b7c2fb133fbd6c54822b2a7a135eb80fa1d`; JSON
`7/7`; Markdown links exact3 union `35 total / 11 local / 0 missing`.
Node and Docker PHP golden parity PASS: scope JCS/LP `311/348`, digest
`d7ec64b8c02b0ff123257682988db5794ed0e166cc7ddc21477a6754953aecc7`; access
JCS/LP `374/418`, requestRef
`oid:v1:2318e23f23c08231b595e2b53d7c3727df900acc626ff61091021a79cc8e38b8`.

Frozen exact7 hashes are Search patch `ae8c375cb95592810d917bf93f18895bedc1e58536b047cb872777b224566543`, lock `dd111150c4a5e4ebda8f8cc268202dd550cf8d812e4a4d1e1372ff4d88f49107`, reader `6e808dde20ba2801e050bec8abeea5223a2f247e50e759535cf94dfc6688463f`, test `6e9ab1cce442122293de6cc2d4de501244b02c603e36cafc51531de62b377924`; Audit D145 `0f9574b12156ece70762c0bfbcec9cfc8f2a33c0854abb7f8c5eaaa4a09ab8f7`, Spec105 `bc4a184f6539c976fea4580d2d8af8de084913646b662ec9e1e453e3076d5ad8`, Spec106 `5b641bd94416f0418dd258e9595cf05aba012786767e22353bcaa11ac5be0050`. Final freeze evidence: branch `agent/p23-001-blackops-1-3-feasibility`; `HEAD=main=5ad87557c08e20c3bcc6d77be83804001ba3eb80`; exact22 paths; staged `0`; no repository-scoped temporary path is present beyond the exact22 worktree; pre-existing `/tmp` review evidence remains and was neither deleted nor counted as worktree scope; `git diff --check` PASS.

### Changed Files

This post-gate management sync changes only exact5: `develop/STATE.md`, `develop/TODO.md`,
the parent Task, the parent Report, and `develop/spec/README.md`. Search exact4
and Audit exact3 remain disjoint frozen source slices; the other17 paths,
generated dist, production code, tests, and external state remain frozen.

### Decisions and Assumptions

Prior Changes Requested/Correction In Progress and prior Local Gate snapshots
are Historical evidence and are not reused. This sync records the fresh
Orchestrator Local Gate only; fresh separate Sol re-reviews, slice decisions,
parent General review, and parent acceptance remain pending. It claims no
Browser, remote CI, runtime implementation, deploy, publication, release,
commit, stage, or push action.

Frozen Search hashes are patch `ae8c375cb95592810d917bf93f18895bedc1e58536b047cb872777b224566543`, lock `dd111150c4a5e4ebda8f8cc268202dd550cf8d812e4a4d1e1372ff4d88f49107`, reader `6e808dde20ba2801e050bec8abeea5223a2f247e50e759535cf94dfc6688463f`, test `6e9ab1cce442122293de6cc2d4de501244b02c603e36cafc51531de62b377924`; frozen Audit hashes are D145 `0f9574b12156ece70762c0bfbcec9cfc8f2a33c0854abb7f8c5eaaa4a09ab8f7`, Spec105 `bc4a184f6539c976fea4580d2d8af8de084913646b662ec9e1e453e3076d5ad8`, Spec106 `5b641bd94416f0418dd258e9595cf05aba012786767e22353bcaa11ac5be0050`.

### Commands and Results

- Bounded post-edit checks for this sync: frozen exact7 hashes, exact22 and
  staged `0`, active status agreement across exact5, no placeholder,
  `git diff --check`, and final exact5 hashes. No source/test/build/Browser/CI
  command is rerun here.

### Acceptance Criteria

- [x] Fresh separate Sol findings, counts, and correction ownership/status are recorded without aggregation.
- [x] Search exact4 source corrections and new Search Local Gate.
- [x] Audit exact3 source corrections and new Audit Local Gate.
- [ ] Fresh separate Sol Search and Audit re-reviews.
- [ ] Separate slice decisions.
- [ ] Parent General review.
- [ ] Orchestrator Acceptance.

### Remaining Issues

Fresh separate Sol Search/Audit read-only re-reviews, separate slice decisions,
parent General review, and Orchestrator Acceptance remain. After acceptance,
P23-001R and ordered Phase 23 work remain.

### Suggested Next Action

Freeze the new exact5 and exact7 hashes and request fresh separate Sol Search
and Sol Audit read-only reviews; then record separate slice decisions, request
parent General review, and proceed to Orchestrator Acceptance. Do not claim
Accepted, Decided, or Released.

## Historical Round29 post-gate completion checkpoint and Sol re-review request (superseded)

Historical status at this superseded checkpoint was `Round29 Audit Local Gate Green / Sol Re-review Requested; Round29 Search Local Gate Green / Sol Re-review Requested — parent not Accepted`. Its Orchestrator evidence and hashes remain Historical evidence for the prior source snapshot and are not reused.

### Summary

The exact current status is `Round29 Audit Local Gate Green / Sol Re-review
Requested; Round29 Search Local Gate Green / Sol Re-review Requested — parent
not Accepted`. Stable `1.2` remains current and Phase 23/`1.3` remains an
unreleased proposal. Search and Audit remain separate and are not aggregated.
Local Gate Green is an Orchestrator gate result only; the parent is not
Accepted, Decided, or Released.

Fresh separate Sol verdicts remain recorded as correction inputs: Search
Changes Requested `P1=0/P2=2/P3=0`; Audit Changes Requested `P1=6/P2=0/P3=1`.
Search P2-1 and Audit P1-1 through P1-6/P3-1 source corrections plus their
fresh Local Gates are complete on the frozen candidate. Search P2-2 management
status drift is closed. Fresh separate Sol Search/Audit re-reviews remain
pending.

### Changed Files

This post-gate management sync changes only exact5: `develop/STATE.md`,
`develop/TODO.md`, the parent Task, the parent Report, and
`develop/spec/README.md`. Search exact4 and Audit exact3 remain disjoint source
slices; the other17 paths, generated dist, production code, tests, child
task/report, and unrelated lock/patch files remain frozen.

### Decisions and Assumptions

The prior Round29 gate and correction/review blocks are Historical evidence for
their source snapshots and are not reused. This sync does not claim a fresh Sol
re-review, slice decisions, parent General review, or parent acceptance. It
claims no Browser, remote CI, commit, stage, push, deploy, publish, tag, or
release action.

### Orchestrator Evidence

- Offline frozen `pnpm install` PASS after the initial patch hunk failure was corrected.
- Focused Search `4/4 PASS`; full Website `135/135 PASS`; Website check `41 pages, 0 errors / 0 warnings / 0 hints`.
- The first sandbox build failed only with Astro local font server listen `EPERM`; an approved out-of-sandbox fresh build PASSed `42` pages, with artifact/site embedded checks PASS for `41` pages.
- Explicit site/source/artifact guards PASS; `bash -n` and version baseline PASS (`published=1.2.0 historical=1.1.0`).
- PHPUnit `30 tests / 250 assertions` PASS; Mago format PASS; lint `186` baseline-filtered/no issues; verify-baseline PASS; analyze exit `0` with existing `71` issues (`46 warning / 25 help`); PHP management-ID guard had no matches.
- Audit Round29 exact3 identical-block extraction is `47,067` bytes with SHA-256 `f4546c064bacf9fcb387e9d535221a545d5a9b0056d8b6a6c113589222b80556`; JSON `7/7`, local links `11/11`, and marker/status guards are clean.
- Exact22, staged `0`, `HEAD=main=5ad87557c08e20c3bcc6d77be83804001ba3eb80`, and `git diff --check` PASS. Installed Blume resolves to the current patch hash.

Current source hashes before this exact5 management edit are frozen: Search
patch `3c1038e493dd4eed5f400c88865d55b109d22e8ab059cdfc2cdf62c19b7cc305`, lock
`81b21213af7388ae90c8e8dd577181bddc518ab10499cb377dc140d8beeaf18d`, reader
`67eee43f8f5365507a4f6835dc9e1284ad148fd3bdaf872486cac2e48ba1fa14`, test
`5a8754fd10b21fbdc66dc35983012591737e7830f432965c26b61d3731c321e4`; Audit
D145 `869c39e535b6014f70c42a5ea4a33d7a2c73aeace421fa6be15a0b69ec29c670`,
Spec105 `74c6ecff785deeb536e6ced9155154d7cfc9fc8fc943ed173692a45052784eda`,
Spec106 `b1eb05c5a762a71861dff67345581c0a095d58ba74a210217d7c7e99f30c22bf`.

### Commands and Results

- Frozen Search exact4 and Audit exact3 SHA-256 probes: PASS; hashes match the
  supplied current source snapshot above.
- Current status synchronization: PASS (`5/5`); stale-current-block guard:
  PASS; exact22 worktree path count: PASS (`22`); staged count: PASS (`0`).
- `git diff --check`: PASS. Final exact5 SHA-256 probe: PASS; values are
  emitted in the worker handoff because embedding a file's own final hash would
  make that hash self-referential.
- No test, build, Browser, network, CI, deploy, publication, release, commit,
  stage, or push command was run by this sync. The PASS evidence above is the
  Orchestrator's recorded post-gate result.

### Acceptance Criteria

- [x] Fresh separate Sol verdicts remain separate with exact counts `Search P1=0/P2=2/P3=0` and `Audit P1=6/P2=0/P3=1`.
- [x] Search P2-1 source correction and fresh Search Local Gate.
- [x] Audit P1-1 through P1-6/P3-1 source correction and fresh Audit Local Gate.
- [x] Search P2-2 management status drift is closed and current status is synchronized.
- [ ] Fresh separate Sol Search and Audit re-reviews.
- [ ] Separate slice decisions.
- [ ] Parent General review.
- [ ] Orchestrator Acceptance.

### Remaining Issues

Fresh separate Sol Search/Audit re-reviews, separate slice decisions, parent
General review, and Orchestrator Acceptance remain. After acceptance, P23-001R
and ordered Phase 23 work remain.

### Suggested Next Action

Freeze exact22/current hashes and route separate fresh Sol Search and Audit
reviews. After those reviews, record slice decisions, request parent General
review, and wait for Orchestrator acceptance. Do not claim parent Accepted,
Decided, or Released; no Browser, remote CI, commit, stage, push, deploy,
publish, tag, or release is authorized.

## Historical Round29 post-gate correction packet and Sol re-review findings (superseded)

Historical status at this superseded checkpoint was `Round29 Audit Sol Re-review Changes Requested / Correction In Progress; Round29 Search Sol Re-review Changes Requested / Correction In Progress — parent not Accepted`. The prior correction findings, pre-edit hashes, and management ownership remain Historical evidence below.

### Summary

The exact current status is `Round29 Audit Sol Re-review Changes Requested /
Correction In Progress; Round29 Search Sol Re-review Changes Requested /
Correction In Progress — parent not Accepted`. Stable `1.2` remains current and
Phase 23/`1.3` remains an unreleased proposal. Search and Audit remain separate
and are not aggregated. The parent is not Green, Accepted, Decided, or Released.

Fresh separate Sol verdicts are current correction inputs: Search Changes
Requested `P1=0/P2=2/P3=0`; Audit Changes Requested `P1=6/P2=0/P3=1`.
Search P2-1 requires partial-TAB/list-continuation surplus visual indent and
paragraph/block-boundary state to match actual renderer parity, production
Search, Raw rewrite, and reader guard, with non-self-oracle fixtures. Search
P2-2 is management status drift and closes in this management correction.
Audit P1-1 requires attempt-32 external success/response-loss reconciliation
before terminalization; P1-2 requires finite Query total error mapping and
precedence; P1-3 requires one Hold domain; P1-4 requires expired Outbox token
and equality-boundary handling; P1-5 requires the policy-unavailable
strictly-newer/same-version fork; P1-6 keeps volatile status out of the
normative exact3 source, with management as authority and exact3 status removed
or simplified to avoid a hash/gate cycle; P3-1 removes truncation warning lines
and adds a guard.

### Changed Files

This current management phase changes only exact5: `develop/STATE.md`,
`develop/TODO.md`, this parent Task, this parent Report, and
`develop/spec/README.md`. Search exact4 remains the separate Blume patch,
Website lock, reader script, and reader test slice. Audit exact3 remains D145,
Specification 105, and Specification 106. The other17 paths, including public
Guides, Website tests/code outside Search exact4, P23-001S, and generated files,
remain frozen.

### Decisions and Assumptions

The prior Round29 Local Gate Green checkpoint is Historical evidence for its
source snapshot and is not reused. This management phase does not claim source
correction completion, a new gate Green result, or parent acceptance.

### Commands and Results

- Read-only start checkpoint: supplied management5 and Search/Audit source
  hashes matched; branch `agent/p23-001-blackops-1-3-feasibility`, HEAD
  `5ad87557c08e20c3bcc6d77be83804001ba3eb80`, staged `0`.
- No test, build, network, CI, deploy, publication, release, commit, stage, or
  push command was run by this management phase.

Pre-edit source snapshot hashes matched: Search exact4 patch
`344035e25063014a138c2b549b63c4c1f12c5bb0ad2f3c1077ab910b149958da`, lock
`1eaff6dd4fa7b1af78820175328cab33a41d4c9264d790f81b08b4bb94d5bced`, reader
`5aa75a842d8969cef1842f2f42d720d25027a2467ed4f1ca0f02868a561b9295`, test
`484ee970cec13740920ec4702a7b50be069484de1c6bb060a81b00ef1429feba`; Audit
exact3 D145 `f6d9c2987398f3834485856af1ecb6bc16dc4440a05e3967f0a07102c654db70`,
Spec105 `a1c86b19757e178a02b7f10733181a3775c1f4e926f5d451d8bf0ccaaacc48ca`,
and Spec106 `03df98b3e17ef2931404bb2e3c3970576ab3e77f2ead1c770b14014e4e36d624`.

### Acceptance Criteria

- [x] Fresh separate Sol verdicts and exact Search/Audit counts are recorded
  without aggregation.
- [x] Exact current status and disjoint ownership are synchronized.
- [x] Search P2-2 management status drift is closed by this management
  correction.
- [ ] Search P2-1 source correction and fresh Search gate.
- [ ] Audit P1-1 through P1-6/P3-1 correction and fresh Audit gate.
- [ ] Slice decisions, parent General review, and Orchestrator Acceptance.

### Remaining Issues

Search and Audit source corrections, their new-candidate gates, fresh separate
Sol re-reviews, slice decisions, parent General review, and Orchestrator
Acceptance remain. After acceptance, P23-001R and ordered Phase 23 work remain.

### Suggested Next Action

Keep the exact12 correction union disjoint and freeze all other17 paths; have
the Search and Audit workers resolve the listed findings, run new-candidate
gates, then request fresh separate Sol reviews. Do not claim Green, Accepted,
Decided, or Released.

Supplied pre-edit management hashes were STATE
`cfcba5c0e12227e76ca8864046f9ce6e7e49dbefcfe5bd395695b87c51738380`, TODO
`d67d71cf0624419fe7617b02aed574a61087849768b04225997d92cfd948f0c1`, Task
`6e04676a7c72d8bdba57a953e388b5271147e329777496c6b8c21bf731ce9ef0`, Report
`0b45427888e27ea13e5c6e5b77067a95ed6f2fa1abdec8144cfe56f18428479e`, and
README `63bc0e69264cb083e9ed8d32913ca97b21144bc7f3258a1c95652560fc8d6aba`.

## Historical Round29 Local Gate Green checkpoint

Orchestrator fresh new-candidate Local Gate: sandbox install first stopped on an
SQLite permission failure; after approval, offline frozen install PASS. Node
syntax `4/4 PASS`; Website tests `133/133 PASS`; Website check
`0 errors/0 warnings/0 hints`; fresh build `42` pages with Artifact boundary
PASS and site check `41` pages PASS; separate site/source/artifact release
checks PASS; `bash -n` and version baseline PASS
(`published=1.2.0 historical=1.1.0`); PHPUnit `30 tests / 250 assertions` PASS;
Mago format PASS, canonical lint PASS (`186` baseline-filtered/no issues),
verify-baseline PASS, analyze exit `0` with existing `71` issues (`46 warning /
25 help`); PHP management-ID guard had no matches. Only existing nonfatal Vite
chunk/route warnings remain.

Audit current-only JSON vectors `7/7`; Markdown links `35 total / 11 local / 0
missing`; Node+Docker PHP goldens PASS (JCS/LP `311/348`, digest
`d7ec64b8c02b0ff123257682988db5794ed0e166cc7ddc21477a6754953aecc7`, access
JCS/LP `374/418`, requestRef
`oid:v1:2318e23f23c08231b595e2b53d7c3727df900acc626ff61091021a79cc8e38b8`);
Audit identical-block extraction is `43,425` bytes, SHA-256
`e2648fe19625da051e711a88bc30965bd994224569ac47190b2c76dcc10d9f88`.
- The full new-candidate Local Gate is Green on this candidate. Prior complete
  Website/Audit gates are Historical evidence for older source snapshots and
  are not reused.
- Freeze: branch `agent/p23-001-blackops-1-3-feasibility`,
  `HEAD=main=5ad87557c08e20c3bcc6d77be83804001ba3eb80`, staged `0`, exact22,
  temporary files absent, and `git diff --check` PASS.
- Browser, remote CI, runtime implementation, deploy, publication, and release
  were not run or claimed in this phase. This management worker did not rerun
  the gate and made no test, build, network, commit, stage, or push action.

Final Search exact4 SHA-256: patch
`344035e25063014a138c2b549b63c4c1f12c5bb0ad2f3c1077ab910b149958da`, lock
`1eaff6dd4fa7b1af78820175328cab33a41d4c9264d790f81b08b4bb94d5bced`, reader
`5aa75a842d8969cef1842f2f42d720d25027a2467ed4f1ca0f02868a561b9295`, test
`484ee970cec13740920ec4702a7b50be069484de1c6bb060a81b00ef1429feba`.
Final Audit exact3 SHA-256: D145
`f6d9c2987398f3834485856af1ecb6bc16dc4440a05e3967f0a07102c654db70`, Spec105
`a1c86b19757e178a02b7f10733181a3775c1f4e926f5d451d8bf0ccaaacc48ca`, Spec106
`03df98b3e17ef2931404bb2e3c3970576ab3e77f2ead1c770b14014e4e36d624`.

Final freeze verification is exact22 paths, staged `0`, HEAD/main
`5ad87557c08e20c3bcc6d77be83804001ba3eb80`, and `git diff --check` PASS;
other17 paths are byte-frozen.

### Acceptance Criteria

- [x] Search correction and focused Satteri parity evidence checked.
- [x] Audit correction and focused query/link/diff evidence checked.
- [x] Separate Sol verdicts and correction ownership/status checked.
- [x] Full new-candidate Local Gate (Orchestrator evidence recorded above).
- [ ] Fresh separate Sol Search/Audit re-reviews.
- [ ] Slice decisions, parent General review, and Orchestrator Acceptance.

### Remaining Issues

Fresh separate Sol Search/Audit re-reviews; slice decisions; fresh parent
General review; Orchestrator Acceptance; and, after acceptance, P23-001R and
ordered Phase 23 work remain. Parent acceptance is not complete.

### Suggested Next Action

Request fresh separate Sol Search/Audit re-reviews against this gated exact22
snapshot; record separate slice decisions, then request parent General review
and Orchestrator Acceptance. Do not claim parent Accepted, Decided, or Released.

## Historical Round29 fresh Sol verdict handoff — Audit / Search

### Summary

The then-current status was `Round29 Audit Re-review Changes Requested / Correction In Progress; Round29 Search Re-review Changes Requested / Correction In Progress — parent not Accepted`. Stable `1.2` remains current and Phase 23/`1.3` remains an unreleased proposal. Search and Audit are separate and are not aggregated. This packet was not Green, Accepted, Decided, or Released.

Fresh Search verdict: Changes Requested `P1=0/P2=2/P3=0`. S1 requires
tab-aware CommonMark visual column/contentColumn handling, where TAB advances
from the current column to the next four-column tab stop across ordered/
unordered nesting, space+TAB, dedent, and real renderer/helper parity. S2
requires one shared Source physical-line/container-fence state for list,
blockquote, nested same-line fences, LF, CRLF, and lone-CR input across title,
fence range, raw image, placeholder, and production Raw rewrite.

Fresh Audit verdict: Changes Requested `P1=6/P2=3/P3=0`. A1 requires a
reconstructible three-page cursor chain with non-circular query-scope digest
and separate head/last/prior-cursor bindings. A2 requires one sole-current
Round29 and active non-Historical base for Access/Outbox/Retention exact wire,
enum, and formula. A3 requires active lease token, expected version, and exact
+1 CAS for `leased -> relayed|poisoned`. A4 requires attempt-32 unavailable
resolution and a total Anchor state/claim/lease/expiry/crash table. A5 requires
deterministic Retention truth-table behavior, clock rollback rejection, policy
unavailable representation, and bounded reversal. A6 synchronizes the exact-
three current status block/header. A7 closes finite query validation/cursor
error wire and precedence. A8 closes binary protectedBody versus logical/DB/
JSON representations and cap/timestamp/key/lease bounds. A9 closes policy/hold
snapshot schema, order, no-hold handling, and domain hash.

### Changed Files and phase ownership

This management phase changes only the five allowed files: STATE, TODO, this
parent Task, this parent Report, and Spec README. The disjoint correction union
is exact12. A separate Search Luna worker owns only
`docs/website/patches/blume@1.3.0.patch`, `docs/website/pnpm-lock.yaml`,
`docs/website/scripts/reader-contract.mjs`, and
`docs/website/tests/reader-contract.test.mjs`; a separate Audit Luna worker
owns only D145, Specification 105, and Specification 106. The
`pnpm-workspace.yaml`, Scheduled Operation Guide, other Website paths, and
existing other10 worktree paths are frozen. Search/Audit workers must not edit
management files; final management returns to these five files only.

### Decisions and Assumptions

The prior Round27 Search `P1=0/P2=3/P3=0` correction is Historical evidence.
Round29 prior correction wording is now Historical. Fresh Round29 Search and
Audit are parallel current Changes Requested lanes. Search correction requires
the exact4 source slice plus fresh complete Search/Website gates. Audit
correction requires the exact3 authority/specification slice plus current-only
static guards; this proposal-document phase makes no runtime/build claim.

### Commands and Results

- Reviewer freeze — branch `agent/p23-001-blackops-1-3-feasibility`,
  `HEAD=main=5ad87557c08e20c3bcc6d77be83804001ba3eb80`, staged `0`, exact22,
  specified source hashes, and `git diff --check` PASS, with no edit before the
  review handoff.
- Search positive evidence — current frozen 40 non-Landing
  Source→Raw→Search→HTML→llms-full parity, Stable boundary, and management
  separation confirmed.
- Audit positive evidence — A2, normal query caps/key window, §9.6.1
  receipt/hash, hold/deletion separation, Stable boundary, and three-file
  current-block parity confirmed.
- The previous complete Search/Website Gate is Historical evidence for the prior
  source snapshot. Any source correction creates a new candidate and requires
  complete Search/Website gates again; no old gate is reused.
- Runtime, Browser, remote CI, deploy, publication, and release are unverified
  and unexecuted. This worker ran no prohibited test/build command and made no
  commit, stage, push, network, or external mutation.

Source snapshot hashes: Search exact4 — patch
`e1da3b29c6a033058e018bb7c4b57da8ec6078acd0a1036779c9efc40f506a3c`, lock
`108d627a45fee16934167fef909515eda845c3c3c9bf9e0dcf6949da99fef075`, reader
script `2927257dba0b1efc40df5e1bfa63fc5473074f99f4821a2a39579951b0960292`,
reader test `46c8652cf1adcff9fdff6a14eb7dd4a844632984f433767325d0c3a61890c4a9`;
Audit exact3 — D145
`6c952d4b29337092061cf99b0458ec6523a3c7f6f14afd6c4826a67fa04adca5`, Spec105
`432f83a9c354e970663e0c09a0004c60d4455568c897572ae5cda611c32abc86`, and
Spec106 `867c268bc6e1d5fc89b87f259a44f2363f8812d8e78dd78a661b3128296d796b`.

Management hashes before this edit are STATE `c5ac26f32e2acf80952417442fafed9324f272296c7dd0814778944a94e71aec`, TODO `30bbe0d7539b5def8558bb81142e05b92e9cb1dd53c9b0a46f627354cdb74182`, Task `afc1b564c9d28da408248845125202a7cf6627dd7e8ca10355556891b395d567`, Report `8c3af58313f63de7b02b7a4951ecba4d1dcd6023352abf1d74aab8e5bc8bd172`, and README `f8300f0facb9ef2c42bdf1aea97e381d1fc430a1de7be0825590e6b66ec37930`.

### Acceptance Criteria

- [ ] Search S1/S2 correction and fresh complete Search gates.
- [ ] Audit A1–A9 current-only correction and static guards.
- [x] Fresh separate Sol verdict and correction ownership/status recorded.
- [ ] Final management sync and fresh separate Sol re-reviews.
- [ ] Parent General review and Orchestrator acceptance.

### Remaining Issues

Remaining: exact4/exact3 Luna corrections; Orchestrator new-candidate
gates/static checks; final exact5 sync; fresh separate Sol Search/Audit
re-reviews; slice decisions; parent General review and Acceptance; only after
acceptance P23-001R and ordered downstream work. Parent acceptance is not
complete.

### Suggested Next Action

Keep exact22 frozen, complete disjoint Search/Audit corrections, run
new-candidate gates/static checks, then request fresh separate Sol reviews. Do
not claim parent Green, Accepted, Decided, or Released.

## Historical Round 27 post-review completion — Correction Implemented / Review Requested

### Summary

The first fresh Sol xHigh Round 27 Search re-review returned `P1=0/P2=3/P3=0`.
Its three findings were the hidden-ancestor visibility reset and first direct
closed-details summary, one shared exact Source fence grammar, and patched
Blume CommonMark container fence grammar. The bounded Source/raw/Search/HTML/
LLM and patched Blume `1.3.0` correction closes all three Search/Website roots.
At that historical checkpoint, the then-current statuses were `Round28 Audit
Contract Correction Implemented / Sol Re-review Requested` and `Round27 Search
Post-review Correction Implemented / Review Requested — parent not Accepted`;
Round26 and all earlier checkpoints are Historical evidence. Stable `1.2`
remains current and Phase 23/`1.3` remains an unreleased proposal. The Scheduled
Operation Source correction is the
single natural phrase `毎日0時00分だけを対象にするため`, a Stable
Documentation rendering correction rather than a capability or Release
change.

### Changed Files

Round27's exact implementation set is eleven paths:

- `develop/STATE.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md`
- `develop/orchestration/reports/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md`
- `develop/spec/README.md`
- `docs/website/pnpm-workspace.yaml`
- `docs/website/pnpm-lock.yaml`
- `docs/website/patches/blume@1.3.0.patch`
- `docs/website/scripts/reader-contract.mjs`
- `docs/website/tests/reader-contract.test.mjs`
- `docs/guide/scheduled-operation.md`

This post-review management synchronization changes only these five allowed
paths: `develop/STATE.md`, `develop/TODO.md`, this parent Task, this parent
Report, and `develop/spec/README.md`. The six Website/Guide correction paths
were existing shared-worktree changes and remain frozen, as do D145,
Specifications 105/106, P23-001S, other Guide/Product-framing/PHP paths, and
generated artifacts.

### Decisions and Assumptions

The `blackops-japanese-docs` skill and style checklist were read before this
management synchronization. Round26 and earlier wording remains Historical.
Round28 Audit and Round27 Search are parallel current lanes, without implying
parent acceptance. Stable `1.2` capability and the unreleased Phase 23 proposal
stay separate. The exact shared-worktree evidence is recorded as Orchestrator-run;
no commit, stage, push, CI, deploy, publication, tag, release, or external
mutation is claimed.

### Commands and Results

- `node --check docs/website/scripts/reader-contract.mjs` and the reader test — not rerun after this management-only edit; the current source-candidate syntax/focused evidence remains PASS.
- Focused visibility/full-path reader checks — PASS; final `full-path artifact validation requires source, raw, and Search placeholder parity` is `1/1`.
- `pnpm --dir docs/website test` — PASS, `129/129`, `0` failures, `250493ms`.
- `pnpm --dir docs/website check` — PASS, `0 errors / 0 warnings / 0 hints`.
- Exact fresh `pnpm --dir docs/website run build` — PASS: `42` pages, Static artifact boundary, and site check `41` pages. Existing nonfatal Vite large-chunk and route-priority warnings remain.
- `pnpm --dir docs/website run release:check:source` — PASS after the dual-lane management sync.
- `pnpm --dir docs/website run release:check:artifact` — PASS.
- `bash -n tests/Consumer/version-baseline.sh` — PASS after the dual-lane management sync.
- `bash tests/Consumer/version-baseline.sh` — PASS after the dual-lane management sync, `published=1.2.0 historical=1.1.0`.
- `pnpm --dir docs/website install --offline --frozen-lockfile` — PASS, `Already up to date`.
- `git diff --check` — PASS; staged count `0`.
- HEAD/main — `5ad87557c08e20c3bcc6d77be83804001ba3eb80`; worktree exact22 (Round27 exact11 plus frozen Round26 exact16 overlap).
- Current non-management SHA-256: patch `46cabdc4a97405f2a290b26ded2df1628788217f9d3fb260a48c8776c6e66131`; workspace `d5d05d64d55ee6111b73d5528967a51b1957d0f0e0f68608233eb487ab2c14a6`; lock `9f01615cdbf3ae6ebab59e6aace08941ae65b18281d1470eeba8170333fa1f72`; reader script `2092ea9a0f82c2c493b305a7bc6ea07599bb15ff59b74edadb77a351ae787155`; reader test `a7e52dc821856647909261dc6562a2b7f60d0b75caecebad64cc1c7b1471aa25`; Scheduled Operation Guide `bf238d493c0c8219688b7f08ac6d6450e97db6767796ff5aec117588cb025bdf`.
- Installed helper/documents/dist SHA-256 values are `218b745e9d6213ff78a0ad6bd32c2877dd163b56994736afeed49703d7428c3a`, `858d1e0fa78f5ce8a743216761384a3dc772207f54902b62967eb9ed834f514c`, and `42d8afe1d259f6bc804998a54d6142a195300b155e0df334e6d57cc19e6d4a2c`; source and dist import the same plain-text helper.
- `rg` current-status/sole-current/legacy-count checks and five-file scope checks — PASS; the 17 frozen worktree paths remain unchanged, current status is dual-lane, and staged count is `0`.
- Management-only follow-up ran no full test or build; those PASS results are the latest Orchestrator measurements for this unchanged source candidate. `node --check` was not rerun because only management Markdown changed.

### Acceptance Criteria

- [x] Source-derived Raw/Search/HTML/LLM parity and fail-closed malformed,
  placeholder, boundary, and mutation guards pass.
- [x] Patched Blume `1.3.0` source/dist behavior is synchronized and the
  offline/frozen patch applies.
- [x] Production-shaped and synthetic reader fixtures cover the exact
  Source-derived placeholder set without a hardcoded 50-item requirement.
- [x] All 40 non-Landing HTML articles bind to validated Raw-derived semantics,
  including the corrected Scheduled Operation sentence.
- [x] Source-root, Search non-content, reproducibility, focused/full Website,
  install, check, build, release, artifact, version, diff, and staged gates
  have the recorded PASS results.
- [ ] Fresh Sol Search Documentation Review and fresh Sol Audit Review (separate lanes).
- [ ] Orchestrator acceptance; parent remains not Accepted.

### Remaining Issues

Exact22 freeze, fresh Sol Search Documentation Review and fresh Sol Audit Review
in separate lanes, Orchestrator Website/Audit slice acceptance or any necessary
bounded correction, fresh parent General review, and P23-001 parent acceptance
remain. After parent acceptance, P23-001R and ordered Phase 23 work follow.
Remaining is not `none`.

### Suggested Next Action

Hand the exact22 frozen snapshot to fresh Sol Search and fresh Sol Audit
reviewers as separate lanes. After those reviews, the Orchestrator should
accept each slice or apply a necessary bounded correction, then route fresh
parent General review before recording P23-001 parent acceptance. No commit,
stage, push, CI, deploy, publication, tag, release, or external action is
authorized.

## Historical Round 26 management/current-authority completion

### Summary

The Round 26 management/current-authority slice was implemented in the exact
ten files permitted by that worker. Round 26 is historical evidence; the
current parent now has disjoint Round28 Audit and Round27 Search correction
lanes, with status `Round28 Audit Re-review Changes Requested / Correction In
Progress` and `Round27 Search Re-review Changes Requested / Correction In
Progress — parent not Accepted`. Stable `1.2` remains current and
Phase 23/`1.3` remains an unreleased proposal. Round 25's source-changing Phase 1 build and complete
Local Gate evidence remain historical Orchestrator evidence. The final
management-only static closeout did not rerun a build, so no unrun command is
reported as PASS. This bounded correction closes the independent Round25 Audit
P1 finding and the management-sync P2 without changing Production Code.

The current authority now closes four findings without relying on Historical
text: fixed-head AuditQuery/cursor continuation and exact result/cursor wire;
Local Audit Outbox closed state/nullability, transitions, immutable protected
body/AAD, retry/dead-letter behavior, owners, and fixtures; AnchorIntent's
prepared/submitted/accepted/rejected matrix with accepted-only triplet,
CAS/idempotency, and recovery; and exact `AuditRetentionState` plus
`AuditHold` wire, genesis, version, terminal, and re-activation invariants.
The parallel Search/Blume artifact correction remains in its existing packet
and was not edited by this worker.

### Changed Files

Only these ten files were changed by this worker:

- `develop/STATE.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md`
- `develop/orchestration/reports/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md`
- `develop/spec/README.md`
- `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`
- `develop/spec/105-blackops-1-3-capability-contract.md`
- `develop/spec/106-phase-23-delivery-plan.md`
- `develop/orchestration/tasks/P23-001S-stable-list-documentation-truth.md`
- `develop/orchestration/reports/P23-001S-stable-list-documentation-truth.md`

The shared worktree's existing `docs/website/**`, `docs/guide/**`, and
`tests/**` changes belong to the parallel artifact/documentation worker and
were preserved; this worker did not edit them. No Production Code, dependency,
generated artifact, commit, stage, push, CI, deploy, publication, tag, or
release action occurred.

### Decisions and Assumptions

The `blackops-japanese-docs` skill and its required style checklist were read
in full before editing. Current authority is separated from Historical
Round25 evidence, and Stable `1.2` is not presented as having any proposed
`1.3` capability. Existing P23-003B/C/F/G ownership and fixture boundaries
are retained. Parent/child Round21–Round25 status, Acceptance Criteria,
Remaining, Next Action, and gate blocks are Historical where retained; the
current parent has parallel Round28 Audit (`Sol Re-review Requested`) and
Round27 Search (`Review Requested`) statuses, and remains not Accepted.

### Current contract handoff

AuditQuery/cursor: a first-page request has `snapshotHeadSequence: null` and
`cursor: null`; the service captures exactly one trusted head. A continuation
must carry the exact partition, filter tuple, and captured head binding. Every
returned record is `sequence <= capturedHead`, and the next page begins strictly
after the previous page's `lastSequence`. The exact result wire is
`{schemaVersion:1,kind:"audit_query_result",records,nextCursor,hasMore}`;
the cursor is the closed `aqc:v1` wire containing partition/filter/head/last
and its trusted-now/key-window checks fail closed for expiry or unknown keys.

Local Audit Outbox: the closed states are `pending`, `leased` (claimed),
`relayed` (sent), and `poisoned` (dead-letter). `pending` has a non-null
`availableAt` and null lease fields; `leased` has a positive attempt, null
`availableAt`, and non-null owner/lease expiry; terminal `relayed` and
`poisoned` rows have null lease fields. Retry is `leased -> pending` with an
incremented attempt and future availability; exhausted retry is
`leased -> poisoned`; only `leased -> relayed` marks sent. The protected body
and tenant-aware AAD are immutable after insert and are not re-encrypted by
relay. Current owner/fixture boundaries remain P23-003C delivery plus the
P23-003B protected-record fixtures.

AnchorIntent: the closed states are `prepared`, `submitted`, `accepted`, and
`rejected`. Prepared has no submission/acceptance receipt; submitted has a
submission receipt but no accepted triplet; rejected has a rejection receipt
and no accepted triplet; only accepted has non-null `acceptedAt`,
`acceptedVersion`, and `acceptedHash`, with the hash equal to the accepted
checkpoint bytes. Genesis uses version `1` and null predecessor; every later
intent uses exactly predecessor version `+1`. CAS protects each transition,
idempotency returns the existing same-body intent, and restart recovery uses
submitted-or-accepted persisted state without manufacturing a new version.
P23-003F owns the implementation and CAS/idempotency/recovery fixtures.

Retention/Hold: `AuditRetentionState` is metadata-only and its current closed
wire fixes `schemaVersion: 1` and `kind: "audit_retention_state"`. `AuditHold`
requires an active genesis at version `1` with null predecessor, later active
versions are exactly `+1`, and `released` is terminal. A released genesis and
released re-activation are invalid. P23-003G owns retention/hold boundaries,
owner checks, and active/genesis/gap/release fixture coverage.

### Commands and Results

- `date --iso-8601=seconds` — PASS; Round27 checkpoint timestamp recorded as
  `2026-08-21T10:01:15+09:00`.
- Read-only `rg`, `sed`, `nl`, and `git status` inspections — PASS; required
  source-of-truth, Task/Report, Historical Round26, and current sections were
  checked before and after editing.
- Pure read-only current-wire and scope inspections — PASS; all four current
  roots, the ten-file management boundary, Stable/proposed boundary, and
  parent/child status strings are present.
- `git diff --check --` over the ten permitted files — PASS; no whitespace
  errors.
- Required Docker/Mago/build/Website/CI commands — NOT RUN by this worker;
  the assignment prohibits build/format/dependency mutation and the parallel
  Search/Blume worker owns Website checks. Round25's source-changing Phase 1
  build and complete Local Gate were run by the Orchestrator and remain
  historical evidence; they are not new Round27 results.

### Acceptance Criteria

- [x] Historical Round26 was recorded; the current management checkpoint has
  parallel Round28 Audit and Round27 Search lanes with exact statuses `Round28
  Audit Contract Correction Implemented / Sol Re-review Requested` and `Round27
  Search Post-review Correction Implemented / Review Requested — parent not
  Accepted`.
- [x] Query/cursor, Outbox, AnchorIntent, Retention, and Hold current wires are
  self-contained, exact, implementable, and assigned to existing owners and
  fixtures.
- [x] Round25 current-looking parent/child management text is explicitly
  Historical where retained; Stable `1.2` and proposed `1.3` boundaries remain.
- [x] Task/Report/STATE/TODO/README/D145/Specifications 105/106 and child
  Task/Report are synchronized.
- [ ] Parallel Search/Blume artifact checks and combined shared-worktree gate.
- [ ] Fresh separate General/Audit reviews and parent acceptance.

### Remaining Issues

Round26 management evidence is historical. The current parent has parallel
Round28 Audit and Round27 Search lanes. Fresh Sol Search and Audit reviews,
Orchestrator slice acceptance or bounded correction, fresh parent General
review, and P23-001 parent acceptance remain. P23-001R and ordered Phase 23
work remain after acceptance. No release, commit, push, or deployment is
claimed.

### Suggested Next Action

Historical Suggested Next Action: verify the combined Round26 scope plus the
parallel artifact patch, rerun the complete Local Gate, route fresh separate
General and Audit reviews, and record parent acceptance only after both
reviews. No external action was authorized.

## Historical Round 25 and prior summary

P23-001 Discovery is complete as a proposal package. Stable `1.2` already
provides FrankenPHP Worker Mode as the default HTTP runtime; D145 records the
Stable/Roadmap boundary, BlackOps-owned command projections, route and schedule
metadata rules, Application-owned `worker:list`/`worker:ls` names with normal
Symfony unknown-command behavior when unregistered, no Framework Job registry,
additional command priority, the
separate retention Lazy-definition correction, the separate-process Deferred
recommendation, the independent Classic migration, and the AuditRecord
boundary. Specifications 105 and 106 provide the normative unreleased
capability contract and Phase 23 delivery DAG. Round 24 is historical Changes
Requested. Round 25 is the sole current parent correction: exact projection
kind/origin mapping and negatives, self-contained Read/Access requestRef
vectors, one total Stage6/7 verifier precedence, conditional AnchorIntent
state/version rules, tenant-aware Outbox AAD, trusted Framework-clock cursor
key windows, and sole-current management wording. Round 23 is historical; Round
21 is the accepted child checkpoint. Round24 review lanes are General
`P1=1/P2=1/P3=0` and Audit `P1=5/P2=1/P3=0`, separately. Round22 values remain
only in Historical sections.

The correction passes make AuditRecord v1 one exact typed schema with an
explicit system partition and non-circular JCS/hashSuite hashes, independent
immutable checkpoint/anchor artifacts, complete-sealed export wire, trusted
freshness snapshot, and exact AuditExportRecord rules; add route `strategy`, record that
P23-002A owns implementation and independent review of the future bounded
`operation:list --strategy=inline|deferred` filter (exact built-ins only while
custom values remain in no-filter output) while preserving Stable no-filter
Human behavior, separate P23-003A core types from P23-003B JCS/chain append and
P23-003E application authoring, assign completed-record sealing to P23-003F,
bound auth/read response ordering and audit-of-audit, and distinguish
service/container isolation from same-container multiprocess packaging. The
filter and all other Phase 23 capabilities remain proposal-only and unreleased
until P23-006.

Round 7 Changes Requested is historical: General `P1=4/P2=1/P3=1` and Audit
`P1=6/P2=6/P3=0`; these lanes are not combined. Round 8 is Correction
Implemented / Review Requested and keeps the canonical delivery union at `required|durable`; the word
`best_effort` applies only to a separate observed JSONL projection with no
Audit Trail claim. The required `policy.auditDelivery` is selected before
effect/response, must equal the resolved catalog delivery, and missing,
omitted, unknown, or mismatched values fail closed; authentication definitions
always use durable. Conditional event families use separate durable/required
selectors selected before mutation or response, and unknown policy values fail
closed. Strict export preflight follows the one fixed Stage 0-7 order: Stage 0
validates bounded external context and expected-context hash (code `19`, zero
manifest/listed-file opens, null top references, `partitions:[]`); Stage 1
validates raw-manifest schema/JCS/count/version and declared lengths (code
`10|17`); Stage 2 compares `manifestHash`; Stage 3 validates the trusted key;
Stage 4 verifies Ed25519 with zero listed-file opens; Stage 5, only after
signature, checks root-confined actual lengths/sums/raw digests (code `13`);
Stage 6 validates the catalog; Stage 7 validates partitions and local records.
Global stages 0-6 return `partitions:[]`; only Stage 7 emits entries. Invalid
signature opens zero files and valid signature with bad actual length reaches
code `13`; valid signature with bad catalog reaches the catalog stage. Strict writer/export caps precede allocation/read/streaming;
constant-time `manifestHash` recomputation is completed before signature
acceptance, while `catalogHash` is recomputed only after authenticated file
open/raw-digest verification. Raw file SHA-256 remains a separate check. The exact
tenant mapping, trusted `AuditVerificationContext`, manifest-wide global
precedence, canonical partition ordering, evidence-field uniqueness, and
trusted `recordedAt`/`occurredAt` bound are now synchronized across D145 and
Specifications 105/106. Independent Round 6 evidence is not aggregated:
full review is `P1=3/P2=4/P3=1`, Audit review is `P1=2/P2=6/P3=1`.
Round 8 additionally fixes ingress `recordedAt` authority/high-water stamping,
canonical-only sequence, `AuditIngressEnvelope v1`, independent
`AuditOutboxRecord` BOPD/AAD ciphertext protection, synthetic complete-record
1 MiB cap at ingress/relay, non-null semantic tuples, <=256-byte typed
evidence, sealed framework namespace/owner authority, independent phase
validation, one verifier stage/result matrix, full freshness chronology,
requester-partition deny mapping, AnchorIntent crash recovery, 15/16 MiB
manifest caps, and exact underlying connection/root transaction checks.
Historical Round 8 remains Review Requested; it is not Green, Accepted, or
Decided.

Round 8 fresh review is historical Changes Requested: General `P1=2/P2=1/P3=0`
and Audit `P1=5/P2=4/P3=1`; these lanes are not combined. Round 9, Round 10,
and Round 11 are historical correction snapshots; Round 11 is historical
Changes Requested with General `P1=2/P2=2/P3=0` and Audit `P1=7/P2=3/P3=1`,
separately. Round 12 through Round 24 are historical; Round 25 is the sole
current parent Local Gate Green / Independent Review Requested checkpoint. The current contract uses one ProjectionBuildSet v1 wire,
dedicated command/route/schedule projections and exact flock publication/GC,
the optional `operation:list --strategy=inline|deferred` filter,
partition-global AuditSegmentState and canonical BOPD/AAD (232-byte
`5930076af5418572a4e575943172884775428ad10d024de7eb9fc419bce04b1c` and
344-byte `f3ef3d96598964f7087ffe654ecde4d2e828caebfa981ecb1daa82859ff7c57f`
goldens), no v1 outbox re-encryption, Stage 0-7 plus external expectedManifest,
F/G ownership separation, four-surface `.unavailable` tuples,
AuditAccessContext, metadata-only AuditRetentionState, and signed
`{role,artifactId}` identity. Stable `1.2` remains current; no 1.3 capability
is delivered.

Round 3 made the export graph implementation-ready. Round 4 narrows its
boundary: each partition has a closed genesis or `selected_range`
`startBoundaries` union with the same exact eight keys; genesis alone reports
`prefixComplete:true`, while a selected range declares unresolved predecessor
values and verifies only its included chain from the first artifact. It makes
no predecessor-existence, predecessor-continuity, prefix/pre-range, or
deletion-legitimacy claim. Audit v1 has no deletion fields,
does not delete sealed bytes or keys, and defers physical deletion and signed
deletion manifests to future P23-003H without a compliance claim. The round
also adds closed domain-hashed AuditEventCatalog v1 with required
`event.catalogHash`, explicit `evidenceVersion`, finite caps and bounded
flat-primitive evidence, one exact-once `event_catalog` file per distinct
covered catalog hash (mixed catalog hashes are valid), and distinct `current`/
`as_of_checkpoint` verifier result contracts. Semantic catalog/checkpoint/receipt hashes remain distinct from
raw LF-terminated file digests. Export-key expiry/revocation fails closed even
when a manifest self-declares an older time, and reverse time order fails. All
canonical nested objects are closed, event evidence is registry-closed, and
credentials, tokens, raw request data, throwables, ciphertext, and key material
are prohibited throughout the canonical body.

Round 2 also records the current Stable positive correlation boundary:
Structured Log/OpenTelemetry Operation/Correlation/Trace/Span correlation and
`ExecutionScopedLogger`/Observability already exist. Only the new four-surface
Audit correlation contract is future-only; it does not relabel current Stable
records as general AuditRecords. Independent acceptance remains pending. Round
4 review was Changes Requested (P1=1/P2=3/P3=0); the reviewer covered the
same four candidates and found no blocker in other lanes before the review was
interrupted. This is not Green or Accepted.

No Production Code, tests, public Guide, Website, release authority, generated
artifact, workflow, dependency, package, tag, deployment, or publication was
changed. No commit, stage, push, PR, CI dispatch, or remote repository command
was performed.

## Historical Round 22 Correction Summary

Round 21 is the accepted child checkpoint; Round 22 was the bounded parent
correction with status `Round 22 Correction Implemented / Review
Requested`. Parent General `P1=2/P2=2/P3=0` and Audit `P1=4/P2=2/P3=0` remain
separate and are not aggregated. The parent is not Green, Accepted, Decided,
or Released.

The current nine-root contract is: Application-owned/unreserved `worker:list`
and `worker:ls` names with normal Symfony unknown-command behavior when
unregistered and no Framework Job registry; one shared-lock reader validation
of the root and all three ProjectionBuildSet files, including non-selected
mismatch invalidation and list/route/schedule exit mapping; P23-001S current
`Round 21 Accepted` with historical reopen evidence preserved; public
`/operations/{operationId}` and structural placeholder collision identity;
exact operation-read and service-action access-event families with their
required/durable delivery; genesis sequence `1`, later prior-final-plus-one,
`recordCount=lastSequence-firstSequence+1`, and `1-42`/`43-50` fixtures; the
finite exact control-stream signing exemption; one closed AuditHold v1 object
with exact hash/CAS/cap rules; and one closed AuditReadContext v1 with exact
requestedAt/nonce/requestRef formula, no target, and Node/Docker-PHP golden
parity fixtures.

## Historical Round 23 Current Correction Summary

Round 23 is the sole current parent checkpoint with status `Round 23
Correction Implemented / Review Requested`. Round 22 is historical Changes
Requested with parent General `P1=3/P2=1/P3=0` and Audit `P1=4/P2=0/P3=0`;
the lanes remain separate. P23-001S remains the accepted Round 21 child.

The eight current roots are: Application-owned and unreserved `worker:list` /
`worker:ls` names with normal Symfony unknown-command behavior when unregistered
and no Framework Job registry; complete root-plus-all-three ProjectionBuildSet
validation under one shared lock before selected-row rendering; canonical
CI Mago evidence and exact final hashes; sole-current Round 23 management
status; required delivery and durable canonical append before operation-read
response/failure; genesis-only sequence one with later prior-final-plus-one and
`1-42`/`43-50` examples; metadata-only `AuditRetentionState v1` with exactly
`retained|eligible_for_future_deletion` and no embedded hold, plus separate
append-only `AuditHold`; and the exact SHA-256-wrapped `AuditReadContext`
requestRef with algorithm-substitution negative fixture.

## Changed Files

- `develop/orchestration/tasks/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md`
- `develop/orchestration/reports/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md`
- `develop/STATE.md`
- `develop/TODO.md`
- `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`
- `develop/spec/105-blackops-1-3-capability-contract.md`
- `develop/spec/106-phase-23-delivery-plan.md`
- `develop/spec/README.md`

## Decisions and Assumptions

- P23/1.3 remains an unreleased proposal; Stable `1.2` current claims remain
  unchanged.
- Round 8 preserves Stable `list` as a current name. The replacement accepts
  all Stable namespace/raw/short/format combinations, uses last-wins repeated
  format, treats `--json`/`--all` as new inputs, makes `--json` alone JSON,
  keeps raw txt-only and short an exact no-op, and assigns multi-segment
  nested-exact, unique-abbreviated, ambiguous, and unknown namespace
  diagnostics/exit to Symfony Stable `1.2.0` goldens. No blanket namespace
  exit `2` is introduced. Its embedded framework recovery projection is always
  available; application rows merge only from same-build/current/valid
  artifacts, and structured status is exactly
  `available|absent|malformed|unsupported|stale|build_mismatch`; list/ls application
  recovery is exit `0`, operational read failure is exit `1`, and complete
  route/schedule artifact failure is exit `2`. Human/raw/JSON/XML/Markdown
  rows are one deterministic safe projection; XML/Markdown v1 are exact
  UTF-8/LF/no-BOM wires and `--short` is an exact no-op.
- One P23-002A/global registry reserves and collision-checks both canonical and
  alias names `list`, `ls`, `help`, `completion`, `_complete`, `route:list`,
  `route:ls`, `schedule:list`, `schedule:ls`, `diagnostics:check`,
  `queue:status`, `about`, and `outbox:status`; B/C/D/E reuse it. Collisions
  report owner/name and actionable rename migration. `worker:list` is not
  reserved.
- Round 8 canonical ingress uses the closed `AuditIngressEnvelope v1` with
  exact `kind=audit_ingress_envelope`, AuditRecord-without-integrity logical
  bytes, fixed contentHash/recordedAt bindings, 64-lowercase-hex contentHash,
  authoritative UTC recordedAt with six fractional microseconds, positive
  JSON-safe ingressOrdinal, and NFC/no-control non-empty idempotencyKey <=256
  UTF-8 bytes; recordId/partitionId use their AuditRecord/partition bounds.
  Trusted ingress-only `recordedAt` uses `max(now,lastAssignedRecordedAt)`,
  with canonical-only sequence and outbox FIFO `ingressOrdinal`. Durable
  storage uses BOPD `AuditOutboxRecord` with length-prefixed AAD binding
  schema/version, record ID, partition, tenant binding, ordinal,
  idempotency-key hash, and content hash; ordinary retry reuses ciphertext.
  Complete-record synthetic max uses the actual logical body and partition ID;
  synthetic max and actual cap+1 fixtures enforce 1 MiB inclusive at ingress
  and relay.
- Catalog tuples require non-null/non-empty bounded resource/outcome/reason
  values; evidence strings are <=256 UTF-8 bytes and typed
  `safe_code|opaque_id`. Framework definitions come from a sealed provider;
  application owner is forced by trusted registry/build identity. Reserved
  framework namespaces are `security.`, `access.`, `retention.`, `storage.`,
  `observer.`, `outbox.`, `audit.`, `configuration.`, `control.`, `replay.`,
  and `rotation.`. Each record validates its phase independently; action ID is
  correlation only.
- The verifier uses one Stage 0-7 total order (external context/code 19 with
  zero manifest/listed-file opens, manifest, hash, key, signature, actual raw
  files, catalog, partition), with
  global `partitions:[]`, non-null endpoint refs for emitted local failures,
  complete freshness chronology, 15/16 MiB manifest caps, requester-partition
  deny/absent mapping, exactly three non-overlapping AnchorIntent crash points
  (before external accept; after accept/before receipt persist; after receipt
  persist/before ack) with same retry receipt/version and no N+1, and exact connection/root
  transaction identity before required effect.
- Symfony's built-in list/descriptor output is implementation detail. The
  Stable `1.2` already has Symfony-backed `list`; the future `list`/`ls`
  contract is a dedicated compiled safe projection with `ls` as the only new
  name. P23-002A preserves optional namespace, `--raw`, `--short`,
  `--format=txt|json|xml|md`, and `--json` as `--format=json`; only new
  `--json` plus an explicit `--format` exits `2`. Reserved-name and existing
  application command/alias collisions fail closed with actionable owner/name
  migration; Stable collision-free and
  upgrade-collision fixtures belong to P23-002A.
- `route:list` and `schedule:list` open and validate the shared
  `ProjectionBuildSet v1` root plus all three projection files under one
  shared lock before selecting/rendering their dedicated rows. P23-002B
  combines Operation routes and the fixed status descriptor at build time, and
  the public `Application::http()` positive composition includes
  `GET /operations/{operationId}`. Structurally equivalent `/operations/{id}`
  is a collision. Low-level nullable `ProductionRuntimeComposer`
  composition is not the public effective-route target. Non-selected mismatch
  invalidates the complete set; both commands fail closed and avoid live
  application resolution.
- `worker:list` and `worker:ls` are neither BlackOps-owned nor reserved. An
  Application may own either spelling as a canonical command or alias; an
  unregistered spelling follows normal Symfony unknown-command behavior. No
  Framework JobDefinition registry or built-in Job listing exists. Deferred
  definitions, aggregate queue status, and future process capability remain
  distinct surfaces.
- `diagnostics:check`/aggregate `queue:status` precede `about`/`outbox:status`;
  `dead-letter:list` remains deferred.
- The existing retention Lazy omission of `idempotency-record-days` is a
  separate bounded Stable correction, not a P23-001 or Audit Trail fix.
- Stable Worker Mode is an existing default HTTP runtime. P23-004A hardens it
  into the sole supported production HTTP profile and gates Classic fallback/
  removal/migration; P23-004B Extension Worker and same-process Deferred remain
  proposal-only.
- Deferred is recommended as a separate service/container from HTTP for the
  strongest process, resource, lifecycle, and deployment isolation.
  Same-container multiprocess packaging preserves OS-process PHP crash
  isolation but shares cgroup, container lifecycle, rollout/rollback, and
  resource ceilings, requiring supervision and signal forwarding. A direct
  CLI/PCNTL loop transplant into standard FrankenPHP HTTP Worker Mode is
  rejected; an optional Extension Worker spike is non-blocking and fails on
  native crash/OOM HTTP coupling.
- Classic removal is independent of same-process Deferred and requires its own
  bootstrap, failure, readiness, state/memory, upgrade, same-SHA, rolling,
  and rollback gates. `Application::http()` remains.
- Application Log, Observability, Lifecycle Journal, and Audit Trail remain
  distinct. In the 1.3 target JSONL `kind=audit` is derived from AuditRecord;
  current Stable narrow records are not retroactively general AuditRecords.
  Stable Structured Log/OpenTelemetry already provide current
  Operation/Correlation/Trace/Span correlation and `ExecutionScopedLogger` is
  a current safe observability projection. The new four-surface Audit
  correlation contract is future-only. Trace correlation inside that future
  contract is optional and sampled, and is never canonical Audit evidence.
- AuditRecord v1 has one exact schema: top-level `schemaVersion`, `kind`,
  `recordId`, `event`, `action`, `actor`, `resource`, `outcome`, `policy`,
  `tenant`, `time`, `source`, `correlation`, `evidence`, and appended
  `integrity`. It is encrypted with new BOPD purpose `AuditRecord`,
  tenant-aware before decrypt, append-only, idempotent by ID/contentHash, and
  chained/sealed with independent immutable JCS/Ed25519 checkpoint and anchor
  artifacts. `actor.principal` is a typed object or null for invalid/unknown/
  unauthenticated context; `actor.execution` is required. The current
  `HttpAuthenticator` has no module/method ID, so no identifier is invented;
  only provider-explicit typed provenance is optional. Required and durable
  AuditRecord delivery and separate observed-projection
  behavior have different guarantees, and auth/read
  decisions commit a dedicated local append/outbox before response or
  disclosure (allow failure fails closed without disclosure; deny/invalid
  failure remains denial).
  The target is audit-ready-by-default, not automatic compliance or
  non-repudiation.

### Historical Round 9 bounded correction decisions

- Schedule complete projections have their own exact exit matrix: `schedule:list`
  and `schedule:ls` only are identical (`0` valid, `1` operational read, `2`
  missing/malformed/unsupported/stale/build mismatch). `list`/`ls` application
  artifacts use framework recovery at `0` for all six statuses, with trusted
  expected schema/kind/build/input-hash identity and ordered combined
  precedence.
- XML v1 and Markdown v1 are single canonical byte streams with no BOM or
  inter-element/blank whitespace, one final LF, exact escaping, and the Spec
  105 SHA-256 goldens
  `579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968` and
  `7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`.
- Evidence descriptors are closed six-field objects with mandatory
  `classification`; safe-code grammar and derived `oid:v1:<64 lowercase hex>`
  opaque IDs are typed wires, not a cryptographic secret-absence claim.
  `AuditIngressEnvelope v1` is durable-only; `AuditOutboxRecord v1` owns exact
  clear indexes, ciphertext body, length-prefixed AAD, idempotency/ordinal
  head/FIFO, poison/fencing, and same-ciphertext retry semantics.
- The verification total function has mode/context nullability and one exact
  Stage 0-7 order: Stage 0 mode/standalone context/hash code `19` with zero
  manifest reads; Stage 1 raw manifest/schema/JCS/count/version/declared
  lengths `10|17`; Stage 2 manifest hash; Stage 3 trusted key; Stage 4
  signature `11` with zero file opens; Stage 5 root-confined actual bytes
  `13`; Stage 6 authenticated catalog/mode-context binding/freshness heads
  (`18|16`); Stage 7 partition semantics. Global failures return
  `partitions:[]`; Stage 4 signature success is the first point at which
  exportId/manifestHash become non-null (signature failure keeps both null),
  and full signed endpoint refs are required for emitted entries; Stage 1 rejects
  malformed endpoint descriptors and Stage 7 cross-checks authenticated refs
  against bodies, with missing/malformed receipt bodies returning code `15`
  and non-null manifest refs. AnchorIntent
  v1 is closed/fenced with deterministic receipt bytes and three non-overlapping
  crash points. All authenticated artifacts require chronology
  `checkpoint.sealedAt <= checkpoint.signedAt <= receipt.anchoredAt <=
  receipt.signedAt <= freshness.observedAt <= verifiedAt < validUntil`, with
  equality and one-microsecond-after fixtures. Checkpoint/control events use the distinct audit-control
  stream; retention is metadata/eligibility only and physical deletion is
  future P23-003H.

## Command Matrix

| Candidate | Source/metadata | Public result | Discovery decision | Implementation |
| --- | --- | --- | --- | --- |
| `list` / `ls` | dedicated compiled command projection; Stable Symfony-backed `list` input compatibility | Human/raw/JSON/XML/Markdown safe projection; optional namespace; `--raw`; `--short`; `--format=txt|json|xml|md`; `--json` alias; hidden policy | adopt exact alias; reserve built-ins; fail-closed actionable collision migration | P23-002A implements/reviews compatibility and deferred filter |
| `route:list` / `route:ls` | dedicated compiled route projection with fixed descriptor combined at build time | deterministic Human/JSON v1 with `strategy:string|null` | no runtime HTTP join or source/provider/DB read | P23-002B within atomic A/B/C slice |
| `schedule:list` / `schedule:ls` | dedicated compiled schedule projection | deterministic Human/JSON v1 | no direct Operation Manifest reader, due/provider/clock/maintenance read | P23-002C within atomic A/B/C slice |
| `worker:list` / `worker:ls` | Application command/alias ownership; no Framework Job registry; deferred Operation metadata | canonical/alias if registered; normal Symfony unknown-command if unregistered | not BlackOps-owned/reserved; use the future bounded `operation:list --strategy=inline|deferred` filter for Operation definitions | P23-002A owns Application-canonical/alias positives, unregistered negative, no-Job-row, and filter fixtures |
| `diagnostics:check` | bounded artifacts/readiness/diagnostics | safe check codes | first additional priority | downstream P23-002D |
| `queue:status` | aggregate queue snapshot | counts/ages only | first additional priority | downstream P23-002D |
| `about` | safe build/runtime capability metadata | bounded capability report | second priority | downstream P23-002E |
| `outbox:status` | aggregate outbox snapshot | bounded counts/ages | second priority | downstream P23-002E |
| `dead-letter:list` | not yet defined | deferred | requires names/tenant/page/redaction | no current task |

## Runtime Failure Matrix

| Failure/event | Separate Deferred service/container | FrankenPHP HTTP Worker | 1.3 contract/decision |
| --- | --- | --- | --- |
| missing/stale compiled artifact | worker startup fails closed | HTTP startup fails closed | no source/reflection fallback |
| Deferred startup failure | supervisor restarts worker; HTTP remains serving | not in HTTP process | process isolation required |
| HTTP worker startup failure | unrelated to Deferred | FrankenPHP startup/backoff policy | safe deployment/readiness gate |
| one request Throwable | worker claim settlement/retry policy | callback catches; next request continues | preserve existing HTTP safe 500 and cleanup |
| Deferred handler failure | supervision/retry/dead-letter | not run in HTTP worker | separate loop/DB lifecycle |
| DB outage | Deferred claim/heartbeat failure and recovery | HTTP readiness/request failure policy | no cross-process contamination |
| heartbeat/lease loss | claim lost; no completion; lease recovery | not applicable | preserve PCNTL/lease contract |
| SIGTERM/SIGINT | stop claim, grace period, natural lease recovery | HTTP server lifecycle | separate signal/rollback evidence |
| memory growth | bounded worker restart/supervisor policy | `max_requests`/memory gate | load and multi-request evidence required |
| repeated worker crash | external supervisor/backoff | FrankenPHP worker backoff; too-many-failures boundary | do not couple failure domains |
| same-container multiprocess | OS-process PHP crash isolation | shared cgroup/container lifecycle/rollout/rollback | supervision and signal forwarding required; no independent resource/deploy claim |
| Extension Worker native crash/OOM | not applicable | may affect serving process | optional spike fails architecture if HTTP falls |
| Classic removal/upgrade | independent | existing Worker Mode sole-profile hardening/migration | `Application::http()` escape hatch preserved |

Stable `1.2` already provides the standard FrankenPHP Worker as the default HTTP
runtime. P23-004A hardens that existing default into the sole supported
production HTTP profile and gates Classic fallback/removal/migration. The
standard FrankenPHP Worker is request-driven. Extension Workers provide
background PHP thread pools via a Go extension and optional Caddy module, but
remain inside the FrankenPHP process. This is why separate services/containers
are recommended for strongest isolation and why same-container multiprocess is
only an optional packaging arrangement for proposed Deferred execution.

## Audit Boundary/Capability Gap

| Surface | Current Stable role | P23/1.3 boundary |
| --- | --- | --- |
| Application Log | diagnostic/application messages | not canonical audit evidence |
| Observability | Structured Log, metrics, W3C trace correlation | trace is optional/sampled; not identity/integrity/evidence |
| Lifecycle Journal | Operation lifecycle and replay truth | never duplicate every event into Audit Trail |
| Audit Trail | narrow retention/replay/rotation audit-like records only | new dedicated encrypted tenant-aware append store |
| JSONL `kind=audit` | future 1.3 derived projection from AuditRecord; current Stable narrow records remain separate | never canonical AuditRecord |

P23-003A owns the core event catalog, typed data types, and safe projection
shapes. P23-003B uniquely owns RFC 8785 JCS logical bytes, contentHash,
recordHash, append sequence, chain append, and same-ID idempotency.
P23-003E owns application registration/projector authoring and the reference
journey; P23-003F consumes completed records for sealing/checkpoint/anchor/
trust/offline verification. The future AuditRecord catalog must distinguish
framework-owned semantics (retention/hold, rotation, replay, outbox retry,
privileged audit/access/config/security) from application-owned business
actions. Business CRUD/refund/approval/consent/regulated access requires a
versioned event definition and typed projector. Handler completion is not
enough to infer action/resource. P23-003D additionally owns adapters and
independent review for current `AuthenticationMiddleware`,
`AuthorizationEvaluator`, and protected Operation Status/Journal/Outcome/
diagnostic data-read boundaries. The current `HttpAuthenticator` exposes no
module/method ID, so the catalog does not invent one; provider-explicit typed
provenance is an optional P23-003A extension. `actor.principal` is a typed
object or null and `actor.execution` is required. Credential/session
issue/revoke is Application-owned unless a later Framework authenticator/
session module owns that semantic.

The concrete framework security/access catalog includes
`security.authentication.succeeded` and `.invalid`,
`security.authorization.allow` and `.deny`, and
`access.operation_status|operation_journal|operation_outcome|operation_diagnostics`
with `.requested`/`.unavailable`/`.accessed` variants. Safe
reasons are authenticator-invalid/expired/malformed,
authentication-required/forbidden/policy-rejected, authorized/denied, and
resource_unavailable. Safe evidence is limited to provider-explicit typed
provenance when present, principal type/ID when authenticated, policy,
operation/resource type, tenant partition, purpose, and absent-ID outcome.
Anonymous is not automatically an event. Credentials, tokens, request bodies,
and protected payloads are never evidence; Operation/Trace may be absent.
Operation-read event definitions register required catalog delivery for all
`.requested`/`.unavailable`/`.accessed` outcomes, and the canonical append is
durably persisted before a read response or failure is released. Service and
other conditional event families may select `required` or `durable` only from
an explicit policy/catalog selector; missing or omitted operation-read
requirements do not default to durable, and no event downgrades to an observed
projection. Fixtures cover
authenticated/invalid actor-null, unauthenticated deny, anonymous no-event,
allow/deny, privileged-read allow/deny, absent-ID, credential-nonrecording,
and response ordering/audit-outage behavior.

`AuditReadContext v1` request identity is exactly
`requestRef = "oid:v1:" || HEX_LOWER(SHA-256(LP(domain) || LP(JCS(context))))`,
where `domain="blackops.audit.read.request-ref.v1"` and `context` is the
closed `{purpose,actor,execution,tenant,requestedAt,nonce}` object. The LP
concatenation is the SHA-256 preimage, not the final reference. The independent
Node/Docker-PHP golden is JCS `150` bytes, LP preimage `192` bytes, and
`oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`;
an algorithm-substitution negative must fail.

Canonical event delivery is fixed to `required|durable`: the versioned policy
evaluator assigns required `policy.auditDelivery` before an effect or
response, only explicit `required` selects the required selector, and only
explicit `durable` selects the durable selector. Missing, omitted, or unknown
policy requirements fail closed; none is defaulted. The recorded
`policy.auditDelivery` must exactly equal the resolved catalog definition's
`delivery`. Authentication success and invalid definitions always set
`durable`. A runtime override or downgrade to an observed projection is
invalid; `best_effort` belongs only to the separate non-canonical JSONL
surface. `event.category`, `action.type`, and `source.owner` must equal the
resolved catalog definition, and mixed catalog hashes are valid only when
each record resolves to its own catalog. Missing, omitted, unknown, and
mismatched `policy.auditDelivery` values are negative fixtures across four
policy input paths.

AuditRecord v1 has one exact top-level schema: `schemaVersion=1`, `kind=audit`,
`recordId`, `event{type,version,category,catalogHash}` with category
`business|security|access|configuration|control|retention|replay|rotation|outbox`,
`action{id,type,phase}` with phase
`requested|started|committed|succeeded|denied|failed|unknown`,
`actor{principal,execution}`, `resource`, `outcome{status,code}` with status
`succeeded|denied|failed|unknown`,
`policy{id,version,decision,reasonCode,auditDelivery}` with
`decision=allow|deny|not_applicable` and `auditDelivery=required|durable`,
`tenant` object or `null`,
`time{occurredAt,recordedAt}`, `source{owner,component,runtime,buildId}` with
owner `framework|application` and runtime `http|cli|worker|scheduler|relay|replay`,
`correlation`, versioned safe `evidence`, and appended `integrity`.
Tenant null maps to the explicit `system:global` partition. A normalized NFC
tenant `{type,id}` maps to `"tenant:" || HEX_LOWER(SHA-256(ENC(
"blackops.audit.partition.tenant.v1") || ENC(JCS({"type":type,"id":id}))))`;
writer/verifier and `integrity.partitionId` use the same mapping, with
separator/Unicode/system-collision negatives. Every canonical
nested object except versioned evidence is closed: event/action/actor/resource/
outcome/policy/tenant/time/source/correlation/integrity have the exact allowed
keys, and `evidence` accepts only `version` plus the registered closed
event-definition members. Credentials, tokens, raw payload/request bodies,
throwables, ciphertext, and key material are prohibited anywhere in the
canonical body. Unknown nested/evidence members are rejected; registered
control evidence is the positive fixture. Each Record validates its phase
independently; action ID is correlation only and v1 makes no cross-record phase
continuity/order claim. Same ID and content hash is idempotent; same ID with
another content hash fails integrity.

`AuditEventCatalog v1` is a closed domain-hashed artifact with exactly
`schemaVersion`, `kind`, `catalogId`, `catalogVersion`, `owner`, `events`, and
derived `catalogHash`; each event definition has only `type`, `version`,
`evidenceVersion`, `category`, `actionTypes`, `semanticTuples`, `evidenceFields`,
`maxEvidenceBytes`, and `delivery`. `semanticTuples` is a 1..32 unique list
of exact `{actionType,phase,resourceType,outcomeStatus,outcomeCode,
policyDecision,policyReasonCode}` objects; strings are NFC/no-control bounded,
every tuple value is non-null and non-empty, NFC/no-control, and bounded by
its v1 action registry, phase/status/decision enum, or resource/outcome/reason
code (wildcard is forbidden), tuple actionTypes equal `actionTypes`, and writer/verifier require full tuple equality
with the record. Each field descriptor is exactly
`{name,type,required,nullable,maxUtf8Bytes,classification}`, with type
`string|integer|boolean`; string classification is `safe_code|opaque_id`,
integer/boolean classification and caps are `null`, evidence string caps are
positive and at most 256 UTF-8 bytes, descriptor name `version` is reserved
and rejected, and `maxEvidenceBytes` is positive and at most 8192. Missing or
unknown classification, non-null integer/boolean classification, bad safe-code
grammar, and raw opaque-token fixtures fail while valid safe/derived-opaque
fixtures pass. Catalogs have at most 256 events, 32 action types, 32
evidence fields per event, and 1,048,576 unsigned JCS bytes. Evidence values
are bounded flat primitives and `evidence.version` must equal
`evidenceVersion`; evidence names are NFC-normalized-byte unique and
`maxEvidenceBytes` measures UTF-8 bytes of RFC 8785 `JCS(evidence)` including
version; unknown/nested/list/object/float/non-finite members fail.
Event/action strings are normalized UTF-8, non-empty, at most 128 bytes, and
contain no control characters. Each record's `event.category`, `action.type`,
and `source.owner` must match its resolved catalog definition/category,
actionTypes, and owner. The catalog definition's delivery is fixed: P23-003C
must choose required/durable from the catalog before mutation or response, with
no runtime override; a different mode requires a new event
version/evidenceVersion. The offline verifier resolves this delivery from the
record's catalog.
`catalogHash` uses
`H(ENC("blackops.audit.catalog.v1") || ENC(JCS(unsignedCatalogBody)))`.
The immutable selector is `(catalogHash,event.type,event.version,evidence.version)`;
within one catalog `(type,version,evidenceVersion)` is unique. The same
selector is idempotent only for identical bytes and fails closed on schema
redefinition. P23-003A owns the catalog/schema and safe projection;
P23-003E owns application registration and projectors; P23-003F owns reusable
catalog/hash verification; P23-003G binds one exact `event_catalog` file per
distinct covered `catalogHash` and its raw-vs-semantic hash distinction.

The export parser first bounds unsigned manifest body to <=15 MiB and complete
stored raw manifest JCS plus LF to <=16 MiB, before allocating buffers or reading files, then preflights
the exact v1 caps: 1..256 partitions; 1..4,096 total
segments with at least one per partition; exactly one start boundary per
partition; 1..1,024 distinct catalogs; exactly `3*segments+catalogs` files
and <=13,312 files; 1..100,000 records per segment and 1..10,000,000 total;
1..1 GiB per file and an unsigned JSON-safe, overflow-checked <=16 GiB byte
sum equal to actual bytes. Each complete canonical AuditRecord JCS line
including LF is <=1 MiB before writer append/commit or JSON allocation;
checkpoint and anchor receipt raw files are each <=1 MiB, event-catalog raw+LF
files are <=2 MiB while unsigned catalog JCS is <=1 MiB. Every cap is
inclusive and cap+1 is rejected by bounded streaming; relative POSIX paths
<=512 UTF-8 bytes with <=128-byte components and no controls, no absolute
path, no backslash, no `..`, and no empty component;
and normalized safe IDs/refs/purpose/selection/media/component/build values
<=256 bytes. `maxEvidenceBytes` is the UTF-8 byte length of RFC 8785
`JCS(evidence)` including `version`. Zero, cap, cap+1, overflow, oversize,
and actual-byte mismatch are rejected before streaming.

Verification uses one fixed Stage 0-7 order (no reverse or alternate order).
Stage 0 validates mode, the strict bounded external `VerificationContext`, and
`expectedContextHash` syntax/recompute/pin (code `19`, all top references null,
zero manifest/listed-file opens, and `partitions:[]`). Stage 1 bounds the raw
manifest and validates strict schema/JCS/count/version and declared lengths
only (code `10|17`); Stage 2 compares `manifestHash`; Stage 3 resolves the
trusted export key; Stage 4 verifies Ed25519 (code `11`, zero listed-file
opens); Stage 5, only after signature success, opens root-confined files and
checks actual byte lengths/sums/raw digests (code `13`); Stage 6 validates the
catalog graph and semantic hashes; Stage 7 validates partitions, records,
checkpoints, anchors, freshness, and scope (codes `14|15|16|18`). Global
stages 0-6 return `partitions:[]`; only Stage 7 emits entries, and every
emitted local failure has four non-null endpoint references. Invalid signature
plus bad actual bytes opens zero files and returns code `11`; valid signature
plus a bad actual length reaches code `13`. Each unsigned catalog `catalogHash` is
recomputed and matched to stored hash, every record `event.catalogHash`, and
file `artifactId`; raw file SHA-256 is independent. The closed manifest-wide
result emits `partitions:[]` for any schema/version/bounds/hash/key/signature/
global-file/shared-catalog failure; only after global acceptance does it emit
every canonical-byte-sorted partition entry. `AuditVerificationContext v1` binds `verifiedAt` and
`contextHash`; verifier invocation requires external `expectedContextHash`
(64 lowercase hex), never bundle/context-provided. Missing, mismatched, or
substituted context is code `19 verification_context_invalid`, with null result
refs and `partitions:[]`; context-valid but unauthenticated manifests retain
only `verifiedAt`/`contextHash`, while authenticated partition-local failures
retain all four refs and bind the exact manifest. Current mode requires the
trusted freshness snapshot/head, exactly one head per manifest partition,
and as-of mode is the distinct checkpoint snapshot scope. The closed
`AuditVerificationResult v1` shape is exactly
`{schemaVersion,kind,mode,status,code,scope,exportId,manifestHash,verifiedAt,
contextHash,partitions}`; global failures use `partitions:[]`, and a
partition-local failed entry has non-null start/end checkpoint/receipt refs.
Its closed `trustStoreSnapshot` is `{ref,hash,observedAt,validUntil,entries}` with unique
unsigned-UTF-8-keyRef sorted entries exactly
`{keyRef,role,publicKey,publicKeyFingerprint,notBefore,notAfter,revokedAt,status}`;
fingerprint is SHA-256 of the exact decoded 32-byte Ed25519 key and is unique
snapshot-wide, with same-material cross-role aliases rejected. The trust
semantic hash excludes only the stored `hash` property; the freshness semantic
hash likewise excludes only its `hash`, and `contextHash` excludes only its
`contextHash` property. A non-null snapshot requires
`observedAt <= verifiedAt < validUntil`. Active accepted entries have
`revokedAt:null` and `notBefore <= signedAt <= verifiedAt < notAfter`; revoked
entries require non-null `revokedAt <= observedAt` and reject, while expired
entries require `revokedAt:null` and `notAfter <= verifiedAt` and reject.
Malformed/internally inconsistent context or snapshot shape/hash/time-order is
Stage 0 code `19`; export-key availability/role/status is Stage 3 global code
`12`; authenticated signedAt key-window failure is Stage 4 global code `12`
with non-null export refs; checkpoint/anchor key/signature/chronology is Stage
7 local code `15`. Missing/expired/stale current freshness is Stage 6/7 code
`18`, while non-null as-of freshness is code `16`. Global failures use
`partitions:[]`.
The exact non-circular formulas are
`trustHash = H(ENC("blackops.audit.trust-store-snapshot.v1") ||
ENC(JCS(unsignedTrustStoreSnapshot)))`,
`freshnessHash = H(ENC("blackops.audit.freshness-snapshot.v1") ||
ENC(JCS(unsignedFreshnessSnapshot)))`, and
`contextHash = H(ENC("blackops.audit.verification-context.v1") ||
ENC(JCS(unsignedContext)))`, where each unsigned body excludes only its own
stored hash property.

Canonical body protection uses BOPD `AuditRecord`; bounded clear indexes and
explicit blind indexes are allowed only for defined queries. Authorize before
decrypt and deny cross-tenant reads indistinguishably. Required means same DB
transaction, durable means immutable identity/bindings/protected envelope plus
fenced state-field mutation and at-least-once same
ID/contentHash relay, and the observed JSONL projection is non-canonical with
no Audit Trail claim. For auth/read
decisions without a business mutation, a dedicated local append/outbox
transaction commits before response or disclosure; allow failure discloses no
data and fails closed, while deny/invalid failure remains denial. No external
exactly-once/atomicity claim is made. Privileged required mutations fail closed.

Required `recordedAt` is stamped once by the canonical store during canonical
append; durable `recordedAt` is stamped once by trusted local Audit Outbox
ingress. Each authority/partition uses `max(now,lastAssignedRecordedAt)` in
the stamping transaction. Canonical sequence allocation occurs only at
canonical append and is the sole chain/order/pagination cursor; recordedAt is
trusted ingestion/retention time, not an ordering claim. Relay/retry preserve
recordedAt, recordId, and contentHash. Producer override, clock rollback,
delay, retry, and replay are fixtures.

Per-tenant-or-system/concrete-segment chains use RFC 8785 JCS and exact
`hashSuite: audit-sha256-jcs-v1`, `partitionId`, `segmentId`, positive sequence
starting at 1/incrementing by 1, `previousRecordHash` null only for the first
record (32 zero-byte first preimage), and 64-lowercase-hex content/record
hashes. Duplicate keys, nonfinite/float values, >53-bit integers, invalid
Unicode, and non-JCS input fail. Canonical bytes exclude integrity and use
unambiguous domain/length encoding; first previous/content digests are encoded
as exact 32-byte values. Sealing never mutates a record.
Independent immutable AuditCheckpoint v1 covers exactly one complete sealed
concrete segment. Only a genesis segment starts at sequence `1`; every later
segment starts at the previous segment's final sequence plus one, and
`recordCount=lastSequence-firstSequence+1`. The current positive sequence
examples are `1-42` followed by `43-50`; gap/overlap negatives are required.
Partition-global no-gap/no-fork/no-overlap checkpoint continuity,
`checkpointHash`, `signedAt`, and `chainHeadHash` equal to the last recordHash
(not a Merkle root); it never mutates a record. AuditAnchorReceipt v1 has
persist-before-ack crash idempotency: same `checkpointId` plus identical
complete bytes/hash succeeds idempotently; same ID with different bytes/hash
or a second checkpoint for one segment is integrity failure.
exact anchor object/version/hash fields, `anchorReceiptHash`,
`checkpointBytesHash` over complete signed checkpoint JCS without LF, separate
Ed25519 role keys. `AuditVerificationContext.freshnessSnapshot` is the sole
v1 current authority: it is `{ref,hash,observedAt,validUntil,heads}` and each
head is the exact `{partitionId,anchorStoreRef,anchorObjectRef,
anchorObjectVersion,anchorReceiptHash}` authority. Every current end receipt
must match the trusted head's exact triple/version/hash and satisfy
`observedAt <= verifiedAt < validUntil`. Anchor identity and version
continuity use the exact triple `{partitionId,anchorStoreRef,
anchorObjectRef}`; two stores sharing an object ref are separate chains and
cross-store predecessor/freshness bindings fail. Anchor versions start at 1
and advance exactly +1 with predecessor hash continuity; a fresh head cannot
waive a missing intermediate receipt. URL/ETag/WORM alone is insufficient.
Signer failure leaves stored `closed` (an operational display may say
`closed_unsealed`); old valid checkpoint/receipt rollback
is code 18, not verified current. Expired or revoked checkpoint/anchor/export
keys fail regardless of self-declared artifact time; artifact time alone is not
historical proof.

AuditExportManifest v1 covers only `complete_sealed_segments` with
`filter:null`; it carries required non-empty `selectionReason`, distinct-partition genesis or
`selected_range` `startBoundaries`, partition-chain-ordered ranges,
recordCount/chainHeadHash, checkpointHash/checkpointBytesHash/anchorReceiptHash,
one exact-once `event_catalog` per distinct covered `catalogHash` plus
role/path/artifact file entries and raw digests, actor/time/source,
`signatureAlgorithm`/`signingKeyRef`/`signedAt`, a 64-hex JCS `manifestHash`
(only the stored properties manifestHash and signature excluded from its
preimage), and Ed25519 signature. AuditExportRecord v1 is complete canonical
AuditRecord JCS/LF wire, with each complete JCS line including LF capped at 1
MiB before writer append/commit or JSON allocation; checkpoint/anchor raw files
are each capped at 1 MiB, event-catalog raw+LF files at 2 MiB while unsigned
catalog JCS is 1 MiB, and all use JCS plus LF. Raw file digests are distinct from semantic
hashes. Arbitrary filtered subsets
are separate safe projections. Both genesis and `selected_range` use the same
exact eight start-boundary keys: `partitionId`, `mode`, `anchorStoreRef`,
`anchorObjectRef`, `previousCheckpointHash`, `previousAnchorReceiptHash`,
`previousAnchorObjectVersion`, and `prefixComplete`. Genesis has non-empty
refs equal to the first receipt, null predecessors, receipt version 1, and
`prefixComplete:true`; selected range has non-empty refs equal to the first
receipt, positive/hash predecessors, receipt version +1, and
`prefixComplete:false`. Selected range declares unresolved predecessor values
and verifies only its included chain from the first artifact; it does not
prove predecessor existence, continuity from predecessor, prefix/pre-range,
or deletion legitimacy. P23-003F
owns reusable catalog/record/checkpoint/anchor/freshness verification and hash
recomputation without append; P23-003G calls it for
manifest/bundle/start-boundary/file/wire/scope orchestration without
reimplementation. The result has exact top-level
`{schemaVersion,kind,mode,status,code,scope,exportId,manifestHash,verifiedAt,contextHash,partitions}`;
partitions are each
parsed manifest partition exactly once, sorted by canonical unsigned-byte
lexical UTF-8 `partitionId` with no locale, with exact
entries `{partitionId,status,code,prefixComplete,suffixComplete,
startCheckpoint,endCheckpoint,startReceipt,endReceipt}` and receipt refs
including `anchorStoreRef`. Verified entries have code 0/all refs/computed
flags; partition-local failed entries have nonzero code/false flags/all refs
non-null. Global/context failures use `partitions:[]`; context invalid is code
19 with all result refs null. Overall code 0
requires every entry; failure code is the first nonzero sorted entry, or the
manifest code with `partitions:[]` before safe inventory. Current freshness is
required for every partition and one code 18 fails overall; as-of snapshot
scope is distinct. Two-valid-partition, invalid-chain, freshness-18, and
malformed-before-inventory fixtures are required. Offline verification uses
finite safe codes and nonzero failures. The bounded `system:global`/
`audit-control-v1` stream family rotates concrete segments and avoids recursive
self-logging.
Separated writer/read/export/sign/retention roles, bounded tenant query, and
category retention/legal hold are required downstream. Physical deletion,
sealed bytes/keys destruction, and signed deletion manifests are explicitly
deferred to future P23-003H; v1 retains sealed material and makes no compliance
claim. Hash/AEAD alone is not tamper proof or non-repudiation.

Current gaps are the general event catalog/projector, dedicated store,
transactional delivery, chain/seal/anchor/verify, signed export, audit-of-
audit, and the future P23-003H physical-deletion boundary. Audit v1 does not
delete sealed bytes or keys and makes no compliance claim. None is delivered by
this Discovery.

## Official Evidence

Official primary documentation was retrieved through a redirect-aware web
fetch with HTTP 200/content checks on 2026-08-19; the corrected PHP PCNTL book
URL returned Process Control content, while the obsolete overview URL returned
404 and is not retained. Final URLs and relevant content were recorded as
discovery evidence:

- [FrankenPHP Worker Mode](https://frankenphp.dev/docs/worker/):
  request-driven loop, state persistence, max-request restart, worker failure
  backoff.
- [FrankenPHP Internals](https://frankenphp.dev/docs/internals/): POSIX PHP
  threads, Go/C state machine, thread restart, thread-vs-process boundary.
- [FrankenPHP Extension Workers](https://frankenphp.dev/docs/extension-workers/):
  background PHP thread pools, Go registration, Caddy configurability.
- [FrankenPHP extensions](https://frankenphp.dev/docs/extensions/): generated
  Go/C extension and `xcaddy` integration.
- [FrankenPHP configuration](https://frankenphp.dev/docs/config/): worker
  count and `max_consecutive_failures` settings.
- [FrankenPHP performance](https://frankenphp.dev/docs/performance/): memory
  and long-running worker constraints.
- [PHP PCNTL overview](https://www.php.net/manual/en/book.pcntl.php), [PHP
  pcntl_signal](https://www.php.net/manual/en/function.pcntl-signal.php), and
  [PHP pcntl_alarm](https://www.php.net/manual/en/function.pcntl-alarm.php):
  signal/timer primitives used by the current CLI worker.
- [OWASP Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html):
  separate process/audit/transaction/security logs, application event context,
  and limits of non-repudiation claims.
- [NIST SP 800-53 Rev. 5](https://csrc.nist.gov/pubs/sp/800/53/r5/upd1/final):
  Audit and Accountability reference, not a compliance claim.
- [W3C Trace Context](https://www.w3.org/TR/trace-context/): trace/parent IDs
  and sampled flag, separate from Operation identity and integrity.

## Commands and Results

Repository source evidence used for the public fixed-route and security/access
boundaries:

- Stable Worker positive fixtures: `docs/guide/installation.md:75`,
  `docs/guide/mvp-status.md:28`, and `examples/quickstart/Caddyfile`.
- Stable correlation positive fixtures: `src/Internal/Logging/ExecutionScopedLogger.php:21-177`,
  `develop/spec/100-structured-logging-and-opentelemetry.md`, and the current
  Structured Log/OpenTelemetry Operation/Correlation/Trace/Span projection;
  the new four-surface Audit correlation contract remains a future negative.
- Public route composition: `src/Internal/Application/ApplicationHttpRuntimeComposer.php:31-124`,
  `src/Internal/Runtime/ProductionRuntimeComposer.php:32-111`,
  `src/Http/OperationRequestHandler.php:39-61`, and
  `src/Http/Status/OperationStatusRequestHandler.php:17-39`.
- Authentication/access boundaries: `src/Http/Authentication/AuthenticationMiddleware.php:20-86`,
  `src/Http/Authentication/HttpAuthenticator.php:10-14`,
  `src/Internal/Authorization/AuthorizationEvaluator.php:13-45`,
  `src/Internal/Http/OperationStatusAuthorizerResolver.php:13-38`, and
  `src/Internal/OperationData/OperationDataReadAuthorizerResolver.php:10-36`.

Read-only repository inspection completed:

- `sed`/`nl` inspection of AGENTS.md, STATE, Task Packet, Spec README, linked
  Specifications 32/42/47/51/78/82/98/99/100, Decisions D062/D136, CLI,
  manifest, route, schedule, worker, retention, replay, rotation, outbox,
  and Symfony Console sources: PASS.
- `rg` inventory of relevant source/spec/test paths and current Stable/Roadmap
  occurrences: PASS.
- `rg`/`test -f` guard confirms `ExecutionScopedLogger` and
  `HttpAuthenticator` paths used for the Stable correlation positive and
  auth-principal negative contract: PASS.
- Explicit filesystem existence guard for all current P23 cited local evidence/
  management paths: PASS (44 unique existing local evidence targets; 46 cited
  ranges; 270 Markdown link occurrences are separate counts). This preserves the current replay runtime path and verifies the
  PostgreSQL retry path at
  `src/Transport/PostgreSql/PostgreSqlOutboxStore.php:317-414`.
- All local cited path ranges were checked separately against each file's
  actual EOF: PASS (46 ranges). The narrower PHP source/test citation guard
  also passed (42 ranges across 27 unique PHP paths), including the corrected
  `HttpAuthenticator.php:10-14` and resolver EOF ranges.
- Official primary references above were retrieved through a redirect-aware
  web fetch with HTTP 200/content checks on 2026-08-19: PASS for the corrected
  PHP book URL and the listed official pages.
- Local `curl -L` reproduction of the corrected PHP URL could not resolve
  `www.php.net` in the sandbox (`curl: (6) Could not resolve host`); the
  redirect-following web retrieval of `book.pcntl.php` returned the PCNTL
  Process Control content with HTTP 200 and is the recorded authority check.
- Docker runtime, full PHPUnit, and broad Consumer/runtime journeys, remote CI,
  deployment, publication, and package commands: NOT RUN. The read-only
  `tests/Consumer/version-baseline.sh` management guard was run separately and
  passed. The broader commands were not required for this
  management/specification-only task, and no Production Code or tests changed.
  Remote mutation, release, and deployment remained out of scope. Official
  primary documentation research was recorded separately as discovery
  evidence; no repository remote mutation was performed.
- `docker compose run --rm app mago format --check src tests`: PASS;
  `INFO All files are already formatted.` This management-only format check did
  not change Production Code or tests.

The Round 4 final local management commands were run after synchronization:

- `git diff --check`: PASS.
- exact allowed-path inventory, comparing tracked changes plus untracked files
  against the exact eight-file Task inventory: PASS.
- management-document trailing-whitespace scan: PASS.
- Stable/Roadmap boundary `rg` across the exact eight files: PASS (matches
  positive Stable Worker/correlation and negative future-only claims).
- required source/test management-ID guard,
  `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'`:
  PASS (no matches).
- review-inconsistency negative guard for stale status wording, bounded NIST
  scope wording, and nonexistent replay path: PASS (no matches).
- exact audit-contract guard for no `checkpoint` property in
  `AuditRecord.integrity`, no obsolete hash/link terms, and positive
  `audit-sha256-jcs-v1`/checkpoint/anchor/complete-sealed-manifest markers:
  PASS.
- exact checkpoint graph guard for stored `checkpointHash`/`signedAt`,
  genesis-only sequence `1`, later prior-final-plus-one,
  `recordCount=lastSequence-firstSequence+1`, `1-42`/`43-50` positives and
  gap/overlap negatives, `previousCheckpointHash` continuity, 64-byte signature and
  32-byte public-key rules, and no checkpoint field in record integrity: PASS.
- exact anchor/export graph guard for `checkpointBytesHash`, receipt hash and
  monotonic anchor version/freshness, `filter:null`, required segment hashes,
  role/path/artifact file entries, JCS/LF `AuditExportRecord` wire, exact-once
  binding, and finite code `18`: PASS.
- Stable Worker/correlation positive-negative guard: existing Worker Mode and
  Structured Log/OpenTelemetry/`ExecutionScopedLogger` positive; sole-profile,
  Classic removal, Extension Worker, same-process Deferred, and new four-surface
  Audit correlation future-negative: PASS.
- P23-003A/P23-003B/P23-003E/P23-003F/P23-003G ownership and auth/read response-ordering
  guard: PASS.
- P23-002A owner, canonical `list`/`operation:list` example order, and route
  `strategy:string|null` synchronization guards: PASS.
- Node JSON parsing of all seven Specification 105 `json` examples: PASS.
- No repository-local dedicated spec/doc management script was present in the
  inspected `scripts` path; the non-mutating inventory, whitespace, boundary,
  and source/test guards above were run instead. No build or public artifact
  was generated.
- Final command outputs: `git diff --check` PASS; `git diff --cached --quiet`
  PASS (nothing staged); exact eight-file inventory PASS (8); trailing
  whitespace PASS; PHP source/test management-ID PASS; version baseline PASS
  (`published=1.2.0 historical=1.1.0`); evidence-path guard PASS; Worker/
  correlation positive-negative guard PASS; audit exact graph/owner/order
  guard PASS; CLI/route/deferred ownership and canonical list order PASS;
  official-link/path/NIST wording guard PASS; all seven Specification 105 JSON
  examples parse PASS; local Markdown link guard PASS (270 links); all local
  path line-range guard PASS (44 ranges).
- Round 4 additions: same-eight-key selected-range/genesis boundary
  (`anchorStoreRef`, `prefixComplete`) and unresolved-predecessor guard;
  AuditEventCatalog/catalogHash/`evidenceVersion`/finite-cap and distinct-
  catalog `event_catalog` exact-once guard; current/as-of verifier
  mode/status/scope/prefix/suffix/start-end reference guard; P23-003H deletion
  deferral/no-deletion-fields guard; and interrupted review truth guard: PASS.
  Independent review remains Changes Requested and pending; this is not a
  Green/Accepted claim.
- Historical Round 4 final counts: that round's P23 local evidence path existence guard PASS
  (50 unique paths), local cited line-range guard PASS (46 ranges), Markdown
  link guard PASS (270 local links), and all seven Specification 105 JSON
  examples parse PASS. The exact-8 inventory and staged-none guard remained
  PASS after these checks.
- Round 5 Sol xHigh checkpoint is Changes Requested (P1=0/P2=4/P3=0); the
  four bounded closures are drafted here and remain pending independent
  re-review. New guards cover catalog category/action/owner/fixed-delivery
  semantics, the exact anchor identity triple and cross-store negatives,
  multi-partition result ordering/failure/freshness behavior, and expired or
  revoked checkpoint/anchor/export key rejection. This is not a Green or
  Accepted claim.
- Round 6 integrated checkpoint is Changes Requested (P1=2/P2=6/P3=1; P2
  comprises three full-review and three Audit-only findings). The current corrections cover the required/durable
  canonical delivery union and projection separation, strict semantic-hash
  recomputation before signature acceptance, global-before-partition result
  precedence, exact tenant mapping, bounded streaming preflight, closed
  verification context, evidence-field uniqueness, and trusted time authority.
  Worker-side drafting is complete; independent re-review remains pending and
  this is not Green, Accepted, or Decided.
  - Round 6 residual gate at `2026-08-19T05:42:51+09:00`: exact eight-file
  inventory PASS (4 tracked + 4 untracked allowed), broad working-tree
  inventory PASS (no path outside the eight), exact source boundary PASS,
  staged-none PASS, `git diff
  --check` PASS, trailing-whitespace PASS, PHP management-ID guard PASS,
  version baseline PASS (`published=1.2.0 historical=1.1.0`), stale freshness/
  manifest-term guard PASS, Stable Worker/Correlation positive-negative guard
  PASS, Audit delivery/hash/context/tenant/graph guard PASS, required
  `policy.auditDelivery` and 1 MiB bounded-line/raw-artifact guard PASS, all
  seven JSON examples parse PASS, local Markdown links 270 PASS, and cited
  evidence path existence/range guard PASS (historical Round 6 scan: 68 paths／46
  ranges). These are management/spec checks only; they do not claim independent
  review Green or Acceptance.

- Historical Round 7 correction checkpoint at `2026-08-19T06:47:02+09:00`: Stable
  Symfony-backed `list` current-positive and BlackOps replacement/`ls` only-new
  compatibility, explicit-only delivery, single verification order, writer
  1 MiB complete-record cap, semantic tuple binding, reserved evidence.version,
  expected external context hash/code 19, public-key fingerprint uniqueness,
  exact verification result shape, recordedAt ingress, checkpoint crash
  idempotency, and event-catalog 2 MiB raw cap were synchronized. Round 6
  independent evidence remains separate (full review P1=3/P2=4/P3=1; Audit
  review P1=2/P2=6/P3=1); no aggregate was fabricated. Final Round 7 local
  management guards at `2026-08-19T06:43:29+09:00` are PASS: exact-8
  inventory (4 tracked + 4 untracked), staged-none, `git diff --check`,
  trailing-whitespace, release source, version baseline
  (`published=1.2.0 historical=1.1.0`), PHP management-ID, Mago format,
  seven JSON examples, 270 local Markdown links, 44 cited local evidence
  paths with 46 EOF-checked ranges, and 17 Stable/Roadmap plus Round 7
  contract assertions including the single verification-order negative guard.
  Independent re-review remains pending. This is not Green, Accepted, or
  Decided.

- Round 8 pre-review bounded correction checkpoint at `2026-08-19T08:02:47+09:00`: current
  evidence counts are unified as 44 unique existing local evidence targets,
  46 cited ranges, and 270 Markdown link occurrences. Stable `list` remains
  current; the correction synchronizes all Stable input goldens, embedded
  framework recovery and status, exact XML/Markdown wires, shared reservation
  registry, `AuditIngressEnvelope v1`, trusted ingress recordedAt/high-water
  stamping, canonical-only sequence, `AuditOutboxRecord` BOPD/AAD protection,
  synthetic complete-record 1 MiB cap, non-null semantic tuples and <=256-byte
  typed evidence, sealed framework namespace/owner authority, independent
  phase validation, one verifier Stage 0-7/null matrix, freshness chronology,
  requester partition mapping, AnchorIntent recovery, 15/16 MiB manifest caps,
  and exact connection/root transaction checks. Round 7 is Changes Requested
  (General `P1=4/P2=1/P3=1`, Audit `P1=6/P2=6/P3=0`); Round 8 is Correction
  Implemented / Review Requested, not Green/Accepted/Decided.

- Final Round 8 pre-review local management gate at `2026-08-19T08:02:47+09:00`: exact
  eight-file scope and staged-none PASS; `git diff --check`, release source,
  version baseline, Mago format, PHP management-ID, exact-8 whitespace,
  Specification 105 JSON parsing, and local Markdown content/link validation
  PASS. `pnpm --dir docs/website content:check` reported
  `Content validation and determinism checks passed.` No commit or stage
  occurred.
- Required command evidence for this checkpoint is explicit: `pnpm --dir
  docs/website release:check:source` PASS; `bash
  tests/Consumer/version-baseline.sh` PASS (`published=1.2.0 historical=1.1.0`);
  `docker compose run --rm app mago format --check src tests` PASS; the PHP
  management-ID guard PASS; the exact-eight trailing-whitespace guard PASS;
  all seven Specification 105 JSON blocks parse PASS; and
  `pnpm --dir docs/website content:check` PASS. No stage or commit occurred.

- Round 9 bounded-correction gate at `2026-08-19T09:10:43+09:00` records the
  exact eight-file inventory/staged-none, `git diff --check`, source release
  check, version baseline, Mago, PHP management-ID, exact-8 whitespace,
  Specification 105 JSON parsing, and known local Markdown validation. The
  historical verdict was Round 9 Correction Implemented / Review Requested;
  fresh Sol review and Orchestrator acceptance are unresolved until this gate
  finishes. SHA-256 values for the seven non-self files are embedded below;
  the exact-eight command output, including this Report's non-self-referential
  digest, is returned in the worker handoff.

- Round 9 required checks completed at `2026-08-19T09:10:43+09:00`:
  exact-8 scope/staged-none PASS; `git diff --check` PASS;
  `pnpm --dir docs/website release:check:source` PASS;
  `bash tests/Consumer/version-baseline.sh` PASS
  (`published=1.2.0 historical=1.1.0`); `docker compose run --rm app mago
  format --check src tests` PASS (`INFO All files are already formatted`);
  PHP management-ID guard PASS; exact-8 trailing-whitespace guard PASS;
  `pnpm --dir docs/website content:check` PASS; all seven Specification 105
  JSON blocks parse PASS. No stage, commit, push, PR, CI dispatch, deploy, or
  release action occurred.

  Final exact-8 SHA-256:
  `develop/STATE.md` = `da409220b6c90c12b9531d13f07806f46b4b68587370d0d4e827867467e9e69c`;
  `develop/TODO.md` = `25d78b7fb67c9b0ee87d52665955ef73de2f492793cd9528cdd183b5a53722c0`;
  `develop/orchestration/tasks/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md` = `851f97b3af9ab971c8de0da1744d02f8e9a11b9a6c1a81709b1ee1a9b85a9eca`;
  `develop/spec/README.md` = `0655ad73273a772e26a930ffef74f0c1d39f7f9893dee350f86946506c53dcfc`;
  `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md` = `354b6196006fbd446c3fc43ecf9611d8820420ed37b5de367d7072f01475bc5e`;
  `develop/spec/105-blackops-1-3-capability-contract.md` = `108b21fd32614b9f5412a91e7f6d3e5158e282615ccff80aa5dee7f7c4c151cf`;
  `develop/spec/106-phase-23-delivery-plan.md` = `20a7a5d59488295a5854b5fc40a2280b0a25991c9d4c031109195565c27504a9`.

## Acceptance Criteria

- [x] User command proposals have meaning, source, output, exit, and alias
  contracts. `worker:list`/`worker:ls` are Application-owned and unreserved:
  registered canonical/alias names execute normally, unregistered names use
  normal Symfony unknown-command behavior, and BlackOps supplies no Framework
  Job registry or built-in Job listing.
- [x] Stable Symfony-backed `list` is current-positive; P23-002A owns the
  unreleased BlackOps replacement with `ls` as the only new name, preserves all
  Stable accepted combinations without exit `2`, reserves exit `2` only for
  new `--json` plus explicit `--format`, and owns collision preflight/migration
  plus collision-free/upgrade-collision fixtures.
- [x] Existing command overlap and additional command priority are decided.
- [x] Same-process Deferred feasibility, dependencies, isolation limits, and
  failure matrix are evidence-backed.
- [x] Stable Worker Mode existing default positive fixture is separated from
  P23-004A sole-profile hardening/Classic migration and P23-004B or
  same-process Deferred future-only claims.
- [x] Classic removal is independently gated from same-process Deferred.
- [x] Application Log, Observability, Lifecycle Journal, and Audit Trail have
  separate owners, canonical roles, access/retention boundaries, and evidence.
- [x] Operation/Trace correlation covers issue, propagation, missing context,
  retry, sampling, cardinality, redaction, and privacy without equating IDs.
- [x] Audit event/actor/tenant/action/resource/reason, delivery, retention,
  access, tamper evidence, export, and failure boundaries are recorded as an
  unreleased proposal.
- [x] Framework authentication/authorization/privileged-read catalog and
  P23-003A/P23-003D ownership include authenticated/invalid, allow/deny,
  absent-ID, and credential-nonrecording fixtures.
- [x] AuditRecord integrity, immutable checkpoint/anchor artifacts, complete-
  sealed-segment export manifest, JCS/Ed25519 trust rules, and finite verifier
  codes are exact and non-mutating.
- [x] Distinct-partition genesis/selected-range start boundaries (genesis-only
  `prefixComplete:true`, no prefix/pre-range/deletion-legitimacy claim), anchor
  +1 continuity keyed by `{partitionId,anchorStoreRef,anchorObjectRef}`, raw
  versus semantic hash separation, conservative checkpoint/anchor/export
  key/time negatives, multi-partition result semantics, closed nested
  AuditRecord/evidence/prohibited-input rules, and Audit v1 physical-deletion
  deferral to P23-003H are synchronized.
- [x] Closed domain-hashed AuditEventCatalog v1, `event.catalogHash`, exact
  `evidenceVersion` selector, finite evidence/catalog caps, bounded
  flat-primitive evidence with category/action/owner/fixed-delivery semantic
  binding, same-selector redefinition refusal, one exact-once
  bundle `event_catalog` per distinct covered catalog hash, and
  mixed-two-catalog positive plus missing/extra/unreferenced/duplicate catalog
  and evidence-version mismatch negatives, and `current`/`as_of_checkpoint`
  verifier result shape are synchronized with P23-003A/003E/003F/003G
  ownership.
- [x] Round 6 delivery, semantic-hash, global-result, tenant-mapping,
  bounded-preflight, verification-context, evidence-uniqueness, and trusted
  time corrections are synchronized across the exact eight management/spec
  files with positive/negative fixtures recorded; independent acceptance is
  still pending.
- [x] Round 7 correction draft synchronizes explicit-only delivery and
  four-path fail-closed fixtures, one Stage 0-7 verification order, complete-record
  1 MiB writer cap, semantic tuple exact binding/reserved evidence.version,
  external expectedContextHash/code 19, public-key fingerprint uniqueness,
  exact result shape, recordedAt authority, checkpoint crash idempotency, and
  event-catalog raw+LF 2 MiB cap; independent re-review remains pending.
- [x] Round 8 correction synchronizes Stable list compatibility/recovery,
  exact XML/Markdown wires, shared reservation registry, ingress/outbox
  contracts and protection, semantic tuple/evidence/authority limits,
  independent phase validation, verifier total order/result refs/freshness,
  requester-partition mapping, AnchorIntent crash recovery, manifest caps, and
  same-connection/root transaction identity; independent review remains pending.
- [x] Round 9 correction synchronizes schedule-only exits, six-state trusted
  projection identity/precedence, canonical XML/Markdown fixture bytes,
  classification-required typed evidence, durable-only ingress and exact
  outbox/AAD/ordering/fencing, verifier total-function nullability and full
  endpoint refs, AnchorIntent deterministic recovery, actor/read matrix,
  root-confined nofollow files, checkpoint control events, metadata-only
  retention, and separate checkpoint-persist vs AnchorReceipt/AnchorIntent P3
  ownership; Round 8 fresh verdicts remain General `P1=2/P2=1/P3=0` and Audit
  `P1=5/P2=4/P3=1`, with no aggregate.
- [x] Audit request is carried into D145, Specifications 105/106, and ordered
  downstream tasks without Stable `1.2` contamination.
- [x] Phase 23 DAG, dependencies, migration, independent gates, and release/
  documentation boundary are defined.
- [x] Positive Stable and negative Roadmap boundary is recorded; no current
  public artifact was changed.

### Historical Round 9 P3 ownership split

Checkpoint persistence is one bounded idempotency concern owned by the
checkpoint writer/control adapter: complete checkpoint bytes persist before
ack, identical `checkpointId+bytes+hash` retries succeed with no duplicate,
different bytes fail, and persist-before-ack/crash/replay fixtures cover that
boundary. This is separate from anchor receipt recovery.

AnchorReceipt/AnchorIntent recovery is a distinct external-write concern owned
by the anchor adapter: fenced `AnchorIntent v1` conditional create recovers the
same external version/hash and deterministic receipt bytes/signature across
the three crash points, sign-before-persist, parallel retry, and read-after-
write fixtures; it never creates N+1. The two owners and fixture families are
not attributed to one mixed P3 finding.

## Historical Round 10 Remaining Issues

Historical worker-side Round 10 correction and acceptance-criteria synchronization were
complete, but the Task and Report await fresh independent Sol xHigh and Orchestrator review and
acceptance. Round 6 evidence remains separate (full review P1=3/P2=4/P3=1;
Audit review P1=2/P2=6/P3=1); no aggregate is claimed. This is not Green,
Accepted, or Decided. Remaining first is P23-001 independent
review/acceptance; after that, P23-001R and the Specification 106 downstream
implementation/release work remain. No 1.3 capability is delivered by this
report.

## Historical Round 10 Suggested Next Action

Historical Next Action was for the Orchestrator to use the PASSed exact-eight
fixed-snapshot Local Gate and begin the fresh independent Sol re-review against
the Round 9 correction,
the separate Round 8 verdict, Round 7 verdict, and the two
separate Round 6 verdict records. After acceptance, create P23-001R and the Specification 106
downstream Task Packets; keep future P23-003H deletion work separately
reviewed. Do not publish 1.3 claims or begin a remote/release gate from this
snapshot.

## Historical Round 10 bounded correction checkpoint

Round 10 was `Correction Implemented / Review Requested`; it was not Green,
Accepted, or Decided. Round 8 historical review remains General
`P1=2/P2=1/P3=0` and Audit `P1=5/P2=4/P3=1`, while Round 9 is a superseded
Orchestrator pre-review correction with no invented Sol verdict. Current
evidence is defined as 44 unique existing local targets, 46 cited ranges, and
270 Markdown link occurrences.

The exact eight-file correction adds one installation-owned `ProjectionBuildSet
v1` root with atomic temp/fsync/rename publication and exact command/route/
schedule projection digests, input hashes, build identity, and safe relative
paths. Readers consume only one dedicated closed projection each, with no
runtime source/provider/container/DB fallback; old-root+old-set or new-root+
new-set are the only valid views. List/ls recovers framework rows at exit 0
for all six states `available|absent|malformed|unsupported|stale|build_mismatch`,
while operational reads are exit 1 and complete route/schedule failures are
exit 2. Stable namespace goldens, all accepted format combinations, repeated
format last-wins, raw txt-only, short no-op, exact JSON/raw/XML/Markdown wires,
hidden `--all`, safe-name injectivity, bounds, and explicit/discovered startup
sentinels are synchronized. XML/Markdown fixture hashes are
`579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968` and
`7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`.

Audit corrections synchronize Spec 99 BOPD binary bytes and custom AAD for
canonical `AuditRecord` and durable `AuditOutboxRecord`, tenant-union and
partition bindings, durable-only ingress, idempotency hash, fenced outbox
state/type matrix, evidence reachability (safe_code 256 passes/257 fails,
opaque_id 71 passes/70/72 fail, nullable null passes), Stage 0-7 code/ref/null precedence, singular
checkpoint/receipt per signed segment with first/last result endpoints,
same-fd nofollow semantics, exact AnchorIntent fields/state matrix and three
non-overlapping crash points, and non-recursive control-stream artifacts.
P23-003B owns storage lookup and authorization-before-decrypt; P23-003G owns
public query/export; the P23-003D control-adapter sub-slice depends on accepted
P23-003F. No Production Code, tests, public Guide, Website, release artifact,
or external state was changed; no stage/commit/push/PR/CI/deploy/release was
performed.

## Historical Round 10 commands and results

- exact-8 inventory and staged-none guard: PASS.
- `git diff --check`: PASS; exact-8 trailing-whitespace guard: PASS.
- `pnpm --dir docs/website release:check:source`: PASS (`Release claim source
  guard passed.`).
- `bash tests/Consumer/version-baseline.sh`: PASS (published `1.2.0`,
  historical `1.1.0`).
- `docker compose run --rm app mago format --check src tests`: PASS (all files
  already formatted).
- `pnpm --dir docs/website content:check`: PASS (content validation and
  determinism checks passed; this is also the known local Markdown/link gate).
- PHP management-ID guard: PASS (no matches).
- Spec 105 JSON fence parser: PASS, 7 fences parsed.
- Independently reconstructed XML/Markdown canonical fixtures: PASS; XML
  SHA-256 `579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968`,
  Markdown SHA-256
  `7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`.
- No stage, commit, push, PR, CI dispatch, deploy, release, or external
  mutation was performed. Remaining is fresh Sol xHigh general+Audit review,
  Orchestrator acceptance, then P23-001R and downstream DAG work. Suggested
  Next Action: return exact-8 gate evidence and final hashes to the
  Orchestrator without publishing a 1.3 current claim.

## Historical Round 11 correction checkpoint

### Summary

Round 11 was a historical `Correction Implemented / Review Requested`
checkpoint and is superseded by Round 12. Round 8, Round 9, and Round 10 are
historical and their verdicts remain separate. Stable `1.2` is current; Phase
23 and every 1.3 capability remain an unreleased proposal. That correction
closed the ProjectionBuildSet
v1 root, canonical AuditStoredRecord transport, verifier total function,
manifest identity/order, and AnchorIntent exactness without changing
Production Code or public artifacts.

### Changed Files

Only the exact eight allowed management/specification files listed at the
front of this report were changed. No source, test, public Guide, Website,
dependency, release artifact, or generated artifact was changed.

### Decisions and Assumptions

- `ProjectionBuildSet v1` is a closed root
  `{schemaVersion,kind,applicationBuildId,buildSetHash,projections}` with
  `kind=projection_build_set` and
  `SHA-256(ENC("blackops.projection-build-set.v1") ||
  ENC(JCS(root excluding buildSetHash)))`. Stored bytes are UTF-8 RFC 8785
  JCS plus exactly one final LF, no BOM/CRLF/other whitespace; the 64 KiB cap
  includes that LF. The configured installation root is an integrity boundary,
  not cryptographic authenticity. Immutable content-addressed generation files
  publish before the root, each with temp+fsync+rename+parent fsync; referenced
  files are never overwritten. Readers do not compare uncompiled working source.
- The exact projection kinds are
  `blackops_command_projection`, `blackops_route_projection`, and
  `blackops_schedule_projection`. Command records are
  `{name,aliases,description,origin,hidden}`; route records are
  `{method,path,strategy,operationType,origin}` sorted by
  `(path,method,origin,operationType,strategy)` with nulls first; schedule
  records are `{name,operationType,cron,timezone,strategy}` sorted by
  `(name,operationType)` with nulls first. Root/projection/row caps and
  malformed/unsupported/build-mismatch/stale precedence are closed.
- `AuditIngressEnvelope v1` includes `tenantBinding` and is durable-only.
  `AuditStoredRecord v1` is the closed canonical row; recordId and segmentId
  are 1..256-byte NFC/no-control strings, partitionId is exactly
  `system:global` or `tenant:<64 lowercase hex>`, tenantBinding uses the exact
  AuditRecord tenant bounds, eventCategory equals decrypted event.category,
  and recordedAt equals decrypted time.recordedAt. Canonical BOPD/AAD binds
  the complete clear identity and tenant union before decrypt; complete
  logical records are <=1 MiB and the maximum envelope allocation is
  <=1 MiB+180 bytes.
- Manifest files bind artifact IDs to segmentId, checkpointId, anchorReceiptId,
  and catalogHash; canonical array/path ordering and raw-file-versus-semantic
  hashes are distinct. Stage 0-7 uses the single code/ref/null precedence;
  Stage 4 signature failure leaves export refs null and opens zero files,
  while signature success is the first point refs become non-null.
- `AnchorIntent v1` uses anchorReceiptId, exact semantic unsigned receipt hash,
  genesis version 1 or predecessor+1, and a fenced lease pair that is present
  only for an active claim. State regression, stale-owner writes, N+1 retries,
  and receipt/hash substitutions fail closed.

### Commands and Results

Round 11 gate results are recorded below only after each command is executed.
No command result is inherited from historical Round 10 evidence.

### Acceptance Criteria

The eight-file scope, Stable `1.2`/unreleased `1.3` boundary, historical Round 11 status,
projection root and field bounds, AuditStoredRecord transport, manifest and
verifier precedence, AnchorIntent state matrix, and management Remaining/Next
Action are synchronized across D145, Specifications 105/106, Task, Report,
STATE, TODO, and README. The package is not Accepted, Decided, or Green.

### Remaining Issues

Historical Remaining was fresh independent Sol xHigh general and Audit review
and Orchestrator acceptance; after acceptance, P23-001R and the ordered
downstream DAG remained. No 1.3 capability was delivered by that checkpoint.

### Suggested Next Action

Historical Suggested Next Action was to run and independently review the
exact-8 uncommitted Round 11 gate snapshot and, after acceptance, open
P23-001R and the ordered downstream Task Packets.

## Round 11 Commands and Results

### Orchestrator-independent frozen pre-management snapshot

The following results were already executed by the Orchestrator against the
frozen pre-management snapshot; they are not worker-run results and are not
reused as the worker post-edit gate:

- Branch: `agent/p23-001-blackops-1-3-feasibility`; `HEAD` and `main` both
  `5ad87557c08e20c3bcc6d77be83804001ba3eb80`.
- Exact scope: 4 tracked plus 4 untracked allowed files; staged none.
- `git diff --check`: PASS; exact-8 trailing whitespace: PASS; PHP
  management-ID guard: PASS.
- `pnpm --dir docs/website release:check:source`: PASS (`Release claim source
  guard passed.`).
- `pnpm --dir docs/website content:check`: PASS (content validation and
  determinism checks passed).
- `bash tests/Consumer/version-baseline.sh`: PASS (`published=1.2.0`,
  `historical=1.1.0`).
- `docker compose run --rm app mago format --check src tests`: PASS (all files
  already formatted).
- Specification 105 JSON fences: PASS, 7/7 parsed.
- XML fixture: 465 bytes, SHA-256
  `579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968`;
  Markdown fixture: 247 bytes, SHA-256
  `7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`;
  source-notation equality: PASS.
- Exact-8 Markdown links: 306 total, 270 local, 0 missing.

### Worker post-edit rerun

The worker reran the required exact-8 scope/staged-none, diff, whitespace,
release-source, content, version-baseline, Mago, management-ID, JSON-fence,
fixture-byte/hash, and Markdown-link checks after this micro-correction. Their
results are recorded here only after execution; no historical PASS is treated
as a post-edit result.

- Exact-8 scope/staged-none: PASS; `git diff --check`: PASS; exact-8 trailing
  whitespace: PASS.
- `pnpm --dir docs/website release:check:source`: PASS (`Release claim source
  guard passed.`).
- `pnpm --dir docs/website content:check`: PASS (content validation and
  determinism checks passed).
- `bash tests/Consumer/version-baseline.sh`: PASS (`published=1.2.0`,
  `historical=1.1.0`).
- `docker compose run --rm app mago format --check src tests`: PASS (all files
  already formatted).
- PHP management-ID guard: PASS.
- Specification 105 JSON fences: PASS, 7/7 parsed.
- XML/Markdown reconstruction and source-notation equality: PASS; XML 465
  bytes SHA-256 `579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968`;
  Markdown 247 bytes SHA-256
  `7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`.
- Exact-8 Markdown links: PASS (306 total, 270 local, 0 missing).
- No stage, commit, push, PR, CI dispatch, deploy, release, or external
  mutation was performed.

## Historical Round 12 correction checkpoint

### Summary

Round 12 was the then-current `Correction Implemented / Review Requested —
Discovery Complete` checkpoint; it was not Green, Accepted, or Decided. Round 11
is historical Changes Requested with General `P1=2/P2=2/P3=0` and Audit
`P1=7/P2=3/P3=1`, separately and without aggregation. Stable `1.2` remains
current and all Phase 23/1.3 capabilities remain unreleased proposals.

The current contract is one `ProjectionBuildSet v1` wire with dedicated
command/route/schedule projections, exact
`.blackops-projection-build-set.lock` shared/exclusive flock publication and
bounded GC, and the optional single `operation:list --strategy=inline|deferred`
filter. Audit uses partition-global `AuditSegmentState`, canonical BOPD/AAD as
the ordered `LP(U32BE length + exact bytes)` concatenation of domain, purpose,
version, algorithm, key, tenant union, and clear identity, with pinned 232-byte
SHA-256
`5930076af5418572a4e575943172884775428ad10d024de7eb9fc419bce04b1c` and
344-byte SHA-256
`f3ef3d96598964f7087ffe654ecde4d2e828caebfa981ecb1daa82859ff7c57f` goldens,
and prohibits v1 outbox re-encryption. Verification is one Stage 0-7 total
function with an external `expectedManifest` pin; F owns signing/reusable
verification and G owns query/export/hosted-verification orchestration. All
four read surfaces use the same durable `.unavailable`/`resource_unavailable`
tuple. `AuditAccessContext`, metadata-only `AuditRetentionState`, and signed
`{role,artifactId}` plus raw-digest identity remain proposal-only.

### Changed Files

Only the exact eight allowed management/specification files changed. No
Production Code, tests, public Guide, Website, dependency, generated artifact,
release artifact, or external state changed.

### Decisions and Assumptions

- Stable list documentation truth was a confirmed P1 boundary issue at
  `docs/guide/project-cli.md:10`. The separate P23-001S packet was created and
  historically accepted; Round 13 reopens it for the bounded DOM correction.
- The historical evidence population was defined as 44 unique existing local
  targets, 46 cited ranges, and 270 Markdown link occurrences; historical
  counts are not mixed into this current checkpoint.
- The system/genesis and tenant/non-genesis AAD goldens are 232 and 344 bytes;
  their pinned hashes are recorded above and in D145/Specifications 105/106.
- F/G ownership is intentionally split: F owns checkpoint/anchor signing and
  reusable verification primitives plus required `audit.access.sign.allow`;
  G owns query/export/hosted verification, expectedManifest/manifest-pin Stage
  orchestration, and the corresponding allow events before release. Offline
  verification has no canonical append claim.
- No commit/stage/push/PR/CI/deploy/release action is authorized or performed.

### Commands and Results

Historical Round 12 worker gates were run after the Round 12 management edits;
the results below are historical evidence and are not Round 13 evidence or
inherited from historical Round 11 evidence.

- Exact scope: PASS — 4 tracked plus 4 untracked allowed files, staged none.
- `git diff --check`: PASS.
- Exact-8 trailing-whitespace guard: PASS.
- `pnpm --dir docs/website release:check:source`: PASS — `Release claim source
  guard passed.`
- `pnpm --dir docs/website content:check`: PASS — content validation and
  determinism checks passed.
- `bash tests/Consumer/version-baseline.sh`: PASS — published `1.2.0`,
  historical `1.1.0`.
- `docker compose run --rm app mago format --check src tests`: PASS — all files
  are already formatted.
- PHP management-ID guard: PASS — no matches.
- Spec105 JSON fences: PASS — 7/7 parsed.
- XML/Markdown canonical reconstruction and source-notation equality: PASS —
  XML 465 bytes SHA-256
  `579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968`; Markdown
  247 bytes SHA-256
  `7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`.
- Exact-8 Markdown link validation: PASS — 306 total, 270 local, 0 missing.
- Historical current-truth guard: PASS — Round 12 was then-current; legacy projection
  sources and F/G inversion were rejected in active text, with historical
  quotations explicitly labelled; the separate P23-001S packet remains outside
  this checkpoint.
- Final consistency rerun after recording evidence: exact scope/staged-none,
  `git diff --check`, exact-8 whitespace, `content:check`, and Markdown-link
  validation all PASS.

### Acceptance Criteria

The exact-8 scope and historical Round 12 status, separate historical Round 11
verdict lanes, Stable `1.2`/unreleased `1.3` boundary, ProjectionBuildSet and
flock lifecycle, operation strategy filter, partition-global AuditSegmentState,
BOPD/AAD goldens, outbox protection, verifier Stage 0-7/expectedManifest,
F/G ownership, unavailable tuple, AuditAccessContext, RetentionState, signed
  artifact identity, and historical P23-001S acceptance are synchronized across
  the management/specification package. The parent package remains unaccepted.

### Remaining Issues

The child P23-001S must receive fresh General review after its Round 13
reopen, then the parent needs fresh General/Audit review and Orchestrator
acceptance before P23-001R/downstream. No 1.3 capability is delivered here.

### Suggested Next Action

Freeze the exact-15 Local Gate, route the reopened child review, then request
fresh parent General/Audit review and Orchestrator acceptance.

## Historical Round 13 Completion Report — superseded review checkpoint

### Summary

Round 13 synchronized the projection publication/ownership correction and the
Audit lifecycle/access/query/hold corrections. The child P23-001S packet is
reopened from historical acceptance for the bounded jsdom visibility review.
Round 12 findings remain historical Changes Requested (General `P1=5/P2=1/P3=0`,
Audit `P1=6/P2=3/P3=2`) and are not aggregated. Stable `1.2` remains current;
Phase 23 `1.3` remains proposal-only. Historical status was `Correction
Implemented / Review Requested`, not Green/Accepted/Decided.

### Changed Files

The exact union is 15 paths: parent Task, parent Report, STATE, TODO, D145,
Spec README, Spec105, Spec106, child P23-001S Task/Report, Guide, two named
PHP tests, Website contract script, and focused Website test. No file outside
that union changed; no commit, stage, push, PR, CI dispatch, deploy, release,
publication, dependency, or generated-artifact action occurred.

### Decisions and Assumptions

- At that historical Round 14 checkpoint, ProjectionBuildSet v1 was the sole
  projection wire. P23-002A/B/C was
  one atomic acceptance slice; A owns wire/lock/reader/publisher/command,
  B owns route, and C owns schedule plus final activation.
- The publisher takes exclusive flock on
  `.blackops-projection-build-set.lock` before any generation preparation and
  holds it through root publication, directory fsync, and GC. Readers hold
  shared flock through same-fd validation/rendering.
- Strategy values use the injective inline/deferred/custom-FQCN wire capped at
  1024 UTF-8 bytes. Direct Operation Manifest/runtime HTTP joins are historical
  only and current static guards reject them.
- Audit corrections preserve partition-global sequence, stored
  `open|closed|sealed`, immutable v1 outbox ciphertext, exact LP/JCS AAD and
  requestRef, optional evidence matrix, trust-window Stage 0, expectedManifest
  pin, bounded query/cursor, immutable overlapping holds, and
  `.requested|.unavailable|.accessed` read families.
- P23-001S is a separate non-1.3 documentation correction. Its prior
  acceptance is historical and the child is reopened; it must receive fresh
  General review before parent review. The jsdom visibility claim excludes
  external CSS/computed layout.

### Commands and Results

Fresh Round 13 evidence after the final Source/Test correction is:

- focused PHP: PASS, `29 tests / 225 assertions`;
- focused Website contract: PASS, `7/7`;
- full Website tests: PASS, `119/119`;
- Website check: PASS, `0 errors / 0 warnings / 0 hints`;
- fresh Website build: PASS, `42` pages, site check `41` pages;
- Source/Artifact release guards and version baseline: PASS, published
  `1.2.0`, historical `1.1.0`;
- Mago format and lint: PASS; analyze exit `0` with the unchanged repository
  baseline output `71` issues (`46` warnings / `25` help);
- PHP management-ID guard, Spec105 JSON `7/7`, canonical XML `465` bytes and
  Markdown `247` bytes with their pinned SHA-256 values: PASS;
- generated CLI Artifact parity: PASS — HTML `101987` bytes / `96525` string
  units, raw Markdown `16052` bytes / `11754` units, Search content `10204`
  units / serialized record `10430`, LLM section `11719` units;
- exact worktree scope `15`, staged `0`, `git diff --check`, trailing
  whitespace, and Markdown links `313` total / `277` local / `0` missing:
  PASS.

Browser, Remote CI, deployment, publication, and release commands were not
run: this correction changes a text contract guard and proposal management,
not visual layout or an authorized external surface. Historical Round 12/13
PASS evidence is not reused as Round 14 evidence.

### Acceptance Criteria

- [x] Historical Round 13 status, Round 12 historical counts, Stable/unreleased
  boundary, and exact union scope are synchronized.
- [x] ProjectionBuildSet lock/publisher/GC protocol and atomic A/B/C ownership
  are explicit in active delivery contracts.
- [x] Strategy wire, AuditSegmentState, outbox immutability, requestRef,
  optional evidence, verifier trust/pin, query/cursor, legal holds, ordering,
  and read-event mappings are synchronized.
- [x] P23-001S historical acceptance is preserved and its Round 13 reopen is
  explicit; no child task recreation claim remains.
- [ ] Fresh child General review, parent General/Audit review, and
  Orchestrator acceptance.

### Remaining Issues

Fresh P23-001S General review; fresh parent General and Audit review; and
Orchestrator acceptance remain. Only after those reviews may P23-001R and
ordered downstream begin. No 1.3 capability is public or released.

### Suggested Next Action

Route the frozen exact-15 snapshot to P23-001S fresh General review, then
request parent General/Audit review and Orchestrator acceptance. Do not commit
or stage before the Orchestrator accepts this snapshot.

## Historical Round 14 Completion Report — superseded review checkpoint

### Summary

Round 14 corrected the active projection/read boundary, publisher quiescence,
atomic A/B/C ownership, strategy wire, and the Audit access/query/retention/
AnchorIntent/verifier contracts. Round 13 is historical Changes Requested
(General `P1=1/P2=1/P3=0`, Audit `P1=4/P2=2/P3=0`), separately and without
aggregation. Round 14 is `Correction Implemented / Review Requested`, not
Green, Accepted, or Decided. Stable `1.2` remains current and Phase 23/`1.3`
remains an unreleased proposal.

### Changed Files

The exact union is 15 paths: parent Task/Report, STATE, TODO, D145, Spec
README, Spec105, Spec106, child P23-001S Task/Report, the already-correct
Guide, both named PHP tests, and the Website contract script/test. No path
outside this union changed; staged files remain absent. No Production Code,
dependency, generated artifact, release authority, commit, stage, push, PR,
CI, deploy, release, or publication action occurred.

### Decisions and Assumptions

- Readers consume only the dedicated command/route/schedule projection files;
  route fixed descriptors are combined at build time and schedule metadata is
  generated at build time. The fixed regular no-follow
  `.blackops-projection-build-set.lock` is acquired shared by readers and
  exclusive by publishers before generation preparation, through publication,
  fsync, validation, and bounded GC.
- P23-002A/B/C is one atomic acceptance slice. The strategy wire is
  `inline|deferred|custom:<canonical-FQCN>` with the 1024-byte bound and
  exact future filter. Direct Operation Manifest/read-time joins are
  historical/non-normative only.
- Audit uses partition-global `AuditSegmentState`, exact LP/U32BE canonical
  BOPD/AAD and the pinned 232/344-byte goldens, immutable v1 outbox ciphertext,
  `.requested|.unavailable|.accessed` events, exact query/cursor and retention/
  hold contracts, AnchorIntent recovery, and the Stage 0-7 verifier with the
  external `expectedManifest` pin. F owns signing/reusable verification;
  G owns query/export/hosted verification orchestration.
- P23-001S was created and historically accepted, then reopened. Fresh child
  General review and parent General/Audit review remain required.

### Commands and Results

Fresh Round 14 local results, run after the Website source/test correction and
the active specification synchronization:

- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Application/ApplicationConsoleKernelTest.php tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php` — PASS, `29 tests / 225 assertions`.
- `pnpm --dir docs/website exec node --test --test-isolation=none tests/product-framing-contract.test.mjs` — PASS, `7/7`.
- `pnpm --dir docs/website test` — PASS, `119/119`.
- `pnpm --dir docs/website check` — PASS, `0 errors / 0 warnings / 0 hints`.
- `pnpm --dir docs/website build` — PASS, `42 pages` and `41-page` site check; existing non-fatal chunk-size/route-priority warnings were emitted.
- `pnpm --dir docs/website release:check:source` and `release:check:artifact` — PASS.
- `bash tests/Consumer/version-baseline.sh` — PASS, published `1.2.0`, historical `1.1.0`.
- `docker compose run --rm app mago format --check src tests` — PASS; `mago lint` — PASS; `mago analyze` — PASS exit `0` with unchanged baseline `71 issues` (`46 warning`, `25 help`).
- PHP management-ID guard — PASS, no matches.
- Specification 105 JSON fences — PASS, `7/7`; canonical XML — PASS, `465` bytes SHA-256 `579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968`; Markdown — PASS, `247` bytes SHA-256 `7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`.
- Read-only Markdown link validation across the exact union's 11 Markdown files — PASS, `313 total / 277 local / 0 missing`.
- `git diff --check`, exact union/staged-none, trailing-whitespace, and current-truth/static legacy guards — PASS after the final management sync.

Browser, Remote CI, deployment, publication, and release execution were not
run; this is a proposal/documentation contract correction and no external
authority was changed. No prior Round 13 PASS evidence is reused as Round 14
evidence.

### Acceptance Criteria

- [x] Dedicated ProjectionBuildSet readers, fixed flock lifecycle, atomic
  A/B/C ownership, strategy wire, and active legacy-source guard are synced.
- [x] Segment/store/outbox, access/query/cursor, AnchorIntent, verifier
  Stage 0-7, trust/freshness, manifest identity/path, retention/hold, and
  event-family contracts are synced.
- [x] Website visual-text guard remains dependency-backed and preserves
  `aria-hidden`/`inert` rendered text, hard-hidden subtrees, and visible
  visibility overrides with paired alias fixtures.
- [ ] Fresh child General review, parent General/Audit review, and
  Orchestrator acceptance.

### Remaining Issues

Fresh P23-001S General review, fresh parent General and Audit review, and
Orchestrator acceptance remain. P23-001S must not be recreated. P23-001R and
ordered downstream remain blocked until acceptance; no 1.3 capability is
public or released.

### Suggested Next Action

The exact-15 Local Gate is frozen and PASS; request fresh child General review,
then request independent parent General/Audit review and Orchestrator acceptance.

## Historical Round 15 Completion Report — superseded review checkpoint

### Summary

Round 15 closes the remaining bounded contract findings. The Website guard now
uses visible body text only, treats `visibility:initial` as visible, and keeps
block boundaries. D145 and Specifications 105/106 mark the former
side-effect-free Guide claim historical. The current Audit contract pins the
exact requestRef vector, Query result/cursor states and HMAC wire,
and a complete AnchorIntent closed schema with receipt input persistence,
lease fencing, and no-N+1 retry identity. Stable `1.2` remains current and
all 1.3 capability work remains proposal-only.

### Changed Files

The allowed union remains exact 15 paths: the existing parent/child
management/specification files plus the Website contract script/test; the
Guide and two PHP behavior tests remain byte-identical. No Production Code,
dependency, generated artifact, release authority, commit, stage, push, PR,
CI, deploy, or release changed.

### Decisions and Assumptions

- `ProjectionBuildSet v1` is the only active projection wire; route fixed
  metadata is combined at build time and route/schedule readers consume one
  dedicated projection each.
- The Round15 149-byte/
  `b063819f7388aa541daab07c2e537df29b98648a81917929338bb63e4a990267`
  value is historical invalid diagnostic evidence from shell quote loss. The
  current independent requestRef golden is literal JCS 121 bytes, LP preimage
  165 bytes, SHA-256
  `692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c`.
  Query cursor HMAC authentication is not record decryption.
- P23-001S is created and historically accepted, then reopened; its current
  status is Round 15 `Correction Implemented / Review Requested`.

### Commands and Results

Historical Round 15 gates after the final Website/specification source edits:

- focused Website contract: PASS, `7/7`;
- full Website test: PASS, `119/119`;
- Website check: PASS, `0 errors / 0 warnings / 0 hints`;
- Website build: PASS, `42` pages and `41`-page site check, with only the
  existing non-fatal chunk-size/route-priority warnings;
- `release:check:source` and `release:check:artifact`: PASS;
- focused PHP: PASS, `29 tests / 225 assertions`;
- version baseline: PASS, `published=1.2.0 historical=1.1.0`;
- Mago format: PASS; Mago lint: PASS; Mago analyze: PASS exit `0`, unchanged
  baseline `71 issues` (`46 warning / 25 help`);
- PHP management-ID guard: PASS, no matches; exact union: PASS, 15 paths and
  staged 0; `git diff --check`: PASS; exact-15 trailing-whitespace guard: PASS;
  Spec105 JSON fences: PASS, 7/7 parsed; canonical XML: PASS, 465 bytes SHA
  `579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968`;
  Markdown: PASS, 247 bytes SHA
  `7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`;
  source-notation equality: PASS; read-only Markdown links: PASS, 313 total,
  277 local, 0 missing; Round15 current-truth/static guard: PASS. These are
  final management-only checks after the fresh source/specification gates;
  historical Round14 PASS evidence is not reused.

### Acceptance Criteria

- [x] Website body/title, visibility-initial, and block-boundary fixtures are
  synchronized for primary and alias HTML surfaces.
- [x] Former Guide wording is historical and current child/parent status is
  Round 15 Review Requested.
- [x] RequestRef, AuditQuery/cursor, and AnchorIntent exact contracts are
  synchronized across the active specifications.
- [x] Fresh Round15 Local Gate is PASS; independent child/parent/Orchestrator
  reviews remain.

### Remaining Issues

Fresh P23-001S General review, fresh parent General and Audit review, and
Orchestrator acceptance remain. P23-001R and ordered downstream remain after
acceptance; no 1.3 capability is public or released.

### Suggested Next Action

The complete Round15 Local Gate is frozen and PASS on the exact-15 union; route
the child General review, parent General/Audit review, and Orchestrator
acceptance. Do not commit or stage before acceptance.

## Historical Round 16 Completion Report — superseded review checkpoint

### Summary

Round 16 corrects only the requestRef golden. The Round15 149-byte value is
historical invalid diagnostic evidence caused by shell quote loss. The current
formula is unchanged and the literal JCS/LP/digest is 121 bytes / 165 bytes /
`692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c`.
Query/cursor, AnchorIntent, Website/Guide/PHP child paths, and the Stable 1.2 /
unreleased 1.3 boundary remain unchanged. Historical status was Round16 `Local Gate Green /
Independent Review Requested`, not Accepted or Decided.

### Changed Files

Only the eight parent files are changed in Round16: STATE, TODO, parent Task,
parent Report, Spec README, D145, Specification 105, and Specification 106.
The child Task/Report, Website script/test, Guide, and two PHP tests remain
byte-identical. No Production Code, dependency, generated artifact, commit,
stage, push, PR, CI, deploy, or release action occurred.

### Decisions and Assumptions

- The exact requestRef is the stated U32BE LP/domain plus JCS formula. The
  correctly quoted Node and Docker PHP recomputations are independent evidence:
  JCS 121 UTF-8 bytes, LP preimage 165 bytes, SHA-256
  `692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c`.
- The former 149-byte / `b063819f...` result is retained only as historical
  invalid diagnostic evidence and is not a contract or release claim.

### Commands and Results

- Safely quoted Node and Docker PHP implementations independently reproduce
  literal JCS `121` bytes, LP preimage `165` bytes, and SHA-256
  `692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c`.
- Focused Website contract: PASS, `7/7`; full Website test: PASS, `119/119`;
  Website check: PASS, `0 errors / 0 warnings / 0 hints`.
- Website build: PASS, `42` pages and `41`-page site check; only the existing
  non-fatal chunk-size and route-priority warnings were emitted.
- Source and Artifact release guards: PASS; version baseline: PASS,
  `published=1.2.0 historical=1.1.0`.
- Focused PHP: PASS, `29 tests / 225 assertions`.
- Mago format and lint: PASS; analyze: PASS exit `0`, unchanged baseline `71`
  issues (`46 warning / 25 help`).
- PHP management-ID guard: PASS, no matches; `git diff --check`: PASS;
  exact-15 scope: PASS, staged `0`; trailing-whitespace guard: PASS.
- Specification 105 JSON fences: PASS, `7/7`; canonical XML: PASS, `465`
  bytes SHA-256
  `579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968`;
  canonical Markdown: PASS, `247` bytes SHA-256
  `7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`;
  source-notation equality: PASS.
- Read-only Markdown links across the exact union's 11 Markdown files: PASS,
  `313 total / 277 local / 0 missing`.

No Round15 gate result is reused.

### Acceptance Criteria

- [x] Current D145, Specifications 105/106, README, Task, Report, STATE, and
  TODO use the 121-byte JCS / 165-byte LP / `692080...` requestRef golden.
- [x] The invalid 149-byte diagnostic is historical-only and Query/cursor,
  AnchorIntent, and child Website/PHP boundaries remain unchanged.
- [x] Fresh Round16 complete exact-15 Local Gate is Green.
- [ ] Child General review, parent General/Audit review, and Orchestrator
  acceptance.

### Remaining Issues

Fresh child General review, parent General/Audit review, and Orchestrator
acceptance remain. P23-001R and ordered downstream remain after acceptance;
no 1.3 capability is public or released.

### Suggested Next Action

Freeze the exact-15 hashes, route the child General review, then route parent
General/Audit review and Orchestrator acceptance.

## Historical Round 17 Completion Report — superseded review checkpoint

### Summary

Round 17 implements the historical child General findings inside the exact
15-path union. Stable `1.2` list behavior now has an explicit class-string /
prebuilt-instance distinction in the Guide and PHP matrix. The Website
Source/Artifact guard honors the bounded inline `hidden` plus `display` rule
and paired `cli-html` / `page:/reference/project-cli` fixtures. At that
historical checkpoint, parent and child identified Round 17 as the then-current
`Correction Implemented / Review Requested` status; Round 18 now supersedes it.
Stable `1.2` remains current and Phase 23 `1.3` remains unreleased.

### Changed Files

The exact union is the existing 15 paths: parent/child Task and Report,
STATE, TODO, Spec README, D145, Specifications 105/106, the Guide, Website
contract script/test, and the two named PHP tests. No path outside that union
was changed. No Production Code, dependency, generated artifact, commit,
stage, push, CI, deploy, publication, or release action occurred.

### Decisions and Assumptions

Class-string registration constructs during Console Kernel composition;
prebuilt registration reuses the caller's same instance without another
construction. The guard's visibility scope is parsed inline HTML only: plain
`hidden` remains non-visible, while non-`none` inline display overrides are
visible. External stylesheet/computed-layout behavior is not claimed. Round
16 child review `P1=1/P2=2/P3=0` is historical and separate.

### Commands and Results

Focused Website: PASS, `7/7`; full Website: PASS, `119/119`; Website
`content:check`: PASS, `0 errors / 0 warnings / 0 hints`; Website build: PASS,
`42` pages and `41`-page site check with only existing non-fatal chunk-size and
route-priority warnings. Source and Artifact release guards: PASS. Version
baseline: PASS, `published=1.2.0 historical=1.1.0`. Focused PHP: PASS,
`30 tests / 250 assertions`. Mago format and lint: PASS; analyze: exit `0`
with unchanged baseline `71 issues` (`46 warning / 25 help`). PHP
management-ID guard: PASS. Spec105 JSON fences: `7/7` parse PASS. Canonical
XML: `465` bytes SHA-256
`579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968` PASS;
Markdown: `247` bytes SHA-256
`7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa` PASS;
source-notation equality PASS. Markdown links: `313 total / 277 local /
0 missing`. Exact union: `15/15`, staged `0`; `git diff --check` and exact-15
trailing-whitespace guards PASS. Round17 current-truth guard PASS. The first
Mago format check identified one newly edited PHP test; the equivalent
formatter layout was applied and the final check above passed. No earlier
Round16 PASS is reused.

### Acceptance Criteria

- [x] Guide and Website markers distinguish class-string and prebuilt Command
  instance behavior.
- [x] PHP default/raw/non-short JSON/XML/Markdown prebuilt matrix passes with
  no additional construction.
- [x] Paired HTML visibility override fixtures pass through the Artifact path.
- [x] Fresh complete Local Gate; [ ] child General re-review, parent review,
  and Orchestrator acceptance.

### Remaining Issues

Child General re-review remains, followed by parent General/Audit review and
Orchestrator acceptance; then P23-001R and ordered downstream. No 1.3
capability is public or released.

### Suggested Next Action

Freeze the exact-15 Round 17 snapshot and route child General review, then
route parent General/Audit review and Orchestrator acceptance.

### Round 17 bounded follow-up evidence

At `2026-08-19T21:59:03+09:00`, the Guide sentence was replaced with
「どの一覧形式を選んでも、この生成・再利用の境界は変わりません。」 and
the same sentence was added to the Website required marker. Fresh focused
Website `7/7`, full Website `119/119`, check `0/0/0`, build `42` pages with a
`41`-page site check, release source/artifact, focused PHP `30/250`, version
baseline, Mago format/lint/analyze, management-ID, JSON `7/7`, XML/Markdown
hash, links `313/277/0`, exact15/staged0, diff/whitespace, and current-truth
guards all PASS. No external action occurred.

## Historical Round 18 Completion Report — superseded review checkpoint

### Summary

Round 18 implements the fresh child General native-visibility correction
inside the exact-15 union. The existing inline HTML guard now exposes only the
first direct `summary` of a closed `details`, hides the remaining body until
`open`, hides a plain closed `dialog`, and preserves an explicit non-`none`
inline display override for a closed dialog. Paired primary/alias fixtures
cover required-marker visibility, hidden and open old claims, first-summary
claims, visibility overrides, and `display:none`. Round 17 is historical
Changes Requested; Round 18 was then-current `Correction Implemented /
Review Requested` and is now historical after child General returned
`P1=0/P2=1/P3=0`. Stable `1.2` remains current and Phase 23 `1.3` remains
unreleased.

### Changed Files

The exact existing 15-path union remains the maximum scope. This correction
changed the Website contract script/test and synchronized STATE, TODO, parent
and child Task/Report, Spec README, D145, and Specifications 105/106. The
Guide and both PHP behavior tests remained byte-identical. No Production Code,
dependency, generated artifact, release authority, commit, stage, push, CI,
deploy, publication, or release action occurred.

### Decisions and Assumptions

Native HTML boolean `open` is presence-based. Closed `details` traversal is
limited to its first direct `summary`; a descendant visibility override cannot
reveal the closed body. A closed `dialog` is hidden without `open` unless its
effective inline `display` is non-`none`; `display:none` remains hidden. The
existing inline cascade and external stylesheet/computed-layout non-claim are
unchanged.

### Commands and Results

Fresh focused Website evidence after the final source edit: PASS, `7/7`.
The complete Website/PHP/Mago/release/version/management gate is recorded as
fresh after this source change: full Website `119/119`, check `0/0/0`, build
`42` pages with a `41`-page site check, release source/artifact PASS, focused
PHP `30 tests / 250 assertions`, version baseline PASS, Mago format/lint PASS
and analyze exit `0` with unchanged `71`-issue baseline, PHP management-ID
PASS, requestRef Node+Docker PHP `JCS 121 bytes / LP 165 bytes / SHA-256
692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c` PASS, JSON
`7/7`, XML `465 bytes SHA-256
579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968` and
Markdown `247 bytes SHA-256
7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa` PASS,
links `313/277/0`, exact15/staged0, diff/whitespace, current-status guard
with historical allowlist, and static guards PASS. No external action occurred.

### Acceptance Criteria

- [x] Closed `details`/`dialog` native visibility and inline display behavior
  are implemented with paired primary/alias fixtures.
- [x] Existing visibility cascade, raw/Search/LLM boundaries, Stable `1.2`,
  and unreleased `1.3` boundary remain unchanged.
- [x] Historical Round 18 native visibility correction was implemented; [ ]
  child General re-review, parent General/Audit review, and Orchestrator
  acceptance.

### Remaining Issues

Fresh child General re-review remains, followed by parent P23-001 General and
Audit review and Orchestrator acceptance. P23-001R and ordered downstream work
remain after acceptance; no 1.3 capability is public or released.

### Suggested Next Action

Historical next action: freeze the exact-15 Round 18 Local Gate snapshot and
route fresh child General review. Round 19 supersedes this checkpoint.

## Historical Round 19 Completion Report — superseded review checkpoint

### Summary

Round 19 made the active Specification 106 DAG authority round-neutral: the
current child Task and Report are authoritative for the sole-current review
checkpoint, while Round 18 and earlier remain historical. No production,
Guide, Website, PHP test, dependency, generated artifact, or release change
was made.

### Changed Files

Only the ten permitted management/specification files changed in this round:
STATE, TODO, parent Task/Report, child Task/Report, Specification README, D145,
and Specifications 105/106. The frozen Guide, Website script/test, and two PHP
tests remain byte-identical within the existing exact-15 union. No commit,
stage, push, CI, deploy, publish, or release occurred.

### Decisions and Assumptions

Round 19 was `Correction Implemented / Review Requested`, not Green, Accepted,
or Decided. The child verdict `P1=0/P2=1/P3=0` belongs to historical Round 18
and is not aggregated. Stable `1.2` is current; all Phase 23/`1.3` material is
proposal-only. The two report files cannot embed their own final SHA-256
without changing that hash; the final handoff therefore supplies all exact-15
hashes after the final write.

### Commands and Results

Fresh complete gate: Website focused `7/7` PASS; Website test `119/119` PASS;
Website check `0 errors/0 warnings/0 hints` PASS; Website build `42` pages and
site check `41` pages PASS; release source and artifact PASS; focused PHP
`30 tests / 250 assertions` PASS; version baseline published `1.2.0`,
historical `1.1.0` PASS; Mago format PASS, lint PASS, analyze exit `0` with the
unchanged `71`-issue baseline; PHP management-ID PASS. Independent Node and
Docker PHP requestRef recomputation both returned JCS `121` bytes, LP `165`
bytes, SHA-256
`692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c` PASS.
Specification 105 JSON fences `7/7` PASS; XML `465` bytes SHA-256
`579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968` PASS;
Markdown `247` bytes SHA-256
`7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa` PASS;
Markdown links `313 total / 277 local / 0 missing` PASS. Exact-15 scope,
staged zero, `git diff --check`, trailing whitespace, current-status and
historical allowlist guards all PASS. Build emitted only its existing chunk
size/route warnings; CSS fixture parse diagnostics were expected and the
focused test passed.

### Acceptance Criteria

- [x] Active DAG authority is round-neutral and Round 18/earlier are historical.
- [x] Round 19 current status, gate state, Remaining, and Next Action are synced.
- [x] Stable `1.2` and unreleased `1.3` boundaries are preserved.
- [ ] Fresh child General review, parent General/Audit review, and Orchestrator
  acceptance remain external review steps.

### Remaining Issues

Fresh P23-001S General review remains, followed by parent P23-001 General/Audit
review and Orchestrator acceptance. P23-001R and ordered downstream work remain
after acceptance; no 1.3 capability is public or released.

### Suggested Next Action

Route the fresh child General review, then request independent parent General
and Audit review. Await Orchestrator acceptance before P23-001R or downstream.

## Historical Round 20 Completion Report — superseded review checkpoint

### Summary

Round 20 removes the final two active Specification 106 DAG phrases that
presented Round 18 as current. The overview node and P23-001S slice now use the
round-neutral current child Task/Report authority. Round 19 is historical
Changes Requested (`P1=0/P2=1/P3=0`); neither child nor parent is Accepted.

### Changed Files

Only the ten permitted management/specification files changed in Round 20.
The Guide, Website contract script/test, and both PHP behavior tests remained
byte-identical. Production Code, dependencies, generated Artifact authority,
and release authority did not change. No commit, stage, push, CI dispatch,
deploy, publication, or release occurred.

### Decisions and Assumptions

Historical Round 18 evidence remains historical. Active DAG status uses a
round-neutral authority so a later review round does not silently become
stale. Stable `1.2` remains current; all Phase 23/`1.3` capability material is
unreleased proposal-only. Report self-hashes are supplied in the external
freeze because embedding them would change the Report bytes.

### Commands and Results

Fresh complete Local Gate after the active DAG correction: focused Website
`7/7`, full Website `119/119`, check `0/0/0`, build `42` pages and site check
`41` pages, release source/artifact, focused PHP `30 tests / 250 assertions`,
and version baseline `published=1.2.0 historical=1.1.0` all PASS. Mago format
and lint PASS; analyze exits `0` with the unchanged `71`-issue baseline (`46`
warnings, `25` help). PHP management-ID guard PASS. Node and Docker PHP both
produce requestRef JCS `121` bytes, LP `165` bytes, SHA-256
`692080e51f459d1d45ed9ce3f14c1edc273ee059e1e93d6198dce1c25511b58c`.
Specification 105 JSON fences `7/7`, canonical XML `465` bytes SHA-256
`579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968`,
Markdown `247` bytes SHA-256
`7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`,
links `313/277/0`, exact-15/staged0, diff/whitespace, active-status, and frozen
five-file guards PASS. Existing build warnings and CSS fixture diagnostics are
non-fatal and unchanged.

### Acceptance Criteria

- [x] Both stale active DAG phrases are corrected.
- [x] Round 20 status, gate, Remaining, and Next Action are synchronized.
- [x] Stable behavior and unreleased `1.3` boundaries are preserved.
- [ ] Fresh child General review, parent General/Audit review, and Orchestrator
  acceptance.

### Remaining Issues

Fresh P23-001S General review remains, followed by parent P23-001 General/Audit
review and Orchestrator acceptance. P23-001R and ordered downstream work remain
after acceptance; no 1.3 capability is public or released.

### Suggested Next Action

Freeze the exact-15 Round 20 snapshot and route fresh child General review;
after child Green, request independent parent General and Audit review.

## Historical Round 21 Completion Report — superseded review checkpoint

### Summary

Round 21 corrected the then-active management synchronization root identified by the
fresh Round 20 child General review (`P1=0/P2=1/P3=0`). The three active
D145/Specification 105/106 gate paragraphs now record the fresh Round 21 Local
Gate, and Specification 106's child-authority cutoff states that all prior
review checkpoints are historical. Fresh Round 21 child General review
returned `P1=0/P2=0/P3=0`, and the Orchestrator accepted P23-001S. At that
historical checkpoint parent P23-001 was `Parent Review Requested`, not Accepted
or Decided; Stable
`1.2` remains current and Phase 23/`1.3` remains unreleased.

### Changed Files

Only the ten permitted management/specification files changed: STATE, TODO,
the parent and child Task/Report files, Spec README, D145, and Specifications
105/106. The Guide, Website contract script/test, and both PHP behavior tests
remain byte-identical. No Production Code, dependency, generated Artifact,
release authority, commit, stage, push, CI, deploy, publication, or release
action occurred.

### Decisions and Assumptions

Round 20's child General verdict remains historical Changes Requested and is
not aggregated with any other review lane. Historical evidence sections are
preserved; only active management truth moved to Round 21. Report self-hashes
are supplied outside the Report because embedding a self-hash would change its
bytes.

### Commands and Results

The fresh complete Round 21 Local Gate was run after the final management edit.
Focused/full Website, Website check/build, Source/Artifact release guards,
focused PHP, version baseline, Mago, management-ID, JSON/XML/Markdown,
Markdown links, exact-15/staged-none, frozen-file, diff, whitespace, and
current-truth guards all PASS; exact command output and counts are recorded in
the child Report's matching Round 21 section and in the final handoff.

### Acceptance Criteria

- [x] The three active Round 19 gate paragraphs now state the fresh Round 21
  Local Gate and Round 20-and-earlier historical boundary.
- [x] Specification 106's active child-authority cutoff is round-neutral for
  all prior checkpoints.
- [x] Round 21 status, gate, Remaining, and Next Action are synchronized across
  the ten management/specification files.
- [x] Fresh child General re-review returned `P1=0/P2=0/P3=0`; P23-001S was
  accepted by the Orchestrator.
- [ ] Parent General/Audit review and parent Orchestrator acceptance.

### Remaining Issues

Parent General/Audit review and parent Orchestrator acceptance remain.
P23-001R and ordered downstream work remain after acceptance; no 1.3
capability is public or released.

### Suggested Next Action

Freeze the accepted-child exact-15 snapshot and request independent parent
General/Audit review. Do not claim parent Green or Acceptance before those
reviews.

## Historical Round 22 Completion Report — superseded review checkpoint

### Summary

Round 22 implemented the bounded parent correction from the nine packet roots.
Its then-current parent status was `Round 22 Correction Implemented / Review
Requested`; Round 21 remains the accepted child checkpoint. Stable `1.2` is
current and Phase 23/BlackOps `1.3` remains proposal-only and unreleased.

### Changed Files

Only the eight allowed management/specification files changed:

- `develop/STATE.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md`
- `develop/orchestration/reports/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md`
- `develop/spec/README.md`
- `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`
- `develop/spec/105-blackops-1-3-capability-contract.md`
- `develop/spec/106-phase-23-delivery-plan.md`

The P23-001S Task/Report, Guide, Website contract script/test, both PHP tests,
Production Code, dependencies, generated artifacts, and release authority are
frozen and remain unchanged.

### Decisions and Assumptions

- Application ownership of `worker:list`/`worker:ls` is permitted; only an
  unregistered name follows normal Symfony unknown-command behavior. BlackOps
  supplies no Framework Job registry or built-in Job listing.
- Every ProjectionBuildSet reader validates root plus all three files under one
  shared lock before selecting dedicated records; a non-selected mismatch
  invalidates the set. List/ls recovery remains exit `0`; route/schedule
  artifact failure remains exit `2`.
- The fixed public route is `{operationId}` and structurally equivalent
  placeholders collide. Operation reads and service actions use separate exact
  families and delivery guarantees.
- Genesis sequence is `1`; subsequent segments use the prior final plus one;
  `recordCount=lastSequence-firstSequence+1`, with positive `1-42`/`43-50`
  examples and gap/overlap negatives.
- Only framework-derived target/role `system:global/audit-control-v1` has the
  finite control signing exemption; ordinary signatures require
  `audit.access.sign.accessed` before release.
- AuditHold v1 and AuditReadContext v1 are closed as specified in the current
  D145/105/106 blocks, including exact hash domains, requestRef formula, caps,
  bounds, and golden parity fixtures.

### Commands and Results

After the final edit, the complete fresh Round 21-equivalent Local Gate was
run with the following exact commands and results:

- `pnpm --dir docs/website exec node --test --test-isolation=none tests/product-framing-contract.test.mjs` — PASS, `7/7`.
- `pnpm --dir docs/website test` — PASS, `119/119`.
- `pnpm --dir docs/website check` — PASS, `0 errors / 0 warnings / 0 hints`.
- `pnpm --dir docs/website build` — PASS, `42` pages; embedded site check `41` pages; only existing non-fatal chunk-size/route-priority warnings.
- `pnpm --dir docs/website site:check` — PASS, `41` pages.
- `pnpm --dir docs/website release:check:source` — PASS.
- `pnpm --dir docs/website release:check:artifact` — PASS.
- `bash tests/Consumer/version-baseline.sh` — PASS, published `1.2.0`, historical `1.1.0`.
- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Application/ApplicationConsoleKernelTest.php tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php` — PASS, `30 tests / 250 assertions`.
- `docker compose run --rm app mago format --check src tests` — PASS, all files already formatted.
- The path-scoped `docker compose run --rm app mago lint src tests` was a
  historical diagnostic with exit `1` because it bypasses the repository
  baseline; it is not a passing gate result and is superseded by the canonical
  Round 23 invocations below.
- `docker compose run --rm app mago analyze` — PASS, exit `0`, unchanged baseline `71` issues (`46 warnings / 25 help`).
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'` — PASS, no matches.
- `node` JSON fence harness for `develop/spec/105-blackops-1-3-capability-contract.md` — PASS, `7/7` JSON fences parse.
- `node` canonical fixture harness for Specification 105 — PASS, XML `465` bytes / SHA-256 `579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968`; Markdown `247` bytes / SHA-256 `7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`.
- Node ReadContext LP/JCS harness — PASS: JCS `150` bytes, LP preimage `192` bytes, `oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`.
- `docker compose run --rm app php -r '...'` ReadContext parity harness — PASS with the same `150 / 192` byte lengths and requestRef.
- Node exact-union Markdown link harness over `11` Markdown files — PASS, `313 total / 277 local / 0 missing`.
- Shell exact-scope/freeze harness — PASS, exact-15 paths, staged `0`, frozen files `7/7`.
- `git diff --check` plus exact-union trailing-whitespace guard — PASS.
- Active Round22 current-truth/static guard — PASS; historical sections were not rewritten.

No commit, stage, push, CI, deploy, publish, tag, or release action was
performed. Browser and Remote CI gates were not in scope for this bounded
management/specification correction.

### Acceptance Criteria

- [x] All nine bounded correction roots had one then-current normative rule,
  owner, counterexample, and fixture assignment.
- [x] Then-current status/index/summary wording was Round 22; P23-001S is
  Round 21 Accepted and no child packet is recreated.
- [x] Historical review evidence remains historical and is not rewritten.
- [x] Exact eight-file write scope and exact-15 freeze scope are preserved.
- [x] Fresh complete Local Gate passes after the final edit.
- [ ] Parent General/Audit review and parent Orchestrator acceptance.

### Remaining Issues

Fresh parent General/Audit review and parent Orchestrator acceptance remain.
After acceptance, P23-001R and ordered downstream Phase 23 work remain. No
production, CI, deployment, publication, or release claim is closed here.

### Suggested Next Action

Orchestrator independently reviews the fresh Local Gate, exact commands, and
exact-15 hashes, then routes separate parent General/Audit review. Acceptance
and any external/release action require later authorized gates.

Historical Round 22 handoff did not embed a final non-Report hash inventory;
the superseding Round 23 completion report below is the authoritative handoff.

## Historical Round 23 Completion Report — superseded review checkpoint

### Summary

Round 23 implements the bounded parent correction requested after the fresh
Round 22 parent General `P1=3/P2=1/P3=0` and Audit `P1=4/P2=0/P3=0` reviews.
Round 23 is the sole current `Correction Implemented / Review Requested`
checkpoint; Round 21 remains the accepted P23-001S child, and Round 22 is
historical Changes Requested. Stable `1.2` remains current and all Phase 23 /
BlackOps `1.3` capability material remains an unreleased proposal.

The eight current roots are synchronized: `worker:list`/`worker:ls` are
Application-owned and unreserved; every projection reader validates the root
and all three files under one shared lock before selected-row rendering;
canonical Mago/hash evidence is recorded; Round 23 is the sole current status;
all operation-read outcomes use required delivery with durable append before
response/failure; later segments continue from the previous final sequence;
retention metadata is exactly two-state with a separate `AuditHold`; and
`AuditReadContext` uses the exact SHA-256-wrapped requestRef algorithm with an
algorithm-substitution negative.

The `blackops-japanese-docs` skill and its required style checklist influenced
the correction by checking factual Japanese/specification wording, Stable /
unreleased release boundaries, first-reader paragraph clarity, consistent
Projection/operation/retention/requestRef terminology, and explicit historical
section labels. No public Japanese Guide or generated public artifact was
changed.

### Changed Files

Only the exact eight allowed files changed in this Round 23 worker scope:

- `develop/STATE.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md`
- `develop/orchestration/reports/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md`
- `develop/spec/README.md`
- `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`
- `develop/spec/105-blackops-1-3-capability-contract.md`
- `develop/spec/106-phase-23-delivery-plan.md`

The P23-001S Task/Report, Guide, Website contract script/test, both PHP tests,
Production Code, dependencies, generated artifacts, release authority, and
other frozen paths were not edited by this Round 23 correction.

### Decisions and Assumptions

- P23/BlackOps `1.3` remains proposal-only; Stable `1.2` and release authority
  remain unchanged. The final status is not Green, Accepted, Decided, or
  Released.
- Historical Round 21 and Round 22 status/gate/Remaining/Next evidence is
  preserved under explicit historical headings. P23-001S remains the accepted
  child and is not recreated.
- `worker:list`/`worker:ls` are not reserved or BlackOps-owned. A registered
  Application canonical command or alias executes normally; an unregistered
  name follows normal Symfony unknown-command behavior; no Framework Job
  registry or built-in Job listing is provided.
- Root-plus-all-three validation is complete-set validation under one shared
  lock; only afterward does a reader select and render dedicated rows, and a
  non-selected mismatch invalidates the set.
- Operation reads use required catalog delivery for all
  `access.operation_{status,journal,outcome,diagnostics}.requested|unavailable|accessed`
  outcomes. The canonical append is durably persisted before a read response
  or failure is released.
- Only a genesis segment starts at sequence `1`; a later segment starts at
  the previous final plus one, with
  `recordCount=lastSequence-firstSequence+1`; current positives are `1-42`
  and `43-50`.
- `AuditRetentionState v1` is metadata-only with exactly
  `retained|eligible_for_future_deletion`; it has no hold field or `held`
  transition. `AuditHold v1` is the separate append-only revision object.
- `AuditReadContext v1` uses
  `requestRef = "oid:v1:" || HEX_LOWER(SHA-256(LP(domain) || LP(JCS(context))))`,
  with domain `blackops.audit.read.request-ref.v1`; the LP concatenation is
  the preimage, not the final reference.
- The path-scoped Mago invocation `docker compose run --rm app mago lint src
  tests` was deliberately not rerun in this fresh gate. Its known baseline-
  bypass behavior is exit `1`; no passing claim is made. The CI-canonical
  commands below are the authoritative results.

### Exact Commands and Results

The complete fresh Local Gate was rerun by the Orchestrator after the final
bounded wording cleanup and is PASS. The exact command lines and results below
are the current gate evidence; no path-scoped Mago PASS is claimed.

- `pnpm --dir docs/website exec node --test --test-isolation=none tests/product-framing-contract.test.mjs` — PASS, `7/7`.
- `pnpm --dir docs/website test` — PASS, `119/119`.
- `pnpm --dir docs/website check` — PASS, `0 errors / 0 warnings / 0 hints`.
- `pnpm --dir docs/website build` — PASS, `42` pages; embedded artifact check and site check completed; existing non-fatal chunk-size/route-priority warnings only.
- `pnpm --dir docs/website site:check` — PASS, `41` pages. (The build also ran this check; this explicit command is part of the gate record.)
- `pnpm --dir docs/website release:check:source` — PASS.
- `pnpm --dir docs/website release:check:artifact` — PASS.
- `bash tests/Consumer/version-baseline.sh` — PASS, `published=1.2.0 historical=1.1.0`.
- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Application/ApplicationConsoleKernelTest.php tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php` — PASS, `30 tests / 250 assertions`.
- `docker compose run --rm app mago format --check src tests` — PASS, all files already formatted.
- `docker compose run --rm app mago lint` — PASS, exit `0`; `186` issues filtered by the repository baseline, no issues found.
- `docker compose run --rm app mago lint --verify-baseline` — PASS, exit `0`; baseline is up to date.
- `docker compose run --rm app mago analyze` — PASS, exit `0`; unchanged baseline `71` issues (`46` warnings / `25` help).
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'` — PASS, no matches.
- Specification 105 JSON fence Node harness — PASS, `7/7` JSON examples parse.
- Specification 105 XML/Markdown fixture Node harness — PASS: XML `465` bytes / SHA-256 `579e79dc9239f6a97703dbab51fcdf4e8786ee72292d2592da219a6f156ac968`; Markdown `247` bytes / SHA-256 `7ce621ce67fb5815d8553b7dad58030cb9d31aae96dccfa8e5b30e0239e7c7fa`.
- Node `AuditReadContext` harness — PASS: JCS `150` bytes, LP preimage `192` bytes, requestRef `oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`; algorithm-substitution negative PASS.
- Docker PHP `AuditReadContext` harness — PASS with the same `150 / 192` byte lengths and requestRef; algorithm-substitution negative PASS.
- Exact-union Markdown link harness — PASS, `313 total / 277 local / 0 missing` across `11` Markdown files.
- Exact-15 scope/frozen-file/staged-none harness — PASS, `8` allowed + `7` frozen paths; staged `0`.
- `git diff --check` and exact-union trailing-whitespace guard — PASS.
- Active Round 23 status/contradiction guard — PASS; stale active Round 21/Round 22 headings, placeholder hash text, path-scoped Mago PASS claim, active `reads only`/`consumes only`, active three-state retention, and active durable-not-required text were absent.

No commit, stage, push, remote CI dispatch, deploy, publish, tag, release, or
other external mutation was performed.

### Acceptance Criteria

- [x] All eight Round 23 roots have one current normative rule, owner,
  counterexample, and future fixture assignment.
- [x] Application-owned/unreserved worker wording replaces explicit rejection;
  normal Symfony unknown behavior and no Framework Job listing are explicit.
- [x] Readers validate root plus all three Projection files under one shared
  lock before selecting/rendering dedicated rows; non-selected mismatch fails
  the complete set.
- [x] Canonical Mago lint and baseline verification are recorded as exit `0`;
  the path-scoped diagnostic is distinguished and not claimed as PASS.
- [x] Round 23 is the sole current parent checkpoint and prior active
  Round21/Round22 status/gate/Remaining/Next sections are historical.
- [x] All operation-read outcomes have required delivery and durable append
  before response/failure.
- [x] Genesis-only sequence one, later prior-final-plus-one, formula, and
  `1-42`/`43-50` examples are current.
- [x] Retention is metadata-only two-state and `AuditHold` is separate;
  requestRef uses the exact SHA-256-wrapped algorithm and negative fixture.
- [x] Exact eight-file scope, frozen paths, Stable boundary, and unreleased
  `1.3` boundary are preserved.
- [x] Orchestrator fresh complete Local Gate after this final bounded cleanup.
- [ ] Fresh parent General/Audit review and parent Orchestrator acceptance.

### Remaining Issues

Fresh parent General/Audit review and parent Orchestrator acceptance remain.
After acceptance, P23-001R and ordered downstream Phase 23 work remain. No
Production Code, public documentation, release, deployment, or capability
delivery issue is claimed closed by this worker correction.

### Suggested Next Action

Route this exact Local Gate freeze to separate parent General/Audit review.
Parent acceptance must precede P23-001R and ordered downstream work; any
release or publication requires later authorized gates.

### Final Non-Report Hashes and Report Self-Hash Handoff

The exact final SHA-256 inventory for the seven non-Report files is:

```text
develop/STATE.md afa6fb0583f70783bea90c52df839d86dbb40429eacb219855e9737f83407791
develop/TODO.md 123578dfc70277672df13ce0b5dd89f23fc9d76ea22a1740c8b839c4de72cf8d
develop/orchestration/tasks/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md 94616d2a57c4213e8934498cb6b20ff07f3f6d2852973d8ae4d990137497d2b0
develop/spec/README.md 33bad58e71d8c1b637ea366835c99fdbe3870f05dc61cf8d139d65ce49a50bb9
develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md 2c91b66b6a6c5c9004771a1e101f0ee12a91a095ccd459c4ff6f96bc64787c8a
develop/spec/105-blackops-1-3-capability-contract.md 0efd663f5eab6d584058a6dbe99ac28cb4ff273ee9809cdd94549fcd232821ae
develop/spec/106-phase-23-delivery-plan.md 44de50a4640ebdb9c65c6154a898088abbbabb37efc870a3eb428a2f8da0542f
```

Report self-hash is deliberately not embedded because changing this Report
would change its own digest. Handoff command: `sha256sum
develop/orchestration/reports/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md`.
The worker returns that self-hash separately after this final edit; the seven
non-Report hashes above are the immutable handoff inputs.

## Historical Round 24 Completion Report — superseded review checkpoint

### Summary

Round 24 implements the seven bounded parent-correction roots in the exact
11-file writable scope. The Application Bootstrap Guide and the Website
Source/HTML/raw/Search/LLM contract now state the observed format-sensitive
Console timing boundary. Specifications and D145 now promote the complete-set
ProjectionBuildSet wire, exact AuditRead/AuditAccess formulas, Stage 6 finite
precedence, and the Query/cursor/Outbox/AnchorIntent current wires. Round 23
is historical Changes Requested; Round 24 remains
`Round 24 Correction Implemented / Review Requested`, not Green, Accepted,
Decided, or Released.
The fresh Round 23 parent General review remains `P1=2/P2=2/P3=0` and the
fresh Audit review remains `P1=3/P2=1/P3=0`; these lanes stay separate and
Round 22 values remain only in Historical sections.

### Current exact wire handoff

The current ProjectionBuildSet v1 wire is implementation-complete and does not
depend on a Historical section. Its root is exactly
`{schemaVersion,kind,applicationBuildId,buildSetHash,projections}` with
`schemaVersion=1`, `kind="projection_build_set"`, and exactly the three
projection keys `command`, `route`, and `schedule`. Each descriptor is exactly
`{kind,inputHash,path,sha256}`; `sha256` is the lowercase SHA-256 of the exact
raw projection bytes, and each distinct `path` is a 1..256-byte root-relative
POSIX path with no absolute form, backslash, empty/`.`/`..` component,
control, symlink, or special file. Descriptor kinds are exactly
`blackops_command_projection|blackops_route_projection|blackops_schedule_projection`.
The projection file is exactly
`{schemaVersion,kind,applicationBuildId,inputHash,records}` with
`schemaVersion=1`, file `kind` equal to its descriptor kind,
`applicationBuildId` equal to the root value, and `inputHash` equal to the
descriptor value. The typed encoding
is `ENC(string)="s:"||decimal_utf8_byte_length||":"||UTF8(string)`,
`ENC(integer)="i:"||canonical_decimal_digits`, `ENC(null)="n:"`, and
`ENC(bytes)="b:"||decimal_byte_length||":"||bytes`; the hashes are exactly
`SHA-256(ENC("blackops.projection-build-set.v1")||ENC(JCS(root excluding buildSetHash)))`
for `buildSetHash` and
`SHA-256(ENC("blackops.projection-input.v1")||ENC(JCS({kind,schemaVersion,applicationBuildId,sourceRecords})))`
for `inputHash`. Command records are
`{name,aliases,description,origin,hidden}` sorted by name then aliases; route
records are `{method,path,strategy,operationType,origin}` sorted by
`(path,method,origin,operationType,strategy)` with nulls first; schedule records
are `{name,operationType,cron,timezone,strategy}` sorted by
`(name,operationType)` with nulls first. Sorts use unsigned UTF-8 bytes,
`origin` is exactly `operation_manifest|framework_fixed`, unknown/duplicate/
missing/wrong-type/unsorted/non-NFC/control/cap+1 values fail closed, root is
UTF-8 RFC 8785 JCS plus one LF at most 64 KiB, and each projection is at most
16 MiB with 0..4096 records. Readers take shared flock on the fixed regular
nofollow `.blackops-projection-build-set.lock`, open and validate the root and
all three same-fd files, then select/render only dedicated rows; any non-selected
mismatch invalidates the complete set. The exclusive publisher holds the lock
through bounded generation writes, flush/fsync, atomic publication, directory
fsync, and generation-safe GC of only paths unreferenced by the current root.

The current AuditQueryRequest v1 is the closed object
`{schemaVersion:1,kind:"audit_query_request",access,partitionId,filters,snapshotHeadSequence,limit,cursor}`.
Its `access` is the exact nine-field AuditAccessContext; `partitionId` is the
authorized `system:global|tenant:<64 lowercase hex>` form; and `filters` is
exactly `{eventCategory:null|string,recordIdPrefix:null|string,fromSequence:null|positive,toSequence:null|positive}`
with null bounds or `fromSequence<=toSequence`. `eventCategory` is null or an
AuditRecord event-category value; `recordIdPrefix` is null or a non-empty
prefix. Every non-null filter string and every string in the cursor payload is
NFC, has no control character, and is 1..256 UTF-8 bytes; null remains allowed.
A positive JSON-safe snapshot
head, limit `1..1000`, strict first-page cursor nullability, closed fields, and
the 64 KiB request cap are required before lookup. AuditQueryResult v1 is
exactly `{schemaVersion:1,kind:"audit_query_result",status,partitionId,records,snapshotHeadSequence,nextCursor,reasonCode}`
with this complete matrix:

| status | partitionId | records | snapshotHeadSequence | nextCursor | reasonCode |
| --- | --- | --- | --- | --- | --- |
| `ok` | authorized non-null | complete non-empty `1..limit` records | positive | only when more rows remain | null |
| `empty` | authorized non-null | `[]` | positive | null | null |
| `unavailable` | null | null | null | null | `resource_unavailable` |

Records are authorized, decrypted, integrity-checked, complete, and ascending
by partition-global sequence; the result JCS+LF cap is 1 MiB inclusive and
cap+1 fails before allocation. The cursor is exactly
`aqc:v1:<BASE64URL_NOPAD(JCS(payload))>.<BASE64URL_NOPAD(tag)>`, with a closed
payload `{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,snapshotHeadSequence,lastSequence,issuedAt,expiresAt,keyId}`
and a 32-byte role-bound HMAC-SHA-256 tag over
`LP("blackops.audit.query.cursor.v1")||LP(JCS(payload))`. Canonical
base64url/JCS, closed-field/cap, partition/filter/head/last, key-role, and
UTC `issuedAt<expiresAt` checks precede constant-time tag comparison; lookup
and decrypt happen only afterward. The cursor is ASCII, has one literal dot,
no padding, and is at most 2048 bytes.

The current AuditOutboxRecord v1 row is exactly
`{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,availableAt,leaseFencingToken,leaseExpiresAt,protectedBody}`.
`protectedBody` is binary BOPD v1 containing JCS(AuditIngressEnvelope v1)
without LF. With `LP(x)=U32BE(byte_length(x))||x`, its exact AAD is
`LP("blackops.audit.outbox.aad.v1")||LP("AuditOutboxRecord")||LP("1")||LP("1")||LP("1")||LP(exactKeyId)||LP(recordId)||LP(partitionId)||LP(tenantPresenceAndTypeAndId)||LP(ASCII(canonicalDecimal(ingressOrdinal)))||LP(decoded32(idempotencyKeyHash))||LP(decoded32(contentHash))`.
The idempotency hash is
`H(ENC("blackops.audit.outbox.idempotency.v1")||ENC(partitionId)||ENC(NFC(idempotencyKey)))`;
the raw key never appears clear. Pending/leased/relayed/poisoned state and
lease nullability are closed, immutable identity/protected bytes are reused on
retry, same-key different-body conflicts, FIFO/fencing substitutions fail,
the logical body is at most 1 MiB, and BOPD allocation is bounded by
`52+keyIdUtf8Bytes`.

AnchorIntent v1 is exactly
`{schemaVersion:1,kind:"audit_anchor_intent",intentId,state,partitionId,checkpointId,checkpointHash,checkpointBytesHash,anchorStoreRef,anchorObjectRef,expectedPredecessorVersion,expectedPredecessorHash,anchorReceiptId,idempotencyKey,conditionalCreateToken,acceptedObjectVersion,acceptedObjectHash,acceptedAnchoredAt,receiptSignedAt,receiptSigningKeyRef,receiptBytesHash,anchorReceiptHash,leaseFencingToken,leaseExpiresAt,version,updatedAt}`.
IDs/refs/tokens are bounded NFC/no-control noncredentials, hashes are
lowercase 64-hex, versions are positive, times have six UTC fractional digits,
and acceptedObjectHash equals checkpointBytesHash. `prepared` has
`acceptedObjectVersion`, `acceptedObjectHash`, and `acceptedAnchoredAt` all
null and all four receipt fields null; `externally_accepted` has the accepted
triplet non-null and receipt fields null; `receipt_inputs_persisted` adds
receiptSignedAt/receiptSigningKeyRef/receiptBytesHash while anchorReceiptHash
is null; `receipt_persisted|acknowledged` has all accepted and receipt fields
non-null. The exact receipt-byte hash is
`SHA-256(ENC("blackops.anchor.receipt.bytes.v1")||ENC(anchorReceiptId)||exact unsigned receipt JCS+LF bytes)`;
anchorReceiptHash is the unsigned semantic hash, not a signed-file raw digest.
Lease fields are both null at rest or both non-null under one fenced claim;
CAS prevents stale writes and retries do not create N+1. P23-003B owns the
outbox row/AAD, P23-003C owns transaction/idempotency/lease/relay, P23-003F
owns AnchorIntent/verification, and P23-003G owns query/cursor orchestration.

The current verifier Stage 6 finite precedence is catalog or authenticated
record-chain graph/hash/semantic mismatch code `14` with safe name
`authenticated_graph_invalid`, then mode/coverage code `16` with
`mode_coverage_invalid`, then freshness code `18` with
`freshness_boundary_unavailable`; global failures retain post-signature top
references, return `partitions:[]`, and never reopen listed files.

### Changed Files

The exact Round 24 files are:

- `develop/STATE.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md`
- `develop/orchestration/reports/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md`
- `develop/spec/README.md`
- `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`
- `develop/spec/105-blackops-1-3-capability-contract.md`
- `develop/spec/106-phase-23-delivery-plan.md`
- `docs/guide/application-bootstrap.md`
- `docs/website/scripts/product-framing-contract.mjs`
- `docs/website/tests/product-framing-contract.test.mjs`

The P23-001S Task/Report, `docs/guide/project-cli.md`, both PHP behavior
tests, and every other path remain frozen for this Round 24 correction.

### Decisions and Assumptions

- The `blackops-japanese-docs` skill and its required style checklist guided
  factual Japanese/specification wording, first-reader paragraph order,
  terminology consistency, release-boundary separation, and explicit
  Historical labels. It did not authorize public Stable-1.2 capability claims.
- Stable `1.2` remains current and Phase 23/`1.3` remains an unreleased
  proposal. No Production Code, dependency, artifact, release authority,
  commit, stage, push, CI dispatch, deploy, publish, tag, or release changed.
- The current AuditRead hash input is exactly the six fields
  `{purpose,actor,execution,tenant,requestedAt,nonce}` and the current
  AuditAccess hash input is exactly
  `{purpose,actor,tenant,requestedAt,nonce}`. LP is only the SHA-256 preimage;
  the final value includes the exact `oid:v1:` wrapper and lowercase digest.
- No unclosed current Round 24 root remains in the seven requested areas.

### Exact Commands and Results

- Focused Website contract:
  `pnpm --dir docs/website exec node --test --test-isolation=none tests/product-framing-contract.test.mjs` — PASS, `8/8`.
- Full Website — PASS, `120/120`; the first build correctly failed at the
  production Application Bootstrap alias guard, and the bounded script/test
  correction was followed by a fresh build PASS with 42 pages and embedded
  artifact/site checks. Only existing nonfatal chunk-size/route-priority
  warnings remained.
- `pnpm --dir docs/website site:check` — PASS, 41 pages, `0` errors/warnings/
  hints.
- `release:check:source` — PASS; `release:check:artifact` — PASS.
- Version baseline — PASS, published `1.2.0`, historical `1.1.0`.
- Focused PHPUnit — PASS, 30 tests / 250 assertions.
- `mago format` — PASS; CI-canonical `docker compose run --rm app mago lint`
  — PASS, 186 baseline-filtered with no issues; `docker compose run --rm app
  mago lint --verify-baseline` — PASS; `mago analyze` — exit `0` with existing
  71 issues (46 warning / 25 help). The path-scoped `mago lint src tests`
  diagnostic is not claimed as a passing gate.
- PHP management-ID guard — PASS.
- Node static guards — JSON `7/7`; XML `465`, hash `579e...`; Markdown `247`,
  hash `7ce6...`; AuditRead `150/192` with `bfad...df0cf` and negative matrix;
  AuditAccess `103/147` with `1c505...e241` and negative matrix; links `315`
  total / `279` local / `0` missing; exact-16/trailing guards `0`.
- Docker-PHP — PASS for both exact requestRef goldens and negative matrices.
- Exact scope — 16 paths (`11` writable + `5` frozen), staged `0`,
  `git diff --check` PASS, and `HEAD=main` at
  `5ad87557c08e20c3bcc6d77be83804001ba3eb80`.
- No external or Git mutation was performed.

### Acceptance Criteria

- [x] All seven Round 24 roots have one current rule, owner, counterexample,
  and current specification/management wording.
- [x] Application Bootstrap timing is synchronized across Guide Source and the
  Website Source/HTML/raw/Search/LLM guard inventory.
- [x] ProjectionBuildSet complete-set validation, exact AuditRead and
  AuditAccess formulas, Stage 6 code/precedence, and Query/cursor/Outbox/
  AnchorIntent wires are current and Historical dependencies are not required.
- [x] Exact 11-file writable scope and five frozen Round 24 paths were
  preserved; Stable `1.2` and unreleased `1.3` boundaries remain unchanged.
- [x] Fresh complete Local Gate PASS after the final edit, including Website,
  source/artifact release checks, version baseline, PHPUnit, Mago, static
  guards, Node/Docker-PHP goldens, links, exact scope, and diff checks.
- [ ] Fresh parent General/Audit review and parent Orchestrator acceptance.

### Remaining Issues

Fresh Sol General/Audit review and parent Orchestrator acceptance remain. After
acceptance, P23-001R and ordered Phase 23 work remain. No production,
publication, release, or capability-delivery issue is claimed closed here.

### Suggested Next Action

Orchestrator: route the fresh Sol General/Audit reviews, then record parent
acceptance. After acceptance, start P23-001R and ordered Phase 23 work. Do not
commit or perform external action.

### Final Non-Report Hashes and Report Self-Hash Handoff

The final non-Report SHA-256 inventory from the fresh complete Local Gate is:

```text
develop/STATE.md d49ca2ee5b4dfd852f3fa289d18f406819e0401af073f07e6b89d028bf4474c5
develop/TODO.md a7bbd84e5f9d3a4ddf458eaf8aa1f5a9e9ddae389176cd5d40ae5e6409ee5f00
develop/orchestration/tasks/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md ccf9a08bdc293f776af5bf1dabcc34494d24bb060df4caf2f0326bd89075d75c
develop/spec/README.md bb805de666f5786e9670010fbcfe98a0ee7fd2b9d3756a2f019c843601e28748
develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md c3b657d100528385bc5ee28887b3ebf9de172cff18a4cc27171a7e66e00428cd
develop/spec/105-blackops-1-3-capability-contract.md f202c0580949678ace9647675bc17dc99a97ffc21b7695a6fab755ebc776fe52
develop/spec/106-phase-23-delivery-plan.md 7166fc8947af1dad5eb1e7008e4b333e7cc3e0c4a4683858a94a58238aa83351
docs/guide/application-bootstrap.md 7103e228dc2ecf9e9cf44d54079a41f8e15af3b376ba38f054eb8a457e3149d1
docs/website/scripts/product-framing-contract.mjs f52aa6a1feb9ea826a1e47021deabd22e8c4ef73b79b071d28c79032ef85c915
docs/website/tests/product-framing-contract.test.mjs 32f383e99bcb3e4315c2a038ead6dd6dc62aa1087dbd2d108759d07df4941cc6
```

After this append, recompute the Report self-hash with:
`sha256sum develop/orchestration/reports/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md`.
The self-hash is returned separately and is not embedded in this Report.

## Historical Round 25 Completion Report — superseded review checkpoint

### Summary

Round 25 is implemented in the exact eight allowed parent management/spec files.
The current contract now pins the one-to-one ProjectionBuildSet kind map and
per-kind origins, self-contained Read/Access requestRef vectors and P23-003F/G
ownership, a total Stage6/7 verifier precedence, conditional AnchorIntent
accepted-state equality/version rules, explicit tenant-null/present Outbox AAD
bytes, and Framework-clock query-cursor key-window validation. Round 24 is
historical Changes Requested with separate General `P1=1/P2=1/P3=0` and Audit
`P1=5/P2=1/P3=0` lanes. Status is `Round 25 Local Gate Green / Independent
Review Requested`; parent acceptance is not yet complete.

The complete Round 25 Local Gate was independently run by the Orchestrator and
is Green. The worker did not rerun the full gate. Exact Orchestrator evidence:

- Focused Website contract PASS `8/8`; full Website PASS `120/120`.
- Website check PASS `0 errors/0 warnings/0 hints`.
- Build PASS `42` pages with embedded artifact/site check; explicit site check
  PASS `41` pages. Only existing nonfatal chunk-size and route-priority
  warnings remain.
- Release source and artifact guards PASS.
- Version baseline PASS: `published=1.2.0`, `historical=1.1.0`.
- Focused PHPUnit PASS: `30 tests / 250 assertions`.
- `mago format --check` PASS; canonical Mago lint PASS with `186`
  baseline-filtered/no issues; `lint --verify-baseline` PASS; Mago analyze
  exit `0` with existing `71 issues` (`46 warning / 25 help`).
- PHP management-ID guard PASS.
- Orchestrator current-only Node guard PASS: exact-16 scope, staged `0`,
  trailing `0`, hashes `16`, JSON `7/7`, Read `150/192/bfad...df0cf`,
  Access `103/147/1c505...e241`, negative matrices, links
  `315 total/279 local/0 missing`, and seven current roots.
- Docker PHP both requestRef goldens and negative matrices PASS.
- `HEAD=main=5ad87557c08e20c3bcc6d77be83804001ba3eb80`; exact-16 scope;
  staged `0`; `git diff --check` PASS.

### Current exact wire handoff

ProjectionBuildSet v1 is one closed root
`{schemaVersion,kind,applicationBuildId,buildSetHash,projections}` with
`schemaVersion=1`, `kind="projection_build_set"`, exactly one descriptor at
each key, and the exact map `command -> blackops_command_projection`, `route ->
blackops_route_projection`, and `schedule -> blackops_schedule_projection`;
swapped/duplicate kinds fail. Descriptors are exactly
`{kind,inputHash,path,sha256}`, with lowercase 64-hex hashes and `sha256` the
SHA-256 of raw projection-file bytes. Each file is exactly
`{schemaVersion,kind,applicationBuildId,inputHash,records}` with `schemaVersion=1`,
file kind/build/input equal to descriptor/root identity. Build IDs are
1..128-byte NFC/no-control UTF-8; paths are distinct root-relative POSIX
1..256-byte paths with no absolute form, backslash, empty component, `.`,
`..`, control, symlink, or special-file component. The exact encodings are
`ENC(string)="s:"||decimal_utf8_byte_length||":"||UTF8(string)`,
`ENC(integer)="i:"||canonical_decimal_digits`, `ENC(null)="n:"`, and
`ENC(bytes)="b:"||decimal_byte_length||":"||bytes`; hashes are
`buildSetHash=SHA-256(ENC("blackops.projection-build-set.v1")||ENC(JCS(root excluding buildSetHash)))`
and `inputHash=SHA-256(ENC("blackops.projection-input.v1")||ENC(JCS({kind,schemaVersion,applicationBuildId,sourceRecords})))`.
Root/projection bytes are UTF-8 RFC 8785 JCS plus one LF; root/projection
caps are 64 KiB/16 MiB and rows 0..4,096. Command records are exactly
`{name,aliases,description,origin,hidden}` with origin `framework|application`;
route records are exactly `{method,path,strategy,operationType,origin}` with
origin `operation_manifest|framework_fixed`; schedule records are exactly
`{name,operationType,cron,timezone,strategy}` with no origin. Sorts are
`(name,aliases)`, `(path,method,origin,operationType,strategy)` with nulls
first, and `(name,operationType)` using canonical unsigned UTF-8 bytes. Readers
validate all three same-fd files under one shared lock before selection; any
non-selected mismatch fails the set. Publisher ordering/flush/fsync/atomic
publication/directory fsync/generation-safe GC is current. P23-002A/B/C own
positive plus swapped/duplicate-kind/invalid-origin negatives.

The exact Read hash-input vector is
`{actor:null,execution:{id:"exec-1",type:"cli"},nonce:"nonce-1",purpose:"status",requestedAt:"2026-08-20T03:20:00.123456Z",tenant:null}`
with JCS/LP `150/192` and
`oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf`.
The exact Access hash-input vector is
`{actor:null,nonce:"n0",purpose:"query",requestedAt:"2026-08-19T00:00:00.000000Z",tenant:null}`
with JCS/LP `103/147` and
`oid:v1:1c505234fd297ef6f9e3cf595106228578833e41a27dda011b17449cb128e241`.
The Access digest without the wrapper is
`1c505234fd297ef6f9e3cf595106228578833e41a27dda011b17449cb128e241`; both
use exact domains, LP(U32BE+UTF-8), SHA-256, lowercase hex, and `oid:v1:`.

The current `AuditQueryRequest v1` is the closed object
`{schemaVersion:1,kind:"audit_query_request",access,partitionId,filters,
snapshotHeadSequence,limit,cursor}` with the exact nine-field
`AuditAccessContext`, authorized `system:global|tenant:<64 lowercase hex>`
partition, and filters
`{eventCategory:null|string,recordIdPrefix:null|string,fromSequence:null|positive,toSequence:null|positive}`.
`eventCategory` is null or an AuditRecord category, `recordIdPrefix` is null
or a non-empty prefix, and every non-null filter/cursor string is NFC,
no-control, and 1..256 UTF-8 bytes. The head is positive JSON-safe, limit is
`1..1000`, and cursor is null only on the first page or strict `aqc:v1` later.
The closed `AuditQueryResult v1` is
`{schemaVersion:1,kind:"audit_query_result",status,partitionId,records,
snapshotHeadSequence,nextCursor,reasonCode}`: `ok` has authorized partition/
head, complete ordered `1..limit` records and optional next cursor; `empty`
has the same partition/head and empty records/null reason; `unavailable` has
null partition/head/records/cursor and `resource_unavailable`. Request/result
JCS caps are 64 KiB and 1 MiB plus LF.

The exact cursor is
`aqc:v1:<BASE64URL_NOPAD(JCS(payload))>.<BASE64URL_NOPAD(tag)>`, with closed
payload `{schemaVersion:1,kind:"audit_query_cursor",partitionId,filters,
snapshotHeadSequence,lastSequence,issuedAt,expiresAt,keyId}`, one literal dot,
ASCII/no padding, and a 2048-byte cap. Its 32-byte tag is HMAC-SHA-256 over
`LP("blackops.audit.query.cursor.v1")||LP(JCS(payload))` using the exactly
32-byte role-bound `audit-query-cursor` secret selected by a 1..128-byte ASCII
NFC/no-control keyId. P23-003G captures trustedNow once from the injected
Framework clock, never cursor input, with six UTC fractional digits and
requires `issuedAt <= trustedNow < expiresAt`; the active unrevoked key must
satisfy `notBefore <= issuedAt <= trustedNow < min(expiresAt,notAfter)`.
Unknown/wrong-role/status/window/key/length fail before lookup/decrypt. Strict
decode/canonical JCS/closed/cap/partition/filter/head/last/time/key checks
precede constant-time tag comparison; rotation verifies old keys only through
outstanding expiry and issuance uses the current active key.

The exact `AuditOutboxRecord v1` row is
`{schemaVersion:1,kind:"audit_outbox_record",recordId,partitionId,
tenantBinding,ingressOrdinal,idempotencyKeyHash,contentHash,state,attempt,
availableAt,leaseFencingToken,leaseExpiresAt,protectedBody}`. Its protected
body is BOPD v1 over JCS(`AuditIngressEnvelope v1`) without LF. With
`LP(x)=U32BE(byte_length(x))||x`, AAD is
`LP("blackops.audit.outbox.aad.v1")||LP("AuditOutboxRecord")||LP("1")||
LP("1")||LP("1")||LP(exactKeyId)||LP(recordId)||LP(partitionId)||
LP(TENANT(tenantBinding))||LP(ASCII(canonicalDecimal(ingressOrdinal)))||
LP(decoded32(idempotencyKeyHash))||LP(decoded32(contentHash))`.
`TENANT(null)=0x00`; `TENANT({type,id})=0x01||LP(UTF8(NFC(type)))||
LP(UTF8(NFC(id)))`, with exact bounds and partition/equality binding. Pending,
leased, relayed, and poisoned state/lease/idempotency/size caps are exact;
identity/body/hash/ciphertext are immutable and retry reuses ciphertext.

The current `AnchorIntent v1` is the explicit closed object
`{schemaVersion:1,kind:"audit_anchor_intent",intentId,state,partitionId,checkpointId,checkpointHash,checkpointBytesHash,anchorStoreRef,anchorObjectRef,expectedPredecessorVersion,expectedPredecessorHash,anchorReceiptId,idempotencyKey,conditionalCreateToken,acceptedObjectVersion,acceptedObjectHash,acceptedAnchoredAt,receiptSignedAt,receiptSigningKeyRef,receiptBytesHash,anchorReceiptHash,leaseFencingToken,leaseExpiresAt,version,updatedAt}`. `prepared` has accepted
version/hash/time and all four receipt fields null. Only externally accepted
and later states require `acceptedObjectHash=checkpointBytesHash`; only then
genesis requires accepted version 1 with null predecessor fields, while
non-genesis requires both predecessor fields and predecessor version+1.
`receiptBytesHash` is exactly
`SHA-256(ENC("blackops.anchor.receipt.bytes.v1")||ENC(anchorReceiptId)||exact unsigned receipt JCS+LF bytes)`;
the final bytes have no additional `ENC`. Receipt-input state has three input
fields non-null and `anchorReceiptHash` null; final states have all four
receipt fields non-null.

Stage 6 runs after signature/file digest and validates authenticated
catalog+record graph first as `14 authenticated_graph_invalid`, then
mode/context/coverage `16 mode_coverage_invalid`, then freshness head shape/
boundary `18 freshness_boundary_unavailable`. Global failures keep refs
non-null, return `partitions:[]`, never reopen listed files, and use this order
for simultaneous failures. Stage 7 is only local checkpoint/anchor
trust/chronology `15 checkpoint_or_anchor_invalid` and local end-head freshness
`18`; catalog code10, graph code14, the deprecated scope label, and mode code16 are not
Stage7 names.

AnchorIntent v1 has explicit `{schemaVersion:1,kind:"audit_anchor_intent",intentId,state,partitionId,checkpointId,checkpointHash,checkpointBytesHash,anchorStoreRef,anchorObjectRef,expectedPredecessorVersion,expectedPredecessorHash,anchorReceiptId,idempotencyKey,conditionalCreateToken,acceptedObjectVersion,acceptedObjectHash,acceptedAnchoredAt,receiptSignedAt,receiptSigningKeyRef,receiptBytesHash,anchorReceiptHash,leaseFencingToken,leaseExpiresAt,version,updatedAt}`.
Prepared accepted version/hash/time and all four receipt fields are null; only
externally accepted and later states require `acceptedObjectHash` equal to
`checkpointBytesHash`. Genesis then requires version 1/null predecessor;
non-genesis requires both predecessor fields and predecessor version+1.
Outbox AAD uses outer `LP(x)=U32BE(byte_length(x))||x`,
`TENANT(null)=0x00`, `TENANT({type,id})=0x01||LP(UTF8(NFC(type)))||LP(UTF8(NFC(id)))`,
and `LP(TENANT(tenantBinding))`, with exact bounds/equality/partition binding.
The cursor keyId is 1..128-byte ASCII NFC/no-control, its HMAC secret exactly
32 bytes under `audit-query-cursor`; P23-003G captures trustedNow once from
the injected Framework clock and requires
`issuedAt <= trustedNow < expiresAt` plus
`notBefore <= issuedAt <= trustedNow < min(expiresAt,notAfter)`. Strict
canonical decode/JCS/caps/key checks precede constant-time tag comparison.

### Changed Files

- `develop/STATE.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md`
- `develop/orchestration/reports/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md`
- `develop/spec/README.md`
- `develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md`
- `develop/spec/105-blackops-1-3-capability-contract.md`
- `develop/spec/106-phase-23-delivery-plan.md`

The eight prior changed public/code/child paths remain frozen byte-for-byte.

### Decisions and Assumptions

- Stable `1.2` remains current; every Phase 23/`1.3` capability remains an
  unreleased proposal. No Production Code, public docs, release authority,
  dependency, artifact, commit, stage, push, CI, build, deploy, publish, tag,
  or release action was performed by this worker.
- The `blackops-japanese-docs` skill and required style checklist guided the
  Japanese/specification wording: factual current-vs-Historical separation,
  first-reader paragraph order, consistent technical terms, and explicit
  Stable/release boundaries. It did not authorize Stable capability claims.
- Current projection records use command origins `framework|application`, route
  origins `operation_manifest|framework_fixed`, and no schedule origin. Current
  requestRef vectors are the exact six-field Read and five-field Access inputs
  with the pinned JCS/LP lengths and digests. LP is only the SHA-256 preimage.
- Current Stage6 is graph/catalog `14`, mode/coverage `16`, freshness `18`;
  Stage7 is local checkpoint/anchor `15` and local end-head freshness `18`.
  Global results retain non-null post-signature refs and `partitions:[]`.

### Exact Commands and Results

The worker completed only the bounded checks below. The complete Website,
PHPUnit, Mago, Docker, build, release, and current-only Node gates were run
independently by the Orchestrator; the worker did not rerun them:

- `date --iso-8601=seconds` — PASS; final sync checkpoint timestamp
  `2026-08-20T07:55:10+09:00`.
- Final exact-16 scope/status probe (`git status --short --branch`, HEAD, and
  staged list) — PASS; exactly the eight allowed parent files plus the eight
  frozen paths are changed, HEAD is
  `5ad87557c08e20c3bcc6d77be83804001ba3eb80`, and staged count is `0`.
- `git diff --check` — PASS; no whitespace errors.
- Exact allowed-scope and frozen-hash verification — PASS; exactly the eight
  allowed parent files plus the eight previously frozen paths are changed,
  staged count is `0`, and all frozen hashes match their supplied values.
- Current-vs-Historical/status guards — PASS; Round 25 is the sole current
  parent status, and retained Round 24/older occurrences are under explicit
  Historical headings.
- Current semantic-wire guard over D145/Specifications 105/106 and this Report
  — PASS; projection map/ENC, both requestRef vectors, finite verifier names,
  AnchorIntent, tenant AAD, QueryRequest/Result, and cursor key-window terms
  are present in current authority.
- Trailing-whitespace scan over all 16 changed paths — PASS.
- Orchestrator complete Local Gate — PASS/Green: focused Website contract
  `8/8`; full Website `120/120`; Website check `0 errors/0 warnings/0 hints`;
  build `42` pages with embedded artifact/site check and explicit site check
  `41` pages, with only existing nonfatal chunk-size and route-priority
  warnings; release source/artifact guards; version baseline
  `published=1.2.0 historical=1.1.0`; focused PHPUnit `30 tests / 250
  assertions`; Mago format check, canonical lint `186` baseline-filtered/no
  issues, lint baseline verification, and analyze exit `0` with existing `71`
  issues (`46 warning / 25 help`); PHP management-ID; current-only Node
  exact-16/staged-0/trailing-0/hashes-16, JSON `7/7`, Read
  `150/192/bfad...df0cf`, Access `103/147/1c505...e241`, negative matrices, links
  `315 total/279 local/0 missing`, seven current roots; Docker PHP both
  requestRef goldens and negative matrices; HEAD/main
  `5ad87557c08e20c3bcc6d77be83804001ba3eb80`; exact-16 scope; staged `0`; and
  `git diff --check` PASS.

### Acceptance Criteria

- [x] Seven Round25 roots are stated in current D145/Specifications 105/106
  and synchronized through Task, Report, STATE, TODO, and README.
- [x] Projection kind map/origins, requestRef vectors/ownership, Stage6/7
  precedence, AnchorIntent conditional matrix, tenant AAD, and cursor key
  windows are current and implementable without Historical text.
- [x] Round24 is explicitly historical with General `P1=1/P2=1/P3=0` and
  Audit `P1=5/P2=1/P3=0` kept as separate historical lanes.
- [x] Stable `1.2`, unreleased `1.3`, frozen paths, and no-external-action
  boundaries remain explicit.
- [x] Final bounded scope/hash/diff/current guards after this Report edit.
- [x] Orchestrator independently completed the full Round25 Local Gate with
  Green evidence recorded above.
- [ ] Fresh separate Sol xHigh General/Audit reviews and parent acceptance.

### Remaining Issues

The worker's bounded static checks passed, and the Orchestrator's independent
complete Local Gate is Green. Fresh separate Sol xHigh General/Audit review and
parent acceptance remain. After acceptance, P23-001R and ordered Phase 23 work
remain. No production or release issue is claimed closed here.

### Suggested Next Action

Freeze the final hashes and route both fresh Sol xHigh General/Audit reviews;
after parent acceptance, start P23-001R and ordered Phase 23.

### Final Non-Report Hashes and Report Self-Hash Handoff

The seven allowed non-Report hashes and eight frozen-path hashes from the final
bounded check are:

```text
develop/STATE.md 81c8c36d9633166e8bc484c615a868d99f83b33359fd62b44465fe1d92bd3ea7
develop/TODO.md d6f3cf5fde6b44fbb299f10405e2e9a52eb993f4601d8ae768214ebef28d8c14
develop/orchestration/tasks/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md a479cca62f17d5f96c3b1cb9ba1c5e29a01885fb75a3aa4d248fdc470a72f4c4
develop/spec/README.md 3bae06532c27ef6ceb1e287e63ceca5a238ecef5af2efd32feeace8ebde918d7
develop/decisions/145-blackops-1-3-command-runtime-and-audit-boundary.md 36a18c395ed0bd5a33b4856601b68750c561ced24b8f3376147e0558a12dfa7a
develop/spec/105-blackops-1-3-capability-contract.md dfb6b7db6d90640b0569e3d0a435a5e46b22da187ab7af7686147be1eb359f91
develop/spec/106-phase-23-delivery-plan.md bf4e8ebe9f4cbd6c1a48d27467a300283d662f281db4be4c42af6c5a7ee715ee
docs/guide/application-bootstrap.md 7103e228dc2ecf9e9cf44d54079a41f8e15af3b376ba38f054eb8a457e3149d1
docs/guide/project-cli.md 28b05041f2d1db347987ad9fc9a313ab74049eb1ebb012e6250b7360db3e0ac0
docs/website/scripts/product-framing-contract.mjs f52aa6a1feb9ea826a1e47021deabd22e8c4ef73b79b071d28c79032ef85c915
docs/website/tests/product-framing-contract.test.mjs 32f383e99bcb3e4315c2a038ead6dd6dc62aa1087dbd2d108759d07df4941cc6
tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php 402c3d0f297beb465cc06f35d38b86958fd07584b29dc83c867e5bf4e6f205d1
tests/Internal/Application/ApplicationConsoleKernelTest.php 078d76c7219c29bf600a5b6b546436610846befd478d1bcc12a12e53a02a4bb9
develop/orchestration/tasks/P23-001S-stable-list-documentation-truth.md 6e1a1b602d107139cb1c8a0773d21fab6822454aa4b3efcb25859c9df279167e
develop/orchestration/reports/P23-001S-stable-list-documentation-truth.md 3b1602cbf4351802fe9a1da306a09d5ad0cb0028742baac5a4ddac3b8a418b1d
```

The Report self-hash is always returned separately and is never embedded.
