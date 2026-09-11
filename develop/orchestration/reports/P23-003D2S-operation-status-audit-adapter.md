# P23-003D2S: Operation Status Audit Adapter Report

Status: Implementation Accepted; required local verification Green; clean final-SHA export pending.

Updated At: 2026-09-12T00:30:45+09:00

## Summary

P23-013 synchronized published1.2.1 into development at clean merge
7d90753485c8bf9e63578e8ce83cc1762234c083 and passed same-SHA Git/Composer export.
D2/D2S1 foundations are already Accepted. Revised spec107/D156/Task now use a
fresh tenant-bound source per read, strict authorizer guard, server-owned Status
read UUID and requested/read-only-snapshot/terminal three-root ordering.
The13-path implementation and two bounded Test-only recovery paths are
Accepted after all three review lanes and the required local verification.
Clean final-SHA export is the remaining adapter gate. No adapter is activated.
Focused independent authority re-review and Root General Review are Green. Published Stable remains1.2.1; this work is unreleased1.3.

## Changed Files

Root: spec107/D156 and this Task/Report; minimal D155/spec108/D157 forward
references; spec index, current TODO and rotated STATE. Runtime and Architecture
workers changed their13 listed adapter Product/Test paths and returned handoffs.
The two approved recovery amendments add only5 and8 lines to two existing
Test-only paths: AuditIngressEnvelopeCodecTest and AuditProtectedBodyCodecTest.
Total Product/Test scope is15 paths; all test/helper bodies remain exact.
P23-013 completion receipts are administrative handoff, outside D2S Product scope.

## Decisions and Assumptions

- Scoped SQL is supplied by Accepted D2S1. The guard supplements SQL, and one
  reused query binds its nullable tenant separately on every read.
- Allocate trusted `operation_status_read` UUIDv7 independently of D2 nonce,
  Clock and Store UUIDs. It is not a replacement for missing caller identity.
- Keep three same-Connection roots; terminal failure overrides pending result
  or safe exception, with no retry/fallback/foreign transaction repair.
- Independent Transaction/Documentation review identified inherited exception
  trace exposure. D2S now explicitly permits only14 SensitiveParameter additions
  to Default Query and necessary formatting; algorithm and exception identity
  remain unchanged. Do not narrow the guarantee to hide the inherited gap.
- The sealed Default makes defensive unknown-result/catch-all rows unreachable
  through normal fixtures. Actual AST/mutation controls cover these rows; no
  mock delegate seam or impossible end-to-end unit gate is demanded.
- Package inventory grows87 to90 with old35 controls plus4 new controls. Final
  full PHPUnit subsumes matching focused iteration coverage per spec109.

## Commands and Results

Clean authority checkpoint:106b0da127326847c11670d38cd0ad67e050bf14;
Product base7d90753485c8bf9e63578e8ce83cc1762234c083. The1905-file frozen input
manifest is `/tmp/blackops-d2s-20260911/freeze.json`; image and compact receipt
are in `freeze-summary.json`. Default Query baseline SHA256
ef3ccd1ebb4e60743ced61cc66584ddbf7dfb8b271d9347c7c96cab8f62e6f3a.
Runtime7 belongs to docs_visual_reset; Architecture/package5 belongs to
archify_site_worker; PostgreSQL1 belongs to a separately assigned Luna worker.
Root owns the combined Report and all shared DB checks. Runtime's source/unit
draft was checkpointed before transferring its still-unwritten PG path. This
reduces the single-worker bottleneck without changing the13 paths or gates.

The older D2S1 raw directory `/tmp/blackops-d2s1-memory` is no longer present.
Do not cite its absent logs as newly reusable evidence. Accepted source and
its persisted Report remain the prerequisite record; the selected D2S Final
full suite executes the existing D2S1 snapshot/race tests on the current bytes.

Read-only source and authority investigation is complete. Iteration results
and corrections are recorded below. Do not infer Product acceptance from
source review. Authority candidates/review context are
under `/tmp/blackops-d2s-20260911/authority`.

