# P23-003D5H Report

Status: Accepted (local) — all eight criteria Green; BlackOps 1.3 unreleased

## Summary

Implemented the bounded hold place/release child after local D4B acceptance.
The Internal adapter owns one mutation+durable-ingress root and preserves the
native Store/Port/layering. Source binding is explicit; no actor authentication
or execution context is inferred from legacy free-form fields. Fresh independent
authority/source/security and final evidence reviews are Green. The single frozen
full passed3,551 tests, preserving every predecessor identity plus38 additions.
The independently approved one-file guard supplement passed all seven cases;
D5H-E1 is closed. Root accepts this child locally with zero open P1/P2/P3.
Canonical integration, parent D and BlackOps1.3 release remain incomplete.

## Changed Files

Root-owned spec116/D165, Packet/Report, index/D154/TODO and rotating STATE.
The two Luna lanes delivered the Packet's exact nine Product/Test files:

- `src/Internal/Audit/Retention/RetentionHoldAuditWriter.php`
- `src/Internal/Retention/AuditedRetentionHoldPort.php`
- `tests/Internal/Audit/Retention/RetentionHoldAuditWriterTest.php`
- `tests/Transport/PostgreSql/PostgreSqlAuditedRetentionHoldPortTest.php`
- `tests/Architecture/RetentionHoldAuditArchitectureTest.php`
- `tests/Architecture/AuditRecordArchitectureTest.php`
- `tests/Architecture/AuditDeliveryArchitectureTest.php`
- `tests/Consumer/framework-package-export.sh`
- `tests/Consumer/framework-package-required-inventory.sh`

## Decisions and Assumptions

Two Internal types avoid a new Transport→Internal edge or native public helper
API. The adapter privately owns the native Store with exact Connection/schema;
one bounded persisted-subject SELECT is explicit. The D4B identity-helper dependency
is added to the D5H DAG rather than silently consuming an unlisted sibling.
Purge and hold locking/recovery changes are outside this child.

## Commands and Results

### Current candidate and gate evidence

All current receipts below are under `/tmp/blackops-d5h-20260924`; each `run-*`
directory records literal argv/cwd, owner, timestamps, monotonic command seconds,
before/after input maps, exit/status and `output.log` hash. PHPUnit runs also
retain their JUnit and hash. Environment is PHP8.5.7/PHPUnit13.2.2/Mago1.42.0,
shared512M `phpunit.xml`, same Compose PostgreSQL service and the dedicated native
fixture schema. Initial image IDs are recorded in `environment.json`.

| Gate | Result / command seconds | Receipt |
| --- | --- | --- |
| Writer unit | PASS8/39;1.347s | run-20260924T101406334608-unit |
| Native PostgreSQL final adapter | PASS23/405;6.815s | run-20260924T224824242241-native |
| Hold architecture before full | PASS7/1,389;34.685s | run-20260924T224748626420-architecture |
| A2 / C3 iteration | PASS16/26,128;12.850s and9/2,769,423;69.146s | run-20260924T213655315313-a2 / run-20260924T213708963037-c3 |
| Lint / strict baseline | PASS;0.899s/0.863s;186 existing filtered issues, baseline unchanged | run-20260924T224831315215-lint / run-20260924T224832478668-lint-baseline |
| Analysis | PASS;1.426s | run-20260924T224833609372-analyze |
| Deptrac | PASS7,064 allowed, zero violation/uncovered/warning/error;1.732s | run-20260924T224835312775-deptrac |
| Whole PHP format / comment identifier scan | PASS;1.201s/0.030s | run-20260924T224837316407-format / run-20260924T224838785816-identifiers |
| Package inventory and rejection controls | PASS109 unique ordered paths/60 controls;2.072s | run-20260924T094255982209-package-inventory |
| Package shell syntax | PASS, reused same two script hashes | writer-package-shell.log |
| Collection and freeze | PASS3,551 identities=3,513 predecessor+38 exact additions | run-20260924T224839088767-collection / run-20260924T224949034584-freeze |
| Frozen full | PASS3,551/7,307,719;1,124.949s;133MiB | run-20260924T225229432878-full |
| Final hold-guard supplement | PASS7/1,399;35.027s | run-20260924T232115801259-architecture |
| Supplement target format / whole format | PASS, no formatting change;0.957s/1.173s | run-20260924T232114569151-format-guard / run-20260924T232151903909-format |
| Supplement identifier scan / diff | PASS;0.015s/0.130s | run-20260924T232153342709-identifiers / run-20260924T232153625255-diff |

