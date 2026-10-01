# P23-003D4B Report

Status: Accepted (local) — frozen full PASS; independent reviews Green
Accepted At: 2026-09-21T23:13:30+09:00

## Summary

Implemented bounded authorization event projection and delivery in Inline,
Deferred acceptance and Worker paths after accepted D4A. Real PostgreSQL fixtures
verify committed records, rollback, denial, replay/retry, root identity, privacy
and controlled post-commit faults in both delivery modes. Canonical composition
remains inactive. Independent source/security review is Green. The frozen full
passes3,513 tests/7,139,885 assertions with all3,446 baseline identities retained
and67 expected added cases. Independent final evidence review is Green, with all
eight Packet criteria satisfied. Root accepts this bounded internal implementation.

Scope is16 Product/Test files and one reviewed location-only quality metadata
file. No new lint suppression/baseline entry, public capability, release, commit,
CI/export or deployment is included.

## Changed Files

Root-owned spec115/D164/Packet/Report, index/TODO and rotating STATE checkpoint.
Product/Test inventory and per-lane owners are in the Packet.

## Decisions and Assumptions

Reuse the existing source-backed root-hook/projection notes and a narrow constructor
survey. Optional constructor dependencies preserve all seven canonical composers.
Both delivery modes join a business mutation root; no-policy/legacy paths remain.
Three bounded new types and two pure connection-identity methods are proposed.
Independent authority review precedes any implementation.

Authority review found an unconditional rollback assertion for commit failure.
It is corrected to distinguish confirmed pre-commit rollback from commit-then-throw.
Source shows an inherited Inline ambiguity: failure Journal/idempotency fallback
can follow committed completion. D4B will reproduce/preserve bounded failure and
no duplicate handler execution; terminal reconciliation remains a named integration
and hardening obligation before release, with no recovery-success claim.

## Commands and Results

### Current gate evidence

All paths below are under `/tmp/blackops-d4b-20260916`. Successful unchanged
inputs are reused; the frozen full supplies the final complete suite.

| Gate | Result | Receipt |
| --- | --- | --- |
| Authorization units / native PostgreSQL | PASS37/381 and45/418; no notices | run-20260921T221441184336 |
| Analysis | PASS; configured Product inputs unchanged | run-20260921T221441184336 |
| Lint / strict baseline | PASS;176 entries,186 filtered diagnostics | run-20260921T222503240441 |
| Deptrac | PASS6,951 allowed dependencies; zero findings | run-20260921T222652697608 |
| Final Authorization guard / PHP format / comment identifiers | PASS6/2,715; format and identifier checks pass | run-20260921T223641271799 |
| Package syntax / inventory / controls | PASS;107 unique paths and58 rejected controls; exact script hashes retained | architecture-shell-receipt.checked.json |
| Source/security review | Green; P1/P2/P3=0 across17 candidate files | source-review-final.md |
| Final independent evidence review | Green; P1/P2/P3=0; all eight criteria satisfied | final-evidence-review.md |
| Frozen full | PASS3,513/7,139,885; zero failures/errors/skips;133MB | run-20260921T224310757539 |

`expected-added-cases.json` lists all67 added identities from passing affected
suites:18 projection/coordinator,45 native PostgreSQL and4 architecture cases.
The initial65-case preparation omitted two new C3 shared-guard methods and is
preserved separately; including all affected A2/C3 receipts corrects that inventory
without a Product/Test edit or rerun. The full's collection count is3,513.
`acceptance-evidence-map.json` maps the eight Packet criteria to tests/reviews;
its independent evidence-review criterion is closed by the final review receipt.
`full-evidence.json`
and `scope-20260921T230158.json` prove exact baseline/added-case identity matches,
17 unchanged candidate files and1,971 unchanged runtime inputs before/after/live.
All six frozen review receipts remain exact. No foreign change occurred;193 public
sources,421 artifacts and release authority are unchanged.