PHP8.5.7 preflight:
`docker compose run --rm --no-deps -T --volume /tmp/blackops-d2s-20260911:/probe app php -d zend.exception_ignore_args=0 /probe/trace-probe.php`
passed exit0 / tool0.911s. It intentionally confirms the baseline exposure using
dummy values: Operation ID/actor/tenant visible=true; previousIsNull=true;
queryCode=status_query.storage_failed. No DB, source mutation or real secrets.
Receipt: `/tmp/blackops-d2s-20260911/trace-probe-result.json`. This is a confirmed
preflight defect requiring the narrow annotation correction, not final safety
proof. The worker must prove all four codes safe with argument capture enabled.

The six assigned Node goldens passed on unchanged fixture inputs at02:23:06–07
+09:00. Exact commands, Node version, fixture hashes, logs and elapsed times
(0.336/0.118/0.093/0.048/0.032/0.052s) are under
`/tmp/blackops-d2s-20260911/goldens`. These read-only checks do not use the worker's
PHP cache or shared DB. Reuse them if the recorded fixture inputs stay exact.
The package worker's shell syntax and90-path/39-control checks passed; receipt
is `worker-architecture/package-inventory.log` (1.685s). Export script SHA256
6cde33f566fb74e193b1c7a2ff60b4c0e76157805627cbc650b95624dab2b1b9;
required-inventory6c47144a957d899d6cacd8dfadeef7581658fe73bdd4ff45a2b3673a35dabea1.
Root confirmed the old87 order and35 controls remain. This is not final-SHA
Git/Composer export. Interim scope check at02:22:09 found no Product change
outside the13 authorized paths; PG fixture and Final freeze were still pending.

`bash tests/Consumer/version-baseline.sh` passed exit0/0.970s; published1.2.1
and historical1.2.0/1.1.0 boundaries remain correct. Exact inputs/receipt/log:
`/tmp/blackops-d2s-20260911/version`. Do not rerun unchanged inputs at Final.

Root took over all PHP execution after the worker's Docker socket denial.
Root's initial wrapper preflight also failed before PHP; the approved
escalated run preserved the same source/image and succeeded. No concurrent
PHP/cache or DB owner remained. Runtime7 and PostgreSQL1 handoffs are recorded
under `worker-runtime/final-handoff.txt` and `worker-pg/handoff.md`.

- No-DB unit3:31 tests/192 assertions, exit0,1.555s, PHP8.5.7/PHPUnit13.2.2,
  same image/head; `run-20260911T095831/{inputs,context,results}.json` and
  `unit.log`. The16 new PHPUnit mock notices were fixed by a one-line
  createMock-to-createStub change; only the affected Query test needs iteration.
- PG iteration:7 tests/199 assertions,2 failed expectations, exit1,14.806s
  including container startup; `run-20260911T112400` receipts/log. Found had
  already committed accessed in the after/poststate cases; the test incorrectly
  expected unavailable. The outward Storage failure and no retry were correct.
  Worker corrected the expectations and added actual PostgreSQL isolation-mode
  assertions. Root additionally requested plaintext Audit evidence canaries;
  searching ciphertext alone cannot prove decoded data exclusion.
- Architecture3 iteration under `run-20260911T133508` finished with14 tests,
  3,818,364 assertions,4 failures/2 warnings, exit1/667.275s. Failures were
  new mutation identifiers and the scoped baseline/global call count; the
  unchanged D2 six-method subset passed. At13:38 the process had102% CPU/RSS69,436KiB with
  OOMKilled=false, so this is computation rather than a stopped DB/process.
  Do not restart the whole run or raise the configured128MiB memory limit.

Each run directory records exact argv, input hashes, image, timestamps, elapsed
seconds and logs. The following delta receipts supersede failed iterations;
failed logs remain intact. No full PHPUnit or final export is claimed yet.

## Final Preparation and Reusable Evidence

- `run-20260911T154009`: Mago lint found the new query's cyclomatic
  complexity28 against15, plus two no-else suggestions, exit1/1.811s. Root
  authorized two class-local `@mago-expect` annotations: the closed protocol
  keeps one failure boundary and no early result release. No threshold,
  baseline, method, signature or algorithm changed. The exact comment-only
  proof is `query-comment-delta.json`: removing the added DocBlock restores
  the independently reviewed f03ce4e7 source hash. Current query SHA256 is
  81a140e55e904cc8929f46606e9828b05621fea7d42a2840d19a1d86e83ea9f1.
