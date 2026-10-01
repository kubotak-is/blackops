# P23-003D5O1: Observer Replay Transaction Outcomes Report

Status: Locally Accepted — implementation, frozen full and independent Review complete; BlackOps1.3 unreleased

## Summary

The read-only native survey found that Runtime treats cursor/advance errors as
Observer failures and writes a second failed state, including after an actual
committed advance. Store cleanup also unconditionally rolls back and leaves the
post-lock transaction begin outside cleanup. Spec120/D170 define the bounded
native correction before a separate four-event adapter.1.3 remains unreleased.

## Changed Files

Root management: Task/Report, spec120/D170, bounded D154 pointer, spec index,
TODO/STATE/archives and parent006 progress. Authorized Product/Test scope is
exactly the two current native owners plus one new fixture in the Task. Luna has
implemented those three files; Root formatted them and owns all executed gates.

## Decisions and Assumptions

Keep four native roots, external at-least-once Observer effects, explicit resume
and old started invocation rows. Do not infer persistence from DBAL activity.
Cursor/advance precommit failures deliberately stop without failure finalization.
Use the existing fixed-message JournalObservationFailed type for that Runtime
path; direct Store and inherited begin/finalize/unlock exceptions remain bounded
only by their existing contracts. No general CLI redaction claim.

## Commands and Results

Read-only source/spec/fixture inspection and baseline capture at
2026-09-29T22:55:43+09:00, exit0. Baseline3338 files/252 dirty, SHA
83501f595fafac42c8329c6d90ee92245b96ac66f8e83a44c8bae6cd8365101a.
`/tmp/blackops-d5o1-20260929/predecessor-preservation.json` confirms all
D5R2 frozen17 candidate/1707 runtime/2219 repository inputs unchanged.
At that initial checkpoint no PHP/Docker/DB/test execution or Product/Git mutation
had occurred. The following execution records supersede that checkpoint only.

Preparation after that source-only checkpoint: management diff/STATE checks pass
(STATEf4c837ac,67 lines/4085 bytes). `scope-authority.json` passes with no foreign
change and all17 prior D5R2 candidate hashes preserved. Current Docker inspection
initially failed only for sandbox socket permission (0.114926s); the authorized
retry passed images in0.088456s and container inventory in0.055306s. Both image
IDs exactly match D5R2. Environment receipt
`/tmp/blackops-d5o1-20260929/environment.json`, SHA256
fb07f1b0981a26836ccb5dd6dae80316d3e35306e6e93ce07c32df23f693a908,
reuses observed PHP8.5.7/PHPUnit13.2.2/Mago1.42.0 with unchanged image and
Composer/runtime/config inputs. Root's temporary serial
runner and scope/freeze/JUnit helpers compile and retain literal command receipts;
they do not modify Repository tooling or test configuration.

Root execution on2026-09-30 uses `/tmp/blackops-d5o1-20260929/run.py`. Each listed
directory retains literal argv, environment hash, before/after input maps,
monotonic elapsed time, exit status and output.log. No gate used a raised limit.

| Directory suffix / command | Result | Elapsed |
| --- | --- | --- |
| `run-20260930T082055443895-format-candidate`; Mago exact3 format | Passed;3 files formatted |1.148867s|
| `run-20260930T082057603304-focused`; new real-PG fixture, display-all-issues, fresh JUnit | Passed11 tests/191 assertions,24MB; all3/1708/2220 candidate/runtime/repository maps unchanged |2.870070s|
| `run-20260930T082524121983-format`; global format check | Not executed beyond Docker permission failure; exit1 |0.070604s|
| `run-20260930T083625817192-format`; authorized global format retry | Passed; input maps unchanged |1.407672s|
| `run-20260930T083628286419-lint`; Mago lint | Failed; Runtime kan-defect1.61>1.6 and halstead8809.21>5000, Store11methods>10 |1.108339s|