Full runner time is1,051.982s; PHPUnit reports17:30.030 and133MB under the shared
512M configuration. There are1 deprecation,2 PHPUnit deprecations and13 notices,
the same aggregate counts as D4A. This is not warning-free evidence and does not
prove equal individual issue identities. JUnit SHA256
9bef228c3afa2657ea07aa78ce1447eb244fbbc5623c9f6e2a21992d07bfd082.

Workflow feedback:70 measured runner commands total1,252.974s, including200.992s
of iteration/format/diagnostics/management and one1,051.982s full. These figures
exclude approval/agent waits and separately recorded shell/probe commands. The
only exact-argv/input repeat follows Docker socket denial before Product execution;
there is no repeated successful full. Formal reviews so far: authority1,
source/security3 (two Changes Requested, then Green), quality amendment2 and final
evidence1. `workflow-feedback.json` preserves the pre-review calculation snapshot.
The three slowest architecture cases take653.883s,117.599s and68.721s; future
performance work must preserve their behavioral/negative coverage in a separate Task.

### Iteration history and input boundaries

- Live AGENTS/STATE/Task/spec/profile/Git checks; accepted D4A10 candidate and1,965
  runtime input hashes match before this task. HEAD unchanged.
- Baseline:3,234 Git-visible files,145 dirty entries; baseline.json SHA256
  53f47c570325ed014e519ec0dd8938dd0f726a410feb9ec29f071d7249087efa.
- Copied421 generated-artifact baseline, release authority and D4A final receipts
  to /tmp/blackops-d4b-20260916. Ignored vendor Composer files remain runtime
  verification inputs, not Git-visible scope-diff inputs.
- Read-only constructor survey identified exact trailing injection seams and
  existing PostgreSQL fixture patterns.
- Authority review Green P1/P2/P3=0; authority-review.md SHA256
  c67f91ee33fc1335e63cd869b6a091f4f259421b73930db169660c0d3f6c558a;
  authority-review-inputs.json SHA256
  18fbb1214654d0de10a18e4549378719a0b0bddd216130201df05a05da7b00b9.
  Status-only updates after this receipt do not change normative authority.
- Management diff/STATE checks passed in run-20260916T203630943929;0.142s/0.033s.
  Ten shared-before Product/Test files have exact baseline byte copies for review.
- Environment-only `docker compose run --rm --no-deps -T app php --version`
  passed0.698s, PHP8.5.7/Zend4.5.7; runtime-version.json records argv/tool receipt.
  PostgreSQL is healthy and no other BlackOps test container is running.
- Early source review found missing exact policy agreement, extra rejection-code
  narrowing, no-policy snapshot bypass and standalone connection identity gaps;
  foundation owner corrected these and included focused regression coverage.
- Worker attempt handoff had a by-value outer arrow around a by-reference inner
  closure. A PHP8.5.7 probe reproduced `outer remains null` in0.627s, meaning deny
  delivery could be skipped. arrow-reference-probe.json and finding receipt
  preserve the probe; native owner fixed the source before the attempted byte
  capture. Persisted denial-event regression remains required.
  These are review findings, not passing Product test results.
- Early native test `php -l` failed255 in0.669s because a fixture declared the
  redundant `object|AuthorizationPolicy|null` union. native-initial-syntax.json
  records the diagnostic; input was still under development, not final evidence.
  The native owner corrected it before real fixture execution.
- Focused commands are recorded with literal argv, before/after inputs, image,
  elapsed time, exit status and logs in each `run-*` directory below. Reported
  durations exclude tool approval waits. The first runner attempt
  run-20260916T211545394741 could not access the Docker socket (0.052s); the
  approved rerun is the actual Product evidence.
- run-20260916T212037962620: unit36/375 passed in1.165s with9 PHPUnit notices.
  These were test-double notices; a mock-to-stub correction followed.
  run-20260916T212646832236 checked the coordinator class only:9/66 passed in
  1.064s without notices. It is not the whole foundation suite.