- `run-20260911T183222`: lint, baseline verification, analyze, Deptrac and
  strict Composer validation all pass, respectively1.763/1.548/2.126/2.335/
  1.606s. Deptrac covers1051 files/6628 allowed dependencies with zero
  violations, uncovered dependencies, warnings or errors. Mago's source and
  configuration inputs are unchanged by the subsequent test-only deltas;
  reuse these passes, including the186 unchanged baseline exclusions.
- The same run's affected Query unit suite passes17 tests/125 assertions
  without the earlier mock notices,1.224s. PG11 tests/975 assertions had
  one incorrect Expired decode-count expectation,4.920s. An authorized
  Expired detail read may decode; zero-decode applies to foreign/missing/
  denied reads. Luna changed only that assertion to require positive calls.
- `run-20260911T184548`: new adapter and scoped architecture delta passes
  8 tests/16,542 assertions, exit0/159.613s, memory46MiB. The final oracle
  fixes cover syntax-normalized fragments, resolved class identities and
  explicit finally/elseif rejection; prior D2 controls remain intact.
- `run-20260911T185934`: affected real-PG Expired case passes1 test/51
  assertions, exit0/2.345s. Global format reports4 test files requiring
  formatting, exit1/1.744s. Their Luna owners are applying only the Mago
  diff; source4 and package2 remain exact. The initial dry-run attempt was
  denied at Docker access; `format-delta-approved.log` is the subsequent
  successful diff calculation, not a format PASS.

All receipts use PHP8.5.7/PHPUnit13.2.2/Mago1.42.0/Deptrac4.7.1 and image
sha256:ab739e1d3bb8d9c7b6696401f7d4821eff72ae870db703feb537015a4fa14f5d.
Root alone executes PHP/cache/DB commands. Final full PHPUnit will include the
existing D2S1 snapshot/race tests; no absent historical raw log is reused.

Root reconciled the settled13-path candidate at19:19:37+09:00 in
`scope-final-inputs.json`:1905 frozen inputs,5 changed/8 new authorized files,
zero outside-scope changes, all194 public inputs unchanged, Framework13 and
Community5 migrations, StoragePurpose9, no existing Consumer cleanup artifacts.
The required PHP management-ID scan and `git diff --check` pass. Source4,
package2, runtime wiring, dependency/config/lock/profile inputs remain exact.
`format-delta-proof.json` reverses all four generated Mago patches and restores
the previously reviewed hashes exactly. `static-reuse-proof.json` confirms
only those four test files changed after the passing static gates.

At23:03:06+09:00 `run-20260911T230306` passed global format in2.030s and
started the selected single full PHPUnit run. Its exact command adds the
JUnit artifact flag `--log-junit .phpunit.cache/d2s-full-junit.xml` to the
Task's `--display-warnings` command; test selection and memory128MiB are unchanged.
The full run subsequently failed as recorded below; Community Board and clean-SHA
export remain pending.

## Full-suite Failure and Bounded Recovery

`run-20260911T230306/full.log` stopped after827.653s, exit2, at approximately
1286 of3289 tests. No assertion failure preceded PHP's134217728-byte limit;
the failing allocation was1048576 bytes in Symfony's Normalizer::recompose,
called by AuditJcsCodec and the unchanged ingress MAX_BYTES+1 fixture.
The JUnit file is empty after the fatal exit and is not passing evidence.
The global format PASS from the successful prefix remains valid for its inputs.

Root then ran the same case alone and with all-suite discovery at23:24:55–56
+09:00. Both pass1 test/3 assertions, at30MiB and82.5MiB respectively; tool
times1.777/2.259s. Exact commands/inputs/logs are in `run-20260911T232455`.
The initial wrapper preflight was denied at Docker access before PHP; the
approved retry preserved the input/image. Independent read-only inspection
found no static/global/test-object AST or token retention in D2S architecture
tests. Their repeated dependency analysis is a separate CPU-cost observation,
not an established cause of this Normalizer allocation failure.

The Task now proposes one14th Test-only recovery path, the existing
AuditIngressEnvelopeCodecTest. Only two existing PHPUnit process-isolation
attributes, imports and rationale may be added to the heavy boundary method;
fixtures, assertions, helpers, source13 and memory_limit128M remain exact.
Root General considers this bounded execution isolation appropriate; independent
Transaction amendment review precedes dispatch. Root will verify inherited
child memory settings, the exact body-preserving delta, related tests and one
fresh full-suite run after this concrete failure. Existing source/static/
golden/package/version/public proofs remain reusable. No Product acceptance.