The serial runner stopped on lint failure; baseline verification, analyzer,
Deptrac, identifier scan and full were not reached by that command. Full remains
NotRun. The worker's earlier host PHP attempt returned `php: command not found`;
it produced no syntax or test evidence. No stage/commit/remote operation occurred.

Independent source/proof Review inspected exact formatted Product hashes
Store68a2a54a, Runtime96671d6b and fixtured06b56c9, verified physical focused
log/JUnit, and requested four bounded P2 corrections. Evidence
`source-proof-review.md`, SHA256
10af9da3078211ede2b48e26a35cfb6946418ca14485929370fab689844b0b51.
The fixture caught its own PHPUnit failure assertions, masked inactive rollback,
lacked phase/root counters, and could hang in non-auto-commit teardown. Its
Runtime terminal/first-and-later advance/resume and delivery-failure proofs were
incomplete. The two Product files' phase semantics were source-correct, but lint
also requires a bounded restructuring. Root assigned all corrections within the
existing exact3 scope; no new file, suppression or threshold change is authorized.

The corrected worker handoff fixed Product to Store913eb5d and Runtimef3d7705b,
expanded the fixture to22 cases, and retained exact phase/root/lock counters.
Root's2026-09-30 11:09 serial iteration reverified identical app/Postgres image
IDs and the six pre-existing containers, then formatted only the new fixture.
All following nonmutating receipts preserve their before/after runtime/repository maps:

| Directory suffix / command | Result | Elapsed |
| --- | --- | --- |
| `run-20260930T110918239143-images`; image identity | Passed; same ab739e1d /4aabea78 |0.171765s|
| `run-20260930T110919442214-containers`; inventory | Passed; prior containers preserved |0.058028s|
| `run-20260930T110919783175-format-candidate`; exact3 format | Passed; fixture-only formatting mutation |1.152371s|
| `run-20260930T110923155611-lint` | Passed; no new issue |1.017970s|
| `run-20260930T110924475773-lint-baseline` | Passed; strict baseline unchanged |1.034050s|
| `run-20260930T110925834129-analyze` | Passed |1.571266s|
| `run-20260930T110927705981-deptrac` | Passed;7344 allowed, zero violation/uncovered/warning/error |2.014237s|
| `run-20260930T110930012022-focused` | Failed22 tests/635 assertions,5 failures,24MB |4.314042s|

The five focused failures are incorrect fixture oracles: native begin-root
counts for3-record completion and Observer failure, a2-record resume count, and
two final cursors naming record3 but expecting sequence2. Root returned the
single fixture to Luna, preserving actual independent-root accounting and
record.sequence rather than weakening assertions. Product2 remains fixed; no
new static waiver or Product defect was found. Tests are outside current Mago
source paths (`src`, `examples/quickstart/app`) and Deptrac (`src`), so unchanged
selected analysis inputs will reuse these passed static receipts. Full is still
NotRun and source/proof r2 must close the original four findings.

The fixture-only oracle correction is fixed at c7550fb7; both Product hashes
remain913eb5d/f3d7705b. Root's13:32 iteration passes every selected command, with
all3/1708/2220 candidate/runtime/repository maps unchanged during each command:

| Directory suffix / command | Result | Elapsed |
| --- | --- | --- |
| `run-20260930T133201312371-format-candidate` | Passed; already formatted |1.475972s|
| `run-20260930T133203840458-format` | Passed global format |1.240142s|
| `run-20260930T133205408733-focused` | Passed22 tests/737 assertions,24MB; fresh JUnit |4.289563s|
| `run-20260930T133210034786-identifiers` | Passed; rg exit1 with no matches |0.040959s|
| `run-20260930T133210372890-schema-cleanup` | Passed; owned schema count0 |0.127534s|