The unit gate pins the final writer. The A2/C3 passes predate only the bounded
adapter return-shape correction and the independent hold-guard correction; they
are iteration evidence, and the full supplies final exact-candidate coverage.
During the package command only the unrelated C3 test changed; both shell inputs
remain exact. No repeated package/syntax run is required for later PHP changes.
The38 additions are8 writer,23 native and7 hold-architecture identities in
`expected-added-identities.json`. Collection compares exact identities after
normalizing JUnit dotted class names to PHPUnit's namespace representation.
The initial freeze helper missed that representation difference and stopped before
any full execution; its failed receipt is retained. Correcting that evidence-only
helper changed no Product/Test input and did not rerun successful gates.

The full ran once, from22:52:29 to23:11:38+09:00; command monotonic time is
1,124.949361s and PHPUnit reports18:43.171. It passed with1 deprecation,
2 PHPUnit deprecations and13 notices, matching the predecessor's aggregate
counts. This is not warning-free evidence or proof of identical individual issue
origins. Full JUnit SHA256:
`f578609e768e12f9e884f87c04f1af0bf437faa1883182269171512d99fc2c85`.
Raw JUnit contains3,551 unique cases, zero failures/errors/skips, all3,513 baseline
identities and exactly the38 expected additions. `full-evidence.json` SHA256
`b58838c14cc1a9dc8e35a4f1ed2b6f4fbe65ede52bc581770f1854fe07ef567b`
confirms all9/1,693/2,196 frozen input maps equal full before/after/live maps before
the supplement was applied. Long-running architecture cases remained active;
read-only process inspection did not trigger a restart. Container `ps` was absent,
and a sandboxed host probe lacked process visibility; direct host `ps` and Docker
state inspection confirmed the running PHP process. These were monitoring results,
not failed Product checks. Approval waiting time is excluded from command durations.

Fresh source review `source-security-review-frozen.md` is Green, P1/P2/P3=0,
SHA256`dacdfa98f985aacaa1f724cdc08a7f55efbbebe0e455db724bc9c6c091342ea6`.
The frozen manifest pins9 candidate files,1,693 runtime inputs and2,196 broader
Git-visible non-management inputs; SHA256
`8cd469829594a832a8276b5fab49d2abdade7d9ecb9ffc9e4483461f46245a80`.
`scope-frozen.json` confirms193 public source/asset files,421 generated artifacts,
Release Authority and unrelated resumed work remain unchanged. The broader map
adds bootstrap/example/script/documentation inputs without invalidating the
scope-specific meaning of earlier focused receipts.

Root corrected oracle bookkeeping without relaxing the literal allowlists:
constructor defaults versus method bodies, PropertyFetch receiver resolution,
fixed exception construction counts and unique mutation anchors. The final guard
rejects extra DML/helper construction, alias/case/qualified/dynamic references,
activation, constructor work and wrong ownership. Product lint corrections removed
an unused pragma, reduced the draft's redundant Catalog parameter, and documented
the adapter's fixed Port/privacy-hook method count locally. The final analyzer
fix returns four validated object references explicitly; exact snapshot validation
and same-Connection identity remain intact. No global baseline/config change.

### Explicit dependency review for D5H-E1

Independent evidence review confirmed the current Production graph, but found that
the adapter guard inventories instance calls and construction while free/static
calls are checked only in its constructor. This P2 is a test-evidence gap, not a
Production defect. Root and the reviewer agreed to let the frozen full finish,
then supplement only `tests/Architecture/RetentionHoldAuditArchitectureTest.php`
with per-method FuncCall/StaticCall inventories and bounded syntax-valid negatives.
The worker prepared the copy in `/tmp` without touching the running full's inputs.
Root and reviewer caught literal suffix/multiplicity mismatches in that prospective
copy before execution; the corrected copy received source Green before integration.