Root General and Transaction approved the amendment, P1/P2/P3=0 (Task
8424748ac0e0fd38236681571f8a6cd9646d6d0bad787f1b238cf89ee6e86d2e before
acceptance-only status wording). Luna's test SHA is
3fe2922366a4400fa62c37e09393801e35320b1be5d25f5ba78c3e1a9cffe11b.
Removing the five added lines restores baseline
cb63bcac82a2eb143d009fe17fdc6441a392571fe4f3d1f488553f80c8ce6a0b exactly;
`memory-recovery-delta.json` proves unchanged bodies, helpers and fixtures.

At23:33:08+09:00 all7 ingress-codec tests pass37 assertions,74.5MiB and
1.831s with full-suite discovery. The temporary diagnostic bootstrap requires
the original vendor/autoload.php and only records process ID/memory settings;
it changes no setting. `isolation-memory.jsonl` confirms parentPID1 and childPID8
both use128M. `run-20260911T233307` records this command and the subsequent
global format PASS1.942s. The full retry uses the original bootstrap and command.
The PHP management-ID scan and diff check also pass. `recovery-final-inputs.json`
freezes exactly14 Product/Test paths (6 changed/8 new), the original13 unchanged,
all194 public inputs unchanged and both process memory settings.

Community Board clean install passed at23:31:00+09:00, exit0/49.295s, in
`run-20260911T233100`. Frontend46 tests/8 files pass; Svelte check reports
zero errors/warnings. The existing pg_dump circular-FK warnings remain visible
and non-failing. All11 generated cleanup targets were absent before execution
and absent afterward. Its Product/config/lock inputs are unchanged by the
parallel Test-only attribute edit, so this result is reused for Final.

The original-command retry `run-20260911T233510/full.log` passed the isolated
ingress boundary but failed after856.289s, exit2, around1295 of3289 tests.
The next large fixture, AuditProtectedBodyCodecTest::testOversizedRecordBodyFailsBeforeActiveKeyLookup,
exhausted128MiB while JCS joined a1052672-byte outbox envelope. No assertion
failure preceded this fatal exit; its JUnit is again empty. The first recovery
worked for its selected case but did not resolve the whole-suite memory gate.

Root is investigating the remaining MiB-scale cases together before another
full restart. Temporary retained-memory probes are diagnostic allocation
budgets, not an exact reproduction of prior process state or Acceptance tests.
The first116MiB-budget probe also exposed a probe-only stderr issue in the
isolated child; this is corrected to file logging before drawing conclusions.
No Product code, PHP memory limit or further Repository test change is made
until the bounded additional recovery scope is reviewed.

The corrected120MiB retained-parent diagnostic at23:57:41 reproduced the
natural second failure (JCS1052672-byte allocation) and found one additional
failure in testOutboxBopdPlaintextCeilingIsInclusiveAndPreKey (BOPD1048576-byte
allocation). The two other unisolated cases in that batch and all six further
MiB-scale cases at23:59:19 pass. Logs/argv/exits/times are in
`budget-20260911T235741` and `budget-20260911T235919`; metrics record
125829120 retained parent bytes and unchanged128M. Diagnostic wrapper exit0
means the probe matrix finished; each case's exit is evaluated separately.
These probes select a narrow recovery scope, not substitute suite coverage.

The second Task amendment adds only AuditProtectedBodyCodecTest as a15th
Product/Test path, with native PHPUnit process isolation on those two failing
methods. All method/helper bodies, fixtures, assertions and original14 paths
must stay exact. Baseline SHA256 is
327bd74a9d950189713de3e71b42762d560aad2d227697716bbb4e30685e75c1.
Root General and independent Transaction approved Task
3059ad69f2cde556bffc7b9dffbee0a4bbbe35555e697bda2c02bbeb0cde9350 with
P1/P2/P3=0 before worker dispatch. Source/static/public/Community evidence is
unchanged; this review covers only the additional Test-only recovery scope.

Luna's second recovery adds exactly8 lines and produces SHA256
`dbed717558598ac37a75d2a654e2465f7e1311212a6a6a64b08bed1de08a986b`.
`memory-recovery-2-delta.json` removes those imports/comments/attributes and
restores the whole baseline file byte-for-byte; original14 files stay exact.
Root General delta review is Green. Worker handoff is
`worker-runtime/memory-recovery-2.md`; no worker PHP command ran.