Focused log SHA2565a5201280b68773b0d302620d6ca5dfb18bd2d3feee7f070a2bdb7579faf5d34;
JUnit1b9786e3c4649c69db1f65fe0ec725220647fe206f45ce0c94c36bf37a34dc21.
`static-reuse-r2.json` SHA256
5517d1115e56d7ef39c783f2fbcb913ba17dac59a3daedb2d86614b6e3430c54
compares1122 selected source/config/Composer dependency metadata hashes to each
passed11:09 static receipt before/after and current files. Only the excluded new
fixture changed in runtime inputs; no dependency installation occurred. This
does not retroactively assert a historical full vendor-source hash inventory.
Reuse all four static receipts. `scope-focused-r2.json` passes with no foreign
change and preserved prior D5R2 candidate17, docs202/artifact424, HEAD/index and
Release Authority.

Independent source/proof r2 is complete, `source-proof-review-r2.md` SHA256
70596ac665920639b25b0c12a89b52fd9ad323779181c5b7974cbd40c21859a8.
Product refactor is Green; original P2-1/2/4 are closed. P2-3 remains limited to
actual Runtime begin commit#1 before/after and paused terminal commit before/after;
the existing corresponding Store-only cases are insufficient for those Runtime
claims. Root assigned only those two fixture loop extensions to Luna. No new
scenario family, Product correction or scope expansion is requested. The current
selection-failure case uses a valid protected envelope and fails canonical decode
after decryption; it proves the Runtime selection catch, not a separate encryption
authentication/key-failure matrix. Full remains NotRun until narrow closure.

The two actual Runtime cases were added in fixture34880c0e, preserving both
Product hashes. Root's18:29 changed-input iteration passes; each receipt has
unchanged3/1708/2220 input maps:

| Directory suffix / command | Result | Elapsed |
| --- | --- | --- |
| `run-20260930T182944795109-format-candidate` | Passed; already formatted |1.438767s|
| `run-20260930T182947423063-format` | Passed global format |1.380455s|
| `run-20260930T182949131886-focused` | Passed24 tests/843 assertions,24MB |4.259668s|
| `run-20260930T182953705982-identifiers` | Passed; no matches |0.031460s|
| `run-20260930T182954020446-schema-cleanup` | Passed; schema count0 |0.130290s|

Focused log64dadc71dafaf68ed06ec92f2f85ea9c6f1751068160c694be09fe7e88aa968f;
JUnit2a1886b41681ff42595c4372111eac184611fc068db75110ba8e15c22777ecd6.
Narrow source/proof r3 is Green, `source-proof-review-r3.md` SHA256
db47896804849d25e94c426a91224de45ad3dc58893929d7ec4c0152283baefe.
All original source/proof findings are closed; Product and old22 fixture cases
are unchanged by the exact two additions. Final static reuse2fbf2415 confirms
all1122 selected hashes. No same-condition static rerun is selected.

Root froze candidate3/runtime1708/repository2220 and exact24 new case identities.
`frozen.json` SHA256
1e4dc7438eec2942f69ab56901421891e68f7472f1c42241c8d811c3cfafa69f.
At2026-10-01T01:08:54+09:00 images again match ab739e1d/4aabea78 and all six
prior containers are preserved (`run-20261001T010854034012-images`,0.188888s;
`run-20261001T010855294315-containers`,0.078588s). The sole final full ran
from01:08:55 to01:30:29 in `run-20261001T010855662944-full`, PID791573,
exit0 in1260.227024s. It passed3668 tests/7694025 assertions,139MB under the
original512M configuration. Log SHA256
89edb10345cfdeb81a9ecad374008e7a57390b0030a9e6f5f6802969637c1b43;
JUnit4bd0ec57016d66c92c59aa32a3f2315d322fcce79c3e4f2ded886f8cb19e91aa.
The log reports1 deprecation,2 PHPUnit deprecations and13 PHPUnit notices.
These aggregate counts equal the predecessor; individual issue identity was not
captured by this full command, so this is not a warning-free or issue-identity claim.