The original full manifest/JUnit/before/after maps remain immutable. The final
guard receives all seven focused cases plus format/identifier/diff checks; a
separate final map must show only that guard changed and the same38 added test
identities. All other Product/Test/bootstrap/config/dependency inputs remain exact.
The reviewer explicitly approved reuse of the original full/native/static evidence
within this boundary under the Packet/spec109 test-only correction rule. Wider
changes or Product failures would reopen affected validation.

The final guard SHA256 is
`cdf1cae2c33ceefcfa8de5704b7abbe09b00a59beb5e16795aba938952298b4a`,
replacing only the full's guard hash
`a07232bc9c5299fef8eb23434aedbf836e441a1e555f5e911aa2db86c566f78f`.
It retains the same seven identities and adds exact per-method function/static
inventories plus three bounded negatives and a case-insensitive positive. Its
7/1,399 focused run has no failures/errors/skips or issue notices. JUnit SHA256:
`49f02fdd02eae76b833f68845b4facc2110da4fcf2f0a569474d1083b036b881`.
Target formatting changed no bytes; whole format/identifier/diff checks passed.

`supplement-evidence.json` SHA256
`de0d941617e1fef8016df6f603e29828d362fc6dfe9ecb826c200859004d2c18`
passes all20 checks. `final-candidate.json` SHA256
`fded91f9bc202bcd5f5bffde206af0a473581a6c30b18d0fb4a48100afee046d`
pins the final9/1,693/2,196 inputs: only this test differs from the full; Product,
all other tests, bootstrap/config/locks and mapped tooling inputs remain exact.
The original full artifacts and its passing pre-supplement comparison are preserved.
This evidence combines the original full with the focused supplement; it does not
claim that the full ran the final guard bytes. No second full/native/static run.

Independent `final-evidence-review.md` (SHA256
`cc6ecb8893c02bc86605146779e22f657c20148d2f3d65357f29d27ce5e8d854`)
verifies the raw full/supplement results,
all input/identity comparisons and preservation maps. It closes E1 and marks all
eight Packet criteria Green, zero open P1/P2/P3. Root reviewed the exact final
Product/test delta and accepts this evidence boundary under spec109.

### Resumption on 2026-09-24

The original `/tmp/blackops-d5h-20260921` directory is absent. Its results below
are historical records; missing logs/input maps are not reused as current passing
evidence. Current work uses `/tmp/blackops-d5h-20260924`. A fresh preservation
baseline pins3,265 Git-visible files/189 dirty entries at the unchanged HEAD:
`resume-baseline.json` SHA256
`7a1af2637a92ffd0aab5cd6ac159374b2ff7be6f0e589033858f43327d2371d6`.
This is a resumption baseline, not a reconstruction of the lost original map.

The surviving D4B full JUnit was copied before new runs. Its SHA256 matches the
Accepted D4B Report exactly (`9bef228c3afa2657ea07aa78ce1447eb244fbbc5623c9f6e2a21992d07bfd082`)
and contains3,513 distinct identities. Surviving D5H focused JUnit files are also
preserved in `recovered/`. The421 existing generated artifacts have a fresh
preservation map; no generation/build is performed.

Root rechecked repository instructions/profiles, authority, Task/Report, Git and
STATE. STATE check/write/check passed. Initial subprocess Docker environment
inspection was socket-denied; authorized environment probes then confirmed
PHP8.5.7, PHPUnit13.2.2, Mago1.42.0 and pinned app/PostgreSQL image IDs in
`environment.json`. The version probes do not count as Product verification.
Root owns serial PHP/Docker/DB/cache validation; both Luna lanes retain their
original exact file ownership.

Fresh independent authority/source review found one P2 timing issue: invalid
persisted tenant values accepted by TenantRef were rejected by AuditTypedReference
only after the writer clock capture. It is now closed: the writer validates the
bounded typed reference before the clock, and native regressions cover a real
persisted embedded-control value plus injected NFC/byte-bound hydration failures.
This implements the existing frozen contract without broadening the adapter graph
or public API. Final full/supplement and evidence Acceptance are recorded above.