At2026-09-12T00:08:17+09:00 both recovery classes pass23 tests/642 assertions
with full-suite discovery in3.566s, PHP memory74.5MiB. The logging-only bootstrap
confirms parentPID1 and all three isolated childPIDs8/9/10 use128M. At00:08:20
and00:08:23 the two previously failing synthetic cases each pass1 test/9
assertions in2.411/2.996s. Global format passes1.907s; PHP management-ID scan
and diff check pass. Exact argv/inputs/image/logs are in `run-20260912T000816`,
with diagnostic cases under `budget-20260912T000820` and process settings in
`recovery-isolation-memory.jsonl`. Synthetic runs remain diagnostic only.

`recovery-2-final-inputs.json` freezes15 Product/Test paths:7 changed/8 new,
no outside-scope delta, original14 and194 public inputs unchanged. It also
verifies the unchanged profiles/AGENTS independently of the runner manifest,
whose source/test selection intentionally omits those four files. The first
scope-script attempt mistakenly treated that omission as deletion; the corrected
read-only collection confirms their exact original hashes. No repository input
changed to address that receipt-only error.

Independent Transaction delta review is Green, P1/P2/P3=0, with an exact
baseline restoration and original14 hash check. It also checked the23/642,
128M parent/three-child and format receipts. Source and Documentation review
inputs remain unchanged. The synthetic recheck's child bootstrap log precedes
the parent's padding event, so its success does not prove the parent retained
120MiB before that child ran. It proves the same diagnostic command now passes;
normal full-suite execution remains the required cumulative-memory check.

Root started the third original full command at2026-09-12T00:10:42+09:00.
`run-20260912T001042/full.log` uses the original bootstrap, unchanged128M and
reviewed15-path candidate, with no diagnostic extension. Results and JUnit are
pending. No previous failed full run is relabeled as passing evidence.

Root reconciled spec106's exact5 management entrypoints during final
preparation. The accepted P23-001 parent Task/Report retained an old suggested
next action; two short notices now send current delivery readers to STATE/TODO
while preserving the entire prior checkpoint byte-for-byte. D154's current
handoff replaces its stale pre-implementation instruction with D2S Task/Report
links. Its historical and contract body is unchanged. The explicit Task
management-scope amendment covers only these navigation changes; Root's
editorial delta review and local link check pass. Proof is
`management-navigation-delta.json`. No source/test/release authority or semantic
contract changed, so no Product check or specialist review is invalidated.

## Final Local Gate and Candidate Acceptance

The third original full command completed exit0 in981.582s. PHPUnit reports
3289 tests / 5,681,826 assertions,127MiB peak under128M, with zero failures,
errors or skips. Existing diagnostics remain:1 deprecation,2 PHPUnit
deprecations and13 PHPUnit notices, matching the Accepted D2S1 counts. These
are not hidden or relabeled as zero-issue output. The complete JUnit SHA256 is
`0de65a84e74403a74dc7bf3e8fdac29939b6d28c66bdb4f9eef06f44dc55a9fc`.
`full-pass-summary.json` checks all3289 cases, all45 new adapter cases
(17 query /8 guard /6 execution-source /11 PostgreSQL /3 architecture), and
all three isolated recovery cases. The original full run confirms the actual
cumulative-memory recovery; synthetic probes are not the Acceptance proof.

`third-full-reuse-proof.json` binds the reviewed15-path candidate to this run
and independently checks1058 source/config/lock inputs against the successful
Mago/Deptrac run. No source/static/golden/inventory/version/Community/public
check is repeated merely because a Test execution attribute or receipt changed.
General, Transaction and Documentation reviews remain Green for their exact
inputs; the additional Test-only recovery deltas have General/Transaction
Green. Root accepts the implementation and verification union. Package90/39
is already Green; the locally committed clean candidate must still pass its
own Git/Composer export before the D2S Task is complete.