- run-20260916T212916190197: Inline allow smoke2/14 passed in2.212s. This is
  limited post-return persistence evidence, not the complete atomicity matrix.
- run-20260916T213015500839: native39/116 errored24 times in13.108s. All24
  failures share the fixture's incorrect JournalRecordFactory constructor
  argument (UUID generator instead of IdentifierFactory); inputs were stable
  throughout the command. Native owner is correcting this fixture before rerun.
- run-20260916T213449462461: foundation and architecture formatting passed
  (0.892s/0.886s). Whole authorization unit36/375 passed in1.109s without
  notices. Architecture5/400 failed3 cases in1.039s: exact helper inventory and
  constructor/mutation graph bookkeeping need correction against spec115; the
  architecture owner retains negative controls while correcting those oracles.
- Architecture-owned shell receipt records107 ordered/unique package paths and
  58 rejected controls (44 inventory/14 call-contract). Root will compare final
  shell input hashes before reuse. Independent source/security review is underway.
- run-20260916T213754135785: lint failed17 issues (6 errors,3 warnings,8 helps)
  in0.857s; analysis did not run after this failure. Owners are resolving scoped
  complexity/signature diagnostics without changing the lint baseline.
- Independent review identified overbroad Worker reject exception remapping and
  type-correct corrupt Attempt snapshot agreement gaps. Both are assigned for
  correction and regression tests; no source Green is claimed. After its handoff,
  foundation corrections transferred to the architecture Luna worker because of
  the live thread limit. Native ownership remains unchanged.
- Initial source review receipt is Changes Requested, P1/P2/P3=0/4/0:
  source-review.md SHA256a346ea2adc0838cbd209706b407790ec7fe50dfe13e7b6f669461d60b8a256d4.
  The other two groups are the missing seven-site no-activation argument guard
  and A2/C3/fixture-anchor bookkeeping. Source fixes and bounded negatives are
  being integrated; historical review inputs remain separate from later edits.
- run-20260917T233945254972: native41/334 ran in15.088s with stable inputs:
  37 cases passed,2 errors in Required record hydration,2 failures in a
  post-commit business-rejection exception classification fixture. The worker
  is investigating the readback shape and actual fault injection location.
  This followed a long approval wait; the wait is not test duration.
- run-20260917T234039961835 analysis found5 errors in2.495s. Detailed diagnostic
  run-20260917T234207726899 (1.358s) locates all5 at Attempt request/plan type
  narrowing around the new agreement validator. Fix is scoped to real runtime
  null-or-instance validation, without suppressions or baseline changes.
- Interim scope receipt scope-20260916T214019.json has all16 candidate files,
  zero out-of-scope changes,193 unchanged public docs,421 unchanged artifacts
  and unchanged release authority. This is hash comparison, not a new build.
- run-20260917T234501863154: formatted native41/350 PASS in14.872s, no notices.
  Controlled post-commit injection performs a real PostgreSQL commit and confirms
  committed rows via an independent connection. Its test Connection makes an
  inactive rollback a no-op to preserve the injected exception through DBAL;
  native DBAL could otherwise replace it with NoActiveTransaction. This proves
  the selected fault path, not real network/process loss or universal exception
  identity. Independent reviewer confirmed this limitation is explicit.
- Follow-up review closed the eight Production source findings, but requires
  two fixture additions before Green: real-PG root/connection rejection and
  nonvacuous input/privacy canaries in own-frame traces and persisted logical
  JSON. Luna owners added the bounded fixtures; execution/review remain pending.
- run-20260917T234649326491: format checks passed; unit37/376 had one fixture
  error (missing AuthorizationAuditEvaluation import), corrected by the owner.