### Historical iteration before resumption

- Live AGENTS/STATE/completed D4B Packet and latest evidence, profiles, targeted
  spec index/D154/spec38/39/109 and source API review. Existing D5 root survey reused.
- D4B's17 accepted candidate hashes and1,971 runtime inputs still match before work.
- New baseline3,253 Git-visible files/165 dirty entries at unchanged
  HEAD8106c3556248dca1f9719b9d55cf1f647255a422; baseline SHA256
  be71b81b8631c9c31db8b5ff6784e0f90135df2bbb00b64182ad6de0a952d675.
  Evidence root: /tmp/blackops-d5h-20260921. Prior full JUnit/review/closeout and
  33-source retention survey were copied for exact input/evidence comparison.
- Feasibility review confirms the two-type shape fits the existing layer rules;
  design-feasibility.md SHA256
  461082df38dab7e5841a30fcf227ef4d7605b7d60d0fcb6cad2a5f2d33350884.
- Independent authority Green, open P1/P2/P3=0: authority-review.md SHA256
  6d1d8003b99b0375e5bac05a647259f9536907ea9bfb3498835847749660e8a3;
  input map SHA256357c1fad229f1c1ae488e36bfc986ce0e2d4df95b3d2180a0c238eb8abefb921.
  Native clock/ID ownership and supporting-constructor wording findings are closed.
  Exact reviewed copies and the explicitly permitted status-only promotion diff
  are retained in authority-reviewed/, authority-status-only.diff and
  authority-closeout.json.
- Preparation scope-20260921T235000.json: zero foreign edits;193 public sources,
  421 generated artifacts and release authority unchanged. git diff --check passed.
  STATE check/write/check passed at Task start and implementation start; old bodies
  were automatically archived. Prior full JUnit contains3,513 unique identities.
- Two Luna lanes delivered exactly nine Product/Test files. Scope receipt
  scope-20260923T195142.json confirms no missing candidate or foreign changes;
  public sources/artifacts and release authority remain exact. Root owns all
  PHP/Docker/DB/cache commands, per-command input hashes and monotonic time.
- Worker package shell syntax and inventory passed:109 ordered unique paths,
  60 rejected controls (46 inventory/14 call-contract). Literal argv, elapsed
  0.00s/2.12s and hashes are in writer-handoff.md and writer-package-shell.log.
  These successful unchanged inputs are reused rather than rerun by Root.
- First unit attempt run-20260922T001224340947 was Docker-socket denied before
  PHP execution (0.140s). Authorized rerun run-20260923T195114033399 passed
  7 tests/36 assertions (1.594s), with six new mock-fixture notices being corrected.
  Runtime PHP8.5.7/PHPUnit13.2.2, shared512M. Root formatting completed in
  run-20260923T195350227498 (1.385s; six files formatted).
- Focused architecture run-20260923T195502815739: A2 passed16 tests/26,128
  assertions (12.155s). New hold guard had two evidence-construction false positives;
  C3 had writer-signature/call-inventory expectation failures. Their bounded oracle
  corrections and missing adapter/recursive-source negatives remain in progress.
- Native run-20260923T195753231024:22 tests/240 assertions, one error, one failure
  and one warning (6.925s). The observation fixture inverted auto-commit admission;
  its privacy assertion exported external PHPUnit frames instead of only owned
  frames. Both test defects and additional nonvacuous timestamp/key/absence/privacy
  checks are being corrected. No full suite has run.
- Root caught and the worker corrected the native cleanup loop before execution:
  DBAL starts a replacement transaction on rollback when auto-commit is disabled.
  Source review also closed writer plan-order, typed-ID revalidation and real-canary
  findings. Current Production source matches authority; final guard/fixture review
  and exact hashes remain pending.

## Acceptance Criteria

Root Accepted (local), with independent authority/source/security/evidence Green
and zero open P1/P2/P3. All eight Packet criteria are satisfied:

| Criterion | Evidence and boundary |
| --- | --- |
| 1. Projection and trusted identity | Reviewed writer plus unit/native event assertions pin Catalog, actor/execution/correlation, persisted tenant/global identity, policy/evidence versions and HMAC inputs. |
| 2. Before-work rejection and privacy | Clock/ID/SQL observations, malformed typed-subject and owned-frame canaries pass; real persisted control-character regression validates before writer clock capture. |
| 3. Connection and root ownership | Exact graph review and native two-Connection/ambient/nested/non-autocommit checks pass. C3 dependency composition remains the trusted caller's obligation. |
| 4. Real durable commit and persisted subject | PostgreSQL probe reads verify hold plus ingress, global/tenant/Inline-Journal subjects and release after source deletion. |
| 5. Failure and repeat behavior | Confirmed pre-commit paths roll back; actual committed-then-throw rows remain without blind retry. Controlled faults do not establish network/process-kill recovery or exactly-once guarantees. |
| 6. Native behavior and activation boundary | activeFor/native contract, privacy and recursive activation negatives pass; no authorization, stronger purge locking or canonical activation is added. |
| 7. Graph and package closure | A2/C3 final coverage comes from the full. The final seven-case hold guard closes E1's parser-call boundary; package109 ordered unique paths/60 rejected controls pass. This is a bounded AST guard, not universal PHP static analysis. |
| 8. Independent review and preservation | One full retains3,513 predecessor cases plus38 exact additions; the approved one-file supplement preserves identities and every other frozen input. Independent final evidence is Green. |

Completion changes only management status/pointers and this Report after the final
candidate. Product validation remains valid; final scope/diff/STATE checks confirm
the closeout, with receipts in `closeout.json` and the scoped management delta in
`acceptance-management.diff`. HEAD stays8106c3556248dca1f9719b9d55cf1f647255a422. No stage, commit,
push, tag, remote CI, package export or deployment was performed.

## Release Documentation Impact

Experimental Stable1.2.1 Framework/Skeleton/site and all public capabilities remain.
The unchanged Release Authority tuple is:

- Framework tag1.2.1, directRef1c72fced890c7f4cfc2cf88a4d2b08dbcfc85dd3,
  peeledSource4efee09f13bebedc1639333f79550a36a4e8ca91.
- Skeleton tag1.2.1, directRef7a47e344ecba6193628119f0e95cb23dc5e0be83,
  peeledSource87a025380df8abcd0c514abf50e139dbbc1953c7.
- Roadmap1.3.0 remains unreleased; internal proposed
  audit.retention_hold_delivery.v1 only.

No public source or route is affected. All193 documentation source/asset files,
421 generated artifacts and six historical references are unchanged inputs;193 is
the monitored file count, not a route count. Final preservation checks confirm exact hashes.
Old-version occurrences and historical allowlist stay unchanged; existing public
positive/negative fixture sources and generated Search/LLM artifacts are preserved,
not newly executed or regenerated in this Task.
Spec116 is internal architecture documentation;
public guide/internal site updates await canonical composition. No docs build,
browser/local final review, same-SHA CI/export or deployment performed.

## Remaining Issues

残り工程: D5H内はなし。上位GoalにはD5 purge/rotation/replay/generic
Outbox, ordinary integration, E/F/G, D-control after Accepted F, hardening/docs/release.
Inherited Inline commit-ambiguity reconciliation remains outside this child and
required before release. User must review the final local documentation candidate.

## Suggested Next Action

Next Action: Root prepares the separate D5 purge authority/Task and obtains
independent authority review before scoped Luna implementation. Preserve the
Accepted hold-only boundary, existing dirty work and the pending integration,
local documentation user review and release gates.

## Subsequent integration boundary

The original local verification and attribution above remain unchanged.
P23-006C owns the subsequent isolated integration in the reviewed D3+D4A+D4B+D5H unit, its separate commits and final convergence checks.
See [the integration Report](P23-006C-accepted-audit-integration.md).
No historical missing intermediate or new same-SHA full is implied.