Recorded command time before export totals3639.111s (60.65min) across the
Root serial runner, six Node goldens, version check and timed package inventory.
Nested diagnostics count once; review/scheduling waits and read-only research
are excluded. Full PHPUnit ran three times: two preserved OOM failures and
one successful run after reviewed Test-only changes. There was no successful
same-input full rerun. The first architecture execution found oracle defects;
only affected iterations ran before the required final union. Authority needed
one correction round; implementation source/oracle findings and both narrow
recovery amendments were closed by delta review, with unchanged lanes reused.

JUnit identifies the main future speed target: the D2 mutation oracle takes
611.230s and the scoped Status mutation oracle129.134s, together75.7% of
suite testcase time. The existing duplicate dependency-inventory analysis is
a concrete follow-up candidate, but no speedup is claimed without measuring
that change. Keep all mutation controls when optimizing this separate task.

The user additionally requested a detailed tenant explanation for the website.
That documentation is the next task, after this frozen candidate's export;
read-only Stable1.2.1 research runs separately. No public source was changed
while validating this candidate. Other Audit reader work remains after that
requested documentation addition.

## Reviews and Acceptance Criteria

First Transaction lane: P1=0/P2=2/P3=0 (trace boundary; unreachable unit gate).
First Documentation lane: P1=0/P2=1/P3=0 (same trace boundary).
Root General agrees and revised those contracts before implementation. Focused
Transaction and Documentation rechecks are Green with P1/P2/P3=0. Reviewed
spec107 content hash before acceptance-only status wording:
912cd4a9ef7e9a3c5990c1d3f71c554b01007619137348bdfce74585d2703125.
Root General confirms exactly14 declared Default parameters, unchanged scoped
source protocol and the actual-oracle coverage assignment. Authority Accepted.
For independent implementation work, the13 paths are split into Runtime7,
Architecture/package5 and PostgreSQL1; PHP/DB ownership is explicit in the Task. No contract or
required command coverage is reduced by this ownership split.
Authority acceptance and Product acceptance are separate; neither is inferred
from the accepted source prerequisite or the1.2.1 release.

## Preliminary Implementation Review

Root reviewed the initial four source files before full worker handoff. The
main scoped binding, terminal order and Default annotation-only diff align.
One P2 was returned: assertIdle's specific QueryException catch could preserve
an inspection failure instead of mapping every guard Throwable to fresh Storage.
The worker must add the corresponding typed-query-exception getter fixture.
Root also requested concrete guard/ValidatedUuidv7Generator snapshot invariants
so malformed snapshots cannot bypass those mandatory wrappers. The independent
Transaction source review found no additional issue. At02:08:57+09:00 it
confirmed the known corrections and Default's annotation-only delta. Inputs:
Default d76b31b611896bf22b856f6307de6029a2c7122df93d21465b59bec65a09d6d1;
query f03ce4e7bf75dc1725468f48dc0e74aa2ae79deefc34608bb2f3a16c89e69e1d;
guard fef0b0d3bff220e534cc6c6145ed18618e31e5de32460f7d61f673e3b74733e6;
execution5b6c138e173a6f1dcfcbfb8de5d9e8c46a0cab0aff1cee63e38506d4c06511fc.
Source-only review adds no new finding; it does not replace tests or final review.
Recheck only subsequent input deltas.

Root's preliminary Architecture review requested actual AST ordering and
exception/result identity controls, strict tenant comparison operators, nested
terminal mapping and resolved-name/case-insensitive callsite handling. Counts
alone must not substitute for these frozen requirements. The Architecture
worker is addressing this feedback before execution; no test Green is claimed.

Independent Documentation implementation review adds no Finding (P1/P2/P3=0):
authority6 and source4 match their recorded hashes,192 public inputs match
published M excluding the two previously reviewed development guards, and the
HTTP composer/Accepted scoped source remain exact. Final acceptance still
requires runtime/architecture evidence. Re-review only affected input deltas.

First full Transaction oracle review returned P1=0/P2=4/P3=0 on new oracle
bd88b40717ad74db5f48ce175fb904d21fa2fbde16467990c4df55eb5427d6d2:
baseline rejection, invalid mutation generation, lexical-order/identity gaps
and incomplete FQCN/consumption detection. Luna returned corrections in the
same3 architecture paths; package scripts remained exact. Independent delta
review is continuing on9acb349c3ffa95f1dba526ac75887051853ae391b144a1126573cb9a3e13ed13.
These are test-oracle findings; the reviewed4 Product sources are unchanged.

## Implementation Review Closure