- run-20260917T234929719675: A2 PASS16/26,077 (11.429s), direct Relay reader
  PASS1/24 (0.914s), analyze PASS (1.354s). Authorization6/2,700 failed one
  negative fixture; C3 9/85,557 failed receiver/constructor/forbidden-symbol
  bookkeeping. Corrected guard deltas await rerun. Normal lint exited0 but had
  four warnings/one help; it is not clean strict-baseline evidence.
- run-20260921T214603037997: strict lint baseline failed with four new/one
  obsolete issues (1.214s). Owners are removing unfulfilled/redundant pragmas
  and handling scoped complexity; the baseline file remains unchanged. Deptrac
  passed6,951 allowed edges, zero violation/uncovered/warning/error (1.814s).
  Comment identifier scan passed with no matches (rg exit1,0.029s).
- run-20260921T215014945837: sandbox-only retry could not access Docker; all
  commands exited before Product execution. The approved batch below supplies
  the actual results; socket-denial durations are not Product failures.
- run-20260921T221441184336: unit37/381 PASS1.520s, native45/418 PASS15.685s,
  authorization architecture6/2,707 PASS2.718s, analyze PASS1.520s, whole PHP
  format check PASS1.223s. C3 still has one exact-receiver baseline mismatch;
  the owner is producing an actual receiver-map diagnostic before correction.
- Strict lint in that run isolates one unchanged diagnostic identity: existing
  InlineDispatcher::dispatchEnvelope `halstead`, now lines241–375 instead of
  235–358. Packet proposes a two-number location-only update to the existing
  baseline entry, keeping176 entries/rules/files; independent review precedes
  the edit. This is quality metadata beside the16 Product/Test files, not a new
  suppression or broad baseline regeneration. All other lint findings are fixed.
  Reviewer parsed176 TOML issue entries; Mago's185 filtered diagnostics plus one
  obsolete diagnostic is a different count and must not be called186 entries.
- The first location edit used rendered one-based lines and did not satisfy
  strict matching (run-20260921T222122502798). JSON diagnostic in
  run-20260921T222228861886 proves Mago stores zero-based240–374 for the rendered
  241–375 range. Packet corrects the same two-number amendment to240–374;
  diagnostic identity and176 entries remain fixed. No source fix is implicated.
- Corrected quality amendment Green is pinned by
  quality-scope-review-correction.md SHA256
  6870adf7ad711dfda049ee3b6511f2d3462058eab9f4b3f25711587493f60a73.
  Final baseline SHA2565913fcc869715888fd8376a0ea6947fc096cf3312b8afea5e8d87d6f4544ed32;
  quality-baseline-final.json proves only those two numbers differ from the saved
  original. run-20260921T222503240441: lint PASS0.946s and strict baseline
  PASS0.791s, no findings;186 diagnostics filtered across176 entries.
  Final candidate/scope receipts now track17 files:16 Product/Test plus this one
  quality metadata file. Historical16-file receipts keep their original meaning.
- run-20260921T222652697608: Authorization guard6/2,713 PASS2.661s;
  C3 guard9/2,668,995 PASS69.725s; final format PASS1.274s; Deptrac6,951 allowed
  edges and no findings PASS1.865s; identifier/diff/STATE checks pass. Final
  bounded follow-up adds a dynamic-call negative to the Authorization result
  chain and removes two vacuous C3 positive snippets. Its real-source positive,
  oracle and all negative controls remain; c3-before-positive-pruning.php pins
  the passing C3 bytes. Independent reviewer permits reuse of those retained
  checks; final full will execute the pruned test, with no exact-file focused
  claim for that later C3 version.
- Mago configured source paths are src and examples/quickstart/app. Unchanged
  Product inputs permit reuse of successful lint/analyze after test-only edits.
  The shell107/58 receipt's syntax success and exact input hashes are reusable,
  but its recorded microsecond syntax duration is unreliable and is not claimed.