`full-evidence.json` SHA256
571853546019e537df9fb8ab4a2eb0e14bebe3be9a44f5e2e05f4e37d205b1f2
passes: all3644 predecessor identities plus exactly24 new identities, no duplicate,
failure/error/skip, and equal frozen/before/after/live3/1708/2220 input maps.
Post-full schema cleanup `run-20261001T013029765054-schema-cleanup` passes
count0 in0.155420s; `run-20261001T013030258178-containers` passes in0.064883s,
preserving all six prior containers. `scope-final.json` SHA256
d056963e53508018ee4a04c3c4d91d2d4651f8a5c3ac575547ff939838d7342f
has no foreign delta, and preserves D5R2 candidate17, Source202/artifact424,
Release Authority, HEAD and cached patch. The baseline includes intent-to-add
index entries; equality of the cached patch is not a claim that the index has
no entries. Root has performed no Git mutation.

## Acceptance Criteria

All seven Task criteria are satisfied; Root grants local Acceptance on
2026-10-01T01:43:43+09:00.
Authority Review a9c63199, source/proof r3 db478968 and final evidence Review
`/tmp/blackops-d5o1-20260929/final-evidence-review.md` SHA256
95040754c70d632315f345c1e4ec89ec85ef9b27e82b3f8573f60beb00ec3700 are Green. No material finding remains.
The final review independently verifies the physical full/log/JUnit, exact
3644+24 case union, stable3/1708/2220 input maps, selected1122 static inputs,
format/identifiers, schema0, six preserved containers and no foreign scope delta.

Four roots reject foreign entries and clean only owned active roots; phase-based
Runtime handling preserves true delivery failures and stops cursor failures
without synthetic failure/finalization. Real native faults and explicit resume
prove the fixed matrix. At-least-once external effects, started-row persistence,
unknown commit results and inherited driver/general CLI limits remain explicit.
The valid-envelope canonical-decode fixture proves the selection catch, not a
new cryptographic fault matrix. No all-Throwable redaction or exactly-once claim.

Accepted candidate Store913eb5d73583541826725cd325834ee4f82b60f3f7d61ab8df6ef08f07d9fe30,
Runtimef3d7705bdccee363d0ccaa6c33bf629de31476f682d90b9be4a55dfdcbca98bd,
fixture34880c0e0bb9955766e1964a02f9724aa72928bb67faf7d7605e6095edbe0d1e.
Complete payloads/modes are retained under `final-candidate/` for later commits.

Verification feedback:23 selected command attempts total1292.506742s, including
the recorded initial Docker permission failure, lint failure and fixture-oracle
failure. Exactly one full ran. Source/proof Review took three rounds with bounded
corrections, followed by this evidence-only closeout; unchanged successful static
inputs were reused and no final same-condition focused rerun was added.

## Release Documentation Impact

Native framework-core replay correction is development-only. The published1.2.1
tuple, public Source/routes/Search/LLM and old-version allowlist are unchanged.
Parent005/006 owns migration guidance, same-SHA CI/export and publication.

## Remaining Issues

None within this locally Accepted child. Required gates pass with the separately
reported suite deprecations/notices; this is not a warning-free claim.
No child commit, clean export, same-SHA CI or deployment occurred.
Parent1.3 retains D5O2/generic Outbox/integration, E/F/control/G,004A/005,
Owner local preview and006 release/delivery. Optional003H/004B remain excluded.

## Suggested Next Action

Root captures the actual common post-O1 baseline, issues reviewed O2/F1 Packets
and obtains bounded issued-input verification before assigning the two Luna
workers. Shared package ownership remains sequential; Parent006 stays active.

## Subsequent integration boundary

The original local verification and attribution above remain unchanged.
P23-006C owns the subsequent isolated integration in the reviewed D5O1 unit, its separate commits and final convergence checks.
See [the integration Report](P23-006C-accepted-audit-integration.md).
No historical missing intermediate or new same-SHA full is implied.