Root General and independent Transaction source review confirmed the fixed
idle-check mapping, mandatory concrete snapshot guards, per-read tenant view,
terminal precedence and exact14-attribute Default correction. Unit fixtures
now exercise real captured dependency canaries with exception arguments enabled,
not empty/vacuous debug objects. Plaintext Audit canaries capture the draft
while forwarding it to the actual PostgreSQL writer; ciphertext search alone
is not the evidence for excluding decrypted data.

All returned architecture P2s were corrected within the three authorized
oracle files. Final Transaction delta review is Green, P1/P2/P3=0, on new
oracle7c0b263a, scoped82997fa2, D2e8b307a6, query81a140e5 and PG2d77e04a.
The subsequent PG assertion correction is explained above and passes its
affected test; final formatting and resulting hashes will be reconciled before
Acceptance. Actual PG fixtures now include Expired410, requested/terminal SQL
append failures, foreign ambient/auto-commit transaction noninterference,
three-root isolation/visibility, and plaintext draft exclusion.

Documentation's final affected-input review is Green, P1/P2/P3=0. It confirms
the comment-only query delta, unchanged release/public inputs and unchanged
composition boundary. No repeated review is needed for receipt-only edits.
Final verification, exact frozen inputs and clean export remain separate gates.

The final Transaction closure confirms the one Expired assertion and all
four formatter deltas preserve Green, P1/P2/P3=0; no new gate is required.
Root General independently confirms the exact Default diff, final input/scope
proof, unchanged source4/package2, corrected oracle controls and evidence reuse.
Specification107/D156/index status lines now refer to the Report for Product
progress; this administrative change replaces stale implementation-pending
wording without changing any contract or release authority.

## Release Documentation Impact

Current release tuple is exactly1.2.1 in release-authority.json. Internal
capability audit.operation_status_adapter.v1 remains unreleased. Public source/
route/API/config inventory empty; no old-version or historical allowlist change.
Spec107 supplies implementer guidance; runnable usage docs await activation.
No generated/Search/LLM/browser/CI/delivery/deployment claim in this Task.
Default's14 annotations also protect existing development callers without
changing their query behavior; unannotated outer Application frames are outside
the guarantee. Actual same-Connection application wiring remains D-integration.
Only detailed reads own the three roots; denied or missing reads skip the detail
snapshot. Do not equate source-only review with exercised transaction evidence.

## Remaining Issues

Non-blocking verification-efficiency follow-up: independent static inspection
found duplicate same-input `d2DependencyInventory()` calls in
AuditOperationReadArchitectureTest::d2ArchitectureViolations (the outside-writer
check). A later Test-only packet can reuse that method-local result while
preserving every positive/negative/mutation control. Do not add a persistent
token cache or change this frozen candidate. This is an unmeasured improvement
candidate, not the cause or resolution of the observed ingress OOM. Compare
PHPUnit elapsed time and reported PHP memory under the same image/config and
128M limit; host RSS is a separate measurement. Use actual PHPUnit options,
not Node's test-isolation flag.

Clean final-SHA export remains. Community Board and all required local checks
are complete. Other Audit adapters/API/signature/query/
export/runtime/docs/release tasks remain outside this bounded adapter.

## Suggested Next Action

Root commits the accepted candidate locally and runs clean-SHA Git/Composer
export. Then create the separate tenant-concept documentation packet requested
by the user; D2J reader preparation follows afterward.

## Historical Summary

The user's continuation starts D2S authority preparation after Accepted D2.
No D2S packet existed. Status is the first independent reader child; no D2J
or D2X dependency is introduced.

## Historical Changed Files

Only this Report, the new D2S Task, STATE, proposed Specification107 and
proposed D156: exact5 management paths. No Production/Test changes.

## Historical Decisions and Assumptions

- D2 final SHA is `0cd699e5e1d031228bc4a05767164a8eb268a004`; previous-turn
  final same-SHA export passed. This turn confirmed HEAD and clean tree before
  management edits, not a new export run.
- D154 requires a most-specific Decision and exact Task before Product work.
- `DefaultOperationStatusQuery` looks up restricted subject metadata before
  authorizing, then reads detail only after Allow. An outer Audit boundary can
  precede lookup but cannot alone fix tenant-scoped SQL for subject/detail.