- run-20260921T223641271799: final Authorization6/2,715 PASS2.789s; whole format
  PASS1.219s; identifier/diff/STATE checks PASS. Source/security review is Green,
  P1/P2/P3=0; source-review-final.md SHA256
  595523c19a61fef7e7a6e78761bf2292aa0002a61eec39be301db8fcd61211ee;
  source-review-final-inputs.json SHA256
  4a42844208b993c01e8e5224bb934c68f60c4ca46aa39b5e8846646f96114595.
  Every17 candidate hash matches live bytes, all1,971 runtime inputs match the
  last quick checks, and no source/fixture/guard finding remains open.
- Frozen candidate SHA256
  b2ae03e7270807132d22404ec7e864800a8166ab4f906a4646a4d96eaa7f33ee
  pins17 candidate files,1,971 runtime inputs and six review receipts. The single
  full started2026-09-21T22:43:10+09:00 under shared512M in
  run-20260921T224310757539; full.log and running.json record execution state.
  No full result or final Acceptance is claimed before completion.

## Acceptance Criteria

Authority, quality amendment and source/security review Green. The frozen full
passes all3,513 cases with the exact baseline and67-case addition described above.
It includes the final pruned C3 bytes and the native PostgreSQL cases. No subset
or static gate was repeated after the full. Final independent evidence review
is Green, P1/P2/P3=0; final-evidence-review.md SHA256
688bc8172abbeb730c8f3f6873ad32df33a7349266b4739c79cf035451f12876;
final-evidence-review-inputs.json SHA256
7f5348dcfbfead12d90b1a6527fac20ec0d4bfb77a7cd39221838e538a554020.
Its eight-criterion matrix closes every Packet criterion. Root records local
Acceptance; canonical activation remains outside this Task.

Management-only closeout is recorded in `management-closeout.json`: exact review
and candidate hashes, status-only specification/Decision changes, unchanged Packet
criteria/verification, completed checkboxes, final diff/STATE checks and the final
scope receipt. The pre-closeout management bytes and readable diff are retained
beside that receipt. Management status edits do not invalidate Product evidence.

## Release Documentation Impact

Experimental Stable1.2.1 tuple and public capabilities unchanged; proposed internal
audit.authorization_delivery.v1 only. Public source/routes, historical allowlist,
search/site/LLM artifacts have no planned change. Spec115 documents implementation
architecture while canonical runtime/configuration remains unconnected. No new
public build/browser check, same-SHA CI/export or production delivery performed.

## Remaining Issues

D4B scoped implementation, verification and Acceptance are complete. Parent D4/D
and BlackOps1.3 remain incomplete; D5/composition/E/F/G/hardening/docs/release follow,
with D-control after Accepted F. Final local documentation preview and user review
are mandatory before release and remain pending.
Inherited Inline ambiguous-commit lifecycle/idempotency reconciliation remains
an explicit integration/hardening obligation before release.

## Suggested Next Action

Root prepares and independently reviews the most-specific D5 retention/hold
authority and bounded Packet before a new Luna implementation. The D4B full has
exited successfully; no repeated Product gate is needed for management closeout.

Preparatory D5 source survey ran independently during the full and changed no
repository files. `d5-retention-root-survey.md` SHA256
e4e9cdc33402e469b384214d009575faba3ff4e22f804a1cc95194fc16534b3a
and its33-file/excerpt input map identify target-batch purge roots, hold's lack of
an explicit root, missing transport/release tenant projection and absent trusted
execution provenance. The next authority must select the event/commit unit,
projection owners, no-op/partial/ambiguous retry semantics and exact callers.
This is read-only preparation, not a D5 contract, implementation or acceptance.

## Subsequent integration boundary

The original local verification and attribution above remain unchanged.
P23-006C owns the subsequent isolated integration in the reviewed D3+D4A+D4B+D5H unit, its separate commits and final convergence checks.
See [the integration Report](P23-006C-accepted-audit-integration.md).
No historical missing intermediate or new same-SHA full is implied.