- The legacy tenant matrix deliberately permits cross-tenant Found when the
  Application authorizer allows it. D147/D155 require cross-tenant unavailable
  for audited reads. The audited graph must explicitly reconcile this.
- Expired is a 410 response after authorized detail access, not a 404
  unavailable result; the new authority must select its terminal explicitly.
- `PostgreSqlOperationStatusSource::readDetail()` owns a repeatable-read,
  read-only transaction. Level0 at reader entry/exit is not a claim that the
  whole detail read runs without a transaction.

## Historical Commands and Results

Read-only AGENTS/STATE/Task/spec/Decision/source/test inspection; Git HEAD
and clean status verified. Production tests, new gates, package export, CI,
browser and deployment have not run in this task.

## Historical Release Documentation Impact

Stable `1.2.0`; candidate `1.3.0-unreleased`; proposed capability
`audit.operation_status_adapter.v1`. Public Source/route inventory: empty at
this management-only stage. No public API/config/command/version occurrence
or generated artifact changed. Browser/generated HTML/Search/raw Markdown/
LLM/CI/Documentation delivery are Not Verified. No Production deployment.
Guide/internal runtime documentation impact will be decided with the exact
adapter graph; no reader activation or Stable capability is claimed.

## Historical Acceptance Criteria

Source research identified a concrete prerequisite; the initial adapter-only
proposal is explicitly incomplete and not accepted. Exact Production scope,
tests and independent authority reviews remain pending. No worker dispatched.

## Historical Confirmed Source Blocker

Specification99:125 requires tenant_type/tenant_id and Operation identity in
the same SQL predicate, not fetch-by-ID followed by PHP tenant comparison.
Source inspection confirms:

- `DefaultOperationStatusQueryTest.php:115-145` intentionally returns Found
  for none/same/cross when the Application authorizer allows it. D147:480-483
  requires cross-tenant unavailable for audited reads. An audit-only mandatory
  tenant guard is necessary, but not sufficient.
- `PostgreSqlOperationStatusSource.php:60-74` calls unscoped `findSubject()`.
  `PostgreSqlStatusReader.php:76-110` already has a scoped subject method, but
  the current source does not select it.
- The source's detail helpers at531-603 call unscoped deferredState,
  purgeTargets, Journal records, outcomeExists, deadLetterExists and Outcome
  find. The source does not pass the subject's tenant into those lookups.
- `PostgreSqlStatusReader.php:113-181` shows operation-ID-only predicates for
  deferred state and retention. Existing Journal `recordsForTenant` and Outcome
  `findForTenant` can be reused, but scoped equivalents for all Status detail
  sources and snapshot/race invariants still need a reviewed contract.

These are source-level contract findings, not a claim of a demonstrated live
cross-tenant exploit. No live exposure test was performed. The initial
three-type spec107/D156 proposal remains visibly blocked; its API/path counts
must not be copied into a worker dispatch.

Independent Transaction research returned Changes Requested P1=1/P2=0/P3=0
for this incomplete adapter-only boundary. Independent General research also
confirmed the complete scoped-source prerequisite. Neither is an Acceptance
review of a corrected implementation; no Product tests were rerun.
The prerequisite must recheck the tenant-scoped subject inside the detail
snapshot before protected decode, not trust an earlier subject indefinitely.

## Historical Proposed Safe Split

1. Separately authorize and review a tenant-scoped Status-source prerequisite:
   exact subject/detail SQL, before-decode isolation, source-owned snapshot,
   null/same/cross and subject/detail race evidence; preserve current public
   API and explicitly decide additive latent paths versus legacy behavior.
2. Implement that exact packet through the configured Luna worker; independent
   General/Transaction reviews and full gates before Acceptance.
3. Revise D2S's audit adapter around the Accepted source, re-derive its exact
   file/package/Architecture inventory, then review and implement it.
4. Keep current Application/HTTP activation in D-integration, not either child.

## Historical Remaining Issues

The source prerequisite exceeded the initial adapter-only proposal; the user
subsequently approved it by continuation. P23-003D2S1 now owns its authority
and implementation. The remaining adapter authority questions must also close.
Parent D remains incomplete; D-control still requires Accepted F.

## Historical Suggested Next Action

Complete the authorized P23-003D2S1 prerequisite packet and its review; do not
dispatch the adapter worker before that prerequisite is Accepted.
